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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Faca login novamente.", 401);
}

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$nome = normalizarEspacos(campo("nome"));
$status = campo("status") ?: "Ativa";

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

    $pdo->exec("
        create table if not exists public.propriedade_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("
        create unique index if not exists propriedade_ativos_nome_lower_unique
            on public.propriedade_ativos (lower(nome))
    ");
  $pdo->exec("
        insert into public.propriedade_ativos (nome, status, atualizado_em)
        select seed.nome, 'Ativa', now()
          from (values ('TITECHSOLUTIONS'), ('TSC')) as seed(nome)
         where not exists (
               select 1
                 from public.propriedade_ativos existente
                where lower(existente.nome) = lower(seed.nome)
         )
    ");

    $stmt = $pdo->prepare("
        insert into public.propriedade_ativos (
            nome,
            status,
            atualizado_em
        ) values (
            :nome,
            :status,
            now()
        )
        returning
            id,
            nome,
            status,
            criado_em,
            atualizado_em
    ");

    $stmt->execute([
        ":nome" => $nome,
        ":status" => $status,
    ]);

    $propriedade = $stmt->fetch();

    responder(true, "Propriedade cadastrada com sucesso.", 201, [
        "propriedade" => $propriedade,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responder(false, "Esta propriedade ja esta cadastrada.", 409);
    }

    responder(false, "Nao foi possivel cadastrar a propriedade agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel cadastrar a propriedade agora.", 500);
}
