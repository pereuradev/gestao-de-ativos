const state = {
  role: "Colaborador",
};

const STORAGE_KEYS = {
  theme: "titech-theme",
  email: "titech-email",
  profile: "titech-profile",
};

const CONFIG = {
  loginUrl: "Backend/login-usuario.php",
  redirectUrl: "pagina-inicial.php",
  pageTransitionDelay: 520,
  themeTransitionDelay: 560,
  toastDuration: 2200,
  toastRemoveDelay: 250,
  redirectDelay: 450,
};

let themeTimer = null;
let toastTimer = null;
let toastRemoveTimer = null;

document.addEventListener("DOMContentLoaded", init);

function getEl(id) {
  return document.getElementById(id);
}

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

function removeSavedItem(key) {
  try {
    localStorage.removeItem(key);
  } catch {
    return;
  }
}

function removeLegacyPassword() {
  removeSavedItem("titech-password");
}

function createElement(tag, className, text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}

function createPageTransitionLayer() {
  if (document.querySelector(".page-transition-layer")) return;

  const layer = createElement("div", "page-transition-layer");
  document.body.appendChild(layer);
}

function startPageEntrance() {
  createPageTransitionLayer();

  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

function navigateWithTransition(url) {
  if (!url) return;

  document.body.classList.add("page-leaving");

  setTimeout(() => {
    window.location.href = new URL(url, window.location.href).href;
  }, CONFIG.pageTransitionDelay);
}

function updateThemeButton(button, isDark) {
  const label = button.querySelector(".label");
  const description = button.querySelector("small");
  const icon = button.querySelector("i");

  if (label) {
    label.textContent = isDark ? "Modo claro" : "Modo escuro";
  }

  if (description) {
    description.textContent = "Trocar tema";
  }

  if (icon) {
    icon.className = isDark ? "bi bi-sun" : "bi bi-moon-stars";
  }

  button.setAttribute(
    "aria-label",
    isDark ? "Alternar para modo claro" : "Alternar para modo escuro",
  );
}

function setTheme(theme) {
  const selectedTheme = theme === "light" ? "light" : "dark";
  const isDark = selectedTheme === "dark";

  document.body.classList.toggle("theme-dark", isDark);
  setSavedItem(STORAGE_KEYS.theme, selectedTheme);

  document.querySelectorAll(".theme-toggle").forEach((button) => {
    updateThemeButton(button, isDark);
  });
}

function toggleTheme() {
  const isDark = document.body.classList.contains("theme-dark");
  const nextTheme = isDark ? "light" : "dark";

  clearTimeout(themeTimer);
  document.body.classList.add("theme-switching");

  requestAnimationFrame(() => {
    setTheme(nextTheme);

    themeTimer = setTimeout(() => {
      document.body.classList.remove("theme-switching");
    }, CONFIG.themeTransitionDelay);
  });
}

function buildToast(message, type) {
  const toast = createElement("div", "toastx");
  const icon = createElement(
    "i",
    type === "error" ? "bi bi-x-circle" : "bi bi-check-circle",
  );
  const text = createElement("span", "", message);

  toast.append(icon, text);

  return toast;
}

function showToast(message, type = "success") {
  const toastStack = getEl("toastStack");

  if (!toastStack || !message) return;

  clearTimeout(toastTimer);
  clearTimeout(toastRemoveTimer);

  let toast = toastStack.querySelector(".toastx");

  if (!toast) {
    toast = buildToast(message, type);
    toastStack.appendChild(toast);
  } else {
    const icon = toast.querySelector("i");
    const text = toast.querySelector("span");

    if (icon) {
      icon.className =
        type === "error" ? "bi bi-x-circle" : "bi bi-check-circle";
    }

    if (text) {
      text.textContent = message;
    }
  }

  toast.classList.remove("hide");
  toast.classList.add("show");

  toastTimer = setTimeout(() => {
    toast.classList.remove("show");
    toast.classList.add("hide");

    toastRemoveTimer = setTimeout(() => {
      toast.remove();
    }, CONFIG.toastRemoveDelay);
  }, CONFIG.toastDuration);
}

function validateLogin(email, password) {
  if (!email || !password) {
    return "Preencha e-mail e senha para continuar.";
  }

  if (!email.includes("@")) {
    return "Digite um e-mail v\u00e1lido.";
  }

  if (!isCorporateEmail(email)) {
    return "Use um e-mail corporativo autorizado.";
  }

  if (password.length < 4) {
    return "A senha precisa ter pelo menos 4 caracteres.";
  }

  return "";
}

function isCorporateEmail(email) {
  return email.toLowerCase().endsWith("@titechsolutions.com.br");
}

function setLoginButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;

  if (!isLoading) {
    const icon = createElement("i", "bi bi-lock");
    const text = createElement("span", "", "Entrar");

    button.replaceChildren(icon, text);
    return;
  }

  const spinner = createElement("span", "spinner-border spinner-border-sm");
  const text = createElement("span", "", "Validando acesso...");

  button.replaceChildren(spinner, text);
}

function saveProfilePreference(email, role, shouldSave) {
  removeLegacyPassword();

  if (shouldSave) {
    setSavedItem(STORAGE_KEYS.email, email);
    setSavedItem(STORAGE_KEYS.profile, role);
    return;
  }

  removeSavedItem(STORAGE_KEYS.email);
  removeSavedItem(STORAGE_KEYS.profile);
}

async function handleLogin(event) {
  event.preventDefault();

  const emailInput = getEl("email");
  const passwordInput = getEl("password");
  const rememberProfile = getEl("rememberProfile");
  const loginError = getEl("loginError");
  const loginButton = getEl("loginButton");

  if (
    !emailInput ||
    !passwordInput ||
    !rememberProfile ||
    !loginError ||
    !loginButton
  ) {
    return;
  }

  const email = emailInput.value.trim();
  const password = passwordInput.value;
  const error = validateLogin(email, password);

  if (error) {
    loginError.textContent = error;
    showToast(error, "error");
    return;
  }

  loginError.textContent = "";
  setLoginButtonLoading(loginButton, true);

  try {
    const formData = new FormData();

    formData.append("email", email);
    formData.append("senha", password);
    formData.append("tipo_usuario", state.role);

    const loginUrl = event.target.getAttribute("action") || CONFIG.loginUrl;
    const response = await fetch(new URL(loginUrl, window.location.href), {
      method: "POST",
      body: formData,
      headers: { Accept: "application/json" },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.ok) {
      throw new Error(data.message || "Nao foi possivel validar o acesso.");
    }

    saveProfilePreference(email, state.role, rememberProfile.checked);
    showToast(data.message || `Login validado como ${state.role}.`);

    setTimeout(() => {
      navigateWithTransition(data.redirect || CONFIG.redirectUrl);
    }, CONFIG.redirectDelay);
  } catch (error) {
    const message = error.message || "Nao foi possivel validar o acesso.";

    loginError.textContent = message;
    showToast(message, "error");
    setLoginButtonLoading(loginButton, false);
  }
}

function initTheme() {
  const savedTheme = getSavedItem(STORAGE_KEYS.theme) || "dark";

  setTheme(savedTheme);

  document.querySelectorAll(".theme-toggle").forEach((button) => {
    button.addEventListener("click", toggleTheme);
  });
}

function updateRememberProfileStatus() {
  const rememberProfile = getEl("rememberProfile");
  const status = getEl("rememberProfileStatus");

  if (!rememberProfile || !status) return;

  status.textContent = rememberProfile.checked
    ? `E-mail e perfil ${state.role} ser\u00e3o lembrados`
    : `Perfil selecionado: ${state.role}`;
}

function initSavedProfile() {
  const rememberProfile = getEl("rememberProfile");
  const emailInput = getEl("email");
  const savedEmail = getSavedItem(STORAGE_KEYS.email);
  const savedProfile = getSavedItem(STORAGE_KEYS.profile);

  removeLegacyPassword();

  if (!rememberProfile) return;

  if (savedEmail && emailInput) {
    emailInput.value = savedEmail;
  }

  rememberProfile.checked = Boolean(savedEmail || savedProfile);
  updateRememberProfileStatus();

  rememberProfile.addEventListener("change", () => {
    saveProfilePreference(
      emailInput?.value.trim() || "",
      state.role,
      rememberProfile.checked,
    );
    updateRememberProfileStatus();

    showToast(
      rememberProfile.checked
        ? "E-mail e perfil ser\u00e3o lembrados."
        : "Dados lembrados removidos.",
    );
  });

  emailInput?.addEventListener("input", () => {
    if (!rememberProfile.checked) return;

    saveProfilePreference(emailInput.value.trim(), state.role, true);
  });
}

function initSessionMessage() {
  const params = new URLSearchParams(window.location.search);
  const sessionStatus = params.get("sessao");

  if (sessionStatus === "expirada") {
    showToast("Sessao expirada. Faca login novamente.", "error");
  }

  if (sessionStatus === "encerrada") {
    showToast("Sessao encerrada com sucesso.");
  }
}

function updateSecurityMeter(role) {
  const meter = getEl("securityMeter");

  if (!meter) return;

  meter.style.width = role === "Administrador" ? "84%" : "72%";
}

function setActiveRole(buttons, selectedButton, segmentControl) {
  const selectedRole = selectedButton.dataset.role;

  if (!selectedRole) return;

  buttons.forEach((button) => {
    button.classList.toggle("active", button === selectedButton);
  });

  state.role = selectedRole;
  segmentControl.dataset.active = selectedRole;
  updateSecurityMeter(selectedRole);

  const rememberProfile = getEl("rememberProfile");
  const emailInput = getEl("email");

  if (rememberProfile?.checked) {
    saveProfilePreference(emailInput?.value.trim() || "", selectedRole, true);
  }

  updateRememberProfileStatus();
  showToast(
    rememberProfile?.checked
      ? `Perfil ${selectedRole} atualizado e salvo.`
      : `Perfil selecionado: ${selectedRole}`,
  );
}

function initRoleSelector() {
  const segmentControl = document.querySelector(".segment-control");
  const buttons = [...document.querySelectorAll(".segment-control button")];

  if (!segmentControl || !buttons.length) return;

  const savedProfile = getSavedItem(STORAGE_KEYS.profile);
  const activeButton = buttons.find((button) =>
    button.classList.contains("active"),
  );
  const savedButton = buttons.find(
    (button) => button.dataset.role === savedProfile,
  );
  const selectedButton = savedButton || activeButton || buttons[0];

  if (selectedButton?.dataset.role) {
    state.role = selectedButton.dataset.role;
  }

  buttons.forEach((button) => {
    button.classList.toggle("active", button === selectedButton);
  });

  segmentControl.dataset.active = state.role;
  updateSecurityMeter(state.role);

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const selectedRole = button.dataset.role;
      const isAlreadyActive =
        state.role === selectedRole && button.classList.contains("active");

      if (isAlreadyActive) return;

      setActiveRole(buttons, button, segmentControl);
    });
  });
}

function initPasswordToggle() {
  const passwordToggle = getEl("passwordToggle");
  const passwordInput = getEl("password");

  if (!passwordToggle || !passwordInput) return;

  passwordToggle.addEventListener("click", () => {
    const icon = passwordToggle.querySelector("i");
    const isHidden = passwordInput.type === "password";

    passwordInput.type = isHidden ? "text" : "password";

    if (icon) {
      icon.className = isHidden ? "bi bi-eye-slash" : "bi bi-eye";
    }

    passwordToggle.setAttribute(
      "aria-label",
      isHidden ? "Ocultar senha" : "Mostrar senha",
    );
  });
}

function initRequestAccess() {
  const requestAccess = getEl("requestAccess");

  if (!requestAccess) return;

  requestAccess.addEventListener("click", (event) => {
    event.preventDefault();
    showToast("Fluxo de recupera\u00e7\u00e3o de senha ainda n\u00e3o configurado.");
  });
}

function initSupportLinks() {
  const forgotPasswordLink = getEl("forgotPasswordLink");

  if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener("click", (event) => {
      event.preventDefault();
      showToast("Fluxo de recupera\u00e7\u00e3o de senha ainda n\u00e3o configurado.");
    });
  }
}

function initBenefitCards() {
  const cards = [...document.querySelectorAll(".benefit-card")];

  if (!cards.length) return;

  cards.forEach((card) => {
    card.addEventListener("click", () => {
      cards.forEach((item) => {
        item.classList.toggle("active", item === card);
      });

      if (card.dataset.benefit) {
        showToast(card.dataset.benefit);
      }
    });
  });
}

function initLoginForm() {
  const loginForm = getEl("loginForm");

  if (!loginForm) return;

  loginForm.addEventListener("submit", handleLogin);
}

function initCustomCursor() {
  const cursor = document.querySelector(".custom-cursor");
  const isTouchDevice = window.matchMedia("(pointer: coarse)").matches;

  if (!cursor || isTouchDevice) return;

  document.documentElement.classList.add("custom-cursor-enabled");
  document.body.classList.add("custom-cursor-enabled");

  window.addEventListener("mousemove", (event) => {
    document.body.classList.add("cursor-visible");

    cursor.style.left = `${event.clientX}px`;
    cursor.style.top = `${event.clientY}px`;
  });

  window.addEventListener("mouseleave", () => {
    document.body.classList.remove("cursor-visible");
  });

  window.addEventListener("mousedown", () => {
    document.body.classList.add("cursor-click");
  });

  window.addEventListener("mouseup", () => {
    document.body.classList.remove("cursor-click");
  });

  document.addEventListener("mouseover", (event) => {
    if (
      event.target.closest(
        "a, button, input, label, .form-control, [role='button']",
      )
    ) {
      document.body.classList.add("cursor-hover");
    }
  });

  document.addEventListener("mouseout", (event) => {
    if (
      event.target.closest(
        "a, button, input, label, .form-control, [role='button']",
      )
    ) {
      document.body.classList.remove("cursor-hover");
    }
  });
}

function init() {
  initTheme();
  initRoleSelector();
  initSavedProfile();
  initPasswordToggle();
  initSupportLinks();
  initRequestAccess();
  initBenefitCards();
  initLoginForm();
  initCustomCursor();
  startPageEntrance();
  initSessionMessage();
}
