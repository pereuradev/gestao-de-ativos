// Valida e envia o cadastro de ativos, atualizando a lista recente sem recarregar a página.
// Usa os diálogos e avisos globais fornecidos pelos módulos compartilhados da interface.

const ATRASO_REDIRECIONAMENTO_MS = 900;
const QUANTIDADE_ATIVOS_MINIMA = 1;
const QUANTIDADE_ATIVOS_MAXIMA = 100;
const TAMANHO_MAXIMO_NUMERO_SERIE = 120;
// Cada modo informa quais campos de identificacao devem ficar disponiveis no formulario.
const CONFIGURACAO_RASTREABILIDADE = {
  nao_possui: { pn: false, sn: false, quantidade: false, numerosSerieModal: false },
  somente_pn: { pn: true, sn: false, quantidade: true, numerosSerieModal: false },
  somente_sn: { pn: false, sn: true, quantidade: false, numerosSerieModal: false },
  ambos: { pn: true, sn: false, quantidade: true, numerosSerieModal: true },
};

document.addEventListener("DOMContentLoaded", inicializarPagina);

let preservarMensagemNaProximaRedefinicao = false;
let resolverModalNumerosSerie = null;
let numerosSerieTemporarios = [];

function inicializarPagina() {
  executarAuxiliarPagina("iniciarAnimacaoPagina");
  executarAuxiliarPagina("carregarTemaSalvo");
  executarAuxiliarPagina("configurarAlternadorTema");
  executarAuxiliarPagina("configurarBarraLateral");
  executarAuxiliarPagina("configurarGruposNavegacao");
  configurarFormularioAtivo();
  configurarModalNumerosSerie();
}

function executarAuxiliarPagina(nomeAuxiliar) {
  const auxiliar = window[nomeAuxiliar];

  if (typeof auxiliar === "function") {
    auxiliar();
  }
}

// O formulário mantém o envio tradicional como fallback, mas usa AJAX quando o JavaScript está ativo.
function configurarFormularioAtivo() {
  const formulario = document.getElementById("assetForm");

  if (!formulario) return;

  formulario.addEventListener("submit", enviarFormularioAtivo);
  formulario.addEventListener("reset", () => {
    numerosSerieTemporarios = [];
    setTimeout(() => atualizarCamposRastreabilidade(formulario), 0);

    if (preservarMensagemNaProximaRedefinicao) {
      preservarMensagemNaProximaRedefinicao = false;
      return;
    }

    setTimeout(() => definirMensagemFormulario("", ""), 0);
  });
  configurarControlesRastreabilidade(formulario);
  configurarControlesQuantidade(formulario);
}

// Só atualiza métricas e registros recentes depois de receber confirmação do backend.
async function enviarFormularioAtivo(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("assetSubmitButton");
  const erro = validarFormularioAtivo(formulario);

  if (erro) {
    definirMensagemFormulario(erro, "error");
    return;
  }

  const rastreabilidade = obterRastreabilidadeSelecionada(formulario);
  let numerosSerie = [];

  if (rastreabilidade === "ambos") {
    const quantidade = obterQuantidadeAtivos(formulario);
    const numerosSerieInformados = await solicitarNumerosSerie(quantidade, formulario);

    if (!numerosSerieInformados) {
      return;
    }

    numerosSerie = numerosSerieInformados;
    numerosSerieTemporarios = [...numerosSerieInformados];
  }

  const confirmado = await confirmarCadastroAtivo(formulario);

  if (!confirmado) {
    return;
  }

  definirCarregandoBotao(botaoEnviar, true);
  definirMensagemFormulario("", "");

  try {
    const dadosCadastro = new FormData(formulario);

    numerosSerie.forEach((numeroSerie) => {
      dadosCadastro.append("numeros_serie[]", numeroSerie);
    });

    const resposta = await fetch(formulario.action, {
      method: "POST",
      body: dadosCadastro,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel cadastrar o ativo.");
    }

    definirMensagemFormulario(resultado.message || "Ativo cadastrado com sucesso.", "success");
    const ativosCriados = Array.isArray(resultado.ativos)
      ? resultado.ativos.filter(Boolean)
      : [resultado.ativo].filter(Boolean);

    ativosCriados.forEach(inserirInicioAtivoRecente);
    atualizarMetricasAtivo(ativosCriados);
    preservarMensagemNaProximaRedefinicao = true;
    numerosSerieTemporarios = [];
    formulario.reset();

    setTimeout(() => {
      definirMensagemFormulario("", "");
    }, ATRASO_REDIRECIONAMENTO_MS * 3);
  } catch (erro) {
    definirMensagemFormulario(erro.message || "Nao foi possivel cadastrar o ativo.", "error");
  } finally {
    definirCarregandoBotao(botaoEnviar, false);
  }
}

// A confirmação compartilhada evita cadastros acidentais antes do envio dos dados.
async function confirmarCadastroAtivo(formulario) {
  const dados = new FormData(formulario);
  const nomeAtivo = String(dados.get("nome") || "este ativo").trim() || "este ativo";
  const rastreabilidade = obterRastreabilidadeSelecionada(formulario);
  const configuracao = CONFIGURACAO_RASTREABILIDADE[rastreabilidade];
  const quantidade = configuracao?.quantidade
    ? obterQuantidadeAtivos(formulario)
    : QUANTIDADE_ATIVOS_MINIMA;
  const textoConfirmacao = quantidade > QUANTIDADE_ATIVOS_MINIMA
    ? `Confirme para cadastrar ${quantidade} unidades de ${nomeAtivo} no inventario.`
    : `Confirme para cadastrar ${nomeAtivo} no inventario.`;

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar ativo?",
      text: textoConfirmacao,
      confirmButtonText: "Cadastrar ativo",
      cancelButtonText: "Revisar dados",
      icon: "info",
    });
  }

  return window.confirm(textoConfirmacao);
}

function validarFormularioAtivo(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const categoria = String(dados.get("categoria_id") || "").trim();
  const status = String(dados.get("status") || "").trim();
  const rastreabilidade = obterRastreabilidadeSelecionada(formulario);
  const configuracao = CONFIGURACAO_RASTREABILIDADE[rastreabilidade];
  const numeroParte = String(dados.get("part_number") || "").trim();
  const numeroSerie = String(dados.get("numero_serie") || "").trim();
  const imei = String(dados.get("imei") || "").trim();
  const quantidade = obterQuantidadeAtivos(formulario);

  if (!nome || !categoria || !status) {
    return "Preencha nome, categoria e status para cadastrar o ativo.";
  }

  if (nome.length < 2) {
    return "O nome do ativo precisa ter pelo menos 2 caracteres.";
  }

  if (!configuracao) {
    return "Selecione uma opcao de rastreabilidade valida.";
  }

  if (configuracao.pn && !numeroParte) {
    return "Informe o PN para a rastreabilidade escolhida.";
  }

  if (configuracao.sn && !numeroSerie) {
    return "Informe o numero de serie para a rastreabilidade escolhida.";
  }

  if (configuracao.quantidade && !quantidade) {
    return `Informe uma quantidade entre ${QUANTIDADE_ATIVOS_MINIMA} e ${QUANTIDADE_ATIVOS_MAXIMA}.`;
  }

  if (configuracao.quantidade && quantidade > QUANTIDADE_ATIVOS_MINIMA && imei) {
    return "Para cadastrar mais de uma unidade, deixe o IMEI vazio.";
  }

  return "";
}

function configurarControlesRastreabilidade(formulario) {
  formulario.querySelectorAll('input[name="rastreabilidade"]').forEach((opcao) => {
    opcao.addEventListener("change", () => atualizarCamposRastreabilidade(formulario));
  });

  atualizarCamposRastreabilidade(formulario);
}

function obterRastreabilidadeSelecionada(formulario) {
  return formulario.querySelector('input[name="rastreabilidade"]:checked')?.value || "nao_possui";
}

function atualizarCamposRastreabilidade(formulario) {
  const rastreabilidade = obterRastreabilidadeSelecionada(formulario);
  const configuracao = CONFIGURACAO_RASTREABILIDADE[rastreabilidade] || CONFIGURACAO_RASTREABILIDADE.nao_possui;

  // Desabilitar campos escondidos impede que valores antigos sejam enviados ao backend.
  alternarCampoRastreabilidade(formulario, "pn", configuracao.pn);
  alternarCampoRastreabilidade(formulario, "sn", configuracao.sn);
  alternarCampoQuantidade(formulario, configuracao.quantidade, rastreabilidade);

  if (!configuracao.numerosSerieModal) {
    numerosSerieTemporarios = [];
    fecharModalNumerosSerie();
  }
}

function alternarCampoRastreabilidade(formulario, campo, deveExibir) {
  const envoltorio = formulario.querySelector(`[data-traceability-field="${campo}"]`);
  const campoEntrada = formulario.querySelector(`[data-traceability-input="${campo}"]`);

  if (envoltorio) {
    envoltorio.hidden = !deveExibir;
  }

  if (!campoEntrada) {
    return;
  }

  campoEntrada.disabled = !deveExibir;
  campoEntrada.required = deveExibir;
}

function configurarControlesQuantidade(formulario) {
  const botaoDecrementar = formulario.querySelector("[data-quantity-decrement]");
  const botaoIncrementar = formulario.querySelector("[data-quantity-increment]");
  const campoEntrada = formulario.querySelector("[data-quantity-input]");

  if (!(campoEntrada instanceof HTMLInputElement)) {
    return;
  }

  botaoDecrementar?.addEventListener("click", () =>
    definirQuantidadeAtivos(formulario, obterQuantidadeAtivos(formulario) - 1),
  );
  botaoIncrementar?.addEventListener("click", () =>
    definirQuantidadeAtivos(formulario, obterQuantidadeAtivos(formulario) + 1),
  );
  campoEntrada.addEventListener("input", () => definirQuantidadeAtivos(formulario, campoEntrada.value));
  definirQuantidadeAtivos(formulario, campoEntrada.value);
}

function alternarCampoQuantidade(formulario, deveExibir, rastreabilidade) {
  const envoltorio = formulario.querySelector("[data-quantity-field]");
  const campoEntrada = formulario.querySelector("[data-quantity-input]");
  const dica = formulario.querySelector("[data-quantity-hint]");

  if (envoltorio) {
    envoltorio.hidden = !deveExibir;
  }

  if (!(campoEntrada instanceof HTMLInputElement)) {
    return;
  }

  campoEntrada.disabled = !deveExibir;

  if (dica) {
    dica.textContent =
      rastreabilidade === "ambos"
        ? "Depois, informe um SN diferente para cada unidade no modal."
        : "Cada unidade com este PN será cadastrada separadamente.";
  }

  if (!deveExibir) {
    definirQuantidadeAtivos(formulario, QUANTIDADE_ATIVOS_MINIMA);
  }
}

function obterQuantidadeAtivos(formulario) {
  const campoEntrada = formulario.querySelector("[data-quantity-input]");

  if (!(campoEntrada instanceof HTMLInputElement)) {
    return QUANTIDADE_ATIVOS_MINIMA;
  }

  const quantidade = Number.parseInt(campoEntrada.value || "", 10);

  return Number.isInteger(quantidade) ? quantidade : 0;
}

function definirQuantidadeAtivos(formulario, valor) {
  const campoEntrada = formulario.querySelector("[data-quantity-input]");
  const botaoDecrementar = formulario.querySelector("[data-quantity-decrement]");
  const botaoIncrementar = formulario.querySelector("[data-quantity-increment]");
  const quantidade = Math.min(
    QUANTIDADE_ATIVOS_MAXIMA,
    Math.max(
      QUANTIDADE_ATIVOS_MINIMA,
      Number.parseInt(String(valor || ""), 10) || QUANTIDADE_ATIVOS_MINIMA,
    ),
  );

  if (campoEntrada instanceof HTMLInputElement) {
    campoEntrada.value = String(quantidade);
  }

  if (botaoDecrementar instanceof HTMLButtonElement) {
    botaoDecrementar.disabled = quantidade <= QUANTIDADE_ATIVOS_MINIMA;
  }

  if (botaoIncrementar instanceof HTMLButtonElement) {
    botaoIncrementar.disabled = quantidade >= QUANTIDADE_ATIVOS_MAXIMA;
  }

  if (numerosSerieTemporarios.length > quantidade) {
    numerosSerieTemporarios = numerosSerieTemporarios.slice(0, quantidade);
  }
}

function configurarModalNumerosSerie() {
  const dialogo = document.getElementById("serialNumbersDialog");
  const formularioModal = dialogo?.querySelector("[data-serial-numbers-form]");

  if (
    typeof HTMLDialogElement === "undefined" ||
    !(dialogo instanceof HTMLDialogElement) ||
    !(formularioModal instanceof HTMLFormElement) ||
    dialogo.dataset.configurado === "true"
  ) {
    return;
  }

  dialogo.dataset.configurado = "true";

  dialogo.querySelectorAll("[data-serial-numbers-close], [data-serial-numbers-cancel]").forEach((botao) => {
    botao.addEventListener("click", () => dialogo.close("cancelado"));
  });

  formularioModal.addEventListener("submit", (evento) => {
    evento.preventDefault();

    const numerosSerie = obterNumerosSerieModal(dialogo);
    const erro = validarNumerosSerieModal(numerosSerie);

    if (erro) {
      definirErroModalNumerosSerie(dialogo, erro.mensagem, erro.indice);
      return;
    }

    numerosSerieTemporarios = [...numerosSerie];
    concluirModalNumerosSerie(numerosSerie);
    dialogo.close("confirmado");
  });

  dialogo.addEventListener("close", () => {
    if (dialogo.returnValue === "confirmado") {
      return;
    }

    numerosSerieTemporarios = obterNumerosSerieModal(dialogo);
    concluirModalNumerosSerie(null);
  });
}

function solicitarNumerosSerie(quantidade, formularioAtivo) {
  const dialogo = document.getElementById("serialNumbersDialog");

  if (
    typeof HTMLDialogElement === "undefined" ||
    !(dialogo instanceof HTMLDialogElement) ||
    typeof dialogo.showModal !== "function"
  ) {
    definirMensagemFormulario(
      "O navegador nao conseguiu abrir o preenchimento dos numeros de serie.",
      "error",
    );
    return Promise.resolve(null);
  }

  montarCamposNumerosSerie(dialogo, quantidade, formularioAtivo);
  dialogo.returnValue = "";
  dialogo.showModal();

  requestAnimationFrame(() => {
    dialogo.querySelector("[data-serial-number-input]")?.focus();
  });

  return new Promise((resolver) => {
    resolverModalNumerosSerie = resolver;
  });
}

function montarCamposNumerosSerie(dialogo, quantidade, formularioAtivo) {
  const conteiner = dialogo.querySelector("[data-serial-numbers-fields]");
  const resumo = document.getElementById("serialNumbersDialogSummary");
  const contador = dialogo.querySelector("[data-serial-numbers-counter]");
  const numeroParte = String(
    formularioAtivo.querySelector('[name="part_number"]')?.value || "",
  ).trim();

  if (!conteiner) {
    return;
  }

  conteiner.replaceChildren();
  definirErroModalNumerosSerie(dialogo, "");

  if (resumo) {
    resumo.textContent = numeroParte
      ? `Preencha um SN para cada unidade do PN ${numeroParte}.`
      : "Preencha um SN diferente para cada unidade.";
  }

  if (contador) {
    contador.textContent = `${quantidade} ${quantidade === 1 ? "unidade" : "unidades"}`;
  }

  for (let indice = 0; indice < quantidade; indice += 1) {
    const identificador = `serialNumberUnit${indice + 1}`;
    const rotulo = criarElemento("label", "serial-number-field");
    const titulo = criarElemento("span", "serial-number-label", `Unidade ${indice + 1}`);
    const envoltorio = criarElemento("div", "input-shell");
    const icone = criarElemento("i", "bi bi-upc-scan");
    const campoEntrada = document.createElement("input");

    campoEntrada.id = identificador;
    campoEntrada.name = "numeros_serie[]";
    campoEntrada.type = "text";
    campoEntrada.placeholder = `Informe o SN da unidade ${indice + 1}`;
    campoEntrada.autocomplete = "off";
    campoEntrada.maxLength = TAMANHO_MAXIMO_NUMERO_SERIE;
    campoEntrada.required = true;
    campoEntrada.dataset.serialNumberInput = "";
    campoEntrada.dataset.serialIndex = String(indice);
    campoEntrada.value = numerosSerieTemporarios[indice] || "";
    campoEntrada.setAttribute("aria-label", `SN da unidade ${indice + 1}`);
    campoEntrada.addEventListener("input", () => definirErroModalNumerosSerie(dialogo, ""));

    envoltorio.append(icone, campoEntrada);
    rotulo.append(titulo, envoltorio);
    conteiner.append(rotulo);
  }
}

function obterNumerosSerieModal(dialogo) {
  return Array.from(dialogo.querySelectorAll("[data-serial-number-input]")).map((campo) =>
    String(campo.value || "").trim(),
  );
}

function validarNumerosSerieModal(numerosSerie) {
  const unidadesPorNumeroSerie = new Map();

  for (let indice = 0; indice < numerosSerie.length; indice += 1) {
    const numeroSerie = numerosSerie[indice];

    if (!numeroSerie) {
      return {
        mensagem: `Informe o SN da unidade ${indice + 1}.`,
        indice,
      };
    }

    if (numeroSerie.length > TAMANHO_MAXIMO_NUMERO_SERIE) {
      return {
        mensagem: `O SN da unidade ${indice + 1} deve ter no maximo ${TAMANHO_MAXIMO_NUMERO_SERIE} caracteres.`,
        indice,
      };
    }

    const chaveNumeroSerie = numeroSerie.toLocaleLowerCase("pt-BR");

    if (unidadesPorNumeroSerie.has(chaveNumeroSerie)) {
      return {
        mensagem: `O SN da unidade ${indice + 1} esta repetido. Informe um SN diferente para cada unidade.`,
        indice,
      };
    }

    unidadesPorNumeroSerie.set(chaveNumeroSerie, indice);
  }

  return null;
}

function definirErroModalNumerosSerie(dialogo, mensagem, indice = null) {
  const elementoErro = dialogo.querySelector("[data-serial-numbers-error]");

  dialogo.querySelectorAll("[data-serial-number-input]").forEach((campo) => {
    campo.removeAttribute("aria-invalid");
  });

  if (elementoErro) {
    elementoErro.textContent = mensagem;
    elementoErro.hidden = !mensagem;
  }

  if (Number.isInteger(indice)) {
    const campoComErro = dialogo.querySelector(`[data-serial-index="${indice}"]`);

    campoComErro?.setAttribute("aria-invalid", "true");
    campoComErro?.focus();
  }
}

function concluirModalNumerosSerie(resultado) {
  const resolver = resolverModalNumerosSerie;

  resolverModalNumerosSerie = null;
  resolver?.(resultado);
}

function fecharModalNumerosSerie() {
  const dialogo = document.getElementById("serialNumbersDialog");

  if (
    typeof HTMLDialogElement !== "undefined" &&
    dialogo instanceof HTMLDialogElement &&
    dialogo.open
  ) {
    dialogo.close("cancelado");
  }
}

function definirCarregandoBotao(botao, estaCarregando) {
  if (!botao) return;

  botao.disabled = estaCarregando;

  if (estaCarregando) {
    botao.replaceChildren(
      criarElemento("i", "bi bi-arrow-repeat"),
      criarElemento("span", "", "Cadastrando..."),
    );
    return;
  }

  botao.replaceChildren(
    criarElemento("i", "bi bi-plus-circle"),
    criarElemento("span", "", "Cadastrar ativo"),
  );
}

function definirMensagemFormulario(mensagem, tipo) {
  const elemento = document.getElementById("assetFormMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function inserirInicioAtivoRecente(ativo) {
  const lista = document.getElementById("recentAssetList");

  if (!lista || !ativo) return;

  lista.querySelector(".empty-state")?.remove();

  const item = criarElemento("div", "recent-asset-item");
  const conteudo = criarElemento("div");
  const titulo = criarElemento("strong", "", String(ativo.nome || "Novo ativo"));
  const numeroParte = String(ativo.part_number || "").trim();
  const numeroSerie = String(ativo.numero_serie || "").trim();
  const detalhes = [String(ativo.status || "Disponivel")];

  if (numeroParte) {
    detalhes.push(`PN ${numeroParte}`);
  }

  if (numeroSerie) {
    detalhes.push(`SN ${numeroSerie}`);
  }

  const detalhe = criarElemento("span", "", detalhes.join(" - "));
  const data = criarElemento("small", "", "Agora");

  conteudo.append(titulo, detalhe);
  item.append(conteudo, data);
  lista.prepend(item);
}

function atualizarMetricasAtivo(ativos) {
  const quantidadeCriados = ativos.length;

  if (quantidadeCriados <= 0) {
    return;
  }

  incrementarMetrica("totalAssetsMetric", quantidadeCriados);
  incrementarMetrica("availableAssetsMetric", ativos.filter(ehAtivoDisponivel).length);
}

function incrementarMetrica(id, quantidade) {
  const elemento = document.getElementById(id);

  if (!elemento || quantidade <= 0) {
    return;
  }

  const atual = Number.parseInt(elemento.textContent || "0", 10);

  elemento.textContent = String((Number.isFinite(atual) ? atual : 0) + quantidade);
}

function ehAtivoDisponivel(ativo) {
  return normalizarTextoAtivo(ativo?.status) === "disponivel";
}

function normalizarTextoAtivo(valor) {
  return String(valor || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function criarElemento(etiqueta, nomeClasse = "", texto = "") {
  const elemento = document.createElement(etiqueta);

  if (nomeClasse) {
    elemento.className = nomeClasse;
  }

  if (texto) {
    elemento.textContent = texto;
  }

  return elemento;
}


