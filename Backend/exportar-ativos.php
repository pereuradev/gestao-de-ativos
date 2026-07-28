<?php

declare(strict_types=1);

// Orquestra a consulta filtrada e encaminha os dados ao gerador do formato solicitado.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Carrega autorização, regras de status e os geradores dos formatos de exportação suportados.
require_once __DIR__ . "/permissoes-acesso.php";
require_once __DIR__ . "/status-ativos.php";
require_once __DIR__ . "/relatorio-ativos-pdf.php";
require_once __DIR__ . "/relatorio-ativos-xlsx.php";
require_once __DIR__ . "/exportar-ativos-csv.php";
exigirPermissaoApi("visualizar_ativos", "Ativos");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responderErroExportacao(405, "Metodo nao permitido.");
}

function responderErroExportacao(int $codigoStatusHttp, string $mensagemResposta): void
{
    http_response_code($codigoStatusHttp);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "ok" => false,
        "message" => $mensagemResposta,
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
    $consultaPreparada = $pdo->prepare("
        select 1
          from information_schema.tables
         where table_schema = 'public'
           and table_name = :tabela
         limit 1
    ");
    $consultaPreparada->execute([":tabela" => $tabela]);

    return $consultaPreparada->fetchColumn() !== false;
}

function colunaExportacaoExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $consultaPreparada = $pdo->prepare("
        select 1
          from information_schema.columns
         where table_schema = 'public'
           and table_name = :tabela
           and column_name = :coluna
         limit 1
    ");
    $consultaPreparada->execute([
        ":tabela" => $tabela,
        ":coluna" => $coluna,
    ]);

    return $consultaPreparada->fetchColumn() !== false;
}

// Mantém compatibilidade com versões do schema que armazenam o responsável de formas diferentes.
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

function formatarDataExportacao(?string $valorEntrada): string
{
    if (!$valorEntrada) {
        return "";
    }

    try {
        return (new DateTimeImmutable($valorEntrada))
            ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
            ->format("d/m/Y H:i");
    } catch (Throwable) {
        return "";
    }
}

try {
    global $pdo;

    // Os filtros são convertidos em condições parametrizadas compartilhadas por todos os formatos.
    $responsavel = contextoResponsavelExportacao($pdo);
    $condicoesSql = [];
    $parametrosConsulta = [];

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
        $condicoesSql[] = "(
            lower(coalesce(a.nome, '')) like lower(:busca)
            or lower(coalesce(a.descricao, '')) like lower(:busca)
            or lower(coalesce(a.numero_serie, '')) like lower(:busca)
            or lower(coalesce(a.part_number, '')) like lower(:busca)
            or lower(coalesce(a.imei, '')) like lower(:busca)
            or lower(coalesce(a.status, '')) like lower(:busca)
            or lower(coalesce(a.marca, '')) like lower(:busca)
            or lower(coalesce(a.propriedade, '')) like lower(:busca)
            or lower(coalesce(a.datasheet, '')) like lower(:busca)
            or lower(coalesce(c.nome, '')) like lower(:busca)
            or lower(coalesce(l.nome, '')) like lower(:busca)
        )";
        $parametrosConsulta[":busca"] = "%" . $busca . "%";
    }

    if (filtroAtivo($status)) {
        $condicoesSql[] = "a.status = :status";
        $parametrosConsulta[":status"] = $status;
    }

    if ($categoriaId !== "" && uuidExportacaoValido($categoriaId)) {
        $condicoesSql[] = "a.categoria_id::text = :categoria_id";
        $parametrosConsulta[":categoria_id"] = $categoriaId;
    } elseif (filtroAtivo($categoria)) {
        $condicoesSql[] = "c.nome = :categoria";
        $parametrosConsulta[":categoria"] = $categoria;
    }

    if (filtroAtivo($marca)) {
        $condicoesSql[] = "a.marca = :marca";
        $parametrosConsulta[":marca"] = $marca;
    }

    if ($localId !== "" && uuidExportacaoValido($localId)) {
        $condicoesSql[] = "a.local_id::text = :local_id";
        $parametrosConsulta[":local_id"] = $localId;
    } elseif ($localizacao === "sem-localizacao") {
        $condicoesSql[] = "a.local_id is null";
    } elseif (filtroAtivo($localizacao)) {
        $condicoesSql[] = "l.nome = :localizacao";
        $parametrosConsulta[":localizacao"] = $localizacao;
    }

    if ($responsavel["disponivel"] && $responsavelId !== "" && uuidExportacaoValido($responsavelId) && $responsavel["campo_id"] !== "") {
        $condicoesSql[] = $responsavel["campo_id"] . " = :responsavel_id";
        $parametrosConsulta[":responsavel_id"] = $responsavelId;
    } elseif ($responsavel["disponivel"] && filtroAtivo($responsavelFiltro) && $responsavel["campo_texto"] !== "") {
        $condicoesSql[] = "lower(" . $responsavel["campo_texto"] . ") like lower(:responsavel)";
        $parametrosConsulta[":responsavel"] = "%" . $responsavelFiltro . "%";
    }

    $clausulaCondicoes = $condicoesSql ? " where " . implode(" and ", $condicoesSql) : "";

    $consultaPreparada = $pdo->prepare("
        select
            a.id,
            a.nome,
            a.descricao,
            a.numero_serie,
            a.part_number,
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
            {$clausulaCondicoes}
      order by a.criado_em desc, a.nome asc
    ");
    $consultaPreparada->execute($parametrosConsulta);
    $ativos = $consultaPreparada->fetchAll();

    foreach ($ativos as &$ativo) {
        $ativo["criado_em_formatado"] = formatarDataExportacao((string) ($ativo["criado_em"] ?? ""));
    }
    unset($ativo);

    $consultaTotalAtivos = $pdo->query("select count(*)::int from public.ativos");
    $totalAtivos = (int) $consultaTotalAtivos->fetchColumn();

    $consultaDisponiveis = $pdo->prepare("
        select count(*)::int
          from public.ativos
         where status = :status
    ");
    $consultaDisponiveis->execute([":status" => statusAtivoPadrao()]);
    $ativosDisponiveis = (int) $consultaDisponiveis->fetchColumn();

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

// A lista permitida impede que um formato arbitrário escolha um fluxo de geração inesperado.
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

// Cada gerador devolve bytes prontos; este endpoint define os cabeçalhos e encerra a resposta.
if ($formato === "xlsx") {
    try {
        $xlsx = (new RelatorioAtivosXlsx())->gerar(
            $ativos,
            $metricasRelatorio,
            $filtrosRelatorio,
            $geradoEm,
            (bool) $responsavel["disponivel"]
        );
    } catch (Throwable) {
        responderErroExportacao(500, "Nao foi possivel gerar a planilha Excel dos ativos agora.");
    }

    $nomeArquivoDownload = "relatorio-ativos-" . $geradoEm->format("Y-m-d-His") . ".xlsx";

    header_remove("Content-Type");
    header("Content-Type: " . RelatorioAtivosXlsx::CONTENT_TYPE);
    header("Content-Disposition: attachment; filename=\"{$nomeArquivoDownload}\"");
    header("Content-Length: " . strlen($xlsx));
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("X-Content-Type-Options: nosniff");

    echo $xlsx;
    exit;
}

// O CSV segue o mesmo conjunto filtrado usado nos relatórios PDF e Excel.
if ($formato === "csv") {
    try {
        $csv = gerarCsvAtivos(
            $ativos,
            (bool) $responsavel["disponivel"]
        );
    } catch (Throwable) {
        responderErroExportacao(500, "Nao foi possivel gerar o CSV dos ativos agora.");
    }

    $nomeArquivoDownload = "ativos-titech-" . $geradoEm->format("Y-m-d-His") . ".csv";

    header_remove("Content-Type");
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$nomeArquivoDownload}\"");
    header("Content-Length: " . strlen($csv));
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("X-Content-Type-Options: nosniff");

    echo $csv;
    exit;
}

$nomeArquivoDownload = "relatorio-ativos-" . $geradoEm->format("Y-m-d-His") . ".pdf";

try {
    $pdf = (new RelatorioAtivosPdf())->gerar(
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
header("Content-Disposition: attachment; filename=\"{$nomeArquivoDownload}\"");
header("Content-Length: " . strlen($pdf));
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

echo $pdf;
