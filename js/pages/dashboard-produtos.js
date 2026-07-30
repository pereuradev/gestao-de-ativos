(function () {
  // Mantemos o dashboard dentro de uma funcao anonima para nao misturar variaveis
  // desta tela com os scripts globais usados nas outras paginas.
  const ENDPOINT_PRODUTOS_PAINEL = "../Backend/dashboard-produtos.php";
  const ENDPOINT_METRICAS_GERAIS = "../Backend/dashboard-metricas.php";
  const CHAVE_ARMAZENAMENTO_TEMA = "titech-theme";
  const CHAVE_ARMAZENAMENTO_DESTAQUE = "titech-accent";
  const TRANSICAO_TEMA_MS = 660;

  // Paletas aceitas pela tela de configuracoes. O dashboard usa as mesmas variaveis
  // para graficos, botoes e estados visuais.
  const TEMAS_DESTAQUE = {
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
  const DADOS_PAINEL_PADRAO = {
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
  const CONFIGURACAO_METRICA = {
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
      description: "Mostra quantos ativos existem por marca no filtro atual.",
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

  const PERIODOS_EVOLUCAO_ATIVOS = {
    hoje: {
      rotulo: "Diariamente",
      detalhe: "por hora de hoje",
    },
    semana: {
      rotulo: "Semanalmente",
      detalhe: "nos \u00faltimos 7 dias",
    },
    mes: {
      rotulo: "Mensalmente",
      detalhe: "nos \u00faltimos 30 dias",
    },
    ano: {
      rotulo: "Anualmente",
      detalhe: "nos \u00faltimos 12 meses",
    },
  };

  let dadosPainel = DADOS_PAINEL_PADRAO;
  let dadosBasePainel = DADOS_PAINEL_PADRAO;
  let graficoProdutos = null;
  let graficoEvolucaoAtivos = null;
  let temporizadorTema = null;
  let controladorRequisicaoPainel = null;
  let controladorRequisicaoEvolucao = null;
  let idRequisicaoPainel = 0;
  let idRequisicaoEvolucao = 0;
  const cachePainel = new Map();
  let periodoEvolucaoAtivos = "semana";
  let dadosEvolucaoAtivos = {
    hoje: [],
    semana: [],
    mes: [],
    ano: [],
  };

  // Estado atual dos filtros da tela. Toda renderizacao le esses valores.
  const estado = {
    categoriaId: "todos",
    marca: "todos",
    localId: "todos",
    metrica: "categorias",
    tipoGrafico: "bar",
    periodo: "30",
  };

  document.addEventListener(
    "DOMContentLoaded",
    inicializarPaginaProdutosPainel,
  );

  function inicializarPaginaProdutosPainel() {
    // O tema e a sidebar seguem o base-interface.js; aqui so reagimos para redesenhar o grafico.
    const tratadorAnteriorAlteracaoTema =
      typeof window.onThemeChanged === "function"
        ? window.onThemeChanged
        : null;
    window.onThemeChanged = () => {
      tratadorAnteriorAlteracaoTema?.();
      renderizarGraficoAtual();
      renderizarEvolucaoAtivos();
    };
    window.addEventListener("titech:motion-change", () => {
      renderizarGraficoAtual();
      renderizarEvolucaoAtivos();
    });
    (window.iniciarAnimacaoPagina || iniciarAnimacaoPagina)();
    (window.carregarTemaSalvo || carregarTemaSalvo)();
    (window.configurarAlternadorTema || configurarAlternadorTema)();
    (window.configurarBarraLateral || configurarBarraLateral)();
    (window.configurarGruposNavegacao || configurarGruposNavegacao)();
    configurarControlesPainel();
    configurarEvolucaoAtivos();
    carregarProdutosPainel();
    carregarEvolucaoAtivos();
  }

  function iniciarAnimacaoPagina() {
    requestAnimationFrame(() => {
      document.body.classList.remove("page-loading");
    });
  }

  function obterItemSalvo(chave) {
    // LocalStorage pode falhar em alguns modos privados; por isso o acesso fica protegido.
    try {
      return localStorage.getItem(chave);
    } catch {
      return null;
    }
  }

  function definirItemSalvo(chave, valor) {
    try {
      localStorage.setItem(chave, valor);
    } catch {
      return;
    }
  }

  function movimentoReduzidoEstaAtivado() {
    if (document.body?.dataset.motion === "reduced") {
      return true;
    }

    if (obterItemSalvo("titech-motion") === "reduced") {
      return true;
    }

    return (
      window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false
    );
  }

  function carregarTemaSalvo() {
    // Fallback local caso o base-interface.js nao esteja disponivel por algum motivo.
    aplicarDestaque(obterItemSalvo(CHAVE_ARMAZENAMENTO_DESTAQUE) || "teal");
    aplicarTema(obterItemSalvo(CHAVE_ARMAZENAMENTO_TEMA) || "dark");
    window.aplicarPreferenciaTamanhoFonte?.(
      obterItemSalvo("titech-font-size") || "default",
    );
    window.aplicarDensidade?.(
      obterItemSalvo("titech-density") || "comfortable",
    );
    window.aplicarPreferenciaMovimento?.(
      obterItemSalvo("titech-motion") || "normal",
    );
    window.aplicarPreferenciaCursor?.(
      obterItemSalvo("titech-cursor") || "enhanced",
    );
  }

  function aplicarDestaque(destaque) {
    const destaqueSelecionado = Object.hasOwn(TEMAS_DESTAQUE, destaque)
      ? destaque
      : "teal";
    const paleta = TEMAS_DESTAQUE[destaqueSelecionado];

    document.body.dataset.accent = destaqueSelecionado;
    document.body.style.setProperty("--cyan", paleta.cyan);
    document.body.style.setProperty("--teal", paleta.teal);
    document.body.style.setProperty("--mint", paleta.mint);
    document.body.style.setProperty("--accent", paleta.accent);
  }

  function configurarAlternadorTema() {
    const alternadorTema = document.getElementById("themeToggle");

    if (!alternadorTema) {
      return;
    }

    alternadorTema.addEventListener("click", () => {
      const ehEscuro = document.body.classList.contains("theme-dark");
      const temaProximo = ehEscuro ? "light" : "dark";

      clearTimeout(temporizadorTema);
      document.body.classList.add("theme-switching");

      aplicarTema(temaProximo);
      definirItemSalvo(CHAVE_ARMAZENAMENTO_TEMA, temaProximo);
      renderizarGraficoAtual();
      renderizarEvolucaoAtivos();

      temporizadorTema = window.setTimeout(() => {
        document.body.classList.remove("theme-switching");
      }, TRANSICAO_TEMA_MS);
    });
  }

  function aplicarTema(tema) {
    const ehEscuro = tema !== "light";
    const alternadorTema = document.getElementById("themeToggle");

    document.body.classList.toggle("theme-dark", ehEscuro);
    document.body.classList.toggle("theme-light", !ehEscuro);

    document.querySelectorAll(".brand-logo").forEach((logo) => {
      logo.src = ehEscuro ? "../assets/logo-branca.png" : "../assets/Logo.png";
    });

    if (!alternadorTema) {
      return;
    }

    const icone = alternadorTema.querySelector("i");
    const rotulo = alternadorTema.querySelector("span");

    if (icone) {
      icone.className = ehEscuro ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
    }

    if (rotulo) {
      rotulo.textContent = ehEscuro ? "Modo claro" : "Modo escuro";
    }
  }

  function configurarBarraLateral() {
    const botaoAbrir = document.getElementById("openSidebar");
    const botaoFechar = document.getElementById("closeSidebar");
    const fundoModal = document.getElementById("sidebarBackdrop");

    botaoAbrir?.addEventListener("click", () =>
      document.body.classList.add("sidebar-open"),
    );
    botaoFechar?.addEventListener("click", () =>
      document.body.classList.remove("sidebar-open"),
    );
    fundoModal?.addEventListener("click", () =>
      document.body.classList.remove("sidebar-open"),
    );

    window.addEventListener("keydown", (evento) => {
      if (evento.key === "Escape") {
        document.body.classList.remove("sidebar-open");
      }
    });

    document.querySelectorAll(".sidebar-nav a").forEach((atalho) => {
      atalho.addEventListener("click", () => {
        if (window.innerWidth <= 920) {
          document.body.classList.remove("sidebar-open");
        }
      });
    });
  }

  function configurarGruposNavegacao() {
    const grupos = Array.from(document.querySelectorAll("[data-nav-group]"));

    grupos.forEach((grupo) => {
      const botao = grupo.querySelector(".nav-toggle");
      const submenu = grupo.querySelector(".nav-submenu");

      if (!botao || !submenu) {
        return;
      }

      botao.addEventListener("click", () => {
        const deveAbrir = !grupo.classList.contains("open");

        grupos.forEach((outroGrupo) => {
          if (outroGrupo === grupo) {
            return;
          }

          outroGrupo.classList.remove("open");
          outroGrupo
            .querySelector(".nav-toggle")
            ?.setAttribute("aria-expanded", "false");
        });

        grupo.classList.toggle("open", deveAbrir);
        botao.setAttribute("aria-expanded", String(deveAbrir));
      });
    });
  }

  function configurarControlesPainel() {
    const filtroCategoria = document.getElementById("categoryFilter");
    const filtroMarca = document.getElementById("brandFilter");
    const filtroLocal = document.getElementById("locationFilter");
    const filtroMetrica = document.getElementById("metricFilter");
    const filtroTipoGrafico = document.getElementById("chartTypeFilter");
    const filtroPeriodo = document.getElementById("periodFilter");
    const botaoAtualizar = document.getElementById("refreshDashboard");

    filtroCategoria?.addEventListener("change", () => {
      estado.categoriaId = filtroCategoria.value || "todos";
      definirMetricaPainel(
        estado.categoriaId === "todos" ? "categorias" : "marcas",
      );
      carregarProdutosPainel();
    });

    filtroMarca?.addEventListener("change", () => {
      estado.marca = filtroMarca.value || "todos";
      definirMetricaPainel(
        estado.marca === "todos" ? estado.metrica : "categorias",
      );
      carregarProdutosPainel();
    });

    filtroLocal?.addEventListener("change", () => {
      estado.localId = filtroLocal.value || "todos";
      carregarProdutosPainel();
    });

    filtroMetrica?.addEventListener("change", () => {
      definirMetricaPainel(filtroMetrica.value || "categorias");

      // Evolucao depende do periodo selecionado; por isso recarrega quando essa metrica entra.
      if (estado.metrica === "evolucao") {
        carregarProdutosPainel();
        return;
      }

      renderizarGraficoAtual();
    });

    filtroTipoGrafico?.addEventListener("change", () => {
      estado.tipoGrafico = filtroTipoGrafico.value || "bar";
      renderizarGraficoAtual();
    });

    filtroPeriodo?.addEventListener("change", () => {
      estado.periodo = filtroPeriodo.value || "30";

      // O periodo so muda dados quando a metrica atual e evolucao.
      if (estado.metrica === "evolucao") {
        carregarProdutosPainel();
        return;
      }

      definirStatus("Periodo atualizado.", "A evolucao usara esse filtro.");
    });

    botaoAtualizar?.addEventListener("click", () => {
      carregarProdutosPainel(true, { forceRefresh: true });
      carregarEvolucaoAtivos(true);
    });
  }

  function configurarEvolucaoAtivos() {
    const botoesPeriodo = Array.from(
      document.querySelectorAll("[data-stock-period]"),
    );

    if (!botoesPeriodo.length) {
      return;
    }

    botoesPeriodo.forEach((botao) => {
      botao.addEventListener("click", () => {
        definirPeriodoEvolucaoAtivos(botao.dataset.stockPeriod || "semana");
      });
    });

    definirPeriodoEvolucaoAtivos(periodoEvolucaoAtivos, false);
  }

  function definirPeriodoEvolucaoAtivos(periodo, deveRenderizar = true) {
    const periodoSeguro = Object.hasOwn(PERIODOS_EVOLUCAO_ATIVOS, periodo)
      ? periodo
      : "semana";

    periodoEvolucaoAtivos = periodoSeguro;

    document.querySelectorAll("[data-stock-period]").forEach((botao) => {
      const estaAtivo = botao.dataset.stockPeriod === periodoSeguro;

      botao.classList.toggle("is-active", estaAtivo);
      botao.setAttribute("aria-pressed", String(estaAtivo));
    });

    definirTexto(
      "assetEvolutionSubtitle",
      `Acompanhe o acumulado do invent\u00e1rio e os novos cadastros ${PERIODOS_EVOLUCAO_ATIVOS[periodoSeguro].detalhe}.`,
    );

    if (deveRenderizar) {
      renderizarEvolucaoAtivos();
    }
  }

  function definirMetricaPainel(metrica) {
    // Garante que o estado interno e o select continuem sincronizados.
    const metricaProxima = Object.hasOwn(CONFIGURACAO_METRICA, metrica)
      ? metrica
      : "categorias";
    const filtroMetrica = document.getElementById("metricFilter");

    estado.metrica = metricaProxima;

    if (filtroMetrica && filtroMetrica.value !== metricaProxima) {
      filtroMetrica.value = metricaProxima;
    }
  }

  function aplicarSelecaoCategoriaLocal() {
    // Se ainda nao temos a carga completa, deixamos o backend buscar os dados.
    if (!dadosBasePainel.ok) {
      return false;
    }

    if (estado.categoriaId === "todos") {
      // Voltar para "Todos" e instantaneo porque guardamos a resposta completa.
      dadosPainel = dadosBasePainel;
      renderizarGraficoAtual();
      definirStatus("Dados exibidos.", "Filtro removido na tela.");
      return true;
    }

    const categoriaSelecionada = dadosBasePainel.categorias.find(
      (categoria) => categoria.id === estado.categoriaId,
    );

    if (!categoriaSelecionada) {
      return false;
    }

    const marcasCategoria =
      dadosBasePainel.marcas_por_categoria?.[estado.categoriaId] || [];
    const statusPorCategoria =
      dadosBasePainel.status_por_categoria?.[estado.categoriaId] || [];
    const locaisCategoria =
      dadosBasePainel.locais_por_categoria?.[estado.categoriaId] || [];

    dadosPainel = {
      // Preserva os dados gerais, mas troca as listas que dependem do tipo selecionado.
      ...dadosBasePainel,
      resumo: {
        ...dadosBasePainel.resumo,
        total_filtrado: categoriaSelecionada.total,
      },
      categoria_selecionada: {
        id: categoriaSelecionada.id,
        nome: categoriaSelecionada.nome,
        total: categoriaSelecionada.total,
        percentual: categoriaSelecionada.percentual,
      },
      status: statusPorCategoria,
      marcas: marcasCategoria,
      locais: locaisCategoria,
    };

    renderizarGraficoAtual();
    definirStatus("Dados exibidos.", "Filtro aplicado na tela.");

    return true;
  }

  async function carregarProdutosPainel(
    exibirCarregamento = true,
    opcoes = {},
  ) {
    // Cache por categoria e periodo: evita buscar novamente dados que acabaram de ser carregados.
    const chaveCache = `${estado.categoriaId}|${estado.marca}|${estado.localId}|${estado.periodo}`;
    const forcarAtualizacao = Boolean(opcoes.forceRefresh);

    if (forcarAtualizacao) {
      cachePainel.delete(chaveCache);
    }

    if (!forcarAtualizacao && cachePainel.has(chaveCache)) {
      aplicarDadosPainel(cachePainel.get(chaveCache));
      definirStatus("Dados exibidos.", "Usando dados ja carregados.");
      return;
    }

    controladorRequisicaoPainel?.abort();
    controladorRequisicaoPainel = new AbortController();

    // ID incremental evita que uma resposta antiga sobrescreva uma selecao mais recente.
    const idRequisicao = ++idRequisicaoPainel;

    if (exibirCarregamento) {
      definirCarregandoPainel(true);
      definirStatus("Carregando dados...", "Buscando informações no banco.");
    }

    const parametros = new URLSearchParams({
      categoria_id: estado.categoriaId,
      marca: estado.marca,
      local_id: estado.localId,
      periodo: estado.periodo,
    });

    try {
      const resposta = await fetch(
        `${ENDPOINT_PRODUTOS_PAINEL}?${parametros.toString()}`,
        {
          headers: {
            Accept: "application/json",
          },
          signal: controladorRequisicaoPainel.signal,
        },
      );

      if (idRequisicao !== idRequisicaoPainel) {
        return;
      }

      if (resposta.status === 401) {
        // Sessao expirada: manda o usuario para o login com mensagem adequada.
        window.location.href = "Pagina-login.html?sessao=expirada";
        return;
      }

      if (!resposta.ok) {
        throw new Error("Falha ao carregar dashboard de produtos.");
      }

      const dadosResposta = await resposta.json();

      if (idRequisicao !== idRequisicaoPainel) {
        return;
      }

      cachePainel.set(chaveCache, dadosResposta);
      aplicarDadosPainel(dadosResposta);
      definirStatus(
        "Dados sincronizados.",
        formatarUltimaAtualizacao(dadosPainel.gerado_em),
      );
    } catch (erro) {
      if (erro.name === "AbortError") {
        return;
      }

      if (idRequisicao !== idRequisicaoPainel) {
        return;
      }

      console.error(erro);
      dadosPainel = DADOS_PAINEL_PADRAO;
      renderizarGraficoAtual();
      definirStatus(
        "Não foi possível carregar os dados.",
        "Confira a conexão com o banco e tente novamente.",
      );
    } finally {
      if (idRequisicao === idRequisicaoPainel) {
        controladorRequisicaoPainel = null;
        definirCarregandoPainel(false);
      }
    }
  }

  async function carregarEvolucaoAtivos(exibirCarregamento = true) {
    controladorRequisicaoEvolucao?.abort();
    controladorRequisicaoEvolucao = new AbortController();

    const idRequisicao = ++idRequisicaoEvolucao;

    if (exibirCarregamento) {
      definirCarregandoEvolucao(true);
    }

    try {
      const resposta = await fetch(ENDPOINT_METRICAS_GERAIS, {
        headers: {
          Accept: "application/json",
        },
        signal: controladorRequisicaoEvolucao.signal,
      });

      if (idRequisicao !== idRequisicaoEvolucao) {
        return;
      }

      if (resposta.status === 401) {
        window.location.href = "Pagina-login.html?sessao=expirada";
        return;
      }

      if (!resposta.ok) {
        throw new Error("Falha ao carregar evolucao de ativos.");
      }

      const dadosResposta = await resposta.json();
      dadosEvolucaoAtivos = normalizarEvolucoesAtivos(
        dadosResposta?.ativos_evolucao,
        dadosResposta?.estoque_evolucao,
      );
      renderizarEvolucaoAtivos();
    } catch (erro) {
      if (erro.name === "AbortError") {
        return;
      }

      if (idRequisicao !== idRequisicaoEvolucao) {
        return;
      }

      console.error(erro);
      dadosEvolucaoAtivos = normalizarEvolucoesAtivos(null, null);
      renderizarEvolucaoAtivos();
      definirStatus(
        "Nao foi possivel carregar a evolucao.",
        "O grafico principal continua disponivel.",
      );
    } finally {
      if (idRequisicao === idRequisicaoEvolucao) {
        controladorRequisicaoEvolucao = null;
        definirCarregandoEvolucao(false);
      }
    }
  }

  function normalizarEvolucoesAtivos(evolucoes, fallbackSemanal) {
    const origem = evolucoes && typeof evolucoes === "object" ? evolucoes : {};

    return Object.keys(PERIODOS_EVOLUCAO_ATIVOS).reduce(
      (normalizado, periodo) => {
        const linhasPeriodo =
          periodo === "semana" && !Array.isArray(origem[periodo])
            ? fallbackSemanal
            : origem[periodo];

        normalizado[periodo] = normalizarLinhasEvolucaoAtivos(linhasPeriodo);

        return normalizado;
      },
      {},
    );
  }

  function normalizarLinhasEvolucaoAtivos(linhas) {
    if (!Array.isArray(linhas)) {
      return [];
    }

    return linhas.map((linha) => ({
      label: String(linha?.label || linha?.nome || "--"),
      total: normalizarNumero(linha?.total),
      novos: normalizarNumero(linha?.novos ?? linha?.total),
    }));
  }

  function definirCarregandoPainel(estaCarregando) {
    document.body.classList.toggle("dashboard-filtering", estaCarregando);

    const areaPrincipal = document.querySelector(
      ".dashboard-products-page .app-main",
    );
    const cartaoGrafico = document.querySelector(".main-chart-card");
    const botaoAtualizar = document.getElementById("refreshDashboard");

    areaPrincipal?.setAttribute("aria-busy", String(estaCarregando));
    cartaoGrafico?.setAttribute("aria-busy", String(estaCarregando));

    if (botaoAtualizar) {
      botaoAtualizar.disabled = estaCarregando;
    }
  }

  function definirCarregandoEvolucao(estaCarregando) {
    const cartaoEvolucao = document.querySelector(".dashboard-asset-evolution");

    cartaoEvolucao?.setAttribute("aria-busy", String(estaCarregando));
    cartaoEvolucao?.classList.toggle("is-loading", estaCarregando);
  }

  function aplicarDadosPainel(dadosResposta) {
    // Normaliza o JSON antes de qualquer componente tentar usar os dados.
    dadosPainel = normalizarDadosPainel(dadosResposta);
    sincronizarEstadoComDadosPainel();

    if (
      dadosPainel.categoria_filtro === "todos" &&
      dadosPainel.marca_filtro === "todos" &&
      dadosPainel.local_filtro === "todos"
    ) {
      // A resposta geral vira a base para filtros instantaneos por categoria.
      dadosBasePainel = dadosPainel;
    }

    preencherFiltroCategoria(dadosPainel.categorias);
    preencherFiltroMarca(dadosPainel.marcas_filtro);
    preencherFiltroLocal(dadosPainel.locais_filtro);
    renderizarGraficoAtual();
  }

  function normalizarDadosPainel(dadosResposta) {
    // Nunca confiamos totalmente no formato vindo da rede; cada campo recebe fallback.
    const dados =
      dadosResposta && typeof dadosResposta === "object"
        ? dadosResposta
        : DADOS_PAINEL_PADRAO;
    const resumo =
      dados.resumo && typeof dados.resumo === "object"
        ? dados.resumo
        : DADOS_PAINEL_PADRAO.resumo;
    const categoriaSelecionada =
      dados.categoria_selecionada &&
      typeof dados.categoria_selecionada === "object"
        ? dados.categoria_selecionada
        : DADOS_PAINEL_PADRAO.categoria_selecionada;

    return {
      ok: Boolean(dados.ok),
      gerado_em: dados.gerado_em || null,
      periodo: normalizarNumero(dados.periodo) || Number(estado.periodo),
      categoria_filtro: String(
        dados.categoria_filtro || estado.categoriaId || "todos",
      ),
      resumo: {
        total_ativos: normalizarNumero(resumo.total_ativos),
        total_tipos: normalizarNumero(resumo.total_tipos),
        total_filtrado: normalizarNumero(resumo.total_filtrado),
        maior_categoria: resumo.maior_categoria || null,
      },
      categoria_selecionada: {
        id: String(categoriaSelecionada.id || "todos"),
        nome: String(categoriaSelecionada.nome || "Todos os tipos"),
        total: normalizarNumero(categoriaSelecionada.total),
        percentual: normalizarPercentual(categoriaSelecionada.percentual),
      },
      marca_filtro: String(dados.marca_filtro || estado.marca || "todos"),
      local_filtro: String(dados.local_filtro || estado.localId || "todos"),
      categorias: normalizarLinhasDados(dados.categorias, true),
      marcas_filtro: normalizarOpcoesFiltro(dados.marcas_filtro),
      locais_filtro: normalizarOpcoesFiltro(dados.locais_filtro),
      status: normalizarLinhasDados(dados.status),
      status_por_categoria: normalizarLinhasPorCategoria(
        dados.status_por_categoria,
      ),
      marcas: normalizarLinhasDados(dados.marcas),
      marcas_por_categoria: normalizarLinhasPorCategoria(
        dados.marcas_por_categoria,
      ),
      locais: normalizarLinhasDados(dados.locais),
      locais_por_categoria: normalizarLinhasPorCategoria(
        dados.locais_por_categoria,
      ),
      evolucao: normalizarLinhasDados(dados.evolucao),
    };
  }

  function sincronizarEstadoComDadosPainel() {
    estado.categoriaId = dadosPainel.categoria_filtro || "todos";
    estado.marca = dadosPainel.marca_filtro || "todos";
    estado.localId = dadosPainel.local_filtro || "todos";
    estado.periodo = String(dadosPainel.periodo || estado.periodo || "30");
  }

  function normalizarOpcoesFiltro(linhas) {
    if (!Array.isArray(linhas)) {
      return [];
    }

    return linhas
      .map((linha) => ({
        id: String(linha?.id || linha?.nome || "").trim(),
        nome: String(linha?.nome || "Sem nome").trim(),
        total: normalizarNumero(linha?.total),
      }))
      .filter(
        (linha) => linha.id !== "" && linha.nome !== "" && linha.total > 0,
      );
  }

  function normalizarLinhasPorCategoria(grupos) {
    // Transforma objetos de grupos em listas normalizadas, mantendo o id da categoria como chave.
    if (!grupos || typeof grupos !== "object" || Array.isArray(grupos)) {
      return {};
    }

    return Object.entries(grupos).reduce(
      (normalizado, [idCategoria, linhas]) => {
        normalizado[String(idCategoria)] = normalizarLinhasDados(linhas);
        return normalizado;
      },
      {},
    );
  }

  function normalizarLinhasDados(linhas, manterId = false) {
    // Padroniza cada linha usada por graficos, ranking e tabela.
    if (!Array.isArray(linhas)) {
      return [];
    }

    return linhas
      .map((linha) => {
        const item = {
          nome: String(linha?.nome || "Sem nome").trim(),
          total: normalizarNumero(linha?.total),
          percentual: normalizarPercentual(linha?.percentual),
        };

        if (manterId) {
          item.id = String(linha?.id || "");
        }

        return item;
      })
      .filter((linha) => linha.nome !== "" && linha.total !== null);
  }

  function normalizarNumero(valor) {
    const numero = Number(valor);

    return Number.isFinite(numero) && numero >= 0 ? numero : 0;
  }

  function normalizarPercentual(valor) {
    const numero = Number(valor);

    return Number.isFinite(numero) && numero >= 0 ? numero : 0;
  }

  function preencherFiltroCategoria(categorias) {
    // Recria o select de tipos mantendo a escolha atual sempre que ela ainda existe.
    const filtroCategoria = document.getElementById("categoryFilter");

    if (!filtroCategoria) {
      return;
    }

    const valorAnterior = filtroCategoria.value || estado.categoriaId;

    filtroCategoria.innerHTML = "";
    filtroCategoria.appendChild(criarOpcao("todos", "Todos os tipos"));

    categorias.forEach((categoria) => {
      filtroCategoria.appendChild(
        criarOpcao(
          categoria.id,
          `${formatarRotuloCategoria(categoria.nome)} (${formatarNumero(categoria.total)})`,
        ),
      );
    });

    filtroCategoria.value = categorias.some(
      (categoria) => categoria.id === valorAnterior,
    )
      ? valorAnterior
      : "todos";
    estado.categoriaId = filtroCategoria.value;
  }

  function preencherFiltroMarca(marcas) {
    preencherSeletorPainel({
      elementId: "brandFilter",
      defaultValue: "todos",
      defaultLabel: "Todas as marcas",
      selectedValue: estado.marca,
      rows: marcas,
      onUpdate(valor) {
        estado.marca = valor;
      },
    });
  }

  function preencherFiltroLocal(locais) {
    preencherSeletorPainel({
      elementId: "locationFilter",
      defaultValue: "todos",
      defaultLabel: "Todos os locais",
      selectedValue: estado.localId,
      rows: locais,
      onUpdate(valor) {
        estado.localId = valor;
      },
    });
  }

  function preencherSeletorPainel(configuracao) {
    const seletor = document.getElementById(configuracao.elementId);

    if (!seletor) {
      return;
    }

    const valorAnterior =
      seletor.value || configuracao.selectedValue || configuracao.defaultValue;

    seletor.innerHTML = "";
    seletor.appendChild(
      criarOpcao(configuracao.defaultValue, configuracao.defaultLabel),
    );

    configuracao.rows.forEach((linha) => {
      seletor.appendChild(
        criarOpcao(linha.id, `${linha.nome} (${formatarNumero(linha.total)})`),
      );
    });

    seletor.value = configuracao.rows.some(
      (linha) => linha.id === valorAnterior,
    )
      ? valorAnterior
      : configuracao.defaultValue;

    configuracao.onUpdate(seletor.value);
  }

  function criarOpcao(valor, rotulo) {
    const opcao = document.createElement("option");
    opcao.value = valor;
    opcao.textContent = rotulo;

    return opcao;
  }

  function montarRotulosFiltroAtivo(categoriaSelecionada) {
    const rotulos = [];

    if (categoriaSelecionada.id !== "todos") {
      rotulos.push(formatarRotuloCategoria(categoriaSelecionada.nome));
    }

    if (estado.marca !== "todos") {
      rotulos.push(obterRotuloOpcaoSelecionada("brandFilter"));
    }

    if (estado.localId !== "todos") {
      rotulos.push(obterRotuloOpcaoSelecionada("locationFilter"));
    }

    if (!rotulos.length) {
      return {
        title: "Todos",
        detail: "",
      };
    }

    return {
      title: rotulos[0],
      detail: rotulos.join(" em "),
    };
  }

  function obterRotuloOpcaoSelecionada(idSeletor) {
    const seletor = document.getElementById(idSeletor);
    const opcao = seletor?.selectedOptions?.[0];
    const texto = opcao?.textContent || "";

    return texto.replace(/\s+\([0-9.]+\)$/u, "").trim() || "Filtro selecionado";
  }

  function renderizarGraficoAtual() {
    // Um unico fluxo renderiza grafico e ranking para todas as metricas.
    const configuracao =
      CONFIGURACAO_METRICA[estado.metrica] || CONFIGURACAO_METRICA.categorias;
    const linhas = obterLinhasAtuais(configuracao);
    const total = calcularTotalLinhas(linhas);
    const linhasVisiveis = obterLinhasVisiveis(linhas);

    definirTexto("mainChartTitle", configuracao.title);
    definirTexto("mainChartDescription", montarDescricaoGrafico(configuracao));
    definirTexto("chartTotalLabel", configuracao.totalLabel);
    definirTexto("chartTotalMetric", formatarNumero(total));

    renderizarGrafico(linhas, configuracao);
    renderizarRanking(linhasVisiveis, total);
  }

  function obterLinhasAtuais(configuracao) {
    // Evolucao conserva dias zerados no grafico; as demais metricas escondem zeros.
    const linhas = Array.isArray(dadosPainel[configuracao.dataKey])
      ? dadosPainel[configuracao.dataKey]
      : [];

    if (estado.metrica === "evolucao") {
      return linhas;
    }

    return linhas.filter((linha) => linha.total > 0);
  }

  function montarDescricaoGrafico(configuracao) {
    const selecionado =
      dadosPainel.categoria_selecionada ||
      DADOS_PAINEL_PADRAO.categoria_selecionada;
    const filtrosAtivos = montarRotulosFiltroAtivo(selecionado);

    if (!filtrosAtivos.detail) {
      return configuracao.description;
    }

    return `${configuracao.description} Filtro ativo: ${filtrosAtivos.detail}.`;
  }

  function calcularTotalLinhas(linhas) {
    return linhas.reduce(
      (soma, linha) => soma + normalizarNumero(linha.total),
      0,
    );
  }

  function obterLinhasVisiveis(linhas) {
    // Ranking e tabela devem mostrar apenas itens/datas com dados reais.
    return linhas.filter((linha) => normalizarNumero(linha.total) > 0);
  }

  function renderizarGrafico(linhas, configuracao) {
    // Chart.js redesenha do zero para evitar sobras visuais ao trocar filtros ou tipo.
    const telaGrafico = document.getElementById("productsChart");

    if (!telaGrafico || !window.Chart) {
      return;
    }

    if (graficoProdutos) {
      graficoProdutos.destroy();
    }

    const linhasGrafico = linhas.length
      ? linhas
      : [{ nome: "Sem dados", total: 0 }];
    const tipoGrafico = obterTipoGraficoSeguro(
      estado.tipoGrafico,
      estado.metrica,
    );
    const estilos = getComputedStyle(document.body);
    const corTexto = estilos.getPropertyValue("--text").trim() || "#f6fbff";
    const corSuave = estilos.getPropertyValue("--muted").trim() || "#9cb8c9";
    const corGrade =
      estilos.getPropertyValue("--line").trim() || "rgba(255,255,255,.13)";
    const paleta = montarPaletaGrafico(linhasGrafico.length);
    const ehLinha = tipoGrafico === "line";
    const ehCircular = ["pie", "doughnut", "polarArea"].includes(tipoGrafico);

    graficoProdutos = new Chart(telaGrafico, {
      type: tipoGrafico,
      data: {
        labels: linhasGrafico.map((linha) => formatarRotuloLinha(linha.nome)),
        datasets: [
          {
            label: configuracao.title,
            data: linhasGrafico.map((linha) => linha.total),
            backgroundColor: ehLinha ? "rgba(79, 199, 177, 0.18)" : paleta,
            borderColor: ehLinha
              ? estilos.getPropertyValue("--mint").trim() || "#66d5c2"
              : paleta,
            borderWidth: ehLinha ? 3 : 1,
            fill: ehLinha,
            tension: 0.36,
            pointRadius: ehLinha ? 4 : 0,
            pointHoverRadius: ehLinha ? 7 : 0,
            borderRadius: tipoGrafico === "bar" ? 12 : 0,
            hoverOffset: ehCircular ? 8 : 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: movimentoReduzidoEstaAtivado()
          ? false
          : {
              duration: 240,
              easing: "easeOutQuart",
            },
        indexAxis:
          tipoGrafico === "bar" && linhasGrafico.length >= 7 ? "y" : "x",
        plugins: {
          legend: {
            display: ehCircular,
            position: "bottom",
            labels: {
              color: corTexto,
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
              label(contexto) {
                const grafico = contexto.chart;
                const eixoIndice = grafico?.options?.indexAxis;

                let valor = 0;

                if (typeof contexto.raw === "number") {
                  valor = contexto.raw;
                } else if (eixoIndice === "y") {
                  valor = Number(contexto.parsed?.x ?? 0);
                } else {
                  valor = Number(contexto.parsed?.y ?? contexto.parsed ?? 0);
                }

                const dadosElemento = contexto.dataset;
                const dados = Array.isArray(dadosElemento.data)
                  ? dadosElemento.data
                  : [];

                const total = dados.reduce((soma, item) => {
                  if (typeof item === "number") {
                    return soma + item;
                  }

                  return (
                    soma +
                    Number(item?.value ?? item?.total ?? item?.quantidade ?? 0)
                  );
                }, 0);

                const percentual =
                  total > 0 ? ((valor / total) * 100).toFixed(1) : "0.0";

                return `${formatarNumero(valor)} ativos - ${percentual}%`;
              },
            },
          },
        },
        scales: ehCircular
          ? {}
          : {
              x: {
                ticks: {
                  color: corSuave,
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
                  color: corSuave,
                  precision: 0,
                  font: {
                    weight: "700",
                  },
                },
                grid: {
                  color: corGrade,
                },
              },
            },
      },
    });
  }

  function renderizarEvolucaoAtivos() {
    const telaGrafico = document.getElementById("stockEvolutionChart");

    if (!telaGrafico) {
      return;
    }

    const linhas = dadosEvolucaoAtivos[periodoEvolucaoAtivos] || [];
    const linhasGrafico = linhas.length
      ? linhas
      : [{ label: "Sem dados", total: 0, novos: 0 }];

    atualizarResumoEvolucaoAtivos(linhas);

    if (!window.Chart) {
      return;
    }

    if (graficoEvolucaoAtivos) {
      graficoEvolucaoAtivos.destroy();
    }

    const estilos = getComputedStyle(document.body);
    const corTexto = estilos.getPropertyValue("--text").trim() || "#f6fbff";
    const corSuave = estilos.getPropertyValue("--muted").trim() || "#9cb8c9";
    const corGrade =
      estilos.getPropertyValue("--line").trim() || "rgba(255,255,255,.13)";
    const corPrimaria = estilos.getPropertyValue("--mint").trim() || "#66d5c2";
    const corSecundaria =
      estilos.getPropertyValue("--cyan").trim() || "#4aa3c7";

    graficoEvolucaoAtivos = new Chart(telaGrafico, {
      type: "bar",
      data: {
        labels: linhasGrafico.map((linha) => linha.label),
        datasets: [
          {
            type: "line",
            label: "Total acumulado",
            data: linhasGrafico.map((linha) => linha.total),
            borderColor: corPrimaria,
            backgroundColor: "rgba(102, 213, 194, 0.16)",
            borderWidth: 3,
            fill: true,
            tension: 0.36,
            pointRadius: 3,
            pointHoverRadius: 6,
            yAxisID: "total",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: movimentoReduzidoEstaAtivado()
          ? false
          : {
              duration: 260,
              easing: "easeOutQuart",
            },
        interaction: {
          mode: "index",
          intersect: false,
        },
        plugins: {
          legend: {
            display: true,
            position: "bottom",
            labels: {
              color: corTexto,
              boxWidth: 12,
              boxHeight: 12,
              padding: 18,
              font: {
                family: "Inter, system-ui, sans-serif",
                weight: "800",
              },
            },
          },
          tooltip: {
            backgroundColor: "rgba(3, 16, 29, 0.92)",
            borderColor: "rgba(79, 199, 177, 0.28)",
            borderWidth: 1,
            titleColor: "#f8feff",
            bodyColor: "#d9fbf6",
            padding: 14,
            cornerRadius: 14,
            callbacks: {
              label(contexto) {
                const valor = Number(contexto.parsed?.y ?? contexto.raw ?? 0);

                return `${contexto.dataset.label}: ${formatarNumero(valor)}`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: corSuave,
              font: {
                weight: "700",
              },
            },
            grid: {
              display: false,
            },
          },
          total: {
            beginAtZero: true,
            position: "left",
            ticks: {
              color: corSuave,
              precision: 0,
              font: {
                weight: "700",
              },
            },
            grid: {
              color: corGrade,
            },
          },
          novos: {
            beginAtZero: true,
            position: "right",
            ticks: {
              color: corSuave,
              precision: 0,
              font: {
                weight: "700",
              },
            },
            grid: {
              drawOnChartArea: false,
            },
          },
        },
      },
    });
  }

  function atualizarResumoEvolucaoAtivos(linhas) {
    const primeiraLinha = linhas[0] || { total: 0, novos: 0 };
    const ultimaLinha = linhas[linhas.length - 1] || { total: 0 };
    const totalAtual = normalizarNumero(ultimaLinha.total);
    const novosPeriodo = linhas.reduce(
      (soma, linha) => soma + normalizarNumero(linha.novos),
      0,
    );
    const totalAntesPeriodo = Math.max(
      0,
      normalizarNumero(primeiraLinha.total) -
        normalizarNumero(primeiraLinha.novos),
    );
    const crescimentoPeriodo = Math.max(0, totalAtual - totalAntesPeriodo);

    definirTexto("stockPeriodTotal", formatarNumero(totalAtual));
    definirTexto("stockPeriodNew", formatarNumero(novosPeriodo));
    definirTexto("stockPeriodDelta", `+${formatarNumero(crescimentoPeriodo)}`);
  }

  function obterTipoGraficoSeguro(tipoGrafico, metrica) {
    // Protege contra valores inesperados vindos do DOM ou de alteracoes manuais.
    if (["bar", "pie", "doughnut", "line", "polarArea"].includes(tipoGrafico)) {
      return tipoGrafico;
    }

    return "bar";
  }

  function montarPaletaGrafico(tamanho) {
    const estilos = getComputedStyle(document.body);
    const coresBase = [
      estilos.getPropertyValue("--mint").trim() || "#66d5c2",
      estilos.getPropertyValue("--cyan").trim() || "#4aa3c7",
      estilos.getPropertyValue("--teal").trim() || "#4fc7b1",
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
      { length: tamanho },
      (itemIgnorado, indice) => coresBase[indice % coresBase.length],
    );
  }

  function renderizarRanking(linhas, total) {
    // Leitura rapida lateral: mostra os principais itens ja filtrados.
    const conteiner = document.getElementById("dashboardRanking");

    if (!conteiner) {
      return;
    }

    conteiner.innerHTML = "";

    if (!linhas.length || total === 0) {
      conteiner.innerHTML =
        '<div class="ranking-empty">Nenhum dado encontrado para o filtro atual.</div>';
      return;
    }

    linhas.slice(0, 8).forEach((linha, indice) => {
      const percentual = total ? (linha.total / total) * 100 : 0;
      const item = document.createElement("div");
      item.className = "ranking-item";

      item.innerHTML = `
            <div class="ranking-item-head">
                <span>${indice + 1}. ${escaparHtml(formatarRotuloLinha(linha.nome))}</span>
                <strong>${formatarNumero(linha.total)}</strong>
            </div>
            <div class="ranking-progress" aria-hidden="true">
                <span style="width: ${Math.min(percentual, 100)}%"></span>
            </div>
            <small>${formatarPercentual(percentual)} de participação</small>
        `;

      conteiner.appendChild(item);
    });
  }

  function definirStatus(titulo, detalhe) {
    definirTexto("dashboardStatusText", `${titulo} ${detalhe}`.trim());
  }

  function definirTexto(id, valor) {
    const elemento = document.getElementById(id);

    if (elemento) {
      elemento.textContent = valor;
    }
  }

  function formatarRotuloLinha(valor) {
    if (estado.metrica === "categorias") {
      return formatarRotuloCategoria(valor);
    }

    return String(valor || "--");
  }

  function formatarRotuloCategoria(valor) {
    const texto = String(valor || "").trim();

    if (texto === "" || texto === "--") {
      return "--";
    }

    const textoMinusculo = texto.toLocaleLowerCase("pt-BR");

    return (
      textoMinusculo.charAt(0).toLocaleUpperCase("pt-BR") +
      textoMinusculo.slice(1)
    );
  }

  function formatarNumero(valor) {
    return new Intl.NumberFormat("pt-BR").format(normalizarNumero(valor));
  }

  function formatarPercentual(valor) {
    return `${Number(valor || 0).toLocaleString("pt-BR", {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1,
    })}%`;
  }

  function formatarUltimaAtualizacao(valorData) {
    if (!valorData) {
      return "Dados carregados do banco.";
    }

    const data = new Date(valorData);

    if (Number.isNaN(data.getTime())) {
      return "Dados carregados do banco.";
    }

    return `Atualizado em ${data.toLocaleDateString("pt-BR")} às ${data.toLocaleTimeString(
      "pt-BR",
      {
        hour: "2-digit",
        minute: "2-digit",
      },
    )}.`;
  }

  function escaparHtml(valor) {
    return String(valor)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }
})();
