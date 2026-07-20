<?php

declare(strict_types=1);

// Centraliza as regras de autorização usadas por páginas e endpoints da aplicação.
require_once __DIR__ . "/Conexao.php";
require_once __DIR__ . "/grupos-acesso-util.php";

function recursoPermissaoAcesso(string $permissao): string
{
    $rotulos = permissoesGruposAcesso();

    return $rotulos[$permissao] ?? "esta area";
}

// Consulta permissões efetivas do usuário autenticado sem alterar o fluxo da página.
function usuarioAtualTemPermissao(string $permissao): bool
{
    global $pdo;

    if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
        return false;
    }

    return usuarioTemPermissaoGrupoAcesso($pdo, $permissao, $_SESSION["usuario"]);
}

// Páginas redirecionam para uma rota visual quando a sessão ou a permissão não é válida.
function exigirPermissaoPagina(string $permissao, ?string $recurso = null): void
{
    global $pdo;

    if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
        header("Location: Pagina-login.html?sessao=expirada");
        exit;
    }

    if (usuarioTemPermissaoGrupoAcesso($pdo, $permissao, $_SESSION["usuario"])) {
        sincronizarPermissoesUsuarioSessao($pdo);
        return;
    }

    $_SESSION["permission_denied_resource"] = $recurso ?? recursoPermissaoAcesso($permissao);
    header("Location: pagina-inicial.php?permissao=negada");
    exit;
}

// APIs devolvem JSON e códigos HTTP para que o JavaScript trate a falha corretamente.
function exigirPermissaoApi(string $permissao, ?string $recurso = null): void
{
    global $pdo;

    header("Content-Type: application/json; charset=utf-8");

    if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
        http_response_code(401);
        echo json_encode([
            "ok" => false,
            "message" => "Sessao expirada. Faca login novamente.",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (usuarioTemPermissaoGrupoAcesso($pdo, $permissao, $_SESSION["usuario"])) {
        sincronizarPermissoesUsuarioSessao($pdo);
        return;
    }

    $nomeRecurso = $recurso ?? recursoPermissaoAcesso($permissao);

    http_response_code(403);
    echo json_encode([
        "ok" => false,
        "message" => "Voce nao tem permissao para acessar {$nomeRecurso}.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Áreas estritamente administrativas mantêm uma verificação separada das permissões por recurso.
function exigirAdministradorPagina(?string $recurso = null): void
{
    if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
        header("Location: Pagina-login.html?sessao=expirada");
        exit;
    }

    if (usuarioGrupoAcessoAdmin($_SESSION["usuario"])) {
        return;
    }

    $_SESSION["permission_denied_resource"] = $recurso ?? "esta area administrativa";
    header("Location: pagina-inicial.php?permissao=negada");
    exit;
}

function exigirAdministradorApi(?string $recurso = null): void
{
    header("Content-Type: application/json; charset=utf-8");

    if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
        http_response_code(401);
        echo json_encode([
            "ok" => false,
            "message" => "Sessao expirada. Faca login novamente.",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (usuarioGrupoAcessoAdmin($_SESSION["usuario"])) {
        return;
    }

    $nomeRecurso = $recurso ?? "esta area administrativa";

    http_response_code(403);
    echo json_encode([
        "ok" => false,
        "message" => "Apenas administradores podem acessar {$nomeRecurso}.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
