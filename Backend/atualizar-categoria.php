<?php

declare(strict_types=1);

// Endpoint de edicao de categorias usadas pelos ativos.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderCategoria(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoCategoria(string $nome): string
{
    return trim((string)($_POST[$nome] ?? ""));
}

function normalizarEspacosCategoria(string $valor): string
{
    return preg_replace("/\s+/u", " ", $valor) ?? $valor;
}

function tamanhoTextoCategoria(string $valor): int
{
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function csrfCategoriaValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenRequisicao = campoCategoria("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenRequisicao);
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
$nome = normalizarEspacosCategoria(campoCategoria("nome"));
$descricao = normalizarEspacosCategoria(campoCategoria("descricao"));
$descricao = $descricao !== "" ? $descricao : null;

if (!uuidCategoriaValido($id)) {
    responderCategoria(false, "Categoria invalida para alteracao.", 422);
}

if ($nome === "") {
    responderCategoria(false, "Informe o nome da categoria.", 422);
}

$tamanhoNome = tamanhoTextoCategoria($nome);

if ($tamanhoNome < 2) {
    responderCategoria(false, "O nome da categoria precisa ter pelo menos 2 caracteres.", 422);
}

if ($tamanhoNome > 80) {
    responderCategoria(false, "O nome da categoria deve ter no maximo 80 caracteres.", 422);
}

if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/]+$/u", $nome)) {
    responderCategoria(false, "Use apenas letras, numeros, espacos e sinais simples no nome da categoria.", 422);
}

if ($descricao !== null && tamanhoTextoCategoria($descricao) > 240) {
    responderCategoria(false, "A descricao da categoria deve ter no maximo 240 caracteres.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    $consultaPreparada = $pdo->prepare("
        update public.categorias_ativos
           set nome = :nome,
               descricao = :descricao,
               atualizado_em = now()
         where id = :id
     returning
               id,
               nome,
               descricao,
               criado_em,
               atualizado_em
    ");

    $consultaPreparada->execute([
        ":id" => $id,
        ":nome" => $nome,
        ":descricao" => $descricao,
    ]);

    $categoria = $consultaPreparada->fetch();

    if (!$categoria) {
        responderCategoria(false, "Categoria nao encontrada.", 404);
    }

    responderCategoria(true, "Categoria alterada com sucesso.", 200, [
        "categoria" => $categoria,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responderCategoria(false, "Ja existe uma categoria cadastrada com este nome.", 409);
    }

    responderCategoria(false, "Nao foi possivel alterar a categoria agora.", 500);
} catch (Throwable) {
    responderCategoria(false, "Nao foi possivel alterar a categoria agora.", 500);
}
