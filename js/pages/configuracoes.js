// Gerencia preferencias do usuario, seguranca visual e diagnosticos da pagina de configuracoes.
// O navegador fica como cache; a fonte principal das preferencias e o perfil salvo no servidor.

document.addEventListener("DOMContentLoaded", inicializarPaginaConfiguracoes);

const TEMPO_EXIBICAO_MENSAGEM_PREFERENCIA_MS = 2400;
const TEMPO_EXIBICAO_NOTIFICACAO_MS = 3200;
const TAMANHO_MAXIMO_FOTO_CRACHA_BYTES = 2 * 1024 * 1024;
const TIPOS_PERMITIDOS_FOTO_CRACHA = ["image/jpeg", "image/png", "image/webp"];
const DESLOCAMENTO_SECAO_CONFIGURACOES_PX = 160;
const CORES_PREDEFINIDAS_DESTAQUE = {
  teal: "#66d5c2",
  green: "#22c55e",
  blue: "#38bdf8",
  violet: "#a78bfa",
};
const PADRAO_COR_HEXADECIMAL = /^#[0-9a-f]{6}$/i;
// Gradientes conicos do CSS iniciam no topo; atan2 considera a direita como angulo zero.
const DESLOCAMENTO_GRADIENTE_CONICO_GRAUS = 90;
const PROPORCAO_RAIO_INTERNO_ANEL = 0.58;
const DISTANCIA_BOLINHA_ANEL_PERCENTUAL = 41;
const PREFERENCIAS_INTERFACE_PADRAO = {
  theme: "dark",
  accent: "teal",
  fontSize: "default",
  density: "comfortable",
  motion: "normal",
  cursor: "enhanced",
};

let temporizadorMensagemPreferencia = null;
let temporizadorNotificacao = null;
let identificadorAnimacaoNavegacao = null;

function inicializarPaginaConfiguracoes() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarNavegacaoConfiguracoes();
  configurarEnvioFotoCracha();
  configurarControlesPreferencias();
  configurarValidacaoSenha();
  configurarDiagnosticos();
}

// Mantém a navegação local alinhada à seção visível sem acoplar essa regra ao restante da página.
function configurarNavegacaoConfiguracoes() {
  const itensNavegacao = [...document.querySelectorAll("[data-settings-nav]")];
  const secoes = itensNavegacao
    .map((itemNavegacao) => document.querySelector(itemNavegacao.getAttribute("href") || ""))
    .filter(Boolean);

  if (!itensNavegacao.length || !secoes.length) {
    return;
  }

  const atualizarSecaoAtiva = () => {
    identificadorAnimacaoNavegacao = null;
    const secaoAtiva = [...secoes]
      .reverse()
      .find((secao) => secao.getBoundingClientRect().top <= DESLOCAMENTO_SECAO_CONFIGURACOES_PX)
      || secoes[0];

    definirItemNavegacaoAtivo(itensNavegacao, secaoAtiva.id);
  };

  const agendarAtualizacaoNavegacao = () => {
    if (identificadorAnimacaoNavegacao !== null) {
      return;
    }

    identificadorAnimacaoNavegacao = window.requestAnimationFrame(atualizarSecaoAtiva);
  };

  itensNavegacao.forEach((itemNavegacao) => {
    itemNavegacao.addEventListener("click", () => {
      definirItemNavegacaoAtivo(itensNavegacao, itemNavegacao.hash.slice(1));
    });
  });

  window.addEventListener("scroll", agendarAtualizacaoNavegacao, { passive: true });
  window.addEventListener("resize", agendarAtualizacaoNavegacao);
  atualizarSecaoAtiva();
}

function definirItemNavegacaoAtivo(itensNavegacao, idSecao) {
  itensNavegacao.forEach((itemNavegacao) => {
    const estaAtivo = itemNavegacao.hash === `#${idSecao}`;

    itemNavegacao.classList.toggle("is-active", estaAtivo);

    if (estaAtivo) {
      itemNavegacao.setAttribute("aria-current", "location");
    } else {
      itemNavegacao.removeAttribute("aria-current");
    }
  });
}

// O JavaScript melhora a experiencia com preview e mensagens, mas a validacao real fica no PHP.
function configurarEnvioFotoCracha() {
  const formulario = document.getElementById("badgePhotoForm");
  const campoFoto = document.getElementById("badgePhotoInput");
  const botaoSalvar = document.getElementById("saveBadgePhotoButton");

  if (!formulario || !campoFoto) {
    return;
  }

  campoFoto.addEventListener("change", () => {
    const arquivoFoto = campoFoto.files?.[0] || null;
    const erroValidacao = validarArquivoFotoCracha(arquivoFoto);

    definirMensagemFotoCracha(erroValidacao, erroValidacao ? "error" : "");

    if (botaoSalvar) {
      botaoSalvar.disabled = Boolean(erroValidacao) || !arquivoFoto;
    }

    if (!erroValidacao && arquivoFoto) {
      exibirPreviaFotoCracha(URL.createObjectURL(arquivoFoto), true);
    }
  });

  formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const arquivoFoto = campoFoto.files?.[0] || null;
    const erroValidacao = validarArquivoFotoCracha(arquivoFoto);

    if (erroValidacao) {
      definirMensagemFotoCracha(erroValidacao, "error");
      return;
    }

    definirBotaoCarregando(botaoSalvar, true, "Salvando...");

    try {
      const resposta = await fetch(formulario.action, {
        method: "POST",
        credentials: "same-origin",
        body: new FormData(formulario),
      });
      const resultado = await resposta.json().catch(() => null);

      if (!resposta.ok || !resultado?.ok) {
        throw new Error(resultado?.message || "Nao foi possivel salvar a foto.");
      }

      const enderecoFoto = adicionarParametroAnticache(resultado.foto_cracha_url || "");

      if (enderecoFoto) {
        exibirPreviaFotoCracha(enderecoFoto, false);
        atualizarFotoCrachaBarraLateral(enderecoFoto);
      }

      campoFoto.value = "";
      definirMensagemFotoCracha(resultado.message || "Foto do cracha atualizada.", "success");
      exibirNotificacao("Foto do cracha salva no perfil.");
    } catch (erro) {
      definirMensagemFotoCracha(erro.message || "Nao foi possivel salvar a foto.", "error");
    } finally {
      definirBotaoCarregando(botaoSalvar, false);

      if (botaoSalvar) {
        botaoSalvar.disabled = true;
      }
    }
  });
}

function validarArquivoFotoCracha(arquivoFoto) {
  if (!arquivoFoto) {
    return "Selecione uma imagem para o cracha.";
  }

  if (!TIPOS_PERMITIDOS_FOTO_CRACHA.includes(arquivoFoto.type)) {
    return "Use uma imagem JPG, PNG ou WebP.";
  }

  if (arquivoFoto.size > TAMANHO_MAXIMO_FOTO_CRACHA_BYTES) {
    return "Envie uma imagem de ate 2 MB.";
  }

  return "";
}

function exibirPreviaFotoCracha(enderecoFoto, revogarEnderecoAposCarregar) {
  const avatarCracha = document.querySelector("[data-badge-photo-preview]");

  if (!avatarCracha || !enderecoFoto) {
    return;
  }

  avatarCracha.classList.add("has-photo");
  avatarCracha.removeAttribute("aria-hidden");
  avatarCracha.textContent = "";

  const imagem = document.createElement("img");
  imagem.src = enderecoFoto;
  imagem.alt = "Foto do cracha";

  if (revogarEnderecoAposCarregar) {
    imagem.addEventListener("load", () => URL.revokeObjectURL(enderecoFoto), { once: true });
  }

  avatarCracha.appendChild(imagem);
}

function atualizarFotoCrachaBarraLateral(enderecoFoto) {
  document.querySelectorAll(".sidebar-avatar").forEach((avatarLateral) => {
    avatarLateral.classList.add("has-photo");
    avatarLateral.textContent = "";

    const imagem = document.createElement("img");
    imagem.src = enderecoFoto;
    imagem.alt = "";
    avatarLateral.appendChild(imagem);
  });
}

function definirMensagemFotoCracha(mensagem, tipo) {
  const elementoMensagem = document.getElementById("badgePhotoMessage");

  if (!elementoMensagem) {
    return;
  }

  elementoMensagem.textContent = mensagem;
  elementoMensagem.classList.toggle("show", Boolean(mensagem));
  elementoMensagem.classList.toggle("success", tipo === "success");
  elementoMensagem.classList.toggle("error", tipo === "error");
}

function adicionarParametroAnticache(endereco) {
  if (!endereco) {
    return "";
  }

  return `${endereco}${endereco.includes("?") ? "&" : "?"}v=${Date.now()}`;
}

// Preferencias visuais sao aplicadas imediatamente e persistidas no perfil do usuario.
function configurarControlesPreferencias() {
  sincronizarFormularioPreferencias();

  document.getElementById("themeToggle")?.addEventListener("click", () => {
    window.setTimeout(sincronizarFormularioPreferencias, 0);
  });

  window.addEventListener("titech:theme-change", sincronizarFormularioPreferencias);
  configurarSeletorCircularCor();

  document.querySelectorAll('input[name="theme"]').forEach((campoTema) => {
    campoTema.addEventListener("change", () => {
      if (!campoTema.checked) return;

      void salvarAlteracaoPreferencias(
        { theme: campoTema.value },
        "Modo de tela atualizado.",
        campoTema.value === "auto" ? "Tema automatico salvo para seu usuario." : "Tema salvo para seu usuario.",
      );
    });
  });

  document.querySelectorAll('input[name="fontSize"]').forEach((campoTamanhoFonte) => {
    campoTamanhoFonte.addEventListener("change", () => {
      if (!campoTamanhoFonte.checked) return;

      void salvarAlteracaoPreferencias(
        { fontSize: campoTamanhoFonte.value },
        "Tamanho da fonte atualizado.",
        "Preferencia de leitura salva para seu usuario.",
      );
    });
  });

  document.getElementById("densityToggle")?.addEventListener("change", (evento) => {
    const densidade = evento.currentTarget.checked ? "compact" : "comfortable";

    void salvarAlteracaoPreferencias(
      { density: densidade },
      "Ajuste de densidade salvo.",
      "Densidade salva para seu usuario.",
    );
  });

  document.getElementById("motionToggle")?.addEventListener("change", (evento) => {
    const animacao = evento.currentTarget.checked ? "reduced" : "normal";

    void salvarAlteracaoPreferencias(
      { motion: animacao },
      "Preferencia de animacao salva.",
      "Preferencia de animacao salva para seu usuario.",
    );
  });

  document.getElementById("cursorToggle")?.addEventListener("change", (evento) => {
    const cursor = evento.currentTarget.checked ? "enhanced" : "normal";

    void salvarAlteracaoPreferencias(
      { cursor },
      "Realce de cursor atualizado.",
      "Cursor salvo para seu usuario.",
    );
  });

  document.getElementById("resetPreferences")?.addEventListener("click", async () => {
    const confirmado = await confirmarAcaoConfiguracoes(
      "Restaurar preferencias?",
      "As escolhas visuais do seu usuario voltarao para o padrao TI TECH."
    );

    if (confirmado) {
      void restaurarPreferencias();
    }
  });
}

function configurarSeletorCircularCor() {
  const anelCores = document.getElementById("accentColorWheel");
  const campoCorHexadecimal = document.getElementById("accentColorValue");
  const campoCorNativo = document.getElementById("accentNativeColor");
  const botoesCoresPredefinidas = document.querySelectorAll("[data-accent-preset]");

  if (!anelCores || !campoCorHexadecimal) {
    return;
  }

  let estaArrastando = false;

  const aplicarPreviaCor = (valor) => {
    const cor = normalizarCorDestaque(valor);

    if (!cor) {
      return "";
    }

    const preferencias = aplicarEstadoPreferencias({
      ...obterEstadoPreferencias(),
      accent: cor,
    });

    sincronizarControlesCorDestaque(preferencias.accent);
    return cor;
  };

  const salvarCor = (valor) => {
    const cor = normalizarCorDestaque(valor);

    if (!cor) {
      sincronizarControlesCorDestaque(obterEstadoPreferencias().accent);
      return;
    }

    void salvarAlteracaoPreferencias(
      { accent: cor },
      "Cor aplicada.",
      "Cor salva para seu usuario.",
    );
  };

  const atualizarCorPeloPonteiro = (evento, deveSalvar) => {
    const cor = obterCorPeloPonteiroDoAnel(anelCores, evento);

    aplicarPreviaCor(cor);

    if (deveSalvar) {
      salvarCor(cor);
    }
  };

  anelCores.addEventListener("pointerdown", (evento) => {
    evento.preventDefault();
    estaArrastando = true;
    anelCores.setPointerCapture?.(evento.pointerId);
    atualizarCorPeloPonteiro(evento, false);
  });

  anelCores.addEventListener("pointermove", (evento) => {
    if (!estaArrastando) {
      return;
    }

    atualizarCorPeloPonteiro(evento, false);
  });

  const finalizarEscolhaPonteiro = (evento) => {
    if (!estaArrastando) {
      return;
    }

    estaArrastando = false;
    anelCores.releasePointerCapture?.(evento.pointerId);
    atualizarCorPeloPonteiro(evento, true);
  };

  anelCores.addEventListener("pointerup", finalizarEscolhaPonteiro);
  anelCores.addEventListener("pointercancel", finalizarEscolhaPonteiro);

  anelCores.addEventListener("keydown", (evento) => {
    if (!["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End"].includes(evento.key)) {
      return;
    }

    evento.preventDefault();

    const corHsv = converterHexParaHsv(converterDestaqueParaHex(obterEstadoPreferencias().accent));
    let proximoMatiz = corHsv.h;

    if (["ArrowLeft", "ArrowDown"].includes(evento.key)) proximoMatiz -= 6;
    if (["ArrowRight", "ArrowUp"].includes(evento.key)) proximoMatiz += 6;
    if (evento.key === "Home") proximoMatiz = 0;
    if (evento.key === "End") proximoMatiz = 359;

    salvarCor(converterHsvParaHex((proximoMatiz + 360) % 360, 1, 1));
  });

  campoCorHexadecimal.addEventListener("input", () => {
    const cor = normalizarCorDestaque(campoCorHexadecimal.value);

    campoCorHexadecimal.classList.toggle("invalid", !cor && campoCorHexadecimal.value.trim() !== "");

    if (cor) {
      aplicarPreviaCor(cor);
    }
  });

  campoCorHexadecimal.addEventListener("change", () => salvarCor(campoCorHexadecimal.value));

  campoCorNativo?.addEventListener("input", () => aplicarPreviaCor(campoCorNativo.value));
  campoCorNativo?.addEventListener("change", () => salvarCor(campoCorNativo.value));

  botoesCoresPredefinidas.forEach((botaoCor) => {
    botaoCor.addEventListener("click", () => salvarCor(botaoCor.dataset.accentPreset || ""));
  });
}

function sincronizarControlesCorDestaque(corDestaque) {
  const cor = converterDestaqueParaHex(corDestaque);
  const campoCorHexadecimal = document.getElementById("accentColorValue");
  const campoCorNativo = document.getElementById("accentNativeColor");
  const rotuloCorAtual = document.getElementById("accentCurrentLabel");
  const amostraCorAtual = document.getElementById("accentCurrentSwatch");
  const bolinhaSeletora = document.getElementById("accentWheelThumb");

  if (campoCorHexadecimal) {
    campoCorHexadecimal.value = cor.toUpperCase();
    campoCorHexadecimal.classList.remove("invalid");
  }

  if (campoCorNativo) {
    campoCorNativo.value = cor;
  }

  if (rotuloCorAtual) {
    rotuloCorAtual.textContent = cor.toUpperCase();
  }

  if (amostraCorAtual) {
    amostraCorAtual.style.backgroundColor = cor;
  }

  if (bolinhaSeletora) {
    const corHsv = converterHexParaHsv(cor);
    const angulo = ((corHsv.h - DESLOCAMENTO_GRADIENTE_CONICO_GRAUS) * Math.PI) / 180;

    bolinhaSeletora.style.left = `${50 + Math.cos(angulo) * DISTANCIA_BOLINHA_ANEL_PERCENTUAL}%`;
    bolinhaSeletora.style.top = `${50 + Math.sin(angulo) * DISTANCIA_BOLINHA_ANEL_PERCENTUAL}%`;
    bolinhaSeletora.style.backgroundColor = cor;
  }
}

function obterCorPeloPonteiroDoAnel(anelCores, evento) {
  const limites = anelCores.getBoundingClientRect();
  const raio = Math.min(limites.width, limites.height) / 2;
  const centroX = limites.left + limites.width / 2;
  const centroY = limites.top + limites.height / 2;
  const diferencaX = evento.clientX - centroX;
  const diferencaY = evento.clientY - centroY;
  const distancia = Math.hypot(diferencaX, diferencaY);

  if (raio <= 0 || distancia < raio * PROPORCAO_RAIO_INTERNO_ANEL) {
    return "";
  }

  const anguloPonteiro = (Math.atan2(diferencaY, diferencaX) * 180) / Math.PI;
  const matiz = anguloPonteiro + DESLOCAMENTO_GRADIENTE_CONICO_GRAUS;

  return converterHsvParaHex((matiz + 360) % 360, 1, 1);
}

function normalizarCorDestaque(valor) {
  const cor = String(valor ?? "").trim();

  if (Object.hasOwn(CORES_PREDEFINIDAS_DESTAQUE, cor)) {
    return CORES_PREDEFINIDAS_DESTAQUE[cor];
  }

  return PADRAO_COR_HEXADECIMAL.test(cor) ? cor.toLowerCase() : "";
}

function converterDestaqueParaHex(valor) {
  return normalizarCorDestaque(valor) || CORES_PREDEFINIDAS_DESTAQUE.teal;
}

function converterHexParaRgb(hexadecimal) {
  const hexadecimalNormalizado = converterDestaqueParaHex(hexadecimal).replace("#", "");

  return {
    r: parseInt(hexadecimalNormalizado.slice(0, 2), 16),
    g: parseInt(hexadecimalNormalizado.slice(2, 4), 16),
    b: parseInt(hexadecimalNormalizado.slice(4, 6), 16),
  };
}

function converterRgbParaHex({ r: vermelho, g: verde, b: azul }) {
  return `#${[vermelho, verde, azul]
    .map((canal) => Math.round(limitarNumero(canal, 0, 255)).toString(16).padStart(2, "0"))
    .join("")}`;
}

function converterHexParaHsv(hexadecimal) {
  const { r: vermelho, g: verde, b: azul } = converterHexParaRgb(hexadecimal);
  const vermelhoNormalizado = vermelho / 255;
  const verdeNormalizado = verde / 255;
  const azulNormalizado = azul / 255;
  const maximo = Math.max(vermelhoNormalizado, verdeNormalizado, azulNormalizado);
  const minimo = Math.min(vermelhoNormalizado, verdeNormalizado, azulNormalizado);
  const diferenca = maximo - minimo;
  let matiz = 0;

  if (diferenca !== 0) {
    if (maximo === vermelhoNormalizado) {
      matiz = 60 * (((verdeNormalizado - azulNormalizado) / diferenca) % 6);
    } else if (maximo === verdeNormalizado) {
      matiz = 60 * ((azulNormalizado - vermelhoNormalizado) / diferenca + 2);
    } else {
      matiz = 60 * ((vermelhoNormalizado - verdeNormalizado) / diferenca + 4);
    }
  }

  return {
    h: (matiz + 360) % 360,
    s: maximo === 0 ? 0 : diferenca / maximo,
    v: maximo,
  };
}

function converterHsvParaHex(matiz, saturacao, valor) {
  const croma = valor * saturacao;
  const segmento = matiz / 60;
  const componenteIntermediario = croma * (1 - Math.abs((segmento % 2) - 1));
  const ajuste = valor - croma;
  let vermelho = 0;
  let verde = 0;
  let azul = 0;

  if (segmento >= 0 && segmento < 1) [vermelho, verde, azul] = [croma, componenteIntermediario, 0];
  else if (segmento >= 1 && segmento < 2) [vermelho, verde, azul] = [componenteIntermediario, croma, 0];
  else if (segmento >= 2 && segmento < 3) [vermelho, verde, azul] = [0, croma, componenteIntermediario];
  else if (segmento >= 3 && segmento < 4) [vermelho, verde, azul] = [0, componenteIntermediario, croma];
  else if (segmento >= 4 && segmento < 5) [vermelho, verde, azul] = [componenteIntermediario, 0, croma];
  else [vermelho, verde, azul] = [croma, 0, componenteIntermediario];

  return converterRgbParaHex({
    r: (vermelho + ajuste) * 255,
    g: (verde + ajuste) * 255,
    b: (azul + ajuste) * 255,
  });
}

function limitarNumero(valor, minimo, maximo) {
  return Math.min(maximo, Math.max(minimo, valor));
}

function obterEstadoPreferencias() {
  if (typeof window.obterPreferenciasUsuarioAtual === "function") {
    return window.obterPreferenciasUsuarioAtual();
  }

  return {
    theme: obterItemSalvo("titech-theme") || PREFERENCIAS_INTERFACE_PADRAO.theme,
    accent: obterItemSalvo("titech-accent") || PREFERENCIAS_INTERFACE_PADRAO.accent,
    fontSize: obterItemSalvo("titech-font-size") || PREFERENCIAS_INTERFACE_PADRAO.fontSize,
    density: obterItemSalvo("titech-density") || PREFERENCIAS_INTERFACE_PADRAO.density,
    motion: obterItemSalvo("titech-motion") || PREFERENCIAS_INTERFACE_PADRAO.motion,
    cursor: obterItemSalvo("titech-cursor") || PREFERENCIAS_INTERFACE_PADRAO.cursor,
  };
}

function normalizarEstadoPreferencias(preferencias) {
  if (typeof window.normalizarPreferenciasUsuario === "function") {
    return window.normalizarPreferenciasUsuario(preferencias);
  }

  return { ...PREFERENCIAS_INTERFACE_PADRAO, ...preferencias };
}

function aplicarEstadoPreferencias(preferencias) {
  const preferenciasNormalizadas = normalizarEstadoPreferencias(preferencias);

  if (typeof window.aplicarPreferenciasUsuario === "function") {
    return window.aplicarPreferenciasUsuario(preferenciasNormalizadas);
  }

  definirItemSalvo("titech-accent", preferenciasNormalizadas.accent);
  definirItemSalvo("titech-theme", preferenciasNormalizadas.theme);
  definirItemSalvo("titech-font-size", preferenciasNormalizadas.fontSize);
  definirItemSalvo("titech-density", preferenciasNormalizadas.density);
  definirItemSalvo("titech-motion", preferenciasNormalizadas.motion);
  definirItemSalvo("titech-cursor", preferenciasNormalizadas.cursor);
  aplicarTema(preferenciasNormalizadas.theme);
  aplicarDestaque(preferenciasNormalizadas.accent);
  aplicarPreferenciaTamanhoFonte(preferenciasNormalizadas.fontSize);
  aplicarDensidade(preferenciasNormalizadas.density);
  aplicarPreferenciaMovimento(preferenciasNormalizadas.motion);
  aplicarPreferenciaCursor(preferenciasNormalizadas.cursor);

  return preferenciasNormalizadas;
}

async function salvarAlteracaoPreferencias(preferenciasParciais, mensagem, notificacaoSucesso) {
  const proximasPreferencias = aplicarEstadoPreferencias({
    ...obterEstadoPreferencias(),
    ...preferenciasParciais,
  });

  sincronizarFormularioPreferencias();
  exibirMensagemPreferencia(mensagem);

  const resultado = typeof window.salvarPreferenciasUsuario === "function"
    ? await window.salvarPreferenciasUsuario(proximasPreferencias)
    : { ok: true, preferences: proximasPreferencias };

  if (resultado.ok) {
    if (resultado.preferences) {
      aplicarEstadoPreferencias(resultado.preferences);
      sincronizarFormularioPreferencias();
    }

    exibirNotificacao(notificacaoSucesso || "Preferencias salvas para seu usuario.");
  } else {
    exibirNotificacao("Preferencia aplicada nesta sessao, mas nao foi salva no usuario.");
  }
}

// A análise no navegador orienta o usuário; o backend repete as regras antes de alterar a senha.
function configurarValidacaoSenha() {
  const formulario = document.getElementById("passwordForm");
  const campoSenhaAtual = document.getElementById("currentPassword");
  const campoNovaSenha = document.getElementById("newPassword");
  const campoConfirmacaoSenha = document.getElementById("confirmPassword");
  const botaoAtualizar = document.getElementById("updatePasswordButton");

  configurarBotoesVisibilidadeSenha(formulario);
  configurarAvisoMaiusculasAtivasSenha(formulario);

  [campoSenhaAtual, campoNovaSenha, campoConfirmacaoSenha].forEach((campoSenha) => {
    campoSenha?.addEventListener("input", () => definirMensagemSenha("", ""));
  });

  campoNovaSenha?.addEventListener("input", atualizarForcaSenha);
  campoConfirmacaoSenha?.addEventListener("input", atualizarForcaSenha);

  formulario?.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const senhaAtual = campoSenhaAtual?.value || "";
    const novaSenha = campoNovaSenha?.value || "";
    const confirmacaoSenha = campoConfirmacaoSenha?.value || "";
    const resultadoAvaliacao = avaliarSenha(novaSenha, confirmacaoSenha);

    definirMensagemSenha("", "");

    if (!senhaAtual || !novaSenha || !confirmacaoSenha) {
      definirMensagemSenha("Preencha senha atual, nova senha e confirmacao.", "error");
      [campoSenhaAtual, campoNovaSenha, campoConfirmacaoSenha]
        .find((campoSenha) => !campoSenha?.value)
        ?.focus();
      return;
    }

    if (!Object.values(resultadoAvaliacao.rules).every(Boolean)) {
      definirMensagemSenha("A nova senha ainda nao atende a todos os criterios.", "error");
      campoNovaSenha?.focus();
      return;
    }

    if (senhaAtual === novaSenha) {
      definirMensagemSenha("A nova senha precisa ser diferente da senha atual.", "error");
      campoNovaSenha?.focus();
      return;
    }

    definirBotaoCarregando(botaoAtualizar, true, "Atualizando...");

    try {
      const resposta = await fetch(formulario.action, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(formulario),
      });
      const corpoResposta = await resposta.json().catch(() => null);

      if (!resposta.ok || !corpoResposta?.ok) {
        if (resposta.status === 401) {
          campoSenhaAtual?.focus();
        }

        throw new Error(corpoResposta?.message || "Nao foi possivel atualizar a senha.");
      }

      formulario.reset();
      restaurarVisibilidadeSenhas(formulario);
      atualizarForcaSenha();
      definirMensagemSenha(corpoResposta.message || "Senha atualizada com sucesso.", "success");
      exibirNotificacao("Senha atualizada no Supabase e no perfil local.");
    } catch (erro) {
      definirMensagemSenha(erro.message || "Nao foi possivel atualizar a senha.", "error");
    } finally {
      definirBotaoCarregando(botaoAtualizar, false);
    }
  });

  atualizarForcaSenha();
}

function configurarBotoesVisibilidadeSenha(formulario) {
  formulario?.querySelectorAll("[data-password-toggle]").forEach((botaoVisibilidade) => {
    const campoSenha = document.getElementById(botaoVisibilidade.dataset.passwordToggle || "");

    if (!campoSenha) {
      return;
    }

    botaoVisibilidade.dataset.passwordLabel = (botaoVisibilidade.getAttribute("aria-label") || "Mostrar senha")
      .replace(/^(Mostrar|Ocultar)\s+/i, "");
    botaoVisibilidade.addEventListener("click", () => {
      definirVisibilidadeCampoSenha(botaoVisibilidade, campoSenha, campoSenha.type === "password");
    });
  });
}

function definirVisibilidadeCampoSenha(
  botaoVisibilidade,
  campoSenha,
  estaVisivel,
  deveReceberFoco = true,
) {
  const rotuloCampo = botaoVisibilidade.dataset.passwordLabel || "senha";
  const icone = botaoVisibilidade.querySelector("i");

  campoSenha.type = estaVisivel ? "text" : "password";
  botaoVisibilidade.setAttribute("aria-pressed", String(estaVisivel));
  botaoVisibilidade.setAttribute("aria-label", `${estaVisivel ? "Ocultar" : "Mostrar"} ${rotuloCampo}`);
  icone?.classList.toggle("bi-eye", !estaVisivel);
  icone?.classList.toggle("bi-eye-slash", estaVisivel);

  if (deveReceberFoco) {
    campoSenha.focus({ preventScroll: true });
  }
}

function restaurarVisibilidadeSenhas(formulario) {
  formulario?.querySelectorAll("[data-password-toggle]").forEach((botaoVisibilidade) => {
    const campoSenha = document.getElementById(botaoVisibilidade.dataset.passwordToggle || "");

    if (campoSenha && campoSenha.type !== "password") {
      definirVisibilidadeCampoSenha(botaoVisibilidade, campoSenha, false, false);
    }
  });
}

function configurarAvisoMaiusculasAtivasSenha(formulario) {
  const avisoMaiusculasAtivas = document.getElementById("passwordCapsLock");

  if (!formulario || !avisoMaiusculasAtivas) {
    return;
  }

  formulario.querySelectorAll('input[autocomplete$="password"]').forEach((campoSenha) => {
    const atualizarAvisoMaiusculasAtivas = (evento) => {
      avisoMaiusculasAtivas.hidden = !Boolean(evento.getModifierState?.("CapsLock"));
    };

    campoSenha.addEventListener("keydown", atualizarAvisoMaiusculasAtivas);
    campoSenha.addEventListener("keyup", atualizarAvisoMaiusculasAtivas);
    campoSenha.addEventListener("blur", () => {
      avisoMaiusculasAtivas.hidden = true;
    });
  });
}

function definirMensagemSenha(mensagem, tipo) {
  const elementoMensagem = document.getElementById("passwordMessage");

  if (!elementoMensagem) {
    return;
  }

  elementoMensagem.textContent = mensagem;
  elementoMensagem.classList.toggle("show", Boolean(mensagem));
  elementoMensagem.classList.toggle("success", tipo === "success");
  elementoMensagem.classList.toggle("error", tipo === "error");
}

// Os diagnósticos exibem apenas informações disponíveis no navegador do usuário.
function configurarDiagnosticos() {
  atualizarDiagnosticos();
  window.addEventListener("resize", atualizarDiagnosticos);
  window.addEventListener("online", atualizarDiagnosticos);
  window.addEventListener("offline", atualizarDiagnosticos);

  document.getElementById("copyDiagnostics")?.addEventListener("click", async () => {
    atualizarDiagnosticos();

    const informacoes = [
      `Navegador: ${obterTexto("diagBrowser")}`,
      `Sistema operacional: ${obterTexto("diagOs")}`,
      `Largura da tela: ${obterTexto("diagWidth")}`,
      `Status: ${navigator.onLine ? "Online" : "Offline"}`,
      `Idioma: ${navigator.language || "--"}`,
      `Data/hora local: ${obterTexto("diagTime")}`,
      "Versao: TI TECH Assets v1.4.0",
    ].join("\n");

    try {
      await navigator.clipboard.writeText(informacoes);
      exibirNotificacao("Informacoes copiadas para o suporte.");
    } catch {
      exibirNotificacao("Nao foi possivel copiar automaticamente. Selecione os dados manualmente.");
    }
  });

  window.setInterval(atualizarDiagnosticos, 30000);
}

function sincronizarFormularioPreferencias() {
  const preferencias = obterEstadoPreferencias();

  sincronizarControlesCorDestaque(preferencias.accent);
  definirValorSelecionado("theme", preferencias.theme);
  definirValorSelecionado("fontSize", preferencias.fontSize);

  definirControleMarcado("densityToggle", preferencias.density === "compact");
  definirControleMarcado("motionToggle", preferencias.motion === "reduced");
  definirControleMarcado("cursorToggle", preferencias.cursor === "enhanced");
}

function definirValorSelecionado(nome, valor) {
  const campoOpcao = document.querySelector(
    `input[name="${nome}"][value="${escaparCss(valor)}"]`,
  );

  if (campoOpcao) {
    campoOpcao.checked = true;
  }
}

function definirControleMarcado(idElemento, estaMarcado) {
  const campoControle = document.getElementById(idElemento);

  if (campoControle) {
    campoControle.checked = estaMarcado;
  }
}

async function restaurarPreferencias() {
  await salvarAlteracaoPreferencias(
    PREFERENCIAS_INTERFACE_PADRAO,
    "Preferencias restauradas para o padrao do sistema.",
    "Preferencias restauradas para seu usuario.",
  );
}

function atualizarForcaSenha() {
  const senha = document.getElementById("newPassword")?.value || "";
  const confirmacaoSenha = document.getElementById("confirmPassword")?.value || "";
  const resultadoAvaliacao = avaliarSenha(senha, confirmacaoSenha);
  const barraForca = document.getElementById("strengthBar");
  const rotuloForca = document.getElementById("strengthLabel");
  const percentual = Math.round((resultadoAvaliacao.score / 5) * 100);

  if (barraForca) {
    barraForca.style.width = `${percentual}%`;
    barraForca.style.background = resultadoAvaliacao.score >= 4
      ? "#22c55e"
      : resultadoAvaliacao.score >= 3
        ? "#f59e0b"
        : "#e05d5d";
  }

  if (rotuloForca) {
    rotuloForca.textContent = resultadoAvaliacao.label;
  }

  Object.entries(resultadoAvaliacao.rules).forEach(([regra, estaValida]) => {
    const elementoRegra = document.querySelector(`[data-rule="${regra}"]`);
    const icone = elementoRegra?.querySelector("i");

    elementoRegra?.classList.toggle("valid", estaValida);
    icone?.classList.toggle("bi-circle", !estaValida);
    icone?.classList.toggle("bi-check-circle-fill", estaValida);
  });

}

function avaliarSenha(senha, confirmacaoSenha) {
  const regras = {
    length: senha.length >= 8,
    uppercase: /[A-Z]/.test(senha),
    number: /\d/.test(senha),
    special: /[^A-Za-z0-9]/.test(senha),
    match: senha !== "" && senha === confirmacaoSenha,
  };
  const pontuacao = Object.values(regras).filter(Boolean).length;
  const rotulos = ["Digite uma nova senha", "Muito fraca", "Fraca", "Media", "Forte", "Muito forte"];

  return {
    rules: regras,
    score: pontuacao,
    label: rotulos[pontuacao] || rotulos[0],
  };
}

function atualizarDiagnosticos() {
  atualizarTexto("diagBrowser", obterNomeNavegador());
  atualizarTexto("diagOs", obterSistemaOperacional());
  atualizarTexto("diagWidth", `${window.innerWidth}px`);
  atualizarTexto("diagTime", new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "medium",
  }).format(new Date()));
}

function obterNomeNavegador() {
  const agenteUsuario = navigator.userAgent;

  if (agenteUsuario.includes("Edg/")) return "Microsoft Edge";
  if (agenteUsuario.includes("Chrome/")) return "Google Chrome";
  if (agenteUsuario.includes("Firefox/")) return "Mozilla Firefox";
  if (agenteUsuario.includes("Safari/")) return "Safari";

  return "Navegador desconhecido";
}

function obterSistemaOperacional() {
  const plataforma = navigator.platform || "";
  const agenteUsuario = navigator.userAgent;

  if (/Win/i.test(plataforma) || /Windows/i.test(agenteUsuario)) return "Windows";
  if (/Mac/i.test(plataforma)) return "macOS";
  if (/Linux/i.test(plataforma)) return "Linux";
  if (/Android/i.test(agenteUsuario)) return "Android";
  if (/iPhone|iPad/i.test(agenteUsuario)) return "iOS";

  return "Sistema desconhecido";
}

function exibirMensagemPreferencia(mensagem) {
  const elementoMensagem = document.getElementById("preferencesMessage");

  if (!elementoMensagem) return;

  clearTimeout(temporizadorMensagemPreferencia);
  elementoMensagem.textContent = mensagem;
  elementoMensagem.classList.add("show", "success");

  temporizadorMensagemPreferencia = setTimeout(() => {
    elementoMensagem.textContent = "";
    elementoMensagem.classList.remove("show", "success");
  }, TEMPO_EXIBICAO_MENSAGEM_PREFERENCIA_MS);
}

function exibirNotificacao(mensagem) {
  const notificacao = document.getElementById("settingsToast");

  if (!notificacao) return;

  clearTimeout(temporizadorNotificacao);
  notificacao.textContent = mensagem;
  notificacao.classList.add("show");

  temporizadorNotificacao = setTimeout(() => {
    notificacao.classList.remove("show");
  }, TEMPO_EXIBICAO_NOTIFICACAO_MS);
}

// Usa o diálogo global quando disponível e mantém confirm() como fallback progressivo.
function confirmarAcaoConfiguracoes(titulo, texto) {
  return new Promise((resolver) => {
    const sobreposicao = document.createElement("div");
    sobreposicao.className = "settings-confirm-overlay";
    sobreposicao.innerHTML = `
      <section class="settings-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="settingsConfirmTitle">
        <div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h2 id="settingsConfirmTitle">${escaparHtml(titulo)}</h2>
        <p>${escaparHtml(texto)}</p>
        <div class="confirm-actions">
          <button class="secondary-button" type="button" data-confirm-cancel>Cancelar</button>
          <button class="primary-button" type="button" data-confirm-ok>Confirmar</button>
        </div>
      </section>
    `;

    document.body.appendChild(sobreposicao);

    const fecharConfirmacao = (resposta) => {
      sobreposicao.remove();
      resolver(resposta);
    };

    sobreposicao.querySelector("[data-confirm-cancel]")
      ?.addEventListener("click", () => fecharConfirmacao(false));
    sobreposicao.querySelector("[data-confirm-ok]")
      ?.addEventListener("click", () => fecharConfirmacao(true));
    sobreposicao.addEventListener("click", (evento) => {
      if (evento.target === sobreposicao) {
        fecharConfirmacao(false);
      }
    });
    sobreposicao.querySelector("[data-confirm-cancel]")?.focus();
  });
}

function definirBotaoCarregando(botao, estaCarregando, textoCarregando = "Aguarde...") {
  if (!botao) return;

  if (estaCarregando) {
    botao.dataset.originalHtml = botao.innerHTML;
    botao.disabled = true;
    botao.innerHTML = `<i class="bi bi-arrow-repeat"></i>${textoCarregando}`;
    return;
  }

  botao.disabled = false;

  if (botao.dataset.originalHtml) {
    botao.innerHTML = botao.dataset.originalHtml;
    delete botao.dataset.originalHtml;
  }
}

function obterTexto(idElemento) {
  return document.getElementById(idElemento)?.textContent?.trim() || "--";
}

function escaparHtml(valor) {
  return String(valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function escaparCss(valor) {
  if (window.CSS?.escape) {
    return window.CSS.escape(valor);
  }

  return String(valor).replace(/["\\]/g, "\\$&");
}
