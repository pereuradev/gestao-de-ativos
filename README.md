Atuali# Gestao de Ativos TI TECH Solutions

Sistema web interno para gestao de ativos de TI, criado para centralizar inventario, usuarios, marcas, propriedades, localizacoes, permissoes e indicadores operacionais em um unico portal.

O projeto foi desenvolvido para uso corporativo da TI TECH Solutions, com foco em organizacao, rastreabilidade, visual moderno e operacao simples para administradores e colaboradores.

---

## Status

Projeto em desenvolvimento ativo.

Principais areas ja estruturadas:

- Login com Supabase Auth.
- Solicitacao publica de acesso com envio de foto.
- Analise, correcao, aprovacao e recusa de solicitacoes de acesso.
- Aviso sonoro e contador de solicitacoes pendentes na sidebar.
- Controle de sessao com PHP.
- Diferenciacao entre administrador e colaborador.
- Sidebar responsiva, expansivel e recolhivel para exibicao somente dos icones.
- Dashboard de produtos com filtros por tipo, marca e localizacao.
- Dashboard de evolucao do inventario com periodos diario, semanal, mensal e anual.
- Cadastro, visualizacao, edicao e exclusao de ativos.
- Cadastro em quantidade para ativos com PN e preenchimento individual de SN.
- Exportacao da lista de ativos em PDF e Excel.
- Cadastro, visualizacao, edicao e exclusao de marcas.
- Cadastro, visualizacao, edicao e exclusao de propriedades.
- Cadastro, visualizacao, edicao e exclusao de localizacoes.
- Cadastro e listagem de funcionarios com foto de cracha.
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
- Pagina publica para novos usuarios solicitarem acesso.
- Foto obrigatoria na solicitacao, com selecao de arquivo no desktop e camera no celular.
- Fluxo interno protegido por permissao para corrigir, aprovar ou recusar pedidos.

### Pagina inicial

- Acoes rapidas para acessar o dashboard, consultar ativos, cadastrar ativos e abrir configuracoes.
- Cards independentes para o site e a loja da empresa.
- Links para LinkedIn, YouTube, Instagram e Facebook com as cores originais de cada rede.
- Layout responsivo e integrado as preferencias visuais do usuario.

### Dashboard

- Indicadores gerais do inventario.
- Filtro por tipo de produto.
- Filtro por marca.
- Filtro por localizacao.
- Visualizacao por tipo, marca, status, local e evolucao de cadastros.
- Graficos em barras, pizza, rosca, linhas e polar.
- Painel lateral com leitura rapida dos dados exibidos.
- Troca automatica da leitura para marcas e localizacoes conforme os filtros selecionados.
- Estados de carregamento para a evolucao dos ativos e para os dados exibidos.

### Ativos

- Cadastro de ativos.
- Edicao de ativos cadastrados.
- Exclusao de ativos.
- Visualizacao com filtros e paginacao.
- Exportacao dos resultados filtrados em PDF e XLSX.
- Cadastro de ate 100 unidades por operacao quando a rastreabilidade permite quantidade.
- Modal para informar um numero de serie diferente para cada unidade quando PN e SN sao usados juntos.
- Coluna de datasheet.
- Campos de categoria, status, local, marca, propriedade, numero de serie, IMEI e descricao.
- Layout adaptado para leitura dos dados sem rolagem horizontal desnecessaria no celular.

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
- Foto de cracha nos cards, detalhes, edicao, configuracoes e sidebar.
- Ultimos acessos criados na tela de cadastro.
- Tela protegida para gerenciar solicitacoes pendentes, aprovadas e recusadas.
- Contador limitado visualmente a `99+` e notificacao de novo pedido.

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
- Atualizacao da foto de cracha.
- Atualizacao de senha com validacao no backend e no Supabase Auth.
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
- cURL.
- Fileinfo.
- Mbstring.
- ZipArchive para exportacao em XLSX.
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
- PHP com `curl`, `fileinfo`, `mbstring`, `pdo_pgsql`, `pgsql` e `zip` habilitados.

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
│   ├── dashboard-metricas.php
│   ├── solicitar-acesso.php
│   ├── processar-solicitacoes-acesso.php
│   ├── foto-solicitacao-acesso.php
│   ├── exportar-ativos.php
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
│   ├── base-interface.js
│   ├── core/
│   │   └── armazenamento-local.js
│   ├── animations/
│   │   ├── efeito-digitacao.js
│   │   └── entrada-pagina.js
│   ├── ui/
│   │   ├── feedback-interface.js
│   │   ├── menu-lateral.js
│   │   ├── notificacoes-solicitacoes-acesso.js
│   │   └── widgets-react.js
│   └── pages/
│       ├── pagina-login.js
│       ├── dashboard-produtos.js
│       ├── solicitar-acesso.js
│       ├── solicitacoes-acesso.js
│       └── scripts especificos das demais paginas
│
├── pages/
│   ├── Pagina-login.html
│   ├── pagina-inicial.php
│   ├── dashboard.php
│   ├── ativos.php
│   ├── cadastro-ativos.php
│   ├── edicao-ativos.php
│   ├── cadastro-funcionarios.php
│   ├── funcionarios.php
│   ├── solicitar-acesso.php
│   ├── gerenciar-solicitacoes-acesso.php
│   ├── cadastro-grupos.php
│   ├── edicao-grupos.php
│   ├── configuracoes.php
│   └── demais paginas da interface
├── index.php
└── README.md
```

---

## Paginas principais

| Pagina                                    | Funcao                                                |
| ----------------------------------------- | ----------------------------------------------------- |
| `pages/Pagina-login.html`                 | Tela de login do sistema.                             |
| `pages/solicitar-acesso.php`              | Formulario publico para solicitar acesso.             |
| `pages/pagina-inicial.php`                | Pagina inicial interna apos login.                    |
| `pages/dashboard.php`                     | Dashboard visual de produtos e inventario.            |
| `pages/ativos.php`                        | Visualizacao e filtros de ativos.                     |
| `pages/cadastro-ativos.php`               | Cadastro de ativos.                                   |
| `pages/edicao-ativos.php`                 | Edicao e exclusao de ativos.                          |
| `pages/funcionarios.php`                  | Listagem de funcionarios.                             |
| `pages/cadastro-funcionarios.php`         | Cadastro de funcionarios, restrito a administradores. |
| `pages/edicao-funcionarios.php`           | Edicao de funcionarios e de seus dados de acesso.     |
| `pages/gerenciar-solicitacoes-acesso.php` | Analise e decisao sobre solicitacoes de acesso.       |
| `pages/cadastro-grupos.php`               | Criacao de grupos e permissoes.                       |
| `pages/edicao-grupos.php`                 | Remocao de membros e exclusao de grupos.              |
| `pages/marcas.php`                        | Cadastro de marcas.                                   |
| `pages/marcas-visualizacao.php`           | Visualizacao de marcas.                               |
| `pages/edicao-marcas.php`                 | Edicao e exclusao de marcas.                          |
| `pages/locais.php`                        | Cadastro de localizacoes.                             |
| `pages/locais-visualizacao.php`           | Visualizacao de localizacoes.                         |
| `pages/edicao-locais.php`                 | Edicao e exclusao de localizacoes.                    |
| `pages/propriedades.php`                  | Cadastro de propriedades.                             |
| `pages/propriedades-visualizacao.php`     | Visualizacao de propriedades.                         |
| `pages/edicao-propriedades.php`           | Edicao e exclusao de propriedades.                    |
| `pages/configuracoes.php`                 | Preferencias, seguranca e ajustes do usuario.         |

---

## Endpoints principais

| Arquivo                                     | Responsabilidade                               |
| ------------------------------------------- | ---------------------------------------------- |
| `Backend/Conexao.php`                       | Cria a conexao PDO com PostgreSQL/Supabase.    |
| `Backend/config.php`                        | Carrega variaveis do arquivo `.env`.           |
| `Backend/login-usuario.php`                 | Autentica usuarios.                            |
| `Backend/logout.php`                        | Encerra a sessao.                              |
| `Backend/solicitar-acesso.php`              | Recebe um novo pedido publico de acesso.       |
| `Backend/processar-solicitacoes-acesso.php` | Lista, altera, aprova e recusa pedidos.        |
| `Backend/foto-solicitacao-acesso.php`       | Entrega fotos somente a usuarios autorizados.  |
| `Backend/cadastrar-usuario.php`             | Cria funcionarios no Auth e no perfil local.   |
| `Backend/cadastrar-ativo.php`               | Cadastra ativos.                               |
| `Backend/atualizar-ativo.php`               | Atualiza ativos.                               |
| `Backend/excluir-ativo.php`                 | Exclui ativos.                                 |
| `Backend/cadastrar-grupo.php`               | Cria grupos de acesso.                         |
| `Backend/remover-membro-grupo.php`          | Remove funcionario de um grupo.                |
| `Backend/excluir-grupo.php`                 | Exclui grupo, membros e permissoes vinculadas. |
| `Backend/grupos-acesso-util.php`            | Centraliza perfis e permissoes de grupos.      |
| `Backend/dashboard-produtos.php`            | Retorna dados do dashboard de produtos.        |
| `Backend/dashboard-metricas.php`            | Retorna indicadores e evolucao do inventario.  |
| `Backend/exportar-ativos.php`               | Exporta a consulta de ativos em PDF ou XLSX.   |
| `Backend/status-ativos.php`                 | Centraliza status de ativos.                   |

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
- `solicitacoes_acesso`

As rotas PHP nao executam DDL em runtime. Este repositorio nao distribui
migrations nem a configuracao local da CLI do Supabase. O schema precisa estar
previamente provisionado no projeto remoto antes de iniciar a aplicacao.

Mudancas de estrutura devem ser aplicadas e documentadas pelo processo externo
de administracao do banco, preservando constraints, indices, funcoes, grants e
politicas RLS necessarias para o funcionamento do sistema.

---

## Variaveis de ambiente

O sistema usa credenciais locais em:

```text
C:\xampp\private\site-gestao-de-ativos\Backend\.env
```

Use `Backend/.env.example` como base e copie o arquivo para esse caminho fora do `htdocs`:

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

`SUPABASE_ANON_KEY` e o nome esperado atualmente pelo codigo. Informe apenas
uma chave publica compativel com o cliente. Nunca use `service_role` ou outra
chave secreta nessa configuracao.

Se precisar usar outro local, defina a variavel de ambiente `APP_ENV_FILE`
apontando para o arquivo desejado.

O arquivo `.env` nao deve ficar dentro do `htdocs` nem ser enviado para o GitHub.

---

## Publicacao no servidor interno com XAMPP

### 1. Publicar os arquivos

Copie o projeto para uma pasta dentro de `C:\xampp\htdocs`. O exemplo abaixo
usa `SITE-GESTAO_DE_ATIVOS`, mas o nome pode ser ajustado ao ambiente:

```text
C:\xampp\htdocs\SITE-GESTAO_DE_ATIVOS
```

Nao copie o `.env` para essa pasta publica. Preserve tambem os arquivos
`.htaccess` existentes, inclusive os que protegem os diretorios de upload.

### 2. Criar a configuracao privada

Crie a pasta abaixo e coloque nela uma copia preenchida de
`Backend/.env.example`:

```text
C:\xampp\private\site-gestao-de-ativos\Backend\.env
```

O `Backend/config.php` procura esse caminho automaticamente. Quando o ambiente
precisar de outro local, configure `APP_ENV_FILE` com o caminho absoluto do
arquivo.

### 3. Habilitar as extensoes do PHP

No arquivo `C:\xampp\php\php.ini`, confirme que estas extensoes nao estao
comentadas com `;`:

```ini
extension=curl
extension=fileinfo
extension=mbstring
extension=pdo_pgsql
extension=pgsql
```

O modulo `zip`, usado na exportacao em Excel, tambem precisa aparecer em
`php -m`. Dependendo da versao do XAMPP, ele ja vem incorporado ao PHP e nao
possui uma linha propria no `php.ini`.

Depois de salvar o `php.ini`, reinicie o Apache pelo painel do XAMPP. Sem o
reinicio, as alteracoes nao sao aplicadas.

Valide os modulos no PowerShell do servidor:

```powershell
& "C:\xampp\php\php.exe" -m |
  Select-String "curl|fileinfo|mbstring|pdo_pgsql|pgsql|zip"

& "C:\xampp\php\php.exe" -r "print_r(PDO::getAvailableDrivers());"
```

A segunda verificacao precisa listar `pgsql` entre os drivers do PDO.

### 4. Liberar e testar o acesso interno

Se o Apache estiver configurado na porta `8080`, confirme que a porta esta
permitida no Firewall do Windows e teste primeiro no proprio servidor. Depois,
teste em outro computador da mesma rede:

```powershell
$enderecoServidor = "192.168.0.4" # Ajuste quando o IP do servidor mudar.
Test-NetConnection $enderecoServidor -Port 8080
```

Formato da URL de acesso:

```text
http://<IP_DO_SERVIDOR>:8080/<PASTA_DO_PROJETO>/pages/Pagina-login.html
```

### 5. Validar o sistema depois da publicacao

- Abrir a pagina de login em um computador da rede.
- Entrar com um usuario valido.
- Confirmar o carregamento do dashboard e dos filtros.
- Abrir a listagem de ativos e testar uma exportacao.
- Enviar uma solicitacao de acesso com foto e conferir a notificacao interna.
- Confirmar que `uploads/crachas` e `uploads/solicitacoes-acesso` permitem
  gravacao pelo processo do Apache.
- Remover qualquer arquivo temporario de `phpinfo` usado no diagnostico.

---

## Diagnostico de `Servidor indisponivel`

A mensagem exibida no login e generica: ela pode representar falha de rede,
mas tambem uma resposta HTTP `500` produzida pelo backend.

Siga esta ordem de verificacao:

1. Confirme que o Apache permanece iniciado no painel do XAMPP.
2. Execute `Test-NetConnection` para o IP e a porta publicados.
3. Confirme a existencia e a leitura do `.env` privado, sem imprimir seus
   valores no terminal.
4. Verifique se `pdo_pgsql` e `pgsql` aparecem no comando `php -m`.
5. Consulte as ultimas linhas do log logo depois de repetir o erro:

```powershell
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50
```

Se a pagina de login abrir, mas o envio do formulario retornar erro, a rede e a
rota basica estao acessiveis. Nesse caso, concentre o diagnostico no PHP, nas
extensoes, no `.env`, na conexao PostgreSQL e no log do Apache.

---

## Seguranca e boas praticas aplicadas

- Credenciais fora do codigo, via `.env`.
- `.env` ignorado pelo Git.
- `.env` mantido fora do `DocumentRoot` do Apache.
- Sessao PHP para paginas internas.
- Validacao de metodo HTTP nos endpoints.
- Validacao de campos no backend.
- CSRF nas rotas sensiveis.
- Escape de dados antes de exibir no HTML.
- Respostas JSON padronizadas.
- Mensagens de erro controladas.
- Uso de PDO com excecoes.
- Fotos de solicitacoes servidas por endpoint protegido por permissao.
- Diretorios de upload com bloqueio de listagem e execucao de scripts.
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
