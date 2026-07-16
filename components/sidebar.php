<?php

declare(strict_types=1);

$componentSidebarUsuario = is_array($_SESSION["usuario"] ?? null) ? $_SESSION["usuario"] : [];

$componentSidebarEscape = static function (mixed $value): string {
  return function_exists("e")
    ? e((string) $value)
    : htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$componentSidebarInitials = static function (string $name): string {
  $parts = preg_split("/\s+/", trim($name)) ?: [];
  $initials = "";

  foreach ($parts as $part) {
    if ($part === "") {
      continue;
    }

    $initials .= strtoupper(substr($part, 0, 1));

    if (strlen($initials) >= 2) {
      break;
    }
  }

  return $initials !== "" ? $initials : "TT";
};

$componentSidebarHasPermission = static function (string $permission) use ($componentSidebarUsuario): bool {
  if (function_exists("usuarioGrupoAcessoAdmin") && usuarioGrupoAcessoAdmin($componentSidebarUsuario)) {
    return true;
  }

  $role = strtolower(trim((string) ($componentSidebarUsuario["tipo_usuario"] ?? "")));

  if (in_array($role, ["adm", "admin", "administrador"], true)) {
    return true;
  }

  $permissions = $componentSidebarUsuario["permissoes_grupos"] ?? [];

  return is_array($permissions) && in_array($permission, $permissions, true);
};

$componentSidebarCurrentPage = basename((string) parse_url($_SERVER["SCRIPT_NAME"] ?? "", PHP_URL_PATH));

$componentSidebarIsActive = static function (string $href) use ($componentSidebarCurrentPage): bool {
  return basename($href) === $componentSidebarCurrentPage;
};

$componentSidebarLinkClass = static function (string $href, string $baseClass = "nav-link") use ($componentSidebarIsActive): string {
  return $baseClass . ($componentSidebarIsActive($href) ? " active" : "");
};

$componentSidebarCurrentAttr = static function (string $href) use ($componentSidebarIsActive): string {
  return $componentSidebarIsActive($href) ? ' aria-current="page"' : "";
};

$componentSidebarSubmenuClass = static function (string $href) use ($componentSidebarIsActive): string {
  return $componentSidebarIsActive($href) ? ' class="active-submenu"' : "";
};

$componentSidebarSubmenuIsOpen = static function (array $hrefs) use ($componentSidebarIsActive): bool {
  foreach ($hrefs as $href) {
    if ($componentSidebarIsActive($href)) {
      return true;
    }
  }

  return false;
};

$componentSidebarNameText = (string) (
  $componentSidebarUsuario["nome_completo"]
  ?? $componentSidebarUsuario["nome"]
  ?? "Usuario"
);
$componentSidebarName = $componentSidebarEscape($componentSidebarNameText);
$componentSidebarRoleRaw = strtolower(trim((string) ($componentSidebarUsuario["tipo_usuario"] ?? "")));
$componentSidebarIsAdminRole = function_exists("usuarioGrupoAcessoAdmin")
  ? usuarioGrupoAcessoAdmin($componentSidebarUsuario)
  : in_array($componentSidebarRoleRaw, ["adm", "admin", "administrador"], true);
$componentSidebarRoleLabel = $componentSidebarEscape($componentSidebarIsAdminRole ? "ADM" : "Colaborador");
$componentSidebarRoleClass = $componentSidebarEscape($componentSidebarIsAdminRole ? "is-admin" : "is-collaborator");
$componentSidebarEmail = $componentSidebarEscape((string) ($componentSidebarUsuario["email"] ?? ""));
$componentSidebarDepartment = $componentSidebarEscape((string) ($componentSidebarUsuario["departamento"] ?? "Sem departamento"));
$componentSidebarAvatar = $componentSidebarEscape($componentSidebarInitials($componentSidebarNameText));

$componentSidebarCanViewEmployees = $componentSidebarHasPermission("visualizar_funcionarios");
$componentSidebarCanViewGroups = $componentSidebarHasPermission("visualizar_grupos");
$componentSidebarCanCreateEmployees = $componentSidebarHasPermission("cadastrar_funcionarios");
$componentSidebarCanCreateGroups = $componentSidebarHasPermission("cadastrar_grupos");
$componentSidebarCanEditEmployees = $componentSidebarHasPermission("editar_funcionarios");
$componentSidebarCanEditGroups = $componentSidebarHasPermission("editar_grupos");

$componentSidebarRegistrationPages = [
  "cadastro-ativos.php",
  "marcas.php",
  "propriedades.php",
  "cadastro-funcionarios.php",
  "cadastro-grupos.php",
  "locais.php",
];
$componentSidebarEditingPages = [
  "edicao-ativos.php",
  "edicao-marcas.php",
  "edicao-propriedades.php",
  "edicao-funcionarios.php",
  "edicao-grupos.php",
  "edicao-locais.php",
];
$componentSidebarRegistrationOpen = $componentSidebarSubmenuIsOpen($componentSidebarRegistrationPages);
$componentSidebarEditingOpen = $componentSidebarSubmenuIsOpen($componentSidebarEditingPages);
?>
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
    <a class="<?php echo $componentSidebarLinkClass("pagina-inicial.php"); ?>" href="pagina-inicial.php" <?php echo $componentSidebarCurrentAttr("pagina-inicial.php"); ?>>
      <i class="bi bi-house-door-fill"></i>
      <span>P&aacute;gina Inicial</span>
    </a>

    <a class="<?php echo $componentSidebarLinkClass("dashboard.php"); ?>" href="dashboard.php" <?php echo $componentSidebarCurrentAttr("dashboard.php"); ?>>
      <i class="bi bi-bar-chart-fill"></i>
      <span>Dashboard</span>
    </a>

    <?php if ($componentSidebarCanViewEmployees): ?>
      <a class="<?php echo $componentSidebarLinkClass("funcionarios.php"); ?>" href="funcionarios.php" <?php echo $componentSidebarCurrentAttr("funcionarios.php"); ?>>
        <i class="bi bi-people-fill"></i>
        <span>Funcion&aacute;rios</span>
      </a>
    <?php else: ?>
      <span class="nav-link nav-link-disabled" aria-disabled="true" data-permission-resource="Funcionarios"
        title="Apenas usuarios autorizados podem acessar funcionarios">
        <i class="bi bi-people-fill"></i>
        <span>Funcion&aacute;rios</span>
      </span>
    <?php endif; ?>

    <?php if ($componentSidebarCanViewGroups): ?>
      <a class="<?php echo $componentSidebarLinkClass("grupos-visualizacao.php"); ?>" href="grupos-visualizacao.php" <?php echo $componentSidebarCurrentAttr("grupos-visualizacao.php"); ?>>
        <i class="bi bi-collection-fill"></i>
        <span>Grupos</span>
      </a>
    <?php else: ?>
      <span class="nav-link nav-link-disabled" aria-disabled="true" data-permission-resource="Grupos"
        title="Apenas usuarios autorizados podem acessar grupos">
        <i class="bi bi-collection-fill"></i>
        <span>Grupos</span>
      </span>
    <?php endif; ?>

    <a class="<?php echo $componentSidebarLinkClass("marcas-visualizacao.php"); ?>" href="marcas-visualizacao.php" <?php echo $componentSidebarCurrentAttr("marcas-visualizacao.php"); ?>>
      <i class="bi bi-tags-fill"></i>
      <span>Marcas</span>
    </a>

    <a class="<?php echo $componentSidebarLinkClass("propriedades-visualizacao.php"); ?>"
      href="propriedades-visualizacao.php" <?php echo $componentSidebarCurrentAttr("propriedades-visualizacao.php"); ?>>
      <i class="bi bi-building-check"></i>
      <span>Propriedades</span>
    </a>

    <a class="<?php echo $componentSidebarLinkClass("locais-visualizacao.php"); ?>" href="locais-visualizacao.php" <?php echo $componentSidebarCurrentAttr("locais-visualizacao.php"); ?>>
      <i class="bi bi-geo-alt-fill"></i>
      <span>Localiza&ccedil;&otilde;es</span>
    </a>

    <div class="nav-group<?php echo $componentSidebarRegistrationOpen ? " open" : ""; ?>" data-nav-group>
      <button class="nav-link nav-toggle<?php echo $componentSidebarRegistrationOpen ? " active" : ""; ?>" type="button"
        aria-expanded="<?php echo $componentSidebarRegistrationOpen ? "true" : "false"; ?>"
        aria-controls="registrationSubmenu">
        <i class="bi bi-folder-plus"></i>
        <span>Cadastros</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>

      <div class="nav-submenu" id="registrationSubmenu">
        <a<?php echo $componentSidebarSubmenuClass("cadastro-ativos.php"); ?> href="cadastro-ativos.php">Ativos</a>
        <a<?php echo $componentSidebarSubmenuClass("marcas.php"); ?> href="marcas.php">Marcas</a>
        <a<?php echo $componentSidebarSubmenuClass("propriedades.php"); ?> href="propriedades.php">Propriedades</a>
        <?php if ($componentSidebarCanCreateEmployees): ?>
          <a<?php echo $componentSidebarSubmenuClass("cadastro-funcionarios.php"); ?>
            href="cadastro-funcionarios.php">Funcion&aacute;rios</a>
        <?php else: ?>
          <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true"
            data-permission-resource="Cadastro de funcionarios"
            title="Apenas usuarios autorizados podem cadastrar funcionarios">Funcion&aacute;rios</span>
        <?php endif; ?>
        <?php if ($componentSidebarCanCreateGroups): ?>
          <a<?php echo $componentSidebarSubmenuClass("cadastro-grupos.php"); ?> href="cadastro-grupos.php">
            Grupos</a>
        <?php else: ?>
          <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true"
            data-permission-resource="Cadastro de grupos"
            title="Apenas usuarios autorizados podem criar grupos">Grupos</span>
        <?php endif; ?>
        <a<?php echo $componentSidebarSubmenuClass("locais.php"); ?> href="locais.php">
          Localiza&ccedil;&otilde;es</a>
      </div>
    </div>

    <div class="nav-group<?php echo $componentSidebarEditingOpen ? " open" : ""; ?>" data-nav-group>
      <button class="nav-link nav-toggle<?php echo $componentSidebarEditingOpen ? " active" : ""; ?>" type="button"
        aria-expanded="<?php echo $componentSidebarEditingOpen ? "true" : "false"; ?>" aria-controls="editingSubmenu">
        <i class="bi bi-pencil-square"></i>
        <span>Edi&ccedil;&atilde;o</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>

      <div class="nav-submenu" id="editingSubmenu">
        <a<?php echo $componentSidebarSubmenuClass("edicao-ativos.php"); ?> href="edicao-ativos.php">Ativos</a>
        <a<?php echo $componentSidebarSubmenuClass("edicao-marcas.php"); ?> href="edicao-marcas.php">Marcas</a>
        <a<?php echo $componentSidebarSubmenuClass("edicao-propriedades.php"); ?> href="edicao-propriedades.php">
          Propriedades</a>
        <?php if ($componentSidebarCanEditEmployees): ?>
          <a<?php echo $componentSidebarSubmenuClass("edicao-funcionarios.php"); ?> href="edicao-funcionarios.php">
            Funcion&aacute;rios</a>
        <?php else: ?>
          <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true"
            data-permission-resource="Edicao de funcionarios"
            title="Apenas usuarios autorizados podem editar funcionarios">Funcion&aacute;rios</span>
        <?php endif; ?>
        <?php if ($componentSidebarCanEditGroups): ?>
          <a<?php echo $componentSidebarSubmenuClass("edicao-grupos.php"); ?> href="edicao-grupos.php">Grupos</a>
        <?php else: ?>
          <span class="nav-submenu-disabled nav-link-disabled" aria-disabled="true"
            data-permission-resource="Edicao de grupos"
            title="Apenas usuarios autorizados podem editar grupos">Grupos</span>
        <?php endif; ?>
        <a<?php echo $componentSidebarSubmenuClass("edicao-locais.php"); ?> href="edicao-locais.php">
          Localiza&ccedil;&otilde;es</a>
      </div>
    </div>

    <a class="<?php echo $componentSidebarLinkClass("ativos.php"); ?>" href="ativos.php" <?php echo $componentSidebarCurrentAttr("ativos.php"); ?>>
      <i class="bi bi-hdd-network-fill"></i>
      <span>Ativos</span>
    </a>

    <a class="<?php echo $componentSidebarLinkClass("configuracoes.php"); ?>" href="configuracoes.php" <?php echo $componentSidebarCurrentAttr("configuracoes.php"); ?>>
      <i class="bi bi-gear-fill"></i>
      <span>Configura&ccedil;&otilde;es</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-summary user-summary-card">
      <div class="sidebar-avatar" aria-hidden="true"><?php echo $componentSidebarAvatar; ?></div>
      <div class="sidebar-user-info">
        <strong title="<?php echo $componentSidebarName; ?>"><?php echo $componentSidebarName; ?></strong>
        <span
          class="sidebar-role <?php echo $componentSidebarRoleClass; ?>"><?php echo $componentSidebarRoleLabel; ?></span>
        <small
          title="<?php echo $componentSidebarEmail; ?>"><?php echo $componentSidebarEmail !== "" ? $componentSidebarEmail : "Email nao informado"; ?></small>
        <small title="<?php echo $componentSidebarDepartment; ?>"><?php echo $componentSidebarDepartment; ?></small>
      </div>
    </div>

    <a href="Backend/logout.php" class="logout-button">
      <i class="bi bi-box-arrow-left"></i>
      <span>Sair do sistema</span>
    </a>
  </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
