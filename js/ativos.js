document.addEventListener("DOMContentLoaded", initPage);

let assetSearchTimer = null;

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetFilters();
}

function setupAssetFilters() {
  const form = document.getElementById("assetFiltersForm");

  if (!form) {
    return;
  }

  document.getElementById("assetSearch")?.addEventListener("input", () => {
    window.clearTimeout(assetSearchTimer);

    assetSearchTimer = window.setTimeout(() => {
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
      resetAssetPageAndSubmit(form);
    });
  });

  document
    .getElementById("clearAssetFilters")
    ?.addEventListener("click", () => {
      window.location.href = "ativos.php";
    });
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
