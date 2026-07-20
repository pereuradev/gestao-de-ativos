// Coordena edição, remoção de membros e exclusão de grupos de acesso.
// As mudanças confirmadas pelo backend são refletidas nos cartões e métricas da página.

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
  setupGroupEditModal();
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
    const editButton = event.target.closest("[data-group-action='edit']");
    const removeButton = event.target.closest("[data-member-action='remove']");
    const deleteButton = event.target.closest("[data-group-action='delete']");

    if (editButton) {
      openGroupEditModal(editButton.closest(".group-edit-item"));
      return;
    }

    if (removeButton) {
      removeGroupMember(removeButton);
      return;
    }

    if (deleteButton) {
      deleteGroup(deleteButton);
    }
  });
}

function setupGroupEditModal() {
  const modal = document.getElementById("groupEditModal");
  const form = document.getElementById("groupModalForm");
  const search = document.getElementById("editGroupEmployeeSearch");

  form?.addEventListener("submit", submitGroupModal);
  search?.addEventListener("input", filterGroupModalMembers);

  document.querySelectorAll("[data-close-group-modal]").forEach((button) => {
    button.addEventListener("click", closeGroupEditModal);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeGroupEditModal();
    }
  });
}

// O modal recebe membros e permissões serializados no cartão selecionado.
function openGroupEditModal(card) {
  const modal = document.getElementById("groupEditModal");

  if (!modal || !card) {
    return;
  }

  setGroupInputValue("editGroupId", card.dataset.id || "");
  setGroupInputValue("editGroupName", card.dataset.name || "");
  setGroupInputValue("editGroupDescription", card.dataset.description || "");
  setGroupInputValue("editGroupStatus", card.dataset.status || "Ativo");

  const memberIds = new Set(
    Array.from(card.querySelectorAll(".group-member-row"))
      .map((row) => row.dataset.memberId || "")
      .filter(Boolean),
  );
  const permissionCodes = new Set((card.dataset.permissionCodes || "").split(",").filter(Boolean));

  setGroupModalChecks("membros[]", memberIds);
  setGroupModalChecks("permissoes[]", permissionCodes);
  syncGroupModalPermissionSections();
  setGroupInputValue("editGroupEmployeeSearch", "");
  filterGroupModalMembers();
  clearGroupModalMessage();

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editGroupName")?.focus();
}

function closeGroupEditModal() {
  const modal = document.getElementById("groupEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

// O backend persiste o conjunto completo antes da atualização visual.
async function submitGroupModal(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = document.getElementById("saveGroupButton");
  const error = validateGroupModalForm(form);

  if (error) {
    setGroupModalMessage(error, "error");
    return;
  }

  const confirmed = await confirmGroupModalEdition(form);

  if (!confirmed) {
    return;
  }

  setGroupEditLoading(submitButton, true, "Salvando...");
  clearGroupModalMessage();

  try {
    const result = await postGroupEdit(form.action, new FormData(form));

    if (result.grupo) {
      updateGroupCard(result.grupo);
    }

    closeGroupEditModal();
    setGroupEditMessage(result.message || "Grupo atualizado com sucesso.", "success");
  } catch (error) {
    setGroupModalMessage(error.message || "Nao foi possivel atualizar o grupo.", "error");
  } finally {
    setGroupEditLoading(submitButton, false);
  }
}

// A remoção individual exige confirmação e preserva a consistência das métricas.
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
    const result = await postGroupEdit("../Backend/remover-membro-grupo.php", body);

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

// A exclusão só remove o cartão após resposta bem-sucedida do servidor.
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
    const result = await postGroupEdit("../Backend/excluir-grupo.php", body);
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

function validateGroupModalForm(form) {
  const data = new FormData(form);
  const name = String(data.get("nome") || "").trim();
  const status = String(data.get("status") || "").trim();

  if (name.length < 3) {
    return "Informe um nome de grupo com pelo menos 3 caracteres.";
  }

  if (name.length > 90) {
    return "O nome do grupo pode ter no maximo 90 caracteres.";
  }

  if (!["Ativo", "Inativo"].includes(status)) {
    return "Selecione se o grupo ficara ativo ou inativo.";
  }

  return "";
}

async function confirmGroupModalEdition(form) {
  const data = new FormData(form);
  const name = String(data.get("nome") || "este grupo").trim() || "este grupo";

  return confirmGroupEditAction({
    title: "Salvar alteracoes?",
    text: `Confirme para atualizar nome, descricao, membros e permissoes de ${name}.`,
    confirmButtonText: "Salvar alteracoes",
    cancelButtonText: "Continuar editando",
    icon: "warning",
  });
}

function filterGroupModalMembers() {
  const search = normalizeGroupEditText(document.getElementById("editGroupEmployeeSearch")?.value || "");

  document.querySelectorAll("[data-modal-member-card]").forEach((card) => {
    const matches = !search || normalizeGroupEditText(card.dataset.search || "").includes(search);
    card.hidden = !matches;
  });
}

function updateGroupCard(group) {
  const card = document.querySelector(`.group-edit-item[data-id="${cssEscapeGroupEdit(group.id)}"]`);

  if (!card) {
    return;
  }

  const oldMemberTotal = Number(card.dataset.members || 0);
  const oldPermissionTotal = Number(card.dataset.permissions || 0);
  const members = Array.isArray(group.membros) ? group.membros : [];
  const permissions = Array.isArray(group.permissoes) ? group.permissoes : [];
  const memberTotal = Number(group.total_membros ?? members.length);
  const permissionTotal = Number(group.total_permissoes ?? permissions.length);
  const description = String(group.descricao || "");
  const status = String(group.status || "Ativo");

  card.dataset.name = String(group.nome || "");
  card.dataset.description = description;
  card.dataset.status = status;
  card.dataset.members = String(memberTotal);
  card.dataset.permissions = String(permissionTotal);
  card.dataset.permissionCodes = permissions.map((permission) => String(permission.codigo || "")).filter(Boolean).join(",");
  card.dataset.search = buildGroupSearchValue(group, members, permissions);

  updateElementText(card.querySelector("[data-group-name]"), group.nome || "--");
  updateElementText(card.querySelector("[data-group-description]"), description || "Sem descricao informada.");
  updateElementText(card.querySelector("[data-member-count]"), String(memberTotal));
  updateElementText(card.querySelector("[data-permission-count]"), String(permissionTotal));
  updateGroupStatusBadge(card, status);

  renderGroupPermissions(card, permissions);
  renderGroupMembers(card, members);
  incrementGroupEditMetric("editGroupMetricMembers", memberTotal - oldMemberTotal);
  incrementGroupEditMetric("editGroupMetricPermissions", permissionTotal - oldPermissionTotal);
  filterGroupEditItems();
}

function renderGroupPermissions(card, permissions) {
  const list = card.querySelector("[data-permission-list]");

  if (!list) {
    return;
  }

  list.replaceChildren();

  if (!permissions.length) {
    list.append(createGroupPermissionChip("Nenhuma permissao cadastrada."));
    return;
  }

  permissions.forEach((permission) => {
    list.append(createGroupPermissionChip(permission.rotulo || permission.codigo || "--"));
  });
}

// A lista usa elementos de DOM para manter o conteúdo da resposta como texto.
function renderGroupMembers(card, members) {
  const list = card.querySelector("[data-member-list]");

  if (!list) {
    return;
  }

  list.replaceChildren();

  members.forEach((member) => {
    list.append(createGroupMemberRow(member));
  });

  ensureGroupMemberEmptyState(card);
}

function createGroupPermissionChip(label) {
  const chip = document.createElement("span");
  chip.textContent = label;

  return chip;
}

function createGroupMemberRow(member) {
  const row = document.createElement("article");
  const avatar = document.createElement("div");
  const info = document.createElement("div");
  const name = document.createElement("strong");
  const email = document.createElement("span");
  const details = document.createElement("small");
  const button = document.createElement("button");

  row.className = "group-member-row";
  row.dataset.memberId = member.id || "";

  avatar.className = "group-member-avatar";
  avatar.setAttribute("aria-hidden", "true");
  avatar.textContent = member.iniciais || getGroupEditInitials(member.nome || "");

  info.className = "group-member-info";
  name.textContent = member.nome || "--";
  email.textContent = member.email || "--";
  details.textContent = `${member.tipo_usuario || "--"} - ${member.departamento || "--"}`;
  info.append(name, email, details);

  button.className = "table-action remove-member-button";
  button.type = "button";
  button.dataset.memberAction = "remove";
  button.innerHTML = '<i class="bi bi-person-dash"></i><span>Remover</span>';

  row.append(avatar, info, button);

  return row;
}

function buildGroupSearchValue(group, members, permissions) {
  const memberSearch = members
    .map((member) => `${member.nome || ""} ${member.email || ""} ${member.departamento || ""}`)
    .join(" ");
  const permissionSearch = permissions
    .map((permission) => `${permission.rotulo || ""} ${permission.codigo || ""}`)
    .join(" ");

  return `${group.nome || ""} ${group.descricao || ""} ${group.status || ""} ${memberSearch} ${permissionSearch}`.toLowerCase().trim();
}

function updateGroupStatusBadge(card, status) {
  const badge = card.querySelector("[data-group-status]");

  if (!badge) {
    return;
  }

  const isActive = normalizeGroupEditText(status) === "ativo";

  badge.textContent = status || "Ativo";
  badge.classList.toggle("status-active", isActive);
  badge.classList.toggle("status-inactive", !isActive);
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

function setGroupModalMessage(message, type) {
  const element = document.getElementById("groupModalMessage");

  if (!element) {
    return;
  }

  element.textContent = message;
  element.classList.toggle("show", Boolean(message));
  element.classList.toggle("success", type === "success");
  element.classList.toggle("error", type === "error");
}

function clearGroupModalMessage() {
  setGroupModalMessage("", "");
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

function updateElementText(element, value) {
  if (element) {
    element.textContent = value;
  }
}

function setGroupInputValue(id, value) {
  const input = document.getElementById(id);

  if (input) {
    input.value = value;
  }
}

function setGroupModalChecks(name, selectedValues) {
  document.querySelectorAll(`#groupEditModal input[name="${name}"]`).forEach((input) => {
    input.checked = selectedValues.has(input.value);
  });
}

function syncGroupModalPermissionSections() {
  document.querySelectorAll("#groupEditModal .permission-section").forEach((section) => {
    section.open = Boolean(section.querySelector('input[name="permissoes[]"]:checked'));
  });
}

function cssEscapeGroupEdit(value) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(value || ""));
  }

  return String(value || "").replace(/["\\]/g, "\\$&");
}

function getGroupEditInitials(name) {
  const initials = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");

  return initials || "TT";
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
