const state = {
  role: "Colaborador",
};

const STORAGE_KEYS = {
  theme: "titech-theme",
};

const CONFIG = {
  loginUrl: "Pagina-login.html",
  pageTransitionDelay: 520,
  themeTransitionDelay: 560,
  toastDuration: 2200,
  toastRemoveDelay: 250,
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

  document.body.appendChild(createElement("div", "page-transition-layer"));
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
    window.location.href = url;
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
      icon.className = type === "error" ? "bi bi-x-circle" : "bi bi-check-circle";
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

function initTheme() {
  setTheme(getSavedItem(STORAGE_KEYS.theme) || "dark");

  document.querySelectorAll(".theme-toggle").forEach((button) => {
    button.addEventListener("click", toggleTheme);
  });
}

function setActiveRole(buttons, selectedButton, segmentControl) {
  const selectedRole = selectedButton.dataset.role;
  const selectedRoleInput = getEl("selectedRole");

  if (!selectedRole) return;

  buttons.forEach((button) => {
    button.classList.toggle("active", button === selectedButton);
  });

  state.role = selectedRole;
  segmentControl.dataset.active = selectedRole;

  if (selectedRoleInput) {
    selectedRoleInput.value = selectedRole;
  }

  showToast(`Tipo de cadastro: ${selectedRole}`);
}

function initRoleSelector() {
  const segmentControl = document.querySelector(".segment-control");
  const buttons = [...document.querySelectorAll(".segment-control button")];

  if (!segmentControl || !buttons.length) return;

  const activeButton = buttons.find((button) => button.classList.contains("active")) || buttons[0];

  state.role = activeButton.dataset.role || state.role;
  segmentControl.dataset.active = state.role;

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const selectedRole = button.dataset.role;
      const isAlreadyActive = state.role === selectedRole && button.classList.contains("active");

      if (isAlreadyActive) return;

      setActiveRole(buttons, button, segmentControl);
    });
  });
}

function initPasswordToggles() {
  document.querySelectorAll(".password-toggle").forEach((button) => {
    button.addEventListener("click", () => {
      const targetId = button.dataset.target;
      const input = targetId ? getEl(targetId) : null;
      const icon = button.querySelector("i");

      if (!input) return;

      const isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";

      if (icon) {
        icon.className = isHidden ? "bi bi-eye-slash" : "bi bi-eye";
      }

      button.setAttribute(
        "aria-label",
        isHidden ? "Ocultar senha" : "Mostrar senha",
      );
    });
  });
}

function getPasswordStrength(password) {
  let score = 0;

  if (password.length >= 6) score += 1;
  if (password.length >= 10) score += 1;
  if (/[A-Z]/.test(password)) score += 1;
  if (/\d/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;

  if (!password) {
    return { label: "Forca da senha: aguardando", width: "0%", color: "var(--muted)" };
  }

  if (score <= 2) {
    return { label: "Forca da senha: baixa", width: "36%", color: "var(--danger)" };
  }

  if (score <= 4) {
    return { label: "Forca da senha: media", width: "68%", color: "var(--warning)" };
  }

  return { label: "Forca da senha: alta", width: "100%", color: "var(--success)" };
}

function updatePasswordStrength() {
  const passwordInput = getEl("password");
  const bar = getEl("passwordStrengthBar");
  const text = getEl("passwordStrengthText");

  if (!passwordInput || !bar || !text) return;

  const strength = getPasswordStrength(passwordInput.value);

  bar.style.setProperty("--strength", strength.width);
  bar.style.setProperty("--strength-color", strength.color);
  text.textContent = strength.label;
}

function initPasswordStrength() {
  const passwordInput = getEl("password");

  if (!passwordInput) return;

  passwordInput.addEventListener("input", updatePasswordStrength);
  updatePasswordStrength();
}

function getOnlyNumbers(value) {
  return value.replace(/\D/g, "");
}

function formatCpf(value) {
  return getOnlyNumbers(value)
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
}

function formatRg(value) {
  return getOnlyNumbers(value)
    .slice(0, 9)
    .replace(/(\d{2})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1})$/, "$1-$2");
}

function formatCellphone(value) {
  return getOnlyNumbers(value)
    .slice(0, 11)
    .replace(/(\d{2})(\d)/, "($1) $2")
    .replace(/(\d{5})(\d{1,4})$/, "$1-$2");
}

function initDocumentMasks() {
  const masks = [
    { input: getEl("rg"), formatter: formatRg },
    { input: getEl("cpf"), formatter: formatCpf },
    { input: getEl("cellphone"), formatter: formatCellphone },
  ];

  masks.forEach(({ input, formatter }) => {
    input?.addEventListener("input", () => {
      input.value = formatter(input.value);
    });
  });
}

function validateSignup(data) {
  if (
    !data.fullName ||
    !data.email ||
    !data.password ||
    !data.rg ||
    !data.cpf ||
    !data.cellphone ||
    !data.birthDate ||
    !data.type ||
    !data.group ||
    !data.company
  ) {
    return "Preencha todos os campos para continuar.";
  }

  if (data.fullName.trim().split(/\s+/).length < 2) {
    return "Informe nome e sobrenome.";
  }

  if (!data.email.includes("@")) {
    return "Digite um e-mail valido.";
  }

  if (!data.email.toLowerCase().endsWith("@titechsolutions.com.br")) {
    return "Use um e-mail corporativo autorizado.";
  }

  if (getOnlyNumbers(data.rg).length < 7) {
    return "Informe um RG valido.";
  }

  if (getOnlyNumbers(data.cpf).length !== 11) {
    return "Informe um CPF valido.";
  }

  if (getOnlyNumbers(data.cellphone).length !== 11) {
    return "Informe um telefone celular valido com DDD.";
  }

  if (new Date(data.birthDate) > new Date()) {
    return "A data de nascimento nao pode ser futura.";
  }

  if (data.password.length < 6) {
    return "A senha precisa ter pelo menos 6 caracteres.";
  }

  return "";
}

function setSignupButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;

  if (!isLoading) {
    button.replaceChildren(
      createElement("i", "bi bi-person-check"),
      createElement("span", "", "Cadastrar usuario"),
    );
    return;
  }

  button.replaceChildren(
    createElement("span", "spinner-border spinner-border-sm"),
    createElement("span", "", "Criando cadastro..."),
  );
}

async function handleSignup(event) {
  event.preventDefault();

  const signupError = getEl("signupError");
  const signupButton = getEl("signupButton");

  const data = {
    fullName: getEl("fullName")?.value.trim() || "",
    email: getEl("email")?.value.trim() || "",
    password: getEl("password")?.value || "",
    rg: getEl("rg")?.value.trim() || "",
    cpf: getEl("cpf")?.value.trim() || "",
    cellphone: getEl("cellphone")?.value.trim() || "",
    birthDate: getEl("birthDate")?.value || "",
    type: state.role,
    group: getEl("group")?.value || "",
    company: getEl("company")?.value.trim() || "",
  };

  const error = validateSignup(data);

  if (!signupError || !signupButton) return;

  if (error) {
    signupError.textContent = error;
    showToast(error, "error");
    return;
  }

  signupError.textContent = "";
  setSignupButtonLoading(signupButton, true);

  try {
    const response = await fetch(event.target.action, {
      method: "POST",
      body: new FormData(event.target),
      headers: {
        Accept: "application/json",
      },
    });

    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel criar o cadastro.");
    }

    showToast(result.message || `Usuario ${data.type} cadastrado com sucesso.`);
    setSignupButtonLoading(signupButton, false);
    event.target.reset();
    state.role = "Colaborador";

    document.querySelectorAll(".segment-control button").forEach((button) => {
      button.classList.toggle("active", button.dataset.role === state.role);
    });

    document.querySelector(".segment-control")?.setAttribute("data-active", state.role);
    const selectedRoleInput = getEl("selectedRole");
    if (selectedRoleInput) {
      selectedRoleInput.value = state.role;
    }
    updatePasswordStrength();

    setTimeout(() => {
      navigateWithTransition(result.redirect || CONFIG.loginUrl);
    }, 900);
  } catch (error) {
    const message = error.message || "Nao foi possivel criar o cadastro.";

    signupError.textContent = message;
    showToast(message, "error");
    setSignupButtonLoading(signupButton, false);
  }
}

function initSignupForm() {
  const signupForm = getEl("signupForm");

  if (!signupForm) return;

  signupForm.addEventListener("submit", handleSignup);
}

function initNavigationLinks() {
  document.querySelectorAll("a[href='Pagina-login.html']").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      navigateWithTransition(CONFIG.loginUrl);
    });
  });
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
    if (event.target.closest("a, button, input, label, select, .form-control, [role='button']")) {
      document.body.classList.add("cursor-hover");
    }
  });

  document.addEventListener("mouseout", (event) => {
    if (event.target.closest("a, button, input, label, select, .form-control, [role='button']")) {
      document.body.classList.remove("cursor-hover");
    }
  });
}

function init() {
  initTheme();
  initRoleSelector();
  initPasswordToggles();
  initPasswordStrength();
  initDocumentMasks();
  initNavigationLinks();
  initSignupForm();
  initCustomCursor();
  startPageEntrance();
}
