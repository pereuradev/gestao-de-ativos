<?php

declare(strict_types=1);

// Persistencia das preferencias visuais do usuario autenticado.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderPreferenciasUsuario(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function dadosPreferenciasUsuario(): array
{
    $conteudoBruto = file_get_contents("php://input");
    $dadosRequisicao = json_decode((string) $conteudoBruto, true);

    if (!is_array($dadosRequisicao)) {
        $dadosRequisicao = $_POST;
    }

    if (isset($dadosRequisicao["preferences"]) && is_array($dadosRequisicao["preferences"])) {
        return $dadosRequisicao["preferences"];
    }

    return $dadosRequisicao;
}

function escolhaPreferenciaUsuario(mixed $valorEntrada, array $permitidos, string $padrao): string
{
    if (is_array($valorEntrada) || is_object($valorEntrada)) {
        return $padrao;
    }

    $valor = trim((string) $valorEntrada);

    return in_array($valor, $permitidos, true) ? $valor : $padrao;
}

function corPreferenciaUsuario(mixed $valorEntrada, string $padrao = "teal"): string
{
    if (is_array($valorEntrada) || is_object($valorEntrada)) {
        return $padrao;
    }

    $valor = trim((string) $valorEntrada);

    if (in_array($valor, ["teal", "green", "blue", "violet"], true)) {
        return $valor;
    }

    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $valor)) {
        return strtolower($valor);
    }

    return $padrao;
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

function normalizarPreferenciasUsuario(array $dadosRequisicao, array $usuario): array
{
    $dadosOrigem = array_merge(preferenciasAtuaisSessao($usuario), $dadosRequisicao);

    return [
        "theme" => escolhaPreferenciaUsuario($dadosOrigem["theme"] ?? null, ["dark", "light", "auto"], "dark"),
        "accent" => corPreferenciaUsuario($dadosOrigem["accent"] ?? null),
        "fontSize" => escolhaPreferenciaUsuario($dadosOrigem["fontSize"] ?? null, ["small", "default", "large", "extra"], "default"),
        "density" => escolhaPreferenciaUsuario($dadosOrigem["density"] ?? null, ["comfortable", "compact"], "comfortable"),
        "motion" => escolhaPreferenciaUsuario($dadosOrigem["motion"] ?? null, ["normal", "reduced"], "normal"),
        "cursor" => escolhaPreferenciaUsuario($dadosOrigem["cursor"] ?? null, ["enhanced", "normal"], "enhanced"),
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

$preferencias = normalizarPreferenciasUsuario(dadosPreferenciasUsuario(), $usuario);

try {
    require __DIR__ . "/Conexao.php";

    $consultaPreparada = $pdo->prepare("
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
    $consultaPreparada->execute([
        ":tema" => $preferencias["theme"],
        ":cor" => $preferencias["accent"],
        ":tamanho_fonte" => $preferencias["fontSize"],
        ":densidade" => $preferencias["density"],
        ":movimento" => $preferencias["motion"],
        ":cursor" => $preferencias["cursor"],
        ":id" => $usuarioId,
    ]);

    $perfil = $consultaPreparada->fetch();

    if (!is_array($perfil)) {
        responderPreferenciasUsuario(false, "Perfil nao encontrado.", 404);
    }

    $preferencias = [
        "theme" => escolhaPreferenciaUsuario($perfil["preferencia_tema"] ?? null, ["dark", "light", "auto"], "dark"),
        "accent" => corPreferenciaUsuario($perfil["preferencia_cor"] ?? null),
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
