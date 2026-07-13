<?php

declare(strict_types=1);

function garantirTabelasGruposAcesso(PDO $pdo): void
{
    // Estrutura criada por migrations. Mantido para compatibilidade.
}

function permissoesGruposAcesso(): array
{
    return [
        "visualizar_dashboard" => "Visualizar dashboard",
        "visualizar_grupos" => "Visualizar grupos",
        "visualizar_funcionarios" => "Visualizar funcionarios",
        "visualizar_ativos" => "Visualizar ativos",
        "visualizar_locais" => "Visualizar localizacoes",
        "visualizar_marcas" => "Visualizar marcas",
        "cadastrar_grupos" => "Cadastrar grupos",
        "cadastrar_funcionarios" => "Cadastrar funcionarios",
        "cadastrar_ativos" => "Cadastrar ativos",
        "cadastrar_locais" => "Cadastrar localizacoes",
        "cadastrar_marcas" => "Cadastrar marcas",
        "editar_grupos" => "Editar grupos",
        "editar_funcionarios" => "Editar funcionarios",
        "editar_ativos" => "Editar ativos",
        "editar_locais" => "Editar localizacoes",
        "editar_marcas" => "Editar marcas",
    ];
}

function permissoesGruposAcessoAgrupadas(): array
{
    $rotulos = permissoesGruposAcesso();
    $grupos = [
        [
            "titulo" => "Ver",
            "descricao" => "Permite apenas consultar as areas liberadas.",
            "icone" => "bi-eye-fill",
            "permissoes" => [
                "visualizar_dashboard" => "Dashboard",
                "visualizar_grupos" => "Grupos",
                "visualizar_funcionarios" => "Funcionarios",
                "visualizar_ativos" => "Ativos",
                "visualizar_locais" => "Localizacoes",
                "visualizar_marcas" => "Marcas",
            ],
        ],
        [
            "titulo" => "Cadastrar",
            "descricao" => "Permite criar novos registros nas areas liberadas.",
            "icone" => "bi-plus-circle-fill",
            "permissoes" => [
                "cadastrar_grupos" => "Grupos",
                "cadastrar_funcionarios" => "Funcionarios",
                "cadastrar_ativos" => "Ativos",
                "cadastrar_locais" => "Localizacoes",
                "cadastrar_marcas" => "Marcas",
            ],
        ],
        [
            "titulo" => "Editar",
            "descricao" => "Permite alterar registros existentes nas areas liberadas.",
            "icone" => "bi-pencil-square",
            "permissoes" => [
                "editar_grupos" => "Grupos",
                "editar_funcionarios" => "Funcionarios",
                "editar_ativos" => "Ativos",
                "editar_locais" => "Localizacoes",
                "editar_marcas" => "Marcas",
            ],
        ],
    ];

    return array_map(static function (array $grupo) use ($rotulos): array {
        $grupo["permissoes"] = array_intersect_key($grupo["permissoes"], $rotulos);

        return $grupo;
    }, $grupos);
}

function usuarioGrupoAcessoAdmin(?array $usuario = null): bool
{
    $usuarioAtual = $usuario ?? ($_SESSION["usuario"] ?? []);
    $tipo = strtolower(trim((string) ($usuarioAtual["tipo_usuario"] ?? "")));

    return in_array($tipo, ["adm", "admin", "administrador"], true);
}

function uuidGrupoAcessoValido(string $uuid): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $uuid
    ) === 1;
}

function permissoesUsuarioGrupoAcesso(PDO $pdo, ?array $usuario = null): array
{
    $usuarioAtual = $usuario ?? ($_SESSION["usuario"] ?? []);
    $permissoesDisponiveis = permissoesGruposAcesso();

    if (usuarioGrupoAcessoAdmin($usuarioAtual)) {
        return array_keys($permissoesDisponiveis);
    }

    garantirTabelasGruposAcesso($pdo);

    $usuarioId = (string) ($usuarioAtual["id"] ?? "");
    $email = (string) ($usuarioAtual["email"] ?? "");

    if ($usuarioId === "" && $email === "") {
        return [];
    }

    $filtros = [];
    $params = [];

    if ($usuarioId !== "" && uuidGrupoAcessoValido($usuarioId)) {
        $filtros[] = "u.id = cast(:usuario_id as uuid)";
        $params[":usuario_id"] = $usuarioId;
    }

    if ($email !== "") {
        $filtros[] = "lower(btrim(u.email)) = lower(btrim(:email))";
        $params[":email"] = $email;
    }

    if (!$filtros) {
        return [];
    }

    $stmt = $pdo->prepare("
        select distinct gp.permissao
          from public.perfis_usuarios u
          join public.grupos_acesso_membros gm on gm.usuario_id = u.id
          join public.grupos_acesso g on g.id = gm.grupo_id
          join public.grupos_acesso_permissoes gp on gp.grupo_id = g.id
         where (" . implode(" or ", $filtros) . ")
           and lower(coalesce(g.status, 'ativo')) = 'ativo'
      order by gp.permissao asc
    ");
    $stmt->execute($params);

    $validas = array_flip(array_keys($permissoesDisponiveis));

    return array_values(array_filter(array_map(static function (array $linha): string {
        return (string) ($linha["permissao"] ?? "");
    }, $stmt->fetchAll()), static function (string $permissao) use ($validas): bool {
        return isset($validas[$permissao]);
    }));
}

function sincronizarPermissoesUsuarioSessao(PDO $pdo): array
{
    $permissoes = permissoesUsuarioGrupoAcesso($pdo);

    if (!empty($_SESSION["usuario"]) && is_array($_SESSION["usuario"])) {
        $_SESSION["usuario"]["permissoes_grupos"] = $permissoes;
    }

    return $permissoes;
}

function usuarioTemPermissaoGrupoAcesso(PDO $pdo, string $permissao, ?array $usuario = null): bool
{
    if (usuarioGrupoAcessoAdmin($usuario)) {
        return true;
    }

    return in_array($permissao, permissoesUsuarioGrupoAcesso($pdo, $usuario), true);
}

function gerarUuidGrupoAcesso(): string
{
    $dados = random_bytes(16);
    $dados[6] = chr((ord($dados[6]) & 0x0f) | 0x40);
    $dados[8] = chr((ord($dados[8]) & 0x3f) | 0x80);
    $hex = bin2hex($dados);

    return sprintf(
        "%s-%s-%s-%s-%s",
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20)
    );
}
