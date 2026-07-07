<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderExclusaoGrupo(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoExclusaoGrupo(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function csrfExclusaoGrupoValido(): bool
{
    $sessao = (string) ($_SESSION["csrf_token"] ?? "");
    $enviado = campoExclusaoGrupo("csrf_token");

    return $sessao !== "" && $enviado !== "" && hash_equals($sessao, $enviado);
}

function uuidExclusaoGrupoValido(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $uuid
    ) === 1;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderExclusaoGrupo(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderExclusaoGrupo(false, "Sessao expirada. Faca login novamente.", 401);
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_grupos", "Edicao de grupos");

if (!csrfExclusaoGrupoValido()) {
    responderExclusaoGrupo(false, "Token de seguranca invalido. Atualize a pagina.", 403);
}

$grupoId = campoExclusaoGrupo("id");

if (!uuidExclusaoGrupoValido($grupoId)) {
    responderExclusaoGrupo(false, "Grupo invalido para exclusao.", 422);
}

try {
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/grupos-acesso-util.php";

    garantirTabelasGruposAcesso($pdo);

    $resumoStmt = $pdo->prepare("
        select
            (select count(*)::int from public.grupos_acesso_membros where grupo_id = cast(:id_membros as uuid)) as membros,
            (select count(*)::int from public.grupos_acesso_permissoes where grupo_id = cast(:id_permissoes as uuid)) as permissoes
    ");
    $resumoStmt->execute([
        ":id_membros" => $grupoId,
        ":id_permissoes" => $grupoId,
    ]);
    $resumo = $resumoStmt->fetch() ?: ["membros" => 0, "permissoes" => 0];

    $stmt = $pdo->prepare("
        delete from public.grupos_acesso
         where id = cast(:id as uuid)
     returning id, nome
    ");
    $stmt->execute([":id" => $grupoId]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        responderExclusaoGrupo(false, "Grupo nao encontrado.", 404);
    }

    responderExclusaoGrupo(true, "Grupo excluido com sucesso.", 200, [
        "grupo" => [
            "id" => (string) ($grupo["id"] ?? $grupoId),
            "nome" => (string) ($grupo["nome"] ?? ""),
            "total_membros" => (int) ($resumo["membros"] ?? 0),
            "total_permissoes" => (int) ($resumo["permissoes"] ?? 0),
        ],
    ]);
} catch (Throwable) {
    responderExclusaoGrupo(false, "Nao foi possivel excluir o grupo agora.", 500);
}
