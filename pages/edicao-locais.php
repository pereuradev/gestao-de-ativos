<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("editar_locais", "Edicao de localizacoes");

// Reutiliza um token por sessão para proteger formulários e operações de alteração contra CSRF.
if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataLocal(?string $value): string
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

function garantirTabelaLocais(PDO $pdo): void
{
  // Estrutura criada por migration. Mantido para compatibilidade.
}

$usuario = $_SESSION["usuario"];
$csrfToken = e((string) $_SESSION["csrf_token"]);

$locais = [];
$totalLocais = 0;
$locaisAtivos = 0;
$locaisInativos = 0;
$erroBanco = "";

try {
  // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
  require __DIR__ . "/../Backend/Conexao.php";

  garantirTabelaLocais($pdo);

  $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where status = 'Ativo')::int as ativos,
            count(*) filter (where status = 'Inativo')::int as inativos
          from public.locais
    ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalLocais = (int) ($resumo["total"] ?? 0);
  $locaisAtivos = (int) ($resumo["ativos"] ?? 0);
  $locaisInativos = (int) ($resumo["inativos"] ?? 0);

  $locaisStmt = $pdo->prepare("
        select id, nome, endereco, status, criado_em, atualizado_em
          from public.locais
      order by nome asc
    ");
  $locaisStmt->execute();
  $locais = $locaisStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os locais do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />

  <title>Edi&ccedil;&atilde;o de localiza&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description" content="Tabela para alterar ou excluir localizacoes de ativos da TI TECH Solutions" />
  <!-- Identidade visual, tipografia e ícones usados pela página. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados e regras específicas deste fluxo. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260720-sidebar-role-accent" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="../css/locais.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="../css/edicao-locais.css?v=20260625-location-edit" />
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
  <script src="../js/pages/edicao-locais.js?v=20260625-location-edit" defer></script>
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
              <span class="typewriter-heading" style="--typewriter-min: 26ch">Edi&ccedil;&atilde;o de
                localiza&ccedil;&otilde;es</span><span aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="locais.php">
            <i class="bi bi-plus-circle"></i>
            Nova localiza&ccedil;&atilde;o
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <!-- Contextualiza a manutenção das localizações do inventário. -->
      <section class="hero-panel compact-hero locations-hero" aria-labelledby="locationEditTitle">
        <div class="hero-content">
          <h2 id="locationEditTitle">
            <span class="typewriter-heading" style="--typewriter-min: 26ch" data-typewriter-loop
              data-typewriter-phrases="Tabela de localiza&ccedil;&otilde;es.|Altere endere&ccedil;os com seguran&ccedil;a.|Organize setores e unidades.">Tabela
              de localiza&ccedil;&otilde;es.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Consulte as localiza&ccedil;&otilde;es cadastradas, altere nome, refer&ecirc;ncia ou status
            e remova registros que n&atilde;o devem aparecer no cadastro de ativos.
          </p>
        </div>
      </section>

      <!-- Indicadores gerais de locais ativos e inativos. -->
      <section class="metrics-grid" aria-label="Resumo das localizacoes">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-geo-alt-fill"></i>
          </div>

          <div>
            <span>Total de locais</span>
            <strong id="totalLocationsMetric"><?php echo e((string) $totalLocais); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-check-circle-fill"></i>
          </div>

          <div>
            <span>Ativos</span>
            <strong id="activeLocationsMetric"><?php echo e((string) $locaisAtivos); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-pause-circle-fill"></i>
          </div>

          <div>
            <span>Inativos</span>
            <strong id="inactiveLocationsMetric"><?php echo e((string) $locaisInativos); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <!-- Filtros e tabela usados para selecionar o local. -->
      <section class="content-card records-card location-edit-card" aria-label="Tabela de edicao de localizacoes">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Localiza&ccedil;&otilde;es cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="locationResultCount"><?php echo e((string) count($locais)); ?> registros</span>
            <select id="locationStatusFilter" aria-label="Filtrar localizacoes por status">
              <option value="todos">Todos</option>
              <option value="ativo">Ativos</option>
              <option value="inativo">Inativos</option>
            </select>
          </div>
        </div>

        <div id="locationPageMessage" class="location-page-message" role="status" aria-live="polite"></div>

        <div class="location-edit-toolbar">
          <div class="search-box location-edit-search">
            <i class="bi bi-search"></i>
            <input id="locationSearch" type="search" placeholder="Buscar local ou refer&ecirc;ncia"
              aria-label="Buscar local ou refer&ecirc;ncia" autocomplete="off" />
          </div>
        </div>

        <div class="records-table-wrap">
          <table class="records-table location-edit-table">
            <thead>
              <tr>
                <th>Local</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Atualizado em</th>
                <th>A&ccedil;&otilde;es</th>
              </tr>
            </thead>
            <tbody id="locationTableBody">
              <?php foreach ($locais as $local): ?>
                <?php
                $id = (string) ($local["id"] ?? "");
                $nome = (string) ($local["nome"] ?? "");
                $endereco = (string) ($local["endereco"] ?? "");
                $status = (string) ($local["status"] ?? "Ativo");
                $search = strtolower(trim($nome . " " . $endereco));
                ?>
                <tr class="registration-row location-row" data-id="<?php echo e($id); ?>"
                  data-name="<?php echo e($nome); ?>" data-address="<?php echo e($endereco); ?>"
                  data-status="<?php echo e(strtolower($status)); ?>" data-status-raw="<?php echo e($status); ?>"
                  data-search="<?php echo e($search); ?>">
                  <td data-label="Local">
                    <strong data-location-name><?php echo e($nome); ?></strong>
                    <span class="location-address" data-location-address>
                      <?php echo e($endereco !== "" ? $endereco : "Sem referencia informada"); ?>
                    </span>
                  </td>
                  <td data-label="Status">
                    <span data-location-status
                      class="status-badge <?php echo $status === "Ativo" ? "status-active" : "status-inactive"; ?>">
                      <?php echo e($status); ?>
                    </span>
                  </td>
                  <td data-label="Criado em"><?php echo e(formatarDataLocal((string) ($local["criado_em"] ?? ""))); ?>
                  </td>
                  <td data-label="Atualizado em" data-location-updated>
                    <?php echo e(formatarDataLocal((string) ($local["atualizado_em"] ?? ""))); ?></td>
                  <td data-label="A&ccedil;&otilde;es" class="location-actions-cell">
                    <div class="row-actions">
                      <button class="table-action edit-location-button" type="button" data-location-action="edit">
                        <i class="bi bi-pencil-square"></i>
                        <span>Alterar</span>
                      </button>
                      <button class="table-action delete-location-button" type="button" data-location-action="delete">
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

        <div id="locationEmptyState" class="empty-state records-empty" <?php echo $locais ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhuma localiza&ccedil;&atilde;o encontrada.</span>
        </div>
      </section>
    </main>
  </div>

  <div class="edit-modal-backdrop" id="locationEditModal" hidden>
    <!-- Modal de edição enviado ao backend com o token CSRF da sessão. -->
    <section class="edit-modal-card" role="dialog" aria-modal="true" aria-labelledby="locationEditModalTitle">
      <div class="edit-modal-header">
        <div>
          <p class="section-tag">Alterar localiza&ccedil;&atilde;o</p>
          <h3 id="locationEditModalTitle">Dados do local</h3>
        </div>

        <button class="icon-button modal-close-button" type="button" aria-label="Fechar edicao" data-close-edit-modal>
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form id="locationEditForm" class="asset-form enhanced-asset-form" action="../Backend/atualizar-local.php"
        method="post" novalidate>
        <input id="editLocationId" type="hidden" name="id" />
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

        <div class="asset-form-grid location-form-grid">
          <label class="asset-field priority-field">
            <span>Nome do local <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-geo-alt"></i>
              <input id="editLocationName" name="nome" type="text" maxlength="100" autocomplete="off" required />
            </div>
          </label>

          <label class="asset-field">
            <span>Status <strong>*</strong></span>
            <div class="input-shell select-shell">
              <i class="bi bi-toggle-on"></i>
              <select id="editLocationStatus" name="status" required>
                <option value="Ativo">Ativo</option>
                <option value="Inativo">Inativo</option>
              </select>
            </div>
          </label>

          <label class="asset-field wide-field">
            <span>Endere&ccedil;o ou refer&ecirc;ncia</span>
            <div class="input-shell">
              <i class="bi bi-signpost-split"></i>
              <input id="editLocationAddress" name="endereco" type="text" maxlength="160" autocomplete="off" />
            </div>
          </label>
        </div>

        <div id="locationEditMessage" class="form-message" role="status" aria-live="polite"></div>

        <div class="asset-form-actions enhanced-form-actions">
          <button class="form-action-button danger-button" type="button" data-close-edit-modal>
            <i class="bi bi-x-circle"></i>
            <span>Cancelar</span>
          </button>

          <button id="saveLocationButton" class="form-action-button success-button" type="submit">
            <i class="bi bi-check-circle"></i>
            <span>Salvar altera&ccedil;&otilde;es</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</body>

</html>
