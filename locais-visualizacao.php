<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_locais", "Localizacoes");

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

$locais = [];
$totalLocais = 0;
$locaisAtivos = 0;
$locaisInativos = 0;
$erroBanco = "";

try {
  require __DIR__ . "/Backend/Conexao.php";

  garantirTabelaLocais($pdo);

  $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where status = 'Ativo')::int as ativos,
            count(*) filter (where status = 'Inativo')::int as inativos
          from public.locais
    ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalLocais = (int) ($resumo["total"] ?? 0);
  $locaisAtivos = (int) ($resumo["ativos"] ?? 0);
  $locaisInativos = (int) ($resumo["inativos"] ?? 0);

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

  <title>Localiza&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description" content="Visualizacao das localizacoes cadastradas para ativos da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="css/locais.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/locais.js?v=20260625-view-page" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <?php require __DIR__ . "/components/sidebar.php"; ?>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Visualiza&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 22ch">Localiza&ccedil;&otilde;es</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="locais.php">
            <i class="bi bi-plus-circle"></i>
            Nova localiza&ccedil;&atilde;o
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero locations-hero" aria-labelledby="locationsViewTitle">
        <div class="hero-content">
          <h2 id="locationsViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 24ch" data-typewriter-loop
              data-typewriter-phrases="Consulta de localiza&ccedil;&otilde;es.|Locais cadastrados.|Endere&ccedil;os organizados.">Consulta
              de localiza&ccedil;&otilde;es.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Visualize unidades, setores, salas e pontos de armazenamento para encontrar rapidamente
            onde cada ativo deve ser vinculado.
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

      <section class="content-card records-card location-view-card" aria-label="Tabela de localizacoes">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Visualiza&ccedil;&atilde;o</p>
            <h3>Localiza&ccedil;&otilde;es cadastradas</h3>
          </div>

          <div class="records-actions">
            <span id="locationResultCount"><?php echo e((string) count($locais)); ?> registros</span>
          </div>
        </div>

        <div class="location-filter-bar" aria-label="Filtros das localizacoes">
          <div class="search-box location-search">
            <i class="bi bi-search"></i>
            <input id="locationSearch" type="search" placeholder="Buscar local ou refer&ecirc;ncia"
              aria-label="Buscar local ou refer&ecirc;ncia" autocomplete="off" />
          </div>

          <select id="locationStatusFilter" aria-label="Filtrar localizacoes por status">
            <option value="todos">Todos os status</option>
            <option value="ativo">Ativos</option>
            <option value="inativo">Inativos</option>
          </select>

          <button id="clearLocationFilters" class="filter-clear-button" type="button">
            <i class="bi bi-x-circle"></i>
            <span>Limpar</span>
          </button>
        </div>

        <div class="records-table-wrap location-table-wrap">
          <table class="records-table location-table">
            <thead>
              <tr>
                <th>Local</th>
                <th>Status</th>
                <th>Criado em</th>
              </tr>
            </thead>
            <tbody id="locationTableBody">
              <?php foreach ($locais as $local): ?>
                <?php
                $nome = (string) ($local["nome"] ?? "");
                $endereco = (string) ($local["endereco"] ?? "");
                $status = (string) ($local["status"] ?? "Ativo");
                $search = strtolower(trim($nome . " " . $endereco));
                ?>
                <tr class="registration-row location-row" data-status="<?php echo e(strtolower($status)); ?>"
                  data-search="<?php echo e($search); ?>">
                  <td data-label="Local">
                    <strong><?php echo e($nome); ?></strong>
                    <span class="location-address">
                      <?php echo e($endereco !== "" ? $endereco : "Sem referencia informada"); ?>
                    </span>
                  </td>
                  <td data-label="Status">
                    <span class="status-badge <?php echo $status === "Ativo" ? "status-active" : "status-inactive"; ?>">
                      <?php echo e($status); ?>
                    </span>
                  </td>
                  <td data-label="Criado em"><?php echo e(formatarDataLocal((string) ($local["criado_em"] ?? ""))); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="locationEmptyState" class="empty-state records-empty" <?php echo $locais ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhuma localiza&ccedil;&atilde;o encontrada.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>
