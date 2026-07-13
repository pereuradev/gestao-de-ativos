-- Remove campos do modelo inicial que nao participam mais do fluxo de ativos importados.
-- Esta limpeza e destrutiva e depende da confirmacao previa de que os dados nao sao consumidos.

alter table public.ativos
  drop column if exists fornecedor_id,
  drop column if exists data_compra,
  drop column if exists valor_compra,
  drop column if exists garantia_ate,
  drop column if exists criado_por;;
