// Valida e cadastra marcas, atualizando métricas e filtros no navegador.
// O mesmo módulo também atende a página de visualização, onde o formulário pode não existir.

const ATRASO_OCULTACAO_MENSAGEM_MS = 2700;

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFormularioMarca();
  configurarFiltrosMarca();
}

function configurarFormularioMarca() {
  const formulario = document.getElementById("brandForm");

  if (!formulario) return;

  formulario.addEventListener("submit", enviarFormularioMarca);
  formulario.addEventListener("reset", () => {
    setTimeout(() => definirMensagemMarca("", ""), 0);
  });
}

// A interface só inclui a nova marca após confirmação do cadastro pelo backend.
async function enviarFormularioMarca(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("brandSubmitButton");
  const erro = validarFormularioMarca(formulario);

  if (erro) {
    definirMensagemMarca(erro, "error");
    return;
  }

  definirCarregandoBotao(botaoEnviar, true);
  definirMensagemMarca("", "");

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
      throw new Error(resultado.message || "Nao foi possivel cadastrar a marca.");
    }

    definirMensagemMarca(resultado.message || "Marca cadastrada com sucesso.", "success");
    inserirInicioLinhaMarca(resultado.marca);
    atualizarMetricasAposCadastro(resultado.marca);
    formulario.reset();
    filtrarMarcas();

    setTimeout(() => {
      definirMensagemMarca("", "");
    }, ATRASO_OCULTACAO_MENSAGEM_MS);
  } catch (erro) {
    definirMensagemMarca(erro.message || "Nao foi possivel cadastrar a marca.", "error");
  } finally {
    definirCarregandoBotao(botaoEnviar, false);
  }
}

function validarFormularioMarca(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const status = String(dados.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status para cadastrar a marca.";
  }

  if (nome.length < 2) {
    return "O nome da marca precisa ter pelo menos 2 caracteres.";
  }

  if (nome.length > 80) {
    return "O nome da marca deve ter no maximo 80 caracteres.";
  }

  return "";
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
    criarElemento("span", "", "Cadastrar marca"),
  );
}

function definirMensagemMarca(mensagem, tipo) {
  const elemento = document.getElementById("brandFormMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function configurarFiltrosMarca() {
  document.getElementById("brandSearch")?.addEventListener("input", filtrarMarcas);
  document.getElementById("brandStatusFilter")?.addEventListener("change", filtrarMarcas);
  document.getElementById("clearBrandFilters")?.addEventListener("click", limparFiltrosMarca);

  filtrarMarcas();
}

function limparFiltrosMarca() {
  const busca = document.getElementById("brandSearch");
  const status = document.getElementById("brandStatusFilter");

  if (busca) {
    busca.value = "";
  }

  if (status) {
    status.value = "todos";
  }

  filtrarMarcas();
  busca?.focus();
}

// Os filtros atuam nas linhas existentes e atualizam contador e estado vazio juntos.
function filtrarMarcas() {
  const linhas = Array.from(document.querySelectorAll(".brand-row"));
  const busca = normalizarTexto(document.getElementById("brandSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("brandStatusFilter")?.value || "todos");
  let quantidadeVisivel = 0;

  linhas.forEach((linha) => {
    const statusLinha = normalizarTexto(linha.dataset.status || "");
    const buscaLinha = normalizarTexto(linha.dataset.search || "");
    const correspondeStatus = status === "todos" || statusLinha === status;
    const correspondeBusca = !busca || buscaLinha.includes(busca);
    const ehVisivel = correspondeStatus && correspondeBusca;

    linha.hidden = !ehVisivel;

    if (ehVisivel) {
      quantidadeVisivel += 1;
    }
  });

  atualizarQuantidadeResultado(quantidadeVisivel);
  atualizarEstadoVazio(linhas.length === 0 || quantidadeVisivel === 0);
}

function inserirInicioLinhaMarca(marca) {
  const corpoTabela = document.getElementById("brandTableBody");

  if (!corpoTabela || !marca) return;

  document.getElementById("brandEmptyState")?.setAttribute("hidden", "");

  const nome = String(marca.nome || "Nova marca");
  const status = String(marca.status || "Ativa");
  const linha = criarElemento("tr", "registration-row brand-row");
  const celulaNome = criarElemento("td");
  const celulaStatus = criarElemento("td");
  const celulaCriacao = criarElemento("td", "", "Agora");
  const nomeDestacado = criarElemento("strong", "", nome);
  const indicador = criarElemento(
    "span",
    `status-badge ${status === "Ativa" ? "status-active" : "status-inactive"}`,
    status,
  );

  linha.dataset.status = normalizarTexto(status);
  linha.dataset.search = normalizarTexto(nome);
  celulaNome.dataset.label = "Marca";
  celulaStatus.dataset.label = "Status";
  celulaCriacao.dataset.label = "Criada em";

  celulaNome.append(nomeDestacado);
  celulaStatus.append(indicador);
  linha.append(celulaNome, celulaStatus, celulaCriacao);
  corpoTabela.prepend(linha);
}

function atualizarMetricasAposCadastro(marca) {
  incrementarMetrica("totalBrandsMetric");

  if (String(marca?.status || "") === "Inativa") {
    incrementarMetrica("inactiveBrandsMetric");
    return;
  }

  incrementarMetrica("activeBrandsMetric");
}

function incrementarMetrica(id) {
  const elemento = document.getElementById(id);
  const valor = Number(elemento?.textContent || 0);

  if (elemento) {
    elemento.textContent = String(Number.isFinite(valor) ? valor + 1 : 1);
  }
}

function atualizarQuantidadeResultado(quantidade) {
  const quantidadeResultado = document.getElementById("brandResultCount");

  if (!quantidadeResultado) return;

  quantidadeResultado.textContent = `${quantidade.toLocaleString("pt-BR")} ${quantidade === 1 ? "registro" : "registros"}`;
}

function atualizarEstadoVazio(exibir) {
  const estadoVazio = document.getElementById("brandEmptyState");

  if (estadoVazio) {
    estadoVazio.hidden = !exibir;
  }
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
