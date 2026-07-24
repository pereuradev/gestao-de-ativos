// Valida e envia o cadastro de ativos, atualizando a lista recente sem recarregar a página.
// Usa os diálogos e avisos globais fornecidos pelos módulos compartilhados da interface.

const REDIRECT_DELAY_MS = 900;
// Cada modo informa quais campos de identificacao devem ficar disponiveis no formulario.
const TRACEABILITY_CONFIG = {
  nao_possui: { pn: false, sn: false },
  somente_pn: { pn: true, sn: false },
  somente_sn: { pn: false, sn: true },
  ambos: { pn: true, sn: true },
};

document.addEventListener("DOMContentLoaded", initPage);

let preserveMessageOnNextReset = false;

function initPage() {
  runPageHelper("startPageAnimation");
  runPageHelper("loadSavedTheme");
  runPageHelper("setupThemeToggle");
  runPageHelper("setupSidebar");
  runPageHelper("setupNavGroups");
  setupAssetForm();
}

function runPageHelper(helperName) {
  const helper = window[helperName];

  if (typeof helper === "function") {
    helper();
  }
}

// O formulário mantém o envio tradicional como fallback, mas usa AJAX quando o JavaScript está ativo.
function setupAssetForm() {
  const form = document.getElementById("assetForm");

  if (!form) return;

  form.addEventListener("submit", submitAssetForm);
  form.addEventListener("reset", () => {
    setTimeout(() => updateTraceabilityFields(form), 0);

    if (preserveMessageOnNextReset) {
      preserveMessageOnNextReset = false;
      return;
    }

    setTimeout(() => setFormMessage("", ""), 0);
  });
  setupTraceabilityControls(form);
}

// Só atualiza métricas e registros recentes depois de receber confirmação do backend.
async function submitAssetForm(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("assetSubmitButton");
  const error = validateAssetForm(form);

  if (error) {
    setFormMessage(error, "error");
    return;
  }

  const confirmed = await confirmAssetRegistration(form);

  if (!confirmed) {
    return;
  }

  setButtonLoading(submitButton, true);
  setFormMessage("", "");

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
      throw new Error(result.message || "Nao foi possivel cadastrar o ativo.");
    }

    setFormMessage(result.message || "Ativo cadastrado com sucesso.", "success");
    const createdAssets = [result.ativo].filter(Boolean);

    createdAssets.forEach(prependRecentAsset);
    updateAssetMetrics(createdAssets);
    preserveMessageOnNextReset = true;
    form.reset();

    setTimeout(() => {
      setFormMessage("", "");
    }, REDIRECT_DELAY_MS * 3);
  } catch (error) {
    setFormMessage(error.message || "Nao foi possivel cadastrar o ativo.", "error");
  } finally {
    setButtonLoading(submitButton, false);
  }
}

// A confirmação compartilhada evita cadastros acidentais antes do envio dos dados.
async function confirmAssetRegistration(form) {
  const data = new FormData(form);
  const assetName = String(data.get("nome") || "este ativo").trim() || "este ativo";
  const confirmationText = `Confirme para cadastrar ${assetName} no inventario.`;

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar ativo?",
      text: confirmationText,
      confirmButtonText: "Cadastrar ativo",
      cancelButtonText: "Revisar dados",
      icon: "info",
    });
  }

  return window.confirm(confirmationText);
}

function validateAssetForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const categoria = String(data.get("categoria_id") || "").trim();
  const status = String(data.get("status") || "").trim();
  const traceability = getSelectedTraceability(form);
  const config = TRACEABILITY_CONFIG[traceability];
  const partNumber = String(data.get("part_number") || "").trim();
  const serial = String(data.get("numero_serie") || "").trim();

  if (!nome || !categoria || !status) {
    return "Preencha nome, categoria e status para cadastrar o ativo.";
  }

  if (nome.length < 2) {
    return "O nome do ativo precisa ter pelo menos 2 caracteres.";
  }

  if (!config) {
    return "Selecione uma opcao de rastreabilidade valida.";
  }

  if (config.pn && !partNumber) {
    return "Informe o PN para a rastreabilidade escolhida.";
  }

  if (config.sn && !serial) {
    return "Informe o numero de serie para a rastreabilidade escolhida.";
  }

  return "";
}

function setupTraceabilityControls(form) {
  form.querySelectorAll('input[name="rastreabilidade"]').forEach((option) => {
    option.addEventListener("change", () => updateTraceabilityFields(form));
  });

  updateTraceabilityFields(form);
}

function getSelectedTraceability(form) {
  return form.querySelector('input[name="rastreabilidade"]:checked')?.value || "nao_possui";
}

function updateTraceabilityFields(form) {
  const config = TRACEABILITY_CONFIG[getSelectedTraceability(form)] || TRACEABILITY_CONFIG.nao_possui;

  // Desabilitar campos escondidos impede que valores antigos sejam enviados ao backend.
  toggleTraceabilityField(form, "pn", config.pn);
  toggleTraceabilityField(form, "sn", config.sn);
}

function toggleTraceabilityField(form, field, shouldShow) {
  const wrapper = form.querySelector(`[data-traceability-field="${field}"]`);
  const input = form.querySelector(`[data-traceability-input="${field}"]`);

  if (wrapper) {
    wrapper.hidden = !shouldShow;
  }

  if (!input) {
    return;
  }

  input.disabled = !shouldShow;
  input.required = shouldShow;
}

function setButtonLoading(button, isLoading) {
  if (!button) return;

  button.disabled = isLoading;

  if (isLoading) {
    button.replaceChildren(
      createElement("i", "bi bi-arrow-repeat"),
      createElement("span", "", "Cadastrando..."),
    );
    return;
  }

  button.replaceChildren(
    createElement("i", "bi bi-plus-circle"),
    createElement("span", "", "Cadastrar ativo"),
  );
}

function setFormMessage(message, type) {
  const element = document.getElementById("assetFormMessage");

  if (!element) return;

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("error", type === "error");
  element.classList.toggle("success", type === "success");
}

function prependRecentAsset(asset) {
  const list = document.getElementById("recentAssetList");

  if (!list || !asset) return;

  list.querySelector(".empty-state")?.remove();

  const item = createElement("div", "recent-asset-item");
  const content = createElement("div");
  const title = createElement("strong", "", String(asset.nome || "Novo ativo"));
  const partNumber = String(asset.part_number || "").trim();
  const details = [String(asset.status || "Disponivel")];

  if (partNumber) {
    details.push(`PN ${partNumber}`);
  }

  const detail = createElement("span", "", details.join(" - "));
  const date = createElement("small", "", "Agora");

  content.append(title, detail);
  item.append(content, date);
  list.prepend(item);
}

function updateAssetMetrics(assets) {
  const createdCount = assets.length;

  if (createdCount <= 0) {
    return;
  }

  incrementMetric("totalAssetsMetric", createdCount);
  incrementMetric("availableAssetsMetric", assets.filter(isAvailableAsset).length);
}

function incrementMetric(id, amount) {
  const element = document.getElementById(id);

  if (!element || amount <= 0) {
    return;
  }

  const current = Number.parseInt(element.textContent || "0", 10);

  element.textContent = String((Number.isFinite(current) ? current : 0) + amount);
}

function isAvailableAsset(asset) {
  return normalizeAssetText(asset?.status) === "disponivel";
}

function normalizeAssetText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function createElement(tag, className = "", text = "") {
  const element = document.createElement(tag);

  if (className) {
    element.className = className;
  }

  if (text) {
    element.textContent = text;
  }

  return element;
}


