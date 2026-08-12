<?php

declare(strict_types=1);

// Prepara a página administrativa de cadastro e o resumo recente de funcionários.
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Reutiliza um token por sessão para proteger formulários e operações de alteração contra CSRF.
if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("cadastrar_funcionarios", "Cadastro de funcionarios");

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

$usuarioPodeGerenciarAdministradores = usuarioGrupoAcessoAdministradorConfirmado($pdo);
$usuario = $_SESSION["usuario"];
$csrfToken = e((string) $_SESSION["csrf_token"]);

$departamentos = [
  "Comercial" => "Comercial",
  "TI" => "TI",
  "Administrativo" => "Administração",
];
$tituloCadastro = $usuarioPodeGerenciarAdministradores
  ? "Cadastre administradores e colaboradores."
  : "Cadastre novos colaboradores.";
$frasesCadastro = $usuarioPodeGerenciarAdministradores
  ? "Cadastre administradores e colaboradores.|Controle quem entra no portal.|Centralize o acesso por perfil."
  : "Cadastre novos colaboradores.|Organize os acessos da equipe.|Mantenha os dados corporativos completos.";
$totalFuncionarios = 0;
$totalAdministradores = 0;
$totalColaboradores = 0;
$ultimoCadastro = "--";
$ultimosFuncionarios = [];
$erroBanco = "";

try {
  // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
  require_once __DIR__ . "/../Backend/Conexao.php";

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
    content="Cadastro interno de funcion&aacute;rios do portal da TI TECH Solutions para usu&aacute;rios autorizados." />
  <!-- Identidade visual, tipografia e ícones usados pela página. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados e regras específicas deste fluxo. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260731-sidebar-compact" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="../css/cadastro-funcionarios.css?v=20260811-form-layout" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260803-desktop-density" />
  <!-- Scripts da interface; os módulos compartilhados devem carregar antes do script da página. -->
  <script src="../js/animations/efeito-digitacao.js?v=20260803-static-headings" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260731-sidebar-compact" defer></script>
  <script src="../js/base-interface.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/pages/cadastro-funcionarios.js?v=20260811-form-layout" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <!-- Navegação compartilhada entre as áreas autenticadas. -->
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

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

      <!-- Apresenta o fluxo administrativo de criação de contas. -->
      <section class="hero-panel compact-hero employee-register-hero" aria-labelledby="employeeRegisterTitle">
        <div class="hero-content">
          <p class="section-tag">Acesso interno</p>
          <h2 id="employeeRegisterTitle">
            <span class="typewriter-heading" style="--typewriter-min: 35ch" data-typewriter-loop
              data-typewriter-phrases="<?php echo e($frasesCadastro); ?>"><?php echo e($tituloCadastro); ?></span><span
              aria-hidden="true"></span>
          </h2>
          <p>
            <?php if ($usuarioPodeGerenciarAdministradores): ?>
              Crie novos acessos e defina o perfil correto com dados corporativos completos.
            <?php else: ?>
              Crie acessos operacionais para colaboradores. Perfis administrativos continuam protegidos.
            <?php endif; ?>
          </p>
        </div>
      </section>

      <!-- Resumo calculado no servidor antes da exibição do formulário. -->
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

      <!-- Formulário protegido por CSRF e painel com os cadastros mais recentes. -->
      <section class="asset-registration-layout employee-registration-layout">
        <article class="content-card asset-form-card-enhanced employee-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formulario</p>
              <h3>Novo funcion&aacute;rio</h3>
              <span class="card-subtitle">
                Preencha os dados obrigat&oacute;rios e revise as informa&ccedil;&otilde;es antes de concluir.
              </span>
            </div>


            <div class="employee-permission-badge <?php echo $usuarioPodeGerenciarAdministradores ? "is-admin" : "is-operational"; ?>">
              <i class="bi <?php echo $usuarioPodeGerenciarAdministradores ? "bi-shield-lock-fill" : "bi-person-check-fill"; ?>"></i>
              <span>
                <small>Cadastro permitido</small>
                <strong><?php echo $usuarioPodeGerenciarAdministradores ? "Todos os perfis" : "Somente colaboradores"; ?></strong>
              </span>
            </div>
          </div>

          <form id="employeeSignupForm" class="enhanced-asset-form" action="../Backend/cadastrar-usuario.php" method="post"
            data-permite-administrador="<?php echo $usuarioPodeGerenciarAdministradores ? "true" : "false"; ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />
            <input id="selectedEmployeeRole" type="hidden" name="tipo_usuario" value="Colaborador" />

            <div class="employee-form-sections">
              <fieldset class="employee-form-section">
                <legend class="employee-form-section-heading">
                  <span class="employee-section-number">01</span>
                  <i class="bi bi-person-vcard-fill" aria-hidden="true"></i>
                  <span class="employee-section-copy">
                    <strong>Dados pessoais e contato</strong>
                    <small>Identifica&ccedil;&atilde;o b&aacute;sica do novo funcion&aacute;rio</small>
                  </span>
                </legend>

                <div class="employee-fields-grid">
                  <label class="asset-field wide-field">
                    <span>Nome completo <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-person"></i>
                      <input id="employeeFullName" name="nome_completo" type="text" placeholder="Nome e sobrenome"
                        autocomplete="name" maxlength="150" required />
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>Data de nascimento <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-calendar3"></i>
                      <input id="employeeBirthDate" name="data_nascimento" type="date" autocomplete="bday"
                        max="<?php echo date("Y-m-d"); ?>" required />
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>Celular <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-phone"></i>
                      <input id="employeeCellphone" name="celular" type="tel" placeholder="(00) 00000-0000"
                        inputmode="tel" autocomplete="tel" maxlength="15" required />
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>RG <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-card-text"></i>
                      <input id="employeeRg" name="rg" type="text" placeholder="00.000.000-0" inputmode="numeric"
                        autocomplete="off" maxlength="12" required />
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>CPF <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-person-vcard"></i>
                      <input id="employeeCpf" name="cpf" type="text" placeholder="000.000.000-00" inputmode="numeric"
                        autocomplete="off" maxlength="14" required />
                    </div>
                  </label>
                </div>
              </fieldset>

              <fieldset class="employee-form-section">
                <legend class="employee-form-section-heading">
                  <span class="employee-section-number">02</span>
                  <i class="bi bi-buildings-fill" aria-hidden="true"></i>
                  <span class="employee-section-copy">
                    <strong>V&iacute;nculo corporativo</strong>
                    <small>Dados usados no perfil e na organiza&ccedil;&atilde;o da equipe</small>
                  </span>
                </legend>

                <div class="employee-fields-grid">
                  <label class="asset-field wide-field">
                    <span>E-mail corporativo <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-envelope"></i>
                      <input id="employeeEmail" name="email" type="email" placeholder="nome@titechsolutions.com.br"
                        autocomplete="email" maxlength="160" required />
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>Departamento <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-diagram-3"></i>
                      <select id="employeeDepartment" name="departamento" required>
                        <option value="">Selecione o departamento</option>
                        <?php foreach ($departamentos as $valorDepartamento => $rotuloDepartamento): ?>
                          <option value="<?php echo e($valorDepartamento); ?>"><?php echo e($rotuloDepartamento); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </label>

                  <label class="asset-field">
                    <span>Empresa <strong>*</strong></span>
                    <div class="input-shell">
                      <i class="bi bi-buildings"></i>
                      <input id="employeeCompany" name="empresa" type="text" placeholder="Nome da empresa"
                        autocomplete="organization" maxlength="150" required />
                    </div>
                  </label>
                </div>
              </fieldset>

              <fieldset class="employee-form-section">
                <legend class="employee-form-section-heading">
                  <span class="employee-section-number">03</span>
                  <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                  <span class="employee-section-copy">
                    <strong>Seguran&ccedil;a e acesso</strong>
                    <small>Defina o perfil e a senha inicial de entrada</small>
                  </span>
                </legend>

                <div class="employee-fields-grid">
                  <div class="asset-field wide-field">
                    <span>Perfil de acesso <strong>*</strong></span>
                    <?php if ($usuarioPodeGerenciarAdministradores): ?>
                      <div class="segment-control employee-role-control" id="employeeRoleControl" data-active="Colaborador"
                        role="radiogroup" aria-label="Perfil de acesso do funcion&aacute;rio">
                        <button class="active" data-role="Colaborador" type="button" role="radio" aria-checked="true">
                          <i class="bi bi-person-badge-fill"></i>
                          <span><strong>Colaborador</strong><small>Acesso definido por grupos</small></span>
                        </button>
                        <button data-role="Administrador" type="button" role="radio" aria-checked="false">
                          <i class="bi bi-shield-lock-fill"></i>
                          <span><strong>Administrador</strong><small>Acesso ampliado ao portal</small></span>
                        </button>
                      </div>
                    <?php else: ?>
                      <div class="employee-role-locked" aria-label="Perfil de acesso fixado como colaborador">
                        <span class="employee-role-locked-icon"><i class="bi bi-person-check-fill"></i></span>
                        <span>
                          <strong>Colaborador</strong>
                          <small>O acesso administrativo s&oacute; pode ser concedido por outro administrador.</small>
                        </span>
                        <i class="bi bi-lock-fill employee-role-lock" aria-hidden="true"></i>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="asset-field wide-field employee-password-field">
                    <label for="employeePassword">Senha inicial <strong>*</strong></label>
                    <div class="input-shell">
                      <i class="bi bi-key"></i>
                      <input id="employeePassword" name="senha" type="password" placeholder="M&iacute;nimo de 6 caracteres"
                        autocomplete="new-password" minlength="6" maxlength="128"
                        aria-describedby="employeePasswordStrengthText" required />
                      <button class="password-toggle" data-target="employeePassword" type="button"
                        aria-label="Mostrar senha">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>

                    <div class="password-meter" aria-live="polite">
                      <div class="meter-track">
                        <span id="employeePasswordStrengthBar"></span>
                      </div>
                      <small id="employeePasswordStrengthText">For&ccedil;a da senha: aguardando</small>
                    </div>
                  </div>
                </div>
              </fieldset>
            </div>

            <div id="employeeFormMessage" class="form-message employee-form-message" role="status" aria-live="polite">
            </div>

            <div class="employee-form-footer">
              <p class="employee-form-review-note">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Revise os dados antes de concluir o cadastro.</span>
              </p>

              <div class="asset-form-actions enhanced-form-actions">
                <button class="form-action-button employee-reset-button" type="reset">
                  <i class="bi bi-arrow-counterclockwise"></i>
                  <span>Limpar formul&aacute;rio</span>
                </button>

                <button id="employeeSubmitButton" class="form-action-button success-button" type="submit">
                  <i class="bi bi-person-plus-fill"></i>
                  <span>Cadastrar funcion&aacute;rio</span>
                </button>
              </div>
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
              <?php if ($usuarioPodeGerenciarAdministradores): ?>
                <li>Administrador tem acesso ampliado a configura&ccedil;&otilde;es e cadastros.</li>
              <?php else: ?>
                <li>Somente administradores podem criar ou promover outros administradores.</li>
              <?php endif; ?>
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
