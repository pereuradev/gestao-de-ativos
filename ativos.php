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

function formatarDataAtivo(?string $value): string
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
$nomeUsuario = e((string)($usuario["nome_completo"] ?? "Usuario"));
$tipoUsuario = e((string)($usuario["tipo_usuario"] ?? ""));
$categoriaFiltro = trim((string)($_GET["categoria"] ?? ""));

$ativos = [];
$categorias = [];
$marcas = [];
$totalAtivos = 0;
$ativosDisponiveis = 0;
$erroBanco = "";
$statusPadrao = "DisponÃ­vel";
$statusOptions = [
    "DisponÃ­vel",
    "Em uso",
    "HomologaÃ§Ã£o",
    "ManutenÃ§Ã£o",
];

try {
    require __DIR__ . "/Backend/Conexao.php";
    require __DIR__ . "/Backend/status-ativos.php";

    $statusOptions = nomesStatusAtivos($pdo);
    $statusPadrao = statusAtivoPadrao();

    $categoriasStmt = $pdo->prepare("
        select id, nome
          from public.categorias_ativos
      order by nome asc
    ");
    $categoriasStmt->execute();
    $categorias = $categoriasStmt->fetchAll();

    $pdo->exec("
        create table if not exists public.marcas_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");

    $marcasStmt = $pdo->prepare("
        select nome
          from public.marcas_ativos
         where status = :status
      order by nome asc
    ");
    $marcasStmt->execute([":status" => "Ativa"]);
    $marcas = $marcasStmt->fetchAll();

    $totalStmt = $pdo->prepare("select count(*)::int from public.ativos");
    $totalStmt->execute();
    $totalAtivos = (int)$totalStmt->fetchColumn();

    $disponiveisStmt = $pdo->prepare("
        select count(*)::int
          from public.ativos
         where status = :status
    ");
    $disponiveisStmt->execute([":status" => $statusPadrao]);
    $ativosDisponiveis = (int)$disponiveisStmt->fetchColumn();

    $ativosStmt = $pdo->prepare("
        select
            a.id,
            a.nome,
            a.numero_serie,
            a.status,
            a.marca,
            a.propriedade,
            a.criado_em,
            c.nome as categoria,
            l.nome as local
          from public.ativos a
     left join public.categorias_ativos c on c.id = a.categoria_id
     left join public.locais l on l.id = a.local_id
      order by a.criado_em desc
    ");
    $ativosStmt->execute();
    $ativos = $ativosStmt->fetchAll();
} catch (Throwable) {
    $erroBanco = "Nao foi possivel carregar os ativos do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Ativos | TI TECH Solutions</title>
  <meta name="description" content="Consulta de ativos cadastrados no inventario da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/ativos.css?v=20260619-view-only" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-common-ui" defer></script>
  <script src="js/ativos.js?v=20260624-common-ui" defer></script>
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

        <a class="nav-link active" href="ativos.php">
          <i class="bi bi-hdd-network-fill"></i>
          <span>Ativos</span>
        </a>

        <a class="nav-link" href="configuracoes.php">
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

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Invent&aacute;rio</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 8ch">Ativos</span><span aria-hidden="true"></span>
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

      <section class="hero-panel compact-hero asset-inventory-hero" aria-labelledby="assetViewTitle">
        <div class="hero-content">
          <h2 id="assetViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 17ch" data-typewriter-loop
              data-typewriter-phrases="Consulta de ativos.|Inventario completo.|Ativos cadastrados.">Consulta de ativos.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Consulte os ativos cadastrados no banco.
            Use os filtros para encontrar itens por nome, s&eacute;rie, marca, status, categoria ou local.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo dos ativos">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-hdd-stack-fill"></i>
          </div>

          <div>
            <span>Total de ativos</span>
            <strong><?php echo e((string)$totalAtivos); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-box-seam-fill"></i>
          </div>

          <div>
            <span>Em estoque</span>
            <strong><?php echo e((string)$ativosDisponiveis); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-list-check"></i>
          </div>

          <div>
            <span>Exibidos</span>
            <strong id="displayedAssetsMetric"><?php echo e((string)count($ativos)); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="content-card records-card asset-view-card" aria-label="Tabela de ativos">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Ativos cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="assetResultCount"><?php echo e((string)count($ativos)); ?> registros</span>
          </div>
        </div>

        <div class="asset-filter-bar" aria-label="Filtros dos ativos">
          <div class="search-box asset-view-search">
            <i class="bi bi-search"></i>
            <input id="assetSearch" type="search" placeholder="Buscar ativo, s&eacute;rie, marca ou local"
              aria-label="Buscar ativo, s&eacute;rie, marca ou local" autocomplete="off" />
          </div>

          <select id="assetStatusFilter" aria-label="Filtrar por status">
            <option value="todos">Todos os status</option>
            <?php foreach ($statusOptions as $statusOpcao): ?>
              <option value="<?php echo e($statusOpcao); ?>"><?php echo e($statusOpcao); ?></option>
            <?php endforeach; ?>
          </select>

          <select id="assetCategoryFilter" aria-label="Filtrar por categoria">
            <option value="todos">Todas as categorias</option>
            <?php foreach ($categorias as $categoria): ?>
              <?php $categoriaNome = (string)($categoria["nome"] ?? ""); ?>
              <option value="<?php echo e($categoriaNome); ?>" <?php echo strcasecmp($categoriaFiltro, $categoriaNome) === 0 ? "selected" : ""; ?>>
                <?php echo e($categoriaNome); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select id="assetBrandFilter" aria-label="Filtrar por marca">
            <option value="todos">Todas as marcas</option>
            <?php foreach ($marcas as $marca): ?>
              <?php $marcaNome = (string)($marca["nome"] ?? ""); ?>
              <option value="<?php echo e($marcaNome); ?>"><?php echo e($marcaNome); ?></option>
            <?php endforeach; ?>
          </select>

          <button id="clearAssetFilters" class="filter-clear-button" type="button">
            <i class="bi bi-x-circle"></i>
            <span>Limpar</span>
          </button>
        </div>

        <div class="records-table-wrap">
          <table class="records-table asset-view-table">
            <thead>
              <tr>
                <th>Ativo</th>
                <th>Categoria</th>
                <th>Marca</th>
                <th>N&ordm; de s&eacute;rie</th>
                <th>Status</th>
                <th>Local</th>
                <th>Criado em</th>
              </tr>
            </thead>
            <tbody id="assetTableBody">
              <?php foreach ($ativos as $ativo): ?>
                <?php
                $nome = (string)($ativo["nome"] ?? "");
                $numeroSerie = (string)($ativo["numero_serie"] ?? "");
                $status = (string)($ativo["status"] ?? "--");
                $marca = (string)($ativo["marca"] ?? "");
                $propriedade = (string)($ativo["propriedade"] ?? "");
                $categoria = (string)($ativo["categoria"] ?? "Sem categoria");
                $local = (string)($ativo["local"] ?? "");
                $criadoEm = formatarDataAtivo((string)($ativo["criado_em"] ?? ""));
                $searchData = strtolower(trim($nome . " " . $numeroSerie . " " . $status . " " . $marca . " " . $propriedade . " " . $categoria . " " . $local));
                ?>
                <tr class="registration-row asset-row"
                  data-status="<?php echo e(strtolower($status)); ?>"
                  data-status-raw="<?php echo e($status); ?>"
                  data-brand="<?php echo e(strtolower($marca)); ?>"
                  data-brand-raw="<?php echo e($marca); ?>"
                  data-category="<?php echo e(strtolower($categoria)); ?>"
                  data-category-raw="<?php echo e($categoria); ?>"
                  data-location="<?php echo e(strtolower($local)); ?>"
                  data-location-raw="<?php echo e($local); ?>"
                  data-search="<?php echo e($searchData); ?>">
                  <td data-label="Ativo">
                    <strong><?php echo e($nome ?: "--"); ?></strong>
                    <span><?php echo e($propriedade); ?></span>
                  </td>
                  <td data-label="Categoria"><?php echo e($categoria); ?></td>
                  <td data-label="Marca"><?php echo e($marca !== "" ? $marca : "--"); ?></td>
                  <td data-label="N&ordm; de s&eacute;rie"><?php echo e($numeroSerie !== "" ? $numeroSerie : "--"); ?></td>
                  <td data-label="Status">
                    <span class="<?php echo e(classeStatusAtivo($status)); ?>"><?php echo e($status); ?></span>
                  </td>
                  <td data-label="Local"><?php echo e($local !== "" ? $local : "--"); ?></td>
                  <td data-label="Criado em"><?php echo e($criadoEm); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="assetEmptyState" class="empty-state records-empty" <?php echo $ativos ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhum ativo encontrado.</span>
        </div>
      </section>
    </main>
  </div>
</body>

</html>





