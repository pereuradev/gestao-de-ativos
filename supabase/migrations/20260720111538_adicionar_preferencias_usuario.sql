begin;

alter table public.perfis_usuarios
    add column if not exists preferencia_tema text not null default 'dark',
    add column if not exists preferencia_cor text not null default 'teal',
    add column if not exists preferencia_tamanho_fonte text not null default 'default',
    add column if not exists preferencia_densidade text not null default 'comfortable',
    add column if not exists preferencia_movimento text not null default 'normal',
    add column if not exists preferencia_cursor text not null default 'enhanced';

alter table public.perfis_usuarios
    drop constraint if exists perfis_usuarios_preferencia_tema_check,
    drop constraint if exists perfis_usuarios_preferencia_cor_check,
    drop constraint if exists perfis_usuarios_preferencia_tamanho_fonte_check,
    drop constraint if exists perfis_usuarios_preferencia_densidade_check,
    drop constraint if exists perfis_usuarios_preferencia_movimento_check,
    drop constraint if exists perfis_usuarios_preferencia_cursor_check;

alter table public.perfis_usuarios
    add constraint perfis_usuarios_preferencia_tema_check
        check (preferencia_tema in ('dark', 'light', 'auto')),
    add constraint perfis_usuarios_preferencia_cor_check
        check (preferencia_cor in ('teal', 'green', 'blue', 'violet')),
    add constraint perfis_usuarios_preferencia_tamanho_fonte_check
        check (preferencia_tamanho_fonte in ('small', 'default', 'large', 'extra')),
    add constraint perfis_usuarios_preferencia_densidade_check
        check (preferencia_densidade in ('comfortable', 'compact')),
    add constraint perfis_usuarios_preferencia_movimento_check
        check (preferencia_movimento in ('normal', 'reduced')),
    add constraint perfis_usuarios_preferencia_cursor_check
        check (preferencia_cursor in ('enhanced', 'normal'));

comment on column public.perfis_usuarios.preferencia_tema is
    'Preferencia de tema visual do usuario: dark, light ou auto.';
comment on column public.perfis_usuarios.preferencia_cor is
    'Paleta de destaque escolhida pelo usuario.';
comment on column public.perfis_usuarios.preferencia_tamanho_fonte is
    'Escala de fonte escolhida pelo usuario.';
comment on column public.perfis_usuarios.preferencia_densidade is
    'Densidade visual escolhida pelo usuario.';
comment on column public.perfis_usuarios.preferencia_movimento is
    'Nivel de movimento e animacao escolhido pelo usuario.';
comment on column public.perfis_usuarios.preferencia_cursor is
    'Preferencia do cursor personalizado do sistema.';

grant select (
    preferencia_tema,
    preferencia_cor,
    preferencia_tamanho_fonte,
    preferencia_densidade,
    preferencia_movimento,
    preferencia_cursor
) on public.perfis_usuarios to authenticated;

grant update (
    preferencia_tema,
    preferencia_cor,
    preferencia_tamanho_fonte,
    preferencia_densidade,
    preferencia_movimento,
    preferencia_cursor
) on public.perfis_usuarios to authenticated;

commit;
