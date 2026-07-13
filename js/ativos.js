document.addEventListener("DOMContentLoaded", initPage);

let assetSearchTimer = null;
let assetExporting = false;

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetFilters();
  setupAssetExports();
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
    "assetLocationFilter",
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

function setupAssetExports() {
  document.querySelectorAll("[data-asset-export]").forEach((button) => {
    button.addEventListener("click", () => exportAssetsFile(button));
  });
}

async function exportAssetsFile(button) {
  if (assetExporting) {
    return;
  }

  const exportUrl = button.dataset.exportUrl;
  const config = getAssetExportConfig(button.dataset.exportFormat);

  if (!exportUrl || !config) {
    notifyAssetExport("O endereco de exportacao nao esta disponivel.", true);
    return;
  }

  assetExporting = true;
  setAssetExportButtonsLoading(button, true);
  clearAssetExportStatus();

  try {
    const response = await fetch(exportUrl, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: `${config.contentType}, application/json` },
    });
    const contentType = response.headers.get("content-type") || "";

    if (!response.ok || !contentType.includes(config.contentType)) {
      throw new Error(await readAssetExportError(response, config.label));
    }

    const fileBlob = await response.blob();

    if (!fileBlob.size) {
      throw new Error(`O servidor retornou um ${config.label} vazio. Tente novamente.`);
    }

    downloadAssetFile(fileBlob, getAssetExportFilename(response, config.fallbackFilename));
    notifyAssetExport(`${config.label} gerado com sucesso.`, false);
  } catch (error) {
    const message = error instanceof TypeError
      ? `Servidor indisponivel. Nao foi possivel gerar o ${config.label} agora.`
      : error?.message || `Nao foi possivel gerar o ${config.label} agora.`;

    notifyAssetExport(message, true);
  } finally {
    assetExporting = false;
    setAssetExportButtonsLoading(button, false);
  }
}

function getAssetExportConfig(format) {
  if (format === "csv") {
    return {
      contentType: "text/csv",
      fallbackFilename: "ativos-titech.csv",
      label: "CSV",
    };
  }

  if (format === "pdf") {
    return {
      contentType: "application/pdf",
      fallbackFilename: "relatorio-ativos.pdf",
      label: "PDF",
    };
  }

  return null;
}

async function readAssetExportError(response, formatLabel) {
  const contentType = response.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    const payload = await response.json().catch(() => null);

    if (payload?.message) {
      return payload.message;
    }
  }

  if (response.status === 401) {
    return `Sua sessao expirou. Entre novamente antes de exportar o ${formatLabel}.`;
  }

  if (response.status === 403) {
    return "Voce nao tem permissao para exportar este relatorio.";
  }

  return `Nao foi possivel gerar o ${formatLabel}. Atualize a pagina e tente novamente.`;
}

function getAssetExportFilename(response, fallbackFilename) {
  const disposition = response.headers.get("content-disposition") || "";
  const encodedMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);

  if (encodedMatch?.[1]) {
    return decodeURIComponent(encodedMatch[1]).replace(/[\\/:*?"<>|]/g, "-");
  }

  const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);

  return filenameMatch?.[1]?.replace(/[\\/:*?"<>|]/g, "-") || fallbackFilename;
}

function downloadAssetFile(blob, filename) {
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = objectUrl;
  link.download = filename;
  link.hidden = true;
  document.body.append(link);
  link.click();
  link.remove();

  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
}

function setAssetExportButtonsLoading(activeButton, isLoading) {
  document.querySelectorAll("[data-asset-export]").forEach((button) => {
    const icon = button.querySelector("i");
    const label = button.querySelector("span");
    const isActive = button === activeButton;

    button.disabled = isLoading;
    button.setAttribute("aria-busy", isLoading && isActive ? "true" : "false");

    if (icon) {
      icon.className = isLoading && isActive
        ? "bi bi-arrow-repeat asset-export-spinner"
        : button.dataset.defaultIcon || "bi bi-download";
    }

    if (label) {
      label.textContent = isLoading && isActive
        ? `Gerando ${getAssetExportConfig(button.dataset.exportFormat)?.label || "arquivo"}...`
        : button.dataset.defaultLabel || "Exportar";
    }
  });
}

function clearAssetExportStatus() {
  const status = document.getElementById("assetExportStatus");

  if (!status) {
    return;
  }

  status.hidden = true;
  status.classList.remove("is-error", "is-success");
  status.textContent = "";
}

function notifyAssetExport(message, isError) {
  const status = document.getElementById("assetExportStatus");
  const type = isError ? "error" : "success";

  if (status) {
    status.hidden = false;
    status.classList.toggle("is-error", isError);
    status.classList.toggle("is-success", !isError);
    status.textContent = message;
  }

  window.titechToast?.(message, type);
}
