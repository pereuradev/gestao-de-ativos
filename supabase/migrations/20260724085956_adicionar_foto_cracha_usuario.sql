begin;

alter table public.perfis_usuarios
    add column if not exists foto_cracha text;

alter table public.perfis_usuarios
    drop constraint if exists perfis_usuarios_foto_cracha_check;

alter table public.perfis_usuarios
    add constraint perfis_usuarios_foto_cracha_check
        check (
            foto_cracha is null
            or foto_cracha ~ '^cracha-[A-Za-z0-9_-]+-[a-f0-9]{16}\.(jpg|png|webp)$'
        );

comment on column public.perfis_usuarios.foto_cracha is
    'Nome do arquivo da foto do cracha salvo no servidor da aplicacao.';

grant select (foto_cracha) on public.perfis_usuarios to authenticated;
grant update (foto_cracha) on public.perfis_usuarios to authenticated;

commit;
