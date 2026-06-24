<?php

declare(strict_types=1);

function garantirMarcasAtivos(PDO $pdo): void
{
    $pdo->exec("
        create table if not exists public.marcas_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

    $pdo->exec("
        update public.ativos
           set marca = null
         where marca is not null
           and btrim(marca) = ''
    ");

    $pdo->exec("
        update public.ativos
           set marca = btrim(marca)
         where marca is not null
           and marca <> btrim(marca)
    ");

    $pdo->exec("
        insert into public.marcas_ativos (nome, status)
        select distinct btrim(a.marca), 'Ativa'
          from public.ativos a
     left join public.marcas_ativos m on lower(m.nome) = lower(btrim(a.marca))
         where a.marca is not null
           and btrim(a.marca) <> ''
           and m.id is null
        on conflict do nothing
    ");

    $pdo->exec("
        update public.ativos a
           set marca = m.nome
          from public.marcas_ativos m
         where a.marca is not null
           and lower(a.marca) = lower(m.nome)
           and a.marca <> m.nome
    ");

    $pdo->exec("
        do $$
        begin
            if not exists (
                select 1
                  from pg_constraint
                 where conname = 'marcas_ativos_nome_unique'
                   and conrelid = 'public.marcas_ativos'::regclass
            ) then
                alter table public.marcas_ativos
                    add constraint marcas_ativos_nome_unique unique (nome);
            end if;
        end $$;
    ");

    $pdo->exec("
        do $$
        begin
            if not exists (
                select 1
                  from pg_constraint
                 where conname = 'ativos_marca_fkey'
                   and conrelid = 'public.ativos'::regclass
            ) then
                alter table public.ativos
                    add constraint ativos_marca_fkey
                    foreign key (marca)
                    references public.marcas_ativos (nome)
                    on update cascade
                    on delete restrict;
            end if;
        end $$;
    ");
}
