<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

require_once __DIR__ . "/Backend/permissoes-acesso.php";
exigirPermissaoPagina("visualizar_funcionarios", "Funcionarios");

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

function formatarDataCurta(?string $value): string
{
  if (!$value) {
    return "--";
  }

  try {
    return (new DateTimeImmutable($value))
      ->setTimezone(new DateTimeZone("America/Sao_Paulo"))
      ->format("d/m/Y");
  } catch (Throwable) {
    return "--";
  }
}

function iniciaisFuncionario(string $nome): string
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

function statusClasse(string $status): string
{
  $statusNormalizado = strtolower(trim($status));

  if ($statusNormalizado === "ativo") {
    return "status-active";
  }

  if ($statusNormalizado === "inativo") {
    return "status-inactive";
  }

  return "status-neutral";
}

$usuario = $_SESSION["usuario"];

$funcionarios = [];
$totalFuncionarios = 0;
$funcionariosAtivos = 0;
$funcionariosInativos = 0;
$ultimoMovimento = "--";
$erroBanco = "";

if ($accessDenied) {
  http_response_code(403);
} else {
  try {
    require __DIR__ . "/Backend/Conexao.php";

    $resumoStmt = $pdo->prepare("
        select
            count(*)::int as total,
            count(*) filter (where lower(status) = 'ativo')::int as ativos,
            count(*) filter (where lower(status) = 'inativo')::int as inativos,
            max(greatest(criado_em, atualizado_em)) as ultimo_movimento
          from public.perfis_usuarios
    ");
    $resumoStmt->execute();
    $resumo = $resumoStmt->fetch() ?: [];

    $totalFuncionarios = (int) ($resumo["total"] ?? 0);
    $funcionariosAtivos = (int) ($resumo["ativos"] ?? 0);
    $funcionariosInativos = (int) ($resumo["inativos"] ?? 0);
    $ultimoMovimento = formatarData((string) ($resumo["ultimo_movimento"] ?? ""));

    $funcionariosStmt = $pdo->prepare("
        select
            id,
            nome_completo,
            email,
            tipo_usuario,
            departamento,
            empresa,
            rg,
            cpf,
            celular,
            data_nascimento,
            status,
            criado_em,
            atualizado_em
          from public.perfis_usuarios
      order by
            case when lower(status) = 'ativo' then 0 else 1 end,
            greatest(criado_em, atualizado_em) desc,
            nome_completo asc
    ");
    $funcionariosStmt->execute();
    $funcionarios = $funcionariosStmt->fetchAll();
  } catch (Throwable) {
    $erroBanco = "Nao foi possivel carregar os funcionarios agora.";
  }
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Funcion&aacute;rios | TI TECH Solutions</title>
  <meta name="description" content="Lista de funcion&aacute;rios cadastrados no portal interno da TI TECH Solutions" />
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/funcionarios.css?v=20260706-employee-filter-modal" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-search-box-reset" />
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/funcionarios.js?v=20260706-employee-cards-modal" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading" <?php echo $accessDenied ? 'data-permission-dialog-open="true" data-permission-resource="Funcionarios"' : ""; ?>>
  <div class="app-shell">
    <?php require __DIR__ . "/components/sidebar.php"; ?>

    <main class="main-area funcionarios-page">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Equipe</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 17ch">Funcion&aacute;rios</span><span
                aria-hidden="true"></span>
            </h1>
          </div>
        </div>

        <div class="topbar-actions">
          <a class="employee-create-button" href="cadastro-funcionarios.php">
            <i class="bi bi-person-plus-fill"></i>
            <span>Novo funcion&aacute;rio</span>
          </a>

          <button class="theme-toggle" id="themeToggle" type="button">
            <i class="bi bi-sun-fill"></i>
            <span>Modo claro</span>
          </button>
        </div>
      </header>

      <section class="hero-panel compact-hero employees-hero" aria-labelledby="employeesTitle">
        <div class="hero-content">
          <h2 id="employeesTitle">
            <span class="typewriter-heading" style="--typewriter-min: 31ch" data-typewriter-loop
              data-typewriter-phrases="Vis&atilde;o geral dos funcion&aacute;rios.|Dados principais da equipe.|Busca simples e objetiva.">Vis&atilde;o
              geral dos funcion&aacute;rios.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Consulte dados principais da equipe, identifique rapidamente quem est&aacute; ativo ou inativo
            e filtre a lista para apoiar rotinas de suporte, acessos e invent&aacute;rio.
          </p>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Resumo de funcion&aacute;rios">
        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-people-fill"></i>
          </div>

          <div>
            <span>Total</span>
            <strong><?php echo e((string) $totalFuncionarios); ?></strong>
            <p>Funcion&aacute;rios cadastrados</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-check-fill"></i>
          </div>

          <div>
            <span>Ativos</span>
            <strong><?php echo e((string) $funcionariosAtivos); ?></strong>
            <p>Usu&aacute;rios liberados para acesso</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-person-dash-fill"></i>
          </div>

          <div>
            <span>Inativos</span>
            <strong><?php echo e((string) $funcionariosInativos); ?></strong>
            <p>Acesso bloqueado pelo status</p>
          </div>
        </article>

        <article class="metric-card">
          <div class="metric-icon">
            <i class="bi bi-clock-history"></i>
          </div>

          <div>
            <span>&Uacute;ltimo movimento</span>
            <strong class="metric-date"><?php echo e($ultimoMovimento); ?></strong>
            <p>Cadastro ou atualiza&ccedil;&atilde;o mais recente</p>
          </div>
        </article>
      </section>

      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <?php if ($accessDenied): ?>
        <div class="dashboard-status error-status" role="status">
          Apenas administradores podem acessar a pagina de funcionarios.
        </div>
      <?php endif; ?>

      <section class="content-card records-card employees-records-card" aria-labelledby="employeesListTitle">
        <div class="card-header records-header">
          <div>
            <p class="section-tag">Lista</p>
            <h3 id="employeesListTitle">Funcion&aacute;rios cadastrados</h3>
          </div>

          <div class="records-actions">
            <div class="search-box employee-search">
              <i class="bi bi-search"></i>
              <input id="employeeSearch" type="search" placeholder="Buscar funcion&aacute;rios..."
                aria-label="Buscar funcion&aacute;rios" />
            </div>

            <span id="employeeResultCount"><?php echo e((string) count($funcionarios)); ?> registros</span>
            <select id="employeeStatusFilter" aria-label="Filtrar por status">
              <option value="todos">Todos os status</option>
              <option value="ativo">Ativos</option>
              <option value="inativo">Inativos</option>
            </select>
          </div>
        </div>

        <div id="employeeCardList" class="employee-cards-grid">
          <?php if (!$funcionarios): ?>
            <div class="empty-state records-empty employee-empty-row">
              <i class="bi bi-info-circle"></i>
              <span>Nenhum funcion&aacute;rio encontrado.</span>
            </div>
          <?php endif; ?>

          <?php foreach ($funcionarios as $funcionario): ?>
            <?php
            $nomeFuncionario = (string) ($funcionario["nome_completo"] ?? "--");
            $emailFuncionario = (string) ($funcionario["email"] ?? "--");
            $tipoFuncionario = (string) ($funcionario["tipo_usuario"] ?? "--");
            $departamentoFuncionario = (string) ($funcionario["departamento"] ?? "--");
            $empresaFuncionario = (string) ($funcionario["empresa"] ?? "--");
            $celularFuncionario = (string) ($funcionario["celular"] ?? "--");
            $rgFuncionario = (string) ($funcionario["rg"] ?? "--");
            $cpfFuncionario = (string) ($funcionario["cpf"] ?? "--");
            $status = (string) ($funcionario["status"] ?? "--");
            $cadastroFuncionario = formatarData((string) ($funcionario["criado_em"] ?? ""));
            $atualizacaoFuncionario = formatarData((string) ($funcionario["atualizado_em"] ?? ""));
            $nascimentoFuncionario = formatarDataCurta((string) ($funcionario["data_nascimento"] ?? ""));
            $searchText = implode(" ", [
              $nomeFuncionario,
              $emailFuncionario,
              $tipoFuncionario,
              $departamentoFuncionario,
              $empresaFuncionario,
              $celularFuncionario,
              $rgFuncionario,
              $cpfFuncionario,
              $status,
            ]);
            ?>
            <button class="employee-card employee-row" type="button" data-employee-card
              data-status="<?php echo e(strtolower($status)); ?>" data-search="<?php echo e($searchText); ?>"
              data-name="<?php echo e($nomeFuncionario); ?>" data-email="<?php echo e($emailFuncionario); ?>"
              data-role="<?php echo e($tipoFuncionario); ?>" data-department="<?php echo e($departamentoFuncionario); ?>"
              data-company="<?php echo e($empresaFuncionario); ?>" data-phone="<?php echo e($celularFuncionario); ?>"
              data-rg="<?php echo e($rgFuncionario); ?>" data-cpf="<?php echo e($cpfFuncionario); ?>"
              data-birth="<?php echo e($nascimentoFuncionario); ?>" data-status-label="<?php echo e($status); ?>"
              data-created="<?php echo e($cadastroFuncionario); ?>" data-updated="<?php echo e($atualizacaoFuncionario); ?>"
              data-initials="<?php echo e(iniciaisFuncionario($nomeFuncionario)); ?>">
              <span class="employee-card-header">
                <span class="employee-card-avatar" aria-hidden="true"><?php echo e(iniciaisFuncionario($nomeFuncionario)); ?></span>
                <span class="employee-card-identity">
                  <strong><?php echo e($nomeFuncionario); ?></strong>
                  <small><?php echo e($emailFuncionario); ?></small>
                </span>
                <span class="status-badge <?php echo e(statusClasse($status)); ?>"><?php echo e($status); ?></span>
              </span>

              <span class="employee-card-body">
                <span>
                  <i class="bi bi-person-badge"></i>
                  <small>Perfil</small>
                  <strong><?php echo e($tipoFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-diagram-3"></i>
                  <small>Departamento</small>
                  <strong><?php echo e($departamentoFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-buildings"></i>
                  <small>Empresa</small>
                  <strong><?php echo e($empresaFuncionario); ?></strong>
                </span>
                <span>
                  <i class="bi bi-phone"></i>
                  <small>Celular</small>
                  <strong><?php echo e($celularFuncionario); ?></strong>
                </span>
              </span>

              <span class="employee-card-footer">
                <span>
                  <i class="bi bi-calendar2-check"></i>
                  Cadastro em <?php echo e($cadastroFuncionario); ?>
                </span>
                <span class="employee-card-open">
                  Ver detalhes
                  <i class="bi bi-arrow-up-right"></i>
                </span>
              </span>
            </button>
          <?php endforeach; ?>
        </div>

        <div id="employeeEmptyState" class="empty-state records-empty employee-filter-empty" hidden>
          <i class="bi bi-search"></i>
          <span>Nenhum funcion&aacute;rio corresponde aos filtros aplicados.</span>
        </div>
      </section>

      <div id="employeeDetailsModal" class="employee-modal-backdrop" hidden>
        <section class="employee-modal-card" role="dialog" aria-modal="true" aria-labelledby="employeeModalTitle">
          <div class="employee-modal-header">
            <div class="employee-modal-profile">
              <span id="employeeModalInitials" class="employee-modal-avatar" aria-hidden="true">TT</span>
              <div>
                <p class="section-tag">Ficha do funcion&aacute;rio</p>
                <h3 id="employeeModalTitle">Funcion&aacute;rio</h3>
                <span id="employeeModalEmail" class="employee-modal-email">--</span>
              </div>
            </div>

            <button class="icon-button modal-close-button" type="button" data-close-employee-modal
              aria-label="Fechar detalhes do funcion&aacute;rio">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <div class="employee-modal-status-row">
            <span id="employeeModalStatus" class="status-badge status-neutral">--</span>
            <span id="employeeModalRole" class="employee-modal-role">--</span>
          </div>

          <dl class="employee-modal-grid">
            <div>
              <dt><i class="bi bi-diagram-3"></i> Departamento</dt>
              <dd id="employeeModalDepartment">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-buildings"></i> Empresa</dt>
              <dd id="employeeModalCompany">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-phone"></i> Celular</dt>
              <dd id="employeeModalPhone">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-card-text"></i> RG</dt>
              <dd id="employeeModalRg">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-person-vcard"></i> CPF</dt>
              <dd id="employeeModalCpf">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-calendar3"></i> Nascimento</dt>
              <dd id="employeeModalBirth">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-calendar2-check"></i> Criado em</dt>
              <dd id="employeeModalCreated">--</dd>
            </div>
            <div>
              <dt><i class="bi bi-clock-history"></i> Atualizado em</dt>
              <dd id="employeeModalUpdated">--</dd>
            </div>
          </dl>

          <div class="employee-modal-actions">
            <button class="secondary-button" type="button" data-close-employee-modal>
              <i class="bi bi-x-lg"></i>
              <span>Fechar</span>
            </button>
            <a class="employee-create-button" href="cadastro-funcionarios.php">
              <i class="bi bi-person-plus-fill"></i>
              <span>Novo funcion&aacute;rio</span>
            </a>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>

</html>
