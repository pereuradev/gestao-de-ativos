<?php

declare(strict_types=1);

// Apresenta as propriedades cadastradas e seus indicadores de status.
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_propriedades", "Propriedades");

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataPropriedade(?string $value): string
{
  if (!$value) {
    return "--";
  }

  try {
    return (new DateTimeImmutable($value))
      ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
      ->format("d/m/Y H:i");
  } catch (Throwable) {
    return "--";
  }
}

$usuario = $_SESSION["usuario"];

$propriedades = [];
$totalPropriedades = 0;
$propriedadesAtivas = 0;
$propriedadesInativas = 0;
$erroBanco = "";

try {
  // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
  require __DIR__ . "/../Backend/Conexao.php";

  $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where status = 'Ativa')::int as ativas,
            count(*) filter (where status = 'Inativa')::int as inativas
          from public.propriedade_ativos
    ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalPropriedades = (int) ($resumo["total"] ?? 0);
  $propriedadesAtivas = (int) ($resumo["ativas"] ?? 0);
  $propriedadesInativas = (int) ($resumo["inativas"] ?? 0);

  $propriedadesStmt = $pdo->prepare("
        select id, nome, status, criado_em
          from public.propriedade_ativos
      order by nome asc
    ");
  $propriedadesStmt->execute();
  $propriedades = $propriedadesStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar as propriedades do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Propriedades cadastradas | TI TECH Solutions</title>
  <meta name="description" content="Visualizacao das propriedades cadastradas para ativos da TI TECH Solutions" />
  <!-- Identidade visual, tipografia e ícones usados pela página. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados e regras específicas deste fluxo. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260720-sidebar-role-accent" />
  <link rel="stylesheet" href="../css/propriedades.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <!-- Scripts da interface; os módulos compartilhados devem carregar antes do script da página. -->
  <script src="../js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="../js/propriedades.js?v=20260626-properties" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <!-- Navegação compartilhada entre as áreas autenticadas. -->
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Visualiza&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch">Propriedades cadastradas</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="propriedades.php">
            <i class="bi bi-plus-circle"></i>
            Nova propriedade
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <!-- Contextualiza a consulta das propriedades cadastradas. -->
      <section class="hero-panel compact-hero brand-partners-hero" aria-labelledby="brandsViewTitle">
        <div class="hero-content">
          <h2 id="brandsViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 21ch" data-typewriter-loop
              data-typewriter-phrases="Consulta de propriedades.|Propriedades do inventario.|Base padronizada.">Consulta
              de propriedades.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Visualize as propriedades cadastradas, filtre por status e encontre propriedades rapidamente
            para manter o invent&aacute;rio consistente.
          </p>
        </div>
      </section>

      <!-- Indicadores gerais calculados no servidor. -->
      <section class="metrics-grid" aria-label="Resumo das propriedades">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-tags-fill"></i>
          </div>

          <div>
            <span>Total de propriedades</span>
            <strong id="totalBrandsMetric"><?php echo e((string) $totalPropriedades); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-check-circle-fill"></i>
          </div>

          <div>
            <span>Ativas</span>
            <strong id="activeBrandsMetric"><?php echo e((string) $propriedadesAtivas); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-pause-circle-fill"></i>
          </div>

          <div>
            <span>Inativas</span>
            <strong id="inactiveBrandsMetric"><?php echo e((string) $propriedadesInativas); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <!-- Filtros locais e tabela de consulta sem ações de edição. -->
      <section class="content-card records-card asset-view-card brand-view-card" aria-label="Tabela de propriedades">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Visualiza&ccedil;&atilde;o</p>
            <h3>Propriedades cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="brandResultCount"><?php echo e((string) count($propriedades)); ?> registros</span>
          </div>
        </div>

        <div class="asset-filter-bar brand-filter-bar" aria-label="Filtros das propriedades">
          <div class="search-box brand-search">
            <i class="bi bi-search"></i>
            <input id="brandSearch" type="search" placeholder="Buscar propriedade" aria-label="Buscar propriedade"
              autocomplete="off" />
          </div>

          <select id="brandStatusFilter" aria-label="Filtrar propriedades por status">
            <option value="todos">Todos os status</option>
            <option value="ativa">Ativas</option>
            <option value="inativa">Inativas</option>
          </select>

          <button id="clearBrandFilters" class="filter-clear-button" type="button">
            <i class="bi bi-x-circle"></i>
            <span>Limpar</span>
          </button>
        </div>

        <div class="records-table-wrap brand-table-wrap">
          <table class="records-table brand-table">
            <thead>
              <tr>
                <th>Propriedade</th>
                <th>Status</th>
                <th>Criada em</th>
              </tr>
            </thead>
            <tbody id="brandTableBody">
              <?php foreach ($propriedades as $propriedade): ?>
                <?php
                $nome = (string) ($propriedade["nome"] ?? "");
                $status = (string) ($propriedade["status"] ?? "");
                ?>
                <tr class="registration-row brand-row" data-status="<?php echo e(strtolower($status)); ?>"
                  data-search="<?php echo e(strtolower($nome)); ?>">
                  <td data-label="Propriedade">
                    <strong><?php echo e($nome); ?></strong>
                  </td>
                  <td data-label="Status">
                    <span class="status-badge <?php echo $status === "Ativa" ? "status-active" : "status-inactive"; ?>">
                      <?php echo e($status); ?>
                    </span>
                  </td>
                  <td data-label="Criada em">
                    <?php echo e(formatarDataPropriedade((string) ($propriedade["criado_em"] ?? ""))); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="brandEmptyState" class="empty-state records-empty" <?php echo $propriedades ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhuma propriedade encontrada.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>
