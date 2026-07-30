// Valida e envia o cadastro de ativos, atualizando a lista recente sem recarregar a página.
// Usa os diálogos e avisos globais fornecidos pelos módulos compartilhados da interface.

const ATRASO_REDIRECIONAMENTO_MS = 900;
const QUANTIDADE_PN_MINIMA = 1;
const QUANTIDADE_PN_MAXIMA = 100;
// Cada modo informa quais campos de identificacao devem ficar disponiveis no formulario.
const CONFIGURACAO_RASTREABILIDADE = {
  nao_possui: { pn: false, sn: false },
  somente_pn: { pn: true, sn: false },
  somente_sn: { pn: false, sn: true },
  ambos: { pn: true, sn: true },
};

document.addEventListener("DOMContentLoaded", inicializarPagina);

let preservarMensagemNaProximaRedefinicao = false;

function inicializarPagina() {
  executarAuxiliarPagina("iniciarAnimacaoPagina");
  executarAuxiliarPagina("carregarTemaSalvo");
  executarAuxiliarPagina("configurarAlternadorTema");
  executarAuxiliarPagina("configurarBarraLateral");
  executarAuxiliarPagina("configurarGruposNavegacao");
  configurarFormularioAtivo();
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

  const confirmado = await confirmarCadastroAtivo(formulario);

  if (!confirmado) {
    return;
  }

  definirCarregandoBotao(botaoEnviar, true);
  definirMensagemFormulario("", "");

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
      throw new Error(resultado.message || "Nao foi possivel cadastrar o ativo.");
    }

    definirMensagemFormulario(resultado.message || "Ativo cadastrado com sucesso.", "success");
    const ativosCriados = Array.isArray(resultado.ativos)
      ? resultado.ativos.filter(Boolean)
      : [resultado.ativo].filter(Boolean);

    ativosCriados.forEach(inserirInicioAtivoRecente);
    atualizarMetricasAtivo(ativosCriados);
    preservarMensagemNaProximaRedefinicao = true;
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
  const quantidade = rastreabilidade === "somente_pn" ? obterQuantidadePn(formulario) : QUANTIDADE_PN_MINIMA;
  const textoConfirmacao = quantidade > QUANTIDADE_PN_MINIMA
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
  const quantidade = obterQuantidadePn(formulario);

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

  if (rastreabilidade === "somente_pn" && !quantidade) {
    return `Informe uma quantidade entre ${QUANTIDADE_PN_MINIMA} e ${QUANTIDADE_PN_MAXIMA}.`;
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
  alternarCampoQuantidadePn(formulario, rastreabilidade === "somente_pn");
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

  botaoDecrementar?.addEventListener("click", () => definirQuantidadePn(formulario, obterQuantidadePn(formulario) - 1));
  botaoIncrementar?.addEventListener("click", () => definirQuantidadePn(formulario, obterQuantidadePn(formulario) + 1));
  campoEntrada.addEventListener("input", () => definirQuantidadePn(formulario, campoEntrada.value));
  definirQuantidadePn(formulario, campoEntrada.value);
}

function alternarCampoQuantidadePn(formulario, deveExibir) {
  const envoltorio = formulario.querySelector("[data-pn-quantity-field]");
  const campoEntrada = formulario.querySelector("[data-quantity-input]");

  if (envoltorio) {
    envoltorio.hidden = !deveExibir;
  }

  if (!(campoEntrada instanceof HTMLInputElement)) {
    return;
  }

  campoEntrada.disabled = !deveExibir;

  if (!deveExibir) {
    definirQuantidadePn(formulario, QUANTIDADE_PN_MINIMA);
  }
}

function obterQuantidadePn(formulario) {
  const campoEntrada = formulario.querySelector("[data-quantity-input]");

  if (!(campoEntrada instanceof HTMLInputElement)) {
    return QUANTIDADE_PN_MINIMA;
  }

  const quantidade = Number.parseInt(campoEntrada.value || "", 10);

  return Number.isInteger(quantidade) ? quantidade : 0;
}

function definirQuantidadePn(formulario, valor) {
  const campoEntrada = formulario.querySelector("[data-quantity-input]");
  const botaoDecrementar = formulario.querySelector("[data-quantity-decrement]");
  const botaoIncrementar = formulario.querySelector("[data-quantity-increment]");
  const quantidade = Math.min(
    QUANTIDADE_PN_MAXIMA,
    Math.max(QUANTIDADE_PN_MINIMA, Number.parseInt(String(valor || ""), 10) || QUANTIDADE_PN_MINIMA),
  );

  if (campoEntrada instanceof HTMLInputElement) {
    campoEntrada.value = String(quantidade);
  }

  if (botaoDecrementar instanceof HTMLButtonElement) {
    botaoDecrementar.disabled = quantidade <= QUANTIDADE_PN_MINIMA;
  }

  if (botaoIncrementar instanceof HTMLButtonElement) {
    botaoIncrementar.disabled = quantidade >= QUANTIDADE_PN_MAXIMA;
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
  const detalhes = [String(ativo.status || "Disponivel")];

  if (numeroParte) {
    detalhes.push(`PN ${numeroParte}`);
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


