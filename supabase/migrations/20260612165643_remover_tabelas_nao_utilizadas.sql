-- Retira modulos do schema inicial que nao sao usados pelo fluxo atual do portal.
-- CASCADE e intencional para remover policies, triggers e FKs dependentes dessas tabelas legadas.

drop table if exists public.atribuicoes_ativos cascade;
drop table if exists public.registros_manutencao cascade;
drop table if exists public.logs_auditoria cascade;
drop table if exists public.fornecedores cascade;;
