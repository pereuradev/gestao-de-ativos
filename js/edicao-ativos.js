const MESSAGE_HIDE_DELAY_MS = 2800;

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
}

function setupAssetFilters() {
  document.getElementById("assetSearch")?.addEventListener("input", filterAssets);
  document.getElementById("assetStatusFilter")?.addEventListener("change", filterAssets);
  document.getElementById("assetCategoryFilter")?.addEventListener("change", () => {
    syncCategoryUrl();
    filterAssets();
  });
  document.getElementById("assetBrandFilter")?.addEventListener("change", filterAssets);

  document.getElementById("clearAssetFilters")?.addEventListener("click", () => {
    setInputValue("assetSearch", "");
    setInputValue("assetStatusFilter", "todos");
    setInputValue("assetCategoryFilter", "todos");
    setInputValue("assetBrandFilter", "todos");
    syncCategoryUrl();
    filterAssets();
  });

  filterAssets();
}

function syncCategoryUrl() {
  const category = document.getElementById("assetCategoryFilter")?.value || "todos";
  const url = new URL(window.location.href);

  if (category === "todos") {
    url.searchParams.delete("categoria");
  } else {
    url.searchParams.set("categoria", category);
  }

  window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
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

    updateAssetRow(result.ativo);
    closeEditModal();
    setPageMessage(result.message || "Ativo alterado com sucesso.", "success");
    filterAssets();
  } catch (error) {
    setEditMessage(error.message || "Nao foi possivel alterar o ativo.", "error");
  } finally {
    setLoading(submitButton, false);
  }
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

    row.remove();
    adjustTotalAssetsMetric(-1);
    setPageMessage(result.message || "Ativo excluido com sucesso.", "success");
    filterAssets();
  } catch (error) {
    setPageMessage(error.message || "Nao foi possivel excluir o ativo.", "error");
  } finally {
    setLoading(button, false);
  }
}

function updateAssetRow(asset) {
  if (!asset?.id) return;

  const row = document.querySelector(`.asset-row[data-id="${cssEscape(String(asset.id))}"]`);

  if (!row) return;

  const name = String(asset.nome || "");
  const description = String(asset.descricao || "");
  const serial = String(asset.numero_serie || "");
  const status = String(asset.status || "");
  const brand = String(asset.marca || "");
  const property = String(asset.propriedade || "");
  const imei = String(asset.imei || "");
  const datasheet = String(asset.datasheet || "");
  const category = String(asset.categoria || "Sem categoria");
  const categoryId = String(asset.categoria_id || "");
  const location = String(asset.local || "");
  const locationId = String(asset.local_id || "");
  const created = formatDate(asset.criado_em) || row.dataset.created || "--";

  row.dataset.name = name;
  row.dataset.description = description;
  row.dataset.serial = serial;
  row.dataset.status = normalizeText(status);
  row.dataset.statusRaw = status;
  row.dataset.brand = normalizeText(brand);
  row.dataset.brandRaw = brand;
  row.dataset.property = property;
  row.dataset.imei = imei;
  row.dataset.datasheet = datasheet;
  row.dataset.category = normalizeText(category);
  row.dataset.categoryRaw = category;
  row.dataset.categoryId = categoryId;
  row.dataset.location = normalizeText(location);
  row.dataset.locationRaw = location;
  row.dataset.locationId = locationId;
  row.dataset.created = created;
  row.dataset.search = normalizeText(`${name} ${serial} ${status} ${brand} ${property} ${category} ${location}`);

  setText(row.querySelector("[data-asset-name]"), name || "--");
  setText(row.querySelector("[data-asset-property]"), property);
  setText(row.querySelector("[data-asset-category]"), category || "Sem categoria");
  setText(row.querySelector("[data-asset-brand]"), brand || "--");
  setText(row.querySelector("[data-asset-serial]"), serial || "--");
  const statusBadge = row.querySelector("[data-asset-status]");
  setText(statusBadge, status || "--");
  setStatusBadgeClass(statusBadge, status);
  setText(row.querySelector("[data-asset-location]"), location || "--");
  setText(row.querySelector("[data-asset-created]"), created);
}

function filterAssets() {
  const rows = Array.from(document.querySelectorAll(".asset-row"));
  const search = normalizeText(document.getElementById("assetSearch")?.value || "");
  const status = normalizeText(document.getElementById("assetStatusFilter")?.value || "todos");
  const category = normalizeText(document.getElementById("assetCategoryFilter")?.value || "todos");
  const brand = normalizeText(document.getElementById("assetBrandFilter")?.value || "todos");
  let visibleCount = 0;

  rows.forEach((row) => {
    const rowStatus = normalizeText(row.dataset.statusRaw || row.dataset.status || "");
    const rowCategory = normalizeText(row.dataset.categoryRaw || row.dataset.category || "");
    const rowBrand = normalizeText(row.dataset.brandRaw || row.dataset.brand || "");
    const rowSearch = normalizeText(row.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesCategory = category === "todos" || rowCategory === category;
    const matchesBrand = brand === "todos" || rowBrand === brand;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesCategory && matchesBrand && matchesSearch;

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateText("assetResultCount", `${visibleCount.toLocaleString("pt-BR")} ${visibleCount === 1 ? "registro" : "registros"}`);
  updateText("displayedAssetsMetric", String(visibleCount));
  updateEmptyState(rows.length === 0 || visibleCount === 0);
}

function adjustTotalAssetsMetric(delta) {
  const metric = document.getElementById("totalAssetsMetric");

  if (!metric) return;

  const current = Number(metric.dataset.totalAssets || metric.textContent || "0");

  if (!Number.isFinite(current)) return;

  const next = Math.max(0, current + delta);

  metric.dataset.totalAssets = String(next);
  metric.textContent = next.toLocaleString("pt-BR");
}

function updateEmptyState(show) {
  const emptyState = document.getElementById("assetEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setStatusBadgeClass(element, status) {
  if (!element) return;

  const normalized = normalizeText(status);
  let modifier = "status-neutral";

  if (normalized.startsWith("dispon")) {
    modifier = "status-available";
  } else if (normalized === "em uso") {
    modifier = "status-in-use";
  } else if (normalized.startsWith("homologa")) {
    modifier = "status-homologation";
  } else if (normalized.startsWith("manuten")) {
    modifier = "status-maintenance";
  }

  element.className = `status-badge ${modifier}`;
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
