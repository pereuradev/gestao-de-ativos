(function () {
// Animacao de entrada padrao das paginas internas.
// O CSS parte de .page-loading e esta funcao libera a transicao no primeiro frame.
function iniciarAnimacaoPagina() {
  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

Object.assign(window, {
  startPageAnimation: iniciarAnimacaoPagina,
});
})();
