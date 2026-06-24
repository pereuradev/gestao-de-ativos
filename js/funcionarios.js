document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupEmployeeFilters();
}

function setupEmployeeFilters() {
  const search = document.getElementById("employeeSearch");
  const statusFilter = document.getElementById("employeeStatusFilter");

  search?.addEventListener("input", filterEmployees);
  statusFilter?.addEventListener("change", filterEmployees);

  filterEmployees();
}

function filterEmployees() {
  const rows = Array.from(document.querySelectorAll(".employee-row"));
  const search = normalizeText(document.getElementById("employeeSearch")?.value || "");
  const status = normalizeText(document.getElementById("employeeStatusFilter")?.value || "todos");
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
  updateFilteredEmptyState(rows.length > 0 && visibleCount === 0);
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

