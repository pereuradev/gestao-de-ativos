-- Amplia temporariamente os status aceitos para importar a planilha legada.
-- O default acompanha o texto exibido pelo portal durante essa etapa de transicao.

alter table public.ativos drop constraint if exists ativos_status_check;

alter table public.ativos add constraint ativos_status_check
check (status in ('Disponível', 'Em uso', 'Manutenção', 'Formatação', 'Homologação', 'Baixado', 'Perdido'));

alter table public.ativos alter column status set default 'Disponível';;
