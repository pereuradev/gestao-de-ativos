-- Padroniza o schema em portugues para refletir os nomes usados pelo PHP.
-- As politicas sao removidas antes dos renames e recriadas ao final com as novas referencias.

drop policy if exists "Users can read their own profile" on public.user_profiles;
drop policy if exists "Users can insert their own profile" on public.user_profiles;
drop policy if exists "Users can update their own profile" on public.user_profiles;
drop policy if exists "Authenticated users can read asset data" on public.asset_categories;
drop policy if exists "Admins can insert asset categories" on public.asset_categories;
drop policy if exists "Admins can update asset categories" on public.asset_categories;
drop policy if exists "Admins can delete asset categories" on public.asset_categories;
drop policy if exists "Authenticated users can read locations" on public.locations;
drop policy if exists "Admins can insert locations" on public.locations;
drop policy if exists "Admins can update locations" on public.locations;
drop policy if exists "Admins can delete locations" on public.locations;
drop policy if exists "Authenticated users can read suppliers" on public.suppliers;
drop policy if exists "Admins can insert suppliers" on public.suppliers;
drop policy if exists "Admins can update suppliers" on public.suppliers;
drop policy if exists "Admins can delete suppliers" on public.suppliers;
drop policy if exists "Authenticated users can read assets" on public.assets;
drop policy if exists "Admins can insert assets" on public.assets;
drop policy if exists "Admins can update assets" on public.assets;
drop policy if exists "Admins can delete assets" on public.assets;
drop policy if exists "Authenticated users can read assignments" on public.asset_assignments;
drop policy if exists "Admins can insert assignments" on public.asset_assignments;
drop policy if exists "Admins can update assignments" on public.asset_assignments;
drop policy if exists "Admins can delete assignments" on public.asset_assignments;
drop policy if exists "Authenticated users can read maintenance" on public.maintenance_records;
drop policy if exists "Admins can insert maintenance" on public.maintenance_records;
drop policy if exists "Admins can update maintenance" on public.maintenance_records;
drop policy if exists "Admins can delete maintenance" on public.maintenance_records;
drop policy if exists "Admins can read audit logs" on public.audit_logs;
drop policy if exists "Admins can insert audit logs" on public.audit_logs;

-- Funcoes, tabelas e colunas precisam ser renomeadas antes de triggers e politicas.
alter function public.set_updated_at() rename to definir_atualizado_em;
alter function public.is_admin() rename to eh_administrador;

alter table public.user_profiles rename to perfis_usuarios;
alter table public.asset_categories rename to categorias_ativos;
alter table public.locations rename to locais;
alter table public.suppliers rename to fornecedores;
alter table public.assets rename to ativos;
alter table public.asset_assignments rename to atribuicoes_ativos;
alter table public.maintenance_records rename to registros_manutencao;
alter table public.audit_logs rename to logs_auditoria;

alter table public.perfis_usuarios rename column full_name to nome_completo;
alter table public.perfis_usuarios rename column role to tipo_usuario;
alter table public.perfis_usuarios rename column department to departamento;
alter table public.perfis_usuarios rename column company to empresa;
alter table public.perfis_usuarios rename column cellphone to celular;
alter table public.perfis_usuarios rename column birth_date to data_nascimento;
alter table public.perfis_usuarios rename column created_at to criado_em;
alter table public.perfis_usuarios rename column updated_at to atualizado_em;

alter table public.categorias_ativos rename column name to nome;
alter table public.categorias_ativos rename column description to descricao;
alter table public.categorias_ativos rename column created_at to criado_em;
alter table public.categorias_ativos rename column updated_at to atualizado_em;

alter table public.locais rename column name to nome;
alter table public.locais rename column address to endereco;
alter table public.locais rename column created_at to criado_em;
alter table public.locais rename column updated_at to atualizado_em;

alter table public.fornecedores rename column name to nome;
alter table public.fornecedores rename column document to documento;
alter table public.fornecedores rename column phone to telefone;
alter table public.fornecedores rename column created_at to criado_em;
alter table public.fornecedores rename column updated_at to atualizado_em;

alter table public.ativos rename column asset_tag to codigo_patrimonio;
alter table public.ativos rename column name to nome;
alter table public.ativos rename column description to descricao;
alter table public.ativos rename column serial_number to numero_serie;
alter table public.ativos rename column category_id to categoria_id;
alter table public.ativos rename column supplier_id to fornecedor_id;
alter table public.ativos rename column location_id to local_id;
alter table public.ativos rename column purchase_date to data_compra;
alter table public.ativos rename column purchase_value to valor_compra;
alter table public.ativos rename column warranty_until to garantia_ate;
alter table public.ativos rename column created_by to criado_por;
alter table public.ativos rename column created_at to criado_em;
alter table public.ativos rename column updated_at to atualizado_em;

alter table public.atribuicoes_ativos rename column asset_id to ativo_id;
alter table public.atribuicoes_ativos rename column user_id to usuario_id;
alter table public.atribuicoes_ativos rename column assigned_by to atribuido_por;
alter table public.atribuicoes_ativos rename column assigned_at to atribuido_em;
alter table public.atribuicoes_ativos rename column returned_at to devolvido_em;
alter table public.atribuicoes_ativos rename column notes to observacoes;
alter table public.atribuicoes_ativos rename column created_at to criado_em;

alter table public.registros_manutencao rename column asset_id to ativo_id;
alter table public.registros_manutencao rename column opened_by to aberto_por;
alter table public.registros_manutencao rename column supplier_id to fornecedor_id;
alter table public.registros_manutencao rename column title to titulo;
alter table public.registros_manutencao rename column description to descricao;
alter table public.registros_manutencao rename column cost to custo;
alter table public.registros_manutencao rename column opened_at to aberto_em;
alter table public.registros_manutencao rename column closed_at to fechado_em;
alter table public.registros_manutencao rename column created_at to criado_em;
alter table public.registros_manutencao rename column updated_at to atualizado_em;

alter table public.logs_auditoria rename column actor_id to autor_id;
alter table public.logs_auditoria rename column action to acao;
alter table public.logs_auditoria rename column entity_table to tabela_entidade;
alter table public.logs_auditoria rename column entity_id to entidade_id;
alter table public.logs_auditoria rename column metadata to metadados;
alter table public.logs_auditoria rename column created_at to criado_em;

-- Recria os triggers porque seus nomes antigos nao acompanhariam a traducao automaticamente.
drop trigger if exists set_user_profiles_updated_at on public.perfis_usuarios;
drop trigger if exists set_asset_categories_updated_at on public.categorias_ativos;
drop trigger if exists set_locations_updated_at on public.locais;
drop trigger if exists set_suppliers_updated_at on public.fornecedores;
drop trigger if exists set_assets_updated_at on public.ativos;
drop trigger if exists set_maintenance_records_updated_at on public.registros_manutencao;

create trigger definir_perfis_usuarios_atualizado_em
before update on public.perfis_usuarios
for each row execute function public.definir_atualizado_em();

create trigger definir_categorias_ativos_atualizado_em
before update on public.categorias_ativos
for each row execute function public.definir_atualizado_em();

create trigger definir_locais_atualizado_em
before update on public.locais
for each row execute function public.definir_atualizado_em();

create trigger definir_fornecedores_atualizado_em
before update on public.fornecedores
for each row execute function public.definir_atualizado_em();

create trigger definir_ativos_atualizado_em
before update on public.ativos
for each row execute function public.definir_atualizado_em();

create trigger definir_registros_manutencao_atualizado_em
before update on public.registros_manutencao
for each row execute function public.definir_atualizado_em();

-- Mantem os indices existentes, alterando somente os nomes operacionais.
alter index if exists assets_category_id_idx rename to ativos_categoria_id_idx;
alter index if exists assets_location_id_idx rename to ativos_local_id_idx;
alter index if exists assets_status_idx rename to ativos_status_idx;
alter index if exists assets_supplier_id_idx rename to ativos_fornecedor_id_idx;
alter index if exists assets_created_by_idx rename to ativos_criado_por_idx;
alter index if exists asset_assignments_asset_id_idx rename to atribuicoes_ativos_ativo_id_idx;
alter index if exists asset_assignments_user_id_idx rename to atribuicoes_ativos_usuario_id_idx;
alter index if exists asset_assignments_assigned_by_idx rename to atribuicoes_ativos_atribuido_por_idx;
alter index if exists one_open_assignment_per_asset_idx rename to uma_atribuicao_aberta_por_ativo_idx;
alter index if exists maintenance_records_asset_id_idx rename to registros_manutencao_ativo_id_idx;
alter index if exists maintenance_records_status_idx rename to registros_manutencao_status_idx;
alter index if exists maintenance_records_opened_by_idx rename to registros_manutencao_aberto_por_idx;
alter index if exists maintenance_records_supplier_id_idx rename to registros_manutencao_fornecedor_id_idx;
alter index if exists audit_logs_actor_id_idx rename to logs_auditoria_autor_id_idx;
alter index if exists audit_logs_entity_idx rename to logs_auditoria_entidade_idx;

-- Restaura o mesmo modelo de acesso usando os identificadores em portugues.
create policy "Usuarios podem ler o proprio perfil"
on public.perfis_usuarios for select
to authenticated
using (id = (select auth.uid()) or (select public.eh_administrador()));

create policy "Usuarios podem inserir o proprio perfil"
on public.perfis_usuarios for insert
to authenticated
with check (id = (select auth.uid()) or (select public.eh_administrador()));

create policy "Usuarios podem atualizar o proprio perfil"
on public.perfis_usuarios for update
to authenticated
using (id = (select auth.uid()) or (select public.eh_administrador()))
with check (id = (select auth.uid()) or (select public.eh_administrador()));

create policy "Usuarios autenticados podem ler categorias"
on public.categorias_ativos for select
to authenticated
using (true);

create policy "Administradores podem inserir categorias"
on public.categorias_ativos for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar categorias"
on public.categorias_ativos for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir categorias"
on public.categorias_ativos for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler locais"
on public.locais for select
to authenticated
using (true);

create policy "Administradores podem inserir locais"
on public.locais for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar locais"
on public.locais for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir locais"
on public.locais for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler fornecedores"
on public.fornecedores for select
to authenticated
using (true);

create policy "Administradores podem inserir fornecedores"
on public.fornecedores for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar fornecedores"
on public.fornecedores for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir fornecedores"
on public.fornecedores for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler ativos"
on public.ativos for select
to authenticated
using (true);

create policy "Administradores podem inserir ativos"
on public.ativos for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar ativos"
on public.ativos for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir ativos"
on public.ativos for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler atribuicoes"
on public.atribuicoes_ativos for select
to authenticated
using (true);

create policy "Administradores podem inserir atribuicoes"
on public.atribuicoes_ativos for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar atribuicoes"
on public.atribuicoes_ativos for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir atribuicoes"
on public.atribuicoes_ativos for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Usuarios autenticados podem ler manutencoes"
on public.registros_manutencao for select
to authenticated
using (true);

create policy "Administradores podem inserir manutencoes"
on public.registros_manutencao for insert
to authenticated
with check ((select public.eh_administrador()));

create policy "Administradores podem atualizar manutencoes"
on public.registros_manutencao for update
to authenticated
using ((select public.eh_administrador()))
with check ((select public.eh_administrador()));

create policy "Administradores podem excluir manutencoes"
on public.registros_manutencao for delete
to authenticated
using ((select public.eh_administrador()));

create policy "Administradores podem ler logs de auditoria"
on public.logs_auditoria for select
to authenticated
using ((select public.eh_administrador()));

create policy "Administradores podem inserir logs de auditoria"
on public.logs_auditoria for insert
to authenticated
with check ((select public.eh_administrador()));

revoke execute on function public.eh_administrador() from anon;;
