<?php

declare(strict_types=1);

// Endpoint responsável por validar e atualizar o grupo, seus membros e suas permissões.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderAtualizacaoGrupo(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoAtualizacaoGrupo(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

// Normaliza listas do formulário e elimina valores vazios ou repetidos antes da validação.
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

// Reconstrói o estado completo devolvido ao navegador após a atualização.
function buscarGrupoAtualizado(PDO $pdo, string $grupoId, array $rotulosPermissoes): array
{
    $consultaGrupo = $pdo->prepare("
        select id, nome, descricao, status, criado_em, atualizado_em
          from public.grupos_acesso
         where id = cast(:id as uuid)
         limit 1
    ");
    $consultaGrupo->execute([":id" => $grupoId]);
    $grupo = $consultaGrupo->fetch() ?: [];

    $consultaMembros = $pdo->prepare("
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
    $consultaMembros->execute([":id" => $grupoId]);
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
    }, $consultaMembros->fetchAll());

    $consultaPermissoes = $pdo->prepare("
        select permissao
          from public.grupos_acesso_permissoes
         where grupo_id = cast(:id as uuid)
      order by permissao asc
    ");
    $consultaPermissoes->execute([":id" => $grupoId]);
    $permissoes = array_map(static function (array $permissao) use ($rotulosPermissoes): array {
        $codigo = (string) ($permissao["permissao"] ?? "");

        return [
            "codigo" => $codigo,
            "rotulo" => $rotulosPermissoes[$codigo] ?? $codigo,
        ];
    }, $consultaPermissoes->fetchAll());

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

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_grupos", "Edicao de grupos");

if (!csrfAtualizacaoGrupoValido()) {
    responderAtualizacaoGrupo(false, "Token de seguranca invalido. Atualize a pagina.", 403);
}

$grupoId = campoAtualizacaoGrupo("id");
$nome = preg_replace("/\s+/u", " ", campoAtualizacaoGrupo("nome")) ?? campoAtualizacaoGrupo("nome");
$descricao = campoAtualizacaoGrupo("descricao");
$status = campoAtualizacaoGrupo("status") ?: "Ativo";
$membros = listaAtualizacaoGrupo("membros");
$permissoes = listaAtualizacaoGrupo("permissoes");

if (!uuidAtualizacaoGrupoValido($grupoId)) {
    responderAtualizacaoGrupo(false, "Grupo invalido para edicao.", 422);
}

if (strlen($nome) < 3 || strlen($nome) > 90) {
    responderAtualizacaoGrupo(false, "Informe um nome de grupo entre 3 e 90 caracteres.", 422);
}

if (!in_array($status, ["Ativo", "Inativo"], true)) {
    responderAtualizacaoGrupo(false, "Status do grupo invalido.", 422);
}

foreach ($membros as $membroId) {
    if (!uuidAtualizacaoGrupoValido($membroId)) {
        responderAtualizacaoGrupo(false, "Existe um funcionario invalido na selecao.", 422);
    }
}

try {
    // Carrega a conexão e as regras compartilhadas de grupos e permissões.
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/grupos-acesso-util.php";

    garantirTabelasGruposAcesso($pdo);

    $permissoesPermitidas = permissoesGruposAcesso();
    $permissoesInvalidas = array_diff($permissoes, array_keys($permissoesPermitidas));

    if ($permissoesInvalidas) {
        responderAtualizacaoGrupo(false, "Existe uma permissao invalida na selecao.", 422);
    }

    if ($membros) {
        $marcadoresConsulta = [];
        $parametrosConsulta = [];

        foreach ($membros as $indice => $membroId) {
            $chaveItem = ":membro_{$indice}";
            $marcadoresConsulta[] = "cast({$chaveItem} as uuid)";
            $parametrosConsulta[$chaveItem] = $membroId;
        }

        $consultaPreparada = $pdo->prepare("
            select count(*)::int
              from public.perfis_usuarios
             where id in (" . implode(", ", $marcadoresConsulta) . ")
               and lower(coalesce(status, 'ativo')) = 'ativo'
        ");
        $consultaPreparada->execute($parametrosConsulta);

        if ((int) $consultaPreparada->fetchColumn() !== count($membros)) {
            responderAtualizacaoGrupo(false, "Selecione apenas funcionarios ativos.", 422);
        }
    }

    $consultaDuplicado = $pdo->prepare("
        select 1
          from public.grupos_acesso
         where lower(btrim(nome)) = lower(btrim(:nome))
           and id <> cast(:id as uuid)
         limit 1
    ");
    $consultaDuplicado->execute([
        ":nome" => $nome,
        ":id" => $grupoId,
    ]);

    if ($consultaDuplicado->fetchColumn() !== false) {
        responderAtualizacaoGrupo(false, "Ja existe outro grupo com este nome.", 409);
    }

    // Grupo, membros e permissões formam uma única alteração e devem confirmar ou falhar juntos.
    $pdo->beginTransaction();

    $consultaGrupo = $pdo->prepare("
        update public.grupos_acesso
           set nome = :nome,
               descricao = :descricao,
               status = :status,
               atualizado_em = now()
         where id = cast(:id as uuid)
     returning id
    ");
    $consultaGrupo->execute([
        ":id" => $grupoId,
        ":nome" => $nome,
        ":descricao" => $descricao !== "" ? $descricao : null,
        ":status" => $status,
    ]);

    if (!$consultaGrupo->fetch()) {
        $pdo->rollBack();
        responderAtualizacaoGrupo(false, "Grupo nao encontrado.", 404);
    }

    $pdo->prepare("delete from public.grupos_acesso_membros where grupo_id = cast(:id as uuid)")
        ->execute([":id" => $grupoId]);

    $consultaMembro = $pdo->prepare("
        insert into public.grupos_acesso_membros (grupo_id, usuario_id)
        values (cast(:grupo_id as uuid), cast(:usuario_id as uuid))
    ");

    foreach ($membros as $membroId) {
        $consultaMembro->execute([
            ":grupo_id" => $grupoId,
            ":usuario_id" => $membroId,
        ]);
    }

    $pdo->prepare("delete from public.grupos_acesso_permissoes where grupo_id = cast(:id as uuid)")
        ->execute([":id" => $grupoId]);

    $consultaPermissao = $pdo->prepare("
        insert into public.grupos_acesso_permissoes (grupo_id, permissao)
        values (cast(:grupo_id as uuid), :permissao)
    ");

    foreach ($permissoes as $permissao) {
        $consultaPermissao->execute([
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
