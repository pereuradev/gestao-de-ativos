// Controla busca, edição e exclusão de locais cadastrados.
// As ações dependem do token CSRF renderizado pela página e da resposta JSON do backend.

const ATRASO_OCULTACAO_MENSAGEM_MS = 2800;

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosLocal();
  configurarAcoesLocal();
  configurarModalEdicao();
}

function configurarFiltrosLocal() {
  document.getElementById("locationSearch")?.addEventListener("input", filtrarLocais);
  document.getElementById("locationStatusFilter")?.addEventListener("change", filtrarLocais);

  filtrarLocais();
}

function configurarAcoesLocal() {
  document.getElementById("locationTableBody")?.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-location-action]");

    if (!botao) return;

    const linha = botao.closest(".location-row");

    if (!linha) return;

    if (botao.dataset.locationAction === "edit") {
      abrirModalEdicao(linha);
      return;
    }

    if (botao.dataset.locationAction === "delete") {
      excluirLocal(linha, botao);
    }
  });
}

function configurarModalEdicao() {
  const formulario = document.getElementById("locationEditForm");
  const modal = document.getElementById("locationEditModal");

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
  const modal = document.getElementById("locationEditModal");
  const campoEntradaId = document.getElementById("editLocationId");
  const campoEntradaNome = document.getElementById("editLocationName");
  const campoEntradaEndereco = document.getElementById("editLocationAddress");
  const campoEntradaStatus = document.getElementById("editLocationStatus");

  if (!modal || !campoEntradaId || !campoEntradaNome || !campoEntradaEndereco || !campoEntradaStatus) return;

  campoEntradaId.value = linha.dataset.id || "";
  campoEntradaNome.value = linha.dataset.name || "";
  campoEntradaEndereco.value = linha.dataset.address || "";
  campoEntradaStatus.value = linha.dataset.statusRaw || "Ativo";
  limparMensagemEdicao();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  campoEntradaNome.focus();
}

function fecharModalEdicao() {
  const modal = document.getElementById("locationEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

// A linha é atualizada somente após a confirmação do backend.
async function enviarFormularioEdicao(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveLocationButton");
  const erro = validarFormularioLocal(formulario);

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
      throw new Error(resultado.message || "Nao foi possivel alterar o local.");
    }

    atualizarLinhaLocal(resultado.local);
    fecharModalEdicao();
    definirMensagemPagina(resultado.message || "Local alterado com sucesso.", "success");
    filtrarLocais();
  } catch (erro) {
    definirMensagemEdicao(erro.message || "Nao foi possivel alterar o local.", "error");
  } finally {
    definirCarregando(botaoEnviar, false);
  }
}

function validarFormularioLocal(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const endereco = String(dados.get("endereco") || "").trim();
  const status = String(dados.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status do local.";
  }

  if (nome.length < 2) {
    return "O nome do local precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 100) {
    return "O nome do local deve ter no maximo 100 caracteres.";
  }

  if (endereco.length > 160) {
    return "O endereco deve ter no maximo 160 caracteres.";
  }

  return "";
}

// A exclusão combina confirmação do usuário, CSRF e resposta JSON válida.
async function excluirLocal(linha, botao) {
  const nome = linha.dataset.name || "este local";
  const confirmado = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${nome}?`,
      text: "Esta acao nao pode ser desfeita.",
      confirmButtonText: "Excluir local",
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
    const resposta = await fetch("../Backend/excluir-local.php", {
      method: "POST",
      body: corpoRequisicao,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel excluir o local.");
    }

    linha.remove();
    definirMensagemPagina(resultado.message || "Local excluido com sucesso.", "success");
    filtrarLocais();
  } catch (erro) {
    definirMensagemPagina(erro.message || "Nao foi possivel excluir o local.", "error");
  } finally {
    definirCarregando(botao, false);
  }
}

function atualizarLinhaLocal(local) {
  if (!local?.id) return;

  const linha = document.querySelector(`.location-row[data-id="${escaparCss(String(local.id))}"]`);

  if (!linha) return;

  const nome = String(local.nome || "");
  const endereco = String(local.endereco || "");
  const status = String(local.status || "Ativo");
  const statusNormalizado = normalizarTexto(status);
  const celulaNome = linha.querySelector("[data-location-name]");
  const celulaEndereco = linha.querySelector("[data-location-address]");
  const celulaStatus = linha.querySelector("[data-location-status]");
  const celulaAtualizacao = linha.querySelector("[data-location-updated]");

  linha.dataset.name = nome;
  linha.dataset.address = endereco;
  linha.dataset.status = statusNormalizado;
  linha.dataset.statusRaw = status;
  linha.dataset.search = normalizarTexto(`${nome} ${endereco}`);

  if (celulaNome) {
    celulaNome.textContent = nome;
  }

  if (celulaEndereco) {
    celulaEndereco.textContent = endereco || "Sem referencia informada";
  }

  if (celulaStatus) {
    celulaStatus.className = `status-badge ${status === "Ativo" ? "status-active" : "status-inactive"}`;
    celulaStatus.textContent = status;
  }

  if (celulaAtualizacao) {
    celulaAtualizacao.textContent = formatarData(local.atualizado_em) || "Agora";
  }
}

// A busca e o status filtram localmente os registros já renderizados.
function filtrarLocais() {
  const linhas = Array.from(document.querySelectorAll(".location-row"));
  const busca = normalizarTexto(document.getElementById("locationSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("locationStatusFilter")?.value || "todos");
  let quantidadeVisivel = 0;
  let quantidadeAtivos = 0;
  let quantidadeInativos = 0;

  linhas.forEach((linha) => {
    const statusLinha = normalizarTexto(linha.dataset.status || "");
    const buscaLinha = normalizarTexto(linha.dataset.search || "");
    const correspondeStatus = status === "todos" || statusLinha === status;
    const correspondeBusca = !busca || buscaLinha.includes(busca);
    const ehVisivel = correspondeStatus && correspondeBusca;

    if (statusLinha === "ativo") {
      quantidadeAtivos += 1;
    } else if (statusLinha === "inativo") {
      quantidadeInativos += 1;
    }

    linha.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarTexto("locationResultCount", `${quantidadeVisivel.toLocaleString("pt-BR")} ${quantidadeVisivel === 1 ? "registro" : "registros"}`);
  atualizarTexto("totalLocationsMetric", String(linhas.length));
  atualizarTexto("activeLocationsMetric", String(quantidadeAtivos));
  atualizarTexto("inactiveLocationsMetric", String(quantidadeInativos));
  atualizarEstadoVazio(linhas.length === 0 || quantidadeVisivel === 0);
}

function atualizarEstadoVazio(exibir) {
  const estadoVazio = document.getElementById("locationEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function definirMensagemPagina(mensagem, tipo) {
  const elemento = document.getElementById("locationPageMessage");

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
  const elemento = document.getElementById("locationEditMessage");

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
