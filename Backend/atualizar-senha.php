<?php

declare(strict_types=1);

// Atualiza a senha da propria conta no Supabase Auth e no perfil local.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderAtualizacaoSenha(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        ["ok" => $sucesso, "message" => $mensagemResposta],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoAtualizacaoSenha(string $nome): string
{
    // Senhas nao sao normalizadas para nao alterar silenciosamente caracteres digitados pelo usuario.
    return (string) ($_POST[$nome] ?? "");
}

function csrfAtualizacaoSenhaValido(): bool
{
    $tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");
    $tokenEnviado = campoAtualizacaoSenha("csrf_token");

    return $tokenSessao !== ""
        && $tokenEnviado !== ""
        && hash_equals($tokenSessao, $tokenEnviado);
}

function uuidAtualizacaoSenhaValido(string $valor): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    ) === 1;
}

function novaSenhaAtendeRegras(string $senha): bool
{
    return strlen($senha) >= 8
        && strlen($senha) <= 128
        && preg_match("/[A-Z]/", $senha) === 1
        && preg_match("/\d/", $senha) === 1
        && preg_match("/[^A-Za-z0-9]/", $senha) === 1;
}

function gerarHashAtualizacaoSenha(string $senha): string
{
    $hash = password_hash($senha, PASSWORD_ARGON2ID, [
        "memory_cost" => 65536,
        "time_cost" => 4,
        "threads" => 2,
    ]);

    if ($hash === false) {
        throw new RuntimeException("Nao foi possivel gerar o hash local da senha.");
    }

    return $hash;
}

function requisicaoAutenticacaoAtualizacaoSenha(
    string $metodoHttp,
    string $url,
    string $chaveAnonima,
    array $dadosRequisicao,
    ?string $tokenAcesso = null
): array {
    $requisicaoCurl = curl_init();

    curl_setopt_array($requisicaoCurl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodoHttp,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "apikey: " . $chaveAnonima,
            "Authorization: Bearer " . ($tokenAcesso ?: $chaveAnonima),
        ],
        CURLOPT_POSTFIELDS => json_encode($dadosRequisicao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $respostaHttp = curl_exec($requisicaoCurl);
    $codigoHttp = (int) curl_getinfo($requisicaoCurl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($requisicaoCurl);

    curl_close($requisicaoCurl);

    return [
        "http_code" => $codigoHttp,
        "data" => json_decode((string) $respostaHttp, true),
        "curl_error" => $erroCurl,
    ];
}

function requisicaoAutenticacaoAtualizacaoSenhaSucesso(array $respostaHttp): bool
{
    $codigoHttp = (int) ($respostaHttp["http_code"] ?? 0);

    return $codigoHttp >= 200
        && $codigoHttp < 300
        && is_array($respostaHttp["data"] ?? null);
}

function cancelarTransacaoAtualizacaoSenha(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderAtualizacaoSenha(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderAtualizacaoSenha(false, "Sessao expirada. Entre novamente no portal.", 401);
}

if (!csrfAtualizacaoSenhaValido()) {
    responderAtualizacaoSenha(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

$senhaAtual = campoAtualizacaoSenha("senha_atual");
$novaSenha = campoAtualizacaoSenha("nova_senha");
$confirmacaoSenha = campoAtualizacaoSenha("confirmacao_senha");
$usuarioSessao = $_SESSION["usuario"];
$usuarioId = trim((string) ($usuarioSessao["id"] ?? ""));
$emailSessao = trim((string) ($usuarioSessao["email"] ?? ""));

if ($senhaAtual === "" || $novaSenha === "" || $confirmacaoSenha === "") {
    responderAtualizacaoSenha(false, "Preencha a senha atual, a nova senha e a confirmacao.", 422);
}

if (!uuidAtualizacaoSenhaValido($usuarioId) || !filter_var($emailSessao, FILTER_VALIDATE_EMAIL)) {
    responderAtualizacaoSenha(false, "Nao foi possivel identificar a conta da sessao.", 422);
}

if ($novaSenha !== $confirmacaoSenha) {
    responderAtualizacaoSenha(false, "A confirmacao da nova senha nao confere.", 422);
}

if ($novaSenha === $senhaAtual) {
    responderAtualizacaoSenha(false, "A nova senha precisa ser diferente da senha atual.", 422);
}

if (!novaSenhaAtendeRegras($novaSenha)) {
    responderAtualizacaoSenha(
        false,
        "Use pelo menos 8 caracteres, uma letra maiuscula, um numero e um caractere especial.",
        422
    );
}

$pdo = null;
$senhaAtualizadaNoSupabase = false;

try {
    require_once __DIR__ . "/config.php";
    $urlSupabase = configObrigatoria("SUPABASE_URL");
    $chaveAnonimaSupabase = configObrigatoria("SUPABASE_ANON_KEY");

    require __DIR__ . "/Conexao.php";
    $pdo->beginTransaction();

    // O bloqueio serializa duas trocas concorrentes da mesma conta.
    $consultaPerfil = $pdo->prepare("
        select id::text, email, status
          from public.perfis_usuarios
         where id = cast(:id as uuid)
           and lower(btrim(email)) = lower(btrim(:email))
         for update
    ");
    $consultaPerfil->execute([
        ":id" => $usuarioId,
        ":email" => $emailSessao,
    ]);
    $perfil = $consultaPerfil->fetch();

    if (!is_array($perfil)) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Perfil da sessao nao encontrado.", 404);
    }

    if (strtolower(trim((string) ($perfil["status"] ?? ""))) !== "ativo") {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Conta inativa. Entre em contato com um administrador.", 403);
    }

    // A senha atual e confirmada diretamente no provedor de identidade.
    $autenticacao = requisicaoAutenticacaoAtualizacaoSenha(
        "POST",
        rtrim($urlSupabase, "/") . "/auth/v1/token?grant_type=password",
        $chaveAnonimaSupabase,
        [
            "email" => $emailSessao,
            "password" => $senhaAtual,
        ]
    );

    if (($autenticacao["curl_error"] ?? "") !== "") {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Nao foi possivel validar a senha no Supabase.", 502);
    }

    if ((int) ($autenticacao["http_code"] ?? 0) === 429) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Muitas tentativas. Aguarde um pouco e tente novamente.", 429);
    }

    if (
        (int) ($autenticacao["http_code"] ?? 0) === 0
        || (int) ($autenticacao["http_code"] ?? 0) >= 500
    ) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "O Supabase esta indisponivel para validar a senha.", 502);
    }

    if (!requisicaoAutenticacaoAtualizacaoSenhaSucesso($autenticacao)) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Senha atual incorreta.", 401);
    }

    $dadosAutenticacao = $autenticacao["data"];
    $usuarioAutenticacao = is_array($dadosAutenticacao["user"] ?? null) ? $dadosAutenticacao["user"] : [];
    $idUsuarioAutenticado = strtolower(trim((string) ($usuarioAutenticacao["id"] ?? "")));

    if ($idUsuarioAutenticado === "" || !hash_equals(strtolower($usuarioId), $idUsuarioAutenticado)) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "A identidade autenticada nao corresponde a sessao atual.", 403);
    }

    $tokenAcesso = (string) ($dadosAutenticacao["access_token"] ?? "");

    if ($tokenAcesso === "") {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "O Supabase nao retornou uma sessao valida.", 502);
    }

    $consultaAtualizacaoLocal = $pdo->prepare("
        update public.perfis_usuarios
           set senha = :senha,
               atualizado_em = now()
         where id = cast(:id as uuid)
    ");
    $consultaAtualizacaoLocal->execute([
        ":senha" => gerarHashAtualizacaoSenha($novaSenha),
        ":id" => $usuarioId,
    ]);

    // O token foi emitido agora, portanto a atualizacao pertence ao proprio usuario autenticado.
    $atualizacaoAutenticacao = requisicaoAutenticacaoAtualizacaoSenha(
        "PUT",
        rtrim($urlSupabase, "/") . "/auth/v1/user",
        $chaveAnonimaSupabase,
        [
            "email" => $emailSessao,
            "current_password" => $senhaAtual,
            "password" => $novaSenha,
        ],
        $tokenAcesso
    );

    if (($atualizacaoAutenticacao["curl_error"] ?? "") !== "") {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Nao foi possivel atualizar a senha no Supabase.", 502);
    }

    if ((int) ($atualizacaoAutenticacao["http_code"] ?? 0) === 429) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "Muitas tentativas. Aguarde um pouco e tente novamente.", 429);
    }

    if (
        (int) ($atualizacaoAutenticacao["http_code"] ?? 0) === 0
        || (int) ($atualizacaoAutenticacao["http_code"] ?? 0) >= 500
    ) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        responderAtualizacaoSenha(false, "O Supabase esta indisponivel para atualizar a senha.", 502);
    }

    if (!requisicaoAutenticacaoAtualizacaoSenhaSucesso($atualizacaoAutenticacao)) {
        cancelarTransacaoAtualizacaoSenha($pdo);
        $erroAutenticacao = strtolower((string) (
            $atualizacaoAutenticacao["data"]["error_code"]
            ?? $atualizacaoAutenticacao["data"]["code"]
            ?? ""
        ));

        if (str_contains($erroAutenticacao, "same_password")) {
            responderAtualizacaoSenha(false, "A nova senha precisa ser diferente da senha atual.", 422);
        }

        if (str_contains($erroAutenticacao, "weak_password")) {
            responderAtualizacaoSenha(false, "A nova senha nao atende a politica de seguranca do Supabase.", 422);
        }

        responderAtualizacaoSenha(false, "O Supabase recusou a atualizacao da senha.", 422);
    }

    $senhaAtualizadaNoSupabase = true;

    if (!$pdo->commit()) {
        throw new RuntimeException("Nao foi possivel confirmar a atualizacao local da senha.");
    }

    // Mantem na sessao PHP os tokens obtidos na confirmacao da senha atual.
    $_SESSION["supabase"] = [
        "access_token" => $tokenAcesso,
        "refresh_token" => (string) ($dadosAutenticacao["refresh_token"] ?? ""),
        "expires_at" => time() + (int) ($dadosAutenticacao["expires_in"] ?? 0),
        "token_type" => (string) ($dadosAutenticacao["token_type"] ?? "bearer"),
    ];
    session_regenerate_id(true);

    responderAtualizacaoSenha(true, "Senha atualizada com sucesso.");
} catch (Throwable $erro) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Falha ao atualizar senha da propria conta: " . $erro->getMessage());

    if ($senhaAtualizadaNoSupabase) {
        responderAtualizacaoSenha(
            false,
            "A senha foi atualizada no Supabase, mas a sincronizacao local falhou. Entre novamente com a nova senha.",
            500
        );
    }

    responderAtualizacaoSenha(false, "Nao foi possivel atualizar a senha.", 500);
}
