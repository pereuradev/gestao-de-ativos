<?php

// Esta página concentra as configurações do usuário logado.
// Primeiro validamos a sessão, depois buscamos os dados no banco
// e, por fim, usamos essas informações para montar a interface.

declare(strict_types=1);

// Inicia a sessão para conseguir acessar os dados do usuário autenticado.
session_start();

// Se não existir usuário válido na sessão, não deixa acessar a página direto pela URL.
// Nesse caso, o usuário é mandado de volta para a tela de login.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Atalho para escapar textos antes de jogar no HTML.
// Isso evita que algum valor vindo do banco ou da sessão quebre a página
// ou abra brecha para injeção de código no navegador.
function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Busca um campo dentro do perfil e devolve um valor padrão quando ele está vazio.
// Ajuda a evitar vários ifs espalhados no HTML só para mostrar "--".
function campoPerfil(array $perfil, string $campo, string $padrao = "--"): string
{
  $valor = trim((string) ($perfil[$campo] ?? ""));

  return $valor !== "" ? $valor : $padrao;
}

// Formata datas vindas do banco para o padrão brasileiro.
// Se a data vier inválida, a tela continua funcionando e mostra apenas "--".
function formatarDataPerfil(?string $value): string
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

// Monta as iniciais do usuário para usar no avatar do crachá digital.
// Exemplo: "Pietro Pereira" vira "PP".
function iniciaisUsuario(string $nome): string
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

function nomeFotoCrachaSeguro(?string $nome): bool
{
  if ($nome === null || $nome === "") {
    return false;
  }

  return (bool) preg_match('/^cracha-[A-Za-z0-9_-]+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nome);
}

function urlFotoCracha(?string $nome): string
{
  if (!nomeFotoCrachaSeguro($nome)) {
    return "";
  }

  $caminho = __DIR__ . "/../uploads/crachas/" . $nome;

  if (!is_file($caminho)) {
    return "";
  }

  return "../uploads/crachas/" . rawurlencode($nome);
}

// Converte o status do usuário em uma classe CSS.
// Assim o PHP decide o estado e o CSS cuida do visual.
function statusClasseConfiguracao(string $status): string
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

// Começamos usando os dados que já estãos salvos na sessão.
// Se o banco responder, esses dados serão complementados logo abaixo.
require_once __DIR__ . "/../Backend/grupos-acesso-util.php";

$usuario = $_SESSION["usuario"];
$perfil = $usuario;
$erroBanco = "";
$usuarioTipoRaw = strtolower(trim((string) ($usuario["tipo_usuario"] ?? "")));
$usuarioEhAdmin = in_array($usuarioTipoRaw, ["adm", "admin", "administrador"], true);
$permissoesAdministrativas = [
  "gerenciar_configuracoes" => "Gerenciar configuracoes",
];
$grupoPermissoesAdministrativas = [
  "titulo" => "Sistema",
  "descricao" => "Acessos de controle geral do portal.",
  "icone" => "bi-shield-lock-fill",
  "permissoes" => [
    "gerenciar_configuracoes" => "Configuracoes",
  ],
];
$rotulosPermissoes = array_merge(permissoesGruposAcesso(), $permissoesAdministrativas);
$permissoesAgrupadas = array_merge(permissoesGruposAcessoAgrupadas(), [$grupoPermissoesAdministrativas]);
$permissoesUsuario = $usuarioEhAdmin
  ? array_keys($rotulosPermissoes)
  : array_values(array_intersect((array) ($usuario["permissoes_grupos"] ?? []), array_keys($rotulosPermissoes)));

try {
  // Carrega a conexão com o banco.
  // O __DIR__ evita problema de caminho quando o arquivo é chamado de lugares diferentes.
  require __DIR__ . "/../Backend/Conexao.php";

  // Consulta os dados completos do usuário no Supabase/PostgreSQL.
  // A busca usa id ou email para funcionar mesmo se algum desses dados estiver ausente na sessão.
  $stmt = $pdo->prepare("
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
            atualizado_em,
            preferencia_tema,
            preferencia_cor,
            preferencia_tamanho_fonte,
            preferencia_densidade,
            preferencia_movimento,
            preferencia_cursor,
            foto_cracha
          from public.perfis_usuarios
         where id = :id
            or lower(btrim(email)) = lower(btrim(:email))
         limit 1
    ");

  // Os valores sÃ£o enviados separados da SQL para evitar SQL Injection.
  $stmt->execute([
    ":id" => (string) ($usuario["id"] ?? ""),
    ":email" => (string) ($usuario["email"] ?? ""),
  ]);

  $perfilBanco = $stmt->fetch();

  // Se encontrou o usuário no banco, junta os dados da sessão com os dados mais completos.
  // O banco fica com prioridade quando houver campos repetidos.
  if (is_array($perfilBanco)) {
    $perfil = array_merge($usuario, $perfilBanco);
  }

  $permissoesUsuario = permissoesUsuarioGrupoAcesso($pdo, $perfil);

  if (!empty($_SESSION["usuario"]) && is_array($_SESSION["usuario"])) {
    $_SESSION["usuario"] = array_merge($_SESSION["usuario"], [
      "permissoes_grupos" => $permissoesUsuario,
      "preferencia_tema" => (string) ($perfil["preferencia_tema"] ?? "dark"),
      "preferencia_cor" => (string) ($perfil["preferencia_cor"] ?? "teal"),
      "preferencia_tamanho_fonte" => (string) ($perfil["preferencia_tamanho_fonte"] ?? "default"),
      "preferencia_densidade" => (string) ($perfil["preferencia_densidade"] ?? "comfortable"),
      "preferencia_movimento" => (string) ($perfil["preferencia_movimento"] ?? "normal"),
      "preferencia_cursor" => (string) ($perfil["preferencia_cursor"] ?? "enhanced"),
      "foto_cracha" => (string) ($perfil["foto_cracha"] ?? ""),
    ]);
  }
} catch (Throwable) {
  // Não travamos a página se o banco falhar.
  // A tela ainda abre com os dados da sessão e mostra um aviso discreto ao usuário.
  $erroBanco = "Nao foi possivel carregar todos os dados do banco. Mostrando informacoes da sessao.";
}

// A partir daqui, os dados são tratados para exibição.
// Separar essa preparação do HTML deixa a tela mais organizada.
$usuarioTipoRaw = strtolower(trim((string) ($perfil["tipo_usuario"] ?? ($usuario["tipo_usuario"] ?? ""))));
$usuarioEhAdmin = in_array($usuarioTipoRaw, ["adm", "admin", "administrador"], true);

if ($usuarioEhAdmin) {
  $permissoesUsuario = array_keys($rotulosPermissoes);
}

$nomeUsuarioTexto = campoPerfil($perfil, "nome_completo", "Usuario TI TECH");
$tipoUsuarioTexto = campoPerfil($perfil, "tipo_usuario", "Colaborador");
$emailUsuarioTexto = campoPerfil($perfil, "email");
$statusUsuarioTexto = campoPerfil($perfil, "status", "Ativo");
$departamentoUsuarioTexto = campoPerfil($perfil, "departamento");
$empresaUsuarioTexto = campoPerfil($perfil, "empresa");
$celularUsuarioTexto = campoPerfil($perfil, "celular");
$rgUsuarioTexto = campoPerfil($perfil, "rg");
$cpfUsuarioTexto = campoPerfil($perfil, "cpf");
$fotoCrachaNome = trim((string) ($perfil["foto_cracha"] ?? ""));
$fotoCrachaUrl = urlFotoCracha($fotoCrachaNome);
$criadoEm = formatarDataPerfil((string) ($perfil["criado_em"] ?? ""));
$atualizadoEm = formatarDataPerfil((string) ($perfil["atualizado_em"] ?? ""));
$ultimoAcesso = date("d/m/Y H:i");

$csrfToken = e((string) $_SESSION["csrf_token"]);
$nomeUsuario = e($nomeUsuarioTexto);
$tipoUsuario = e($tipoUsuarioTexto);
$emailUsuario = e($emailUsuarioTexto);
$statusUsuario = e($statusUsuarioTexto);
$departamentoUsuario = e($departamentoUsuarioTexto);
$empresaUsuario = e($empresaUsuarioTexto);
$celularUsuario = e($celularUsuarioTexto);
$rgUsuario = e($rgUsuarioTexto);
$cpfUsuario = e($cpfUsuarioTexto);
$iniciais = e(iniciaisUsuario($nomeUsuarioTexto));
$statusClasse = e(statusClasseConfiguracao($statusUsuarioTexto));
$permissoesUsuario = array_values(array_unique(array_intersect($permissoesUsuario, array_keys($rotulosPermissoes))));
$permissoesConcedidas = array_flip($permissoesUsuario);
$totalPermissoesUsuario = count($permissoesUsuario);
$permissoesVisiveis = [];

foreach ($permissoesAgrupadas as $grupoPermissao) {
  $itensPermitidos = [];

  foreach (($grupoPermissao["permissoes"] ?? []) as $codigoPermissao => $rotuloPermissao) {
    if (!isset($permissoesConcedidas[$codigoPermissao])) {
      continue;
    }

    $itensPermitidos[$codigoPermissao] = $rotuloPermissao;
  }

  if ($itensPermitidos) {
    $grupoPermissao["permissoes"] = $itensPermitidos;
    $permissoesVisiveis[] = $grupoPermissao;
  }
}

$resumoPermissoes = $usuarioEhAdmin
  ? "Todas as permissoes do sistema estao liberadas para administradores."
  : "Permissoes liberadas pelos grupos de acesso ativos.";
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <!-- Configurações básicas da página e responsividade. -->
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Configura&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description"
    content="Painel de configura&ccedil;&otilde;es de conta, seguran&ccedil;a e prefer&ecirc;ncias do portal TI TECH Solutions" />
  <!-- ícone da aba do navegador. -->
  <link rel="icon" type="image/png" href="../assets/favicon.png?v=20260630-ti-favicon" />

  <!-- Pré-conexão e fonte principal usada na interface. -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />


  <!-- CSS separado por responsabilidade: base do sistema, efeitos gerais e ajustes especi­ficos desta página. -->
  <link rel="stylesheet" href="../css/pagina-base.css?v=20260731-sidebar-compact" />
  <link rel="stylesheet" href="../css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="../css/ux-profissional.css?v=20260724-toast-contrast" />
  <link rel="stylesheet" href="../css/configuracoes.css?v=20260727-settings-simple" />


  <!-- Scripts carregados com defer para não bloquear a montagem do HTML. -->
  <link rel="stylesheet" href="../css/responsivo-global.css?v=20260803-desktop-density" />
  <script src="../js/animations/efeito-digitacao.js?v=20260803-static-headings" defer></script>
  <script src="../js/ui/feedback-interface.js?v=20260630-reduced-motion" defer></script>
  <script src="../js/core/armazenamento-local.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/animations/entrada-pagina.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/ui/menu-lateral.js?v=20260731-sidebar-compact" defer></script>
  <script src="../js/base-interface.js?v=20260730-sidebar-contract" defer></script>
  <script src="../js/pages/configuracoes.js?v=20260727-identificadores-portugues" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="../js/ui/widgets-react.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <!-- Estrutura principal da aplicação: menu lateral + área de conteúdo. -->
  <div class="app-shell">
    <!-- Menu lateral usado para navegar entre as áreas do sistema. -->
    <?php require __DIR__ . "/../components/sidebar.php"; ?>

    <!-- Conteúdo principal da página. O data-user-role permite que o JavaScript/CSS adaptem comportamentos pelo cargo. -->
    <main class="main-area settings-page" data-user-role="<?php echo e(strtolower($tipoUsuarioTexto)); ?>">
      <!-- Barra superior com título da página e atalho para alternar tema. -->
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-button menu-button" id="openSidebar" type="button" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
          </button>

          <div>
            <p class="eyebrow">Painel de controle</p>
            <h1>
              <span class="typewriter-heading" style="--typewriter-min: 16ch">Configura&ccedil;&otilde;es</span><span
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

      <!-- Bloco de apresentação, da página, dando contexto ao usuário sobre o que ele pode configurar. -->
      <section class="settings-intro" aria-labelledby="settingsTitle">
        <span class="settings-intro-icon" aria-hidden="true">
          <i class="bi bi-sliders2"></i>
        </span>
        <div>
          <p class="section-tag">Central do usu&aacute;rio</p>
          <h2 id="settingsTitle">Sua conta e sua experi&ecirc;ncia em um s&oacute; lugar</h2>
          <p>Revise seus dados, personalize a interface e mantenha o acesso protegido.</p>
        </div>
      </section>

      <!-- Aviso exibido somente quando a consulta ao banco falha. -->
      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <!-- Resumo rápido da conta antes das configurações detalhadas. -->
      <div class="settings-layout">
        <aside class="settings-navigation" aria-label="Se&ccedil;&otilde;es das configura&ccedil;&otilde;es">
          <div class="settings-navigation-profile">
            <?php if ($fotoCrachaUrl !== ""): ?>
              <span class="settings-navigation-avatar has-photo">
                <img src="<?php echo e($fotoCrachaUrl); ?>" alt="" />
              </span>
            <?php else: ?>
              <span class="settings-navigation-avatar" aria-hidden="true"><?php echo $iniciais; ?></span>
            <?php endif; ?>
            <span>
              <strong><?php echo $nomeUsuario; ?></strong>
              <small><?php echo $emailUsuario; ?></small>
            </span>
          </div>

          <nav class="settings-navigation-menu" aria-label="Navega&ccedil;&atilde;o da p&aacute;gina">
            <a class="settings-navigation-link is-active" href="#conta" data-settings-nav aria-current="location">
              <i class="bi bi-person-circle" aria-hidden="true"></i>
              <span>Conta</span>
            </a>
            <a class="settings-navigation-link" href="#interface" data-settings-nav>
              <i class="bi bi-palette2" aria-hidden="true"></i>
              <span>Interface</span>
            </a>
            <a class="settings-navigation-link" href="#seguranca" data-settings-nav>
              <i class="bi bi-shield-lock" aria-hidden="true"></i>
              <span>Seguran&ccedil;a</span>
            </a>
            <a class="settings-navigation-link" href="#sistema" data-settings-nav>
              <i class="bi bi-laptop" aria-hidden="true"></i>
              <span>Sistema</span>
            </a>
          </nav>

          <p class="settings-navigation-note">
            <i class="bi bi-cloud-check" aria-hidden="true"></i>
            Prefer&ecirc;ncias visuais s&atilde;o salvas automaticamente.
          </p>
        </aside>

        <div class="settings-content">
          <section class="settings-overview" aria-label="Resumo das configura&ccedil;&otilde;es">
            <!-- Crachá digital com os principais dados do usuário logado. -->
            <article class="content-card digital-badge-card" id="conta">
              <div class="badge-topline">
                <span>Cracha digital</span>
              </div>

              <div class="badge-main">
                <?php if ($fotoCrachaUrl !== ""): ?>
                  <div class="profile-avatar large-avatar has-photo" data-badge-photo-preview
                    data-initials="<?php echo $iniciais; ?>">
                    <img src="<?php echo e($fotoCrachaUrl); ?>"
                      alt="Foto do crach&aacute; de <?php echo $nomeUsuario; ?>" />
                  </div>
                <?php else: ?>
                  <div class="profile-avatar large-avatar" aria-hidden="true" data-badge-photo-preview
                    data-initials="<?php echo $iniciais; ?>"><?php echo $iniciais; ?></div>
                <?php endif; ?>
                <div>
                  <h2><?php echo $nomeUsuario; ?></h2>
                  <p><?php echo $emailUsuario; ?></p>
                  <div class="badge-tags">
                    <span><?php echo $tipoUsuario; ?></span>
                    <span class="status-badge <?php echo $statusClasse; ?>"><?php echo $statusUsuario; ?></span>
                  </div>
                </div>
              </div>

              <div class="badge-grid">
                <div>
                  <span>Departamento</span>
                  <strong><?php echo $departamentoUsuario; ?></strong>
                </div>
                <div>
                  <span>Empresa</span>
                  <strong><?php echo $empresaUsuario; ?></strong>
                </div>
                <div>
                  <span>&Uacute;ltimo acesso</span>
                  <strong><?php echo e($ultimoAcesso); ?></strong>
                </div>
              </div>

              <form class="badge-photo-form" id="badgePhotoForm" action="../Backend/atualizar-foto-cracha.php"
                method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

                <label class="badge-photo-picker">
                  <input id="badgePhotoInput" name="foto_cracha" type="file" accept="image/jpeg,image/png,image/webp" />
                  <span><i class="bi bi-image"></i>Selecionar foto</span>
                </label>

                <button class="secondary-button compact-button" id="saveBadgePhotoButton" type="submit" disabled>
                  <i class="bi bi-cloud-arrow-up"></i>
                  Salvar foto
                </button>

                <small>JPG, PNG ou WebP ate 2 MB.</small>
              </form>

              <div id="badgePhotoMessage" class="form-message" role="status" aria-live="polite"></div>
            </article>

          </section>

          <!-- Grade principal de cards. Cada article representa uma área de configuração. -->
          <section class="settings-grid" aria-label="Painel de configura&ccedil;&otilde;es">
            <!-- Dados operacionais do perfil, exibidos de forma somente leitura. -->
            <article class="content-card profile-card" aria-labelledby="profileTitle">
              <div class="card-header">
                <div>
                  <p class="section-tag">Conta</p>
                  <h3 id="profileTitle">Perfil operacional</h3>
                </div>
              </div>

              <div class="profile-details">
                <div class="profile-field"><span>Nome</span><strong><?php echo $nomeUsuario; ?></strong></div>
                <div class="profile-field"><span>Email</span><strong><?php echo $emailUsuario; ?></strong></div>
                <div class="profile-field"><span>Cargo</span><strong><?php echo $tipoUsuario; ?></strong></div>
                <div class="profile-field"><span>Departamento</span><strong><?php echo $departamentoUsuario; ?></strong>
                </div>
                <div class="profile-field"><span>Celular</span><strong><?php echo $celularUsuario; ?></strong></div>
                <div class="profile-field"><span>RG</span><strong><?php echo $rgUsuario; ?></strong></div>
                <div class="profile-field"><span>CPF</span><strong><?php echo $cpfUsuario; ?></strong></div>
                <div class="profile-field"><span>Criado em</span><strong><?php echo e($criadoEm); ?></strong></div>
                <div class="profile-field"><span>Atualizado em</span><strong><?php echo e($atualizadoEm); ?></strong>
                </div>
              </div>

              <details class="account-permissions-panel">
                <summary class="account-permissions-summary">
                  <span>
                    <span class="section-tag">Permiss&otilde;es</span>
                    <strong>Acessos liberados</strong>
                    <small><?php echo e($resumoPermissoes); ?></small>
                  </span>
                  <span class="account-permissions-summary-meta">
                    <strong><?php echo e((string) $totalPermissoesUsuario); ?></strong>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                  </span>
                </summary>

                <?php if ($permissoesVisiveis): ?>
                  <div class="account-permissions-grid" aria-label="Permiss&otilde;es liberadas">
                    <?php foreach ($permissoesVisiveis as $grupoPermissao): ?>
                      <section class="account-permission-group">
                        <span class="account-permission-title">
                          <i class="bi <?php echo e((string) ($grupoPermissao["icone"] ?? "bi-shield-check")); ?>"></i>
                          <?php echo e((string) ($grupoPermissao["titulo"] ?? "Permissao")); ?>
                        </span>
                        <div class="account-permission-chips">
                          <?php foreach (($grupoPermissao["permissoes"] ?? []) as $rotuloPermissao): ?>
                            <span><i class="bi bi-check2"></i><?php echo e((string) $rotuloPermissao); ?></span>
                          <?php endforeach; ?>
                        </div>
                      </section>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="account-permissions-empty">
                    <i class="bi bi-lock"></i>
                    <span>Nenhuma permiss&atilde;o liberada para este usu&aacute;rio.</span>
                  </div>
                <?php endif; ?>
              </details>
            </article>

            <!-- Preferências visuais salvas pelo JavaScript, como tema, cor e densidade da interface. -->
            <article class="content-card preferences-card" id="interface" aria-labelledby="interfaceTitle">
              <div class="card-header">
                <div>
                  <p class="section-tag">Interface</p>
                  <h3 id="interfaceTitle">Prefer&ecirc;ncias visuais</h3>
                </div>
                <button class="secondary-button compact-preference-button settings-accent-button" id="resetPreferences"
                  type="button">
                  <i class="bi bi-arrow-counterclockwise"></i>
                  Restaurar
                </button>
              </div>

              <form class="preferences-form" id="preferencesForm">
                <!-- Cores de destaque da interface. O JS converte a posicao no circulo em uma cor hexadecimal. -->
                <fieldset class="preference-group">
                  <legend>Prefer&ecirc;ncia de cor</legend>
                  <div class="accent-wheel-control" data-accent-wheel-control>
                    <button class="accent-color-wheel" id="accentColorWheel" type="button"
                      aria-label="Escolher cor de destaque pelo anel colorido">
                      <span class="accent-wheel-thumb" id="accentWheelThumb" aria-hidden="true"></span>
                    </button>

                    <div class="accent-color-panel">
                      <div class="accent-current-card">
                        <span class="accent-current-swatch" id="accentCurrentSwatch" aria-hidden="true"></span>
                        <div>
                          <span>Cor atual</span>
                          <strong id="accentCurrentLabel">#66D5C2</strong>
                        </div>
                      </div>

                      <label class="accent-hex-field">
                        <span>HEX</span>
                        <input id="accentColorValue" type="text" value="#66D5C2" maxlength="7" spellcheck="false"
                          inputmode="text" />
                      </label>

                      <input class="accent-native-color" id="accentNativeColor" type="color" value="#66d5c2"
                        aria-label="Selecionar cor pelo controle do navegador" />

                      <div class="accent-preset-strip" aria-label="Cores rapidas">
                        <button type="button" style="--preset-color: #66d5c2" data-accent-preset="#66d5c2"
                          aria-label="Usar ciano TI TECH"></button>
                        <button type="button" style="--preset-color: #22c55e" data-accent-preset="#22c55e"
                          aria-label="Usar verde"></button>
                        <button type="button" style="--preset-color: #38bdf8" data-accent-preset="#38bdf8"
                          aria-label="Usar azul"></button>
                        <button type="button" style="--preset-color: #a78bfa" data-accent-preset="#a78bfa"
                          aria-label="Usar violeta"></button>
                        <button type="button" style="--preset-color: #ff2d75" data-accent-preset="#ff2d75"
                          aria-label="Usar magenta"></button>
                      </div>
                    </div>
                  </div>
                </fieldset>

                <!-- Escolha do tema visual: escuro, claro ou automático pelo sistema. -->
                <fieldset class="preference-group">
                  <legend>Modo de tela</legend>
                  <div class="segmented-control three-options" role="radiogroup" aria-label="Modo de tela">
                    <label><input type="radio" name="theme" value="dark" /><span><i class="bi bi-moon-stars-fill"></i>
                        Escuro</span></label>
                    <label><input type="radio" name="theme" value="light" /><span><i class="bi bi-sun-fill"></i>
                        Claro</span></label>
                    <label><input type="radio" name="theme" value="auto" /><span><i class="bi bi-circle-half"></i>
                        Auto</span></label>
                  </div>
                </fieldset>

                <!-- Tamanho da fonte aplicado no site inteiro para melhorar a leitura sem depender do zoom do navegador. -->
                <fieldset class="preference-group">
                  <legend>Tamanho da fonte</legend>
                  <div class="segmented-control four-options font-size-control" role="radiogroup"
                    aria-label="Tamanho da fonte do site">
                    <label><input type="radio" name="fontSize" value="small" /><span><i class="bi bi-type"></i>
                        Pequena</span></label>
                    <label><input type="radio" name="fontSize" value="default" /><span><i class="bi bi-type"></i>
                        Padr&atilde;o</span></label>
                    <label><input type="radio" name="fontSize" value="large" /><span><i class="bi bi-fonts"></i>
                        Grande</span></label>
                    <label><input type="radio" name="fontSize" value="extra" /><span><i class="bi bi-fonts"></i>
                        Extra</span></label>
                  </div>
                </fieldset>

                <!-- Ajustes finos de experiência para adaptar a tela ao jeito de trabalho do usuário. -->
                <fieldset class="preference-group">
                  <legend>Ajustes de UX</legend>
                  <div class="toggle-list">
                    <label class="toggle-row">
                      <span><strong>Interface compacta</strong><small>Reduz espa&ccedil;amentos para ver mais
                          informa&ccedil;&otilde;es.</small></span>
                      <input type="checkbox" id="densityToggle" name="density" value="compact" />
                    </label>
                    <label class="toggle-row">
                      <span><strong>Reduzir anima&ccedil;&otilde;es</strong><small>Deixa transi&ccedil;&otilde;es mais
                          discretas.</small></span>
                      <input type="checkbox" id="motionToggle" name="motion" value="reduced" />
                    </label>
                    <label class="toggle-row">
                      <span><strong>Realce do cursor</strong><small>Aumenta o feedback visual em links, bot&otilde;es e
                          campos.</small></span>
                      <input type="checkbox" id="cursorToggle" name="cursor" value="enhanced" />
                    </label>
                  </div>
                </fieldset>
              </form>
              <div class="form-message success" id="preferencesMessage" role="status"></div>
            </article>

            <!-- Área de segurança. O navegador orienta o usuário e o backend valida e atualiza a senha real. -->
            <article class="content-card security-card wide-card" id="seguranca" aria-labelledby="securityTitle">
              <div class="card-header security-card-header">
                <div class="security-title-group">
                  <span class="security-title-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                  <div>
                    <p class="section-tag">Seguran&ccedil;a da conta</p>
                    <h3 id="securityTitle">Alterar senha</h3>
                    <span class="card-subtitle">Confirme sua identidade e escolha uma senha exclusiva para o
                      portal.</span>
                  </div>
                </div>

              </div>

              <div class="password-change-layout">
                <!-- A confirmação da senha atual reduz o impacto de uma sessão aberta indevidamente. -->
                <form class="password-form password-form-panel" id="passwordForm"
                  action="../Backend/atualizar-senha.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />

                  <div class="password-step-heading">
                    <span class="password-step-number" aria-hidden="true">1</span>
                    <div>
                      <strong>Confirme sua identidade</strong>
                      <small>Digite a senha que voc&ecirc; usa atualmente para entrar.</small>
                    </div>
                  </div>

                  <div class="asset-field password-field">
                    <label for="currentPassword">Senha atual</label>
                    <span class="input-shell password-input-shell">
                      <i class="bi bi-lock" aria-hidden="true"></i>
                      <input id="currentPassword" name="senha_atual" type="password" autocomplete="current-password"
                        maxlength="128" placeholder="Digite sua senha atual" required />
                      <button class="password-visibility-toggle" type="button" data-password-toggle="currentPassword"
                        aria-label="Mostrar senha atual" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                      </button>
                    </span>
                  </div>

                  <div class="password-section-divider" aria-hidden="true"></div>

                  <div class="password-step-heading">
                    <span class="password-step-number" aria-hidden="true">2</span>
                    <div>
                      <strong>Crie sua nova senha</strong>
                      <small>Os requisitos abaixo s&atilde;o atualizados enquanto voc&ecirc; digita.</small>
                    </div>
                  </div>

                  <div class="password-field-grid">
                    <div class="asset-field password-field">
                      <label for="newPassword">Nova senha</label>
                      <span class="input-shell password-input-shell">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <input id="newPassword" name="nova_senha" type="password" autocomplete="new-password"
                          minlength="8" maxlength="128" placeholder="Crie uma senha forte"
                          aria-describedby="passwordRules" required />
                        <button class="password-visibility-toggle" type="button" data-password-toggle="newPassword"
                          aria-label="Mostrar nova senha" aria-pressed="false">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                      </span>
                    </div>

                    <div class="asset-field password-field">
                      <label for="confirmPassword">Confirmar nova senha</label>
                      <span class="input-shell password-input-shell">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        <input id="confirmPassword" name="confirmacao_senha" type="password" autocomplete="new-password"
                          minlength="8" maxlength="128" placeholder="Repita a nova senha"
                          aria-describedby="passwordRules" required />
                        <button class="password-visibility-toggle" type="button" data-password-toggle="confirmPassword"
                          aria-label="Mostrar confirma&ccedil;&atilde;o da senha" aria-pressed="false">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                      </span>
                    </div>
                  </div>

                  <p class="password-caps-warning" id="passwordCapsLock" role="status" aria-live="polite" hidden>
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    Caps Lock est&aacute; ativado.
                  </p>

                  <div class="password-strength-card" aria-live="polite">
                    <div class="password-strength-heading">
                      <span>For&ccedil;a da senha</span>
                      <strong id="strengthLabel">Digite uma nova senha</strong>
                    </div>
                    <div class="strength-track" aria-hidden="true"><span id="strengthBar"></span></div>
                  </div>

                  <ul class="password-rules" id="passwordRules" aria-label="Requisitos da nova senha">
                    <li data-rule="length"><i class="bi bi-circle" aria-hidden="true"></i>Pelo menos 8 caracteres</li>
                    <li data-rule="uppercase"><i class="bi bi-circle" aria-hidden="true"></i>Letra mai&uacute;scula</li>
                    <li data-rule="number"><i class="bi bi-circle" aria-hidden="true"></i>N&uacute;mero</li>
                    <li data-rule="special"><i class="bi bi-circle" aria-hidden="true"></i>Caractere especial</li>
                    <li data-rule="match"><i class="bi bi-circle" aria-hidden="true"></i>Confirma&ccedil;&atilde;o igual
                    </li>
                  </ul>

                  <div id="passwordMessage" class="form-message" role="status" aria-live="polite"></div>

                  <div class="password-form-actions">
                    <span class="password-session-note">
                      <i class="bi bi-info-circle" aria-hidden="true"></i>
                      A senha ser&aacute; atualizada no acesso principal e no perfil local.
                    </span>
                    <button class="primary-button password-submit-button" id="updatePasswordButton" type="submit">
                      <i class="bi bi-shield-check" aria-hidden="true"></i>
                      Salvar nova senha
                    </button>
                  </div>
                </form>

                <aside class="password-guidance-panel" aria-labelledby="passwordGuidanceTitle">
                  <span class="password-guidance-icon" aria-hidden="true"><i class="bi bi-fingerprint"></i></span>
                  <p class="section-tag">Boas pr&aacute;ticas</p>
                  <h4 id="passwordGuidanceTitle">Uma senha mais dif&iacute;cil de adivinhar</h4>
                  <p>Prefira uma combina&ccedil;&atilde;o exclusiva e f&aacute;cil de guardar em um gerenciador de
                    senhas.</p>

                  <ul class="password-tips-list">
                    <li><i class="bi bi-check2" aria-hidden="true"></i>N&atilde;o reutilize a senha do e-mail
                      corporativo.</li>
                    <li><i class="bi bi-check2" aria-hidden="true"></i>Evite nomes, datas e informa&ccedil;&otilde;es
                      pessoais.</li>
                    <li><i class="bi bi-check2" aria-hidden="true"></i>Use um gerenciador para criar e guardar senhas.
                    </li>
                  </ul>

                  <div class="password-sync-note">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <div>
                      <strong>Sincroniza&ccedil;&atilde;o autom&aacute;tica</strong>
                      <span>Depois de salvar, a nova senha passa a valer no pr&oacute;ximo acesso.</span>
                    </div>
                  </div>
                </aside>
              </div>
            </article>

            <!-- Diagnóstico do ambiente do usuário. Os dados com id são preenchidos pelo JavaScript no navegador. -->
            <article class="content-card diagnostics-card wide-card" id="sistema" aria-labelledby="systemTitle">
              <div class="card-header">
                <div>
                  <p class="section-tag">Sistema</p>
                  <h3 id="systemTitle">Diagn&oacute;stico para suporte</h3>
                </div>
                <button class="secondary-button compact-preference-button settings-accent-button" id="copyDiagnostics"
                  type="button">
                  <i class="bi bi-clipboard-check"></i>
                  Copiar informa&ccedil;&otilde;es
                </button>
              </div>
              <div class="diagnostics-grid">
                <div><span>Navegador</span><strong id="diagBrowser">--</strong></div>
                <div><span>Sistema operacional</span><strong id="diagOs">--</strong></div>
                <div><span>Largura da tela</span><strong id="diagWidth">--</strong></div>
                <div><span>Data/hora local</span><strong id="diagTime">--</strong></div>
              </div>
            </article>
          </section>

          <!-- Toast usado para mensagens rápidas sem interromper a navegação. -->
        </div>
      </div>

      <div class="settings-toast" id="settingsToast" role="status" aria-live="polite"></div>
    </main>
  </div>
</body>

</html>