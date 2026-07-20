<?php

declare(strict_types=1);

// Endpoint de cadastro de locais onde os ativos podem ficar.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Todas as respostas seguem o mesmo envelope JSON.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Retorna o texto enviado no POST ja sem espacos laterais.
    return trim((string)($_POST[$nome] ?? ""));
}

function campoNulo(string $nome): ?string
{
    // Endereco e opcional; vazio vira null para nao gravar string vazia.
    $valor = campo($nome);

    return $valor !== "" ? $valor : null;
}

function normalizarEspacos(string $valor): string
{
    // Padroniza espacos internos do nome do local.
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTexto(string $valor): int
{
    // Conta caracteres de forma segura para textos com acentos.
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfValido(): bool
{
    // Protege o cadastro contra envio sem o token da sessao.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function garantirTabelaLocais(PDO $pdo): void
{
    // Estrutura criada por migration. Mantido para compatibilidade.
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Faca login novamente.", 401);
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_locais", "Cadastro de localizacoes");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

// Dados principais do local.
$nome = normalizarEspacos(campo("nome"));
$endereco = campoNulo("endereco");
$status = campo("status") ?: "Ativo";

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

if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/,ºª°()]+$/u", $nome)) {
    responder(false, "Use apenas letras, numeros, espacos e sinais simples no nome do local.", 422);
}

if (!in_array($status, ["Ativo", "Inativo"], true)) {
    responder(false, "Status invalido para locais.", 422);
}

try {
    // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
    require __DIR__ . "/Conexao.php";

    garantirTabelaLocais($pdo);

    // Bloqueia duplicidade por nome antes de inserir.
    $duplicadoStmt = $pdo->prepare("
        select 1
          from public.locais
         where lower(btrim(nome)) = lower(btrim(:nome))
         limit 1
    ");
    $duplicadoStmt->execute([":nome" => $nome]);

    if ($duplicadoStmt->fetchColumn() !== false) {
        responder(false, "Este local ja esta cadastrado.", 409);
    }

    // Insere e retorna o local criado.
    $stmt = $pdo->prepare("
        insert into public.locais (
            nome,
            endereco,
            status,
            atualizado_em
        ) values (
            :nome,
            :endereco,
            :status,
            now()
        )
        returning
            id,
            nome,
            endereco,
            status,
            criado_em,
            atualizado_em
    ");

    $stmt->execute([
        ":nome" => $nome,
        ":endereco" => $endereco,
        ":status" => $status,
    ]);

    $local = $stmt->fetch();

    responder(true, "Local cadastrado com sucesso.", 201, [
        "local" => $local,
    ]);
} catch (Throwable) {
    responder(false, "Nao foi possivel cadastrar o local agora.", 500);
}

