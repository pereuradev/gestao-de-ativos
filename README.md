# Gestão de Ativos TI TECH Solutions

Sistema web interno para gestão de ativos de TI, desenvolvido para apoiar rotinas de inventário, suporte, cadastro, consulta e controle operacional de equipamentos, funcionários, marcas, categorias e localizações.

O projeto foi construído com PHP, JavaScript, CSS e Supabase, rodando em ambiente local com XAMPP.

## Visão Geral

O sistema centraliza informações importantes para a operação de TI, permitindo acompanhar ativos cadastrados, funcionários, marcas, locais de armazenamento e indicadores do inventário em uma interface corporativa moderna.

A proposta visual segue uma identidade escura, tecnológica e limpa, com suporte a modo claro, componentes responsivos, filtros dinâmicos e painel de configurações para preferências do usuário.

## Principais Funcionalidades

- Login e cadastro de usuários
- Integração com Supabase
- Dashboard com indicadores operacionais
- Cadastro de ativos
- Consulta e filtragem de ativos
- Edição de ativos cadastrados
- Cadastro e edição de marcas
- Cadastro de localizações
- Listagem de funcionários
- Filtros por status, categoria, marca e busca textual
- Modo claro e modo escuro
- Preferências visuais salvas no navegador
- Painel de configurações do usuário
- Validação visual de senha no painel de configurações
- Diagnóstico do sistema para suporte
- Layout responsivo para desktop, tablet e celular

## Tecnologias Utilizadas

- PHP
- JavaScript
- CSS
- HTML
- Supabase
- PostgreSQL
- XAMPP
- Bootstrap Icons
- Chart.js

## Estrutura do Projeto

```text
SIte-Gestão_de_Ativos/
├── Backend/
│   ├── Conexao.php
│   ├── config.php
│   ├── login-usuario.php
│   ├── cadastrar-usuario.php
│   ├── cadastrar-ativo.php
│   ├── cadastrar-marca.php
│   ├── cadastrar-local.php
│   ├── atualizar-ativo.php
│   ├── atualizar-marca.php
│   ├── excluir-ativo.php
│   ├── excluir-marca.php
│   ├── dashboard-metricas.php
│   ├── logout.php
│   └── status-ativos.php
├── assets/
│   ├── Logo.png
│   ├── logo-branca.png
│   ├── favicon.png
│   └── imagens de fundo do sistema
├── css/
│   ├── pagina-base.css
│   ├── ux-profissional.css
│   ├── ativos.css
│   ├── cadastro-ativos.css
│   ├── configuracoes.css
│   ├── funcionarios.css
│   ├── locais.css
│   └── marcas.css
├── js/
│   ├── app-base.js
│   ├── ativos.js
│   ├── cadastro-ativos.js
│   ├── configuracoes.js
│   ├── funcionarios.js
│   ├── locais.js
│   └── marcas.js
├── Pagina-login.html
├── Pagina-cadastro.html
├── pagina-inicial.php
├── ativos.php
├── cadastro-ativos.php
├── edicao-ativos.php
├── funcionarios.php
├── marcas.php
├── locais.php
├── configuracoes.php
└── README.md
```

## Páginas do Sistema

### Login

Tela inicial de autenticação do usuário, integrada ao fluxo de validação do Supabase e aos perfis cadastrados no banco.

### Página Inicial

Dashboard operacional com indicadores, visão geral do inventário, gráficos e atalhos para as principais áreas do sistema.

### Ativos

Página de consulta dos ativos cadastrados, com busca textual e filtros por status, categoria e marca.

### Cadastro de Ativos

Formulário para registrar novos equipamentos no inventário, com campos relacionados a categoria, marca, localização, status e informações complementares.

### Funcionários

Tela de listagem de colaboradores cadastrados, exibindo dados principais, status e informações úteis para controle de acesso e suporte.

### Marcas

Área para cadastro e gerenciamento de marcas utilizadas nos ativos.

### Localizações

Área para cadastro de locais, salas, setores e pontos de armazenamento dos ativos.

### Configurações

Painel de controle do usuário com dados da conta, preferências de interface, segurança, notificações, permissões e diagnóstico do sistema.

## Configuração do Ambiente

### 1. Clonar o repositório

```powershell
cd "C:\xampp\htdocs"
git clone https://github.com/pereuradev/gestao-de-ativos.git
```

### 2. Acessar a pasta do projeto

```powershell
cd "C:\xampp\htdocs\gestao-de-ativos"
```

Se o projeto estiver com o nome original da pasta local:

```powershell
cd "C:\xampp\htdocs\SIte-Gestão_de_Ativos"
```

### 3. Configurar o arquivo de ambiente

Crie o arquivo:

```text
Backend/.env
```

Use como base:

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

Importante: o arquivo `Backend/.env` não deve ser enviado ao GitHub.

### 4. Iniciar o XAMPP

No painel do XAMPP, inicie:

- Apache

Se estiver usando banco local, inicie também:

- MySQL

Neste projeto, o banco principal utilizado é o Supabase/PostgreSQL.

### 5. Acessar no navegador

```text
http://localhost/SIte-Gestão_de_Ativos/
```

Ou, se a pasta clonada estiver com outro nome:

```text
http://localhost/gestao-de-ativos/
```

## Banco de Dados

O projeto utiliza Supabase com PostgreSQL.

Tabelas utilizadas pelo sistema:

- `perfis_usuarios`
- `ativos`
- `marcas_ativos`
- `locais`
- `categorias_ativos`

As credenciais de conexão são carregadas pelo arquivo `Backend/.env`, usando o helper `Backend/config.php`.

## Segurança

Cuidados aplicados no projeto:

- Credenciais removidas do código-fonte
- Uso de `.env` para dados sensíveis
- `.gitignore` bloqueando o envio de credenciais
- Senhas de usuários tratadas com hash seguro no backend
- Sessão PHP validada nas páginas internas
- Escapamento de dados antes da exibição em HTML
- Mensagens de erro mais genéricas na conexão com banco

Arquivos sensíveis que não devem ser versionados:

```text
Backend/.env
.env
*.local.php
*.log
*.tmp
```

## Preferências do Usuário

Algumas preferências são salvas localmente no navegador por meio de `localStorage`, como:

- Tema claro, escuro ou automático
- Cor de destaque
- Densidade da interface
- Redução de animações
- Realce de cursor
- Preferências de notificações
- Modo de trabalho
- Preferências do dashboard

Essas preferências estão preparadas para futura integração com Supabase.

## Próximas Melhorias

- Salvar preferências do usuário no banco
- Implementar alteração real de senha pelo backend
- Criar histórico real de atividades
- Adicionar controle avançado de permissões
- Implementar relatórios exportáveis
- Criar testes automatizados
- Melhorar documentação do banco de dados
- Adicionar telas administrativas para categorias
- Implementar recuperação de senha
- Adicionar autenticação em duas etapas

## Como Contribuir

1. Faça um fork do projeto
2. Crie uma branch para sua alteração
3. Faça commit das mudanças
4. Envie para o GitHub
5. Abra um Pull Request

Exemplo:

```powershell
git checkout -b minha-melhoria
git add .
git commit -m "Descreve a melhoria realizada"
git push origin minha-melhoria
```

## Autor

Desenvolvido por Pietro Pereira para o projeto interno de gestão de ativos da TI TECH Solutions.

## Licença

Projeto interno. O uso, cópia ou distribuição deve respeitar as regras da organização responsável.
