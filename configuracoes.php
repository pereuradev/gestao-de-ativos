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

function campoPerfil(array $perfil, string $campo, string $padrao = "--"): string
{
    $valor = trim((string)($perfil[$campo] ?? ""));

    return $valor !== "" ? $valor : $padrao;
}

function formatarDataPerfil(?string $value): string
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

function iniciaisUsuario(string $nome): string
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

function statusClasseConfiguracao(string $status): string
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
$perfil = $usuario;
$erroBanco = "";

try {
    require __DIR__ . "/Backend/Conexao.php";

    $stmt = $pdo->prepare("
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
         where id = :id
            or lower(email) = lower(:email)
         limit 1
    ");

    $stmt->execute([
        ":id" => (string)($usuario["id"] ?? ""),
        ":email" => (string)($usuario["email"] ?? ""),
    ]);

    $perfilBanco = $stmt->fetch();

    if (is_array($perfilBanco)) {
        $perfil = array_merge($usuario, $perfilBanco);
    }
} catch (Throwable) {
    $erroBanco = "Nao foi possivel carregar todos os dados do banco. Mostrando informacoes da sessao.";
}

$nomeUsuarioTexto = campoPerfil($perfil, "nome_completo", "Usuario TI TECH");
$tipoUsuarioTexto = campoPerfil($perfil, "tipo_usuario", "Colaborador");
$emailUsuarioTexto = campoPerfil($perfil, "email");
$statusUsuarioTexto = campoPerfil($perfil, "status", "Ativo");
$departamentoUsuarioTexto = campoPerfil($perfil, "departamento");
$empresaUsuarioTexto = campoPerfil($perfil, "empresa");
$celularUsuarioTexto = campoPerfil($perfil, "celular");
$rgUsuarioTexto = campoPerfil($perfil, "rg");
$cpfUsuarioTexto = campoPerfil($perfil, "cpf");
$criadoEm = formatarDataPerfil((string)($perfil["criado_em"] ?? ""));
$atualizadoEm = formatarDataPerfil((string)($perfil["atualizado_em"] ?? ""));
$ultimoAcesso = date("d/m/Y H:i");
$codigoInterno = "TT-USER-" . str_pad(substr(preg_replace("/\D/", "", (string)($perfil["id"] ?? "")), 0, 3) ?: "001", 3, "0", STR_PAD_LEFT);
$isAdministrador = strtolower($tipoUsuarioTexto) === "administrador";

$nomeUsuario = e($nomeUsuarioTexto);
$tipoUsuario = e($tipoUsuarioTexto);
$emailUsuario = e($emailUsuarioTexto);
$statusUsuario = e($statusUsuarioTexto);
$departamentoUsuario = e($departamentoUsuarioTexto);
$empresaUsuario = e($empresaUsuarioTexto);
$celularUsuario = e($celularUsuarioTexto);
$rgUsuario = e($rgUsuarioTexto);
$cpfUsuario = e($cpfUsuarioTexto);
$iniciais = e(iniciaisUsuario($nomeUsuarioTexto));
$statusClasse = e(statusClasseConfiguracao($statusUsuarioTexto));
$codigoInternoEscapado = e($codigoInterno);

$permissoes = [
    ["label" => "Cadastrar funcionários", "allowed" => $isAdministrador],
    ["label" => "Editar ativos", "allowed" => true],
    ["label" => "Excluir registros", "allowed" => $isAdministrador],
    ["label" => "Gerar relatórios", "allowed" => $isAdministrador],
    ["label" => "Gerenciar usuários", "allowed" => $isAdministrador],
];

$atividades = [
    ["icon" => "bi-box-arrow-in-right", "title" => "Login realizado", "text" => "Acesso validado no portal interno.", "time" => $ultimoAcesso],
    ["icon" => "bi-sliders", "title" => "Preferências alteradas", "text" => "Configurações visuais salvas neste navegador.", "time" => "Hoje"],
    ["icon" => "bi-person-check", "title" => "Perfil consultado", "text" => "Dados principais carregados da sessão e do banco.", "time" => "Hoje"],
    ["icon" => "bi-hdd-network", "title" => "Ativo cadastrado", "text" => "Exemplo de evento pronto para histórico real.", "time" => "Mock"],
];
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Configura&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description" content="Painel de configura&ccedil;&otilde;es de conta, seguran&ccedil;a e prefer&ecirc;ncias do portal TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-settings-panel" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/configuracoes.css?v=20260624-settings-panel" />

  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-settings-panel" defer></script>
  <script src="js/configuracoes.js?v=20260624-settings-panel" defer></script>
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

        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
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

        <a class="nav-link active" href="configuracoes.php">
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

    <main class="main-area settings-page" data-user-role="<?php echo e(strtolower($tipoUsuarioTexto)); ?>">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Painel de controle</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 16ch">Configura&ccedil;&otilde;es</span><span aria-hidden="true"></span>
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

      <section class="hero-panel settings-hero" aria-labelledby="settingsTitle">
        <div class="hero-content">
          <p class="section-tag">Central do usu&aacute;rio</p>
          <h2 id="settingsTitle">
            <span
              class="typewriter-heading"
              style="--typewriter-min: 34ch"
              data-typewriter-loop
              data-typewriter-phrases="Conta, seguran&ccedil;a e experi&ecirc;ncia.|Seu ambiente, do seu jeito.|Prefer&ecirc;ncias com controle e clareza."
            >Conta, seguran&ccedil;a e experi&ecirc;ncia.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Personalize sua interface, acompanhe permiss&otilde;es, revise dados da conta
            e prepare integra&ccedil;&otilde;es futuras com PHP e Supabase.
          </p>
        </div>
      </section>

      <?php if ($erroBanco !== "") : ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="settings-overview" aria-label="Resumo das configura&ccedil;&otilde;es">
        <article class="content-card digital-badge-card" id="conta">
          <div class="badge-topline">
            <span>Cracha digital</span>
            <strong><?php echo $codigoInternoEscapado; ?></strong>
          </div>

          <div class="badge-main">
            <div class="profile-avatar large-avatar" aria-hidden="true"><?php echo $iniciais; ?></div>
            <div>
              <h2><?php echo $nomeUsuario; ?></h2>
              <p><?php echo $emailUsuario; ?></p>
              <div class="badge-tags">
                <span><?php echo $tipoUsuario; ?></span>
                <span class="status-badge <?php echo $statusClasse; ?>"><?php echo $statusUsuario; ?></span>
              </div>
            </div>
          </div>

          <div class="badge-grid">
            <div>
              <span>Departamento</span>
              <strong><?php echo $departamentoUsuario; ?></strong>
            </div>
            <div>
              <span>Empresa</span>
              <strong><?php echo $empresaUsuario; ?></strong>
            </div>
            <div>
              <span>&Uacute;ltimo acesso</span>
              <strong><?php echo e($ultimoAcesso); ?></strong>
            </div>
          </div>
        </article>

        <aside class="content-card settings-index" aria-label="Se&ccedil;&otilde;es da p&aacute;gina">
          <p class="section-tag">Se&ccedil;&otilde;es</p>
          <nav class="settings-section-nav">
            <a href="#conta"><i class="bi bi-person-badge"></i> Conta</a>
            <a href="#interface"><i class="bi bi-palette"></i> Interface</a>
            <a href="#seguranca"><i class="bi bi-shield-lock"></i> Seguran&ccedil;a</a>
            <a href="#notificacoes"><i class="bi bi-bell"></i> Notifica&ccedil;&otilde;es</a>
            <a href="#permissoes"><i class="bi bi-key"></i> Permiss&otilde;es</a>
            <a href="#sistema"><i class="bi bi-cpu"></i> Sistema</a>
          </nav>
        </aside>
      </section>

      <section class="settings-grid" aria-label="Painel de configura&ccedil;&otilde;es">
        <article class="content-card profile-card" aria-labelledby="profileTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Conta</p>
              <h3 id="profileTitle">Perfil operacional</h3>
            </div>
          </div>

          <div class="profile-details">
            <div class="profile-field"><span>Nome</span><strong><?php echo $nomeUsuario; ?></strong></div>
            <div class="profile-field"><span>Email</span><strong><?php echo $emailUsuario; ?></strong></div>
            <div class="profile-field"><span>Cargo</span><strong><?php echo $tipoUsuario; ?></strong></div>
            <div class="profile-field"><span>Departamento</span><strong><?php echo $departamentoUsuario; ?></strong></div>
            <div class="profile-field"><span>Celular</span><strong><?php echo $celularUsuario; ?></strong></div>
            <div class="profile-field"><span>RG</span><strong><?php echo $rgUsuario; ?></strong></div>
            <div class="profile-field"><span>CPF</span><strong><?php echo $cpfUsuario; ?></strong></div>
            <div class="profile-field"><span>Criado em</span><strong><?php echo e($criadoEm); ?></strong></div>
            <div class="profile-field"><span>Atualizado em</span><strong><?php echo e($atualizadoEm); ?></strong></div>
          </div>
        </article>

        <article class="content-card preferences-card" id="interface" aria-labelledby="interfaceTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Interface</p>
              <h3 id="interfaceTitle">Prefer&ecirc;ncias visuais</h3>
            </div>
            <button class="secondary-button compact-preference-button" id="resetPreferences" type="button">
              <i class="bi bi-arrow-counterclockwise"></i>
              Restaurar
            </button>
          </div>

          <form class="preferences-form" id="preferencesForm">
            <fieldset class="preference-group">
              <legend>Prefer&ecirc;ncia de cor</legend>
              <div class="accent-options" role="radiogroup" aria-label="Prefer&ecirc;ncia de cor">
                <label class="accent-option accent-teal"><input type="radio" name="accent" value="teal" /><span></span>TI TECH</label>
                <label class="accent-option accent-green"><input type="radio" name="accent" value="green" /><span></span>Verde positivo</label>
                <label class="accent-option accent-blue"><input type="radio" name="accent" value="blue" /><span></span>Azul tecnologia</label>
                <label class="accent-option accent-violet"><input type="radio" name="accent" value="violet" /><span></span>Violeta</label>
              </div>
            </fieldset>

            <fieldset class="preference-group">
              <legend>Modo de tela</legend>
              <div class="segmented-control three-options" role="radiogroup" aria-label="Modo de tela">
                <label><input type="radio" name="theme" value="dark" /><span><i class="bi bi-moon-stars-fill"></i> Escuro</span></label>
                <label><input type="radio" name="theme" value="light" /><span><i class="bi bi-sun-fill"></i> Claro</span></label>
                <label><input type="radio" name="theme" value="auto" /><span><i class="bi bi-circle-half"></i> Auto</span></label>
              </div>
            </fieldset>

            <fieldset class="preference-group">
              <legend>Ajustes de UX</legend>
              <div class="toggle-list">
                <label class="toggle-row">
                  <span><strong>Interface compacta</strong><small>Reduz espa&ccedil;amentos para ver mais informa&ccedil;&otilde;es.</small></span>
                  <input type="checkbox" id="densityToggle" name="density" value="compact" />
                </label>
                <label class="toggle-row">
                  <span><strong>Reduzir anima&ccedil;&otilde;es</strong><small>Deixa transi&ccedil;&otilde;es mais discretas.</small></span>
                  <input type="checkbox" id="motionToggle" name="motion" value="reduced" />
                </label>
                <label class="toggle-row">
                  <span><strong>Realce do cursor</strong><small>Aumenta o feedback visual em links, bot&otilde;es e campos.</small></span>
                  <input type="checkbox" id="cursorToggle" name="cursor" value="enhanced" />
                </label>
              </div>
            </fieldset>

            <div class="preference-preview" aria-live="polite">
              <div><span>Preview</span><strong>Indicador positivo</strong></div>
              <span class="status-badge status-active">Ativo</span>
            </div>
          </form>
          <div class="form-message success" id="preferencesMessage" role="status"></div>
        </article>

        <article class="content-card security-card wide-card" id="seguranca" aria-labelledby="securityTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Seguran&ccedil;a</p>
              <h3 id="securityTitle">Prote&ccedil;&atilde;o da conta</h3>
            </div>
            <div class="security-score" aria-label="Score de seguran&ccedil;a">
              <span id="securityScoreValue">42</span>
              <small>/100</small>
            </div>
          </div>

          <div class="security-layout">
            <form class="password-form" id="passwordForm">
              <label class="asset-field">
                <span>Senha atual</span>
                <span class="input-shell"><i class="bi bi-lock"></i><input id="currentPassword" type="password" autocomplete="current-password" /></span>
              </label>
              <label class="asset-field">
                <span>Nova senha</span>
                <span class="input-shell"><i class="bi bi-key"></i><input id="newPassword" type="password" autocomplete="new-password" /></span>
              </label>
              <label class="asset-field">
                <span>Confirmar nova senha</span>
                <span class="input-shell"><i class="bi bi-check2-circle"></i><input id="confirmPassword" type="password" autocomplete="new-password" /></span>
              </label>

              <div class="password-strength" aria-live="polite">
                <div class="strength-track"><span id="strengthBar"></span></div>
                <strong id="strengthLabel">Digite uma nova senha</strong>
              </div>

              <ul class="password-rules" id="passwordRules">
                <li data-rule="length">Pelo menos 8 caracteres</li>
                <li data-rule="uppercase">Letra mai&uacute;scula</li>
                <li data-rule="number">N&uacute;mero</li>
                <li data-rule="special">Caractere especial</li>
                <li data-rule="match">Confirma&ccedil;&atilde;o igual</li>
              </ul>

              <button class="primary-button" id="updatePasswordButton" type="submit">
                <i class="bi bi-shield-check"></i>
                Atualizar senha
              </button>
            </form>

            <div class="security-actions">
              <article class="action-tile">
                <i class="bi bi-phone-lock"></i>
                <div><strong>Verifica&ccedil;&atilde;o em duas etapas</strong><span>Interface pronta para integrar com Supabase Auth.</span></div>
                <button class="mini-action" data-feature-button type="button">Configurar</button>
              </article>
              <article class="action-tile">
                <i class="bi bi-display"></i>
                <div><strong>Sess&otilde;es ativas</strong><span>Preparado para listar dispositivos conectados.</span></div>
                <button class="mini-action" data-feature-button type="button">Revisar</button>
              </article>
              <article class="action-tile danger-tile">
                <i class="bi bi-box-arrow-right"></i>
                <div><strong>Sair de todos os dispositivos</strong><span>A&ccedil;&atilde;o cr&iacute;tica aguardando backend.</span></div>
                <button class="mini-action danger-action" id="logoutAllDevices" type="button">Solicitar</button>
              </article>
            </div>
          </div>
        </article>

        <article class="content-card notifications-card" id="notificacoes" aria-labelledby="notificationsTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Notifica&ccedil;&otilde;es</p>
              <h3 id="notificationsTitle">Alertas inteligentes</h3>
            </div>
          </div>
          <div class="toggle-list settings-toggle-list">
            <label class="toggle-row"><span><strong>Novo ativo cadastrado</strong><small>Avisar quando novos equipamentos entrarem no invent&aacute;rio.</small></span><input type="checkbox" data-setting="notify-new-asset" /></label>
            <label class="toggle-row"><span><strong>Altera&ccedil;&otilde;es em funcion&aacute;rios</strong><small>Acompanhar mudan&ccedil;as de status, cargo ou departamento.</small></span><input type="checkbox" data-setting="notify-employees" /></label>
            <label class="toggle-row"><span><strong>Login suspeito</strong><small>Prioridade alta para comportamento incomum.</small></span><input type="checkbox" data-setting="notify-suspicious-login" /></label>
            <label class="toggle-row"><span><strong>Relat&oacute;rios dispon&iacute;veis</strong><small>Receber aviso quando novos dados estiverem prontos.</small></span><input type="checkbox" data-setting="notify-reports" /></label>
            <label class="toggle-row"><span><strong>Pend&ecirc;ncias administrativas</strong><small>Alertar sobre cadastros incompletos ou inconsistentes.</small></span><input type="checkbox" data-setting="notify-admin-pending" /></label>
            <label class="toggle-row smart-rule"><span><strong>Ativo sem patrim&ocirc;nio</strong><small>Regra inteligente para cadastro incompleto.</small></span><input type="checkbox" data-setting="notify-asset-without-property" /></label>
            <label class="toggle-row smart-rule"><span><strong>Funcion&aacute;rio sem departamento</strong><small>Ajuda a manter a base de colaboradores organizada.</small></span><input type="checkbox" data-setting="notify-employee-without-department" /></label>
          </div>
        </article>

        <article class="content-card dashboard-card" aria-labelledby="dashboardPrefsTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Dashboard</p>
              <h3 id="dashboardPrefsTitle">Prefer&ecirc;ncias de navega&ccedil;&atilde;o</h3>
            </div>
          </div>
          <div class="preference-select-grid">
            <label class="select-field">
              <span>P&aacute;gina inicial padr&atilde;o</span>
              <select data-setting="home-page">
                <option value="dashboard">Dashboard</option>
                <option value="funcionarios">Funcion&aacute;rios</option>
                <option value="ativos">Ativos</option>
                <option value="relatorios">Relat&oacute;rios</option>
                <option value="cadastro">Cadastro</option>
              </select>
            </label>
            <label class="select-field">
              <span>Visualiza&ccedil;&atilde;o preferida</span>
              <select data-setting="dashboard-view">
                <option value="cards">Cards</option>
                <option value="tabela">Tabela</option>
                <option value="grafico">Gr&aacute;fico</option>
              </select>
            </label>
            <label class="toggle-row full-toggle"><span><strong>Manter filtros salvos</strong><small>Restaurar filtros usados em consultas anteriores.</small></span><input type="checkbox" data-setting="saved-filters" /></label>
          </div>
        </article>

        <article class="content-card work-mode-card wide-card" aria-labelledby="workModeTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Modo de trabalho</p>
              <h3 id="workModeTitle">Foco operacional</h3>
            </div>
          </div>
          <div class="work-mode-grid" role="radiogroup" aria-label="Modo de trabalho">
            <label><input type="radio" name="workMode" value="admin" data-work-mode /><span><i class="bi bi-command"></i><strong>Administrador</strong><small>Controle de usu&aacute;rios, permiss&otilde;es e decis&otilde;es.</small></span></label>
            <label><input type="radio" name="workMode" value="support" data-work-mode /><span><i class="bi bi-tools"></i><strong>Suporte</strong><small>Foco em ativos, manuten&ccedil;&atilde;o, hist&oacute;rico e diagn&oacute;stico.</small></span></label>
            <label><input type="radio" name="workMode" value="register" data-work-mode /><span><i class="bi bi-folder-plus"></i><strong>Cadastro</strong><small>Agilidade para inserir ativos, marcas e localiza&ccedil;&otilde;es.</small></span></label>
            <label><input type="radio" name="workMode" value="view" data-work-mode /><span><i class="bi bi-eye"></i><strong>Visualiza&ccedil;&atilde;o</strong><small>Consulta segura, leitura e acompanhamento de indicadores.</small></span></label>
          </div>
        </article>

        <article class="content-card permissions-card" id="permissoes" aria-labelledby="permissionsTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Permiss&otilde;es</p>
              <h3 id="permissionsTitle">N&iacute;vel de acesso</h3>
            </div>
          </div>
          <div class="permission-list">
            <?php foreach ($permissoes as $permissao) : ?>
              <div class="permission-item <?php echo $permissao["allowed"] ? "allowed" : "blocked"; ?>">
                <span><i class="bi <?php echo $permissao["allowed"] ? "bi-check-circle-fill" : "bi-lock-fill"; ?>"></i><?php echo e($permissao["label"]); ?></span>
                <strong><?php echo $permissao["allowed"] ? "Permitido" : "Bloqueado"; ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="content-card activity-card" aria-labelledby="activityTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Hist&oacute;rico</p>
              <h3 id="activityTitle">Linha do tempo</h3>
            </div>
          </div>
          <ol class="activity-timeline">
            <?php foreach ($atividades as $atividade) : ?>
              <li>
                <i class="bi <?php echo e($atividade["icon"]); ?>"></i>
                <div>
                  <strong><?php echo e($atividade["title"]); ?></strong>
                  <span><?php echo e($atividade["text"]); ?></span>
                  <small><?php echo e($atividade["time"]); ?></small>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </article>

        <article class="content-card diagnostics-card wide-card" id="sistema" aria-labelledby="systemTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Sistema</p>
              <h3 id="systemTitle">Diagn&oacute;stico para suporte</h3>
            </div>
            <button class="secondary-button compact-preference-button" id="copyDiagnostics" type="button">
              <i class="bi bi-clipboard-check"></i>
              Copiar informa&ccedil;&otilde;es
            </button>
          </div>
          <div class="diagnostics-grid">
            <div><span>Navegador</span><strong id="diagBrowser">--</strong></div>
            <div><span>Sistema operacional</span><strong id="diagOs">--</strong></div>
            <div><span>Largura da tela</span><strong id="diagWidth">--</strong></div>
            <div><span>Status</span><strong id="diagOnline">--</strong></div>
            <div><span>Idioma</span><strong id="diagLanguage">--</strong></div>
            <div><span>Data/hora local</span><strong id="diagTime">--</strong></div>
            <div><span>Vers&atilde;o</span><strong>TI TECH Assets v1.4.0</strong></div>
          </div>
        </article>
      </section>

      <div class="settings-toast" id="settingsToast" role="status" aria-live="polite"></div>
    </main>
  </div>
</body>

</html>
