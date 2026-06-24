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

function formatarDataMarca(?string $value): string
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
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
$csrfToken = e((string) $_SESSION["csrf_token"]);

$marcas = [];
$totalMarcas = 0;
$marcasAtivas = 0;
$marcasInativas = 0;
$erroBanco = "";

try {
  require __DIR__ . "/Backend/Conexao.php";

  $pdo->exec("
        create table if not exists public.marcas_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

  $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

  $totalStmt = $pdo->prepare("select count(*)::int from public.marcas_ativos");
  $totalStmt->execute();
  $totalMarcas = (int) $totalStmt->fetchColumn();

  $ativasStmt = $pdo->prepare("
        select count(*)::int
          from public.marcas_ativos
         where status = :status
    ");
  $ativasStmt->execute([":status" => "Ativa"]);
  $marcasAtivas = (int) $ativasStmt->fetchColumn();

  $inativasStmt = $pdo->prepare("
        select count(*)::int
          from public.marcas_ativos
         where status = :status
    ");
  $inativasStmt->execute([":status" => "Inativa"]);
  $marcasInativas = (int) $inativasStmt->fetchColumn();

  $marcasStmt = $pdo->prepare("
        select id, nome, status, criado_em, atualizado_em
          from public.marcas_ativos
      order by nome asc
    ");
  $marcasStmt->execute();
  $marcas = $marcasStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar as marcas do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de marcas | TI TECH Solutions</title>
  <meta name="description" content="Cadastro de marcas para padronizar o cadastro de ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="css/marcas.css?v=20260619-align" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-common-ui" defer></script>
  <script src="js/marcas.js?v=20260624-common-ui" defer></script>
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
          <i class="bi bi-speedometer2"></i>
          <span>P&aacute;gina Inicial</span>
        </a>

        <a class="nav-link" href="funcionarios.php">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
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
            <a class="active-submenu" href="marcas.php">Marcas</a>
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

        <a class="nav-link" href="configuracoes.php">
          <i class="bi bi-gear-fill"></i>
          <span>Configura&ccedil;&otilde;es</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-summary">
          <span><?php echo $tipoUsuario; ?></span>
          <strong title="<?php echo $nomeUsuario; ?>"><?php echo $nomeUsuario; ?></strong>
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
              <span style="--typewriter-min: 18ch">Cadastro de marcas</span><span 
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-moon-stars-fill"></i>
            <span>Modo escuro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero" aria-labelledby="brandsRegistrationTitle">
        <div class="hero-content">
          <h2 id="brandsRegistrationTitle">
            <span class="typewriter-heading" style="--typewriter-min: 22ch" data-typewriter-loop
              data-typewriter-phrases="Marcas padronizadas.|Menos erro no cadastro.|Sele&ccedil;&atilde;o direta nos ativos.">Marcas
              padronizadas.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Cadastre fabricantes e marcas uma vez para selecionar depois no cadastro de ativos,
            mantendo os nomes consistentes no banco.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo das marcas">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-building-fill"></i>
          </div>

          <div>
            <span>Total de marcas</span>
            <strong id="totalBrandsMetric"><?php echo e((string) $totalMarcas); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-check-circle-fill"></i>
          </div>

          <div>
            <span>Ativas</span>
            <strong id="activeBrandsMetric"><?php echo e((string) $marcasAtivas); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-pause-circle-fill"></i>
          </div>

          <div>
            <span>Inativas</span>
            <strong id="inactiveBrandsMetric"><?php echo e((string) $marcasInativas); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="brands-layout" aria-label="Cadastro e lista de marcas">
        <article class="content-card asset-form-card asset-form-card-enhanced brand-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formul&aacute;rio</p>
              <h3>Cadastrar marca</h3>
              <span class="card-subtitle">Use o nome oficial ou mais conhecido da marca. O cadastro bloqueia
                duplicidades por nome.</span>
            </div>

            <div class="form-badge" aria-label="Padronizacao">
              <i class="bi bi-shield-check"></i>
              Padronizado
            </div>
          </div>

          <form id="brandForm" class="asset-form enhanced-asset-form" action="Backend/cadastrar-marca.php" method="post"
            novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid brand-form-grid">
              <label class="asset-field priority-field">
                <span>Nome da marca <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-building"></i>
                  <input name="nome" type="text" placeholder="Ex: Zebra, Honeywell, Samsung" maxlength="80"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-toggle-on"></i>
                  <select name="status" required>
                    <option value="Ativa" selected>Ativa</option>
                    <option value="Inativa">Inativa</option>
                  </select>
                </div>
              </label>
            </div>

            <div id="brandFormMessage" class="form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="brandSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-circle"></i>
                <span>Cadastrar marca</span>
              </button>
            </div>
          </form>
        </article>

        <article class="content-card records-card brand-records-card">
          <div class="card-header records-header">
            <div>
              <p class="section-tag">Banco de dados</p>
              <h3>Marcas cadastradas</h3>
            </div>

            <div class="records-actions">
              <span id="brandResultCount"><?php echo e((string) count($marcas)); ?> registros</span>
              <select id="brandStatusFilter" aria-label="Filtrar marcas por status">
                <option value="todos">Todos</option>
                <option value="ativa">Ativas</option>
                <option value="inativa">Inativas</option>
              </select>
            </div>
          </div>

          <div class="search-box brand-search">
            <i class="bi bi-search"></i>
            <input id="brandSearch" type="search" placeholder="Buscar marca" aria-label="Buscar marca"
              autocomplete="off" />
          </div>

          <div class="records-table-wrap brand-table-wrap">
            <table class="records-table brand-table">
              <thead>
                <tr>
                  <th>Marca</th>
                  <th>Status</th>
                  <th>Criada em</th>
                  <th>Atualizada em</th>
                </tr>
              </thead>
              <tbody id="brandTableBody">
                <?php foreach ($marcas as $marca): ?>
                  <?php
                  $nome = (string) ($marca["nome"] ?? "");
                  $status = (string) ($marca["status"] ?? "");
                  ?>
                  <tr class="registration-row brand-row" data-status="<?php echo e(strtolower($status)); ?>"
                    data-search="<?php echo e(strtolower($nome)); ?>">
                    <td data-label="Marca">
                      <strong><?php echo e($nome); ?></strong>
                    </td>
                    <td data-label="Status">
                      <span class="status-badge <?php echo $status === "Ativa" ? "status-active" : "status-inactive"; ?>">
                        <?php echo e($status); ?>
                      </span>
                    </td>
                    <td data-label="Criada em"><?php echo e(formatarDataMarca((string) ($marca["criado_em"] ?? ""))); ?>
                    </td>
                    <td data-label="Atualizada em">
                      <?php echo e(formatarDataMarca((string) ($marca["atualizado_em"] ?? ""))); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div id="brandEmptyState" class="empty-state records-empty" <?php echo $marcas ? "hidden" : ""; ?>>
            <i class="bi bi-info-circle"></i>
            <span>Nenhuma marca encontrada.</span>
          </div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>




