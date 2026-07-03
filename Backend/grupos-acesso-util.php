<?php

declare(strict_types=1);

function garantirTabelasGruposAcesso(PDO $pdo): void
{
    $pdo->exec("
        create table if not exists public.grupos_acesso (
            id uuid primary key,
            nome varchar(90) not null,
            descricao text,
            status varchar(20) not null default 'Ativo',
            criado_por uuid null,
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("alter table public.grupos_acesso add column if not exists descricao text");
    $pdo->exec("alter table public.grupos_acesso add column if not exists status varchar(20) not null default 'Ativo'");
    $pdo->exec("alter table public.grupos_acesso add column if not exists criado_por uuid null");
    $pdo->exec("alter table public.grupos_acesso add column if not exists criado_em timestamptz not null default now()");
    $pdo->exec("alter table public.grupos_acesso add column if not exists atualizado_em timestamptz not null default now()");

    $pdo->exec("
        create unique index if not exists grupos_acesso_nome_lower_unique
            on public.grupos_acesso (lower(nome))
    ");

    $pdo->exec("
        create table if not exists public.grupos_acesso_membros (
            grupo_id uuid not null references public.grupos_acesso(id) on delete cascade,
            usuario_id uuid not null references public.perfis_usuarios(id) on delete cascade,
            criado_em timestamptz not null default now(),
            primary key (grupo_id, usuario_id)
        )
    ");

    $pdo->exec("
        create table if not exists public.grupos_acesso_permissoes (
            grupo_id uuid not null references public.grupos_acesso(id) on delete cascade,
            permissao varchar(80) not null,
            criado_em timestamptz not null default now(),
            primary key (grupo_id, permissao)
        )
    ");
}

function permissoesGruposAcesso(): array
{
    return [
        "visualizar_dashboard" => "Visualizar dashboard",
        "visualizar_ativos" => "Visualizar ativos",
        "visualizar_marcas" => "Visualizar marcas",
        "visualizar_propriedades" => "Visualizar propriedades",
        "visualizar_locais" => "Visualizar localizacoes",
        "visualizar_funcionarios" => "Visualizar funcionarios",
        "cadastrar_ativos" => "Cadastrar ativos",
        "editar_ativos" => "Editar ativos",
        "cadastrar_marcas" => "Cadastrar marcas",
        "editar_marcas" => "Editar marcas",
        "cadastrar_propriedades" => "Cadastrar propriedades",
        "editar_propriedades" => "Editar propriedades",
        "cadastrar_locais" => "Cadastrar localizacoes",
        "editar_locais" => "Editar localizacoes",
    ];
}

function permissoesGruposAcessoAgrupadas(): array
{
    $rotulos = permissoesGruposAcesso();
    $grupos = [
        [
            "titulo" => "Dashboard",
            "descricao" => "Painel visual e indicadores do inventario.",
            "icone" => "bi-bar-chart-fill",
            "permissoes" => [
                "visualizar_dashboard" => "Ver dashboard",
            ],
        ],
        [
            "titulo" => "Ativos",
            "descricao" => "Consulta, cadastro e manutencao dos ativos.",
            "icone" => "bi-hdd-network-fill",
            "permissoes" => [
                "visualizar_ativos" => "Ver",
                "cadastrar_ativos" => "Cadastrar",
                "editar_ativos" => "Editar",
            ],
        ],
        [
            "titulo" => "Marcas",
            "descricao" => "Controle das marcas usadas pelos ativos.",
            "icone" => "bi-tags-fill",
            "permissoes" => [
                "visualizar_marcas" => "Ver",
                "cadastrar_marcas" => "Cadastrar",
                "editar_marcas" => "Editar",
            ],
        ],
        [
            "titulo" => "Propriedades",
            "descricao" => "Empresas ou donos vinculados aos ativos.",
            "icone" => "bi-building-check",
            "permissoes" => [
                "visualizar_propriedades" => "Ver",
                "cadastrar_propriedades" => "Cadastrar",
                "editar_propriedades" => "Editar",
            ],
        ],
        [
            "titulo" => "Localizacoes",
            "descricao" => "Locais fisicos onde os ativos ficam.",
            "icone" => "bi-geo-alt-fill",
            "permissoes" => [
                "visualizar_locais" => "Ver",
                "cadastrar_locais" => "Cadastrar",
                "editar_locais" => "Editar",
            ],
        ],
        [
            "titulo" => "Funcionarios",
            "descricao" => "Consulta dos colaboradores cadastrados.",
            "icone" => "bi-people-fill",
            "permissoes" => [
                "visualizar_funcionarios" => "Ver funcionarios",
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
        $filtros[] = "lower(u.email) = lower(:email)";
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
