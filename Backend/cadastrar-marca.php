<?php

declare(strict_types=1);

// Endpoint de cadastro de marcas usadas pelos ativos.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Padroniza a resposta para o JavaScript saber se deve mostrar sucesso ou erro.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Remove espacos simples antes de validar o valor recebido.
    return trim((string)($_POST[$nome] ?? ""));
}

function normalizarEspacos(string $valor): string
{
    // Troca sequencias grandes de espaco por um unico espaco.
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTexto(string $valor): int
{
    // Usa mb_strlen quando disponivel para contar acentos corretamente.
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfValido(): bool
{
    // Confirma que a requisicao veio do formulario gerado na sessao atual.
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

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_marcas", "Cadastro de marcas");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

// Campos que definem a marca.
$nome = normalizarEspacos(campo("nome"));
$status = campo("status") ?: "Ativa";

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
    require __DIR__ . "/Conexao.php";

    // Garante a tabela antes de inserir, util em ambientes recem-configurados.
    $pdo->exec("
        create table if not exists public.marcas_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

    // Insere e devolve a marca cadastrada para a tela atualizar a listagem.
    $stmt = $pdo->prepare("
        insert into public.marcas_ativos (
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

    $marca = $stmt->fetch();

    responder(true, "Marca cadastrada com sucesso.", 201, [
        "marca" => $marca,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responder(false, "Esta marca ja esta cadastrada.", 409);
    }

    responder(false, "Nao foi possivel cadastrar a marca agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel cadastrar a marca agora.", 500);
}
