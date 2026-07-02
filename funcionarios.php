<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarData(?string $value): string
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

function statusClasse(string $status): string
{
  $statusNormalizado = strtolower(trim($status));

  if ($statusNormalizado === "ativo") {
    return "status-active";
  }

  if ($statusNormalizado === "inativo") {
    return "status-inactive";
  }

  return "status-neutral";
}

$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);
$accessDenied = !$sidebarIsAdmin;
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

$funcionarios = [];
$totalFuncionarios = 0;
$funcionariosAtivos = 0;
$funcionariosInativos = 0;
$ultimoMovimento = "--";
$erroBanco = "";

if ($accessDenied) {
  http_response_code(403);
} else {
  try {
    require __DIR__ . "/Backend/Conexao.php";

    $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where lower(status) = 'ativo')::int as ativos,
            count(*) filter (where lower(status) = 'inativo')::int as inativos,
            max(greatest(criado_em, atualizado_em)) as ultimo_movimento
          from public.perfis_usuarios
    ");
    $resumoStmt->execute();
    $resumo = $resumoStmt->fetch() ?: [];

    $totalFuncionarios = (int) ($resumo["total"] ?? 0);
    $funcionariosAtivos = (int) ($resumo["ativos"] ?? 0);
    $funcionariosInativos = (int) ($resumo["inativos"] ?? 0);
    $ultimoMovimento = formatarData((string) ($resumo["ultimo_movimento"] ?? ""));

    $funcionariosStmt = $pdo->prepare("
        select
            id,
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            empresa,
            celular,
            status,
            criado_em,
            atualizado_em
          from public.perfis_usuarios
      order by
            case when lower(status) = 'ativo' then 0 else 1 end,
            greatest(criado_em, atualizado_em) desc,
            nome_completo asc
    ");
    $funcionariosStmt->execute();
    $funcionarios = $funcionariosStmt->fetchAll();
  } catch (Throwable) {
    $erroBanco = "Nao foi possivel carregar os funcionarios agora.";
  }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Funcion&aacute;rios | TI TECH Solutions</title>
  <meta name="description" content="Lista de funcion&aacute;rios cadastrados no portal interno da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/funcionarios.css?v=20260622-hero-polish" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260702-bottom-toast" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260630-reduced-motion" defer></script>
  <script src="js/funcionarios.js?v=20260624-common-ui" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading" <?php echo $accessDenied ? 'data-permission-dialog-open="true" data-permission-resource="Funcionarios"' : ""; ?>>
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
          <a class="nav-link active" href="funcionarios.php">
            <i class="bi bi-people-fill"></i>
            <span>Funcion&aacute;rios</span>
          </a>
        <?php else: ?>
          <span class="nav-link nav-link-disabled" aria-disabled="true" data-permission-resource="Funcionarios"
            title="Apenas administradores podem acessar funcionarios">
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
            <?php if ($sidebarIsAdmin): ?>
              <a href="cadastro-funcionarios.php">Funcion&aacute;rios</a>

            <?php else: ?>
              <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Cadastro de funcionarios" title="Apenas administradores podem cadastrar funcionarios">Funcion&aacute;rios</span>

            <?php endif; ?>
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

    <main class="main-area funcionarios-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Equipe</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 17ch">Funcion&aacute;rios</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <div class="search-box employee-search">
            <i class="bi bi-search"></i>
            <input id="employeeSearch" type="search" placeholder="Buscar funcion&aacute;rios..."
              aria-label="Buscar funcion&aacute;rios" />
          </div>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero employees-hero" aria-labelledby="employeesTitle">
        <div class="hero-content">
          <h2 id="employeesTitle">
            <span class="typewriter-heading" style="--typewriter-min: 31ch" data-typewriter-loop
              data-typewriter-phrases="Vis&atilde;o geral dos funcion&aacute;rios.|Dados principais da equipe.|Busca simples e objetiva.">Vis&atilde;o
              geral dos funcion&aacute;rios.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Consulte dados principais da equipe, identifique rapidamente quem est&aacute; ativo ou inativo
            e filtre a lista para apoiar rotinas de suporte, acessos e invent&aacute;rio.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo de funcion&aacute;rios">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-people-fill"></i>
          </div>

          <div>
            <span>Total</span>
            <strong><?php echo e((string) $totalFuncionarios); ?></strong>
            <p>Funcion&aacute;rios cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-check-fill"></i>
          </div>

          <div>
            <span>Ativos</span>
            <strong><?php echo e((string) $funcionariosAtivos); ?></strong>
            <p>Usu&aacute;rios liberados para acesso</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-dash-fill"></i>
          </div>

          <div>
            <span>Inativos</span>
            <strong><?php echo e((string) $funcionariosInativos); ?></strong>
            <p>Acesso bloqueado pelo status</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-clock-history"></i>
          </div>

          <div>
            <span>&Uacute;ltimo movimento</span>
            <strong class="metric-date"><?php echo e($ultimoMovimento); ?></strong>
            <p>Cadastro ou atualiza&ccedil;&atilde;o mais recente</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <?php if ($accessDenied): ?>
        <div class="dashboard-status error-status" role="status">
          Apenas administradores podem acessar a pagina de funcionarios.
        </div>
      <?php endif; ?>

      <section class="content-card records-card employees-records-card" aria-labelledby="employeesListTitle">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Lista</p>
            <h3 id="employeesListTitle">Funcion&aacute;rios cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="employeeResultCount"><?php echo e((string) count($funcionarios)); ?> registros</span>
            <select id="employeeStatusFilter" aria-label="Filtrar por status">
              <option value="todos">Todos os status</option>
              <option value="ativo">Ativos</option>
              <option value="inativo">Inativos</option>
            </select>
          </div>
        </div>

        <div class="records-table-wrap">
          <table class="records-table employees-table">
            <thead>
              <tr>
                <th>Funcion&aacute;rio</th>
                <th>Tipo</th>
                <th>Departamento</th>
                <th>Empresa</th>
                <th>Celular</th>
                <th>Status</th>
                <th>Cadastro</th>
              </tr>
            </thead>
            <tbody id="employeeTableBody">
              <?php if (!$funcionarios): ?>
                <tr class="employee-empty-row">
                  <td colspan="7">
                    <div class="empty-state records-empty">
                      <i class="bi bi-info-circle"></i>
                      <span>Nenhum funcion&aacute;rio encontrado.</span>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach ($funcionarios as $funcionario): ?>
                <?php
                $status = (string) ($funcionario["status"] ?? "--");
                $searchText = implode(" ", [
                  (string) ($funcionario["nome_completo"] ?? ""),
                  (string) ($funcionario["email"] ?? ""),
                  (string) ($funcionario["tipo_usuario"] ?? ""),
                  (string) ($funcionario["departamento"] ?? ""),
                  (string) ($funcionario["empresa"] ?? ""),
                  (string) ($funcionario["celular"] ?? ""),
                  $status,
                ]);
                ?>
                <tr class="registration-row employee-row" data-status="<?php echo e(strtolower($status)); ?>"
                  data-search="<?php echo e($searchText); ?>">
                  <td data-label="Funcion&aacute;rio">
                    <strong><?php echo e((string) ($funcionario["nome_completo"] ?? "--")); ?></strong>
                    <span class="employee-email"><?php echo e((string) ($funcionario["email"] ?? "--")); ?></span>
                  </td>
                  <td data-label="Tipo"><?php echo e((string) ($funcionario["tipo_usuario"] ?? "--")); ?></td>
                  <td data-label="Departamento"><?php echo e((string) ($funcionario["departamento"] ?? "--")); ?></td>
                  <td data-label="Empresa"><?php echo e((string) ($funcionario["empresa"] ?? "--")); ?></td>
                  <td data-label="Celular"><?php echo e((string) ($funcionario["celular"] ?? "--")); ?></td>
                  <td data-label="Status">
                    <span class="status-badge <?php echo e(statusClasse($status)); ?>">
                      <?php echo e($status); ?>
                    </span>
                  </td>
                  <td data-label="Cadastro"><?php echo e(formatarData((string) ($funcionario["criado_em"] ?? ""))); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="employeeEmptyState" class="empty-state records-empty employee-filter-empty" hidden>
          <i class="bi bi-search"></i>
          <span>Nenhum funcion&aacute;rio corresponde aos filtros aplicados.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>
