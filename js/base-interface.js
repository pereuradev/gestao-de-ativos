(function () {
// Script base carregado nas paginas internas. Ele concentra preferencias,
// permissoes, tema e pequenos helpers usados por varios modulos.
const THEME_TRANSITION_MS = 660;
const FONT_SIZE_OPTIONS = {
  small: 15,
  default: 16,
  large: 17,
  extra: 18,
};
const USER_PREFERENCE_DEFAULTS = {
  theme: "dark",
  accent: "teal",
  fontSize: "default",
  density: "comfortable",
  motion: "normal",
  cursor: "enhanced",
};
const USER_PREFERENCE_STORAGE_KEYS = {
  theme: "titech-theme",
  accent: "titech-accent",
  fontSize: "titech-font-size",
  density: "titech-density",
  motion: "titech-motion",
  cursor: "titech-cursor",
};
const USER_PREFERENCE_ENDPOINT = "../Backend/preferencias-usuario.php";
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

const getSavedItem = typeof window.getSavedItem === "function" ? window.getSavedItem : () => null;
const setSavedItem =
  typeof window.setSavedItem === "function"
    ? window.setSavedItem
    : () => undefined;
const normalizeChoice =
  typeof window.normalizeChoice === "function"
    ? window.normalizeChoice
    : (value, allowedValues, fallback) => {
        const normalized = String(value ?? "").trim();

        return allowedValues.includes(normalized) ? normalized : fallback;
      };
const startPageAnimation =
  typeof window.startPageAnimation === "function"
    ? window.startPageAnimation
    : () => {
        requestAnimationFrame(() => {
          document.body.classList.remove("page-loading");
        });
      };
const setupSidebar = typeof window.setupSidebar === "function" ? window.setupSidebar : () => undefined;
const openSidebar = typeof window.openSidebar === "function" ? window.openSidebar : () => undefined;
const closeSidebar = typeof window.closeSidebar === "function" ? window.closeSidebar : () => undefined;
const applySidebarWidth =
  typeof window.applySidebarWidth === "function" ? window.applySidebarWidth : () => undefined;
const setupSidebarResize =
  typeof window.setupSidebarResize === "function" ? window.setupSidebarResize : () => undefined;
const setupNavGroups = typeof window.setupNavGroups === "function" ? window.setupNavGroups : () => undefined;

document.addEventListener("DOMContentLoaded", () => {
  applyUserPreferences(getCurrentUserPreferences());
  hydrateSidebarProfile();
  setupPermissionDeniedTriggers();
});

function normalizeUserPreferences(preferences = {}) {
  // Aceita tanto nomes usados no JavaScript quanto nomes vindos das colunas do banco.
  const source = preferences && typeof preferences === "object" ? preferences : {};

  return {
    theme: normalizeChoice(
      source.theme ?? source.preferencia_tema,
      ["dark", "light", "auto"],
      USER_PREFERENCE_DEFAULTS.theme,
    ),
    accent: normalizeChoice(
      source.accent ?? source.preferencia_cor,
      Object.keys(ACCENT_THEMES),
      USER_PREFERENCE_DEFAULTS.accent,
    ),
    fontSize: normalizeChoice(
      source.fontSize ?? source.font_size ?? source.preferencia_tamanho_fonte,
      Object.keys(FONT_SIZE_OPTIONS),
      USER_PREFERENCE_DEFAULTS.fontSize,
    ),
    density: normalizeChoice(
      source.density ?? source.preferencia_densidade,
      ["comfortable", "compact"],
      USER_PREFERENCE_DEFAULTS.density,
    ),
    motion: normalizeChoice(
      source.motion ?? source.preferencia_movimento,
      ["normal", "reduced"],
      USER_PREFERENCE_DEFAULTS.motion,
    ),
    cursor: normalizeChoice(
      source.cursor ?? source.preferencia_cursor,
      ["enhanced", "normal"],
      USER_PREFERENCE_DEFAULTS.cursor,
    ),
  };
}

function getServerUserPreferences() {
  return window.TITECH_USER_PREFERENCES && typeof window.TITECH_USER_PREFERENCES === "object"
    ? window.TITECH_USER_PREFERENCES
    : {};
}

function getStoredUserPreferences() {
  const preferences = {};

  Object.entries(USER_PREFERENCE_STORAGE_KEYS).forEach(([name, key]) => {
    const value = getSavedItem(key);

    if (value !== null) {
      preferences[name] = value;
    }
  });

  return preferences;
}

function getCurrentUserPreferences() {
  // A sessao PHP tem prioridade para evitar que usuarios diferentes herdem o mesmo navegador.
  return normalizeUserPreferences({
    ...getStoredUserPreferences(),
    ...getServerUserPreferences(),
  });
}

function cacheUserPreferences(preferences) {
  const normalized = normalizeUserPreferences(preferences);

  window.TITECH_USER_PREFERENCES = normalized;
  Object.entries(USER_PREFERENCE_STORAGE_KEYS).forEach(([name, key]) => {
    setSavedItem(key, normalized[name]);
  });

  return normalized;
}

function applyUserPreferences(preferences) {
  const normalized = cacheUserPreferences(preferences);

  applyTheme(normalized.theme);
  applyAccent(normalized.accent);
  applyFontSizePreference(normalized.fontSize);
  applyDensity(normalized.density);
  applyMotionPreference(normalized.motion);
  applyCursorPreference(normalized.cursor);
  applySavedSidebarWidth();

  return normalized;
}

async function saveUserPreferences(preferences) {
  const normalized = cacheUserPreferences({
    ...getCurrentUserPreferences(),
    ...preferences,
  });

  try {
    const response = await fetch(USER_PREFERENCE_ENDPOINT, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(normalized),
    });
    const result = await response.json().catch(() => null);

    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Nao foi possivel salvar as preferencias.");
    }

    return {
      ok: true,
      preferences: cacheUserPreferences(result.preferences || normalized),
    };
  } catch (error) {
    return {
      ok: false,
      error,
      preferences: normalized,
    };
  }
}

async function hydrateSidebarProfile() {
  // A sidebar nasce com dados da sessao PHP, mas este refresh corrige sessoes antigas sem novo login.
  if (!document.querySelector(".sidebar-user-info")) {
    return;
  }

  try {
    const response = await fetch("../Backend/usuario-sessao.php", {
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

    if (result.usuario.preferencias) {
      applyUserPreferences(result.usuario.preferencias);
    }
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
    logo.src = isDark ? "../assets/logo-branca.png" : "../assets/Logo.png";
  });
}

function loadSavedTheme() {
  // Restaura tema e preferencias antes de configurar os controles da pagina.
  applyUserPreferences(getCurrentUserPreferences());
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
    void saveUserPreferences({ theme: nextTheme });

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
    if (getCurrentUserPreferences().theme === "auto") {
      applyTheme("auto");
    }
  };

  mediaQuery.addEventListener?.("change", updateAutoTheme);
  systemThemeListenerAttached = true;
}

function loadInterfacePreferences() {
  // Preferencias salvas deixam as paginas com a mesma aparencia escolhida pelo usuario.
  const preferences = getCurrentUserPreferences();

  applyAccent(preferences.accent);
  applyFontSizePreference(preferences.fontSize);
  applyDensity(preferences.density);
  applyMotionPreference(preferences.motion);
  applyCursorPreference(preferences.cursor);
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
  normalizeUserPreferences,
  getCurrentUserPreferences,
  cacheUserPreferences,
  applyUserPreferences,
  saveUserPreferences,
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
