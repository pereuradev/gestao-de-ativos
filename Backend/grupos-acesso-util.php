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
        "cadastrar_ativos" => "Cadastrar ativos",
        "editar_ativos" => "Editar ativos",
        "cadastrar_marcas" => "Cadastrar marcas",
        "editar_marcas" => "Editar marcas",
        "cadastrar_locais" => "Cadastrar localizacoes",
        "editar_locais" => "Editar localizacoes",
        "visualizar_funcionarios" => "Visualizar funcionarios",
    ];
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
