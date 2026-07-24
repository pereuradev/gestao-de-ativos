<?php

declare(strict_types=1);

// Lista categorias e prepara os dados usados pelo modal de edicao e exclusao.
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("editar_categorias", "Edicao de categorias");

if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

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

$csrfToken = e((string) $_SESSION["csrf_token"]);
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
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />

  <title>Edi&ccedil;&atilde;o de categorias | TI TECH Solutions</title>
  <meta name="description" content="Tabela para alterar ou excluir categorias de ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260721-categorias" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="../css/categorias.css?v=20260721-categorias" />
  <link rel="stylesheet" href="../css/edicao-categorias.css?v=20260721-categorias" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="../js/animations/efeito-digitacao.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260721-js-structure" defer></script>
  <script src="../js/base-interface.js?v=20260721-categorias" defer></script>
  <script src="../js/pages/edicao-categorias.js?v=20260721-categorias" defer></script>
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
            <p class="eyebrow">Edi&ccedil;&atilde;o</p>
            <h1>Edi&ccedil;&atilde;o de categorias</h1>
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

      <section class="hero-panel compact-hero brand-partners-hero" aria-labelledby="categoryEditTitle">
        <div class="hero-content">
          <h2 id="categoryEditTitle">
            <span class="typewriter-heading" style="--typewriter-min: 23ch" data-typewriter-loop
              data-typewriter-phrases="Tabela de categorias.|Ajuste classificacoes.|Remova duplicidades.">Tabela de
              categorias.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Altere nome e descricao das categorias usadas pelos ativos. Exclusoes ficam bloqueadas
            quando existe ativo vinculado.
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

      <section class="content-card records-card category-edit-card" aria-label="Tabela de edicao de categorias">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Categorias cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="categoryResultCount"><?php echo e((string) count($categorias)); ?> registros</span>
          </div>
        </div>

        <div id="categoryPageMessage" class="category-page-message" role="status" aria-live="polite"></div>

        <div class="category-edit-toolbar">
          <div class="search-box category-edit-search">
            <i class="bi bi-search"></i>
            <input id="categorySearch" type="search" placeholder="Buscar categoria" aria-label="Buscar categoria"
              autocomplete="off" />
          </div>
        </div>

        <div class="records-table-wrap">
          <table class="records-table category-edit-table">
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Descricao</th>
                <th>Ativos</th>
                <th>Criada em</th>
                <th>Atualizada em</th>
                <th>A&ccedil;&otilde;es</th>
              </tr>
            </thead>
            <tbody id="categoryTableBody">
              <?php foreach ($categorias as $categoria): ?>
                <?php
                $id = (string) ($categoria["id"] ?? "");
                $nome = (string) ($categoria["nome"] ?? "");
                $descricao = (string) ($categoria["descricao"] ?? "");
                $totalAtivos = (int) ($categoria["total_ativos"] ?? 0);
                ?>
                <tr class="registration-row category-row" data-id="<?php echo e($id); ?>"
                  data-name="<?php echo e($nome); ?>" data-description="<?php echo e($descricao); ?>"
                  data-assets="<?php echo e((string) $totalAtivos); ?>"
                  data-search="<?php echo e(strtolower($nome . " " . $descricao)); ?>">
                  <td data-label="Categoria">
                    <strong data-category-name><?php echo e($nome); ?></strong>
                  </td>
                  <td data-label="Descricao">
                    <span class="category-description" data-category-description>
                      <?php echo e($descricao !== "" ? $descricao : "Sem descricao"); ?>
                    </span>
                  </td>
                  <td data-label="Ativos">
                    <span class="category-assets-count"><?php echo e((string) $totalAtivos); ?></span>
                  </td>
                  <td data-label="Criada em">
                    <?php echo e(formatarDataCategoria((string) ($categoria["criado_em"] ?? ""))); ?>
                  </td>
                  <td data-label="Atualizada em" data-category-updated>
                    <?php echo e(formatarDataCategoria((string) ($categoria["atualizado_em"] ?? ""))); ?>
                  </td>
                  <td data-label="A&ccedil;&otilde;es" class="category-actions-cell">
                    <div class="row-actions">
                      <button class="table-action edit-category-button" type="button" data-category-action="edit">
                        <i class="bi bi-pencil-square"></i>
                        <span>Alterar</span>
                      </button>
                      <button class="table-action delete-category-button" type="button" data-category-action="delete">
                        <i class="bi bi-trash3"></i>
                        <span>Excluir</span>
                      </button>
                    </div>
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

  <div class="edit-modal-backdrop" id="categoryEditModal" hidden>
    <section class="edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="categoryEditModalTitle">
      <div class="edit-modal-header">
        <div>
          <p class="section-tag">Alterar categoria</p>
          <h3 id="categoryEditModalTitle">Dados da categoria</h3>
        </div>

        <button class="icon-button modal-close-button" type="button" aria-label="Fechar edicao" data-close-edit-modal>
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form id="categoryEditForm" class="asset-form enhanced-asset-form" action="../Backend/atualizar-categoria.php"
        method="post" novalidate>
        <input id="editCategoryId" type="hidden" name="id" />
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

        <div class="asset-form-grid category-form-grid">
          <label class="asset-field priority-field">
            <span>Nome da categoria <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-diagram-3"></i>
              <input id="editCategoryName" name="nome" type="text" maxlength="80" autocomplete="off" required />
            </div>
          </label>

          <label class="asset-field">
            <span>Descricao</span>
            <div class="input-shell">
              <i class="bi bi-card-text"></i>
              <input id="editCategoryDescription" name="descricao" type="text" maxlength="240" autocomplete="off" />
            </div>
          </label>
        </div>

        <div id="categoryEditMessage" class="form-message" role="status" aria-live="polite"></div>

        <div class="asset-form-actions enhanced-form-actions">
          <button class="form-action-button danger-button" type="button" data-close-edit-modal>
            <i class="bi bi-x-circle"></i>
            <span>Cancelar</span>
          </button>

          <button id="saveCategoryButton" class="form-action-button success-button" type="submit">
            <i class="bi bi-check-circle"></i>
            <span>Salvar altera&ccedil;&otilde;es</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</body>

</html>
