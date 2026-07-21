// Aplica melhorias compartilhadas de animação, avisos, diálogos e acessibilidade.
// As funções públicas são expostas em window para reutilização pelos scripts de cada página.

(function () {
  const REVEAL_SELECTOR = [
    ".hero-panel",
    ".metric-card",
    ".chart-shell",
    ".dashboard-status",
  ].join(",");
  const RIPPLE_SELECTOR = [
    "button",
    ".nav-link",
    ".nav-submenu a",
    ".form-action-button",
    ".primary-button",
    ".secondary-button",
    ".table-action",
    ".logout-button",
  ].join(",");
  const MESSAGE_SELECTOR = [
    ".form-message",
    "[id$='Message']",
  ].join(",");

  const shownMessages = new WeakMap();
  const dialogTriggers = new WeakMap();
  let pendingDialogTrigger = null;
  let activeDialog = null;
  let rippleEventsReady = false;

  document.addEventListener("DOMContentLoaded", initProfessionalUX);
  window.addEventListener("titech:motion-change", handleMotionPreferenceChange);

  window.titechToast = showToast;
  window.titechConfirm = confirmAction;
  window.titechRememberDialogTrigger = rememberDialogTrigger;

  function initProfessionalUX() {
    document.body.classList.add("ux-enhanced");
    requestAnimationFrame(() => document.body.classList.remove("page-loading"));
    setupReveals();
    setupRipples();
    setupTooltips();
    setupMessageToasts();
    setupSearchShortcut();
    setupTableAccessibility();
    setupDialogFocusManagement();
  }

  // Animações são desativadas quando o usuário prefere movimento reduzido.
  function setupReveals() {
    const elements = Array.from(document.querySelectorAll(REVEAL_SELECTOR));

    if (isReducedMotionEnabled()) {
      elements.forEach((element) => element.classList.add("is-visible"));
      return;
    }

    elements.forEach((element, index) => {
      element.classList.add("ux-reveal");
      element.style.transitionDelay = `${Math.min(index * 28, 168)}ms`;
    });

    if (!("IntersectionObserver" in window)) {
      elements.forEach((element) => element.classList.add("is-visible"));
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;

          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.12 },
    );

    elements.forEach((element) => observer.observe(element));
  }

  function setupRipples() {
    if (isReducedMotionEnabled()) {
      removeRippleArtifacts();
      return;
    }

    document.querySelectorAll(RIPPLE_SELECTOR).forEach((element) => {
      if (element.classList.contains("icon-button")) return;
      element.classList.add("ux-ripple");
    });

    if (rippleEventsReady) return;

    rippleEventsReady = true;

    document.addEventListener("click", (event) => {
      if (isReducedMotionEnabled()) return;

      const target = event.target.closest(".ux-ripple");

      if (!target || target.disabled) return;

      const rect = target.getBoundingClientRect();
      const ripple = document.createElement("span");

      ripple.className = "ux-ripple-dot";
      ripple.style.left = `${event.clientX - rect.left}px`;
      ripple.style.top = `${event.clientY - rect.top}px`;

      target.append(ripple);
      ripple.addEventListener("animationend", () => ripple.remove(), { once: true });
    });
  }

  function handleMotionPreferenceChange() {
    if (!isReducedMotionEnabled()) {
      setupReveals();
      setupRipples();
      return;
    }

    document.querySelectorAll(REVEAL_SELECTOR).forEach((element) => {
      element.classList.add("is-visible");
      element.style.transitionDelay = "";
    });
    removeRippleArtifacts();
  }

  function removeRippleArtifacts() {
    document.querySelectorAll(".ux-ripple-dot").forEach((ripple) => ripple.remove());
  }

  function isReducedMotionEnabled() {
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

  function setupTooltips() {
    document.querySelectorAll(".icon-button[aria-label], .table-action[aria-label]").forEach((element) => {
      if (element.dataset.uxTooltip) return;

      const label = element.getAttribute("aria-label");

      if (label) {
        element.dataset.uxTooltip = label;
      }
    });
  }

  // Observa mensagens existentes para gerar avisos sem alterar cada módulo de página.
  function setupMessageToasts() {
    const elements = Array.from(document.querySelectorAll(MESSAGE_SELECTOR));

    elements.forEach((element) => {
      maybeToastFromElement(element);

      const observer = new MutationObserver(() => {
        maybeToastFromElement(element);
      });

      observer.observe(element, {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true,
      });
    });
  }

  function maybeToastFromElement(element) {
    const message = normalizeMessage(element.textContent);

    if (!message || element.hidden || shouldIgnoreMessageElement(element)) return;

    const previous = shownMessages.get(element);

    if (previous === message) return;

    shownMessages.set(element, message);
    showToast(message, getMessageType(element));
  }

  function shouldIgnoreMessageElement(element) {
    if (element.classList.contains("form-message") && !element.classList.contains("show")) {
      return true;
    }

    if (element.id && /message$/i.test(element.id) && !element.classList.contains("show")) {
      return true;
    }

    if (element.id && /resultcount$/i.test(element.id)) {
      return true;
    }

    return false;
  }

  function getMessageType(element) {
    if (element.classList.contains("error") || element.classList.contains("error-status")) {
      return "error";
    }

    if (element.classList.contains("success") || element.classList.contains("success-status")) {
      return "success";
    }

    return "info";
  }

  function showToast(message, type = "info") {
    const text = normalizeMessage(message);

    if (!text) return;

    const host = getToastHost();
    const toast = document.createElement("div");
    const icon = document.createElement("span");
    const label = document.createElement("span");
    const closeButton = document.createElement("button");

    toast.className = `ux-toast ux-toast-${normalizeToastIcon(type)}`;
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    icon.className = "ux-toast-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = getToastSymbol(type);
    label.textContent = text;
    closeButton.className = "ux-toast-close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Fechar aviso");
    closeButton.textContent = "x";

    closeButton.addEventListener("click", () => dismissToast(toast));
    toast.append(icon, label, closeButton);
    host.append(toast);

    requestAnimationFrame(() => toast.classList.add("show"));
    setTimeout(() => dismissToast(toast), type === "error" ? 5200 : 3200);
  }

  // O diálogo resolve uma Promise e devolve o foco ao elemento que iniciou a ação.
  async function confirmAction(options = {}) {
    const title = options.title || "Confirmar acao?";
    const text = options.text || "";
    const confirmButtonText = options.confirmButtonText || "Confirmar";
    const cancelButtonText = options.cancelButtonText || "Cancelar";

    return new Promise((resolve) => {
      const previousFocus = document.activeElement;
      const backdrop = document.createElement("div");
      const dialog = document.createElement("section");
      const heading = document.createElement("h2");
      const description = document.createElement("p");
      const actions = document.createElement("div");
      const cancelButton = document.createElement("button");
      const confirmButton = document.createElement("button");
      const titleId = `ux-confirm-title-${Date.now()}`;
      const descriptionId = `ux-confirm-description-${Date.now()}`;

      backdrop.className = "ux-confirm-backdrop";
      dialog.className = "ux-confirm-card";
      dialog.setAttribute("role", "dialog");
      dialog.setAttribute("aria-modal", "true");
      dialog.setAttribute("aria-labelledby", titleId);
      dialog.setAttribute("aria-describedby", descriptionId);
      heading.id = titleId;
      heading.textContent = title;
      description.id = descriptionId;
      description.textContent = text;
      actions.className = "ux-confirm-actions";
      cancelButton.className = "ux-confirm-button ux-confirm-cancel";
      confirmButton.className = "ux-confirm-button ux-confirm-primary";
      cancelButton.type = "button";
      confirmButton.type = "button";
      cancelButton.textContent = cancelButtonText;
      confirmButton.textContent = confirmButtonText;

      actions.append(cancelButton, confirmButton);
      dialog.append(heading, description, actions);
      backdrop.append(dialog);
      document.body.append(backdrop);

      const finish = (value) => {
        deactivateDialog(dialog, previousFocus);
        backdrop.classList.remove("show");
        setTimeout(() => backdrop.remove(), 160);
        resolve(value);
      };

      cancelButton.addEventListener("click", () => finish(false));
      confirmButton.addEventListener("click", () => finish(true));
      backdrop.addEventListener("click", (event) => {
        if (event.target === backdrop) {
          finish(false);
        }
      });
      backdrop.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
          event.preventDefault();
          finish(false);
        }
      });

      requestAnimationFrame(() => {
        backdrop.classList.add("show");
        activateDialog(dialog, previousFocus);
        cancelButton.focus({ preventScroll: true });
      });
    });
  }

  function setupSearchShortcut() {
    window.addEventListener("keydown", (event) => {
      if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey) return;
      if (isTypingTarget(event.target)) return;

      const search = document.querySelector("input[type='search']");

      if (!search) return;

      event.preventDefault();
      search.focus();
      search.select();
      showToast("Busca pronta para digitar.", "info");
    });
  }

  // Completa informações semânticas ausentes sem duplicar marcação em todas as páginas.
  function setupTableAccessibility() {
    document.querySelectorAll(".records-table").forEach((table) => {
      table.querySelectorAll("th").forEach((header) => {
        if (!header.scope) {
          header.scope = "col";
        }
      });

      if (table.querySelector("caption")) return;

      const title = table.closest(".content-card")?.querySelector("h3")?.textContent || "Tabela de registros";
      const caption = document.createElement("caption");

      caption.className = "ux-sr-only";
      caption.textContent = normalizeMessage(title);
      table.prepend(caption);
    });
  }

  // Centraliza o foco dos modais legados e dos diálogos criados dinamicamente.
  function setupDialogFocusManagement() {
    document.querySelectorAll("[role='dialog'][aria-modal='true']").forEach((dialog) => {
      const container = dialog.closest("[hidden], .edit-modal-backdrop") || dialog;

      if (!container || container.dataset.uxDialogManaged) return;

      container.dataset.uxDialogManaged = "true";

      const syncDialogState = () => {
        if (!container.hidden) {
          activateDialog(dialog, pendingDialogTrigger || document.activeElement);
          pendingDialogTrigger = null;
          return;
        }

        if (activeDialog === dialog) {
          deactivateDialog(dialog, dialogTriggers.get(dialog));
        }
      };

      new MutationObserver(syncDialogState).observe(container, {
        attributes: true,
        attributeFilter: ["hidden"],
      });

      syncDialogState();
    });

    document.addEventListener("keydown", trapDialogFocus);
  }

  function rememberDialogTrigger() {
    pendingDialogTrigger = document.activeElement;
  }

  function activateDialog(dialog, trigger) {
    activeDialog = dialog;

    if (trigger && !dialog.contains(trigger)) {
      dialogTriggers.set(dialog, trigger);
    }

    requestAnimationFrame(() => {
      if (dialog.contains(document.activeElement)) return;

      getFocusableElements(dialog)[0]?.focus({ preventScroll: true });
    });
  }

  function deactivateDialog(dialog, trigger) {
    if (activeDialog === dialog) {
      activeDialog = null;
    }

    if (trigger?.isConnected && !dialog.contains(trigger)) {
      trigger.focus({ preventScroll: true });
    }
  }

  function trapDialogFocus(event) {
    if (!activeDialog || event.key !== "Tab") return;

    const focusable = getFocusableElements(activeDialog);

    if (focusable.length === 0) {
      event.preventDefault();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function getFocusableElements(container) {
    return Array.from(
      container.querySelectorAll(
        "a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex='-1'])",
      ),
    ).filter((element) => element.offsetParent !== null || element === document.activeElement);
  }

  function getToastHost() {
    let host = document.getElementById("uxToastRegion");

    if (!host) {
      host = document.createElement("div");
      host.id = "uxToastRegion";
      host.className = "ux-toast-region";
      host.setAttribute("aria-live", "polite");
      host.setAttribute("aria-relevant", "additions");
      document.body.append(host);
    }

    return host;
  }

  function dismissToast(toast) {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 180);
  }

  function isTypingTarget(target) {
    return Boolean(target?.closest?.("input, textarea, select, [contenteditable='true']"));
  }

  function normalizeToastIcon(type) {
    if (type === "success" || type === "error" || type === "warning") {
      return type;
    }

    return "info";
  }

  function getToastSymbol(type) {
    if (type === "success") return "OK";
    if (type === "error") return "!";
    if (type === "warning") return "!";

    return "i";
  }

  function normalizeMessage(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  }
})();
