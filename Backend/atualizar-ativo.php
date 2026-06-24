<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    return trim((string)($_POST[$nome] ?? ""));
}

function campoNulo(string $nome): ?string
{
    $valor = campo($nome);

    return $valor !== "" ? $valor : null;
}

function csrfValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(?string $valor): bool
{
    if ($valor === null || $valor === "") {
        return true;
    }

    return (bool)preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    );
}

function statusPermitido(string $status): bool
{
    return in_array($status, [
        "Disponível",
        "Em uso",
        "Manutenção",
        "Formatação",
        "Homologação",
        "Baixado",
        "Perdido",
    ], true);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Faca login novamente.", 401);
}

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$id = campo("id");
$nome = campo("nome");
$descricao = campoNulo("descricao");
$numeroSerie = campoNulo("numero_serie");
$categoriaId = campoNulo("categoria_id");
$localId = campoNulo("local_id");
$status = campo("status") ?: "Disponível";
$marca = campoNulo("marca");
$propriedade = campoNulo("propriedade");
$imei = campoNulo("imei");
$datasheet = campoNulo("datasheet");

if (!uuidValido($id)) {
    responder(false, "Ativo invalido para alteracao.", 422);
}

if ($nome === "") {
    responder(false, "Informe o nome do ativo.", 422);
}

if (strlen($nome) < 2) {
    responder(false, "O nome do ativo precisa ter pelo menos 2 caracteres.", 422);
}

if ($categoriaId === null) {
    responder(false, "Selecione a categoria do ativo.", 422);
}

if (!uuidValido($categoriaId) || !uuidValido($localId)) {
    responder(false, "Categoria ou local invalido.", 422);
}

try {
    require __DIR__ . "/Conexao.php";
    require __DIR__ . "/status-ativos.php";

    $statusNormalizado = obterStatusAtivo($pdo, $status);

    if ($statusNormalizado === null) {
        responder(false, "Status invalido para ativos.", 422);
    }

    $status = $statusNormalizado;

    if ($marca !== null) {
        $marcaStmt = $pdo->prepare("
            select nome
              from public.marcas_ativos
             where lower(nome) = lower(:marca)
               and status = :status
             limit 1
        ");
        $marcaStmt->execute([
            ":marca" => $marca,
            ":status" => "Ativa",
        ]);

        $marcaAtiva = $marcaStmt->fetchColumn();

        if ($marcaAtiva === false) {
            responder(false, "Selecione uma marca ativa cadastrada.", 422);
        }

        $marca = (string)$marcaAtiva;
    }

    $stmt = $pdo->prepare("
        with ativo_atualizado as (
            update public.ativos
               set nome = :nome,
                   descricao = :descricao,
                   numero_serie = :numero_serie,
                   categoria_id = :categoria_id,
                   local_id = :local_id,
                   status = :status,
                   marca = :marca,
                   propriedade = :propriedade,
                   imei = :imei,
                   datasheet = :datasheet
             where id = :id
         returning
                   id,
                   nome,
                   descricao,
                   numero_serie,
                   categoria_id,
                   local_id,
                   status,
                   marca,
                   propriedade,
                   imei,
                   datasheet,
                   criado_em
        )
        select
            a.*,
            c.nome as categoria,
            l.nome as local
          from ativo_atualizado a
     left join public.categorias_ativos c on c.id = a.categoria_id
     left join public.locais l on l.id = a.local_id
    ");

    $stmt->execute([
        ":id" => $id,
        ":nome" => $nome,
        ":descricao" => $descricao,
        ":numero_serie" => $numeroSerie,
        ":categoria_id" => $categoriaId,
        ":local_id" => $localId,
        ":status" => $status,
        ":marca" => $marca,
        ":propriedade" => $propriedade,
        ":imei" => $imei,
        ":datasheet" => $datasheet,
    ]);

    $ativo = $stmt->fetch();

    if (!$ativo) {
        responder(false, "Ativo nao encontrado.", 404);
    }

    responder(true, "Ativo alterado com sucesso.", 200, [
        "ativo" => $ativo,
    ]);
} catch (Throwable) {
    responder(false, "Nao foi possivel alterar o ativo agora.", 500);
}

