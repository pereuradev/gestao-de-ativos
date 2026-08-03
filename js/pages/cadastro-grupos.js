// Gerencia seleção de membros e permissões durante o cadastro de grupos de acesso.
// Usa confirmações e avisos globais definidos pelos módulos compartilhados.

document.addEventListener("DOMContentLoaded", inicializarPaginaCadastroGrupo);

function inicializarPaginaCadastroGrupo() {
  chamarGlobalGrupo("iniciarAnimacaoPagina");
  chamarGlobalGrupo("carregarTemaSalvo");
  chamarGlobalGrupo("configurarAlternadorTema");
  chamarGlobalGrupo("configurarBarraLateral");
  chamarGlobalGrupo("configurarGruposNavegacao");
  configurarBuscaFuncionarioGrupo();
  configurarResumoSelecaoGrupo();
  configurarModelosPermissaoGrupo();
  configurarFormularioGrupo();
  configurarRedefinicaoFormularioGrupo();
}

function chamarGlobalGrupo(nomeFuncao) {
  if (typeof window[nomeFuncao] === "function") {
    window[nomeFuncao]();
  }
}

function obterElementoGrupo(id) {
  return document.getElementById(id);
}

function criarElementoGrupo(etiqueta, nomeClasse = "", texto = "") {
  const elemento = document.createElement(etiqueta);

  if (nomeClasse) {
    elemento.className = nomeClasse;
  }

  if (texto) {
    elemento.textContent = texto;
  }

  return elemento;
}

// A busca atua apenas sobre os funcionários já carregados pelo PHP.
function configurarBuscaFuncionarioGrupo() {
  const busca = obterElementoGrupo("groupEmployeeSearch");
  const botaoLimpar = obterElementoGrupo("clearGroupEmployees");

  busca?.addEventListener("input", filtrarFuncionariosGrupo);

  botaoLimpar?.addEventListener("click", () => {
    if (busca) {
      busca.value = "";
    }

    document
      .querySelectorAll('#groupEmployeeList input[type="checkbox"]')
      .forEach((campoEntrada) => {
        campoEntrada.checked = false;
      });

    filtrarFuncionariosGrupo();
    atualizarResumoSelecaoGrupo();
  });
}

function configurarResumoSelecaoGrupo() {
  const formulario = obterElementoGrupo("groupForm");

  formulario?.addEventListener("change", (evento) => {
    if (
      evento.target instanceof HTMLInputElement &&
      evento.target.matches('input[type="checkbox"]')
    ) {
      atualizarResumoSelecaoGrupo();
    }
  });

  atualizarResumoSelecaoGrupo();
}

function atualizarResumoSelecaoGrupo() {
  const totalMembros = document.querySelectorAll(
    'input[name="membros[]"]:checked',
  ).length;
  const totalPermissoes = document.querySelectorAll(
    'input[name="permissoes[]"]:checked',
  ).length;
  const membrosSelecionados = obterElementoGrupo("groupMembersSelected");
  const permissoesSelecionadas = obterElementoGrupo("groupPermissionsSelected");

  if (membrosSelecionados) {
    membrosSelecionados.textContent = `${totalMembros} ${totalMembros === 1 ? "funcionário" : "funcionários"}`;
  }

  if (permissoesSelecionadas) {
    permissoesSelecionadas.textContent = `${totalPermissoes} ${totalPermissoes === 1 ? "permissão" : "permissões"}`;
  }
}

function configurarModelosPermissaoGrupo() {
  document.querySelectorAll("[data-permission-preset]").forEach((botao) => {
    botao.addEventListener("click", () => {
      const modelo = botao.dataset.permissionPreset || "clear";
      const permissoes = document.querySelectorAll(
        'input[name="permissoes[]"]',
      );

      permissoes.forEach((campoEntrada) => {
        const codigo = String(campoEntrada.value || "");

        campoEntrada.checked =
          modelo === "full" ||
          (modelo === "view" && codigo.startsWith("visualizar_")) ||
          (modelo === "operate" &&
            (codigo.startsWith("visualizar_") ||
              codigo.startsWith("cadastrar_")));
      });

      atualizarResumoSelecaoGrupo();
      window.titechToast?.(
        modelo === "clear"
          ? "Permissoes removidas."
          : "Modelo aplicado. Revise os acessos antes de cadastrar.",
      );
    });
  });
}

function filtrarFuncionariosGrupo() {
  const busca = normalizarTextoGrupo(obterElementoGrupo("groupEmployeeSearch")?.value || "");

  document.querySelectorAll(".group-check-card").forEach((cartao) => {
    const textoPesquisa = normalizarTextoGrupo(cartao.dataset.search || "");
    cartao.hidden = busca !== "" && !textoPesquisa.includes(busca);
  });
}

function normalizarTextoGrupo(valor) {
  return String(valor)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function configurarFormularioGrupo() {
  const formulario = obterElementoGrupo("groupForm");

  if (!formulario) {
    return;
  }

  formulario.addEventListener("submit", tratarEnvioGrupo);
}

function configurarRedefinicaoFormularioGrupo() {
  const formulario = obterElementoGrupo("groupForm");

  if (!formulario) {
    return;
  }

  formulario.addEventListener("reset", () => {
    requestAnimationFrame(() => {
      definirMensagemGrupo("");
      filtrarFuncionariosGrupo();
      atualizarResumoSelecaoGrupo();
    });
  });
}

// Membros e permissões são enviados juntos para o backend gravar o grupo de forma atômica.
async function tratarEnvioGrupo(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = obterElementoGrupo("groupSubmitButton");
  const erroValidacao = validarFormularioGrupo(formulario);

  if (erroValidacao) {
    definirMensagemGrupo(erroValidacao, "error");
    window.titechToast?.(erroValidacao, "error");
    return;
  }

  const nomeGrupo = obterElementoGrupo("groupName")?.value.trim() || "este grupo";
  const totalMembros = formulario.querySelectorAll(
    'input[name="membros[]"]:checked',
  ).length;
  const totalPermissoes = formulario.querySelectorAll(
    'input[name="permissoes[]"]:checked',
  ).length;
  const confirmado = await confirmarCriacaoGrupo(
    nomeGrupo,
    totalMembros,
    totalPermissoes,
  );

  if (!confirmado) {
    return;
  }

  definirMensagemGrupo("");
  definirCarregandoEnviarGrupo(botaoEnviar, true);

  try {
    const resposta = await fetch(formulario.action, {
      method: "POST",
      body: new FormData(formulario),
      headers: {
        Accept: "application/json",
      },
    });
    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (resultado.redirect && resposta.status === 401) {
      window.location.href = resultado.redirect;
      return;
    }

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel cadastrar o grupo.");
    }

    definirMensagemGrupo(resultado.message || "Grupo criado com sucesso.", "success");
    window.titechToast?.(resultado.message || "Grupo criado com sucesso.");
    atualizarMetricasGrupo(resultado.grupo);
    inserirInicioGrupoRecente(resultado.grupo);
    formulario.reset();
  } catch (erro) {
    const mensagem =
      erro instanceof Error ? erro.message : "Nao foi possivel cadastrar o grupo.";

    definirMensagemGrupo(mensagem, "error");
    window.titechToast?.(mensagem, "error");
  } finally {
    definirCarregandoEnviarGrupo(botaoEnviar, false);
  }
}

function validarFormularioGrupo(formulario) {
  const nome = obterElementoGrupo("groupName")?.value.trim() || "";
  const membros = formulario.querySelectorAll('input[name="membros[]"]:checked');
  const permissoes = formulario.querySelectorAll('input[name="permissoes[]"]:checked');

  if (nome.length < 3) {
    return "Informe um nome de grupo com pelo menos 3 caracteres.";
  }

  if (!membros.length) {
    return "Selecione pelo menos um funcionario para o grupo.";
  }

  if (!permissoes.length) {
    return "Selecione pelo menos uma permissao para o grupo.";
  }

  return "";
}

async function confirmarCriacaoGrupo(nomeGrupo, totalMembros, totalPermissoes) {
  const rotuloMembros = totalMembros === 1 ? "funcionário" : "funcionários";
  const rotuloPermissoes = totalPermissoes === 1 ? "permissão" : "permissões";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar grupo?",
      text: `O grupo ${nomeGrupo} terá ${totalMembros} ${rotuloMembros} e ${totalPermissoes} ${rotuloPermissoes}.`,
      confirmButtonText: "Cadastrar grupo",
      cancelButtonText: "Revisar",
      icon: "info",
    });
  }

  return window.confirm(`Criar o grupo ${nomeGrupo}?`);
}

function definirMensagemGrupo(mensagem, tipo = "") {
  const elemento = obterElementoGrupo("groupFormMessage");

  if (!elemento) {
    return;
  }

  elemento.textContent = mensagem;
  elemento.classList.remove("is-error", "is-success");

  if (tipo === "error") {
    elemento.classList.add("is-error");
  }

  if (tipo === "success") {
    elemento.classList.add("is-success");
  }
}

function definirCarregandoEnviarGrupo(botao, estaCarregando) {
  if (!botao) {
    return;
  }

  botao.disabled = estaCarregando;

  if (estaCarregando) {
    botao.replaceChildren(
      criarElementoGrupo("span", "spinner-border spinner-border-sm"),
      criarElementoGrupo("span", "", "Cadastrando grupo..."),
    );
    return;
  }

  botao.replaceChildren(
    criarElementoGrupo("i", "bi bi-plus-lg"),
    criarElementoGrupo("span", "", "Cadastrar grupo"),
  );
}

function atualizarMetricasGrupo(grupo) {
  if (!grupo || typeof grupo !== "object") {
    return;
  }

  incrementarMetricaGrupo("groupMetricTotal", 1);
  incrementarMetricaGrupo("groupMetricMembers", Number(grupo.total_membros || 0));
  incrementarMetricaGrupo("groupMetricPermissions", Number(grupo.total_permissoes || 0));
}

function incrementarMetricaGrupo(id, quantidade) {
  const elemento = obterElementoGrupo(id);
  const atual = Number.parseInt(elemento?.textContent || "0", 10);

  if (!elemento || Number.isNaN(atual)) {
    return;
  }

  elemento.textContent = String(atual + quantidade);
}

// O novo cartão usa APIs de DOM para manter os valores da resposta como texto.
function inserirInicioGrupoRecente(grupo) {
  if (!grupo || typeof grupo !== "object") {
    return;
  }

  const lista = obterElementoGrupo("recentGroupList");

  if (!lista) {
    return;
  }

  lista.querySelector(".compact-empty-state")?.remove();

  const artigo = criarElementoGrupo(
    "article",
    "recent-asset-item recent-employee-card group-recent-card",
  );
  const linhaSuperior = criarElementoGrupo("div", "recent-asset-topline");
  const titulo = criarElementoGrupo("strong", "", grupo.nome || "Novo grupo");
  const status = criarElementoGrupo("span", "status-badge status-active", grupo.status || "Ativo");
  const rodape = criarElementoGrupo("div", "recent-asset-footer");
  const membros = criarElementoGrupo("span", "", `${grupo.total_membros || 0} membros`);
  const permissoes = criarElementoGrupo(
    "span",
    "",
    `${grupo.total_permissoes || 0} permissoes`,
  );
  const tempo = document.createElement("time");

  tempo.dateTime = grupo.criado_em || "";
  tempo.textContent = formatarDataHoraGrupo(grupo.criado_em || "");

  linhaSuperior.append(titulo, status);
  rodape.append(membros, permissoes, tempo);
  artigo.append(linhaSuperior, rodape);
  lista.prepend(artigo);

  [...lista.querySelectorAll(".group-recent-card")]
    .slice(6)
    .forEach((cartao) => cartao.remove());
}

function formatarDataHoraGrupo(valor) {
  if (!valor) {
    return "--";
  }

  const data = new Date(valor);

  if (Number.isNaN(data.getTime())) {
    return "--";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "America/Sao_Paulo",
  }).format(data);
}
