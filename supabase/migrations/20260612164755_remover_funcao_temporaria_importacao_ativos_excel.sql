-- Encerra a janela de importacao e remove a RPC SECURITY DEFINER da API exposta.
-- A revogacao vem antes do DROP para explicitar a retirada imediata do acesso anonimo.

revoke execute on function public.importar_ativos_excel(jsonb) from anon;
drop function if exists public.importar_ativos_excel(jsonb);;
