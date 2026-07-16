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

require_once __DIR__ . "/Backend/permissoes-acesso.php";

$podeVisualizarGrupos = usuarioAtualTemPermissao("visualizar_grupos");
$podeEditarGrupos = usuarioAtualTemPermissao("editar_grupos");
$podeCadastrarGrupos = usuarioAtualTemPermissao("cadastrar_grupos");

if (!$podeVisualizarGrupos && !$podeEditarGrupos) {
  $_SESSION["permission_denied_resource"] = "Grupos";
  header("Location: pagina-inicial.php?permissao=negada");
  exit;
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function formatarDataEdicaoGrupo(?string $value): string
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

function iniciaisEdicaoGrupo(string $nome): string
{
  $partes = preg_split("/\s+/", trim($nome)) ?: [];
  $iniciais = "";

  foreach ($partes as $parte) {
    if ($parte === "") {
      continue;
    }

    $iniciais .= strtoupper(substr($parte, 0, 1));

    if (strlen($iniciais) >= 2) {
      break;
    }
  }

  return $iniciais !== "" ? $iniciais : "TT";
}

$usuario = $_SESSION["usuario"];
$csrfToken = e((string) $_SESSION["csrf_token"]);

$grupos = [];
$funcionarios = [];
$membrosPorGrupo = [];
$permissoesPorGrupo = [];
$permissoesCodigosPorGrupo = [];
$permissoesDisponiveis = [];
$permissoesAgrupadas = [];
$totalGrupos = 0;
$totalMembros = 0;
$totalPermissoes = 0;
$erroBanco = "";

try {
  require_once __DIR__ . "/Backend/Conexao.php";
  require_once __DIR__ . "/Backend/grupos-acesso-util.php";

  garantirTabelasGruposAcesso($pdo);
  $permissoesDisponiveis = permissoesGruposAcesso();
  $permissoesAgrupadas = permissoesGruposAcessoAgrupadas();

  $funcionariosStmt = $pdo->prepare("
      select id, nome_completo, email, tipo_usuario, departamento
        from public.perfis_usuarios
       where lower(coalesce(status, 'ativo')) = 'ativo'
    order by nome_completo asc
  ");
  $funcionariosStmt->execute();
  $funcionarios = $funcionariosStmt->fetchAll();

  $resumoStmt = $pdo->prepare("
      select
          (select count(*)::int from public.grupos_acesso) as grupos,
          (select count(*)::int from public.grupos_acesso_membros) as membros,
          (select count(*)::int from public.grupos_acesso_permissoes) as permissoes
  ");
  $resumoStmt->execute();
  $resumo = $resumoStmt->fetch() ?: [];

  $totalGrupos = (int) ($resumo["grupos"] ?? 0);
  $totalMembros = (int) ($resumo["membros"] ?? 0);
  $totalPermissoes = (int) ($resumo["permissoes"] ?? 0);

  $gruposStmt = $pdo->prepare("
      select
          g.id,
          g.nome,
          g.descricao,
          g.status,
          g.criado_em,
          g.atualizado_em,
          count(distinct gm.usuario_id)::int as total_membros,
          count(distinct gp.permissao)::int as total_permissoes
        from public.grupos_acesso g
   left join public.grupos_acesso_membros gm on gm.grupo_id = g.id
   left join public.grupos_acesso_permissoes gp on gp.grupo_id = g.id
    group by g.id, g.nome, g.descricao, g.status, g.criado_em, g.atualizado_em
    order by g.nome asc
  ");
  $gruposStmt->execute();
  $grupos = $gruposStmt->fetchAll();

  $membrosStmt = $pdo->prepare("
      select
          gm.grupo_id,
          gm.usuario_id,
          gm.criado_em,
          u.nome_completo,
          u.email,
          u.tipo_usuario,
          u.departamento
        from public.grupos_acesso_membros gm
        join public.perfis_usuarios u on u.id = gm.usuario_id
    order by u.nome_completo asc
  ");
  $membrosStmt->execute();

  foreach ($membrosStmt->fetchAll() as $membro) {
    $grupoId = (string) ($membro["grupo_id"] ?? "");
    $membrosPorGrupo[$grupoId][] = $membro;
  }

  $permissoesStmt = $pdo->prepare("
      select grupo_id, permissao
        from public.grupos_acesso_permissoes
    order by permissao asc
  ");
  $permissoesStmt->execute();

  foreach ($permissoesStmt->fetchAll() as $permissao) {
    $grupoId = (string) ($permissao["grupo_id"] ?? "");
    $codigo = (string) ($permissao["permissao"] ?? "");
    $permissoesCodigosPorGrupo[$grupoId][] = $codigo;
    $permissoesPorGrupo[$grupoId][] = $permissoesDisponiveis[$codigo] ?? $codigo;
  }
} catch (Throwable) {
  $erroBanco = "Nao foi possivel carregar os grupos agora.";
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />

  <title>Edi&ccedil;&atilde;o de grupos | TI TECH Solutions</title>
  <meta name="description" content="Edicao de grupos de acesso e membros dos colaboradores." />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-ativos.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/cadastro-funcionarios.css?v=20260702-employee-hero-gradient" />
  <link rel="stylesheet" href="css/cadastro-grupos.css?v=20260707-permission-icons" />
  <link rel="stylesheet" href="css/edicao-grupos.css?v=20260703-permission-sections" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260701-admin-employee-register-v2" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/ux-profissional.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/edicao-grupos.js?v=20260707-group-status" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <?php require __DIR__ . "/components/sidebar.php"; ?>

    <main class="main-area employee-registration-page group-registration-page group-edit-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Edi&ccedil;&atilde;o</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 17ch">Editar grupos</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <?php if ($podeCadastrarGrupos): ?>
            <a class="secondary-button compact-button" href="cadastro-grupos.php">
              <i class="bi bi-plus-circle"></i>
              Novo grupo
            </a>
          <?php endif; ?>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero employee-register-hero group-register-hero" aria-labelledby="groupEditTitle">
        <div class="hero-content">
          <p class="section-tag">Controle de acesso</p>
          <h2 id="groupEditTitle">
            <span class="typewriter-heading" style="--typewriter-min: 31ch" data-typewriter-loop
              data-typewriter-phrases="Gerencie membros dos grupos.|Remova acessos sem sair da tela.|Exclua grupos que nao serao usados.">Gerencie
              membros dos grupos.</span><span aria-hidden="true"></span>
          </h2>
          <p>Remova colaboradores de grupos e exclua grupos que nao fazem mais parte da operacao.</p>
        </div>
      </section>

      <section class="metrics-grid employee-registration-metrics" aria-label="Resumo dos grupos">
        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-collection-fill"></i></div>
          <div>
            <span>Grupos</span>
            <strong id="editGroupMetricTotal"><?php echo e((string) $totalGrupos); ?></strong>
            <p>Grupos cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-person-check-fill"></i></div>
          <div>
            <span>Membros</span>
            <strong id="editGroupMetricMembers"><?php echo e((string) $totalMembros); ?></strong>
            <p>Vinculos em grupos</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon"><i class="bi bi-shield-lock"></i></div>
          <div>
            <span>Permiss&otilde;es</span>
            <strong id="editGroupMetricPermissions"><?php echo e((string) $totalPermissoes); ?></strong>
            <p>Regras cadastradas</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status"><?php echo e($erroBanco); ?></div>
      <?php endif; ?>

      <section class="content-card group-edit-card" aria-label="Edicao dos grupos">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Banco de dados</p>
            <h3>Grupos cadastrados</h3>
          </div>

          <div class="records-actions">
            <span id="groupEditResultCount"><?php echo e((string) count($grupos)); ?> registros</span>
          </div>
        </div>

        <div id="groupEditPageMessage" class="group-page-message" role="status" aria-live="polite"></div>

        <div class="group-edit-toolbar">
          <div class="search-box group-edit-search">
            <i class="bi bi-search"></i>
            <input id="groupEditSearch" type="search" placeholder="Buscar grupo, permissao ou funcionario"
              aria-label="Buscar grupo, permissao ou funcionario" autocomplete="off" />
          </div>
        </div>

        <div id="groupEditList" class="group-edit-list">
          <?php foreach ($grupos as $grupo): ?>
            <?php
            $grupoId = (string) ($grupo["id"] ?? "");
            $nome = (string) ($grupo["nome"] ?? "--");
            $descricao = (string) ($grupo["descricao"] ?? "");
            $status = (string) ($grupo["status"] ?? "Ativo");
            $membros = $membrosPorGrupo[$grupoId] ?? [];
            $permissoes = $permissoesPorGrupo[$grupoId] ?? [];
            $permissoesCodigos = $permissoesCodigosPorGrupo[$grupoId] ?? [];
            $buscaMembros = implode(" ", array_map(static fn(array $membro): string => (string) ($membro["nome_completo"] ?? ""), $membros));
            $buscaPermissoes = implode(" ", $permissoes);
            $search = strtolower(trim($nome . " " . $descricao . " " . $status . " " . $buscaMembros . " " . $buscaPermissoes));
            ?>
            <article class="group-edit-item" data-id="<?php echo e($grupoId); ?>" data-name="<?php echo e($nome); ?>"
              data-description="<?php echo e($descricao); ?>"
              data-status="<?php echo e($status); ?>"
              data-members="<?php echo e((string) count($membros)); ?>"
              data-permissions="<?php echo e((string) count($permissoes)); ?>"
              data-permission-codes="<?php echo e(implode(",", $permissoesCodigos)); ?>" data-search="<?php echo e($search); ?>">
              <header class="group-edit-item-header">
                <div>
                  <p class="section-tag">Grupo</p>
                  <h4 data-group-name><?php echo e($nome); ?></h4>
                  <span data-group-description><?php echo e($descricao !== "" ? $descricao : "Sem descricao informada."); ?></span>
                </div>

                <div class="group-edit-actions">
                  <span class="status-badge <?php echo strtolower($status) === "ativo" ? "status-active" : "status-inactive"; ?>" data-group-status>
                    <?php echo e($status); ?>
                  </span>
                  <?php if ($podeEditarGrupos): ?>
                    <button class="table-action edit-group-button" type="button" data-group-action="edit">
                      <i class="bi bi-pencil-square"></i>
                      <span>Editar</span>
                    </button>
                    <button class="table-action delete-group-button" type="button" data-group-action="delete">
                      <i class="bi bi-trash3"></i>
                      <span>Excluir grupo</span>
                    </button>
                  <?php endif; ?>
                </div>
              </header>

              <div class="group-edit-summary">
                <span><strong data-member-count><?php echo e((string) count($membros)); ?></strong> membros</span>
                <span><strong data-permission-count><?php echo e((string) count($permissoes)); ?></strong> permissoes</span>
                <span>Atualizado em <?php echo e(formatarDataEdicaoGrupo((string) ($grupo["atualizado_em"] ?? ""))); ?></span>
              </div>

              <div class="group-permission-list" aria-label="Permissoes do grupo" data-permission-list>
                <?php foreach ($permissoes as $permissao): ?>
                  <span><?php echo e((string) $permissao); ?></span>
                <?php endforeach; ?>
                <?php if (!$permissoes): ?>
                  <span>Nenhuma permissao cadastrada.</span>
                <?php endif; ?>
              </div>

              <div class="group-member-list" data-member-list>
                <?php foreach ($membros as $membro): ?>
                  <?php
                  $membroId = (string) ($membro["usuario_id"] ?? "");
                  $membroNome = (string) ($membro["nome_completo"] ?? "--");
                  ?>
                  <article class="group-member-row" data-member-id="<?php echo e($membroId); ?>">
                    <div class="group-member-avatar" aria-hidden="true"><?php echo e(iniciaisEdicaoGrupo($membroNome)); ?></div>
                    <div class="group-member-info">
                      <strong><?php echo e($membroNome); ?></strong>
                      <span><?php echo e((string) ($membro["email"] ?? "--")); ?></span>
                      <small><?php echo e((string) ($membro["tipo_usuario"] ?? "--")); ?> &middot; <?php echo e((string) ($membro["departamento"] ?? "--")); ?></small>
                    </div>
                    <?php if ($podeEditarGrupos): ?>
                      <button class="table-action remove-member-button" type="button" data-member-action="remove">
                        <i class="bi bi-person-dash"></i>
                        <span>Remover</span>
                      </button>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>

                <?php if (!$membros): ?>
                  <div class="group-member-empty" data-member-empty>
                    <i class="bi bi-info-circle"></i>
                    <span>Nenhum membro neste grupo.</span>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div id="groupEditEmptyState" class="empty-state records-empty" <?php echo $grupos ? "hidden" : ""; ?>>
          <i class="bi bi-info-circle"></i>
          <span>Nenhum grupo encontrado.</span>
        </div>
      </section>
    </main>
  </div>

  <?php if ($podeEditarGrupos): ?>
  <div class="edit-modal-backdrop group-modal-backdrop" id="groupEditModal" hidden>
    <section class="edit-modal-card group-modal-card" role="dialog" aria-modal="true"
      aria-labelledby="groupEditModalTitle">
      <div class="edit-modal-header">
        <div>
          <p class="section-tag">Alterar grupo</p>
          <h3 id="groupEditModalTitle">Dados do grupo</h3>
        </div>

        <button class="icon-button modal-close-button" type="button" aria-label="Fechar edicao"
          data-close-group-modal>
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <form id="groupModalForm" class="enhanced-asset-form" action="Backend/atualizar-grupo.php" method="post"
        novalidate>
        <input id="editGroupId" type="hidden" name="id" />
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

        <div class="asset-form-grid group-modal-grid">
          <label class="asset-field">
            <span>Nome do grupo <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-diagram-3"></i>
              <input id="editGroupName" name="nome" type="text" maxlength="90" required />
            </div>
          </label>

          <label class="asset-field">
            <span>Descri&ccedil;&atilde;o</span>
            <div class="input-shell">
              <i class="bi bi-card-text"></i>
              <input id="editGroupDescription" name="descricao" type="text" maxlength="220" />
            </div>
          </label>

          <label class="asset-field">
            <span>Status do grupo <strong>*</strong></span>
            <div class="input-shell">
              <i class="bi bi-toggle-on"></i>
              <select id="editGroupStatus" name="status" required>
                <option value="Ativo">Ativo</option>
                <option value="Inativo">Inativo</option>
              </select>
            </div>
          </label>

          <div class="form-section-title secondary-section">
            <i class="bi bi-people"></i>
            <span>Funcion&aacute;rios do grupo</span>
          </div>

          <div class="group-selector group-modal-selector wide-field">
            <div class="group-selector-toolbar">
              <div class="search-box group-search-box">
                <i class="bi bi-search"></i>
                <input id="editGroupEmployeeSearch" type="search" placeholder="Buscar funcionario" autocomplete="off" />
              </div>
            </div>

            <div id="editGroupEmployeeList" class="group-check-list group-modal-list">
              <?php foreach ($funcionarios as $funcionario): ?>
                <?php
                $funcionarioId = (string) ($funcionario["id"] ?? "");
                $funcionarioNome = (string) ($funcionario["nome_completo"] ?? "--");
                $funcionarioBusca = strtolower($funcionarioNome . " " . (string) ($funcionario["email"] ?? "") . " " . (string) ($funcionario["departamento"] ?? ""));
                ?>
                <label class="group-check-card" data-search="<?php echo e($funcionarioBusca); ?>" data-modal-member-card>
                  <input type="checkbox" name="membros[]" value="<?php echo e($funcionarioId); ?>" />
                  <span class="group-check-body">
                    <strong><?php echo e($funcionarioNome); ?></strong>
                    <small><?php echo e((string) ($funcionario["email"] ?? "--")); ?></small>
                    <small><?php echo e((string) ($funcionario["tipo_usuario"] ?? "--")); ?> &middot; <?php echo e((string) ($funcionario["departamento"] ?? "--")); ?></small>
                  </span>
                </label>
              <?php endforeach; ?>

              <?php if (!$funcionarios): ?>
                <div class="empty-state records-empty compact-empty-state">
                  <i class="bi bi-info-circle"></i>
                  <span>Nenhum funcionario ativo encontrado.</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-section-title secondary-section">
            <i class="bi bi-shield-check"></i>
            <span>Permiss&otilde;es do grupo</span>
          </div>

          <div class="permission-sections group-modal-permission-sections wide-field">
            <?php foreach ($permissoesAgrupadas as $grupoPermissao): ?>
              <details class="permission-section">
                <summary>
                  <span class="permission-section-icon" aria-hidden="true">
                    <i class="bi <?php echo e((string) ($grupoPermissao["icone"] ?? "bi-shield-check")); ?>"></i>
                  </span>
                  <span class="permission-section-title">
                    <strong><?php echo e((string) ($grupoPermissao["titulo"] ?? "")); ?></strong>
                    <small><?php echo e((string) ($grupoPermissao["descricao"] ?? "")); ?></small>
                  </span>
                  <i class="bi bi-chevron-down permission-section-chevron" aria-hidden="true"></i>
                </summary>

                <div class="permission-section-options">
                  <?php foreach (($grupoPermissao["permissoes"] ?? []) as $codigo => $rotulo): ?>
                    <label class="permission-toggle">
                      <input type="checkbox" name="permissoes[]" value="<?php echo e((string) $codigo); ?>" />
                      <span><?php echo e((string) $rotulo); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="groupModalMessage" class="form-message employee-form-message" role="status" aria-live="polite"></div>

        <div class="asset-form-actions enhanced-form-actions group-modal-actions">
          <button class="form-action-button danger-button" type="button" data-close-group-modal>
            <i class="bi bi-x-lg"></i>
            <span>Cancelar</span>
          </button>

          <button id="saveGroupButton" class="form-action-button success-button" type="submit">
            <i class="bi bi-check-lg"></i>
            <span>Salvar altera&ccedil;&otilde;es</span>
          </button>
        </div>
      </form>
    </section>
  </div>
  <?php endif; ?>
</body>

</html>
