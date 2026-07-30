// Controla autenticação, tema, perfil lembrado e estados acessíveis da página de login.
// Dados sensíveis não são persistidos; o armazenamento local guarda apenas preferências de interface.

const estado = {
  role: "Colaborador",
};

const CHAVES_ARMAZENAMENTO = {
  theme: "titech-theme",
  accent: "titech-accent",
  fontSize: "titech-font-size",
  density: "titech-density",
  motion: "titech-motion",
  cursor: "titech-cursor",
  email: "titech-email",
  profile: "titech-profile",
};

const CONFIGURACAO = {
  loginUrl: "../Backend/login-usuario.php",
  redirectUrl: "pagina-inicial.php",
  inactiveAccountMessage:
    "Sua conta est\u00e1 inativa. Solicite ajuda a um administrador para reativar o acesso.",
  pageTransitionDelay: 520,
  themeTransitionDelay: 560,
  toastDuration: 2200,
  toastRemoveDelay: 250,
  redirectDelay: 450,
  invalidCredentialsMessage: "Credenciais invalidas. Confira e-mail, senha e perfil selecionado.",
  serverUnavailableMessage: "Servidor indisponivel. Tente novamente em instantes.",
};

const CONTEUDO_TIPO_USUARIO = {
  Administrador: {
    badge: "Controle total do ambiente",
    title: "Acesso administrativo",
    description:
      "Gerencie usu\u00e1rios, ativos, permiss\u00f5es e configura\u00e7\u00f5es internas do sistema.",
  },
  Colaborador: {
    badge: "Acesso operacional seguro",
    title: "Acesso colaborador",
    description:
      "Consulte informa\u00e7\u00f5es, acompanhe ativos e utilize os recursos liberados para sua fun\u00e7\u00e3o.",
  },
};

let temporizadorTema = null;
let temporizadorNotificacao = null;
let temporizadorRemoverNotificacao = null;
let focoUltimoDialogoInativo = null;
let animandoTrocaTipoUsuario = false;

document.addEventListener("DOMContentLoaded", inicializar);

function obterElemento(id) {
  return document.getElementById(id);
}

// O acesso ao armazenamento é protegido porque o navegador pode bloqueá-lo por privacidade.
function obterItemSalvo(chave) {
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

function normalizarPreferenciaLogin(valor, valoresPermitidos, padrao) {
  const normalizado = String(valor ?? "").trim();

  return valoresPermitidos.includes(normalizado) ? normalizado : padrao;
}

function salvarPreferenciasInterfacePeloLogin(preferencias) {
  if (!preferencias || typeof preferencias !== "object") {
    return;
  }

  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.theme,
    normalizarPreferenciaLogin(preferencias.theme, ["dark", "light", "auto"], "dark"),
  );
  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.accent,
    normalizarPreferenciaLogin(preferencias.accent, ["teal", "green", "blue", "violet"], "teal"),
  );
  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.fontSize,
    normalizarPreferenciaLogin(preferencias.fontSize, ["small", "default", "large", "extra"], "default"),
  );
  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.density,
    normalizarPreferenciaLogin(preferencias.density, ["comfortable", "compact"], "comfortable"),
  );
  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.motion,
    normalizarPreferenciaLogin(preferencias.motion, ["normal", "reduced"], "normal"),
  );
  definirItemSalvo(
    CHAVES_ARMAZENAMENTO.cursor,
    normalizarPreferenciaLogin(preferencias.cursor, ["enhanced", "normal"], "enhanced"),
  );
}

function removerItemSalvo(chave) {
  try {
    localStorage.removeItem(chave);
  } catch {
    return;
  }
}

function removerSenhaLegado() {
  removerItemSalvo("titech-password");
}

function criarElemento(etiqueta, nomeClasse, texto = "") {
  const elemento = document.createElement(etiqueta);

  if (nomeClasse) {
    elemento.className = nomeClasse;
  }

  if (texto) {
    elemento.textContent = texto;
  }

  return elemento;
}

function criarCamadaTransicaoPagina() {
  if (document.querySelector(".page-transition-layer")) return;

  const camada = criarElemento("div", "page-transition-layer");
  document.body.appendChild(camada);
}

function iniciarEntradaPagina() {
  criarCamadaTransicaoPagina();

  requestAnimationFrame(() => {
    document.body.classList.remove("page-loading");
  });
}

// A transição respeita a preferência de movimento reduzido antes de navegar.
function navegarComTransicao(url) {
  if (!url) return;

  document.body.classList.add("page-leaving");

  setTimeout(() => {
    window.location.href = new URL(url, window.location.href).href;
  }, CONFIGURACAO.pageTransitionDelay);
}

function atualizarBotaoTema(botao, ehEscuro) {
  const rotulo = botao.querySelector(".label");
  const descricao = botao.querySelector("small");
  const icone = botao.querySelector("i");

  if (rotulo) {
    rotulo.textContent = ehEscuro ? "Modo claro" : "Modo escuro";
  }

  if (descricao) {
    descricao.textContent = "Trocar tema";
  }

  if (icone) {
    icone.className = ehEscuro ? "bi bi-sun" : "bi bi-moon-stars";
  }

  botao.setAttribute(
    "aria-label",
    ehEscuro ? "Alternar para modo claro" : "Alternar para modo escuro",
  );
}

function resolverTemaLogin(tema) {
  if (tema === "auto") {
    return window.matchMedia?.("(prefers-color-scheme: light)")?.matches ? "light" : "dark";
  }

  return tema === "light" ? "light" : "dark";
}

function definirTema(tema) {
  const temaSelecionado = ["dark", "light", "auto"].includes(tema) ? tema : "dark";
  const ehEscuro = resolverTemaLogin(temaSelecionado) === "dark";

  document.body.classList.toggle("theme-dark", ehEscuro);
  definirItemSalvo(CHAVES_ARMAZENAMENTO.theme, temaSelecionado);

  document.querySelectorAll(".theme-toggle").forEach((botao) => {
    atualizarBotaoTema(botao, ehEscuro);
  });
}

function alternarTema() {
  const ehEscuro = document.body.classList.contains("theme-dark");
  const temaProximo = ehEscuro ? "light" : "dark";

  clearTimeout(temporizadorTema);
  document.body.classList.add("theme-switching");

  requestAnimationFrame(() => {
    definirTema(temaProximo);

    temporizadorTema = setTimeout(() => {
      document.body.classList.remove("theme-switching");
    }, CONFIGURACAO.themeTransitionDelay);
  });
}

function montarNotificacao(mensagem, tipo) {
  const notificacao = criarElemento("div", `toastx toastx-${tipo}`);
  notificacao.setAttribute("role", tipo === "error" ? "alert" : "status");
  const icone = criarElemento(
    "i",
    tipo === "error" ? "bi bi-x-circle" : "bi bi-check-circle",
  );
  icone.setAttribute("aria-hidden", "true");
  const texto = criarElemento("span", "", mensagem);

  notificacao.append(icone, texto);

  return notificacao;
}

function exibirNotificacao(mensagem, tipo = "success") {
  const pilhaNotificacoes = obterElemento("toastStack");

  if (!pilhaNotificacoes || !mensagem) return;

  clearTimeout(temporizadorNotificacao);
  clearTimeout(temporizadorRemoverNotificacao);

  let notificacao = pilhaNotificacoes.querySelector(".toastx");

  if (!notificacao) {
    notificacao = montarNotificacao(mensagem, tipo);
    pilhaNotificacoes.appendChild(notificacao);
  } else {
    const icone = notificacao.querySelector("i");
    const texto = notificacao.querySelector("span");

    if (icone) {
      icone.className =
        tipo === "error" ? "bi bi-x-circle" : "bi bi-check-circle";
    }

    if (texto) {
      texto.textContent = mensagem;
    }

    notificacao.className = `toastx toastx-${tipo}`;
  }

  notificacao.classList.remove("hide");
  notificacao.classList.add("show");

  temporizadorNotificacao = setTimeout(() => {
    notificacao.classList.remove("show");
    notificacao.classList.add("hide");

    temporizadorRemoverNotificacao = setTimeout(() => {
      notificacao.remove();
    }, CONFIGURACAO.toastRemoveDelay);
  }, CONFIGURACAO.toastDuration);
}

function validarLogin(email, senha) {
  if (!email) {
    return {
      ok: false,
      field: "email",
      message: "Informe seu e-mail corporativo.",
    };
  }

  if (!email.includes("@")) {
    return {
      ok: false,
      field: "email",
      message: "Digite um e-mail valido.",
    };
  }

  if (!ehEmailCorporativo(email)) {
    return {
      ok: false,
      field: "email",
      message: "Use um e-mail corporativo autorizado.",
    };
  }

  if (!senha) {
    return {
      ok: false,
      field: "password",
      message: "Informe sua senha.",
    };
  }

  if (senha.length < 4) {
    return {
      ok: false,
      field: "password",
      message: "A senha precisa ter pelo menos 4 caracteres.",
    };
  }

  return { ok: true, field: "", message: "" };
}

function ehEmailCorporativo(email) {
  return email.toLowerCase().endsWith("@titechsolutions.com.br");
}

function definirErroCampo(idCampo, mensagem) {
  const campoEntrada = obterElemento(idCampo);
  const erro = obterElemento(`${idCampo}Error`);
  const recipiente = campoEntrada?.closest(".input-wrap");

  if (!campoEntrada || !erro || !recipiente) return;

  campoEntrada.setAttribute("aria-invalid", "true");
  recipiente.classList.add("field-invalid");
  erro.textContent = mensagem;
  erro.hidden = false;
}

function limparErroCampo(idCampo) {
  const campoEntrada = obterElemento(idCampo);
  const erro = obterElemento(`${idCampo}Error`);
  const recipiente = campoEntrada?.closest(".input-wrap");

  if (!campoEntrada || !erro || !recipiente) return;

  campoEntrada.setAttribute("aria-invalid", "false");
  recipiente.classList.remove("field-invalid");
  erro.textContent = "";
  erro.hidden = true;
}

function limparValidacaoLogin() {
  limparErroCampo("email");
  limparErroCampo("password");
}

function obterMensagemFalhaLogin(resposta, dados) {
  if (resposta.status >= 500) {
    return CONFIGURACAO.serverUnavailableMessage;
  }

  if (resposta.status === 401) {
    return CONFIGURACAO.invalidCredentialsMessage;
  }

  return dados.message || CONFIGURACAO.invalidCredentialsMessage;
}

function definirCarregandoBotaoLogin(botao, estaCarregando) {
  if (!botao) return;

  botao.disabled = estaCarregando;
  botao.setAttribute("aria-busy", estaCarregando ? "true" : "false");
  botao.dataset.loading = estaCarregando ? "true" : "false";

  if (!estaCarregando) {
    const icone = criarElemento("i", "bi bi-lock");
    icone.setAttribute("aria-hidden", "true");
    const texto = criarElemento("span", "", botao.dataset.defaultLabel || "Entrar");

    botao.replaceChildren(icone, texto);
    return;
  }

  const indicadorCarregamento = criarElemento("i", "bi bi-arrow-repeat button-spinner");
  indicadorCarregamento.setAttribute("aria-hidden", "true");
  const texto = criarElemento("span", "", "Validando acesso...");

  botao.replaceChildren(indicadorCarregamento, texto);
}

function salvarPreferenciaPerfil(email, tipoUsuario, deveSalvar) {
  removerSenhaLegado();

  if (deveSalvar) {
    definirItemSalvo(CHAVES_ARMAZENAMENTO.email, email);
    definirItemSalvo(CHAVES_ARMAZENAMENTO.profile, tipoUsuario);
    return;
  }

  removerItemSalvo(CHAVES_ARMAZENAMENTO.email);
  removerItemSalvo(CHAVES_ARMAZENAMENTO.profile);
}

function abrirDialogoContaInativa(mensagem) {
  const dialogo = obterElemento("inactiveAccountDialog");
  const texto = obterElemento("inactiveAccountText");

  if (!dialogo) {
    exibirNotificacao(mensagem || CONFIGURACAO.inactiveAccountMessage, "error");
    return;
  }

  focoUltimoDialogoInativo =
    document.activeElement instanceof HTMLElement ? document.activeElement : null;

  if (texto) {
    texto.textContent = mensagem || CONFIGURACAO.inactiveAccountMessage;
  }

  dialogo.hidden = false;
  document.body.classList.add("login-modal-open");
  dialogo.querySelector("[data-close-inactive-dialog]")?.focus();
}

function fecharDialogoContaInativa() {
  const dialogo = obterElemento("inactiveAccountDialog");

  if (!dialogo) {
    return;
  }

  dialogo.hidden = true;
  document.body.classList.remove("login-modal-open");
  focoUltimoDialogoInativo?.focus?.();
  focoUltimoDialogoInativo = null;
}

// O diálogo controla foco e tecla Escape para manter a navegação acessível.
function inicializarDialogoContaInativo() {
  const dialogo = obterElemento("inactiveAccountDialog");

  if (!dialogo) {
    return;
  }

  dialogo.querySelectorAll("[data-close-inactive-dialog]").forEach((botao) => {
    botao.addEventListener("click", fecharDialogoContaInativa);
  });

  dialogo.addEventListener("click", (evento) => {
    if (evento.target === dialogo) {
      fecharDialogoContaInativa();
    }
  });

  document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && !dialogo.hidden) {
      fecharDialogoContaInativa();
    }
  });
}

// A navegação para o portal ocorre somente após autenticação confirmada pelo backend.
async function tratarLogin(evento) {
  evento.preventDefault();

  const campoEntradaEmail = obterElemento("email");
  const campoEntradaSenha = obterElemento("password");
  const lembrarPerfil = obterElemento("rememberProfile");
  const erroLogin = obterElemento("loginError");
  const botaoLogin = obterElemento("loginButton");

  if (
    !campoEntradaEmail ||
    !campoEntradaSenha ||
    !lembrarPerfil ||
    !erroLogin ||
    !botaoLogin
  ) {
    return;
  }

  const email = campoEntradaEmail.value.trim();
  const senha = campoEntradaSenha.value;
  const validacao = validarLogin(email, senha);

  limparValidacaoLogin();

  if (!validacao.ok) {
    erroLogin.textContent = validacao.message;
    definirErroCampo(validacao.field, validacao.message);
    obterElemento(validacao.field)?.focus();
    exibirNotificacao(validacao.message, "error");
    return;
  }

  erroLogin.textContent = "";
  definirCarregandoBotaoLogin(botaoLogin, true);

  try {
    const dadosFormulario = new FormData();

    dadosFormulario.append("email", email);
    dadosFormulario.append("senha", senha);
    dadosFormulario.append("tipo_usuario", estado.role);

    const urlLogin = evento.target.getAttribute("action") || CONFIGURACAO.loginUrl;
    const resposta = await fetch(new URL(urlLogin, window.location.href), {
      method: "POST",
      body: dadosFormulario,
      headers: { Accept: "application/json" },
    });
    const dados = await resposta.json().catch(() => ({}));

    if (!resposta.ok || !dados.ok) {
      if (dados.reason === "inactive_account") {
        erroLogin.textContent = "";
        definirCarregandoBotaoLogin(botaoLogin, false);
        abrirDialogoContaInativa();
        return;
      }

      throw new Error(obterMensagemFalhaLogin(resposta, dados));
    }

    salvarPreferenciaPerfil(email, estado.role, lembrarPerfil.checked);
    salvarPreferenciasInterfacePeloLogin(dados.preferences);
    exibirNotificacao(dados.message || "Login realizado com sucesso.");

    setTimeout(() => {
      navegarComTransicao(dados.redirect || CONFIGURACAO.redirectUrl);
    }, CONFIGURACAO.redirectDelay);
  } catch (erro) {
    const mensagem =
      erro instanceof TypeError
        ? CONFIGURACAO.serverUnavailableMessage
        : erro.message || CONFIGURACAO.serverUnavailableMessage;

    erroLogin.textContent = mensagem;
    exibirNotificacao(mensagem, "error");
    definirCarregandoBotaoLogin(botaoLogin, false);
  }
}

function inicializarTema() {
  const temaSalvo = obterItemSalvo(CHAVES_ARMAZENAMENTO.theme) || "dark";

  definirTema(temaSalvo);

  document.querySelectorAll(".theme-toggle").forEach((botao) => {
    botao.addEventListener("click", alternarTema);
  });
}

function atualizarStatusLembrarPerfil() {
  const lembrarPerfil = obterElemento("rememberProfile");
  const status = obterElemento("rememberProfileStatus");

  if (!lembrarPerfil || !status) return;

  status.textContent = lembrarPerfil.checked
    ? `E-mail e perfil ${estado.role} ser\u00e3o lembrados`
    : `Perfil selecionado: ${estado.role}`;
}

function inicializarPerfilSalvo() {
  const lembrarPerfil = obterElemento("rememberProfile");
  const campoEntradaEmail = obterElemento("email");
  const emailSalvo = obterItemSalvo(CHAVES_ARMAZENAMENTO.email);
  const perfilSalvo = obterItemSalvo(CHAVES_ARMAZENAMENTO.profile);

  removerSenhaLegado();

  if (!lembrarPerfil) return;

  if (emailSalvo && campoEntradaEmail) {
    campoEntradaEmail.value = emailSalvo;
  }

  lembrarPerfil.checked = Boolean(emailSalvo || perfilSalvo);
  atualizarStatusLembrarPerfil();

  lembrarPerfil.addEventListener("change", () => {
    salvarPreferenciaPerfil(
      campoEntradaEmail?.value.trim() || "",
      estado.role,
      lembrarPerfil.checked,
    );
    atualizarStatusLembrarPerfil();

    exibirNotificacao(
      lembrarPerfil.checked
        ? "E-mail e perfil ser\u00e3o lembrados."
        : "Dados lembrados removidos.",
    );
  });

  campoEntradaEmail?.addEventListener("input", () => {
    if (!lembrarPerfil.checked) return;

    salvarPreferenciaPerfil(campoEntradaEmail.value.trim(), estado.role, true);
  });
}

function inicializarMensagemSessao() {
  const parametros = new URLSearchParams(window.location.search);
  const statusSessao = parametros.get("sessao");

  if (statusSessao === "expirada") {
    exibirNotificacao("Sessao expirada. Faca login novamente.", "error");
  }

  if (statusSessao === "encerrada") {
    exibirNotificacao("Sessao encerrada com sucesso.");
  }
}

function deveReduzirMovimento() {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function atualizarConteudoPainelTipoUsuario(tipoUsuario) {
  const conteudo = CONTEUDO_TIPO_USUARIO[tipoUsuario] || CONTEUDO_TIPO_USUARIO.Colaborador;
  const indicador = obterElemento("roleBadge");
  const titulo = obterElemento("roleTitle");
  const descricao = obterElemento("roleDescription");

  if (indicador) {
    indicador.textContent = conteudo.badge;
  }

  if (titulo) {
    titulo.textContent = conteudo.title;
  }

  if (descricao) {
    descricao.textContent = conteudo.description;
  }
}

function sincronizarCampoEntradaTipoUsuario(tipoUsuario) {
  const campoEntradaTipoUsuario = obterElemento("roleInput");

  if (campoEntradaTipoUsuario) {
    campoEntradaTipoUsuario.value = tipoUsuario;
  }
}

function atualizarEstadoBotaoTipoUsuario(botoes, botaoSelecionado) {
  botoes.forEach((botao) => {
    const ehSelecionado = botao === botaoSelecionado;

    botao.classList.toggle("active", ehSelecionado);
    botao.setAttribute("aria-checked", ehSelecionado ? "true" : "false");
  });
}

function animarAlteracaoPainelTipoUsuario(tipoUsuario, direcao, controleSegmentado) {
  const painel = obterElemento("rolePanel");
  const instanciaGsap = window.gsap;

  if (!painel || !instanciaGsap || deveReduzirMovimento()) {
    atualizarConteudoPainelTipoUsuario(tipoUsuario);
    animandoTrocaTipoUsuario = false;
    controleSegmentado?.removeAttribute("data-switching");
    return;
  }

  const saidaX = direcao === "left" ? -28 : 28;
  const entradaX = direcao === "left" ? 28 : -28;

  animandoTrocaTipoUsuario = true;

  instanciaGsap
    .timeline({
      defaults: {
        duration: 0.32,
        ease: "power2.out",
      },
      onComplete: () => {
        animandoTrocaTipoUsuario = false;
        controleSegmentado?.removeAttribute("data-switching");
        instanciaGsap.set(painel, { clearProps: "transform,opacity" });
      },
    })
    .to(painel, {
      x: saidaX,
      opacity: 0,
      duration: 0.28,
    })
    .add(() => atualizarConteudoPainelTipoUsuario(tipoUsuario))
    .fromTo(
      painel,
      { x: entradaX, opacity: 0 },
      { x: 0, opacity: 1, duration: 0.34, ease: "power3.out" },
    );
}

function atualizarIndicadorSeguranca(tipoUsuario) {
  const indicador = obterElemento("securityMeter");

  if (!indicador) return;

  indicador.style.width = tipoUsuario === "Administrador" ? "84%" : "72%";
}

function definirTipoUsuarioAtivo(botoes, botaoSelecionado, controleSegmentado) {
  if (animandoTrocaTipoUsuario) {
    return;
  }

  const tipoUsuarioSelecionado = botaoSelecionado.dataset.role;

  if (!tipoUsuarioSelecionado) return;

  const tipoUsuarioAnterior = estado.role;
  const direcao = tipoUsuarioSelecionado === "Administrador" ? "left" : "right";

  atualizarEstadoBotaoTipoUsuario(botoes, botaoSelecionado);

  animandoTrocaTipoUsuario = true;
  controleSegmentado.dataset.switching = "true";
  estado.role = tipoUsuarioSelecionado;
  sincronizarCampoEntradaTipoUsuario(tipoUsuarioSelecionado);
  controleSegmentado.dataset.active = tipoUsuarioSelecionado;
  atualizarIndicadorSeguranca(tipoUsuarioSelecionado);

  const lembrarPerfil = obterElemento("rememberProfile");
  const campoEntradaEmail = obterElemento("email");

  if (lembrarPerfil?.checked) {
    salvarPreferenciaPerfil(campoEntradaEmail?.value.trim() || "", tipoUsuarioSelecionado, true);
  }

  atualizarStatusLembrarPerfil();
  exibirNotificacao(
    lembrarPerfil?.checked
      ? `Perfil ${tipoUsuarioSelecionado} atualizado e salvo.`
      : `Perfil selecionado: ${tipoUsuarioSelecionado}`,
  );

  if (tipoUsuarioAnterior !== tipoUsuarioSelecionado) {
    animarAlteracaoPainelTipoUsuario(tipoUsuarioSelecionado, direcao, controleSegmentado);
  } else {
    animandoTrocaTipoUsuario = false;
    controleSegmentado.removeAttribute("data-switching");
  }
}

// A troca de perfil mantém botão, campo enviado e conteúdo explicativo sincronizados.
function inicializarSeletorTipoUsuario() {
  const controleSegmentado = document.querySelector(".segment-control");
  const botoes = [...document.querySelectorAll(".segment-control button")];

  if (!controleSegmentado || !botoes.length) return;

  const perfilSalvo = obterItemSalvo(CHAVES_ARMAZENAMENTO.profile);
  const botaoAtivo = botoes.find((botao) =>
    botao.classList.contains("active"),
  );
  const botaoSalvo = botoes.find(
    (botao) => botao.dataset.role === perfilSalvo,
  );
  const botaoSelecionado = botaoSalvo || botaoAtivo || botoes[0];

  if (botaoSelecionado?.dataset.role) {
    estado.role = botaoSelecionado.dataset.role;
  }

  atualizarEstadoBotaoTipoUsuario(botoes, botaoSelecionado);
  sincronizarCampoEntradaTipoUsuario(estado.role);

  controleSegmentado.dataset.active = estado.role;
  atualizarIndicadorSeguranca(estado.role);
  atualizarConteudoPainelTipoUsuario(estado.role);

  botoes.forEach((botao) => {
    botao.addEventListener("click", () => {
      const tipoUsuarioSelecionado = botao.dataset.role;
      const jaEstaAtivo =
        estado.role === tipoUsuarioSelecionado && botao.classList.contains("active");

      if (jaEstaAtivo) return;

      definirTipoUsuarioAtivo(botoes, botao, controleSegmentado);
    });

    botao.addEventListener("keydown", (evento) => {
      const chaves = ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"];

      if (!chaves.includes(evento.key)) return;

      evento.preventDefault();

      const indiceAtual = botoes.indexOf(botao);
      const etapa = evento.key === "ArrowRight" || evento.key === "ArrowDown" ? 1 : -1;
      const indiceProximo = (indiceAtual + etapa + botoes.length) % botoes.length;
      const botaoProximo = botoes[indiceProximo];

      botaoProximo.focus();
      definirTipoUsuarioAtivo(botoes, botaoProximo, controleSegmentado);
    });
  });
}

function inicializarAlternadorSenha() {
  const alternadorSenha = obterElemento("passwordToggle");
  const campoEntradaSenha = obterElemento("password");

  if (!alternadorSenha || !campoEntradaSenha) return;

  alternadorSenha.addEventListener("click", () => {
    const icone = alternadorSenha.querySelector("i");
    const ehOculto = campoEntradaSenha.type === "password";

    campoEntradaSenha.type = ehOculto ? "text" : "password";

    if (icone) {
      icone.className = ehOculto ? "bi bi-eye-slash" : "bi bi-eye";
    }

    alternadorSenha.setAttribute(
      "aria-label",
      ehOculto ? "Ocultar senha" : "Mostrar senha",
    );
    alternadorSenha.setAttribute("aria-pressed", ehOculto ? "true" : "false");
  });
}

function inicializarValidacaoCampo() {
  ["email", "password"].forEach((idCampo) => {
    obterElemento(idCampo)?.addEventListener("input", () => limparErroCampo(idCampo));
  });
}

function inicializarSolicitacaoAcesso() {
  const solicitarAcesso = obterElemento("requestAccess");

  if (!solicitarAcesso) return;

  solicitarAcesso.addEventListener("click", (evento) => {
    evento.preventDefault();
    exibirNotificacao("Fluxo de recupera\u00e7\u00e3o de senha ainda n\u00e3o configurado.");
  });
}

function inicializarAtalhosSuporte() {
  const atalhoEsqueciSenha = obterElemento("forgotPasswordLink");

  if (atalhoEsqueciSenha) {
    atalhoEsqueciSenha.addEventListener("click", (evento) => {
      evento.preventDefault();
      exibirNotificacao("Fluxo de recupera\u00e7\u00e3o de senha ainda n\u00e3o configurado.");
    });
  }
}

function inicializarCartoesBeneficio() {
  const cartoes = [...document.querySelectorAll(".benefit-card")];

  if (!cartoes.length) return;

  cartoes.forEach((cartao) => {
    cartao.addEventListener("click", () => {
      cartoes.forEach((item) => {
        item.classList.toggle("active", item === cartao);
      });

      if (cartao.dataset.benefit) {
        exibirNotificacao(cartao.dataset.benefit);
      }
    });
  });
}

function inicializarFormularioLogin() {
  const formularioLogin = obterElemento("loginForm");

  if (!formularioLogin) return;

  formularioLogin.addEventListener("submit", tratarLogin);
}

function inicializarCursorPersonalizado() {
  const cursor = document.querySelector(".custom-cursor");
  const ehDispositivoToque = window.matchMedia("(pointer: coarse)").matches;

  if (!cursor || ehDispositivoToque) return;

  document.documentElement.classList.add("custom-cursor-enabled");
  document.body.classList.add("custom-cursor-enabled");

  window.addEventListener("mousemove", (evento) => {
    document.body.classList.add("cursor-visible");

    cursor.style.left = `${evento.clientX}px`;
    cursor.style.top = `${evento.clientY}px`;
  });

  window.addEventListener("mouseleave", () => {
    document.body.classList.remove("cursor-visible");
  });

  window.addEventListener("mousedown", () => {
    document.body.classList.add("cursor-click");
  });

  window.addEventListener("mouseup", () => {
    document.body.classList.remove("cursor-click");
  });

  document.addEventListener("mouseover", (evento) => {
    if (
      evento.target.closest(
        "a, button, input, label, .form-control, [role='button']",
      )
    ) {
      document.body.classList.add("cursor-hover");
    }
  });

  document.addEventListener("mouseout", (evento) => {
    if (
      evento.target.closest(
        "a, button, input, label, .form-control, [role='button']",
      )
    ) {
      document.body.classList.remove("cursor-hover");
    }
  });
}

// A inicialização restaura preferências antes de ativar as interações da página.
function inicializar() {
  inicializarTema();
  inicializarDialogoContaInativo();
  inicializarSeletorTipoUsuario();
  inicializarPerfilSalvo();
  inicializarAlternadorSenha();
  inicializarValidacaoCampo();
  inicializarAtalhosSuporte();
  inicializarSolicitacaoAcesso();
  inicializarCartoesBeneficio();
  inicializarFormularioLogin();
  inicializarCursorPersonalizado();
  iniciarEntradaPagina();
  inicializarMensagemSessao();
}
