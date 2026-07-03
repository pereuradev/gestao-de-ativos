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

$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
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

$departamentos = ["TI", "Comercial", "Administrativo"];
$totalFuncionarios = 0;
$totalAdministradores = 0;
$totalColaboradores = 0;
$ultimoCadastro = "--";
$ultimosFuncionarios = [];
$erroBanco = "";

try {
  require __DIR__ . "/Backend/Conexao.php";

  $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where lower(tipo_usuario) = 'administrador')::int as administradores,
            count(*) filter (where lower(tipo_usuario) = 'colaborador')::int as colaboradores,
            max(criado_em) as ultimo_cadastro
          from public.perfis_usuarios
    ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalFuncionarios = (int) ($resumo["total"] ?? 0);
  $totalAdministradores = (int) ($resumo["administradores"] ?? 0);
  $totalColaboradores = (int) ($resumo["colaboradores"] ?? 0);
  $ultimoCadastro = formatarData((string) ($resumo["ultimo_cadastro"] ?? ""));

  $ultimosStmt = $pdo->prepare("
        select
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            status,
            criado_em
          from public.perfis_usuarios
      order by criado_em desc, nome_completo asc
         limit 6
    ");
  $ultimosStmt->execute();
  $ultimosFuncionarios = $ultimosStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar o resumo de funcionarios agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de funcion&aacute;rios | TI TECH Solutions</title>
  <meta name="description"
    content="Cadastro interno de funcion&aacute;rios do portal da TI TECH Solutions, restrito a administradores." />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-funcionarios.css?v=20260702-employee-hero-gradient" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260703-modal-sidebar-profile" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/ux-profissional.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/app-base.js?v=20260703-group-permissions" defer></script>
  <script src="js/cadastro-funcionarios.js?v=20260702-confirm-dialogs" defer></script>
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
            <a class="active-submenu" href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
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

    <main class="main-area employee-registration-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Cadastros</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 23ch">Funcion&aacute;rios</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="funcionarios.php">
            <i class="bi bi-people"></i>
            <span>Ver lista</span>
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero employee-register-hero" aria-labelledby="employeeRegisterTitle">
        <div class="hero-content">
          <p class="section-tag">Acesso interno</p>
          <h2 id="employeeRegisterTitle">
            <span class="typewriter-heading" style="--typewriter-min: 35ch" data-typewriter-loop
              data-typewriter-phrases="Cadastre administradores e colaboradores.|Controle quem entra no portal.|Centralize o acesso por perfil.">Cadastre
              administradores e colaboradores.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Esta &aacute;rea &eacute; exclusiva para administradores. Use-a para criar novos acessos com o perfil
            correto,
            dados corporativos completos e rastreabilidade desde o primeiro login.
          </p>
        </div>
      </section>

      <section class="metrics-grid employee-registration-metrics"
        aria-label="Resumo de cadastros de funcion&aacute;rios">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-people-fill"></i>
          </div>
          <div>
            <span>Total</span>
            <strong id="employeeMetricTotal"><?php echo e((string) $totalFuncionarios); ?></strong>
            <p>Funcion&aacute;rios no portal</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <div>
            <span>Administradores</span>
            <strong id="employeeMetricAdmins"><?php echo e((string) $totalAdministradores); ?></strong>
            <p>Perfis com permiss&atilde;o ampliada</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-badge-fill"></i>
          </div>
          <div>
            <span>Colaboradores</span>
            <strong id="employeeMetricCollaborators"><?php echo e((string) $totalColaboradores); ?></strong>
            <p>Perfis operacionais do portal</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-clock-history"></i>
          </div>
          <div>
            <span>&Uacute;ltimo cadastro</span>
            <strong id="employeeMetricLast" class="metric-date"><?php echo e($ultimoCadastro); ?></strong>
            <p>Hor&aacute;rio do registro mais recente</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="asset-registration-layout employee-registration-layout">
        <article class="content-card asset-form-card-enhanced employee-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formulario</p>
              <h3>Novo funcion&aacute;rio</h3>
              <span class="card-subtitle">
                Crie o acesso com dados completos. O perfil define se o usu&aacute;rio entrar&aacute; como administrador
                ou colaborador no portal.
              </span>
            </div>


          </div>

          <form id="employeeSignupForm" class="enhanced-asset-form" action="Backend/cadastrar-usuario.php" method="post"
            novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />
            <input id="selectedEmployeeRole" type="hidden" name="tipo_usuario" value="Colaborador" />

            <div class="asset-form-grid">
              <div class="form-section-title">
                <i class="bi bi-person-plus"></i>
                <span>Dados principais</span>
              </div>

              <label class="asset-field wide-field">
                <span>Nome completo <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-person"></i>
                  <input id="employeeFullName" name="nome_completo" type="text" placeholder="Nome e sobrenome"
                    autocomplete="name" required />
                </div>
              </label>

              <label class="asset-field">
                <span>E-mail corporativo <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-envelope"></i>
                  <input id="employeeEmail" name="email" type="email" placeholder="nome@titechsolutions.com.br"
                    autocomplete="email" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Departamento <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-diagram-3"></i>
                  <select id="employeeDepartment" name="departamento" required>
                    <option value="">Selecione o departamento</option>
                    <?php foreach ($departamentos as $departamento): ?>
                      <option value="<?php echo e($departamento); ?>"><?php echo e($departamento); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </label>

              <div class="asset-field wide-field">
                <span>Perfil de acesso <strong>*</strong></span>
                <div class="segment-control" id="employeeRoleControl" data-active="Colaborador"
                  aria-label="Perfil de acesso do funcion&aacute;rio">
                  <button class="active" data-role="Colaborador" type="button">Colaborador</button>
                  <button data-role="Administrador" type="button">Administrador</button>
                </div>
              </div>

              <div class="form-section-title secondary-section">
                <i class="bi bi-patch-check"></i>
                <span>Identificacao e contato</span>
              </div>

              <label class="asset-field">
                <span>RG <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-card-text"></i>
                  <input id="employeeRg" name="rg" type="text" placeholder="00.000.000-0" inputmode="numeric"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>CPF <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-person-vcard"></i>
                  <input id="employeeCpf" name="cpf" type="text" placeholder="000.000.000-00" inputmode="numeric"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Celular <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-phone"></i>
                  <input id="employeeCellphone" name="celular" type="tel" placeholder="(00) 00000-0000" inputmode="tel"
                    autocomplete="tel" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Data de nascimento <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-calendar3"></i>
                  <input id="employeeBirthDate" name="data_nascimento" type="date" autocomplete="bday" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Empresa <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-buildings"></i>
                  <input id="employeeCompany" name="empresa" type="text" placeholder="Nome da empresa"
                    autocomplete="organization" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Senha inicial <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-key"></i>
                  <input id="employeePassword" name="senha" type="password" placeholder="Minimo de 6 caracteres"
                    autocomplete="new-password" required />
                  <button class="password-toggle" data-target="employeePassword" type="button"
                    aria-label="Mostrar senha">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </label>
            </div>

            <div class="password-meter" aria-live="polite">
              <div class="meter-track">
                <span id="employeePasswordStrengthBar"></span>
              </div>
              <small id="employeePasswordStrengthText">Forca da senha: aguardando</small>
            </div>

            <div id="employeeFormMessage" class="form-message employee-form-message" role="status" aria-live="polite">
            </div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="employeeSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-person-plus-fill"></i>
                <span>Cadastrar funcion&aacute;rio</span>
              </button>
            </div>
          </form>
        </article>

        <article class="content-card recent-assets-card employee-side-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Leitura rapida</p>
              <h3>&Uacute;ltimos acessos criados</h3>
            </div>
          </div>

          <div class="employee-note-panel">
            <h4>Regras do cadastro</h4>
            <ul class="employee-note-list">
              <li>O e-mail deve ser corporativo da TI TECH Solutions.</li>
              <li>Administrador tem acesso ampliado a configuracoes e cadastros.</li>
              <li>Colaborador entra no portal com perfil operacional.</li>
            </ul>
          </div>

          <div id="recentEmployeeList" class="recent-asset-list recent-employee-list">
            <?php if (!$ultimosFuncionarios): ?>
              <div class="empty-state records-empty compact-empty-state">
                <i class="bi bi-info-circle"></i>
                <span>Nenhum funcion&aacute;rio cadastrado ainda.</span>
              </div>
            <?php endif; ?>

            <?php foreach ($ultimosFuncionarios as $funcionario): ?>
              <article class="recent-asset-item recent-employee-card">
                <div class="recent-asset-topline">
                  <strong><?php echo e((string) ($funcionario["nome_completo"] ?? "--")); ?></strong>
                  <span
                    class="status-badge <?php echo strtolower((string) ($funcionario["status"] ?? "")) === "ativo" ? "status-active" : "status-neutral"; ?>">
                    <?php echo e((string) ($funcionario["status"] ?? "Ativo")); ?>
                  </span>
                </div>

                <div class="recent-asset-meta">
                </div>

                <div class="recent-asset-footer">
                  <span>
                    <?php echo e((string) ($funcionario["tipo_usuario"] ?? "--")); ?>
                  </span>
                  <span>
                    <?php echo e((string) ($funcionario["departamento"] ?? "--")); ?>
                  </span>
                  <span><?php echo e((string) ($funcionario["email"] ?? "--")); ?></span>
                  <time
                    datetime="<?php echo e((string) ($funcionario["criado_em"] ?? "")); ?>"><?php echo e(formatarData((string) ($funcionario["criado_em"] ?? ""))); ?></time>
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
