-- Remove a referencia a linha da planilha apos o encerramento da auditoria de importacao.
-- O identificador nao faz parte do dominio permanente do ativo.

alter table public.ativos drop column if exists linha_excel;;
