// Gerencia filtros e o modal de atualização dos dados de funcionários.
// O cartão editado é sincronizado no DOM após a confirmação do backend.

const EMPLOYEE_EDIT_MESSAGE_HIDE_DELAY_MS = 2800;

document.addEventListener("DOMContentLoaded", initEmployeeEditPage);

function initEmployeeEditPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupEmployeeFilters();
  setupEmployeeCards();
  setupEmployeeEditModal();
}

function setupEmployeeFilters() {
  document.getElementById("employeeSearch")?.addEventListener("input", filterEmployees);
  document.getElementById("employeeStatusFilter")?.addEventListener("change", filterEmployees);

  filterEmployees();
}

function setupEmployeeCards() {
  document.getElementById("employeeCardList")?.addEventListener("click", (event) => {
    const card = event.target.closest("[data-employee-card]");

    if (card) {
      openEmployeeEditModal(card);
    }
  });
}

function setupEmployeeEditModal() {
  const modal = document.getElementById("employeeEditModal");
  const form = document.getElementById("employeeEditForm");

  form?.addEventListener("submit", submitEmployeeEditForm);

  document.querySelectorAll("[data-close-employee-modal]").forEach((button) => {
    button.addEventListener("click", closeEmployeeEditModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeEmployeeEditModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal && !modal.hidden) {
      closeEmployeeEditModal();
    }
  });
}

// A filtragem ocorre sobre os cartões já renderizados e mantém o estado vazio sincronizado.
function filterEmployees() {
  const cards = Array.from(document.querySelectorAll(".employee-row"));
  const search = normalizeText(document.getElementById("employeeSearch")?.value || "");
  const status = normalizeText(document.getElementById("employeeStatusFilter")?.value || "todos");
  let visibleCount = 0;
  let activeCount = 0;
  let inactiveCount = 0;

  cards.forEach((card) => {
    const rowStatus = normalizeText(card.dataset.status || "");
    const rowSearch = normalizeText(card.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesSearch;

    if (rowStatus === "ativo") {
      activeCount += 1;
    } else if (rowStatus === "inativo") {
      inactiveCount += 1;
    }

    card.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateEmployeeText("employeeResultCount", `${visibleCount.toLocaleString("pt-BR")} ${visibleCount === 1 ? "registro" : "registros"}`);
  updateEmployeeText("employeeTotalMetric", String(cards.length));
  updateEmployeeText("employeeActiveMetric", String(activeCount));
  updateEmployeeText("employeeInactiveMetric", String(inactiveCount));
  updateFilteredEmptyState(cards.length > 0 && visibleCount === 0);
}

// O modal é preenchido a partir dos atributos do cartão selecionado.
function openEmployeeEditModal(card) {
  const modal = document.getElementById("employeeEditModal");

  if (!modal) {
    return;
  }

  modal.dataset.lastTriggerId = card.dataset.id || "";
  setEmployeeValue("editEmployeeId", card.dataset.id || "");
  setEmployeeValue("editEmployeeName", card.dataset.name || "");
  setEmployeeValue("editEmployeeEmail", card.dataset.email || "");
  setEmployeeValue("editEmployeeRole", card.dataset.role || "Colaborador");
  setEmployeeValue("editEmployeeStatus", card.dataset.statusLabel || "Ativo");
  setEmployeeValue("editEmployeeDepartment", card.dataset.department || "TI");
  setEmployeeValue("editEmployeeCompany", card.dataset.company || "");
  setEmployeeValue("editEmployeeRg", card.dataset.rg || "");
  setEmployeeValue("editEmployeeCpf", card.dataset.cpf || "");
  setEmployeeValue("editEmployeePhone", card.dataset.phone || "");
  setEmployeeValue("editEmployeeBirth", card.dataset.birthValue || "");
  updateEmployeeText("employeeEditInitials", card.dataset.initials || "TT");
  updateEmployeeText("employeeEditModalTitle", card.dataset.name || "Funcionario");
  updateEmployeeText("employeeEditEmailText", card.dataset.email || "--");
  clearEmployeeEditMessage();

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editEmployeeName")?.focus();
}

function closeEmployeeEditModal() {
  const modal = document.getElementById("employeeEditModal");

  if (!modal) {
    return;
  }

  modal.hidden = true;

  const triggerId = modal.dataset.lastTriggerId || "";
  const trigger = triggerId
    ? document.querySelector(`[data-employee-card][data-id="${cssEscapeEmployee(triggerId)}"]`)
    : null;

  trigger?.focus();
}

// Os dados visuais só são atualizados depois da confirmação do backend.
async function submitEmployeeEditForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveEmployeeButton");
  const error = validateEmployeeEditForm(form);

  if (error) {
    setEmployeeEditMessage(error, "error");
    return;
  }

  const confirmed = window.titechConfirm
    ? await window.titechConfirm({
      title: "Salvar alteracoes?",
      text: "Os dados do funcionario serao atualizados no cadastro.",
      confirmButtonText: "Salvar",
      icon: "warning",
    })
    : window.confirm("Salvar alteracoes deste funcionario?");

  if (!confirmed) {
    return;
  }

  setEmployeeLoading(submitButton, true, "Salvando...");
  clearEmployeeEditMessage();

  try {
    const response = await fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel atualizar o funcionario.");
    }

    updateEmployeeCard(result.funcionario);
    closeEmployeeEditModal();
    setEmployeeEditPageMessage(result.message || "Funcionario atualizado com sucesso.", "success");
    filterEmployees();
  } catch (error) {
    setEmployeeEditMessage(error.message || "Nao foi possivel atualizar o funcionario.", "error");
  } finally {
    setEmployeeLoading(submitButton, false);
  }
}

function validateEmployeeEditForm(form) {
  const data = new FormData(form);
  const name = String(data.get("nome_completo") || "").trim();
  const role = String(data.get("tipo_usuario") || "").trim();
  const status = String(data.get("status") || "").trim();
  const department = String(data.get("departamento") || "").trim();
  const company = String(data.get("empresa") || "").trim();
  const rg = String(data.get("rg") || "").replace(/\D+/g, "");
  const cpf = String(data.get("cpf") || "").replace(/\D+/g, "");
  const phone = String(data.get("celular") || "").replace(/\D+/g, "");
  const birthDate = String(data.get("data_nascimento") || "").trim();

  if (!name || !role || !status || !department || !company || !birthDate) {
    return "Preencha todos os campos obrigatorios.";
  }

  if (name.split(/\s+/).filter(Boolean).length < 2) {
    return "Informe nome e sobrenome.";
  }

  if (rg.length < 7) {
    return "Informe um RG valido.";
  }

  if (cpf.length !== 11) {
    return "Informe um CPF valido.";
  }

  if (phone.length !== 11) {
    return "Informe um celular valido com DDD.";
  }

  if (!["Colaborador", "Administrador"].includes(role)) {
    return "Perfil de acesso invalido.";
  }

  if (!["Ativo", "Inativo"].includes(status)) {
    return "Status invalido.";
  }

  return "";
}

// Sincroniza o cartão existente para evitar recarregar toda a listagem.
function updateEmployeeCard(employee) {
  if (!employee?.id) {
    return;
  }

  const card = document.querySelector(`[data-employee-card][data-id="${cssEscapeEmployee(String(employee.id))}"]`);

  if (!card) {
    return;
  }

  const name = String(employee.nome_completo || "");
  const email = String(employee.email || card.dataset.email || "");
  const role = String(employee.tipo_usuario || "Colaborador");
  const department = String(employee.departamento || "");
  const company = String(employee.empresa || "");
  const phone = String(employee.celular || "");
  const rg = String(employee.rg || "");
  const cpf = String(employee.cpf || "");
  const status = String(employee.status || "Ativo");
  const birthValue = String(employee.data_nascimento || "");
  const birthLabel = formatEmployeeDateOnly(birthValue);
  const updatedLabel = formatEmployeeDateTime(employee.atualizado_em) || "Agora";
  const initials = getEmployeeInitials(name);
  const search = [name, email, role, department, company, phone, rg, cpf, status].join(" ");

  Object.assign(card.dataset, {
    name,
    email,
    role,
    department,
    company,
    phone,
    rg,
    cpf,
    status: normalizeText(status),
    statusLabel: status,
    birth: birthLabel,
    birthValue,
    updated: updatedLabel,
    initials,
    search,
  });

  updateEmployeeTextFrom(card, ".employee-card-avatar", initials);
  updateEmployeeTextFrom(card, ".employee-card-identity strong", name);
  updateEmployeeTextFrom(card, ".employee-card-identity small", email);
  updateEmployeeCardStatus(card, status);
  updateEmployeeCardField(card, "Perfil", role);
  updateEmployeeCardField(card, "Departamento", department);
  updateEmployeeCardField(card, "Empresa", company);
  updateEmployeeCardField(card, "Celular", phone);
}

function updateEmployeeCardStatus(card, status) {
  const badge = card.querySelector(".status-badge");
  const statusClass = normalizeText(status) === "ativo"
    ? "status-active"
    : normalizeText(status) === "inativo"
      ? "status-inactive"
      : "status-neutral";

  if (badge) {
    badge.className = `status-badge ${statusClass}`;
    badge.textContent = status;
  }
}

function updateEmployeeCardField(card, label, value) {
  const items = Array.from(card.querySelectorAll(".employee-card-body > span"));
  const item = items.find((element) => normalizeText(element.querySelector("small")?.textContent || "") === normalizeText(label));

  updateEmployeeTextFrom(item, "strong", value);
}

function updateEmployeeTextFrom(root, selector, value) {
  const element = root?.querySelector?.(selector);

  if (element) {
    element.textContent = value;
  }
}

function updateFilteredEmptyState(show) {
  const emptyState = document.getElementById("employeeEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setEmployeeValue(id, value) {
  const input = document.getElementById(id);

  if (input) {
    input.value = value;
  }
}

function setEmployeeEditMessage(message, type) {
  const element = document.getElementById("employeeEditMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function clearEmployeeEditMessage() {
  setEmployeeEditMessage("", "");
}

function setEmployeeEditPageMessage(message, type) {
  const element = document.getElementById("employeeEditPageMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");

  if (message && type === "success") {
    setTimeout(() => setEmployeeEditPageMessage("", ""), EMPLOYEE_EDIT_MESSAGE_HIDE_DELAY_MS);
  }
}

function setEmployeeLoading(button, isLoading, loadingText = "Aguarde...") {
  if (!button) {
    return;
  }

  if (isLoading) {
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<i class="bi bi-arrow-repeat"></i><span>${loadingText}</span>`;
    return;
  }

  button.disabled = false;

  if (button.dataset.originalHtml) {
    button.innerHTML = button.dataset.originalHtml;
    delete button.dataset.originalHtml;
  }
}

function updateEmployeeText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

function getEmployeeInitials(name) {
  const initials = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");

  return initials || "TT";
}

function formatEmployeeDateOnly(value) {
  if (!value) {
    return "--";
  }

  const date = new Date(`${value}T00:00:00`);

  if (Number.isNaN(date.getTime())) {
    return "--";
  }

  return new Intl.DateTimeFormat("pt-BR").format(date);
}

function formatEmployeeDateTime(value) {
  if (!value) {
    return "";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(date);
}

function cssEscapeEmployee(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(value || ""));
  }

  return String(value || "").replace(/["\\]/g, "\\$&");
}
