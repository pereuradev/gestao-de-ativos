<?php

// ForÃ§a o PHP a respeitar tipos de dados declarados em funÃ§ÃƒÂµes e parÃƒÂ¢metros.
// Isso ajuda a evitar conversÃƒÂµes automÃ¡ticas inesperadas, deixando o cÃ³digo mais seguro e previsÃ­vel.
declare(strict_types=1);

// Inicia ou recupera a sessÃ£o atual do usuÃ¡rio.
// Ã‰ necessÃ¡rio para acessar os dados salvos em $_SESSION, como usuÃ¡rio logado, tipo de usuÃ¡rio etc.
session_start();

// Verifica se existe um usuÃ¡rio salvo na sessÃ£o e se esse dado estÃ¡ no formato esperado: array.
// Caso nÃ£o exista, significa que o usuÃ¡rio nÃ£o estÃ¡ logado ou que a sessÃ£o expirou.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  // Redireciona o usuÃ¡rio para a tela de login informando, via query string, que a sessÃ£o expirou.
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

/**
 * Escapa textos antes de exibi-los no HTML.
 *
 * Essa funÃ§Ã£o evita XSS, ou seja, impede que um valor vindo da sessÃ£o ou do banco
 * seja interpretado como cÃ³digo HTML/JavaScript dentro da pÃ¡gina.
 */
function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Recupera os dados do usuÃ¡rio logado que foram salvos na sessÃ£o.
$usuario = $_SESSION["usuario"];

// Define o nome do usuÃ¡rio que serÃ¡ exibido na sidebar.
// Caso o Ã­ndice "nome_completo" nÃ£o exista, usa "Usuario" como valor padrÃ£o.
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));

// Define o tipo/perfil do usuÃ¡rio, por exemplo: Administrador, Colaborador etc.
// TambÃ©m Ã© escapado antes de ser exibido no HTML.
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <!-- ConfiguraÃ§Ã£o bÃ¡sica de caracteres e responsividade da pÃ¡gina -->
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- TÃ­tulo e descriÃ§Ã£o da pÃ¡gina, usados pelo navegador e mecanismos de busca -->
  <title>P&aacute;gina Inicial | TI TECH Solutions</title>
  <meta name="description" content="P&aacute;gina inicial do portal interno de gest&atilde;o de ativos da TI TECH Solutions" />

  <!-- Ãcone exibido na aba do navegador -->
  <link rel="icon" type="image/png" href="assets/favicon.png" />

  <!-- PrÃ©-conexÃ£o com o Google Fonts para melhorar o carregamento da fonte -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <!-- Fonte principal da interface -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- Biblioteca de Ã­cones Bootstrap Icons usada nos menus, botÃƒÂµes e cards -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Arquivos CSS da pÃ¡gina -->
  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />

  <!-- Chart.js usado para renderizar grÃ¡ficos no dashboard -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>

  <!-- Scripts da animaÃ§Ã£o de texto e da pÃ¡gina base. O defer faz o JS carregar sem travar o HTML. -->
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-common-ui" defer></script>
  <script src="js/pagina-base.js?v=20260624-dashboard-render-fix" defer></script>
</head>

<body class="theme-dark page-loading">
  <!-- Estrutura principal da aplicaÃ§Ã£o: sidebar lateral + Ã¡rea principal -->
  <div class="app-shell">

    <!-- Menu lateral fixo da aplicaÃ§Ã£o -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">

        <!-- Ãrea da marca/logo. Leva para o site institucional da TI TECH Solutions. -->
        <a href="https://www.titechsolutions.com.br/" class="brand-area" aria-label="Acessar site da TI TECH Solutions">
          <img class="brand-logo" src="assets/logo-branca.png" alt="TI TECH Solutions" />
        </a>

        <!-- BotÃ£o usado no mobile/responsivo para fechar a sidebar -->
        <button class="icon-button sidebar-close" id="closeSidebar" type="button" aria-label="Fechar menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- NavegaÃ§Ã£o principal do sistema -->
      <nav class="sidebar-nav" aria-label="Menu principal">

        <!-- Link da pÃ¡gina atual. A classe active indica visualmente que o usuÃ¡rio estÃ¡ nesta tela. -->
        <a class="nav-link active" href="pagina-inicial.php">
          <i class="bi bi-speedometer2"></i>
          <span>P&aacute;gina Inicial</span>
        </a>

        <!-- Link para a tela de funcionÃ¡rios -->
        <a class="nav-link" href="funcionarios.php">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
        </a>

        <!-- Grupo expansÃ­vel de cadastros -->
        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <!-- Submenu aberto/fechado via JavaScript -->
          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
            <a href="locais.php">Localiza&ccedil;&otilde;es</a>
          </div>
        </div>

        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="editingSubmenu">
            <i class="bi bi-pencil-square"></i>
            <span>Edi&ccedil;&atilde;o</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="editingSubmenu">
            <a href="edicao-ativos.php">Ativos</a>
            <a href="edicao-marcas.php">Marcas</a>
          </div>
        </div>

        <a class="nav-link" href="ativos.php">
          <i class="bi bi-hdd-network-fill"></i>
          <span>Ativos</span>
        </a>

        <!-- Link para configuraÃ§ÃƒÂµes do sistema -->
        <a class="nav-link" href="configuracoes.php">
          <i class="bi bi-gear-fill"></i>
          <span>Configura&ccedil;&otilde;es</span>
        </a>
      </nav>

      <!-- RodapÃ© da sidebar com dados do usuÃ¡rio logado e botÃ£o de logout -->
      <div class="sidebar-footer">
        <div class="sidebar-summary">
          <!-- Tipo do usuÃ¡rio vindo da sessÃ£o PHP -->
          <span><?php echo $tipoUsuario; ?></span>

          <!-- Nome do usuÃ¡rio vindo da sessÃ£o PHP. O title ajuda quando o nome for grande e ficar cortado no layout. -->
          <strong title="<?php echo $nomeUsuario; ?>"><?php echo $nomeUsuario; ?></strong>
        </div>

        <!-- Link de logout. O arquivo Backend/logout.php deve destruir a sessÃ£o e redirecionar o usuÃ¡rio. -->
        <a href="Backend/logout.php" class="logout-button">
          <i class="bi bi-box-arrow-left"></i>
          <span>Sair do sistema</span>
        </a>
      </div>
    </aside>

    <!-- Camada escura usada no mobile para fechar a sidebar ao clicar fora dela -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ConteÃºdo principal da pÃ¡gina -->
    <main class="main-area">

      <!-- Barra superior com botÃ£o de menu, tÃ­tulo, busca e troca de tema -->
      <header class="topbar">
        <div class="topbar-left">

          <!-- BotÃ£o para abrir a sidebar em telas menores -->
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <!-- TÃ­tulo principal da pÃ¡gina com efeito de digitaÃ§Ã£o -->
          <div>
            <p class="eyebrow">Portal TI TECH</p>
            <h1>
              <span
                class="typewriter-heading"
                style="--typewriter-min: 18ch"
                data-typewriter-phrases="Gest&atilde;o de ativos.|Indicadores conectados.|Opera&ccedil;&atilde;o em tempo real."
              >Gest&atilde;o de ativos</span><span aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">

          <!-- Campo de busca para filtrar categorias exibidas no dashboard -->
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input id="categorySearch" type="search" placeholder="Filtrar categorias..."
              aria-label="Filtrar categorias" />
          </div>

          <!-- BotÃ£o de alternÃƒÂ¢ncia de tema. O comportamento visual deve estar no arquivo JS. -->
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-moon-stars-fill"></i>
            <span>Modo escuro</span>
          </button>
        </div>
      </header>

      <!-- Painel principal de apresentaÃ§Ã£o do dashboard -->
      <section class="hero-panel dashboard-hero" aria-labelledby="dashboardTitle">
        <div class="hero-content">
          <p class="section-tag">P&aacute;gina inicial operacional</p>

          <!-- TÃ­tulo com loop de frases usando o script typewriter.js -->
          <h2 id="dashboardTitle">
            <span
              class="typewriter-heading"
              style="--typewriter-min: 30ch"
              data-typewriter-loop
              data-typewriter-phrases="Controle de ativos conectado.|Invent&aacute;rio sincronizado.|Decis&otilde;es mais r&aacute;pidas."
            >Controle de ativos conectado.</span><span aria-hidden="true"></span>
          </h2>

          <!-- Texto de apoio do painel principal -->
          <p>
            Acompanhe equipamentos cadastrados, usu&aacute;rios ativos e
            categorias de ativos em uma vis&atilde;o &uacute;nica para suporte e invent&aacute;rio.
          </p>

          <!-- BotÃƒÂµes principais de navegaÃ§Ã£o rÃ¡pida -->
          <div class="hero-actions">
            <a href="ativos.php" class="primary-button">
              <i class="bi bi-pc-display-horizontal"></i>
              Ver ativos
            </a>

            <a href="funcionarios.php" class="secondary-button">
              <i class="bi bi-people-fill"></i>
              Funcion&aacute;rios
            </a>
          </div>
        </div>
      </section>

      <!-- Cards com indicadores principais da pÃ¡gina inicial -->
      <section class="metrics-grid" aria-label="Indicadores principais">

        <!-- Card/link para o portal institucional da empresa -->
        <a class="metric-card site-metric-card" href="https://www.titechsolutions.com.br/"
          aria-label="Ir para o site da TI TECH Solutions">
          <div class="metric-icon">
            <i class="bi bi-box-arrow-up-right"></i>
          </div>

          <div>
            <span>Portal institucional</span>
            <strong>Ir para o nosso site</strong>
            <p>Conhe&ccedil;a as solu&ccedil;&otilde;es no nosso site oficial da TI TECH.</p>
          </div>
        </a>

        <!-- Card com total de ativos. O valor provavelmente Ã© preenchido pelo JavaScript apÃ³s consulta ao backend. -->
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-hdd-stack-fill"></i>
          </div>

          <div>
            <span>Total de ativos</span>
            <strong id="totalAssetsMetric">--</strong>
          </div>
        </article>

        <!-- Card com total de funcionÃ¡rios. O valor tambÃ©m deve ser preenchido dinamicamente. -->
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-badge-fill"></i>
          </div>

          <div>
            <span>Funcion&aacute;rios</span>
            <strong id="employeesMetric">--</strong>
          </div>
        </article>
      </section>

      <!-- Card do grÃ¡fico de evoluÃ§Ã£o do estoque -->
      <section class="content-card chart-card" aria-labelledby="stockChartTitle">
        <div class="card-header">
          <div>
            <p class="section-tag">Estoque</p>
            <h3 id="stockChartTitle">Evolu&ccedil;&atilde;o de itens em estoque</h3>
          </div>

          <!-- Link simples para recarregar/voltar para a pÃ¡gina inicial -->
          <a class="text-button compact-action" href="pagina-inicial.php">
            Ir para p&aacute;gina inicial
          </a>
        </div>

        <!-- Ãrea onde o Chart.js vai desenhar o grÃ¡fico -->
        <div class="chart-shell">
          <canvas id="stockEvolutionChart" aria-label="Gr&aacute;fico evolutivo dos itens em estoque"></canvas>
        </div>
      </section>

      <!-- ATENÃ‡ÃƒO: no cÃ³digo original existe este segundo fechamento de section.
           Ele parece estar duplicado. Se o layout quebrar, remova esta linha abaixo. -->
      </section>

      <!-- Grid com lista de categorias e atalhos principais -->
      <section class="content-grid">

        <!-- Card maior com categorias de ativos -->
        <article class="content-card large-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Categorias</p>
              <h3>Ativos por tipo</h3>
            </div>

            <a class="text-button" href="ativos.php">Ver todos</a>
          </div>

          <!-- Lista preenchida dinamicamente pelo JS com as categorias vindas do banco/backend -->
          <div id="categoryList" class="category-list" aria-live="polite">
            <div class="empty-state">
              <i class="bi bi-cloud-arrow-down"></i>
              <span>Carregando categorias...</span>
            </div>
          </div>
        </article>

        <!-- Card lateral com atalhos rÃ¡pidos -->
        <article class="content-card product-health-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Produtos</p>
              <h3>Sa&uacute;de dos produtos</h3>
            </div>
          </div>

          <!-- Links rÃ¡pidos para telas/filtros importantes do sistema -->
          <div class="product-health-body" aria-live="polite">
            <div class="product-health-chart-shell">
              <canvas id="productHealthChart" aria-label="Distribui&ccedil;&atilde;o de produtos por status"></canvas>
              <div class="product-health-center" aria-hidden="true">
                <strong id="productHealthTotal">--</strong>
                <span>ativos</span>
              </div>
            </div>

            <div id="productHealthLegend" class="product-health-legend"></div>
          </div>

          <!-- Status de carregamento/conexÃ£o do dashboard. Deve ser atualizado pelo JS. -->
          <div id="dashboardStatus" class="dashboard-status product-health-status" role="status">
            Conectando ao banco de dados...
          </div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>




