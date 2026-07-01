<?php

declare(strict_types=1);

// Cadastro de usuarios. Cria a conta no Supabase Auth e salva o perfil local.
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/config.php";

// Chaves usadas para chamar o endpoint de signup do Supabase.
$supabaseUrl = configObrigatoria("SUPABASE_URL");
$supabaseAnonKey = configObrigatoria("SUPABASE_ANON_KEY");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Mantem o mesmo formato de resposta para erro e sucesso.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function campo(string $nome, string $padrao = ""): string
{
    // Le campo de formulario e remove espacos extras nas pontas.
    return trim((string) ($_POST[$nome] ?? $padrao));
}

function usuarioAdministrador(): bool
{
    $tipoUsuario = strtolower(trim((string) ($_SESSION["usuario"]["tipo_usuario"] ?? "")));

    return in_array($tipoUsuario, ["adm", "admin", "administrador"], true);
}

function csrfValido(): bool
{
    $tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");
    $tokenEnviado = (string) ($_POST["csrf_token"] ?? "");

    return $tokenSessao !== "" && $tokenEnviado !== "" && hash_equals($tokenSessao, $tokenEnviado);
}

function apenasNumeros(string $valor): string
{
    // Usado para validar RG, CPF e celular sem mascara.
    return preg_replace("/\D+/", "", $valor) ?? "";
}

function cpfValido(string $valor): bool
{
    // Valida o CPF pelo calculo dos dois digitos verificadores.
    $cpf = apenasNumeros($valor);

    if (strlen($cpf) !== 11) {
        return false;
    }

    if (preg_match("/^(\d)\1{10}$/", $cpf)) {
        return false;
    }

    $soma = 0;

    for ($i = 0; $i < 9; $i++) {
        $soma += (int) $cpf[$i] * (10 - $i);
    }

    $primeiroDigito = ($soma * 10) % 11;

    if ($primeiroDigito === 10) {
        $primeiroDigito = 0;
    }

    if ($primeiroDigito !== (int) $cpf[9]) {
        return false;
    }

    $soma = 0;

    for ($i = 0; $i < 10; $i++) {
        $soma += (int) $cpf[$i] * (11 - $i);
    }

    $segundoDigito = ($soma * 10) % 11;

    if ($segundoDigito === 10) {
        $segundoDigito = 0;
    }

    return $segundoDigito === (int) $cpf[10];
}

function validarCampoPermitido(string $valor, array $permitidos, string $padrao): string
{
    // Evita valores fora das listas esperadas quando o HTML e manipulado.
    return in_array($valor, $permitidos, true) ? $valor : $padrao;
}

function emailCorporativoValido(string $email): bool
{
    // Restringe cadastro ao dominio corporativo.
    return str_ends_with(strtolower($email), "@titechsolutions.com.br");
}

function gerarHashSenha(string $senha): string
{
    // Salva um hash local seguro para facilitar validacoes futuras.
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

function gerarUuidLocal(): string
{
    $dados = random_bytes(16);
    $dados[6] = chr((ord($dados[6]) & 0x0f) | 0x40);
    $dados[8] = chr((ord($dados[8]) & 0x3f) | 0x80);
    $hex = bin2hex($dados);

    return sprintf(
        "%s-%s-%s-%s-%s",
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20)
    );
}

function criarUsuarioSupabase(string $url, string $anonKey, array $payload): array
{
    // O Supabase Auth fica responsavel pela identidade principal do usuario.
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($url, "/") . "/auth/v1/signup",
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
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        responder(false, "Erro ao comunicar com o Supabase: " . $curlError, 502);
    }

    $authData = json_decode((string) $response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $authData["msg"] ?? $authData["message"] ?? "Erro ao criar usuario no Supabase Auth.";

        if (stripos($message, "already") !== false || stripos($message, "registered") !== false) {
            return ["auth_email_existente" => true];
        }

        responder(false, $message, 400, ["supabase_status" => $httpCode]);
    }

    return is_array($authData) ? $authData : [];
}

function autenticarUsuarioSupabase(string $url, string $anonKey, string $email, string $senha): array
{
    // Quando o Auth ja tem a conta mas o perfil local falhou, o login recupera o ID para completar o perfil.
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
        CURLOPT_POSTFIELDS => json_encode([
            "email" => $email,
            "password" => $senha,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        responder(false, "Erro ao comunicar com o Supabase: " . $curlError, 502);
    }

    $authData = json_decode((string) $response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [];
    }

    return is_array($authData) ? $authData : [];
}

function buscarUsuarioAuthPorEmail(PDO $pdo, string $email): ?string
{
    $stmt = $pdo->prepare("
        select id::text
          from auth.users
         where lower(email) = lower(:email)
         limit 1
    ");
    $stmt->execute([":email" => $email]);

    $id = $stmt->fetchColumn();

    return is_string($id) && $id !== "" ? $id : null;
}

function liberarPerfilLocalDoAuth(PDO $pdo): void
{
    // O login do portal valida primeiro a senha local do perfil.
    // Por isso o cadastro interno nao pode depender da sincronizacao imediata do Supabase Auth.
    $pdo->exec("
        alter table public.perfis_usuarios
        drop constraint if exists perfis_usuarios_id_fkey
    ");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Entre novamente no portal.", 401, [
        "redirect" => "../Pagina-login.html?sessao=expirada",
    ]);
}

if (!usuarioAdministrador()) {
    responder(false, "Apenas administradores podem cadastrar funcionarios.", 403);
}

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 419);
}

// Coleta e normaliza todos os campos enviados pelo formulario de cadastro.
$nomeCompleto = campo("nome_completo");
$email = campo("email");
$senha = (string) ($_POST["senha"] ?? "");
$tipoUsuario = validarCampoPermitido(
    campo("tipo_usuario", "Colaborador"),
    ["Colaborador", "Administrador"],
    "Colaborador"
);
$departamento = validarCampoPermitido(
    campo("departamento"),
    ["TI", "Operacao", "Financeiro", "Administrativo", "Gestao"],
    ""
);
$empresa = campo("empresa");
$rg = campo("rg");
$cpf = campo("cpf");
$celular = campo("celular");
$dataNascimento = campo("data_nascimento");

if (
    // O cadastro exige dados completos porque eles aparecem no perfil e na sidebar.
    $nomeCompleto === "" ||
    $email === "" ||
    $senha === "" ||
    $rg === "" ||
    $cpf === "" ||
    $celular === "" ||
    $dataNascimento === "" ||
    $departamento === "" ||
    $empresa === ""
) {
    responder(false, "Preencha todos os campos para continuar.", 422);
}

if (count(preg_split("/\s+/", $nomeCompleto, -1, PREG_SPLIT_NO_EMPTY)) < 2) {
    responder(false, "Informe nome e sobrenome.", 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(false, "Digite um e-mail valido.", 422);
}

if (!emailCorporativoValido($email)) {
    responder(false, "Use um e-mail corporativo autorizado.", 422);
}

if (strlen(apenasNumeros($rg)) < 7) {
    responder(false, "Informe um RG valido.", 422);
}

if (!cpfValido($cpf)) {
    responder(false, "Informe um CPF valido.", 422);
}

if (strlen(apenasNumeros($celular)) !== 11) {
    responder(false, "Informe um telefone celular valido com DDD.", 422);
}

$nascimento = DateTime::createFromFormat("Y-m-d", $dataNascimento);

if (!$nascimento || $nascimento > new DateTime("today")) {
    responder(false, "Informe uma data de nascimento valida.", 422);
}

if (strlen($senha) < 6) {
    responder(false, "A senha precisa ter pelo menos 6 caracteres.", 422);
}

$senhaHash = gerarHashSenha($senha);

try {
    require_once __DIR__ . "/Conexao.php";
    liberarPerfilLocalDoAuth($pdo);

    // Antes de chamar o Auth, verificamos duplicidade nos dados locais principais.
    $stmt = $pdo->prepare("
        select email, cpf, rg
        from public.perfis_usuarios
        where email = :email
           or cpf = :cpf
           or rg = :rg
        limit 1
    ");
    $stmt->execute([
        ":email" => $email,
        ":cpf" => $cpf,
        ":rg" => $rg,
    ]);

    $usuarioExistente = $stmt->fetch();

    if ($usuarioExistente) {
        if (($usuarioExistente["email"] ?? "") === $email) {
            responder(false, "Este e-mail ja esta cadastrado.", 409);
        }

        if (($usuarioExistente["cpf"] ?? "") === $cpf) {
            responder(false, "Este CPF ja esta cadastrado.", 409);
        }

        if (($usuarioExistente["rg"] ?? "") === $rg) {
            responder(false, "Este RG ja esta cadastrado.", 409);
        }
    }
} catch (Throwable $erro) {
    responder(false, "Erro ao consultar o banco de dados.", 500);
}

$metadata = [
    // Esses dados acompanham o usuario no Supabase e ajudam a reconstruir o perfil.
    "nome_completo" => $nomeCompleto,
    "tipo_usuario" => $tipoUsuario,
    "departamento" => $departamento,
    "empresa" => $empresa,
    "rg" => $rg,
    "cpf" => $cpf,
    "celular" => $celular,
    "data_nascimento" => $dataNascimento,
];

$authData = criarUsuarioSupabase($supabaseUrl, $supabaseAnonKey, [
    // Cadastro no Auth: e-mail e senha ficam no provedor de autenticacao.
    "email" => $email,
    "password" => $senha,
    "data" => $metadata,
]);

$userId = $authData["user"]["id"] ?? $authData["id"] ?? null;

if (!$userId && !empty($authData["auth_email_existente"])) {
    $userId = buscarUsuarioAuthPorEmail($pdo, $email);

    if (!$userId) {
        $authData = autenticarUsuarioSupabase($supabaseUrl, $supabaseAnonKey, $email, $senha);
        $userId = $authData["user"]["id"] ?? $authData["id"] ?? null;
    }
}

if (!$userId) {
    $userId = gerarUuidLocal();
}

try {
    // Depois do Auth, gravamos ou atualizamos o perfil na tabela local.
    $sql = "
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
            senha,
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
            :senha,
            'Ativo'
        )
        on conflict (id) do update set
            nome_completo = excluded.nome_completo,
            email = excluded.email,
            tipo_usuario = excluded.tipo_usuario,
            departamento = excluded.departamento,
            empresa = excluded.empresa,
            rg = excluded.rg,
            cpf = excluded.cpf,
            celular = excluded.celular,
            data_nascimento = excluded.data_nascimento,
            senha = excluded.senha,
            status = 'Ativo',
            atualizado_em = now()
        returning
            id,
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            empresa,
            celular,
            status,
            criado_em,
            atualizado_em
    ";

    $params = [
        ":id" => $userId,
        ":nome_completo" => $nomeCompleto,
        ":email" => $email,
        ":tipo_usuario" => $tipoUsuario,
        ":departamento" => $departamento,
        ":empresa" => $empresa,
        ":rg" => $rg,
        ":cpf" => $cpf,
        ":celular" => $celular,
        ":data_nascimento" => $dataNascimento,
        ":senha" => $senhaHash,
    ];

    $tentativas = 0;

    do {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $usuarioCriado = $stmt->fetch();
            break;
        } catch (PDOException $erroPerfil) {
            $tentativas++;
            $erroForeignKeyAuth = $erroPerfil->getCode() === "23503"
                && str_contains($erroPerfil->getMessage(), "perfis_usuarios_id_fkey");

            if (!$erroForeignKeyAuth || $tentativas >= 4) {
                throw $erroPerfil;
            }

            // O Supabase Auth pode levar alguns milissegundos para refletir o usuario na conexao SQL.
            usleep(250000 * $tentativas);
        }
    } while ($tentativas < 4);
} catch (Throwable $erro) {
    $message = $erro->getMessage();

    if (str_contains($message, "perfis_usuarios_email_key")) {
        responder(false, "Este e-mail ja esta cadastrado.", 409);
    }

    if (str_contains($message, "perfis_usuarios_cpf_key")) {
        responder(false, "Este CPF ja esta cadastrado.", 409);
    }

    if (str_contains($message, "perfis_usuarios_rg_key")) {
        responder(false, "Este RG ja esta cadastrado.", 409);
    }

    error_log("Erro ao salvar perfil de usuario {$email}: " . $message);
    responder(false, "Usuario criado no Auth, mas houve erro ao salvar o perfil.", 500);
}

$usuarioResposta = is_array($usuarioCriado ?? null) ? [
    "id" => (string) ($usuarioCriado["id"] ?? ""),
    "nome_completo" => (string) ($usuarioCriado["nome_completo"] ?? $nomeCompleto),
    "email" => (string) ($usuarioCriado["email"] ?? $email),
    "tipo_usuario" => (string) ($usuarioCriado["tipo_usuario"] ?? $tipoUsuario),
    "departamento" => (string) ($usuarioCriado["departamento"] ?? $departamento),
    "empresa" => (string) ($usuarioCriado["empresa"] ?? $empresa),
    "celular" => (string) ($usuarioCriado["celular"] ?? $celular),
    "status" => (string) ($usuarioCriado["status"] ?? "Ativo"),
    "criado_em" => (string) ($usuarioCriado["criado_em"] ?? ""),
    "atualizado_em" => (string) ($usuarioCriado["atualizado_em"] ?? ""),
] : [];

responder(true, "Usuario cadastrado com sucesso.", 201, [
    "redirect" => "../cadastro-funcionarios.php",
    "usuario" => $usuarioResposta,
]);
