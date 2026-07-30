// Controla busca, edição e exclusão de marcas cadastradas.
// As ações dependem do token CSRF renderizado pela página e da resposta JSON do backend.

const ATRASO_OCULTACAO_MENSAGEM_MS = 2800;

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosMarca();
  configurarAcoesMarca();
  configurarModalEdicao();
}

function configurarFiltrosMarca() {
  document.getElementById("brandSearch")?.addEventListener("input", filtrarMarcas);
  document.getElementById("brandStatusFilter")?.addEventListener("change", filtrarMarcas);

  filtrarMarcas();
}

function configurarAcoesMarca() {
  document.getElementById("brandTableBody")?.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-brand-action]");

    if (!botao) return;

    const linha = botao.closest(".brand-row");

    if (!linha) return;

    if (botao.dataset.brandAction === "edit") {
      abrirModalEdicao(linha);
      return;
    }

    if (botao.dataset.brandAction === "delete") {
      excluirMarca(linha, botao);
    }
  });
}

function configurarModalEdicao() {
  const formulario = document.getElementById("brandEditForm");
  const modal = document.getElementById("brandEditModal");

  formulario?.addEventListener("submit", enviarFormularioEdicao);

  document.querySelectorAll("[data-close-edit-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalEdicao);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalEdicao();
    }
  });
}

// O formulário recebe os dados da linha selecionada por meio de atributos data-*.
function abrirModalEdicao(linha) {
  const modal = document.getElementById("brandEditModal");
  const campoEntradaId = document.getElementById("editBrandId");
  const campoEntradaNome = document.getElementById("editBrandName");
  const campoEntradaStatus = document.getElementById("editBrandStatus");

  if (!modal || !campoEntradaId || !campoEntradaNome || !campoEntradaStatus) return;

  campoEntradaId.value = linha.dataset.id || "";
  campoEntradaNome.value = linha.dataset.name || "";
  campoEntradaStatus.value = linha.dataset.statusRaw || "Ativa";
  limparMensagemEdicao();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  campoEntradaNome.focus();
}

function fecharModalEdicao() {
  const modal = document.getElementById("brandEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

// A linha é atualizada somente após a confirmação do backend.
async function enviarFormularioEdicao(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveBrandButton");
  const erro = validarFormularioMarca(formulario);

  if (erro) {
    definirMensagemEdicao(erro, "error");
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
      throw new Error(resultado.message || "Nao foi possivel alterar a marca.");
    }

    atualizarLinhaMarca(resultado.marca);
    fecharModalEdicao();
    definirMensagemPagina(resultado.message || "Marca alterada com sucesso.", "success");
    filtrarMarcas();
  } catch (erro) {
    definirMensagemEdicao(erro.message || "Nao foi possivel alterar a marca.", "error");
  } finally {
    definirCarregando(botaoEnviar, false);
  }
}

function validarFormularioMarca(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const status = String(dados.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status da marca.";
  }

  if (nome.length < 2) {
    return "O nome da marca precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da marca deve ter no maximo 80 caracteres.";
  }

  return "";
}

// A exclusão combina confirmação do usuário, CSRF e resposta JSON válida.
async function excluirMarca(linha, botao) {
  const nome = linha.dataset.name || "esta marca";
  const confirmado = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${nome}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir marca",
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
    const resposta = await fetch("../Backend/excluir-marca.php", {
      method: "POST",
      body: corpoRequisicao,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel excluir a marca.");
    }

    linha.remove();
    definirMensagemPagina(resultado.message || "Marca excluida com sucesso.", "success");
    filtrarMarcas();
  } catch (erro) {
    definirMensagemPagina(erro.message || "Nao foi possivel excluir a marca.", "error");
  } finally {
    definirCarregando(botao, false);
  }
}

function atualizarLinhaMarca(marca) {
  if (!marca?.id) return;

  const linha = document.querySelector(`.brand-row[data-id="${escaparCss(String(marca.id))}"]`);

  if (!linha) return;

  const nome = String(marca.nome || "");
  const status = String(marca.status || "Ativa");
  const statusNormalizado = normalizarTexto(status);
  const celulaNome = linha.querySelector("[data-brand-name]");
  const celulaStatus = linha.querySelector("[data-brand-status]");
  const celulaAtualizacao = linha.querySelector("[data-brand-updated]");

  linha.dataset.name = nome;
  linha.dataset.status = statusNormalizado;
  linha.dataset.statusRaw = status;
  linha.dataset.search = normalizarTexto(nome);

  if (celulaNome) {
    celulaNome.textContent = nome;
  }

  if (celulaStatus) {
    celulaStatus.className = `status-badge ${status === "Ativa" ? "status-active" : "status-inactive"}`;
    celulaStatus.textContent = status;
  }

  if (celulaAtualizacao) {
    celulaAtualizacao.textContent = formatarData(marca.atualizado_em) || "Agora";
  }
}

// A busca e o status filtram localmente os registros já renderizados.
function filtrarMarcas() {
  const linhas = Array.from(document.querySelectorAll(".brand-row"));
  const busca = normalizarTexto(document.getElementById("brandSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("brandStatusFilter")?.value || "todos");
  let quantidadeVisivel = 0;
  let quantidadeAtivos = 0;
  let quantidadeInativos = 0;

  linhas.forEach((linha) => {
    const statusLinha = normalizarTexto(linha.dataset.status || "");
    const buscaLinha = normalizarTexto(linha.dataset.search || "");
    const correspondeStatus = status === "todos" || statusLinha === status;
    const correspondeBusca = !busca || buscaLinha.includes(busca);
    const ehVisivel = correspondeStatus && correspondeBusca;

    if (statusLinha === "ativa") {
      quantidadeAtivos += 1;
    } else if (statusLinha === "inativa") {
      quantidadeInativos += 1;
    }

    linha.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarTexto("brandResultCount", `${quantidadeVisivel.toLocaleString("pt-BR")} ${quantidadeVisivel === 1 ? "registro" : "registros"}`);
  atualizarTexto("totalBrandsMetric", String(linhas.length));
  atualizarTexto("activeBrandsMetric", String(quantidadeAtivos));
  atualizarTexto("inactiveBrandsMetric", String(quantidadeInativos));
  atualizarEstadoVazio(linhas.length === 0 || quantidadeVisivel === 0);
}

function atualizarEstadoVazio(exibir) {
  const estadoVazio = document.getElementById("brandEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function definirMensagemPagina(mensagem, tipo) {
  const elemento = document.getElementById("brandPageMessage");

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
  const elemento = document.getElementById("brandEditMessage");

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

function obterTokenCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

function formatarData(valor) {
  if (!valor) return "";

  const data = new Date(valor);

  if (Number.isNaN(data.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(data);
}

function escaparCss(valor) {
  if (window.CSS?.escape) {
    return window.CSS.escape(valor);
  }

  return valor.replace(/["\\]/g, "\\$&");
}
