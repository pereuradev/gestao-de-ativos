<?php

declare(strict_types=1);

session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderGrupo(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function csrfGrupoValido(): bool
{
    $sessao = (string) ($_SESSION["csrf_token"] ?? "");
    $enviado = (string) ($_POST["csrf_token"] ?? "");

    return $sessao !== "" && $enviado !== "" && hash_equals($sessao, $enviado);
}

function campoGrupo(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function listaGrupoPost(string $nome): array
{
    $valor = $_POST[$nome] ?? [];

    if (!is_array($valor)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(static function ($item): string {
        return trim((string) $item);
    }, $valor))));
}

function uuidGrupoValido(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $uuid
    ) === 1;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderGrupo(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderGrupo(false, "Sessao expirada. Entre novamente no portal.", 401, [
        "redirect" => "../pages/Pagina-login.html?sessao=expirada",
    ]);
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_grupos", "Cadastro de grupos");

if (!csrfGrupoValido()) {
    responderGrupo(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 419);
}

$nome = campoGrupo("nome");
$descricao = campoGrupo("descricao");
$membros = listaGrupoPost("membros");
$permissoes = listaGrupoPost("permissoes");

if (strlen($nome) < 3 || strlen($nome) > 90) {
    responderGrupo(false, "Informe um nome de grupo entre 3 e 90 caracteres.", 422);
}

if (!$membros) {
    responderGrupo(false, "Selecione pelo menos um funcionario para o grupo.", 422);
}

if (!$permissoes) {
    responderGrupo(false, "Selecione pelo menos uma permissao para o grupo.", 422);
}

foreach ($membros as $membroId) {
    if (!uuidGrupoValido($membroId)) {
        responderGrupo(false, "Existe um funcionario invalido na selecao.", 422);
    }
}

try {
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/grupos-acesso-util.php";

    garantirTabelasGruposAcesso($pdo);

    $permissoesPermitidas = permissoesGruposAcesso();
    $permissoesInvalidas = array_diff($permissoes, array_keys($permissoesPermitidas));

    if ($permissoesInvalidas) {
        responderGrupo(false, "Existe uma permissao invalida na selecao.", 422);
    }

    $placeholders = [];
    $params = [];

    foreach ($membros as $index => $membroId) {
        $key = ":membro_{$index}";
        $placeholders[] = $key;
        $params[$key] = $membroId;
    }

    $stmt = $pdo->prepare("
        select count(*)::int
          from public.perfis_usuarios
         where id in (" . implode(", ", $placeholders) . ")
           and lower(coalesce(status, 'ativo')) = 'ativo'
    ");
    $stmt->execute($params);

    if ((int) $stmt->fetchColumn() !== count($membros)) {
        responderGrupo(false, "Selecione apenas funcionarios ativos.", 422);
    }

    $grupoId = gerarUuidGrupoAcesso();
    $criadorId = (string) ($_SESSION["usuario"]["id"] ?? "");
    $criadorId = uuidGrupoValido($criadorId) ? $criadorId : null;

    $pdo->beginTransaction();

    $grupoStmt = $pdo->prepare("
        insert into public.grupos_acesso (
            id,
            nome,
            descricao,
            status,
            criado_por
        ) values (
            :id,
            :nome,
            :descricao,
            'Ativo',
            :criado_por
        )
        returning id, nome, descricao, status, criado_em
    ");
    $grupoStmt->execute([
        ":id" => $grupoId,
        ":nome" => $nome,
        ":descricao" => $descricao !== "" ? $descricao : null,
        ":criado_por" => $criadorId,
    ]);
    $grupo = $grupoStmt->fetch();

    $membroStmt = $pdo->prepare("
        insert into public.grupos_acesso_membros (grupo_id, usuario_id)
        values (:grupo_id, :usuario_id)
    ");

    foreach ($membros as $membroId) {
        $membroStmt->execute([
            ":grupo_id" => $grupoId,
            ":usuario_id" => $membroId,
        ]);
    }

    $permissaoStmt = $pdo->prepare("
        insert into public.grupos_acesso_permissoes (grupo_id, permissao)
        values (:grupo_id, :permissao)
    ");

    foreach ($permissoes as $permissao) {
        $permissaoStmt->execute([
            ":grupo_id" => $grupoId,
            ":permissao" => $permissao,
        ]);
    }

    $pdo->commit();

    responderGrupo(true, "Grupo criado com sucesso.", 201, [
        "grupo" => [
            "id" => (string) ($grupo["id"] ?? $grupoId),
            "nome" => (string) ($grupo["nome"] ?? $nome),
            "descricao" => (string) ($grupo["descricao"] ?? ""),
            "status" => (string) ($grupo["status"] ?? "Ativo"),
            "criado_em" => (string) ($grupo["criado_em"] ?? ""),
            "total_membros" => count($membros),
            "total_permissoes" => count($permissoes),
        ],
    ]);
} catch (Throwable $erro) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (str_contains($erro->getMessage(), "grupos_acesso_nome_lower_unique")) {
        responderGrupo(false, "Ja existe um grupo com este nome.", 409);
    }

    error_log("Erro ao cadastrar grupo de acesso: " . $erro->getMessage());
    responderGrupo(false, "Nao foi possivel criar o grupo agora.", 500);
}
