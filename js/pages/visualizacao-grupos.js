// Filtra grupos já renderizados e mantém contador e estado vazio sincronizados.
// A normalização permite buscar por nomes, membros e permissões sem nova consulta ao servidor.

document.addEventListener("DOMContentLoaded", inicializarPaginaVisualizacaoGrupo);

function inicializarPaginaVisualizacaoGrupo() {
  chamarGlobalVisualizacaoGrupo("iniciarAnimacaoPagina");
  chamarGlobalVisualizacaoGrupo("carregarTemaSalvo");
  chamarGlobalVisualizacaoGrupo("configurarAlternadorTema");
  chamarGlobalVisualizacaoGrupo("configurarBarraLateral");
  chamarGlobalVisualizacaoGrupo("configurarGruposNavegacao");
  configurarBuscaVisualizacaoGrupo();
}

function chamarGlobalVisualizacaoGrupo(nomeFuncao) {
  if (typeof window[nomeFuncao] === "function") {
    window[nomeFuncao]();
  }
}

function configurarBuscaVisualizacaoGrupo() {
  document.getElementById("groupViewSearch")?.addEventListener("input", filtrarItensVisualizacaoGrupo);
  filtrarItensVisualizacaoGrupo();
}

// A busca considera o texto agregado de grupo, membros e permissões renderizado pelo PHP.
function filtrarItensVisualizacaoGrupo() {
  const busca = normalizarTextoVisualizacaoGrupo(document.getElementById("groupViewSearch")?.value || "");
  const cartoes = Array.from(document.querySelectorAll("#groupViewList .group-edit-item"));
  let visivel = 0;

  cartoes.forEach((cartao) => {
    const correspondencias = !busca || normalizarTextoVisualizacaoGrupo(cartao.dataset.search || "").includes(busca);

    cartao.hidden = !correspondencias;

    if (correspondencias) {
      visivel += 1;
    }
  });

  atualizarQuantidadeVisualizacaoGrupo(visivel);
  atualizarEstadoVazioVisualizacaoGrupo();
}

function atualizarQuantidadeVisualizacaoGrupo(total) {
  const contador = document.getElementById("groupViewResultCount");

  if (!contador) {
    return;
  }

  contador.textContent = `${total.toLocaleString("pt-BR")} ${total === 1 ? "registro" : "registros"}`;
}

function atualizarEstadoVazioVisualizacaoGrupo() {
  const vazio = document.getElementById("groupViewEmptyState");
  const cartoesVisiveis = Array.from(document.querySelectorAll("#groupViewList .group-edit-item"))
    .filter((cartao) => !cartao.hidden);

  if (vazio) {
    vazio.hidden = cartoesVisiveis.length > 0;
  }
}

function normalizarTextoVisualizacaoGrupo(valor) {
  if (typeof window.normalizarTexto === "function") {
    return window.normalizarTexto(valor);
  }

  return String(valor || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}
