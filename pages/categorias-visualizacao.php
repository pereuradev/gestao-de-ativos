<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_categorias", "Categorias");

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataCategoria(?string $value): string
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

$categorias = [];
$totalCategorias = 0;
$categoriasVinculadas = 0;
$categoriasSemAtivos = 0;
$erroBanco = "";

try {
  require __DIR__ . "/../Backend/Conexao.php";

  $categoriasStmt = $pdo->prepare("
      select
          c.id,
          c.nome,
          c.descricao,
          c.criado_em,
          c.atualizado_em,
          count(a.id)::int as total_ativos
        from public.categorias_ativos c
   left join public.ativos a on a.categoria_id = c.id
    group by c.id, c.nome, c.descricao, c.criado_em, c.atualizado_em
    order by c.nome asc
    ");
  $categoriasStmt->execute();
  $categorias = $categoriasStmt->fetchAll();

  $totalCategorias = count($categorias);
  foreach ($categorias as $categoria) {
    if ((int) ($categoria["total_ativos"] ?? 0) > 0) {
      $categoriasVinculadas++;
    } else {
      $categoriasSemAtivos++;
    }
  }
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar as categorias do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Categorias cadastradas | TI TECH Solutions</title>
  <meta name="description" content="Visualizacao das categorias cadastradas para ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260721-categorias" />
  <link rel="stylesheet" href="../css/categorias.css?v=20260721-categorias" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="../js/animations/efeito-digitacao.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260721-js-structure" defer></script>
  <script src="../js/base-interface.js?v=20260721-categorias" defer></script>
  <script src="../js/pages/gerenciamento-categorias.js?v=20260721-categorias" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Visualiza&ccedil;&atilde;o</p>
            <h1>Categorias cadastradas</h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="categorias.php">
            <i class="bi bi-plus-circle"></i>
            Nova categoria
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero brand-partners-hero" aria-labelledby="categoriesViewTitle">
        <div class="hero-content">
          <h2 id="categoriesViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 21ch" data-typewriter-loop
              data-typewriter-phrases="Consulta de categorias.|Base de classificacao.|Ativos bem organizados.">Consulta
              de categorias.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Visualize categorias cadastradas, encontre rapidamente uma classificacao e acompanhe
            quantos ativos usam cada categoria.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo das categorias">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-diagram-3-fill"></i>
          </div>

          <div>
            <span>Total de categorias</span>
            <strong id="totalCategoriesMetric"><?php echo e((string) $totalCategorias); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-link-45deg"></i>
          </div>

          <div>
            <span>Com ativos</span>
            <strong id="linkedCategoriesMetric"><?php echo e((string) $categoriasVinculadas); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-inboxes-fill"></i>
          </div>

          <div>
            <span>Sem ativos</span>
            <strong id="unlinkedCategoriesMetric"><?php echo e((string) $categoriasSemAtivos); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="content-card records-card category-view-card" aria-label="Tabela de categorias">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Visualiza&ccedil;&atilde;o</p>
            <h3>Categorias cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="categoryResultCount"><?php echo e((string) count($categorias)); ?> registros</span>
          </div>
        </div>

        <div class="category-filter-bar" aria-label="Filtros das categorias">
          <div class="search-box category-search">
            <i class="bi bi-search"></i>
            <input id="categorySearch" type="search" placeholder="Buscar categoria" aria-label="Buscar categoria"
              autocomplete="off" />
          </div>

          <button id="clearCategoryFilters" class="filter-clear-button" type="button">
            <i class="bi bi-x-circle"></i>
            <span>Limpar</span>
          </button>
        </div>

        <div class="records-table-wrap category-table-wrap">
          <table class="records-table category-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Descricao</th>
                <th>Ativos</th>
                <th>Criada em</th>
              </tr>
            </thead>
            <tbody id="categoryTableBody">
              <?php foreach ($categorias as $categoria): ?>
                <?php
                $nome = (string) ($categoria["nome"] ?? "");
                $descricao = (string) ($categoria["descricao"] ?? "");
                $totalAtivos = (int) ($categoria["total_ativos"] ?? 0);
                ?>
                <tr class="registration-row category-row"
                  data-search="<?php echo e(strtolower($nome . " " . $descricao)); ?>">
                  <td data-label="Categoria">
                    <strong><?php echo e($nome); ?></strong>
                  </td>
                  <td data-label="Descricao">
                    <span class="category-description"><?php echo e($descricao !== "" ? $descricao : "Sem descricao"); ?></span>
                  </td>
                  <td data-label="Ativos">
                    <span class="category-assets-count"><?php echo e((string) $totalAtivos); ?></span>
                  </td>
                  <td data-label="Criada em">
                    <?php echo e(formatarDataCategoria((string) ($categoria["criado_em"] ?? ""))); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="categoryEmptyState" class="empty-state records-empty" <?php echo $categorias ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhuma categoria encontrada.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>
