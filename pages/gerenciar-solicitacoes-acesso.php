<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
  header('Location: Pagina-login.html?sessao=expirada');
  exit;
}

if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../Backend/permissoes-acesso.php';
exigirPermissaoPagina('gerenciar_solicitacoes_acesso', 'Solicitacoes de acesso');

function escaparSolicitacoes(string $valor): string
{
  return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

$csrfToken = escaparSolicitacoes($_SESSION['csrf_token']);
$resumo = ['pendentes' => 0, 'aprovadas' => 0, 'recusadas' => 0];
$erroResumo = '';

try {
  $consultaResumo = $pdo->query("
        select
            count(*) filter (where status = 'Pendente')::int as pendentes,
            count(*) filter (where status = 'Aprovada')::int as aprovadas,
            count(*) filter (where status = 'Recusada')::int as recusadas
          from public.solicitacoes_acesso
    ");
  $resumoBanco = $consultaResumo->fetch() ?: [];
  $resumo = [
    'pendentes' => (int) ($resumoBanco['pendentes'] ?? 0),
    'aprovadas' => (int) ($resumoBanco['aprovadas'] ?? 0),
    'recusadas' => (int) ($resumoBanco['recusadas'] ?? 0),
  ];
} catch (Throwable) {
  $erroResumo = 'Nao foi possivel carregar o resumo agora.';
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="titech-csrf-token" content="<?php echo $csrfToken; ?>" />
  <title>Solicitacoes de acesso | TI TECH Solutions</title>
  <link rel="icon" type="image/png" href="../assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260803-access-requests" />
  <link rel="stylesheet" href="../css/solicitacoes-acesso.css?v=20260811-modal-status-photo" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260803-desktop-density" />
  <script src="../js/ui/feedback-interface.js?v=20260701-admin-employee-register-v2" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260731-sidebar-compact" defer></script>
  <script src="../js/base-interface.js?v=20260806-arquivos-claros" defer></script>
  <script src="../js/pages/solicitacoes-acesso.js?v=20260811-modal-status-photo" defer></script>
</head>

<body class="theme-dark page-loading">
  <div class="app-shell">
    <?php require __DIR__ . '/../components/sidebar.php'; ?>

    <main class="main-area access-requests-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list" aria-hidden="true"></i>
          </button>
          <div>
            <p class="eyebrow">Controle de acesso</p>
            <h1>Solicitacoes</h1>
          </div>
        </div>
        <div class="topbar-actions">
          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill" aria-hidden="true"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="access-request-hero" aria-labelledby="accessRequestsTitle">
        <div>
          <p class="section-tag">Fila de analise</p>
          <h2 id="accessRequestsTitle">Decida quem pode entrar no portal</h2>
          <p>Confira os dados, corrija informacoes e aprove ou recuse cada pedido.</p>
        </div>
        <span class="access-request-hero-icon" aria-hidden="true"><i class="bi bi-person-check-fill"></i></span>
      </section>

      <section class="access-request-metrics" aria-label="Resumo das solicitacoes">
        <article>
          <span class="metric-symbol pending"><i class="bi bi-hourglass-split"></i></span>
          <div><small>Pendentes</small><strong id="requestMetricPending"><?php echo $resumo['pendentes']; ?></strong>
          </div>
        </article>
        <article>
          <span class="metric-symbol approved"><i class="bi bi-person-check"></i></span>
          <div><small>Aprovadas</small><strong id="requestMetricApproved"><?php echo $resumo['aprovadas']; ?></strong>
          </div>
        </article>
        <article>
          <span class="metric-symbol rejected"><i class="bi bi-person-x"></i></span>
          <div><small>Recusadas</small><strong id="requestMetricRejected"><?php echo $resumo['recusadas']; ?></strong>
          </div>
        </article>
      </section>

      <?php if ($erroResumo !== ''): ?>
        <div class="dashboard-status error-status" role="status"><?php echo escaparSolicitacoes($erroResumo); ?></div>
      <?php endif; ?>

      <section class="access-request-panel" aria-labelledby="requestListTitle">
        <div class="access-request-panel-header">
          <div>
            <p class="section-tag">Solicitacoes recebidas</p>
            <h2 id="requestListTitle">Fila de acessos</h2>
          </div>
          <div class="access-request-filters">
            <label class="access-request-search">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input id="requestSearch" type="search" placeholder="Buscar por nome, e-mail ou CPF" />
            </label>
            <select id="requestStatusFilter" aria-label="Filtrar por status">
              <option value="Pendente">Pendentes</option>
              <option value="Todas">Todas</option>
              <option value="Aprovada">Aprovadas</option>
              <option value="Recusada">Recusadas</option>
            </select>
            <button id="refreshRequests" class="refresh-requests-button" type="button"
              aria-label="Atualizar solicitacoes">
              <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div id="accessRequestList" class="access-request-list" aria-live="polite">
          <div class="access-request-loading"><i class="bi bi-arrow-repeat"></i> Carregando solicitacoes...</div>
        </div>
      </section>
    </main>
  </div>

  <dialog id="requestDetailsDialog" class="access-request-dialog">
    <form id="requestDetailsForm" method="dialog" novalidate>
      <input id="detailsRequestId" type="hidden" />
      <input id="detailsRequestVersion" type="hidden" />
      <header>
        <div class="dialog-person">
          <div class="dialog-photo"><img id="detailsPhoto" alt="Foto da pessoa solicitante" /></div>
          <div>
            <p class="section-tag">Detalhes do pedido</p>
            <h2 id="detailsNameTitle">Solicitacao de acesso</h2>
            <span id="detailsStatus" class="request-status pending">Pendente</span>
          </div>
        </div>
        <button class="dialog-close" type="button" data-close-details aria-label="Fechar detalhes"><i
            class="bi bi-x-lg"></i></button>
      </header>

      <div class="dialog-fields">
        <label><span>Nome completo</span><input name="nome_completo" type="text" required disabled /></label>
        <label><span>E-mail</span><input name="email" type="email" required disabled /></label>
        <label><span>Tipo de usuario</span><select name="tipo_usuario" required disabled>
            <option>Colaborador</option>
            <option>Administrador</option>
          </select></label>
        <label><span>Departamento</span><select name="departamento" required disabled>
            <option value="Comercial">Comercial</option>
            <option value="TI">TI</option>
            <option value="Administrativo">Administra&ccedil;&atilde;o</option>
          </select></label>
        <label><span>Empresa</span><input name="empresa" type="text" required disabled /></label>
        <label><span>Celular</span><input name="celular" type="tel" required disabled /></label>
        <label><span>RG</span><input name="rg" type="text" required disabled /></label>
        <label><span>CPF</span><input name="cpf" type="text" required disabled /></label>
        <label><span>Data de nascimento</span><input name="data_nascimento" type="date" required disabled /></label>
        <label id="newPasswordField" hidden><span>Nova senha temporaria</span><input name="nova_senha" type="password"
            minlength="6" disabled placeholder="Preencha apenas se precisar corrigir" /></label>
      </div>

      <p id="detailsAnalysis" class="details-analysis" hidden></p>
      <div id="detailsMessage" class="details-message" role="status" aria-live="polite"></div>

      <footer>
        <button id="editRequestButton" class="dialog-button secondary" type="button"><i class="bi bi-pencil-square"></i>
          Alterar informacoes</button>
        <button id="cancelEditRequestButton" class="dialog-button ghost" type="button" hidden>Cancelar
          alteracao</button>
        <button id="saveRequestButton" class="dialog-button primary" type="button" hidden><i class="bi bi-check2"></i>
          Salvar alteracoes</button>
        <span class="dialog-action-spacer"></span>
        <button id="rejectRequestDialogButton" class="dialog-button danger" type="button"><i class="bi bi-x-circle"></i>
          Recusar</button>
        <button id="approveRequestDialogButton" class="dialog-button success" type="button"><i
            class="bi bi-person-check"></i> Aceitar</button>
      </footer>
    </form>
  </dialog>

  <dialog id="rejectRequestDialog" class="reject-request-dialog">
    <form id="rejectRequestForm" method="dialog">
      <span class="reject-dialog-icon"><i class="bi bi-person-x"></i></span>
      <h2>Recusar solicitacao?</h2>
      <p>Informe um motivo curto para manter o historico da decisao.</p>
      <label><span>Motivo da recusa</span><textarea id="rejectRequestReason" maxlength="500" required
          placeholder="Ex.: dados nao conferem"></textarea></label>
      <div id="rejectRequestMessage" class="details-message" role="status"></div>
      <footer>
        <button class="dialog-button ghost" type="button" data-cancel-rejection>Cancelar</button>
        <button class="dialog-button danger" type="submit">Confirmar recusa</button>
      </footer>
    </form>
  </dialog>
</body>

</html>
