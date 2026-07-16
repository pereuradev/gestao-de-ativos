(function () {
  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback, { once: true });
      return;
    }

    callback();
  }

  function initReactWidgets() {
    if (!window.React || !window.ReactDOM) {
      return;
    }

    const root = document.createElement("div");
    root.id = "reactResponsiveRoot";
    root.dataset.reactRoot = "responsive-tools";
    document.body.appendChild(root);

    const h = window.React.createElement;
    const { useEffect, useRef, useState } = window.React;

    function HeadlessPermissionDialog() {
      const [isOpen, setIsOpen] = useState(false);
      const [resource, setResource] = useState("esta area");
      const closeButtonRef = useRef(null);

      function closeDialog() {
        setIsOpen(false);
      }

      useEffect(() => {
        function openDialog(event) {
          event.preventDefault?.();
          document.getElementById("uxToastRegion")?.remove();
          document.getElementById("settingsToast")?.classList.remove("show");
          setResource(event.detail?.resource || "esta area");
          setIsOpen(true);
        }

        window.addEventListener("titech:permission-denied", openDialog);

        if (document.body.dataset.permissionDialogOpen === "true") {
          openDialog({
            detail: {
              resource: document.body.dataset.permissionResource || "esta area",
            },
          });
        }

        return () => window.removeEventListener("titech:permission-denied", openDialog);
      }, []);

      useEffect(() => {
        if (!isOpen) {
          return undefined;
        }

        const previousFocus = document.activeElement;
        closeButtonRef.current?.focus();

        function handleKeydown(event) {
          if (event.key === "Escape") {
            event.preventDefault();
            closeDialog();
          }
        }

        document.addEventListener("keydown", handleKeydown);

        return () => {
          document.removeEventListener("keydown", handleKeydown);
          previousFocus?.focus?.();
        };
      }, [isOpen]);

      if (!isOpen) {
        return null;
      }

      return h(
        "div",
        {
          className: "permission-dialog-layer",
          role: "presentation",
        },
        h("div", {
          className: "permission-dialog-backdrop",
          onClick: closeDialog,
        }),
        h(
          "section",
          {
            className: "permission-dialog-panel",
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": "permissionDialogTitle",
            "aria-describedby": "permissionDialogDescription",
          },
          h(
            "div",
            { className: "permission-dialog-icon", "aria-hidden": "true" },
            h("i", { className: "bi bi-shield-lock-fill" }),
          ),
          h("p", { className: "section-tag" }, "Permissao necessaria"),
          h("h2", { id: "permissionDialogTitle" }, "Acesso restrito"),
          h(
            "p",
            { id: "permissionDialogDescription" },
            `Voce nao tem permissao para acessar ${resource}. Solicite liberacao a um administrador para continuar.`,
          ),
          h(
            "button",
            {
              ref: closeButtonRef,
              type: "button",
              className: "primary-button permission-dialog-close",
              onClick: closeDialog,
            },
            h("i", { className: "bi bi-check2-circle", "aria-hidden": "true" }),
            h("span", null, "Entendi"),
          ),
        ),
      );
    }

    function ReactWidgetsApp() {
      return h(HeadlessPermissionDialog);
    }

    window.ReactDOM.createRoot(root).render(h(ReactWidgetsApp));
  }

  onReady(initReactWidgets);
})();
