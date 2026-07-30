// Controla os filtros enviados ao servidor e o download dos relatórios de ativos.
// Depende dos helpers globais de interface carregados por base-interface.js e feedback-interface.js.

document.addEventListener("DOMContentLoaded", inicializarPagina);

let temporizadorBuscaAtivo = null;
let exportacaoAtivoEmAndamento = false;

function inicializarPagina() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarFiltrosAtivo();
  configurarExportacoesAtivos();
}

// Os filtros são enviados ao servidor; a busca usa atraso curto para evitar requisições a cada tecla.
function configurarFiltrosAtivo() {
  const formulario = document.getElementById("assetFiltersForm");

  if (!formulario) {
    return;
  }

  document.getElementById("assetSearch")?.addEventListener("input", () => {
    window.clearTimeout(temporizadorBuscaAtivo);

    temporizadorBuscaAtivo = window.setTimeout(() => {
      redefinirPaginaAtivoEEnviar(formulario);
    }, 450);
  });

  [
    "assetStatusFilter",
    "assetCategoryFilter",
    "assetBrandFilter",
    "assetLocationFilter",
    "assetPerPage",
  ].forEach((idCampo) => {
    document.getElementById(idCampo)?.addEventListener("change", () => {
      redefinirPaginaAtivoEEnviar(formulario);
    });
  });

  document
    .getElementById("clearAssetFilters")
    ?.addEventListener("click", () => {
      window.location.href = "ativos.php";
    });
}

function redefinirPaginaAtivoEEnviar(formulario) {
  const campoEntradaPagina = formulario.querySelector('input[name="pagina"]');

  if (campoEntradaPagina) {
    campoEntradaPagina.value = "1";
  }

  if (typeof formulario.requestSubmit === "function") {
    formulario.requestSubmit();
    return;
  }

  formulario.submit();
}

// A exportação reutiliza a URL filtrada renderizada pelo PHP e bloqueia downloads concorrentes.
function configurarExportacoesAtivos() {
  document.querySelectorAll("[data-asset-export]").forEach((botao) => {
    botao.addEventListener("click", () => exportarArquivoAtivos(botao));
  });
}

async function exportarArquivoAtivos(botao) {
  if (exportacaoAtivoEmAndamento) {
    return;
  }

  const urlExportacao = botao.dataset.exportUrl;
  const configuracao = obterConfiguracaoExportacaoAtivo(botao.dataset.exportFormat);

  if (!urlExportacao || !configuracao) {
    notificarExportacaoAtivo("O endereco de exportacao nao esta disponivel.", true);
    return;
  }

  exportacaoAtivoEmAndamento = true;
  definirCarregandoBotoesExportacaoAtivo(botao, true);
  limparStatusExportacaoAtivo();

  try {
    const resposta = await fetch(urlExportacao, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: `${configuracao.contentType}, application/json` },
    });
    const tipoConteudo = resposta.headers.get("content-type") || "";

    if (!resposta.ok || !tipoConteudo.includes(configuracao.contentType)) {
      throw new Error(await lerErroExportacaoAtivo(resposta, configuracao.label));
    }

    const blobArquivo = await resposta.blob();

    if (!blobArquivo.size) {
      throw new Error(`O servidor retornou um ${configuracao.label} vazio. Tente novamente.`);
    }

    baixarArquivoAtivo(blobArquivo, obterNomeArquivoExportacaoAtivo(resposta, configuracao.fallbackFilename));
    notificarExportacaoAtivo(`${configuracao.label} gerado com sucesso.`, false);
  } catch (erro) {
    const mensagem = erro instanceof TypeError
      ? `Servidor indisponivel. Nao foi possivel gerar o ${configuracao.label} agora.`
      : erro?.message || `Nao foi possivel gerar o ${configuracao.label} agora.`;

    notificarExportacaoAtivo(mensagem, true);
  } finally {
    exportacaoAtivoEmAndamento = false;
    definirCarregandoBotoesExportacaoAtivo(botao, false);
  }
}

function obterConfiguracaoExportacaoAtivo(formato) {
  if (formato === "xlsx") {
    return {
      contentType: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      fallbackFilename: "relatorio-ativos.xlsx",
      label: "Excel",
    };
  }

  if (formato === "csv") {
    return {
      contentType: "text/csv",
      fallbackFilename: "ativos-titech.csv",
      label: "CSV",
    };
  }

  if (formato === "pdf") {
    return {
      contentType: "application/pdf",
      fallbackFilename: "relatorio-ativos.pdf",
      label: "PDF",
    };
  }

  return null;
}

async function lerErroExportacaoAtivo(resposta, rotuloFormato) {
  const tipoConteudo = resposta.headers.get("content-type") || "";

  if (tipoConteudo.includes("application/json")) {
    const dadosErro = await resposta.json().catch(() => null);

    if (dadosErro?.message) {
      return dadosErro.message;
    }
  }

  if (resposta.status === 401) {
    return `Sua sessao expirou. Entre novamente antes de exportar o ${rotuloFormato}.`;
  }

  if (resposta.status === 403) {
    return "Voce nao tem permissao para exportar este relatorio.";
  }

  return `Nao foi possivel gerar o ${rotuloFormato}. Atualize a pagina e tente novamente.`;
}

function obterNomeArquivoExportacaoAtivo(resposta, nomeArquivoPadrao) {
  const disposicao = resposta.headers.get("content-disposition") || "";
  const correspondenciaCodificada = disposicao.match(/filename\*=UTF-8''([^;]+)/i);

  if (correspondenciaCodificada?.[1]) {
    return decodeURIComponent(correspondenciaCodificada[1]).replace(/[\\/:*?"<>|]/g, "-");
  }

  const correspondenciaNomeArquivo = disposicao.match(/filename="?([^";]+)"?/i);

  return correspondenciaNomeArquivo?.[1]?.replace(/[\\/:*?"<>|]/g, "-") || nomeArquivoPadrao;
}

// O arquivo recebido vira uma URL temporária, revogada logo após iniciar o download.
function baixarArquivoAtivo(blob, nomeArquivo) {
  const urlObjeto = URL.createObjectURL(blob);
  const atalho = document.createElement("a");

  atalho.href = urlObjeto;
  atalho.download = nomeArquivo;
  atalho.hidden = true;
  document.body.append(atalho);
  atalho.click();
  atalho.remove();

  window.setTimeout(() => URL.revokeObjectURL(urlObjeto), 1000);
}

function definirCarregandoBotoesExportacaoAtivo(botaoAtivo, estaCarregando) {
  document.querySelectorAll("[data-asset-export]").forEach((botao) => {
    const icone = botao.querySelector("i");
    const rotulo = botao.querySelector("span");
    const ehAtivo = botao === botaoAtivo;

    botao.disabled = estaCarregando;
    botao.setAttribute("aria-busy", estaCarregando && ehAtivo ? "true" : "false");

    if (icone) {
      icone.className = estaCarregando && ehAtivo
        ? "bi bi-arrow-repeat asset-export-spinner"
        : botao.dataset.defaultIcon || "bi bi-download";
    }

    if (rotulo) {
      rotulo.textContent = estaCarregando && ehAtivo
        ? `Gerando ${obterConfiguracaoExportacaoAtivo(botao.dataset.exportFormat)?.label || "arquivo"}...`
        : botao.dataset.defaultLabel || "Exportar";
    }
  });
}

function limparStatusExportacaoAtivo() {
  const status = document.getElementById("assetExportStatus");

  if (!status) {
    return;
  }

  status.hidden = true;
  status.classList.remove("is-error", "is-success");
  status.textContent = "";
}

function notificarExportacaoAtivo(mensagem, ocorreuErro) {
  const status = document.getElementById("assetExportStatus");
  const tipo = ocorreuErro ? "error" : "success";

  if (status) {
    status.hidden = false;
    status.classList.toggle("is-error", ocorreuErro);
    status.classList.toggle("is-success", !ocorreuErro);
    status.textContent = mensagem;
  }

  window.titechToast?.(mensagem, tipo);
}
