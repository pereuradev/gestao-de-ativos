<?php
session_start();

if (empty($_SESSION["usuario"])) {
    header("Location: Pagina-login.html?sessao=expirada");
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

$usuario = $_SESSION["usuario"] ?? [];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? $usuario["nome"] ?? "Usuário TI TECH"));
$tipoUsuario = (string) ($usuario["tipo_usuario"] ?? "Colaborador");
$sidebarRoleLabel = e($tipoUsuario !== "" ? $tipoUsuario : "Colaborador");
$sidebarRoleClass = strcasecmp($tipoUsuario, "Administrador") === 0 ? "is-admin" : "is-collaborator";
$sidebarEmail = e((string) ($usuario["email"] ?? ""));
$sidebarDepartment = e((string) ($usuario["departamento"] ?? "Suporte Técnico"));

$partesNome = preg_split('/\s+/', trim((string) ($usuario["nome_completo"] ?? $usuario["nome"] ?? ""))) ?: [];
$sidebarInitialsText = "";

foreach ($partesNome as $parte) {
    if ($parte === "") {
        continue;
    }

    $sidebarInitialsText .= mb_strtoupper(mb_substr($parte, 0, 1, "UTF-8"), "UTF-8");

    if (mb_strlen($sidebarInitialsText, "UTF-8") >= 2) {
        break;
    }
}

$sidebarInitials = e($sidebarInitialsText !== "" ? $sidebarInitialsText : "TT");
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard de Produtos | TI TECH Solutions</title>

    <link rel="icon" type="image/png" href="assets/favicon.png" />
    <link rel="preconnect" href="https://cdn.jsdelivr.net" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="css/pagina-base.css?v=20260626-dashboard-fix" />
    <link rel="stylesheet" href="css/dashboard-produtos.css?v=20260626-dashboard-fix" />
</head>

<body class="theme-dark page-loading dashboard-products-page" data-accent="teal">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="https://www.titechsolutions.com.br/" class="brand-area"
                    aria-label="Acessar site da TI TECH Solutions">
                    <img class="brand-logo" src="assets/logo-branca.png" alt="TI TECH Solutions" />
                </a>

                <button class="icon-button sidebar-close" id="closeSidebar" type="button" aria-label="Fechar menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="sidebar-nav" aria-label="Menu principal">
                <a class="nav-link" href="pagina-inicial.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Página Inicial</span>
                </a>

                <a class="nav-link active" href="dashboard.php" aria-current="page">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Dashboard</span>
                </a>

                <a class="nav-link" href="funcionarios.php">
                    <i class="bi bi-people-fill"></i>
                    <span>Funcionários</span>
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
                    <span>Localizações</span>
                </a>

                <div class="nav-group" data-nav-group>
                    <button class="nav-link nav-toggle" type="button" aria-expanded="false"
                        aria-controls="registrationSubmenu">
                        <i class="bi bi-folder-plus"></i>
                        <span>Cadastros</span>
                        <i class="bi bi-chevron-down nav-chevron"></i>
                    </button>

                    <div class="nav-submenu" id="registrationSubmenu">
                        <a href="cadastro-ativos.php">Ativos</a>
                        <a href="marcas.php">Marcas</a>
                        <a href="propriedades.php">Propriedades</a>
                        <a href="locais.php">Localizações</a>
                    </div>
                </div>

                <div class="nav-group" data-nav-group>
                    <button class="nav-link nav-toggle" type="button" aria-expanded="false"
                        aria-controls="editingSubmenu">
                        <i class="bi bi-pencil-square"></i>
                        <span>Edição</span>
                        <i class="bi bi-chevron-down nav-chevron"></i>
                    </button>

                    <div class="nav-submenu" id="editingSubmenu">
                        <a href="edicao-ativos.php">Ativos</a>
                        <a href="edicao-marcas.php">Marcas</a>
                        <a href="edicao-propriedades.php">Propriedades</a>
                        <a href="edicao-locais.php">Localizações</a>
                    </div>
                </div>

                <a class="nav-link" href="ativos.php">
                    <i class="bi bi-hdd-network-fill"></i>
                    <span>Ativos</span>
                </a>

                <a class="nav-link" href="configuracoes.php">
                    <i class="bi bi-gear-fill"></i>
                    <span>Configurações</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-summary user-summary-card">
                    <div class="sidebar-avatar" aria-hidden="true"><?php echo $sidebarInitials; ?></div>

                    <div class="sidebar-user-info">
                        <strong title="<?php echo $nomeUsuario; ?>"><?php echo $nomeUsuario; ?></strong>
                        <span
                            class="sidebar-role <?php echo $sidebarRoleClass; ?>"><?php echo $sidebarRoleLabel; ?></span>
                        <small
                            title="<?php echo $sidebarEmail; ?>"><?php echo $sidebarEmail !== "" ? $sidebarEmail : "Email não informado"; ?></small>
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

        <main class="main-area app-main">
            <header class="topbar dashboard-topbar">
                <div class="topbar-left">
                    <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
                        <i class="bi bi-list"></i>
                    </button>

                    <div>
                        <p class="eyebrow">Portal TI TECH</p>
                        <h1>Dashboard de produtos</h1>
                    </div>
                </div>

                <div class="topbar-actions">
                    <button class="theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema">
                        <i class="bi bi-moon-stars-fill"></i>
                        <span>Modo escuro</span>
                    </button>
                </div>
            </header>

            <section class="dashboard-hero" aria-labelledby="dashboardTitle">
                <div>
                    <span class="eyebrow">Análise visual do inventário</span>
                    <h2 id="dashboardTitle">Veja seus produtos por tipo, status, marca, local e evolução.</h2>
                    <p>
                        Escolha o tipo de gráfico, filtre uma categoria específica e acompanhe quantos ativos existem em
                        cada grupo.
                    </p>
                </div>

                <div class="hero-status-card" aria-live="polite">
                    <i class="bi bi-activity"></i>
                    <div>
                        <strong id="dashboardConnectionStatus">Carregando dados...</strong>
                        <span id="dashboardLastUpdate">Aguardando conexão com o banco.</span>
                    </div>
                </div>
            </section>

            <section class="dashboard-summary-grid" aria-label="Resumo do inventário">
                <article class="summary-card">
                    <span>Total de ativos</span>
                    <strong id="totalAssetsMetric">--</strong>
                    <small>Itens cadastrados no inventário.</small>
                </article>

                <article class="summary-card">
                    <span>Tipos de produtos</span>
                    <strong id="totalTypesMetric">--</strong>
                    <small>Categorias registradas no banco.</small>
                </article>

                <article class="summary-card">
                    <span>Tipo selecionado</span>
                    <strong id="selectedTypeMetric">Todos</strong>
                    <small id="selectedTypeDetail">Visualização geral do inventário.</small>
                </article>

                <article class="summary-card">
                    <span>Maior grupo</span>
                    <strong id="largestTypeMetric">--</strong>
                    <small id="largestTypeDetail">Aguardando dados.</small>
                </article>
            </section>

            <section class="dashboard-panel chart-control-panel" aria-label="Controles do dashboard">
                <div class="dashboard-control-group">
                    <label for="categoryFilter">Tipo de produto</label>
                    <select id="categoryFilter">
                        <option value="todos">Todos os tipos</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="metricFilter">Dados do gráfico</label>
                    <select id="metricFilter">
                        <option value="categorias">Quantidade por tipo</option>
                        <option value="status">Quantidade por status</option>
                        <option value="marcas">Quantidade por marca</option>
                        <option value="locais">Quantidade por localização</option>
                        <option value="evolucao">Evolução de cadastros</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="chartTypeFilter">Tipo de gráfico</label>
                    <select id="chartTypeFilter">
                        <option value="bar">Barras</option>
                        <option value="pie">Pizza</option>
                        <option value="doughnut">Rosca</option>
                        <option value="line">Linhas</option>
                        <option value="polarArea">Polar</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="periodFilter">Período da evolução</label>
                    <select id="periodFilter">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>

                <button class="refresh-dashboard-button" id="refreshDashboard" type="button">
                    <i class="bi bi-arrow-clockwise"></i>
                    Atualizar
                </button>
            </section>

            <section class="dashboard-grid-main">
                <article class="dashboard-panel main-chart-card">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Gráfico principal</span>
                            <h3 id="mainChartTitle">Quantidade por tipo</h3>
                            <p id="mainChartDescription">Distribuição dos ativos cadastrados por categoria de produto.
                            </p>
                        </div>

                        <div class="chart-total-pill">
                            <span id="chartTotalLabel">Total analisado</span>
                            <strong id="chartTotalMetric">--</strong>
                        </div>
                    </div>

                    <div class="chart-wrapper">
                        <canvas id="productsChart" aria-label="Gráfico do dashboard de produtos" role="img"></canvas>
                    </div>
                </article>

                <aside class="dashboard-panel details-card">
                    <div class="panel-heading compact">
                        <div>
                            <span class="eyebrow">Leitura rápida</span>
                            <h3>Dados exibidos</h3>
                        </div>
                    </div>

                    <div id="dashboardRanking" class="ranking-list" aria-live="polite">
                        <div class="ranking-empty">Carregando informações...</div>
                    </div>
                </aside>
            </section>

            <section class="dashboard-panel table-panel" aria-label="Tabela de apoio do dashboard">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Base do gráfico</span>
                        <h3>Tabela dos dados atuais</h3>
                        <p>Use a tabela para conferir os números exatos usados no gráfico.</p>
                    </div>
                </div>

                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Quantidade</th>
                                <th>Participação</th>
                            </tr>
                        </thead>
                        <tbody id="dashboardTableBody">
                            <tr>
                                <td colspan="3">Carregando dados...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="js/dashboard-produtos.js?v=20260626-dashboard-fix"></script>
</body>

</html>