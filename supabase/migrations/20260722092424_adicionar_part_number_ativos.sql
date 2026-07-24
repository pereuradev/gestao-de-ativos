begin;

set local lock_timeout = '10s';
set local statement_timeout = '2min';

alter table public.ativos
    add column if not exists part_number text;

update public.ativos
   set part_number = null
 where part_number is not null
   and btrim(part_number) = '';

comment on column public.ativos.part_number is
    'Part number do ativo. Pode se repetir entre varias unidades do mesmo produto.';

commit;
