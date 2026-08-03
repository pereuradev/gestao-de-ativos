// Valida e cadastra categorias, atualizando metricas e filtros no navegador.
// O mesmo modulo tambem atende a pagina de visualizacao, onde o formulario pode nao existir.

const ATRASO_OCULTACAO_MENSAGEM_CATEGORIA_MS = 2700;

document.addEventListener("DOMContentLoaded", inicializarPaginaCategoria);

function inicializarPaginaCategoria() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFormularioCategoria();
  configurarFiltrosCategoria();
}

function configurarFormularioCategoria() {
  const formulario = document.getElementById("categoryForm");

  if (!formulario) return;

  formulario.addEventListener("submit", enviarFormularioCategoria);
  formulario.addEventListener("reset", () => {
    setTimeout(() => definirMensagemCategoria("", ""), 0);
  });
}

async function enviarFormularioCategoria(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("categorySubmitButton");
  const erro = validarFormularioCategoria(formulario);

  if (erro) {
    definirMensagemCategoria(erro, "error");
    return;
  }

  definirCarregandoBotaoCategoria(botaoEnviar, true);
  definirMensagemCategoria("", "");

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
      throw new Error(
        resultado.message || "Nao foi possivel cadastrar a categoria.",
      );
    }

    definirMensagemCategoria("Categoria cadastrada com sucesso!");
    incrementarMetricaCategoria("totalCategoriesMetric");
    incrementarMetricaCategoria("unlinkedCategoriesMetric");
    formulario.reset();
  } catch (erro) {
    definirMensagemCategoria(
      erro.message || "Nao foi possivel cadastrar a categoria.",
      "error",
    );
  } finally {
    definirCarregandoBotaoCategoria(botaoEnviar, false);
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

function definirCarregandoBotaoCategoria(botao, estaCarregando) {
  if (!botao) return;

  botao.disabled = estaCarregando;

  if (estaCarregando) {
    botao.replaceChildren(
      criarElementoCategoria("i", "bi bi-arrow-repeat"),
      criarElementoCategoria("span", "", "Cadastrando..."),
    );
    return;
  }

  botao.replaceChildren(
    criarElementoCategoria("i", "bi bi-plus-circle"),
    criarElementoCategoria("span", "", "Cadastrar categoria"),
  );
}

function definirMensagemCategoria(mensagem, tipo) {
  const elemento = document.getElementById("categoryFormMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function configurarFiltrosCategoria() {
  document
    .getElementById("categorySearch")
    ?.addEventListener("input", filtrarCategorias);
  document
    .getElementById("clearCategoryFilters")
    ?.addEventListener("click", limparFiltrosCategoria);

  filtrarCategorias();
}

function limparFiltrosCategoria() {
  const busca = document.getElementById("categorySearch");

  if (busca) {
    busca.value = "";
  }

  filtrarCategorias();
  busca?.focus();
}

function filtrarCategorias() {
  const linhas = Array.from(document.querySelectorAll(".category-row"));
  const busca = normalizarTexto(
    document.getElementById("categorySearch")?.value || "",
  );
  let quantidadeVisivel = 0;

  linhas.forEach((linha) => {
    const buscaLinha = normalizarTexto(linha.dataset.search || "");
    const ehVisivel = !busca || buscaLinha.includes(busca);

    linha.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarQuantidadeResultadoCategoria(quantidadeVisivel);
  atualizarEstadoVazioCategoria(linhas.length === 0 || quantidadeVisivel === 0);
}

function incrementarMetricaCategoria(id) {
  const elemento = document.getElementById(id);
  const valor = Number(elemento?.textContent || 0);

  if (elemento) {
    elemento.textContent = String(Number.isFinite(valor) ? valor + 1 : 1);
  }
}

function atualizarQuantidadeResultadoCategoria(quantidade) {
  const quantidadeResultado = document.getElementById("categoryResultCount");

  if (!quantidadeResultado) return;

  quantidadeResultado.textContent = `${quantidade.toLocaleString("pt-BR")} ${quantidade === 1 ? "registro" : "registros"}`;
}

function atualizarEstadoVazioCategoria(exibir) {
  const estadoVazio = document.getElementById("categoryEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
}

function criarElementoCategoria(etiqueta, nomeClasse = "", texto = "") {
  const elemento = document.createElement(etiqueta);

  if (nomeClasse) {
    elemento.className = nomeClasse;
  }

  if (texto) {
    elemento.textContent = texto;
  }

  return elemento;
}
