const GROUP_EDIT_MESSAGE_HIDE_DELAY_MS = 2800;

document.addEventListener("DOMContentLoaded", initGroupEditPage);

function initGroupEditPage() {
  callGroupEditGlobal("startPageAnimation");
  callGroupEditGlobal("loadSavedTheme");
  callGroupEditGlobal("setupThemeToggle");
  callGroupEditGlobal("setupSidebar");
  callGroupEditGlobal("setupNavGroups");
  setupGroupEditSearch();
  setupGroupEditActions();
  updateGroupEditEmptyState();
}

function callGroupEditGlobal(functionName) {
  if (typeof window[functionName] === "function") {
    window[functionName]();
  }
}

function setupGroupEditSearch() {
  document.getElementById("groupEditSearch")?.addEventListener("input", filterGroupEditItems);
  filterGroupEditItems();
}

function setupGroupEditActions() {
  document.getElementById("groupEditList")?.addEventListener("click", (event) => {
    const removeButton = event.target.closest("[data-member-action='remove']");
    const deleteButton = event.target.closest("[data-group-action='delete']");

    if (removeButton) {
      removeGroupMember(removeButton);
      return;
    }

    if (deleteButton) {
      deleteGroup(deleteButton);
    }
  });
}

async function removeGroupMember(button) {
  const row = button.closest(".group-member-row");
  const card = button.closest(".group-edit-item");

  if (!row || !card) {
    return;
  }

  const memberName = row.querySelector(".group-member-info strong")?.textContent || "este membro";
  const groupName = card.dataset.name || "este grupo";
  const confirmed = await confirmGroupEditAction({
    title: `Remover ${memberName}?`,
    text: `O colaborador sera removido do grupo ${groupName}.`,
    confirmButtonText: "Remover membro",
    icon: "warning",
  });

  if (!confirmed) {
    return;
  }

  const body = new FormData();
  body.append("csrf_token", getGroupEditCsrfToken());
  body.append("grupo_id", card.dataset.id || "");
  body.append("usuario_id", row.dataset.memberId || "");

  setGroupEditLoading(button, true, "Removendo...");
  clearGroupEditMessage();

  try {
    const result = await postGroupEdit("Backend/remover-membro-grupo.php", body);

    row.remove();
    updateGroupMemberCount(card, -1);
    ensureGroupMemberEmptyState(card);
    setGroupEditMessage(result.message || "Membro removido do grupo.", "success");
  } catch (error) {
    setGroupEditMessage(error.message || "Nao foi possivel remover o membro.", "error");
  } finally {
    setGroupEditLoading(button, false);
  }
}

async function deleteGroup(button) {
  const card = button.closest(".group-edit-item");

  if (!card) {
    return;
  }

  const groupName = card.dataset.name || "este grupo";
  const confirmed = await confirmGroupEditAction({
    title: `Excluir ${groupName}?`,
    text: "Esta acao remove o grupo, seus membros e suas permissoes.",
    confirmButtonText: "Excluir grupo",
    icon: "warning",
  });

  if (!confirmed) {
    return;
  }

  const body = new FormData();
  body.append("csrf_token", getGroupEditCsrfToken());
  body.append("id", card.dataset.id || "");

  setGroupEditLoading(button, true, "Excluindo...");
  clearGroupEditMessage();

  try {
    const result = await postGroupEdit("Backend/excluir-grupo.php", body);
    const removedMembers = Number(card.dataset.members || result.grupo?.total_membros || 0);
    const removedPermissions = Number(card.dataset.permissions || result.grupo?.total_permissoes || 0);

    card.remove();
    incrementGroupEditMetric("editGroupMetricTotal", -1);
    incrementGroupEditMetric("editGroupMetricMembers", -removedMembers);
    incrementGroupEditMetric("editGroupMetricPermissions", -removedPermissions);
    filterGroupEditItems();
    setGroupEditMessage(result.message || "Grupo excluido com sucesso.", "success");
  } catch (error) {
    setGroupEditMessage(error.message || "Nao foi possivel excluir o grupo.", "error");
  } finally {
    setGroupEditLoading(button, false);
  }
}

async function postGroupEdit(url, body) {
  const response = await fetch(url, {
    method: "POST",
    body,
    headers: { Accept: "application/json" },
  });
  const result = await response.json().catch(() => ({
    ok: false,
    message: "Resposta invalida do servidor.",
  }));

  if (!response.ok || !result.ok) {
    throw new Error(result.message || "Nao foi possivel concluir a acao.");
  }

  return result;
}

async function confirmGroupEditAction(options) {
  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm(options);
  }

  return window.confirm(`${options.title}\n${options.text}`);
}

function updateGroupMemberCount(card, amount) {
  const nextValue = Math.max(0, Number(card.dataset.members || 0) + amount);
  const counter = card.querySelector("[data-member-count]");

  card.dataset.members = String(nextValue);

  if (counter) {
    counter.textContent = String(nextValue);
  }

  incrementGroupEditMetric("editGroupMetricMembers", amount);
}

function ensureGroupMemberEmptyState(card) {
  const list = card.querySelector("[data-member-list]");

  if (!list || list.querySelector(".group-member-row") || list.querySelector("[data-member-empty]")) {
    return;
  }

  const empty = document.createElement("div");
  empty.className = "group-member-empty";
  empty.dataset.memberEmpty = "";
  empty.innerHTML = '<i class="bi bi-info-circle"></i><span>Nenhum membro neste grupo.</span>';
  list.append(empty);
}

function filterGroupEditItems() {
  const search = normalizeGroupEditText(document.getElementById("groupEditSearch")?.value || "");
  const cards = Array.from(document.querySelectorAll(".group-edit-item"));
  let visible = 0;

  cards.forEach((card) => {
    const matches = !search || normalizeGroupEditText(card.dataset.search || "").includes(search);

    card.hidden = !matches;

    if (matches) {
      visible += 1;
    }
  });

  updateGroupEditText("groupEditResultCount", `${visible.toLocaleString("pt-BR")} ${visible === 1 ? "registro" : "registros"}`);
  updateGroupEditEmptyState();
}

function updateGroupEditEmptyState() {
  const empty = document.getElementById("groupEditEmptyState");
  const visibleCards = Array.from(document.querySelectorAll(".group-edit-item")).filter((card) => !card.hidden);

  if (empty) {
    empty.hidden = visibleCards.length > 0;
  }
}

function setGroupEditMessage(message, type) {
  const element = document.getElementById("groupEditPageMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("success", type === "success");
  element.classList.toggle("error", type === "error");

  if (message && type === "success") {
    setTimeout(clearGroupEditMessage, GROUP_EDIT_MESSAGE_HIDE_DELAY_MS);
  }
}

function clearGroupEditMessage() {
  setGroupEditMessage("", "");
}

function setGroupEditLoading(button, isLoading, loadingText = "Aguarde...") {
  if (!button) {
    return;
  }

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

function incrementGroupEditMetric(id, amount) {
  const element = document.getElementById(id);
  const current = Number.parseInt(element?.textContent || "0", 10);

  if (!element || Number.isNaN(current)) {
    return;
  }

  element.textContent = String(Math.max(0, current + amount));
}

function updateGroupEditText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

function normalizeGroupEditText(value) {
  return String(value)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function getGroupEditCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}
