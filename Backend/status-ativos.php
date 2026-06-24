<?php

declare(strict_types=1);

function garantirStatusAtivos(PDO $pdo): void
{
    $pdo->exec("
        create table if not exists public.status_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null,
            slug text not null unique,
            ordem integer not null default 0,
            ativo boolean not null default true,
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("
        create unique index if not exists status_ativos_nome_lower_unique
            on public.status_ativos (lower(nome))
    ");

    $status = [
        ["slug" => "disponivel", "nome" => "Disponível", "ordem" => 1],
        ["slug" => "em-uso", "nome" => "Em uso", "ordem" => 2],
        ["slug" => "homologacao", "nome" => "Homologação", "ordem" => 3],
        ["slug" => "manutencao", "nome" => "Manutenção", "ordem" => 4],
    ];

    $stmt = $pdo->prepare("
        insert into public.status_ativos (slug, nome, ordem, ativo)
        values (:slug, :nome, :ordem, true)
        on conflict (slug) do update
            set nome = excluded.nome,
                ordem = excluded.ordem,
                ativo = true,
                atualizado_em = now()
    ");

    foreach ($status as $item) {
        $stmt->execute([
            ":slug" => $item["slug"],
            ":nome" => $item["nome"],
            ":ordem" => $item["ordem"],
        ]);
    }

    $pdo->exec("
        update public.status_ativos
           set ativo = false,
               atualizado_em = now()
         where slug not in ('disponivel', 'em-uso', 'homologacao', 'manutencao')
    ");

    $pdo->exec("
        update public.ativos
           set status = case
                when status is null or btrim(status) = '' then 'Disponível'
                when lower(status) like 'dispon%' or lower(status) in ('estoque', 'em estoque') then 'Disponível'
                when lower(status) = 'em uso' then 'Em uso'
                when lower(status) like 'homologa%' then 'Homologação'
                when lower(status) like 'manuten%' or lower(status) like 'formata%' then 'Manutenção'
                when lower(status) in ('baixado', 'perdido') then 'Manutenção'
                else status
            end
         where status is null
            or btrim(status) = ''
            or lower(status) like 'dispon%'
            or lower(status) in ('estoque', 'em estoque', 'em uso', 'baixado', 'perdido')
            or lower(status) like 'homologa%'
            or lower(status) like 'manuten%'
            or lower(status) like 'formata%'
    ");

    $pdo->exec("
        do $$
        begin
            if not exists (
                select 1
                  from pg_constraint
                 where conname = 'status_ativos_nome_unique'
                   and conrelid = 'public.status_ativos'::regclass
            ) then
                alter table public.status_ativos
                    add constraint status_ativos_nome_unique unique (nome);
            end if;
        end $$;
    ");

    $pdo->exec("
        do $$
        begin
            if not exists (
                select 1
                  from pg_constraint
                 where conname = 'ativos_status_fkey'
                   and conrelid = 'public.ativos'::regclass
            ) then
                alter table public.ativos
                    add constraint ativos_status_fkey
                    foreign key (status)
                    references public.status_ativos (nome)
                    on update cascade
                    on delete restrict;
            end if;
        end $$;
    ");
}

function nomesStatusAtivos(PDO $pdo): array
{
    garantirStatusAtivos($pdo);

    $stmt = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
      order by ordem asc, nome asc
    ");
    $stmt->execute();

    return array_map(
        static fn(array $row): string => (string)($row["nome"] ?? ""),
        $stmt->fetchAll()
    );
}

function statusAtivoPadrao(): string
{
    return "Disponível";
}

function obterStatusAtivo(PDO $pdo, string $status): ?string
{
    garantirStatusAtivos($pdo);

    $valor = strtolower(trim($status));
    $slug = null;

    if (strpos($valor, "dispon") === 0) {
        $slug = "disponivel";
    } elseif ($valor === "em uso") {
        $slug = "em-uso";
    } elseif (strpos($valor, "homologa") === 0) {
        $slug = "homologacao";
    } elseif (strpos($valor, "manuten") === 0 || strpos($valor, "formata") === 0 || $valor === "baixado" || $valor === "perdido") {
        $slug = "manutencao";
    }

    if ($slug !== null) {
        $stmt = $pdo->prepare("
            select nome
              from public.status_ativos
             where ativo = true
               and slug = :slug
             limit 1
        ");
        $stmt->execute([":slug" => $slug]);

        $nome = $stmt->fetchColumn();

        return $nome !== false ? (string)$nome : null;
    }

    $stmt = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
           and lower(nome) = lower(:status)
         limit 1
    ");
    $stmt->execute([":status" => trim($status)]);

    $nome = $stmt->fetchColumn();

    return $nome !== false ? (string)$nome : null;
}

function classeStatusAtivo(string $status): string
{
    $valor = strtolower(trim($status));

    if (strpos($valor, "dispon") === 0) {
        return "status-badge status-available";
    }

    if ($valor === "em uso") {
        return "status-badge status-in-use";
    }

    if (strpos($valor, "homologa") === 0) {
        return "status-badge status-homologation";
    }

    if (strpos($valor, "manuten") === 0) {
        return "status-badge status-maintenance";
    }

    return "status-badge status-neutral";
}

