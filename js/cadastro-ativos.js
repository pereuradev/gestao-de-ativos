const REDIRECT_DELAY_MS = 900;

document.addEventListener("DOMContentLoaded", initPage);

function initPage() {
  startPageAnimation();
  loadSavedTheme();
  setupThemeToggle();
  setupSidebar();
  setupNavGroups();
  setupAssetForm();
}

function setupAssetForm() {
  const form = document.getElementById("assetForm");

  if (!form) return;

  form.addEventListener("submit", submitAssetForm);
  form.addEventListener("reset", () => {
    setTimeout(() => setFormMessage("", ""), 0);
  });
}

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
    prependRecentAsset(result.ativo);
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

async function confirmAssetRegistration(form) {
  const data = new FormData(form);
  const assetName = String(data.get("nome") || "este ativo").trim() || "este ativo";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar ativo?",
      text: `Confirme para cadastrar ${assetName} no inventario.`,
      confirmButtonText: "Cadastrar ativo",
      cancelButtonText: "Revisar dados",
      icon: "info",
    });
  }

  return window.confirm(`Cadastrar ${assetName} no inventario?`);
}

function validateAssetForm(form) {
  const data = new FormData(form);
  const nome = String(data.get("nome") || "").trim();
  const categoria = String(data.get("categoria_id") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (!nome || !categoria || !status) {
    return "Preencha nome, categoria e status para cadastrar o ativo.";
  }

  if (nome.length < 2) {
    return "O nome do ativo precisa ter pelo menos 2 caracteres.";
  }

  return "";
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
  const detail = createElement("span", "", String(asset.status || "Disponível"));
  const date = createElement("small", "", "Agora");

  content.append(title, detail);
  item.append(content, date);
  list.prepend(item);
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


