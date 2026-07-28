<?php

declare(strict_types=1);

// Recebe a foto do cracha, salva o arquivo no servidor e grava apenas o nome no banco.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

const FOTO_CRACHA_CAMPO = "foto_cracha";
const FOTO_CRACHA_MAX_BYTES = 2097152;
const FOTO_CRACHA_PASTA = __DIR__ . "/../uploads/crachas";
const FOTO_CRACHA_URL_BASE = "../uploads/crachas/";

function responderFotoCracha(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoFotoCracha(string $nome): string
{
    $valor = $_POST[$nome] ?? "";

    if (is_array($valor)) {
        return "";
    }

    return trim((string) $valor);
}

function csrfFotoCrachaValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenRequisicao = campoFotoCracha("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenRequisicao);
}

function extensaoFotoCracha(string $caminhoTemporario): ?string
{
    $permitidos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
    ];

    $mime = "";

    if (class_exists("finfo")) {
        $informacoesArquivo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $informacoesArquivo->file($caminhoTemporario);
    } elseif (function_exists("mime_content_type")) {
        $mime = (string) mime_content_type($caminhoTemporario);
    }

    return $permitidos[$mime] ?? null;
}

function nomeArquivoFotoCracha(string $usuarioId, string $extensao): string
{
    $usuarioSeguro = preg_replace("/[^A-Za-z0-9_-]/", "", $usuarioId) ?: "usuario";

    return "cracha-" . $usuarioSeguro . "-" . bin2hex(random_bytes(8)) . "." . $extensao;
}

function nomeFotoCrachaSeguro(?string $nome): bool
{
    if ($nome === null || $nome === "") {
        return false;
    }

    return (bool) preg_match('/^cracha-[A-Za-z0-9_-]+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nome);
}

function urlFotoCracha(string $nome): string
{
    return FOTO_CRACHA_URL_BASE . rawurlencode($nome);
}

function removerFotoCrachaAntiga(?string $nome, string $nomeNovo): void
{
    // Remove apenas nomes criados pelo proprio sistema dentro da pasta esperada.
    if (!nomeFotoCrachaSeguro($nome) || $nome === $nomeNovo) {
        return;
    }

    $caminho = FOTO_CRACHA_PASTA . DIRECTORY_SEPARATOR . $nome;

    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

function mensagemErroUploadFotoCracha(int $erro): string
{
    return match ($erro) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "A imagem ultrapassa o tamanho permitido.",
        UPLOAD_ERR_PARTIAL => "O upload foi interrompido. Tente novamente.",
        UPLOAD_ERR_NO_FILE => "Selecione uma imagem para o cracha.",
        default => "Nao foi possivel receber a imagem enviada.",
    };
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderFotoCracha(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderFotoCracha(false, "Sessao expirada. Faca login novamente.", 401);
}

if (!csrfFotoCrachaValido()) {
    responderFotoCracha(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$usuario = $_SESSION["usuario"];
$usuarioId = trim((string) ($usuario["id"] ?? ""));

if ($usuarioId === "") {
    responderFotoCracha(false, "Usuario da sessao sem identificador.", 422);
}

$arquivo = $_FILES[FOTO_CRACHA_CAMPO] ?? null;

if (!is_array($arquivo)) {
    responderFotoCracha(false, "Selecione uma imagem para o cracha.", 422);
}

$erroUploadOriginal = $arquivo["error"] ?? UPLOAD_ERR_NO_FILE;

if (is_array($erroUploadOriginal)) {
    responderFotoCracha(false, "Envie apenas uma imagem por vez.", 422);
}

$erroUpload = (int) $erroUploadOriginal;

if ($erroUpload !== UPLOAD_ERR_OK) {
    responderFotoCracha(false, mensagemErroUploadFotoCracha($erroUpload), 422);
}

$caminhoTemporarioOriginal = $arquivo["tmp_name"] ?? "";
$tamanhoOriginal = $arquivo["size"] ?? 0;

if (is_array($caminhoTemporarioOriginal) || is_array($tamanhoOriginal)) {
    responderFotoCracha(false, "Envie apenas uma imagem por vez.", 422);
}

$caminhoTemporario = (string) $caminhoTemporarioOriginal;
$tamanho = (int) $tamanhoOriginal;

if ($caminhoTemporario === "" || !is_uploaded_file($caminhoTemporario)) {
    responderFotoCracha(false, "Upload invalido.", 422);
}

if ($tamanho <= 0 || $tamanho > FOTO_CRACHA_MAX_BYTES) {
    responderFotoCracha(false, "Envie uma imagem de ate 2 MB.", 422);
}

$extensao = extensaoFotoCracha($caminhoTemporario);

if ($extensao === null || @getimagesize($caminhoTemporario) === false) {
    responderFotoCracha(false, "Use uma imagem JPG, PNG ou WebP valida.", 422);
}

if (!is_dir(FOTO_CRACHA_PASTA) && !mkdir(FOTO_CRACHA_PASTA, 0775, true)) {
    responderFotoCracha(false, "Nao foi possivel preparar a pasta de uploads.", 500);
}

$nomeArquivo = nomeArquivoFotoCracha($usuarioId, $extensao);
$destino = FOTO_CRACHA_PASTA . DIRECTORY_SEPARATOR . $nomeArquivo;

if (!move_uploaded_file($caminhoTemporario, $destino)) {
    responderFotoCracha(false, "Nao foi possivel salvar a imagem no servidor.", 500);
}

@chmod($destino, 0644);

try {
    require __DIR__ . "/Conexao.php";

    $consultaPreparada = $pdo->prepare("
        update public.perfis_usuarios
           set foto_cracha = :foto_cracha,
               atualizado_em = now()
         where id = :id
     returning foto_cracha
    ");
    $consultaPreparada->execute([
        ":foto_cracha" => $nomeArquivo,
        ":id" => $usuarioId,
    ]);

    $perfil = $consultaPreparada->fetch();

    if (!is_array($perfil)) {
        @unlink($destino);
        responderFotoCracha(false, "Perfil nao encontrado.", 404);
    }

    removerFotoCrachaAntiga((string) ($usuario["foto_cracha"] ?? ""), $nomeArquivo);

    $_SESSION["usuario"] = array_merge($usuario, [
        "foto_cracha" => $nomeArquivo,
    ]);

    responderFotoCracha(true, "Foto do cracha atualizada.", 200, [
        "foto_cracha" => $nomeArquivo,
        "foto_cracha_url" => urlFotoCracha($nomeArquivo),
    ]);
} catch (Throwable) {
    @unlink($destino);
    responderFotoCracha(false, "Nao foi possivel salvar a foto no perfil.", 500);
}
