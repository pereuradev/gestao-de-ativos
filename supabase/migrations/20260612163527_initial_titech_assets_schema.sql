-- Baseline inicial do banco do portal de ativos.
-- Define tabelas, relacionamentos, dados de referencia e as primeiras politicas RLS.
-- Senhas pertencem ao Supabase Auth e nao devem ser armazenadas nas tabelas publicas.

create extension if not exists pgcrypto;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table if not exists public.user_profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null,
  email text not null unique,
  role text not null default 'Colaborador'
    check (role in ('Colaborador', 'Administrador')),
  department text not null
    check (department in ('TI', 'Operacao', 'Financeiro', 'Administrativo', 'Gestao')),
  company text not null,
  rg text not null unique,
  cpf text not null unique,
  cellphone text not null,
  birth_date date not null,
  status text not null default 'Ativo'
    check (status in ('Ativo', 'Inativo', 'Pendente')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_user_profiles_updated_at
before update on public.user_profiles
for each row execute function public.set_updated_at();

create table if not exists public.asset_categories (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  description text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_asset_categories_updated_at
before update on public.asset_categories
for each row execute function public.set_updated_at();

create table if not exists public.locations (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  address text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_locations_updated_at
before update on public.locations
for each row execute function public.set_updated_at();

create table if not exists public.suppliers (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  document text,
  email text,
  phone text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_suppliers_updated_at
before update on public.suppliers
for each row execute function public.set_updated_at();

create table if not exists public.assets (
  id uuid primary key default gen_random_uuid(),
  asset_tag text not null unique,
  name text not null,
  description text,
  serial_number text unique,
  category_id uuid references public.asset_categories(id) on delete set null,
  supplier_id uuid references public.suppliers(id) on delete set null,
  location_id uuid references public.locations(id) on delete set null,
  purchase_date date,
  purchase_value numeric(12, 2),
  warranty_until date,
  status text not null default 'Disponivel'
    check (status in ('Disponivel', 'Em uso', 'Manutencao', 'Baixado', 'Perdido')),
  created_by uuid references public.user_profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists assets_category_id_idx on public.assets(category_id);
create index if not exists assets_location_id_idx on public.assets(location_id);
create index if not exists assets_status_idx on public.assets(status);

create trigger set_assets_updated_at
before update on public.assets
for each row execute function public.set_updated_at();

create table if not exists public.asset_assignments (
  id uuid primary key default gen_random_uuid(),
  asset_id uuid not null references public.assets(id) on delete cascade,
  user_id uuid not null references public.user_profiles(id) on delete restrict,
  assigned_by uuid references public.user_profiles(id) on delete set null,
  assigned_at timestamptz not null default now(),
  returned_at timestamptz,
  notes text,
  created_at timestamptz not null default now(),
  constraint returned_after_assigned check (returned_at is null or returned_at >= assigned_at)
);

create index if not exists asset_assignments_asset_id_idx on public.asset_assignments(asset_id);
create index if not exists asset_assignments_user_id_idx on public.asset_assignments(user_id);
create unique index if not exists one_open_assignment_per_asset_idx
  on public.asset_assignments(asset_id)
  where returned_at is null;

create table if not exists public.maintenance_records (
  id uuid primary key default gen_random_uuid(),
  asset_id uuid not null references public.assets(id) on delete cascade,
  opened_by uuid references public.user_profiles(id) on delete set null,
  supplier_id uuid references public.suppliers(id) on delete set null,
  title text not null,
  description text,
  status text not null default 'Aberta'
    check (status in ('Aberta', 'Em andamento', 'Concluida', 'Cancelada')),
  cost numeric(12, 2),
  opened_at timestamptz not null default now(),
  closed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint closed_after_opened check (closed_at is null or closed_at >= opened_at)
);

create index if not exists maintenance_records_asset_id_idx on public.maintenance_records(asset_id);
create index if not exists maintenance_records_status_idx on public.maintenance_records(status);

create trigger set_maintenance_records_updated_at
before update on public.maintenance_records
for each row execute function public.set_updated_at();

create table if not exists public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor_id uuid references public.user_profiles(id) on delete set null,
  action text not null,
  entity_table text not null,
  entity_id uuid,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists audit_logs_actor_id_idx on public.audit_logs(actor_id);
create index if not exists audit_logs_entity_idx on public.audit_logs(entity_table, entity_id);

insert into public.asset_categories (name, description)
values
  ('Notebook', 'Computadores portateis'),
  ('Desktop', 'Computadores de mesa'),
  ('Monitor', 'Monitores e telas'),
  ('Periferico', 'Teclados, mouses, headsets e acessorios'),
  ('Rede', 'Equipamentos de rede')
on conflict (name) do nothing;

create or replace function public.is_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.user_profiles
    where id = auth.uid()
      and role = 'Administrador'
      and status = 'Ativo'
  );
$$;

alter table public.user_profiles enable row level security;
alter table public.asset_categories enable row level security;
alter table public.locations enable row level security;
alter table public.suppliers enable row level security;
alter table public.assets enable row level security;
alter table public.asset_assignments enable row level security;
alter table public.maintenance_records enable row level security;
alter table public.audit_logs enable row level security;

create policy "Users can read their own profile"
on public.user_profiles for select
to authenticated
using (id = auth.uid() or public.is_admin());

create policy "Users can insert their own profile"
on public.user_profiles for insert
to authenticated
with check (id = auth.uid() or public.is_admin());

create policy "Users can update their own profile"
on public.user_profiles for update
to authenticated
using (id = auth.uid() or public.is_admin())
with check (id = auth.uid() or public.is_admin());

create policy "Authenticated users can read asset data"
on public.asset_categories for select
to authenticated
using (true);

create policy "Admins can manage asset categories"
on public.asset_categories for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Authenticated users can read locations"
on public.locations for select
to authenticated
using (true);

create policy "Admins can manage locations"
on public.locations for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Authenticated users can read suppliers"
on public.suppliers for select
to authenticated
using (true);

create policy "Admins can manage suppliers"
on public.suppliers for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Authenticated users can read assets"
on public.assets for select
to authenticated
using (true);

create policy "Admins can manage assets"
on public.assets for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Authenticated users can read assignments"
on public.asset_assignments for select
to authenticated
using (true);

create policy "Admins can manage assignments"
on public.asset_assignments for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Authenticated users can read maintenance"
on public.maintenance_records for select
to authenticated
using (true);

create policy "Admins can manage maintenance"
on public.maintenance_records for all
to authenticated
using (public.is_admin())
with check (public.is_admin());

create policy "Admins can read audit logs"
on public.audit_logs for select
to authenticated
using (public.is_admin());

create policy "Admins can insert audit logs"
on public.audit_logs for insert
to authenticated
with check (public.is_admin());;
