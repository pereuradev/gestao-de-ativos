document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetFilters();
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

function updateEmptyState(show) {
  const emptyState = document.getElementById("assetEmptyState");

  if (emptyState) {
    emptyState.hidden = !show;
  }
}

