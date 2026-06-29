<?php

declare(strict_types=1);

// Endpoint de exclusao de locais.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Retorno padrao para o JavaScript.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Busca o identificador enviado pela tela.
    return trim((string)($_POST[$nome] ?? ""));
}

function csrfValido(): bool
{
    // Evita exclusoes disparadas sem o token da sessao.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
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

if ($id === "") {
    responder(false, "Identificador do local nao informado.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    // Remove o local e retorna os dados basicos do registro removido.
    $stmt = $pdo->prepare("
        delete from public.locais
         where id::text = :id
     returning id, nome
    ");

    $stmt->execute([":id" => $id]);
    $local = $stmt->fetch();

    if (!$local) {
        responder(false, "Local nao encontrado.", 404);
    }

    responder(true, "Local excluido com sucesso.", 200, [
        "local" => $local,
    ]);
} catch (PDOException $exception) {
    // Se o local esta em uso por ativos, o banco bloqueia a exclusao.
    if ($exception->getCode() === "23503") {
        responder(false, "Nao e possivel excluir este local porque ele esta vinculado a ativos.", 409);
    }

    responder(false, "Nao foi possivel excluir o local agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel excluir o local agora.", 500);
}
