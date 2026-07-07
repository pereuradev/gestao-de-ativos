document.addEventListener("DOMContentLoaded", initGroupViewPage);

function initGroupViewPage() {
  callGroupViewGlobal("startPageAnimation");
  callGroupViewGlobal("loadSavedTheme");
  callGroupViewGlobal("setupThemeToggle");
  callGroupViewGlobal("setupSidebar");
  callGroupViewGlobal("setupNavGroups");
  setupGroupViewSearch();
}

function callGroupViewGlobal(functionName) {
  if (typeof window[functionName] === "function") {
    window[functionName]();
  }
}

function setupGroupViewSearch() {
  document.getElementById("groupViewSearch")?.addEventListener("input", filterGroupViewItems);
  filterGroupViewItems();
}

function filterGroupViewItems() {
  const search = normalizeGroupViewText(document.getElementById("groupViewSearch")?.value || "");
  const cards = Array.from(document.querySelectorAll("#groupViewList .group-edit-item"));
  let visible = 0;

  cards.forEach((card) => {
    const matches = !search || normalizeGroupViewText(card.dataset.search || "").includes(search);

    card.hidden = !matches;

    if (matches) {
      visible += 1;
    }
  });

  updateGroupViewCount(visible);
  updateGroupViewEmptyState();
}

function updateGroupViewCount(total) {
  const counter = document.getElementById("groupViewResultCount");

  if (!counter) {
    return;
  }

  counter.textContent = `${total.toLocaleString("pt-BR")} ${total === 1 ? "registro" : "registros"}`;
}

function updateGroupViewEmptyState() {
  const empty = document.getElementById("groupViewEmptyState");
  const visibleCards = Array.from(document.querySelectorAll("#groupViewList .group-edit-item"))
    .filter((card) => !card.hidden);

  if (empty) {
    empty.hidden = visibleCards.length > 0;
  }
}

function normalizeGroupViewText(value) {
  if (typeof window.normalizeText === "function") {
    return window.normalizeText(value);
  }

  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}
