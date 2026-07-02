<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderRemocaoMembroGrupo(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoRemocaoMembroGrupo(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function usuarioRemocaoMembroGrupoAdmin(): bool
{
    $tipo = strtolower(trim((string) ($_SESSION["usuario"]["tipo_usuario"] ?? "")));

    return in_array($tipo, ["adm", "admin", "administrador"], true);
}

function csrfRemocaoMembroGrupoValido(): bool
{
    $sessao = (string) ($_SESSION["csrf_token"] ?? "");
    $enviado = campoRemocaoMembroGrupo("csrf_token");

    return $sessao !== "" && $enviado !== "" && hash_equals($sessao, $enviado);
}

function uuidRemocaoMembroGrupoValido(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $uuid
    ) === 1;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderRemocaoMembroGrupo(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderRemocaoMembroGrupo(false, "Sessao expirada. Faca login novamente.", 401);
}

if (!usuarioRemocaoMembroGrupoAdmin()) {
    responderRemocaoMembroGrupo(false, "Apenas administradores podem alterar grupos.", 403);
}

if (!csrfRemocaoMembroGrupoValido()) {
    responderRemocaoMembroGrupo(false, "Token de seguranca invalido. Atualize a pagina.", 403);
}

$grupoId = campoRemocaoMembroGrupo("grupo_id");
$usuarioId = campoRemocaoMembroGrupo("usuario_id");

if (!uuidRemocaoMembroGrupoValido($grupoId) || !uuidRemocaoMembroGrupoValido($usuarioId)) {
    responderRemocaoMembroGrupo(false, "Dados invalidos para remover membro.", 422);
}

try {
    require __DIR__ . "/Conexao.php";
    require __DIR__ . "/grupos-acesso-util.php";

    garantirTabelasGruposAcesso($pdo);

    $stmt = $pdo->prepare("
        delete from public.grupos_acesso_membros
         where grupo_id = cast(:grupo_id as uuid)
           and usuario_id = cast(:usuario_id as uuid)
     returning grupo_id, usuario_id
    ");
    $stmt->execute([
        ":grupo_id" => $grupoId,
        ":usuario_id" => $usuarioId,
    ]);

    $removido = $stmt->fetch();

    if (!$removido) {
        responderRemocaoMembroGrupo(false, "Membro nao encontrado neste grupo.", 404);
    }

    $atualizarStmt = $pdo->prepare("
        update public.grupos_acesso
           set atualizado_em = now()
         where id = cast(:grupo_id as uuid)
    ");
    $atualizarStmt->execute([":grupo_id" => $grupoId]);

    responderRemocaoMembroGrupo(true, "Membro removido do grupo.", 200, [
        "grupo_id" => $grupoId,
        "usuario_id" => $usuarioId,
    ]);
} catch (Throwable) {
    responderRemocaoMembroGrupo(false, "Nao foi possivel remover o membro agora.", 500);
}
