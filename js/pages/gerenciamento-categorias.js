// Valida e cadastra categorias, atualizando metricas e filtros no navegador.
// O mesmo modulo tambem atende a pagina de visualizacao, onde o formulario pode nao existir.

const CATEGORY_MESSAGE_HIDE_DELAY_MS = 2700;

document.addEventListener("DOMContentLoaded", initCategoryPage);

function initCategoryPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupCategoryForm();
  setupCategoryFilters();
}

function setupCategoryForm() {
  const form = document.getElementById("categoryForm");

  if (!form) return;

  form.addEventListener("submit", submitCategoryForm);
  form.addEventListener("reset", () => {
    setTimeout(() => setCategoryMessage("", ""), 0);
  });
}

async function submitCategoryForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("categorySubmitButton");
  const error = validateCategoryForm(form);

  if (error) {
    setCategoryMessage(error, "error");
    return;
  }

  setCategoryButtonLoading(submitButton, true);
  setCategoryMessage("", "");

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
      throw new Error(result.message || "Nao foi possivel cadastrar a categoria.");
    }

    setCategoryMessage(result.message || "Categoria cadastrada com sucesso.", "success");
    incrementCategoryMetric("totalCategoriesMetric");
    incrementCategoryMetric("unlinkedCategoriesMetric");
    form.reset();

    setTimeout(() => {
      setCategoryMessage("", "");
    }, CATEGORY_MESSAGE_HIDE_DELAY_MS);
  } catch (error) {
    setCategoryMessage(error.message || "Nao foi possivel cadastrar a categoria.", "error");
  } finally {
    setCategoryButtonLoading(submitButton, false);
  }
}

function validateCategoryForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const descricao = String(data.get("descricao") || "").trim();

  if (!nome) {
    return "Informe o nome da categoria.";
  }

  if (nome.length < 2) {
    return "O nome da categoria precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da categoria deve ter no maximo 80 caracteres.";
  }

  if (descricao.length > 240) {
    return "A descricao da categoria deve ter no maximo 240 caracteres.";
  }

  return "";
}

function setCategoryButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createCategoryElement("i", "bi bi-arrow-repeat"),
      createCategoryElement("span", "", "Cadastrando..."),
    );
    return;
  }

  button.replaceChildren(
    createCategoryElement("i", "bi bi-plus-circle"),
    createCategoryElement("span", "", "Cadastrar categoria"),
  );
}

function setCategoryMessage(message, type) {
  const element = document.getElementById("categoryFormMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function setupCategoryFilters() {
  document.getElementById("categorySearch")?.addEventListener("input", filterCategories);
  document.getElementById("clearCategoryFilters")?.addEventListener("click", clearCategoryFilters);

  filterCategories();
}

function clearCategoryFilters() {
  const search = document.getElementById("categorySearch");

  if (search) {
    search.value = "";
  }

  filterCategories();
  search?.focus();
}

function filterCategories() {
  const rows = Array.from(document.querySelectorAll(".category-row"));
  const search = normalizeText(document.getElementById("categorySearch")?.value || "");
  let visibleCount = 0;

  rows.forEach((row) => {
    const rowSearch = normalizeText(row.dataset.search || "");
    const isVisible = !search || rowSearch.includes(search);

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateCategoryResultCount(visibleCount);
  updateCategoryEmptyState(rows.length === 0 || visibleCount === 0);
}

function incrementCategoryMetric(id) {
  const element = document.getElementById(id);
  const value = Number(element?.textContent || 0);

  if (element) {
    element.textContent = String(Number.isFinite(value) ? value + 1 : 1);
  }
}

function updateCategoryResultCount(count) {
  const resultCount = document.getElementById("categoryResultCount");

  if (!resultCount) return;

  resultCount.textContent = `${count.toLocaleString("pt-BR")} ${count === 1 ? "registro" : "registros"}`;
}

function updateCategoryEmptyState(show) {
  const emptyState = document.getElementById("categoryEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function createCategoryElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}
