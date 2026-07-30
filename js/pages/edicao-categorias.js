// Controla busca, edicao e exclusao de categorias cadastradas.
// As acoes dependem do token CSRF renderizado pela pagina e da resposta JSON do backend.

const ATRASO_OCULTACAO_MENSAGEM_CATEGORIA_MS = 2800;

document.addEventListener("DOMContentLoaded", inicializarPaginaEdicaoCategoria);

function inicializarPaginaEdicaoCategoria() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosCategoria();
  configurarAcoesCategoria();
  configurarModalEdicaoCategoria();
}

function configurarFiltrosCategoria() {
  document.getElementById("categorySearch")?.addEventListener("input", filtrarCategorias);

  filtrarCategorias();
}

function configurarAcoesCategoria() {
  document.getElementById("categoryTableBody")?.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-category-action]");

    if (!botao) return;

    const linha = botao.closest(".category-row");

    if (!linha) return;

    if (botao.dataset.categoryAction === "edit") {
      abrirModalEdicaoCategoria(linha);
      return;
    }

    if (botao.dataset.categoryAction === "delete") {
      excluirCategoria(linha, botao);
    }
  });
}

function configurarModalEdicaoCategoria() {
  const formulario = document.getElementById("categoryEditForm");
  const modal = document.getElementById("categoryEditModal");

  formulario?.addEventListener("submit", enviarFormularioEdicaoCategoria);

  document.querySelectorAll("[data-close-edit-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalEdicaoCategoria);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalEdicaoCategoria();
    }
  });
}

function abrirModalEdicaoCategoria(linha) {
  const modal = document.getElementById("categoryEditModal");
  const campoEntradaId = document.getElementById("editCategoryId");
  const campoEntradaNome = document.getElementById("editCategoryName");
  const campoDescricao = document.getElementById("editCategoryDescription");

  if (!modal || !campoEntradaId || !campoEntradaNome || !campoDescricao) return;

  campoEntradaId.value = linha.dataset.id || "";
  campoEntradaNome.value = linha.dataset.name || "";
  campoDescricao.value = linha.dataset.description || "";
  limparMensagemEdicaoCategoria();
  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  campoEntradaNome.focus();
}

function fecharModalEdicaoCategoria() {
  const modal = document.getElementById("categoryEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

async function enviarFormularioEdicaoCategoria(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveCategoryButton");
  const erro = validarFormularioCategoria(formulario);

  if (erro) {
    definirMensagemEdicaoCategoria(erro, "error");
    return;
  }

  definirCarregandoCategoria(botaoEnviar, true, "Salvando...");
  limparMensagemEdicaoCategoria();

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
      throw new Error(resultado.message || "Nao foi possivel alterar a categoria.");
    }

    atualizarLinhaCategoria(resultado.categoria);
    fecharModalEdicaoCategoria();
    definirMensagemPaginaCategoria(resultado.message || "Categoria alterada com sucesso.", "success");
    filtrarCategorias();
  } catch (erro) {
    definirMensagemEdicaoCategoria(erro.message || "Nao foi possivel alterar a categoria.", "error");
  } finally {
    definirCarregandoCategoria(botaoEnviar, false);
  }
}

function validarFormularioCategoria(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const descricao = String(dados.get("descricao") || "").trim();

  if (!nome) {
    return "Informe o nome da categoria.";
  }

  if (nome.length < 2) {
    return "O nome da categoria precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da categoria deve ter no maximo 80 caracteres.";
  }

  if (descricao.length > 240) {
    return "A descricao da categoria deve ter no maximo 240 caracteres.";
  }

  return "";
}

async function excluirCategoria(linha, botao) {
  const nome = linha.dataset.name || "esta categoria";
  const ativosVinculados = Number(linha.dataset.assets || 0);
  const texto = ativosVinculados > 0
    ? "Esta categoria possui ativos vinculados e o banco deve bloquear a exclusao."
    : "Esta acao nao pode ser desfeita.";
  const confirmado = window.titechConfirm
    ? await window.titechConfirm({
      title: `Excluir ${nome}?`,
      text: texto,
      confirmButtonText: "Excluir categoria",
      icon: "warning",
    })
    : window.confirm(`Excluir ${nome}? ${texto}`);

  if (!confirmado) return;

  const corpoRequisicao = new FormData();
  corpoRequisicao.append("csrf_token", obterTokenCsrfCategoria());
  corpoRequisicao.append("id", linha.dataset.id || "");

  definirCarregandoCategoria(botao, true, "Excluindo...");
  limparMensagemPaginaCategoria();

  try {
    const resposta = await fetch("../Backend/excluir-categoria.php", {
      method: "POST",
      body: corpoRequisicao,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel excluir a categoria.");
    }

    linha.remove();
    definirMensagemPaginaCategoria(resultado.message || "Categoria excluida com sucesso.", "success");
    filtrarCategorias();
  } catch (erro) {
    definirMensagemPaginaCategoria(erro.message || "Nao foi possivel excluir a categoria.", "error");
  } finally {
    definirCarregandoCategoria(botao, false);
  }
}

function atualizarLinhaCategoria(categoria) {
  if (!categoria?.id) return;

  const linha = document.querySelector(`.category-row[data-id="${escaparCss(String(categoria.id))}"]`);

  if (!linha) return;

  const nome = String(categoria.nome || "");
  const descricao = String(categoria.descricao || "");
  const celulaNome = linha.querySelector("[data-category-name]");
  const celulaDescricao = linha.querySelector("[data-category-description]");
  const celulaAtualizacao = linha.querySelector("[data-category-updated]");

  linha.dataset.name = nome;
  linha.dataset.description = descricao;
  linha.dataset.search = normalizarTexto(`${nome} ${descricao}`);

  if (celulaNome) {
    celulaNome.textContent = nome;
  }

  if (celulaDescricao) {
    celulaDescricao.textContent = descricao || "Sem descricao";
  }

  if (celulaAtualizacao) {
    celulaAtualizacao.textContent = formatarDataCategoria(categoria.atualizado_em) || "Agora";
  }
}

function filtrarCategorias() {
  const linhas = Array.from(document.querySelectorAll(".category-row"));
  const busca = normalizarTexto(document.getElementById("categorySearch")?.value || "");
  let quantidadeVisivel = 0;
  let quantidadeVinculados = 0;
  let quantidadeDesvinculados = 0;

  linhas.forEach((linha) => {
    const buscaLinha = normalizarTexto(linha.dataset.search || "");
    const ativos = Number(linha.dataset.assets || 0);
    const ehVisivel = !busca || buscaLinha.includes(busca);

    if (ativos > 0) {
      quantidadeVinculados += 1;
    } else {
      quantidadeDesvinculados += 1;
    }

    linha.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarTexto("categoryResultCount", `${quantidadeVisivel.toLocaleString("pt-BR")} ${quantidadeVisivel === 1 ? "registro" : "registros"}`);
  atualizarTexto("totalCategoriesMetric", String(linhas.length));
  atualizarTexto("linkedCategoriesMetric", String(quantidadeVinculados));
  atualizarTexto("unlinkedCategoriesMetric", String(quantidadeDesvinculados));
  atualizarEstadoVazioCategoria(linhas.length === 0 || quantidadeVisivel === 0);
}

function atualizarEstadoVazioCategoria(exibir) {
  const estadoVazio = document.getElementById("categoryEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function definirMensagemPaginaCategoria(mensagem, tipo) {
  const elemento = document.getElementById("categoryPageMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");

  if (mensagem && tipo === "success") {
    setTimeout(limparMensagemPaginaCategoria, ATRASO_OCULTACAO_MENSAGEM_CATEGORIA_MS);
  }
}

function limparMensagemPaginaCategoria() {
  definirMensagemPaginaCategoria("", "");
}

function definirMensagemEdicaoCategoria(mensagem, tipo) {
  const elemento = document.getElementById("categoryEditMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function limparMensagemEdicaoCategoria() {
  definirMensagemEdicaoCategoria("", "");
}

function definirCarregandoCategoria(botao, estaCarregando, textoCarregando = "Aguarde...") {
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

function obterTokenCsrfCategoria() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

function formatarDataCategoria(valor) {
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
