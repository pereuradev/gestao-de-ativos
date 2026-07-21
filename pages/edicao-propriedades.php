<?php

declare(strict_types=1);

// Lista propriedades e prepara os dados usados pelo modal de edição e exclusão.
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("editar_propriedades", "Edicao de propriedades");

// Reutiliza um token por sessão para proteger formulários e operações de alteração contra CSRF.
if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

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
$csrfToken = e((string) $_SESSION["csrf_token"]);

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
        select id, nome, status, criado_em, atualizado_em
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
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />

  <title>Edi&ccedil;&atilde;o de propriedades | TI TECH Solutions</title>
  <meta name="description" content="Tabela para alterar ou excluir propriedades de ativos da TI TECH Solutions" />
  <!-- Identidade visual, tipografia e ícones usados pela página. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados e regras específicas deste fluxo. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260720-sidebar-role-accent" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="../css/edicao-propriedades.css?v=20260619-brand-status-actions" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <!-- Scripts da interface; os módulos compartilhados devem carregar antes do script da página. -->
  <script src="../js/animations/efeito-digitacao.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260721-js-structure" defer></script>
  <script src="../js/base-interface.js?v=20260721-js-structure" defer></script>
  <script src="../js/pages/edicao-propriedades.js?v=20260626-properties" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
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
            <p class="eyebrow">Edi&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch">Edi&ccedil;&atilde;o de
                propriedades</span><span aria-hidden="true"></span>
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

      <!-- Contextualiza a manutenção das propriedades dos ativos. -->
      <section class="hero-panel compact-hero brand-partners-hero" aria-labelledby="brandEditTitle">
        <div class="hero-content">
          <h2 id="brandEditTitle">
            <span class="typewriter-heading" style="--typewriter-min: 23ch" data-typewriter-loop
              data-typewriter-phrases="Tabela de propriedades.|Altere dados com seguranca.|Exclua registros duplicados.">Tabela
              de propriedades.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Consulte as propriedades cadastradas, altere nome ou status e remova registros que n&atilde;o devem aparecer
            no cadastro de ativos.
          </p>
        </div>
      </section>

      <!-- Indicadores gerais de propriedades ativas e inativas. -->
      <section class="metrics-grid" aria-label="Resumo das propriedades">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-building-fill"></i>
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

      <!-- Filtros e tabela usados para selecionar a propriedade. -->
      <section class="content-card records-card brand-edit-card" aria-label="Tabela de edicao de propriedades">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Propriedades cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="brandResultCount"><?php echo e((string) count($propriedades)); ?> registros</span>
            <select id="brandStatusFilter" aria-label="Filtrar propriedades por status">
              <option value="todos">Todos</option>
              <option value="ativa">Ativas</option>
              <option value="inativa">Inativas</option>
            </select>
          </div>
        </div>

        <div id="brandPageMessage" class="brand-page-message" role="status" aria-live="polite"></div>

        <div class="brand-edit-toolbar">
          <div class="search-box brand-edit-search">
            <i class="bi bi-search"></i>
            <input id="brandSearch" type="search" placeholder="Buscar propriedade" aria-label="Buscar propriedade"
              autocomplete="off" />
          </div>
        </div>

        <div class="records-table-wrap">
          <table class="records-table brand-edit-table">
            <thead>
              <tr>
                <th>Propriedade</th>
                <th>Status</th>
                <th>Criada em</th>
                <th>Atualizada em</th>
                <th>A&ccedil;&otilde;es</th>
              </tr>
            </thead>
            <tbody id="brandTableBody">
              <?php foreach ($propriedades as $propriedade): ?>
                <?php
                $id = (string) ($propriedade["id"] ?? "");
                $nome = (string) ($propriedade["nome"] ?? "");
                $status = (string) ($propriedade["status"] ?? "");
                ?>
                <tr class="registration-row brand-row" data-id="<?php echo e($id); ?>" data-name="<?php echo e($nome); ?>"
                  data-status="<?php echo e(strtolower($status)); ?>" data-status-raw="<?php echo e($status); ?>"
                  data-search="<?php echo e(strtolower($nome)); ?>">
                  <td data-label="Propriedade">
                    <strong data-brand-name><?php echo e($nome); ?></strong>
                  </td>
                  <td data-label="Status">
                    <span data-brand-status
                      class="status-badge <?php echo $status === "Ativa" ? "status-active" : "status-inactive"; ?>">
                      <?php echo e($status); ?>
                    </span>
                  </td>
                  <td data-label="Criada em">
                    <?php echo e(formatarDataPropriedade((string) ($propriedade["criado_em"] ?? ""))); ?></td>
                  <td data-label="Atualizada em" data-brand-updated>
                    <?php echo e(formatarDataPropriedade((string) ($propriedade["atualizado_em"] ?? ""))); ?></td>
                  <td data-label="A&ccedil;&otilde;es" class="brand-actions-cell">
                    <div class="row-actions">
                      <button class="table-action edit-brand-button" type="button" data-brand-action="edit">
                        <i class="bi bi-pencil-square"></i>
                        <span>Alterar</span>
                      </button>
                      <button class="table-action delete-brand-button" type="button" data-brand-action="delete">
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

        <div id="brandEmptyState" class="empty-state records-empty" <?php echo $propriedades ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhuma propriedade encontrada.</span>
        </div>
      </section>
    </main>
  </div>

  <div class="edit-modal-backdrop" id="brandEditModal" hidden>
    <!-- Modal de edição enviado ao backend com o token CSRF da sessão. -->
    <section class="edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="brandEditModalTitle">
      <div class="edit-modal-header">
        <div>
          <p class="section-tag">Alterar propriedade</p>
          <h3 id="brandEditModalTitle">Dados da propriedade</h3>
        </div>

        <button class="icon-button modal-close-button" type="button" aria-label="Fechar edicao" data-close-edit-modal>
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form id="brandEditForm" class="asset-form enhanced-asset-form" action="../Backend/atualizar-propriedade.php"
        method="post" novalidate>
        <input id="editBrandId" type="hidden" name="id" />
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

        <div class="asset-form-grid brand-form-grid">
          <label class="asset-field priority-field">
            <span>Nome da propriedade <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-building"></i>
              <input id="editBrandName" name="nome" type="text" maxlength="80" autocomplete="off" required />
            </div>
          </label>

          <label class="asset-field">
            <span>Status <strong>*</strong></span>
            <div class="input-shell select-shell">
              <i class="bi bi-toggle-on"></i>
              <select id="editBrandStatus" name="status" required>
                <option value="Ativa">Ativa</option>
                <option value="Inativa">Inativa</option>
              </select>
            </div>
          </label>
        </div>

        <div id="brandEditMessage" class="form-message" role="status" aria-live="polite"></div>

        <div class="asset-form-actions enhanced-form-actions">
          <button class="form-action-button danger-button" type="button" data-close-edit-modal>
            <i class="bi bi-x-circle"></i>
            <span>Cancelar</span>
          </button>

          <button id="saveBrandButton" class="form-action-button success-button" type="submit">
            <i class="bi bi-check-circle"></i>
            <span>Salvar altera&ccedil;&otilde;es</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</body>

</html>
