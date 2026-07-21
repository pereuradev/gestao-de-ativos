<?php

declare(strict_types=1);

// Persistencia das preferencias visuais do usuario autenticado.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderPreferenciasUsuario(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function payloadPreferenciasUsuario(): array
{
    $raw = file_get_contents("php://input");
    $payload = json_decode((string) $raw, true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    if (isset($payload["preferences"]) && is_array($payload["preferences"])) {
        return $payload["preferences"];
    }

    return $payload;
}

function escolhaPreferenciaUsuario(mixed $value, array $permitidos, string $padrao): string
{
    $valor = trim((string) $value);

    return in_array($valor, $permitidos, true) ? $valor : $padrao;
}

function preferenciasAtuaisSessao(array $usuario): array
{
    return [
        "theme" => $usuario["preferencia_tema"] ?? "dark",
        "accent" => $usuario["preferencia_cor"] ?? "teal",
        "fontSize" => $usuario["preferencia_tamanho_fonte"] ?? "default",
        "density" => $usuario["preferencia_densidade"] ?? "comfortable",
        "motion" => $usuario["preferencia_movimento"] ?? "normal",
        "cursor" => $usuario["preferencia_cursor"] ?? "enhanced",
    ];
}

function normalizarPreferenciasUsuario(array $payload, array $usuario): array
{
    $source = array_merge(preferenciasAtuaisSessao($usuario), $payload);

    return [
        "theme" => escolhaPreferenciaUsuario($source["theme"] ?? null, ["dark", "light", "auto"], "dark"),
        "accent" => escolhaPreferenciaUsuario($source["accent"] ?? null, ["teal", "green", "blue", "violet"], "teal"),
        "fontSize" => escolhaPreferenciaUsuario($source["fontSize"] ?? null, ["small", "default", "large", "extra"], "default"),
        "density" => escolhaPreferenciaUsuario($source["density"] ?? null, ["comfortable", "compact"], "comfortable"),
        "motion" => escolhaPreferenciaUsuario($source["motion"] ?? null, ["normal", "reduced"], "normal"),
        "cursor" => escolhaPreferenciaUsuario($source["cursor"] ?? null, ["enhanced", "normal"], "enhanced"),
    ];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderPreferenciasUsuario(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderPreferenciasUsuario(false, "Sessao expirada.", 401);
}

$usuario = $_SESSION["usuario"];
$usuarioId = trim((string) ($usuario["id"] ?? ""));

if ($usuarioId === "") {
    responderPreferenciasUsuario(false, "Usuario da sessao sem identificador.", 422);
}

$preferencias = normalizarPreferenciasUsuario(payloadPreferenciasUsuario(), $usuario);

try {
    require __DIR__ . "/Conexao.php";

    $stmt = $pdo->prepare("
        update public.perfis_usuarios
           set preferencia_tema = :tema,
               preferencia_cor = :cor,
               preferencia_tamanho_fonte = :tamanho_fonte,
               preferencia_densidade = :densidade,
               preferencia_movimento = :movimento,
               preferencia_cursor = :cursor,
               atualizado_em = now()
         where id = :id
        returning
               preferencia_tema,
               preferencia_cor,
               preferencia_tamanho_fonte,
               preferencia_densidade,
               preferencia_movimento,
               preferencia_cursor
    ");
    $stmt->execute([
        ":tema" => $preferencias["theme"],
        ":cor" => $preferencias["accent"],
        ":tamanho_fonte" => $preferencias["fontSize"],
        ":densidade" => $preferencias["density"],
        ":movimento" => $preferencias["motion"],
        ":cursor" => $preferencias["cursor"],
        ":id" => $usuarioId,
    ]);

    $perfil = $stmt->fetch();

    if (!is_array($perfil)) {
        responderPreferenciasUsuario(false, "Perfil nao encontrado.", 404);
    }

    $preferencias = [
        "theme" => escolhaPreferenciaUsuario($perfil["preferencia_tema"] ?? null, ["dark", "light", "auto"], "dark"),
        "accent" => escolhaPreferenciaUsuario($perfil["preferencia_cor"] ?? null, ["teal", "green", "blue", "violet"], "teal"),
        "fontSize" => escolhaPreferenciaUsuario($perfil["preferencia_tamanho_fonte"] ?? null, ["small", "default", "large", "extra"], "default"),
        "density" => escolhaPreferenciaUsuario($perfil["preferencia_densidade"] ?? null, ["comfortable", "compact"], "comfortable"),
        "motion" => escolhaPreferenciaUsuario($perfil["preferencia_movimento"] ?? null, ["normal", "reduced"], "normal"),
        "cursor" => escolhaPreferenciaUsuario($perfil["preferencia_cursor"] ?? null, ["enhanced", "normal"], "enhanced"),
    ];

    $_SESSION["usuario"] = array_merge($usuario, [
        "preferencia_tema" => $preferencias["theme"],
        "preferencia_cor" => $preferencias["accent"],
        "preferencia_tamanho_fonte" => $preferencias["fontSize"],
        "preferencia_densidade" => $preferencias["density"],
        "preferencia_movimento" => $preferencias["motion"],
        "preferencia_cursor" => $preferencias["cursor"],
    ]);

    responderPreferenciasUsuario(true, "Preferencias salvas.", 200, [
        "preferences" => $preferencias,
    ]);
} catch (Throwable) {
    responderPreferenciasUsuario(false, "Nao foi possivel salvar as preferencias.", 500);
}
