-- Remove colunas auxiliares depois da consolidacao do numero de serie e da marca.
-- Tambem garante o status final obrigatorio para bancos que vieram da importacao.

alter table public.ativos
  drop column if exists numero_serie_original,
  drop column if exists marca_agrupada;

alter table public.ativos
  add column if not exists status text not null default 'Disponível';;
