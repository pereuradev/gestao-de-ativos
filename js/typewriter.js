const TYPEWRITER_SELECTOR = "[data-typewriter-loop]";
const TYPEWRITER_DEFAULT_PHRASES = [
  "Gestao de ativos com clareza.",
  "Dados confiaveis para a operacao.",
  "Controle simples, rapido e seguro.",
];

document.addEventListener("DOMContentLoaded", initTypewriterLoops);

function initTypewriterLoops() {
  const elements = [...document.querySelectorAll(TYPEWRITER_SELECTOR)];

  if (!elements.length) return;

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;

  elements.forEach((element) => {
    const phrases = getTypewriterPhrases(element);

    if (!phrases.length) return;

    stabilizeTypewriterElement(element, phrases);

    if (prefersReducedMotion || phrases.length === 1) {
      element.textContent = phrases[0];
      return;
    }

    runTypewriter(element, phrases);
  });
}

function getTypewriterPhrases(element) {
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
  let phraseIndex = 0;
  let letterIndex = 0;
  let isDeleting = false;

  const typeDelay = Number(element.dataset.typewriterTypeDelay) || 84;
  const deleteDelay = Number(element.dataset.typewriterDeleteDelay) || 42;
  const readDelay = Number(element.dataset.typewriterReadDelay) || 2450;
  const nextDelay = Number(element.dataset.typewriterNextDelay) || 620;

  const write = () => {
    const phrase = phrases[phraseIndex];
    element.textContent = phrase.slice(0, letterIndex);

    if (!isDeleting && letterIndex < phrase.length) {
      letterIndex += 1;
      setTimeout(write, typeDelay);
      return;
    }

    if (!isDeleting) {
      isDeleting = true;
      setTimeout(write, readDelay);
      return;
    }

    if (letterIndex > 0) {
      letterIndex -= 1;
      setTimeout(write, deleteDelay);
      return;
    }

    isDeleting = false;
    phraseIndex = (phraseIndex + 1) % phrases.length;
    setTimeout(write, nextDelay);
  };

  write();
}
