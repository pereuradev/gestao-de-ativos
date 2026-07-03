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

function formatarDataGrupo(?string $value): string
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
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);

if (!$sidebarIsAdmin) {
  header("Location: pagina-inicial.php");
  exit;
}

$sidebarRoleLabel = e("ADM");
$sidebarRoleClass = e("is-admin");
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
$funcionarios = [];
$gruposRecentes = [];
$permissoesDisponiveis = [];
$totalGrupos = 0;
$totalMembros = 0;
$totalPermissoes = 0;
$erroBanco = "";

try {
  require __DIR__ . "/Backend/Conexao.php";
  require __DIR__ . "/Backend/grupos-acesso-util.php";

  garantirTabelasGruposAcesso($pdo);
  $permissoesDisponiveis = permissoesGruposAcesso();

  $funcionariosStmt = $pdo->prepare("
      select id, nome_completo, email, tipo_usuario, departamento
        from public.perfis_usuarios
       where lower(coalesce(status, 'ativo')) = 'ativo'
       order by nome_completo asc
  ");
  $funcionariosStmt->execute();
  $funcionarios = $funcionariosStmt->fetchAll();

  $resumoStmt = $pdo->prepare("
      select
          (select count(*)::int from public.grupos_acesso) as grupos,
          (select count(*)::int from public.grupos_acesso_membros) as membros,
          (select count(*)::int from public.grupos_acesso_permissoes) as permissoes
  ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalGrupos = (int) ($resumo["grupos"] ?? 0);
  $totalMembros = (int) ($resumo["membros"] ?? 0);
  $totalPermissoes = (int) ($resumo["permissoes"] ?? 0);

  $gruposStmt = $pdo->prepare("
      select
          g.id,
          g.nome,
          g.descricao,
          g.status,
          g.criado_em,
          count(distinct gm.usuario_id)::int as total_membros,
          count(distinct gp.permissao)::int as total_permissoes
        from public.grupos_acesso g
   left join public.grupos_acesso_membros gm on gm.grupo_id = g.id
   left join public.grupos_acesso_permissoes gp on gp.grupo_id = g.id
    group by g.id, g.nome, g.descricao, g.status, g.criado_em
    order by g.criado_em desc
       limit 6
  ");
  $gruposStmt->execute();
  $gruposRecentes = $gruposStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os grupos agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de grupos | TI TECH Solutions</title>
  <meta name="description" content="Cadastro de grupos de acesso e permissoes de colaboradores." />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-funcionarios.css?v=20260702-employee-hero-gradient" />
  <link rel="stylesheet" href="css/cadastro-grupos.css?v=20260702-groups-clear-red" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260703-modal-sidebar-profile" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/ux-profissional.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/app-base.js?v=20260703-sidebar-profile-modal" defer></script>
  <script src="js/cadastro-grupos.js?v=20260702-groups-page" defer></script>
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
            <a href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
            <a class="active-submenu" href="cadastro-grupos.php">Grupos</a>
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
            <?php if ($sidebarIsAdmin): ?>
            <a href="edicao-grupos.php">Grupos</a>
            <?php else: ?>
            <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Edicao de grupos" title="Apenas administradores podem editar grupos">Grupos</span>
            <?php endif; ?>
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
            <small title="<?php echo $sidebarEmail; ?>"><?php echo $sidebarEmail !== "" ? $sidebarEmail : "Email nao informado"; ?></small>
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

    <main class="main-area employee-registration-page group-registration-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Cadastros</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch">Grupos de acesso</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero employee-register-hero group-register-hero" aria-labelledby="groupRegisterTitle">
        <div class="hero-content">
          <p class="section-tag">Controle de acesso</p>
          <h2 id="groupRegisterTitle">
            <span class="typewriter-heading" style="--typewriter-min: 33ch" data-typewriter-loop
              data-typewriter-phrases="Crie grupos para colaboradores.|Defina permissoes por equipe.|Organize acessos do portal.">Crie
              grupos para colaboradores.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Selecione funcionarios, defina as permissoes do grupo e mantenha o acesso operacional organizado por equipe.
          </p>
        </div>
      </section>

      <section class="metrics-grid employee-registration-metrics" aria-label="Resumo dos grupos">
        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-collection-fill"></i></div>
          <div>
            <span>Grupos</span>
            <strong id="groupMetricTotal"><?php echo e((string) $totalGrupos); ?></strong>
            <p>Grupos cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-person-check-fill"></i></div>
          <div>
            <span>Membros</span>
            <strong id="groupMetricMembers"><?php echo e((string) $totalMembros); ?></strong>
            <p>Vinculos ativos em grupos</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-shield-check"></i></div>
          <div>
            <span>Permiss&otilde;es</span>
            <strong id="groupMetricPermissions"><?php echo e((string) $totalPermissoes); ?></strong>
            <p>Regras distribuidas</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-people"></i></div>
          <div>
            <span>Funcion&aacute;rios</span>
            <strong><?php echo e((string) count($funcionarios)); ?></strong>
            <p>Disponiveis para selecao</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="asset-registration-layout employee-registration-layout group-registration-layout">
        <article class="content-card asset-form-card-enhanced employee-form-card group-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formulario</p>
              <h3>Novo grupo</h3>
              <span class="card-subtitle">
                Crie um grupo, selecione colaboradores e marque as permissoes que essa equipe podera usar.
              </span>
            </div>
          </div>

          <form id="groupForm" class="enhanced-asset-form" action="Backend/cadastrar-grupo.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid group-form-grid">
              <div class="form-section-title">
                <i class="bi bi-collection"></i>
                <span>Dados do grupo</span>
              </div>

              <label class="asset-field">
                <span>Nome do grupo <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-diagram-3"></i>
                  <input id="groupName" name="nome" type="text" placeholder="Ex: Operacao de estoque" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Descricao</span>
                <div class="input-shell">
                  <i class="bi bi-card-text"></i>
                  <input id="groupDescription" name="descricao" type="text" placeholder="Resumo do uso do grupo" />
                </div>
              </label>

              <div class="form-section-title secondary-section">
                <i class="bi bi-people"></i>
                <span>Funcionarios do grupo</span>
              </div>

              <div class="group-selector wide-field">
                <div class="group-selector-toolbar">
                  <div class="search-box group-search-box">
                    <i class="bi bi-search"></i>
                    <input id="groupEmployeeSearch" type="search" placeholder="Buscar funcionario" autocomplete="off" />
                  </div>
                  <button class="filter-clear-button" id="clearGroupEmployees" type="button">
                    <i class="bi bi-eraser-fill"></i>
                    Limpar
                  </button>
                </div>

                <div id="groupEmployeeList" class="group-check-list">
                  <?php foreach ($funcionarios as $funcionario): ?>
                    <?php
                    $funcionarioId = (string) ($funcionario["id"] ?? "");
                    $funcionarioNome = (string) ($funcionario["nome_completo"] ?? "--");
                    $funcionarioBusca = strtolower($funcionarioNome . " " . (string) ($funcionario["email"] ?? "") . " " . (string) ($funcionario["departamento"] ?? ""));
                    ?>
                    <label class="group-check-card" data-search="<?php echo e($funcionarioBusca); ?>">
                      <input type="checkbox" name="membros[]" value="<?php echo e($funcionarioId); ?>" />
                      <span class="group-check-body">
                        <strong><?php echo e($funcionarioNome); ?></strong>
                        <small><?php echo e((string) ($funcionario["email"] ?? "--")); ?></small>
                        <small><?php echo e((string) ($funcionario["tipo_usuario"] ?? "--")); ?> &middot; <?php echo e((string) ($funcionario["departamento"] ?? "--")); ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>

                  <?php if (!$funcionarios): ?>
                    <div class="empty-state records-empty compact-empty-state">
                      <i class="bi bi-info-circle"></i>
                      <span>Nenhum funcionario ativo encontrado.</span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-section-title secondary-section">
                <i class="bi bi-shield-check"></i>
                <span>Permissoes do grupo</span>
              </div>

              <div class="permission-grid wide-field">
                <?php foreach ($permissoesDisponiveis as $codigo => $rotulo): ?>
                  <label class="permission-toggle">
                    <input type="checkbox" name="permissoes[]" value="<?php echo e((string) $codigo); ?>" />
                    <span><?php echo e((string) $rotulo); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div id="groupFormMessage" class="form-message employee-form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-eraser"></i>
                <span>Limpar campos</span>
              </button>

              <button id="groupSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-lg"></i>
                <span>Cadastrar grupo</span>
              </button>
            </div>
          </form>
        </article>

        <article class="content-card recent-assets-card employee-side-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Leitura rapida</p>
              <h3>Grupos recentes</h3>
            </div>
          </div>

          <div class="employee-note-panel">
            <h4>Uso das permissoes</h4>
            <ul class="employee-note-list">
              <li>Administrador continua com acesso ampliado ao sistema.</li>
              <li>Grupos organizam permissoes para colaboradores.</li>
              <li>Novas telas podem consultar estes grupos para liberar funcoes.</li>
            </ul>
          </div>

          <div id="recentGroupList" class="recent-asset-list recent-employee-list">
            <?php if (!$gruposRecentes): ?>
              <div class="empty-state records-empty compact-empty-state">
                <i class="bi bi-info-circle"></i>
                <span>Nenhum grupo cadastrado ainda.</span>
              </div>
            <?php endif; ?>

            <?php foreach ($gruposRecentes as $grupo): ?>
              <article class="recent-asset-item recent-employee-card group-recent-card">
                <div class="recent-asset-topline">
                  <strong><?php echo e((string) ($grupo["nome"] ?? "--")); ?></strong>
                  <span class="status-badge status-active"><?php echo e((string) ($grupo["status"] ?? "Ativo")); ?></span>
                </div>
                <div class="recent-asset-footer">
                  <span><?php echo e((string) ($grupo["total_membros"] ?? "0")); ?> membros</span>
                  <span><?php echo e((string) ($grupo["total_permissoes"] ?? "0")); ?> permissoes</span>
                  <time datetime="<?php echo e((string) ($grupo["criado_em"] ?? "")); ?>"><?php echo e(formatarDataGrupo((string) ($grupo["criado_em"] ?? ""))); ?></time>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>
