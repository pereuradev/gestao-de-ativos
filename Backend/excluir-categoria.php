<?php

declare(strict_types=1);

// Endpoint de exclusao de categorias.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderCategoria(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoCategoria(string $nome): string
{
    return trim((string)($_POST[$nome] ?? ""));
}

function csrfCategoriaValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campoCategoria("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidCategoriaValido(string $valor): bool
{
    return (bool)preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    );
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderCategoria(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderCategoria(false, "Sessao expirada. Faca login novamente.", 401);
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_categorias", "Edicao de categorias");

if (!csrfCategoriaValido()) {
    responderCategoria(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$id = campoCategoria("id");

if (!uuidCategoriaValido($id)) {
    responderCategoria(false, "Categoria invalida para exclusao.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    $stmt = $pdo->prepare("
        delete from public.categorias_ativos
         where id = :id
     returning id, nome, descricao
    ");

    $stmt->execute([":id" => $id]);
    $categoria = $stmt->fetch();

    if (!$categoria) {
        responderCategoria(false, "Categoria nao encontrada.", 404);
    }

    responderCategoria(true, "Categoria excluida com sucesso.", 200, [
        "categoria" => $categoria,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23503") {
        responderCategoria(false, "Nao e possivel excluir esta categoria porque ela esta vinculada a ativos.", 409);
    }

    responderCategoria(false, "Nao foi possivel excluir a categoria agora.", 500);
} catch (Throwable) {
    responderCategoria(false, "Nao foi possivel excluir a categoria agora.", 500);
}
