<?php
declare(strict_types=1);

// Endpoint JSON usado pelo dashboard.php. Ele concentra todas as consultas do painel
// para a tela nao precisar fazer varias chamadas pequenas ao banco.
session_start();

// O navegador precisa receber JSON e nao deve guardar cache desses numeros.
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Se a sessao expirou, o JavaScript redireciona o usuario para o login.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Sessao expirada. Faca login novamente.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function responderJson(array $payload, int $statusCode = 200): void
{
    // Centraliza o formato da resposta para sucesso e erro sairem iguais.
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function consultarValor(PDO $pdo, string $sql, array $params = []): int
{
    // Usado para consultas de contagem, onde esperamos apenas um numero.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function consultarLinhas(PDO $pdo, string $sql, array $params = []): array
{
    // Usado para rankings e series do grafico, retornando varias linhas.
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function normalizarPeriodo(mixed $periodo): int
{
    // Aceitamos apenas periodos conhecidos para evitar consultas inesperadas.
    $periodo = (int)$periodo;
    $periodosPermitidos = [7, 30, 90];

    return in_array($periodo, $periodosPermitidos, true) ? $periodo : 30;
}

function montarFiltroCategoria(string $categoriaId): array
{
    // Retorna o trecho WHERE e os parametros correspondentes.
    // Isso deixa todas as consultas usando o mesmo filtro de categoria.
    $categoriaId = trim($categoriaId);

    if ($categoriaId === "" || $categoriaId === "todos") {
        return ["", []];
    }

    if ($categoriaId === "sem-categoria") {
        return [" where a.categoria_id is null ", []];
    }

    return [" where a.categoria_id::text = :categoria_id ", [":categoria_id" => $categoriaId]];
}

function calcularPercentual(int $valor, int $total): float
{
    // Evita divisao por zero quando o filtro nao encontra dados.
    if ($total <= 0) {
        return 0.0;
    }

    return round(($valor / $total) * 100, 1);
}

function agruparLinhasPorCategoria(array $linhas, array $totaisPorCategoria): array
{
    // Monta mapas como marcas_por_categoria e status_por_categoria.
    // O frontend usa esses mapas para trocar o filtro sem esperar nova requisicao.
    $grupos = [];

    foreach ($linhas as $linha) {
        $categoriaId = (string)($linha["categoria_id"] ?? "sem-categoria");
        $total = (int)($linha["total"] ?? 0);
        $baseTotal = (int)($totaisPorCategoria[$categoriaId] ?? 0);

        $grupos[$categoriaId][] = [
            "nome" => (string)($linha["nome"] ?? "Sem nome"),
            "total" => $total,
            "percentual" => calcularPercentual($total, $baseTotal),
        ];
    }

    return $grupos;
}

try {
    // Abre a conexao ja configurada em Backend/Conexao.php.
    require __DIR__ . "/Conexao.php";

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException("Conexao PDO indisponivel.");
    }

    $categoriaId = trim((string)($_GET["categoria_id"] ?? "todos"));
    $periodo = normalizarPeriodo($_GET["periodo"] ?? 30);

    [$whereCategoria, $paramsCategoria] = montarFiltroCategoria($categoriaId);

    // Numeros gerais que alimentam os cards principais do dashboard.
    $totalAtivos = consultarValor($pdo, "select count(*) from public.ativos");
    $totalTipos = consultarValor($pdo, "select count(*) from public.categorias_ativos");

    // Lista todos os tipos de produto, incluindo ativos sem categoria.
    $categorias = consultarLinhas($pdo, "
        select id, nome, total
        from (
            select
                c.id::text as id,
                coalesce(nullif(trim(c.nome), ''), 'Sem nome') as nome,
                count(a.id)::int as total
            from public.categorias_ativos c
            left join public.ativos a on a.categoria_id = c.id
            group by c.id, c.nome

            union all

            select
                'sem-categoria' as id,
                'Sem categoria' as nome,
                count(a.id)::int as total
            from public.ativos a
            where a.categoria_id is null
            having count(a.id) > 0
        ) dados
        order by total desc, nome asc
    ");

    $totaisPorCategoria = [];

    // Acrescenta percentual em cada categoria e cria um indice para calculos posteriores.
    foreach ($categorias as &$categoria) {
        $categoria["total"] = (int)$categoria["total"];
        $categoria["percentual"] = calcularPercentual((int)$categoria["total"], $totalAtivos);
        $totaisPorCategoria[(string)$categoria["id"]] = (int)$categoria["total"];
    }
    unset($categoria);

    $totalSelecionado = consultarValor($pdo, "select count(*) from public.ativos a {$whereCategoria}", $paramsCategoria);

    // Por padrao a tela esta em "Todos"; se vier um tipo especifico, substituimos abaixo.
    $categoriaSelecionada = [
        "id" => "todos",
        "nome" => "Todos os tipos",
        "total" => $totalSelecionado,
        "percentual" => calcularPercentual($totalSelecionado, $totalAtivos),
    ];

    if ($categoriaId !== "" && $categoriaId !== "todos") {
        foreach ($categorias as $categoria) {
            if ((string)$categoria["id"] === $categoriaId) {
                $categoriaSelecionada = [
                    "id" => (string)$categoria["id"],
                    "nome" => (string)$categoria["nome"],
                    "total" => (int)$categoria["total"],
                    "percentual" => (float)$categoria["percentual"],
                ];
                break;
            }
        }
    }

    $maiorCategoria = $categorias[0] ?? null;

    // Agrupamento por status respeitando o filtro atual.
    $status = consultarLinhas($pdo, "
        select
            coalesce(nullif(trim(a.status), ''), 'Sem status') as nome,
            count(*)::int as total
        from public.ativos a
        {$whereCategoria}
        group by coalesce(nullif(trim(a.status), ''), 'Sem status')
        order by total desc, nome asc
    ", $paramsCategoria);

    // Mesmo agrupamento, mas separado por categoria para troca instantanea no frontend.
    $statusPorCategoria = consultarLinhas($pdo, "
        select
            coalesce(a.categoria_id::text, 'sem-categoria') as categoria_id,
            coalesce(nullif(trim(a.status), ''), 'Sem status') as nome,
            count(*)::int as total
        from public.ativos a
        group by
            coalesce(a.categoria_id::text, 'sem-categoria'),
            coalesce(nullif(trim(a.status), ''), 'Sem status')
        order by categoria_id asc, total desc, nome asc
    ");

    // Ranking de marcas do filtro atual, limitado para manter a leitura simples.
    $marcas = consultarLinhas($pdo, "
        select
            coalesce(nullif(trim(a.marca), ''), 'Sem marca') as nome,
            count(*)::int as total
        from public.ativos a
        {$whereCategoria}
        group by coalesce(nullif(trim(a.marca), ''), 'Sem marca')
        order by total desc, nome asc
        limit 12
    ", $paramsCategoria);

    // Ranking de marcas pre-carregado por categoria.
    $marcasPorCategoria = consultarLinhas($pdo, "
        with marcas as (
            select
                coalesce(a.categoria_id::text, 'sem-categoria') as categoria_id,
                coalesce(nullif(trim(a.marca), ''), 'Sem marca') as nome,
                count(*)::int as total
            from public.ativos a
            group by
                coalesce(a.categoria_id::text, 'sem-categoria'),
                coalesce(nullif(trim(a.marca), ''), 'Sem marca')
        ),
        ordenadas as (
            select
                categoria_id,
                nome,
                total,
                row_number() over (
                    partition by categoria_id
                    order by total desc, nome asc
                ) as posicao
            from marcas
        )
        select categoria_id, nome, total
        from ordenadas
        where posicao <= 12
        order by categoria_id asc, posicao asc
    ");

    // Ranking de locais do filtro atual.
    $locais = consultarLinhas($pdo, "
        select
            coalesce(nullif(trim(l.nome), ''), 'Sem localização') as nome,
            count(a.id)::int as total
        from public.ativos a
        left join public.locais l on l.id = a.local_id
        {$whereCategoria}
        group by coalesce(nullif(trim(l.nome), ''), 'Sem localização')
        order by total desc, nome asc
        limit 12
    ", $paramsCategoria);

    // Ranking de locais pre-carregado por categoria.
    $locaisPorCategoria = consultarLinhas($pdo, "
        with locais as (
            select
                coalesce(a.categoria_id::text, 'sem-categoria') as categoria_id,
                coalesce(nullif(trim(l.nome), ''), 'Sem localizacao') as nome,
                count(a.id)::int as total
            from public.ativos a
            left join public.locais l on l.id = a.local_id
            group by
                coalesce(a.categoria_id::text, 'sem-categoria'),
                coalesce(nullif(trim(l.nome), ''), 'Sem localizacao')
        ),
        ordenados as (
            select
                categoria_id,
                nome,
                total,
                row_number() over (
                    partition by categoria_id
                    order by total desc, nome asc
                ) as posicao
            from locais
        )
        select categoria_id, nome, total
        from ordenados
        where posicao <= 12
        order by categoria_id asc, posicao asc
    ");

    $evolucaoParams = $paramsCategoria;
    $evolucaoParams[":periodo"] = $periodo;

    // A evolucao precisa manter todos os dias do periodo, mesmo quando o total do dia e zero.
    $joinEvolucao = "a.criado_em::date = d.dia";

    if ($categoriaId === "sem-categoria") {
        $joinEvolucao .= " and a.categoria_id is null";
    } elseif ($categoriaId !== "" && $categoriaId !== "todos") {
        $joinEvolucao .= " and a.categoria_id::text = :categoria_id";
    }

    $evolucao = consultarLinhas($pdo, "
        with dias as (
            select generate_series(
                (current_date - ((:periodo - 1) * interval '1 day'))::date,
                current_date::date,
                interval '1 day'
            )::date as dia
        )
        select
            to_char(d.dia, 'DD/MM') as nome,
            count(a.id)::int as total
        from dias d
        left join public.ativos a on {$joinEvolucao}
        group by d.dia
        order by d.dia
    ", $evolucaoParams);

    $normalizarLista = static function (array $lista, int $baseTotal): array {
        // Padroniza nome, total e percentual antes de enviar para o JavaScript.
        return array_map(static function (array $item) use ($baseTotal): array {
            $total = (int)($item["total"] ?? 0);

            return [
                "nome" => (string)($item["nome"] ?? "Sem nome"),
                "total" => $total,
                "percentual" => calcularPercentual($total, $baseTotal),
            ];
        }, $lista);
    };

    // Resposta unica que alimenta cards, filtros, grafico, ranking e tabela.
    responderJson([
        "ok" => true,
        "gerado_em" => date("c"),
        "periodo" => $periodo,
        "categoria_filtro" => $categoriaId,
        "resumo" => [
            "total_ativos" => $totalAtivos,
            "total_tipos" => $totalTipos,
            "total_filtrado" => $totalSelecionado,
            "maior_categoria" => $maiorCategoria ? [
                "id" => (string)$maiorCategoria["id"],
                "nome" => (string)$maiorCategoria["nome"],
                "total" => (int)$maiorCategoria["total"],
                "percentual" => (float)$maiorCategoria["percentual"],
            ] : null,
        ],
        "categoria_selecionada" => $categoriaSelecionada,
        "categorias" => $categorias,
        "status" => $normalizarLista($status, $totalSelecionado),
        "status_por_categoria" => agruparLinhasPorCategoria($statusPorCategoria, $totaisPorCategoria),
        "marcas" => $normalizarLista($marcas, $totalSelecionado),
        "marcas_por_categoria" => agruparLinhasPorCategoria($marcasPorCategoria, $totaisPorCategoria),
        "locais" => $normalizarLista($locais, $totalSelecionado),
        "locais_por_categoria" => agruparLinhasPorCategoria($locaisPorCategoria, $totaisPorCategoria),
        "evolucao" => $normalizarLista($evolucao, max(1, array_sum(array_map(static fn ($item) => (int)$item["total"], $evolucao)))),
    ]);
} catch (Throwable $erro) {
    responderJson([
        "ok" => false,
        "message" => "Nao foi possivel carregar o dashboard de produtos.",
    ], 500);
}
