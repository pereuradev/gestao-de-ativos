<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("cadastrar_locais", "Cadastro de localizacoes");

if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataLocal(?string $value): string
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

function garantirTabelaLocais(PDO $pdo): void
{
  // Estrutura criada por migration. Mantido para compatibilidade.
}

$usuario = $_SESSION["usuario"];
$csrfToken = e((string) $_SESSION["csrf_token"]);

$locais = [];
$totalLocais = 0;
$locaisAtivos = 0;
$locaisInativos = 0;
$erroBanco = "";

try {
  require __DIR__ . "/../Backend/Conexao.php";

  garantirTabelaLocais($pdo);

  $totalStmt = $pdo->prepare("select count(*)::int from public.locais");
  $totalStmt->execute();
  $totalLocais = (int) $totalStmt->fetchColumn();

  $ativosStmt = $pdo->prepare("
        select count(*)::int
          from public.locais
         where status = :status
    ");
  $ativosStmt->execute([":status" => "Ativo"]);
  $locaisAtivos = (int) $ativosStmt->fetchColumn();

  $inativosStmt = $pdo->prepare("
        select count(*)::int
          from public.locais
         where status = :status
    ");
  $inativosStmt->execute([":status" => "Inativo"]);
  $locaisInativos = (int) $inativosStmt->fetchColumn();

  $locaisStmt = $pdo->prepare("
        select id, nome, endereco, status, criado_em
          from public.locais
      order by criado_em desc, nome asc
    ");
  $locaisStmt->execute();
  $locais = $locaisStmt->fetchAll();
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os locais do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de localiza&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description"
    content="Cadastro de localiza&ccedil;&otilde;es para organizar os ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/cadastro-ativos.css?v=20260630-clean-form-card" />
  <link rel="stylesheet" href="../css/locais.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="../js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="../js/locais.js?v=20260624-common-ui" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
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
              <span style="--typewriter-min: 27ch">Cadastro de localiza&ccedil;&otilde;es</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="locais-visualizacao.php">
            <i class="bi bi-table"></i>
            Visualizar localiza&ccedil;&otilde;es
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero locations-hero" aria-labelledby="locationsRegistrationTitle">
        <div class="hero-content">
          <h2 id="locationsRegistrationTitle">
            <span class="typewriter-heading" style="--typewriter-min: 25ch" data-typewriter-loop
              data-typewriter-phrases="Localiza&ccedil;&otilde;es organizadas.|Endere&ccedil;os f&aacute;ceis de encontrar.|Controle por setor e unidade.">Localiza&ccedil;&otilde;es
              organizadas.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Registre unidades, setores, salas e pontos de armazenamento para vincular cada ativo
            ao local correto no sistema.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo das localizacoes">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-geo-alt-fill"></i>
          </div>

          <div>
            <span>Total de locais</span>
            <strong id="totalLocationsMetric"><?php echo e((string) $totalLocais); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-check-circle-fill"></i>
          </div>

          <div>
            <span>Ativos</span>
            <strong id="activeLocationsMetric"><?php echo e((string) $locaisAtivos); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-pause-circle-fill"></i>
          </div>

          <div>
            <span>Inativos</span>
            <strong id="inactiveLocationsMetric"><?php echo e((string) $locaisInativos); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="locations-layout" aria-label="Cadastro de localizacoes">
        <article class="content-card asset-form-card asset-form-card-enhanced location-form-card">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formul&aacute;rio</p>
              <h3>Cadastrar localiza&ccedil;&atilde;o</h3>
              <span class="card-subtitle">Use nomes claros como unidade, setor, sala ou arm&aacute;rio. O cadastro
                bloqueia duplicidades por nome.</span>
            </div>

          </div>

          <form id="locationForm" class="asset-form enhanced-asset-form" action="../Backend/cadastrar-local.php"
            method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid location-form-grid">
              <label class="asset-field priority-field">
                <span>Nome do local <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-geo-alt"></i>
                  <input name="nome" type="text" placeholder="Ex: Matriz - Estoque TI" maxlength="100"
                    autocomplete="off" required />
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-toggle-on"></i>
                  <select name="status" required>
                    <option value="Ativo" selected>Ativo</option>
                    <option value="Inativo">Inativo</option>
                  </select>
                </div>
              </label>

              <label class="asset-field wide-field">
                <span>Endere&ccedil;o ou refer&ecirc;ncia</span>
                <div class="input-shell">
                  <i class="bi bi-signpost-split"></i>
                  <input name="endereco" type="text" placeholder="Ex: 2&ordm; andar, sala 204, rack A" maxlength="160"
                    autocomplete="off" />
                </div>
              </label>
            </div>

            <div id="locationFormMessage" class="form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="locationSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-circle"></i>
                <span>Cadastrar local</span>
              </button>
            </div>
          </form>
        </article>
      </section>
    </main>
  </div>
</body>

</html>
