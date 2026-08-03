(function () {
// Helpers seguros para armazenamento local e normalizacao simples.
// Ficam em arquivo proprio porque preferencias, tema e sidebar usam a mesma base.
function obterItemSalvo(chave) {
  try {
    return localStorage.getItem(chave);
  } catch {
    return null;
  }
}

function definirItemSalvo(chave, valor) {
  try {
    localStorage.setItem(chave, valor);
  } catch {
    return;
  }
}

function normalizarEscolha(valor, valoresPermitidos, padrao) {
  const normalizado = String(valor ?? "").trim();

  return valoresPermitidos.includes(normalizado) ? normalizado : padrao;
}

Object.assign(window, {
  obterItemSalvo,
  definirItemSalvo,
  normalizarEscolha,
  // Os aliases antigos preservam páginas que ainda não foram migradas.
  getSavedItem: obterItemSalvo,
  setSavedItem: definirItemSalvo,
  normalizeChoice: normalizarEscolha,
});
})();
