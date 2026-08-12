const estadoSolicitacaoAcesso = {
  foto: null,
  nomeFoto: "",
  identificadorProcessamentoFoto: 0,
  urlPreviaFoto: "",
};

const TAMANHO_MAXIMO_FOTO_SOLICITACAO_BYTES = 2 * 1024 * 1024;
const MAIOR_DIMENSAO_FOTO_SOLICITACAO_PX = 1280;
const TIPOS_FOTO_SOLICITACAO_PERMITIDOS = ["image/jpeg", "image/png", "image/webp"];
const TIPOS_FOTO_SOLICITACAO_POR_EXTENSAO = {
  heic: "image/heic",
  heif: "image/heif",
  jpeg: "image/jpeg",
  jpg: "image/jpeg",
  png: "image/png",
  webp: "image/webp",
};

document.addEventListener("DOMContentLoaded", inicializarSolicitacaoAcesso);

function inicializarSolicitacaoAcesso() {
  configurarTipoUsuarioSolicitacao();
  configurarFotoSolicitacao();
  configurarMascarasSolicitacao();
  configurarSenhaSolicitacao();
  document
    .getElementById("accessRequestForm")
    ?.addEventListener("submit", enviarSolicitacaoAcesso);
}

function configurarTipoUsuarioSolicitacao() {
  const controle = document.getElementById("requestRoleControl");
  const campo = document.getElementById("requestRole");

  controle?.querySelectorAll("[data-role]").forEach((botao) => {
    botao.addEventListener("click", () => {
      const perfil = botao.dataset.role || "Colaborador";

      controle.dataset.active = perfil;
      campo.value = perfil;
      controle.querySelectorAll("[data-role]").forEach((item) => {
        item.classList.toggle("active", item === botao);
      });
    });
  });
}

function configurarFotoSolicitacao() {
  const entradaArquivo = document.getElementById("requestPhotoFile");
  const entradaCamera = document.getElementById("requestPhotoCamera");

  entradaArquivo?.addEventListener("change", () => tratarAlteracaoFotoSolicitacao(entradaArquivo));
  entradaCamera?.addEventListener("change", () => tratarAlteracaoFotoSolicitacao(entradaCamera, true));
  entradaArquivo?.addEventListener("click", () => prepararAberturaFotoSolicitacao(entradaArquivo));
  entradaCamera?.addEventListener("click", () => prepararAberturaFotoSolicitacao(entradaCamera));

  document.querySelectorAll("[data-photo-label]").forEach((rotulo) => {
    rotulo.addEventListener("keydown", (evento) => {
      if (!["Enter", " "].includes(evento.key)) return;

      evento.preventDefault();
      const entrada = rotulo.dataset.photoLabel === "camera" ? entradaCamera : entradaArquivo;
      entrada?.click();
    });
  });

  window.addEventListener("pagehide", liberarUrlPreviaFotoSolicitacao);
}

function prepararAberturaFotoSolicitacao(entrada) {
  const nome = document.getElementById("requestPhotoName");

  if (entrada) entrada.value = "";
  nome?.classList.remove("is-error");
  if (nome) nome.textContent = "Aguardando a foto...";
}

function tratarAlteracaoFotoSolicitacao(entrada, veioDaCamera = false) {
  const arquivo = entrada?.files?.[0] || null;

  if (arquivo) {
    selecionarFotoSolicitacao(arquivo);
    return;
  }

  if (veioDaCamera) {
    const nome = document.getElementById("requestPhotoName");
    const mensagem = "O celular nao entregou a foto. Toque em Usar Foto antes de voltar.";

    nome?.classList.add("is-error");
    if (nome) nome.textContent = mensagem;
    definirMensagemSolicitacao(mensagem, "error");
  }
}

async function selecionarFotoSolicitacao(arquivo) {
  const nome = document.getElementById("requestPhotoName");
  const imagem = document.getElementById("requestPhotoImage");
  const previa = document.getElementById("requestPhotoPreview");

  if (!arquivo) return;

  if (!arquivoEhImagemSolicitacao(arquivo)) {
    definirMensagemSolicitacao("Use uma foto valida.", "error");
    return;
  }

  const identificadorProcessamento = ++estadoSolicitacaoAcesso.identificadorProcessamentoFoto;
  estadoSolicitacaoAcesso.foto = null;
  estadoSolicitacaoAcesso.nomeFoto = "";
  nome.classList.remove("is-error");
  nome.textContent = "Carregando previa...";
  definirMensagemSolicitacao("Carregando foto...", "loading");

  try {
    await exibirPreviaFotoSolicitacao(arquivo, imagem, previa);

    if (estadoSolicitacaoAcesso.identificadorProcessamentoFoto !== identificadorProcessamento) return;

    nome.textContent = "Otimizando foto para envio...";
    const fotoPreparada = await prepararFotoSolicitacao(arquivo);

    if (estadoSolicitacaoAcesso.identificadorProcessamentoFoto !== identificadorProcessamento) return;

    estadoSolicitacaoAcesso.foto = fotoPreparada.arquivo;
    estadoSolicitacaoAcesso.nomeFoto = fotoPreparada.nome;
    nome.textContent = fotoPreparada.foiReduzida ? "Foto otimizada e pronta" : fotoPreparada.nome;
    definirMensagemSolicitacao(
      fotoPreparada.foiReduzida ? "Foto reduzida e pronta para envio." : "Foto pronta para envio.",
      "success",
    );
  } catch (erroFoto) {
    if (estadoSolicitacaoAcesso.identificadorProcessamentoFoto !== identificadorProcessamento) return;

    estadoSolicitacaoAcesso.foto = null;
    estadoSolicitacaoAcesso.nomeFoto = "";
    const mensagemErro = erroFoto instanceof Error
      ? erroFoto.message
      : "Nao foi possivel carregar a foto. Tente novamente.";

    nome.classList.add("is-error");
    nome.textContent = mensagemErro;
    definirMensagemSolicitacao(mensagemErro, "error");
  }
}

async function exibirPreviaFotoSolicitacao(arquivo, imagem, previa) {
  liberarUrlPreviaFotoSolicitacao();

  const urlPrevia = URL.createObjectURL(arquivo);
  estadoSolicitacaoAcesso.urlPreviaFoto = urlPrevia;

  try {
    await carregarOrigemImagemSolicitacao(imagem, urlPrevia);
    previa.classList.add("has-photo");
  } catch (erroPrevia) {
    limparPreviaFotoSolicitacao(imagem, previa);
    throw erroPrevia;
  }
}

function limparPreviaFotoSolicitacao(imagem, previa) {
  liberarUrlPreviaFotoSolicitacao();
  imagem.removeAttribute("src");
  previa.classList.remove("has-photo");
}

function liberarUrlPreviaFotoSolicitacao() {
  if (!estadoSolicitacaoAcesso.urlPreviaFoto) return;

  URL.revokeObjectURL(estadoSolicitacaoAcesso.urlPreviaFoto);
  estadoSolicitacaoAcesso.urlPreviaFoto = "";
}

async function prepararFotoSolicitacao(arquivo) {
  const tipo = obterTipoFotoSolicitacao(arquivo);

  if (
    TIPOS_FOTO_SOLICITACAO_PERMITIDOS.includes(tipo)
    && arquivo.size <= TAMANHO_MAXIMO_FOTO_SOLICITACAO_BYTES
  ) {
    return {
      arquivo,
      nome: arquivo.name || "foto.jpg",
      foiReduzida: false,
    };
  }

  const urlOriginal = URL.createObjectURL(arquivo);
  let fotoReduzida;

  try {
    const imagemOriginal = await criarImagemSolicitacao(urlOriginal);
    fotoReduzida = await reduzirFotoSolicitacao(imagemOriginal);
  } finally {
    URL.revokeObjectURL(urlOriginal);
  }

  return {
    arquivo: fotoReduzida,
    nome: criarNomeFotoReduzidaSolicitacao(arquivo.name),
    foiReduzida: true,
  };
}

function arquivoEhImagemSolicitacao(arquivo) {
  const tipo = obterTipoFotoSolicitacao(arquivo);
  return tipo.startsWith("image/");
}

function obterTipoFotoSolicitacao(arquivo) {
  const tipoInformado = String(arquivo?.type || "").toLowerCase().trim();

  if (tipoInformado === "image/jpg") return "image/jpeg";
  if (tipoInformado) return tipoInformado;

  const extensao = String(arquivo?.name || "").split(".").pop()?.toLowerCase() || "";
  return TIPOS_FOTO_SOLICITACAO_POR_EXTENSAO[extensao] || "";
}

function criarImagemSolicitacao(origem) {
  return new Promise((resolver, rejeitar) => {
    const imagem = new Image();

    imagem.addEventListener("load", () => resolver(imagem), { once: true });
    imagem.addEventListener(
      "error",
      () => rejeitar(new Error("O formato da foto nao pode ser processado neste aparelho.")),
      { once: true },
    );
    imagem.src = origem;
  });
}

function carregarOrigemImagemSolicitacao(imagem, origem) {
  return new Promise((resolver, rejeitar) => {
    const aoCarregar = () => {
      imagem.removeEventListener("error", aoFalhar);
      resolver();
    };
    const aoFalhar = () => {
      imagem.removeEventListener("load", aoCarregar);
      rejeitar(new Error("Nao foi possivel montar a previa da foto."));
    };

    imagem.addEventListener("load", aoCarregar, { once: true });
    imagem.addEventListener("error", aoFalhar, { once: true });
    imagem.src = origem;
  });
}

async function reduzirFotoSolicitacao(imagem) {
  const maiorDimensao = Math.max(imagem.naturalWidth, imagem.naturalHeight);
  const escala = Math.min(1, MAIOR_DIMENSAO_FOTO_SOLICITACAO_PX / maiorDimensao);
  const largura = Math.max(1, Math.round(imagem.naturalWidth * escala));
  const altura = Math.max(1, Math.round(imagem.naturalHeight * escala));
  const tela = document.createElement("canvas");
  const contexto = tela.getContext("2d");

  if (!contexto || !imagem.naturalWidth || !imagem.naturalHeight) {
    throw new Error("Nao foi possivel processar a foto capturada.");
  }

  tela.width = largura;
  tela.height = altura;
  contexto.fillStyle = "#ffffff";
  contexto.fillRect(0, 0, largura, altura);
  contexto.drawImage(imagem, 0, 0, largura, altura);

  for (const qualidade of [0.86, 0.76, 0.66, 0.56]) {
    const arquivoReduzido = await converterTelaEmFotoSolicitacao(tela, qualidade);

    if (arquivoReduzido.size <= TAMANHO_MAXIMO_FOTO_SOLICITACAO_BYTES) {
      return arquivoReduzido;
    }
  }

  throw new Error("Nao foi possivel reduzir a foto para o tamanho permitido.");
}

function converterTelaEmFotoSolicitacao(tela, qualidade) {
  return new Promise((resolver, rejeitar) => {
    tela.toBlob(
      (arquivo) => {
        if (arquivo) {
          resolver(arquivo);
          return;
        }

        rejeitar(new Error("Nao foi possivel converter a foto capturada."));
      },
      "image/jpeg",
      qualidade,
    );
  });
}

function criarNomeFotoReduzidaSolicitacao(nomeOriginal) {
  const nomeBase = String(nomeOriginal || "foto")
    .replace(/\.[^.]+$/, "")
    .replace(/[^a-z0-9_-]+/gi, "-")
    .replace(/^-+|-+$/g, "") || "foto";

  return `${nomeBase}.jpg`;
}

function configurarMascarasSolicitacao() {
  const formulario = document.getElementById("accessRequestForm");

  formulario?.elements.rg?.addEventListener("input", (evento) => {
    const numeros = obterNumerosSolicitacao(evento.target.value).slice(0, 9);
    evento.target.value = numeros
      .replace(/(\d{2})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d{1})$/, "$1-$2");
  });

  formulario?.elements.cpf?.addEventListener("input", (evento) => {
    const numeros = obterNumerosSolicitacao(evento.target.value).slice(0, 11);
    evento.target.value = numeros
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  });

  formulario?.elements.celular?.addEventListener("input", (evento) => {
    const numeros = obterNumerosSolicitacao(evento.target.value).slice(0, 11);
    evento.target.value = numeros
      .replace(/^(\d{2})(\d)/, "($1) $2")
      .replace(/(\d{5})(\d{1,4})$/, "$1-$2");
  });
}

function configurarSenhaSolicitacao() {
  const senha = document.getElementById("requestPassword");
  const botao = document.querySelector(".password-visibility");

  senha?.addEventListener("input", atualizarForcaSenhaSolicitacao);
  botao?.addEventListener("click", () => {
    const ocultar = senha.type === "password";
    senha.type = ocultar ? "text" : "password";
    botao.setAttribute("aria-label", ocultar ? "Ocultar senha" : "Mostrar senha");
    botao.querySelector("i").className = ocultar ? "bi bi-eye-slash" : "bi bi-eye";
  });
}

function atualizarForcaSenhaSolicitacao() {
  const senha = document.getElementById("requestPassword")?.value || "";
  const barra = document.getElementById("requestPasswordBar");
  const texto = document.getElementById("requestPasswordText");
  let pontos = 0;

  if (senha.length >= 6) pontos += 1;
  if (senha.length >= 10) pontos += 1;
  if (/[A-Z]/.test(senha)) pontos += 1;
  if (/\d/.test(senha)) pontos += 1;
  if (/[^A-Za-z0-9]/.test(senha)) pontos += 1;

  const niveis = [
    ["0%", "#64748b", "Forca da senha: aguardando"],
    ["35%", "#ef4444", "Forca da senha: baixa"],
    ["68%", "#f59e0b", "Forca da senha: media"],
    ["100%", "#34d399", "Forca da senha: alta"],
  ];
  const indice = senha === "" ? 0 : pontos <= 2 ? 1 : pontos <= 4 ? 2 : 3;

  barra.style.width = niveis[indice][0];
  barra.style.background = niveis[indice][1];
  texto.textContent = niveis[indice][2];
}

async function enviarSolicitacaoAcesso(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botao = document.getElementById("accessRequestSubmit");
  const erro = validarFormularioSolicitacao(formulario);

  if (erro) {
    definirMensagemSolicitacao(erro, "error");
    return;
  }

  const dados = new FormData(formulario);
  dados.append(
    "foto",
    estadoSolicitacaoAcesso.foto,
    estadoSolicitacaoAcesso.nomeFoto || estadoSolicitacaoAcesso.foto.name || "foto.jpg",
  );
  alternarEnvioSolicitacao(botao, true);
  definirMensagemSolicitacao("Enviando solicitacao...", "loading");

  try {
    const resposta = await fetch("../Backend/solicitar-acesso.php", {
      method: "POST",
      body: dados,
      headers: { Accept: "application/json" },
    });
    const resultado = await resposta.json();

    if (!resposta.ok || !resultado.ok) {
      throw new Error(resultado.message || "Nao foi possivel enviar a solicitacao.");
    }

    formulario.hidden = true;
    document.getElementById("accessRequestSuccess").hidden = false;
  } catch (erroEnvio) {
    definirMensagemSolicitacao(
      erroEnvio instanceof Error ? erroEnvio.message : "Nao foi possivel enviar a solicitacao.",
      "error",
    );
  } finally {
    alternarEnvioSolicitacao(botao, false);
  }
}

function validarFormularioSolicitacao(formulario) {
  if (!formulario.checkValidity()) {
    formulario.reportValidity();
    return "Revise os campos obrigatorios antes de continuar.";
  }

  if (!estadoSolicitacaoAcesso.foto) {
    return "Selecione ou tire uma foto para continuar.";
  }

  if ((formulario.elements.senha?.value || "").length < 6) {
    return "A senha precisa ter pelo menos 6 caracteres.";
  }

  return "";
}

function alternarEnvioSolicitacao(botao, enviando) {
  if (!botao) return;

  botao.disabled = enviando;
  botao.querySelector("span").textContent = enviando ? "Enviando..." : "Solicitar acesso";
  botao.querySelector("i").className = enviando ? "bi bi-arrow-repeat spinning" : "bi bi-send-check";
}

function definirMensagemSolicitacao(mensagem, tipo) {
  const elemento = document.getElementById("accessRequestMessage");

  if (!elemento) return;

  elemento.textContent = mensagem;
  elemento.className = `request-message${tipo ? ` is-${tipo}` : ""}`;
}

function obterNumerosSolicitacao(valor) {
  return String(valor || "").replace(/\D/g, "");
}
