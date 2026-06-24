document.addEventListener("DOMContentLoaded", initSettingsPage);

const PREFERENCE_MESSAGE_TIMEOUT_MS = 2400;

let preferenceMessageTimer = null;

function initSettingsPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupPreferenceControls();
}

function setupPreferenceControls() {
  syncPreferenceForm();

  document.getElementById("themeToggle")?.addEventListener("click", () => {
    window.setTimeout(syncPreferenceForm, 0);
  });

  document.querySelectorAll('input[name="accent"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      setSavedItem("titech-accent", input.value);
      applyAccent(input.value);
      showPreferenceMessage("Preferencia de cor aplicada.");
    });
  });

  document.querySelectorAll('input[name="theme"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      setSavedItem("titech-theme", input.value);
      applyTheme(input.value);
      showPreferenceMessage("Modo de tela atualizado.");
    });
  });

  document.getElementById("densityToggle")?.addEventListener("change", (event) => {
    const density = event.currentTarget.checked ? "compact" : "comfortable";

    setSavedItem("titech-density", density);
    applyDensity(density);
    showPreferenceMessage("Ajuste de densidade salvo.");
  });

  document.getElementById("motionToggle")?.addEventListener("change", (event) => {
    const motion = event.currentTarget.checked ? "reduced" : "normal";

    setSavedItem("titech-motion", motion);
    applyMotionPreference(motion);
    showPreferenceMessage("Preferencia de animacao salva.");
  });

  document.getElementById("resetPreferences")?.addEventListener("click", resetPreferences);
}

function syncPreferenceForm() {
  const accent = getSavedItem("titech-accent") || "teal";
  const theme = getSavedItem("titech-theme") || (document.body.classList.contains("theme-light") ? "light" : "dark");
  const density = getSavedItem("titech-density") || "comfortable";
  const motion = getSavedItem("titech-motion") || "normal";

  setCheckedValue("accent", accent);
  setCheckedValue("theme", theme);

  const densityToggle = document.getElementById("densityToggle");
  const motionToggle = document.getElementById("motionToggle");

  if (densityToggle) {
    densityToggle.checked = density === "compact";
  }

  if (motionToggle) {
    motionToggle.checked = motion === "reduced";
  }
}

function setCheckedValue(name, value) {
  const input = document.querySelector(`input[name="${name}"][value="${cssEscape(value)}"]`);

  if (input) {
    input.checked = true;
  }
}

function resetPreferences() {
  setSavedItem("titech-accent", "teal");
  setSavedItem("titech-theme", "dark");
  setSavedItem("titech-density", "comfortable");
  setSavedItem("titech-motion", "normal");

  applyTheme("dark");
  applyAccent("teal");
  applyDensity("comfortable");
  applyMotionPreference("normal");
  syncPreferenceForm();
  showPreferenceMessage("Preferencias restauradas para o padrao do sistema.");
}

function showPreferenceMessage(message) {
  const element = document.getElementById("preferencesMessage");

  if (!element) return;

  clearTimeout(preferenceMessageTimer);
  element.textContent = message;
  element.classList.add("show", "success");

  preferenceMessageTimer = setTimeout(() => {
    element.textContent = "";
    element.classList.remove("show", "success");
  }, PREFERENCE_MESSAGE_TIMEOUT_MS);
}

function cssEscape(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(value);
  }

  return String(value).replace(/["\\]/g, "\\$&");
}
