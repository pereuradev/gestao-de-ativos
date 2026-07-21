<?php

declare(strict_types=1);

// Atualiza os dados e as permissões do usuário mantidos na sessão ativa.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderUsuarioSessao(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function iniciaisUsuarioSessao(string $nome): string
{
    $partes = preg_split("/\s+/", trim($nome)) ?: [];
    $iniciais = "";

    foreach ($partes as $parte) {
        if ($parte === "") {
            continue;
        }

        $iniciais .= strtoupper(substr($parte, 0, 1));

        if (strlen($iniciais) >= 2) {
            break;
        }
    }

    return $iniciais !== "" ? $iniciais : "TT";
}

function preferenciaUsuarioSessao(array $perfil, string $campo, array $permitidos, string $padrao): string
{
    $valor = trim((string) ($perfil[$campo] ?? ""));

    return in_array($valor, $permitidos, true) ? $valor : $padrao;
}

function preferenciasUsuarioSessao(array $perfil): array
{
    return [
        "theme" => preferenciaUsuarioSessao($perfil, "preferencia_tema", ["dark", "light", "auto"], "dark"),
        "accent" => preferenciaUsuarioSessao($perfil, "preferencia_cor", ["teal", "green", "blue", "violet"], "teal"),
        "fontSize" => preferenciaUsuarioSessao($perfil, "preferencia_tamanho_fonte", ["small", "default", "large", "extra"], "default"),
        "density" => preferenciaUsuarioSessao($perfil, "preferencia_densidade", ["comfortable", "compact"], "comfortable"),
        "motion" => preferenciaUsuarioSessao($perfil, "preferencia_movimento", ["normal", "reduced"], "normal"),
        "cursor" => preferenciaUsuarioSessao($perfil, "preferencia_cursor", ["enhanced", "normal"], "enhanced"),
    ];
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderUsuarioSessao(false, "Sessao expirada.", 401);
}

$usuarioSessao = $_SESSION["usuario"];
$email = trim((string) ($usuarioSessao["email"] ?? ""));

if ($email === "") {
    responderUsuarioSessao(false, "Usuario da sessao sem e-mail.", 422);
}

try {
    // Carrega a conexão e as regras compartilhadas de grupos e permissões.
    require __DIR__ . "/Conexao.php";
    require __DIR__ . "/grupos-acesso-util.php";

    // Recarrega o perfil pelo e-mail da sessão para refletir mudanças administrativas recentes.
    $stmt = $pdo->prepare("
        select
            id,
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            empresa,
            status,
            preferencia_tema,
            preferencia_cor,
            preferencia_tamanho_fonte,
            preferencia_densidade,
            preferencia_movimento,
            preferencia_cursor
          from public.perfis_usuarios
         where lower(btrim(email)) = lower(btrim(:email))
         limit 1
    ");
    $stmt->execute([":email" => $email]);
    $perfil = $stmt->fetch();

    if (!is_array($perfil)) {
        responderUsuarioSessao(false, "Perfil nao encontrado.", 404);
    }

    $preferencias = preferenciasUsuarioSessao($perfil);

    // Preserva campos complementares da sessão e substitui apenas os dados canônicos do perfil.
    $_SESSION["usuario"] = array_merge($usuarioSessao, [
        "id" => (string) ($perfil["id"] ?? ($usuarioSessao["id"] ?? "")),
        "nome_completo" => (string) ($perfil["nome_completo"] ?? ($usuarioSessao["nome_completo"] ?? "")),
        "email" => (string) ($perfil["email"] ?? $email),
        "tipo_usuario" => (string) ($perfil["tipo_usuario"] ?? ($usuarioSessao["tipo_usuario"] ?? "")),
        "departamento" => (string) ($perfil["departamento"] ?? ""),
        "empresa" => (string) ($perfil["empresa"] ?? ""),
        "status" => (string) ($perfil["status"] ?? ($usuarioSessao["status"] ?? "")),
        "preferencia_tema" => $preferencias["theme"],
        "preferencia_cor" => $preferencias["accent"],
        "preferencia_tamanho_fonte" => $preferencias["fontSize"],
        "preferencia_densidade" => $preferencias["density"],
        "preferencia_movimento" => $preferencias["motion"],
        "preferencia_cursor" => $preferencias["cursor"],
    ]);

    $usuarioAtualizado = $_SESSION["usuario"];
    $nome = (string) ($usuarioAtualizado["nome_completo"] ?? "");
    // As permissões são recalculadas junto com o perfil para manter sidebar e rotas coerentes.
    $permissoes = sincronizarPermissoesUsuarioSessao($pdo);

    responderUsuarioSessao(true, "Perfil carregado.", 200, [
        "usuario" => [
            "nome_completo" => $nome,
            "email" => (string) ($usuarioAtualizado["email"] ?? ""),
            "tipo_usuario" => (string) ($usuarioAtualizado["tipo_usuario"] ?? ""),
            "departamento" => (string) ($usuarioAtualizado["departamento"] ?? ""),
            "empresa" => (string) ($usuarioAtualizado["empresa"] ?? ""),
            "status" => (string) ($usuarioAtualizado["status"] ?? ""),
            "iniciais" => iniciaisUsuarioSessao($nome),
            "permissoes" => $permissoes,
            "is_admin" => usuarioGrupoAcessoAdmin($usuarioAtualizado),
            "preferencias" => $preferencias,
        ],
    ]);
} catch (Throwable) {
    responderUsuarioSessao(false, "Nao foi possivel carregar o perfil.", 500);
}
