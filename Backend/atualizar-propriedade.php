<?php

declare(strict_types=1);

// Endpoint de edicao de propriedades.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Retorno padrao usado pelos scripts de edicao.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Normaliza o valor bruto enviado pelo formulario.
    return trim((string)($_POST[$nome] ?? ""));
}

function normalizarEspacos(string $valor): string
{
    // Remove excesso de espacos internos no nome.
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTexto(string $valor): int
{
    // Usa mbstring quando disponivel para textos com acentos.
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfValido(): bool
{
    // Garante que a requisicao veio da pagina carregada pelo usuario.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(string $valor): bool
{
    // A propriedade editada e identificada por UUID.
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

// Campos alteraveis da propriedade.
$id = campo("id");
$nome = normalizarEspacos(campo("nome"));
$status = campo("status") ?: "Ativa";

if (!uuidValido($id)) {
    responder(false, "Propriedade invalida para alteracao.", 422);
}

if ($nome === "") {
    responder(false, "Informe o nome da propriedade.", 422);
}

$tamanhoNome = tamanhoTexto($nome);

if ($tamanhoNome < 2) {
    responder(false, "O nome da propriedade precisa ter pelo menos 2 caracteres.", 422);
}

if ($tamanhoNome > 80) {
    responder(false, "O nome da propriedade deve ter no maximo 80 caracteres.", 422);
}

if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/]+$/u", $nome)) {
    responder(false, "Use apenas letras, numeros, espacos e sinais simples no nome da propriedade.", 422);
}

if (!in_array($status, ["Ativa", "Inativa"], true)) {
    responder(false, "Status invalido para propriedades.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    // Atualiza e devolve a propriedade para a interface substituir a linha.
    $stmt = $pdo->prepare("
        update public.propriedade_ativos
           set nome = :nome,
               status = :status,
               atualizado_em = now()
         where id = :id
     returning
               id,
               nome,
               status,
               criado_em,
               atualizado_em
    ");

    $stmt->execute([
        ":id" => $id,
        ":nome" => $nome,
        ":status" => $status,
    ]);

    $propriedade = $stmt->fetch();

    if (!$propriedade) {
        responder(false, "Propriedade nao encontrada.", 404);
    }

    responder(true, "Propriedade alterada com sucesso.", 200, [
        "propriedade" => $propriedade,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responder(false, "Ja existe uma propriedade cadastrada com este nome.", 409);
    }

    responder(false, "Nao foi possivel alterar a propriedade agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel alterar a propriedade agora.", 500);
}
