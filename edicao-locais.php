<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

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
  $pdo->exec("
        create table if not exists public.locais (
            id uuid primary key default gen_random_uuid(),
            nome text not null,
            endereco text,
            status text not null default 'Ativo',
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

  $pdo->exec("alter table public.locais add column if not exists endereco text");
  $pdo->exec("alter table public.locais add column if not exists status text not null default 'Ativo'");
  $pdo->exec("alter table public.locais add column if not exists criado_em timestamptz not null default now()");
  $pdo->exec("alter table public.locais add column if not exists atualizado_em timestamptz not null default now()");
}

$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
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
$csrfToken = e((string) $_SESSION["csrf_token"]);

$locais = [];
$totalLocais = 0;
$locaisAtivos = 0;
$locaisInativos = 0;
$erroBanco = "";

try {
  require __DIR__ . "/Backend/Conexao.php";

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
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-sidebar-resize" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="css/locais.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/edicao-locais.css?v=20260625-location-edit" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260630-font-size" defer></script>
  <script src="js/edicao-locais.js?v=20260625-location-edit" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <a href="https://www.titechsolutions.com.br/" class="brand-area" aria-label="Acessar site da TI TECH Solutions">
          <img class="brand-logo" src="assets/logo-branca.png" alt="TI TECH Solutions" />
        </a>

        <button class="icon-button sidebar-close" id="closeSidebar" type="button" aria-label="Fechar menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <nav class="sidebar-nav" aria-label="Menu principal">
        <a class="nav-link" href="pagina-inicial.php">
          <i class="bi bi-house-door-fill"></i>
          <span>P&aacute;gina Inicial</span>
        </a>
        <a class="nav-link" href="dashboard.php">
          <i class="bi bi-bar-chart-fill"></i>
          <span>Dashboard</span>
        </a>

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
        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
            <a href="propriedades.php">Propriedades</a>
            <a href="locais.php">Localiza&ccedil;&otilde;es</a>
          </div>
        </div>

        <div class="nav-group open" data-nav-group>
          <button class="nav-link nav-toggle active" type="button" aria-expanded="true" aria-controls="editingSubmenu">
            <i class="bi bi-pencil-square"></i>
            <span>Edi&ccedil;&atilde;o</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="editingSubmenu">
            <a href="edicao-ativos.php">Ativos</a>
            <a href="edicao-marcas.php">Marcas</a>
            <a href="edicao-propriedades.php">Propriedades</a>
            <a class="active-submenu" href="edicao-locais.php">Localiza&ccedil;&otilde;es</a>
          </div>
        </div>

        <a class="nav-link" href="ativos.php">
          <i class="bi bi-hdd-network-fill"></i>
          <span>Ativos</span>
        </a>

        <a class="nav-link" href="configuracoes.php">
          <i class="bi bi-gear-fill"></i>
          <span>Configura&ccedil;&otilde;es</span>
        </a>
      </nav>

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
        <a href="Backend/logout.php" class="logout-button">
          <i class="bi bi-box-arrow-left"></i>
          <span>Sair do sistema</span>
        </a>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

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

      <form id="locationEditForm" class="asset-form enhanced-asset-form" action="Backend/atualizar-local.php"
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