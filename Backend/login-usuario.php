<?php

declare(strict_types=1);

// Login em JSON. A tela envia e-mail, senha e tipo de usuario e recebe o destino.
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/config.php";

// Credenciais publicas do Supabase Auth usadas para validar a senha do usuario.
$supabaseUrl = configObrigatoria("SUPABASE_URL");
$supabaseAnonKey = configObrigatoria("SUPABASE_ANON_KEY");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Todas as saidas passam por aqui para o frontend tratar sempre o mesmo formato.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Normaliza campos de formulario removendo espacos nas pontas.
    return trim((string)($_POST[$nome] ?? ""));
}

function tipoUsuarioValido(string $tipoUsuario): bool
{
    // Evita que o usuario force outro papel pelo HTML.
    return in_array($tipoUsuario, ["Colaborador", "Administrador"], true);
}

function emailCorporativoValido(string $email): bool
{
    // O sistema aceita apenas contas do dominio corporativo.
    return str_ends_with(strtolower($email), "@titechsolutions.com.br");
}

function caminhoAplicacao(string $arquivo): string
{
    // Monta o redirect respeitando a pasta onde o XAMPP serviu o projeto.
    $scriptPath = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $backendPath = dirname($scriptPath);
    $appPath = preg_replace("#/Backend$#", "", $backendPath) ?: "";

    return rtrim($appPath, "/") . "/" . ltrim($arquivo, "/");
}

function gerarHashSenha(string $senha): string
{
    // Guarda a senha local com Argon2ID para permitir login rapido nas proximas vezes.
    $hash = password_hash($senha, PASSWORD_ARGON2ID, [
        "memory_cost" => 65536,
        "time_cost" => 4,
        "threads" => 2,
    ]);

    if ($hash === false) {
        responder(false, "Nao foi possivel proteger a senha do usuario.", 500);
    }

    return $hash;
}

function autenticarSupabase(string $url, string $anonKey, string $email, string $senha): array
{
    // Quando a senha local nao confere, validamos direto no Supabase Auth.
    $payload = [
        "email" => $email,
        "password" => $senha,
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($url, "/") . "/auth/v1/token?grant_type=password",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "apikey: " . $anonKey,
            "Authorization: Bearer " . $anonKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        responder(false, "Erro ao comunicar com o Supabase: " . $curlError, 502);
    }

    $authData = json_decode((string)$response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($authData)) {
        responder(false, "E-mail ou senha invalidos.", 401, ["supabase_status" => $httpCode]);
    }

    return $authData;
}

function buscarPerfilPorEmail(PDO $pdo, string $email): ?array
{
    // Primeiro tentamos achar o perfil pelo e-mail informado no formulario.
    $stmt = $pdo->prepare("
        select *
          from public.perfis_usuarios
         where lower(email) = lower(:email)
         limit 1
    ");
    $stmt->execute([":email" => $email]);

    $perfil = $stmt->fetch();

    return is_array($perfil) ? $perfil : null;
}

function buscarPerfil(PDO $pdo, string $userId, string $email): ?array
{
    // Depois da autenticacao no Supabase, buscamos por id ou e-mail retornado.
    $stmt = $pdo->prepare("
        select *
          from public.perfis_usuarios
         where id = :id
            or lower(email) = lower(:email)
         limit 1
    ");
    $stmt->execute([
        ":id" => $userId,
        ":email" => $email,
    ]);

    $perfil = $stmt->fetch();

    return is_array($perfil) ? $perfil : null;
}

function atualizarSenhaPerfil(PDO $pdo, string $perfilId, string $senhaHash): void
{
    // Sincroniza o hash local quando o login foi validado pelo Supabase.
    if ($perfilId === "") {
        return;
    }

    $stmt = $pdo->prepare("
        update public.perfis_usuarios
           set senha = :senha,
               atualizado_em = now()
         where id = :id
    ");
    $stmt->execute([
        ":senha" => $senhaHash,
        ":id" => $perfilId,
    ]);
}

function criarPerfilMinimo(PDO $pdo, array $authUser): array
{
    // Se o Auth possui o usuario mas a tabela local ainda nao, criamos um perfil basico.
    $metadata = is_array($authUser["user_metadata"] ?? null) ? $authUser["user_metadata"] : [];
    $userId = (string)($authUser["id"] ?? "");
    $email = (string)($authUser["email"] ?? "");
    $nomeCompleto = trim((string)($metadata["nome_completo"] ?? $email));

    if ($userId === "" || $email === "") {
        responder(false, "Nao foi possivel identificar o usuario autenticado.", 500);
    }

    $stmt = $pdo->prepare("
        insert into public.perfis_usuarios (
            id,
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            empresa,
            rg,
            cpf,
            celular,
            data_nascimento,
            status
        ) values (
            :id,
            :nome_completo,
            :email,
            :tipo_usuario,
            :departamento,
            :empresa,
            :rg,
            :cpf,
            :celular,
            :data_nascimento,
            'Ativo'
        )
        on conflict (id) do update set
            email = excluded.email,
            tipo_usuario = coalesce(public.perfis_usuarios.tipo_usuario, excluded.tipo_usuario),
            status = coalesce(public.perfis_usuarios.status, 'Ativo'),
            atualizado_em = now()
        returning *
    ");

    $stmt->execute([
        ":id" => $userId,
        ":nome_completo" => $nomeCompleto,
        ":email" => $email,
        ":tipo_usuario" => "Colaborador",
        ":departamento" => (string)($metadata["departamento"] ?? ""),
        ":empresa" => (string)($metadata["empresa"] ?? ""),
        ":rg" => (string)($metadata["rg"] ?? ""),
        ":cpf" => (string)($metadata["cpf"] ?? ""),
        ":celular" => (string)($metadata["celular"] ?? ""),
        ":data_nascimento" => ($metadata["data_nascimento"] ?? null) ?: null,
    ]);

    $perfil = $stmt->fetch();

    if (!is_array($perfil)) {
        responder(false, "Nao foi possivel carregar o perfil do usuario.", 500);
    }

    return $perfil;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

// Dados enviados pelo formulario de login.
$email = campo("email");
$senha = (string)($_POST["senha"] ?? "");
$tipoUsuario = campo("tipo_usuario");

if ($email === "" || $senha === "" || $tipoUsuario === "") {
    responder(false, "Preencha e-mail, senha e tipo de acesso.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(false, "Digite um e-mail valido.", 422);
}

if (!emailCorporativoValido($email)) {
    responder(false, "Use um e-mail corporativo autorizado.", 422);
}

if (!tipoUsuarioValido($tipoUsuario)) {
    responder(false, "Tipo de acesso invalido.", 422);
}

try {
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/grupos-acesso-util.php";

    // Comecamos pelo perfil local; se ele nao resolver a senha, caimos para Supabase.
    $authData = null;
    $perfil = buscarPerfilPorEmail($pdo, $email);
    $authEmail = (string)($perfil["email"] ?? $email);
    $senhaPrecisaAtualizar = false;
    $senhaHashAtual = (string)($perfil["senha"] ?? "");

    if ($perfil && $senhaHashAtual !== "" && password_verify($senha, $senhaHashAtual)) {
        // Senha local valida: so verificamos se o hash precisa ser atualizado.
        $senhaPrecisaAtualizar = password_needs_rehash($senhaHashAtual, PASSWORD_ARGON2ID, [
            "memory_cost" => 65536,
            "time_cost" => 4,
            "threads" => 2,
        ]);
    } else {
        // Senha local ausente ou invalida: Supabase decide se o login e verdadeiro.
        $authData = autenticarSupabase($supabaseUrl, $supabaseAnonKey, $email, $senha);
        $authUser = is_array($authData["user"] ?? null) ? $authData["user"] : [];
        $userId = (string)($authUser["id"] ?? "");
        $authEmail = (string)($authUser["email"] ?? $email);

        if ($userId === "") {
            responder(false, "Nao foi possivel identificar o usuario autenticado.", 500);
        }

        $perfil = buscarPerfil($pdo, $userId, $authEmail);

        if (!$perfil) {
            $perfil = criarPerfilMinimo($pdo, $authUser);
        }

        $senhaPrecisaAtualizar = true;
    }

    $status = strtolower(trim((string)($perfil["status"] ?? "")));

    if ($status !== "ativo") {
        unset($_SESSION["usuario"], $_SESSION["supabase"]);
        responder(false, "Conta inativa. Solicite ajuda a um administrador para reativar o acesso.", 403, [
            "reason" => "inactive_account",
        ]);
    }

    // Confere se o tipo escolhido no login bate com o perfil cadastrado.
    if ((string)($perfil["tipo_usuario"] ?? "") !== $tipoUsuario) {
        responder(false, "Tipo de acesso nao autorizado para este usuario.", 403);
    }

    if ($senhaPrecisaAtualizar) {
        atualizarSenhaPerfil($pdo, (string)($perfil["id"] ?? ""), gerarHashSenha($senha));
    }

    // Regenera a sessao para reduzir risco de fixacao de sessao apos login.
    session_regenerate_id(true);

    // Dados minimos usados pelas paginas protegidas e pela sidebar.
    $_SESSION["usuario"] = [
        "id" => (string)$perfil["id"],
        "nome_completo" => (string)($perfil["nome_completo"] ?? ""),
        "email" => (string)($perfil["email"] ?? $authEmail),
        "tipo_usuario" => (string)$perfil["tipo_usuario"],
        "departamento" => (string)($perfil["departamento"] ?? ""),
        "empresa" => (string)($perfil["empresa"] ?? ""),
        "status" => (string)$perfil["status"],
    ];
    $_SESSION["usuario"]["permissoes_grupos"] = permissoesUsuarioGrupoAcesso($pdo, $_SESSION["usuario"]);

    if (is_array($authData)) {
        // Guardamos tokens quando a autenticacao veio do Supabase.
        $_SESSION["supabase"] = [
            "access_token" => (string)($authData["access_token"] ?? ""),
            "refresh_token" => (string)($authData["refresh_token"] ?? ""),
            "expires_at" => time() + (int)($authData["expires_in"] ?? 0),
            "token_type" => (string)($authData["token_type"] ?? "bearer"),
        ];
    } else {
        unset($_SESSION["supabase"]);
    }

    responder(true, "Login realizado com sucesso.", 200, [
        "redirect" => caminhoAplicacao("pagina-inicial.php"),
    ]);
} catch (Throwable $erro) {
    responder(false, "Nao foi possivel validar o acesso agora.", 500);
}
