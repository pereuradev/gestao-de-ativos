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

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("visualizar_dashboard", "Dashboard");

$inicioProcessamento = microtime(true);

function responderJson(array $dadosResposta, int $codigoStatusHttp = 200): void
{
    // Centraliza o formato da resposta para sucesso e erro sairem iguais.
    http_response_code($codigoStatusHttp);
    echo json_encode($dadosResposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function consultarLinhas(PDO $pdo, string $sql, array $parametrosConsulta = []): array
{
    // Usado para rankings e series do grafico, retornando varias linhas.
    $consultaPreparada = $pdo->prepare($sql);

    foreach ($parametrosConsulta as $chaveItem => $valorEntrada) {
        $consultaPreparada->bindValue($chaveItem, $valorEntrada);
    }

    $consultaPreparada->execute();

    return $consultaPreparada->fetchAll(PDO::FETCH_ASSOC);
}

function calcularPercentual(int $valor, int $total): float
{
    // Evita divisao por zero quando o filtro nao encontra dados.
    if ($total <= 0) {
        return 0.0;
    }

    return round(($valor / $total) * 100, 1);
}

function decodificarListaPainel(mixed $valor): array
{
    if (is_array($valor)) {
        return $valor;
    }

    $lista = json_decode((string)$valor, true);

    return is_array($lista) ? $lista : [];
}

try {
    // Abre a conexao ja configurada em Backend/Conexao.php.
    require __DIR__ . "/Conexao.php";

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException("Conexao PDO indisponivel.");
    }

    $categoriaId = trim((string)($_GET["categoria_id"] ?? "todos"));
    $marcaFiltro = trim((string)($_GET["marca"] ?? "todos"));
    $localId = trim((string)($_GET["local_id"] ?? "todos"));
    $categoriaId = $categoriaId !== "" ? $categoriaId : "todos";
    $marcaFiltro = $marcaFiltro !== "" ? $marcaFiltro : "todos";
    $localId = $localId !== "" ? $localId : "todos";

    $filtroCategoriaSql = "(
        f.categoria_id = 'todos'
        or (f.categoria_id = 'sem-categoria' and a.categoria_id is null)
        or (f.categoria_id not in ('todos', 'sem-categoria') and a.categoria_id::text = f.categoria_id)
    )";
    $filtroMarcaSql = "(
        f.marca = 'todos'
        or (f.marca = 'sem-marca' and nullif(trim(a.marca), '') is null)
        or (f.marca not in ('todos', 'sem-marca') and lower(trim(a.marca)) = lower(f.marca))
    )";
    $filtroLocalSql = "(
        f.local_id = 'todos'
        or (f.local_id = 'sem-localizacao' and a.local_id is null)
        or (f.local_id not in ('todos', 'sem-localizacao') and a.local_id::text = f.local_id)
    )";

    // Todas as secoes sao agregadas em uma unica consulta para evitar varias viagens ao banco remoto.
    $dadosConsulta = consultarLinhas($pdo, "
        with filtros as (
            select
                cast(:categoria_id as text) as categoria_id,
                cast(:marca as text) as marca,
                cast(:local_id as text) as local_id
        )
        select
            (select count(*)::int from public.ativos) as total_ativos,
            (select count(*)::int from public.categorias_ativos) as total_tipos,
            (
                select count(*)::int
                from public.ativos a
                cross join filtros f
                where {$filtroCategoriaSql} and {$filtroMarcaSql} and {$filtroLocalSql}
            ) as total_selecionado,
            coalesce((
                select jsonb_agg(to_jsonb(lista_categorias))
                from (
                    select
                        coalesce(c.id::text, 'sem-categoria') as id,
                        coalesce(nullif(trim(c.nome), ''), 'Sem categoria') as nome,
                        count(a.id)::int as total
                    from public.ativos a
                    left join public.categorias_ativos c on c.id = a.categoria_id
                    cross join filtros f
                    where {$filtroMarcaSql} and {$filtroLocalSql}
                    group by coalesce(c.id::text, 'sem-categoria'), coalesce(nullif(trim(c.nome), ''), 'Sem categoria')
                    order by total desc, nome asc
                ) lista_categorias
            ), '[]'::jsonb)::text as categorias_json,
            coalesce((
                select jsonb_agg(to_jsonb(lista_status))
                from (
                    select coalesce(nullif(trim(a.status), ''), 'Sem status') as nome, count(*)::int as total
                    from public.ativos a
                    cross join filtros f
                    where {$filtroCategoriaSql} and {$filtroMarcaSql} and {$filtroLocalSql}
                    group by coalesce(nullif(trim(a.status), ''), 'Sem status')
                    order by total desc, nome asc
                ) lista_status
            ), '[]'::jsonb)::text as status_json,
            coalesce((
                select jsonb_agg(to_jsonb(lista_marcas))
                from (
                    select coalesce(nullif(trim(a.marca), ''), 'Sem marca') as nome, count(*)::int as total
                    from public.ativos a
                    cross join filtros f
                    where {$filtroCategoriaSql} and {$filtroMarcaSql} and {$filtroLocalSql}
                    group by coalesce(nullif(trim(a.marca), ''), 'Sem marca')
                    order by total desc, nome asc
                    limit 12
                ) lista_marcas
            ), '[]'::jsonb)::text as marcas_json,
            coalesce((
                select jsonb_agg(to_jsonb(lista_marcas_filtro))
                from (
                    select
                        case when nullif(trim(a.marca), '') is null then 'sem-marca' else trim(a.marca) end as id,
                        coalesce(nullif(trim(a.marca), ''), 'Sem marca') as nome,
                        count(*)::int as total
                    from public.ativos a
                    cross join filtros f
                    where {$filtroCategoriaSql} and {$filtroLocalSql}
                    group by
                        case when nullif(trim(a.marca), '') is null then 'sem-marca' else trim(a.marca) end,
                        coalesce(nullif(trim(a.marca), ''), 'Sem marca')
                    order by total desc, nome asc
                ) lista_marcas_filtro
            ), '[]'::jsonb)::text as marcas_filtro_json,
            coalesce((
                select jsonb_agg(to_jsonb(lista_locais))
                from (
                    select coalesce(nullif(trim(l.nome), ''), 'Sem localizacao') as nome, count(a.id)::int as total
                    from public.ativos a
                    left join public.locais l on l.id = a.local_id
                    cross join filtros f
                    where {$filtroCategoriaSql} and {$filtroMarcaSql} and {$filtroLocalSql}
                    group by coalesce(nullif(trim(l.nome), ''), 'Sem localizacao')
                    order by total desc, nome asc
                    limit 12
                ) lista_locais
            ), '[]'::jsonb)::text as locais_json,
            coalesce((
                select jsonb_agg(to_jsonb(lista_locais_filtro))
                from (
                    select
                        coalesce(l.id::text, 'sem-localizacao') as id,
                        coalesce(nullif(trim(l.nome), ''), 'Sem localizacao') as nome,
                        count(a.id)::int as total
                    from public.ativos a
                    left join public.locais l on l.id = a.local_id
                    cross join filtros f
                    where {$filtroCategoriaSql} and {$filtroMarcaSql}
                    group by coalesce(l.id::text, 'sem-localizacao'), coalesce(nullif(trim(l.nome), ''), 'Sem localizacao')
                    order by total desc, nome asc
                ) lista_locais_filtro
            ), '[]'::jsonb)::text as locais_filtro_json
    ", [
        ":categoria_id" => $categoriaId,
        ":marca" => $marcaFiltro,
        ":local_id" => $localId,
    ])[0] ?? [];

    $totalAtivos = (int)($dadosConsulta["total_ativos"] ?? 0);
    $totalTipos = (int)($dadosConsulta["total_tipos"] ?? 0);
    $totalSelecionado = (int)($dadosConsulta["total_selecionado"] ?? 0);
    $categorias = decodificarListaPainel($dadosConsulta["categorias_json"] ?? []);
    $status = decodificarListaPainel($dadosConsulta["status_json"] ?? []);
    $marcas = decodificarListaPainel($dadosConsulta["marcas_json"] ?? []);
    $marcasFiltro = decodificarListaPainel($dadosConsulta["marcas_filtro_json"] ?? []);
    $locais = decodificarListaPainel($dadosConsulta["locais_json"] ?? []);
    $locaisFiltro = decodificarListaPainel($dadosConsulta["locais_filtro_json"] ?? []);

    // Acrescenta percentual em cada categoria antes de enviar ao frontend.
    foreach ($categorias as &$categoria) {
        $categoria["total"] = (int)$categoria["total"];
        $categoria["percentual"] = calcularPercentual((int)$categoria["total"], $totalAtivos);
    }
    unset($categoria);

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

    $normalizarLista = static function (array $lista, int $totalBase): array {
        // Padroniza nome, total e percentual antes de enviar para o JavaScript.
        return array_map(static function (array $item) use ($totalBase): array {
            $total = (int)($item["total"] ?? 0);

            return [
                "nome" => (string)($item["nome"] ?? "Sem nome"),
                "total" => $total,
                "percentual" => calcularPercentual($total, $totalBase),
            ];
        }, $lista);
    };

    $normalizarOpcoesFiltro = static function (array $lista): array {
        return array_map(static function (array $item): array {
            return [
                "id" => (string)($item["id"] ?? $item["nome"] ?? ""),
                "nome" => (string)($item["nome"] ?? "Sem nome"),
                "total" => (int)($item["total"] ?? 0),
            ];
        }, $lista);
    };

    $duracaoMs = round((microtime(true) - $inicioProcessamento) * 1000, 1);
    header("Server-Timing: dashboard;dur={$duracaoMs}");

    // Resposta unica que alimenta cards, filtros, grafico, ranking e tabela.
    responderJson([
        "ok" => true,
        "gerado_em" => date("c"),
        "duracao_ms" => $duracaoMs,
        "categoria_filtro" => $categoriaId,
        "marca_filtro" => $marcaFiltro,
        "local_filtro" => $localId,
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
        "marcas_filtro" => $normalizarOpcoesFiltro($marcasFiltro),
        "locais_filtro" => $normalizarOpcoesFiltro($locaisFiltro),
        "status" => $normalizarLista($status, $totalSelecionado),
        "marcas" => $normalizarLista($marcas, $totalSelecionado),
        "locais" => $normalizarLista($locais, $totalSelecionado),
    ]);
} catch (Throwable $erro) {
    error_log("Falha no dashboard de produtos: " . $erro->getMessage());
    responderJson([
        "ok" => false,
        "message" => "Nao foi possivel carregar o dashboard de produtos.",
    ], 500);
}
