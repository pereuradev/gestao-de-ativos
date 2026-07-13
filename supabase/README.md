# Supabase local

O diretorio `migrations/` contem o historico versionado do banco remoto.

- As 13 migrations de `20260612163527` a `20260612165643` foram recuperadas do historico remoto com `supabase migration fetch` em 2026-07-13.
- `20260713120035_harden_schema_auth_rls_indexes.sql` captura o drift que antes era criado pelo PHP e aplica constraints, indices normalizados, FKs, RLS, politicas e grants auditados.
- Os dados de referencia ficam nas migrations. `seed.sql` existe apenas para manter o fluxo local explicito.

## Validacao local

Com Docker disponivel:

```powershell
supabase db reset
supabase migration list
```

Para comparar com um projeto vinculado:

```powershell
supabase db pull --schema public
supabase db diff --linked --schema public
```

O `db pull` exige um banco shadow local. Na captura inicial deste repositorio, o comando foi tentado, mas o ambiente nao possuia Docker; por isso o baseline foi recuperado pelo historico remoto e a migration incremental foi validada diretamente no PostgreSQL dentro de uma transacao com rollback.
