-- Cria uma RPC temporaria para importar em lote o JSON produzido a partir da planilha.
-- SECURITY DEFINER e o grant para anon sao limitados a esta janela e revogados na proxima migration.

create or replace function public.importar_ativos_excel(dados jsonb)
returns integer
language plpgsql
security definer
set search_path = public
as $$
declare
  item jsonb;
  categoria_uuid uuid;
  local_uuid uuid;
  total integer := 0;
begin
  for item in select * from jsonb_array_elements(dados)
  loop
    insert into public.categorias_ativos (nome)
    values (coalesce(nullif(item->>'tipo', ''), 'Sem categoria'))
    on conflict (nome) do update set nome = excluded.nome
    returning id into categoria_uuid;

    local_uuid := null;

    if nullif(item->>'local', '') is not null then
      insert into public.locais (nome)
      values (item->>'local')
      on conflict (nome) do update set nome = excluded.nome
      returning id into local_uuid;
    end if;

    insert into public.ativos (
      codigo_patrimonio,
      nome,
      descricao,
      numero_serie,
      categoria_id,
      local_id,
      status,
      marca,
      marca_agrupada,
      propriedade,
      imei,
      datasheet,
      numero_serie_original,
      linha_excel
    )
    values (
      item->>'codigo_patrimonio',
      item->>'modelo',
      nullif(item->>'descricao', ''),
      nullif(item->>'numero_serie', ''),
      categoria_uuid,
      local_uuid,
      coalesce(nullif(item->>'status', ''), 'Disponível'),
      nullif(item->>'marca', ''),
      nullif(item->>'marca_agrupada', ''),
      nullif(item->>'propriedade', ''),
      nullif(item->>'imei', ''),
      nullif(item->>'datasheet', ''),
      nullif(item->>'sn', ''),
      nullif(item->>'linha_excel', '')::integer
    )
    on conflict (codigo_patrimonio) do update set
      nome = excluded.nome,
      descricao = excluded.descricao,
      numero_serie = excluded.numero_serie,
      categoria_id = excluded.categoria_id,
      local_id = excluded.local_id,
      status = excluded.status,
      marca = excluded.marca,
      marca_agrupada = excluded.marca_agrupada,
      propriedade = excluded.propriedade,
      imei = excluded.imei,
      datasheet = excluded.datasheet,
      numero_serie_original = excluded.numero_serie_original,
      linha_excel = excluded.linha_excel;

    total := total + 1;
  end loop;

  return total;
end;
$$;

-- Exposicao temporaria necessaria ao cliente de importacao usado nesta etapa.
grant execute on function public.importar_ativos_excel(jsonb) to anon;;
