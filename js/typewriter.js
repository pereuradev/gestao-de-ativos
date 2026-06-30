const TYPEWRITER_SELECTOR = "[data-typewriter-loop]";

// Frases usadas quando o HTML nao informa uma lista propria.
const TYPEWRITER_DEFAULT_PHRASES = [
  "Gestao de ativos com clareza.",
  "Dados confiaveis para a operacao.",
  "Controle simples, rapido e seguro.",
];
const typewriterTimers = new WeakMap();

document.addEventListener("DOMContentLoaded", initTypewriterLoops);
window.addEventListener("titech:motion-change", initTypewriterLoops);

function initTypewriterLoops() {
  // Procura todos os elementos marcados para animacao de escrita.
  const elements = [...document.querySelectorAll(TYPEWRITER_SELECTOR)];

  if (!elements.length) return;

  const shouldReduceMotion = isReducedMotionActive();

  elements.forEach((element) => {
    // Cada elemento pode ter suas proprias frases em data-typewriter-phrases.
    const phrases = getTypewriterPhrases(element);

    if (!phrases.length) return;

    stopTypewriter(element);
    stabilizeTypewriterElement(element, phrases);

    if (shouldReduceMotion || phrases.length === 1) {
      setStaticTypewriterText(element, phrases);
      return;
    }

    runTypewriter(element, phrases);
  });
}

function getTypewriterPhrases(element) {
  // As frases no HTML sao separadas por pipe: "frase 1|frase 2".
  const rawPhrases = element.dataset.typewriterPhrases || "";
  const phrases = rawPhrases
    .split("|")
    .map((phrase) => phrase.trim())
    .filter(Boolean);

  if (phrases.length) {
    return phrases;
  }

  const fallback = element.textContent.trim();

  return fallback ? [fallback] : TYPEWRITER_DEFAULT_PHRASES;
}

function stabilizeTypewriterElement(element, phrases) {
  // Mede a maior frase antes da animacao para evitar pulo de layout.
  const originalText = element.textContent;
  const longestPhrase = phrases.reduce((longest, phrase) => (
    phrase.length > longest.length ? phrase : longest
  ), "");
  const currentTransition = element.style.transition;
  const heights = [];

  element.style.transition = "none";
  element.style.setProperty(
    "--typewriter-min",
    `${Math.max(longestPhrase.length, 12)}ch`,
  );

  phrases.forEach((phrase) => {
    // Testa cada frase no proprio elemento para descobrir a altura maxima real.
    element.textContent = phrase;
    heights.push(element.getBoundingClientRect().height);
  });

  const stableHeight = Math.ceil(Math.max(...heights, 0));

  if (stableHeight > 0) {
    element.style.setProperty("--typewriter-min-height", `${stableHeight}px`);
  }

  element.textContent = originalText;
  element.style.transition = currentTransition;
}

function runTypewriter(element, phrases) {
  // Controla qual frase e qual letra estao sendo exibidas neste momento.
  let phraseIndex = 0;
  let letterIndex = 0;
  let isDeleting = false;

  const typeDelay = Number(element.dataset.typewriterTypeDelay) || 84;
  const deleteDelay = Number(element.dataset.typewriterDeleteDelay) || 42;
  const readDelay = Number(element.dataset.typewriterReadDelay) || 2450;
  const nextDelay = Number(element.dataset.typewriterNextDelay) || 620;

  const write = () => {
    // Escreve, espera, apaga e passa para a proxima frase em loop.
    const phrase = phrases[phraseIndex];
    element.textContent = phrase.slice(0, letterIndex);

    if (!isDeleting && letterIndex < phrase.length) {
      letterIndex += 1;
      scheduleTypewriterStep(element, write, typeDelay);
      return;
    }

    if (!isDeleting) {
      isDeleting = true;
      scheduleTypewriterStep(element, write, readDelay);
      return;
    }

    if (letterIndex > 0) {
      letterIndex -= 1;
      scheduleTypewriterStep(element, write, deleteDelay);
      return;
    }

    isDeleting = false;
    phraseIndex = (phraseIndex + 1) % phrases.length;
    scheduleTypewriterStep(element, write, nextDelay);
  };

  write();
}

function setStaticTypewriterText(element, phrases) {
  // Com menos movimento, o titulo fica legivel e nao simula digitacao.
  element.textContent = phrases[0];
}

function stopTypewriter(element) {
  const timer = typewriterTimers.get(element);

  if (timer) {
    clearTimeout(timer);
    typewriterTimers.delete(element);
  }
}

function scheduleTypewriterStep(element, callback, delay) {
  const timer = setTimeout(callback, delay);

  typewriterTimers.set(element, timer);
}

function isReducedMotionActive() {
  if (document.body?.dataset.motion === "reduced") {
    return true;
  }

  if (getSavedMotionPreference() === "reduced") {
    return true;
  }

  return window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
}

function getSavedMotionPreference() {
  try {
    return localStorage.getItem("titech-motion");
  } catch {
    return null;
  }
}
