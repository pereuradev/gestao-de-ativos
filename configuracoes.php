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

$nomeUsuario = e(campoPerfil($perfil, "nome_completo", "Usuario"));
$tipoUsuario = e(campoPerfil($perfil, "tipo_usuario", "Colaborador"));
$emailUsuario = e(campoPerfil($perfil, "email"));
$statusUsuario = campoPerfil($perfil, "status", "Ativo");
$departamentoUsuario = e(campoPerfil($perfil, "departamento"));
$empresaUsuario = e(campoPerfil($perfil, "empresa"));
$celularUsuario = e(campoPerfil($perfil, "celular"));
$rgUsuario = e(campoPerfil($perfil, "rg"));
$cpfUsuario = e(campoPerfil($perfil, "cpf"));
$criadoEm = formatarDataPerfil((string)($perfil["criado_em"] ?? ""));
$atualizadoEm = formatarDataPerfil((string)($perfil["atualizado_em"] ?? ""));
$iniciais = e(iniciaisUsuario((string)campoPerfil($perfil, "nome_completo", "Usuario")));
$statusClasse = e(statusClasseConfiguracao($statusUsuario));
$statusUsuarioEscapado = e($statusUsuario);
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Configura&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description" content="Configura&ccedil;&otilde;es de perfil e experi&ecirc;ncia do portal interno TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-settings" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/configuracoes.css?v=20260624-settings" />

  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-settings" defer></script>
  <script src="js/configuracoes.js?v=20260624-settings" defer></script>
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

    <main class="main-area settings-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Prefer&ecirc;ncias</p>
            <h1>
              <span
                class="typewriter-heading"
                style="--typewriter-min: 16ch"
              >Configura&ccedil;&otilde;es</span><span aria-hidden="true"></span>
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
              style="--typewriter-min: 28ch"
              data-typewriter-loop
              data-typewriter-phrases="Seu ambiente, do seu jeito.|Perfil e experi&ecirc;ncia conectados.|Ajustes simples para trabalhar melhor."
            >Seu ambiente, do seu jeito.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Veja seus dados de funcion&aacute;rio e personalize a apar&ecirc;ncia do sistema
            com prefer&ecirc;ncias salvas neste navegador.
          </p>
        </div>
      </section>

      <?php if ($erroBanco !== "") : ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="settings-summary-grid" aria-label="Resumo da conta">
        <article class="metric-card settings-metric">
          <div class="metric-icon">
            <i class="bi bi-person-badge-fill"></i>
          </div>
          <div>
            <span>Perfil</span>
            <strong><?php echo $tipoUsuario; ?></strong>
            <p>Tipo de acesso no sistema</p>
          </div>
        </article>

        <article class="metric-card settings-metric">
          <div class="metric-icon positive-icon">
            <i class="bi bi-shield-check"></i>
          </div>
          <div>
            <span>Status</span>
            <strong>
              <span class="status-badge <?php echo $statusClasse; ?>"><?php echo $statusUsuarioEscapado; ?></span>
            </strong>
            <p>Condi&ccedil;&atilde;o atual do funcion&aacute;rio</p>
          </div>
        </article>

        <article class="metric-card settings-metric">
          <div class="metric-icon">
            <i class="bi bi-building"></i>
          </div>
          <div>
            <span>Empresa</span>
            <strong><?php echo $empresaUsuario; ?></strong>
            <p>V&iacute;nculo cadastrado no banco</p>
          </div>
        </article>
      </section>

      <section class="settings-layout" aria-label="Configura&ccedil;&otilde;es da conta">
        <article class="content-card profile-card" aria-labelledby="profileTitle">
          <div class="profile-heading">
            <div class="profile-avatar" aria-hidden="true"><?php echo $iniciais; ?></div>
            <div>
              <p class="section-tag">Funcion&aacute;rio</p>
              <h3 id="profileTitle"><?php echo $nomeUsuario; ?></h3>
              <p><?php echo $emailUsuario; ?></p>
            </div>
          </div>

          <div class="profile-details">
            <div class="profile-field">
              <span>Departamento</span>
              <strong><?php echo $departamentoUsuario; ?></strong>
            </div>
            <div class="profile-field">
              <span>Celular</span>
              <strong><?php echo $celularUsuario; ?></strong>
            </div>
            <div class="profile-field">
              <span>RG</span>
              <strong><?php echo $rgUsuario; ?></strong>
            </div>
            <div class="profile-field">
              <span>CPF</span>
              <strong><?php echo $cpfUsuario; ?></strong>
            </div>
            <div class="profile-field">
              <span>Cadastrado em</span>
              <strong><?php echo e($criadoEm); ?></strong>
            </div>
            <div class="profile-field">
              <span>Atualizado em</span>
              <strong><?php echo e($atualizadoEm); ?></strong>
            </div>
          </div>
        </article>

        <article class="content-card preferences-card" aria-labelledby="preferencesTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Experi&ecirc;ncia</p>
              <h3 id="preferencesTitle">Prefer&ecirc;ncias visuais</h3>
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
                <label class="accent-option accent-teal">
                  <input type="radio" name="accent" value="teal" />
                  <span></span>
                  TI TECH
                </label>
                <label class="accent-option accent-green">
                  <input type="radio" name="accent" value="green" />
                  <span></span>
                  Verde positivo
                </label>
                <label class="accent-option accent-blue">
                  <input type="radio" name="accent" value="blue" />
                  <span></span>
                  Azul tecnologia
                </label>
                <label class="accent-option accent-violet">
                  <input type="radio" name="accent" value="violet" />
                  <span></span>
                  Violeta
                </label>
              </div>
            </fieldset>

            <fieldset class="preference-group">
              <legend>Modo de tela</legend>
              <div class="segmented-control" role="radiogroup" aria-label="Modo de tela">
                <label>
                  <input type="radio" name="theme" value="dark" />
                  <span><i class="bi bi-moon-stars-fill"></i> Escuro</span>
                </label>
                <label>
                  <input type="radio" name="theme" value="light" />
                  <span><i class="bi bi-sun-fill"></i> Claro</span>
                </label>
              </div>
            </fieldset>

            <fieldset class="preference-group">
              <legend>Ajustes de UX</legend>
              <div class="toggle-list">
                <label class="toggle-row">
                  <span>
                    <strong>Interface compacta</strong>
                    <small>Reduz espa&ccedil;amentos para ver mais informa&ccedil;&otilde;es na tela.</small>
                  </span>
                  <input type="checkbox" id="densityToggle" name="density" value="compact" />
                </label>

                <label class="toggle-row">
                  <span>
                    <strong>Reduzir anima&ccedil;&otilde;es</strong>
                    <small>Deixa transi&ccedil;&otilde;es mais discretas para uma navega&ccedil;&atilde;o direta.</small>
                  </span>
                  <input type="checkbox" id="motionToggle" name="motion" value="reduced" />
                </label>
              </div>
            </fieldset>

            <div class="preference-preview" aria-live="polite">
              <div>
                <span>Preview</span>
                <strong>Indicador positivo</strong>
              </div>
              <span class="status-badge status-active">Ativo</span>
            </div>
          </form>

          <div class="form-message success" id="preferencesMessage" role="status"></div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>
