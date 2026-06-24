(function () {
const THEME_TRANSITION_MS = 660;

let themeTimer = null;

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
  const isDark = theme !== "light";

  document.body.classList.toggle("theme-dark", isDark);
  document.body.classList.toggle("theme-light", !isDark);
  updateBrandLogo(isDark);

  if (!themeToggle) return;

  const icon = themeToggle.querySelector("i");
  const label = themeToggle.querySelector("span");

  if (icon) {
    icon.className = isDark ? "bi bi-moon-stars-fill" : "bi bi-sun-fill";
  }

  if (label) {
    label.textContent = isDark ? "Modo escuro" : "Modo claro";
  }
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
