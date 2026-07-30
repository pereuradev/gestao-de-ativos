<?php

declare(strict_types=1);

header("Content-Type: text/html; charset=UTF-8");
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_dashboard", "Dashboard");
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard | TI TECH Solutions</title>
  <meta name="description"
    content="Dashboard anal&iacute;tico de ativos por tipo, marca, localiza&ccedil;&atilde;o e per&iacute;odo" />

  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://cdn.jsdelivr.net" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap"
    rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260727-sidebar-layout" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/dashboard-produtos.css?v=20260728-evolucao-ativos" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260721-js-structure" defer></script>
  <script src="../js/base-interface.js?v=20260724-custom-accent" defer></script>
  <script src="../js/pages/dashboard-produtos.js?v=20260728-evolucao-ativos" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading dashboard-products-page" data-accent="teal">
  <div class="app-shell">
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <main class="main-area app-main">
      <header class="topbar dashboard-topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Invent&aacute;rio</p>
            <h1>Dashboard de produtos</h1>
          </div>
        </div>

        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button" aria-label="Selecionar tema da interface">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <p id="dashboardStatusText" class="dashboard-status-text" role="status" aria-live="polite">
        Carregando dados do banco.
      </p>




      <section class="dashboard-panel asset-evolution-card dashboard-asset-evolution"
        aria-labelledby="assetEvolutionTitle">
        <div class="panel-heading asset-chart-header">
          <div>
            <span class="eyebrow">Evolu&ccedil;&atilde;o dos ativos</span>
            <h2 id="assetEvolutionTitle">Ativos cadastrados no tempo</h2>
            <p class="chart-subtitle" id="assetEvolutionSubtitle">
              Acompanhe o acumulado do invent&aacute;rio e os novos cadastros por per&iacute;odo.
            </p>
          </div>

          <div class="chart-period-filter" aria-label="Per&iacute;odo da evolu&ccedil;&atilde;o de ativos">
            <button class="is-active" type="button" data-stock-period="semana" aria-pressed="true">
              Semanalmente
            </button>
            <button type="button" data-stock-period="hoje" aria-pressed="false">
              Diariamente
            </button>
            <button type="button" data-stock-period="mes" aria-pressed="false">
              Mensalmente
            </button>
            <button type="button" data-stock-period="ano" aria-pressed="false">
              Anualmente
            </button>
          </div>
        </div>

        <div class="asset-chart-summary" aria-live="polite">
          <div>
            <span>Total atual</span>
            <strong id="stockPeriodTotal">--</strong>
          </div>
          <div>
            <span>Novos no per&iacute;odo</span>
            <strong id="stockPeriodNew">--</strong>
          </div>
          <div>
            <span>Crescimento</span>
            <strong id="stockPeriodDelta">--</strong>
          </div>
        </div>

        <div class="chart-shell asset-evolution-shell">
          <canvas id="stockEvolutionChart" aria-label="Gr&aacute;fico de evolu&ccedil;&atilde;o dos ativos cadastrados"
            role="img"></canvas>
        </div>
      </section>
      <section class="dashboard-panel chart-control-panel" aria-label="Controles do dashboard">
        <div class="dashboard-control-group">
          <label for="categoryFilter">Tipo de produto</label>
          <select id="categoryFilter">
            <option value="todos">Todos os tipos</option>
          </select>
        </div>

        <div class="dashboard-control-group">
          <label for="brandFilter">Marca</label>
          <select id="brandFilter">
            <option value="todos">Todas as marcas</option>
          </select>
        </div>

        <div class="dashboard-control-group">
          <label for="locationFilter">Localiza&ccedil;&atilde;o</label>
          <select id="locationFilter">
            <option value="todos">Todos os locais</option>
          </select>
        </div>

        <div class="dashboard-control-group">
          <label for="metricFilter">Dados do gr&aacute;fico</label>
          <select id="metricFilter">
            <option value="categorias">Quantidade por tipo</option>
            <option value="status">Quantidade por status</option>
            <option value="marcas">Quantidade por marca</option>
            <option value="locais">Quantidade por localiza&ccedil;&atilde;o</option>

          </select>
        </div>

        <div class="dashboard-control-group">
          <label for="chartTypeFilter">Tipo de gr&aacute;fico</label>
          <select id="chartTypeFilter">
            <option value="bar">Barras</option>
            <option value="pie">Pizza</option>
            <option value="doughnut">Rosca</option>
            <option value="line">Linhas</option>
            <option value="polarArea">Polar</option>
          </select>
        </div>
        <button class="refresh-dashboard-button" id="refreshDashboard" type="button">
          <i class="bi bi-arrow-clockwise"></i>
          Atualizar
        </button>
      </section>

      <section class="dashboard-grid-main">
        <article class="dashboard-panel main-chart-card">
          <div class="panel-heading">
            <div>
              <span class="eyebrow">Gr&aacute;fico principal</span>
              <h2 id="mainChartTitle">Quantidade por tipo</h2>
              <p id="mainChartDescription">
                Distribui&ccedil;&atilde;o dos ativos cadastrados por categoria de produto.
              </p>
            </div>

            <div class="chart-total-pill">
              <span id="chartTotalLabel">Ativos no invent&aacute;rio</span>
              <strong id="chartTotalMetric">--</strong>
            </div>
          </div>

          <div class="chart-wrapper">
            <canvas id="productsChart" aria-label="Gr&aacute;fico do dashboard de produtos" role="img"></canvas>
          </div>
        </article>

        <aside class="dashboard-panel details-card">
          <div class="panel-heading compact">
            <div>
              <span class="eyebrow">Leitura r&aacute;pida</span>
              <h2>Dados exibidos</h2>
            </div>
          </div>

          <div id="dashboardRanking" class="ranking-list" aria-live="polite">
            <div class="ranking-empty">Carregando informa&ccedil;&otilde;es...</div>
          </div>
        </aside>
      </section>
    </main>
  </div>
</body>

</html>