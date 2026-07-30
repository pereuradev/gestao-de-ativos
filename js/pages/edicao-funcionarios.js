// Gerencia filtros e o modal de atualização dos dados de funcionários.
// O cartão editado é sincronizado no DOM após a confirmação do backend.

const ATRASO_OCULTACAO_MENSAGEM_EDICAO_FUNCIONARIO_MS = 2800;

document.addEventListener("DOMContentLoaded", inicializarPaginaEdicaoFuncionario);

function inicializarPaginaEdicaoFuncionario() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosFuncionario();
  configurarCartoesFuncionario();
  configurarModalEdicaoFuncionario();
}

function configurarFiltrosFuncionario() {
  document.getElementById("employeeSearch")?.addEventListener("input", filtrarFuncionarios);
  document.getElementById("employeeStatusFilter")?.addEventListener("change", filtrarFuncionarios);

  filtrarFuncionarios();
}

function configurarCartoesFuncionario() {
  document.getElementById("employeeCardList")?.addEventListener("click", (evento) => {
    const cartao = evento.target.closest("[data-employee-card]");

    if (cartao) {
      abrirModalEdicaoFuncionario(cartao);
    }
  });
}

function configurarModalEdicaoFuncionario() {
  const modal = document.getElementById("employeeEditModal");
  const formulario = document.getElementById("employeeEditForm");

  formulario?.addEventListener("submit", enviarFormularioEdicaoFuncionario);

  document.querySelectorAll("[data-close-employee-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalEdicaoFuncionario);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalEdicaoFuncionario();
    }
  });

  document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && modal && !modal.hidden) {
      fecharModalEdicaoFuncionario();
    }
  });
}

// A filtragem ocorre sobre os cartões já renderizados e mantém o estado vazio sincronizado.
function filtrarFuncionarios() {
  const cartoes = Array.from(document.querySelectorAll(".employee-row"));
  const busca = normalizarTexto(document.getElementById("employeeSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("employeeStatusFilter")?.value || "todos");
  let quantidadeVisivel = 0;
  let quantidadeAtivos = 0;
  let quantidadeInativos = 0;

  cartoes.forEach((cartao) => {
    const statusLinha = normalizarTexto(cartao.dataset.status || "");
    const buscaLinha = normalizarTexto(cartao.dataset.search || "");
    const correspondeStatus = status === "todos" || statusLinha === status;
    const correspondeBusca = !busca || buscaLinha.includes(busca);
    const ehVisivel = correspondeStatus && correspondeBusca;

    if (statusLinha === "ativo") {
      quantidadeAtivos += 1;
    } else if (statusLinha === "inativo") {
      quantidadeInativos += 1;
    }

    cartao.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarTextoFuncionario("employeeResultCount", `${quantidadeVisivel.toLocaleString("pt-BR")} ${quantidadeVisivel === 1 ? "registro" : "registros"}`);
  atualizarTextoFuncionario("employeeTotalMetric", String(cartoes.length));
  atualizarTextoFuncionario("employeeActiveMetric", String(quantidadeAtivos));
  atualizarTextoFuncionario("employeeInactiveMetric", String(quantidadeInativos));
  atualizarEstadoVazioFiltrado(cartoes.length > 0 && quantidadeVisivel === 0);
}

// O modal é preenchido a partir dos atributos do cartão selecionado.
function abrirModalEdicaoFuncionario(cartao) {
  const modal = document.getElementById("employeeEditModal");

  if (!modal) {
    return;
  }

  modal.dataset.lastTriggerId = cartao.dataset.id || "";
  definirValorFuncionario("editEmployeeId", cartao.dataset.id || "");
  definirValorFuncionario("editEmployeeName", cartao.dataset.name || "");
  definirValorFuncionario("editEmployeeEmail", cartao.dataset.email || "");
  definirValorFuncionario("editEmployeeRole", cartao.dataset.role || "Colaborador");
  definirValorFuncionario("editEmployeeStatus", cartao.dataset.statusLabel || "Ativo");
  definirValorFuncionario("editEmployeeDepartment", cartao.dataset.department || "TI");
  definirValorFuncionario("editEmployeeCompany", cartao.dataset.company || "");
  definirValorFuncionario("editEmployeeRg", cartao.dataset.rg || "");
  definirValorFuncionario("editEmployeeCpf", cartao.dataset.cpf || "");
  definirValorFuncionario("editEmployeePhone", cartao.dataset.phone || "");
  definirValorFuncionario("editEmployeeBirth", cartao.dataset.birthValue || "");
  atualizarTextoFuncionario("employeeEditInitials", cartao.dataset.initials || "TT");
  atualizarTextoFuncionario("employeeEditModalTitle", cartao.dataset.name || "Funcionario");
  atualizarTextoFuncionario("employeeEditEmailText", cartao.dataset.email || "--");
  limparMensagemEdicaoFuncionario();

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editEmployeeName")?.focus();
}

function fecharModalEdicaoFuncionario() {
  const modal = document.getElementById("employeeEditModal");

  if (!modal) {
    return;
  }

  modal.hidden = true;

  const idAcionador = modal.dataset.lastTriggerId || "";
  const acionador = idAcionador
    ? document.querySelector(`[data-employee-card][data-id="${escaparCssFuncionario(idAcionador)}"]`)
    : null;

  acionador?.focus();
}

// Os dados visuais só são atualizados depois da confirmação do backend.
async function enviarFormularioEdicaoFuncionario(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveEmployeeButton");
  const erro = validarFormularioEdicaoFuncionario(formulario);

  if (erro) {
    definirMensagemEdicaoFuncionario(erro, "error");
    return;
  }

  const confirmado = window.titechConfirm
    ? await window.titechConfirm({
      title: "Salvar alteracoes?",
      text: "Os dados do funcionario serao atualizados no cadastro.",
      confirmButtonText: "Salvar",
      icon: "warning",
    })
    : window.confirm("Salvar alteracoes deste funcionario?");

  if (!confirmado) {
    return;
  }

  definirCarregandoFuncionario(botaoEnviar, true, "Salvando...");
  limparMensagemEdicaoFuncionario();

  try {
    const resposta = await fetch(formulario.action, {
      method: "POST",
      body: new FormData(formulario),
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel atualizar o funcionario.");
    }

    atualizarCartaoFuncionario(resultado.funcionario);
    fecharModalEdicaoFuncionario();
    definirMensagemPaginaEdicaoFuncionario(resultado.message || "Funcionario atualizado com sucesso.", "success");
    filtrarFuncionarios();
  } catch (erro) {
    definirMensagemEdicaoFuncionario(erro.message || "Nao foi possivel atualizar o funcionario.", "error");
  } finally {
    definirCarregandoFuncionario(botaoEnviar, false);
  }
}

function validarFormularioEdicaoFuncionario(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome_completo") || "").trim();
  const tipoUsuario = String(dados.get("tipo_usuario") || "").trim();
  const status = String(dados.get("status") || "").trim();
  const departamento = String(dados.get("departamento") || "").trim();
  const empresa = String(dados.get("empresa") || "").trim();
  const rg = String(dados.get("rg") || "").replace(/\D+/g, "");
  const cpf = String(dados.get("cpf") || "").replace(/\D+/g, "");
  const telefone = String(dados.get("celular") || "").replace(/\D+/g, "");
  const dataNascimento = String(dados.get("data_nascimento") || "").trim();

  if (!nome || !tipoUsuario || !status || !departamento || !empresa || !dataNascimento) {
    return "Preencha todos os campos obrigatorios.";
  }

  if (nome.split(/\s+/).filter(Boolean).length < 2) {
    return "Informe nome e sobrenome.";
  }

  if (rg.length < 7) {
    return "Informe um RG valido.";
  }

  if (cpf.length !== 11) {
    return "Informe um CPF valido.";
  }

  if (telefone.length !== 11) {
    return "Informe um celular valido com DDD.";
  }

  if (!["Colaborador", "Administrador"].includes(tipoUsuario)) {
    return "Perfil de acesso invalido.";
  }

  if (!["Ativo", "Inativo"].includes(status)) {
    return "Status invalido.";
  }

  return "";
}

// Sincroniza o cartão existente para evitar recarregar toda a listagem.
function atualizarCartaoFuncionario(funcionario) {
  if (!funcionario?.id) {
    return;
  }

  const cartao = document.querySelector(`[data-employee-card][data-id="${escaparCssFuncionario(String(funcionario.id))}"]`);

  if (!cartao) {
    return;
  }

  const nome = String(funcionario.nome_completo || "");
  const email = String(funcionario.email || cartao.dataset.email || "");
  const tipoUsuario = String(funcionario.tipo_usuario || "Colaborador");
  const departamento = String(funcionario.departamento || "");
  const empresa = String(funcionario.empresa || "");
  const telefone = String(funcionario.celular || "");
  const rg = String(funcionario.rg || "");
  const cpf = String(funcionario.cpf || "");
  const status = String(funcionario.status || "Ativo");
  const valorNascimento = String(funcionario.data_nascimento || "");
  const rotuloNascimento = formatarSomenteDataFuncionario(valorNascimento);
  const rotuloAtualizado = formatarDataHoraFuncionario(funcionario.atualizado_em) || "Agora";
  const iniciais = obterIniciaisFuncionario(nome);
  const busca = [nome, email, tipoUsuario, departamento, empresa, telefone, rg, cpf, status].join(" ");

  Object.assign(cartao.dataset, {
    name: nome,
    email,
    role: tipoUsuario,
    department: departamento,
    company: empresa,
    phone: telefone,
    rg,
    cpf,
    status: normalizarTexto(status),
    statusLabel: status,
    birth: rotuloNascimento,
    birthValue: valorNascimento,
    updated: rotuloAtualizado,
    initials: iniciais,
    search: busca,
  });

  atualizarTextoFuncionarioNoContexto(cartao, ".employee-card-avatar", iniciais);
  atualizarTextoFuncionarioNoContexto(cartao, ".employee-card-identity strong", nome);
  atualizarTextoFuncionarioNoContexto(cartao, ".employee-card-identity small", email);
  atualizarStatusCartaoFuncionario(cartao, status);
  atualizarCampoCartaoFuncionario(cartao, "Perfil", tipoUsuario);
  atualizarCampoCartaoFuncionario(cartao, "Departamento", departamento);
  atualizarCampoCartaoFuncionario(cartao, "Empresa", empresa);
  atualizarCampoCartaoFuncionario(cartao, "Celular", telefone);
}

function atualizarStatusCartaoFuncionario(cartao, status) {
  const indicador = cartao.querySelector(".status-badge");
  const classeStatus = normalizarTexto(status) === "ativo"
    ? "status-active"
    : normalizarTexto(status) === "inativo"
      ? "status-inactive"
      : "status-neutral";

  if (indicador) {
    indicador.className = `status-badge ${classeStatus}`;
    indicador.textContent = status;
  }
}

function atualizarCampoCartaoFuncionario(cartao, rotulo, valor) {
  const itens = Array.from(cartao.querySelectorAll(".employee-card-body > span"));
  const item = itens.find((elemento) => normalizarTexto(elemento.querySelector("small")?.textContent || "") === normalizarTexto(rotulo));

  atualizarTextoFuncionarioNoContexto(item, "strong", valor);
}

function atualizarTextoFuncionarioNoContexto(raiz, seletor, valor) {
  const elemento = raiz?.querySelector?.(seletor);

  if (elemento) {
    elemento.textContent = valor;
  }
}

function atualizarEstadoVazioFiltrado(exibir) {
  const estadoVazio = document.getElementById("employeeEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function definirValorFuncionario(id, valor) {
  const campoEntrada = document.getElementById(id);

  if (campoEntrada) {
    campoEntrada.value = valor;
  }
}

function definirMensagemEdicaoFuncionario(mensagem, tipo) {
  const elemento = document.getElementById("employeeEditMessage");

  if (!elemento) {
    return;
  }

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function limparMensagemEdicaoFuncionario() {
  definirMensagemEdicaoFuncionario("", "");
}

function definirMensagemPaginaEdicaoFuncionario(mensagem, tipo) {
  const elemento = document.getElementById("employeeEditPageMessage");

  if (!elemento) {
    return;
  }

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");

  if (mensagem && tipo === "success") {
    setTimeout(() => definirMensagemPaginaEdicaoFuncionario("", ""), ATRASO_OCULTACAO_MENSAGEM_EDICAO_FUNCIONARIO_MS);
  }
}

function definirCarregandoFuncionario(botao, estaCarregando, textoCarregando = "Aguarde...") {
  if (!botao) {
    return;
  }

  if (estaCarregando) {
    botao.dataset.originalHtml = botao.innerHTML;
    botao.disabled = true;
    botao.innerHTML = `<i class="bi bi-arrow-repeat"></i><span>${textoCarregando}</span>`;
    return;
  }

  botao.disabled = false;

  if (botao.dataset.originalHtml) {
    botao.innerHTML = botao.dataset.originalHtml;
    delete botao.dataset.originalHtml;
  }
}

function atualizarTextoFuncionario(id, valor) {
  const elemento = document.getElementById(id);

  if (elemento) {
    elemento.textContent = valor;
  }
}

function obterIniciaisFuncionario(nome) {
  const iniciais = String(nome || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte.charAt(0).toUpperCase())
    .join("");

  return iniciais || "TT";
}

function formatarSomenteDataFuncionario(valor) {
  if (!valor) {
    return "--";
  }

  const data = new Date(`${valor}T00:00:00`);

  if (Number.isNaN(data.getTime())) {
    return "--";
  }

  return new Intl.DateTimeFormat("pt-BR").format(data);
}

function formatarDataHoraFuncionario(valor) {
  if (!valor) {
    return "";
  }

  const data = new Date(valor);

  if (Number.isNaN(data.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(data);
}

function escaparCssFuncionario(valor) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(valor || ""));
  }

  return String(valor || "").replace(/["\\]/g, "\\$&");
}
