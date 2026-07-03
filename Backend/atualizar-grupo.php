<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderAtualizacaoGrupo(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoAtualizacaoGrupo(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function listaAtualizacaoGrupo(string $nome): array
{
    $valor = $_POST[$nome] ?? [];

    if (!is_array($valor)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(static function ($item): string {
        return trim((string) $item);
    }, $valor))));
}

function usuarioAtualizacaoGrupoAdmin(): bool
{
    $tipo = strtolower(trim((string) ($_SESSION["usuario"]["tipo_usuario"] ?? "")));

    return in_array($tipo, ["adm", "admin", "administrador"], true);
}

function csrfAtualizacaoGrupoValido(): bool
{
    $sessao = (string) ($_SESSION["csrf_token"] ?? "");
    $enviado = campoAtualizacaoGrupo("csrf_token");

    return $sessao !== "" && $enviado !== "" && hash_equals($sessao, $enviado);
}

function uuidAtualizacaoGrupoValido(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $uuid
    ) === 1;
}

function iniciaisAtualizacaoGrupo(string $nome): string
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

function buscarGrupoAtualizado(PDO $pdo, string $grupoId, array $rotulosPermissoes): array
{
    $grupoStmt = $pdo->prepare("
        select id, nome, descricao, status, criado_em, atualizado_em
          from public.grupos_acesso
         where id = cast(:id as uuid)
         limit 1
    ");
    $grupoStmt->execute([":id" => $grupoId]);
    $grupo = $grupoStmt->fetch() ?: [];

    $membrosStmt = $pdo->prepare("
        select
            u.id,
            u.nome_completo,
            u.email,
            u.tipo_usuario,
            u.departamento
          from public.grupos_acesso_membros gm
          join public.perfis_usuarios u on u.id = gm.usuario_id
         where gm.grupo_id = cast(:id as uuid)
      order by u.nome_completo asc
    ");
    $membrosStmt->execute([":id" => $grupoId]);
    $membros = array_map(static function (array $membro): array {
        $nome = (string) ($membro["nome_completo"] ?? "");

        return [
            "id" => (string) ($membro["id"] ?? ""),
            "nome" => $nome,
            "email" => (string) ($membro["email"] ?? ""),
            "tipo_usuario" => (string) ($membro["tipo_usuario"] ?? ""),
            "departamento" => (string) ($membro["departamento"] ?? ""),
            "iniciais" => iniciaisAtualizacaoGrupo($nome),
        ];
    }, $membrosStmt->fetchAll());

    $permissoesStmt = $pdo->prepare("
        select permissao
          from public.grupos_acesso_permissoes
         where grupo_id = cast(:id as uuid)
      order by permissao asc
    ");
    $permissoesStmt->execute([":id" => $grupoId]);
    $permissoes = array_map(static function (array $permissao) use ($rotulosPermissoes): array {
        $codigo = (string) ($permissao["permissao"] ?? "");

        return [
            "codigo" => $codigo,
            "rotulo" => $rotulosPermissoes[$codigo] ?? $codigo,
        ];
    }, $permissoesStmt->fetchAll());

    return [
        "id" => (string) ($grupo["id"] ?? $grupoId),
        "nome" => (string) ($grupo["nome"] ?? ""),
        "descricao" => (string) ($grupo["descricao"] ?? ""),
        "status" => (string) ($grupo["status"] ?? "Ativo"),
        "criado_em" => (string) ($grupo["criado_em"] ?? ""),
        "atualizado_em" => (string) ($grupo["atualizado_em"] ?? ""),
        "membros" => $membros,
        "permissoes" => $permissoes,
        "total_membros" => count($membros),
        "total_permissoes" => count($permissoes),
    ];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderAtualizacaoGrupo(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderAtualizacaoGrupo(false, "Sessao expirada. Faca login novamente.", 401);
}

if (!usuarioAtualizacaoGrupoAdmin()) {
    responderAtualizacaoGrupo(false, "Apenas administradores podem editar grupos.", 403);
}

if (!csrfAtualizacaoGrupoValido()) {
    responderAtualizacaoGrupo(false, "Token de seguranca invalido. Atualize a pagina.", 403);
}

$grupoId = campoAtualizacaoGrupo("id");
$nome = preg_replace("/\s+/u", " ", campoAtualizacaoGrupo("nome")) ?? campoAtualizacaoGrupo("nome");
$descricao = campoAtualizacaoGrupo("descricao");
$membros = listaAtualizacaoGrupo("membros");
$permissoes = listaAtualizacaoGrupo("permissoes");

if (!uuidAtualizacaoGrupoValido($grupoId)) {
    responderAtualizacaoGrupo(false, "Grupo invalido para edicao.", 422);
}

if (strlen($nome) < 3 || strlen($nome) > 90) {
    responderAtualizacaoGrupo(false, "Informe um nome de grupo entre 3 e 90 caracteres.", 422);
}

foreach ($membros as $membroId) {
    if (!uuidAtualizacaoGrupoValido($membroId)) {
        responderAtualizacaoGrupo(false, "Existe um funcionario invalido na selecao.", 422);
    }
}

try {
    require __DIR__ . "/Conexao.php";
    require __DIR__ . "/grupos-acesso-util.php";

    garantirTabelasGruposAcesso($pdo);

    $permissoesPermitidas = permissoesGruposAcesso();
    $permissoesInvalidas = array_diff($permissoes, array_keys($permissoesPermitidas));

    if ($permissoesInvalidas) {
        responderAtualizacaoGrupo(false, "Existe uma permissao invalida na selecao.", 422);
    }

    if ($membros) {
        $placeholders = [];
        $params = [];

        foreach ($membros as $index => $membroId) {
            $key = ":membro_{$index}";
            $placeholders[] = "cast({$key} as uuid)";
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
            responderAtualizacaoGrupo(false, "Selecione apenas funcionarios ativos.", 422);
        }
    }

    $duplicadoStmt = $pdo->prepare("
        select 1
          from public.grupos_acesso
         where lower(nome) = lower(:nome)
           and id <> cast(:id as uuid)
         limit 1
    ");
    $duplicadoStmt->execute([
        ":nome" => $nome,
        ":id" => $grupoId,
    ]);

    if ($duplicadoStmt->fetchColumn() !== false) {
        responderAtualizacaoGrupo(false, "Ja existe outro grupo com este nome.", 409);
    }

    $pdo->beginTransaction();

    $grupoStmt = $pdo->prepare("
        update public.grupos_acesso
           set nome = :nome,
               descricao = :descricao,
               atualizado_em = now()
         where id = cast(:id as uuid)
     returning id
    ");
    $grupoStmt->execute([
        ":id" => $grupoId,
        ":nome" => $nome,
        ":descricao" => $descricao !== "" ? $descricao : null,
    ]);

    if (!$grupoStmt->fetch()) {
        $pdo->rollBack();
        responderAtualizacaoGrupo(false, "Grupo nao encontrado.", 404);
    }

    $pdo->prepare("delete from public.grupos_acesso_membros where grupo_id = cast(:id as uuid)")
        ->execute([":id" => $grupoId]);

    $membroStmt = $pdo->prepare("
        insert into public.grupos_acesso_membros (grupo_id, usuario_id)
        values (cast(:grupo_id as uuid), cast(:usuario_id as uuid))
    ");

    foreach ($membros as $membroId) {
        $membroStmt->execute([
            ":grupo_id" => $grupoId,
            ":usuario_id" => $membroId,
        ]);
    }

    $pdo->prepare("delete from public.grupos_acesso_permissoes where grupo_id = cast(:id as uuid)")
        ->execute([":id" => $grupoId]);

    $permissaoStmt = $pdo->prepare("
        insert into public.grupos_acesso_permissoes (grupo_id, permissao)
        values (cast(:grupo_id as uuid), :permissao)
    ");

    foreach ($permissoes as $permissao) {
        $permissaoStmt->execute([
            ":grupo_id" => $grupoId,
            ":permissao" => $permissao,
        ]);
    }

    $pdo->commit();

    responderAtualizacaoGrupo(true, "Grupo atualizado com sucesso.", 200, [
        "grupo" => buscarGrupoAtualizado($pdo, $grupoId, $permissoesPermitidas),
    ]);
} catch (Throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responderAtualizacaoGrupo(false, "Nao foi possivel atualizar o grupo agora.", 500);
}
