// Este módulo cria o efeito de digitação, apagamento e troca de frases nos títulos.
// O seletor procura qualquer elemento HTML que possua o atributo data-typewriter-loop.
// Guardar o seletor em uma constante evita repetir a mesma string em outras funções.
const SELETOR_EFEITO_DIGITACAO = "[data-typewriter-loop]";

// Esta lista é o plano B usado quando o HTML não fornece frases próprias.
const FRASES_PADRAO_EFEITO_DIGITACAO = [
  // Primeira frase padrão apresentada ao usuário.
  "Gestao de ativos com clareza.",
  // Segunda frase padrão do ciclo.
  "Dados confiaveis para a operacao.",
  // Terceira frase padrão; depois dela, o ciclo volta para a primeira.
  "Controle simples, rapido e seguro.",
];
// WeakMap relaciona cada elemento ao seu temporizador sem impedir que o navegador libere elementos removidos.
// Isso também permite cancelar somente a animação pertencente ao elemento que está sendo reiniciado.
const temporizadoresEfeitoDigitacao = new WeakMap();

// Aguarda o HTML terminar de ser interpretado antes de procurar os títulos na página.
document.addEventListener("DOMContentLoaded", inicializarCiclosEfeitoDigitacao);
// Reinicia as animações quando outra parte do sistema altera a preferência de movimento.
window.addEventListener("titech:motion-change", inicializarCiclosEfeitoDigitacao);

// Função principal: encontra, prepara e inicia todos os títulos animados da página.
function inicializarCiclosEfeitoDigitacao() {
  // querySelectorAll devolve uma NodeList; o operador ... cria um Array para facilitar o uso.
  // TYPEWRITER_SELECTOR limita a busca aos elementos que optaram pelo efeito no HTML.
  const elementos = [...document.querySelectorAll(SELETOR_EFEITO_DIGITACAO)];

  // Se a página não possui nenhum título animável, encerra cedo e evita trabalho desnecessário.
  if (!elementos.length) return;

  // Calcula uma única vez se as animações devem ser reduzidas para esta inicialização.
  const deveReduzirMovimento = movimentoReduzidoEstaAtivo();

  // Percorre cada título separadamente, pois cada um pode ter frases e tempos diferentes.
  elementos.forEach((elemento) => {
    // Lê as frases configuradas no próprio elemento ou escolhe um fallback adequado.
    const frases = obterFrasesEfeitoDigitacao(elemento);
    // Titulos permanecem legiveis por padrao. A animacao so ocorre quando a pagina
    // pede explicitamente data-typewriter-animate="true".
    const animacaoFoiSolicitada =
      elemento.dataset.typewriterAnimate === "true";

    // Sem frases não existe conteúdo para animar, então este elemento é ignorado.
    if (!frases.length) return;

    // Cancela um temporizador anterior para impedir duas animações simultâneas no mesmo título.
    pararEfeitoDigitacao(elemento);
    // Mede o espaço necessário antes de animar para evitar saltos no layout.
    estabilizarElementoEfeitoDigitacao(elemento, frases);

    // Acessibilidade: movimento reduzido, texto estatico ou uma unica frase nao precisam de loop.
    if (!animacaoFoiSolicitada || deveReduzirMovimento || frases.length === 1) {
      // Mostra a primeira frase completa, sem simular digitação.
      definirTextoEfeitoDigitacaoEstatico(elemento, frases);
      // Encerra somente esta iteração do forEach e impede a chamada de runTypewriter abaixo.
      return;
    }

    // Com várias frases e movimento permitido, inicia o ciclo de escrita e apagamento.
    executarEfeitoDigitacao(elemento, frases);
  });
}

// Extrai e normaliza a lista de frases pertencente a um elemento.
function obterFrasesEfeitoDigitacao(elemento) {
  // dataset.typewriterPhrases corresponde ao atributo HTML data-typewriter-phrases.
  // O operador || usa uma string vazia quando o atributo não existe.
  const frasesBrutas = elemento.dataset.typewriterPhrases || "";
  // A transformação é dividida em etapas encadeadas para produzir um Array limpo.
  const frases = frasesBrutas
    // split separa a string pelo caractere |: "Uma|Duas" vira ["Uma", "Duas"].
    .split("|")
    // map aplica trim em cada frase para remover espaços no começo e no fim.
    .map((frase) => frase.trim())
    // filter(Boolean) remove strings vazias que poderiam surgir de separadores duplicados.
    .filter(Boolean);
  // Se o atributo produziu ao menos uma frase válida, ele tem prioridade.
  if (frases.length) {
    // Devolve imediatamente a configuração específica deste elemento.
    return frases;
  }

  // Sem atributo válido, tenta aproveitar o texto que já estava escrito dentro do título.
  const padrao = elemento.textContent.trim();

  // O ternário cria uma lista com o texto original ou, se ele estiver vazio, usa as frases padrão.
  return padrao ? [padrao] : FRASES_PADRAO_EFEITO_DIGITACAO;
}

// Reserva largura e altura suficientes para que a troca de frases não desloque outros componentes.
function estabilizarElementoEfeitoDigitacao(elemento, frases) {
  // Guarda o conteúdo original porque as próximas medições substituirão o texto temporariamente.
  const textoOriginal = elemento.textContent;
  // reduce compara todas as frases e mantém no acumulador aquela com mais caracteres.
  const fraseMaisLonga = frases.reduce(
    (maisLonga, frase) =>
      // O ternário devolve a frase atual quando ela supera a maior encontrada até aqui.
      frase.length > maisLonga.length ? frase : maisLonga,
      // A string vazia é o valor inicial do acumulador na primeira comparação.
    "",
  );
  // Salva a transição inline atual para restaurá-la depois das medições.
  const transicaoAtual = elemento.style.transition;
  // Este Array receberá a altura real que cada frase ocupa no elemento.
  const alturas = [];

  // Desativa transições durante a medição para o usuário não enxergar as trocas temporárias.
  elemento.style.transition = "none";
  // setProperty permite definir uma variável CSS personalizada diretamente pelo JavaScript.
  elemento.style.setProperty(
    // --typewriter-min é lida no CSS para reservar uma largura mínima.
    "--typewriter-min",
    // Math.max garante ao menos 12ch; ch aproxima a largura pela quantidade de caracteres.
    `${Math.max(fraseMaisLonga.length, 12)}ch`,
  );

  // Testa cada frase porque textos com tamanhos diferentes também podem quebrar em mais linhas.
  frases.forEach((frase) => {
    // Coloca temporariamente a frase atual dentro do elemento para medir seu tamanho real.
    elemento.textContent = frase;
    // getBoundingClientRect calcula a altura renderizada; push guarda o resultado no Array.
    alturas.push(elemento.getBoundingClientRect().height);
  });

  // Math.max encontra a maior altura, o 0 evita problemas em lista vazia e ceil arredonda para cima.
  const alturaEstavel = Math.ceil(Math.max(...alturas, 0));

  // Só cria a variável de altura quando o navegador conseguiu medir um valor positivo.
  if (alturaEstavel > 0) {
    // A maior altura vira uma variável CSS em pixels, mantendo o título estável durante o loop.
    elemento.style.setProperty("--typewriter-min-height", `${alturaEstavel}px`);
  }

  // Restaura o texto que existia antes das medições temporárias.
  elemento.textContent = textoOriginal;
  // Restaura também a transição inline original do elemento.
  elemento.style.transition = transicaoAtual;
}

// Executa a máquina de estados responsável por escrever, pausar, apagar e trocar a frase.
function executarEfeitoDigitacao(elemento, frases) {
  // Índice da frase atual dentro do Array; começa na primeira posição, que é 0.
  let indiceFrase = 0;
  // Quantidade de caracteres que deve aparecer; começa em 0 para iniciar com texto vazio.
  let indiceLetra = 0;
  // false significa fase de escrita; true significa fase de apagamento.
  let estaApagando = false;

  // Converte data-typewriter-type-delay em número ou usa 84 ms entre letras escritas.
  const atrasoDigitacao = Number(elemento.dataset.typewriterTypeDelay) || 84;
  // Define a velocidade de apagamento; 42 ms torna a remoção mais rápida que a escrita.
  const atrasoExclusao = Number(elemento.dataset.typewriterDeleteDelay) || 42;
  // Mantém a frase completa visível por 2450 ms antes de começar a apagar.
  const atrasoLeitura = Number(elemento.dataset.typewriterReadDelay) || 2450;
  // Espera 620 ms com o título vazio antes de iniciar a próxima frase.
  // Como o código usa ||, um valor 0 no HTML também cairia no tempo padrão.
  const atrasoProximo = Number(elemento.dataset.typewriterNextDelay) || 620;

  // Esta arrow function representa um passo do ciclo e agenda a própria próxima execução.
  const escrever = () => {
    // Obtém do Array a frase apontada pelo índice atual.
    const frase = frases[indiceFrase];
    // slice cria o trecho entre a posição 0 e letterIndex, sem incluir o limite final.
    elemento.textContent = frase.slice(0, indiceLetra);

    // Enquanto estiver escrevendo e ainda faltarem letras, entra neste bloco.
    if (!estaApagando && indiceLetra < frase.length) {
      // Aumenta o limite para que o próximo passo mostre mais um caractere.
      indiceLetra += 1;
      // Agenda o próximo passo usando o intervalo definido para digitação.
      agendarEtapaEfeitoDigitacao(elemento, escrever, atrasoDigitacao);
      // Evita que o mesmo passo continue e entre nas regras de pausa ou apagamento.
      return;
    }

    // Chegar aqui sem apagar significa que a frase acabou de ser escrita por completo.
    if (!estaApagando) {
      // Muda o estado para que os próximos passos removam caracteres.
      estaApagando = true;
      // Agenda a primeira remoção somente depois do tempo reservado para leitura.
      agendarEtapaEfeitoDigitacao(elemento, escrever, atrasoLeitura);
      // Encerra este passo após agendar a continuação.
      return;
    }

    // Durante o apagamento, continua enquanto ainda existir algum caractere visível.
    if (indiceLetra > 0) {
      // Reduz o limite para remover uma letra na próxima execução.
      indiceLetra -= 1;
      // Agenda o próximo passo com a velocidade específica de apagamento.
      agendarEtapaEfeitoDigitacao(elemento, escrever, atrasoExclusao);
      // Interrompe o passo atual para não trocar de frase antes de terminar de apagar.
      return;
    }

    // Quando letterIndex chega a 0, volta ao estado de escrita.
    estaApagando = false;
    // O resto da divisão (%) volta o índice para 0 depois da última frase, criando o loop.
    indiceFrase = (indiceFrase + 1) % frases.length;
    // Agenda o início da próxima frase após a pausa com o título vazio.
    agendarEtapaEfeitoDigitacao(elemento, escrever, atrasoProximo);
  };

  // Chama write imediatamente; as execuções seguintes serão controladas por setTimeout.
  escrever();
}

// Apresenta uma versão estática quando a animação não é necessária ou não é recomendada.
function definirTextoEfeitoDigitacaoEstatico(elemento, frases) {
  // A primeira frase permanece completa e legível, sem alterações posteriores.
  elemento.textContent = frases[0];
}

// Cancela qualquer continuação já agendada para um elemento específico.
function pararEfeitoDigitacao(elemento) {
  // Consulta no WeakMap o identificador do último setTimeout associado ao elemento.
  const temporizador = temporizadoresEfeitoDigitacao.get(elemento);

  // Só tenta cancelar quando existe um temporizador registrado.
  if (temporizador) {
    // clearTimeout impede que o callback agendado volte a executar.
    clearTimeout(temporizador);
    // Remove a associação antiga para que o WeakMap represente apenas timers ativos.
    temporizadoresEfeitoDigitacao.delete(elemento);
  }
}

// Agenda o próximo passo da animação e guarda uma forma de cancelá-lo depois.
function agendarEtapaEfeitoDigitacao(elemento, funcaoRetorno, atraso) {
  // setTimeout executará callback após delay milissegundos e devolve o identificador do timer.
  const temporizador = setTimeout(funcaoRetorno, atraso);

  // Registra o timer usando o próprio elemento DOM como chave.
  temporizadoresEfeitoDigitacao.set(elemento, temporizador);
}

// Reúne as três fontes de preferência que podem pedir uma interface com menos movimento.
function movimentoReduzidoEstaAtivo() {
  // Primeiro respeita a escolha aplicada no body pelas configurações da própria aplicação.
  // O ?. evita erro caso document.body ainda não esteja disponível.
  if (document.body?.dataset.motion === "reduced") {
    // true informa às outras funções que o loop animado deve ser substituído por texto estático.
    return true;
  }

  // Depois consulta a preferência que a aplicação guardou no localStorage.
  if (obterPreferenciaMovimentoSalva() === "reduced") {
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
function obterPreferenciaMovimentoSalva() {
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
