document.addEventListener("DOMContentLoaded", initSettingsPage);

const SETTINGS_PREFIX = "titech-settings:";
const PREFERENCE_MESSAGE_TIMEOUT_MS = 2400;
const TOAST_TIMEOUT_MS = 3200;

let preferenceMessageTimer = null;
let toastTimer = null;

function initSettingsPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupPreferenceControls();
  setupLocalSettings();
  setupPasswordValidation();
  setupSecurityActions();
  setupDiagnostics();
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
      showToast("Preferencia visual salva neste navegador.");
    });
  });

  document.querySelectorAll('input[name="theme"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      setSavedItem("titech-theme", input.value);
      applyTheme(input.value);
      showPreferenceMessage("Modo de tela atualizado.");
      showToast(input.value === "auto" ? "Tema automatico ativado." : "Tema atualizado.");
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

  document.getElementById("cursorToggle")?.addEventListener("change", (event) => {
    const cursor = event.currentTarget.checked ? "enhanced" : "normal";

    setSavedItem("titech-cursor", cursor);
    applyCursorPreference(cursor);
    showPreferenceMessage("Realce de cursor atualizado.");
  });

  document.getElementById("resetPreferences")?.addEventListener("click", async () => {
    const confirmed = await confirmSettingsAction(
      "Restaurar preferencias?",
      "As escolhas visuais deste navegador voltarao para o padrao TI TECH."
    );

    if (confirmed) {
      resetPreferences();
    }
  });
}

function setupLocalSettings() {
  document.querySelectorAll("[data-setting]").forEach((control) => {
    const key = getSettingKey(control.dataset.setting);
    const savedValue = getSavedItem(key);

    if (control.type === "checkbox") {
      control.checked = savedValue === "true";
      control.addEventListener("change", () => {
        setSavedItem(key, String(control.checked));
        showToast("Preferencia salva localmente.");
        updateSecurityScore();
      });
      return;
    }

    if (savedValue !== null) {
      control.value = savedValue;
    }

    control.addEventListener("change", () => {
      setSavedItem(key, control.value);
      showToast("Configuracao salva neste navegador.");
    });
  });

  setupWorkModes();
}

function setupWorkModes() {
  const savedMode = getSavedItem(getSettingKey("work-mode")) || "support";

  setCheckedValue("workMode", savedMode);

  document.querySelectorAll("[data-work-mode]").forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      setSavedItem(getSettingKey("work-mode"), input.value);
      showToast("Modo de trabalho atualizado.");
    });
  });
}

function setupPasswordValidation() {
  const form = document.getElementById("passwordForm");
  const newPassword = document.getElementById("newPassword");
  const confirmPassword = document.getElementById("confirmPassword");

  newPassword?.addEventListener("input", updatePasswordStrength);
  confirmPassword?.addEventListener("input", updatePasswordStrength);

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const current = document.getElementById("currentPassword")?.value || "";
    const next = newPassword?.value || "";
    const confirmation = confirmPassword?.value || "";
    const result = evaluatePassword(next, confirmation);

    if (!current || !next || !confirmation) {
      showToast("Preencha senha atual, nova senha e confirmacao.");
      return;
    }

    if (result.score < 4 || next !== confirmation) {
      showToast("A nova senha ainda nao atende aos criterios minimos.");
      return;
    }

    const button = document.getElementById("updatePasswordButton");

    setButtonLoading(button, true, "Validando...");
    await wait(650);
    setButtonLoading(button, false);
    showToast("Validacao concluida. Integracao com backend necessaria para alterar a senha real.");
    form.reset();
    updatePasswordStrength();
  });

  updatePasswordStrength();
}

function setupSecurityActions() {
  document.querySelectorAll("[data-feature-button]").forEach((button) => {
    button.addEventListener("click", () => {
      showToast("Interface pronta. Integracao futura com PHP/Supabase necessaria.");
      updateSecurityScore();
    });
  });

  document.getElementById("logoutAllDevices")?.addEventListener("click", async () => {
    const confirmed = await confirmSettingsAction(
      "Solicitar saida global?",
      "Hoje a interface apenas valida a acao. A execucao real depende do backend de sessoes."
    );

    if (confirmed) {
      showToast("Solicitacao registrada visualmente. Backend necessario para encerrar sessoes reais.");
    }
  });

  updateSecurityScore();
}

function setupDiagnostics() {
  updateDiagnostics();
  window.addEventListener("resize", updateDiagnostics);
  window.addEventListener("online", updateDiagnostics);
  window.addEventListener("offline", updateDiagnostics);

  document.getElementById("copyDiagnostics")?.addEventListener("click", async () => {
    updateDiagnostics();

    const info = [
      `Navegador: ${getText("diagBrowser")}`,
      `Sistema operacional: ${getText("diagOs")}`,
      `Largura da tela: ${getText("diagWidth")}`,
      `Status: ${getText("diagOnline")}`,
      `Idioma: ${getText("diagLanguage")}`,
      `Data/hora local: ${getText("diagTime")}`,
      "Versao: TI TECH Assets v1.4.0",
    ].join("\n");

    try {
      await navigator.clipboard.writeText(info);
      showToast("Informacoes copiadas para o suporte.");
    } catch {
      showToast("Nao foi possivel copiar automaticamente. Selecione os dados manualmente.");
    }
  });

  window.setInterval(updateDiagnostics, 30000);
}

function syncPreferenceForm() {
  const accent = getSavedItem("titech-accent") || "teal";
  const theme = getSavedItem("titech-theme") || (document.body.classList.contains("theme-light") ? "light" : "dark");
  const density = getSavedItem("titech-density") || "comfortable";
  const motion = getSavedItem("titech-motion") || "normal";
  const cursor = getSavedItem("titech-cursor") || "normal";

  setCheckedValue("accent", accent);
  setCheckedValue("theme", theme);

  setChecked("densityToggle", density === "compact");
  setChecked("motionToggle", motion === "reduced");
  setChecked("cursorToggle", cursor === "enhanced");
}

function setCheckedValue(name, value) {
  const input = document.querySelector(`input[name="${name}"][value="${cssEscape(value)}"]`);

  if (input) {
    input.checked = true;
  }
}

function setChecked(id, checked) {
  const input = document.getElementById(id);

  if (input) {
    input.checked = checked;
  }
}

function resetPreferences() {
  setSavedItem("titech-accent", "teal");
  setSavedItem("titech-theme", "dark");
  setSavedItem("titech-density", "comfortable");
  setSavedItem("titech-motion", "normal");
  setSavedItem("titech-cursor", "normal");

  applyTheme("dark");
  applyAccent("teal");
  applyDensity("comfortable");
  applyMotionPreference("normal");
  applyCursorPreference("normal");
  syncPreferenceForm();
  showPreferenceMessage("Preferencias restauradas para o padrao do sistema.");
  showToast("Preferencias restauradas.");
}

function updatePasswordStrength() {
  const password = document.getElementById("newPassword")?.value || "";
  const confirmation = document.getElementById("confirmPassword")?.value || "";
  const result = evaluatePassword(password, confirmation);
  const bar = document.getElementById("strengthBar");
  const label = document.getElementById("strengthLabel");
  const percent = Math.round((result.score / 5) * 100);

  if (bar) {
    bar.style.width = `${percent}%`;
    bar.style.background = result.score >= 4 ? "#22c55e" : result.score >= 3 ? "#f59e0b" : "#e05d5d";
  }

  if (label) {
    label.textContent = result.label;
  }

  Object.entries(result.rules).forEach(([rule, isValid]) => {
    document.querySelector(`[data-rule="${rule}"]`)?.classList.toggle("valid", isValid);
  });

  updateSecurityScore(result);
}

function evaluatePassword(password, confirmation) {
  const rules = {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    number: /\d/.test(password),
    special: /[^A-Za-z0-9]/.test(password),
    match: password !== "" && password === confirmation,
  };
  const score = Object.values(rules).filter(Boolean).length;
  const labels = ["Digite uma nova senha", "Muito fraca", "Fraca", "Media", "Forte", "Muito forte"];

  return {
    rules,
    score,
    label: labels[score] || labels[0],
  };
}

function updateSecurityScore(passwordResult = null) {
  const scoreElement = document.getElementById("securityScoreValue");

  if (!scoreElement) return;

  const passwordScore = passwordResult ? passwordResult.score * 10 : 0;
  const suspiciousLogin = getSavedItem(getSettingKey("notify-suspicious-login")) === "true" ? 15 : 0;
  const reviewedSessions = 12;
  const preferencesComplete = getSavedItem("titech-theme") && getSavedItem("titech-accent") ? 13 : 8;
  const score = Math.min(100, 42 + passwordScore + suspiciousLogin + reviewedSessions + preferencesComplete);

  scoreElement.textContent = String(score);
}

function updateDiagnostics() {
  updateText("diagBrowser", getBrowserName());
  updateText("diagOs", getOperatingSystem());
  updateText("diagWidth", `${window.innerWidth}px`);
  updateText("diagOnline", navigator.onLine ? "Online" : "Offline");
  updateText("diagLanguage", navigator.language || "--");
  updateText("diagTime", new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "medium",
  }).format(new Date()));
}

function getBrowserName() {
  const ua = navigator.userAgent;

  if (ua.includes("Edg/")) return "Microsoft Edge";
  if (ua.includes("Chrome/")) return "Google Chrome";
  if (ua.includes("Firefox/")) return "Mozilla Firefox";
  if (ua.includes("Safari/")) return "Safari";

  return "Navegador desconhecido";
}

function getOperatingSystem() {
  const platform = navigator.platform || "";
  const ua = navigator.userAgent;

  if (/Win/i.test(platform) || /Windows/i.test(ua)) return "Windows";
  if (/Mac/i.test(platform)) return "macOS";
  if (/Linux/i.test(platform)) return "Linux";
  if (/Android/i.test(ua)) return "Android";
  if (/iPhone|iPad/i.test(ua)) return "iOS";

  return "Sistema desconhecido";
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

function showToast(message) {
  const toast = document.getElementById("settingsToast");

  if (!toast) return;

  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.classList.add("show");

  toastTimer = setTimeout(() => {
    toast.classList.remove("show");
  }, TOAST_TIMEOUT_MS);
}

function confirmSettingsAction(title, text) {
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "settings-confirm-overlay";
    overlay.innerHTML = `
      <section class="settings-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="settingsConfirmTitle">
        <div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h2 id="settingsConfirmTitle">${escapeHtml(title)}</h2>
        <p>${escapeHtml(text)}</p>
        <div class="confirm-actions">
          <button class="secondary-button" type="button" data-confirm-cancel>Cancelar</button>
          <button class="primary-button" type="button" data-confirm-ok>Confirmar</button>
        </div>
      </section>
    `;

    document.body.appendChild(overlay);

    const close = (answer) => {
      overlay.remove();
      resolve(answer);
    };

    overlay.querySelector("[data-confirm-cancel]")?.addEventListener("click", () => close(false));
    overlay.querySelector("[data-confirm-ok]")?.addEventListener("click", () => close(true));
    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        close(false);
      }
    });
    overlay.querySelector("[data-confirm-cancel]")?.focus();
  });
}

function setButtonLoading(button, isLoading, loadingText = "Aguarde...") {
  if (!button) return;

  if (isLoading) {
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<i class="bi bi-arrow-repeat"></i>${loadingText}`;
    return;
  }

  button.disabled = false;

  if (button.dataset.originalHtml) {
    button.innerHTML = button.dataset.originalHtml;
    delete button.dataset.originalHtml;
  }
}

function getSettingKey(key) {
  return `${SETTINGS_PREFIX}${key}`;
}

function getText(id) {
  return document.getElementById(id)?.textContent?.trim() || "--";
}

function wait(ms) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function cssEscape(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(value);
  }

  return String(value).replace(/["\\]/g, "\\$&");
}
