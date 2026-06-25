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

function formatarData(?string $value): string
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
$csrfToken = e((string)$_SESSION["csrf_token"]);

$categorias = [];
$locais = [];
$marcas = [];
$ultimosAtivos = [];
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

    $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

    $marcasStmt = $pdo->prepare("
        select id, nome
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

    $ultimosStmt = $pdo->prepare("
        select
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
         limit 8
    ");
    $ultimosStmt->execute();
    $ultimosAtivos = $ultimosStmt->fetchAll();
} catch (Throwable) {
    $erroBanco = "Nao foi possivel carregar os dados do banco agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Cadastro de ativos | TI TECH Solutions</title>
  <meta name="description" content="Cadastro de novos ativos em estoque conectado ao banco da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260624-focus-fix" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260619-select-options" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260619-stable" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260624-focus-fix" />
  <script src="js/typewriter.js?v=20260619-stable" defer></script>
  <script src="js/ux-profissional.js?v=20260623-restore-content" defer></script>
  <script src="js/app-base.js?v=20260624-common-ui" defer></script>
  <script src="js/cadastro-ativos.js?v=20260624-common-ui" defer></script>
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

        <a class="nav-link" href="marcas-visualizacao.php">
          <i class="bi bi-tags-fill"></i>
          <span>Marcas</span>
        </a>

        <a class="nav-link" href="locais-visualizacao.php">
          <i class="bi bi-geo-alt-fill"></i>
          <span>Localiza&ccedil;&otilde;es</span>
        </a>

        <div class="nav-group open" data-nav-group>
          <button class="nav-link nav-toggle active" type="button" aria-expanded="true" aria-controls="registrationSubmenu">
            <i class="bi bi-folder-plus"></i>
            <span>Cadastros</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </button>

          <div class="nav-submenu" id="registrationSubmenu">
            <a class="active-submenu" href="cadastro-ativos.php">Ativos</a>
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
            <p class="eyebrow">Cadastros</p>
            <h1>
              <span
                class="typewriter-heading"
                style="--typewriter-min: 18ch"
               
              >Cadastro de ativos</span><span  aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="secondary-button compact-button" href="ativos.php">
            <i class="bi bi-grid-3x3-gap-fill"></i>
            Ver ativos
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-moon-stars-fill"></i>
            <span>Modo escuro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero asset-inventory-hero" aria-labelledby="assetsRegistrationTitle">
        <div class="hero-content">
          <h2 id="assetsRegistrationTitle">
            <span
              class="typewriter-heading"
              style="--typewriter-min: 23ch"
              data-typewriter-loop
              data-typewriter-phrases="Novo item de estoque.|Cadastro conectado ao banco.|Entrada registrada no sistema."
            >Novo item de estoque.</span><span  aria-hidden="true"></span>
          </h2>
          <p>
            Cadastre equipamentos, perif&eacute;ricos e acess&oacute;rios direto na tabela de ativos.
            A data de cadastro &eacute; registrada automaticamente pelo banco.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo do estoque">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-hdd-stack-fill"></i>
          </div>

          <div>
            <span>Total de ativos</span>
            <strong id="totalAssetsMetric"><?php echo e((string)$totalAtivos); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-box-seam-fill"></i>
          </div>

          <div>
            <span>Em estoque</span>
            <strong id="availableAssetsMetric"><?php echo e((string)$ativosDisponiveis); ?></strong>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-tags-fill"></i>
          </div>

          <div>
            <span>Categorias</span>
            <strong><?php echo e((string)count($categorias)); ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== "") : ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <section class="asset-registration-layout" aria-label="Cadastro e Ãºltimos ativos">
        <article class="content-card asset-form-card asset-form-card-enhanced">
          <div class="card-header asset-card-header">
            <div>
              <p class="section-tag">Formul&aacute;rio</p>
              <h3>Cadastrar item</h3>
              <span class="card-subtitle">Preencha primeiro os dados obrigat&oacute;rios. Os demais campos ajudam na rastreabilidade do estoque.</span>
            </div>

            <div class="form-badge" aria-label="Campos obrigatÃ³rios">
              <i class="bi bi-asterisk"></i>
              Obrigat&oacute;rios
            </div>
          </div>

          <form id="assetForm" class="asset-form enhanced-asset-form" action="Backend/cadastrar-ativo.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

            <div class="asset-form-grid">
              <div class="form-section-title wide-field">
                <i class="bi bi-box-seam"></i>
                <span>Dados principais</span>
              </div>

              <label class="asset-field wide-field priority-field">
                <span>Nome do ativo <strong>*</strong></span>
                <div class="input-shell">
                  <i class="bi bi-hdd-stack"></i>
                  <input name="nome" type="text" placeholder="Ex: Coletor Zebra MC3300" autocomplete="off" required />
                </div>
                <small class="field-hint">Use um nome f&aacute;cil de encontrar depois, com tipo e modelo.</small>
              </label>

              <label class="asset-field">
                <span>Categoria <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-tags"></i>
                  <select name="categoria_id" required>
                    <option value="">Selecione a categoria</option>
                    <?php foreach ($categorias as $categoria) : ?>
                      <option value="<?php echo e((string)$categoria["id"]); ?>">
                        <?php echo e((string)$categoria["nome"]); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Status <strong>*</strong></span>
                <div class="input-shell select-shell">
                  <i class="bi bi-activity"></i>
                  <select name="status" required>
                    <?php foreach ($statusOptions as $status) : ?>
                      <option value="<?php echo e($status); ?>" <?php echo $status === $statusPadrao ? "selected" : ""; ?>>
                        <?php echo e($status); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Local</span>
                <div class="input-shell select-shell">
                  <i class="bi bi-geo-alt"></i>
                  <select name="local_id">
                    <option value="">Sem local definido</option>
                    <?php foreach ($locais as $local) : ?>
                      <option value="<?php echo e((string)$local["id"]); ?>">
                        <?php echo e((string)$local["nome"]); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </label>

              <label class="asset-field">
                <span>Marca</span>
                <div class="input-shell select-shell">
                  <i class="bi bi-building"></i>
                  <select name="marca">
                    <option value="">Selecione a marca</option>
                    <?php foreach ($marcas as $marca) : ?>
                      <option value="<?php echo e((string)$marca["nome"]); ?>">
                        <?php echo e((string)$marca["nome"]); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <small class="field-hint">Cadastre novas marcas na tela de Marcas para evitar diverg&ecirc;ncias.</small>
              </label>

              <div class="form-section-title wide-field secondary-section">
                <i class="bi bi-upc-scan"></i>
                <span>Identifica&ccedil;&atilde;o e rastreio</span>
              </div>

              <label class="asset-field">
                <span>Propriedade</span>
                <div class="input-shell">
                  <i class="bi bi-shield-check"></i>
                  <input name="propriedade" type="text" list="propertyOptions" placeholder="Ex: TITECHSOLUTIONS" autocomplete="off" />
                </div>
                <datalist id="propertyOptions">
                  <option value="TITECHSOLUTIONS"></option>
                  <option value="TSC"></option>
                </datalist>
              </label>

              <label class="asset-field">
                <span>N&uacute;mero de s&eacute;rie</span>
                <div class="input-shell">
                  <i class="bi bi-123"></i>
                  <input name="numero_serie" type="text" placeholder="Serial do equipamento" autocomplete="off" />
                </div>
              </label>

              <label class="asset-field">
                <span>IMEI</span>
                <div class="input-shell">
                  <i class="bi bi-phone"></i>
                  <input name="imei" type="text" placeholder="Somente se houver" autocomplete="off" />
                </div>
              </label>

              <label class="asset-field wide-field">
                <span>Datasheet</span>
                <div class="input-shell">
                  <i class="bi bi-link-45deg"></i>
                  <input name="datasheet" type="text" placeholder="URL ou refer&ecirc;ncia do datasheet" autocomplete="off" />
                </div>
              </label>

              <label class="asset-field wide-field">
                <span>Descri&ccedil;&atilde;o</span>
                <div class="input-shell textarea-shell">
                  <i class="bi bi-card-text"></i>
                  <textarea name="descricao" rows="4" placeholder="Observa&ccedil;&otilde;es, condi&ccedil;&atilde;o f&iacute;sica ou detalhes do item"></textarea>
                </div>
              </label>
            </div>

            <div id="assetFormMessage" class="form-message" role="status" aria-live="polite"></div>

            <div class="asset-form-actions enhanced-form-actions">
              <button class="form-action-button danger-button" type="reset">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Limpar campos</span>
              </button>

              <button id="assetSubmitButton" class="form-action-button success-button" type="submit">
                <i class="bi bi-plus-circle"></i>
                <span>Cadastrar ativo</span>
              </button>
            </div>
          </form>
        </article>

        <article class="content-card recent-assets-card">
          <div class="card-header">
            <div>
              <p class="section-tag">Banco de dados</p>
              <h3>&Uacute;ltimos ativos cadastrados</h3>
            </div>
          </div>

          <div class="recent-asset-list" id="recentAssetList">
            <?php if (!$ultimosAtivos) : ?>
              <div class="empty-state">
                <i class="bi bi-info-circle"></i>
                <span>Nenhum ativo encontrado.</span>
              </div>
            <?php endif; ?>

            <?php foreach ($ultimosAtivos as $ativo) : ?>
              <div class="recent-asset-item">
                <div>
                  <strong><?php echo e((string)($ativo["nome"] ?? "--")); ?></strong>
                  <span>
                    <?php echo e((string)($ativo["categoria"] ?? "Sem categoria")); ?>
                    &middot;
                    <?php echo e((string)($ativo["status"] ?? "--")); ?>
                  </span>
                </div>

                <small><?php echo e(formatarData((string)($ativo["criado_em"] ?? ""))); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      </section>
    </main>
  </div>
</body>

</html>





