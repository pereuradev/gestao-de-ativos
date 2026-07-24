// Gerencia preferencias do usuario, seguranca visual e diagnosticos da pagina de configuracoes.
// O navegador fica como cache; a fonte principal das preferencias e o perfil salvo no servidor.

document.addEventListener("DOMContentLoaded", initSettingsPage);

const SETTINGS_PREFIX = "titech-settings:";
const PREFERENCE_MESSAGE_TIMEOUT_MS = 2400;
const TOAST_TIMEOUT_MS = 3200;
const BADGE_PHOTO_MAX_BYTES = 2 * 1024 * 1024;
const BADGE_PHOTO_ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"];
const ACCENT_PRESET_COLORS = {
  teal: "#66d5c2",
  green: "#22c55e",
  blue: "#38bdf8",
  violet: "#a78bfa",
};
const HEX_COLOR_PATTERN = /^#[0-9a-f]{6}$/i;
const DEFAULT_INTERFACE_PREFERENCES = {
  theme: "dark",
  accent: "teal",
  fontSize: "default",
  density: "comfortable",
  motion: "normal",
  cursor: "enhanced",
};

let preferenceMessageTimer = null;
let toastTimer = null;

function initSettingsPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupBadgePhotoUpload();
  setupPreferenceControls();
  setupLocalSettings();
  setupPasswordValidation();
  setupSecurityActions();
  setupDiagnostics();
}

// O JavaScript melhora a experiencia com preview e mensagens, mas a validacao real fica no PHP.
function setupBadgePhotoUpload() {
  const form = document.getElementById("badgePhotoForm");
  const input = document.getElementById("badgePhotoInput");
  const button = document.getElementById("saveBadgePhotoButton");

  if (!form || !input) {
    return;
  }

  input.addEventListener("change", () => {
    const file = input.files?.[0] || null;
    const error = validateBadgePhotoFile(file);

    setBadgePhotoMessage(error, error ? "error" : "");

    if (button) {
      button.disabled = Boolean(error) || !file;
    }

    if (!error && file) {
      previewBadgePhoto(URL.createObjectURL(file), true);
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const file = input.files?.[0] || null;
    const error = validateBadgePhotoFile(file);

    if (error) {
      setBadgePhotoMessage(error, "error");
      return;
    }

    setButtonLoading(button, true, "Salvando...");

    try {
      const response = await fetch(form.action, {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(form),
      });
      const result = await response.json().catch(() => null);

      if (!response.ok || !result?.ok) {
        throw new Error(result?.message || "Nao foi possivel salvar a foto.");
      }

      const photoUrl = addCacheBuster(result.foto_cracha_url || "");

      if (photoUrl) {
        previewBadgePhoto(photoUrl, false);
        updateSidebarBadgePhoto(photoUrl);
      }

      input.value = "";
      setBadgePhotoMessage(result.message || "Foto do cracha atualizada.", "success");
      showToast("Foto do cracha salva no perfil.");
    } catch (error) {
      setBadgePhotoMessage(error.message || "Nao foi possivel salvar a foto.", "error");
    } finally {
      setButtonLoading(button, false);

      if (button) {
        button.disabled = true;
      }
    }
  });
}

function validateBadgePhotoFile(file) {
  if (!file) {
    return "Selecione uma imagem para o cracha.";
  }

  if (!BADGE_PHOTO_ALLOWED_TYPES.includes(file.type)) {
    return "Use uma imagem JPG, PNG ou WebP.";
  }

  if (file.size > BADGE_PHOTO_MAX_BYTES) {
    return "Envie uma imagem de ate 2 MB.";
  }

  return "";
}

function previewBadgePhoto(url, revokeAfterLoad) {
  const avatar = document.querySelector("[data-badge-photo-preview]");

  if (!avatar || !url) {
    return;
  }

  avatar.classList.add("has-photo");
  avatar.removeAttribute("aria-hidden");
  avatar.textContent = "";

  const image = document.createElement("img");
  image.src = url;
  image.alt = "Foto do cracha";

  if (revokeAfterLoad) {
    image.addEventListener("load", () => URL.revokeObjectURL(url), { once: true });
  }

  avatar.appendChild(image);
}

function updateSidebarBadgePhoto(url) {
  document.querySelectorAll(".sidebar-avatar").forEach((avatar) => {
    avatar.classList.add("has-photo");
    avatar.textContent = "";

    const image = document.createElement("img");
    image.src = url;
    image.alt = "";
    avatar.appendChild(image);
  });
}

function setBadgePhotoMessage(message, type) {
  const element = document.getElementById("badgePhotoMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("success", type === "success");
  element.classList.toggle("error", type === "error");
}

function addCacheBuster(url) {
  if (!url) {
    return "";
  }

  return `${url}${url.includes("?") ? "&" : "?"}v=${Date.now()}`;
}

// Preferencias visuais sao aplicadas imediatamente e persistidas no perfil do usuario.
function setupPreferenceControls() {
  syncPreferenceForm();

  document.getElementById("themeToggle")?.addEventListener("click", () => {
    window.setTimeout(syncPreferenceForm, 0);
  });

  window.addEventListener("titech:theme-change", syncPreferenceForm);
  setupAccentWheelControl();

  document.querySelectorAll('input[name="theme"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      void savePreferenceChange(
        { theme: input.value },
        "Modo de tela atualizado.",
        input.value === "auto" ? "Tema automatico salvo para seu usuario." : "Tema salvo para seu usuario.",
      );
    });
  });

  document.querySelectorAll('input[name="fontSize"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (!input.checked) return;

      void savePreferenceChange(
        { fontSize: input.value },
        "Tamanho da fonte atualizado.",
        "Preferencia de leitura salva para seu usuario.",
      );
    });
  });

  document.getElementById("densityToggle")?.addEventListener("change", (event) => {
    const density = event.currentTarget.checked ? "compact" : "comfortable";

    void savePreferenceChange(
      { density },
      "Ajuste de densidade salvo.",
      "Densidade salva para seu usuario.",
    );
  });

  document.getElementById("motionToggle")?.addEventListener("change", (event) => {
    const motion = event.currentTarget.checked ? "reduced" : "normal";

    void savePreferenceChange(
      { motion },
      "Preferencia de animacao salva.",
      "Preferencia de animacao salva para seu usuario.",
    );
  });

  document.getElementById("cursorToggle")?.addEventListener("change", (event) => {
    const cursor = event.currentTarget.checked ? "enhanced" : "normal";

    void savePreferenceChange(
      { cursor },
      "Realce de cursor atualizado.",
      "Cursor salvo para seu usuario.",
    );
  });

  document.getElementById("resetPreferences")?.addEventListener("click", async () => {
    const confirmed = await confirmSettingsAction(
      "Restaurar preferencias?",
      "As escolhas visuais do seu usuario voltarao para o padrao TI TECH."
    );

    if (confirmed) {
      void resetPreferences();
    }
  });
}

function setupAccentWheelControl() {
  const wheel = document.getElementById("accentColorWheel");
  const colorInput = document.getElementById("accentColorValue");
  const nativeInput = document.getElementById("accentNativeColor");
  const presetButtons = document.querySelectorAll("[data-accent-preset]");

  if (!wheel || !colorInput) {
    return;
  }

  let isDragging = false;

  const previewColor = (value) => {
    const color = normalizeAccentColor(value);

    if (!color) {
      return "";
    }

    const preferences = applyPreferenceState({
      ...getPreferenceState(),
      accent: color,
    });

    syncAccentColorControls(preferences.accent);
    updateSecurityScore();

    return color;
  };

  const saveColor = (value) => {
    const color = normalizeAccentColor(value);

    if (!color) {
      syncAccentColorControls(getPreferenceState().accent);
      return;
    }

    void savePreferenceChange(
      { accent: color },
      "Cor aplicada.",
      "Cor salva para seu usuario.",
    );
  };

  const updateFromPointer = (event, shouldSave) => {
    const color = colorFromWheelPointer(wheel, event);

    previewColor(color);

    if (shouldSave) {
      saveColor(color);
    }
  };

  wheel.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    isDragging = true;
    wheel.setPointerCapture?.(event.pointerId);
    updateFromPointer(event, false);
  });

  wheel.addEventListener("pointermove", (event) => {
    if (!isDragging) {
      return;
    }

    updateFromPointer(event, false);
  });

  const finishPointerChoice = (event) => {
    if (!isDragging) {
      return;
    }

    isDragging = false;
    wheel.releasePointerCapture?.(event.pointerId);
    updateFromPointer(event, true);
  };

  wheel.addEventListener("pointerup", finishPointerChoice);
  wheel.addEventListener("pointercancel", finishPointerChoice);

  wheel.addEventListener("keydown", (event) => {
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End"].includes(event.key)) {
      return;
    }

    event.preventDefault();

    const hsv = hexToHsv(accentToHex(getPreferenceState().accent));
    let nextHue = hsv.h;
    let nextSaturation = hsv.s;

    if (event.key === "ArrowLeft") nextHue -= 6;
    if (event.key === "ArrowRight") nextHue += 6;
    if (event.key === "ArrowUp") nextSaturation += 0.05;
    if (event.key === "ArrowDown") nextSaturation -= 0.05;
    if (event.key === "Home") nextSaturation = 0;
    if (event.key === "End") nextSaturation = 1;

    saveColor(hsvToHex((nextHue + 360) % 360, clampNumber(nextSaturation, 0, 1), 1));
  });

  colorInput.addEventListener("input", () => {
    const color = normalizeAccentColor(colorInput.value);

    colorInput.classList.toggle("invalid", !color && colorInput.value.trim() !== "");

    if (color) {
      previewColor(color);
    }
  });

  colorInput.addEventListener("change", () => saveColor(colorInput.value));

  nativeInput?.addEventListener("input", () => previewColor(nativeInput.value));
  nativeInput?.addEventListener("change", () => saveColor(nativeInput.value));

  presetButtons.forEach((button) => {
    button.addEventListener("click", () => saveColor(button.dataset.accentPreset || ""));
  });
}

function syncAccentColorControls(accent) {
  const color = accentToHex(accent);
  const colorInput = document.getElementById("accentColorValue");
  const nativeInput = document.getElementById("accentNativeColor");
  const label = document.getElementById("accentCurrentLabel");
  const swatch = document.getElementById("accentCurrentSwatch");
  const thumb = document.getElementById("accentWheelThumb");

  if (colorInput) {
    colorInput.value = color.toUpperCase();
    colorInput.classList.remove("invalid");
  }

  if (nativeInput) {
    nativeInput.value = color;
  }

  if (label) {
    label.textContent = color.toUpperCase();
  }

  if (swatch) {
    swatch.style.backgroundColor = color;
  }

  if (thumb) {
    const hsv = hexToHsv(color);
    const angle = (hsv.h * Math.PI) / 180;
    const distance = hsv.s * 50;

    thumb.style.left = `${50 + Math.cos(angle) * distance}%`;
    thumb.style.top = `${50 + Math.sin(angle) * distance}%`;
    thumb.style.backgroundColor = color;
  }
}

function colorFromWheelPointer(wheel, event) {
  const rect = wheel.getBoundingClientRect();
  const radius = Math.min(rect.width, rect.height) / 2;
  const centerX = rect.left + rect.width / 2;
  const centerY = rect.top + rect.height / 2;
  const deltaX = event.clientX - centerX;
  const deltaY = event.clientY - centerY;
  const distance = Math.min(Math.hypot(deltaX, deltaY), radius);
  const saturation = radius > 0 ? distance / radius : 0;
  const hue = (Math.atan2(deltaY, deltaX) * 180) / Math.PI;

  return hsvToHex((hue + 360) % 360, saturation, 1);
}

function normalizeAccentColor(value) {
  const color = String(value ?? "").trim();

  if (Object.hasOwn(ACCENT_PRESET_COLORS, color)) {
    return ACCENT_PRESET_COLORS[color];
  }

  return HEX_COLOR_PATTERN.test(color) ? color.toLowerCase() : "";
}

function accentToHex(value) {
  return normalizeAccentColor(value) || ACCENT_PRESET_COLORS.teal;
}

function hexToRgb(hex) {
  const normalized = accentToHex(hex).replace("#", "");

  return {
    r: parseInt(normalized.slice(0, 2), 16),
    g: parseInt(normalized.slice(2, 4), 16),
    b: parseInt(normalized.slice(4, 6), 16),
  };
}

function rgbToHex({ r, g, b }) {
  return `#${[r, g, b]
    .map((channel) => Math.round(clampNumber(channel, 0, 255)).toString(16).padStart(2, "0"))
    .join("")}`;
}

function hexToHsv(hex) {
  const { r, g, b } = hexToRgb(hex);
  const red = r / 255;
  const green = g / 255;
  const blue = b / 255;
  const max = Math.max(red, green, blue);
  const min = Math.min(red, green, blue);
  const delta = max - min;
  let hue = 0;

  if (delta !== 0) {
    if (max === red) {
      hue = 60 * (((green - blue) / delta) % 6);
    } else if (max === green) {
      hue = 60 * ((blue - red) / delta + 2);
    } else {
      hue = 60 * ((red - green) / delta + 4);
    }
  }

  return {
    h: (hue + 360) % 360,
    s: max === 0 ? 0 : delta / max,
    v: max,
  };
}

function hsvToHex(hue, saturation, value) {
  const chroma = value * saturation;
  const segment = hue / 60;
  const x = chroma * (1 - Math.abs((segment % 2) - 1));
  const match = value - chroma;
  let red = 0;
  let green = 0;
  let blue = 0;

  if (segment >= 0 && segment < 1) [red, green, blue] = [chroma, x, 0];
  else if (segment >= 1 && segment < 2) [red, green, blue] = [x, chroma, 0];
  else if (segment >= 2 && segment < 3) [red, green, blue] = [0, chroma, x];
  else if (segment >= 3 && segment < 4) [red, green, blue] = [0, x, chroma];
  else if (segment >= 4 && segment < 5) [red, green, blue] = [x, 0, chroma];
  else [red, green, blue] = [chroma, 0, x];

  return rgbToHex({
    r: (red + match) * 255,
    g: (green + match) * 255,
    b: (blue + match) * 255,
  });
}

function clampNumber(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function getPreferenceState() {
  if (typeof window.getCurrentUserPreferences === "function") {
    return window.getCurrentUserPreferences();
  }

  return {
    theme: getSavedItem("titech-theme") || DEFAULT_INTERFACE_PREFERENCES.theme,
    accent: getSavedItem("titech-accent") || DEFAULT_INTERFACE_PREFERENCES.accent,
    fontSize: getSavedItem("titech-font-size") || DEFAULT_INTERFACE_PREFERENCES.fontSize,
    density: getSavedItem("titech-density") || DEFAULT_INTERFACE_PREFERENCES.density,
    motion: getSavedItem("titech-motion") || DEFAULT_INTERFACE_PREFERENCES.motion,
    cursor: getSavedItem("titech-cursor") || DEFAULT_INTERFACE_PREFERENCES.cursor,
  };
}

function normalizePreferenceState(preferences) {
  if (typeof window.normalizeUserPreferences === "function") {
    return window.normalizeUserPreferences(preferences);
  }

  return { ...DEFAULT_INTERFACE_PREFERENCES, ...preferences };
}

function applyPreferenceState(preferences) {
  const normalized = normalizePreferenceState(preferences);

  if (typeof window.applyUserPreferences === "function") {
    return window.applyUserPreferences(normalized);
  }

  setSavedItem("titech-accent", normalized.accent);
  setSavedItem("titech-theme", normalized.theme);
  setSavedItem("titech-font-size", normalized.fontSize);
  setSavedItem("titech-density", normalized.density);
  setSavedItem("titech-motion", normalized.motion);
  setSavedItem("titech-cursor", normalized.cursor);
  applyTheme(normalized.theme);
  applyAccent(normalized.accent);
  applyFontSizePreference(normalized.fontSize);
  applyDensity(normalized.density);
  applyMotionPreference(normalized.motion);
  applyCursorPreference(normalized.cursor);

  return normalized;
}

async function savePreferenceChange(partialPreferences, message, successToast) {
  const nextPreferences = applyPreferenceState({
    ...getPreferenceState(),
    ...partialPreferences,
  });

  syncPreferenceForm();
  showPreferenceMessage(message);

  const result = typeof window.saveUserPreferences === "function"
    ? await window.saveUserPreferences(nextPreferences)
    : { ok: true, preferences: nextPreferences };

  if (result.ok) {
    if (result.preferences) {
      applyPreferenceState(result.preferences);
      syncPreferenceForm();
    }

    showToast(successToast || "Preferencias salvas para seu usuario.");
  } else {
    showToast("Preferencia aplicada nesta sessao, mas nao foi salva no usuario.");
  }

  updateSecurityScore();
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

// A análise de senha nesta página é informativa e não substitui a validação do backend.
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

// Os diagnósticos exibem apenas informações disponíveis no navegador do usuário.
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
  const preferences = getPreferenceState();

  syncAccentColorControls(preferences.accent);
  setCheckedValue("theme", preferences.theme);
  setCheckedValue("fontSize", preferences.fontSize);

  setChecked("densityToggle", preferences.density === "compact");
  setChecked("motionToggle", preferences.motion === "reduced");
  setChecked("cursorToggle", preferences.cursor === "enhanced");
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

async function resetPreferences() {
  await savePreferenceChange(
    DEFAULT_INTERFACE_PREFERENCES,
    "Preferencias restauradas para o padrao do sistema.",
    "Preferencias restauradas para seu usuario.",
  );
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
  const currentPreferences = getPreferenceState();
  const preferencesComplete = currentPreferences.theme && currentPreferences.accent ? 13 : 8;
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

// Usa o diálogo global quando disponível e mantém confirm() como fallback progressivo.
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
