// Coordena perfil, máscaras, validações e envio do formulário de novos funcionários.
// Os helpers globais de interface são carregados pela página antes deste módulo.

const employeeSignupState = {
  role: "Colaborador",
};

document.addEventListener("DOMContentLoaded", initEmployeeRegistrationPage);

function initEmployeeRegistrationPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupEmployeeRoleSelector();
  setupEmployeePasswordToggle();
  setupEmployeePasswordStrength();
  setupEmployeeDocumentMasks();
  setupEmployeeSignupForm();
  setupEmployeeFormReset();
}

function getEmployeeElement(id) {
  return document.getElementById(id);
}

function createEmployeeElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}

// O seletor visual e o campo enviado ao backend precisam permanecer sincronizados.
function setupEmployeeRoleSelector() {
  const roleControl = getEmployeeElement("employeeRoleControl");
  const buttons = roleControl
    ? [...roleControl.querySelectorAll("button[data-role]")]
    : [];

  if (!roleControl || !buttons.length) {
    return;
  }

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const nextRole = button.dataset.role || "Colaborador";

      if (
        employeeSignupState.role === nextRole &&
        button.classList.contains("active")
      ) {
        return;
      }

      setEmployeeRole(nextRole);
    });
  });

  setEmployeeRole(employeeSignupState.role);
}

function setEmployeeRole(role) {
  const nextRole = role === "Administrador" ? "Administrador" : "Colaborador";
  const roleControl = getEmployeeElement("employeeRoleControl");
  const hiddenRole = getEmployeeElement("selectedEmployeeRole");
  const buttons = roleControl
    ? [...roleControl.querySelectorAll("button[data-role]")]
    : [];

  employeeSignupState.role = nextRole;

  if (roleControl) {
    roleControl.dataset.active = nextRole;
  }

  if (hiddenRole) {
    hiddenRole.value = nextRole;
  }

  buttons.forEach((button) => {
    button.classList.toggle("active", button.dataset.role === nextRole);
  });
}

function setupEmployeePasswordToggle() {
  document
    .querySelectorAll(".password-toggle[data-target]")
    .forEach((button) => {
      button.addEventListener("click", () => {
        const targetId = button.dataset.target;
        const input = targetId ? getEmployeeElement(targetId) : null;
        const icon = button.querySelector("i");

        if (!input) {
          return;
        }

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

function getEmployeePasswordStrength(password) {
  let score = 0;

  if (password.length >= 6) score += 1;
  if (password.length >= 10) score += 1;
  if (/[A-Z]/.test(password)) score += 1;
  if (/\d/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;

  if (!password) {
    return {
      label: "Forca da senha: aguardando",
      width: "0%",
      color: "var(--muted)",
    };
  }

  if (score <= 2) {
    return { label: "Forca da senha: baixa", width: "36%", color: "#ef4444" };
  }

  if (score <= 4) {
    return { label: "Forca da senha: media", width: "68%", color: "#f59e0b" };
  }

  return { label: "Forca da senha: alta", width: "100%", color: "#10b981" };
}

function updateEmployeePasswordStrength() {
  const passwordInput = getEmployeeElement("employeePassword");
  const strengthBar = getEmployeeElement("employeePasswordStrengthBar");
  const strengthText = getEmployeeElement("employeePasswordStrengthText");

  if (!passwordInput || !strengthBar || !strengthText) {
    return;
  }

  const strength = getEmployeePasswordStrength(passwordInput.value);

  strengthBar.style.setProperty("--strength", strength.width);
  strengthBar.style.setProperty("--strength-color", strength.color);
  strengthText.textContent = strength.label;
}

function setupEmployeePasswordStrength() {
  const passwordInput = getEmployeeElement("employeePassword");

  if (!passwordInput) {
    return;
  }

  passwordInput.addEventListener("input", updateEmployeePasswordStrength);
  updateEmployeePasswordStrength();
}

function getOnlyNumbers(value) {
  return value.replace(/\D/g, "");
}

function formatEmployeeCpf(value) {
  return getOnlyNumbers(value)
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
}

function isValidEmployeeCpf(value) {
  const cpf = getOnlyNumbers(value);

  if (cpf.length !== 11) {
    return false;
  }

  if (/^(\d)\1{10}$/.test(cpf)) {
    return false;
  }

  let sum = 0;

  for (let index = 0; index < 9; index += 1) {
    sum += Number(cpf[index]) * (10 - index);
  }

  let firstDigit = (sum * 10) % 11;

  if (firstDigit === 10) {
    firstDigit = 0;
  }

  if (firstDigit !== Number(cpf[9])) {
    return false;
  }

  sum = 0;

  for (let index = 0; index < 10; index += 1) {
    sum += Number(cpf[index]) * (11 - index);
  }

  let secondDigit = (sum * 10) % 11;

  if (secondDigit === 10) {
    secondDigit = 0;
  }

  return secondDigit === Number(cpf[10]);
}

function formatEmployeeRg(value) {
  return getOnlyNumbers(value)
    .slice(0, 9)
    .replace(/(\d{2})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)$/, "$1-$2");
}

function formatEmployeeCellphone(value) {
  return getOnlyNumbers(value)
    .slice(0, 11)
    .replace(/(\d{2})(\d)/, "($1) $2")
    .replace(/(\d{5})(\d{1,4})$/, "$1-$2");
}

// As máscaras ajudam na digitação; a validade real também é conferida antes do envio.
function setupEmployeeDocumentMasks() {
  const masks = [
    { input: getEmployeeElement("employeeRg"), formatter: formatEmployeeRg },
    { input: getEmployeeElement("employeeCpf"), formatter: formatEmployeeCpf },
    {
      input: getEmployeeElement("employeeCellphone"),
      formatter: formatEmployeeCellphone,
    },
  ];

  masks.forEach(({ input, formatter }) => {
    input?.addEventListener("input", () => {
      input.value = formatter(input.value);
    });
  });
}

// Concentra as regras do formulário para impedir validações divergentes entre os eventos.
function validateEmployeeSignup(data) {
  if (
    !data.nomeCompleto ||
    !data.email ||
    !data.senha ||
    !data.rg ||
    !data.cpf ||
    !data.celular ||
    !data.dataNascimento ||
    !data.tipoUsuario ||
    !data.departamento ||
    !data.empresa
  ) {
    return "Preencha todos os campos obrigatorios para continuar.";
  }

  if (data.nomeCompleto.trim().split(/\s+/).length < 2) {
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

  if (!isValidEmployeeCpf(data.cpf)) {
    return "Informe um CPF valido.";
  }

  if (getOnlyNumbers(data.celular).length !== 11) {
    return "Informe um telefone celular valido com DDD.";
  }

  if (new Date(data.dataNascimento) > new Date()) {
    return "A data de nascimento nao pode ser futura.";
  }

  if (data.senha.length < 6) {
    return "A senha precisa ter pelo menos 6 caracteres.";
  }

  return "";
}

function setEmployeeFormMessage(message, type = "") {
  const messageBox = getEmployeeElement("employeeFormMessage");

  if (!messageBox) {
    return;
  }

  messageBox.textContent = message;
  messageBox.classList.remove("is-error", "is-success");

  if (type === "error") {
    messageBox.classList.add("is-error");
  }

  if (type === "success") {
    messageBox.classList.add("is-success");
  }
}

function setEmployeeSubmitLoading(button, isLoading) {
  if (!button) {
    return;
  }

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createEmployeeElement("span", "spinner-border spinner-border-sm"),
      createEmployeeElement("span", "", "Cadastrando funcionario..."),
    );
    return;
  }

  button.replaceChildren(
    createEmployeeElement("i", "bi bi-person-plus-fill"),
    createEmployeeElement("span", "", "Cadastrar funcionario"),
  );
}

function buildEmployeePayload() {
  return {
    nomeCompleto: getEmployeeElement("employeeFullName")?.value.trim() || "",
    email: getEmployeeElement("employeeEmail")?.value.trim() || "",
    senha: getEmployeeElement("employeePassword")?.value || "",
    rg: getEmployeeElement("employeeRg")?.value.trim() || "",
    cpf: getEmployeeElement("employeeCpf")?.value.trim() || "",
    celular: getEmployeeElement("employeeCellphone")?.value.trim() || "",
    dataNascimento: getEmployeeElement("employeeBirthDate")?.value || "",
    tipoUsuario: employeeSignupState.role,
    departamento: getEmployeeElement("employeeDepartment")?.value || "",
    empresa: getEmployeeElement("employeeCompany")?.value.trim() || "",
  };
}

// O cadastro só altera a interface após uma resposta JSON bem-sucedida do servidor.
async function handleEmployeeSignup(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = getEmployeeElement("employeeSubmitButton");
  const payload = buildEmployeePayload();
  const validationError = validateEmployeeSignup(payload);

  if (validationError) {
    setEmployeeFormMessage(validationError, "error");
    window.titechToast?.(validationError, "error");
    return;
  }

  const confirmed = await confirmEmployeeRegistration(payload);

  if (!confirmed) {
    return;
  }

  setEmployeeFormMessage("");
  setEmployeeSubmitLoading(submitButton, true);

  try {
    const response = await fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: {
        Accept: "application/json",
      },
    });

    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (result.redirect && response.status === 401) {
      window.location.href = result.redirect;
      return;
    }

    if (!response.ok || !result.ok) {
      throw new Error(
        result.message || "Nao foi possivel cadastrar o funcionario.",
      );
    }

    setEmployeeFormMessage(
      result.message || "Funcionario cadastrado com sucesso.",
      "success",
    );
    window.titechToast?.(
      result.message || "Funcionario cadastrado com sucesso.",
    );
    updateEmployeeSummary(result.usuario || null);
    prependRecentEmployee(result.usuario || null);
    form.reset();
    resetEmployeeSignupFormState();
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "Nao foi possivel cadastrar o funcionario.";

    setEmployeeFormMessage(message, "error");
    window.titechToast?.(message, "error");
  } finally {
    setEmployeeSubmitLoading(submitButton, false);
  }
}

async function confirmEmployeeRegistration(payload) {
  const employeeName = payload.nomeCompleto || "este funcionario";
  const employeeRole = payload.tipoUsuario || "Colaborador";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar funcionario?",
      text: `Confirme para criar o acesso de ${employeeName} como ${employeeRole}.`,
      confirmButtonText: "Cadastrar funcionario",
      cancelButtonText: "Revisar dados",
      icon: "info",
    });
  }

  return window.confirm(`Criar o acesso de ${employeeName} como ${employeeRole}?`);
}

function setupEmployeeSignupForm() {
  const form = getEmployeeElement("employeeSignupForm");

  if (!form) {
    return;
  }

  form.addEventListener("submit", handleEmployeeSignup);
}

function setupEmployeeFormReset() {
  const form = getEmployeeElement("employeeSignupForm");

  if (!form) {
    return;
  }

  form.addEventListener("reset", () => {
    requestAnimationFrame(() => {
      resetEmployeeSignupFormState();
      setEmployeeFormMessage("");
    });
  });
}

function resetEmployeeSignupFormState() {
  setEmployeeRole("Colaborador");
  updateEmployeePasswordStrength();
}

// Mantém métricas e lista recente coerentes com o funcionário recém-cadastrado.
function updateEmployeeSummary(usuario) {
  if (!usuario || typeof usuario !== "object") {
    return;
  }

  incrementEmployeeMetric("employeeMetricTotal");

  if ((usuario.tipo_usuario || "") === "Administrador") {
    incrementEmployeeMetric("employeeMetricAdmins");
  } else {
    incrementEmployeeMetric("employeeMetricCollaborators");
  }

  const lastMetric = getEmployeeElement("employeeMetricLast");

  if (lastMetric && usuario.criado_em) {
    lastMetric.textContent = formatEmployeeDateTime(usuario.criado_em);
  }
}

function incrementEmployeeMetric(id) {
  const element = getEmployeeElement(id);
  const currentValue = Number.parseInt(element?.textContent || "0", 10);

  if (!element || Number.isNaN(currentValue)) {
    return;
  }

  element.textContent = String(currentValue + 1);
}

function prependRecentEmployee(usuario) {
  if (!usuario || typeof usuario !== "object") {
    return;
  }

  const list = getEmployeeElement("recentEmployeeList");

  if (!list) {
    return;
  }

  list.querySelector(".compact-empty-state")?.remove();

  const article = createEmployeeElement(
    "article",
    "recent-asset-item recent-employee-card",
  );
  const topLine = createEmployeeElement("div", "recent-asset-topline");
  const name = createEmployeeElement(
    "strong",
    "",
    usuario.nome_completo || "Funcionario",
  );
  const status = createEmployeeElement(
    "span",
    `status-badge ${String(usuario.status || "").toLowerCase() === "ativo" ? "status-active" : "status-neutral"}`,
    usuario.status || "Ativo",
  );
  const meta = createEmployeeElement("div", "recent-asset-meta");
  const role = createEmployeeElement("span", "", usuario.tipo_usuario || "--");
  const department = createEmployeeElement(
    "span",
    "",
    usuario.departamento || "--",
  );
  const footer = createEmployeeElement("div", "recent-asset-footer");
  const email = createEmployeeElement("span", "", usuario.email || "--");
  const time = document.createElement("time");

  time.dateTime = usuario.criado_em || "";
  time.textContent = formatEmployeeDateTime(usuario.criado_em || "");

  topLine.append(name, status);
  meta.append(role, department);
  footer.append(email, time);
  article.append(topLine, meta, footer);

  list.prepend(article);

  const cards = [...list.querySelectorAll(".recent-employee-card")];

  cards.slice(6).forEach((card) => card.remove());
}

function formatEmployeeDateTime(value) {
  if (!value) {
    return "--";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "--";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "America/Sao_Paulo",
  }).format(date);
}
