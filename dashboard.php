<?php
declare(strict_types=1);

// Esta pagina monta a tela do dashboard. Os dados dos graficos chegam por AJAX,
// mas a seguranca da sessao e a estrutura visual principal ficam neste arquivo.
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Sem usuario na sessao, a pagina nao deve carregar direto pela URL.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    header("Location: Pagina-login.html?sessao=expirada");
    exit;
}

// Escapa qualquer texto vindo da sessao antes de mostrar no HTML.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Dados usados no rodape da sidebar. Mantemos valores padrao para a tela nao quebrar
// quando algum campo ainda nao foi preenchido no cadastro do usuario.
$usuario = $_SESSION["usuario"];
$nomeUsuario = e((string) ($usuario["nome_completo"] ?? $usuario["nome"] ?? "Usuario TI TECH"));
$sidebarRoleRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$sidebarIsAdmin = in_array($sidebarRoleRaw, ["adm", "admin", "administrador"], true);
$sidebarRoleLabel = e($sidebarIsAdmin ? "ADM" : "Colaborador");
$sidebarRoleClass = e($sidebarIsAdmin ? "is-admin" : "is-collaborator");
$sidebarEmail = e((string) ($usuario["email"] ?? ""));
$sidebarDepartment = e((string) ($usuario["departamento"] ?? "Sem departamento"));

// Gera as iniciais exibidas no avatar da sidebar usando as duas primeiras partes do nome.
$partesNome = preg_split("/\s+/", trim((string) ($usuario["nome_completo"] ?? $usuario["nome"] ?? ""))) ?: [];
$sidebarInitialsText = "";

foreach ($partesNome as $parte) {
    if ($parte === "") {
        continue;
    }

    $sidebarInitialsText .= strtoupper(substr($parte, 0, 1));

    if (strlen($sidebarInitialsText) >= 2) {
        break;
    }
}

$sidebarInitials = e($sidebarInitialsText !== "" ? $sidebarInitialsText : "TT");
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard | TI TECH Solutions</title>
    <meta name="description"
        content="Dashboard operacional de ativos, categorias, status, marcas, localizacoes e evolucao do inventario da TI TECH Solutions" />

    <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="preconnect" href="https://cdn.jsdelivr.net" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

    <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
    <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
    <link rel="stylesheet" href="css/ux-profissional.css?v=20260702-bottom-toast" />
    <link rel="stylesheet" href="css/dashboard-produtos.css?v=20260702-loading-spinner-fix" />
    <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
    <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
    <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
    <script src="js/app-base.js?v=20260630-reduced-motion" defer></script>
    <script src="js/dashboard-produtos.js?v=20260702-filter-loading-feedback" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
    <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading dashboard-products-page" data-accent="teal">
    <div class="app-shell">
        <!-- Sidebar padrao do sistema. Os links seguem a mesma ordem das outras paginas. -->
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
                    <i class="bi bi-house-door-fill"></i>
                    <span>Página Inicial</span>
                </a>

                <a class="nav-link active" href="dashboard.php" aria-current="page">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Dashboard</span>
                </a>

<?php if ($sidebarIsAdmin): ?>
                <a class="nav-link" href="funcionarios.php">
                    <i class="bi bi-people-fill"></i>
                    <span>Funcionários</span>
                </a>
<?php else: ?>
                <span class="nav-link nav-link-disabled" aria-disabled="true" data-permission-resource="Funcionarios" title="Apenas administradores podem acessar funcionarios">
                    <i class="bi bi-people-fill"></i>
                    <span>Funcionários</span>
                </span>
<?php endif; ?>

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
                        <?php if ($sidebarIsAdmin): ?>
                            <a href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
                            <a href="cadastro-grupos.php">Grupos</a>

                        <?php else: ?>
                            <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Cadastro de funcionarios" title="Apenas administradores podem cadastrar funcionarios">Funcion&aacute;rios</span>
                            <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Cadastro de grupos" title="Apenas administradores podem criar grupos">Grupos</span>

                        <?php endif; ?>
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
                        <?php if ($sidebarIsAdmin): ?>
                        <a href="edicao-grupos.php">Grupos</a>
                        <?php else: ?>
                        <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true" data-permission-resource="Edicao de grupos" title="Apenas administradores podem editar grupos">Grupos</span>
                        <?php endif; ?>
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
            <!-- Topbar fixa com titulo da pagina e botao de tema controlado pelo app-base.js. -->
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
                        <i class="bi bi-sun-fill"></i>
                        <span>Modo claro</span>
                    </button>
                </div>
            </header>

            <!-- Hero do dashboard. O texto principal usa typewriter para alternar as frases. -->
            <section class="dashboard-hero" aria-labelledby="dashboardTitle">
                <div>
                    <span class="eyebrow">Análise visual do inventário</span>
                    <h2 id="dashboardTitle">
                        <span class="typewriter-heading" data-typewriter-loop
                            data-typewriter-phrases="Veja seus produtos por tipo, status, marca, local e evolução.|Filtre um tipo e veja as marcas.|Acompanhe o inventário por grupo.">Veja
                            seus produtos por tipo, status, marca, local e evolução.</span><span
                            aria-hidden="true"></span>
                    </h2>
                    <p>
                        Escolha o tipo de gráfico, filtre uma categoria específica e acompanhe quantos ativos existem em
                        cada grupo.
                    </p>
                </div>

            </section>

            <!-- Texto de status para leitores de tela; visualmente fica escondido no CSS. -->
            <p id="dashboardStatusText" class="dashboard-status-text" role="status" aria-live="polite">
                Carregando dados do banco.
            </p>

            <!-- Cards preenchidos pelo JavaScript depois que o endpoint retorna os dados. -->
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
                    <span>Filtro ativo</span>
                    <strong id="selectedTypeMetric">Todos</strong>
                    <small id="selectedTypeDetail">Visualização geral do inventário.</small>
                </article>

                <article class="summary-card">
                    <span>Maior grupo</span>
                    <strong id="largestTypeMetric">--</strong>
                    <small id="largestTypeDetail">Aguardando dados.</small>
                </article>
            </section>

            <!-- Filtros que controlam qual agrupamento aparece no grafico principal. -->
            <section class="dashboard-panel chart-control-panel" aria-label="Controles do dashboard">
                <div class="dashboard-control-group">
                    <label for="categoryFilter">Tipo de produto</label>
                    <select id="categoryFilter">
                        <option value="todos">Todos os tipos</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="brandFilter">Marca</label>
                    <select id="brandFilter">
                        <option value="todos">Todas as marcas</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="locationFilter">Localiza&ccedil;&atilde;o</label>
                    <select id="locationFilter">
                        <option value="todos">Todos os locais</option>
                    </select>
                </div>

                <div class="dashboard-control-group">
                    <label for="metricFilter">Dados do gr&aacute;fico</label>
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

            <!-- Area principal: grafico grande de um lado e leitura rapida do outro. -->
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

            <!-- Tabela de apoio para quem prefere ler os numeros em formato tabular. -->
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
    </div>
</body>

</html>
