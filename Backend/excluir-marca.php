<?php

declare(strict_types=1);

// Endpoint de exclusao de marcas.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Todas as respostas seguem o envelope JSON padrao.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Pega o ID enviado pelo formulario.
    return trim((string)($_POST[$nome] ?? ""));
}

function csrfValido(): bool
{
    // Confere o token para evitar exclusao fora da pagina autenticada.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(string $valor): bool
{
    // O ID da marca precisa ser um UUID valido.
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
exigirPermissaoApi("editar_marcas", "Edicao de marcas");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$id = campo("id");

if (!uuidValido($id)) {
    responder(false, "Marca invalida para exclusao.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    // Exclui e retorna o registro apagado para atualizar a interface.
    $stmt = $pdo->prepare("
        delete from public.marcas_ativos
         where id = :id
     returning id, nome, status
    ");

    $stmt->execute([":id" => $id]);
    $marca = $stmt->fetch();

    if (!$marca) {
        responder(false, "Marca nao encontrada.", 404);
    }

    responder(true, "Marca excluida com sucesso.", 200, [
        "marca" => $marca,
    ]);
} catch (PDOException $erro) {
    // Codigo 23503 significa que existe ativo apontando para esta marca.
    if ($erro->getCode() === "23503") {
        responder(false, "Nao e possivel excluir esta marca porque ela esta vinculada a ativos.", 409);
    }

    responder(false, "Nao foi possivel excluir a marca agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel excluir a marca agora.", 500);
}
