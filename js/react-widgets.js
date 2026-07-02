(function () {
  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback, { once: true });
      return;
    }

    callback();
  }

  function toggleTheme() {
    const toggle = document.getElementById("themeToggle") || document.getElementById("themeToggleLogin");

    if (toggle) {
      toggle.click();
    }
  }

  function openMenu() {
    if (typeof window.openSidebar === "function") {
      window.openSidebar();
      return;
    }

    document.body.classList.add("sidebar-open");
  }

  function scrollTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
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
    const { Fragment, useEffect, useRef, useState } = window.React;

    function MobileDock() {
      const hasSidebar = Boolean(document.getElementById("sidebar"));
      const actions = [
        hasSidebar && {
          key: "menu",
          icon: "bi bi-list",
          label: "Menu",
          action: openMenu,
        },
        {
          key: "theme",
          icon: "bi bi-circle-half",
          label: "Tema",
          action: toggleTheme,
        },
        {
          key: "top",
          icon: "bi bi-arrow-up",
          label: "Topo",
          action: scrollTop,
        },
      ].filter(Boolean);

      return h(
        "nav",
        {
          className: "react-mobile-dock",
          "aria-label": "Acoes rapidas responsivas",
        },
        actions.map((item) =>
          h(
            "button",
            {
              key: item.key,
              type: "button",
              onClick: item.action,
              "aria-label": item.label,
            },
            h("i", { className: item.icon, "aria-hidden": "true" }),
            h("span", null, item.label)
          )
        )
      );
    }

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
      return h(
        Fragment,
        null,
        h(MobileDock),
        h(HeadlessPermissionDialog),
      );
    }

    window.ReactDOM.createRoot(root).render(h(ReactWidgetsApp));
  }

  onReady(initReactWidgets);
})();
