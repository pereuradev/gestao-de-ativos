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

  $totalStmt = $pdo->prepare("select count(*)::int from public.locais");
  $totalStmt->execute();
  $totalLocais = (int) $totalStmt->fetchColumn();

  $ativosStmt = $pdo->prepare("
        select count(*)::int
          from public.locais
         where status = :status
    ");
  $ativosStmt->execute([":status" => "Ativo"]);
  $locaisAtivos = (int) $ativosStmt->fetchColumn();

  $inativosStmt = $pdo->prepare("
        select count(*)::int
          from public.locais
         where status = :status
    ");
  $inativosStmt->execute([":status" => "Inativo"]);
  $locaisInativos = (int) $inativosStmt->fetchColumn();

  $locaisStmt = $pdo->prepare("
        select id, nome, endereco, status, criado_em
          from public.locais
      order by criado_em desc, nome asc
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

  <title>Cadastro de localiza&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description"
    content="Cadastro de localiza&ccedil;&otilde;es para organizar os ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260630-clean-form-card" />
  <link rel="stylesheet" href="css/locais.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260630-reduced-motion" defer></script>
  <script src="js/locais.js?v=20260624-common-ui" defer></script>
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
<?php if ($sidebarIsAdmin): ?>
        <a class="nav-link" href="funcionarios.php">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
        </a>
<?php else: ?>
        <span class="nav-link nav-link-disabled" aria-disabled="true" title="Apenas administradores podem acessar funcionários">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
        </span>
<?php endif; ?>

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
        <div class="nav-group open" data-nav-group>
          <button class="nav-link nav-toggle active" type="button" aria-expanded="true"
            aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
            <a href="propriedades.php">Propriedades</a>
<?php if ($sidebarIsAdmin): ?>
            <a href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
<?php endif; ?>
            <a class="active-submenu" href="locais.php">Localiza&ccedil;&otilde;es</a>
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
            <p class="eyebrow">Cadastros</p>
            <h1>
              <span style="--typewriter-min: 27ch">Cadastro de localiza&ccedil;&otilde;es</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="locais-visualizacao.php">
            <i class="bi bi-table"></i>
            Visualizar localiza&ccedil;&otilde;es
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero locations-hero" aria-labelledby="locationsRegistrationTitle">
        <div class="hero-content">
          <h2 id="locationsRegistrationTitle">
            <span class="typewriter-heading" style="--typewriter-min: 25ch" data-typewriter-loop
              data-typewriter-phrases="Localiza&ccedil;&otilde;es organizadas.|Endere&ccedil;os f&aacute;ceis de encontrar.|Controle por setor e unidade.">Localiza&ccedil;&otilde;es
              organizadas.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Registre unidades, setores, salas e pontos de armazenamento para vincular cada ativo
            ao local correto no sistema.
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

      <section class="locations-layout" aria-label="Cadastro de localizacoes">
        <article class="content-card asset-form-card asset-form-card-enhanced location-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formul&aacute;rio</p>
              <h3>Cadastrar localiza&ccedil;&atilde;o</h3>
              <span class="card-subtitle">Use nomes claros como unidade, setor, sala ou arm&aacute;rio. O cadastro
                bloqueia duplicidades por nome.</span>
            </div>

          </div>

          <form id="locationForm" class="asset-form enhanced-asset-form" action="Backend/cadastrar-local.php"
            method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid location-form-grid">
              <label class="asset-field priority-field">
                <span>Nome do local <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-geo-alt"></i>
                  <input name="nome" type="text" placeholder="Ex: Matriz - Estoque TI" maxlength="100"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-toggle-on"></i>
                  <select name="status" required>
                    <option value="Ativo" selected>Ativo</option>
                    <option value="Inativo">Inativo</option>
                  </select>
                </div>
              </label>

              <label class="asset-field wide-field">
                <span>Endere&ccedil;o ou refer&ecirc;ncia</span>
                <div class="input-shell">
                  <i class="bi bi-signpost-split"></i>
                  <input name="endereco" type="text" placeholder="Ex: 2&ordm; andar, sala 204, rack A" maxlength="160"
                    autocomplete="off" />
                </div>
              </label>
            </div>

            <div id="locationFormMessage" class="form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="locationSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-circle"></i>
                <span>Cadastrar local</span>
              </button>
            </div>
          </form>
        </article>
      </section>
    </main>
  </div>
</body>

</html>
