<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_grupos", "Grupos");

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataGrupoVisualizacao(?string $value): string
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

function iniciaisGrupoVisualizacao(string $nome): string
{
  $partes = preg_split("/\s+/", trim($nome)) ?: [];
  $iniciais = "";

  foreach ($partes as $parte) {
    if ($parte === "") {
      continue;
    }

    $iniciais .= strtoupper(substr($parte, 0, 1));

    if (strlen($iniciais) >= 2) {
      break;
    }
  }

  return $iniciais !== "" ? $iniciais : "TT";
}

$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);
$sidebarRoleLabel = e($sidebarIsAdmin ? "ADM" : "Colaborador");
$sidebarRoleClass = e($sidebarIsAdmin ? "is-admin" : "is-collaborator");
$sidebarEmail = e((string) ($usuario["email"] ?? ""));
$sidebarDepartment = e((string) ($usuario["departamento"] ?? "Sem departamento"));
$sidebarInitials = e(iniciaisGrupoVisualizacao((string) ($usuario["nome_completo"] ?? "Usuario")));

$grupos = [];
$membrosPorGrupo = [];
$permissoesPorGrupo = [];
$permissoesDisponiveis = [];
$totalGrupos = 0;
$gruposAtivos = 0;
$gruposInativos = 0;
$totalMembros = 0;
$erroBanco = "";

try {
  require_once __DIR__ . "/Backend/Conexao.php";
  require_once __DIR__ . "/Backend/grupos-acesso-util.php";

  garantirTabelasGruposAcesso($pdo);
  $permissoesDisponiveis = permissoesGruposAcesso();

  $resumoStmt = $pdo->prepare("
      select
          count(*)::int as grupos,
          count(*) filter (where lower(coalesce(status, 'ativo')) = 'ativo')::int as ativos,
          count(*) filter (where lower(coalesce(status, 'ativo')) = 'inativo')::int as inativos,
          (select count(*)::int from public.grupos_acesso_membros) as membros
        from public.grupos_acesso
  ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalGrupos = (int) ($resumo["grupos"] ?? 0);
  $gruposAtivos = (int) ($resumo["ativos"] ?? 0);
  $gruposInativos = (int) ($resumo["inativos"] ?? 0);
  $totalMembros = (int) ($resumo["membros"] ?? 0);

  $gruposStmt = $pdo->prepare("
      select
          g.id,
          g.nome,
          g.descricao,
          g.status,
          g.criado_em,
          g.atualizado_em,
          count(distinct gm.usuario_id)::int as total_membros,
          count(distinct gp.permissao)::int as total_permissoes
        from public.grupos_acesso g
   left join public.grupos_acesso_membros gm on gm.grupo_id = g.id
   left join public.grupos_acesso_permissoes gp on gp.grupo_id = g.id
    group by g.id, g.nome, g.descricao, g.status, g.criado_em, g.atualizado_em
    order by lower(coalesce(g.nome, '')) asc
  ");
  $gruposStmt->execute();
  $grupos = $gruposStmt->fetchAll();

  $membrosStmt = $pdo->prepare("
      select
          gm.grupo_id,
          gm.usuario_id,
          u.nome_completo,
          u.email,
          u.tipo_usuario,
          u.departamento
        from public.grupos_acesso_membros gm
        join public.perfis_usuarios u on u.id = gm.usuario_id
    order by u.nome_completo asc
  ");
  $membrosStmt->execute();

  foreach ($membrosStmt->fetchAll() as $membro) {
    $grupoId = (string) ($membro["grupo_id"] ?? "");
    $membrosPorGrupo[$grupoId][] = $membro;
  }

  $permissoesStmt = $pdo->prepare("
      select grupo_id, permissao
        from public.grupos_acesso_permissoes
    order by permissao asc
  ");
  $permissoesStmt->execute();

  foreach ($permissoesStmt->fetchAll() as $permissao) {
    $grupoId = (string) ($permissao["grupo_id"] ?? "");
    $codigo = (string) ($permissao["permissao"] ?? "");
    $permissoesPorGrupo[$grupoId][] = $permissoesDisponiveis[$codigo] ?? $codigo;
  }
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os grupos agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Grupos cadastrados | TI TECH Solutions</title>
  <meta name="description" content="Visualizacao dos grupos de acesso cadastrados no portal." />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-funcionarios.css?v=20260702-employee-hero-gradient" />
  <link rel="stylesheet" href="css/cadastro-grupos.css?v=20260707-permission-icons" />
  <link rel="stylesheet" href="css/edicao-grupos.css?v=20260707-group-view" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/ux-profissional.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/grupos-visualizacao.js?v=20260707-group-view" defer></script>
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
        <a class="nav-link active" href="grupos-visualizacao.php">
          <i class="bi bi-collection-fill"></i>
          <span>Grupos</span>
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
            <a href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
            <a href="cadastro-grupos.php">Grupos</a>
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
            <a href="edicao-funcionarios.php">Funcion&aacute;rios</a>
            <a href="edicao-grupos.php">Grupos</a>
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

    <main class="main-area employee-registration-page group-registration-page group-edit-page group-view-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Visualiza&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch">Grupos de acesso</span><span aria-hidden="true"></span>
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

      <section class="hero-panel compact-hero employee-register-hero group-register-hero" aria-labelledby="groupViewTitle">
        <div class="hero-content">
          <p class="section-tag">Controle de acesso</p>
          <h2 id="groupViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 31ch" data-typewriter-loop
              data-typewriter-phrases="Consulte grupos e permissoes.|Veja membros vinculados.|Acompanhe acessos por equipe.">Consulte grupos e permissoes.</span><span aria-hidden="true"></span>
          </h2>
          <p>Visualize quais colaboradores fazem parte de cada grupo e quais acessos est&atilde;o liberados.</p>
        </div>
      </section>

      <section class="metrics-grid employee-registration-metrics" aria-label="Resumo dos grupos">
        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-collection-fill"></i></div>
          <div>
            <span>Grupos</span>
            <strong><?php echo e((string) $totalGrupos); ?></strong>
            <p>Grupos cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-check-circle-fill"></i></div>
          <div>
            <span>Ativos</span>
            <strong><?php echo e((string) $gruposAtivos); ?></strong>
            <p>Grupos liberados para uso</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-pause-circle-fill"></i></div>
          <div>
            <span>Inativos</span>
            <strong><?php echo e((string) $gruposInativos); ?></strong>
            <p>Grupos sem efeito no acesso</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-person-check-fill"></i></div>
          <div>
            <span>Membros</span>
            <strong><?php echo e((string) $totalMembros); ?></strong>
            <p>V&iacute;nculos em grupos</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status"><?php echo e($erroBanco); ?></div>
      <?php endif; ?>

      <section class="content-card group-edit-card" aria-label="Visualizacao dos grupos">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Grupos cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="groupViewResultCount"><?php echo e((string) count($grupos)); ?> registros</span>
          </div>
        </div>

        <div class="group-edit-toolbar">
          <div class="search-box group-edit-search">
            <i class="bi bi-search"></i>
            <input id="groupViewSearch" type="search" placeholder="Buscar grupo, permissao, status ou funcionario"
              aria-label="Buscar grupo, permissao, status ou funcionario" autocomplete="off" />
          </div>
        </div>

        <div id="groupViewList" class="group-edit-list">
          <?php foreach ($grupos as $grupo): ?>
            <?php
            $grupoId = (string) ($grupo["id"] ?? "");
            $nome = (string) ($grupo["nome"] ?? "--");
            $descricao = (string) ($grupo["descricao"] ?? "");
            $status = (string) ($grupo["status"] ?? "Ativo");
            $membros = $membrosPorGrupo[$grupoId] ?? [];
            $permissoes = $permissoesPorGrupo[$grupoId] ?? [];
            $buscaMembros = implode(" ", array_map(static fn(array $membro): string => (string) ($membro["nome_completo"] ?? ""), $membros));
            $buscaPermissoes = implode(" ", $permissoes);
            $search = strtolower(trim($nome . " " . $descricao . " " . $status . " " . $buscaMembros . " " . $buscaPermissoes));
            ?>
            <article class="group-edit-item" data-search="<?php echo e($search); ?>">
              <header class="group-edit-item-header">
                <div>
                  <p class="section-tag">Grupo</p>
                  <h4><?php echo e($nome); ?></h4>
                  <span><?php echo e($descricao !== "" ? $descricao : "Sem descricao informada."); ?></span>
                </div>

                <div class="group-edit-actions">
                  <span class="status-badge <?php echo strtolower($status) === "ativo" ? "status-active" : "status-inactive"; ?>">
                    <?php echo e($status); ?>
                  </span>
                </div>
              </header>

              <div class="group-edit-summary">
                <span><strong><?php echo e((string) count($membros)); ?></strong> membros</span>
                <span><strong><?php echo e((string) count($permissoes)); ?></strong> permissoes</span>
                <span>Criado em <?php echo e(formatarDataGrupoVisualizacao((string) ($grupo["criado_em"] ?? ""))); ?></span>
                <span>Atualizado em <?php echo e(formatarDataGrupoVisualizacao((string) ($grupo["atualizado_em"] ?? ""))); ?></span>
              </div>

              <div class="group-permission-list" aria-label="Permissoes do grupo">
                <?php foreach ($permissoes as $permissao): ?>
                  <span><?php echo e((string) $permissao); ?></span>
                <?php endforeach; ?>
                <?php if (!$permissoes): ?>
                  <span>Nenhuma permissao cadastrada.</span>
                <?php endif; ?>
              </div>

              <div class="group-member-list">
                <?php foreach ($membros as $membro): ?>
                  <?php $membroNome = (string) ($membro["nome_completo"] ?? "--"); ?>
                  <article class="group-member-row group-view-member-row">
                    <div class="group-member-avatar" aria-hidden="true"><?php echo e(iniciaisGrupoVisualizacao($membroNome)); ?></div>
                    <div class="group-member-info">
                      <strong><?php echo e($membroNome); ?></strong>
                      <span><?php echo e((string) ($membro["email"] ?? "--")); ?></span>
                      <small><?php echo e((string) ($membro["tipo_usuario"] ?? "--")); ?> &middot; <?php echo e((string) ($membro["departamento"] ?? "--")); ?></small>
                    </div>
                  </article>
                <?php endforeach; ?>

                <?php if (!$membros): ?>
                  <div class="group-member-empty">
                    <i class="bi bi-info-circle"></i>
                    <span>Nenhum membro neste grupo.</span>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div id="groupViewEmptyState" class="empty-state records-empty" <?php echo $grupos ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhum grupo encontrado.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>
