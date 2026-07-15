<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/permissoes-acesso.php";
require_once __DIR__ . "/status-ativos.php";
require_once __DIR__ . "/relatorio-ativos-pdf.php";
require_once __DIR__ . "/relatorio-ativos-xlsx.php";
require_once __DIR__ . "/exportar-ativos-csv.php";
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

    foreach ($ativos as &$ativo) {
        $ativo["criado_em_formatado"] = formatarDataExportacao((string) ($ativo["criado_em"] ?? ""));
    }
    unset($ativo);

    $totalAtivosStmt = $pdo->query("select count(*)::int from public.ativos");
    $totalAtivos = (int) $totalAtivosStmt->fetchColumn();

    $disponiveisStmt = $pdo->prepare("
        select count(*)::int
          from public.ativos
         where status = :status
    ");
    $disponiveisStmt->execute([":status" => statusAtivoPadrao()]);
    $ativosDisponiveis = (int) $disponiveisStmt->fetchColumn();

    $filtrosRelatorio = [];

    if ($busca !== "") {
        $filtrosRelatorio["Busca"] = $busca;
    }

    if (filtroAtivo($status)) {
        $filtrosRelatorio["Status"] = $status;
    }

    if (filtroAtivo($categoria)) {
        $filtrosRelatorio["Categoria"] = $categoria;
    }

    if (filtroAtivo($marca)) {
        $filtrosRelatorio["Marca"] = $marca;
    }

    if (filtroAtivo($localizacao)) {
        $filtrosRelatorio["Local"] = $localizacao === "sem-localizacao" ? "Sem localização" : $localizacao;
    }

    if (filtroAtivo($responsavelFiltro)) {
        $filtrosRelatorio["Responsável"] = $responsavelFiltro;
    }
} catch (Throwable) {
    responderErroExportacao(500, "Nao foi possivel carregar os dados do relatorio agora.");
}

$formato = strtolower(filtroExportacao("formato", "pdf"));

if (!in_array($formato, ["pdf", "xlsx", "csv"], true)) {
    responderErroExportacao(400, "Formato de exportacao invalido.");
}

$geradoEm = new DateTimeImmutable("now", new DateTimeZone("America/Sao_Paulo"));
$metricasRelatorio = [
    "total" => $totalAtivos,
    "disponiveis" => $ativosDisponiveis,
    "filtrados" => count($ativos),
];

if ($formato === "xlsx") {
    try {
        $xlsx = (new RelatorioAtivosXlsx())->generate(
            $ativos,
            $metricasRelatorio,
            $filtrosRelatorio,
            $geradoEm,
            (bool) $responsavel["disponivel"]
        );
    } catch (Throwable) {
        responderErroExportacao(500, "Nao foi possivel gerar a planilha Excel dos ativos agora.");
    }

    $filename = "relatorio-ativos-" . $geradoEm->format("Y-m-d-His") . ".xlsx";

    header_remove("Content-Type");
    header("Content-Type: " . RelatorioAtivosXlsx::CONTENT_TYPE);
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Content-Length: " . strlen($xlsx));
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("X-Content-Type-Options: nosniff");

    echo $xlsx;
    exit;
}

if ($formato === "csv") {
    try {
        $csv = gerarCsvAtivos(
            $ativos,
            (bool) $responsavel["disponivel"]
        );
    } catch (Throwable) {
        responderErroExportacao(500, "Nao foi possivel gerar o CSV dos ativos agora.");
    }

    $filename = "ativos-titech-" . $geradoEm->format("Y-m-d-His") . ".csv";

    header_remove("Content-Type");
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Content-Length: " . strlen($csv));
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("X-Content-Type-Options: nosniff");

    echo $csv;
    exit;
}

$filename = "relatorio-ativos-" . $geradoEm->format("Y-m-d-His") . ".pdf";

try {
    $pdf = (new RelatorioAtivosPdf())->generate(
        $ativos,
        $metricasRelatorio,
        $filtrosRelatorio,
        $geradoEm
    );
} catch (Throwable) {
    responderErroExportacao(500, "Nao foi possivel gerar o PDF do relatorio agora.");
}

header_remove("Content-Type");
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Length: " . strlen($pdf));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

echo $pdf;
