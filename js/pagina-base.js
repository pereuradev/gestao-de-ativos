// Endpoint responsável por buscar as métricas do dashboard no backend.
// Esse arquivo PHP deve retornar dados em JSON.
const DASHBOARD_ENDPOINT = "../Backend/dashboard-metricas.php";

// Tempo, em milissegundos, usado para controlar a animação de troca de tema.
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

// Dados padrão usados caso o backend falhe ou não retorne informaçÃµes válidas.
// Isso evita que a tela quebre caso o banco de dados esteja indisponível.
const FALLBACK_DATA = {
  total_ativos: null,
  total_funcionarios: null,
  funcionarios_ativos: null,
  categorias: [],
  status_ativos: [],
  estoque_evolucao: [],
  ativos_evolucao: {},
  cadastros_evolucao: [],
};

const PRODUCT_HEALTH_STATUS_META = [
  { key: "disponivel", label: "DISPON\u00cdVEL", color: "#22c55e", aliases: ["disponivel", "estoque", "em estoque"] },
  { key: "formatacao", label: "FORMATA\u00c7\u00c3O", color: "#0ea5e9", aliases: ["formatacao"] },
  { key: "manutencao", label: "MANUTEN\u00c7\u00c3O", color: "#ef4444", aliases: ["manutencao"] },
  { key: "em-uso", label: "EM USO", color: "#3b82f6", aliases: ["em uso", "uso"] },
  { key: "homologacao", label: "HOMOLOGA\u00c7\u00c3O", color: "#8b5cf6", aliases: ["homologacao"] },
];

// Variável global que guarda os dados atuais do dashboard.
// Inicialmente recebe os dados de fallback.
let dashboardData = FALLBACK_DATA;

// Guarda a instÃ¢ncia do gráfico de evolução de estoque.
// Isso permite destruir e recriar o gráfico quando necessário.
let stockChart = null;

// Guarda a instÃ¢ncia do gráfico de evolução de cadastros.
let registrationsChart = null;

let productHealthChart = null;
let productHealthFilter = "todos";
let stockEvolutionPeriod = "semana";

// Timer usado para remover a classe de animação da troca de tema.
let themeTimer = null;

// Quando o HTML termina de carregar, a função principal da página é iniciada.
document.addEventListener("DOMContentLoaded", initPage);

/**
 * Busca um valor salvo no localStorage.
 * 
 * O try/catch evita erro caso o navegador bloqueie o acesso ao localStorage,
 * o que pode acontecer em alguns modos privados ou restriçÃµes de segurança.
 */
function getSavedItem(key) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}

/**
 * Salva um valor no localStorage.
 * 
 * Aqui é usado principalmente para guardar a preferência de tema do usuário,
 * como "dark" ou "light".
 */
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

function updateBrandLogo(isDark) {
  document.querySelectorAll(".brand-logo").forEach((logo) => {
    logo.src = isDark ? "../assets/logo-branca.png" : "../assets/Logo.png";
  });
}

/**
 * Função principal da página.
 * 
 * Ela inicializa todas as funcionalidades necessárias:
 * animação inicial, tema, sidebar, menus, busca e carregamento dos dados.
 */
function initPage() {
  startPageAnimation();
  loadSavedTheme();
  window.addEventListener("titech:motion-change", renderDashboardCharts);
  setupThemeToggle();
  setupSidebar();
  setupAssetMenu();
  setupCategorySearch();
  setupStockPeriodFilter();
  loadDashboardData();
}

function renderDashboardCharts() {
  if (!dashboardData) return;

  renderProductHealthChart(dashboardData.status_ativos || [], dashboardData.total_ativos);
  renderStockEvolutionChart(getStockEvolutionSeries(dashboardData));
  renderRegistrationEvolutionChart(dashboardData.cadastros_evolucao || []);
}

/**
 * Remove a classe "page-loading" do body.
 * 
 * Isso permite disparar animaçÃµes de entrada depois que a página já foi carregada.
 */
function startPageAnimation() {
  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

/**
 * Carrega o tema salvo pelo usuário.
 * 
 * Caso não exista tema salvo, o sistema assume o tema escuro como padrão.
 */
function loadSavedTheme() {
  const preferences = window.getCurrentUserPreferences?.() || {
    theme: getSavedItem("titech-theme") || "dark",
    accent: getSavedItem("titech-accent") || "teal",
    fontSize: getSavedItem("titech-font-size") || "default",
    density: getSavedItem("titech-density") || "comfortable",
    motion: getSavedItem("titech-motion") || "normal",
    cursor: getSavedItem("titech-cursor") || "enhanced",
  };

  applyTheme(preferences.theme);
  applyAccent(preferences.accent);
  window.applyFontSizePreference?.(preferences.fontSize);
  window.applyDensity?.(preferences.density);
  window.applyMotionPreference?.(preferences.motion);
  window.applyCursorPreference?.(preferences.cursor);
}

/**
 * Configura o botão de alternÃ¢ncia entre tema claro e escuro.
 */
function applyAccent(accent) {
  const nextAccent = Object.hasOwn(ACCENT_THEMES, accent) ? accent : "teal";
  const palette = ACCENT_THEMES[nextAccent];

  document.body.dataset.accent = nextAccent;
  document.body.style.setProperty("--cyan", palette.cyan);
  document.body.style.setProperty("--teal", palette.teal);
  document.body.style.setProperty("--mint", palette.mint);
  document.body.style.setProperty("--accent", palette.accent);
}
function setupThemeToggle() {
  const themeToggle = document.getElementById("themeToggle");

  // Se o botão não existir no HTML, a função é encerrada.
  if (!themeToggle) return;

  themeToggle.addEventListener("click", () => {
    // Verifica se o tema atual é escuro.
    const isDark = document.body.classList.contains("theme-dark");

    // Define o próximo tema com base no tema atual.
    const nextTheme = isDark ? "light" : "dark";

    // Limpa qualquer timer anterior para evitar conflito em cliques rápidos.
    clearTimeout(themeTimer);

    // Adiciona classe temporária usada para animação de troca de tema.
    document.body.classList.add("theme-switching");

    // Aplica o novo tema na página.
    applyTheme(nextTheme);

    // Salva a preferência do usuário no navegador.
    if (typeof window.saveUserPreferences === "function") {
      void window.saveUserPreferences({ theme: nextTheme });
    } else {
      setSavedItem("titech-theme", nextTheme);
    }

    // Recria os gráficos para atualizar as cores conforme o novo tema.
    renderProductHealthChart(dashboardData.status_ativos || [], dashboardData.total_ativos);
    renderStockEvolutionChart(dashboardData.estoque_evolucao || []);
    renderRegistrationEvolutionChart(dashboardData.cadastros_evolucao || []);

    // Remove a classe de animação depois do tempo definido.
    themeTimer = setTimeout(() => {
      document.body.classList.remove("theme-switching");
    }, THEME_TRANSITION_MS);
  });
}

/**
 * Aplica o tema visual na página.
 * 
 * Se o tema recebido for diferente de "light",
 * o sistema considera como tema escuro.
 */
function applyTheme(theme) {
  const themeToggle = document.getElementById("themeToggle");
  const nextTheme = ["dark", "light", "auto"].includes(theme) ? theme : "dark";
  const resolvedTheme = typeof window.resolveThemeMode === "function"
    ? window.resolveThemeMode(nextTheme)
    : (nextTheme === "light" ? "light" : "dark");
  const isDark = resolvedTheme === "dark";

  // Alterna as classes principais de tema no body.
  document.body.classList.toggle("theme-dark", isDark);
  document.body.classList.toggle("theme-light", !isDark);
  document.body.dataset.themePreference = nextTheme;
  updateBrandLogo(isDark);

  // Se o botão de tema não existir, não há mais nada para atualizar.
  if (!themeToggle) return;

  const icon = themeToggle.querySelector("i");
  const label = themeToggle.querySelector("span");

  // Atualiza o ícone do botão conforme o tema atual.
  if (icon) {
    icon.className = isDark ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
  }

  // Atualiza o texto do botão conforme o tema atual.
  if (label) {
    label.textContent = isDark ? "Modo claro" : "Modo escuro";
  }
}

/**
 * Configura a abertura e fechamento da sidebar.
 * 
 * Funciona com botão de abrir, botão de fechar, clique no fundo escuro
 * e tecla Escape.
 */
function setupSidebar() {
  const openButton = document.getElementById("openSidebar");
  const closeButton = document.getElementById("closeSidebar");
  const backdrop = document.getElementById("sidebarBackdrop");

  // Operador ?. evita erro caso algum elemento não exista.
  openButton?.addEventListener("click", openSidebar);
  closeButton?.addEventListener("click", closeSidebar);
  backdrop?.addEventListener("click", closeSidebar);

  // Fecha a sidebar ao pressionar Escape.
  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeSidebar();
    }
  });

  // Em telas menores, fecha a sidebar ao clicar em algum link do menu.
  document.querySelectorAll(".sidebar-nav a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 920) {
        closeSidebar();
      }
    });
  });

  window.setupSidebarResize?.();
}

/**
 * Abre a sidebar adicionando a classe "sidebar-open" no body.
 */
function openSidebar() {
  document.documentElement.classList.add("sidebar-open");
  document.body.classList.add("sidebar-open");
}

/**
 * Fecha a sidebar removendo a classe "sidebar-open" do body.
 */
function closeSidebar() {
  document.documentElement.classList.remove("sidebar-open");
  document.body.classList.remove("sidebar-open");
}

/**
 * Configura os grupos de menu expansíveis da sidebar.
 * 
 * Exemplo:
 * - Cadastros
 * - Ativos
 */
function setupAssetMenu() {
  const groups = Array.from(document.querySelectorAll("[data-nav-group]"));

  groups.forEach((group) => {
    const button = group.querySelector(".nav-toggle");
    const submenu = group.querySelector(".nav-submenu");

    // Se o grupo não tiver botão ou submenu, ele é ignorado.
    if (!button || !submenu) return;

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

/**
 * Configura o campo de busca de categorias.
 * 
 * A cada caractere digitado, a lista de categorias é renderizada novamente.
 */
function setupCategorySearch() {
  const search = document.getElementById("categorySearch");

  search?.addEventListener("input", () => {
    renderCategories(dashboardData.categorias || [], search.value);
  });
}

/**
 * Carrega os dados do dashboard a partir do backend.
 * 
 * Se a requisição der certo, os dados são normalizados e exibidos.
 * Se falhar, a interface continua funcionando com dados vazios.
 */
async function loadDashboardData() {
  const status = document.getElementById("dashboardStatus");

  try {
    const response = await fetch(DASHBOARD_ENDPOINT, {
      headers: { Accept: "application/json" },
    });

    // Verifica se a resposta HTTP foi bem-sucedida.
    if (!response.ok) {
      // Se o backend retornar 401, significa que a sessão expirou ou o usuário não está autorizado.
      if (response.status === 401) {
        window.location.href = "Pagina-login.html?sessao=expirada";
        return;
      }

      throw new Error("Falha ao carregar metricas");
    }

    // Converte a resposta JSON e normaliza os dados.
    dashboardData = normalizeDashboardPayload(await response.json());
  } catch {
    // Em caso de erro, usa os dados de fallback.
    dashboardData = FALLBACK_DATA;

    // Renderiza a interface mesmo sem dados reais.
    try {
      renderDashboard(FALLBACK_DATA);
    } catch (error) {
      console.error("Erro ao renderizar fallback do dashboard:", error);
    }

    // Mostra uma mensagem amigável para o usuário.
    setStatus(
      "N\u00e3o foi poss\u00edvel carregar o banco agora. A interface permanece dispon\u00edvel.",
    );
    return;
  }

  try {
    renderDashboard(dashboardData);
    setStatus("Sincronizado com o banco Supabase.");
  } catch (error) {
    console.error("Erro ao renderizar dashboard:", error);
    setText("productHealthTotal", formatNumber(dashboardData.total_ativos));
    setStatus("Banco conectado. Revise os gr\u00e1ficos se algum dado n\u00e3o aparecer.");
  }

  /**
   * Atualiza o texto do elemento de status do dashboard.
   */
  function setStatus(message) {
    if (status) {
      status.textContent = message;
    }
  }
}

/**
 * Normaliza o payload recebido do backend.
 * 
 * Essa função garante que os dados tenham o formato esperado
 * antes de serem usados pela interface.
 */
function normalizeDashboardPayload(payload) {
  const data = payload && typeof payload === "object" ? payload : {};
  const categories = Array.isArray(data.categorias) ? data.categorias : [];
  const assetStatuses = Array.isArray(data.status_ativos) ? data.status_ativos : [];
  const assetEvolution = data.ativos_evolucao && typeof data.ativos_evolucao === "object"
    ? data.ativos_evolucao
    : {};

  return {
    total_ativos: normalizeNumber(data.total_ativos),
    total_funcionarios: normalizeNumber(data.total_funcionarios),
    funcionarios_ativos: normalizeNumber(data.funcionarios_ativos),

    // Normaliza cada categoria recebida do backend.
    categorias: categories
      .map((category) => ({
        nome: String(category?.nome || "").trim(),
        total: normalizeNumber(category?.total),
      }))
      .filter((category) => category.nome !== ""),

    status_ativos: assetStatuses
      .map((status) => ({
        status: String(status?.status || "Sem status").trim(),
        total: normalizeNumber(status?.total),
      }))
      .filter((status) => status.status !== "" && status.total !== null),

    // Normaliza as séries usadas nos gráficos.
    estoque_evolucao: normalizeEvolutionSeries(data.estoque_evolucao),
    ativos_evolucao: {
      hoje: normalizeEvolutionSeries(assetEvolution.hoje),
      semana: normalizeEvolutionSeries(assetEvolution.semana || data.estoque_evolucao),
      mes: normalizeEvolutionSeries(assetEvolution.mes),
      ano: normalizeEvolutionSeries(assetEvolution.ano),
    },
    cadastros_evolucao: normalizeEvolutionSeries(data.cadastros_evolucao),
  };
}

/**
 * Normaliza uma série evolutiva usada nos gráficos.
 * 
 * Cada item precisa ter:
 * - label: texto do eixo X
 * - total: valor numérico
 */
function normalizeEvolutionSeries(series) {
  if (!Array.isArray(series)) {
    return [];
  }

  return series
    .map((item) => ({
      label: String(item?.label || "").trim(),
      total: normalizeNumber(item?.total),
      novos: normalizeNumber(item?.novos) ?? 0,
    }))
    .filter((item) => item.label !== "" && item.total !== null);
}

/**
 * Normaliza valores numéricos.
 * 
 * Retorna null se o valor for vazio, inválido, negativo ou não numérico.
 */
function normalizeNumber(value) {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const number = Number(value);

  return Number.isFinite(number) && number >= 0 ? number : null;
}

/**
 * Renderiza todas as partes principais do dashboard.
 */
function renderDashboard(data) {
  // Atualiza os cards principais.
  setText("totalAssetsMetric", formatNumber(data.total_ativos));
  setText("employeesMetric", formatNumber(data.total_funcionarios));

  // Monta o detalhe de funcionários ativos, caso essa informação exista.
  const employeesDetail = data.funcionarios_ativos !== null
    ? `${formatNumber(data.funcionarios_ativos)} funcion\u00e1rios ativos`
    : "Registros em public.perfis_usuarios";

  setText("employeesDetail", employeesDetail);

  // Atualiza listas, menus dinÃ¢micos e gráficos.
  renderCategories(data.categorias || []);
  renderProductHealthChart(data.status_ativos || [], data.total_ativos);
  renderDynamicCategoryLinks(data.categorias || []);
  renderStockEvolutionChart(getStockEvolutionSeries(data));
  renderRegistrationEvolutionChart(data.cadastros_evolucao || []);
}

function getStockEvolutionSeries(data) {
  const seriesByPeriod = data.ativos_evolucao || {};

  return seriesByPeriod[stockEvolutionPeriod]?.length
    ? seriesByPeriod[stockEvolutionPeriod]
    : data.estoque_evolucao || [];
}

function renderProductHealthChart(statuses, totalAssets) {
  const canvas = document.getElementById("productHealthChart");
  const legend = document.getElementById("productHealthLegend");
  const totalFromStatuses = statuses.reduce((sum, item) => sum + (item.total || 0), 0);
  const total = totalAssets !== null && totalAssets !== undefined ? totalAssets : totalFromStatuses;
  const items = buildProductHealthItems(statuses);
  const chartItems = items.filter((item) => item.total > 0);
  const selectedItem = items.find((item) => item.key === productHealthFilter);
  const hasData = chartItems.length > 0;
  const visibleItems = buildProductHealthChartItems(chartItems, selectedItem, totalFromStatuses);
  const centerTotal = selectedItem ? selectedItem.total : total;

  setText("productHealthTotal", formatNumber(centerTotal));
  renderProductHealthLegend(legend, items, totalFromStatuses, productHealthFilter);

  if (!canvas || !window.Chart) return;

  if (productHealthChart) {
    productHealthChart.destroy();
  }

  productHealthChart = new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: visibleItems.map((item) => item.label),
      datasets: [
        {
          data: visibleItems.map((item) => item.total),
          backgroundColor: visibleItems.map((item) => item.color),
          hoverBackgroundColor: visibleItems.map((item) => item.hoverColor || item.color),
          borderColor: "rgba(255, 255, 255, 0)",
          borderWidth: 0,
          hoverOffset: hasData ? 8 : 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: isReducedMotionEnabled() ? false : {
        duration: 850,
        easing: "easeOutQuart",
      },
      cutout: "72%",
      onHover(event, elements) {
        if (event.native?.target) {
          event.native.target.style.cursor = elements.length && hasData ? "pointer" : "default";
        }
      },
      onClick(_event, elements) {
        if (!hasData || !elements.length) return;

        const item = visibleItems[elements[0].index];

        if (!item?.filterKey || item.filterKey === "restante") return;

        productHealthFilter = productHealthFilter === item.filterKey ? "todos" : item.filterKey;
        renderProductHealthChart(dashboardData.status_ativos || [], dashboardData.total_ativos);
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: false,
          external: createProductHealthTooltip,
          callbacks: {
            label(context) {
              const value = Number(context.parsed || 0);
              const percent = totalFromStatuses ? Math.round((value / totalFromStatuses) * 100) : 0;

              return `${context.label}: ${formatNumber(value)} (${percent}%)`;
            },
          },
        },
      },
    },
  });
}

function buildProductHealthChartItems(chartItems, selectedItem, total) {
  if (!chartItems.length) {
    return [{ label: "SEM DADOS", total: 1, color: "rgba(148, 163, 184, 0.45)" }];
  }

  if (!selectedItem) {
    return chartItems.map((item) => ({
      ...item,
      filterKey: item.key,
      hoverColor: item.color,
    }));
  }

  const remainder = Math.max(0, total - selectedItem.total);
  const filteredItems = [{
    ...selectedItem,
    filterKey: selectedItem.key,
    hoverColor: selectedItem.color,
  }];

  if (remainder > 0) {
    filteredItems.push({
      key: "restante",
      label: "OUTROS STATUS",
      total: remainder,
      color: "rgba(148, 163, 184, 0.22)",
      hoverColor: "rgba(148, 163, 184, 0.32)",
      filterKey: "restante",
    });
  }

  return filteredItems;
}

function buildProductHealthItems(statuses) {
  const totals = new Map(PRODUCT_HEALTH_STATUS_META.map((item) => [item.key, 0]));

  statuses.forEach((item) => {
    const meta = getProductStatusMeta(item.status);
    totals.set(meta.key, (totals.get(meta.key) || 0) + (item.total || 0));
  });

  return PRODUCT_HEALTH_STATUS_META.map((meta) => ({
    ...meta,
    total: totals.get(meta.key) || 0,
  }));
}

function getProductStatusMeta(status) {
  const normalized = normalizeText(status).trim();

  return PRODUCT_HEALTH_STATUS_META.find((meta) =>
    meta.aliases.some((alias) => normalizeText(alias).trim() === normalized),
  ) || PRODUCT_HEALTH_STATUS_META[0];
}

function renderProductHealthLegend(legend, items, total, activeFilter) {
  if (!legend) return;

  legend.innerHTML = "";

  const allButton = createProductHealthLegendButton({
    item: {
      key: "todos",
      label: "TODOS",
      total,
      color: "linear-gradient(135deg, var(--cyan), var(--teal))",
    },
    total,
    activeFilter,
    muted: total === 0,
  });

  legend.appendChild(allButton);

  items.forEach((item) => {
    legend.appendChild(createProductHealthLegendButton({
      item,
      total,
      activeFilter,
      muted: item.total === 0,
    }));
  });
}

function createProductHealthLegendButton({
  item,
  total,
  activeFilter,
  muted,
}) {
  const row = document.createElement("button");
  const swatch = document.createElement("span");
  const label = document.createElement("span");
  const value = document.createElement("strong");
  const percent = total ? Math.round((item.total / total) * 100) : 0;

  row.type = "button";
  row.className = "product-health-legend-item";
  row.dataset.healthFilter = item.key;
  row.classList.toggle("is-muted", muted);
  row.classList.toggle("is-active", activeFilter === item.key);
  row.setAttribute("aria-pressed", String(activeFilter === item.key));
  row.setAttribute("aria-label", `Filtrar por ${item.label}`);
  swatch.className = "product-health-swatch";
  swatch.style.background = item.color;
  label.textContent = item.label;
  value.textContent = `${formatNumber(item.total)} / ${percent}%`;

  row.addEventListener("click", () => {
    productHealthFilter = activeFilter === item.key ? "todos" : item.key;
    renderProductHealthChart(dashboardData.status_ativos || [], dashboardData.total_ativos);
  });

  row.append(swatch, label, value);

  return row;
}

function createProductHealthTooltip(context) {
  const { chart, tooltip } = context;
  const parent = chart.canvas.parentNode;

  if (!parent) return;

  let tooltipElement = parent.querySelector(".product-health-tooltip");

  if (!tooltipElement) {
    tooltipElement = document.createElement("div");
    tooltipElement.className = "product-health-tooltip";
    parent.appendChild(tooltipElement);
  }

  if (tooltip.opacity === 0) {
    tooltipElement.classList.remove("show");
    return;
  }

  const title = tooltip.title?.[0] || "";
  const label = tooltip.body?.[0]?.lines?.[0] || "";

  tooltipElement.innerHTML = `
    <span>${escapeHtml(title)}</span>
    <strong>${escapeHtml(label.replace(`${title}: `, ""))}</strong>
  `;
  tooltipElement.classList.add("show");
}

/**
 * Renderiza o gráfico de evolução de estoque.
 */
function renderStockEvolutionChart(series) {
  updateStockEvolutionSummary(series);

  stockChart = renderEvolutionChart({
    chart: stockChart,
    canvasId: "stockEvolutionChart",
    series,
    label: "Novos ativos",
    yTitle: "Novos ativos",
    tooltipSuffix: "novos ativos",
    colorVar: "--mint",
    backgroundColor: "rgba(79, 199, 177, 0.2)",
    secondaryColorVar: "--cyan",
    valueKey: "novos",
  });
}

function updateStockEvolutionSummary(series) {
  const first = series[0]?.total ?? 0;
  const last = series.at(-1)?.total ?? 0;
  const totalNew = series.reduce((sum, item) => sum + (item.novos || 0), 0);
  const delta = last - first;

  setText("stockPeriodTotal", formatNumber(last));
  setText("stockPeriodNew", formatNumber(totalNew));
  setText("stockPeriodDelta", `${delta >= 0 ? "+" : ""}${formatNumber(delta)}`);
}

function setupStockPeriodFilter() {
  const buttons = document.querySelectorAll("[data-stock-period]");

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const period = button.dataset.stockPeriod || "semana";

      stockEvolutionPeriod = period;

      buttons.forEach((item) => {
        const isActive = item === button;

        item.classList.toggle("is-active", isActive);
        item.setAttribute("aria-pressed", String(isActive));
      });

      renderStockEvolutionChart(getStockEvolutionSeries(dashboardData));
    });
  });
}

/**
 * Renderiza o gráfico de evolução de cadastros.
 */
function renderRegistrationEvolutionChart(series) {
  registrationsChart = renderEvolutionChart({
    chart: registrationsChart,
    canvasId: "registrationEvolutionChart",
    series,
    label: "Cadastros",
    yTitle: "Cadastros",
    tooltipSuffix: "cadastros",
    colorVar: "--cyan",
    backgroundColor: "rgba(74, 163, 199, 0.16)",
  });
}

/**
 * Função genérica para renderizar gráficos evolutivos usando Chart.js.
 * 
 * Ela é reutilizada tanto para o gráfico de estoque quanto para o de cadastros.
 */
function renderEvolutionChart({
  chart,
  canvasId,
  series,
  label,
  yTitle,
  tooltipSuffix,
  colorVar,
  backgroundColor,
  secondaryColorVar = colorVar,
  valueKey = "total",
}) {
  const canvas = document.getElementById(canvasId);

  // Se o canvas não existir ou o Chart.js não estiver carregado, mantém o gráfico atual.
  if (!canvas || !window.Chart) return chart;

  // Separa os valores e labels que serão usados no gráfico.
  const values = series.map((item) => item[valueKey] ?? item.total);
  const labels = series.map((item) => item.label);

  // Pega variáveis CSS do tema atual para aplicar no gráfico.
  const styles = getComputedStyle(document.body);
  const textColor = styles.getPropertyValue("--text").trim() || "#f6fbff";
  const mutedColor = styles.getPropertyValue("--muted").trim() || "#9cb8c9";
  const lineColor = styles.getPropertyValue(colorVar).trim() || "#66d5c2";
  const secondaryColor = styles.getPropertyValue(secondaryColorVar).trim() || lineColor;
  const gridColor = styles.getPropertyValue("--line").trim() || "rgba(255, 255, 255, 0.13)";
  const context = canvas.getContext("2d");
  const gradient = context.createLinearGradient(0, 0, 0, canvas.offsetHeight || 320);

  gradient.addColorStop(0, backgroundColor);
  gradient.addColorStop(0.72, "rgba(79, 199, 177, 0.035)");
  gradient.addColorStop(1, "rgba(79, 199, 177, 0)");

  // Destrói o gráfico anterior antes de criar outro.
  // Isso evita sobreposição e bugs visuais.
  if (chart) {
    chart.destroy();
  }

  // Cria o gráfico de linha com Chart.js.
  return new Chart(canvas, {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          label,
          data: values,
          borderColor: lineColor,
          backgroundColor: gradient,
          borderWidth: 4,
          pointBackgroundColor: lineColor,
          pointBorderColor: "#f8feff",
          pointBorderWidth: 3,
          pointRadius: 4,
          pointHoverRadius: 7,
          pointHoverBackgroundColor: secondaryColor,
          pointHoverBorderColor: "#ffffff",
          tension: 0.42,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: isReducedMotionEnabled() ? false : {
        duration: 650,
        easing: "easeOutQuart",
      },

      // Define como o gráfico responde ao passar o mouse.
      interaction: {
        intersect: false,
        mode: "index",
      },

      plugins: {
        // Esconde a legenda, já que o card já explica o conteúdo.
        legend: {
          display: false,
        },

        // Personaliza o tooltip exibido ao passar o mouse.
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
            title(items) {
              return items[0]?.label || "";
            },
            label(context) {
              const item = series[context.dataIndex] || {};
              const total = item.total !== undefined ? ` | ${formatNumber(item.total)} no total` : "";

              return `${formatNumber(context.parsed.y)} ${tooltipSuffix}${total}`;
            },
          },
        },
      },

      scales: {
        x: {
          border: {
            display: false,
          },
          grid: {
            color: "rgba(255, 255, 255, 0.055)",
          },
          ticks: {
            color: mutedColor,
            maxRotation: 0,
            font: {
              weight: 700,
            },
          },
        },
        y: {
          beginAtZero: true,
          border: {
            display: false,
          },
          grid: {
            color: gridColor,
          },
          ticks: {
            color: mutedColor,
            precision: 0,
            font: {
              weight: 700,
            },
          },
          title: {
            display: true,
            text: yTitle,
            color: textColor,
            font: {
              size: 16,
              weight: 800,
            },
          },
        },
      },
    },
  });
}

/**
 * Renderiza a lista de categorias na página inicial.
 * 
 * Também permite filtrar as categorias com base no texto digitado na busca.
 */
function renderCategories(categories, filter = "") {
  const list = document.getElementById("categoryList");

  if (!list) return;

  // Normaliza o texto pesquisado para ignorar maiúsculas, minúsculas e acentos.
  const normalizedFilter = normalizeText(filter);

  // Filtra as categorias e limita a exibição a 8 itens.
  const visibleCategories = categories
    .filter((category) =>
      normalizeText(category.nome).includes(normalizedFilter),
    )
    .slice(0, 8);

  // Se nenhuma categoria for encontrada, exibe estado vazio.
  if (!visibleCategories.length) {
    list.innerHTML = "";
    list.appendChild(createEmptyState("Nenhuma categoria encontrada."));
    return;
  }

  // Fragment melhora performance ao inserir vários elementos no DOM.
  const fragment = document.createDocumentFragment();

  visibleCategories.forEach((category) => {
    const link = document.createElement("a");
    const title = document.createElement("strong");
    const detail = document.createElement("span");
    const count = document.createElement("span");
    const textWrap = document.createElement("div");

    // Link para abrir a página de ativos já filtrada pela categoria.
    link.className = "category-item";
    link.href = `ativos.php?categoria=${encodeURIComponent(category.nome)}`;

    title.textContent = formatCategoryName(category.nome);
    detail.textContent = "Abrir ativos desta categoria";
    count.className = "category-count";
    count.textContent = formatNumber(category.total);

    textWrap.append(title, detail);
    link.append(textWrap, count);
    fragment.appendChild(link);
  });

  // Limpa a lista atual e insere os novos itens.
  list.innerHTML = "";
  list.appendChild(fragment);
}

/**
 * Renderiza links de categorias dentro do submenu de ativos.
 * 
 * As categorias fixas "COLETOR" e "TABLET" já existem no HTML,
 * então aqui são adicionadas apenas categorias extras.
 */
function renderDynamicCategoryLinks(categories) {
  const container = document.getElementById("dynamicCategoryLinks");

  if (!container) return;

  const fixed = new Set(["COLETOR", "TABLET"]);

  const extraCategories = categories
    .filter((category) => category.nome && !fixed.has(category.nome.toUpperCase()))
    .slice(0, 5);

  container.innerHTML = "";

  extraCategories.forEach((category) => {
    const link = document.createElement("a");
    const label = document.createElement("span");
    const count = document.createElement("small");

    link.href = `ativos.php?categoria=${encodeURIComponent(category.nome)}`;

    label.className = "submenu-label";
    label.textContent = formatCategoryName(category.nome);

    count.textContent = formatNumber(category.total);

    link.append(label, count);
    container.appendChild(link);
  });
}

/**
 * Cria um bloco visual de estado vazio.
 * 
 * Usado quando não existem categorias ou nenhum resultado foi encontrado.
 */
function createEmptyState(message) {
  const empty = document.createElement("div");
  const icon = document.createElement("i");
  const text = document.createElement("span");

  empty.className = "empty-state";
  icon.className = "bi bi-info-circle";
  text.textContent = message;

  empty.append(icon, text);

  return empty;
}

/**
 * Atualiza o texto de um elemento pelo ID.
 * 
 * Evita erro caso o elemento não exista.
 */
function setText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

/**
 * Formata números no padrão brasileiro.
 * 
 * Exemplo:
 * 1000 vira "1.000"
 */
function formatNumber(value) {
  if (value === null || value === undefined || value === "") {
    return "--";
  }

  return Number(value).toLocaleString("pt-BR");
}

/**
 * Converte nomes técnicos de categorias em nomes mais amigáveis para o usuário.
 */
function formatCategoryName(name) {
  const labels = {
    COLETOR: "Coletores de dados",
    TABLET: "Tablets",
    NOTEBOOKS: "Notebooks",
    IMPRESSORA: "Impressoras",
    "DOCA DE CARGA": "Docas de carga",
  };

  return labels[name?.toUpperCase()] || name;
}

/**
 * Normaliza textos para facilitar buscas.
 * 
 * Essa função:
 * - transforma qualquer valor em string;
 * - remove acentos;
 * - deixa tudo em minúsculo.
 * 
 * Assim, buscar "tablet", "TABLET" ou "táblet" fica mais tolerante.
 */
function normalizeText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

