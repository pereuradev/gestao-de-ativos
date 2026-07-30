// Aplica melhorias compartilhadas de animação, avisos, diálogos e acessibilidade.
// As funções públicas são expostas em window para reutilização pelos scripts de cada página.

(function () {
  const SELETOR_REVELACAO = [
    ".hero-panel",
    ".metric-card",
    ".chart-shell",
    ".dashboard-status",
  ].join(",");
  const SELETOR_EFEITO_ONDA = [
    "button",
    ".nav-link",
    ".nav-submenu a",
    ".form-action-button",
    ".primary-button",
    ".secondary-button",
    ".table-action",
    ".logout-button",
  ].join(",");
  const SELETOR_MENSAGEM = [
    ".form-message",
    "[id$='Message']",
  ].join(",");

  const mensagensExibidas = new WeakMap();
  const acionadoresDialogo = new WeakMap();
  let acionadorDialogoPendente = null;
  let dialogoAtivo = null;
  let eventosEfeitoOndaProntos = false;

  document.addEventListener("DOMContentLoaded", inicializarExperienciaProfissional);
  window.addEventListener("titech:motion-change", tratarAlteracaoPreferenciaMovimento);

  window.titechToast = exibirNotificacao;
  window.titechConfirm = confirmarAcao;
  window.titechRememberDialogTrigger = lembrarAcionadorDialogo;

  function inicializarExperienciaProfissional() {
    document.body.classList.add("ux-enhanced");
    requestAnimationFrame(() => document.body.classList.remove("page-loading"));
    configurarRevelacoes();
    configurarEfeitosOnda();
    configurarDicas();
    configurarNotificacoesMensagem();
    configurarAtalhoBusca();
    configurarAcessibilidadeTabela();
    configurarGerenciamentoFocoDialogo();
  }

  // Animações são desativadas quando o usuário prefere movimento reduzido.
  function configurarRevelacoes() {
    const elementos = Array.from(document.querySelectorAll(SELETOR_REVELACAO));

    if (movimentoReduzidoEstaAtivado()) {
      elementos.forEach((elemento) => elemento.classList.add("is-visible"));
      return;
    }

    elementos.forEach((elemento, indice) => {
      elemento.classList.add("ux-reveal");
      elemento.style.transitionDelay = `${Math.min(indice * 28, 168)}ms`;
    });

    if (!("IntersectionObserver" in window)) {
      elementos.forEach((elemento) => elemento.classList.add("is-visible"));
      return;
    }

    const observador = new IntersectionObserver(
      (entradas) => {
        entradas.forEach((entrada) => {
          if (!entrada.isIntersecting) return;

          entrada.target.classList.add("is-visible");
          observador.unobserve(entrada.target);
        });
      },
      { threshold: 0.12 },
    );

    elementos.forEach((elemento) => observador.observe(elemento));
  }

  function configurarEfeitosOnda() {
    if (movimentoReduzidoEstaAtivado()) {
      removerArtefatosEfeitoOnda();
      return;
    }

    document.querySelectorAll(SELETOR_EFEITO_ONDA).forEach((elemento) => {
      if (elemento.classList.contains("icon-button")) return;
      elemento.classList.add("ux-ripple");
    });

    if (eventosEfeitoOndaProntos) return;

    eventosEfeitoOndaProntos = true;

    document.addEventListener("click", (evento) => {
      if (movimentoReduzidoEstaAtivado()) return;

      const destino = evento.target.closest(".ux-ripple");

      if (!destino || destino.disabled) return;

      const retangulo = destino.getBoundingClientRect();
      const efeitoOnda = document.createElement("span");

      efeitoOnda.className = "ux-ripple-dot";
      efeitoOnda.style.left = `${evento.clientX - retangulo.left}px`;
      efeitoOnda.style.top = `${evento.clientY - retangulo.top}px`;

      destino.append(efeitoOnda);
      efeitoOnda.addEventListener("animationend", () => efeitoOnda.remove(), { once: true });
    });
  }

  function tratarAlteracaoPreferenciaMovimento() {
    if (!movimentoReduzidoEstaAtivado()) {
      configurarRevelacoes();
      configurarEfeitosOnda();
      return;
    }

    document.querySelectorAll(SELETOR_REVELACAO).forEach((elemento) => {
      elemento.classList.add("is-visible");
      elemento.style.transitionDelay = "";
    });
    removerArtefatosEfeitoOnda();
  }

  function removerArtefatosEfeitoOnda() {
    document.querySelectorAll(".ux-ripple-dot").forEach((efeitoOnda) => efeitoOnda.remove());
  }

  function movimentoReduzidoEstaAtivado() {
    if (document.body?.dataset.motion === "reduced") {
      return true;
    }

    try {
      if (localStorage.getItem("titech-motion") === "reduced") {
        return true;
      }
    } catch {
      return false;
    }

    return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
  }

  function configurarDicas() {
    document.querySelectorAll(".icon-button[aria-label], .table-action[aria-label]").forEach((elemento) => {
      if (elemento.dataset.uxTooltip) return;

      const rotulo = elemento.getAttribute("aria-label");

      if (rotulo) {
        elemento.dataset.uxTooltip = rotulo;
      }
    });
  }

  // Observa mensagens existentes para gerar avisos sem alterar cada módulo de página.
  function configurarNotificacoesMensagem() {
    const elementos = Array.from(document.querySelectorAll(SELETOR_MENSAGEM));

    elementos.forEach((elemento) => {
      obterPossivelNotificacaoElemento(elemento);

      const observador = new MutationObserver(() => {
        obterPossivelNotificacaoElemento(elemento);
      });

      observador.observe(elemento, {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true,
      });
    });
  }

  function obterPossivelNotificacaoElemento(elemento) {
    const mensagem = normalizarMensagem(elemento.textContent);

    if (!mensagem || elemento.hidden || deveIgnorarElementoMensagem(elemento)) return;

    const anterior = mensagensExibidas.get(elemento);

    if (anterior === mensagem) return;

    mensagensExibidas.set(elemento, mensagem);
    exibirNotificacao(mensagem, obterTipoMensagem(elemento));
  }

  function deveIgnorarElementoMensagem(elemento) {
    if (elemento.classList.contains("form-message") && !elemento.classList.contains("show")) {
      return true;
    }

    if (elemento.id && /message$/i.test(elemento.id) && !elemento.classList.contains("show")) {
      return true;
    }

    if (elemento.id && /resultcount$/i.test(elemento.id)) {
      return true;
    }

    return false;
  }

  function obterTipoMensagem(elemento) {
    if (elemento.classList.contains("error") || elemento.classList.contains("error-status")) {
      return "error";
    }

    if (elemento.classList.contains("success") || elemento.classList.contains("success-status")) {
      return "success";
    }

    return "info";
  }

  function exibirNotificacao(mensagem, tipo = "info") {
    const texto = normalizarMensagem(mensagem);

    if (!texto) return;

    const hospedeiro = obterHospedeiroNotificacao();
    const notificacao = document.createElement("div");
    const icone = document.createElement("span");
    const rotulo = document.createElement("span");
    const botaoFechar = document.createElement("button");

    notificacao.className = `ux-toast ux-toast-${normalizarIconeNotificacao(tipo)}`;
    notificacao.setAttribute("role", tipo === "error" ? "alert" : "status");
    icone.className = "ux-toast-icon";
    icone.setAttribute("aria-hidden", "true");
    icone.textContent = obterSimboloNotificacao(tipo);
    rotulo.textContent = texto;
    botaoFechar.className = "ux-toast-close";
    botaoFechar.type = "button";
    botaoFechar.setAttribute("aria-label", "Fechar aviso");
    botaoFechar.textContent = "x";

    botaoFechar.addEventListener("click", () => dispensarNotificacao(notificacao));
    notificacao.append(icone, rotulo, botaoFechar);
    hospedeiro.append(notificacao);

    requestAnimationFrame(() => notificacao.classList.add("show"));
    setTimeout(() => dispensarNotificacao(notificacao), tipo === "error" ? 5200 : 3200);
  }

  // O diálogo resolve uma Promise e devolve o foco ao elemento que iniciou a ação.
  async function confirmarAcao(opcoes = {}) {
    const titulo = opcoes.title || "Confirmar acao?";
    const texto = opcoes.text || "";
    const textoBotaoConfirmar = opcoes.confirmButtonText || "Confirmar";
    const textoBotaoCancelar = opcoes.cancelButtonText || "Cancelar";

    return new Promise((resolverPromessa) => {
      const focoAnterior = document.activeElement;
      const fundoModal = document.createElement("div");
      const dialogo = document.createElement("section");
      const tituloSecao = document.createElement("h2");
      const descricao = document.createElement("p");
      const acoes = document.createElement("div");
      const botaoCancelar = document.createElement("button");
      const botaoConfirmar = document.createElement("button");
      const idTitulo = `ux-confirm-title-${Date.now()}`;
      const idDescricao = `ux-confirm-description-${Date.now()}`;

      fundoModal.className = "ux-confirm-backdrop";
      dialogo.className = "ux-confirm-card";
      dialogo.setAttribute("role", "dialog");
      dialogo.setAttribute("aria-modal", "true");
      dialogo.setAttribute("aria-labelledby", idTitulo);
      dialogo.setAttribute("aria-describedby", idDescricao);
      tituloSecao.id = idTitulo;
      tituloSecao.textContent = titulo;
      descricao.id = idDescricao;
      descricao.textContent = texto;
      acoes.className = "ux-confirm-actions";
      botaoCancelar.className = "ux-confirm-button ux-confirm-cancel";
      botaoConfirmar.className = "ux-confirm-button ux-confirm-primary";
      botaoCancelar.type = "button";
      botaoConfirmar.type = "button";
      botaoCancelar.textContent = textoBotaoCancelar;
      botaoConfirmar.textContent = textoBotaoConfirmar;

      acoes.append(botaoCancelar, botaoConfirmar);
      dialogo.append(tituloSecao, descricao, acoes);
      fundoModal.append(dialogo);
      document.body.append(fundoModal);

      const finalizar = (valor) => {
        desativarDialogo(dialogo, focoAnterior);
        fundoModal.classList.remove("show");
        setTimeout(() => fundoModal.remove(), 160);
        resolverPromessa(valor);
      };

      botaoCancelar.addEventListener("click", () => finalizar(false));
      botaoConfirmar.addEventListener("click", () => finalizar(true));
      fundoModal.addEventListener("click", (evento) => {
        if (evento.target === fundoModal) {
          finalizar(false);
        }
      });
      fundoModal.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape") {
          evento.preventDefault();
          finalizar(false);
        }
      });

      requestAnimationFrame(() => {
        fundoModal.classList.add("show");
        ativarDialogo(dialogo, focoAnterior);
        botaoCancelar.focus({ preventScroll: true });
      });
    });
  }

  function configurarAtalhoBusca() {
    window.addEventListener("keydown", (evento) => {
      if (evento.key !== "/" || evento.ctrlKey || evento.metaKey || evento.altKey) return;
      if (ehDestinoDigitacao(evento.target)) return;

      const busca = document.querySelector("input[type='search']");

      if (!busca) return;

      evento.preventDefault();
      busca.focus();
      busca.select();
      exibirNotificacao("Busca pronta para digitar.", "info");
    });
  }

  // Completa informações semânticas ausentes sem duplicar marcação em todas as páginas.
  function configurarAcessibilidadeTabela() {
    document.querySelectorAll(".records-table").forEach((tabela) => {
      tabela.querySelectorAll("th").forEach((cabecalho) => {
        if (!cabecalho.scope) {
          cabecalho.scope = "col";
        }
      });

      if (tabela.querySelector("caption")) return;

      const titulo = tabela.closest(".content-card")?.querySelector("h3")?.textContent || "Tabela de registros";
      const legenda = document.createElement("caption");

      legenda.className = "ux-sr-only";
      legenda.textContent = normalizarMensagem(titulo);
      tabela.prepend(legenda);
    });
  }

  // Centraliza o foco dos modais legados e dos diálogos criados dinamicamente.
  function configurarGerenciamentoFocoDialogo() {
    document.querySelectorAll("[role='dialog'][aria-modal='true']").forEach((dialogo) => {
      const conteiner = dialogo.closest("[hidden], .edit-modal-backdrop") || dialogo;

      if (!conteiner || conteiner.dataset.uxDialogManaged) return;

      conteiner.dataset.uxDialogManaged = "true";

      const sincronizarEstadoDialogo = () => {
        if (!conteiner.hidden) {
          ativarDialogo(dialogo, acionadorDialogoPendente || document.activeElement);
          acionadorDialogoPendente = null;
          return;
        }

        if (dialogoAtivo === dialogo) {
          desativarDialogo(dialogo, acionadoresDialogo.get(dialogo));
        }
      };

      new MutationObserver(sincronizarEstadoDialogo).observe(conteiner, {
        attributes: true,
        attributeFilter: ["hidden"],
      });

      sincronizarEstadoDialogo();
    });

    document.addEventListener("keydown", conterFocoDialogo);
  }

  function lembrarAcionadorDialogo() {
    acionadorDialogoPendente = document.activeElement;
  }

  function ativarDialogo(dialogo, acionador) {
    dialogoAtivo = dialogo;

    if (acionador && !dialogo.contains(acionador)) {
      acionadoresDialogo.set(dialogo, acionador);
    }

    requestAnimationFrame(() => {
      if (dialogo.contains(document.activeElement)) return;

      obterElementosFocaveis(dialogo)[0]?.focus({ preventScroll: true });
    });
  }

  function desativarDialogo(dialogo, acionador) {
    if (dialogoAtivo === dialogo) {
      dialogoAtivo = null;
    }

    if (acionador?.isConnected && !dialogo.contains(acionador)) {
      acionador.focus({ preventScroll: true });
    }
  }

  function conterFocoDialogo(evento) {
    if (!dialogoAtivo || evento.key !== "Tab") return;

    const focavel = obterElementosFocaveis(dialogoAtivo);

    if (focavel.length === 0) {
      evento.preventDefault();
      return;
    }

    const primeiro = focavel[0];
    const ultimo = focavel[focavel.length - 1];

    if (evento.shiftKey && document.activeElement === primeiro) {
      evento.preventDefault();
      ultimo.focus();
      return;
    }

    if (!evento.shiftKey && document.activeElement === ultimo) {
      evento.preventDefault();
      primeiro.focus();
    }
  }

  function obterElementosFocaveis(conteiner) {
    return Array.from(
      conteiner.querySelectorAll(
        "a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])",
      ),
    ).filter((elemento) => elemento.offsetParent !== null || elemento === document.activeElement);
  }

  function obterHospedeiroNotificacao() {
    let hospedeiro = document.getElementById("uxToastRegion");

    if (!hospedeiro) {
      hospedeiro = document.createElement("div");
      hospedeiro.id = "uxToastRegion";
      hospedeiro.className = "ux-toast-region";
      hospedeiro.setAttribute("aria-live", "polite");
      hospedeiro.setAttribute("aria-relevant", "additions");
      document.body.append(hospedeiro);
    }

    return hospedeiro;
  }

  function dispensarNotificacao(notificacao) {
    notificacao.classList.remove("show");
    setTimeout(() => notificacao.remove(), 180);
  }

  function ehDestinoDigitacao(destino) {
    return Boolean(destino?.closest?.("input, textarea, select, [contenteditable='true']"));
  }

  function normalizarIconeNotificacao(tipo) {
    if (tipo === "success" || tipo === "error" || tipo === "warning") {
      return tipo;
    }

    return "info";
  }

  function obterSimboloNotificacao(tipo) {
    if (tipo === "success") return "OK";
    if (tipo === "error") return "!";
    if (tipo === "warning") return "!";

    return "i";
  }

  function normalizarMensagem(valor) {
    return String(valor || "").replace(/\s+/g, " ").trim();
  }
})();
