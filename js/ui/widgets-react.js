// Monta widgets React isolados usados como melhorias progressivas da interface.
// React e ReactDOM são dependências globais; sem elas, o módulo encerra sem afetar a página.

(function () {
  function aoCarregar(funcaoRetorno) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", funcaoRetorno, { once: true });
      return;
    }

    funcaoRetorno();
  }

  function inicializarWidgetsReact() {
    // O widget é progressivo: a página continua funcional quando React não está disponível.
    if (!window.React || !window.ReactDOM) {
      return;
    }

    const raiz = document.createElement("div");
    raiz.id = "reactResponsiveRoot";
    raiz.dataset.reactRoot = "responsive-tools";
    document.body.appendChild(raiz);

    const criarElementoReact = window.React.createElement;
    const { useEffect, useRef, useState } = window.React;

    function DialogoPermissaoSemCabecalho() {
      const [estaAberto, definirAberto] = useState(false);
      const [recurso, definirRecurso] = useState("esta area");
      const referenciaBotaoFechar = useRef(null);

      function fecharDialogo() {
        definirAberto(false);
      }

      // Escuta o evento global usado pelas rotas para abrir o aviso de permissão.
      useEffect(() => {
        function abrirDialogo(evento) {
          evento.preventDefault?.();
          document.getElementById("uxToastRegion")?.remove();
          document.getElementById("settingsToast")?.classList.remove("show");
          definirRecurso(evento.detail?.resource || "esta area");
          definirAberto(true);
        }

        window.addEventListener("titech:permission-denied", abrirDialogo);

        if (document.body.dataset.permissionDialogOpen === "true") {
          abrirDialogo({
            detail: {
              resource: document.body.dataset.permissionResource || "esta area",
            },
          });
        }

        return () => window.removeEventListener("titech:permission-denied", abrirDialogo);
      }, []);

      // Ao abrir, controla Escape e restaura o foco no elemento anterior ao fechar.
      useEffect(() => {
        if (!estaAberto) {
          return undefined;
        }

        const focoAnterior = document.activeElement;
        referenciaBotaoFechar.current?.focus();

        function tratarTeclaPressionada(evento) {
          if (evento.key === "Escape") {
            evento.preventDefault();
            fecharDialogo();
          }
        }

        document.addEventListener("keydown", tratarTeclaPressionada);

        return () => {
          document.removeEventListener("keydown", tratarTeclaPressionada);
          focoAnterior?.focus?.();
        };
      }, [estaAberto]);

      if (!estaAberto) {
        return null;
      }

      return criarElementoReact(
        "div",
        {
          className: "permission-dialog-layer",
          role: "presentation",
        },
        criarElementoReact("div", {
          className: "permission-dialog-backdrop",
          onClick: fecharDialogo,
        }),
        criarElementoReact(
          "section",
          {
            className: "permission-dialog-panel",
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": "permissionDialogTitle",
            "aria-describedby": "permissionDialogDescription",
          },
          criarElementoReact(
            "div",
            { className: "permission-dialog-icon", "aria-hidden": "true" },
            criarElementoReact("i", { className: "bi bi-shield-lock-fill" }),
          ),
          criarElementoReact("p", { className: "section-tag" }, "Permissao necessaria"),
          criarElementoReact("h2", { id: "permissionDialogTitle" }, "Acesso restrito"),
          criarElementoReact(
            "p",
            { id: "permissionDialogDescription" },
            `Voce nao tem permissao para acessar ${recurso}. Solicite liberacao a um administrador para continuar.`,
          ),
          criarElementoReact(
            "button",
            {
              ref: referenciaBotaoFechar,
              type: "button",
              className: "primary-button permission-dialog-close",
              onClick: fecharDialogo,
            },
            criarElementoReact("i", { className: "bi bi-check2-circle", "aria-hidden": "true" }),
            criarElementoReact("span", null, "Entendi"),
          ),
        ),
      );
    }

    function AplicacaoWidgetsReact() {
      return criarElementoReact(DialogoPermissaoSemCabecalho);
    }

    window.ReactDOM.createRoot(raiz).render(criarElementoReact(AplicacaoWidgetsReact));
  }

  aoCarregar(inicializarWidgetsReact);
})();
