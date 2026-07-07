<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("visualizar_ativos", "Ativos");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responderErroExportacao(405, "Metodo nao permitido.");
}

function responderErroExportacao(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "ok" => false,
        "message" => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function filtroExportacao(string $nome, string $padrao = ""): string
{
    return trim((string) ($_GET[$nome] ?? $padrao));
}

function filtroAtivo(string $valor): bool
{
    return $valor !== "" && strtolower($valor) !== "todos";
}

function uuidExportacaoValido(string $valor): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    ) === 1;
}

function tabelaExportacaoExiste(PDO $pdo, string $tabela): bool
{
    $stmt = $pdo->prepare("
        select 1
          from information_schema.tables
         where table_schema = 'public'
           and table_name = :tabela
         limit 1
    ");
    $stmt->execute([":tabela" => $tabela]);

    return $stmt->fetchColumn() !== false;
}

function colunaExportacaoExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("
        select 1
          from information_schema.columns
         where table_schema = 'public'
           and table_name = :tabela
           and column_name = :coluna
         limit 1
    ");
    $stmt->execute([
        ":tabela" => $tabela,
        ":coluna" => $coluna,
    ]);

    return $stmt->fetchColumn() !== false;
}

function contextoResponsavelExportacao(PDO $pdo): array
{
    if (colunaExportacaoExiste($pdo, "ativos", "responsavel_id") && tabelaExportacaoExiste($pdo, "perfis_usuarios")) {
        return [
            "disponivel" => true,
            "select" => ", coalesce(nullif(resp.nome_completo, ''), resp.email, '') as responsavel",
            "join" => " left join public.perfis_usuarios resp on resp.id = a.responsavel_id",
            "campo_texto" => "coalesce(resp.nome_completo, resp.email, '')",
            "campo_id" => "a.responsavel_id::text",
        ];
    }

    if (colunaExportacaoExiste($pdo, "ativos", "responsavel")) {
        return [
            "disponivel" => true,
            "select" => ", coalesce(a.responsavel, '') as responsavel",
            "join" => "",
            "campo_texto" => "coalesce(a.responsavel, '')",
            "campo_id" => "",
        ];
    }

    return [
        "disponivel" => false,
        "select" => ", '' as responsavel",
        "join" => "",
        "campo_texto" => "",
        "campo_id" => "",
    ];
}

function formatarDataExportacao(?string $value): string
{
    if (!$value) {
        return "";
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
            ->format("d/m/Y H:i");
    } catch (Throwable) {
        return "";
    }
}

try {
    global $pdo;

    $responsavel = contextoResponsavelExportacao($pdo);
    $where = [];
    $params = [];

    $busca = filtroExportacao("busca");
    $status = filtroExportacao("status", "todos");
    $categoria = filtroExportacao("categoria", "todos");
    $categoriaId = filtroExportacao("categoria_id");
    $marca = filtroExportacao("marca", "todos");
    $localizacao = filtroExportacao("localizacao", filtroExportacao("local", "todos"));
    $localId = filtroExportacao("local_id");
    $responsavelFiltro = filtroExportacao("responsavel", "todos");
    $responsavelId = filtroExportacao("responsavel_id");

    if ($busca !== "") {
        $where[] = "(
            lower(coalesce(a.nome, '')) like lower(:busca)
            or lower(coalesce(a.descricao, '')) like lower(:busca)
            or lower(coalesce(a.numero_serie, '')) like lower(:busca)
            or lower(coalesce(a.imei, '')) like lower(:busca)
            or lower(coalesce(a.status, '')) like lower(:busca)
            or lower(coalesce(a.marca, '')) like lower(:busca)
            or lower(coalesce(a.propriedade, '')) like lower(:busca)
            or lower(coalesce(a.datasheet, '')) like lower(:busca)
            or lower(coalesce(c.nome, '')) like lower(:busca)
            or lower(coalesce(l.nome, '')) like lower(:busca)
        )";
        $params[":busca"] = "%" . $busca . "%";
    }

    if (filtroAtivo($status)) {
        $where[] = "a.status = :status";
        $params[":status"] = $status;
    }

    if ($categoriaId !== "" && uuidExportacaoValido($categoriaId)) {
        $where[] = "a.categoria_id::text = :categoria_id";
        $params[":categoria_id"] = $categoriaId;
    } elseif (filtroAtivo($categoria)) {
        $where[] = "c.nome = :categoria";
        $params[":categoria"] = $categoria;
    }

    if (filtroAtivo($marca)) {
        $where[] = "a.marca = :marca";
        $params[":marca"] = $marca;
    }

    if ($localId !== "" && uuidExportacaoValido($localId)) {
        $where[] = "a.local_id::text = :local_id";
        $params[":local_id"] = $localId;
    } elseif ($localizacao === "sem-localizacao") {
        $where[] = "a.local_id is null";
    } elseif (filtroAtivo($localizacao)) {
        $where[] = "l.nome = :localizacao";
        $params[":localizacao"] = $localizacao;
    }

    if ($responsavel["disponivel"] && $responsavelId !== "" && uuidExportacaoValido($responsavelId) && $responsavel["campo_id"] !== "") {
        $where[] = $responsavel["campo_id"] . " = :responsavel_id";
        $params[":responsavel_id"] = $responsavelId;
    } elseif ($responsavel["disponivel"] && filtroAtivo($responsavelFiltro) && $responsavel["campo_texto"] !== "") {
        $where[] = "lower(" . $responsavel["campo_texto"] . ") like lower(:responsavel)";
        $params[":responsavel"] = "%" . $responsavelFiltro . "%";
    }

    $whereSql = $where ? " where " . implode(" and ", $where) : "";

    $stmt = $pdo->prepare("
        select
            a.id,
            a.nome,
            a.descricao,
            a.numero_serie,
            a.imei,
            c.nome as categoria,
            a.marca,
            a.propriedade,
            l.nome as localizacao,
            a.status,
            a.datasheet,
            a.criado_em
            {$responsavel["select"]}
          from public.ativos a
     left join public.categorias_ativos c on c.id = a.categoria_id
     left join public.locais l on l.id = a.local_id
            {$responsavel["join"]}
            {$whereSql}
      order by a.criado_em desc, a.nome asc
    ");
    $stmt->execute($params);
    $ativos = $stmt->fetchAll();
} catch (Throwable) {
    responderErroExportacao(500, "Nao foi possivel exportar os ativos agora.");
}

$filename = "ativos-titech-" . (new DateTimeImmutable("now", new DateTimeZone("America/Sao_Paulo")))->format("Y-m-d") . ".csv";
$cabecalho = [
    "ID",
    "Nome",
    "Descricao",
    "Numero de serie",
    "IMEI",
    "Categoria",
    "Marca",
    "Propriedade",
    "Localizacao",
];

if ($responsavel["disponivel"]) {
    $cabecalho[] = "Responsavel";
}

$cabecalho = array_merge($cabecalho, [
    "Status",
    "Datasheet",
    "Data de cadastro",
]);

header_remove("Content-Type");
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

$output = fopen("php://output", "wb");

if ($output === false) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $cabecalho, ";");

foreach ($ativos as $ativo) {
    $linha = [
        (string) ($ativo["id"] ?? ""),
        (string) ($ativo["nome"] ?? ""),
        (string) ($ativo["descricao"] ?? ""),
        (string) ($ativo["numero_serie"] ?? ""),
        (string) ($ativo["imei"] ?? ""),
        (string) ($ativo["categoria"] ?? ""),
        (string) ($ativo["marca"] ?? ""),
        (string) ($ativo["propriedade"] ?? ""),
        (string) ($ativo["localizacao"] ?? ""),
    ];

    if ($responsavel["disponivel"]) {
        $linha[] = (string) ($ativo["responsavel"] ?? "");
    }

    $linha[] = (string) ($ativo["status"] ?? "");
    $linha[] = (string) ($ativo["datasheet"] ?? "");
    $linha[] = formatarDataExportacao((string) ($ativo["criado_em"] ?? ""));

    fputcsv($output, $linha, ";");
}

fclose($output);
