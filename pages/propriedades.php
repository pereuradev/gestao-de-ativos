<?php

declare(strict_types=1);

// Prepara as métricas, a listagem e o formulário de cadastro de propriedades.
session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("cadastrar_propriedades", "Cadastro de propriedades");

// Reutiliza um token por sessão para proteger formulários e operações de alteração contra CSRF.
if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataPropriedade(?string $value): string
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
$csrfToken = e((string) $_SESSION["csrf_token"]);

$propriedades = [];
$totalPropriedades = 0;
$propriedadesAtivas = 0;
$propriedadesInativas = 0;
$erroBanco = "";

try {
  // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
  require __DIR__ . "/../Backend/Conexao.php";

  $totalStmt = $pdo->prepare("select count(*)::int from public.propriedade_ativos");
  $totalStmt->execute();
  $totalPropriedades = (int) $totalStmt->fetchColumn();

  $ativasStmt = $pdo->prepare("
        select count(*)::int
          from public.propriedade_ativos
         where status = :status
    ");
  $ativasStmt->execute([":status" => "Ativa"]);
  $propriedadesAtivas = (int) $ativasStmt->fetchColumn();

  $inativasStmt = $pdo->prepare("
        select count(*)::int
          from public.propriedade_ativos
         where status = :status
    ");
  $inativasStmt->execute([":status" => "Inativa"]);
  $propriedadesInativas = (int) $inativasStmt->fetchColumn();

  $propriedadesStmt = $pdo->prepare("
        select id, nome, status, criado_em, atualizado_em
          from public.propriedade_ativos
      order by nome asc
    ");
  $propriedadesStmt->execute();
  $propriedades = $propriedadesStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar as propriedades do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de propriedades | TI TECH Solutions</title>
  <meta name="description"
    content="Cadastro de propriedades para padronizar o cadastro de ativos da TI TECH Solutions" />
  <!-- Identidade visual, tipografia e ícones usados pela página. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados e regras específicas deste fluxo. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260720-sidebar-role-accent" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260630-clean-form-card" />
  <link rel="stylesheet" href="../css/propriedades.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <!-- Scripts da interface; os módulos compartilhados devem carregar antes do script da página. -->
  <script src="../js/animations/efeito-digitacao.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260721-js-structure" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260721-js-structure" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260721-js-structure" defer></script>
  <script src="../js/base-interface.js?v=20260721-js-structure" defer></script>
  <script src="../js/pages/gerenciamento-propriedades.js?v=20260626-properties" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <!-- Navegação compartilhada entre as áreas autenticadas. -->
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Cadastros</p>
            <h1>
              <span style="--typewriter-min: 18ch">Cadastro de propriedades</span><span aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="propriedades-visualizacao.php">
            <i class="bi bi-table"></i>
            Visualizar propriedades
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <!-- Apresenta o papel das propriedades na classificação dos ativos. -->
      <section class="hero-panel compact-hero brand-partners-hero" aria-labelledby="brandsRegistrationTitle">
        <div class="hero-content">
          <h2 id="brandsRegistrationTitle">
            <span class="typewriter-heading" style="--typewriter-min: 22ch" data-typewriter-loop
              data-typewriter-phrases="Propriedades padronizadas.|Menos erro no cadastro.|Sele&ccedil;&atilde;o direta nos ativos.">Propriedades
              padronizadas.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Cadastre propriedades de ativos uma vez para selecionar depois no cadastro de ativos,
            mantendo os nomes consistentes no banco.
          </p>
        </div>
      </section>

      <!-- Indicadores gerais calculados no servidor. -->
      <section class="metrics-grid" aria-label="Resumo das propriedades">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-tags-fill"></i>
          </div>

          <div>
            <span>Total de propriedades</span>
            <strong id="totalBrandsMetric"><?php echo e((string) $totalPropriedades); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-check-circle-fill"></i>
          </div>

          <div>
            <span>Ativas</span>
            <strong id="activeBrandsMetric"><?php echo e((string) $propriedadesAtivas); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-pause-circle-fill"></i>
          </div>

          <div>
            <span>Inativas</span>
            <strong id="inactiveBrandsMetric"><?php echo e((string) $propriedadesInativas); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <!-- Formulário protegido por CSRF e listagem filtrável das propriedades. -->
      <section class="brands-layout" aria-label="Cadastro de propriedades">
        <article class="content-card asset-form-card asset-form-card-enhanced brand-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formul&aacute;rio</p>
              <h3>Cadastrar propriedade</h3>
              <span class="card-subtitle">Use o nome oficial ou mais conhecido da propriedade. O cadastro bloqueia
                duplicidades por nome.</span>
            </div>
          </div>

          <form id="brandForm" class="asset-form enhanced-asset-form" action="../Backend/cadastrar-propriedade.php"
            method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid brand-form-grid">
              <label class="asset-field priority-field">
                <span>Nome da propriedade <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-building"></i>
                  <input name="nome" type="text" placeholder="Ex: Zebra, Honeywell, Samsung" maxlength="80"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-toggle-on"></i>
                  <select name="status" required>
                    <option value="Ativa" selected>Ativa</option>
                    <option value="Inativa">Inativa</option>
                  </select>
                </div>
              </label>
            </div>

            <div id="brandFormMessage" class="form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="brandSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-circle"></i>
                <span>Cadastrar propriedade</span>
              </button>
            </div>
          </form>
        </article>
      </section>
    </main>
  </div>
</body>

</html>
