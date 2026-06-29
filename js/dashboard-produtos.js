(function () {
const DASHBOARD_PRODUCTS_ENDPOINT = "Backend/dashboard-produtos.php";
const THEME_STORAGE_KEY = "titech-theme";
const ACCENT_STORAGE_KEY = "titech-accent";
const THEME_TRANSITION_MS = 660;

const ACCENT_THEMES = {
  teal: {
    cyan: "#4aa3c7",
    teal: "#4fc7b1",
    mint: "#66d5c2",
    accent: "#66d5c2",
  },
  green: {
    cyan: "#22c55e",
    teal: "#16a34a",
    mint: "#86efac",
    accent: "#22c55e",
  },
  blue: {
    cyan: "#38bdf8",
    teal: "#2563eb",
    mint: "#7dd3fc",
    accent: "#38bdf8",
  },
  violet: {
    cyan: "#a78bfa",
    teal: "#7c3aed",
    mint: "#c4b5fd",
    accent: "#a78bfa",
  },
};

const DEFAULT_DASHBOARD_DATA = {
  ok: false,
  resumo: {
    total_ativos: 0,
    total_tipos: 0,
    total_filtrado: 0,
    maior_categoria: null,
  },
  categoria_selecionada: {
    id: "todos",
    nome: "Todos os tipos",
    total: 0,
    percentual: 0,
  },
  categorias: [],
  status: [],
  status_por_categoria: {},
  marcas: [],
  marcas_por_categoria: {},
  locais: [],
  locais_por_categoria: {},
  evolucao: [],
};

const METRIC_CONFIG = {
  categorias: {
    title: "Quantidade por tipo",
    description:
      "Distribuição dos ativos cadastrados por categoria de produto.",
    totalLabel: "Ativos no inventário",
    dataKey: "categorias",
  },
  status: {
    title: "Quantidade por status",
    description:
      "Mostra como os ativos estão distribuídos por situação operacional.",
    totalLabel: "Ativos analisados",
    dataKey: "status",
  },
  marcas: {
    title: "Quantidade por marca",
    description:
      "Mostra quantos ativos existem por marca no filtro atual.",
    totalLabel: "Ativos analisados",
    dataKey: "marcas",
  },
  locais: {
    title: "Quantidade por localização",
    description:
      "Distribuição dos ativos por local, setor ou ponto de armazenamento.",
    totalLabel: "Ativos analisados",
    dataKey: "locais",
  },
  evolucao: {
    title: "Evolução de cadastros",
    description:
      "Quantidade de ativos cadastrados por dia no período selecionado.",
    totalLabel: "Novos cadastros",
    dataKey: "evolucao",
  },
};

let dashboardData = DEFAULT_DASHBOARD_DATA;
let dashboardBaseData = DEFAULT_DASHBOARD_DATA;
let productsChart = null;
let themeTimer = null;
let dashboardRequestController = null;
let dashboardRequestId = 0;
const dashboardCache = new Map();

const state = {
  categoriaId: "todos",
  metrica: "categorias",
  tipoGrafico: "bar",
  periodo: "30",
};

document.addEventListener("DOMContentLoaded", initDashboardProductsPage);

function initDashboardProductsPage() {
  window.onThemeChanged = () => renderCurrentChart();
  (window.startPageAnimation || startPageAnimation)();
  (window.loadSavedTheme || loadSavedTheme)();
  (window.setupThemeToggle || setupThemeToggle)();
  (window.setupSidebar || setupSidebar)();
  (window.setupNavGroups || setupNavGroups)();
  setupDashboardControls();
  loadDashboardProducts();
}

function startPageAnimation() {
  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

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

function loadSavedTheme() {
  applyAccent(getSavedItem(ACCENT_STORAGE_KEY) || "teal");
  applyTheme(getSavedItem(THEME_STORAGE_KEY) || "dark");
  window.applyDensity?.(getSavedItem("titech-density") || "comfortable");
  window.applyMotionPreference?.(getSavedItem("titech-motion") || "normal");
  window.applyCursorPreference?.(getSavedItem("titech-cursor") || "normal");
}

function applyAccent(accent) {
  const selectedAccent = Object.hasOwn(ACCENT_THEMES, accent) ? accent : "teal";
  const palette = ACCENT_THEMES[selectedAccent];

  document.body.dataset.accent = selectedAccent;
  document.body.style.setProperty("--cyan", palette.cyan);
  document.body.style.setProperty("--teal", palette.teal);
  document.body.style.setProperty("--mint", palette.mint);
  document.body.style.setProperty("--accent", palette.accent);
}

function setupThemeToggle() {
  const themeToggle = document.getElementById("themeToggle");

  if (!themeToggle) {
    return;
  }

  themeToggle.addEventListener("click", () => {
    const isDark = document.body.classList.contains("theme-dark");
    const nextTheme = isDark ? "light" : "dark";

    clearTimeout(themeTimer);
    document.body.classList.add("theme-switching");

    applyTheme(nextTheme);
    setSavedItem(THEME_STORAGE_KEY, nextTheme);
    renderCurrentChart();

    themeTimer = window.setTimeout(() => {
      document.body.classList.remove("theme-switching");
    }, THEME_TRANSITION_MS);
  });
}

function applyTheme(theme) {
  const isDark = theme !== "light";
  const themeToggle = document.getElementById("themeToggle");

  document.body.classList.toggle("theme-dark", isDark);
  document.body.classList.toggle("theme-light", !isDark);

  document.querySelectorAll(".brand-logo").forEach((logo) => {
    logo.src = isDark ? "assets/logo-branca.png" : "assets/Logo.png";
  });

  if (!themeToggle) {
    return;
  }

  const icon = themeToggle.querySelector("i");
  const label = themeToggle.querySelector("span");

  if (icon) {
    icon.className = isDark ? "bi bi-moon-stars-fill" : "bi bi-sun-fill";
  }

  if (label) {
    label.textContent = isDark ? "Modo escuro" : "Modo claro";
  }
}

function setupSidebar() {
  const openButton = document.getElementById("openSidebar");
  const closeButton = document.getElementById("closeSidebar");
  const backdrop = document.getElementById("sidebarBackdrop");

  openButton?.addEventListener("click", () =>
    document.body.classList.add("sidebar-open"),
  );
  closeButton?.addEventListener("click", () =>
    document.body.classList.remove("sidebar-open"),
  );
  backdrop?.addEventListener("click", () =>
    document.body.classList.remove("sidebar-open"),
  );

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      document.body.classList.remove("sidebar-open");
    }
  });

  document.querySelectorAll(".sidebar-nav a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 920) {
        document.body.classList.remove("sidebar-open");
      }
    });
  });
}

function setupNavGroups() {
  const groups = Array.from(document.querySelectorAll("[data-nav-group]"));

  groups.forEach((group) => {
    const button = group.querySelector(".nav-toggle");
    const submenu = group.querySelector(".nav-submenu");

    if (!button || !submenu) {
      return;
    }

    button.addEventListener("click", () => {
      const shouldOpen = !group.classList.contains("open");

      groups.forEach((otherGroup) => {
        if (otherGroup === group) {
          return;
        }

        otherGroup.classList.remove("open");
        otherGroup
          .querySelector(".nav-toggle")
          ?.setAttribute("aria-expanded", "false");
      });

      group.classList.toggle("open", shouldOpen);
      button.setAttribute("aria-expanded", String(shouldOpen));
    });
  });
}

function setupDashboardControls() {
  const categoryFilter = document.getElementById("categoryFilter");
  const metricFilter = document.getElementById("metricFilter");
  const chartTypeFilter = document.getElementById("chartTypeFilter");
  const periodFilter = document.getElementById("periodFilter");
  const refreshButton = document.getElementById("refreshDashboard");

  categoryFilter?.addEventListener("change", () => {
    state.categoriaId = categoryFilter.value || "todos";
    setDashboardMetric(state.categoriaId === "todos" ? "categorias" : "marcas");

    if (!applyLocalCategorySelection()) {
      loadDashboardProducts();
    }
  });

  metricFilter?.addEventListener("change", () => {
    setDashboardMetric(metricFilter.value || "categorias");

    if (state.metrica === "evolucao") {
      loadDashboardProducts();
      return;
    }

    renderCurrentChart();
  });

  chartTypeFilter?.addEventListener("change", () => {
    state.tipoGrafico = chartTypeFilter.value || "bar";
    renderCurrentChart();
  });

  periodFilter?.addEventListener("change", () => {
    state.periodo = periodFilter.value || "30";

    if (state.metrica === "evolucao") {
      loadDashboardProducts();
      return;
    }

    setStatus("Periodo atualizado.", "A evolucao usara esse filtro.");
  });

  refreshButton?.addEventListener("click", () => {
    loadDashboardProducts(true, { forceRefresh: true });
  });
}

function setDashboardMetric(metric) {
  const nextMetric = Object.hasOwn(METRIC_CONFIG, metric)
    ? metric
    : "categorias";
  const metricFilter = document.getElementById("metricFilter");

  state.metrica = nextMetric;

  if (metricFilter && metricFilter.value !== nextMetric) {
    metricFilter.value = nextMetric;
  }
}

function applyLocalCategorySelection() {
  if (!dashboardBaseData.ok) {
    return false;
  }

  if (state.categoriaId === "todos") {
    dashboardData = dashboardBaseData;
    renderSummaryCards();
    renderCurrentChart();
    setStatus("Dados exibidos.", "Filtro removido na tela.");
    return true;
  }

  const selectedCategory = dashboardBaseData.categorias.find(
    (category) => category.id === state.categoriaId,
  );

  if (!selectedCategory) {
    return false;
  }

  const categoryBrands =
    dashboardBaseData.marcas_por_categoria?.[state.categoriaId] || [];
  const categoryStatuses =
    dashboardBaseData.status_por_categoria?.[state.categoriaId] || [];
  const categoryLocations =
    dashboardBaseData.locais_por_categoria?.[state.categoriaId] || [];

  dashboardData = {
    ...dashboardBaseData,
    resumo: {
      ...dashboardBaseData.resumo,
      total_filtrado: selectedCategory.total,
    },
    categoria_selecionada: {
      id: selectedCategory.id,
      nome: selectedCategory.nome,
      total: selectedCategory.total,
      percentual: selectedCategory.percentual,
    },
    status: categoryStatuses,
    marcas: categoryBrands,
    locais: categoryLocations,
  };

  renderSummaryCards();
  renderCurrentChart();
  setStatus("Dados exibidos.", "Filtro aplicado na tela.");

  return true;
}

async function loadDashboardProducts(showLoading = true, options = {}) {
  const cacheKey = `${state.categoriaId}|${state.periodo}`;
  const forceRefresh = Boolean(options.forceRefresh);

  if (forceRefresh) {
    dashboardCache.delete(cacheKey);
  }

  if (!forceRefresh && dashboardCache.has(cacheKey)) {
    applyDashboardPayload(dashboardCache.get(cacheKey));
    setStatus("Dados exibidos.", "Usando dados ja carregados.");
    return;
  }

  dashboardRequestController?.abort();
  dashboardRequestController = new AbortController();

  const requestId = ++dashboardRequestId;

  if (showLoading) {
    setStatus("Carregando dados...", "Buscando informações no banco.");
  }

  const params = new URLSearchParams({
    categoria_id: state.categoriaId,
    periodo: state.periodo,
  });

  try {
    const response = await fetch(
      `${DASHBOARD_PRODUCTS_ENDPOINT}?${params.toString()}`,
      {
        headers: {
          Accept: "application/json",
        },
        signal: dashboardRequestController.signal,
      },
    );

    if (requestId !== dashboardRequestId) {
      return;
    }

    if (response.status === 401) {
      window.location.href = "Pagina-login.html?sessao=expirada";
      return;
    }

    if (!response.ok) {
      throw new Error("Falha ao carregar dashboard de produtos.");
    }

    const payload = await response.json();

    if (requestId !== dashboardRequestId) {
      return;
    }

    dashboardCache.set(cacheKey, payload);
    applyDashboardPayload(payload);
    setStatus(
      "Dados sincronizados.",
      formatLastUpdate(dashboardData.gerado_em),
    );
  } catch (error) {
    if (error.name === "AbortError") {
      return;
    }

    if (requestId !== dashboardRequestId) {
      return;
    }

    console.error(error);
    dashboardData = DEFAULT_DASHBOARD_DATA;
    renderSummaryCards();
    renderCurrentChart();
    setStatus(
      "Não foi possível carregar os dados.",
      "Confira a conexão com o banco e tente novamente.",
    );
  } finally {
    if (requestId === dashboardRequestId) {
      dashboardRequestController = null;
    }
  }
}

function applyDashboardPayload(payload) {
  dashboardData = normalizeDashboardPayload(payload);

  if (dashboardData.categoria_selecionada.id === "todos") {
    dashboardBaseData = dashboardData;
  }

  populateCategoryFilter(dashboardData.categorias);
  renderSummaryCards();
  renderCurrentChart();
}

function normalizeDashboardPayload(payload) {
  const data =
    payload && typeof payload === "object" ? payload : DEFAULT_DASHBOARD_DATA;
  const resumo =
    data.resumo && typeof data.resumo === "object"
      ? data.resumo
      : DEFAULT_DASHBOARD_DATA.resumo;
  const categoriaSelecionada =
    data.categoria_selecionada && typeof data.categoria_selecionada === "object"
      ? data.categoria_selecionada
      : DEFAULT_DASHBOARD_DATA.categoria_selecionada;

  return {
    ok: Boolean(data.ok),
    gerado_em: data.gerado_em || null,
    periodo: normalizeNumber(data.periodo) || Number(state.periodo),
    resumo: {
      total_ativos: normalizeNumber(resumo.total_ativos),
      total_tipos: normalizeNumber(resumo.total_tipos),
      total_filtrado: normalizeNumber(resumo.total_filtrado),
      maior_categoria: resumo.maior_categoria || null,
    },
    categoria_selecionada: {
      id: String(categoriaSelecionada.id || "todos"),
      nome: String(categoriaSelecionada.nome || "Todos os tipos"),
      total: normalizeNumber(categoriaSelecionada.total),
      percentual: normalizePercent(categoriaSelecionada.percentual),
    },
    categorias: normalizeDataRows(data.categorias, true),
    status: normalizeDataRows(data.status),
    status_por_categoria: normalizeRowsByCategory(data.status_por_categoria),
    marcas: normalizeDataRows(data.marcas),
    marcas_por_categoria: normalizeRowsByCategory(data.marcas_por_categoria),
    locais: normalizeDataRows(data.locais),
    locais_por_categoria: normalizeRowsByCategory(data.locais_por_categoria),
    evolucao: normalizeDataRows(data.evolucao),
  };
}

function normalizeRowsByCategory(groups) {
  if (!groups || typeof groups !== "object" || Array.isArray(groups)) {
    return {};
  }

  return Object.entries(groups).reduce((normalized, [categoryId, rows]) => {
    normalized[String(categoryId)] = normalizeDataRows(rows);
    return normalized;
  }, {});
}

function normalizeDataRows(rows, keepId = false) {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .map((row) => {
      const item = {
        nome: String(row?.nome || "Sem nome").trim(),
        total: normalizeNumber(row?.total),
        percentual: normalizePercent(row?.percentual),
      };

      if (keepId) {
        item.id = String(row?.id || "");
      }

      return item;
    })
    .filter((row) => row.nome !== "" && row.total !== null);
}

function normalizeNumber(value) {
  const number = Number(value);

  return Number.isFinite(number) && number >= 0 ? number : 0;
}

function normalizePercent(value) {
  const number = Number(value);

  return Number.isFinite(number) && number >= 0 ? number : 0;
}

function populateCategoryFilter(categories) {
  const categoryFilter = document.getElementById("categoryFilter");

  if (!categoryFilter) {
    return;
  }

  const previousValue = categoryFilter.value || state.categoriaId;

  categoryFilter.innerHTML = "";
  categoryFilter.appendChild(createOption("todos", "Todos os tipos"));

  categories.forEach((category) => {
    categoryFilter.appendChild(
      createOption(
        category.id,
        `${formatCategoryLabel(category.nome)} (${formatNumber(category.total)})`,
      ),
    );
  });

  categoryFilter.value = categories.some(
    (category) => category.id === previousValue,
  )
    ? previousValue
    : "todos";
  state.categoriaId = categoryFilter.value;
}

function createOption(value, label) {
  const option = document.createElement("option");
  option.value = value;
  option.textContent = label;

  return option;
}

function renderSummaryCards() {
  const resumo = dashboardData.resumo || DEFAULT_DASHBOARD_DATA.resumo;
  const selected =
    dashboardData.categoria_selecionada ||
    DEFAULT_DASHBOARD_DATA.categoria_selecionada;
  const largest = resumo.maior_categoria;

  setText("totalAssetsMetric", formatNumber(resumo.total_ativos));
  setText("totalTypesMetric", formatNumber(resumo.total_tipos));
  setText(
    "selectedTypeMetric",
    selected.id === "todos" ? "Todos" : formatCategoryLabel(selected.nome),
  );

  const selectedDetail =
    selected.id === "todos"
      ? `Analisando ${formatNumber(resumo.total_filtrado)} ativos no total.`
      : `${formatNumber(selected.total)} ativos, ${formatPercent(selected.percentual)} do inventário.`;

  setText("selectedTypeDetail", selectedDetail);

  if (largest) {
    setText("largestTypeMetric", formatCategoryLabel(largest.nome || "--"));
    setText(
      "largestTypeDetail",
      `${formatNumber(largest.total)} ativos, ${formatPercent(largest.percentual)} do total.`,
    );
  } else {
    setText("largestTypeMetric", "--");
    setText("largestTypeDetail", "Nenhuma categoria encontrada.");
  }
}

function renderCurrentChart() {
  const config = METRIC_CONFIG[state.metrica] || METRIC_CONFIG.categorias;
  const rows = getCurrentRows(config);
  const total = calculateRowsTotal(rows);

  setText("mainChartTitle", config.title);
  setText("mainChartDescription", buildChartDescription(config));
  setText("chartTotalLabel", config.totalLabel);
  setText("chartTotalMetric", formatNumber(total));

  renderChart(rows, config);
  renderRanking(rows, total);
  renderTable(rows, total);
}

function getCurrentRows(config) {
  const rows = Array.isArray(dashboardData[config.dataKey])
    ? dashboardData[config.dataKey]
    : [];

  if (state.metrica === "evolucao") {
    return rows;
  }

  return rows.filter((row) => row.total > 0);
}

function buildChartDescription(config) {
  const selected =
    dashboardData.categoria_selecionada ||
    DEFAULT_DASHBOARD_DATA.categoria_selecionada;

  if (selected.id === "todos") {
    return config.description;
  }

  return `${config.description} Filtro ativo: ${selected.nome}.`;
}

function calculateRowsTotal(rows) {
  return rows.reduce((sum, row) => sum + normalizeNumber(row.total), 0);
}

function renderChart(rows, config) {
  const canvas = document.getElementById("productsChart");

  if (!canvas || !window.Chart) {
    return;
  }

  if (productsChart) {
    productsChart.destroy();
  }

  const chartRows = rows.length ? rows : [{ nome: "Sem dados", total: 0 }];
  const chartType = getSafeChartType(state.tipoGrafico, state.metrica);
  const styles = getComputedStyle(document.body);
  const textColor = styles.getPropertyValue("--text").trim() || "#f6fbff";
  const mutedColor = styles.getPropertyValue("--muted").trim() || "#9cb8c9";
  const gridColor =
    styles.getPropertyValue("--line").trim() || "rgba(255,255,255,.13)";
  const palette = buildChartPalette(chartRows.length);
  const isLine = chartType === "line";
  const isCircular = ["pie", "doughnut", "polarArea"].includes(chartType);

  productsChart = new Chart(canvas, {
    type: chartType,
    data: {
      labels: chartRows.map((row) => formatRowLabel(row.nome)),
      datasets: [
        {
          label: config.title,
          data: chartRows.map((row) => row.total),
          backgroundColor: isLine ? "rgba(79, 199, 177, 0.18)" : palette,
          borderColor: isLine
            ? styles.getPropertyValue("--mint").trim() || "#66d5c2"
            : palette,
          borderWidth: isLine ? 3 : 1,
          fill: isLine,
          tension: 0.36,
          pointRadius: isLine ? 4 : 0,
          pointHoverRadius: isLine ? 7 : 0,
          borderRadius: chartType === "bar" ? 12 : 0,
          hoverOffset: isCircular ? 8 : 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 240,
        easing: "easeOutQuart",
      },
      indexAxis: chartType === "bar" && chartRows.length >= 7 ? "y" : "x",
      plugins: {
        legend: {
          display: isCircular,
          position: "bottom",
          labels: {
            color: textColor,
            boxWidth: 12,
            boxHeight: 12,
            padding: 18,
            font: {
              family: "Inter, system-ui, sans-serif",
              weight: "700",
            },
          },
        },
        tooltip: {
          backgroundColor: "rgba(3, 16, 29, 0.92)",
          borderColor: "rgba(79, 199, 177, 0.28)",
          borderWidth: 1,
          titleColor: "#f8feff",
          bodyColor: "#d9fbf6",
          displayColors: false,
          padding: 14,
          cornerRadius: 14,
          callbacks: {
            label(context) {
              const chart = context.chart;
              const indexAxis = chart?.options?.indexAxis;

              let value = 0;

              if (typeof context.raw === "number") {
                value = context.raw;
              } else if (indexAxis === "y") {
                value = Number(context.parsed?.x ?? 0);
              } else {
                value = Number(context.parsed?.y ?? context.parsed ?? 0);
              }

              const dataset = context.dataset;
              const data = Array.isArray(dataset.data) ? dataset.data : [];

              const total = data.reduce((sum, item) => {
                if (typeof item === "number") {
                  return sum + item;
                }

                return (
                  sum +
                  Number(item?.value ?? item?.total ?? item?.quantidade ?? 0)
                );
              }, 0);

              const percent =
                total > 0 ? ((value / total) * 100).toFixed(1) : "0.0";

              return `${formatNumber(value)} ativos - ${percent}%`;
            },
          },
        },
      },
      scales: isCircular
        ? {}
        : {
            x: {
              ticks: {
                color: mutedColor,
                font: {
                  weight: "700",
                },
              },
              grid: {
                display: false,
              },
            },
            y: {
              beginAtZero: true,
              ticks: {
                color: mutedColor,
                precision: 0,
                font: {
                  weight: "700",
                },
              },
              grid: {
                color: gridColor,
              },
            },
          },
    },
  });
}

function getSafeChartType(chartType, metric) {
  if (
    metric === "evolucao" &&
    ["pie", "doughnut", "polarArea"].includes(chartType)
  ) {
    return "line";
  }

  if (["bar", "pie", "doughnut", "line", "polarArea"].includes(chartType)) {
    return chartType;
  }

  return "bar";
}

function buildChartPalette(size) {
  const styles = getComputedStyle(document.body);
  const baseColors = [
    styles.getPropertyValue("--mint").trim() || "#66d5c2",
    styles.getPropertyValue("--cyan").trim() || "#4aa3c7",
    styles.getPropertyValue("--teal").trim() || "#4fc7b1",
    "#38bdf8",
    "#8b5cf6",
    "#f59e0b",
    "#22c55e",
    "#ef4444",
    "#14b8a6",
    "#6366f1",
    "#ec4899",
    "#84cc16",
  ];

  return Array.from(
    { length: size },
    (_item, index) => baseColors[index % baseColors.length],
  );
}

function renderRanking(rows, total) {
  const container = document.getElementById("dashboardRanking");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  if (!rows.length || total === 0) {
    container.innerHTML =
      '<div class="ranking-empty">Nenhum dado encontrado para o filtro atual.</div>';
    return;
  }

  rows.slice(0, 8).forEach((row, index) => {
    const percent = total ? (row.total / total) * 100 : 0;
    const item = document.createElement("div");
    item.className = "ranking-item";

    item.innerHTML = `
            <div class="ranking-item-head">
                <span>${index + 1}. ${escapeHtml(formatRowLabel(row.nome))}</span>
                <strong>${formatNumber(row.total)}</strong>
            </div>
            <div class="ranking-progress" aria-hidden="true">
                <span style="width: ${Math.min(percent, 100)}%"></span>
            </div>
            <small>${formatPercent(percent)} de participação</small>
        `;

    container.appendChild(item);
  });
}

function renderTable(rows, total) {
  const tableBody = document.getElementById("dashboardTableBody");

  if (!tableBody) {
    return;
  }

  tableBody.innerHTML = "";

  if (!rows.length || total === 0) {
    tableBody.innerHTML =
      '<tr><td colspan="3">Nenhum dado encontrado para o filtro atual.</td></tr>';
    return;
  }

  rows.forEach((row) => {
    const percent = total ? (row.total / total) * 100 : 0;
    const tr = document.createElement("tr");

    tr.innerHTML = `
            <td>${escapeHtml(formatRowLabel(row.nome))}</td>
            <td>${formatNumber(row.total)}</td>
            <td>${formatPercent(percent)}</td>
        `;

    tableBody.appendChild(tr);
  });
}

function setStatus(title, detail) {
  setText("dashboardStatusText", `${title} ${detail}`.trim());
}

function setText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

function formatRowLabel(value) {
  if (state.metrica === "categorias") {
    return formatCategoryLabel(value);
  }

  return String(value || "--");
}

function formatCategoryLabel(value) {
  const text = String(value || "").trim();

  if (text === "" || text === "--") {
    return "--";
  }

  const lowerText = text.toLocaleLowerCase("pt-BR");

  return lowerText.charAt(0).toLocaleUpperCase("pt-BR") + lowerText.slice(1);
}

function formatNumber(value) {
  return new Intl.NumberFormat("pt-BR").format(normalizeNumber(value));
}

function formatPercent(value) {
  return `${Number(value || 0).toLocaleString("pt-BR", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`;
}

function formatLastUpdate(dateValue) {
  if (!dateValue) {
    return "Dados carregados do banco.";
  }

  const date = new Date(dateValue);

  if (Number.isNaN(date.getTime())) {
    return "Dados carregados do banco.";
  }

  return `Atualizado em ${date.toLocaleDateString("pt-BR")} às ${date.toLocaleTimeString(
    "pt-BR",
    {
      hour: "2-digit",
      minute: "2-digit",
    },
  )}.`;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
})();
