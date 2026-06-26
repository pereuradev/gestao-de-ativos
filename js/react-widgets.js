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

    window.ReactDOM.createRoot(root).render(h(MobileDock));
  }

  onReady(initReactWidgets);
})();
