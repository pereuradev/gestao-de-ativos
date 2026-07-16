<?php

// Esta pÃ¡gina concentra as configuraÃ§Ãµes do usuÃ¡rio logado.
// Primeiro validamos a sessÃ£o, depois buscamos os dados no banco
// e, por fim, usamos essas informaÃ§Ãµes para montar a interface.

declare(strict_types=1);

// Inicia a sessÃ£o para conseguir acessar os dados do usuÃ¡rio autenticado.
session_start();

// Se nÃ£o existir usuÃ¡rio vÃ¡lido na sessÃ£o, nÃ£o deixa acessar a pÃ¡gina direto pela URL.
// Nesse caso, o usuÃ¡rio Ã© mandado de volta para a tela de login.
if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
  header("Location: Pagina-login.html?sessao=expirada");
  exit;
}

// Atalho para escapar textos antes de jogar no HTML.
// Isso evita que algum valor vindo do banco ou da sessÃ£o quebre a pÃ¡gina
// ou abra brecha para injeÃ§Ã£o de cÃ³digo no navegador.
function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// Busca um campo dentro do perfil e devolve um valor padrÃ£o quando ele estÃ¡ vazio.
// Ajuda a evitar vÃ¡rios ifs espalhados no HTML sÃ³ para mostrar "--".
function campoPerfil(array $perfil, string $campo, string $padrao = "--"): string
{
  $valor = trim((string) ($perfil[$campo] ?? ""));

  return $valor !== "" ? $valor : $padrao;
}

// Formata datas vindas do banco para o padrÃ£o brasileiro.
// Se a data vier invÃ¡lida, a tela continua funcionando e mostra apenas "--".
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

// Monta as iniciais do usuÃ¡rio para usar no avatar do crachÃ¡ digital.
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

// Converte o status do usuÃ¡rio em uma classe CSS.
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

// ComeÃ§amos usando os dados que jÃ¡ estÃ£o salvos na sessÃ£o.
// Se o banco responder, esses dados serÃ£o complementados logo abaixo.
require_once __DIR__ . "/Backend/grupos-acesso-util.php";

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
  // Carrega a conexÃ£o com o banco.
  // O __DIR__ evita problema de caminho quando o arquivo Ã© chamado de lugares diferentes.
  require __DIR__ . "/Backend/Conexao.php";

  // Consulta os dados completos do usuÃ¡rio no Supabase/PostgreSQL.
  // A busca usa id ou email para funcionar mesmo se algum desses dados estiver ausente na sessÃ£o.
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
            atualizado_em
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

  // Se encontrou o usuÃ¡rio no banco, junta os dados da sessÃ£o com os dados mais completos.
  // O banco fica com prioridade quando houver campos repetidos.
  if (is_array($perfilBanco)) {
    $perfil = array_merge($usuario, $perfilBanco);
  }

  $permissoesUsuario = permissoesUsuarioGrupoAcesso($pdo, $perfil);

  if (!empty($_SESSION["usuario"]) && is_array($_SESSION["usuario"])) {
    $_SESSION["usuario"]["permissoes_grupos"] = $permissoesUsuario;
  }
} catch (Throwable) {
  // NÃ£o travamos a pÃ¡gina se o banco falhar.
  // A tela ainda abre com os dados da sessÃ£o e mostra um aviso discreto ao usuÃ¡rio.
  $erroBanco = "Nao foi possivel carregar todos os dados do banco. Mostrando informacoes da sessao.";
}

// A partir daqui, os dados sÃ£o tratados para exibiÃ§Ã£o.
// Separar essa preparaÃ§Ã£o do HTML deixa a tela mais organizada.
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
$criadoEm = formatarDataPerfil((string) ($perfil["criado_em"] ?? ""));
$atualizadoEm = formatarDataPerfil((string) ($perfil["atualizado_em"] ?? ""));
$ultimoAcesso = date("d/m/Y H:i");
$codigoInterno = "TT-USER-" . str_pad(substr(preg_replace("/\D/", "", (string) ($perfil["id"] ?? "")), 0, 3) ?: "001", 3, "0", STR_PAD_LEFT);

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
$codigoInternoEscapado = e($codigoInterno);
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
  <!-- ConfiguraÃ§Ãµes bÃ¡sicas da pÃ¡gina e responsividade. -->
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Configura&ccedil;&otilde;es | TI TECH Solutions</title>
  <meta name="description"
    content="Painel de configura&ccedil;&otilde;es de conta, seguran&ccedil;a e prefer&ecirc;ncias do portal TI TECH Solutions" />
  <!-- Ãcone da aba do navegador. -->
  <link rel="icon" type="image/png" href="assets/favicon.png?v=20260630-ti-favicon" />

  <!-- PrÃ©-conexÃ£o e fonte principal usada na interface. -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />


  <!-- CSS separado por responsabilidade: base do sistema, efeitos gerais e ajustes especÃ­ficos desta pÃ¡gina. -->
  <link rel="stylesheet" href="css/pagina-base.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/typewriter.css?v=20260630-reduced-motion" />
  <link rel="stylesheet" href="css/ux-profissional.css?v=20260706-record-counts" />
  <link rel="stylesheet" href="css/configuracoes.css?v=20260707-user-permissions" />


  <!-- Scripts carregados com defer para nÃ£o bloquear a montagem do HTML. -->
  <link rel="stylesheet" href="css/responsivo-global.css?v=20260626-react-responsive" />
  <script src="js/typewriter.js?v=20260630-reduced-motion" defer></script>
  <script src="js/ux-profissional.js?v=20260630-reduced-motion" defer></script>
  <script src="js/app-base.js?v=20260707-group-view-route" defer></script>
  <script src="js/configuracoes.js?v=20260630-system-cursor" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react@18/umd/react.production.min.js" crossorigin defer></script>
  <script src="https://cdn.jsdelivr.net/npm/react-dom@18/umd/react-dom.production.min.js" crossorigin defer></script>
  <script src="js/react-widgets.js?v=20260626-react-responsive" defer></script>
</head>

<body class="theme-dark page-loading">
  <!-- Estrutura principal da aplicaÃ§Ã£o: menu lateral + Ã¡rea de conteÃºdo. -->
  <div class="app-shell">
    <!-- Menu lateral usado para navegar entre as Ã¡reas do sistema. -->
    <?php require __DIR__ . "/components/sidebar.php"; ?>

    <!-- ConteÃºdo principal da pÃ¡gina. O data-user-role permite que o JavaScript/CSS adaptem comportamentos pelo cargo. -->
    <main class="main-area settings-page" data-user-role="<?php echo e(strtolower($tipoUsuarioTexto)); ?>">
      <!-- Barra superior com tÃ­tulo da pÃ¡gina e atalho para alternar tema. -->
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

      <!-- Bloco de apresentaÃ§Ã£o da pÃ¡gina, dando contexto ao usuÃ¡rio sobre o que ele pode configurar. -->
      <section class="hero-panel settings-hero" aria-labelledby="settingsTitle">
        <div class="hero-content">
          <p class="section-tag">Central do usu&aacute;rio</p>
          <h2 id="settingsTitle">
            <span class="typewriter-heading" style="--typewriter-min: 34ch" data-typewriter-loop
              data-typewriter-phrases="Conta, seguran&ccedil;a e experi&ecirc;ncia.|Seu ambiente, do seu jeito.|Prefer&ecirc;ncias com controle e clareza.">Conta,
              seguran&ccedil;a e experi&ecirc;ncia.</span><span aria-hidden="true"></span>
          </h2>
          <p>
            Personalize sua interface, revise dados da conta e ajuste a experi&ecirc;ncia do sistema.
          </p>
        </div>
      </section>

      <!-- Aviso exibido somente quando a consulta ao banco falha. -->
      <?php if ($erroBanco !== ""): ?>
        <div class="dashboard-status error-status" role="status">
          <?php echo e($erroBanco); ?>
        </div>
      <?php endif; ?>

      <!-- Resumo rÃ¡pido da conta antes das configuraÃ§Ãµes detalhadas. -->
      <section class="settings-overview" aria-label="Resumo das configura&ccedil;&otilde;es">
        <!-- CrachÃ¡ digital com os principais dados do usuÃ¡rio logado. -->
        <article class="content-card digital-badge-card" id="conta">
          <div class="badge-topline">
            <span>Cracha digital</span>
            <strong><?php echo $codigoInternoEscapado; ?></strong>
          </div>

          <div class="badge-main">
            <div class="profile-avatar large-avatar" aria-hidden="true"><?php echo $iniciais; ?></div>
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
        </article>

      </section>

      <!-- Grade principal de cards. Cada article representa uma Ã¡rea de configuraÃ§Ã£o. -->
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
            <div class="profile-field"><span>Atualizado em</span><strong><?php echo e($atualizadoEm); ?></strong></div>
          </div>

          <div class="account-permissions-panel" aria-labelledby="accountPermissionsTitle">
            <div class="account-permissions-head">
              <div>
                <p class="section-tag">Permiss&otilde;es</p>
                <h4 id="accountPermissionsTitle">Acessos liberados</h4>
                <span><?php echo e($resumoPermissoes); ?></span>
              </div>
              <strong><?php echo e((string) $totalPermissoesUsuario); ?></strong>
            </div>

            <?php if ($permissoesVisiveis): ?>
              <div class="account-permissions-grid">
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
          </div>
        </article>

        <!-- PreferÃªncias visuais salvas pelo JavaScript, como tema, cor e densidade da interface. -->
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
            <!-- Cores de destaque da interface. O JS lÃª o radio selecionado e aplica a classe/variÃ¡vel correspondente. -->
            <fieldset class="preference-group">
              <legend>Prefer&ecirc;ncia de cor</legend>
              <div class="accent-options" role="radiogroup" aria-label="Prefer&ecirc;ncia de cor">
                <label class="accent-option accent-teal"><input type="radio" name="accent"
                    value="teal" /><span></span>TI TECH</label>
                <label class="accent-option accent-green"><input type="radio" name="accent"
                    value="green" /><span></span>Verde positivo</label>
                <label class="accent-option accent-blue"><input type="radio" name="accent"
                    value="blue" /><span></span>Azul tecnologia</label>
                <label class="accent-option accent-violet"><input type="radio" name="accent"
                    value="violet" /><span></span>Violeta</label>
              </div>
            </fieldset>

            <!-- Escolha do tema visual: escuro, claro ou automÃ¡tico pelo sistema. -->
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

            <!-- Ajustes finos de experiÃªncia para adaptar a tela ao jeito de trabalho do usuÃ¡rio. -->
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

        <!-- Ãrea de seguranÃ§a. A validaÃ§Ã£o visual da senha fica no JS; a troca real precisa ser feita no backend. -->
        <article class="content-card security-card wide-card" id="seguranca" aria-labelledby="securityTitle">
          <div class="card-header">
            <div>
              <p class="section-tag">Seguran&ccedil;a</p>
              <h3 id="securityTitle">Prote&ccedil;&atilde;o da conta</h3>
            </div>
          </div>

          <div class="security-layout">
            <!-- FormulÃ¡rio de senha preparado para receber integraÃ§Ã£o real depois. -->
            <form class="password-form" id="passwordForm">
              <label class="asset-field">
                <span>Senha atual</span>
                <span class="input-shell"><i class="bi bi-lock"></i><input id="currentPassword" type="password"
                    autocomplete="current-password" /></span>
              </label>
              <label class="asset-field">
                <span>Nova senha</span>
                <span class="input-shell"><i class="bi bi-key"></i><input id="newPassword" type="password"
                    autocomplete="new-password" /></span>
              </label>
              <label class="asset-field">
                <span>Confirmar nova senha</span>
                <span class="input-shell"><i class="bi bi-check2-circle"></i><input id="confirmPassword" type="password"
                    autocomplete="new-password" /></span>
              </label>

              <div class="password-strength" aria-live="polite">
                <div class="strength-track"><span id="strengthBar"></span></div>
                <strong id="strengthLabel">Digite uma nova senha</strong>
              </div>

              <ul class="password-rules" id="passwordRules">
                <li data-rule="length">Pelo menos 8 caracteres</li>
                <li data-rule="uppercase">Letra mai&uacute;scula</li>
                <li data-rule="number">N&uacute;mero</li>
                <li data-rule="special">Caractere especial</li>
                <li data-rule="match">Confirma&ccedil;&atilde;o igual</li>
              </ul>

              <button class="primary-button" id="updatePasswordButton" type="submit">
                <i class="bi bi-shield-check"></i>
                Atualizar senha
              </button>
            </form>

          </div>
        </article>

        <!-- DiagnÃ³stico do ambiente do usuÃ¡rio. Os dados com id sÃ£o preenchidos pelo JavaScript no navegador. -->
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

      <!-- Toast usado para mensagens rÃ¡pidas sem interromper a navegaÃ§Ã£o. -->
      <div class="settings-toast" id="settingsToast" role="status" aria-live="polite"></div>
    </main>
  </div>
</body>

</html>
