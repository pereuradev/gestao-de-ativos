<?php

// Mantém a tipagem mais rígida no PHP.
// Isso reduz conversões automáticas inesperadas e deixa o comportamento do código mais previsível.
declare(strict_types=1);

// Inicia a sessão para conseguir acessar os dados do usuário logado.
session_start();

// Garante que só usuários autenticados acessem a página.
// Se não houver usuário válido na sessão, o acesso volta para o login.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  // Informa ao login que a sessão expirou, para a tela poder exibir o aviso correto.
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

/**
 * Prepara textos antes de exibir no HTML.
 *
 * Isso evita XSS, impedindo que dados vindos da sessão ou do banco
 * sejam interpretados como HTML ou JavaScript pela página.
 */
function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Dados básicos do usuário recuperados da sessão.
$usuario = $_SESSION["usuario"];

// Nome exibido na sidebar. Se não vier da sessão, usa um valor padrão.
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));

// Tipo de usuário exibido na interface, como Administrador ou Colaborador.
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);
$sidebarRoleLabel = e($sidebarIsAdmin ? "ADM" : "Colaborador");
$sidebarRoleClass = e($sidebarIsAdmin ? "is-admin" : "is-collaborator");
$sidebarEmail = e((string) ($usuario["email"] ?? ""));
$sidebarDepartment = e((string) ($usuario["departamento"] ?? "Sem departamento"));
$sidebarNameText = (string) ($usuario["nome_completo"] ?? "Usuario");
$sidebarNameParts = preg_split("/\s+/", trim($sidebarNameText)) ?: [];
$sidebarInitialsText = "";
foreach ($sidebarNameParts as $sidebarNamePart) {
  if ($sidebarNamePart === "") {
    continue;
  }

  $sidebarInitialsText .= strtoupper(substr($sidebarNamePart, 0, 1));

  if (strlen($sidebarInitialsText) >= 2) {
    break;
  }
}
$sidebarInitials = e($sidebarInitialsText !== "" ? $sidebarInitialsText : "TT");
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <!-- Configuração básica de caracteres e responsividade -->
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Título e descrição da página -->
  <title>P&aacute;gina Inicial | TI TECH Solutions</title>
  <meta name="description"
    content="P&aacute;gina inicial do portal interno de gest&atilde;o de ativos da TI TECH Solutions" />

  <!-- Ícone exibido na aba do navegador -->
  <link rel="icon" type="image/png" href="assets/favicon.png" />

  <!-- Otimiza a conexão com o Google Fonts antes de carregar a fonte -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <!-- Fonte principal da interface -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- Bootstrap Icons: biblioteca usada para os ícones da interface -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Arquivos CSS da página -->
  <link rel="stylesheet" href="css/pagina-base.css?v=20260626-user-card" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260626-clear-button" />

  <!-- Chart.js usado para renderizar gráficos no dashboard -->
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>

  <!-- Scripts da página. O defer evita bloquear o carregamento do HTML. -->
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260629-theme-action-label" defer></script>
  <script src="js/pagina-base.js?v=20260629-theme-action-label" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <!-- Estrutura principal: menu lateral e conteúdo da página -->
  <div class="app-shell">

    <!-- Menu lateral fixo da aplicação -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">

        <!-- Logo da empresa com link para o site institucional -->
        <a href="https://www.titechsolutions.com.br/" class="brand-area" aria-label="Acessar site da TI TECH Solutions">
          <img class="brand-logo" src="assets/logo-branca.png" alt="TI TECH Solutions" />
        </a>

        <!-- Fecha o menu lateral em telas menores -->
        <button class="icon-button sidebar-close" id="closeSidebar" type="button" aria-label="Fechar menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- Navegação principal do sistema -->
      <nav class="sidebar-nav" aria-label="Menu principal">

        <!-- Link da página atual. A classe active destaca o item no menu. -->
        <a class="nav-link active" href="pagina-inicial.php">
          <i class="bi bi-house-door-fill"></i>
          <span>P&aacute;gina Inicial</span>
        </a>
        <a class="nav-link" href="dashboard.php">
          <i class="bi bi-bar-chart-fill"></i>
          <span>Dashboard</span>
        </a>

        <!-- Link para a tela de funcionários -->
        <a class="nav-link" href="funcionarios.php">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
        </a>

        <a class="nav-link" href="marcas-visualizacao.php">
          <i class="bi bi-tags-fill"></i>
          <span>Marcas</span>
        </a>

        <a class="nav-link" href="propriedades-visualizacao.php">
          <i class="bi bi-building-check"></i>
          <span>Propriedades</span>
        </a>

        <a class="nav-link" href="locais-visualizacao.php">
          <i class="bi bi-geo-alt-fill"></i>
          <span>Localiza&ccedil;&otilde;es</span>
        </a>
        <!-- Grupo expansível de cadastros -->
        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <!-- Submenu controlado pelo JavaScript -->
          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
            <a href="propriedades.php">Propriedades</a>
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
            <a href="edicao-propriedades.php">Propriedades</a>
            <a href="edicao-locais.php">Localiza&ccedil;&otilde;es</a>
          </div>
        </div>

        <a class="nav-link" href="ativos.php">
          <i class="bi bi-hdd-network-fill"></i>
          <span>Ativos</span>
        </a>

        <!-- Link para configurações do sistema -->
        <a class="nav-link" href="configuracoes.php">
          <i class="bi bi-gear-fill"></i>
          <span>Configura&ccedil;&otilde;es</span>
        </a>
      </nav>

      <!-- Rodapé do menu com usuário logado e saída do sistema -->
      <div class="sidebar-footer">
        <div class="sidebar-summary user-summary-card">
          <div class="sidebar-avatar" aria-hidden="true"><?php echo $sidebarInitials; ?></div>
          <div class="sidebar-user-info">
            <strong title="<?php echo $nomeUsuario; ?>"><?php echo $nomeUsuario; ?></strong>
            <span class="sidebar-role <?php echo $sidebarRoleClass; ?>"><?php echo $sidebarRoleLabel; ?></span>
            <small
              title="<?php echo $sidebarEmail; ?>"><?php echo $sidebarEmail !== "" ? $sidebarEmail : "Email nao informado"; ?></small>
            <small title="<?php echo $sidebarDepartment; ?>"><?php echo $sidebarDepartment; ?></small>
          </div>
        </div>

        <!-- Logout: encerra a sessão no backend e tira o usuário do sistema -->
        <a href="Backend/logout.php" class="logout-button">
          <i class="bi bi-box-arrow-left"></i>
          <span>Sair do sistema</span>
        </a>
      </div>
    </aside>

    <!-- Fundo clicável usado para fechar a sidebar em telas menores -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Conteúdo principal da página -->
    <main class="main-area">

      <!-- Barra superior com menu, título, busca e troca de tema -->
      <header class="topbar">
        <div class="topbar-left">

          <!-- Botão para abrir a sidebar em telas menores -->
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <!-- Título principal com efeito de digitação -->
          <div>
            <p class="eyebrow">Portal TI TECH</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch"
                data-typewriter-phrases="Gest&atilde;o de ativos.|Indicadores conectados.|Opera&ccedil;&atilde;o em tempo real.">Gest&atilde;o
                de ativos</span><span aria-hidden="true"></span>
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

          <!-- Alterna o tema visual da página. A lógica fica no JavaScript. -->
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <!-- Bloco de apresentação do dashboard -->
      <section class="hero-panel dashboard-hero" aria-labelledby="dashboardTitle">
        <div class="hero-content">
          <p class="section-tag">P&aacute;gina inicial operacional</p>

          <!-- Título com frases alternadas pelo typewriter.js -->
          <h2 id="dashboardTitle">
            <span class="typewriter-heading" style="--typewriter-min: 30ch" data-typewriter-loop
              data-typewriter-phrases="Controle de ativos conectado.|Invent&aacute;rio sincronizado.|Decis&otilde;es mais r&aacute;pidas.">Controle
              de ativos conectado.</span><span aria-hidden="true"></span>
          </h2>

          <!-- Texto de apoio do painel principal -->
          <p>
            Acompanhe equipamentos cadastrados, usu&aacute;rios ativos e
            categorias de ativos em uma vis&atilde;o &uacute;nica para suporte e invent&aacute;rio.
          </p>

          <!-- Atalhos principais da página inicial -->
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

      <!-- Cards com indicadores principais da página inicial -->
      <section class="metrics-grid" aria-label="Indicadores principais">

        <!-- Card/link para o portal institucional da empresa -->
        <a class="metric-card site-metric-card" href="https://www.titechsolutions.com.br/"
          aria-label="Ir para o site da TI TECH Solutions">
          <div class="metric-icon">
            <i class="bi bi-laptop"></i>
          </div>

          <div>
            <span>Site Oficial TiTechSolutions</span>
            <strong>Ir para o nosso site</strong>
            <p>Conhe&ccedil;a as solu&ccedil;&otilde;es no nosso site oficial da TI TECH.</p>
          </div>
        </a>

        <!-- Total de ativos preenchido dinamicamente pelo JavaScript -->
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-database-fill"></i>
          </div>

          <div>
            <span>Total de ativos</span>
            <strong id="totalAssetsMetric">--</strong>
          </div>
        </article>

        <!-- Total de funcionários preenchido dinamicamente pelo JavaScript -->
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

      <!-- Card do gráfico de evolução do estoque -->
      <section class="content-card chart-card asset-evolution-card" aria-labelledby="stockChartTitle">
        <div class="card-header asset-chart-header">
          <div>
            <p class="section-tag">Invent&aacute;rio</p>
            <h3 id="stockChartTitle">Evolu&ccedil;&atilde;o de ativos cadastrados</h3>
            <p class="chart-subtitle">Acompanhe todos os itens registrados no sistema por per&iacute;odo.</p>
          </div>

          <!-- Filtros de período usados para atualizar o gráfico -->
          <div class="chart-period-filter" aria-label="Filtrar per&iacute;odo do gr&aacute;fico">
            <button class="is-active" type="button" data-stock-period="semana">Semana</button>
            <button type="button" data-stock-period="hoje">Hoje</button>
            <button type="button" data-stock-period="mes">M&ecirc;s</button>
            <button type="button" data-stock-period="ano">Ano</button>
          </div>
        </div>

        <div class="asset-chart-summary" aria-label="Resumo do per&iacute;odo">
          <div>
            <span>Total cadastrado</span>
            <strong id="stockPeriodTotal">--</strong>
          </div>
          <div>
            <span>Novos registros</span>
            <strong id="stockPeriodNew">--</strong>
          </div>
          <div>
            <span>Crescimento</span>
            <strong id="stockPeriodDelta">--</strong>
          </div>
        </div>

        <!-- Área onde o Chart.js vai desenhar o gráfico -->
        <div class="chart-shell asset-evolution-shell">
          <canvas id="stockEvolutionChart" aria-label="Gr&aacute;fico evolutivo dos itens em estoque"></canvas>
        </div>
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

          <!-- Lista de categorias carregada dinamicamente pelo JavaScript -->
          <div id="categoryList" class="category-list" aria-live="polite">
            <div class="empty-state">
              <i class="bi bi-cloud-arrow-down"></i>
              <span>Carregando categorias...</span>
            </div>
          </div>
        </article>

        <!-- Card lateral com atalhos rápidos -->
        <article class="content-card product-health-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Produtos</p>
              <h3>Sa&uacute;de dos produtos</h3>
            </div>
          </div>

          <!-- Gráfico e legenda de saúde dos produtos -->
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

          <!-- Mensagem de status atualizada pelo JavaScript durante o carregamento -->
          <div id="dashboardStatus" class="dashboard-status product-health-status" role="status">
            Conectando ao banco de dados...
          </div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>