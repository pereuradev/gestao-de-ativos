(function () {
// Mantemos o dashboard dentro de uma funcao anonima para nao misturar variaveis
// desta tela com os scripts globais usados nas outras paginas.
const DASHBOARD_PRODUCTS_ENDPOINT = "../Backend/dashboard-produtos.php";
const THEME_STORAGE_KEY = "titech-theme";
const ACCENT_STORAGE_KEY = "titech-accent";
const THEME_TRANSITION_MS = 660;

// Paletas aceitas pela tela de configuracoes. O dashboard usa as mesmas variaveis
// para graficos, botoes e estados visuais.
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

// Estrutura vazia usada antes do banco responder ou quando ocorre algum erro.
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
  categoria_filtro: "todos",
  marca_filtro: "todos",
  local_filtro: "todos",
  categorias: [],
  marcas_filtro: [],
  locais_filtro: [],
  status: [],
  status_por_categoria: {},
  marcas: [],
  marcas_por_categoria: {},
  locais: [],
  locais_por_categoria: {},
  evolucao: [],
};

// Cada opcao do select "Dados do grafico" aponta para uma lista diferente do JSON.
// Assim a renderizacao reaproveita o mesmo codigo para tipo, status, marca, local e evolucao.
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

// Estado atual dos filtros da tela. Toda renderizacao le esses valores.
const state = {
  categoriaId: "todos",
  marca: "todos",
  localId: "todos",
  metrica: "categorias",
  tipoGrafico: "bar",
  periodo: "30",
};

document.addEventListener("DOMContentLoaded", initDashboardProductsPage);

function initDashboardProductsPage() {
  // O tema e a sidebar seguem o base-interface.js; aqui so reagimos para redesenhar o grafico.
  window.onThemeChanged = () => renderCurrentChart();
  window.addEventListener("titech:motion-change", renderCurrentChart);
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
  // LocalStorage pode falhar em alguns modos privados; por isso o acesso fica protegido.
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

function isReducedMotionEnabled() {
  if (document.body?.dataset.motion === "reduced") {
    return true;
  }

  if (getSavedItem("titech-motion") === "reduced") {
    return true;
  }

  return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
}

function loadSavedTheme() {
  // Fallback local caso o base-interface.js nao esteja disponivel por algum motivo.
  applyAccent(getSavedItem(ACCENT_STORAGE_KEY) || "teal");
  applyTheme(getSavedItem(THEME_STORAGE_KEY) || "dark");
  window.applyFontSizePreference?.(getSavedItem("titech-font-size") || "default");
  window.applyDensity?.(getSavedItem("titech-density") || "comfortable");
  window.applyMotionPreference?.(getSavedItem("titech-motion") || "normal");
  window.applyCursorPreference?.(getSavedItem("titech-cursor") || "enhanced");
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
    logo.src = isDark ? "../assets/logo-branca.png" : "../assets/Logo.png";
  });

  if (!themeToggle) {
    return;
  }

  const icon = themeToggle.querySelector("i");
  const label = themeToggle.querySelector("span");

  if (icon) {
    icon.className = isDark ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
  }

  if (label) {
    label.textContent = isDark ? "Modo claro" : "Modo escuro";
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
  const brandFilter = document.getElementById("brandFilter");
  const locationFilter = document.getElementById("locationFilter");
  const metricFilter = document.getElementById("metricFilter");
  const chartTypeFilter = document.getElementById("chartTypeFilter");
  const periodFilter = document.getElementById("periodFilter");
  const refreshButton = document.getElementById("refreshDashboard");

  categoryFilter?.addEventListener("change", () => {
    state.categoriaId = categoryFilter.value || "todos";
    setDashboardMetric(state.categoriaId === "todos" ? "categorias" : "marcas");
    loadDashboardProducts();
  });

  brandFilter?.addEventListener("change", () => {
    state.marca = brandFilter.value || "todos";
    setDashboardMetric(state.marca === "todos" ? state.metrica : "categorias");
    loadDashboardProducts();
  });

  locationFilter?.addEventListener("change", () => {
    state.localId = locationFilter.value || "todos";
    loadDashboardProducts();
  });

  metricFilter?.addEventListener("change", () => {
    setDashboardMetric(metricFilter.value || "categorias");

    // Evolucao depende do periodo selecionado; por isso recarrega quando essa metrica entra.
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

    // O periodo so muda dados quando a metrica atual e evolucao.
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
  // Garante que o estado interno e o select continuem sincronizados.
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
  // Se ainda nao temos a carga completa, deixamos o backend buscar os dados.
  if (!dashboardBaseData.ok) {
    return false;
  }

  if (state.categoriaId === "todos") {
    // Voltar para "Todos" e instantaneo porque guardamos a resposta completa.
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
    // Preserva os dados gerais, mas troca as listas que dependem do tipo selecionado.
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
  // Cache por categoria e periodo: evita buscar novamente dados que acabaram de ser carregados.
  const cacheKey = `${state.categoriaId}|${state.marca}|${state.localId}|${state.periodo}`;
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

  // ID incremental evita que uma resposta antiga sobrescreva uma selecao mais recente.
  const requestId = ++dashboardRequestId;

  if (showLoading) {
    setDashboardLoading(true);
    setStatus("Carregando dados...", "Buscando informações no banco.");
  }

  const params = new URLSearchParams({
    categoria_id: state.categoriaId,
    marca: state.marca,
    local_id: state.localId,
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
      // Sessao expirada: manda o usuario para o login com mensagem adequada.
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
      setDashboardLoading(false);
    }
  }
}

function setDashboardLoading(isLoading) {
  document.body.classList.toggle("dashboard-filtering", isLoading);

  const mainArea = document.querySelector(".dashboard-products-page .app-main");
  const chartCard = document.querySelector(".main-chart-card");
  const refreshButton = document.getElementById("refreshDashboard");

  mainArea?.setAttribute("aria-busy", String(isLoading));
  chartCard?.setAttribute("aria-busy", String(isLoading));

  if (refreshButton) {
    refreshButton.disabled = isLoading;
  }
}

function applyDashboardPayload(payload) {
  // Normaliza o JSON antes de qualquer componente tentar usar os dados.
  dashboardData = normalizeDashboardPayload(payload);
  syncStateWithDashboardPayload();

  if (
    dashboardData.categoria_filtro === "todos" &&
    dashboardData.marca_filtro === "todos" &&
    dashboardData.local_filtro === "todos"
  ) {
    // A resposta geral vira a base para filtros instantaneos por categoria.
    dashboardBaseData = dashboardData;
  }

  populateCategoryFilter(dashboardData.categorias);
  populateBrandFilter(dashboardData.marcas_filtro);
  populateLocationFilter(dashboardData.locais_filtro);
  renderSummaryCards();
  renderCurrentChart();
}

function normalizeDashboardPayload(payload) {
  // Nunca confiamos totalmente no formato vindo da rede; cada campo recebe fallback.
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
    categoria_filtro: String(data.categoria_filtro || state.categoriaId || "todos"),
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
    marca_filtro: String(data.marca_filtro || state.marca || "todos"),
    local_filtro: String(data.local_filtro || state.localId || "todos"),
    categorias: normalizeDataRows(data.categorias, true),
    marcas_filtro: normalizeFilterOptions(data.marcas_filtro),
    locais_filtro: normalizeFilterOptions(data.locais_filtro),
    status: normalizeDataRows(data.status),
    status_por_categoria: normalizeRowsByCategory(data.status_por_categoria),
    marcas: normalizeDataRows(data.marcas),
    marcas_por_categoria: normalizeRowsByCategory(data.marcas_por_categoria),
    locais: normalizeDataRows(data.locais),
    locais_por_categoria: normalizeRowsByCategory(data.locais_por_categoria),
    evolucao: normalizeDataRows(data.evolucao),
  };
}

function syncStateWithDashboardPayload() {
  state.categoriaId = dashboardData.categoria_filtro || "todos";
  state.marca = dashboardData.marca_filtro || "todos";
  state.localId = dashboardData.local_filtro || "todos";
  state.periodo = String(dashboardData.periodo || state.periodo || "30");
}

function normalizeFilterOptions(rows) {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .map((row) => ({
      id: String(row?.id || row?.nome || "").trim(),
      nome: String(row?.nome || "Sem nome").trim(),
      total: normalizeNumber(row?.total),
    }))
    .filter((row) => row.id !== "" && row.nome !== "" && row.total > 0);
}

function normalizeRowsByCategory(groups) {
  // Transforma objetos de grupos em listas normalizadas, mantendo o id da categoria como chave.
  if (!groups || typeof groups !== "object" || Array.isArray(groups)) {
    return {};
  }

  return Object.entries(groups).reduce((normalized, [categoryId, rows]) => {
    normalized[String(categoryId)] = normalizeDataRows(rows);
    return normalized;
  }, {});
}

function normalizeDataRows(rows, keepId = false) {
  // Padroniza cada linha usada por graficos, ranking e tabela.
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
  // Recria o select de tipos mantendo a escolha atual sempre que ela ainda existe.
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

function populateBrandFilter(brands) {
  populateDashboardSelect({
    elementId: "brandFilter",
    defaultValue: "todos",
    defaultLabel: "Todas as marcas",
    selectedValue: state.marca,
    rows: brands,
    onUpdate(value) {
      state.marca = value;
    },
  });
}

function populateLocationFilter(locations) {
  populateDashboardSelect({
    elementId: "locationFilter",
    defaultValue: "todos",
    defaultLabel: "Todos os locais",
    selectedValue: state.localId,
    rows: locations,
    onUpdate(value) {
      state.localId = value;
    },
  });
}

function populateDashboardSelect(config) {
  const select = document.getElementById(config.elementId);

  if (!select) {
    return;
  }

  const previousValue = select.value || config.selectedValue || config.defaultValue;

  select.innerHTML = "";
  select.appendChild(createOption(config.defaultValue, config.defaultLabel));

  config.rows.forEach((row) => {
    select.appendChild(
      createOption(row.id, `${row.nome} (${formatNumber(row.total)})`),
    );
  });

  select.value = config.rows.some((row) => row.id === previousValue)
    ? previousValue
    : config.defaultValue;

  config.onUpdate(select.value);
}

function createOption(value, label) {
  const option = document.createElement("option");
  option.value = value;
  option.textContent = label;

  return option;
}

function renderSummaryCards() {
  // Atualiza os quatro cards superiores a partir do resumo atual.
  const resumo = dashboardData.resumo || DEFAULT_DASHBOARD_DATA.resumo;
  const selected =
    dashboardData.categoria_selecionada ||
    DEFAULT_DASHBOARD_DATA.categoria_selecionada;
  const largest = resumo.maior_categoria;
  const activeFilters = buildActiveFilterLabels(selected);
  const totalFiltrado = normalizeNumber(resumo.total_filtrado);
  const resultText = totalFiltrado === 1 ? "ativo encontrado" : "ativos encontrados";

  setText("totalAssetsMetric", formatNumber(resumo.total_ativos));
  setText("totalTypesMetric", formatNumber(resumo.total_tipos));
  setText("selectedTypeMetric", activeFilters.title);

  const selectedDetail = `${formatNumber(totalFiltrado)} ${resultText}${
    activeFilters.detail ? ` para ${activeFilters.detail}.` : " no inventario."
  }`;

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

function buildActiveFilterLabels(selectedCategory) {
  const labels = [];

  if (selectedCategory.id !== "todos") {
    labels.push(formatCategoryLabel(selectedCategory.nome));
  }

  if (state.marca !== "todos") {
    labels.push(getSelectedOptionLabel("brandFilter"));
  }

  if (state.localId !== "todos") {
    labels.push(getSelectedOptionLabel("locationFilter"));
  }

  if (!labels.length) {
    return {
      title: "Todos",
      detail: "",
    };
  }

  return {
    title: labels[0],
    detail: labels.join(" em "),
  };
}

function getSelectedOptionLabel(selectId) {
  const select = document.getElementById(selectId);
  const option = select?.selectedOptions?.[0];
  const text = option?.textContent || "";

  return text.replace(/\s+\([0-9.]+\)$/u, "").trim() || "Filtro selecionado";
}

function renderCurrentChart() {
  // Um unico fluxo renderiza grafico, ranking e tabela para todas as metricas.
  const config = METRIC_CONFIG[state.metrica] || METRIC_CONFIG.categorias;
  const rows = getCurrentRows(config);
  const total = calculateRowsTotal(rows);
  const visibleRows = getVisibleRows(rows);

  setText("mainChartTitle", config.title);
  setText("mainChartDescription", buildChartDescription(config));
  setText("chartTotalLabel", config.totalLabel);
  setText("chartTotalMetric", formatNumber(total));

  renderChart(rows, config);
  renderRanking(visibleRows, total);
  renderTable(visibleRows, total);
}

function getCurrentRows(config) {
  // Evolucao conserva dias zerados no grafico; as demais metricas escondem zeros.
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
  const activeFilters = buildActiveFilterLabels(selected);

  if (!activeFilters.detail) {
    return config.description;
  }

  return `${config.description} Filtro ativo: ${activeFilters.detail}.`;
}

function calculateRowsTotal(rows) {
  return rows.reduce((sum, row) => sum + normalizeNumber(row.total), 0);
}

function getVisibleRows(rows) {
  // Ranking e tabela devem mostrar apenas itens/datas com dados reais.
  return rows.filter((row) => normalizeNumber(row.total) > 0);
}

function renderChart(rows, config) {
  // Chart.js redesenha do zero para evitar sobras visuais ao trocar filtros ou tipo.
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
      animation: isReducedMotionEnabled() ? false : {
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
  // Protege contra valores inesperados vindos do DOM ou de alteracoes manuais.
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
  // Leitura rapida lateral: mostra os principais itens ja filtrados.
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
  // Tabela completa de apoio, usando os mesmos dados da leitura rapida.
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
