-- Adiciona os campos necessarios para preservar e revisar os dados da planilha importada.
-- As colunas auxiliares de rastreabilidade serao removidas depois da consolidacao.

alter table public.ativos
  add column if not exists marca text,
  add column if not exists marca_agrupada text,
  add column if not exists propriedade text,
  add column if not exists imei text,
  add column if not exists datasheet text,
  add column if not exists numero_serie_original text,
  add column if not exists linha_excel integer;

-- Estes indices atendem as consultas de agrupamento e diagnostico da importacao.
create index if not exists ativos_marca_idx on public.ativos(marca);
create index if not exists ativos_linha_excel_idx on public.ativos(linha_excel);;
