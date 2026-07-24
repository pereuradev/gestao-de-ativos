// Controla busca, edicao e exclusao de categorias cadastradas.
// As acoes dependem do token CSRF renderizado pela pagina e da resposta JSON do backend.

const CATEGORY_MESSAGE_HIDE_DELAY_MS = 2800;

document.addEventListener("DOMContentLoaded", initCategoryEditPage);

function initCategoryEditPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupCategoryFilters();
  setupCategoryActions();
  setupCategoryEditModal();
}

function setupCategoryFilters() {
  document.getElementById("categorySearch")?.addEventListener("input", filterCategories);

  filterCategories();
}

function setupCategoryActions() {
  document.getElementById("categoryTableBody")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-category-action]");

    if (!button) return;

    const row = button.closest(".category-row");

    if (!row) return;

    if (button.dataset.categoryAction === "edit") {
      openCategoryEditModal(row);
      return;
    }

    if (button.dataset.categoryAction === "delete") {
      deleteCategory(row, button);
    }
  });
}

function setupCategoryEditModal() {
  const form = document.getElementById("categoryEditForm");
  const modal = document.getElementById("categoryEditModal");

  form?.addEventListener("submit", submitCategoryEditForm);

  document.querySelectorAll("[data-close-edit-modal]").forEach((button) => {
    button.addEventListener("click", closeCategoryEditModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeCategoryEditModal();
    }
  });
}

function openCategoryEditModal(row) {
  const modal = document.getElementById("categoryEditModal");
  const idInput = document.getElementById("editCategoryId");
  const nameInput = document.getElementById("editCategoryName");
  const descriptionInput = document.getElementById("editCategoryDescription");

  if (!modal || !idInput || !nameInput || !descriptionInput) return;

  idInput.value = row.dataset.id || "";
  nameInput.value = row.dataset.name || "";
  descriptionInput.value = row.dataset.description || "";
  clearCategoryEditMessage();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  nameInput.focus();
}

function closeCategoryEditModal() {
  const modal = document.getElementById("categoryEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

async function submitCategoryEditForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveCategoryButton");
  const error = validateCategoryForm(form);

  if (error) {
    setCategoryEditMessage(error, "error");
    return;
  }

  setCategoryLoading(submitButton, true, "Salvando...");
  clearCategoryEditMessage();

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
      throw new Error(result.message || "Nao foi possivel alterar a categoria.");
    }

    updateCategoryRow(result.categoria);
    closeCategoryEditModal();
    setCategoryPageMessage(result.message || "Categoria alterada com sucesso.", "success");
    filterCategories();
  } catch (error) {
    setCategoryEditMessage(error.message || "Nao foi possivel alterar a categoria.", "error");
  } finally {
    setCategoryLoading(submitButton, false);
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

async function deleteCategory(row, button) {
  const name = row.dataset.name || "esta categoria";
  const linkedAssets = Number(row.dataset.assets || 0);
  const text = linkedAssets > 0
    ? "Esta categoria possui ativos vinculados e o banco deve bloquear a exclusao."
    : "Esta acao nao pode ser desfeita.";
  const confirmed = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${name}?`,
      text,
      confirmButtonText: "Excluir categoria",
      icon: "warning",
    })
    : window.confirm(`Excluir ${name}? ${text}`);

  if (!confirmed) return;

  const body = new FormData();
  body.append("csrf_token", getCategoryCsrfToken());
  body.append("id", row.dataset.id || "");

  setCategoryLoading(button, true, "Excluindo...");
  clearCategoryPageMessage();

  try {
    const response = await fetch("../Backend/excluir-categoria.php", {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel excluir a categoria.");
    }

    row.remove();
    setCategoryPageMessage(result.message || "Categoria excluida com sucesso.", "success");
    filterCategories();
  } catch (error) {
    setCategoryPageMessage(error.message || "Nao foi possivel excluir a categoria.", "error");
  } finally {
    setCategoryLoading(button, false);
  }
}

function updateCategoryRow(category) {
  if (!category?.id) return;

  const row = document.querySelector(`.category-row[data-id="${cssEscape(String(category.id))}"]`);

  if (!row) return;

  const name = String(category.nome || "");
  const description = String(category.descricao || "");
  const nameCell = row.querySelector("[data-category-name]");
  const descriptionCell = row.querySelector("[data-category-description]");
  const updatedCell = row.querySelector("[data-category-updated]");

  row.dataset.name = name;
  row.dataset.description = description;
  row.dataset.search = normalizeText(`${name} ${description}`);

  if (nameCell) {
    nameCell.textContent = name;
  }

  if (descriptionCell) {
    descriptionCell.textContent = description || "Sem descricao";
  }

  if (updatedCell) {
    updatedCell.textContent = formatCategoryDate(category.atualizado_em) || "Agora";
  }
}

function filterCategories() {
  const rows = Array.from(document.querySelectorAll(".category-row"));
  const search = normalizeText(document.getElementById("categorySearch")?.value || "");
  let visibleCount = 0;
  let linkedCount = 0;
  let unlinkedCount = 0;

  rows.forEach((row) => {
    const rowSearch = normalizeText(row.dataset.search || "");
    const assets = Number(row.dataset.assets || 0);
    const isVisible = !search || rowSearch.includes(search);

    if (assets > 0) {
      linkedCount += 1;
    } else {
      unlinkedCount += 1;
    }

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateText("categoryResultCount", `${visibleCount.toLocaleString("pt-BR")} ${visibleCount === 1 ? "registro" : "registros"}`);
  updateText("totalCategoriesMetric", String(rows.length));
  updateText("linkedCategoriesMetric", String(linkedCount));
  updateText("unlinkedCategoriesMetric", String(unlinkedCount));
  updateCategoryEmptyState(rows.length === 0 || visibleCount === 0);
}

function updateCategoryEmptyState(show) {
  const emptyState = document.getElementById("categoryEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setCategoryPageMessage(message, type) {
  const element = document.getElementById("categoryPageMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");

  if (message && type === "success") {
    setTimeout(clearCategoryPageMessage, CATEGORY_MESSAGE_HIDE_DELAY_MS);
  }
}

function clearCategoryPageMessage() {
  setCategoryPageMessage("", "");
}

function setCategoryEditMessage(message, type) {
  const element = document.getElementById("categoryEditMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function clearCategoryEditMessage() {
  setCategoryEditMessage("", "");
}

function setCategoryLoading(button, isLoading, loadingText = "Aguarde...") {
  if (!button) return;

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

function getCategoryCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

function formatCategoryDate(value) {
  if (!value) return "";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(date);
}

function cssEscape(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(value);
  }

  return value.replace(/["\\]/g, "\\$&");
}
