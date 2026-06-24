const MESSAGE_HIDE_DELAY_MS = 2700;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupLocationForm();
  setupLocationFilters();
}

function setupLocationForm() {
  const form = document.getElementById("locationForm");

  if (!form) return;

  form.addEventListener("submit", submitLocationForm);
  form.addEventListener("reset", () => {
    setTimeout(() => setLocationMessage("", ""), 0);
  });
}

async function submitLocationForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("locationSubmitButton");
  const error = validateLocationForm(form);

  if (error) {
    setLocationMessage(error, "error");
    return;
  }

  setButtonLoading(submitButton, true);
  setLocationMessage("", "");

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
      throw new Error(result.message || "Nao foi possivel cadastrar o local.");
    }

    setLocationMessage(result.message || "Local cadastrado com sucesso.", "success");
    prependLocationRow(result.local);
    updateMetricsAfterCreate(result.local);
    form.reset();
    filterLocations();

    setTimeout(() => {
      setLocationMessage("", "");
    }, MESSAGE_HIDE_DELAY_MS);
  } catch (error) {
    setLocationMessage(error.message || "Nao foi possivel cadastrar o local.", "error");
  } finally {
    setButtonLoading(submitButton, false);
  }
}

function validateLocationForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const endereco = String(data.get("endereco") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status para cadastrar o local.";
  }

  if (nome.length < 2) {
    return "O nome do local precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 100) {
    return "O nome do local deve ter no maximo 100 caracteres.";
  }

  if (endereco.length > 160) {
    return "O endereco deve ter no maximo 160 caracteres.";
  }

  return "";
}

function setButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createElement("i", "bi bi-arrow-repeat"),
      createElement("span", "", "Cadastrando..."),
    );
    return;
  }

  button.replaceChildren(
    createElement("i", "bi bi-plus-circle"),
    createElement("span", "", "Cadastrar local"),
  );
}

function setLocationMessage(message, type) {
  const element = document.getElementById("locationFormMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function setupLocationFilters() {
  document.getElementById("locationSearch")?.addEventListener("input", filterLocations);
  document.getElementById("locationStatusFilter")?.addEventListener("change", filterLocations);

  filterLocations();
}

function filterLocations() {
  const rows = Array.from(document.querySelectorAll(".location-row"));
  const search = normalizeText(document.getElementById("locationSearch")?.value || "");
  const status = normalizeText(document.getElementById("locationStatusFilter")?.value || "todos");
  let visibleCount = 0;

  rows.forEach((row) => {
    const rowStatus = normalizeText(row.dataset.status || "");
    const rowSearch = normalizeText(row.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesSearch;

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateResultCount(visibleCount);
  updateEmptyState(rows.length === 0 || visibleCount === 0);
}

function prependLocationRow(local) {
  const tbody = document.getElementById("locationTableBody");

  if (!tbody || !local) return;

  document.getElementById("locationEmptyState")?.setAttribute("hidden", "");

  const name = String(local.nome || "Novo local");
  const address = String(local.endereco || "Sem endereco informado");
  const status = String(local.status || "Ativo");
  const row = createElement("tr", "registration-row location-row");
  const nameCell = createElement("td");
  const statusCell = createElement("td");
  const createdCell = createElement("td", "", "Agora");
  const nameStrong = createElement("strong", "", name);
  const addressSpan = createElement("span", "location-address", address);
  const badge = createElement(
    "span",
    `status-badge ${status === "Ativo" ? "status-active" : "status-inactive"}`,
    status,
  );

  row.dataset.status = normalizeText(status);
  row.dataset.search = normalizeText(`${name} ${address}`);
  nameCell.dataset.label = "Local";
  statusCell.dataset.label = "Status";
  createdCell.dataset.label = "Criado em";

  nameCell.append(nameStrong, addressSpan);
  statusCell.append(badge);
  row.append(nameCell, statusCell, createdCell);
  tbody.prepend(row);
}

function updateMetricsAfterCreate(local) {
  incrementMetric("totalLocationsMetric");

  if (String(local?.status || "") === "Inativo") {
    incrementMetric("inactiveLocationsMetric");
    return;
  }

  incrementMetric("activeLocationsMetric");
}

function incrementMetric(id) {
  const element = document.getElementById(id);
  const value = Number(element?.textContent || 0);

  if (element) {
    element.textContent = String(Number.isFinite(value) ? value + 1 : 1);
  }
}

function updateResultCount(count) {
  const resultCount = document.getElementById("locationResultCount");

  if (!resultCount) return;

  resultCount.textContent = `${count.toLocaleString("pt-BR")} ${count === 1 ? "registro" : "registros"}`;
}

function updateEmptyState(show) {
  const emptyState = document.getElementById("locationEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function createElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}
