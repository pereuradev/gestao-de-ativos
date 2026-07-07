document.addEventListener("DOMContentLoaded", initPage);

let assetSearchTimer = null;
let assetImportSubmitting = false;
let assetImportLastTrigger = null;

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetFilters();
  setupAssetImportExport();
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

function setupAssetImportExport() {
  const openButton = document.getElementById("openAssetImportModal");
  const modal = document.getElementById("assetImportModal");
  const form = document.getElementById("assetImportForm");

  if (!modal || !form) {
    return;
  }

  openButton?.addEventListener("click", () => openAssetImportModal(openButton));

  modal.querySelectorAll("[data-close-asset-import]").forEach((button) => {
    button.addEventListener("click", closeAssetImportModal);
  });

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeAssetImportModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeAssetImportModal();
    }
  });

  form.addEventListener("submit", submitAssetImport);
}

function openAssetImportModal(trigger) {
  const modal = document.getElementById("assetImportModal");
  const fileInput = document.getElementById("assetImportFile");

  if (!modal) {
    return;
  }

  assetImportLastTrigger = trigger || document.activeElement;
  clearAssetImportResult();
  modal.hidden = false;
  document.body.classList.add("modal-open");

  requestAnimationFrame(() => fileInput?.focus({ preventScroll: true }));
}

function closeAssetImportModal() {
  if (assetImportSubmitting) {
    return;
  }

  const modal = document.getElementById("assetImportModal");

  if (!modal) {
    return;
  }

  modal.hidden = true;
  document.body.classList.remove("modal-open");

  if (assetImportLastTrigger?.isConnected) {
    assetImportLastTrigger.focus({ preventScroll: true });
  }
}

async function submitAssetImport(event) {
  event.preventDefault();

  if (assetImportSubmitting) {
    return;
  }

  const form = event.currentTarget;
  const fileInput = document.getElementById("assetImportFile");
  const submitButton = document.getElementById("assetImportSubmit");

  if (!fileInput?.files?.length) {
    setAssetImportResult({
      ok: false,
      message: "Selecione um arquivo CSV para importar.",
      importados: 0,
      ignorados: 0,
      erros: [],
    }, true);
    return;
  }

  assetImportSubmitting = true;
  setAssetImportButtonLoading(submitButton, true);
  clearAssetImportResult();

  try {
    const response = await fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { Accept: "application/json" },
    });
    const result = await response.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
      importados: 0,
      ignorados: 0,
      erros: [],
    }));
    const hasError = !response.ok || !result.ok;

    setAssetImportResult(result, hasError);

    if (hasError) {
      window.titechToast?.(result.message || "Nao foi possivel importar os ativos.", "error");
      return;
    }

    window.titechToast?.(result.message || "Importacao concluida.", "success");
    form.reset();
  } catch (error) {
    const message = error?.message || "Nao foi possivel importar os ativos.";

    setAssetImportResult({
      ok: false,
      message,
      importados: 0,
      ignorados: 0,
      erros: [],
    }, true);
    window.titechToast?.(message, "error");
  } finally {
    assetImportSubmitting = false;
    setAssetImportButtonLoading(submitButton, false);
  }
}

function setAssetImportButtonLoading(button, isLoading) {
  if (!button) {
    return;
  }

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createAssetElement("i", "bi bi-arrow-repeat"),
      createAssetElement("span", "", "Importando..."),
    );
    return;
  }

  button.replaceChildren(
    createAssetElement("i", "bi bi-upload"),
    createAssetElement("span", "", "Importar ativos"),
  );
}

function clearAssetImportResult() {
  const result = document.getElementById("assetImportResult");

  if (!result) {
    return;
  }

  result.hidden = true;
  result.classList.remove("is-error");
  result.replaceChildren();
}

function setAssetImportResult(payload, isError) {
  const result = document.getElementById("assetImportResult");

  if (!result) {
    return;
  }

  const imported = Number(payload?.importados || 0);
  const ignored = Number(payload?.ignorados || 0);
  const errors = Array.isArray(payload?.erros) ? payload.erros : [];
  const title = createAssetElement("strong", "", payload?.message || "Resultado da importacao.");
  const summary = createAssetElement(
    "p",
    "",
    `${imported} ativo(s) importado(s). ${ignored} linha(s) ignorada(s).`,
  );

  result.hidden = false;
  result.classList.toggle("is-error", Boolean(isError));
  result.replaceChildren(title, summary);

  if (!errors.length) {
    return;
  }

  const list = document.createElement("ul");
  const visibleErrors = errors.slice(0, 8);

  visibleErrors.forEach((error) => {
    const line = error?.linha ? `Linha ${error.linha}: ` : "";
    const message = error?.mensagem || "Erro de validacao.";

    list.append(createAssetElement("li", "", `${line}${message}`));
  });

  if (errors.length > visibleErrors.length) {
    list.append(createAssetElement("li", "", `Mais ${errors.length - visibleErrors.length} erro(s) no arquivo.`));
  }

  result.append(list);
}

function createAssetElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}
