<?php

declare(strict_types=1);

header("Content-Type: text/html; charset=UTF-8");
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

$recursoPermissaoNegada = "";

if (($_GET["permissao"] ?? "") === "negada") {
  $recursoPermissaoNegada = (string) ($_SESSION["permission_denied_resource"] ?? "esta area");
  unset($_SESSION["permission_denied_resource"]);
}

function e(string $valor): string
{
  return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}

$usuario = $_SESSION["usuario"];
$tipoUsuario = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$usuarioAdministrador = in_array($tipoUsuario, ["adm", "admin", "administrador"], true);
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>P&aacute;gina Inicial | TI TECH Solutions</title>
  <meta name="description"
    content="P&aacute;gina inicial do portal interno de gest&atilde;o de ativos da TI TECH Solutions" />

  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260731-sidebar-compact" />
  <link rel="stylesheet" href="../css/pagina-inicial.css?v=20260803-operational-home" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260803-desktop-density" />

  <script src="../js/animations/efeito-digitacao.js?v=20260803-static-headings" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260731-sidebar-compact" defer></script>
  <script src="../js/base-interface.js?v=20260730-sidebar-contract" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading" data-accent="teal">
  <div class="app-shell">
    <?php require __DIR__ . "/../components/sidebar.php"; ?>
    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Portal TI TECH</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch"
                data-typewriter-phrases="Gest&atilde;o de ativos.|Opera&ccedil;&atilde;o conectada.|Acesso centralizado.">P&aacute;gina
                inicial</span><span aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button" aria-label="Selecionar tema da interface">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel dashboard-hero" aria-labelledby="welcomeTitle">
        <div class="hero-content">
          <p class="section-tag">P&aacute;gina inicial</p>
          <h2 id="welcomeTitle">
            <span class="typewriter-heading" style="--typewriter-min: 30ch" data-typewriter-loop
              data-typewriter-phrases="Controle de ativos conectado.|Invent&aacute;rio sincronizado.|Decis&otilde;es mais r&aacute;pidas.">Controle
              de ativos conectado.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Acesse rapidamente o invent&aacute;rio, o dashboard e os fluxos principais do portal.
          </p>
        </div>
      </section>

      <div class="home-shortcuts-layout">
        <section class="content-card quick-actions-card" aria-labelledby="quickActionsTitle">
          <header>
            <div>
              <p class="section-tag">Acesso direto</p>
              <h2 id="quickActionsTitle">A&ccedil;&otilde;es r&aacute;pidas</h2>
              <p>Continue seu trabalho sem precisar procurar no menu.</p>
            </div>
          </header>

          <nav class="quick-actions-grid" aria-label="A&ccedil;&otilde;es r&aacute;pidas do sistema">
            <a class="quick-action-link" href="dashboard.php" data-quick-action="dashboard">
              <span class="quick-action-icon" aria-hidden="true">
                <i class="bi bi-bar-chart-line"></i>
              </span>
              <span class="quick-action-content">
                <strong>Abrir dashboard</strong>
                <small>Acompanhe indicadores e distribui&ccedil;&otilde;es.</small>
              </span>
            </a>

            <a class="quick-action-link" href="ativos.php" data-quick-action="inventory">
              <span class="quick-action-icon" aria-hidden="true">
                <i class="bi bi-box-seam"></i>
              </span>
              <span class="quick-action-content">
                <strong>Consultar ativos</strong>
                <small>Pesquise, filtre e exporte o invent&aacute;rio.</small>
              </span>
            </a>

            <a class="quick-action-link" href="cadastro-ativos.php" data-quick-action="create">
              <span class="quick-action-icon" aria-hidden="true">
                <i class="bi bi-plus-square"></i>
              </span>
              <span class="quick-action-content">
                <strong>Cadastrar ativos</strong>
                <small>Adicione novos itens ao invent&aacute;rio.</small>
              </span>
            </a>

            <a class="quick-action-link" href="configuracoes.php" data-quick-action="settings">
              <span class="quick-action-icon" aria-hidden="true">
                <i class="bi bi-gear-wide-connected"></i>
              </span>
              <span class="quick-action-content">
                <strong>Configura&ccedil;&otilde;es</strong>
                <small>Personalize sua conta e a interface.</small>
              </span>
            </a>
          </nav>
        </section>

        <nav class="company-access-grid" aria-label="Canais oficiais da TI TECH">
          <a class="content-card company-access-card" href="https://www.titechsolutions.com.br/" target="_blank"
            rel="noopener noreferrer" data-company-access="website">
            <span class="company-access-icon" aria-hidden="true">
              <i class="bi bi-globe2"></i>
            </span>

            <span class="company-access-content">
              <small>Institucional</small>
              <strong>Site da empresa</strong>
              <span>Conhe&ccedil;a a TI TECH, seus servi&ccedil;os e suas solu&ccedil;&otilde;es.</span>
            </span>

            <i class="bi bi-box-arrow-up-right company-access-arrow" aria-hidden="true"></i>
          </a>

          <a class="content-card company-access-card" href="https://loja.titechsolutions.com.br/" target="_blank"
            rel="noopener noreferrer" data-company-access="store">
            <span class="company-access-icon" aria-hidden="true">
              <i class="bi bi-bag"></i>
            </span>

            <span class="company-access-content">
              <small>Produtos</small>
              <strong>Loja da empresa</strong>
              <span>Consulte o cat&aacute;logo de equipamentos e tecnologias.</span>
            </span>

            <i class="bi bi-box-arrow-up-right company-access-arrow" aria-hidden="true"></i>
          </a>
        </nav>
      </div>

      <section class="content-card social-links-card" aria-labelledby="socialLinksTitle">
        <header class="social-links-header">
          <div>
            <p class="section-tag">Conecte-se com a TI TECH</p>
            <h2 id="socialLinksTitle">Nossas redes sociais</h2>
          </div>
          <p>Acompanhe novidades, produtos e conte&uacute;dos da empresa.</p>
        </header>

        <nav class="social-links-grid" aria-label="Redes sociais da TI TECH">
          <a class="social-link" href="https://www.linkedin.com/company/titechsolutionss/" target="_blank"
            rel="noopener noreferrer" aria-label="Acessar o LinkedIn da TI TECH">
            <span class="social-link-icon" aria-hidden="true">
              <i class="bi bi-linkedin"></i>
            </span>
            <strong>LinkedIn</strong>
            <i class="bi bi-box-arrow-up-right social-link-arrow" aria-hidden="true"></i>
          </a>

          <a class="social-link" href="https://www.youtube.com/@titechsolutions_BR" target="_blank"
            rel="noopener noreferrer" aria-label="Acessar o YouTube da TI TECH">
            <span class="social-link-icon" aria-hidden="true">
              <i class="bi bi-youtube"></i>
            </span>
            <strong>YouTube</strong>
            <i class="bi bi-box-arrow-up-right social-link-arrow" aria-hidden="true"></i>
          </a>

          <a class="social-link" href="https://www.instagram.com/titechsolutions/" target="_blank"
            rel="noopener noreferrer" aria-label="Acessar o Instagram da TI TECH">
            <span class="social-link-icon" aria-hidden="true">
              <i class="bi bi-instagram"></i>
            </span>
            <strong>Instagram</strong>
            <i class="bi bi-box-arrow-up-right social-link-arrow" aria-hidden="true"></i>
          </a>

          <a class="social-link" href="https://www.facebook.com/titechsolutions/" target="_blank"
            rel="noopener noreferrer" aria-label="Acessar o Facebook da TI TECH">
            <span class="social-link-icon" aria-hidden="true">
              <i class="bi bi-facebook"></i>
            </span>
            <strong>Facebook</strong>
            <i class="bi bi-box-arrow-up-right social-link-arrow" aria-hidden="true"></i>
          </a>
        </nav>
      </section>
    </main>
  </div>
</body>

</html>
