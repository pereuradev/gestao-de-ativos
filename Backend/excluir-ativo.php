<?php

declare(strict_types=1);

// Endpoint de exclusao de ativos.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Padroniza a resposta consumida pelo JavaScript.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Le o identificador enviado pelo formulario/modal.
    return trim((string)($_POST[$nome] ?? ""));
}

function csrfValido(): bool
{
    // Garante que a exclusao foi solicitada a partir da sessao atual.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(string $valor): bool
{
    // Ativos usam UUID como identificador.
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

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_ativos", "Edicao de ativos");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$id = campo("id");

if (!uuidValido($id)) {
    responder(false, "Ativo invalido para exclusao.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    // O returning permite confirmar o que foi removido e avisar a tela.
    $stmt = $pdo->prepare("
        delete from public.ativos
         where id = :id
     returning id, nome, status
    ");

    $stmt->execute([":id" => $id]);
    $ativo = $stmt->fetch();

    if (!$ativo) {
        responder(false, "Ativo nao encontrado.", 404);
    }

    responder(true, "Ativo excluido com sucesso.", 200, [
        "ativo" => $ativo,
    ]);
} catch (Throwable) {
    responder(false, "Nao foi possivel excluir o ativo agora.", 500);
}
