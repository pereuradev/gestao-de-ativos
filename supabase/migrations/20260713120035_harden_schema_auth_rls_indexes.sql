-- Consolida o drift criado anteriormente em runtime e endurece o schema exposto pelo Supabase.
-- A ordem e intencional: preflight, normalizacao, FKs/Auth e somente depois RLS e grants.
-- Toda a mudanca e atomica para impedir que uma falha deixe o banco parcialmente migrado.

begin;

set local lock_timeout = '10s';
set local statement_timeout = '2min';

create extension if not exists pgcrypto;

-- Objetos que existiam apenas no runtime passam a fazer parte do historico.
alter table public.perfis_usuarios
    add column if not exists senha text;

alter table public.locais
    add column if not exists status text not null default 'Ativo';

alter table public.ativos
    add column if not exists responsavel_id uuid,
    add column if not exists responsavel text;

create table if not exists public.marcas_ativos (
    id uuid primary key default gen_random_uuid(),
    nome text not null,
    status text not null default 'Ativa',
    criado_em timestamptz not null default now(),
    atualizado_em timestamptz not null default now()
);

create table if not exists public.propriedade_ativos (
    id uuid primary key default gen_random_uuid(),
    nome text not null,
    status text not null default 'Ativa',
    criado_em timestamptz not null default now(),
    atualizado_em timestamptz not null default now()
);

create table if not exists public.status_ativos (
    id uuid primary key default gen_random_uuid(),
    nome text not null,
    slug text not null,
    ordem integer not null default 0,
    ativo boolean not null default true,
    criado_em timestamptz not null default now(),
    atualizado_em timestamptz not null default now()
);

create table if not exists public.grupos_acesso (
    id uuid primary key default gen_random_uuid(),
    nome varchar(90) not null,
    descricao text,
    status varchar(20) not null default 'Ativo',
    criado_por uuid,
    criado_em timestamptz not null default now(),
    atualizado_em timestamptz not null default now()
);

create table if not exists public.grupos_acesso_membros (
    grupo_id uuid not null,
    usuario_id uuid not null,
    criado_em timestamptz not null default now(),
    constraint grupos_acesso_membros_pkey primary key (grupo_id, usuario_id)
);

create table if not exists public.grupos_acesso_permissoes (
    grupo_id uuid not null,
    permissao varchar(80) not null,
    criado_em timestamptz not null default now(),
    constraint grupos_acesso_permissoes_pkey primary key (grupo_id, permissao)
);

alter table public.grupos_acesso
    alter column id set default gen_random_uuid();

create or replace function public.definir_atualizado_em()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
    new.atualizado_em = now();
    return new;
end;
$$;

drop trigger if exists definir_marcas_ativos_atualizado_em on public.marcas_ativos;
create trigger definir_marcas_ativos_atualizado_em
before update on public.marcas_ativos
for each row execute function public.definir_atualizado_em();

drop trigger if exists definir_propriedade_ativos_atualizado_em on public.propriedade_ativos;
create trigger definir_propriedade_ativos_atualizado_em
before update on public.propriedade_ativos
for each row execute function public.definir_atualizado_em();

drop trigger if exists definir_status_ativos_atualizado_em on public.status_ativos;
create trigger definir_status_ativos_atualizado_em
before update on public.status_ativos
for each row execute function public.definir_atualizado_em();

drop trigger if exists definir_grupos_acesso_atualizado_em on public.grupos_acesso;
create trigger definir_grupos_acesso_atualizado_em
before update on public.grupos_acesso
for each row execute function public.definir_atualizado_em();

-- As FKs sao recriadas abaixo com regras canonicas e nomes estaveis.
alter table public.perfis_usuarios
    drop constraint if exists perfis_usuarios_id_fkey;

alter table public.ativos
    drop constraint if exists ativos_categoria_id_fkey,
    drop constraint if exists ativos_local_id_fkey,
    drop constraint if exists ativos_responsavel_id_fkey,
    drop constraint if exists ativos_marca_fkey,
    drop constraint if exists ativos_propriedade_fkey,
    drop constraint if exists ativos_status_fkey;

alter table public.grupos_acesso
    drop constraint if exists grupos_acesso_criado_por_fkey;

alter table public.grupos_acesso_membros
    drop constraint if exists grupos_acesso_membros_grupo_id_fkey,
    drop constraint if exists grupos_acesso_membros_usuario_id_fkey;

alter table public.grupos_acesso_permissoes
    drop constraint if exists grupos_acesso_permissoes_grupo_id_fkey;

-- Interrompe a migration antes de trocar constraints quando ha colisao real.
do $preflight$
begin
    if exists (
        select 1
          from public.perfis_usuarios
         where nullif(btrim(email), '') is not null
         group by lower(btrim(email))
        having count(*) > 1
    ) then
        raise exception 'Existem e-mails duplicados apos normalizacao.'
            using hint = 'Corrija os e-mails conflitantes antes de aplicar a migration.';
    end if;

    if exists (
        select 1
          from public.perfis_usuarios
         where nullif(regexp_replace(cpf, '[^0-9]', '', 'g'), '') is not null
         group by regexp_replace(cpf, '[^0-9]', '', 'g')
        having count(*) > 1
    ) then
        raise exception 'Existem CPFs duplicados apos remover a mascara.';
    end if;

    if exists (
        select 1
          from public.perfis_usuarios
         where nullif(regexp_replace(rg, '[^0-9]', '', 'g'), '') is not null
         group by regexp_replace(rg, '[^0-9]', '', 'g')
        having count(*) > 1
    ) then
        raise exception 'Existem RGs duplicados apos remover a mascara.';
    end if;

    if exists (
        select 1
          from public.ativos
         where nullif(btrim(numero_serie), '') is not null
         group by lower(btrim(numero_serie))
        having count(*) > 1
    ) then
        raise exception 'Existem numeros de serie duplicados apos normalizacao.';
    end if;

    if exists (
        select 1
          from public.ativos
         where btrim(imei) ~ '^[0-9]{8,20}$'
         group by btrim(imei)
        having count(*) > 1
    ) then
        raise exception 'Existem IMEIs numericos duplicados.';
    end if;

    if exists (
        select 1 from public.categorias_ativos
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem categorias duplicadas apos normalizacao.';
    end if;

    if exists (
        select 1 from public.locais
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem locais duplicados apos normalizacao.';
    end if;

    if exists (
        select 1 from public.marcas_ativos
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem marcas duplicadas apos normalizacao.';
    end if;

    if exists (
        select 1 from public.propriedade_ativos
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem propriedades duplicadas apos normalizacao.';
    end if;

    if exists (
        select 1 from public.status_ativos
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem status duplicados apos normalizacao.';
    end if;

    if exists (
        select 1 from public.status_ativos
         group by lower(btrim(slug)) having count(*) > 1
    ) then
        raise exception 'Existem slugs de status duplicados apos normalizacao.';
    end if;

    if exists (
        select 1 from public.grupos_acesso
         group by lower(btrim(nome)) having count(*) > 1
    ) then
        raise exception 'Existem grupos de acesso duplicados apos normalizacao.';
    end if;

    if exists (
        select 1
          from public.perfis_usuarios p
          left join auth.users usuario_id on usuario_id.id = p.id
         where usuario_id.id is null
           and not exists (
               select 1
                 from auth.users usuario_email
                where lower(btrim(usuario_email.email)) = lower(btrim(p.email))
           )
    ) then
        raise exception 'Ha perfil local sem conta correspondente no Supabase Auth.'
            using hint = 'Crie ou vincule a conta Auth antes de aplicar a migration.';
    end if;

    if exists (
        select 1
          from public.perfis_usuarios p
          join auth.users usuario_email
            on lower(btrim(usuario_email.email)) = lower(btrim(p.email))
           and usuario_email.id <> p.id
          join public.perfis_usuarios conflito on conflito.id = usuario_email.id
    ) then
        raise exception 'A sincronizacao Auth encontrou UUID de perfil ja utilizado.';
    end if;
end;
$preflight$;

-- Canonicaliza os catalogos antes de recriar as FKs por texto.
update public.marcas_ativos set nome = btrim(nome) where nome <> btrim(nome);
update public.propriedade_ativos set nome = btrim(nome) where nome <> btrim(nome);
update public.status_ativos
   set nome = btrim(nome), slug = lower(btrim(slug))
 where nome <> btrim(nome) or slug <> lower(btrim(slug));
update public.grupos_acesso set nome = btrim(nome) where nome <> btrim(nome);
update public.categorias_ativos set nome = btrim(nome) where nome <> btrim(nome);
update public.locais set nome = btrim(nome) where nome <> btrim(nome);

update public.ativos set marca = null where marca is not null and btrim(marca) = '';
update public.ativos set propriedade = null where propriedade is not null and btrim(propriedade) = '';

insert into public.marcas_ativos (nome, status)
select min(btrim(a.marca)), 'Ativa'
  from public.ativos a
 where nullif(btrim(a.marca), '') is not null
   and not exists (
       select 1
         from public.marcas_ativos m
        where lower(btrim(m.nome)) = lower(btrim(a.marca))
   )
 group by lower(btrim(a.marca))
on conflict do nothing;

insert into public.propriedade_ativos (nome, status)
select min(btrim(a.propriedade)), 'Ativa'
  from public.ativos a
 where nullif(btrim(a.propriedade), '') is not null
   and not exists (
       select 1
         from public.propriedade_ativos p
        where lower(btrim(p.nome)) = lower(btrim(a.propriedade))
   )
 group by lower(btrim(a.propriedade))
on conflict do nothing;

update public.ativos a
   set marca = m.nome
  from public.marcas_ativos m
 where a.marca is not null
   and lower(btrim(a.marca)) = lower(btrim(m.nome))
   and a.marca is distinct from m.nome;

update public.ativos a
   set propriedade = p.nome
  from public.propriedade_ativos p
 where a.propriedade is not null
   and lower(btrim(a.propriedade)) = lower(btrim(p.nome))
   and a.propriedade is distinct from p.nome;

-- Os quatro status-base continuam editaveis pelo catalogo, sem check estatico no ativo.
insert into public.status_ativos (slug, nome, ordem, ativo)
values
    ('disponivel', U&'Dispon\00EDvel', 1, true),
    ('em-uso', 'Em uso', 2, true),
    ('homologacao', U&'Homologa\00E7\00E3o', 3, true),
    ('manutencao', U&'Manuten\00E7\00E3o', 4, true)
on conflict do nothing;

update public.status_ativos
   set nome = case slug
       when 'disponivel' then U&'Dispon\00EDvel'
       when 'em-uso' then 'Em uso'
       when 'homologacao' then U&'Homologa\00E7\00E3o'
       when 'manutencao' then U&'Manuten\00E7\00E3o'
       else nome
   end,
       ordem = case slug
       when 'disponivel' then 1
       when 'em-uso' then 2
       when 'homologacao' then 3
       when 'manutencao' then 4
       else ordem
   end,
       ativo = true
 where slug in ('disponivel', 'em-uso', 'homologacao', 'manutencao');

update public.ativos
   set status = case
       when status is null or btrim(status) = '' then U&'Dispon\00EDvel'
       when lower(btrim(status)) like 'dispon%'
         or lower(btrim(status)) in ('estoque', 'em estoque') then U&'Dispon\00EDvel'
       when lower(btrim(status)) = 'em uso' then 'Em uso'
       when lower(btrim(status)) like 'homologa%' then U&'Homologa\00E7\00E3o'
       when lower(btrim(status)) like 'manuten%'
         or lower(btrim(status)) like 'formata%'
         or lower(btrim(status)) in ('baixado', 'perdido') then U&'Manuten\00E7\00E3o'
       else btrim(status)
   end;

-- Constraints antigas exatas sao substituidas por unicidade normalizada.
alter table public.perfis_usuarios
    drop constraint if exists perfis_usuarios_email_key,
    drop constraint if exists perfis_usuarios_cpf_key,
    drop constraint if exists perfis_usuarios_rg_key;

drop index if exists public.perfis_usuarios_email_key;
drop index if exists public.perfis_usuarios_cpf_key;
drop index if exists public.perfis_usuarios_rg_key;

create unique index perfis_usuarios_email_key
    on public.perfis_usuarios (lower(btrim(email)))
 where nullif(btrim(email), '') is not null;

create unique index perfis_usuarios_cpf_key
    on public.perfis_usuarios (regexp_replace(cpf, '[^0-9]', '', 'g'))
 where nullif(regexp_replace(cpf, '[^0-9]', '', 'g'), '') is not null;

create unique index perfis_usuarios_rg_key
    on public.perfis_usuarios (regexp_replace(rg, '[^0-9]', '', 'g'))
 where nullif(regexp_replace(rg, '[^0-9]', '', 'g'), '') is not null;

alter table public.ativos
    drop constraint if exists ativos_numero_serie_key,
    drop constraint if exists ativos_status_check;

drop index if exists public.ativos_numero_serie_key;
drop index if exists public.ativos_numero_serie_unico_idx;
drop index if exists public.ativos_imei_numerico_unico_idx;
drop index if exists public.ativos_imei_numerico_unico_v2_idx;

create unique index ativos_numero_serie_key
    on public.ativos (lower(btrim(numero_serie)))
 where nullif(btrim(numero_serie), '') is not null;

create unique index ativos_imei_numerico_unico_v2_idx
    on public.ativos (btrim(imei))
 where btrim(imei) ~ '^[0-9]{8,20}$';

alter table public.marcas_ativos
    drop constraint if exists marcas_ativos_nome_unique,
    drop constraint if exists marcas_ativos_status_check;
alter table public.propriedade_ativos
    drop constraint if exists propriedade_ativos_nome_unique,
    drop constraint if exists propriedade_ativos_status_check;
alter table public.grupos_acesso
    drop constraint if exists grupos_acesso_status_check;
alter table public.locais
    drop constraint if exists locais_status_check;

do $constraints$
begin
    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.marcas_ativos'::regclass
           and conname = 'marcas_ativos_nome_key'
    ) then
        alter table public.marcas_ativos
            add constraint marcas_ativos_nome_key unique (nome);
    end if;

    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.propriedade_ativos'::regclass
           and conname = 'propriedade_ativos_nome_key'
    ) then
        alter table public.propriedade_ativos
            add constraint propriedade_ativos_nome_key unique (nome);
    end if;

    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.status_ativos'::regclass
           and conname = 'status_ativos_nome_unique'
    ) then
        alter table public.status_ativos
            add constraint status_ativos_nome_unique unique (nome);
    end if;

    if not exists (
        select 1 from pg_constraint
         where conrelid = 'public.status_ativos'::regclass
           and conname = 'status_ativos_slug_key'
    ) then
        alter table public.status_ativos
            add constraint status_ativos_slug_key unique (slug);
    end if;
end;
$constraints$;

alter table public.marcas_ativos
    add constraint marcas_ativos_status_check
    check (status in ('Ativa', 'Inativa')) not valid;
alter table public.marcas_ativos validate constraint marcas_ativos_status_check;

alter table public.propriedade_ativos
    add constraint propriedade_ativos_status_check
    check (status in ('Ativa', 'Inativa')) not valid;
alter table public.propriedade_ativos validate constraint propriedade_ativos_status_check;

alter table public.grupos_acesso
    add constraint grupos_acesso_status_check
    check (status in ('Ativo', 'Inativo')) not valid;
alter table public.grupos_acesso validate constraint grupos_acesso_status_check;

alter table public.locais
    add constraint locais_status_check
    check (status in ('Ativo', 'Inativo')) not valid;
alter table public.locais validate constraint locais_status_check;

drop index if exists public.categorias_ativos_nome_lower_unique;
drop index if exists public.locais_nome_lower_unique;
drop index if exists public.marcas_ativos_nome_lower_unique;
drop index if exists public.propriedade_ativos_nome_lower_unique;
drop index if exists public.status_ativos_nome_lower_unique;
drop index if exists public.grupos_acesso_nome_lower_unique;

create unique index categorias_ativos_nome_lower_unique
    on public.categorias_ativos (lower(btrim(nome)));
create unique index locais_nome_lower_unique
    on public.locais (lower(btrim(nome)));
create unique index marcas_ativos_nome_lower_unique
    on public.marcas_ativos (lower(btrim(nome)));
create unique index propriedade_ativos_nome_lower_unique
    on public.propriedade_ativos (lower(btrim(nome)));
create unique index status_ativos_nome_lower_unique
    on public.status_ativos (lower(btrim(nome)));
create unique index grupos_acesso_nome_lower_unique
    on public.grupos_acesso (lower(btrim(nome)));

create index if not exists ativos_categoria_id_idx on public.ativos (categoria_id);
create index if not exists ativos_local_id_idx on public.ativos (local_id);
create index if not exists ativos_responsavel_id_idx on public.ativos (responsavel_id);
create index if not exists ativos_marca_idx on public.ativos (marca);
create index if not exists ativos_propriedade_idx on public.ativos (propriedade);
create index if not exists ativos_status_idx on public.ativos (status);
create index if not exists grupos_acesso_criado_por_idx on public.grupos_acesso (criado_por);
create index if not exists grupos_acesso_membros_usuario_id_idx
    on public.grupos_acesso_membros (usuario_id);
create index if not exists status_ativos_ativo_ordem_idx
    on public.status_ativos (ativo, ordem, nome);

-- FKs recebem NOT VALID para separar criacao e varredura, depois sao validadas.
alter table public.ativos
    add constraint ativos_categoria_id_fkey
    foreign key (categoria_id) references public.categorias_ativos (id)
    on update cascade on delete restrict not valid,
    add constraint ativos_local_id_fkey
    foreign key (local_id) references public.locais (id)
    on update cascade on delete restrict not valid,
    add constraint ativos_responsavel_id_fkey
    foreign key (responsavel_id) references public.perfis_usuarios (id)
    on update cascade on delete set null not valid,
    add constraint ativos_marca_fkey
    foreign key (marca) references public.marcas_ativos (nome)
    on update cascade on delete restrict not valid,
    add constraint ativos_propriedade_fkey
    foreign key (propriedade) references public.propriedade_ativos (nome)
    on update cascade on delete restrict not valid,
    add constraint ativos_status_fkey
    foreign key (status) references public.status_ativos (nome)
    on update cascade on delete restrict not valid;

alter table public.ativos validate constraint ativos_categoria_id_fkey;
alter table public.ativos validate constraint ativos_local_id_fkey;
alter table public.ativos validate constraint ativos_responsavel_id_fkey;
alter table public.ativos validate constraint ativos_marca_fkey;
alter table public.ativos validate constraint ativos_propriedade_fkey;
alter table public.ativos validate constraint ativos_status_fkey;

alter table public.grupos_acesso
    add constraint grupos_acesso_criado_por_fkey
    foreign key (criado_por) references public.perfis_usuarios (id)
    on update cascade on delete set null not valid;
alter table public.grupos_acesso validate constraint grupos_acesso_criado_por_fkey;

alter table public.grupos_acesso_membros
    add constraint grupos_acesso_membros_grupo_id_fkey
    foreign key (grupo_id) references public.grupos_acesso (id)
    on update cascade on delete cascade not valid,
    add constraint grupos_acesso_membros_usuario_id_fkey
    foreign key (usuario_id) references public.perfis_usuarios (id)
    on update cascade on delete cascade not valid;
alter table public.grupos_acesso_membros
    validate constraint grupos_acesso_membros_grupo_id_fkey;
alter table public.grupos_acesso_membros
    validate constraint grupos_acesso_membros_usuario_id_fkey;

alter table public.grupos_acesso_permissoes
    add constraint grupos_acesso_permissoes_grupo_id_fkey
    foreign key (grupo_id) references public.grupos_acesso (id)
    on update cascade on delete cascade not valid;
alter table public.grupos_acesso_permissoes
    validate constraint grupos_acesso_permissoes_grupo_id_fkey;

-- Contas locais com o mesmo e-mail recebem o UUID canonico de auth.users.
update public.perfis_usuarios p
   set id = usuario_email.id,
       atualizado_em = now()
  from auth.users usuario_email
 where p.id <> usuario_email.id
   and lower(btrim(p.email)) = lower(btrim(usuario_email.email))
   and not exists (select 1 from auth.users usuario_id where usuario_id.id = p.id);

do $auth_integrity$
begin
    if exists (
        select 1
          from public.perfis_usuarios p
          left join auth.users usuario on usuario.id = p.id
         where usuario.id is null
    ) then
        raise exception 'Nao foi possivel vincular todos os perfis a auth.users.';
    end if;
end;
$auth_integrity$;

alter table public.perfis_usuarios
    add constraint perfis_usuarios_id_fkey
    foreign key (id) references auth.users (id)
    on update cascade on delete cascade not valid;
alter table public.perfis_usuarios validate constraint perfis_usuarios_id_fkey;

create or replace function public.eh_administrador()
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
    select exists (
        select 1
          from public.perfis_usuarios
         where id = (select auth.uid())
           and tipo_usuario = 'Administrador'
           and status = 'Ativo'
    );
$$;

alter table public.perfis_usuarios enable row level security;
alter table public.categorias_ativos enable row level security;
alter table public.locais enable row level security;
alter table public.ativos enable row level security;
alter table public.marcas_ativos enable row level security;
alter table public.propriedade_ativos enable row level security;
alter table public.status_ativos enable row level security;
alter table public.grupos_acesso enable row level security;
alter table public.grupos_acesso_membros enable row level security;
alter table public.grupos_acesso_permissoes enable row level security;

-- Remove as politicas conhecidas para que este arquivo seja a fonte canonica.
drop policy if exists "Usuarios podem ler o proprio perfil" on public.perfis_usuarios;
drop policy if exists "Usuarios podem inserir o proprio perfil" on public.perfis_usuarios;
drop policy if exists "Usuarios podem atualizar o proprio perfil" on public.perfis_usuarios;

drop policy if exists "Usuarios autenticados podem ler categorias" on public.categorias_ativos;
drop policy if exists "Administradores podem inserir categorias" on public.categorias_ativos;
drop policy if exists "Administradores podem atualizar categorias" on public.categorias_ativos;
drop policy if exists "Administradores podem excluir categorias" on public.categorias_ativos;
drop policy if exists "Administradores podem gerenciar categorias" on public.categorias_ativos;

drop policy if exists "Usuarios autenticados podem ler locais" on public.locais;
drop policy if exists "Administradores podem inserir locais" on public.locais;
drop policy if exists "Administradores podem atualizar locais" on public.locais;
drop policy if exists "Administradores podem excluir locais" on public.locais;
drop policy if exists "Administradores podem gerenciar locais" on public.locais;

drop policy if exists "Usuarios autenticados podem ler ativos" on public.ativos;
drop policy if exists "Administradores podem inserir ativos" on public.ativos;
drop policy if exists "Administradores podem atualizar ativos" on public.ativos;
drop policy if exists "Administradores podem excluir ativos" on public.ativos;
drop policy if exists "Administradores podem gerenciar ativos" on public.ativos;

drop policy if exists "Usuarios autenticados podem ler marcas" on public.marcas_ativos;
drop policy if exists "Administradores podem gerenciar marcas" on public.marcas_ativos;
drop policy if exists "Usuarios autenticados podem ler propriedades" on public.propriedade_ativos;
drop policy if exists "Administradores podem gerenciar propriedades" on public.propriedade_ativos;
drop policy if exists "Usuarios autenticados podem ler status" on public.status_ativos;
drop policy if exists "Administradores podem gerenciar status" on public.status_ativos;
drop policy if exists "Administradores podem gerenciar grupos" on public.grupos_acesso;
drop policy if exists "Administradores podem gerenciar membros de grupos" on public.grupos_acesso_membros;
drop policy if exists "Administradores podem gerenciar permissoes de grupos" on public.grupos_acesso_permissoes;

create policy "Usuarios podem ler o proprio perfil"
on public.perfis_usuarios for select to authenticated
using (id = (select auth.uid()) or (select public.eh_administrador()));

create policy "Usuarios podem inserir o proprio perfil"
on public.perfis_usuarios for insert to authenticated
with check (
    (
        id = (select auth.uid())
        and lower(btrim(email)) = lower(btrim(coalesce((select auth.jwt() ->> 'email'), '')))
    )
    or (select public.eh_administrador())
);

create policy "Usuarios podem atualizar o proprio perfil"
on public.perfis_usuarios for update to authenticated
using (id = (select auth.uid()) or (select public.eh_administrador()))
with check (
    (
        id = (select auth.uid())
        and lower(btrim(email)) = lower(btrim(coalesce((select auth.jwt() ->> 'email'), '')))
    )
    or (select public.eh_administrador())
);

create policy "Usuarios autenticados podem ler categorias"
on public.categorias_ativos for select to authenticated using (true);
create policy "Administradores podem gerenciar categorias"
on public.categorias_ativos for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler locais"
on public.locais for select to authenticated using (true);
create policy "Administradores podem gerenciar locais"
on public.locais for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler ativos"
on public.ativos for select to authenticated using (true);
create policy "Administradores podem gerenciar ativos"
on public.ativos for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler marcas"
on public.marcas_ativos for select to authenticated using (true);
create policy "Administradores podem gerenciar marcas"
on public.marcas_ativos for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler propriedades"
on public.propriedade_ativos for select to authenticated using (true);
create policy "Administradores podem gerenciar propriedades"
on public.propriedade_ativos for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler status"
on public.status_ativos for select to authenticated using (true);
create policy "Administradores podem gerenciar status"
on public.status_ativos for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem gerenciar grupos"
on public.grupos_acesso for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem gerenciar membros de grupos"
on public.grupos_acesso_membros for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem gerenciar permissoes de grupos"
on public.grupos_acesso_permissoes for all to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

revoke all privileges on table
    public.perfis_usuarios,
    public.categorias_ativos,
    public.locais,
    public.ativos,
    public.marcas_ativos,
    public.propriedade_ativos,
    public.status_ativos,
    public.grupos_acesso,
    public.grupos_acesso_membros,
    public.grupos_acesso_permissoes
from public, anon, authenticated;

grant select on table public.perfis_usuarios to authenticated;
grant insert (
    id, nome_completo, email, departamento, empresa, rg, cpf, celular, data_nascimento
) on public.perfis_usuarios to authenticated;
grant update (
    nome_completo, email, departamento, empresa, rg, cpf, celular, data_nascimento
) on public.perfis_usuarios to authenticated;

grant select, insert, update, delete on table
    public.categorias_ativos,
    public.locais,
    public.ativos,
    public.marcas_ativos,
    public.propriedade_ativos,
    public.status_ativos,
    public.grupos_acesso,
    public.grupos_acesso_membros,
    public.grupos_acesso_permissoes
to authenticated;

grant all privileges on table
    public.perfis_usuarios,
    public.categorias_ativos,
    public.locais,
    public.ativos,
    public.marcas_ativos,
    public.propriedade_ativos,
    public.status_ativos,
    public.grupos_acesso,
    public.grupos_acesso_membros,
    public.grupos_acesso_permissoes
to service_role;

revoke all privileges on function public.eh_administrador() from public, anon;
grant execute on function public.eh_administrador() to authenticated, service_role;

revoke all privileges on function public.definir_atualizado_em() from public, anon, authenticated;
grant execute on function public.definir_atualizado_em() to service_role;

commit;
