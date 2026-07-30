// Controla filtros, modal, atualização e exclusão de ativos.
// Mensagens entre recarregamentos usam sessionStorage para preservar o retorno da operação.

const ATRASO_OCULTACAO_MENSAGEM_MS = 2800;
const CHAVE_ARMAZENAMENTO_MENSAGEM_PAGINA = "titech-edicao-ativos-message";
// Cada modo informa quais campos de identificacao devem ficar disponiveis no modal.
const CONFIGURACAO_RASTREABILIDADE = {
  nao_possui: { pn: false, sn: false },
  somente_pn: { pn: true, sn: false },
  somente_sn: { pn: false, sn: true },
  ambos: { pn: true, sn: true },
};

let temporizadorBuscaAtivo = null;

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosAtivo();
  configurarAcoesAtivo();
  configurarModalEdicao();
  restaurarMensagemPaginaPendente();
}

// Filtros e paginação são processados pelo servidor; a busca é enviada após um pequeno debounce.
function configurarFiltrosAtivo() {
  const formulario = document.getElementById("assetFiltersForm");
  const campoEntradaBusca = document.getElementById("assetSearch");
  const valorBusca = document.getElementById("assetSearchValue");

  if (!formulario) {
    return;
  }

  if (campoEntradaBusca && valorBusca) {
    campoEntradaBusca.value = valorBusca.value || "";
  }

  campoEntradaBusca?.addEventListener("input", () => {
    window.clearTimeout(temporizadorBuscaAtivo);

    temporizadorBuscaAtivo = window.setTimeout(() => {
      sincronizarValorBusca();
      redefinirPaginaAtivoEEnviar(formulario);
    }, 450);
  });

  [
    "assetStatusFilter",
    "assetCategoryFilter",
    "assetBrandFilter",
    "assetPerPage",
  ].forEach((idCampo) => {
    document.getElementById(idCampo)?.addEventListener("change", () => {
      sincronizarValorBusca();
      redefinirPaginaAtivoEEnviar(formulario);
    });
  });

  formulario.addEventListener("submit", sincronizarValorBusca);

  document.getElementById("clearAssetFilters")?.addEventListener("click", () => {
    window.location.href = "edicao-ativos.php";
  });
}

function sincronizarValorBusca() {
  const campoEntradaBusca = document.getElementById("assetSearch");
  const valorBusca = document.getElementById("assetSearchValue");

  if (campoEntradaBusca && valorBusca) {
    valorBusca.value = campoEntradaBusca.value;
  }
}

function redefinirPaginaAtivoEEnviar(formulario) {
  const campoEntradaPagina = formulario.querySelector('input[name="pagina"]');

  if (campoEntradaPagina) {
    campoEntradaPagina.value = "1";
  }

  if (typeof formulario.requestSubmit === "function") {
    formulario.requestSubmit();
    return;
  }

  formulario.submit();
}

function configurarAcoesAtivo() {
  document.getElementById("assetTableBody")?.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-asset-action]");

    if (!botao) return;

    const linha = botao.closest(".asset-row");

    if (!linha) return;

    if (botao.dataset.assetAction === "edit") {
      abrirModalEdicao(linha);
      return;
    }

    if (botao.dataset.assetAction === "delete") {
      excluirAtivo(linha, botao);
    }
  });
}

function configurarModalEdicao() {
  const formulario = document.getElementById("assetEditForm");
  const modal = document.getElementById("assetEditModal");

  formulario?.addEventListener("submit", enviarFormularioEdicao);
  if (formulario) {
    configurarControlesRastreabilidade(formulario);
  }

  document.querySelectorAll("[data-close-asset-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalEdicao);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalEdicao();
    }
  });
}

// Os atributos data-* da linha abastecem o formulário sem uma consulta adicional.
function abrirModalEdicao(linha) {
  const modal = document.getElementById("assetEditModal");
  const formulario = document.getElementById("assetEditForm");

  if (!modal) return;

  const numeroSerie = linha.dataset.serial || "";
  const numeroParte = linha.dataset.partNumber || "";

  definirValorCampoEntrada("editAssetId", linha.dataset.id || "");
  definirValorCampoEntrada("editAssetName", linha.dataset.name || "");
  definirValorCampoEntrada("editAssetCategory", linha.dataset.categoryId || "");
  definirValorSeletor("editAssetStatus", linha.dataset.statusRaw || "");
  definirValorSeletor("editAssetBrand", linha.dataset.brandRaw || "");
  definirValorCampoEntrada("editAssetLocation", linha.dataset.locationId || "");
  definirValorCampoEntrada("editAssetSerial", numeroSerie);
  definirValorCampoEntrada("editAssetPartNumber", numeroParte);
  definirValorCampoEntrada("editAssetProperty", linha.dataset.property || "");
  definirValorCampoEntrada("editAssetImei", linha.dataset.imei || "");
  definirValorCampoEntrada("editAssetDatasheet", linha.dataset.datasheet || "");
  definirValorCampoEntrada("editAssetDescription", linha.dataset.description || "");
  definirRastreabilidadeSelecionada(formulario, obterRastreabilidadePelosValores(numeroParte, numeroSerie));
  atualizarCamposRastreabilidade(formulario);

  limparMensagemEdicao();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editAssetName")?.focus();
}

function fecharModalEdicao() {
  const modal = document.getElementById("assetEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

// A página só é recarregada depois que o backend confirma a atualização.
async function enviarFormularioEdicao(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveAssetButton");
  const erro = validarFormularioAtivo(formulario);

  if (erro) {
    definirMensagemEdicao(erro, "error");
    return;
  }

  const confirmado = await confirmarEdicaoAtivo(formulario);

  if (!confirmado) {
    return;
  }

  definirCarregando(botaoEnviar, true, "Salvando...");
  limparMensagemEdicao();

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
      throw new Error(resultado.message || "Nao foi possivel alterar o ativo.");
    }

    recarregarPaginaAtivoComMensagem(resultado.message || "Ativo alterado com sucesso.", "success");
  } catch (erro) {
    definirMensagemEdicao(erro.message || "Nao foi possivel alterar o ativo.", "error");
  } finally {
    definirCarregando(botaoEnviar, false);
  }
}

async function confirmarEdicaoAtivo(formulario) {
  const dados = new FormData(formulario);
  const nomeAtivo = String(dados.get("nome") || "este ativo").trim() || "este ativo";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Confirmar edicao?",
      text: `Confirme para salvar as alteracoes de ${nomeAtivo}.`,
      confirmButtonText: "Salvar edicao",
      cancelButtonText: "Continuar editando",
      icon: "warning",
    });
  }

  return window.confirm(`Salvar as alteracoes de ${nomeAtivo}?`);
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

  if (!nome || !categoria || !status) {
    return "Preencha nome, categoria e status do ativo.";
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

  return "";
}

// A rastreabilidade alterna os campos visiveis sem permitir envio de valores escondidos.
function configurarControlesRastreabilidade(formulario) {
  formulario.querySelectorAll('input[name="rastreabilidade"]').forEach((opcao) => {
    opcao.addEventListener("change", () => atualizarCamposRastreabilidade(formulario));
  });

  atualizarCamposRastreabilidade(formulario);
}

function obterRastreabilidadePelosValores(numeroParte, numeroSerie) {
  const possuiNumeroParte = String(numeroParte || "").trim() !== "";
  const possuiNumeroSerie = String(numeroSerie || "").trim() !== "";

  if (possuiNumeroParte && possuiNumeroSerie) {
    return "ambos";
  }

  if (possuiNumeroParte) {
    return "somente_pn";
  }

  if (possuiNumeroSerie) {
    return "somente_sn";
  }

  return "nao_possui";
}

function definirRastreabilidadeSelecionada(formulario, valor) {
  formulario?.querySelector(`input[name="rastreabilidade"][value="${valor}"]`)?.click();
}

function obterRastreabilidadeSelecionada(formulario) {
  return formulario?.querySelector('input[name="rastreabilidade"]:checked')?.value || "nao_possui";
}

function atualizarCamposRastreabilidade(formulario) {
  if (!formulario) return;

  const configuracao = CONFIGURACAO_RASTREABILIDADE[obterRastreabilidadeSelecionada(formulario)] || CONFIGURACAO_RASTREABILIDADE.nao_possui;

  // Campos ocultos ficam desabilitados para o backend receber somente a escolha atual.
  alternarCampoRastreabilidade(formulario, "pn", configuracao.pn);
  alternarCampoRastreabilidade(formulario, "sn", configuracao.sn);
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

// A exclusão exige confirmação e token CSRF antes de remover o registro.
async function excluirAtivo(linha, botao) {
  const nome = linha.dataset.name || "este ativo";
  const confirmado = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${nome}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir ativo",
      icon: "warning",
    })
    : window.confirm(`Excluir ${nome}? Esta acao nao pode ser desfeita.`);

  if (!confirmado) return;

  const corpoRequisicao = new FormData();
  corpoRequisicao.append("csrf_token", obterTokenCsrf());
  corpoRequisicao.append("id", linha.dataset.id || "");

  definirCarregando(botao, true, "Excluindo...");
  limparMensagemPagina();

  try {
    const resposta = await fetch("../Backend/excluir-ativo.php", {
      method: "POST",
      body: corpoRequisicao,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel excluir o ativo.");
    }

    recarregarPaginaAtivoComMensagem(resultado.message || "Ativo excluido com sucesso.", "success");
  } catch (erro) {
    definirMensagemPagina(erro.message || "Nao foi possivel excluir o ativo.", "error");
  } finally {
    definirCarregando(botao, false);
  }
}

// A mensagem fica na sessão do navegador para sobreviver ao recarregamento.
function recarregarPaginaAtivoComMensagem(mensagem, tipo) {
  try {
    sessionStorage.setItem(CHAVE_ARMAZENAMENTO_MENSAGEM_PAGINA, JSON.stringify({ message: mensagem, type: tipo }));
  } catch {
    return window.location.reload();
  }

  window.location.reload();
}

function restaurarMensagemPaginaPendente() {
  let mensagemPendente = null;

  try {
    mensagemPendente = JSON.parse(sessionStorage.getItem(CHAVE_ARMAZENAMENTO_MENSAGEM_PAGINA) || "null");
    sessionStorage.removeItem(CHAVE_ARMAZENAMENTO_MENSAGEM_PAGINA);
  } catch {
    mensagemPendente = null;
  }

  if (mensagemPendente?.message) {
    definirMensagemPagina(mensagemPendente.message, mensagemPendente.type || "success");
  }
}

function definirMensagemPagina(mensagem, tipo) {
  const elemento = document.getElementById("assetPageMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");

  if (mensagem && tipo === "success") {
    setTimeout(limparMensagemPagina, ATRASO_OCULTACAO_MENSAGEM_MS);
  }
}

function limparMensagemPagina() {
  definirMensagemPagina("", "");
}

function definirMensagemEdicao(mensagem, tipo) {
  const elemento = document.getElementById("assetEditMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function limparMensagemEdicao() {
  definirMensagemEdicao("", "");
}

function definirCarregando(botao, estaCarregando, textoCarregando = "Aguarde...") {
  if (!botao) return;

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

function definirValorSeletor(id, valor) {
  const seletor = document.getElementById(id);

  if (!seletor) return;

  const valorNormalizado = normalizarTexto(valor);
  const opcao = [...seletor.options].find((item) => normalizarTexto(item.value) === valorNormalizado);

  seletor.value = opcao ? opcao.value : "";
}

function obterTokenCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}
