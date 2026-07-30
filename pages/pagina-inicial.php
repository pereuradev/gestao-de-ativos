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

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260727-sidebar-layout" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />

  <script src="../js/animations/efeito-digitacao.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260730-sidebar-init" defer></script>
  <script src="../js/base-interface.js?v=20260730-sidebar-init" defer></script>
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
    </main>
  </div>
</body>

</html>
