(function () {
// Script base carregado nas paginas internas. Ele concentra tema, sidebar,
// preferencias visuais e pequenos helpers usados por varios modulos.
const THEME_TRANSITION_MS = 660;
const SIDEBAR_WIDTH_STORAGE_KEY = "titech-sidebar-width";
const SIDEBAR_DEFAULT_WIDTH = 292;
const SIDEBAR_MIN_WIDTH = 236;
const SIDEBAR_MAX_WIDTH = 392;
const SIDEBAR_DESKTOP_QUERY = "(min-width: 921px)";
const FONT_SIZE_OPTIONS = {
  small: 15,
  default: 16,
  large: 17,
  extra: 18,
};
const CUSTOM_CURSOR_INTERACTIVE_SELECTOR = [
  "a",
  "button",
  "input",
  "label",
  "textarea",
  "select",
  ".form-control",
  ".input-shell",
  ".theme-toggle",
  ".nav-link",
  ".nav-toggle",
  ".sidebar-resize-handle",
  "[role='button']",
  "[role='tab']",
  "[tabindex]:not([tabindex='-1'])",
].join(", ");
const PAGE_PERMISSION_RULES = {
  "dashboard.php": { permission: "visualizar_dashboard", resource: "Dashboard" },
  "ativos.php": { permission: "visualizar_ativos", resource: "Ativos" },
  "cadastro-ativos.php": { permission: "cadastrar_ativos", resource: "Cadastro de ativos" },
  "edicao-ativos.php": { permission: "editar_ativos", resource: "Edicao de ativos" },
  "marcas-visualizacao.php": { permission: "visualizar_marcas", resource: "Marcas" },
  "marcas.php": { permission: "cadastrar_marcas", resource: "Cadastro de marcas" },
  "edicao-marcas.php": { permission: "editar_marcas", resource: "Edicao de marcas" },
  "propriedades-visualizacao.php": { permission: "visualizar_propriedades", resource: "Propriedades" },
  "propriedades.php": { permission: "cadastrar_propriedades", resource: "Cadastro de propriedades" },
  "edicao-propriedades.php": { permission: "editar_propriedades", resource: "Edicao de propriedades" },
  "locais-visualizacao.php": { permission: "visualizar_locais", resource: "Localizacoes" },
  "locais.php": { permission: "cadastrar_locais", resource: "Cadastro de localizacoes" },
  "edicao-locais.php": { permission: "editar_locais", resource: "Edicao de localizacoes" },
  "funcionarios.php": { permission: "visualizar_funcionarios", resource: "Funcionarios" },
  "grupos-visualizacao.php": { permission: "visualizar_grupos", resource: "Grupos" },
  "cadastro-funcionarios.php": { permission: "cadastrar_funcionarios", resource: "Cadastro de funcionarios" },
  "edicao-funcionarios.php": { permission: "editar_funcionarios", resource: "Edicao de funcionarios" },
  "cadastro-grupos.php": { permission: "cadastrar_grupos", resource: "Cadastro de grupos" },
  "edicao-grupos.php": { permission: "editar_grupos", resource: "Edicao de grupos" },
};
const DISABLED_PERMISSION_LINKS = {
  Funcionarios: { permission: "visualizar_funcionarios", href: "funcionarios.php" },
  Grupos: { permission: "visualizar_grupos", href: "grupos-visualizacao.php" },
  "Cadastro de funcionarios": { permission: "cadastrar_funcionarios", href: "cadastro-funcionarios.php" },
  "Edicao de funcionarios": { permission: "editar_funcionarios", href: "edicao-funcionarios.php" },
  "Cadastro de grupos": { permission: "cadastrar_grupos", href: "cadastro-grupos.php" },
  "Edicao de grupos": { permission: "editar_grupos", href: "edicao-grupos.php" },
};

// Paletas que podem ser escolhidas nas configuracoes do usuario.
const ACCENT_THEMES = {
  teal: {
    cyan: "#4aa3c7",
    teal: "#4fc7b1",
    mint: "#66d5c2",
    accent: "#66d5c2",
  },
  green: {
    cyan: "#22c55e",
    teal: "#16a34a",
    mint: "#86efac",
    accent: "#22c55e",
  },
  blue: {
    cyan: "#38bdf8",
    teal: "#2563eb",
    mint: "#7dd3fc",
    accent: "#38bdf8",
  },
  violet: {
    cyan: "#a78bfa",
    teal: "#7c3aed",
    mint: "#c4b5fd",
    accent: "#a78bfa",
  },
};

let themeTimer = null;
let systemThemeListenerAttached = false;
let customCursorReady = false;
let customCursorElement = null;
let permissionDialogElement = null;
let permissionDialogPreviousFocus = null;

document.addEventListener("DOMContentLoaded", () => {
  applyCursorPreference(getSavedItem("titech-cursor") || "enhanced");
  hydrateSidebarProfile();
  setupPermissionDeniedTriggers();
});

function getSavedItem(key) {
  // localStorage pode falhar em navegador restrito, entao sempre acessamos com try/catch.
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function setSavedItem(key, value) {
  // Salva preferencias de interface sem quebrar a pagina caso o navegador bloqueie.
  try {
    localStorage.setItem(key, value);
  } catch {
    return;
  }
}

async function hydrateSidebarProfile() {
  // A sidebar nasce com dados da sessao PHP, mas este refresh corrige sessoes antigas sem novo login.
  if (!document.querySelector(".sidebar-user-info")) {
    return;
  }

  try {
    const response = await fetch("Backend/usuario-sessao.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => null);

    if (!response.ok || !result?.ok || !result.usuario) {
      return;
    }

    updateSidebarProfile(result.usuario);
    applyNavigationPermissions(result.usuario);
  } catch {
    return;
  }
}

function updateSidebarProfile(usuario) {
  const summary = document.querySelector(".sidebar-user-info");
  const avatar = document.querySelector(".sidebar-avatar");

  if (!summary) {
    return;
  }

  const name = String(usuario.nome_completo || "Usuario").trim() || "Usuario";
  const email = String(usuario.email || "").trim();
  const department = String(usuario.departamento || "").trim() || "Sem departamento";
  const nameElement = summary.querySelector("strong");
  const smalls = summary.querySelectorAll("small");

  if (avatar && usuario.iniciais) {
    avatar.textContent = usuario.iniciais;
  }

  if (nameElement) {
    nameElement.textContent = name;
    nameElement.title = name;
  }

  if (smalls[0]) {
    smalls[0].textContent = email || "Email nao informado";
    smalls[0].title = email || "Email nao informado";
  }

  if (smalls[1]) {
    smalls[1].textContent = department;
    smalls[1].title = department;
  }
}

function applyNavigationPermissions(usuario) {
  const permissions = new Set(Array.isArray(usuario?.permissoes) ? usuario.permissoes : []);

  if (usuario?.is_admin) {
    ensureGroupViewNavigationLink(permissions);
    return;
  }

  enableAllowedDisabledLinks(permissions);
  ensureGroupViewNavigationLink(permissions);

  document.querySelectorAll(".sidebar-nav a[href]").forEach((link) => {
    const rule = PAGE_PERMISSION_RULES[getPageNameFromHref(link.getAttribute("href"))];

    if (!rule || permissionRuleIsAllowed(permissions, rule)) {
      return;
    }

    disableNavigationLink(link, rule.resource);
  });

  setupPermissionDeniedTriggers();
}

function enableAllowedDisabledLinks(permissions) {
  document.querySelectorAll(".nav-link-disabled, .disabled-action").forEach((item) => {
    const rule = DISABLED_PERMISSION_LINKS[item.dataset.permissionResource];

    if (!rule || !permissionRuleIsAllowed(permissions, rule)) {
      return;
    }

    const link = document.createElement("a");
    link.href = rule.href;
    link.className = item.className;
    link.classList.remove("nav-link-disabled", "disabled-action");

    if (item.classList.contains("nav-submenu-disabled")) {
      link.classList.remove("nav-submenu-disabled");
    }

    link.innerHTML = item.innerHTML;
    item.replaceWith(link);
  });
}

function ensureGroupViewNavigationLink(permissions) {
  if (!permissions.has("visualizar_grupos") || document.querySelector('.sidebar-nav a[href="grupos-visualizacao.php"]')) {
    return;
  }

  const sidebarNav = document.querySelector(".sidebar-nav");
  const reference = document.querySelector('.sidebar-nav a[href="funcionarios.php"], .sidebar-nav [data-permission-resource="Funcionarios"]')
    || document.querySelector('.sidebar-nav a[href="dashboard.php"]');

  if (!sidebarNav || !reference) {
    return;
  }

  const link = document.createElement("a");
  link.className = "nav-link";
  link.href = "grupos-visualizacao.php";

  if (getPageNameFromHref(window.location.href) === "grupos-visualizacao.php") {
    link.classList.add("active");
  }

  link.innerHTML = '<i class="bi bi-collection-fill"></i><span>Grupos</span>';
  reference.insertAdjacentElement("afterend", link);
}

function permissionRuleIsAllowed(permissions, rule) {
  const requiredPermissions = Array.isArray(rule.permissions)
    ? rule.permissions
    : [rule.permission].filter(Boolean);

  return requiredPermissions.some((permission) => permissions.has(permission));
}

function getPageNameFromHref(href) {
  try {
    const url = new URL(href || "", window.location.href);
    const parts = url.pathname.split("/").filter(Boolean);

    return (parts.pop() || "").toLowerCase();
  } catch {
    return String(href || "").split("?")[0].split("/").pop().toLowerCase();
  }
}

function disableNavigationLink(link, resource) {
  const disabled = document.createElement("span");
  const isTopLevel = link.classList.contains("nav-link");

  disabled.className = link.className || "nav-submenu-disabled";
  disabled.classList.remove("active", "active-submenu");
  disabled.classList.add("nav-link-disabled");

  if (!isTopLevel) {
    disabled.classList.add("nav-submenu-disabled");
  }

  disabled.innerHTML = link.innerHTML;
  disabled.setAttribute("aria-disabled", "true");
  disabled.setAttribute("data-permission-resource", resource);
  disabled.setAttribute("title", `Voce nao tem permissao para acessar ${resource}`);
  link.replaceWith(disabled);
}

function updateBrandLogo(isDark) {
  // Troca o logo para manter contraste correto no modo claro e escuro.
  document.querySelectorAll(".brand-logo").forEach((logo) => {
    logo.src = isDark ? "assets/logo-branca.png" : "assets/Logo.png";
  });
}

function startPageAnimation() {
  // Remove a classe inicial depois do primeiro frame para liberar a animacao de entrada.
  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

function loadSavedTheme() {
  // Restaura tema e preferencias antes de configurar os controles da pagina.
  applyTheme(getSavedItem("titech-theme") || "dark");
  loadInterfacePreferences();
  setupSystemThemeListener();
}

function setupThemeToggle() {
  // Liga o botao de tema, quando ele existe na pagina atual.
  const themeToggle = document.getElementById("themeToggle");

  if (!themeToggle) return;

  themeToggle.addEventListener("click", () => {
    const isDark = document.body.classList.contains("theme-dark");
    const nextTheme = isDark ? "light" : "dark";

    clearTimeout(themeTimer);
    document.body.classList.add("theme-switching");
    applyTheme(nextTheme);
    setSavedItem("titech-theme", nextTheme);

    if (typeof window.onThemeChanged === "function") {
      window.onThemeChanged(nextTheme);
    }

    themeTimer = setTimeout(() => {
      document.body.classList.remove("theme-switching");
    }, THEME_TRANSITION_MS);
  });
}

function applyTheme(theme) {
  // Aceita dark, light ou auto. Qualquer outro valor volta para dark.
  const themeToggle = document.getElementById("themeToggle");
  const nextTheme = ["dark", "light", "auto"].includes(theme) ? theme : "dark";
  const isDark = resolveThemeMode(nextTheme) === "dark";

  document.body.classList.toggle("theme-dark", isDark);
  document.body.classList.toggle("theme-light", !isDark);
  document.body.dataset.themePreference = nextTheme;
  updateBrandLogo(isDark);

  if (!themeToggle) return;

  const icon = themeToggle.querySelector("i");
  const label = themeToggle.querySelector("span");

  if (icon) {
    icon.className = isDark ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
  }

  if (label) {
    label.textContent = isDark ? "Modo claro" : "Modo escuro";
  }
}

function resolveThemeMode(theme) {
  // No modo auto, o navegador informa se o sistema prefere tema claro.
  if (theme !== "auto") {
    return theme === "light" ? "light" : "dark";
  }

  return window.matchMedia?.("(prefers-color-scheme: light)")?.matches ? "light" : "dark";
}

function setupSystemThemeListener() {
  // Quando o usuario escolhe auto, reagimos se o tema do sistema mudar.
  if (systemThemeListenerAttached || !window.matchMedia) return;

  const mediaQuery = window.matchMedia("(prefers-color-scheme: light)");
  const updateAutoTheme = () => {
    if (getSavedItem("titech-theme") === "auto") {
      applyTheme("auto");
    }
  };

  mediaQuery.addEventListener?.("change", updateAutoTheme);
  systemThemeListenerAttached = true;
}

function loadInterfacePreferences() {
  // Preferencias salvas deixam as paginas com a mesma aparencia escolhida pelo usuario.
  applyAccent(getSavedItem("titech-accent") || "teal");
  applyFontSizePreference(getSavedItem("titech-font-size") || "default");
  applyDensity(getSavedItem("titech-density") || "comfortable");
  applyMotionPreference(getSavedItem("titech-motion") || "normal");
  applyCursorPreference(getSavedItem("titech-cursor") || "enhanced");
  applySavedSidebarWidth();
}

function applyAccent(accent) {
  // Atualiza variaveis CSS globais para botoes, graficos e detalhes visuais.
  const nextAccent = Object.hasOwn(ACCENT_THEMES, accent) ? accent : "teal";
  const palette = ACCENT_THEMES[nextAccent];

  document.body.dataset.accent = nextAccent;
  document.body.style.setProperty("--cyan", palette.cyan);
  document.body.style.setProperty("--teal", palette.teal);
  document.body.style.setProperty("--mint", palette.mint);
  document.body.style.setProperty("--accent", palette.accent);

  window.dispatchEvent(new CustomEvent("titech:accent-change", {
    detail: { accent: nextAccent, palette },
  }));
}

function applyDensity(density) {
  // Densidade compacta reduz espacamentos sem criar outro CSS completo.
  document.body.dataset.density = density === "compact" ? "compact" : "comfortable";
}

function applyFontSizePreference(size) {
  // A escala fica no html para que todos os textos em rem acompanhem a preferencia.
  const nextSize = Object.hasOwn(FONT_SIZE_OPTIONS, size) ? size : "default";

  document.documentElement.dataset.fontSize = nextSize;
  document.documentElement.style.fontSize = `${FONT_SIZE_OPTIONS[nextSize]}px`;

  window.dispatchEvent(new CustomEvent("titech:font-size-change", {
    detail: { size: nextSize, pixels: FONT_SIZE_OPTIONS[nextSize] },
  }));
}

function applyMotionPreference(motion) {
  // Preferencia de movimento reduz animacoes para quem precisa de menos movimento.
  const nextMotion = motion === "reduced" ? "reduced" : "normal";

  document.body.dataset.motion = nextMotion;

  window.dispatchEvent(new CustomEvent("titech:motion-change", {
    detail: { motion: nextMotion },
  }));
}

function applyCursorPreference(cursor) {
  // O cursor personalizado segue o estilo do login e pode ser desligado nas configuracoes.
  const isEnhanced = cursor === "enhanced";

  if (!document.body) {
    return;
  }

  document.body.dataset.cursor = isEnhanced ? "enhanced" : "normal";
  setCustomCursorEnabled(isEnhanced);
}

function setCustomCursorEnabled(isEnabled) {
  const shouldEnable = Boolean(isEnabled && isCustomCursorSupported());

  if (shouldEnable) {
    setupCustomCursor();
  }

  document.documentElement.classList.toggle("custom-cursor-enabled", shouldEnable);
  document.body.classList.toggle("custom-cursor-enabled", shouldEnable);

  if (!shouldEnable) {
    document.body.classList.remove("cursor-visible", "cursor-hover", "cursor-click");
  }
}

function setupCustomCursor() {
  if (customCursorReady || !document.body || !isCustomCursorSupported()) {
    return;
  }

  customCursorElement = document.querySelector(".custom-cursor");

  if (!customCursorElement) {
    customCursorElement = document.createElement("div");
    customCursorElement.className = "custom-cursor";
    customCursorElement.setAttribute("aria-hidden", "true");
    document.body.appendChild(customCursorElement);
  }

  customCursorReady = true;

  window.addEventListener("mousemove", updateCustomCursorPosition, { passive: true });
  window.addEventListener("mouseleave", hideCustomCursor);
  window.addEventListener("blur", hideCustomCursor);
  window.addEventListener("mousedown", pressCustomCursor);
  window.addEventListener("mouseup", releaseCustomCursor);
  document.addEventListener("mouseover", updateCustomCursorHover);
  document.addEventListener("mouseout", clearCustomCursorHover);
}

function updateCustomCursorPosition(event) {
  if (!isCustomCursorActive() || !customCursorElement) {
    return;
  }

  document.body.classList.add("cursor-visible");
  customCursorElement.style.left = `${event.clientX}px`;
  customCursorElement.style.top = `${event.clientY}px`;
}

function hideCustomCursor() {
  document.body.classList.remove("cursor-visible", "cursor-hover", "cursor-click");
}

function pressCustomCursor() {
  if (isCustomCursorActive()) {
    document.body.classList.add("cursor-click");
  }
}

function releaseCustomCursor() {
  document.body.classList.remove("cursor-click");
}

function updateCustomCursorHover(event) {
  if (!isCustomCursorActive()) {
    return;
  }

  if (event.target instanceof Element && event.target.closest(CUSTOM_CURSOR_INTERACTIVE_SELECTOR)) {
    document.body.classList.add("cursor-hover");
  }
}

function clearCustomCursorHover(event) {
  if (!isCustomCursorActive()) {
    return;
  }

  if (event.target instanceof Element && event.target.closest(CUSTOM_CURSOR_INTERACTIVE_SELECTOR)) {
    document.body.classList.remove("cursor-hover");
  }
}

function isCustomCursorActive() {
  return document.documentElement.classList.contains("custom-cursor-enabled");
}

function isCustomCursorSupported() {
  if (!window.matchMedia) {
    return true;
  }

  return window.matchMedia("(pointer: fine)").matches && !window.matchMedia("(hover: none)").matches;
}

function setupSidebar() {
  // Controla abertura no mobile, fechamento por fundo escuro e Escape.
  const openButton = document.getElementById("openSidebar");
  const closeButton = document.getElementById("closeSidebar");
  const backdrop = document.getElementById("sidebarBackdrop");

  openButton?.addEventListener("click", openSidebar);
  closeButton?.addEventListener("click", closeSidebar);
  backdrop?.addEventListener("click", closeSidebar);

  window.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;

    if (typeof window.closeEditModal === "function") {
      window.closeEditModal();
    }

    closeSidebar();
  });

  document.querySelectorAll(".sidebar-nav a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 920) {
        closeSidebar();
      }
    });
  });

  setupSidebarResize();
}

function openSidebar() {
  // A classe no body permite que CSS mova a sidebar e mostre o backdrop.
  document.body.classList.add("sidebar-open");
}

function closeSidebar() {
  // Fecha a sidebar removendo o estado global.
  document.body.classList.remove("sidebar-open");
}

function setupSidebarResize() {
  const sidebar = document.getElementById("sidebar");

  if (!sidebar || sidebar.dataset.resizeReady === "true") {
    return;
  }

  sidebar.dataset.resizeReady = "true";

  const handle = document.createElement("div");
  handle.className = "sidebar-resize-handle";
  handle.setAttribute("role", "separator");
  handle.setAttribute("aria-orientation", "vertical");
  handle.setAttribute("aria-label", "Redimensionar menu lateral");
  handle.setAttribute("aria-valuemin", String(SIDEBAR_MIN_WIDTH));
  handle.setAttribute("aria-valuemax", String(SIDEBAR_MAX_WIDTH));
  handle.tabIndex = 0;
  sidebar.appendChild(handle);

  updateSidebarResizeHandle(handle, getCurrentSidebarWidth());

  let startX = 0;
  let startWidth = SIDEBAR_DEFAULT_WIDTH;
  let activePointerId = null;

  const finishResize = () => {
    if (activePointerId === null) {
      return;
    }

    activePointerId = null;
    document.body.classList.remove("sidebar-resizing");
    setSavedItem(SIDEBAR_WIDTH_STORAGE_KEY, String(getCurrentSidebarWidth()));
  };

  handle.addEventListener("pointerdown", (event) => {
    if (!isSidebarResizableViewport()) {
      return;
    }

    activePointerId = event.pointerId;
    startX = event.clientX;
    startWidth = getCurrentSidebarWidth();
    document.body.classList.add("sidebar-resizing");
    handle.setPointerCapture?.(event.pointerId);
    event.preventDefault();
  });

  handle.addEventListener("pointermove", (event) => {
    if (activePointerId !== event.pointerId) {
      return;
    }

    const nextWidth = startWidth + (event.clientX - startX);
    applySidebarWidth(nextWidth);
    updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
  });

  handle.addEventListener("pointerup", finishResize);
  handle.addEventListener("pointercancel", finishResize);

  handle.addEventListener("dblclick", () => {
    applySidebarWidth(SIDEBAR_DEFAULT_WIDTH);
    updateSidebarResizeHandle(handle, SIDEBAR_DEFAULT_WIDTH);
    setSavedItem(SIDEBAR_WIDTH_STORAGE_KEY, String(SIDEBAR_DEFAULT_WIDTH));
  });

  handle.addEventListener("keydown", (event) => {
    if (!isSidebarResizableViewport()) {
      return;
    }

    const step = event.shiftKey ? 24 : 12;
    const currentWidth = getCurrentSidebarWidth();
    let nextWidth = currentWidth;

    if (event.key === "ArrowLeft") {
      nextWidth = currentWidth - step;
    } else if (event.key === "ArrowRight") {
      nextWidth = currentWidth + step;
    } else if (event.key === "Home") {
      nextWidth = SIDEBAR_MIN_WIDTH;
    } else if (event.key === "End") {
      nextWidth = SIDEBAR_MAX_WIDTH;
    } else {
      return;
    }

    event.preventDefault();
    applySidebarWidth(nextWidth);
    updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
    setSavedItem(SIDEBAR_WIDTH_STORAGE_KEY, String(getCurrentSidebarWidth()));
  });

  window.addEventListener("resize", () => {
    if (isSidebarResizableViewport()) {
      applySavedSidebarWidth();
      updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
      return;
    }

    document.body.style.removeProperty("--sidebar-width");
  });
}

function applySavedSidebarWidth() {
  const savedWidth = Number(getSavedItem(SIDEBAR_WIDTH_STORAGE_KEY));

  if (!isSidebarResizableViewport()) {
    document.body.style.removeProperty("--sidebar-width");
    return;
  }

  applySidebarWidth(Number.isFinite(savedWidth) ? savedWidth : SIDEBAR_DEFAULT_WIDTH);
}

function applySidebarWidth(width) {
  const nextWidth = clampSidebarWidth(width);

  document.body.style.setProperty("--sidebar-width", `${nextWidth}px`);
}

function getCurrentSidebarWidth() {
  const sidebar = document.getElementById("sidebar");
  const currentWidth = sidebar?.getBoundingClientRect().width || SIDEBAR_DEFAULT_WIDTH;

  return clampSidebarWidth(currentWidth);
}

function clampSidebarWidth(width) {
  const numericWidth = Number(width);

  if (!Number.isFinite(numericWidth)) {
    return SIDEBAR_DEFAULT_WIDTH;
  }

  return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, Math.round(numericWidth)));
}

function updateSidebarResizeHandle(handle, width) {
  handle.setAttribute("aria-valuenow", String(clampSidebarWidth(width)));
}

function isSidebarResizableViewport() {
  return window.matchMedia?.(SIDEBAR_DESKTOP_QUERY)?.matches ?? window.innerWidth >= 921;
}

function setupNavGroups() {
  // Menus agrupados da sidebar abrem um por vez para evitar lista muito longa.
  const groups = Array.from(document.querySelectorAll("[data-nav-group]"));

  groups.forEach((group) => {
    const button = group.querySelector(".nav-toggle");

    if (!button) return;

    button.addEventListener("click", () => {
      const shouldOpen = !group.classList.contains("open");

      groups.forEach((otherGroup) => {
        if (otherGroup === group) return;

        otherGroup.classList.remove("open");
        otherGroup.querySelector(".nav-toggle")?.setAttribute("aria-expanded", "false");
      });

      group.classList.toggle("open", shouldOpen);
      button.setAttribute("aria-expanded", String(shouldOpen));
    });
  });
}

function setupPermissionDeniedTriggers() {
  const restrictedItems = Array.from(document.querySelectorAll(".nav-link-disabled, .disabled-action"));

  restrictedItems.forEach((item) => {
    setupPermissionDeniedTrigger(item);
  });

  if (document.body.dataset.permissionDialogOpen === "true") {
    openPermissionDeniedDialog(document.body);
  }
}

function setupPermissionDeniedTrigger(item) {
  if (item.dataset.permissionTriggerReady === "true") {
    return;
  }

  item.dataset.permissionTriggerReady = "true";
  item.setAttribute("role", "button");
  item.setAttribute("tabindex", "0");

  item.addEventListener("click", (event) => {
    event.preventDefault();
    openPermissionDeniedDialog(item);
  });

  item.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;

    event.preventDefault();
    openPermissionDeniedDialog(item);
  });
}

function openPermissionDeniedDialog(source) {
  document.getElementById("uxToastRegion")?.remove();
  document.getElementById("settingsToast")?.classList.remove("show");

  const event = new CustomEvent("titech:permission-denied", {
    cancelable: true,
    detail: {
      resource: source?.dataset?.permissionResource || document.body.dataset.permissionResource || "esta area",
    },
  });

  window.dispatchEvent(event);

  if (!event.defaultPrevented) {
    showPermissionDeniedDialog(event.detail.resource);
  }
}

function showPermissionDeniedDialog(resource = "esta area") {
  closePermissionDeniedDialog();

  permissionDialogPreviousFocus = document.activeElement;
  permissionDialogElement = document.createElement("div");
  permissionDialogElement.className = "permission-dialog-layer";
  permissionDialogElement.setAttribute("role", "presentation");
  permissionDialogElement.innerHTML = `
    <div class="permission-dialog-backdrop" data-permission-close></div>
    <section class="permission-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="permissionDialogTitle" aria-describedby="permissionDialogDescription">
      <div class="permission-dialog-icon" aria-hidden="true">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <p class="section-tag">Permissao necessaria</p>
      <h2 id="permissionDialogTitle">Acesso restrito</h2>
      <p id="permissionDialogDescription">Voce nao tem permissao para acessar ${escapeHtml(resource)}. Solicite liberacao a um administrador para continuar.</p>
      <button type="button" class="primary-button permission-dialog-close" data-permission-close>
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
        <span>Entendi</span>
      </button>
    </section>
  `;

  permissionDialogElement.addEventListener("click", (event) => {
    if (event.target?.closest?.("[data-permission-close]")) {
      closePermissionDeniedDialog();
    }
  });

  document.addEventListener("keydown", handlePermissionDialogKeydown);
  document.body.append(permissionDialogElement);
  permissionDialogElement.querySelector(".permission-dialog-close")?.focus();
}

function closePermissionDeniedDialog() {
  if (!permissionDialogElement) return;

  permissionDialogElement.remove();
  permissionDialogElement = null;
  document.removeEventListener("keydown", handlePermissionDialogKeydown);
  permissionDialogPreviousFocus?.focus?.();
  permissionDialogPreviousFocus = null;
}

function handlePermissionDialogKeydown(event) {
  if (event.key === "Escape") {
    event.preventDefault();
    closePermissionDeniedDialog();
  }
}

function escapeHtml(value) {
  const text = document.createElement("span");

  text.textContent = String(value || "");

  return text.innerHTML;
}

function setInputValue(id, value) {
  // Helper pequeno para preencher inputs por id sem repetir verificacao de null.
  const element = document.getElementById(id);

  if (element) {
    element.value = value;
  }
}

function setText(element, text) {
  // Atualiza texto quando o elemento existe.
  if (element) {
    element.textContent = text;
  }
}

function updateText(id, text) {
  // Versao por id do setText, usada em paginas que atualizam contadores.
  setText(document.getElementById(id), text);
}

function normalizeText(value) {
  // Remove acentos e padroniza caixa para buscas locais.
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

Object.assign(window, {
  // Expomos os helpers no window porque as paginas antigas ainda chamam essas funcoes.
  getSavedItem,
  setSavedItem,
  updateBrandLogo,
  startPageAnimation,
  loadSavedTheme,
  setupThemeToggle,
  applyTheme,
  resolveThemeMode,
  loadInterfacePreferences,
  applyAccent,
  applyFontSizePreference,
  applyDensity,
  applyMotionPreference,
  applyCursorPreference,
  setupCustomCursor,
  setupSidebar,
  openSidebar,
  closeSidebar,
  applySidebarWidth,
  setupSidebarResize,
  setupNavGroups,
  setupPermissionDeniedTriggers,
  openPermissionDeniedDialog,
  showPermissionDeniedDialog,
  closePermissionDeniedDialog,
  setInputValue,
  setText,
  updateText,
  normalizeText,
});
})();
