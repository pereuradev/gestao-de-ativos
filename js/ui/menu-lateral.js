(function () {
const CHAVE_ARMAZENAMENTO_LARGURA_BARRA_LATERAL = "titech-sidebar-width";
const CHAVE_ARMAZENAMENTO_ESTADO_COMPACTO = "titech-menu-lateral-compacto";
const LARGURA_PADRAO_BARRA_LATERAL = 292;
const LARGURA_MINIMA_BARRA_LATERAL = 236;
const LARGURA_MAXIMA_BARRA_LATERAL = 392;
const CONSULTA_TELA_AMPLA_BARRA_LATERAL = "(min-width: 921px)";

function configurarBarraLateral() {
  const barraLateral = document.getElementById("sidebar");

  // Algumas páginas ainda chamam esta função diretamente. A marca evita eventos duplicados.
  if (!barraLateral || barraLateral.dataset.eventosConfigurados === "true") {
    return;
  }

  barraLateral.dataset.eventosConfigurados = "true";

  const botaoAbrir = document.getElementById("openSidebar");
  const botaoFechar = document.getElementById("closeSidebar");
  const botaoAlternarEstado = document.getElementById("toggleSidebarCompact");
  const fundoModal = document.getElementById("sidebarBackdrop");

  botaoAbrir?.addEventListener("click", abrirBarraLateral);
  botaoFechar?.addEventListener("click", fecharBarraLateral);
  botaoAlternarEstado?.addEventListener("click", alternarEstadoCompactoBarraLateral);
  fundoModal?.addEventListener("click", fecharBarraLateral);

  window.addEventListener("keydown", (evento) => {
    if (evento.key !== "Escape") return;

    if (typeof window.fecharModalEdicao === "function") {
      window.fecharModalEdicao();
    }

    fecharBarraLateral();
  });

  document.querySelectorAll(".sidebar-nav a").forEach((atalho) => {
    atalho.addEventListener("click", () => {
      if (window.innerWidth <= 920) {
        fecharBarraLateral();
      }
    });
  });

  configurarRedimensionamentoBarraLateral();
  aplicarEstadoCompactoSalvoBarraLateral();
}

function abrirBarraLateral() {
  document.documentElement.classList.add("sidebar-open");
  document.body.classList.add("sidebar-open");
}

function fecharBarraLateral() {
  document.documentElement.classList.remove("sidebar-open");
  document.body.classList.remove("sidebar-open");
}

function configurarRedimensionamentoBarraLateral() {
  const barraLateral = document.getElementById("sidebar");

  if (!barraLateral || barraLateral.dataset.resizeReady === "true") {
    return;
  }

  barraLateral.dataset.resizeReady = "true";

  const manipulador = document.createElement("div");
  manipulador.className = "sidebar-resize-handle";
  manipulador.setAttribute("role", "separator");
  manipulador.setAttribute("aria-orientation", "vertical");
  manipulador.setAttribute("aria-label", "Redimensionar menu lateral");
  manipulador.setAttribute("aria-valuemin", String(LARGURA_MINIMA_BARRA_LATERAL));
  manipulador.setAttribute("aria-valuemax", String(LARGURA_MAXIMA_BARRA_LATERAL));
  manipulador.tabIndex = 0;
  barraLateral.appendChild(manipulador);

  atualizarManipuladorRedimensionamentoBarraLateral(manipulador, obterLarguraBarraLateralAtual());

  let xInicial = 0;
  let larguraInicial = LARGURA_PADRAO_BARRA_LATERAL;
  let idPonteiroAtivo = null;

  const finalizarRedimensionamento = () => {
    if (idPonteiroAtivo === null) {
      return;
    }

    idPonteiroAtivo = null;
    document.body.classList.remove("sidebar-resizing");
    definirItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_LARGURA_BARRA_LATERAL, String(obterLarguraBarraLateralAtual()));
  };

  manipulador.addEventListener("pointerdown", (evento) => {
    if (!permiteRedimensionarBarraLateral()) {
      return;
    }

    idPonteiroAtivo = evento.pointerId;
    xInicial = evento.clientX;
    larguraInicial = obterLarguraBarraLateralAtual();
    document.body.classList.add("sidebar-resizing");
    manipulador.setPointerCapture?.(evento.pointerId);
    evento.preventDefault();
  });

  manipulador.addEventListener("pointermove", (evento) => {
    if (idPonteiroAtivo !== evento.pointerId) {
      return;
    }

    const larguraProxima = larguraInicial + (evento.clientX - xInicial);
    aplicarLarguraBarraLateral(larguraProxima);
    atualizarManipuladorRedimensionamentoBarraLateral(manipulador, obterLarguraBarraLateralAtual());
  });

  manipulador.addEventListener("pointerup", finalizarRedimensionamento);
  manipulador.addEventListener("pointercancel", finalizarRedimensionamento);

  manipulador.addEventListener("dblclick", () => {
    aplicarLarguraBarraLateral(LARGURA_PADRAO_BARRA_LATERAL);
    atualizarManipuladorRedimensionamentoBarraLateral(manipulador, LARGURA_PADRAO_BARRA_LATERAL);
    definirItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_LARGURA_BARRA_LATERAL, String(LARGURA_PADRAO_BARRA_LATERAL));
  });

  manipulador.addEventListener("keydown", (evento) => {
    if (!permiteRedimensionarBarraLateral()) {
      return;
    }

    const etapa = evento.shiftKey ? 24 : 12;
    const larguraAtual = obterLarguraBarraLateralAtual();
    let larguraProxima = larguraAtual;

    if (evento.key === "ArrowLeft") {
      larguraProxima = larguraAtual - etapa;
    } else if (evento.key === "ArrowRight") {
      larguraProxima = larguraAtual + etapa;
    } else if (evento.key === "Home") {
      larguraProxima = LARGURA_MINIMA_BARRA_LATERAL;
    } else if (evento.key === "End") {
      larguraProxima = LARGURA_MAXIMA_BARRA_LATERAL;
    } else {
      return;
    }

    evento.preventDefault();
    aplicarLarguraBarraLateral(larguraProxima);
    atualizarManipuladorRedimensionamentoBarraLateral(manipulador, obterLarguraBarraLateralAtual());
    definirItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_LARGURA_BARRA_LATERAL, String(obterLarguraBarraLateralAtual()));
  });

  window.addEventListener("resize", () => {
    aplicarEstadoCompactoSalvoBarraLateral();

    if (permiteRedimensionarBarraLateral()) {
      aplicarLarguraSalvaBarraLateral();
      atualizarManipuladorRedimensionamentoBarraLateral(manipulador, obterLarguraBarraLateralAtual());
      return;
    }

    document.body.style.removeProperty("--sidebar-width");
  });
}

function aplicarLarguraSalvaBarraLateral() {
  const larguraSalva = Number(obterItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_LARGURA_BARRA_LATERAL));

  if (!permiteRedimensionarBarraLateral()) {
    document.body.style.removeProperty("--sidebar-width");
    return;
  }

  aplicarLarguraBarraLateral(Number.isFinite(larguraSalva) ? larguraSalva : LARGURA_PADRAO_BARRA_LATERAL);
}

function aplicarLarguraBarraLateral(largura) {
  const larguraProxima = limitarLarguraBarraLateral(largura);

  document.body.style.setProperty("--sidebar-width", `${larguraProxima}px`);
}

function obterLarguraBarraLateralAtual() {
  const barraLateral = document.getElementById("sidebar");
  const larguraAtual = barraLateral?.getBoundingClientRect().width || LARGURA_PADRAO_BARRA_LATERAL;

  return limitarLarguraBarraLateral(larguraAtual);
}

function limitarLarguraBarraLateral(largura) {
  const larguraNumerica = Number(largura);

  if (!Number.isFinite(larguraNumerica)) {
    return LARGURA_PADRAO_BARRA_LATERAL;
  }

  return Math.min(LARGURA_MAXIMA_BARRA_LATERAL, Math.max(LARGURA_MINIMA_BARRA_LATERAL, Math.round(larguraNumerica)));
}

function atualizarManipuladorRedimensionamentoBarraLateral(manipulador, largura) {
  manipulador.setAttribute("aria-valuenow", String(limitarLarguraBarraLateral(largura)));
}

function permiteRedimensionarBarraLateral() {
  const estaEmTelaAmpla = window.matchMedia?.(CONSULTA_TELA_AMPLA_BARRA_LATERAL)?.matches ?? window.innerWidth >= 921;

  return estaEmTelaAmpla && !barraLateralEstaCompacta();
}

function barraLateralEstaCompacta() {
  return document.body.classList.contains("menu-lateral-compacto");
}

function aplicarEstadoCompactoBarraLateral(deveCompactar, opcoes = {}) {
  const { persistir = true } = opcoes;
  const estaEmTelaAmpla = window.matchMedia?.(CONSULTA_TELA_AMPLA_BARRA_LATERAL)?.matches ?? window.innerWidth >= 921;
  const deveAplicarEstadoCompacto = Boolean(deveCompactar) && estaEmTelaAmpla;

  document.body.classList.toggle("menu-lateral-compacto", deveAplicarEstadoCompacto);
  atualizarControleEstadoCompactoBarraLateral(deveAplicarEstadoCompacto);
  atualizarTitulosMenuLateral(deveAplicarEstadoCompacto);

  if (!deveAplicarEstadoCompacto && estaEmTelaAmpla) {
    aplicarLarguraSalvaBarraLateral();
  }

  if (persistir) {
    definirItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_ESTADO_COMPACTO, String(Boolean(deveCompactar)));
  }
}

function alternarEstadoCompactoBarraLateral() {
  aplicarEstadoCompactoBarraLateral(!barraLateralEstaCompacta());
}

function aplicarEstadoCompactoSalvoBarraLateral() {
  const estadoSalvo = obterItemBarraLateralSalvo(CHAVE_ARMAZENAMENTO_ESTADO_COMPACTO) === "true";

  aplicarEstadoCompactoBarraLateral(estadoSalvo, { persistir: false });
}

function atualizarControleEstadoCompactoBarraLateral(estaCompacta) {
  const botaoAlternarEstado = document.getElementById("toggleSidebarCompact");

  if (!botaoAlternarEstado) return;

  const rotulo = estaCompacta ? "Expandir menu lateral" : "Recolher menu lateral";
  const icone = botaoAlternarEstado.querySelector("i");

  botaoAlternarEstado.setAttribute("aria-expanded", String(!estaCompacta));
  botaoAlternarEstado.setAttribute("aria-label", rotulo);
  botaoAlternarEstado.title = rotulo;

  if (icone) {
    icone.className = estaCompacta ? "bi bi-chevron-bar-right" : "bi bi-chevron-bar-left";
  }
}

function atualizarTitulosMenuLateral(estaCompacta) {
  document.querySelectorAll(".sidebar-nav .nav-link, .sidebar-footer .logout-button").forEach((item) => {
    if (item.dataset.tituloOriginal === undefined) {
      item.dataset.tituloOriginal = item.getAttribute("title") || "";
    }

    const tituloOriginal = item.dataset.tituloOriginal;
    const rotulo = item.querySelector("span")?.textContent?.trim() || "";

    if (estaCompacta && !tituloOriginal && rotulo) {
      item.setAttribute("title", rotulo);
      return;
    }

    if (tituloOriginal) {
      item.setAttribute("title", tituloOriginal);
    } else {
      item.removeAttribute("title");
    }
  });
}

function configurarGruposNavegacao() {
  const grupos = Array.from(document.querySelectorAll("[data-nav-group]"));

  grupos.forEach((grupo) => {
    const botao = grupo.querySelector(".nav-toggle");

    if (!botao || botao.dataset.grupoNavegacaoConfigurado === "true") return;

    botao.dataset.grupoNavegacaoConfigurado = "true";

    botao.addEventListener("click", () => {
      const estavaCompacta = barraLateralEstaCompacta();
      const deveAbrir = estavaCompacta || !grupo.classList.contains("open");

      if (estavaCompacta) {
        aplicarEstadoCompactoBarraLateral(false);
      }

      grupos.forEach((outroGrupo) => {
        if (outroGrupo === grupo) return;

        outroGrupo.classList.remove("open");
        outroGrupo.querySelector(".nav-toggle")?.setAttribute("aria-expanded", "false");
      });

      grupo.classList.toggle("open", deveAbrir);
      botao.setAttribute("aria-expanded", String(deveAbrir));
    });
  });
}

function obterItemBarraLateralSalvo(chave) {
  if (typeof window.obterItemSalvo === "function") {
    return window.obterItemSalvo(chave);
  }

  try {
    return localStorage.getItem(chave);
  } catch {
    return null;
  }
}

function definirItemBarraLateralSalvo(chave, valor) {
  if (typeof window.definirItemSalvo === "function") {
    window.definirItemSalvo(chave, valor);
    return;
  }

  try {
    localStorage.setItem(chave, valor);
  } catch {
    return;
  }
}

Object.assign(window, {
  configurarBarraLateral,
  abrirBarraLateral,
  fecharBarraLateral,
  aplicarLarguraBarraLateral,
  aplicarLarguraSalvaBarraLateral,
  aplicarEstadoCompactoBarraLateral,
  aplicarEstadoCompactoSalvoBarraLateral,
  alternarEstadoCompactoBarraLateral,
  configurarRedimensionamentoBarraLateral,
  configurarGruposNavegacao,
  // Os nomes antigos continuam disponíveis para páginas que ainda não foram migradas.
  setupSidebar: configurarBarraLateral,
  openSidebar: abrirBarraLateral,
  closeSidebar: fecharBarraLateral,
  applySidebarWidth: aplicarLarguraBarraLateral,
  applySavedSidebarWidth: aplicarLarguraSalvaBarraLateral,
  setupSidebarResize: configurarRedimensionamentoBarraLateral,
  setupNavGroups: configurarGruposNavegacao,
});

aplicarEstadoCompactoSalvoBarraLateral();
})();
