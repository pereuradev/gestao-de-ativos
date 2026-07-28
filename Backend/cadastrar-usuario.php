<?php

declare(strict_types=1);

// Cadastro de usuarios. Cria a conta no Supabase Auth e salva o perfil local.
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// Carrega a configuração da aplicação antes de inicializar esta dependência.
require_once __DIR__ . "/config.php";

// Chaves usadas para chamar o endpoint de signup do Supabase.
$urlSupabase = configObrigatoria("SUPABASE_URL");
$chaveAnonimaSupabase = configObrigatoria("SUPABASE_ANON_KEY");

function responder(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    // Mantem o mesmo formato de resposta para erro e sucesso.
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function campo(string $nome, string $padrao = ""): string
{
    // Le campo de formulario e remove espacos extras nas pontas.
    return trim((string) ($_POST[$nome] ?? $padrao));
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

    for ($indiceDigito = 0; $indiceDigito < 9; $indiceDigito++) {
        $soma += (int) $cpf[$indiceDigito] * (10 - $indiceDigito);
    }

    $primeiroDigito = ($soma * 10) % 11;

    if ($primeiroDigito === 10) {
        $primeiroDigito = 0;
    }

    if ($primeiroDigito !== (int) $cpf[9]) {
        return false;
    }

    $soma = 0;

    for ($indiceDigito = 0; $indiceDigito < 10; $indiceDigito++) {
        $soma += (int) $cpf[$indiceDigito] * (11 - $indiceDigito);
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

function criarUsuarioSupabase(string $url, string $chaveAnonima, array $dadosRequisicao): array
{
    // O Supabase Auth fica responsavel pela identidade principal do usuario.
    $requisicaoCurl = curl_init();

    curl_setopt_array($requisicaoCurl, [
        CURLOPT_URL => rtrim($url, "/") . "/auth/v1/signup",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "apikey: " . $chaveAnonima,
            "Authorization: Bearer " . $chaveAnonima,
        ],
        CURLOPT_POSTFIELDS => json_encode($dadosRequisicao, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $respostaHttp = curl_exec($requisicaoCurl);
    $codigoHttp = (int) curl_getinfo($requisicaoCurl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($requisicaoCurl);

    curl_close($requisicaoCurl);

    if ($erroCurl) {
        responder(false, "Erro ao comunicar com o Supabase: " . $erroCurl, 502);
    }

    $dadosAutenticacao = json_decode((string) $respostaHttp, true);

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        $mensagemResposta = $dadosAutenticacao["msg"] ?? $dadosAutenticacao["message"] ?? "Erro ao criar usuario no Supabase Auth.";

        if (stripos($mensagemResposta, "already") !== false || stripos($mensagemResposta, "registered") !== false) {
            return ["auth_email_existente" => true];
        }

        responder(false, $mensagemResposta, 400, ["supabase_status" => $codigoHttp]);
    }

    return is_array($dadosAutenticacao) ? $dadosAutenticacao : [];
}

function autenticarUsuarioSupabase(string $url, string $chaveAnonima, string $email, string $senha): array
{
    // Quando o Auth ja tem a conta mas o perfil local falhou, o login recupera o ID para completar o perfil.
    $requisicaoCurl = curl_init();

    curl_setopt_array($requisicaoCurl, [
        CURLOPT_URL => rtrim($url, "/") . "/auth/v1/token?grant_type=password",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "apikey: " . $chaveAnonima,
            "Authorization: Bearer " . $chaveAnonima,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "email" => $email,
            "password" => $senha,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $respostaHttp = curl_exec($requisicaoCurl);
    $codigoHttp = (int) curl_getinfo($requisicaoCurl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($requisicaoCurl);

    curl_close($requisicaoCurl);

    if ($erroCurl) {
        responder(false, "Erro ao comunicar com o Supabase: " . $erroCurl, 502);
    }

    $dadosAutenticacao = json_decode((string) $respostaHttp, true);

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        return [];
    }

    return is_array($dadosAutenticacao) ? $dadosAutenticacao : [];
}

function buscarUsuarioAutenticacaoPorEmail(PDO $pdo, string $email): ?string
{
    $consultaPreparada = $pdo->prepare("
        select id::text
          from auth.users
         where lower(btrim(email)) = lower(btrim(:email))
         limit 1
    ");
    $consultaPreparada->execute([":email" => $email]);

    $id = $consultaPreparada->fetchColumn();

    return is_string($id) && $id !== "" ? $id : null;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Entre novamente no portal.", 401, [
        "redirect" => "../pages/Pagina-login.html?sessao=expirada",
    ]);
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_funcionarios", "Cadastro de funcionarios");

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
    // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
    require_once __DIR__ . "/Conexao.php";

    // Antes de chamar o Auth, verificamos duplicidade nos dados locais principais.
    $consultaPreparada = $pdo->prepare("
        select email, cpf, rg
        from public.perfis_usuarios
        where lower(btrim(email)) = lower(btrim(:email))
           or regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
           or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
        limit 1
    ");
    $consultaPreparada->execute([
        ":email" => $email,
        ":cpf" => $cpf,
        ":rg" => $rg,
    ]);

    $usuarioExistente = $consultaPreparada->fetch();

    if ($usuarioExistente) {
        if (strcasecmp(trim((string) ($usuarioExistente["email"] ?? "")), trim($email)) === 0) {
            responder(false, "Este e-mail ja esta cadastrado.", 409);
        }

        if (apenasNumeros((string) ($usuarioExistente["cpf"] ?? "")) === apenasNumeros($cpf)) {
            responder(false, "Este CPF ja esta cadastrado.", 409);
        }

        if (apenasNumeros((string) ($usuarioExistente["rg"] ?? "")) === apenasNumeros($rg)) {
            responder(false, "Este RG ja esta cadastrado.", 409);
        }
    }
} catch (Throwable $erro) {
    responder(false, "Erro ao consultar o banco de dados.", 500);
}

$metadados = [
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

$dadosAutenticacao = criarUsuarioSupabase($urlSupabase, $chaveAnonimaSupabase, [
    // Cadastro no Auth: e-mail e senha ficam no provedor de autenticacao.
    "email" => $email,
    "password" => $senha,
    "data" => $metadados,
]);

$idUsuarioAutenticacao = $dadosAutenticacao["user"]["id"] ?? $dadosAutenticacao["id"] ?? null;

if (!$idUsuarioAutenticacao && !empty($dadosAutenticacao["auth_email_existente"])) {
    $idUsuarioAutenticacao = buscarUsuarioAutenticacaoPorEmail($pdo, $email);

    if (!$idUsuarioAutenticacao) {
        $dadosAutenticacao = autenticarUsuarioSupabase($urlSupabase, $chaveAnonimaSupabase, $email, $senha);
        $idUsuarioAutenticacao = $dadosAutenticacao["user"]["id"] ?? $dadosAutenticacao["id"] ?? null;
    }
}

if (!$idUsuarioAutenticacao) {
    $idUsuarioAutenticacao = gerarUuidLocal();
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

    $parametrosConsulta = [
        ":id" => $idUsuarioAutenticacao,
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
            $consultaPreparada = $pdo->prepare($sql);
            $consultaPreparada->execute($parametrosConsulta);
            $usuarioCriado = $consultaPreparada->fetch();
            break;
        } catch (PDOException $erroPerfil) {
            $tentativas++;
            $erroChaveEstrangeiraAutenticacao = $erroPerfil->getCode() === "23503"
                && str_contains($erroPerfil->getMessage(), "perfis_usuarios_id_fkey");

            if (!$erroChaveEstrangeiraAutenticacao || $tentativas >= 4) {
                throw $erroPerfil;
            }

            // O Supabase Auth pode levar alguns milissegundos para refletir o usuario na conexao SQL.
            usleep(250000 * $tentativas);
        }
    } while ($tentativas < 4);
} catch (Throwable $erro) {
    $mensagemResposta = $erro->getMessage();

    if (str_contains($mensagemResposta, "perfis_usuarios_email_key")) {
        responder(false, "Este e-mail ja esta cadastrado.", 409);
    }

    if (str_contains($mensagemResposta, "perfis_usuarios_cpf_key")) {
        responder(false, "Este CPF ja esta cadastrado.", 409);
    }

    if (str_contains($mensagemResposta, "perfis_usuarios_rg_key")) {
        responder(false, "Este RG ja esta cadastrado.", 409);
    }

    error_log("Erro ao salvar perfil de usuario {$email}: " . $mensagemResposta);
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
    "redirect" => "../pages/cadastro-funcionarios.php",
    "usuario" => $usuarioResposta,
]);
