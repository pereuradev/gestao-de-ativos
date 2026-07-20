<?php

declare(strict_types=1);

session_start();

// Impede que a página carregue permissões ou consulte dados sem uma sessão válida.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// A autorização da página fica centralizada para manter a mesma regra usada no restante do sistema.
require_once __DIR__ . "/../Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_ativos", "Ativos");

// Centraliza o escape dos valores dinâmicos antes de inseri-los no HTML.
function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Padroniza as datas no fuso da interface e preserva a renderização caso o valor seja inválido.
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

// Mantém os filtros atuais entre as páginas e remove parâmetros que representam valores padrão.
function urlAtivosPaginada(int $pagina, array $overrides = []): string
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

  return "ativos.php" . ($params ? "?" . http_build_query($params) : "");
}

// Reaproveita os filtros da tela na exportação, descartando controles exclusivos da paginação visual.
function urlExportarAtivos(string $formato, array $overrides = []): string
{
  $params = array_merge($_GET, $overrides, ["formato" => $formato]);

  unset($params["pagina"], $params["por_pagina"]);

  foreach ($params as $key => $value) {
    if ($value === null || $value === "" || $value === "todos") {
      unset($params[$key]);
    }
  }

  return "../Backend/exportar-ativos.php" . ($params ? "?" . http_build_query($params) : "");
}

$usuario = $_SESSION["usuario"];

// Os filtros chegam pela URL para permitir recarregamento, paginação e compartilhamento da mesma consulta.
$categoriaFiltro = trim((string) ($_GET["categoria"] ?? ""));
$buscaFiltro = trim((string) ($_GET["busca"] ?? ""));
$statusFiltro = trim((string) ($_GET["status"] ?? "todos"));
$marcaFiltro = trim((string) ($_GET["marca"] ?? "todos"));
$localizacaoFiltro = trim((string) ($_GET["localizacao"] ?? "todos"));

$porPaginaOpcoes = [10, 25, 50, 100];
$porPagina = (int) ($_GET["por_pagina"] ?? 10);

// Aceita somente tamanhos previstos para evitar consultas com limites arbitrários.
if (!in_array($porPagina, $porPaginaOpcoes, true)) {
  $porPagina = 10;
}

$paginaAtual = max(1, (int) ($_GET["pagina"] ?? 1));

$ativos = [];
$categorias = [];
$marcas = [];
$locais = [];

$totalAtivos = 0;
$totalFiltradoAtivos = 0;
$totalPaginas = 1;
$inicioRegistro = 0;
$fimRegistro = 0;
$ativosDisponiveis = 0;

$erroBanco = "";

// Estes valores funcionam como fallback; o cadastro central de status os substitui quando o banco está disponível.
$statusPadrao = "DisponÃ­vel";
$statusOptions = [
  "DisponÃ­vel",
  "Em uso",
  "HomologaÃ§Ã£o",
  "ManutenÃ§Ã£o",
];

try {
  // A conexão e as regras de status são compartilhadas com o backend para evitar definições divergentes.
  require __DIR__ . "/../Backend/Conexao.php";
  require __DIR__ . "/../Backend/status-ativos.php";

  $statusOptions = nomesStatusAtivos($pdo);
  $statusPadrao = statusAtivoPadrao();

  // Carrega as opções usadas nos filtros antes de montar a consulta principal.
  $categoriasStmt = $pdo->prepare("
        select id, nome
        from public.categorias_ativos
        order by nome asc
    ");
  $categoriasStmt->execute();
  $categorias = $categoriasStmt->fetchAll();

  $marcasStmt = $pdo->prepare("
        select nome
        from public.marcas_ativos
        where status = :status
        order by nome asc
    ");
  $marcasStmt->execute([":status" => "Ativa"]);
  $marcas = $marcasStmt->fetchAll();

  $locaisStmt = $pdo->prepare("
        select id, nome
        from public.locais
        order by nome asc
    ");
  $locaisStmt->execute();
  $locais = $locaisStmt->fetchAll();

  // As métricas globais não mudam conforme os filtros da tabela.
  $totalStmt = $pdo->prepare("select count(*)::int from public.ativos");
  $totalStmt->execute();
  $totalAtivos = (int) $totalStmt->fetchColumn();

  $disponiveisStmt = $pdo->prepare("
        select count(*)::int
        from public.ativos
        where status = :status
    ");
  $disponiveisStmt->execute([":status" => $statusPadrao]);
  $ativosDisponiveis = (int) $disponiveisStmt->fetchColumn();

  $where = [];
  $params = [];

  // Cada filtro adiciona uma condição parametrizada, mantendo os valores fora da SQL montada dinamicamente.
  if ($buscaFiltro !== "") {
    $where[] = "(
            lower(coalesce(a.nome, '')) like lower(:busca)
            or lower(coalesce(a.numero_serie, '')) like lower(:busca)
            or lower(coalesce(a.status, '')) like lower(:busca)
            or lower(coalesce(a.marca, '')) like lower(:busca)
            or lower(coalesce(a.propriedade, '')) like lower(:busca)
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

  if ($localizacaoFiltro !== "" && $localizacaoFiltro !== "todos") {
    $where[] = "l.nome = :localizacaoFiltro";
    $params[":localizacaoFiltro"] = $localizacaoFiltro;
  }

  $whereSql = $where ? " where " . implode(" and ", $where) : "";

  // A contagem usa exatamente as mesmas condições da listagem para calcular a paginação corretamente.
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

  // Corrige URLs com uma página maior que o total disponível após a aplicação dos filtros.
  if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
  }

  $offset = ($paginaAtual - 1) * $porPagina;

  // A consulta retorna apenas o recorte da página atual; limite e deslocamento são vinculados como inteiros.
  $ativosStmt = $pdo->prepare("
        select
            a.id,
            a.nome,
            a.numero_serie,
            a.status,
            a.marca,
            a.propriedade,
            a.datasheet,
            a.criado_em,
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
  // Não expõe detalhes internos da conexão ou da consulta para o usuário final.
  $erroBanco = "Nao foi possivel carregar os ativos do banco agora.";
}

// Os botões recebem URLs prontas com os mesmos filtros aplicados à listagem.
$exportarAtivosPdfUrl = urlExportarAtivos("pdf");
$exportarAtivosExcelUrl = urlExportarAtivos("xlsx");
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ativos | TI TECH Solutions</title>
  <meta name="description" content="Consulta de ativos cadastrados no inventario da TI TECH Solutions" />

  <!-- Identidade da página, tipografia e biblioteca externa de ícones. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Estilos compartilhados do sistema e ajustes específicos da consulta de ativos. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ativos.css?v=20260713-export-xlsx" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260626-react-responsive" />

  <!-- Scripts locais controlam a interface; React é carregado para os widgets reutilizáveis. -->
  <script src="../js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="../js/ativos.js?v=20260713-export-xlsx" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <!-- A navegação lateral é compartilhada pelas páginas autenticadas. -->
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Invent&aacute;rio</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 8ch">Ativos</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <!-- Contextualiza a consulta e os filtros disponíveis para o inventário. -->
      <section class="hero-panel compact-hero asset-inventory-hero" aria-labelledby="assetViewTitle">
        <div class="hero-content">
          <h2 id="assetViewTitle">
            <span class="typewriter-heading" style="--typewriter-min: 17ch" data-typewriter-loop
              data-typewriter-phrases="Consulta de ativos.|Inventario completo.|Ativos cadastrados.">Consulta de
              ativos.</span><span aria-hidden="true"></span>
          </h2>

          <p>
            Consulte os ativos cadastrados no banco. Use os filtros para encontrar itens por nome, s&eacute;rie, marca,
            status, categoria ou local.
          </p>
        </div>
      </section>

      <!-- Métricas gerais do inventário, independentes dos filtros da listagem. -->
      <section class="metrics-grid" aria-label="Resumo dos ativos">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-hdd-stack-fill"></i>
          </div>

          <div>
            <span>Total de ativos</span>
            <strong><?php echo e((string) $totalAtivos); ?></strong>
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

      <!-- Exportações, filtros e tabela paginada usam o mesmo conjunto de parâmetros. -->
      <section class="content-card records-card asset-view-card" aria-label="Tabela de ativos">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Ativos cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="assetResultCount">
              <?php echo e((string) $totalFiltradoAtivos); ?>
              <?php echo $totalFiltradoAtivos === 1 ? "registro encontrado" : "registros encontrados"; ?>
            </span>

            <!-- O JavaScript usa os atributos data-* para solicitar e baixar o formato escolhido. -->
            <div class="asset-export-actions" aria-label="Exportacao de ativos">
              <button class="asset-export-button asset-export-button-pdf" id="exportAssetsPdf" type="button"
                data-asset-export
                data-export-format="pdf" data-export-url="<?php echo e($exportarAtivosPdfUrl); ?>"
                data-default-label="Exportar PDF" data-default-icon="bi bi-file-earmark-pdf">
                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                <span>Exportar PDF</span>
              </button>

              <button class="asset-export-button asset-export-button-excel" id="exportAssetsExcel" type="button"
                data-asset-export data-export-format="xlsx" data-export-url="<?php echo e($exportarAtivosExcelUrl); ?>"
                data-default-label="Exportar Excel" data-default-icon="bi bi-file-earmark-excel">
                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                <span>Exportar Excel</span>
              </button>
            </div>

            <div id="assetExportStatus" class="asset-export-status" role="status" aria-live="polite" hidden></div>
          </div>
        </div>

        <!-- O envio por GET preserva os filtros na URL; qualquer alteração reinicia a consulta na primeira página. -->
        <form id="assetFiltersForm" class="asset-filter-bar" method="get" action="ativos.php"
          aria-label="Filtros dos ativos">
          <input type="hidden" name="pagina" value="1" />

          <div class="search-box asset-view-search">
            <i class="bi bi-search"></i>
            <input id="assetSearch" name="busca" type="search" value="<?php echo e($buscaFiltro); ?>"
              placeholder="Buscar ativo, s&eacute;rie, marca ou local"
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

          <select id="assetLocationFilter" name="localizacao" aria-label="Filtrar por localizacao">
            <option value="todos">Todas as localizacoes</option>

            <?php foreach ($locais as $localFiltroOpcao): ?>
              <?php $localNome = (string) ($localFiltroOpcao["nome"] ?? ""); ?>

              <option value="<?php echo e($localNome); ?>" <?php echo strcasecmp($localizacaoFiltro, $localNome) === 0 ? "selected" : ""; ?>>
                <?php echo e($localNome); ?>
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
          <table class="records-table asset-view-table">
            <thead>
              <tr>
                <th>Ativo</th>
                <th>Categoria</th>
                <th>Marca</th>
                <th>N&ordm; de s&eacute;rie</th>
                <th>Status</th>
                <th>Local</th>
                <th>Datasheet</th>
                <th>Criado em</th>
              </tr>
            </thead>

            <tbody id="assetTableBody">
              <?php foreach ($ativos as $ativo): ?>
                <?php
                // Normaliza os dados uma vez para manter a marcação abaixo simples e sempre escapada na saída.
                $nome = (string) ($ativo["nome"] ?? "");
                $numeroSerie = (string) ($ativo["numero_serie"] ?? "");
                $status = (string) ($ativo["status"] ?? "--");
                $marca = (string) ($ativo["marca"] ?? "");
                $propriedade = (string) ($ativo["propriedade"] ?? "");
                $datasheet = trim((string) ($ativo["datasheet"] ?? ""));
                // Somente URLs HTTP(S) viram links clicáveis; outros valores continuam sendo exibidos como texto.
                $datasheetEhUrl = filter_var($datasheet, FILTER_VALIDATE_URL)
                  && preg_match("#^https?://#i", $datasheet);
                $categoria = (string) ($ativo["categoria"] ?? "Sem categoria");
                $local = (string) ($ativo["local"] ?? "");
                $criadoEm = formatarDataAtivo((string) ($ativo["criado_em"] ?? ""));
                $searchData = strtolower(trim($nome . " " . $numeroSerie . " " . $status . " " . $marca . " " . $propriedade . " " . $datasheet . " " . $categoria . " " . $local));
                ?>

                <tr class="registration-row asset-row" data-status="<?php echo e(strtolower($status)); ?>"
                  data-status-raw="<?php echo e($status); ?>" data-brand="<?php echo e(strtolower($marca)); ?>"
                  data-brand-raw="<?php echo e($marca); ?>" data-category="<?php echo e(strtolower($categoria)); ?>"
                  data-category-raw="<?php echo e($categoria); ?>" data-location="<?php echo e(strtolower($local)); ?>"
                  data-location-raw="<?php echo e($local); ?>" data-search="<?php echo e($searchData); ?>">
                  <td data-label="Ativo">
                    <strong><?php echo e($nome ?: "--"); ?></strong>
                    <span><?php echo e($propriedade); ?></span>
                  </td>

                  <td data-label="Categoria"><?php echo e($categoria); ?></td>
                  <td data-label="Marca"><?php echo e($marca !== "" ? $marca : "--"); ?></td>
                  <td data-label="N&ordm; de s&eacute;rie"><?php echo e($numeroSerie !== "" ? $numeroSerie : "--"); ?>
                  </td>

                  <td data-label="Status">
                    <span class="<?php echo e(classeStatusAtivo($status)); ?>"><?php echo e($status); ?></span>
                  </td>

                  <td data-label="Local"><?php echo e($local !== "" ? $local : "--"); ?></td>

                  <td data-label="Datasheet">
                    <?php if ($datasheet === ""): ?>
                      --
                    <?php elseif ($datasheetEhUrl): ?>
                      <a class="asset-datasheet-link" href="<?php echo e($datasheet); ?>" target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Abrir</span>
                      </a>
                    <?php else: ?>
                      <span class="asset-datasheet-text" title="<?php echo e($datasheet); ?>">
                        <?php echo e($datasheet); ?>
                      </span>
                    <?php endif; ?>
                  </td>

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

        <!-- A paginação é processada no servidor e conserva todos os filtros ativos. -->
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
                  <a class="pagination-button" href="<?php echo e(urlAtivosPaginada($paginaAtual - 1)); ?>">
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
                  <a class="pagination-button compact" href="<?php echo e(urlAtivosPaginada(1)); ?>">1</a>

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
                    <a class="pagination-button compact" href="<?php echo e(urlAtivosPaginada($pagina)); ?>">
                      <?php echo e((string) $pagina); ?>
                    </a>
                  <?php endif; ?>
                <?php endfor; ?>

                <?php if ($fimPagina < $totalPaginas): ?>
                  <?php if ($fimPagina < $totalPaginas - 1): ?>
                    <span class="pagination-ellipsis">...</span>
                  <?php endif; ?>

                  <a class="pagination-button compact" href="<?php echo e(urlAtivosPaginada($totalPaginas)); ?>">
                    <?php echo e((string) $totalPaginas); ?>
                  </a>
                <?php endif; ?>

                <?php if ($paginaAtual < $totalPaginas): ?>
                  <a class="pagination-button" href="<?php echo e(urlAtivosPaginada($paginaAtual + 1)); ?>">
                    Próxima
                    <i class="bi bi-chevron-right"></i>
                  </a>
                <?php else: ?>
                  <span class="pagination-button disabled" aria-disabled="true">
                    Próxima
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

</body>

</html>
