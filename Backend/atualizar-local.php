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

function normalizarEspacos(string $valor): string
{
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTexto(string $valor): int
{
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function garantirTabelaLocais(PDO $pdo): void
{
    $pdo->exec("
        create table if not exists public.locais (
            id uuid primary key default gen_random_uuid(),
            nome text not null,
            endereco text,
            status text not null default 'Ativo',
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("alter table public.locais add column if not exists endereco text");
    $pdo->exec("alter table public.locais add column if not exists status text not null default 'Ativo'");
    $pdo->exec("alter table public.locais add column if not exists criado_em timestamptz not null default now()");
    $pdo->exec("alter table public.locais add column if not exists atualizado_em timestamptz not null default now()");
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
$nome = normalizarEspacos(campo("nome"));
$endereco = campoNulo("endereco");
$status = campo("status") ?: "Ativo";

if ($id === "") {
    responder(false, "Identificador do local nao informado.", 422);
}

if ($nome === "") {
    responder(false, "Informe o nome do local.", 422);
}

$tamanhoNome = tamanhoTexto($nome);

if ($tamanhoNome < 2) {
    responder(false, "O nome do local precisa ter pelo menos 2 caracteres.", 422);
}

if ($tamanhoNome > 100) {
    responder(false, "O nome do local deve ter no maximo 100 caracteres.", 422);
}

if ($endereco !== null && tamanhoTexto($endereco) > 160) {
    responder(false, "O endereco deve ter no maximo 160 caracteres.", 422);
}

if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/,ÂºÂªÂ°()]+$/u", $nome)) {
    responder(false, "Use apenas letras, numeros, espacos e sinais simples no nome do local.", 422);
}

if (!in_array($status, ["Ativo", "Inativo"], true)) {
    responder(false, "Status invalido para locais.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    garantirTabelaLocais($pdo);

    $duplicadoStmt = $pdo->prepare("
        select 1
          from public.locais
         where lower(nome) = lower(:nome)
           and id::text <> :id
         limit 1
    ");
    $duplicadoStmt->execute([
        ":nome" => $nome,
        ":id" => $id,
    ]);

    if ($duplicadoStmt->fetchColumn() !== false) {
        responder(false, "Ja existe um local cadastrado com este nome.", 409);
    }

    $stmt = $pdo->prepare("
        update public.locais
           set nome = :nome,
               endereco = :endereco,
               status = :status,
               atualizado_em = now()
         where id::text = :id
     returning id, nome, endereco, status, criado_em, atualizado_em
    ");

    $stmt->execute([
        ":id" => $id,
        ":nome" => $nome,
        ":endereco" => $endereco,
        ":status" => $status,
    ]);

    $local = $stmt->fetch();

    if (!$local) {
        responder(false, "Local nao encontrado.", 404);
    }

    responder(true, "Local alterado com sucesso.", 200, [
        "local" => $local,
    ]);
} catch (Throwable) {
    responder(false, "Nao foi possivel alterar o local agora.", 500);
}
