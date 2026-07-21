(function () {
// Helpers seguros para armazenamento local e normalizacao simples.
// Ficam em arquivo proprio porque preferencias, tema e sidebar usam a mesma base.
function getSavedItem(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function setSavedItem(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    return;
  }
}

function normalizeChoice(value, allowedValues, fallback) {
  const normalized = String(value ?? "").trim();

  return allowedValues.includes(normalized) ? normalized : fallback;
}

Object.assign(window, {
  getSavedItem,
  setSavedItem,
  normalizeChoice,
});
})();
