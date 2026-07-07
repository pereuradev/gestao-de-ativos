<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_ativos", "Importacao de ativos");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "ok" => false,
        "message" => "Metodo nao permitido.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$filename = "modelo-importacao-ativos.csv";
$cabecalho = [
    "nome",
    "descricao",
    "numero_serie",
    "imei",
    "categoria",
    "marca",
    "propriedade",
    "localizacao",
    "status",
    "datasheet",
];
$exemplo = [
    "Notebook Dell Latitude",
    "Notebook para colaborador",
    "SN123456",
    "359999999999999",
    "Notebook",
    "Dell",
    "TI TECH Solutions",
    "Estoque",
    "Disponivel",
    "https://exemplo.com/datasheet.pdf",
];

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
fputcsv($output, $exemplo, ";");
fclose($output);
