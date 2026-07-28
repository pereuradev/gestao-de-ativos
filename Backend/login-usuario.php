<?php

declare(strict_types=1);

// Login em JSON. A tela envia e-mail, senha e tipo de usuario e recebe o destino.
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// Carrega a configuração da aplicação antes de inicializar esta dependência.
require_once __DIR__ . "/config.php";

// Credenciais publicas do Supabase Auth usadas para validar a senha do usuario.
$urlSupabase = configObrigatoria("SUPABASE_URL");
$chaveAnonimaSupabase = configObrigatoria("SUPABASE_ANON_KEY");

function responder(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    // Todas as saidas passam por aqui para o frontend tratar sempre o mesmo formato.
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
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

function preferenciaUsuario(array $perfil, string $campo, array $permitidos, string $padrao): string
{
    $valor = trim((string)($perfil[$campo] ?? ""));

    return in_array($valor, $permitidos, true) ? $valor : $padrao;
}

function corPreferenciaUsuario(array $perfil): string
{
    $valor = trim((string)($perfil["preferencia_cor"] ?? ""));

    if (in_array($valor, ["teal", "green", "blue", "violet"], true)) {
        return $valor;
    }

    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $valor)) {
        return strtolower($valor);
    }

    return "teal";
}

function preferenciasUsuario(array $perfil): array
{
    // Estes nomes sao os mesmos usados pelo JavaScript para aplicar a interface.
    return [
        "theme" => preferenciaUsuario($perfil, "preferencia_tema", ["dark", "light", "auto"], "dark"),
        "accent" => corPreferenciaUsuario($perfil),
        "fontSize" => preferenciaUsuario($perfil, "preferencia_tamanho_fonte", ["small", "default", "large", "extra"], "default"),
        "density" => preferenciaUsuario($perfil, "preferencia_densidade", ["comfortable", "compact"], "comfortable"),
        "motion" => preferenciaUsuario($perfil, "preferencia_movimento", ["normal", "reduced"], "normal"),
        "cursor" => preferenciaUsuario($perfil, "preferencia_cursor", ["enhanced", "normal"], "enhanced"),
    ];
}

function caminhoAplicacao(string $arquivo): string
{
    // Monta o redirect respeitando a pasta onde o XAMPP serviu o projeto.
    $caminhoScript = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $caminhoServidor = dirname($caminhoScript);
    $caminhoBaseAplicacao = preg_replace("#/Backend$#", "", $caminhoServidor) ?: "";

    return rtrim($caminhoBaseAplicacao, "/") . "/" . ltrim($arquivo, "/");
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

function autenticarSupabase(string $url, string $chaveAnonima, string $email, string $senha): array
{
    // Quando a senha local nao confere, validamos direto no Supabase Auth.
    $dadosRequisicao = [
        "email" => $email,
        "password" => $senha,
    ];

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
        CURLOPT_POSTFIELDS => json_encode($dadosRequisicao, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $respostaHttp = curl_exec($requisicaoCurl);
    $codigoHttp = (int)curl_getinfo($requisicaoCurl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($requisicaoCurl);

    curl_close($requisicaoCurl);

    if ($erroCurl) {
        responder(false, "Erro ao comunicar com o Supabase: " . $erroCurl, 502);
    }

    $dadosAutenticacao = json_decode((string)$respostaHttp, true);

    if ($codigoHttp < 200 || $codigoHttp >= 300 || !is_array($dadosAutenticacao)) {
        responder(false, "E-mail ou senha invalidos.", 401, ["supabase_status" => $codigoHttp]);
    }

    return $dadosAutenticacao;
}

function buscarPerfilPorEmail(PDO $pdo, string $email): ?array
{
    // Primeiro tentamos achar o perfil pelo e-mail informado no formulario.
    $consultaPreparada = $pdo->prepare("
        select *
          from public.perfis_usuarios
         where lower(btrim(email)) = lower(btrim(:email))
         limit 1
    ");
    $consultaPreparada->execute([":email" => $email]);

    $perfil = $consultaPreparada->fetch();

    return is_array($perfil) ? $perfil : null;
}

function buscarPerfil(PDO $pdo, string $idUsuarioAutenticacao, string $email): ?array
{
    // Depois da autenticacao no Supabase, buscamos por id ou e-mail retornado.
    $consultaPreparada = $pdo->prepare("
        select *
          from public.perfis_usuarios
         where id = :id
            or lower(btrim(email)) = lower(btrim(:email))
         limit 1
    ");
    $consultaPreparada->execute([
        ":id" => $idUsuarioAutenticacao,
        ":email" => $email,
    ]);

    $perfil = $consultaPreparada->fetch();

    return is_array($perfil) ? $perfil : null;
}

function atualizarSenhaPerfil(PDO $pdo, string $perfilId, string $senhaHash): void
{
    // Sincroniza o hash local quando o login foi validado pelo Supabase.
    if ($perfilId === "") {
        return;
    }

    $consultaPreparada = $pdo->prepare("
        update public.perfis_usuarios
           set senha = :senha,
               atualizado_em = now()
         where id = :id
    ");
    $consultaPreparada->execute([
        ":senha" => $senhaHash,
        ":id" => $perfilId,
    ]);
}

function criarPerfilMinimo(PDO $pdo, array $usuarioAutenticacao): array
{
    // Se o Auth possui o usuario mas a tabela local ainda nao, criamos um perfil basico.
    $metadados = is_array($usuarioAutenticacao["user_metadata"] ?? null) ? $usuarioAutenticacao["user_metadata"] : [];
    $idUsuarioAutenticacao = (string)($usuarioAutenticacao["id"] ?? "");
    $email = (string)($usuarioAutenticacao["email"] ?? "");
    $nomeCompleto = trim((string)($metadados["nome_completo"] ?? $email));

    if ($idUsuarioAutenticacao === "" || $email === "") {
        responder(false, "Nao foi possivel identificar o usuario autenticado.", 500);
    }

    $consultaPreparada = $pdo->prepare("
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

    $consultaPreparada->execute([
        ":id" => $idUsuarioAutenticacao,
        ":nome_completo" => $nomeCompleto,
        ":email" => $email,
        ":tipo_usuario" => "Colaborador",
        ":departamento" => (string)($metadados["departamento"] ?? ""),
        ":empresa" => (string)($metadados["empresa"] ?? ""),
        ":rg" => (string)($metadados["rg"] ?? ""),
        ":cpf" => (string)($metadados["cpf"] ?? ""),
        ":celular" => (string)($metadados["celular"] ?? ""),
        ":data_nascimento" => ($metadados["data_nascimento"] ?? null) ?: null,
    ]);

    $perfil = $consultaPreparada->fetch();

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
    // Carrega a conexão e as regras compartilhadas de grupos e permissões.
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/grupos-acesso-util.php";

    // Comecamos pelo perfil local; se ele nao resolver a senha, caimos para Supabase.
    $dadosAutenticacao = null;
    $perfil = buscarPerfilPorEmail($pdo, $email);
    $emailAutenticacao = (string)($perfil["email"] ?? $email);
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
        $dadosAutenticacao = autenticarSupabase($urlSupabase, $chaveAnonimaSupabase, $email, $senha);
        $usuarioAutenticacao = is_array($dadosAutenticacao["user"] ?? null) ? $dadosAutenticacao["user"] : [];
        $idUsuarioAutenticacao = (string)($usuarioAutenticacao["id"] ?? "");
        $emailAutenticacao = (string)($usuarioAutenticacao["email"] ?? $email);

        if ($idUsuarioAutenticacao === "") {
            responder(false, "Nao foi possivel identificar o usuario autenticado.", 500);
        }

        $perfil = buscarPerfil($pdo, $idUsuarioAutenticacao, $emailAutenticacao);

        if (!$perfil) {
            $perfil = criarPerfilMinimo($pdo, $usuarioAutenticacao);
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

    $preferencias = preferenciasUsuario($perfil);

    // Dados minimos usados pelas paginas protegidas e pela sidebar.
    $_SESSION["usuario"] = [
        "id" => (string)$perfil["id"],
        "nome_completo" => (string)($perfil["nome_completo"] ?? ""),
        "email" => (string)($perfil["email"] ?? $emailAutenticacao),
        "tipo_usuario" => (string)$perfil["tipo_usuario"],
        "departamento" => (string)($perfil["departamento"] ?? ""),
        "empresa" => (string)($perfil["empresa"] ?? ""),
        "status" => (string)$perfil["status"],
        "foto_cracha" => (string)($perfil["foto_cracha"] ?? ""),
        "preferencia_tema" => $preferencias["theme"],
        "preferencia_cor" => $preferencias["accent"],
        "preferencia_tamanho_fonte" => $preferencias["fontSize"],
        "preferencia_densidade" => $preferencias["density"],
        "preferencia_movimento" => $preferencias["motion"],
        "preferencia_cursor" => $preferencias["cursor"],
    ];
    $_SESSION["usuario"]["permissoes_grupos"] = permissoesUsuarioGrupoAcesso($pdo, $_SESSION["usuario"]);

    if (is_array($dadosAutenticacao)) {
        // Guardamos tokens quando a autenticacao veio do Supabase.
        $_SESSION["supabase"] = [
            "access_token" => (string)($dadosAutenticacao["access_token"] ?? ""),
            "refresh_token" => (string)($dadosAutenticacao["refresh_token"] ?? ""),
            "expires_at" => time() + (int)($dadosAutenticacao["expires_in"] ?? 0),
            "token_type" => (string)($dadosAutenticacao["token_type"] ?? "bearer"),
        ];
    } else {
        unset($_SESSION["supabase"]);
    }

    responder(true, "Login realizado com sucesso.", 200, [
        "redirect" => caminhoAplicacao("pages/pagina-inicial.php"),
        "preferences" => $preferencias,
    ]);
} catch (Throwable $erro) {
    responder(false, "Nao foi possivel validar o acesso agora.", 500);
}
