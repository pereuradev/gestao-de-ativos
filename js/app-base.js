(function () {
const THEME_TRANSITION_MS = 660;
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

function getSavedItem(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function setSavedItem(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    return;
  }
}

function updateBrandLogo(isDark) {
  document.querySelectorAll(".brand-logo").forEach((logo) => {
    logo.src = isDark ? "assets/logo-branca.png" : "assets/Logo.png";
  });
}

function startPageAnimation() {
  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

function loadSavedTheme() {
  applyTheme(getSavedItem("titech-theme") || "dark");
  loadInterfacePreferences();
  setupSystemThemeListener();
}

function setupThemeToggle() {
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
    icon.className = nextTheme === "auto"
      ? "bi bi-circle-half"
      : isDark ? "bi bi-moon-stars-fill" : "bi bi-sun-fill";
  }

  if (label) {
    label.textContent = nextTheme === "auto" ? "Modo auto" : isDark ? "Modo escuro" : "Modo claro";
  }
}

function resolveThemeMode(theme) {
  if (theme !== "auto") {
    return theme === "light" ? "light" : "dark";
  }

  return window.matchMedia?.("(prefers-color-scheme: light)")?.matches ? "light" : "dark";
}

function setupSystemThemeListener() {
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
  applyAccent(getSavedItem("titech-accent") || "teal");
  applyDensity(getSavedItem("titech-density") || "comfortable");
  applyMotionPreference(getSavedItem("titech-motion") || "normal");
  applyCursorPreference(getSavedItem("titech-cursor") || "normal");
}

function applyAccent(accent) {
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
  document.body.dataset.density = density === "compact" ? "compact" : "comfortable";
}

function applyMotionPreference(motion) {
  document.body.dataset.motion = motion === "reduced" ? "reduced" : "normal";
}

function applyCursorPreference(cursor) {
  document.body.dataset.cursor = cursor === "enhanced" ? "enhanced" : "normal";
}

function setupSidebar() {
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
}

function openSidebar() {
  document.body.classList.add("sidebar-open");
}

function closeSidebar() {
  document.body.classList.remove("sidebar-open");
}

function setupNavGroups() {
  document.querySelectorAll("[data-nav-group]").forEach((group) => {
    const button = group.querySelector(".nav-toggle");

    if (!button) return;

    button.addEventListener("click", () => {
      const isOpen = group.classList.toggle("open");
      button.setAttribute("aria-expanded", String(isOpen));
    });
  });
}

function setInputValue(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.value = value;
  }
}

function setText(element, text) {
  if (element) {
    element.textContent = text;
  }
}

function updateText(id, text) {
  setText(document.getElementById(id), text);
}

function normalizeText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

Object.assign(window, {
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
  applyDensity,
  applyMotionPreference,
  applyCursorPreference,
  setupSidebar,
  openSidebar,
  closeSidebar,
  setupNavGroups,
  setInputValue,
  setText,
  updateText,
  normalizeText,
});
})();
