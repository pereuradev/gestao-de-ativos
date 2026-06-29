# Gestão de Ativos TI TECH Solutions

Sistema web interno para gestão de ativos de TI, desenvolvido para apoiar o controle operacional de equipamentos, usuários, marcas, propriedades, localizações e indicadores de inventário.

O projeto tem foco em uso corporativo: login, dashboard, cadastros, consultas, filtros, edição de registros e uma interface responsiva com identidade visual própria da TI TECH Solutions.

---

## Visão geral

O sistema centraliza informações importantes para a rotina de suporte técnico e inventário. A proposta é transformar o controle de ativos em um fluxo mais organizado, visual e rastreável, reduzindo dependência de planilhas soltas e facilitando a consulta de dados no dia a dia.

A aplicação roda em ambiente local com XAMPP/Apache, utiliza PHP no backend, JavaScript no frontend e Supabase/PostgreSQL como banco de dados.

---

## Status do projeto

Projeto em desenvolvimento.

Funcionalidades principais já estruturadas:

- Autenticação de usuários.
- Diferenciação visual entre perfil de colaborador e administrador.
- Dashboard com indicadores operacionais.
- Cadastro e consulta de ativos.
- Cadastro, visualização, edição e exclusão de marcas.
- Cadastro, visualização, edição e exclusão de localizações.
- Cadastro, visualização, edição e exclusão de propriedades.
- Listagem de funcionários.
- Página de configurações.
- Modo claro e modo escuro.
- Preferências visuais salvas no navegador.
- Layout responsivo para desktop, tablet e celular.

---

## Funcionalidades

### Autenticação

- Tela de login para acesso ao portal.
- Seleção visual de perfil entre colaborador e administrador.
- Fluxo de sessão PHP para proteger páginas internas.
- Logout do sistema.

### Dashboard

- Total de ativos cadastrados.
- Total de funcionários.
- Funcionários ativos.
- Distribuição de ativos por categoria.
- Distribuição por status.
- Evolução de ativos por período:
  - Hoje.
  - Semana.
  - Mês.
  - Ano.

### Ativos

- Consulta de ativos cadastrados.
- Cadastro de novos ativos.
- Edição de ativos existentes.
- Exclusão de ativos.
- Filtros por:
  - Status.
  - Categoria.
  - Marca.
  - Busca textual.
- Exibição de dados como:
  - Nome do ativo.
  - Categoria.
  - Marca.
  - Número de série.
  - Status.
  - Localização.
  - Data de criação.

### Marcas

- Cadastro de marcas de ativos.
- Visualização de marcas.
- Edição de marcas.
- Exclusão de marcas.
- Controle de status ativo/inativo.

### Localizações

- Cadastro de locais, setores, salas ou pontos de armazenamento.
- Visualização de localizações.
- Edição de localizações.
- Exclusão de localizações.

### Propriedades

- Cadastro de propriedades responsáveis pelos ativos.
- Visualização de propriedades.
- Edição de propriedades.
- Exclusão de propriedades.
- Controle de status ativo/inativo.

### Funcionários

- Listagem de usuários/colaboradores cadastrados.
- Exibição de informações úteis para suporte e controle interno.

### Configurações

- Painel de preferências do usuário.
- Ajustes visuais.
- Preferências salvas localmente no navegador.
- Preparação para futura integração das preferências com o banco de dados.

---

## Tecnologias utilizadas

### Frontend

- HTML5.
- CSS3.
- JavaScript.
- Bootstrap 5.
- Bootstrap Icons.
- Chart.js.
- React via CDN para widgets específicos da interface.
- LocalStorage para preferências visuais.

### Backend

- PHP.
- PDO.
- Sessões PHP.
- Respostas JSON para operações assíncronas.
- Validações no servidor.
- Proteção básica com CSRF em rotas sensíveis.

### Banco de dados

- Supabase.
- PostgreSQL.
- Conexão via PDO PostgreSQL.
- SSL habilitado na conexão.

### Ambiente local

- XAMPP.
- Apache.
- PHP com extensão PostgreSQL habilitada.

---

## Estrutura do projeto

```text
gestao-de-ativos/
├── Backend/
│   ├── .env.example
│   ├── Conexao.php
│   ├── config.php
│   ├── login-usuario.php
│   ├── logout.php
│   ├── cadastrar-usuario.php
│   ├── cadastrar-ativo.php
│   ├── cadastrar-marca.php
│   ├── cadastrar-local.php
│   ├── cadastrar-propriedade.php
│   ├── atualizar-ativo.php
│   ├── atualizar-marca.php
│   ├── atualizar-local.php
│   ├── atualizar-propriedade.php
│   ├── excluir-ativo.php
│   ├── excluir-marca.php
│   ├── excluir-local.php
│   ├── excluir-propriedade.php
│   ├── dashboard-metricas.php
│   ├── marcas-ativos.php
│   └── status-ativos.php
│
├── assets/
│   ├── Logo.png
│   ├── logo-branca.png
│   ├── favicon.png
│   └── imagens utilizadas na interface
│
├── css/
│   ├── pagina-base.css
│   ├── pagina-login.css
│   ├── pagina-cadastro.css
│   ├── responsivo-global.css
│   ├── typewriter.css
│   ├── ux-profissional.css
│   ├── ativos.css
│   ├── cadastro-ativos.css
│   ├── configuracoes.css
│   ├── funcionarios.css
│   ├── locais.css
│   ├── marcas.css
│   ├── propriedades.css
│   ├── edicao-ativos.css
│   ├── edicao-locais.css
│   ├── edicao-marcas.css
│   └── edicao-propriedades.css
│
├── js/
│   ├── app-base.js
│   ├── pagina-base.js
│   ├── pagina-login.js
│   ├── pagina-cadastro.js
│   ├── react-widgets.js
│   ├── typewriter.js
│   ├── ux-profissional.js
│   ├── ativos.js
│   ├── cadastro-ativos.js
│   ├── configuracoes.js
│   ├── funcionarios.js
│   ├── locais.js
│   ├── marcas.js
│   ├── propriedades.js
│   ├── edicao-ativos.js
│   ├── edicao-locais.js
│   ├── edicao-marcas.js
│   └── edicao-propriedades.js
│
├── Pagina-login.html
├── Pagina-cadastro.html
├── pagina-inicial.html
├── pagina-inicial.php
├── ativos.php
├── cadastro-ativos.php
├── funcionarios.php
├── configuracoes.php
├── marcas.php
├── marcas-visualizacao.php
├── edicao-marcas.php
├── locais.php
├── locais-visualizacao.php
├── edicao-locais.php
├── propriedades.php
├── propriedades-visualizacao.php
├── edicao-propriedades.php
├── edicao-ativos.php
├── .gitignore
└── README.md
```

---

## Páginas principais

| Página | Função |
|---|---|
| `Pagina-login.html` | Tela inicial de autenticação do sistema. |
| `Pagina-cadastro.html` | Tela de cadastro de usuário. |
| `pagina-inicial.php` | Dashboard operacional após login. |
| `ativos.php` | Consulta e filtragem de ativos. |
| `cadastro-ativos.php` | Cadastro de novos ativos. |
| `edicao-ativos.php` | Edição de ativos cadastrados. |
| `funcionarios.php` | Listagem de funcionários/usuários. |
| `marcas.php` | Cadastro de marcas. |
| `marcas-visualizacao.php` | Visualização de marcas cadastradas. |
| `edicao-marcas.php` | Edição de marcas. |
| `locais.php` | Cadastro de localizações. |
| `locais-visualizacao.php` | Visualização de localizações cadastradas. |
| `edicao-locais.php` | Edição de localizações. |
| `propriedades.php` | Cadastro de propriedades. |
| `propriedades-visualizacao.php` | Visualização de propriedades cadastradas. |
| `edicao-propriedades.php` | Edição de propriedades. |
| `configuracoes.php` | Preferências, segurança e ajustes do usuário. |

---

## Endpoints principais do backend

| Arquivo | Responsabilidade |
|---|---|
| `Backend/Conexao.php` | Cria a conexão PDO com o PostgreSQL/Supabase. |
| `Backend/config.php` | Carrega variáveis de ambiente do arquivo `.env`. |
| `Backend/login-usuario.php` | Processa autenticação do usuário. |
| `Backend/logout.php` | Encerra a sessão do usuário. |
| `Backend/cadastrar-usuario.php` | Registra novos usuários. |
| `Backend/cadastrar-ativo.php` | Registra novos ativos. |
| `Backend/atualizar-ativo.php` | Atualiza dados de ativos. |
| `Backend/excluir-ativo.php` | Remove ativos. |
| `Backend/cadastrar-marca.php` | Cadastra marcas. |
| `Backend/atualizar-marca.php` | Atualiza marcas. |
| `Backend/excluir-marca.php` | Remove marcas. |
| `Backend/cadastrar-local.php` | Cadastra localizações. |
| `Backend/atualizar-local.php` | Atualiza localizações. |
| `Backend/excluir-local.php` | Remove localizações. |
| `Backend/cadastrar-propriedade.php` | Cadastra propriedades. |
| `Backend/atualizar-propriedade.php` | Atualiza propriedades. |
| `Backend/excluir-propriedade.php` | Remove propriedades. |
| `Backend/dashboard-metricas.php` | Retorna métricas do dashboard em JSON. |
| `Backend/marcas-ativos.php` | Fornece dados de marcas para uso na interface. |
| `Backend/status-ativos.php` | Centraliza status disponíveis para ativos. |

---

## Banco de dados

O projeto utiliza Supabase com PostgreSQL.

Tabelas esperadas ou utilizadas pelo sistema:

- `perfis_usuarios`
- `ativos`
- `categorias_ativos`
- `marcas_ativos`
- `locais`
- `propriedade_ativos`

Algumas rotas do backend criam tabelas auxiliares automaticamente quando necessário, mas a recomendação para evolução do projeto é versionar um arquivo SQL de estrutura inicial, por exemplo:

```text
database/schema.sql
database/seed.sql
```

Isso facilita reinstalar o sistema em outro ambiente sem depender apenas da criação automática espalhada pelos scripts PHP.

---

## Variáveis de ambiente

O projeto utiliza um arquivo local para credenciais:

```text
Backend/.env
```

Use o arquivo de exemplo como base:

```env
DB_HOST=SEU_HOST_POOLER_SUPABASE
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.SEUIDDOPROJETO
DB_PASSWORD=SUA_NOVA_SENHA_DO_BANCO
DB_SSLMODE=require

SUPABASE_URL=https://SEUIDDOPROJETO.supabase.co
SUPABASE_ANON_KEY=SUA_CHAVE_PUBLICAVEL_DO_SUPABASE
```


---

## Arquivos ignorados pelo Git

O `.gitignore` protege arquivos locais e sensíveis:

```gitignore
Backend/.env
.env
*.local.php
*.log
*.tmp
```

---

## Como rodar o projeto localmente

### 1. Clonar o repositório

Abra o terminal na pasta `htdocs` do XAMPP:

```bash
cd C:\xampp\htdocs
git clone https://github.com/pereuradev/gestao-de-ativos.git
```

### 2. Entrar na pasta

```bash
cd gestao-de-ativos
```

### 3. Configurar variáveis de ambiente

Crie uma cópia do arquivo de exemplo:

```bash
copy Backend\.env.example Backend\.env
```

Depois edite `Backend/.env` com os dados reais do Supabase.

### 4. Habilitar PostgreSQL no PHP

No XAMPP, abra o arquivo `php.ini` e confira se as extensões abaixo estão habilitadas:

```ini
extension=pgsql
extension=pdo_pgsql
```

Depois reinicie o Apache.

### 5. Iniciar o Apache

Abra o painel do XAMPP e inicie:

```text
Apache
```

### 6. Acessar no navegador

```text
http://localhost/gestao-de-ativos/Pagina-login.html
```

Se a pasta local estiver com outro nome, ajuste a URL de acordo com o nome da pasta dentro de `htdocs`.

---

## Fluxo básico de uso

1. Acesse a tela de login.
2. Entre com um usuário cadastrado.
3. Use a página inicial para acompanhar indicadores.
4. Cadastre marcas, propriedades e localizações.
5. Cadastre os ativos.
6. Consulte os ativos pela tela de inventário.
7. Use as telas de edição para manter os dados atualizados.
8. Ajuste preferências visuais em configurações.

---

## Segurança

Cuidados já aplicados ou previstos no projeto:

- Uso de `.env` para credenciais.
- `.gitignore` bloqueando arquivos sensíveis.
- Conexão PDO com tratamento de exceções.
- Sessões PHP para controle de autenticação.
- Validação de método HTTP em rotas do backend.
- Validação de campos recebidos por formulário.
- CSRF em rotas sensíveis.
- Respostas JSON padronizadas.
- Mensagens de erro mais genéricas para evitar exposição de detalhes internos.
- Escape de dados antes da exibição em HTML.

Pontos importantes para evolução:

- Implementar recuperação de senha.
- Revisar regras de permissão por tipo de usuário.
- Criar política mais rígida para rotas administrativas.
- Versionar scripts SQL do banco.
- Implementar logs internos sem expor dados sensíveis.
- Adicionar testes automatizados.
- Avaliar autenticação em duas etapas para administradores.

---

## Decisões de interface

A interface segue uma linha visual corporativa e tecnológica, com:

- Tema escuro como experiência principal.
- Suporte a modo claro.
- Layout com sidebar nas páginas internas.
- Componentes responsivos.
- Cards de indicadores.
- Gráficos para leitura rápida do inventário.
- Animações sutis.
- Preferências visuais salvas localmente.

---

## Autor

Desenvolvido por Pietro Pereira.

Projeto criado para apoiar a gestão interna de ativos da TI TECH Solutions.

---

## Licença

Projeto interno. O uso, cópia, modificação ou distribuição deve respeitar as regras da organização responsável.
