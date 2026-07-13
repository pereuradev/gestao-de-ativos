-- Exclui categorias criadas durante a importacao que nao ficaram vinculadas a nenhum ativo.
-- Categorias referenciadas sao preservadas pela condicao NOT EXISTS.

delete from public.categorias_ativos c
where not exists (
  select 1
  from public.ativos a
  where a.categoria_id = c.id
);;
