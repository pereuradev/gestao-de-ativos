const MESSAGE_HIDE_DELAY_MS = 2700;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupBrandForm();
  setupBrandFilters();
}

function setupBrandForm() {
  const form = document.getElementById("brandForm");

  if (!form) return;

  form.addEventListener("submit", submitBrandForm);
  form.addEventListener("reset", () => {
    setTimeout(() => setBrandMessage("", ""), 0);
  });
}

async function submitBrandForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("brandSubmitButton");
  const error = validateBrandForm(form);

  if (error) {
    setBrandMessage(error, "error");
    return;
  }

  setButtonLoading(submitButton, true);
  setBrandMessage("", "");

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
      throw new Error(result.message || "Nao foi possivel cadastrar a marca.");
    }

    setBrandMessage(result.message || "Marca cadastrada com sucesso.", "success");
    prependBrandRow(result.marca);
    updateMetricsAfterCreate(result.marca);
    form.reset();
    filterBrands();

    setTimeout(() => {
      setBrandMessage("", "");
    }, MESSAGE_HIDE_DELAY_MS);
  } catch (error) {
    setBrandMessage(error.message || "Nao foi possivel cadastrar a marca.", "error");
  } finally {
    setButtonLoading(submitButton, false);
  }
}

function validateBrandForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status para cadastrar a marca.";
  }

  if (nome.length < 2) {
    return "O nome da marca precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da marca deve ter no maximo 80 caracteres.";
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
    createElement("span", "", "Cadastrar marca"),
  );
}

function setBrandMessage(message, type) {
  const element = document.getElementById("brandFormMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function setupBrandFilters() {
  document.getElementById("brandSearch")?.addEventListener("input", filterBrands);
  document.getElementById("brandStatusFilter")?.addEventListener("change", filterBrands);

  filterBrands();
}

function filterBrands() {
  const rows = Array.from(document.querySelectorAll(".brand-row"));
  const search = normalizeText(document.getElementById("brandSearch")?.value || "");
  const status = normalizeText(document.getElementById("brandStatusFilter")?.value || "todos");
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

function prependBrandRow(brand) {
  const tbody = document.getElementById("brandTableBody");

  if (!tbody || !brand) return;

  document.getElementById("brandEmptyState")?.setAttribute("hidden", "");

  const name = String(brand.nome || "Nova marca");
  const status = String(brand.status || "Ativa");
  const row = createElement("tr", "registration-row brand-row");
  const nameCell = createElement("td");
  const statusCell = createElement("td");
  const createdCell = createElement("td", "", "Agora");
  const updatedCell = createElement("td", "", "Agora");
  const nameStrong = createElement("strong", "", name);
  const badge = createElement(
    "span",
    `status-badge ${status === "Ativa" ? "status-active" : "status-inactive"}`,
    status,
  );

  row.dataset.status = normalizeText(status);
  row.dataset.search = normalizeText(name);
  nameCell.dataset.label = "Marca";
  statusCell.dataset.label = "Status";
  createdCell.dataset.label = "Criada em";
  updatedCell.dataset.label = "Atualizada em";

  nameCell.append(nameStrong);
  statusCell.append(badge);
  row.append(nameCell, statusCell, createdCell, updatedCell);
  tbody.prepend(row);
}

function updateMetricsAfterCreate(brand) {
  incrementMetric("totalBrandsMetric");

  if (String(brand?.status || "") === "Inativa") {
    incrementMetric("inactiveBrandsMetric");
    return;
  }

  incrementMetric("activeBrandsMetric");
}

function incrementMetric(id) {
  const element = document.getElementById(id);
  const value = Number(element?.textContent || 0);

  if (element) {
    element.textContent = String(Number.isFinite(value) ? value + 1 : 1);
  }
}

function updateResultCount(count) {
  const resultCount = document.getElementById("brandResultCount");

  if (!resultCount) return;

  resultCount.textContent = `${count.toLocaleString("pt-BR")} ${count === 1 ? "registro" : "registros"}`;
}

function updateEmptyState(show) {
  const emptyState = document.getElementById("brandEmptyState");

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
