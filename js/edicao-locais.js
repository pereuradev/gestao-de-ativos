const MESSAGE_HIDE_DELAY_MS = 2800;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupLocationFilters();
  setupLocationActions();
  setupEditModal();
}

function setupLocationFilters() {
  document.getElementById("locationSearch")?.addEventListener("input", filterLocations);
  document.getElementById("locationStatusFilter")?.addEventListener("change", filterLocations);

  filterLocations();
}

function setupLocationActions() {
  document.getElementById("locationTableBody")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-location-action]");

    if (!button) return;

    const row = button.closest(".location-row");

    if (!row) return;

    if (button.dataset.locationAction === "edit") {
      openEditModal(row);
      return;
    }

    if (button.dataset.locationAction === "delete") {
      deleteLocation(row, button);
    }
  });
}

function setupEditModal() {
  const form = document.getElementById("locationEditForm");
  const modal = document.getElementById("locationEditModal");

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
  const modal = document.getElementById("locationEditModal");
  const idInput = document.getElementById("editLocationId");
  const nameInput = document.getElementById("editLocationName");
  const addressInput = document.getElementById("editLocationAddress");
  const statusInput = document.getElementById("editLocationStatus");

  if (!modal || !idInput || !nameInput || !addressInput || !statusInput) return;

  idInput.value = row.dataset.id || "";
  nameInput.value = row.dataset.name || "";
  addressInput.value = row.dataset.address || "";
  statusInput.value = row.dataset.statusRaw || "Ativo";
  clearEditMessage();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  nameInput.focus();
}

function closeEditModal() {
  const modal = document.getElementById("locationEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

async function submitEditForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveLocationButton");
  const error = validateLocationForm(form);

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
      throw new Error(result.message || "Nao foi possivel alterar o local.");
    }

    updateLocationRow(result.local);
    closeEditModal();
    setPageMessage(result.message || "Local alterado com sucesso.", "success");
    filterLocations();
  } catch (error) {
    setEditMessage(error.message || "Nao foi possivel alterar o local.", "error");
  } finally {
    setLoading(submitButton, false);
  }
}

function validateLocationForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const endereco = String(data.get("endereco") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status do local.";
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

async function deleteLocation(row, button) {
  const name = row.dataset.name || "este local";
  const confirmed = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${name}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir local",
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
    const response = await fetch("../Backend/excluir-local.php", {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Nao foi possivel excluir o local.");
    }

    row.remove();
    setPageMessage(result.message || "Local excluido com sucesso.", "success");
    filterLocations();
  } catch (error) {
    setPageMessage(error.message || "Nao foi possivel excluir o local.", "error");
  } finally {
    setLoading(button, false);
  }
}

function updateLocationRow(local) {
  if (!local?.id) return;

  const row = document.querySelector(`.location-row[data-id="${cssEscape(String(local.id))}"]`);

  if (!row) return;

  const name = String(local.nome || "");
  const address = String(local.endereco || "");
  const status = String(local.status || "Ativo");
  const normalizedStatus = normalizeText(status);
  const nameCell = row.querySelector("[data-location-name]");
  const addressCell = row.querySelector("[data-location-address]");
  const statusCell = row.querySelector("[data-location-status]");
  const updatedCell = row.querySelector("[data-location-updated]");

  row.dataset.name = name;
  row.dataset.address = address;
  row.dataset.status = normalizedStatus;
  row.dataset.statusRaw = status;
  row.dataset.search = normalizeText(`${name} ${address}`);

  if (nameCell) {
    nameCell.textContent = name;
  }

  if (addressCell) {
    addressCell.textContent = address || "Sem referencia informada";
  }

  if (statusCell) {
    statusCell.className = `status-badge ${status === "Ativo" ? "status-active" : "status-inactive"}`;
    statusCell.textContent = status;
  }

  if (updatedCell) {
    updatedCell.textContent = formatDate(local.atualizado_em) || "Agora";
  }
}

function filterLocations() {
  const rows = Array.from(document.querySelectorAll(".location-row"));
  const search = normalizeText(document.getElementById("locationSearch")?.value || "");
  const status = normalizeText(document.getElementById("locationStatusFilter")?.value || "todos");
  let visibleCount = 0;
  let activeCount = 0;
  let inactiveCount = 0;

  rows.forEach((row) => {
    const rowStatus = normalizeText(row.dataset.status || "");
    const rowSearch = normalizeText(row.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesSearch;

    if (rowStatus === "ativo") {
      activeCount += 1;
    } else if (rowStatus === "inativo") {
      inactiveCount += 1;
    }

    row.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateText("locationResultCount", `${visibleCount.toLocaleString("pt-BR")} ${visibleCount === 1 ? "registro" : "registros"}`);
  updateText("totalLocationsMetric", String(rows.length));
  updateText("activeLocationsMetric", String(activeCount));
  updateText("inactiveLocationsMetric", String(inactiveCount));
  updateEmptyState(rows.length === 0 || visibleCount === 0);
}

function updateEmptyState(show) {
  const emptyState = document.getElementById("locationEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setPageMessage(message, type) {
  const element = document.getElementById("locationPageMessage");

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
  const element = document.getElementById("locationEditMessage");

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
