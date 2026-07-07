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

require_once __DIR__ . "/Backend/permissoes-acesso.php";
exigirPermissaoPagina("editar_funcionarios", "Edicao de funcionarios");

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

function formatarDataCurta(?string $value): string
{
  if (!$value) {
    return "--";
  }

  try {
    return (new DateTimeImmutable($value))
      ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
      ->format("d/m/Y");
  } catch (Throwable) {
    return "--";
  }
}

function iniciaisFuncionario(string $nome): string
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
$csrfToken = e((string) $_SESSION["csrf_token"]);
$departamentos = ["TI", "Operacao", "Financeiro", "Administrativo", "Gestao"];

try {
  require_once __DIR__ . "/Backend/Conexao.php";

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
            rg,
            cpf,
            celular,
            data_nascimento,
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
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Edi&ccedil;&atilde;o de funcion&aacute;rios | TI TECH Solutions</title>
  <meta name="description" content="Edi&ccedil;&atilde;o de funcion&aacute;rios cadastrados no portal interno da TI TECH Solutions" />
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/funcionarios.css?v=20260706-employee-edit-page" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-search-box-reset" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/edicao-funcionarios.js?v=20260706-employee-edit-page" defer></script>
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
              <a href="cadastro-grupos.php">Grupos</a>

            <?php else: ?>
              <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Cadastro de funcionarios" title="Apenas administradores podem cadastrar funcionarios">Funcion&aacute;rios</span>
              <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Cadastro de grupos" title="Apenas administradores podem criar grupos">Grupos</span>

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
            <?php if ($sidebarIsAdmin): ?>
            <a class="active-submenu" href="edicao-funcionarios.php">Funcion&aacute;rios</a>
            <a href="edicao-grupos.php">Grupos</a>
            <?php else: ?>
            <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Edicao de funcionarios" title="Apenas administradores podem editar funcionarios">Funcion&aacute;rios</span>
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
              <span class="typewriter-heading" style="--typewriter-min: 22ch">Editar funcion&aacute;rios</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="employee-create-button" href="cadastro-funcionarios.php">
            <i class="bi bi-person-plus-fill"></i>
            <span>Novo funcion&aacute;rio</span>
          </a>

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
              data-typewriter-phrases="Edite dados dos funcion&aacute;rios.|Mantenha perfis atualizados.|Controle status e departamento.">Edite
              dados dos funcion&aacute;rios.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Selecione um funcion&aacute;rio para atualizar perfil, departamento, contato, documentos
            e status de acesso sem sair desta tela.
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
            <strong id="employeeTotalMetric"><?php echo e((string) $totalFuncionarios); ?></strong>
            <p>Funcion&aacute;rios cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-check-fill"></i>
          </div>

          <div>
            <span>Ativos</span>
            <strong id="employeeActiveMetric"><?php echo e((string) $funcionariosAtivos); ?></strong>
            <p>Usu&aacute;rios liberados para acesso</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-dash-fill"></i>
          </div>

          <div>
            <span>Inativos</span>
            <strong id="employeeInactiveMetric"><?php echo e((string) $funcionariosInativos); ?></strong>
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

      <div id="employeeEditPageMessage" class="form-message employee-form-message" role="status" aria-live="polite"></div>

      <section class="content-card records-card employees-records-card" aria-labelledby="employeesListTitle">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Edi&ccedil;&atilde;o</p>
            <h3 id="employeesListTitle">Funcion&aacute;rios para editar</h3>
          </div>

          <div class="records-actions">
            <div class="search-box employee-search">
              <i class="bi bi-search"></i>
              <input id="employeeSearch" type="search" placeholder="Buscar funcion&aacute;rios..."
                aria-label="Buscar funcion&aacute;rios" />
            </div>

            <span id="employeeResultCount"><?php echo e((string) count($funcionarios)); ?> registros</span>
            <select id="employeeStatusFilter" aria-label="Filtrar por status">
              <option value="todos">Todos os status</option>
              <option value="ativo">Ativos</option>
              <option value="inativo">Inativos</option>
            </select>
          </div>
        </div>

        <div id="employeeCardList" class="employee-cards-grid">
          <?php if (!$funcionarios): ?>
            <div class="empty-state records-empty employee-empty-row">
              <i class="bi bi-info-circle"></i>
              <span>Nenhum funcion&aacute;rio encontrado.</span>
            </div>
          <?php endif; ?>

          <?php foreach ($funcionarios as $funcionario): ?>
            <?php
            $nomeFuncionario = (string) ($funcionario["nome_completo"] ?? "--");
            $emailFuncionario = (string) ($funcionario["email"] ?? "--");
            $tipoFuncionario = (string) ($funcionario["tipo_usuario"] ?? "--");
            $departamentoFuncionario = (string) ($funcionario["departamento"] ?? "--");
            $empresaFuncionario = (string) ($funcionario["empresa"] ?? "--");
            $celularFuncionario = (string) ($funcionario["celular"] ?? "--");
            $rgFuncionario = (string) ($funcionario["rg"] ?? "--");
            $cpfFuncionario = (string) ($funcionario["cpf"] ?? "--");
            $status = (string) ($funcionario["status"] ?? "--");
            $cadastroFuncionario = formatarData((string) ($funcionario["criado_em"] ?? ""));
            $atualizacaoFuncionario = formatarData((string) ($funcionario["atualizado_em"] ?? ""));
            $nascimentoValor = (string) ($funcionario["data_nascimento"] ?? "");
            $nascimentoFuncionario = formatarDataCurta($nascimentoValor);
            $searchText = implode(" ", [
              $nomeFuncionario,
              $emailFuncionario,
              $tipoFuncionario,
              $departamentoFuncionario,
              $empresaFuncionario,
              $celularFuncionario,
              $rgFuncionario,
              $cpfFuncionario,
              $status,
            ]);
            ?>
            <button class="employee-card employee-row employee-edit-card" type="button" data-employee-card
              data-id="<?php echo e((string) ($funcionario["id"] ?? "")); ?>"
              data-status="<?php echo e(strtolower($status)); ?>" data-search="<?php echo e($searchText); ?>"
              data-name="<?php echo e($nomeFuncionario); ?>" data-email="<?php echo e($emailFuncionario); ?>"
              data-role="<?php echo e($tipoFuncionario); ?>" data-department="<?php echo e($departamentoFuncionario); ?>"
              data-company="<?php echo e($empresaFuncionario); ?>" data-phone="<?php echo e($celularFuncionario); ?>"
              data-rg="<?php echo e($rgFuncionario); ?>" data-cpf="<?php echo e($cpfFuncionario); ?>"
              data-birth="<?php echo e($nascimentoFuncionario); ?>" data-birth-value="<?php echo e($nascimentoValor); ?>"
              data-status-label="<?php echo e($status); ?>"
              data-created="<?php echo e($cadastroFuncionario); ?>" data-updated="<?php echo e($atualizacaoFuncionario); ?>"
              data-initials="<?php echo e(iniciaisFuncionario($nomeFuncionario)); ?>">
              <span class="employee-card-header">
                <span class="employee-card-avatar" aria-hidden="true"><?php echo e(iniciaisFuncionario($nomeFuncionario)); ?></span>
                <span class="employee-card-identity">
                  <strong><?php echo e($nomeFuncionario); ?></strong>
                  <small><?php echo e($emailFuncionario); ?></small>
                </span>
                <span class="status-badge <?php echo e(statusClasse($status)); ?>"><?php echo e($status); ?></span>
              </span>

              <span class="employee-card-body">
                <span>
                  <i class="bi bi-person-badge"></i>
                  <small>Perfil</small>
                  <strong><?php echo e($tipoFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-diagram-3"></i>
                  <small>Departamento</small>
                  <strong><?php echo e($departamentoFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-buildings"></i>
                  <small>Empresa</small>
                  <strong><?php echo e($empresaFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-phone"></i>
                  <small>Celular</small>
                  <strong><?php echo e($celularFuncionario); ?></strong>
                </span>
              </span>

              <span class="employee-card-footer">
                <span>
                  <i class="bi bi-calendar2-check"></i>
                  Cadastro em <?php echo e($cadastroFuncionario); ?>
                </span>
                <span class="employee-card-open">
                  Editar funcion&aacute;rio
                  <i class="bi bi-pencil-square"></i>
                </span>
              </span>
            </button>
          <?php endforeach; ?>
        </div>

        <div id="employeeEmptyState" class="empty-state records-empty employee-filter-empty" hidden>
          <i class="bi bi-search"></i>
          <span>Nenhum funcion&aacute;rio corresponde aos filtros aplicados.</span>
        </div>
      </section>

      <div id="employeeEditModal" class="employee-modal-backdrop" hidden>
        <section class="employee-modal-card employee-edit-modal-card" role="dialog" aria-modal="true"
          aria-labelledby="employeeEditModalTitle">
          <form id="employeeEditForm" class="enhanced-asset-form employee-edit-form" action="Backend/atualizar-funcionario.php"
            method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />
            <input id="editEmployeeId" type="hidden" name="id" />

            <div class="employee-modal-header">
              <div class="employee-modal-profile">
                <span id="employeeEditInitials" class="employee-modal-avatar" aria-hidden="true">TT</span>
                <div>
                  <p class="section-tag">Edi&ccedil;&atilde;o do funcion&aacute;rio</p>
                  <h3 id="employeeEditModalTitle">Funcion&aacute;rio</h3>
                  <span id="employeeEditEmailText" class="employee-modal-email">--</span>
                </div>
              </div>

              <button class="icon-button modal-close-button" type="button" data-close-employee-modal
                aria-label="Fechar edi&ccedil;&atilde;o do funcion&aacute;rio">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>

            <div class="employee-edit-grid">
              <label class="asset-field">
                <span>Nome completo <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-person"></i>
                  <input id="editEmployeeName" name="nome_completo" type="text" required />
                </div>
              </label>

              <label class="asset-field">
                <span>E-mail de login</span>
                <div class="input-shell is-readonly">
                  <i class="bi bi-envelope"></i>
                  <input id="editEmployeeEmail" name="email" type="email" readonly />
                </div>
              </label>

              <label class="asset-field">
                <span>Perfil <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-person-badge"></i>
                  <select id="editEmployeeRole" name="tipo_usuario" required>
                    <option value="Colaborador">Colaborador</option>
                    <option value="Administrador">Administrador</option>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-toggle-on"></i>
                  <select id="editEmployeeStatus" name="status" required>
                    <option value="Ativo">Ativo</option>
                    <option value="Inativo">Inativo</option>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Departamento <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-diagram-3"></i>
                  <select id="editEmployeeDepartment" name="departamento" required>
                    <?php foreach ($departamentos as $departamento): ?>
                      <option value="<?php echo e($departamento); ?>"><?php echo e($departamento); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Empresa <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-buildings"></i>
                  <input id="editEmployeeCompany" name="empresa" type="text" required />
                </div>
              </label>

              <label class="asset-field">
                <span>RG <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-card-text"></i>
                  <input id="editEmployeeRg" name="rg" type="text" required />
                </div>
              </label>

              <label class="asset-field">
                <span>CPF <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-person-vcard"></i>
                  <input id="editEmployeeCpf" name="cpf" type="text" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Celular <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-phone"></i>
                  <input id="editEmployeePhone" name="celular" type="tel" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Data de nascimento <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-calendar3"></i>
                  <input id="editEmployeeBirth" name="data_nascimento" type="date" required />
                </div>
              </label>
            </div>

            <div id="employeeEditMessage" class="form-message employee-form-message" role="status" aria-live="polite"></div>

            <div class="employee-modal-actions">
              <button class="secondary-button" type="button" data-close-employee-modal>
                <i class="bi bi-x-lg"></i>
                <span>Cancelar</span>
              </button>
              <button id="saveEmployeeButton" class="employee-create-button" type="submit">
                <i class="bi bi-check-lg"></i>
                <span>Salvar altera&ccedil;&otilde;es</span>
              </button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>
</body>

</html>
