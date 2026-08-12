const ENDPOINT_SOLICITACOES_ACESSO = "../Backend/processar-solicitacoes-acesso.php";
const FOTO_PADRAO_SOLICITACAO = "../assets/favicon.png";
const estadoPaginaSolicitacoes = {
  solicitacoes: [],
  solicitacaoAtual: null,
  carregando: false,
  temporizadorBusca: null,
};

document.addEventListener("DOMContentLoaded", inicializarPaginaSolicitacoesAcesso);

function inicializarPaginaSolicitacoesAcesso() {
  document.getElementById("refreshRequests")?.addEventListener("click", carregarSolicitacoesAcesso);
  document.getElementById("requestStatusFilter")?.addEventListener("change", carregarSolicitacoesAcesso);
  document.getElementById("requestSearch")?.addEventListener("input", agendarBuscaSolicitacoes);
  document.querySelector("[data-close-details]")?.addEventListener("click", fecharDetalhesSolicitacao);
  document.getElementById("editRequestButton")?.addEventListener("click", () => alternarEdicaoSolicitacao(true));
  document.getElementById("cancelEditRequestButton")?.addEventListener("click", () => preencherDialogoSolicitacao(estadoPaginaSolicitacoes.solicitacaoAtual));
  document.getElementById("saveRequestButton")?.addEventListener("click", salvarAlteracoesSolicitacao);
  document.getElementById("approveRequestDialogButton")?.addEventListener("click", () => aprovarSolicitacao(estadoPaginaSolicitacoes.solicitacaoAtual));
  document.getElementById("rejectRequestDialogButton")?.addEventListener("click", () => abrirRecusaSolicitacao(estadoPaginaSolicitacoes.solicitacaoAtual));
  document.querySelector("[data-cancel-rejection]")?.addEventListener("click", fecharRecusaSolicitacao);
  document.getElementById("rejectRequestForm")?.addEventListener("submit", confirmarRecusaSolicitacao);
  carregarSolicitacoesAcesso();
  atualizarResumoSolicitacoes();
}

function agendarBuscaSolicitacoes() {
  clearTimeout(estadoPaginaSolicitacoes.temporizadorBusca);
  estadoPaginaSolicitacoes.temporizadorBusca = setTimeout(carregarSolicitacoesAcesso, 320);
}

async function carregarSolicitacoesAcesso() {
  if (estadoPaginaSolicitacoes.carregando) return;

  const lista = document.getElementById("accessRequestList");
  const status = document.getElementById("requestStatusFilter")?.value || "Pendente";
  const busca = document.getElementById("requestSearch")?.value.trim() || "";
  const parametros = new URLSearchParams({ acao: "listar", status, busca });

  estadoPaginaSolicitacoes.carregando = true;
  lista.replaceChildren(criarEstadoListaSolicitacoes("loading", "Carregando solicitacoes..."));

  try {
    const resposta = await fetch(`${ENDPOINT_SOLICITACOES_ACESSO}?${parametros}`, {
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json();

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel carregar as solicitacoes.");
    }

    estadoPaginaSolicitacoes.solicitacoes = Array.isArray(resultado.solicitacoes)
      ? resultado.solicitacoes
      : [];
    renderizarSolicitacoesAcesso();
  } catch (erro) {
    const mensagem = erro instanceof Error ? erro.message : "Nao foi possivel carregar as solicitacoes.";
    lista.replaceChildren(criarEstadoListaSolicitacoes("error", mensagem));
  } finally {
    estadoPaginaSolicitacoes.carregando = false;
  }
}

function renderizarSolicitacoesAcesso() {
  const lista = document.getElementById("accessRequestList");

  if (estadoPaginaSolicitacoes.solicitacoes.length === 0) {
    lista.replaceChildren(criarEstadoListaSolicitacoes("empty", "Nenhuma solicitacao encontrada."));
    return;
  }

  const fragmento = document.createDocumentFragment();
  estadoPaginaSolicitacoes.solicitacoes.forEach((solicitacao) => {
    fragmento.append(criarCartaoSolicitacao(solicitacao));
  });
  lista.replaceChildren(fragmento);
}

function criarCartaoSolicitacao(solicitacao) {
  const cartao = criarElemento("article", "access-request-item");
  const cabecalho = criarElemento("div", "request-person-header");
  const avatar = criarElemento("div", "request-person-avatar");
  const identidade = criarElemento("div", "request-person-copy");
  const status = criarStatusSolicitacao(solicitacao.status);

  if (solicitacao.foto_url) {
    const imagem = document.createElement("img");
    imagem.src = solicitacao.foto_url;
    imagem.alt = "";
    imagem.addEventListener("error", () => {
      avatar.textContent = obterIniciaisSolicitacao(solicitacao.nome_completo);
      avatar.classList.add("fallback");
    }, { once: true });
    avatar.append(imagem);
  } else {
    avatar.textContent = obterIniciaisSolicitacao(solicitacao.nome_completo);
    avatar.classList.add("fallback");
  }

  identidade.append(
    criarElemento("strong", "", solicitacao.nome_completo || "--"),
    criarElemento("span", "", solicitacao.email || "--"),
  );
  cabecalho.append(avatar, identidade, status);

  const informacoes = criarElemento("div", "request-person-facts");
  informacoes.append(
    criarFatoSolicitacao("bi-person-badge", "Perfil", solicitacao.tipo_usuario),
    criarFatoSolicitacao("bi-diagram-3", "Departamento", solicitacao.departamento),
    criarFatoSolicitacao("bi-buildings", "Empresa", solicitacao.empresa),
  );

  const rodape = criarElemento("div", "request-person-footer");
  const data = criarElemento("span", "request-date");
  data.append(criarElemento("i", "bi bi-clock"), document.createTextNode(` ${formatarDataSolicitacao(solicitacao.criado_em)}`));
  const acoes = criarElemento("div", "request-card-actions");
  const botaoDetalhes = criarBotaoSolicitacao("bi-eye", "Mais informacoes", "details");
  botaoDetalhes.addEventListener("click", () => abrirDetalhesSolicitacao(solicitacao));
  acoes.append(botaoDetalhes);

  if (solicitacao.status === "Pendente") {
    const botaoRecusar = criarBotaoSolicitacao("bi-x-lg", "Recusar", "reject");
    const botaoAceitar = criarBotaoSolicitacao("bi-check2", "Aceitar", "approve");
    botaoRecusar.addEventListener("click", () => abrirRecusaSolicitacao(solicitacao));
    botaoAceitar.addEventListener("click", () => aprovarSolicitacao(solicitacao));
    acoes.append(botaoRecusar, botaoAceitar);
  }

  rodape.append(data, acoes);
  cartao.append(cabecalho, informacoes, rodape);

  return cartao;
}

function criarFatoSolicitacao(icone, rotulo, valor) {
  const fato = criarElemento("div", "request-fact");
  const iconeElemento = criarElemento("i", `bi ${icone}`);
  const texto = criarElemento("span");
  texto.append(criarElemento("small", "", rotulo), criarElemento("strong", "", valor || "--"));
  fato.append(iconeElemento, texto);
  return fato;
}

function criarBotaoSolicitacao(icone, rotulo, variante) {
  const botao = criarElemento("button", `request-card-button ${variante}`);
  botao.type = "button";
  botao.append(criarElemento("i", `bi ${icone}`), document.createTextNode(rotulo));
  return botao;
}

function criarStatusSolicitacao(status) {
  const classe = status === "Aprovada" ? "approved" : status === "Recusada" ? "rejected" : "pending";
  return criarElemento("span", `request-status ${classe}`, status || "Pendente");
}

function criarEstadoListaSolicitacoes(tipo, mensagem) {
  const estado = criarElemento("div", `access-request-state ${tipo}`);
  const icone = tipo === "loading" ? "bi-arrow-repeat" : tipo === "error" ? "bi-exclamation-triangle" : "bi-inbox";
  estado.append(criarElemento("i", `bi ${icone}`), criarElemento("span", "", mensagem));
  return estado;
}

function abrirDetalhesSolicitacao(solicitacao) {
  estadoPaginaSolicitacoes.solicitacaoAtual = solicitacao;
  preencherDialogoSolicitacao(solicitacao);
  document.getElementById("requestDetailsDialog")?.showModal();
}

function preencherDialogoSolicitacao(solicitacao) {
  if (!solicitacao) return;

  const formulario = document.getElementById("requestDetailsForm");
  const pendente = solicitacao.status === "Pendente";
  const statusElemento = document.getElementById("detailsStatus");

  document.getElementById("detailsRequestId").value = solicitacao.id;
  document.getElementById("detailsRequestVersion").value = solicitacao.versao;
  document.getElementById("detailsNameTitle").textContent = solicitacao.nome_completo || "Solicitacao de acesso";
  definirFotoDetalhesSolicitacao(solicitacao);
  statusElemento.replaceWith(criarStatusSolicitacao(solicitacao.status));
  document.querySelector(".dialog-person .request-status").id = "detailsStatus";

  [
    "nome_completo",
    "email",
    "tipo_usuario",
    "departamento",
    "empresa",
    "celular",
    "rg",
    "cpf",
    "data_nascimento",
  ].forEach((campo) => {
    if (formulario.elements[campo]) formulario.elements[campo].value = solicitacao[campo] || "";
  });
  formulario.elements.nova_senha.value = "";

  const analise = document.getElementById("detailsAnalysis");
  analise.hidden = pendente;
  analise.textContent = pendente
    ? ""
    : `Analisada por ${solicitacao.analisada_por_nome || "usuario autorizado"} em ${formatarDataSolicitacao(solicitacao.analisada_em)}${solicitacao.motivo_recusa ? `. Motivo: ${solicitacao.motivo_recusa}` : "."}`;

  document.getElementById("editRequestButton").hidden = !pendente;
  document.getElementById("approveRequestDialogButton").hidden = !pendente;
  document.getElementById("rejectRequestDialogButton").hidden = !pendente;
  document.getElementById("detailsMessage").textContent = "";
  alternarEdicaoSolicitacao(false);
}

function definirFotoDetalhesSolicitacao(solicitacao) {
  const foto = document.getElementById("detailsPhoto");

  if (!foto) return;

  foto.dataset.fallbackAplicado = "false";
  foto.onerror = () => {
    if (foto.dataset.fallbackAplicado === "true") return;

    foto.dataset.fallbackAplicado = "true";
    foto.src = FOTO_PADRAO_SOLICITACAO;
  };
  foto.src = solicitacao.foto_url || FOTO_PADRAO_SOLICITACAO;
}

function alternarEdicaoSolicitacao(editando) {
  const formulario = document.getElementById("requestDetailsForm");
  const pendente = estadoPaginaSolicitacoes.solicitacaoAtual?.status === "Pendente";

  formulario.querySelectorAll(".dialog-fields input:not([type='hidden']), .dialog-fields select").forEach((campo) => {
    campo.disabled = !editando;
  });
  document.getElementById("newPasswordField").hidden = !editando;
  document.getElementById("editRequestButton").hidden = editando || !pendente;
  document.getElementById("cancelEditRequestButton").hidden = !editando;
  document.getElementById("saveRequestButton").hidden = !editando;
  document.getElementById("approveRequestDialogButton").hidden = editando || !pendente;
  document.getElementById("rejectRequestDialogButton").hidden = editando || !pendente;
}

async function salvarAlteracoesSolicitacao() {
  const formulario = document.getElementById("requestDetailsForm");

  if (!formulario.checkValidity()) {
    formulario.reportValidity();
    return;
  }

  const dados = Object.fromEntries(new FormData(formulario).entries());
  dados.acao = "alterar";
  dados.id = document.getElementById("detailsRequestId").value;
  dados.versao = Number(document.getElementById("detailsRequestVersion").value);
  await executarAcaoSolicitacao(dados, "Salvando alteracoes...", async (resultado) => {
    estadoPaginaSolicitacoes.solicitacaoAtual.versao = resultado.versao;
    document.getElementById("detailsRequestVersion").value = resultado.versao;
    alternarEdicaoSolicitacao(false);
    await carregarSolicitacoesAcesso();
  });
}

async function aprovarSolicitacao(solicitacao) {
  if (!solicitacao) return;

  const confirmar = typeof window.titechConfirm === "function"
    ? await window.titechConfirm({
      title: "Aprovar este acesso?",
      text: `${solicitacao.nome_completo} podera entrar no portal imediatamente.`,
      confirmButtonText: "Aprovar acesso",
      cancelButtonText: "Cancelar",
    })
    : window.confirm(`Aprovar o acesso de ${solicitacao.nome_completo}?`);

  if (!confirmar) return;

  await executarAcaoSolicitacao(
    { acao: "aprovar", id: solicitacao.id, versao: Number(solicitacao.versao) },
    "Aprovando acesso...",
    async () => {
      document.getElementById("requestDetailsDialog")?.close();
      notificarSolicitacao("Acesso aprovado com sucesso.", "success");
      await Promise.all([carregarSolicitacoesAcesso(), atualizarResumoSolicitacoes()]);
    },
  );
}

function abrirRecusaSolicitacao(solicitacao) {
  if (!solicitacao) return;

  estadoPaginaSolicitacoes.solicitacaoAtual = solicitacao;
  document.getElementById("rejectRequestReason").value = "";
  document.getElementById("rejectRequestMessage").textContent = "";
  document.getElementById("rejectRequestDialog")?.showModal();
}

function fecharRecusaSolicitacao() {
  document.getElementById("rejectRequestDialog")?.close();
}

async function confirmarRecusaSolicitacao(evento) {
  evento.preventDefault();

  const solicitacao = estadoPaginaSolicitacoes.solicitacaoAtual;
  const motivo = document.getElementById("rejectRequestReason").value.trim();

  if (motivo.length < 3) {
    document.getElementById("rejectRequestMessage").textContent = "Informe um motivo com pelo menos 3 caracteres.";
    return;
  }

  await executarAcaoSolicitacao(
    { acao: "recusar", id: solicitacao.id, versao: Number(solicitacao.versao), motivo },
    "Registrando recusa...",
    async () => {
      fecharRecusaSolicitacao();
      document.getElementById("requestDetailsDialog")?.close();
      notificarSolicitacao("Solicitacao recusada.", "info");
      await Promise.all([carregarSolicitacoesAcesso(), atualizarResumoSolicitacoes()]);
    },
    "rejectRequestMessage",
  );
}

async function executarAcaoSolicitacao(dados, mensagemEspera, aoConcluir, idMensagem = "detailsMessage") {
  const mensagem = document.getElementById(idMensagem);
  mensagem.textContent = mensagemEspera;
  mensagem.className = "details-message loading";

  try {
    const resposta = await fetch(ENDPOINT_SOLICITACOES_ACESSO, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-Token": obterTokenCsrfSolicitacoes(),
      },
      body: JSON.stringify(dados),
    });
    const resultado = await resposta.json();

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel concluir a acao.");
    }

    mensagem.textContent = resultado.message || "Acao concluida.";
    mensagem.className = "details-message success";
    await aoConcluir(resultado);
  } catch (erro) {
    mensagem.textContent = erro instanceof Error ? erro.message : "Nao foi possivel concluir a acao.";
    mensagem.className = "details-message error";
  }
}

async function atualizarResumoSolicitacoes() {
  try {
    const resposta = await fetch(`${ENDPOINT_SOLICITACOES_ACESSO}?acao=resumo`, {
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json();

    if (!resposta.ok || !resultado.ok) return;

    document.getElementById("requestMetricPending").textContent = resultado.resumo.pendentes;
    document.getElementById("requestMetricApproved").textContent = resultado.resumo.aprovadas;
    document.getElementById("requestMetricRejected").textContent = resultado.resumo.recusadas;
  } catch {
    // O resumo inicial do PHP continua visivel se a atualizacao falhar.
  }
}

function fecharDetalhesSolicitacao() {
  document.getElementById("requestDetailsDialog")?.close();
}

function obterTokenCsrfSolicitacoes() {
  return document.querySelector('meta[name="titech-csrf-token"]')?.content || "";
}

function formatarDataSolicitacao(valor) {
  if (!valor) return "--";

  const data = new Date(valor);
  return Number.isNaN(data.getTime())
    ? "--"
    : new Intl.DateTimeFormat("pt-BR", { dateStyle: "short", timeStyle: "short" }).format(data);
}

function obterIniciaisSolicitacao(nome) {
  return String(nome || "U")
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((parte) => parte[0] || "")
    .join("")
    .toUpperCase();
}

function notificarSolicitacao(mensagem, tipo) {
  if (typeof window.titechToast === "function") {
    window.titechToast(mensagem, tipo);
  }
}

function criarElemento(tag, classe = "", texto = "") {
  const elemento = document.createElement(tag);
  if (classe) elemento.className = classe;
  if (texto !== "") elemento.textContent = texto;
  return elemento;
}
