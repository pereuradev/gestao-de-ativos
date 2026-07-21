// Este módulo cria o efeito de digitação, apagamento e troca de frases nos títulos.
// O seletor procura qualquer elemento HTML que possua o atributo data-typewriter-loop.
// Guardar o seletor em uma constante evita repetir a mesma string em outras funções.
const TYPEWRITER_SELECTOR = "[data-typewriter-loop]";

// Esta lista é o plano B usado quando o HTML não fornece frases próprias.
const TYPEWRITER_DEFAULT_PHRASES = [
  // Primeira frase padrão apresentada ao usuário.
  "Gestao de ativos com clareza.",
  // Segunda frase padrão do ciclo.
  "Dados confiaveis para a operacao.",
  // Terceira frase padrão; depois dela, o ciclo volta para a primeira.
  "Controle simples, rapido e seguro.",
];
// WeakMap relaciona cada elemento ao seu temporizador sem impedir que o navegador libere elementos removidos.
// Isso também permite cancelar somente a animação pertencente ao elemento que está sendo reiniciado.
const typewriterTimers = new WeakMap();

// Aguarda o HTML terminar de ser interpretado antes de procurar os títulos na página.
document.addEventListener("DOMContentLoaded", initTypewriterLoops);
// Reinicia as animações quando outra parte do sistema altera a preferência de movimento.
window.addEventListener("titech:motion-change", initTypewriterLoops);

// Função principal: encontra, prepara e inicia todos os títulos animados da página.
function initTypewriterLoops() {
  // querySelectorAll devolve uma NodeList; o operador ... cria um Array para facilitar o uso.
  // TYPEWRITER_SELECTOR limita a busca aos elementos que optaram pelo efeito no HTML.
  const elements = [...document.querySelectorAll(TYPEWRITER_SELECTOR)];

  // Se a página não possui nenhum título animável, encerra cedo e evita trabalho desnecessário.
  if (!elements.length) return;

  // Calcula uma única vez se as animações devem ser reduzidas para esta inicialização.
  const shouldReduceMotion = isReducedMotionActive();

  // Percorre cada título separadamente, pois cada um pode ter frases e tempos diferentes.
  elements.forEach((element) => {
    // Lê as frases configuradas no próprio elemento ou escolhe um fallback adequado.
    const phrases = getTypewriterPhrases(element);

    // Sem frases não existe conteúdo para animar, então este elemento é ignorado.
    if (!phrases.length) return;

    // Cancela um temporizador anterior para impedir duas animações simultâneas no mesmo título.
    stopTypewriter(element);
    // Mede o espaço necessário antes de animar para evitar saltos no layout.
    stabilizeTypewriterElement(element, phrases);

    // Acessibilidade: movimento reduzido e listas com uma única frase não precisam de loop.
    if (shouldReduceMotion || phrases.length === 1) {
      // Mostra a primeira frase completa, sem simular digitação.
      setStaticTypewriterText(element, phrases);
      // Encerra somente esta iteração do forEach e impede a chamada de runTypewriter abaixo.
      return;
    }

    // Com várias frases e movimento permitido, inicia o ciclo de escrita e apagamento.
    runTypewriter(element, phrases);
  });
}

// Extrai e normaliza a lista de frases pertencente a um elemento.
function getTypewriterPhrases(element) {
  // dataset.typewriterPhrases corresponde ao atributo HTML data-typewriter-phrases.
  // O operador || usa uma string vazia quando o atributo não existe.
  const rawPhrases = element.dataset.typewriterPhrases || "";
  // A transformação é dividida em etapas encadeadas para produzir um Array limpo.
  const phrases = rawPhrases
    // split separa a string pelo caractere |: "Uma|Duas" vira ["Uma", "Duas"].
    .split("|")
    // map aplica trim em cada frase para remover espaços no começo e no fim.
    .map((phrase) => phrase.trim())
    // filter(Boolean) remove strings vazias que poderiam surgir de separadores duplicados.
    .filter(Boolean);
  // Se o atributo produziu ao menos uma frase válida, ele tem prioridade.
  if (phrases.length) {
    // Devolve imediatamente a configuração específica deste elemento.
    return phrases;
  }

  // Sem atributo válido, tenta aproveitar o texto que já estava escrito dentro do título.
  const fallback = element.textContent.trim();

  // O ternário cria uma lista com o texto original ou, se ele estiver vazio, usa as frases padrão.
  return fallback ? [fallback] : TYPEWRITER_DEFAULT_PHRASES;
}

// Reserva largura e altura suficientes para que a troca de frases não desloque outros componentes.
function stabilizeTypewriterElement(element, phrases) {
  // Guarda o conteúdo original porque as próximas medições substituirão o texto temporariamente.
  const originalText = element.textContent;
  // reduce compara todas as frases e mantém no acumulador aquela com mais caracteres.
  const longestPhrase = phrases.reduce(
    (longest, phrase) =>
      // O ternário devolve a frase atual quando ela supera a maior encontrada até aqui.
      phrase.length > longest.length ? phrase : longest,
      // A string vazia é o valor inicial do acumulador na primeira comparação.
    "",
  );
  // Salva a transição inline atual para restaurá-la depois das medições.
  const currentTransition = element.style.transition;
  // Este Array receberá a altura real que cada frase ocupa no elemento.
  const heights = [];

  // Desativa transições durante a medição para o usuário não enxergar as trocas temporárias.
  element.style.transition = "none";
  // setProperty permite definir uma variável CSS personalizada diretamente pelo JavaScript.
  element.style.setProperty(
    // --typewriter-min é lida no CSS para reservar uma largura mínima.
    "--typewriter-min",
    // Math.max garante ao menos 12ch; ch aproxima a largura pela quantidade de caracteres.
    `${Math.max(longestPhrase.length, 12)}ch`,
  );

  // Testa cada frase porque textos com tamanhos diferentes também podem quebrar em mais linhas.
  phrases.forEach((phrase) => {
    // Coloca temporariamente a frase atual dentro do elemento para medir seu tamanho real.
    element.textContent = phrase;
    // getBoundingClientRect calcula a altura renderizada; push guarda o resultado no Array.
    heights.push(element.getBoundingClientRect().height);
  });

  // Math.max encontra a maior altura, o 0 evita problemas em lista vazia e ceil arredonda para cima.
  const stableHeight = Math.ceil(Math.max(...heights, 0));

  // Só cria a variável de altura quando o navegador conseguiu medir um valor positivo.
  if (stableHeight > 0) {
    // A maior altura vira uma variável CSS em pixels, mantendo o título estável durante o loop.
    element.style.setProperty("--typewriter-min-height", `${stableHeight}px`);
  }

  // Restaura o texto que existia antes das medições temporárias.
  element.textContent = originalText;
  // Restaura também a transição inline original do elemento.
  element.style.transition = currentTransition;
}

// Executa a máquina de estados responsável por escrever, pausar, apagar e trocar a frase.
function runTypewriter(element, phrases) {
  // Índice da frase atual dentro do Array; começa na primeira posição, que é 0.
  let phraseIndex = 0;
  // Quantidade de caracteres que deve aparecer; começa em 0 para iniciar com texto vazio.
  let letterIndex = 0;
  // false significa fase de escrita; true significa fase de apagamento.
  let isDeleting = false;

  // Converte data-typewriter-type-delay em número ou usa 84 ms entre letras escritas.
  const typeDelay = Number(element.dataset.typewriterTypeDelay) || 84;
  // Define a velocidade de apagamento; 42 ms torna a remoção mais rápida que a escrita.
  const deleteDelay = Number(element.dataset.typewriterDeleteDelay) || 42;
  // Mantém a frase completa visível por 2450 ms antes de começar a apagar.
  const readDelay = Number(element.dataset.typewriterReadDelay) || 2450;
  // Espera 620 ms com o título vazio antes de iniciar a próxima frase.
  // Como o código usa ||, um valor 0 no HTML também cairia no tempo padrão.
  const nextDelay = Number(element.dataset.typewriterNextDelay) || 620;

  // Esta arrow function representa um passo do ciclo e agenda a própria próxima execução.
  const write = () => {
    // Obtém do Array a frase apontada pelo índice atual.
    const phrase = phrases[phraseIndex];
    // slice cria o trecho entre a posição 0 e letterIndex, sem incluir o limite final.
    element.textContent = phrase.slice(0, letterIndex);

    // Enquanto estiver escrevendo e ainda faltarem letras, entra neste bloco.
    if (!isDeleting && letterIndex < phrase.length) {
      // Aumenta o limite para que o próximo passo mostre mais um caractere.
      letterIndex += 1;
      // Agenda o próximo passo usando o intervalo definido para digitação.
      scheduleTypewriterStep(element, write, typeDelay);
      // Evita que o mesmo passo continue e entre nas regras de pausa ou apagamento.
      return;
    }

    // Chegar aqui sem apagar significa que a frase acabou de ser escrita por completo.
    if (!isDeleting) {
      // Muda o estado para que os próximos passos removam caracteres.
      isDeleting = true;
      // Agenda a primeira remoção somente depois do tempo reservado para leitura.
      scheduleTypewriterStep(element, write, readDelay);
      // Encerra este passo após agendar a continuação.
      return;
    }

    // Durante o apagamento, continua enquanto ainda existir algum caractere visível.
    if (letterIndex > 0) {
      // Reduz o limite para remover uma letra na próxima execução.
      letterIndex -= 1;
      // Agenda o próximo passo com a velocidade específica de apagamento.
      scheduleTypewriterStep(element, write, deleteDelay);
      // Interrompe o passo atual para não trocar de frase antes de terminar de apagar.
      return;
    }

    // Quando letterIndex chega a 0, volta ao estado de escrita.
    isDeleting = false;
    // O resto da divisão (%) volta o índice para 0 depois da última frase, criando o loop.
    phraseIndex = (phraseIndex + 1) % phrases.length;
    // Agenda o início da próxima frase após a pausa com o título vazio.
    scheduleTypewriterStep(element, write, nextDelay);
  };

  // Chama write imediatamente; as execuções seguintes serão controladas por setTimeout.
  write();
}

// Apresenta uma versão estática quando a animação não é necessária ou não é recomendada.
function setStaticTypewriterText(element, phrases) {
  // A primeira frase permanece completa e legível, sem alterações posteriores.
  element.textContent = phrases[0];
}

// Cancela qualquer continuação já agendada para um elemento específico.
function stopTypewriter(element) {
  // Consulta no WeakMap o identificador do último setTimeout associado ao elemento.
  const timer = typewriterTimers.get(element);

  // Só tenta cancelar quando existe um temporizador registrado.
  if (timer) {
    // clearTimeout impede que o callback agendado volte a executar.
    clearTimeout(timer);
    // Remove a associação antiga para que o WeakMap represente apenas timers ativos.
    typewriterTimers.delete(element);
  }
}

// Agenda o próximo passo da animação e guarda uma forma de cancelá-lo depois.
function scheduleTypewriterStep(element, callback, delay) {
  // setTimeout executará callback após delay milissegundos e devolve o identificador do timer.
  const timer = setTimeout(callback, delay);

  // Registra o timer usando o próprio elemento DOM como chave.
  typewriterTimers.set(element, timer);
}

// Reúne as três fontes de preferência que podem pedir uma interface com menos movimento.
function isReducedMotionActive() {
  // Primeiro respeita a escolha aplicada no body pelas configurações da própria aplicação.
  // O ?. evita erro caso document.body ainda não esteja disponível.
  if (document.body?.dataset.motion === "reduced") {
    // true informa às outras funções que o loop animado deve ser substituído por texto estático.
    return true;
  }

  // Depois consulta a preferência que a aplicação guardou no localStorage.
  if (getSavedMotionPreference() === "reduced") {
    // A escolha salva também desativa a animação.
    return true;
  }

  // Por último consulta prefers-reduced-motion, a configuração de acessibilidade do sistema.
  // Optional chaining protege navegadores sem matchMedia e ?? false fornece um booleano seguro.
  return (
    window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false
  );
}

// Lê a preferência salva sem deixar uma restrição de armazenamento quebrar a página.
function getSavedMotionPreference() {
  // localStorage pode lançar exceção em modo privado ou quando políticas bloqueiam armazenamento.
  try {
    // Recupera a string salva com a chave usada pelo restante da aplicação.
    return localStorage.getItem("titech-motion");
  } catch {
    // Se a leitura falhar, o catch mantém o módulo funcional.
    // null significa que nenhuma preferência pôde ser obtida; o sistema operacional ainda será consultado.
    return null;
  }
}
