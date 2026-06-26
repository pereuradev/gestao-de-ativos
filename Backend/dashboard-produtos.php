<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (empty($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "message" => "Sessao expirada. Faca login novamente.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function responderJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function consultarValor(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function consultarLinhas(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function normalizarPeriodo(mixed $periodo): int
{
    $periodo = (int)$periodo;
    $periodosPermitidos = [7, 30, 90];

    return in_array($periodo, $periodosPermitidos, true) ? $periodo : 30;
}

function montarFiltroCategoria(string $categoriaId): array
{
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
    if ($total <= 0) {
        return 0.0;
    }

    return round(($valor / $total) * 100, 1);
}

try {
    require __DIR__ . "/Conexao.php";

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException("Conexao PDO indisponivel.");
    }

    $categoriaId = trim((string)($_GET["categoria_id"] ?? "todos"));
    $periodo = normalizarPeriodo($_GET["periodo"] ?? 30);

    [$whereCategoria, $paramsCategoria] = montarFiltroCategoria($categoriaId);

    $totalAtivos = consultarValor($pdo, "select count(*) from public.ativos");
    $totalTipos = consultarValor($pdo, "select count(*) from public.categorias_ativos");

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

    foreach ($categorias as &$categoria) {
        $categoria["total"] = (int)$categoria["total"];
        $categoria["percentual"] = calcularPercentual((int)$categoria["total"], $totalAtivos);
    }
    unset($categoria);

    $totalSelecionado = consultarValor($pdo, "select count(*) from public.ativos a {$whereCategoria}", $paramsCategoria);

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

    $status = consultarLinhas($pdo, "
        select
            coalesce(nullif(trim(a.status), ''), 'Sem status') as nome,
            count(*)::int as total
        from public.ativos a
        {$whereCategoria}
        group by coalesce(nullif(trim(a.status), ''), 'Sem status')
        order by total desc, nome asc
    ", $paramsCategoria);

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

    $evolucaoParams = $paramsCategoria;
    $evolucaoParams[":periodo"] = $periodo;

    $filtroEvolucao = $whereCategoria;

    if ($filtroEvolucao === "") {
        $filtroEvolucao = " where a.criado_em::date = d.dia ";
    } else {
        $filtroEvolucao .= " and a.criado_em::date = d.dia ";
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
        left join public.ativos a on true
        {$filtroEvolucao}
        group by d.dia
        order by d.dia
    ", $evolucaoParams);

    $normalizarLista = static function (array $lista, int $baseTotal): array {
        return array_map(static function (array $item) use ($baseTotal): array {
            $total = (int)($item["total"] ?? 0);

            return [
                "nome" => (string)($item["nome"] ?? "Sem nome"),
                "total" => $total,
                "percentual" => calcularPercentual($total, $baseTotal),
            ];
        }, $lista);
    };

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
        "marcas" => $normalizarLista($marcas, $totalSelecionado),
        "locais" => $normalizarLista($locais, $totalSelecionado),
        "evolucao" => $normalizarLista($evolucao, max(1, array_sum(array_map(static fn ($item) => (int)$item["total"], $evolucao)))),
    ]);
} catch (Throwable $erro) {
    responderJson([
        "ok" => false,
        "message" => "Nao foi possivel carregar o dashboard de produtos.",
    ], 500);
}
