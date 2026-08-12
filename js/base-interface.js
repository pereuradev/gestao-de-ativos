(function () {
// Script base carregado nas paginas internas. Ele concentra preferencias,
// permissoes, tema e pequenos helpers usados por varios modulos.
const TRANSICAO_TEMA_MS = 660;
const OPCOES_TAMANHO_FONTE = {
  small: 15,
  default: 16,
  large: 17,
  extra: 18,
};
const PADROES_PREFERENCIA_USUARIO = {
  theme: "dark",
  accent: "teal",
  fontSize: "default",
  density: "comfortable",
  motion: "normal",
  cursor: "enhanced",
};
const OPCOES_MODO_TEMA = {
  light: { label: "Claro", icon: "bi-sun-fill" },
  dark: { label: "Escuro", icon: "bi-moon-stars-fill" },
  auto: { label: "Sistema", icon: "bi-display" },
};
const CHAVES_ARMAZENAMENTO_PREFERENCIA_USUARIO = {
  theme: "titech-theme",
  accent: "titech-accent",
  fontSize: "titech-font-size",
  density: "titech-density",
  motion: "titech-motion",
  cursor: "titech-cursor",
};
const ENDPOINT_PREFERENCIA_USUARIO = "../Backend/preferencias-usuario.php";
const SELETOR_INTERATIVO_CURSOR_PERSONALIZADO = [
  "a",
  "button",
  "input",
  "label",
  "textarea",
  "select",
  ".form-control",
  ".input-shell",
  ".theme-toggle",
  ".nav-link",
  ".nav-toggle",
  ".sidebar-resize-handle",
  "[role='button']",
  "[role='tab']",
  "[tabindex]:not([tabindex='-1'])",
].join(", ");
const REGRAS_PERMISSAO_PAGINA = {
  "dashboard.php": { permission: "visualizar_dashboard", resource: "Dashboard" },
  "ativos.php": { permission: "visualizar_ativos", resource: "Ativos" },
  "cadastro-ativos.php": { permission: "cadastrar_ativos", resource: "Cadastro de ativos" },
  "edicao-ativos.php": { permission: "editar_ativos", resource: "Edicao de ativos" },
  "categorias-visualizacao.php": { permission: "visualizar_categorias", resource: "Categorias" },
  "categorias.php": { permission: "cadastrar_categorias", resource: "Cadastro de categorias" },
  "edicao-categorias.php": { permission: "editar_categorias", resource: "Edicao de categorias" },
  "marcas-visualizacao.php": { permission: "visualizar_marcas", resource: "Marcas" },
  "marcas.php": { permission: "cadastrar_marcas", resource: "Cadastro de marcas" },
  "edicao-marcas.php": { permission: "editar_marcas", resource: "Edicao de marcas" },
  "propriedades-visualizacao.php": { permission: "visualizar_propriedades", resource: "Propriedades" },
  "propriedades.php": { permission: "cadastrar_propriedades", resource: "Cadastro de propriedades" },
  "edicao-propriedades.php": { permission: "editar_propriedades", resource: "Edicao de propriedades" },
  "locais-visualizacao.php": { permission: "visualizar_locais", resource: "Localizacoes" },
  "locais.php": { permission: "cadastrar_locais", resource: "Cadastro de localizacoes" },
  "edicao-locais.php": { permission: "editar_locais", resource: "Edicao de localizacoes" },
  "funcionarios.php": { permission: "visualizar_funcionarios", resource: "Funcionarios" },
  "grupos-visualizacao.php": { permission: "visualizar_grupos", resource: "Grupos" },
  "cadastro-funcionarios.php": { permission: "cadastrar_funcionarios", resource: "Cadastro de funcionarios" },
  "edicao-funcionarios.php": { permission: "editar_funcionarios", resource: "Edicao de funcionarios" },
  "cadastro-grupos.php": { permission: "cadastrar_grupos", resource: "Cadastro de grupos" },
  "edicao-grupos.php": { permission: "editar_grupos", resource: "Edicao de grupos" },
  "gerenciar-solicitacoes-acesso.php": { permission: "gerenciar_solicitacoes_acesso", resource: "Solicitacoes de acesso" },
};
const ATALHOS_PERMISSAO_DESABILITADOS = {
  Funcionarios: { permission: "visualizar_funcionarios", href: "funcionarios.php" },
  Grupos: { permission: "visualizar_grupos", href: "grupos-visualizacao.php" },
  Categorias: { permission: "visualizar_categorias", href: "categorias-visualizacao.php" },
  "Cadastro de funcionarios": { permission: "cadastrar_funcionarios", href: "cadastro-funcionarios.php" },
  "Cadastro de categorias": { permission: "cadastrar_categorias", href: "categorias.php" },
  "Edicao de funcionarios": { permission: "editar_funcionarios", href: "edicao-funcionarios.php" },
  "Edicao de categorias": { permission: "editar_categorias", href: "edicao-categorias.php" },
  "Cadastro de grupos": { permission: "cadastrar_grupos", href: "cadastro-grupos.php" },
  "Edicao de grupos": { permission: "editar_grupos", href: "edicao-grupos.php" },
  "Solicitacoes de acesso": { permission: "gerenciar_solicitacoes_acesso", href: "gerenciar-solicitacoes-acesso.php" },
};

// Paletas que podem ser escolhidas nas configuracoes do usuario.
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
const PADRAO_DESTAQUE_PERSONALIZADO = TEMAS_DESTAQUE.teal.accent;

let temporizadorTema = null;
let observadorTemaSistemaRegistrado = false;
let cursorPersonalizadoPronto = false;
let elementoCursorPersonalizado = null;
let elementoDialogoPermissao = null;
let focoAnteriorDialogoPermissao = null;

const obterItemSalvo = typeof window.obterItemSalvo === "function" ? window.obterItemSalvo : () => null;
const definirItemSalvo =
  typeof window.definirItemSalvo === "function"
    ? window.definirItemSalvo
    : () => undefined;
const normalizarEscolha =
  typeof window.normalizarEscolha === "function"
    ? window.normalizarEscolha
    : (valor, valoresPermitidos, padrao) => {
        const normalizado = String(valor ?? "").trim();

        return valoresPermitidos.includes(normalizado) ? normalizado : padrao;
      };
const iniciarAnimacaoPagina =
  typeof window.iniciarAnimacaoPagina === "function"
    ? window.iniciarAnimacaoPagina
    : () => {
        requestAnimationFrame(() => {
          document.body.classList.remove("page-loading");
        });
      };
const configurarBarraLateral = typeof window.configurarBarraLateral === "function" ? window.configurarBarraLateral : () => undefined;
const abrirBarraLateral = typeof window.abrirBarraLateral === "function" ? window.abrirBarraLateral : () => undefined;
const fecharBarraLateral = typeof window.fecharBarraLateral === "function" ? window.fecharBarraLateral : () => undefined;
const aplicarLarguraBarraLateral =
  typeof window.aplicarLarguraBarraLateral === "function" ? window.aplicarLarguraBarraLateral : () => undefined;
const aplicarLarguraSalvaBarraLateral =
  typeof window.aplicarLarguraSalvaBarraLateral === "function" ? window.aplicarLarguraSalvaBarraLateral : () => undefined;
const configurarRedimensionamentoBarraLateral =
  typeof window.configurarRedimensionamentoBarraLateral === "function" ? window.configurarRedimensionamentoBarraLateral : () => undefined;
const configurarGruposNavegacao = typeof window.configurarGruposNavegacao === "function" ? window.configurarGruposNavegacao : () => undefined;

document.addEventListener("DOMContentLoaded", () => {
  aplicarPreferenciasUsuario(obterPreferenciasUsuarioAtual());
  configurarAlternadorTema();
  hidratarPerfilBarraLateral();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarAcionadoresPermissaoNegada();
});

function normalizarPreferenciasUsuario(preferencias = {}) {
  // Aceita tanto nomes usados no JavaScript quanto nomes vindos das colunas do banco.
  const origem = preferencias && typeof preferencias === "object" ? preferencias : {};

  return {
    theme: normalizarEscolha(
      origem.theme ?? origem.preferencia_tema,
      ["dark", "light", "auto"],
      PADROES_PREFERENCIA_USUARIO.theme,
    ),
    accent: normalizarPreferenciaDestaque(origem.accent ?? origem.preferencia_cor),
    fontSize: normalizarEscolha(
      origem.fontSize ?? origem.font_size ?? origem.preferencia_tamanho_fonte,
      Object.keys(OPCOES_TAMANHO_FONTE),
      PADROES_PREFERENCIA_USUARIO.fontSize,
    ),
    density: normalizarEscolha(
      origem.density ?? origem.preferencia_densidade,
      ["comfortable", "compact"],
      PADROES_PREFERENCIA_USUARIO.density,
    ),
    motion: normalizarEscolha(
      origem.motion ?? origem.preferencia_movimento,
      ["normal", "reduced"],
      PADROES_PREFERENCIA_USUARIO.motion,
    ),
    cursor: normalizarEscolha(
      origem.cursor ?? origem.preferencia_cursor,
      ["enhanced", "normal"],
      PADROES_PREFERENCIA_USUARIO.cursor,
    ),
  };
}

function normalizarPreferenciaDestaque(valor) {
  const destaque = String(valor ?? "").trim();

  if (Object.hasOwn(TEMAS_DESTAQUE, destaque)) {
    return destaque;
  }

  if (ehCorHexadecimal(destaque)) {
    return destaque.toLowerCase();
  }

  return PADROES_PREFERENCIA_USUARIO.accent;
}

function ehCorHexadecimal(valor) {
  return /^#[0-9a-f]{6}$/i.test(String(valor ?? "").trim());
}

function obterPreferenciasUsuarioServidor() {
  return window.TITECH_USER_PREFERENCES && typeof window.TITECH_USER_PREFERENCES === "object"
    ? window.TITECH_USER_PREFERENCES
    : {};
}

function obterPreferenciasUsuarioArmazenadas() {
  const preferencias = {};

  Object.entries(CHAVES_ARMAZENAMENTO_PREFERENCIA_USUARIO).forEach(([nome, chave]) => {
    const valor = obterItemSalvo(chave);

    if (valor !== null) {
      preferencias[nome] = valor;
    }
  });

  return preferencias;
}

function obterPreferenciasUsuarioAtual() {
  // A sessao PHP tem prioridade para evitar que usuarios diferentes herdem o mesmo navegador.
  return normalizarPreferenciasUsuario({
    ...obterPreferenciasUsuarioArmazenadas(),
    ...obterPreferenciasUsuarioServidor(),
  });
}

function armazenarCachePreferenciasUsuario(preferencias) {
  const normalizado = normalizarPreferenciasUsuario(preferencias);

  window.TITECH_USER_PREFERENCES = normalizado;
  Object.entries(CHAVES_ARMAZENAMENTO_PREFERENCIA_USUARIO).forEach(([nome, chave]) => {
    definirItemSalvo(chave, normalizado[nome]);
  });

  return normalizado;
}

function aplicarPreferenciasUsuario(preferencias) {
  const normalizado = armazenarCachePreferenciasUsuario(preferencias);

  aplicarTema(normalizado.theme);
  aplicarDestaque(normalizado.accent);
  aplicarPreferenciaTamanhoFonte(normalizado.fontSize);
  aplicarDensidade(normalizado.density);
  aplicarPreferenciaMovimento(normalizado.motion);
  aplicarPreferenciaCursor(normalizado.cursor);
  aplicarLarguraSalvaBarraLateral();

  return normalizado;
}

async function salvarPreferenciasUsuario(preferencias) {
  const normalizado = armazenarCachePreferenciasUsuario({
    ...obterPreferenciasUsuarioAtual(),
    ...preferencias,
  });

  try {
    const resposta = await fetch(ENDPOINT_PREFERENCIA_USUARIO, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(normalizado),
    });
    const resultado = await resposta.json().catch(() => null);

    if (!resposta.ok || !resultado?.ok) {
      throw new Error(resultado?.message || "Nao foi possivel salvar as preferencias.");
    }

    return {
      ok: true,
      preferences: armazenarCachePreferenciasUsuario(resultado.preferences || normalizado),
    };
  } catch (erro) {
    return {
      ok: false,
      error: erro,
      preferences: normalizado,
    };
  }
}

async function hidratarPerfilBarraLateral() {
  // A sidebar nasce com dados da sessao PHP, mas este refresh corrige sessoes antigas sem novo login.
  if (!document.querySelector(".sidebar-user-info")) {
    return;
  }

  try {
    const resposta = await fetch("../Backend/usuario-sessao.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json().catch(() => null);

    if (!resposta.ok || !resultado?.ok || !resultado.usuario) {
      return;
    }

    atualizarPerfilBarraLateral(resultado.usuario);
    aplicarPermissoesNavegacao(resultado.usuario);

    if (resultado.usuario.preferencias) {
      aplicarPreferenciasUsuario(resultado.usuario.preferencias);
    }
  } catch {
    return;
  }
}

function atualizarPerfilBarraLateral(usuario) {
  const resumo = document.querySelector(".sidebar-user-info");
  const avatar = document.querySelector(".sidebar-avatar");

  if (!resumo) {
    return;
  }

  const nome = String(usuario.nome_completo || "Usuario").trim() || "Usuario";
  const email = String(usuario.email || "").trim();
  const departamento = String(usuario.departamento || "").trim() || "Sem departamento";
  const elementoNome = resumo.querySelector("strong");
  const textosAuxiliares = resumo.querySelectorAll("small");

  if (avatar) {
    atualizarAvatarBarraLateral(avatar, usuario.foto_cracha_url || "", usuario.iniciais || "");
  }

  if (elementoNome) {
    elementoNome.textContent = nome;
    elementoNome.title = nome;
  }

  if (textosAuxiliares[0]) {
    textosAuxiliares[0].textContent = email || "Email nao informado";
    textosAuxiliares[0].title = email || "Email nao informado";
  }

  if (textosAuxiliares[1]) {
    textosAuxiliares[1].textContent = departamento;
    textosAuxiliares[1].title = departamento;
  }
}

function atualizarAvatarBarraLateral(avatar, urlFoto, iniciais) {
  avatar.classList.toggle("has-photo", Boolean(urlFoto));

  if (urlFoto) {
    avatar.textContent = "";

    const imagem = document.createElement("img");
    imagem.src = urlFoto;
    imagem.alt = "";
    avatar.appendChild(imagem);
    return;
  }

  if (iniciais) {
    avatar.textContent = iniciais;
  }
}

function aplicarPermissoesNavegacao(usuario) {
  const permissoes = new Set(Array.isArray(usuario?.permissoes) ? usuario.permissoes : []);

  if (usuario?.is_admin) {
    garantirAtalhoNavegacaoVisualizacaoGrupo(permissoes);
    return;
  }

  habilitarAtalhosPermitidosDesabilitados(permissoes);
  garantirAtalhoNavegacaoVisualizacaoGrupo(permissoes);

  document.querySelectorAll(".sidebar-nav a[href]").forEach((atalho) => {
    const regra = REGRAS_PERMISSAO_PAGINA[obterNomePaginaPeloEndereco(atalho.getAttribute("href"))];

    if (!regra || regraPermissaoEhPermitida(permissoes, regra)) {
      return;
    }

    desabilitarAtalhoNavegacao(atalho, regra.resource);
  });

  configurarAcionadoresPermissaoNegada();
}

function habilitarAtalhosPermitidosDesabilitados(permissoes) {
  document.querySelectorAll(".nav-link-disabled, .disabled-action").forEach((item) => {
    const regra = ATALHOS_PERMISSAO_DESABILITADOS[item.dataset.permissionResource];

    if (!regra || !regraPermissaoEhPermitida(permissoes, regra)) {
      return;
    }

    const atalho = document.createElement("a");
    atalho.href = regra.href;
    atalho.className = item.className;
    atalho.classList.remove("nav-link-disabled", "disabled-action");

    if (item.classList.contains("nav-submenu-disabled")) {
      atalho.classList.remove("nav-submenu-disabled");
    }

    atalho.innerHTML = item.innerHTML;
    item.replaceWith(atalho);
  });
}

function garantirAtalhoNavegacaoVisualizacaoGrupo(permissoes) {
  if (!permissoes.has("visualizar_grupos") || document.querySelector('.sidebar-nav a[href="grupos-visualizacao.php"]')) {
    return;
  }

  const navegacaoBarraLateral = document.querySelector(".sidebar-nav");
  const referencia = document.querySelector('.sidebar-nav a[href="funcionarios.php"], .sidebar-nav [data-permission-resource="Funcionarios"]')
    || document.querySelector('.sidebar-nav a[href="dashboard.php"]');

  if (!navegacaoBarraLateral || !referencia) {
    return;
  }

  const atalho = document.createElement("a");
  atalho.className = "nav-link";
  atalho.href = "grupos-visualizacao.php";

  if (obterNomePaginaPeloEndereco(window.location.href) === "grupos-visualizacao.php") {
    atalho.classList.add("active");
  }

  atalho.innerHTML = '<i class="bi bi-collection-fill"></i><span>Grupos</span>';
  referencia.insertAdjacentElement("afterend", atalho);
}

function regraPermissaoEhPermitida(permissoes, regra) {
  const permissoesObrigatorias = Array.isArray(regra.permissions)
    ? regra.permissions
    : [regra.permission].filter(Boolean);

  return permissoesObrigatorias.some((permissao) => permissoes.has(permissao));
}

function obterNomePaginaPeloEndereco(enderecoDestino) {
  try {
    const url = new URL(enderecoDestino || "", window.location.href);
    const partes = url.pathname.split("/").filter(Boolean);

    return (partes.pop() || "").toLowerCase();
  } catch {
    return String(enderecoDestino || "").split("?")[0].split("/").pop().toLowerCase();
  }
}

function desabilitarAtalhoNavegacao(atalho, recurso) {
  const desativado = document.createElement("span");
  const ehNivelSuperior = atalho.classList.contains("nav-link");

  desativado.className = atalho.className || "nav-submenu-disabled";
  desativado.classList.remove("active", "active-submenu");
  desativado.classList.add("nav-link-disabled");

  if (!ehNivelSuperior) {
    desativado.classList.add("nav-submenu-disabled");
  }

  desativado.innerHTML = atalho.innerHTML;
  desativado.setAttribute("aria-disabled", "true");
  desativado.setAttribute("data-permission-resource", recurso);
  desativado.setAttribute("title", `Voce nao tem permissao para acessar ${recurso}`);
  atalho.replaceWith(desativado);
}

function atualizarLogoMarca(ehEscuro) {
  // Troca o logo para manter contraste correto no modo claro e escuro.
  document.querySelectorAll(".brand-logo").forEach((logo) => {
    logo.src = ehEscuro ? "../assets/logo-branca.png" : "../assets/Logo.png";
  });
}

function carregarTemaSalvo() {
  // Restaura tema e preferencias antes de configurar os controles da pagina.
  aplicarPreferenciasUsuario(obterPreferenciasUsuarioAtual());
  configurarObservadorTemaSistema();
}

function configurarAlternadorTema() {
  const alternadorTema = document.getElementById("themeToggle");

  if (!alternadorTema) return;
  if (alternadorTema.dataset.themeMenuReady === "true") return;

  const seletor = criarSeletorTema(alternadorTema);
  const menuTema = seletor.querySelector(".theme-menu");

  alternadorTema.dataset.themeMenuReady = "true";
  alternadorTema.classList.add("theme-toggle-menu-button");
  alternadorTema.setAttribute("aria-haspopup", "menu");
  alternadorTema.setAttribute("aria-expanded", "false");
  alternadorTema.setAttribute("aria-label", "Selecionar tema da interface");
  garantirIndicadorAlternadorTema(alternadorTema);

  alternadorTema.addEventListener("click", (evento) => {
    evento.stopPropagation();
    alternarMenuTema(seletor);
  });

  alternadorTema.addEventListener("keydown", (evento) => {
    if (evento.key === "ArrowDown" || evento.key === "ArrowUp") {
      evento.preventDefault();
      abrirMenuTema(seletor);
      focarOpcaoTemaAtivo(menuTema);
    }

    if (evento.key === "Escape") {
      fecharMenuTema(seletor);
    }
  });

  menuTema.querySelectorAll("[data-theme-value]").forEach((opcao) => {
    opcao.addEventListener("click", () => {
      fecharMenuTema(seletor);
      selecionarPreferenciaTema(opcao.dataset.themeValue);
      alternadorTema.focus();
    });
  });

  menuTema.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape") {
      evento.preventDefault();
      fecharMenuTema(seletor);
      alternadorTema.focus();
    }

    if (evento.key === "ArrowDown" || evento.key === "ArrowUp") {
      evento.preventDefault();
      focarOpcaoTemaAdjacente(menuTema, evento.key === "ArrowDown" ? 1 : -1);
    }
  });

  document.addEventListener("click", (evento) => {
    if (!seletor.contains(evento.target)) {
      fecharMenuTema(seletor);
    }
  });

  atualizarEstadoMenuTema(obterPreferenciasUsuarioAtual().theme);
}

function criarSeletorTema(alternadorTema) {
  const seletorExistente = alternadorTema.closest(".theme-picker");

  if (seletorExistente) {
    if (!seletorExistente.querySelector(".theme-menu")) {
      seletorExistente.appendChild(criarMenuTema());
    }

    return seletorExistente;
  }

  const seletor = document.createElement("div");
  seletor.className = "theme-picker";

  alternadorTema.parentNode.insertBefore(seletor, alternadorTema);
  seletor.appendChild(alternadorTema);
  seletor.appendChild(criarMenuTema());

  return seletor;
}

function criarMenuTema() {
  const menu = document.createElement("div");

  menu.className = "theme-menu";
  menu.setAttribute("role", "menu");
  menu.setAttribute("aria-label", "Opcoes de tema");
  menu.hidden = true;

  Object.entries(OPCOES_MODO_TEMA).forEach(([valor, opcao]) => {
    const botao = document.createElement("button");
    const icone = document.createElement("i");
    const rotulo = document.createElement("span");
    const verificacao = document.createElement("i");

    botao.type = "button";
    botao.className = "theme-menu-option";
    botao.dataset.themeValue = valor;
    botao.setAttribute("role", "menuitemradio");
    botao.setAttribute("aria-checked", "false");

    icone.className = `bi ${opcao.icon}`;
    icone.setAttribute("aria-hidden", "true");

    rotulo.textContent = opcao.label;

    verificacao.className = "bi bi-check-lg theme-menu-check";
    verificacao.setAttribute("aria-hidden", "true");

    botao.append(icone, rotulo, verificacao);
    menu.appendChild(botao);
  });

  return menu;
}

function garantirIndicadorAlternadorTema(alternadorTema) {
  if (alternadorTema.querySelector(".theme-toggle-caret")) return;

  const indicadorSeta = document.createElement("i");
  indicadorSeta.className = "bi bi-chevron-down theme-toggle-caret";
  indicadorSeta.setAttribute("aria-hidden", "true");
  alternadorTema.appendChild(indicadorSeta);
}

function alternarMenuTema(seletor) {
  const estaAberto = seletor.classList.contains("is-open");

  if (estaAberto) {
    fecharMenuTema(seletor);
    return;
  }

  abrirMenuTema(seletor);
}

function abrirMenuTema(seletor) {
  const alternadorTema = seletor.querySelector("#themeToggle");
  const menuTema = seletor.querySelector(".theme-menu");

  seletor.classList.add("is-open");
  menuTema.hidden = false;
  alternadorTema.setAttribute("aria-expanded", "true");
  atualizarEstadoMenuTema(obterPreferenciasUsuarioAtual().theme);
}

function fecharMenuTema(seletor) {
  const alternadorTema = seletor.querySelector("#themeToggle");
  const menuTema = seletor.querySelector(".theme-menu");

  seletor.classList.remove("is-open");
  menuTema.hidden = true;
  alternadorTema.setAttribute("aria-expanded", "false");
}

function selecionarPreferenciaTema(tema) {
  const temaProximo = normalizarEscolha(tema, Object.keys(OPCOES_MODO_TEMA), PADROES_PREFERENCIA_USUARIO.theme);

  clearTimeout(temporizadorTema);
  document.body.classList.add("theme-switching");
  aplicarTema(temaProximo);
  void salvarPreferenciasUsuario({ theme: temaProximo });
  notificarAlteracaoTema(temaProximo);

  temporizadorTema = setTimeout(() => {
    document.body.classList.remove("theme-switching");
  }, TRANSICAO_TEMA_MS);
}

function focarOpcaoTemaAdjacente(menuTema, direcao) {
  const opcoes = Array.from(menuTema.querySelectorAll(".theme-menu-option"));
  const indiceAtual = opcoes.indexOf(document.activeElement);
  const indiceProximo = indiceAtual === -1
    ? 0
    : (indiceAtual + direcao + opcoes.length) % opcoes.length;

  opcoes[indiceProximo]?.focus();
}

function focarOpcaoTemaAtivo(menuTema) {
  const opcaoAtiva = menuTema.querySelector(".theme-menu-option.active")
    || menuTema.querySelector(".theme-menu-option");

  opcaoAtiva?.focus();
}

function aplicarTema(tema) {
  // Aceita dark, light ou auto. Qualquer outro valor volta para dark.
  const alternadorTema = document.getElementById("themeToggle");
  const temaProximo = ["dark", "light", "auto"].includes(tema) ? tema : "dark";
  const ehEscuro = resolverModoTema(temaProximo) === "dark";
  const opcaoTema = OPCOES_MODO_TEMA[temaProximo];

  document.body.classList.toggle("theme-dark", ehEscuro);
  document.body.classList.toggle("theme-light", !ehEscuro);
  document.body.dataset.themePreference = temaProximo;
  atualizarLogoMarca(ehEscuro);

  if (!alternadorTema) return;

  const icone = alternadorTema.querySelector("i");
  const rotulo = alternadorTema.querySelector("span");

  if (icone) {
    icone.className = `bi ${opcaoTema.icon}`;
  }

  if (rotulo) {
    rotulo.textContent = opcaoTema.label;
  }

  alternadorTema.title = `Tema: ${opcaoTema.label}`;
  atualizarEstadoMenuTema(temaProximo);
}

function atualizarEstadoMenuTema(tema) {
  const temaAtivo = normalizarEscolha(tema, Object.keys(OPCOES_MODO_TEMA), PADROES_PREFERENCIA_USUARIO.theme);

  document.querySelectorAll(".theme-menu-option").forEach((opcao) => {
    const ehAtivo = opcao.dataset.themeValue === temaAtivo;

    opcao.classList.toggle("active", ehAtivo);
    opcao.setAttribute("aria-checked", String(ehAtivo));
  });
}

function notificarAlteracaoTema(tema) {
  const temaResolvido = resolverModoTema(tema);

  window.dispatchEvent(new CustomEvent("titech:theme-change", {
    detail: { theme: tema, resolvedTheme: temaResolvido },
  }));

  if (typeof window.onThemeChanged === "function") {
    window.onThemeChanged(tema);
  }
}

function resolverModoTema(tema) {
  // No modo auto, o navegador informa se o sistema prefere tema claro.
  if (tema !== "auto") {
    return tema === "light" ? "light" : "dark";
  }

  return window.matchMedia?.("(prefers-color-scheme: light)")?.matches ? "light" : "dark";
}

function configurarObservadorTemaSistema() {
  // Quando o usuario escolhe auto, reagimos se o tema do sistema mudar.
  if (observadorTemaSistemaRegistrado || !window.matchMedia) return;

  const consultaMidia = window.matchMedia("(prefers-color-scheme: light)");
  const atualizarTemaAutomatico = () => {
    if (obterPreferenciasUsuarioAtual().theme === "auto") {
      aplicarTema("auto");
      notificarAlteracaoTema("auto");
    }
  };

  consultaMidia.addEventListener?.("change", atualizarTemaAutomatico);
  observadorTemaSistemaRegistrado = true;
}

function carregarPreferenciasInterface() {
  // Preferencias salvas deixam as paginas com a mesma aparencia escolhida pelo usuario.
  const preferencias = obterPreferenciasUsuarioAtual();

  aplicarDestaque(preferencias.accent);
  aplicarPreferenciaTamanhoFonte(preferencias.fontSize);
  aplicarDensidade(preferencias.density);
  aplicarPreferenciaMovimento(preferencias.motion);
  aplicarPreferenciaCursor(preferencias.cursor);
  aplicarLarguraSalvaBarraLateral();
}

function aplicarDestaque(destaque) {
  // Atualiza variaveis CSS globais para botoes, graficos e detalhes visuais.
  const destaqueProximo = normalizarPreferenciaDestaque(destaque);
  const paleta = obterPaletaDestaque(destaqueProximo);

  document.body.dataset.accent = ehCorHexadecimal(destaqueProximo) ? "custom" : destaqueProximo;
  document.body.style.setProperty("--cyan", paleta.cyan);
  document.body.style.setProperty("--teal", paleta.teal);
  document.body.style.setProperty("--mint", paleta.mint);
  document.body.style.setProperty("--accent", paleta.accent);
  document.body.style.setProperty("--accent-strong", obterCorForteDestaque(paleta.accent));
  document.body.style.setProperty("--accent-contrast", obterCorContrasteDestaque(paleta.accent));

  window.dispatchEvent(new CustomEvent("titech:accent-change", {
    detail: { accent: destaqueProximo, palette: paleta },
  }));
}

function obterPaletaDestaque(destaque) {
  if (Object.hasOwn(TEMAS_DESTAQUE, destaque)) {
    return TEMAS_DESTAQUE[destaque];
  }

  const rgb = converterHexParaRgb(ehCorHexadecimal(destaque) ? destaque : PADRAO_DESTAQUE_PERSONALIZADO);

  return {
    cyan: converterRgbParaHex(misturarRgb(rgb, converterHexParaRgb("#ffffff"), 0.28)),
    teal: converterRgbParaHex(misturarRgb(rgb, converterHexParaRgb("#03101d"), 0.22)),
    mint: converterRgbParaHex(misturarRgb(rgb, converterHexParaRgb("#ffffff"), 0.42)),
    accent: converterRgbParaHex(rgb),
  };
}

// Cores muito claras ou escuras precisam de apoio para continuarem legíveis nos dois temas.
function obterCorForteDestaque(destaque) {
  const rgb = converterHexParaRgb(destaque);
  const luminancia = obterLuminanciaRelativa(rgb);

  if (luminancia > 0.62) {
    return converterRgbParaHex(misturarRgb(rgb, converterHexParaRgb("#0a253c"), 0.46));
  }

  if (luminancia < 0.1) {
    return converterRgbParaHex(misturarRgb(rgb, converterHexParaRgb("#ffffff"), 0.35));
  }

  return converterRgbParaHex(rgb);
}

  function obterCorContrasteDestaque(destaque) {
    return obterLuminanciaRelativa(converterHexParaRgb(destaque)) > 0.18 ? "#05291f" : "#ffffff";
  }

function obterLuminanciaRelativa({ r, g, b }) {
  const [vermelho, verde, azul] = [r, g, b].map((canal) => {
    const normalizado = canal / 255;

    return normalizado <= 0.04045
      ? normalizado / 12.92
      : ((normalizado + 0.055) / 1.055) ** 2.4;
  });

  return (0.2126 * vermelho) + (0.7152 * verde) + (0.0722 * azul);
}

function converterHexParaRgb(hex) {
  const normalizado = String(hex).replace("#", "");

  return {
    r: parseInt(normalizado.slice(0, 2), 16),
    g: parseInt(normalizado.slice(2, 4), 16),
    b: parseInt(normalizado.slice(4, 6), 16),
  };
}

function misturarRgb(base, destino, pesoDestino) {
  const pesoBase = 1 - pesoDestino;

  return {
    r: Math.round(base.r * pesoBase + destino.r * pesoDestino),
    g: Math.round(base.g * pesoBase + destino.g * pesoDestino),
    b: Math.round(base.b * pesoBase + destino.b * pesoDestino),
  };
}

function converterRgbParaHex(rgb) {
  return `#${[rgb.r, rgb.g, rgb.b]
    .map((canal) => Math.max(0, Math.min(255, canal)).toString(16).padStart(2, "0"))
    .join("")}`;
}

function aplicarDensidade(densidade) {
  // Densidade compacta reduz espacamentos sem criar outro CSS completo.
  document.body.dataset.density = densidade === "compact" ? "compact" : "comfortable";
}

function aplicarPreferenciaTamanhoFonte(tamanho) {
  // A escala fica no html para que todos os textos em rem acompanhem a preferencia.
  const tamanhoProximo = Object.hasOwn(OPCOES_TAMANHO_FONTE, tamanho) ? tamanho : "default";

  document.documentElement.dataset.fontSize = tamanhoProximo;
  document.documentElement.style.fontSize = `${OPCOES_TAMANHO_FONTE[tamanhoProximo]}px`;

  window.dispatchEvent(new CustomEvent("titech:font-size-change", {
    detail: { size: tamanhoProximo, pixels: OPCOES_TAMANHO_FONTE[tamanhoProximo] },
  }));
}

function aplicarPreferenciaMovimento(movimento) {
  // Preferencia de movimento reduz animacoes para quem precisa de menos movimento.
  const movimentoProximo = movimento === "reduced" ? "reduced" : "normal";

  document.body.dataset.motion = movimentoProximo;

  window.dispatchEvent(new CustomEvent("titech:motion-change", {
    detail: { motion: movimentoProximo },
  }));
}

function aplicarPreferenciaCursor(cursor) {
  // O cursor personalizado segue o estilo do login e pode ser desligado nas configuracoes.
  const estaAprimorado = cursor === "enhanced";

  if (!document.body) {
    return;
  }

  document.body.dataset.cursor = estaAprimorado ? "enhanced" : "normal";
  definirCursorPersonalizadoAtivado(estaAprimorado);
}

function definirCursorPersonalizadoAtivado(ehAtivado) {
  const deveHabilitar = Boolean(ehAtivado && cursorPersonalizadoEhCompativel());

  if (deveHabilitar) {
    configurarCursorPersonalizado();
  }

  document.documentElement.classList.toggle("custom-cursor-enabled", deveHabilitar);
  document.body.classList.toggle("custom-cursor-enabled", deveHabilitar);

  if (!deveHabilitar) {
    document.body.classList.remove("cursor-visible", "cursor-hover", "cursor-click");
  }
}

function configurarCursorPersonalizado() {
  if (cursorPersonalizadoPronto || !document.body || !cursorPersonalizadoEhCompativel()) {
    return;
  }

  elementoCursorPersonalizado = document.querySelector(".custom-cursor");

  if (!elementoCursorPersonalizado) {
    elementoCursorPersonalizado = document.createElement("div");
    elementoCursorPersonalizado.className = "custom-cursor";
    elementoCursorPersonalizado.setAttribute("aria-hidden", "true");
    document.body.appendChild(elementoCursorPersonalizado);
  }

  cursorPersonalizadoPronto = true;

  window.addEventListener("mousemove", atualizarPosicaoCursorPersonalizado, { passive: true });
  window.addEventListener("mouseleave", ocultarCursorPersonalizado);
  window.addEventListener("blur", ocultarCursorPersonalizado);
  window.addEventListener("mousedown", pressionarCursorPersonalizado);
  window.addEventListener("mouseup", liberarCursorPersonalizado);
  document.addEventListener("mouseover", atualizarSobreElementoCursorPersonalizado);
  document.addEventListener("mouseout", limparSobreElementoCursorPersonalizado);
}

function atualizarPosicaoCursorPersonalizado(evento) {
  if (!cursorPersonalizadoEstaAtivo() || !elementoCursorPersonalizado) {
    return;
  }

  document.body.classList.add("cursor-visible");
  elementoCursorPersonalizado.style.left = `${evento.clientX}px`;
  elementoCursorPersonalizado.style.top = `${evento.clientY}px`;
}

function ocultarCursorPersonalizado() {
  document.body.classList.remove("cursor-visible", "cursor-hover", "cursor-click");
}

function pressionarCursorPersonalizado() {
  if (cursorPersonalizadoEstaAtivo()) {
    document.body.classList.add("cursor-click");
  }
}

function liberarCursorPersonalizado() {
  document.body.classList.remove("cursor-click");
}

function atualizarSobreElementoCursorPersonalizado(evento) {
  if (!cursorPersonalizadoEstaAtivo()) {
    return;
  }

  if (evento.target instanceof Element && evento.target.closest(SELETOR_INTERATIVO_CURSOR_PERSONALIZADO)) {
    document.body.classList.add("cursor-hover");
  }
}

function limparSobreElementoCursorPersonalizado(evento) {
  if (!cursorPersonalizadoEstaAtivo()) {
    return;
  }

  if (evento.target instanceof Element && evento.target.closest(SELETOR_INTERATIVO_CURSOR_PERSONALIZADO)) {
    document.body.classList.remove("cursor-hover");
  }
}

function cursorPersonalizadoEstaAtivo() {
  return document.documentElement.classList.contains("custom-cursor-enabled");
}

function cursorPersonalizadoEhCompativel() {
  if (!window.matchMedia) {
    return true;
  }

  return window.matchMedia("(pointer: fine)").matches && !window.matchMedia("(hover: none)").matches;
}

function configurarAcionadoresPermissaoNegada() {
  const itensRestritos = Array.from(document.querySelectorAll(".nav-link-disabled, .disabled-action"));

  itensRestritos.forEach((item) => {
    configurarAcionadorPermissaoNegada(item);
  });

  if (document.body.dataset.permissionDialogOpen === "true") {
    abrirDialogoPermissaoNegada(document.body);
  }
}

function configurarAcionadorPermissaoNegada(item) {
  if (item.dataset.permissionTriggerReady === "true") {
    return;
  }

  item.dataset.permissionTriggerReady = "true";
  item.setAttribute("role", "button");
  item.setAttribute("tabindex", "0");

  item.addEventListener("click", (evento) => {
    evento.preventDefault();
    abrirDialogoPermissaoNegada(item);
  });

  item.addEventListener("keydown", (evento) => {
    if (evento.key !== "Enter" && evento.key !== " ") return;

    evento.preventDefault();
    abrirDialogoPermissaoNegada(item);
  });
}

function abrirDialogoPermissaoNegada(origem) {
  document.getElementById("uxToastRegion")?.remove();
  document.getElementById("settingsToast")?.classList.remove("show");

  const evento = new CustomEvent("titech:permission-denied", {
    cancelable: true,
    detail: {
      resource: origem?.dataset?.permissionResource || document.body.dataset.permissionResource || "esta area",
    },
  });

  window.dispatchEvent(evento);

  if (!evento.defaultPrevented) {
    exibirDialogoPermissaoNegada(evento.detail.resource);
  }
}

function exibirDialogoPermissaoNegada(recurso = "esta area") {
  fecharDialogoPermissaoNegada();

  focoAnteriorDialogoPermissao = document.activeElement;
  elementoDialogoPermissao = document.createElement("div");
  elementoDialogoPermissao.className = "permission-dialog-layer";
  elementoDialogoPermissao.setAttribute("role", "presentation");
  elementoDialogoPermissao.innerHTML = `
    <div class="permission-dialog-backdrop" data-permission-close></div>
    <section class="permission-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="permissionDialogTitle" aria-describedby="permissionDialogDescription">
      <div class="permission-dialog-icon" aria-hidden="true">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <p class="section-tag">Permissao necessaria</p>
      <h2 id="permissionDialogTitle">Acesso restrito</h2>
      <p id="permissionDialogDescription">Voce nao tem permissao para acessar ${escaparHtml(recurso)}. Solicite liberacao a um administrador para continuar.</p>
      <button type="button" class="primary-button permission-dialog-close" data-permission-close>
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
        <span>Entendi</span>
      </button>
    </section>
  `;

  elementoDialogoPermissao.addEventListener("click", (evento) => {
    if (evento.target?.closest?.("[data-permission-close]")) {
      fecharDialogoPermissaoNegada();
    }
  });

  document.addEventListener("keydown", tratarTeclaDialogoPermissao);
  document.body.append(elementoDialogoPermissao);
  elementoDialogoPermissao.querySelector(".permission-dialog-close")?.focus();
}

function fecharDialogoPermissaoNegada() {
  if (!elementoDialogoPermissao) return;

  elementoDialogoPermissao.remove();
  elementoDialogoPermissao = null;
  document.removeEventListener("keydown", tratarTeclaDialogoPermissao);
  focoAnteriorDialogoPermissao?.focus?.();
  focoAnteriorDialogoPermissao = null;
}

function tratarTeclaDialogoPermissao(evento) {
  if (evento.key === "Escape") {
    evento.preventDefault();
    fecharDialogoPermissaoNegada();
  }
}

function escaparHtml(valor) {
  const texto = document.createElement("span");

  texto.textContent = String(valor || "");

  return texto.innerHTML;
}

function definirValorCampoEntrada(id, valor) {
  // Helper pequeno para preencher inputs por id sem repetir verificacao de null.
  const elemento = document.getElementById(id);

  if (elemento) {
    elemento.value = valor;
  }
}

function definirTexto(elemento, texto) {
  // Atualiza texto quando o elemento existe.
  if (elemento) {
    elemento.textContent = texto;
  }
}

function atualizarTexto(id, texto) {
  // Versao por id do setText, usada em paginas que atualizam contadores.
  definirTexto(document.getElementById(id), texto);
}

function normalizarTexto(valor) {
  // Remove acentos e padroniza caixa para buscas locais.
  return String(valor || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

Object.assign(window, {
  // API atual em português usada pelos scripts de cada página.
  obterItemSalvo,
  definirItemSalvo,
  normalizarPreferenciasUsuario,
  obterPreferenciasUsuarioAtual,
  armazenarCachePreferenciasUsuario,
  aplicarPreferenciasUsuario,
  salvarPreferenciasUsuario,
  atualizarLogoMarca,
  iniciarAnimacaoPagina,
  carregarTemaSalvo,
  configurarAlternadorTema,
  aplicarTema,
  resolverModoTema,
  carregarPreferenciasInterface,
  aplicarDestaque,
  aplicarPreferenciaTamanhoFonte,
  aplicarDensidade,
  aplicarPreferenciaMovimento,
  aplicarPreferenciaCursor,
  configurarCursorPersonalizado,
  configurarBarraLateral,
  abrirBarraLateral,
  fecharBarraLateral,
  aplicarLarguraBarraLateral,
  aplicarLarguraSalvaBarraLateral,
  configurarRedimensionamentoBarraLateral,
  configurarGruposNavegacao,
  configurarAcionadoresPermissaoNegada,
  abrirDialogoPermissaoNegada,
  exibirDialogoPermissaoNegada,
  fecharDialogoPermissaoNegada,
  definirValorCampoEntrada,
  definirTexto,
  atualizarTexto,
  normalizarTexto,
  // Os aliases antigos preservam páginas que ainda não foram migradas.
  getSavedItem: obterItemSalvo,
  setSavedItem: definirItemSalvo,
  normalizeUserPreferences: normalizarPreferenciasUsuario,
  getCurrentUserPreferences: obterPreferenciasUsuarioAtual,
  cacheUserPreferences: armazenarCachePreferenciasUsuario,
  applyUserPreferences: aplicarPreferenciasUsuario,
  saveUserPreferences: salvarPreferenciasUsuario,
  updateBrandLogo: atualizarLogoMarca,
  startPageAnimation: iniciarAnimacaoPagina,
  loadSavedTheme: carregarTemaSalvo,
  setupThemeToggle: configurarAlternadorTema,
  applyTheme: aplicarTema,
  resolveThemeMode: resolverModoTema,
  loadInterfacePreferences: carregarPreferenciasInterface,
  applyAccent: aplicarDestaque,
  applyFontSizePreference: aplicarPreferenciaTamanhoFonte,
  applyDensity: aplicarDensidade,
  applyMotionPreference: aplicarPreferenciaMovimento,
  applyCursorPreference: aplicarPreferenciaCursor,
  setupCustomCursor: configurarCursorPersonalizado,
  setupSidebar: configurarBarraLateral,
  openSidebar: abrirBarraLateral,
  closeSidebar: fecharBarraLateral,
  applySidebarWidth: aplicarLarguraBarraLateral,
  applySavedSidebarWidth: aplicarLarguraSalvaBarraLateral,
  setupSidebarResize: configurarRedimensionamentoBarraLateral,
  setupNavGroups: configurarGruposNavegacao,
  setupPermissionDeniedTriggers: configurarAcionadoresPermissaoNegada,
  openPermissionDeniedDialog: abrirDialogoPermissaoNegada,
  showPermissionDeniedDialog: exibirDialogoPermissaoNegada,
  closePermissionDeniedDialog: fecharDialogoPermissaoNegada,
  setInputValue: definirValorCampoEntrada,
  setText: definirTexto,
  updateText: atualizarTexto,
  normalizeText: normalizarTexto,
});
})();
