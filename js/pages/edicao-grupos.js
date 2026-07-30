// Coordena edição, remoção de membros e exclusão de grupos de acesso.
// As mudanças confirmadas pelo backend são refletidas nos cartões e métricas da página.

const ATRASO_OCULTACAO_MENSAGEM_EDICAO_GRUPO_MS = 2800;

document.addEventListener("DOMContentLoaded", inicializarPaginaEdicaoGrupo);

function inicializarPaginaEdicaoGrupo() {
  chamarGlobalEdicaoGrupo("iniciarAnimacaoPagina");
  chamarGlobalEdicaoGrupo("carregarTemaSalvo");
  chamarGlobalEdicaoGrupo("configurarAlternadorTema");
  chamarGlobalEdicaoGrupo("configurarBarraLateral");
  chamarGlobalEdicaoGrupo("configurarGruposNavegacao");
  configurarBuscaEdicaoGrupo();
  configurarAcoesEdicaoGrupo();
  configurarModalEdicaoGrupo();
  atualizarEstadoVazioEdicaoGrupo();
}

function chamarGlobalEdicaoGrupo(nomeFuncao) {
  if (typeof window[nomeFuncao] === "function") {
    window[nomeFuncao]();
  }
}

function configurarBuscaEdicaoGrupo() {
  document.getElementById("groupEditSearch")?.addEventListener("input", filtrarItensEdicaoGrupo);
  filtrarItensEdicaoGrupo();
}

function configurarAcoesEdicaoGrupo() {
  document.getElementById("groupEditList")?.addEventListener("click", (evento) => {
    const botaoEdicao = evento.target.closest("[data-group-action='edit']");
    const botaoRemover = evento.target.closest("[data-member-action='remove']");
    const botaoExcluir = evento.target.closest("[data-group-action='delete']");

    if (botaoEdicao) {
      abrirModalEdicaoGrupo(botaoEdicao.closest(".group-edit-item"));
      return;
    }

    if (botaoRemover) {
      removerMembroGrupo(botaoRemover);
      return;
    }

    if (botaoExcluir) {
      excluirGrupo(botaoExcluir);
    }
  });
}

function configurarModalEdicaoGrupo() {
  const modal = document.getElementById("groupEditModal");
  const formulario = document.getElementById("groupModalForm");
  const busca = document.getElementById("editGroupEmployeeSearch");

  formulario?.addEventListener("submit", enviarModalGrupo);
  busca?.addEventListener("input", filtrarMembrosModalGrupo);

  document.querySelectorAll("[data-close-group-modal]").forEach((botao) => {
    botao.addEventListener("click", fecharModalEdicaoGrupo);
  });

  modal?.addEventListener("click", (evento) => {
    if (evento.target === modal) {
      fecharModalEdicaoGrupo();
    }
  });
}

// O modal recebe membros e permissões serializados no cartão selecionado.
function abrirModalEdicaoGrupo(cartao) {
  const modal = document.getElementById("groupEditModal");

  if (!modal || !cartao) {
    return;
  }

  definirValorCampoEntradaGrupo("editGroupId", cartao.dataset.id || "");
  definirValorCampoEntradaGrupo("editGroupName", cartao.dataset.name || "");
  definirValorCampoEntradaGrupo("editGroupDescription", cartao.dataset.description || "");
  definirValorCampoEntradaGrupo("editGroupStatus", cartao.dataset.status || "Ativo");

  const idsMembro = new Set(
    Array.from(cartao.querySelectorAll(".group-member-row"))
      .map((linha) => linha.dataset.memberId || "")
      .filter(Boolean),
  );
  const codigosPermissao = new Set((cartao.dataset.permissionCodes || "").split(",").filter(Boolean));

  definirMarcacoesModalGrupo("membros[]", idsMembro);
  definirMarcacoesModalGrupo("permissoes[]", codigosPermissao);
  sincronizarSecoesPermissaoModalGrupo();
  definirValorCampoEntradaGrupo("editGroupEmployeeSearch", "");
  filtrarMembrosModalGrupo();
  limparMensagemModalGrupo();

  window.titechRememberDialogTrigger?.();
  modal.hidden = false;
  document.getElementById("editGroupName")?.focus();
}

function fecharModalEdicaoGrupo() {
  const modal = document.getElementById("groupEditModal");

  if (modal) {
    modal.hidden = true;
  }
}

// O backend persiste o conjunto completo antes da atualização visual.
async function enviarModalGrupo(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = document.getElementById("saveGroupButton");
  const erro = validarFormularioModalGrupo(formulario);

  if (erro) {
    definirMensagemModalGrupo(erro, "error");
    return;
  }

  const confirmado = await confirmarEdicaoModalGrupo(formulario);

  if (!confirmado) {
    return;
  }

  definirCarregandoEdicaoGrupo(botaoEnviar, true, "Salvando...");
  limparMensagemModalGrupo();

  try {
    const resultado = await enviarEdicaoGrupo(formulario.action, new FormData(formulario));

    if (resultado.grupo) {
      atualizarCartaoGrupo(resultado.grupo);
    }

    fecharModalEdicaoGrupo();
    definirMensagemEdicaoGrupo(resultado.message || "Grupo atualizado com sucesso.", "success");
  } catch (erro) {
    definirMensagemModalGrupo(erro.message || "Nao foi possivel atualizar o grupo.", "error");
  } finally {
    definirCarregandoEdicaoGrupo(botaoEnviar, false);
  }
}

// A remoção individual exige confirmação e preserva a consistência das métricas.
async function removerMembroGrupo(botao) {
  const linha = botao.closest(".group-member-row");
  const cartao = botao.closest(".group-edit-item");

  if (!linha || !cartao) {
    return;
  }

  const nomeMembro = linha.querySelector(".group-member-info strong")?.textContent || "este membro";
  const nomeGrupo = cartao.dataset.name || "este grupo";
  const confirmado = await confirmarAcaoEdicaoGrupo({
    title: `Remover ${nomeMembro}?`,
    text: `O colaborador sera removido do grupo ${nomeGrupo}.`,
    confirmButtonText: "Remover membro",
    icon: "warning",
  });

  if (!confirmado) {
    return;
  }

  const corpoRequisicao = new FormData();
  corpoRequisicao.append("csrf_token", obterTokenCsrfEdicaoGrupo());
  corpoRequisicao.append("grupo_id", cartao.dataset.id || "");
  corpoRequisicao.append("usuario_id", linha.dataset.memberId || "");

  definirCarregandoEdicaoGrupo(botao, true, "Removendo...");
  limparMensagemEdicaoGrupo();

  try {
    const resultado = await enviarEdicaoGrupo("../Backend/remover-membro-grupo.php", corpoRequisicao);

    linha.remove();
    atualizarQuantidadeMembroGrupo(cartao, -1);
    garantirEstadoVazioMembroGrupo(cartao);
    definirMensagemEdicaoGrupo(resultado.message || "Membro removido do grupo.", "success");
  } catch (erro) {
    definirMensagemEdicaoGrupo(erro.message || "Nao foi possivel remover o membro.", "error");
  } finally {
    definirCarregandoEdicaoGrupo(botao, false);
  }
}

// A exclusão só remove o cartão após resposta bem-sucedida do servidor.
async function excluirGrupo(botao) {
  const cartao = botao.closest(".group-edit-item");

  if (!cartao) {
    return;
  }

  const nomeGrupo = cartao.dataset.name || "este grupo";
  const confirmado = await confirmarAcaoEdicaoGrupo({
    title: `Excluir ${nomeGrupo}?`,
    text: "Esta acao remove o grupo, seus membros e suas permissoes.",
    confirmButtonText: "Excluir grupo",
    icon: "warning",
  });

  if (!confirmado) {
    return;
  }

  const corpoRequisicao = new FormData();
  corpoRequisicao.append("csrf_token", obterTokenCsrfEdicaoGrupo());
  corpoRequisicao.append("id", cartao.dataset.id || "");

  definirCarregandoEdicaoGrupo(botao, true, "Excluindo...");
  limparMensagemEdicaoGrupo();

  try {
    const resultado = await enviarEdicaoGrupo("../Backend/excluir-grupo.php", corpoRequisicao);
    const membrosRemovidos = Number(cartao.dataset.members || resultado.grupo?.total_membros || 0);
    const permissoesRemovidas = Number(cartao.dataset.permissions || resultado.grupo?.total_permissoes || 0);

    cartao.remove();
    incrementarMetricaEdicaoGrupo("editGroupMetricTotal", -1);
    incrementarMetricaEdicaoGrupo("editGroupMetricMembers", -membrosRemovidos);
    incrementarMetricaEdicaoGrupo("editGroupMetricPermissions", -permissoesRemovidas);
    filtrarItensEdicaoGrupo();
    definirMensagemEdicaoGrupo(resultado.message || "Grupo excluido com sucesso.", "success");
  } catch (erro) {
    definirMensagemEdicaoGrupo(erro.message || "Nao foi possivel excluir o grupo.", "error");
  } finally {
    definirCarregandoEdicaoGrupo(botao, false);
  }
}

function validarFormularioModalGrupo(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "").trim();
  const status = String(dados.get("status") || "").trim();

  if (nome.length < 3) {
    return "Informe um nome de grupo com pelo menos 3 caracteres.";
  }

  if (nome.length > 90) {
    return "O nome do grupo pode ter no maximo 90 caracteres.";
  }

  if (!["Ativo", "Inativo"].includes(status)) {
    return "Selecione se o grupo ficara ativo ou inativo.";
  }

  return "";
}

async function confirmarEdicaoModalGrupo(formulario) {
  const dados = new FormData(formulario);
  const nome = String(dados.get("nome") || "este grupo").trim() || "este grupo";

  return confirmarAcaoEdicaoGrupo({
    title: "Salvar alteracoes?",
    text: `Confirme para atualizar nome, descricao, membros e permissoes de ${nome}.`,
    confirmButtonText: "Salvar alteracoes",
    cancelButtonText: "Continuar editando",
    icon: "warning",
  });
}

function filtrarMembrosModalGrupo() {
  const busca = normalizarTextoEdicaoGrupo(document.getElementById("editGroupEmployeeSearch")?.value || "");

  document.querySelectorAll("[data-modal-member-card]").forEach((cartao) => {
    const correspondencias = !busca || normalizarTextoEdicaoGrupo(cartao.dataset.search || "").includes(busca);
    cartao.hidden = !correspondencias;
  });
}

function atualizarCartaoGrupo(grupo) {
  const cartao = document.querySelector(`.group-edit-item[data-id="${escaparCssEdicaoGrupo(grupo.id)}"]`);

  if (!cartao) {
    return;
  }

  const totalMembroAntigo = Number(cartao.dataset.members || 0);
  const totalPermissaoAntigo = Number(cartao.dataset.permissions || 0);
  const membros = Array.isArray(grupo.membros) ? grupo.membros : [];
  const permissoes = Array.isArray(grupo.permissoes) ? grupo.permissoes : [];
  const totalMembro = Number(grupo.total_membros ?? membros.length);
  const totalPermissao = Number(grupo.total_permissoes ?? permissoes.length);
  const descricao = String(grupo.descricao || "");
  const status = String(grupo.status || "Ativo");

  cartao.dataset.name = String(grupo.nome || "");
  cartao.dataset.description = descricao;
  cartao.dataset.status = status;
  cartao.dataset.members = String(totalMembro);
  cartao.dataset.permissions = String(totalPermissao);
  cartao.dataset.permissionCodes = permissoes.map((permissao) => String(permissao.codigo || "")).filter(Boolean).join(",");
  cartao.dataset.search = montarValorBuscaGrupo(grupo, membros, permissoes);

  atualizarTextoElemento(cartao.querySelector("[data-group-name]"), grupo.nome || "--");
  atualizarTextoElemento(cartao.querySelector("[data-group-description]"), descricao || "Sem descricao informada.");
  atualizarTextoElemento(cartao.querySelector("[data-member-count]"), String(totalMembro));
  atualizarTextoElemento(cartao.querySelector("[data-permission-count]"), String(totalPermissao));
  atualizarIndicadorStatusGrupo(cartao, status);

  renderizarPermissoesGrupo(cartao, permissoes);
  renderizarMembrosGrupo(cartao, membros);
  incrementarMetricaEdicaoGrupo("editGroupMetricMembers", totalMembro - totalMembroAntigo);
  incrementarMetricaEdicaoGrupo("editGroupMetricPermissions", totalPermissao - totalPermissaoAntigo);
  filtrarItensEdicaoGrupo();
}

function renderizarPermissoesGrupo(cartao, permissoes) {
  const lista = cartao.querySelector("[data-permission-list]");

  if (!lista) {
    return;
  }

  lista.replaceChildren();

  if (!permissoes.length) {
    lista.append(criarEtiquetaPermissaoGrupo("Nenhuma permissao cadastrada."));
    return;
  }

  permissoes.forEach((permissao) => {
    lista.append(criarEtiquetaPermissaoGrupo(permissao.rotulo || permissao.codigo || "--"));
  });
}

// A lista usa elementos de DOM para manter o conteúdo da resposta como texto.
function renderizarMembrosGrupo(cartao, membros) {
  const lista = cartao.querySelector("[data-member-list]");

  if (!lista) {
    return;
  }

  lista.replaceChildren();

  membros.forEach((membro) => {
    lista.append(criarLinhaMembroGrupo(membro));
  });

  garantirEstadoVazioMembroGrupo(cartao);
}

function criarEtiquetaPermissaoGrupo(rotulo) {
  const etiqueta = document.createElement("span");
  etiqueta.textContent = rotulo;

  return etiqueta;
}

function criarLinhaMembroGrupo(membro) {
  const linha = document.createElement("article");
  const avatar = document.createElement("div");
  const informacoes = document.createElement("div");
  const nome = document.createElement("strong");
  const email = document.createElement("span");
  const detalhes = document.createElement("small");
  const botao = document.createElement("button");

  linha.className = "group-member-row";
  linha.dataset.memberId = membro.id || "";

  avatar.className = "group-member-avatar";
  avatar.setAttribute("aria-hidden", "true");
  avatar.textContent = membro.iniciais || obterIniciaisEdicaoGrupo(membro.nome || "");

  informacoes.className = "group-member-info";
  nome.textContent = membro.nome || "--";
  email.textContent = membro.email || "--";
  detalhes.textContent = `${membro.tipo_usuario || "--"} - ${membro.departamento || "--"}`;
  informacoes.append(nome, email, detalhes);

  botao.className = "table-action remove-member-button";
  botao.type = "button";
  botao.dataset.memberAction = "remove";
  botao.innerHTML = '<i class="bi bi-person-dash"></i><span>Remover</span>';

  linha.append(avatar, informacoes, botao);

  return linha;
}

function montarValorBuscaGrupo(grupo, membros, permissoes) {
  const buscaMembro = membros
    .map((membro) => `${membro.nome || ""} ${membro.email || ""} ${membro.departamento || ""}`)
    .join(" ");
  const buscaPermissao = permissoes
    .map((permissao) => `${permissao.rotulo || ""} ${permissao.codigo || ""}`)
    .join(" ");

  return `${grupo.nome || ""} ${grupo.descricao || ""} ${grupo.status || ""} ${buscaMembro} ${buscaPermissao}`.toLowerCase().trim();
}

function atualizarIndicadorStatusGrupo(cartao, status) {
  const indicador = cartao.querySelector("[data-group-status]");

  if (!indicador) {
    return;
  }

  const ehAtivo = normalizarTextoEdicaoGrupo(status) === "ativo";

  indicador.textContent = status || "Ativo";
  indicador.classList.toggle("status-active", ehAtivo);
  indicador.classList.toggle("status-inactive", !ehAtivo);
}

async function enviarEdicaoGrupo(url, corpoRequisicao) {
  const resposta = await fetch(url, {
    method: "POST",
    body: corpoRequisicao,
    headers: { Accept: "application/json" },
  });
  const resultado = await resposta.json().catch(() => ({
    ok: false,
    message: "Resposta invalida do servidor.",
  }));

  if (!resposta.ok || !resultado.ok) {
    throw new Error(resultado.message || "Nao foi possivel concluir a acao.");
  }

  return resultado;
}

async function confirmarAcaoEdicaoGrupo(opcoes) {
  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm(opcoes);
  }

  return window.confirm(`${opcoes.title}\n${opcoes.text}`);
}

function atualizarQuantidadeMembroGrupo(cartao, quantidade) {
  const valorProximo = Math.max(0, Number(cartao.dataset.members || 0) + quantidade);
  const contador = cartao.querySelector("[data-member-count]");

  cartao.dataset.members = String(valorProximo);

  if (contador) {
    contador.textContent = String(valorProximo);
  }

  incrementarMetricaEdicaoGrupo("editGroupMetricMembers", quantidade);
}

function garantirEstadoVazioMembroGrupo(cartao) {
  const lista = cartao.querySelector("[data-member-list]");

  if (!lista || lista.querySelector(".group-member-row") || lista.querySelector("[data-member-empty]")) {
    return;
  }

  const vazio = document.createElement("div");
  vazio.className = "group-member-empty";
  vazio.dataset.memberEmpty = "";
  vazio.innerHTML = '<i class="bi bi-info-circle"></i><span>Nenhum membro neste grupo.</span>';
  lista.append(vazio);
}

function filtrarItensEdicaoGrupo() {
  const busca = normalizarTextoEdicaoGrupo(document.getElementById("groupEditSearch")?.value || "");
  const cartoes = Array.from(document.querySelectorAll(".group-edit-item"));
  let visivel = 0;

  cartoes.forEach((cartao) => {
    const correspondencias = !busca || normalizarTextoEdicaoGrupo(cartao.dataset.search || "").includes(busca);

    cartao.hidden = !correspondencias;

    if (correspondencias) {
      visivel += 1;
    }
  });

  atualizarTextoEdicaoGrupo("groupEditResultCount", `${visivel.toLocaleString("pt-BR")} ${visivel === 1 ? "registro" : "registros"}`);
  atualizarEstadoVazioEdicaoGrupo();
}

function atualizarEstadoVazioEdicaoGrupo() {
  const vazio = document.getElementById("groupEditEmptyState");
  const cartoesVisiveis = Array.from(document.querySelectorAll(".group-edit-item")).filter((cartao) => !cartao.hidden);

  if (vazio) {
    vazio.hidden = cartoesVisiveis.length > 0;
  }
}

function definirMensagemModalGrupo(mensagem, tipo) {
  const elemento = document.getElementById("groupModalMessage");

  if (!elemento) {
    return;
  }

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("success", tipo === "success");
  elemento.classList.toggle("error", tipo === "error");
}

function limparMensagemModalGrupo() {
  definirMensagemModalGrupo("", "");
}

function definirMensagemEdicaoGrupo(mensagem, tipo) {
  const elemento = document.getElementById("groupEditPageMessage");

  if (!elemento) {
    return;
  }

  elemento.textContent = mensagem;
  elemento.classList.toggle("show", Boolean(mensagem));
  elemento.classList.toggle("success", tipo === "success");
  elemento.classList.toggle("error", tipo === "error");

  if (mensagem && tipo === "success") {
    setTimeout(limparMensagemEdicaoGrupo, ATRASO_OCULTACAO_MENSAGEM_EDICAO_GRUPO_MS);
  }
}

function limparMensagemEdicaoGrupo() {
  definirMensagemEdicaoGrupo("", "");
}

function definirCarregandoEdicaoGrupo(botao, estaCarregando, textoCarregando = "Aguarde...") {
  if (!botao) {
    return;
  }

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

function incrementarMetricaEdicaoGrupo(id, quantidade) {
  const elemento = document.getElementById(id);
  const atual = Number.parseInt(elemento?.textContent || "0", 10);

  if (!elemento || Number.isNaN(atual)) {
    return;
  }

  elemento.textContent = String(Math.max(0, atual + quantidade));
}

function atualizarTextoEdicaoGrupo(id, valor) {
  const elemento = document.getElementById(id);

  if (elemento) {
    elemento.textContent = valor;
  }
}

function atualizarTextoElemento(elemento, valor) {
  if (elemento) {
    elemento.textContent = valor;
  }
}

function definirValorCampoEntradaGrupo(id, valor) {
  const campoEntrada = document.getElementById(id);

  if (campoEntrada) {
    campoEntrada.value = valor;
  }
}

function definirMarcacoesModalGrupo(nome, valoresSelecionados) {
  document.querySelectorAll(`#groupEditModal input[name="${nome}"]`).forEach((campoEntrada) => {
    campoEntrada.checked = valoresSelecionados.has(campoEntrada.value);
  });
}

function sincronizarSecoesPermissaoModalGrupo() {
  document.querySelectorAll("#groupEditModal .permission-section").forEach((secao) => {
    secao.open = Boolean(secao.querySelector('input[name="permissoes[]"]:checked'));
  });
}

function escaparCssEdicaoGrupo(valor) {
  if (window.CSS?.escape) {
    return window.CSS.escape(String(valor || ""));
  }

  return String(valor || "").replace(/["\\]/g, "\\$&");
}

function obterIniciaisEdicaoGrupo(nome) {
  const iniciais = String(nome || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte.charAt(0).toUpperCase())
    .join("");

  return iniciais || "TT";
}

function normalizarTextoEdicaoGrupo(valor) {
  return String(valor)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function obterTokenCsrfEdicaoGrupo() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}
