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
  inactiveAccountMessage:
    "Sua conta est\u00e1 inativa. Solicite ajuda a um administrador para reativar o acesso.",
  pageTransitionDelay: 520,
  themeTransitionDelay: 560,
  toastDuration: 2200,
  toastRemoveDelay: 250,
  redirectDelay: 450,
  invalidCredentialsMessage: "Credenciais invalidas. Confira e-mail, senha e perfil selecionado.",
  serverUnavailableMessage: "Servidor indisponivel. Tente novamente em instantes.",
};

const ROLE_CONTENT = {
  Administrador: {
    badge: "Controle total do ambiente",
    title: "Acesso administrativo",
    description:
      "Gerencie usu\u00e1rios, ativos, permiss\u00f5es e configura\u00e7\u00f5es internas do sistema.",
  },
  Colaborador: {
    badge: "Acesso operacional seguro",
    title: "Acesso colaborador",
    description:
      "Consulte informa\u00e7\u00f5es, acompanhe ativos e utilize os recursos liberados para sua fun\u00e7\u00e3o.",
  },
};

let themeTimer = null;
let toastTimer = null;
let toastRemoveTimer = null;
let inactiveDialogLastFocus = null;
let roleSwitchAnimating = false;

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
  const toast = createElement("div", `toastx toastx-${type}`);
  toast.setAttribute("role", type === "error" ? "alert" : "status");
  const icon = createElement(
    "i",
    type === "error" ? "bi bi-x-circle" : "bi bi-check-circle",
  );
  icon.setAttribute("aria-hidden", "true");
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

    toast.className = `toastx toastx-${type}`;
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
  if (!email) {
    return {
      ok: false,
      field: "email",
      message: "Informe seu e-mail corporativo.",
    };
  }

  if (!email.includes("@")) {
    return {
      ok: false,
      field: "email",
      message: "Digite um e-mail valido.",
    };
  }

  if (!isCorporateEmail(email)) {
    return {
      ok: false,
      field: "email",
      message: "Use um e-mail corporativo autorizado.",
    };
  }

  if (!password) {
    return {
      ok: false,
      field: "password",
      message: "Informe sua senha.",
    };
  }

  if (password.length < 4) {
    return {
      ok: false,
      field: "password",
      message: "A senha precisa ter pelo menos 4 caracteres.",
    };
  }

  return { ok: true, field: "", message: "" };
}

function isCorporateEmail(email) {
  return email.toLowerCase().endsWith("@titechsolutions.com.br");
}

function setFieldError(fieldId, message) {
  const input = getEl(fieldId);
  const error = getEl(`${fieldId}Error`);
  const wrap = input?.closest(".input-wrap");

  if (!input || !error || !wrap) return;

  input.setAttribute("aria-invalid", "true");
  wrap.classList.add("field-invalid");
  error.textContent = message;
  error.hidden = false;
}

function clearFieldError(fieldId) {
  const input = getEl(fieldId);
  const error = getEl(`${fieldId}Error`);
  const wrap = input?.closest(".input-wrap");

  if (!input || !error || !wrap) return;

  input.setAttribute("aria-invalid", "false");
  wrap.classList.remove("field-invalid");
  error.textContent = "";
  error.hidden = true;
}

function clearLoginValidation() {
  clearFieldError("email");
  clearFieldError("password");
}

function getLoginFailureMessage(response, data) {
  if (response.status >= 500) {
    return CONFIG.serverUnavailableMessage;
  }

  if (response.status === 401) {
    return CONFIG.invalidCredentialsMessage;
  }

  return data.message || CONFIG.invalidCredentialsMessage;
}

function setLoginButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;
  button.setAttribute("aria-busy", isLoading ? "true" : "false");
  button.dataset.loading = isLoading ? "true" : "false";

  if (!isLoading) {
    const icon = createElement("i", "bi bi-lock");
    icon.setAttribute("aria-hidden", "true");
    const text = createElement("span", "", button.dataset.defaultLabel || "Entrar");

    button.replaceChildren(icon, text);
    return;
  }

  const spinner = createElement("i", "bi bi-arrow-repeat button-spinner");
  spinner.setAttribute("aria-hidden", "true");
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

function openInactiveAccountDialog(message) {
  const dialog = getEl("inactiveAccountDialog");
  const text = getEl("inactiveAccountText");

  if (!dialog) {
    showToast(message || CONFIG.inactiveAccountMessage, "error");
    return;
  }

  inactiveDialogLastFocus =
    document.activeElement instanceof HTMLElement ? document.activeElement : null;

  if (text) {
    text.textContent = message || CONFIG.inactiveAccountMessage;
  }

  dialog.hidden = false;
  document.body.classList.add("login-modal-open");
  dialog.querySelector("[data-close-inactive-dialog]")?.focus();
}

function closeInactiveAccountDialog() {
  const dialog = getEl("inactiveAccountDialog");

  if (!dialog) {
    return;
  }

  dialog.hidden = true;
  document.body.classList.remove("login-modal-open");
  inactiveDialogLastFocus?.focus?.();
  inactiveDialogLastFocus = null;
}

function initInactiveAccountDialog() {
  const dialog = getEl("inactiveAccountDialog");

  if (!dialog) {
    return;
  }

  dialog.querySelectorAll("[data-close-inactive-dialog]").forEach((button) => {
    button.addEventListener("click", closeInactiveAccountDialog);
  });

  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      closeInactiveAccountDialog();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !dialog.hidden) {
      closeInactiveAccountDialog();
    }
  });
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
  const validation = validateLogin(email, password);

  clearLoginValidation();

  if (!validation.ok) {
    loginError.textContent = validation.message;
    setFieldError(validation.field, validation.message);
    getEl(validation.field)?.focus();
    showToast(validation.message, "error");
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
      if (data.reason === "inactive_account") {
        loginError.textContent = "";
        setLoginButtonLoading(loginButton, false);
        openInactiveAccountDialog();
        return;
      }

      throw new Error(getLoginFailureMessage(response, data));
    }

    saveProfilePreference(email, state.role, rememberProfile.checked);
    showToast(data.message || "Login realizado com sucesso.");

    setTimeout(() => {
      navigateWithTransition(data.redirect || CONFIG.redirectUrl);
    }, CONFIG.redirectDelay);
  } catch (error) {
    const message =
      error instanceof TypeError
        ? CONFIG.serverUnavailableMessage
        : error.message || CONFIG.serverUnavailableMessage;

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

function shouldReduceMotion() {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function updateRolePanelContent(role) {
  const content = ROLE_CONTENT[role] || ROLE_CONTENT.Colaborador;
  const badge = getEl("roleBadge");
  const title = getEl("roleTitle");
  const description = getEl("roleDescription");

  if (badge) {
    badge.textContent = content.badge;
  }

  if (title) {
    title.textContent = content.title;
  }

  if (description) {
    description.textContent = content.description;
  }
}

function syncRoleInput(role) {
  const roleInput = getEl("roleInput");

  if (roleInput) {
    roleInput.value = role;
  }
}

function updateRoleButtonState(buttons, selectedButton) {
  buttons.forEach((button) => {
    const isSelected = button === selectedButton;

    button.classList.toggle("active", isSelected);
    button.setAttribute("aria-checked", isSelected ? "true" : "false");
  });
}

function animateRolePanelChange(role, direction, segmentControl) {
  const panel = getEl("rolePanel");
  const gsapInstance = window.gsap;

  if (!panel || !gsapInstance || shouldReduceMotion()) {
    updateRolePanelContent(role);
    roleSwitchAnimating = false;
    segmentControl?.removeAttribute("data-switching");
    return;
  }

  const exitX = direction === "left" ? -28 : 28;
  const enterX = direction === "left" ? 28 : -28;

  roleSwitchAnimating = true;

  gsapInstance
    .timeline({
      defaults: {
        duration: 0.32,
        ease: "power2.out",
      },
      onComplete: () => {
        roleSwitchAnimating = false;
        segmentControl?.removeAttribute("data-switching");
        gsapInstance.set(panel, { clearProps: "transform,opacity" });
      },
    })
    .to(panel, {
      x: exitX,
      opacity: 0,
      duration: 0.28,
    })
    .add(() => updateRolePanelContent(role))
    .fromTo(
      panel,
      { x: enterX, opacity: 0 },
      { x: 0, opacity: 1, duration: 0.34, ease: "power3.out" },
    );
}

function updateSecurityMeter(role) {
  const meter = getEl("securityMeter");

  if (!meter) return;

  meter.style.width = role === "Administrador" ? "84%" : "72%";
}

function setActiveRole(buttons, selectedButton, segmentControl) {
  if (roleSwitchAnimating) {
    return;
  }

  const selectedRole = selectedButton.dataset.role;

  if (!selectedRole) return;

  const previousRole = state.role;
  const direction = selectedRole === "Administrador" ? "left" : "right";

  updateRoleButtonState(buttons, selectedButton);

  roleSwitchAnimating = true;
  segmentControl.dataset.switching = "true";
  state.role = selectedRole;
  syncRoleInput(selectedRole);
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

  if (previousRole !== selectedRole) {
    animateRolePanelChange(selectedRole, direction, segmentControl);
  } else {
    roleSwitchAnimating = false;
    segmentControl.removeAttribute("data-switching");
  }
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

  updateRoleButtonState(buttons, selectedButton);
  syncRoleInput(state.role);

  segmentControl.dataset.active = state.role;
  updateSecurityMeter(state.role);
  updateRolePanelContent(state.role);

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const selectedRole = button.dataset.role;
      const isAlreadyActive =
        state.role === selectedRole && button.classList.contains("active");

      if (isAlreadyActive) return;

      setActiveRole(buttons, button, segmentControl);
    });

    button.addEventListener("keydown", (event) => {
      const keys = ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"];

      if (!keys.includes(event.key)) return;

      event.preventDefault();

      const currentIndex = buttons.indexOf(button);
      const step = event.key === "ArrowRight" || event.key === "ArrowDown" ? 1 : -1;
      const nextIndex = (currentIndex + step + buttons.length) % buttons.length;
      const nextButton = buttons[nextIndex];

      nextButton.focus();
      setActiveRole(buttons, nextButton, segmentControl);
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
    passwordToggle.setAttribute("aria-pressed", isHidden ? "true" : "false");
  });
}

function initFieldValidation() {
  ["email", "password"].forEach((fieldId) => {
    getEl(fieldId)?.addEventListener("input", () => clearFieldError(fieldId));
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
  initInactiveAccountDialog();
  initRoleSelector();
  initSavedProfile();
  initPasswordToggle();
  initFieldValidation();
  initSupportLinks();
  initRequestAccess();
  initBenefitCards();
  initLoginForm();
  initCustomCursor();
  startPageEntrance();
  initSessionMessage();
}
