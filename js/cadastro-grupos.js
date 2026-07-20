// Gerencia seleção de membros e permissões durante o cadastro de grupos de acesso.
// Usa confirmações e avisos globais definidos pelos módulos compartilhados.

document.addEventListener("DOMContentLoaded", initGroupRegistrationPage);

function initGroupRegistrationPage() {
  callGroupGlobal("startPageAnimation");
  callGroupGlobal("loadSavedTheme");
  callGroupGlobal("setupThemeToggle");
  callGroupGlobal("setupSidebar");
  callGroupGlobal("setupNavGroups");
  setupGroupEmployeeSearch();
  setupGroupForm();
  setupGroupFormReset();
}

function callGroupGlobal(functionName) {
  if (typeof window[functionName] === "function") {
    window[functionName]();
  }
}

function getGroupElement(id) {
  return document.getElementById(id);
}

function createGroupElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}

// A busca atua apenas sobre os funcionários já carregados pelo PHP.
function setupGroupEmployeeSearch() {
  const search = getGroupElement("groupEmployeeSearch");
  const clearButton = getGroupElement("clearGroupEmployees");

  search?.addEventListener("input", filterGroupEmployees);

  clearButton?.addEventListener("click", () => {
    if (search) {
      search.value = "";
    }

    document
      .querySelectorAll('#groupEmployeeList input[type="checkbox"]')
      .forEach((input) => {
        input.checked = false;
      });

    filterGroupEmployees();
  });
}

function filterGroupEmployees() {
  const search = normalizeGroupText(getGroupElement("groupEmployeeSearch")?.value || "");

  document.querySelectorAll(".group-check-card").forEach((card) => {
    const haystack = normalizeGroupText(card.dataset.search || "");
    card.hidden = search !== "" && !haystack.includes(search);
  });
}

function normalizeGroupText(value) {
  return String(value)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function setupGroupForm() {
  const form = getGroupElement("groupForm");

  if (!form) {
    return;
  }

  form.addEventListener("submit", handleGroupSubmit);
}

function setupGroupFormReset() {
  const form = getGroupElement("groupForm");

  if (!form) {
    return;
  }

  form.addEventListener("reset", () => {
    requestAnimationFrame(() => {
      setGroupMessage("");
      filterGroupEmployees();
    });
  });
}

// Membros e permissões são enviados juntos para o backend gravar o grupo de forma atômica.
async function handleGroupSubmit(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = getGroupElement("groupSubmitButton");
  const validationError = validateGroupForm(form);

  if (validationError) {
    setGroupMessage(validationError, "error");
    window.titechToast?.(validationError, "error");
    return;
  }

  const groupName = getGroupElement("groupName")?.value.trim() || "este grupo";
  const confirmed = await confirmGroupCreate(groupName);

  if (!confirmed) {
    return;
  }

  setGroupMessage("");
  setGroupSubmitLoading(submitButton, true);

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
      throw new Error(result.message || "Nao foi possivel cadastrar o grupo.");
    }

    setGroupMessage(result.message || "Grupo criado com sucesso.", "success");
    window.titechToast?.(result.message || "Grupo criado com sucesso.");
    updateGroupMetrics(result.grupo);
    prependRecentGroup(result.grupo);
    form.reset();
  } catch (error) {
    const message =
      error instanceof Error ? error.message : "Nao foi possivel cadastrar o grupo.";

    setGroupMessage(message, "error");
    window.titechToast?.(message, "error");
  } finally {
    setGroupSubmitLoading(submitButton, false);
  }
}

function validateGroupForm(form) {
  const name = getGroupElement("groupName")?.value.trim() || "";
  const members = form.querySelectorAll('input[name="membros[]"]:checked');
  const permissions = form.querySelectorAll('input[name="permissoes[]"]:checked');

  if (name.length < 3) {
    return "Informe um nome de grupo com pelo menos 3 caracteres.";
  }

  if (!members.length) {
    return "Selecione pelo menos um funcionario para o grupo.";
  }

  if (!permissions.length) {
    return "Selecione pelo menos uma permissao para o grupo.";
  }

  return "";
}

async function confirmGroupCreate(groupName) {
  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar grupo?",
      text: `Confirme para criar o grupo ${groupName} com os funcionarios e permissoes selecionados.`,
      confirmButtonText: "Cadastrar grupo",
      cancelButtonText: "Revisar",
      icon: "info",
    });
  }

  return window.confirm(`Criar o grupo ${groupName}?`);
}

function setGroupMessage(message, type = "") {
  const element = getGroupElement("groupFormMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.remove("is-error", "is-success");

  if (type === "error") {
    element.classList.add("is-error");
  }

  if (type === "success") {
    element.classList.add("is-success");
  }
}

function setGroupSubmitLoading(button, isLoading) {
  if (!button) {
    return;
  }

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createGroupElement("span", "spinner-border spinner-border-sm"),
      createGroupElement("span", "", "Cadastrando grupo..."),
    );
    return;
  }

  button.replaceChildren(
    createGroupElement("i", "bi bi-plus-lg"),
    createGroupElement("span", "", "Cadastrar grupo"),
  );
}

function updateGroupMetrics(group) {
  if (!group || typeof group !== "object") {
    return;
  }

  incrementGroupMetric("groupMetricTotal", 1);
  incrementGroupMetric("groupMetricMembers", Number(group.total_membros || 0));
  incrementGroupMetric("groupMetricPermissions", Number(group.total_permissoes || 0));
}

function incrementGroupMetric(id, amount) {
  const element = getGroupElement(id);
  const current = Number.parseInt(element?.textContent || "0", 10);

  if (!element || Number.isNaN(current)) {
    return;
  }

  element.textContent = String(current + amount);
}

// O novo cartão usa APIs de DOM para manter os valores da resposta como texto.
function prependRecentGroup(group) {
  if (!group || typeof group !== "object") {
    return;
  }

  const list = getGroupElement("recentGroupList");

  if (!list) {
    return;
  }

  list.querySelector(".compact-empty-state")?.remove();

  const article = createGroupElement(
    "article",
    "recent-asset-item recent-employee-card group-recent-card",
  );
  const topLine = createGroupElement("div", "recent-asset-topline");
  const title = createGroupElement("strong", "", group.nome || "Novo grupo");
  const status = createGroupElement("span", "status-badge status-active", group.status || "Ativo");
  const footer = createGroupElement("div", "recent-asset-footer");
  const members = createGroupElement("span", "", `${group.total_membros || 0} membros`);
  const permissions = createGroupElement(
    "span",
    "",
    `${group.total_permissoes || 0} permissoes`,
  );
  const time = document.createElement("time");

  time.dateTime = group.criado_em || "";
  time.textContent = formatGroupDateTime(group.criado_em || "");

  topLine.append(title, status);
  footer.append(members, permissions, time);
  article.append(topLine, footer);
  list.prepend(article);

  [...list.querySelectorAll(".group-recent-card")]
    .slice(6)
    .forEach((card) => card.remove());
}

function formatGroupDateTime(value) {
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
