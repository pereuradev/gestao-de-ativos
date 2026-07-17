const MESSAGE_HIDE_DELAY_MS = 2800;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupBrandFilters();
  setupBrandActions();
  setupEditModal();
}

function setupBrandFilters() {
  document.getElementById("brandSearch")?.addEventListener("input", filterBrands);
  document.getElementById("brandStatusFilter")?.addEventListener("change", filterBrands);

  filterBrands();
}

function setupBrandActions() {
  document.getElementById("brandTableBody")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-brand-action]");

    if (!button) return;

    const row = button.closest(".brand-row");

    if (!row) return;

    if (button.dataset.brandAction === "edit") {
      openEditModal(row);
      return;
    }

    if (button.dataset.brandAction === "delete") {
      deleteBrand(row, button);
    }
  });
}

function setupEditModal() {
  const form = document.getElementById("brandEditForm");
  const modal = document.getElementById("brandEditModal");

  form?.addEventListener("submit", submitEditForm);

  document.querySelectorAll("[data-close-edit-modal]").forEach((button) => {
    button.addEventListener("click", closeEditModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeEditModal();
    }
  });
}

function openEditModal(row) {
  const modal = document.getElementById("brandEditModal");
  const idInput = document.getElementById("editBrandId");
  const nameInput = document.getElementById("editBrandName");
  const statusInput = document.getElementById("editBrandStatus");

  if (!modal || !idInput || !nameInput || !statusInput) return;

  idInput.value = row.dataset.id || "";
  nameInput.value = row.dataset.name || "";
  statusInput.value = row.dataset.statusRaw || "Ativa";
  clearEditMessage();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  nameInput.focus();
}

function closeEditModal() {
  const modal = document.getElementById("brandEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

async function submitEditForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveBrandButton");
  const error = validateBrandForm(form);

  if (error) {
    setEditMessage(error, "error");
    return;
  }

  setLoading(submitButton, true, "Salvando...");
  clearEditMessage();

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
      throw new Error(result.message || "Nao foi possivel alterar a marca.");
    }

    updateBrandRow(result.marca);
    closeEditModal();
    setPageMessage(result.message || "Marca alterada com sucesso.", "success");
    filterBrands();
  } catch (error) {
    setEditMessage(error.message || "Nao foi possivel alterar a marca.", "error");
  } finally {
    setLoading(submitButton, false);
  }
}

function validateBrandForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status da marca.";
  }

  if (nome.length < 2) {
    return "O nome da marca precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da marca deve ter no maximo 80 caracteres.";
  }

  return "";
}

async function deleteBrand(row, button) {
  const name = row.dataset.name || "esta marca";
  const confirmed = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${name}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir marca",
      icon: "warning",
    })
    : window.confirm(`Excluir ${name}? Esta acao nao pode ser desfeita.`);

  if (!confirmed) return;

  const body = new FormData();
  body.append("csrf_token", getCsrfToken());
  body.append("id", row.dataset.id || "");

  setLoading(button, true, "Excluindo...");
  clearPageMessage();

  try {
    const response = await fetch("../Backend/excluir-marca.php", {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel excluir a marca.");
    }

    row.remove();
    setPageMessage(result.message || "Marca excluida com sucesso.", "success");
    filterBrands();
  } catch (error) {
    setPageMessage(error.message || "Nao foi possivel excluir a marca.", "error");
  } finally {
    setLoading(button, false);
  }
}

function updateBrandRow(brand) {
  if (!brand?.id) return;

  const row = document.querySelector(`.brand-row[data-id="${cssEscape(String(brand.id))}"]`);

  if (!row) return;

  const name = String(brand.nome || "");
  const status = String(brand.status || "Ativa");
  const normalizedStatus = normalizeText(status);
  const nameCell = row.querySelector("[data-brand-name]");
  const statusCell = row.querySelector("[data-brand-status]");
  const updatedCell = row.querySelector("[data-brand-updated]");

  row.dataset.name = name;
  row.dataset.status = normalizedStatus;
  row.dataset.statusRaw = status;
  row.dataset.search = normalizeText(name);

  if (nameCell) {
    nameCell.textContent = name;
  }

  if (statusCell) {
    statusCell.className = `status-badge ${status === "Ativa" ? "status-active" : "status-inactive"}`;
    statusCell.textContent = status;
  }

  if (updatedCell) {
    updatedCell.textContent = formatDate(brand.atualizado_em) || "Agora";
  }
}

function filterBrands() {
  const rows = Array.from(document.querySelectorAll(".brand-row"));
  const search = normalizeText(document.getElementById("brandSearch")?.value || "");
  const status = normalizeText(document.getElementById("brandStatusFilter")?.value || "todos");
  let visibleCount = 0;
  let activeCount = 0;
  let inactiveCount = 0;

  rows.forEach((row) => {
    const rowStatus = normalizeText(row.dataset.status || "");
    const rowSearch = normalizeText(row.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesSearch;

    if (rowStatus === "ativa") {
      activeCount += 1;
    } else if (rowStatus === "inativa") {
      inactiveCount += 1;
    }

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateText("brandResultCount", `${visibleCount.toLocaleString("pt-BR")} ${visibleCount === 1 ? "registro" : "registros"}`);
  updateText("totalBrandsMetric", String(rows.length));
  updateText("activeBrandsMetric", String(activeCount));
  updateText("inactiveBrandsMetric", String(inactiveCount));
  updateEmptyState(rows.length === 0 || visibleCount === 0);
}

function updateEmptyState(show) {
  const emptyState = document.getElementById("brandEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setPageMessage(message, type) {
  const element = document.getElementById("brandPageMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");

  if (message && type === "success") {
    setTimeout(clearPageMessage, MESSAGE_HIDE_DELAY_MS);
  }
}

function clearPageMessage() {
  setPageMessage("", "");
}

function setEditMessage(message, type) {
  const element = document.getElementById("brandEditMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function clearEditMessage() {
  setEditMessage("", "");
}

function setLoading(button, isLoading, loadingText = "Aguarde...") {
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

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

function formatDate(value) {
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
