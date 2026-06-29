<?php

declare(strict_types=1);

// Endpoint de exclusao de propriedades.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Mantem o mesmo formato de retorno das outras rotas.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Recupera o ID enviado pela tela.
    return trim((string)($_POST[$nome] ?? ""));
}

function csrfValido(): bool
{
    // Confere se o token do formulario pertence a sessao atual.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(string $valor): bool
{
    // A propriedade e identificada por UUID no banco.
    return (bool)preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    );
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

if (!uuidValido($id)) {
    responder(false, "Propriedade invalida para exclusao.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    // O returning confirma qual propriedade saiu do banco.
    $stmt = $pdo->prepare("
        delete from public.propriedade_ativos
         where id = :id
     returning id, nome, status
    ");

    $stmt->execute([":id" => $id]);
    $propriedade = $stmt->fetch();

    if (!$propriedade) {
        responder(false, "Propriedade nao encontrada.", 404);
    }

    responder(true, "Propriedade excluida com sucesso.", 200, [
        "propriedade" => $propriedade,
    ]);
} catch (PDOException $erro) {
    // Codigo 23503 indica vinculo com ativos, entao nao podemos remover.
    if ($erro->getCode() === "23503") {
        responder(false, "Nao e possivel excluir esta propriedade porque ela esta vinculada a ativos.", 409);
    }

    responder(false, "Nao foi possivel excluir a propriedade agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel excluir a propriedade agora.", 500);
}
