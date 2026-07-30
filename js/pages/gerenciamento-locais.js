// Valida e cadastra locais, atualizando métricas e filtros no navegador.
// Os helpers globais de interface são carregados antes deste módulo.

const ATRASO_OCULTACAO_MENSAGEM_MS = 2700;

document.addEventListener("DOMContentLoaded", inicializarPagina);

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFormularioLocal();
  configurarFiltrosLocal();
}

function configurarFormularioLocal() {
  const formulario = document.getElementById("locationForm");

  if (!formulario) return;

  formulario.addEventListener("submit", enviarFormularioLocal);
  formulario.addEventListener("reset", () => {
    setTimeout(() => definirMensagemLocal("", ""), 0);
  });
}

// A interface só inclui o novo local após confirmação do cadastro pelo backend.
async function enviarFormularioLocal(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("locationSubmitButton");
  const erro = validarFormularioLocal(formulario);

  if (erro) {
    definirMensagemLocal(erro, "error");
    return;
  }

  definirCarregandoBotao(botaoEnviar, true);
  definirMensagemLocal("", "");

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
      throw new Error(resultado.message || "Nao foi possivel cadastrar o local.");
    }

    definirMensagemLocal(resultado.message || "Local cadastrado com sucesso.", "success");
    inserirInicioLinhaLocal(resultado.local);
    atualizarMetricasAposCadastro(resultado.local);
    formulario.reset();
    filtrarLocais();

    setTimeout(() => {
      definirMensagemLocal("", "");
    }, ATRASO_OCULTACAO_MENSAGEM_MS);
  } catch (erro) {
    definirMensagemLocal(erro.message || "Nao foi possivel cadastrar o local.", "error");
  } finally {
    definirCarregandoBotao(botaoEnviar, false);
  }
}

function validarFormularioLocal(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const endereco = String(dados.get("endereco") || "").trim();
  const status = String(dados.get("status") || "").trim();

  if (!nome || !status) {
    return "Informe nome e status para cadastrar o local.";
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
    criarElemento("span", "", "Cadastrar local"),
  );
}

function definirMensagemLocal(mensagem, tipo) {
  const elemento = document.getElementById("locationFormMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("error", tipo === "error");
  elemento.classList.toggle("success", tipo === "success");
}

function configurarFiltrosLocal() {
  document.getElementById("locationSearch")?.addEventListener("input", filtrarLocais);
  document.getElementById("locationStatusFilter")?.addEventListener("change", filtrarLocais);
  document.getElementById("clearLocationFilters")?.addEventListener("click", limparFiltrosLocal);

  filtrarLocais();
}

function limparFiltrosLocal() {
  const busca = document.getElementById("locationSearch");
  const status = document.getElementById("locationStatusFilter");

  if (busca) {
    busca.value = "";
  }

  if (status) {
    status.value = "todos";
  }

  filtrarLocais();
  busca?.focus();
}

// Os filtros atuam nas linhas existentes e atualizam contador e estado vazio juntos.
function filtrarLocais() {
  const linhas = Array.from(document.querySelectorAll(".location-row"));
  const busca = normalizarTexto(document.getElementById("locationSearch")?.value || "");
  const status = normalizarTexto(document.getElementById("locationStatusFilter")?.value || "todos");
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

function inserirInicioLinhaLocal(local) {
  const corpoTabela = document.getElementById("locationTableBody");

  if (!corpoTabela || !local) return;

  document.getElementById("locationEmptyState")?.setAttribute("hidden", "");

  const nome = String(local.nome || "Novo local");
  const endereco = String(local.endereco || "Sem endereco informado");
  const status = String(local.status || "Ativo");
  const linha = criarElemento("tr", "registration-row location-row");
  const celulaNome = criarElemento("td");
  const celulaStatus = criarElemento("td");
  const celulaCriacao = criarElemento("td", "", "Agora");
  const nomeDestacado = criarElemento("strong", "", nome);
  const elementoEndereco = criarElemento("span", "location-address", endereco);
  const indicador = criarElemento(
    "span",
    `status-badge ${status === "Ativo" ? "status-active" : "status-inactive"}`,
    status,
  );

  linha.dataset.status = normalizarTexto(status);
  linha.dataset.search = normalizarTexto(`${nome} ${endereco}`);
  celulaNome.dataset.label = "Local";
  celulaStatus.dataset.label = "Status";
  celulaCriacao.dataset.label = "Criado em";

  celulaNome.append(nomeDestacado, elementoEndereco);
  celulaStatus.append(indicador);
  linha.append(celulaNome, celulaStatus, celulaCriacao);
  corpoTabela.prepend(linha);
}

function atualizarMetricasAposCadastro(local) {
  incrementarMetrica("totalLocationsMetric");

  if (String(local?.status || "") === "Inativo") {
    incrementarMetrica("inactiveLocationsMetric");
    return;
  }

  incrementarMetrica("activeLocationsMetric");
}

function incrementarMetrica(id) {
  const elemento = document.getElementById(id);
  const valor = Number(elemento?.textContent || 0);

  if (elemento) {
    elemento.textContent = String(Number.isFinite(valor) ? valor + 1 : 1);
  }
}

function atualizarQuantidadeResultado(quantidade) {
  const quantidadeResultado = document.getElementById("locationResultCount");

  if (!quantidadeResultado) return;

  quantidadeResultado.textContent = `${quantidade.toLocaleString("pt-BR")} ${quantidade === 1 ? "registro" : "registros"}`;
}

function atualizarEstadoVazio(exibir) {
  const estadoVazio = document.getElementById("locationEmptyState");

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
