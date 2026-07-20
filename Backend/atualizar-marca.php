<?php

declare(strict_types=1);

// Endpoint de edicao de marcas.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Mantem o contrato JSON esperado pelo JavaScript.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Le o POST e remove espacos das bordas.
    return trim((string)($_POST[$nome] ?? ""));
}

function normalizarEspacos(string $valor): string
{
    // Deixa o nome da marca com espacos consistentes.
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTexto(string $valor): int
{
    // Conta texto com suporte a UTF-8 quando possivel.
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfValido(): bool
{
    // Evita alteracoes disparadas fora da sessao autenticada.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(string $valor): bool
{
    // O ID precisa ser UUID porque vem da chave primaria do banco.
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

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_marcas", "Edicao de marcas");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

// Dados recebidos da tela de edicao.
$id = campo("id");
$nome = normalizarEspacos(campo("nome"));
$status = campo("status") ?: "Ativa";

if (!uuidValido($id)) {
    responder(false, "Marca invalida para alteracao.", 422);
}

if ($nome === "") {
    responder(false, "Informe o nome da marca.", 422);
}

$tamanhoNome = tamanhoTexto($nome);

if ($tamanhoNome < 2) {
    responder(false, "O nome da marca precisa ter pelo menos 2 caracteres.", 422);
}

if ($tamanhoNome > 80) {
    responder(false, "O nome da marca deve ter no maximo 80 caracteres.", 422);
}

if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/]+$/u", $nome)) {
    responder(false, "Use apenas letras, numeros, espacos e sinais simples no nome da marca.", 422);
}

if (!in_array($status, ["Ativa", "Inativa"], true)) {
    responder(false, "Status invalido para marcas.", 422);
}

try {
    // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
    require __DIR__ . "/Conexao.php";

    // Atualiza e retorna o registro alterado.
    $stmt = $pdo->prepare("
        update public.marcas_ativos
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

    $marca = $stmt->fetch();

    if (!$marca) {
        responder(false, "Marca nao encontrada.", 404);
    }

    responder(true, "Marca alterada com sucesso.", 200, [
        "marca" => $marca,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responder(false, "Ja existe uma marca cadastrada com este nome.", 409);
    }

    responder(false, "Nao foi possivel alterar a marca agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel alterar a marca agora.", 500);
}
