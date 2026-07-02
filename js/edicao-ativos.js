const MESSAGE_HIDE_DELAY_MS = 2800;
const PAGE_MESSAGE_STORAGE_KEY = "titech-edicao-ativos-message";

let assetSearchTimer = null;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetFilters();
  setupAssetActions();
  setupEditModal();
  restorePendingPageMessage();
}

function setupAssetFilters() {
  const form = document.getElementById("assetFiltersForm");
  const searchInput = document.getElementById("assetSearch");
  const searchValue = document.getElementById("assetSearchValue");

  if (!form) {
    return;
  }

  if (searchInput && searchValue) {
    searchInput.value = searchValue.value || "";
  }

  searchInput?.addEventListener("input", () => {
    window.clearTimeout(assetSearchTimer);

    assetSearchTimer = window.setTimeout(() => {
      syncSearchValue();
      resetAssetPageAndSubmit(form);
    }, 450);
  });

  [
    "assetStatusFilter",
    "assetCategoryFilter",
    "assetBrandFilter",
    "assetPerPage",
  ].forEach((fieldId) => {
    document.getElementById(fieldId)?.addEventListener("change", () => {
      syncSearchValue();
      resetAssetPageAndSubmit(form);
    });
  });

  form.addEventListener("submit", syncSearchValue);

  document.getElementById("clearAssetFilters")?.addEventListener("click", () => {
    window.location.href = "edicao-ativos.php";
  });
}

function syncSearchValue() {
  const searchInput = document.getElementById("assetSearch");
  const searchValue = document.getElementById("assetSearchValue");

  if (searchInput && searchValue) {
    searchValue.value = searchInput.value;
  }
}

function resetAssetPageAndSubmit(form) {
  const pageInput = form.querySelector('input[name="pagina"]');

  if (pageInput) {
    pageInput.value = "1";
  }

  if (typeof form.requestSubmit === "function") {
    form.requestSubmit();
    return;
  }

  form.submit();
}

function setupAssetActions() {
  document.getElementById("assetTableBody")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-asset-action]");

    if (!button) return;

    const row = button.closest(".asset-row");

    if (!row) return;

    if (button.dataset.assetAction === "edit") {
      openEditModal(row);
      return;
    }

    if (button.dataset.assetAction === "delete") {
      deleteAsset(row, button);
    }
  });
}

function setupEditModal() {
  const form = document.getElementById("assetEditForm");
  const modal = document.getElementById("assetEditModal");

  form?.addEventListener("submit", submitEditForm);

  document.querySelectorAll("[data-close-asset-modal]").forEach((button) => {
    button.addEventListener("click", closeEditModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeEditModal();
    }
  });
}

function openEditModal(row) {
  const modal = document.getElementById("assetEditModal");

  if (!modal) return;

  setInputValue("editAssetId", row.dataset.id || "");
  setInputValue("editAssetName", row.dataset.name || "");
  setInputValue("editAssetCategory", row.dataset.categoryId || "");
  setSelectValue("editAssetStatus", row.dataset.statusRaw || "");
  setSelectValue("editAssetBrand", row.dataset.brandRaw || "");
  setInputValue("editAssetLocation", row.dataset.locationId || "");
  setInputValue("editAssetSerial", row.dataset.serial || "");
  setInputValue("editAssetProperty", row.dataset.property || "");
  setInputValue("editAssetImei", row.dataset.imei || "");
  setInputValue("editAssetDatasheet", row.dataset.datasheet || "");
  setInputValue("editAssetDescription", row.dataset.description || "");

  clearEditMessage();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editAssetName")?.focus();
}

function closeEditModal() {
  const modal = document.getElementById("assetEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

async function submitEditForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveAssetButton");
  const error = validateAssetForm(form);

  if (error) {
    setEditMessage(error, "error");
    return;
  }

  const confirmed = await confirmAssetEdition(form);

  if (!confirmed) {
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
      throw new Error(result.message || "Nao foi possivel alterar o ativo.");
    }

    reloadAssetPageWithMessage(result.message || "Ativo alterado com sucesso.", "success");
  } catch (error) {
    setEditMessage(error.message || "Nao foi possivel alterar o ativo.", "error");
  } finally {
    setLoading(submitButton, false);
  }
}

async function confirmAssetEdition(form) {
  const data = new FormData(form);
  const assetName = String(data.get("nome") || "este ativo").trim() || "este ativo";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Confirmar edicao?",
      text: `Confirme para salvar as alteracoes de ${assetName}.`,
      confirmButtonText: "Salvar edicao",
      cancelButtonText: "Continuar editando",
      icon: "warning",
    });
  }

  return window.confirm(`Salvar as alteracoes de ${assetName}?`);
}

function validateAssetForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const categoria = String(data.get("categoria_id") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !categoria || !status) {
    return "Preencha nome, categoria e status do ativo.";
  }

  if (nome.length < 2) {
    return "O nome do ativo precisa ter pelo menos 2 caracteres.";
  }

  return "";
}

async function deleteAsset(row, button) {
  const name = row.dataset.name || "este ativo";
  const confirmed = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${name}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir ativo",
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
    const response = await fetch("Backend/excluir-ativo.php", {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel excluir o ativo.");
    }

    reloadAssetPageWithMessage(result.message || "Ativo excluido com sucesso.", "success");
  } catch (error) {
    setPageMessage(error.message || "Nao foi possivel excluir o ativo.", "error");
  } finally {
    setLoading(button, false);
  }
}

function reloadAssetPageWithMessage(message, type) {
  try {
    sessionStorage.setItem(PAGE_MESSAGE_STORAGE_KEY, JSON.stringify({ message, type }));
  } catch {
    return window.location.reload();
  }

  window.location.reload();
}

function restorePendingPageMessage() {
  let payload = null;

  try {
    payload = JSON.parse(sessionStorage.getItem(PAGE_MESSAGE_STORAGE_KEY) || "null");
    sessionStorage.removeItem(PAGE_MESSAGE_STORAGE_KEY);
  } catch {
    payload = null;
  }

  if (payload?.message) {
    setPageMessage(payload.message, payload.type || "success");
  }
}

function setPageMessage(message, type) {
  const element = document.getElementById("assetPageMessage");

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
  const element = document.getElementById("assetEditMessage");

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

function setSelectValue(id, value) {
  const select = document.getElementById(id);

  if (!select) return;

  const normalizedValue = normalizeText(value);
  const option = [...select.options].find((item) => normalizeText(item.value) === normalizedValue);

  select.value = option ? option.value : "";
}

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}
