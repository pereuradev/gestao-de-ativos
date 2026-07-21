(function () {
const SIDEBAR_WIDTH_STORAGE_KEY = "titech-sidebar-width";
const SIDEBAR_DEFAULT_WIDTH = 292;
const SIDEBAR_MIN_WIDTH = 236;
const SIDEBAR_MAX_WIDTH = 392;
const SIDEBAR_DESKTOP_QUERY = "(min-width: 921px)";

function setupSidebar() {
  const openButton = document.getElementById("openSidebar");
  const closeButton = document.getElementById("closeSidebar");
  const backdrop = document.getElementById("sidebarBackdrop");

  openButton?.addEventListener("click", openSidebar);
  closeButton?.addEventListener("click", closeSidebar);
  backdrop?.addEventListener("click", closeSidebar);

  window.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;

    if (typeof window.closeEditModal === "function") {
      window.closeEditModal();
    }

    closeSidebar();
  });

  document.querySelectorAll(".sidebar-nav a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 920) {
        closeSidebar();
      }
    });
  });

  setupSidebarResize();
}

function openSidebar() {
  document.documentElement.classList.add("sidebar-open");
  document.body.classList.add("sidebar-open");
}

function closeSidebar() {
  document.documentElement.classList.remove("sidebar-open");
  document.body.classList.remove("sidebar-open");
}

function setupSidebarResize() {
  const sidebar = document.getElementById("sidebar");

  if (!sidebar || sidebar.dataset.resizeReady === "true") {
    return;
  }

  sidebar.dataset.resizeReady = "true";

  const handle = document.createElement("div");
  handle.className = "sidebar-resize-handle";
  handle.setAttribute("role", "separator");
  handle.setAttribute("aria-orientation", "vertical");
  handle.setAttribute("aria-label", "Redimensionar menu lateral");
  handle.setAttribute("aria-valuemin", String(SIDEBAR_MIN_WIDTH));
  handle.setAttribute("aria-valuemax", String(SIDEBAR_MAX_WIDTH));
  handle.tabIndex = 0;
  sidebar.appendChild(handle);

  updateSidebarResizeHandle(handle, getCurrentSidebarWidth());

  let startX = 0;
  let startWidth = SIDEBAR_DEFAULT_WIDTH;
  let activePointerId = null;

  const finishResize = () => {
    if (activePointerId === null) {
      return;
    }

    activePointerId = null;
    document.body.classList.remove("sidebar-resizing");
    setSavedSidebarItem(SIDEBAR_WIDTH_STORAGE_KEY, String(getCurrentSidebarWidth()));
  };

  handle.addEventListener("pointerdown", (event) => {
    if (!isSidebarResizableViewport()) {
      return;
    }

    activePointerId = event.pointerId;
    startX = event.clientX;
    startWidth = getCurrentSidebarWidth();
    document.body.classList.add("sidebar-resizing");
    handle.setPointerCapture?.(event.pointerId);
    event.preventDefault();
  });

  handle.addEventListener("pointermove", (event) => {
    if (activePointerId !== event.pointerId) {
      return;
    }

    const nextWidth = startWidth + (event.clientX - startX);
    applySidebarWidth(nextWidth);
    updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
  });

  handle.addEventListener("pointerup", finishResize);
  handle.addEventListener("pointercancel", finishResize);

  handle.addEventListener("dblclick", () => {
    applySidebarWidth(SIDEBAR_DEFAULT_WIDTH);
    updateSidebarResizeHandle(handle, SIDEBAR_DEFAULT_WIDTH);
    setSavedSidebarItem(SIDEBAR_WIDTH_STORAGE_KEY, String(SIDEBAR_DEFAULT_WIDTH));
  });

  handle.addEventListener("keydown", (event) => {
    if (!isSidebarResizableViewport()) {
      return;
    }

    const step = event.shiftKey ? 24 : 12;
    const currentWidth = getCurrentSidebarWidth();
    let nextWidth = currentWidth;

    if (event.key === "ArrowLeft") {
      nextWidth = currentWidth - step;
    } else if (event.key === "ArrowRight") {
      nextWidth = currentWidth + step;
    } else if (event.key === "Home") {
      nextWidth = SIDEBAR_MIN_WIDTH;
    } else if (event.key === "End") {
      nextWidth = SIDEBAR_MAX_WIDTH;
    } else {
      return;
    }

    event.preventDefault();
    applySidebarWidth(nextWidth);
    updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
    setSavedSidebarItem(SIDEBAR_WIDTH_STORAGE_KEY, String(getCurrentSidebarWidth()));
  });

  window.addEventListener("resize", () => {
    if (isSidebarResizableViewport()) {
      applySavedSidebarWidth();
      updateSidebarResizeHandle(handle, getCurrentSidebarWidth());
      return;
    }

    document.body.style.removeProperty("--sidebar-width");
  });
}

function applySavedSidebarWidth() {
  const savedWidth = Number(getSavedSidebarItem(SIDEBAR_WIDTH_STORAGE_KEY));

  if (!isSidebarResizableViewport()) {
    document.body.style.removeProperty("--sidebar-width");
    return;
  }

  applySidebarWidth(Number.isFinite(savedWidth) ? savedWidth : SIDEBAR_DEFAULT_WIDTH);
}

function applySidebarWidth(width) {
  const nextWidth = clampSidebarWidth(width);

  document.body.style.setProperty("--sidebar-width", `${nextWidth}px`);
}

function getCurrentSidebarWidth() {
  const sidebar = document.getElementById("sidebar");
  const currentWidth = sidebar?.getBoundingClientRect().width || SIDEBAR_DEFAULT_WIDTH;

  return clampSidebarWidth(currentWidth);
}

function clampSidebarWidth(width) {
  const numericWidth = Number(width);

  if (!Number.isFinite(numericWidth)) {
    return SIDEBAR_DEFAULT_WIDTH;
  }

  return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, Math.round(numericWidth)));
}

function updateSidebarResizeHandle(handle, width) {
  handle.setAttribute("aria-valuenow", String(clampSidebarWidth(width)));
}

function isSidebarResizableViewport() {
  return window.matchMedia?.(SIDEBAR_DESKTOP_QUERY)?.matches ?? window.innerWidth >= 921;
}

function setupNavGroups() {
  const groups = Array.from(document.querySelectorAll("[data-nav-group]"));

  groups.forEach((group) => {
    const button = group.querySelector(".nav-toggle");

    if (!button) return;

    button.addEventListener("click", () => {
      const shouldOpen = !group.classList.contains("open");

      groups.forEach((otherGroup) => {
        if (otherGroup === group) return;

        otherGroup.classList.remove("open");
        otherGroup.querySelector(".nav-toggle")?.setAttribute("aria-expanded", "false");
      });

      group.classList.toggle("open", shouldOpen);
      button.setAttribute("aria-expanded", String(shouldOpen));
    });
  });
}

function getSavedSidebarItem(key) {
  if (typeof window.getSavedItem === "function") {
    return window.getSavedItem(key);
  }

  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

function setSavedSidebarItem(key, value) {
  if (typeof window.setSavedItem === "function") {
    window.setSavedItem(key, value);
    return;
  }

  try {
    localStorage.setItem(key, value);
  } catch {
    return;
  }
}

Object.assign(window, {
  setupSidebar,
  openSidebar,
  closeSidebar,
  applySidebarWidth,
  setupSidebarResize,
  setupNavGroups,
});
})();
