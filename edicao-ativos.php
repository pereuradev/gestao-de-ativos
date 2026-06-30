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

function urlEdicaoAtivosPaginada(int $pagina, array $overrides = []): string
{
  $params = array_merge($_GET, $overrides, ["pagina" => $pagina]);

  foreach ($params as $key => $value) {
    if ($value === null || $value === "" || $value === "todos") {
      unset($params[$key]);
      continue;
    }

    if ($key === "pagina" && (int) $value <= 1) {
      unset($params[$key]);
    }
  }

  return "edicao-ativos.php" . ($params ? "?" . http_build_query($params) : "");
}

$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? "Usuario"));
$tipoUsuario = e((string) ($usuario["tipo_usuario"] ?? ""));
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);
$sidebarRoleLabel = e($sidebarIsAdmin ? "ADM" : "Colaborador");
$sidebarRoleClass = e($sidebarIsAdmin ? "is-admin" : "is-collaborator");
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
$categoriaFiltro = trim((string) ($_GET["categoria"] ?? ""));
$buscaFiltro = trim((string) ($_GET["busca"] ?? ""));
$statusFiltro = trim((string) ($_GET["status"] ?? "todos"));
$marcaFiltro = trim((string) ($_GET["marca"] ?? "todos"));

$porPaginaOpcoes = [10, 25, 50, 100];
$porPagina = (int) ($_GET["por_pagina"] ?? 10);

if (!in_array($porPagina, $porPaginaOpcoes, true)) {
  $porPagina = 10;
}

$paginaAtual = max(1, (int) ($_GET["pagina"] ?? 1));

$ativos = [];
$categorias = [];
$locais = [];
$marcas = [];
$totalAtivos = 0;
$totalFiltradoAtivos = 0;
$totalPaginas = 1;
$inicioRegistro = 0;
$fimRegistro = 0;
$ativosDisponiveis = 0;
$erroBanco = "";

$statusOptions = [
  "DisponÃ­vel",
  "Em uso",
  "ManutenÃ§Ã£o",
  "FormataÃ§Ã£o",
  "HomologaÃ§Ã£o",
  "Baixado",
  "Perdido",
];
$statusOptions = [
  "DisponÃ­vel",
  "Em uso",
  "HomologaÃ§Ã£o",
  "ManutenÃ§Ã£o",
];
$statusPadrao = "DisponÃ­vel";

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

  $locaisStmt = $pdo->prepare("
        select id, nome
          from public.locais
      order by nome asc
    ");
  $locaisStmt->execute();
  $locais = $locaisStmt->fetchAll();

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
  $totalAtivos = (int) $totalStmt->fetchColumn();

  $disponiveisStmt = $pdo->prepare("
        select count(*)::int
          from public.ativos
         where lower(status) in ('disponivel', 'disponÃ­vel', 'estoque', 'em estoque')
    ");
  $disponiveisStmt->execute();
  $ativosDisponiveis = (int) $disponiveisStmt->fetchColumn();

  $disponiveisStmt = $pdo->prepare("
        select count(*)::int
          from public.ativos
         where status = :status
    ");
  $disponiveisStmt->execute([":status" => $statusPadrao]);
  $ativosDisponiveis = (int) $disponiveisStmt->fetchColumn();

  $where = [];
  $params = [];

  if ($buscaFiltro !== "") {
    $where[] = "(
            lower(coalesce(a.nome, '')) like lower(:busca)
            or lower(coalesce(a.descricao, '')) like lower(:busca)
            or lower(coalesce(a.numero_serie, '')) like lower(:busca)
            or lower(coalesce(a.status, '')) like lower(:busca)
            or lower(coalesce(a.marca, '')) like lower(:busca)
            or lower(coalesce(a.propriedade, '')) like lower(:busca)
            or lower(coalesce(a.imei, '')) like lower(:busca)
            or lower(coalesce(a.datasheet, '')) like lower(:busca)
            or lower(coalesce(c.nome, '')) like lower(:busca)
            or lower(coalesce(l.nome, '')) like lower(:busca)
        )";
    $params[":busca"] = "%" . $buscaFiltro . "%";
  }

  if ($statusFiltro !== "" && $statusFiltro !== "todos") {
    $where[] = "a.status = :statusFiltro";
    $params[":statusFiltro"] = $statusFiltro;
  }

  if ($categoriaFiltro !== "" && $categoriaFiltro !== "todos") {
    $where[] = "c.nome = :categoriaFiltro";
    $params[":categoriaFiltro"] = $categoriaFiltro;
  }

  if ($marcaFiltro !== "" && $marcaFiltro !== "todos") {
    $where[] = "a.marca = :marcaFiltro";
    $params[":marcaFiltro"] = $marcaFiltro;
  }

  $whereSql = $where ? " where " . implode(" and ", $where) : "";

  $totalFiltradoStmt = $pdo->prepare("
        select count(*)::int
          from public.ativos a
     left join public.categorias_ativos c on c.id = a.categoria_id
     left join public.locais l on l.id = a.local_id
        {$whereSql}
    ");

  foreach ($params as $name => $value) {
    $totalFiltradoStmt->bindValue($name, $value);
  }

  $totalFiltradoStmt->execute();
  $totalFiltradoAtivos = (int) $totalFiltradoStmt->fetchColumn();

  $totalPaginas = max(1, (int) ceil($totalFiltradoAtivos / $porPagina));

  if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
  }

  $offset = ($paginaAtual - 1) * $porPagina;

  $ativosStmt = $pdo->prepare("
        select
            a.id,
            a.nome,
            a.descricao,
            a.numero_serie,
            a.status,
            a.marca,
            a.propriedade,
            a.imei,
            a.datasheet,
            a.criado_em,
            a.categoria_id,
            a.local_id,
            c.nome as categoria,
            l.nome as local
          from public.ativos a
     left join public.categorias_ativos c on c.id = a.categoria_id
     left join public.locais l on l.id = a.local_id
        {$whereSql}
      order by a.criado_em desc
         limit :limite offset :offset
    ");

  foreach ($params as $name => $value) {
    $ativosStmt->bindValue($name, $value);
  }

  $ativosStmt->bindValue(":limite", $porPagina, PDO::PARAM_INT);
  $ativosStmt->bindValue(":offset", $offset, PDO::PARAM_INT);

  $ativosStmt->execute();
  $ativos = $ativosStmt->fetchAll();

  $inicioRegistro = $totalFiltradoAtivos > 0 ? $offset + 1 : 0;
  $fimRegistro = min($offset + count($ativos), $totalFiltradoAtivos);
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os ativos do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />

  <title>Edi&ccedil;&atilde;o de ativos | TI TECH Solutions</title>
  <meta name="description" content="Listagem de ativos cadastrados na TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-sidebar-resize" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="css/edicao-ativos.css?v=20260629-pagination" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260626-clear-button" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260630-sidebar-resize" defer></script>
  <script src="js/edicao-ativos.js?v=20260629-pagination" defer></script>
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
        <a class="nav-link" href="funcionarios.php">
          <i class="bi bi-people-fill"></i>
          <span>Funcion&aacute;rios</span>
        </a>

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
        <div class="nav-group" data-nav-group>
          <button class="nav-link nav-toggle" type="button" aria-expanded="false" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="registrationSubmenu">
            <a href="cadastro-ativos.php">Ativos</a>
            <a href="marcas.php">Marcas</a>
            <a href="propriedades.php">Propriedades</a>
            <a href="locais.php">Localiza&ccedil;&otilde;es</a>
          </div>
        </div>

        <div class="nav-group open" data-nav-group>
          <button class="nav-link nav-toggle active" type="button" aria-expanded="true" aria-controls="editingSubmenu">
            <i class="bi bi-pencil-square"></i>
            <span>Edi&ccedil;&atilde;o</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="editingSubmenu">
            <a class="active-submenu" href="edicao-ativos.php">Ativos</a>
            <a href="edicao-marcas.php">Marcas</a>
            <a href="edicao-propriedades.php">Propriedades</a>
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

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Edi&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 18ch">Edi&ccedil;&atilde;o de ativos</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="cadastro-ativos.php">
            <i class="bi bi-plus-circle"></i>
            Novo ativo
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero asset-inventory-hero" aria-labelledby="assetEditTitle">
        <div class="hero-content">
          <h2 id="assetEditTitle">
            <span class="typewriter-heading" style="--typewriter-min: 21ch" data-typewriter-loop
              data-typewriter-phrases="Ativos cadastrados.|Inventario para consulta.|Dados recentes do estoque.">Ativos
              cadastrados.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Veja os ativos cadastrados no banco para apoiar a manuten&ccedil;&atilde;o do invent&aacute;rio.
            Use filtros para encontrar itens rapidamente e a coluna de a&ccedil;&otilde;es para alterar ou excluir
            registros.
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
            <strong id="totalAssetsMetric"
              data-total-assets="<?php echo e((string) $totalAtivos); ?>"><?php echo e((string) $totalAtivos); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-box-seam-fill"></i>
          </div>

          <div>
            <span>Em estoque</span>
            <strong><?php echo e((string) $ativosDisponiveis); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-list-check"></i>
          </div>

          <div>
            <span>Exibidos</span>
            <strong id="displayedAssetsMetric"><?php echo e((string) count($ativos)); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="content-card records-card asset-edit-card" aria-label="Tabela de ativos">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Ativos cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="assetResultCount" data-total-filtered="<?php echo e((string) $totalFiltradoAtivos); ?>">
              <?php echo e((string) $totalFiltradoAtivos); ?>
              <?php echo $totalFiltradoAtivos === 1 ? "registro encontrado" : "registros encontrados"; ?>
            </span>
          </div>
        </div>

        <div id="assetPageMessage" class="asset-page-message" role="status" aria-live="polite"></div>

        <form id="assetFiltersForm" class="asset-filter-bar" method="get" action="edicao-ativos.php"
          aria-label="Filtros dos ativos">
          <input type="hidden" name="pagina" value="1" />
          <input id="assetSearchValue" type="hidden" name="busca" value="<?php echo e($buscaFiltro); ?>" />
          <div class="search-box asset-edit-search">
            <i class="bi bi-search"></i>
            <input id="assetSearch" type="search" placeholder="Buscar ativo, s&eacute;rie, marca ou local"
              aria-label="Buscar ativo, s&eacute;rie, marca ou local" autocomplete="off" />
          </div>

          <select id="assetStatusFilter" name="status" aria-label="Filtrar por status">
            <option value="todos">Todos os status</option>
            <?php foreach ($statusOptions as $statusOpcao): ?>
              <option value="<?php echo e($statusOpcao); ?>" <?php echo strcasecmp($statusFiltro, $statusOpcao) === 0 ? "selected" : ""; ?>>
                <?php echo e($statusOpcao); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select id="assetCategoryFilter" name="categoria" aria-label="Filtrar por categoria">
            <option value="todos">Todas as categorias</option>
            <?php foreach ($categorias as $categoria): ?>
              <?php $categoriaNome = (string) ($categoria["nome"] ?? ""); ?>
              <option value="<?php echo e($categoriaNome); ?>" <?php echo strcasecmp($categoriaFiltro, $categoriaNome) === 0 ? "selected" : ""; ?>>
                <?php echo e($categoriaNome); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select id="assetBrandFilter" name="marca" aria-label="Filtrar por marca">
            <option value="todos">Todas as marcas</option>
            <?php foreach ($marcas as $marca): ?>
              <?php $marcaNome = (string) ($marca["nome"] ?? ""); ?>
              <option value="<?php echo e($marcaNome); ?>" <?php echo strcasecmp($marcaFiltro, $marcaNome) === 0 ? "selected" : ""; ?>>
                <?php echo e($marcaNome); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select id="assetPerPage" name="por_pagina" aria-label="Registros por p&aacute;gina">
            <?php foreach ($porPaginaOpcoes as $opcaoPorPagina): ?>
              <option value="<?php echo e((string) $opcaoPorPagina); ?>" <?php echo $porPagina === $opcaoPorPagina ? "selected" : ""; ?>>
                <?php echo e((string) $opcaoPorPagina); ?> por p&aacute;gina
              </option>
            <?php endforeach; ?>
          </select>

          <button id="clearAssetFilters" class="filter-clear-button" type="button">
            <i class="bi bi-x-circle"></i>
            <span>Limpar</span>
          </button>
        </form>

        <div class="records-table-wrap">
          <table class="records-table asset-edit-table">
            <thead>
              <tr>
                <th>Ativo</th>
                <th>Categoria</th>
                <th>Marca</th>
                <th>N&ordm; de s&eacute;rie</th>
                <th>Status</th>
                <th>Local</th>
                <th>Criado em</th>
                <th>A&ccedil;&otilde;es</th>
              </tr>
            </thead>
            <tbody id="assetTableBody">
              <?php foreach ($ativos as $ativo): ?>
                <?php
                $id = (string) ($ativo["id"] ?? "");
                $nome = (string) ($ativo["nome"] ?? "");
                $descricao = (string) ($ativo["descricao"] ?? "");
                $numeroSerie = (string) ($ativo["numero_serie"] ?? "");
                $status = (string) ($ativo["status"] ?? "--");
                $marca = (string) ($ativo["marca"] ?? "");
                $propriedade = (string) ($ativo["propriedade"] ?? "");
                $imei = (string) ($ativo["imei"] ?? "");
                $datasheet = (string) ($ativo["datasheet"] ?? "");
                $categoriaId = (string) ($ativo["categoria_id"] ?? "");
                $localId = (string) ($ativo["local_id"] ?? "");
                $categoria = (string) ($ativo["categoria"] ?? "Sem categoria");
                $local = (string) ($ativo["local"] ?? "");
                $searchData = strtolower(trim($nome . " " . $numeroSerie . " " . $status . " " . $marca . " " . $propriedade . " " . $categoria . " " . $local));
                ?>
                <tr class="registration-row asset-row" data-id="<?php echo e($id); ?>" data-name="<?php echo e($nome); ?>"
                  data-description="<?php echo e($descricao); ?>" data-serial="<?php echo e($numeroSerie); ?>"
                  data-status="<?php echo e(strtolower($status)); ?>" data-status-raw="<?php echo e($status); ?>"
                  data-brand="<?php echo e(strtolower($marca)); ?>" data-brand-raw="<?php echo e($marca); ?>"
                  data-property="<?php echo e($propriedade); ?>" data-imei="<?php echo e($imei); ?>"
                  data-datasheet="<?php echo e($datasheet); ?>" data-category="<?php echo e(strtolower($categoria)); ?>"
                  data-category-raw="<?php echo e($categoria); ?>" data-category-id="<?php echo e($categoriaId); ?>"
                  data-location="<?php echo e(strtolower($local)); ?>" data-location-raw="<?php echo e($local); ?>"
                  data-location-id="<?php echo e($localId); ?>"
                  data-created="<?php echo e(formatarDataAtivo((string) ($ativo["criado_em"] ?? ""))); ?>"
                  data-search="<?php echo e($searchData); ?>">
                  <td data-label="Ativo">
                    <strong data-asset-name><?php echo e($nome ?: "--"); ?></strong>
                    <span data-asset-property><?php echo e($propriedade); ?></span>
                  </td>
                  <td data-label="Categoria" data-asset-category><?php echo e($categoria); ?></td>
                  <td data-label="Marca" data-asset-brand><?php echo e($marca !== "" ? $marca : "--"); ?></td>
                  <td data-label="N&ordm; de s&eacute;rie" data-asset-serial>
                    <?php echo e($numeroSerie !== "" ? $numeroSerie : "--"); ?></td>
                  <td data-label="Status">
                    <span class="<?php echo e(classeStatusAtivo($status)); ?>"
                      data-asset-status><?php echo e($status); ?></span>
                  </td>
                  <td data-label="Local" data-asset-location><?php echo e($local !== "" ? $local : "--"); ?></td>
                  <td data-label="Criado em" data-asset-created>
                    <?php echo e(formatarDataAtivo((string) ($ativo["criado_em"] ?? ""))); ?></td>
                  <td data-label="A&ccedil;&otilde;es" class="asset-actions-cell">
                    <div class="row-actions">
                      <button class="table-action edit-asset-button" type="button" data-asset-action="edit">
                        <i class="bi bi-pencil-square"></i>
                        <span>Alterar</span>
                      </button>
                      <button class="table-action delete-asset-button" type="button" data-asset-action="delete">
                        <i class="bi bi-trash3"></i>
                        <span>Excluir</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="assetEmptyState" class="empty-state records-empty" <?php echo $ativos ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhum ativo encontrado.</span>
        </div>

        <?php if ($totalFiltradoAtivos > 0): ?>
          <nav class="asset-pagination" aria-label="Pagina&ccedil;&atilde;o de ativos">
            <div class="asset-pagination-info">
              Mostrando
              <strong><?php echo e((string) $inicioRegistro); ?>-<?php echo e((string) $fimRegistro); ?></strong>
              de
              <strong><?php echo e((string) $totalFiltradoAtivos); ?></strong>
              registros
            </div>

            <?php if ($totalPaginas > 1): ?>
              <div class="asset-pagination-controls">
                <?php if ($paginaAtual > 1): ?>
                  <a class="pagination-button" href="<?php echo e(urlEdicaoAtivosPaginada($paginaAtual - 1)); ?>">
                    <i class="bi bi-chevron-left"></i>
                    Anterior
                  </a>
                <?php else: ?>
                  <span class="pagination-button disabled" aria-disabled="true">
                    <i class="bi bi-chevron-left"></i>
                    Anterior
                  </span>
                <?php endif; ?>

                <?php
                $inicioPagina = max(1, $paginaAtual - 2);
                $fimPagina = min($totalPaginas, $paginaAtual + 2);
                ?>

                <?php if ($inicioPagina > 1): ?>
                  <a class="pagination-button compact" href="<?php echo e(urlEdicaoAtivosPaginada(1)); ?>">1</a>

                  <?php if ($inicioPagina > 2): ?>
                    <span class="pagination-ellipsis">...</span>
                  <?php endif; ?>
                <?php endif; ?>

                <?php for ($pagina = $inicioPagina; $pagina <= $fimPagina; $pagina++): ?>
                  <?php if ($pagina === $paginaAtual): ?>
                    <span class="pagination-button compact active" aria-current="page">
                      <?php echo e((string) $pagina); ?>
                    </span>
                  <?php else: ?>
                    <a class="pagination-button compact" href="<?php echo e(urlEdicaoAtivosPaginada($pagina)); ?>">
                      <?php echo e((string) $pagina); ?>
                    </a>
                  <?php endif; ?>
                <?php endfor; ?>

                <?php if ($fimPagina < $totalPaginas): ?>
                  <?php if ($fimPagina < $totalPaginas - 1): ?>
                    <span class="pagination-ellipsis">...</span>
                  <?php endif; ?>

                  <a class="pagination-button compact" href="<?php echo e(urlEdicaoAtivosPaginada($totalPaginas)); ?>">
                    <?php echo e((string) $totalPaginas); ?>
                  </a>
                <?php endif; ?>

                <?php if ($paginaAtual < $totalPaginas): ?>
                  <a class="pagination-button" href="<?php echo e(urlEdicaoAtivosPaginada($paginaAtual + 1)); ?>">
                    Pr&oacute;xima
                    <i class="bi bi-chevron-right"></i>
                  </a>
                <?php else: ?>
                  <span class="pagination-button disabled" aria-disabled="true">
                    Pr&oacute;xima
                    <i class="bi bi-chevron-right"></i>
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <div class="edit-modal-backdrop" id="assetEditModal" hidden>
    <section class="edit-modal-card asset-modal-card" role="dialog" aria-modal="true"
      aria-labelledby="assetEditModalTitle">
      <div class="edit-modal-header">
        <div>
          <p class="section-tag">Alterar ativo</p>
          <h3 id="assetEditModalTitle">Dados do ativo</h3>
        </div>

        <button class="icon-button modal-close-button" type="button" aria-label="Fechar edicao" data-close-asset-modal>
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form id="assetEditForm" class="asset-form enhanced-asset-form" action="Backend/atualizar-ativo.php" method="post"
        novalidate>
        <input id="editAssetId" type="hidden" name="id" />
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

        <div class="asset-form-grid asset-modal-grid">
          <label class="asset-field wide-field priority-field">
            <span>Nome do ativo <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-hdd-stack"></i>
              <input id="editAssetName" name="nome" type="text" autocomplete="off" required />
            </div>
          </label>

          <label class="asset-field">
            <span>Categoria <strong>*</strong></span>
            <div class="input-shell select-shell">
              <i class="bi bi-tags"></i>
              <select id="editAssetCategory" name="categoria_id" required>
                <option value="">Selecione a categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                  <option value="<?php echo e((string) ($categoria["id"] ?? "")); ?>">
                    <?php echo e((string) ($categoria["nome"] ?? "")); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <label class="asset-field">
            <span>Status <strong>*</strong></span>
            <div class="input-shell select-shell">
              <i class="bi bi-activity"></i>
              <select id="editAssetStatus" name="status" required>
                <?php foreach ($statusOptions as $statusOpcao): ?>
                  <option value="<?php echo e($statusOpcao); ?>"><?php echo e($statusOpcao); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <label class="asset-field">
            <span>Marca</span>
            <div class="input-shell select-shell">
              <i class="bi bi-building"></i>
              <select id="editAssetBrand" name="marca">
                <option value="">Sem marca</option>
                <?php foreach ($marcas as $marca): ?>
                  <option value="<?php echo e((string) ($marca["nome"] ?? "")); ?>">
                    <?php echo e((string) ($marca["nome"] ?? "")); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <label class="asset-field">
            <span>Local</span>
            <div class="input-shell select-shell">
              <i class="bi bi-geo-alt"></i>
              <select id="editAssetLocation" name="local_id">
                <option value="">Sem local definido</option>
                <?php foreach ($locais as $local): ?>
                  <option value="<?php echo e((string) ($local["id"] ?? "")); ?>">
                    <?php echo e((string) ($local["nome"] ?? "")); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <label class="asset-field">
            <span>N&ordm; de s&eacute;rie</span>
            <div class="input-shell">
              <i class="bi bi-123"></i>
              <input id="editAssetSerial" name="numero_serie" type="text" autocomplete="off" />
            </div>
          </label>

          <label class="asset-field">
            <span>Propriedade</span>
            <div class="input-shell">
              <i class="bi bi-shield-check"></i>
              <input id="editAssetProperty" name="propriedade" type="text" autocomplete="off" />
            </div>
          </label>

          <label class="asset-field">
            <span>IMEI</span>
            <div class="input-shell">
              <i class="bi bi-phone"></i>
              <input id="editAssetImei" name="imei" type="text" autocomplete="off" />
            </div>
          </label>

          <label class="asset-field wide-field">
            <span>Datasheet</span>
            <div class="input-shell">
              <i class="bi bi-link-45deg"></i>
              <input id="editAssetDatasheet" name="datasheet" type="text" autocomplete="off" />
            </div>
          </label>

          <label class="asset-field wide-field">
            <span>Descri&ccedil;&atilde;o</span>
            <div class="input-shell textarea-shell">
              <i class="bi bi-card-text"></i>
              <textarea id="editAssetDescription" name="descricao" rows="3"></textarea>
            </div>
          </label>
        </div>

        <div id="assetEditMessage" class="form-message" role="status" aria-live="polite"></div>

        <div class="asset-form-actions enhanced-form-actions">
          <button class="form-action-button danger-button" type="button" data-close-asset-modal>
            <i class="bi bi-x-circle"></i>
            <span>Cancelar</span>
          </button>

          <button id="saveAssetButton" class="form-action-button success-button" type="submit">
            <i class="bi bi-check-circle"></i>
            <span>Salvar altera&ccedil;&otilde;es</span>
          </button>
        </div>
      </form>
    </section>
  </div>
</body>

</html>
