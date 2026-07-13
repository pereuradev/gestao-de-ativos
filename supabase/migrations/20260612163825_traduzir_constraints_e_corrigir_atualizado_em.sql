-- Finaliza a traducao dos objetos que nao foram cobertos pelos renames de tabelas.
-- Preserva a semantica das constraints para manter FKs, checks e erros esperados pelo app.

-- A funcao usa o nome de coluna em portugues adotado pela migration anterior.
create or replace function public.definir_atualizado_em()
returns trigger
language plpgsql
set search_path = public
as $$
begin
  new.atualizado_em = now();
  return new;
end;
$$;

-- Renomeia constraints por tabela; nenhuma regra de integridade e removida aqui.
alter table public.ativos rename constraint assets_asset_tag_key to ativos_codigo_patrimonio_key;
alter table public.ativos rename constraint assets_category_id_fkey to ativos_categoria_id_fkey;
alter table public.ativos rename constraint assets_created_by_fkey to ativos_criado_por_fkey;
alter table public.ativos rename constraint assets_location_id_fkey to ativos_local_id_fkey;
alter table public.ativos rename constraint assets_pkey to ativos_pkey;
alter table public.ativos rename constraint assets_serial_number_key to ativos_numero_serie_key;
alter table public.ativos rename constraint assets_status_check to ativos_status_check;
alter table public.ativos rename constraint assets_supplier_id_fkey to ativos_fornecedor_id_fkey;

alter table public.atribuicoes_ativos rename constraint asset_assignments_asset_id_fkey to atribuicoes_ativos_ativo_id_fkey;
alter table public.atribuicoes_ativos rename constraint asset_assignments_assigned_by_fkey to atribuicoes_ativos_atribuido_por_fkey;
alter table public.atribuicoes_ativos rename constraint asset_assignments_pkey to atribuicoes_ativos_pkey;
alter table public.atribuicoes_ativos rename constraint asset_assignments_user_id_fkey to atribuicoes_ativos_usuario_id_fkey;
alter table public.atribuicoes_ativos rename constraint returned_after_assigned to devolucao_apos_atribuicao_check;

alter table public.categorias_ativos rename constraint asset_categories_name_key to categorias_ativos_nome_key;
alter table public.categorias_ativos rename constraint asset_categories_pkey to categorias_ativos_pkey;

alter table public.fornecedores rename constraint suppliers_name_key to fornecedores_nome_key;
alter table public.fornecedores rename constraint suppliers_pkey to fornecedores_pkey;

alter table public.locais rename constraint locations_name_key to locais_nome_key;
alter table public.locais rename constraint locations_pkey to locais_pkey;

alter table public.logs_auditoria rename constraint audit_logs_actor_id_fkey to logs_auditoria_autor_id_fkey;
alter table public.logs_auditoria rename constraint audit_logs_pkey to logs_auditoria_pkey;

alter table public.perfis_usuarios rename constraint user_profiles_cpf_key to perfis_usuarios_cpf_key;
alter table public.perfis_usuarios rename constraint user_profiles_department_check to perfis_usuarios_departamento_check;
alter table public.perfis_usuarios rename constraint user_profiles_email_key to perfis_usuarios_email_key;
alter table public.perfis_usuarios rename constraint user_profiles_id_fkey to perfis_usuarios_id_fkey;
alter table public.perfis_usuarios rename constraint user_profiles_pkey to perfis_usuarios_pkey;
alter table public.perfis_usuarios rename constraint user_profiles_rg_key to perfis_usuarios_rg_key;
alter table public.perfis_usuarios rename constraint user_profiles_role_check to perfis_usuarios_tipo_usuario_check;
alter table public.perfis_usuarios rename constraint user_profiles_status_check to perfis_usuarios_status_check;

alter table public.registros_manutencao rename constraint closed_after_opened to fechamento_apos_abertura_check;
alter table public.registros_manutencao rename constraint maintenance_records_asset_id_fkey to registros_manutencao_ativo_id_fkey;
alter table public.registros_manutencao rename constraint maintenance_records_opened_by_fkey to registros_manutencao_aberto_por_fkey;
alter table public.registros_manutencao rename constraint maintenance_records_pkey to registros_manutencao_pkey;
alter table public.registros_manutencao rename constraint maintenance_records_status_check to registros_manutencao_status_check;
alter table public.registros_manutencao rename constraint maintenance_records_supplier_id_fkey to registros_manutencao_fornecedor_id_fkey;;
