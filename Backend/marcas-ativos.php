<?php

declare(strict_types=1);

// Mantem a tabela de marcas alinhada com os textos ja existentes em ativos.marca.
function garantirMarcasAtivos(PDO $pdo): void
{
    // Cria a tabela oficial de marcas caso ela ainda nao exista.
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

    // Evita duplicidade quando a diferenca e apenas maiuscula/minuscula.
    $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

    // Limpa marcas vazias gravadas como string para o banco tratar como ausencia.
    $pdo->exec("
        update public.ativos
           set marca = null
         where marca is not null
           and btrim(marca) = ''
    ");

    // Remove espacos sobrando no inicio/fim das marcas ja gravadas.
    $pdo->exec("
        update public.ativos
           set marca = btrim(marca)
         where marca is not null
           and marca <> btrim(marca)
    ");

    // Transforma marcas ja digitadas nos ativos em registros oficiais ativos.
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

    // Ajusta o texto dos ativos para bater exatamente com o nome oficial.
    $pdo->exec("
        update public.ativos a
           set marca = m.nome
          from public.marcas_ativos m
         where a.marca is not null
           and lower(a.marca) = lower(m.nome)
           and a.marca <> m.nome
    ");

    // Garante unicidade por nome para permitir a chave estrangeira por texto.
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

    // Depois da limpeza, protege ativos.marca contra nomes que nao existem na tabela.
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
