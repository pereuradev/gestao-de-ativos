// Filtra os cartões de funcionários e apresenta detalhes no modal de visualização.
// O módulo altera apenas a interface; os dados de origem são renderizados pelo PHP.

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosFuncionario();
  configurarCartoesFuncionario();
  configurarModalFuncionario();
}

function configurarFiltrosFuncionario() {
  const busca = document.getElementById("employeeSearch");
  const filtroStatus = document.getElementById("employeeStatusFilter");

  busca?.addEventListener("input", filtrarFuncionarios);
  filtroStatus?.addEventListener("change", filtrarFuncionarios);

  filtrarFuncionarios();
}

// A busca combina os campos normalizados de cada cartão sem consultar novamente o servidor.
function filtrarFuncionarios() {
  const cartoes = Array.from(document.querySelectorAll(".employee-row"));
  const busca = normalizarTexto(document.getElementById("employeeSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("employeeStatusFilter")?.value || "todos");
  let quantidadeVisivel = 0;

  cartoes.forEach((cartao) => {
    const statusLinha = normalizarTexto(cartao.dataset.status || "");
    const buscaLinha = normalizarTexto(cartao.dataset.search || "");
    const correspondeStatus = status === "todos" || statusLinha === status;
    const correspondeBusca = !busca || buscaLinha.includes(busca);
    const ehVisivel = correspondeStatus && correspondeBusca;

    cartao.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarQuantidadeResultado(quantidadeVisivel);
  atualizarEstadoVazioFiltrado(cartoes.length > 0 && quantidadeVisivel === 0);
}

function atualizarQuantidadeResultado(quantidade) {
  const quantidadeResultado = document.getElementById("employeeResultCount");

  if (!quantidadeResultado) return;

  quantidadeResultado.textContent = `${quantidade.toLocaleString("pt-BR")} ${quantidade === 1 ? "registro" : "registros"}`;
}

function atualizarEstadoVazioFiltrado(exibir) {
  const estadoVazio = document.getElementById("employeeEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function configurarCartoesFuncionario() {
  document.getElementById("employeeCardList")?.addEventListener("click", (evento) => {
    const cartao = evento.target.closest("[data-employee-card]");

    if (cartao) {
      abrirModalFuncionario(cartao);
    }
  });
}

function configurarModalFuncionario() {
  const modal = document.getElementById("employeeDetailsModal");

  document.querySelectorAll("[data-close-employee-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalFuncionario);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalFuncionario();
    }
  });

  document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && modal && !modal.hidden) {
      fecharModalFuncionario();
    }
  });
}

// O modal usa os dados já presentes no cartão e devolve o foco ao fechar.
function abrirModalFuncionario(cartao) {
  const modal = document.getElementById("employeeDetailsModal");

  if (!modal) {
    return;
  }

  modal.dataset.lastTriggerId = cartao.dataset.email || "";
  atualizarTextoFuncionario("employeeModalInitials", cartao.dataset.initials || "TT");
  atualizarTextoFuncionario("employeeModalTitle", cartao.dataset.name || "Funcionario");
  atualizarTextoFuncionario("employeeModalEmail", cartao.dataset.email || "--");
  atualizarTextoFuncionario("employeeModalRole", cartao.dataset.role || "--");
  atualizarTextoFuncionario("employeeModalDepartment", cartao.dataset.department || "--");
  atualizarTextoFuncionario("employeeModalCompany", cartao.dataset.company || "--");
  atualizarTextoFuncionario("employeeModalPhone", cartao.dataset.phone || "--");
  atualizarTextoFuncionario("employeeModalRg", cartao.dataset.rg || "--");
  atualizarTextoFuncionario("employeeModalCpf", cartao.dataset.cpf || "--");
  atualizarTextoFuncionario("employeeModalBirth", cartao.dataset.birth || "--");
  atualizarTextoFuncionario("employeeModalCreated", cartao.dataset.created || "--");
  atualizarTextoFuncionario("employeeModalUpdated", cartao.dataset.updated || "--");
  atualizarStatusFuncionario(cartao);

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  modal.querySelector("[data-close-employee-modal]")?.focus();
}

function fecharModalFuncionario() {
  const modal = document.getElementById("employeeDetailsModal");

  if (!modal) {
    return;
  }

  modal.hidden = true;

  const emailAcionador = modal.dataset.lastTriggerId || "";
  const acionador = emailAcionador
    ? document.querySelector(`[data-employee-card][data-email="${escaparCssFuncionario(emailAcionador)}"]`)
    : null;

  acionador?.focus();
}

function atualizarStatusFuncionario(cartao) {
  const status = document.getElementById("employeeModalStatus");
  const rotuloStatus = cartao.dataset.statusLabel || "--";
  const classeStatus = normalizarTexto(rotuloStatus) === "ativo"
    ? "status-active"
    : normalizarTexto(rotuloStatus) === "inativo"
      ? "status-inactive"
      : "status-neutral";

  if (!status) {
    return;
  }

  status.className = `status-badge ${classeStatus}`;
  status.textContent = rotuloStatus;
}

function atualizarTextoFuncionario(id, valor) {
  const elemento = document.getElementById(id);

  if (elemento) {
    elemento.textContent = valor;
  }
}

function escaparCssFuncionario(valor) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(valor || ""));
  }

  return String(valor || "").replace(/["\\]/g, "\\$&");
}
