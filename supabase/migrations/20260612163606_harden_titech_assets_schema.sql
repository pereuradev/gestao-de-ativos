-- Endurece o baseline inicial sem mudar o modelo funcional.
-- Fixa o search_path, indexa FKs e separa politicas administrativas por operacao.

create or replace function public.set_updated_at()
returns trigger
language plpgsql
set search_path = public
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create index if not exists assets_supplier_id_idx on public.assets(supplier_id);
create index if not exists assets_created_by_idx on public.assets(created_by);
create index if not exists asset_assignments_assigned_by_idx on public.asset_assignments(assigned_by);
create index if not exists maintenance_records_opened_by_idx on public.maintenance_records(opened_by);
create index if not exists maintenance_records_supplier_id_idx on public.maintenance_records(supplier_id);

-- A verificacao administrativa e interna as politicas e nao deve ser exposta a anon.
revoke execute on function public.is_admin() from anon;

alter policy "Users can read their own profile"
on public.user_profiles
using (id = (select auth.uid()) or (select public.is_admin()));

alter policy "Users can insert their own profile"
on public.user_profiles
with check (id = (select auth.uid()) or (select public.is_admin()));

alter policy "Users can update their own profile"
on public.user_profiles
using (id = (select auth.uid()) or (select public.is_admin()))
with check (id = (select auth.uid()) or (select public.is_admin()));

-- Politicas FOR ALL sao substituidas para distinguir insert, update e delete.
drop policy if exists "Admins can manage asset categories" on public.asset_categories;
drop policy if exists "Admins can manage locations" on public.locations;
drop policy if exists "Admins can manage suppliers" on public.suppliers;
drop policy if exists "Admins can manage assets" on public.assets;
drop policy if exists "Admins can manage assignments" on public.asset_assignments;
drop policy if exists "Admins can manage maintenance" on public.maintenance_records;

create policy "Admins can insert asset categories"
on public.asset_categories for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update asset categories"
on public.asset_categories for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete asset categories"
on public.asset_categories for delete
to authenticated
using ((select public.is_admin()));

create policy "Admins can insert locations"
on public.locations for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update locations"
on public.locations for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete locations"
on public.locations for delete
to authenticated
using ((select public.is_admin()));

create policy "Admins can insert suppliers"
on public.suppliers for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update suppliers"
on public.suppliers for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete suppliers"
on public.suppliers for delete
to authenticated
using ((select public.is_admin()));

create policy "Admins can insert assets"
on public.assets for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update assets"
on public.assets for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete assets"
on public.assets for delete
to authenticated
using ((select public.is_admin()));

create policy "Admins can insert assignments"
on public.asset_assignments for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update assignments"
on public.asset_assignments for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete assignments"
on public.asset_assignments for delete
to authenticated
using ((select public.is_admin()));

create policy "Admins can insert maintenance"
on public.maintenance_records for insert
to authenticated
with check ((select public.is_admin()));

create policy "Admins can update maintenance"
on public.maintenance_records for update
to authenticated
using ((select public.is_admin()))
with check ((select public.is_admin()));

create policy "Admins can delete maintenance"
on public.maintenance_records for delete
to authenticated
using ((select public.is_admin()));;
