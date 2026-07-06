document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupEmployeeFilters();
  setupEmployeeCards();
  setupEmployeeModal();
}

function setupEmployeeFilters() {
  const search = document.getElementById("employeeSearch");
  const statusFilter = document.getElementById("employeeStatusFilter");

  search?.addEventListener("input", filterEmployees);
  statusFilter?.addEventListener("change", filterEmployees);

  filterEmployees();
}

function filterEmployees() {
  const cards = Array.from(document.querySelectorAll(".employee-row"));
  const search = normalizeText(document.getElementById("employeeSearch")?.value || "");
  const status = normalizeText(document.getElementById("employeeStatusFilter")?.value || "todos");
  let visibleCount = 0;

  cards.forEach((card) => {
    const rowStatus = normalizeText(card.dataset.status || "");
    const rowSearch = normalizeText(card.dataset.search || "");
    const matchesStatus = status === "todos" || rowStatus === status;
    const matchesSearch = !search || rowSearch.includes(search);
    const isVisible = matchesStatus && matchesSearch;

    card.hidden = !isVisible;

    if (isVisible) {
      visibleCount += 1;
    }
  });

  updateResultCount(visibleCount);
  updateFilteredEmptyState(cards.length > 0 && visibleCount === 0);
}

function updateResultCount(count) {
  const resultCount = document.getElementById("employeeResultCount");

  if (!resultCount) return;

  resultCount.textContent = `${count.toLocaleString("pt-BR")} ${count === 1 ? "registro" : "registros"}`;
}

function updateFilteredEmptyState(show) {
  const emptyState = document.getElementById("employeeEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

function setupEmployeeCards() {
  document.getElementById("employeeCardList")?.addEventListener("click", (event) => {
    const card = event.target.closest("[data-employee-card]");

    if (card) {
      openEmployeeModal(card);
    }
  });
}

function setupEmployeeModal() {
  const modal = document.getElementById("employeeDetailsModal");

  document.querySelectorAll("[data-close-employee-modal]").forEach((button) => {
    button.addEventListener("click", closeEmployeeModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeEmployeeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal && !modal.hidden) {
      closeEmployeeModal();
    }
  });
}

function openEmployeeModal(card) {
  const modal = document.getElementById("employeeDetailsModal");

  if (!modal) {
    return;
  }

  modal.dataset.lastTriggerId = card.dataset.email || "";
  updateEmployeeText("employeeModalInitials", card.dataset.initials || "TT");
  updateEmployeeText("employeeModalTitle", card.dataset.name || "Funcionario");
  updateEmployeeText("employeeModalEmail", card.dataset.email || "--");
  updateEmployeeText("employeeModalRole", card.dataset.role || "--");
  updateEmployeeText("employeeModalDepartment", card.dataset.department || "--");
  updateEmployeeText("employeeModalCompany", card.dataset.company || "--");
  updateEmployeeText("employeeModalPhone", card.dataset.phone || "--");
  updateEmployeeText("employeeModalRg", card.dataset.rg || "--");
  updateEmployeeText("employeeModalCpf", card.dataset.cpf || "--");
  updateEmployeeText("employeeModalBirth", card.dataset.birth || "--");
  updateEmployeeText("employeeModalCreated", card.dataset.created || "--");
  updateEmployeeText("employeeModalUpdated", card.dataset.updated || "--");
  updateEmployeeStatus(card);

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  modal.querySelector("[data-close-employee-modal]")?.focus();
}

function closeEmployeeModal() {
  const modal = document.getElementById("employeeDetailsModal");

  if (!modal) {
    return;
  }

  modal.hidden = true;

  const triggerEmail = modal.dataset.lastTriggerId || "";
  const trigger = triggerEmail
    ? document.querySelector(`[data-employee-card][data-email="${cssEscapeEmployee(triggerEmail)}"]`)
    : null;

  trigger?.focus();
}

function updateEmployeeStatus(card) {
  const status = document.getElementById("employeeModalStatus");
  const statusLabel = card.dataset.statusLabel || "--";
  const statusClass = normalizeText(statusLabel) === "ativo"
    ? "status-active"
    : normalizeText(statusLabel) === "inativo"
      ? "status-inactive"
      : "status-neutral";

  if (!status) {
    return;
  }

  status.className = `status-badge ${statusClass}`;
  status.textContent = statusLabel;
}

function updateEmployeeText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

function cssEscapeEmployee(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(value || ""));
  }

  return String(value || "").replace(/["\\]/g, "\\$&");
}
