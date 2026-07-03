# Gestao de Ativos TI TECH Solutions

Sistema web interno para gestao de ativos de TI, criado para centralizar inventario, usuarios, marcas, propriedades, localizacoes, permissoes e indicadores operacionais em um unico portal.

O projeto foi desenvolvido para uso corporativo da TI TECH Solutions, com foco em organizacao, rastreabilidade, visual moderno e operacao simples para administradores e colaboradores.

---

## Status

Projeto em desenvolvimento ativo.

Principais areas ja estruturadas:

- Login com Supabase Auth.
- Controle de sessao com PHP.
- Diferenciacao entre administrador e colaborador.
- Sidebar responsiva com secoes de cadastro, edicao e consulta.
- Dashboard de produtos com filtros por tipo, marca e localizacao.
- Cadastro, visualizacao, edicao e exclusao de ativos.
- Cadastro, visualizacao, edicao e exclusao de marcas.
- Cadastro, visualizacao, edicao e exclusao de propriedades.
- Cadastro, visualizacao, edicao e exclusao de localizacoes.
- Cadastro e listagem de funcionarios.
- Criacao e edicao de grupos de acesso.
- Remocao de membros de grupos.
- Exclusao de grupos.
- Configuracoes visuais do usuario.
- Modo claro e modo escuro.
- Preferencia de cor, tamanho de fonte, reducao de animacoes e cursor personalizado.
- Interface responsiva para desktop, tablet e celular.

---

## Visao geral

O sistema substitui controles manuais e planilhas soltas por uma aplicacao web organizada, com dados salvos em PostgreSQL/Supabase e operacoes internas feitas por telas protegidas.

Administradores conseguem cadastrar funcionarios, ativos e dados auxiliares, alem de gerenciar grupos de acesso. Colaboradores acessam apenas as areas permitidas pelo perfil e pelas regras de interface.

---

## Funcionalidades

### Autenticacao

- Login com e-mail corporativo.
- Integracao com Supabase Auth.
- Sessao PHP para proteger paginas internas.
- Logout seguro.
- Redirecionamento para login quando a sessao expira.

### Dashboard

- Indicadores gerais do inventario.
- Filtro por tipo de produto.
- Filtro por marca.
- Filtro por localizacao.
- Visualizacao por tipo, marca, status, local e evolucao de cadastros.
- Graficos em barras, pizza, rosca, linhas e polar.
- Painel lateral com leitura rapida dos dados exibidos.

### Ativos

- Cadastro de ativos.
- Edicao de ativos cadastrados.
- Exclusao de ativos.
- Visualizacao com filtros e paginacao.
- Coluna de datasheet.
- Campos de categoria, status, local, marca, propriedade, numero de serie, IMEI e descricao.

### Marcas

- Cadastro de marcas.
- Visualizacao de marcas.
- Edicao de marcas.
- Exclusao de marcas.
- Controle de status ativo/inativo.

### Propriedades

- Cadastro de propriedades responsaveis pelos ativos.
- Visualizacao de propriedades.
- Edicao de propriedades.
- Exclusao de propriedades.
- Controle de status ativo/inativo.

### Localizacoes

- Cadastro de locais, setores, salas ou pontos de estoque.
- Visualizacao de localizacoes.
- Edicao de localizacoes.
- Exclusao de localizacoes.
- Controle de status ativo/inativo.

### Funcionarios

- Listagem de funcionarios.
- Cadastro interno de novos funcionarios por administradores.
- Definicao de perfil de acesso: administrador ou colaborador.
- Informacoes de contato, documento, departamento e empresa.
- Ultimos acessos criados na tela de cadastro.

### Grupos de acesso

- Criacao de grupos.
- Selecao de funcionarios para cada grupo.
- Selecao de permissoes por grupo.
- Edicao de grupos.
- Remocao de funcionarios de um grupo.
- Exclusao de grupos.
- Cards com membros e permissoes vinculadas.

### Configuracoes

- Preferencia de tema.
- Cor de destaque.
- Tamanho da fonte do site.
- Reducao de animacoes.
- Cursor personalizado.
- Copia de informacoes tecnicas para suporte.

---

## Tecnologias

### Frontend

- HTML5.
- CSS3.
- JavaScript.
- Bootstrap Icons.
- Chart.js.
- React via CDN para widgets especificos.
- LocalStorage para preferencias visuais.

### Backend

- PHP.
- PDO.
- Sessoes PHP.
- Rotas JSON para operacoes assincronas.
- Validacao no servidor.
- CSRF em rotas sensiveis.

### Banco de dados

- Supabase.
- PostgreSQL.
- Conexao via PDO PostgreSQL.
- SSL habilitado na conexao.

### Ambiente local

- XAMPP.
- Apache.
- PHP com `pgsql` e `pdo_pgsql` habilitados.

---

## Estrutura resumida

```text
SIte-Gestao_de_Ativos/
├── Backend/
│   ├── Conexao.php
│   ├── config.php
│   ├── login-usuario.php
│   ├── logout.php
│   ├── cadastrar-usuario.php
│   ├── cadastrar-ativo.php
│   ├── cadastrar-grupo.php
│   ├── remover-membro-grupo.php
│   ├── excluir-grupo.php
│   ├── dashboard-produtos.php
│   ├── grupos-acesso-util.php
│   └── demais rotas de cadastro, edicao e exclusao
│
├── assets/
│   ├── Logo.png
│   ├── logo-branca.png
│   ├── favicon.png
│   └── imagens de fundo da interface
│
├── css/
│   ├── pagina-base.css
│   ├── pagina-login.css
│   ├── dashboard-produtos.css
│   ├── cadastro-grupos.css
│   ├── edicao-grupos.css
│   └── estilos das demais paginas
│
├── js/
│   ├── app-base.js
│   ├── pagina-login.js
│   ├── dashboard-produtos.js
│   ├── cadastro-grupos.js
│   ├── edicao-grupos.js
│   └── scripts das demais paginas
│
├── Pagina-login.html
├── pagina-inicial.php
├── dashboard.php
├── ativos.php
├── cadastro-ativos.php
├── edicao-ativos.php
├── cadastro-funcionarios.php
├── funcionarios.php
├── cadastro-grupos.php
├── edicao-grupos.php
├── configuracoes.php
└── README.md
```

---

## Paginas principais

| Pagina                          | Funcao                                                |
| ------------------------------- | ----------------------------------------------------- |
| `Pagina-login.html`             | Tela de login do sistema.                             |
| `pagina-inicial.php`            | Pagina inicial interna apos login.                    |
| `dashboard.php`                 | Dashboard visual de produtos e inventario.            |
| `ativos.php`                    | Visualizacao e filtros de ativos.                     |
| `cadastro-ativos.php`           | Cadastro de ativos.                                   |
| `edicao-ativos.php`             | Edicao e exclusao de ativos.                          |
| `funcionarios.php`              | Listagem de funcionarios.                             |
| `cadastro-funcionarios.php`     | Cadastro de funcionarios, restrito a administradores. |
| `cadastro-grupos.php`           | Criacao de grupos e permissoes.                       |
| `edicao-grupos.php`             | Remocao de membros e exclusao de grupos.              |
| `marcas.php`                    | Cadastro de marcas.                                   |
| `marcas-visualizacao.php`       | Visualizacao de marcas.                               |
| `edicao-marcas.php`             | Edicao e exclusao de marcas.                          |
| `locais.php`                    | Cadastro de localizacoes.                             |
| `locais-visualizacao.php`       | Visualizacao de localizacoes.                         |
| `edicao-locais.php`             | Edicao e exclusao de localizacoes.                    |
| `propriedades.php`              | Cadastro de propriedades.                             |
| `propriedades-visualizacao.php` | Visualizacao de propriedades.                         |
| `edicao-propriedades.php`       | Edicao e exclusao de propriedades.                    |
| `configuracoes.php`             | Preferencias, seguranca e ajustes do usuario.         |

---

## Endpoints principais

| Arquivo                            | Responsabilidade                               |
| ---------------------------------- | ---------------------------------------------- |
| `Backend/Conexao.php`              | Cria a conexao PDO com PostgreSQL/Supabase.    |
| `Backend/config.php`               | Carrega variaveis do arquivo `.env`.           |
| `Backend/login-usuario.php`        | Autentica usuarios.                            |
| `Backend/logout.php`               | Encerra a sessao.                              |
| `Backend/cadastrar-usuario.php`    | Cria funcionarios no Auth e no perfil local.   |
| `Backend/cadastrar-ativo.php`      | Cadastra ativos.                               |
| `Backend/atualizar-ativo.php`      | Atualiza ativos.                               |
| `Backend/excluir-ativo.php`        | Exclui ativos.                                 |
| `Backend/cadastrar-grupo.php`      | Cria grupos de acesso.                         |
| `Backend/remover-membro-grupo.php` | Remove funcionario de um grupo.                |
| `Backend/excluir-grupo.php`        | Exclui grupo, membros e permissoes vinculadas. |
| `Backend/grupos-acesso-util.php`   | Garante tabelas e lista permissoes de grupos.  |
| `Backend/dashboard-produtos.php`   | Retorna dados do dashboard de produtos.        |
| `Backend/status-ativos.php`        | Centraliza status de ativos.                   |
| `Backend/marcas-ativos.php`        | Fornece marcas para telas e formularios.       |

---

## Banco de dados

O projeto usa Supabase com PostgreSQL.

Tabelas principais utilizadas:

- `perfis_usuarios`
- `ativos`
- `categorias_ativos`
- `marcas_ativos`
- `locais`
- `propriedade_ativos`
- `grupos_acesso`
- `grupos_acesso_membros`
- `grupos_acesso_permissoes`

Algumas rotas garantem tabelas auxiliares automaticamente quando necessario. Para producao ou instalacao em outro ambiente, o ideal e versionar scripts SQL em uma pasta como:

```text
database/schema.sql
database/seed.sql
```

---

## Variaveis de ambiente

O sistema usa credenciais locais em:

```text
Backend/.env
```

Use `Backend/.env.example` como base:

```env
DB_HOST=SEU_HOST_POOLER_SUPABASE
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.SEUIDDOPROJETO
DB_PASSWORD=SUA_SENHA_DO_BANCO
DB_SSLMODE=require

SUPABASE_URL=https://SEUIDDOPROJETO.supabase.co
SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICAVEL_DO_SUPABASE
```

O arquivo `Backend/.env` nao deve ser enviado para o GitHub.

---

## Seguranca e boas praticas aplicadas

- Credenciais fora do codigo, via `.env`.
- `.env` ignorado pelo Git.
- Sessao PHP para paginas internas.
- Validacao de metodo HTTP nos endpoints.
- Validacao de campos no backend.
- CSRF nas rotas sensiveis.
- Escape de dados antes de exibir no HTML.
- Respostas JSON padronizadas.
- Mensagens de erro controladas.
- Uso de PDO com excecoes.
- Separacao entre telas, estilos, scripts e endpoints.

---

## Decisoes de interface

- Tema escuro como experiencia principal.
- Modo claro disponivel.
- Sidebar fixa e redimensionavel.
- Componentes responsivos.
- Cards de metricas.
- Graficos para leitura rapida.
- Typewriter em titulos selecionados.
- Dialogs de confirmacao para acoes importantes.
- Toasts posicionados no canto inferior direito.
- Preferencias visuais salvas no navegador.

---

## Autor

Desenvolvido por Pietro Pereira.

Projeto criado para apoiar a gestao interna de ativos da TI TECH Solutions.

---

## Licenca

Este projeto e proprietario e esta protegido por direitos autorais.

Nao e permitido copiar, modificar, distribuir, revender, hospedar, publicar,
comercializar ou usar este codigo, total ou parcialmente, sem autorizacao previa
e por escrito do titular dos direitos.

Consulte o arquivo [LICENSE](LICENSE) para mais detalhes.
