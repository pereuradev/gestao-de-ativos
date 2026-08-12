(function () {
  const ENDPOINT_RESUMO = "../Backend/processar-solicitacoes-acesso.php?acao=resumo";
  const CHAVE_ULTIMA_SOLICITACAO = "titech-ultima-solicitacao-pendente";
  const INTERVALO_ATUALIZACAO_MS = 20000;
  let contextoAudio = null;
  let consultaEmAndamento = false;

  document.addEventListener("DOMContentLoaded", inicializarNotificacoesSolicitacoes);

  function inicializarNotificacoesSolicitacoes() {
    document.addEventListener("pointerdown", prepararAudioNotificacao, { once: true });
    document.addEventListener("keydown", prepararAudioNotificacao, { once: true });
    atualizarNotificacoesSolicitacoes();
    window.setInterval(atualizarNotificacoesSolicitacoes, INTERVALO_ATUALIZACAO_MS);
  }

  async function atualizarNotificacoesSolicitacoes() {
    if (consultaEmAndamento) return;
    consultaEmAndamento = true;

    try {
      const resposta = await fetch(ENDPOINT_RESUMO, {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const resultado = await resposta.json();

      if (!resposta.ok || !resultado.ok) return;

      const total = Number(resultado.resumo?.pendentes || 0);
      const ultimaPendente = resultado.resumo?.ultima_pendente_em || "";
      atualizarBadgeSolicitacoes(total);
      detectarNovaSolicitacao(ultimaPendente);
    } catch {
      // A falha silenciosa evita interromper a navegacao; a proxima consulta tenta novamente.
    } finally {
      consultaEmAndamento = false;
    }
  }

  function atualizarBadgeSolicitacoes(total) {
    const badge = document.getElementById("sidebarAccessRequestBadge");
    if (!badge) return;

    const quantidade = Math.max(0, Math.trunc(total));
    badge.hidden = quantidade === 0;
    badge.textContent = quantidade > 99 ? "99+" : String(quantidade);
    badge.setAttribute(
      "aria-label",
      `${quantidade} ${quantidade === 1 ? "solicitacao pendente" : "solicitacoes pendentes"}`,
    );
  }

  function detectarNovaSolicitacao(ultimaPendente) {
    const instanteAtual = Date.parse(ultimaPendente);
    const instanteAnterior = Date.parse(obterUltimaSolicitacaoVista());

    if (!Number.isFinite(instanteAtual)) return;

    if (Number.isFinite(instanteAnterior) && instanteAtual > instanteAnterior) {
      exibirAlertaNovaSolicitacao();
    }

    guardarUltimaSolicitacaoVista(ultimaPendente);
  }

  function exibirAlertaNovaSolicitacao() {
    if (typeof window.titechToast === "function") {
      window.titechToast("Novo acesso solicitado", "info");
    } else {
      const aviso = document.createElement("div");
      aviso.className = "access-request-floating-notification";
      aviso.setAttribute("role", "status");
      aviso.innerHTML = '<i class="bi bi-person-plus-fill" aria-hidden="true"></i><span>Novo acesso solicitado</span>';
      document.body.append(aviso);
      window.setTimeout(() => aviso.remove(), 4200);
    }

    emitirSomNovaSolicitacao();
  }

  function prepararAudioNotificacao() {
    const ConstrutorAudio = window.AudioContext || window.webkitAudioContext;
    if (!ConstrutorAudio) return;

    contextoAudio ||= new ConstrutorAudio();
    contextoAudio.resume?.().catch(() => undefined);
  }

  function emitirSomNovaSolicitacao() {
    if (!contextoAudio || contextoAudio.state !== "running") return;

    const oscilador = contextoAudio.createOscillator();
    const volume = contextoAudio.createGain();
    const inicio = contextoAudio.currentTime;

    oscilador.type = "sine";
    oscilador.frequency.setValueAtTime(720, inicio);
    oscilador.frequency.setValueAtTime(920, inicio + 0.12);
    volume.gain.setValueAtTime(0.0001, inicio);
    volume.gain.exponentialRampToValueAtTime(0.16, inicio + 0.02);
    volume.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.28);
    oscilador.connect(volume);
    volume.connect(contextoAudio.destination);
    oscilador.start(inicio);
    oscilador.stop(inicio + 0.3);
  }

  function obterUltimaSolicitacaoVista() {
    try {
      return sessionStorage.getItem(CHAVE_ULTIMA_SOLICITACAO) || "";
    } catch {
      return "";
    }
  }

  function guardarUltimaSolicitacaoVista(valor) {
    try {
      sessionStorage.setItem(CHAVE_ULTIMA_SOLICITACAO, valor);
    } catch {
      // A notificacao visual continua funcionando mesmo sem armazenamento da sessao.
    }
  }
})();
