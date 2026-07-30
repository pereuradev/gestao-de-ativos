// Coordena perfil, máscaras, validações e envio do formulário de novos funcionários.
// Os helpers globais de interface são carregados pela página antes deste módulo.

const estadoCadastroFuncionario = {
  role: "Colaborador",
};

document.addEventListener("DOMContentLoaded", inicializarPaginaCadastroFuncionario);

function inicializarPaginaCadastroFuncionario() {
  iniciarAnimacaoPagina();
  carregarTemaSalvo();
  configurarAlternadorTema();
  configurarBarraLateral();
  configurarGruposNavegacao();
  configurarSeletorTipoUsuarioFuncionario();
  configurarAlternadorSenhaFuncionario();
  configurarForcaSenhaFuncionario();
  configurarMascarasDocumentoFuncionario();
  configurarFormularioCadastroFuncionario();
  configurarRedefinicaoFormularioFuncionario();
}

function obterElementoFuncionario(id) {
  return document.getElementById(id);
}

function criarElementoFuncionario(etiqueta, nomeClasse = "", texto = "") {
  const elemento = document.createElement(etiqueta);

  if (nomeClasse) {
    elemento.className = nomeClasse;
  }

  if (texto) {
    elemento.textContent = texto;
  }

  return elemento;
}

// O seletor visual e o campo enviado ao backend precisam permanecer sincronizados.
function configurarSeletorTipoUsuarioFuncionario() {
  const controleTipoUsuario = obterElementoFuncionario("employeeRoleControl");
  const botoes = controleTipoUsuario
    ? [...controleTipoUsuario.querySelectorAll("button[data-role]")]
    : [];

  if (!controleTipoUsuario || !botoes.length) {
    return;
  }

  botoes.forEach((botao) => {
    botao.addEventListener("click", () => {
      const tipoUsuarioProximo = botao.dataset.role || "Colaborador";

      if (
        estadoCadastroFuncionario.role === tipoUsuarioProximo &&
        botao.classList.contains("active")
      ) {
        return;
      }

      definirTipoUsuarioFuncionario(tipoUsuarioProximo);
    });
  });

  definirTipoUsuarioFuncionario(estadoCadastroFuncionario.role);
}

function definirTipoUsuarioFuncionario(tipoUsuario) {
  const tipoUsuarioProximo = tipoUsuario === "Administrador" ? "Administrador" : "Colaborador";
  const controleTipoUsuario = obterElementoFuncionario("employeeRoleControl");
  const tipoUsuarioOculto = obterElementoFuncionario("selectedEmployeeRole");
  const botoes = controleTipoUsuario
    ? [...controleTipoUsuario.querySelectorAll("button[data-role]")]
    : [];

  estadoCadastroFuncionario.role = tipoUsuarioProximo;

  if (controleTipoUsuario) {
    controleTipoUsuario.dataset.active = tipoUsuarioProximo;
  }

  if (tipoUsuarioOculto) {
    tipoUsuarioOculto.value = tipoUsuarioProximo;
  }

  botoes.forEach((botao) => {
    botao.classList.toggle("active", botao.dataset.role === tipoUsuarioProximo);
  });
}

function configurarAlternadorSenhaFuncionario() {
  document
    .querySelectorAll(".password-toggle[data-target]")
    .forEach((botao) => {
      botao.addEventListener("click", () => {
        const idDestino = botao.dataset.target;
        const campoEntrada = idDestino ? obterElementoFuncionario(idDestino) : null;
        const icone = botao.querySelector("i");

        if (!campoEntrada) {
          return;
        }

        const ehOculto = campoEntrada.type === "password";

        campoEntrada.type = ehOculto ? "text" : "password";

        if (icone) {
          icone.className = ehOculto ? "bi bi-eye-slash" : "bi bi-eye";
        }

        botao.setAttribute(
          "aria-label",
          ehOculto ? "Ocultar senha" : "Mostrar senha",
        );
      });
    });
}

function obterForcaSenhaFuncionario(senha) {
  let pontuacao = 0;

  if (senha.length >= 6) pontuacao += 1;
  if (senha.length >= 10) pontuacao += 1;
  if (/[A-Z]/.test(senha)) pontuacao += 1;
  if (/\d/.test(senha)) pontuacao += 1;
  if (/[^A-Za-z0-9]/.test(senha)) pontuacao += 1;

  if (!senha) {
    return {
      label: "Forca da senha: aguardando",
      width: "0%",
      color: "var(--muted)",
    };
  }

  if (pontuacao <= 2) {
    return { label: "Forca da senha: baixa", width: "36%", color: "#ef4444" };
  }

  if (pontuacao <= 4) {
    return { label: "Forca da senha: media", width: "68%", color: "#f59e0b" };
  }

  return { label: "Forca da senha: alta", width: "100%", color: "#10b981" };
}

function atualizarForcaSenhaFuncionario() {
  const campoEntradaSenha = obterElementoFuncionario("employeePassword");
  const barraForca = obterElementoFuncionario("employeePasswordStrengthBar");
  const textoForca = obterElementoFuncionario("employeePasswordStrengthText");

  if (!campoEntradaSenha || !barraForca || !textoForca) {
    return;
  }

  const forca = obterForcaSenhaFuncionario(campoEntradaSenha.value);

  barraForca.style.setProperty("--strength", forca.width);
  barraForca.style.setProperty("--strength-color", forca.color);
  textoForca.textContent = forca.label;
}

function configurarForcaSenhaFuncionario() {
  const campoEntradaSenha = obterElementoFuncionario("employeePassword");

  if (!campoEntradaSenha) {
    return;
  }

  campoEntradaSenha.addEventListener("input", atualizarForcaSenhaFuncionario);
  atualizarForcaSenhaFuncionario();
}

function obterSomenteNumeros(valor) {
  return valor.replace(/\D/g, "");
}

function formatarCpfFuncionario(valor) {
  return obterSomenteNumeros(valor)
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
}

function ehCpfFuncionarioValido(valor) {
  const cpf = obterSomenteNumeros(valor);

  if (cpf.length !== 11) {
    return false;
  }

  if (/^(\d)\1{10}$/.test(cpf)) {
    return false;
  }

  let soma = 0;

  for (let indice = 0; indice < 9; indice += 1) {
    soma += Number(cpf[indice]) * (10 - indice);
  }

  let primeiroDigito = (soma * 10) % 11;

  if (primeiroDigito === 10) {
    primeiroDigito = 0;
  }

  if (primeiroDigito !== Number(cpf[9])) {
    return false;
  }

  soma = 0;

  for (let indice = 0; indice < 10; indice += 1) {
    soma += Number(cpf[indice]) * (11 - indice);
  }

  let segundoDigito = (soma * 10) % 11;

  if (segundoDigito === 10) {
    segundoDigito = 0;
  }

  return segundoDigito === Number(cpf[10]);
}

function formatarRgFuncionario(valor) {
  return obterSomenteNumeros(valor)
    .slice(0, 9)
    .replace(/(\d{2})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)$/, "$1-$2");
}

function formatarCelularFuncionario(valor) {
  return obterSomenteNumeros(valor)
    .slice(0, 11)
    .replace(/(\d{2})(\d)/, "($1) $2")
    .replace(/(\d{5})(\d{1,4})$/, "$1-$2");
}

// As máscaras ajudam na digitação; a validade real também é conferida antes do envio.
function configurarMascarasDocumentoFuncionario() {
  const mascaras = [
    { input: obterElementoFuncionario("employeeRg"), formatter: formatarRgFuncionario },
    { input: obterElementoFuncionario("employeeCpf"), formatter: formatarCpfFuncionario },
    {
      input: obterElementoFuncionario("employeeCellphone"),
      formatter: formatarCelularFuncionario,
    },
  ];

  mascaras.forEach(({ input: campoEntrada, formatter: formatador }) => {
    campoEntrada?.addEventListener("input", () => {
      campoEntrada.value = formatador(campoEntrada.value);
    });
  });
}

// Concentra as regras do formulário para impedir validações divergentes entre os eventos.
function validarCadastroFuncionario(dados) {
  if (
    !dados.nomeCompleto ||
    !dados.email ||
    !dados.senha ||
    !dados.rg ||
    !dados.cpf ||
    !dados.celular ||
    !dados.dataNascimento ||
    !dados.tipoUsuario ||
    !dados.departamento ||
    !dados.empresa
  ) {
    return "Preencha todos os campos obrigatorios para continuar.";
  }

  if (dados.nomeCompleto.trim().split(/\s+/).length < 2) {
    return "Informe nome e sobrenome.";
  }

  if (!dados.email.includes("@")) {
    return "Digite um e-mail valido.";
  }

  if (!dados.email.toLowerCase().endsWith("@titechsolutions.com.br")) {
    return "Use um e-mail corporativo autorizado.";
  }

  if (obterSomenteNumeros(dados.rg).length < 7) {
    return "Informe um RG valido.";
  }

  if (!ehCpfFuncionarioValido(dados.cpf)) {
    return "Informe um CPF valido.";
  }

  if (obterSomenteNumeros(dados.celular).length !== 11) {
    return "Informe um telefone celular valido com DDD.";
  }

  if (new Date(dados.dataNascimento) > new Date()) {
    return "A data de nascimento nao pode ser futura.";
  }

  if (dados.senha.length < 6) {
    return "A senha precisa ter pelo menos 6 caracteres.";
  }

  return "";
}

function definirMensagemFormularioFuncionario(mensagem, tipo = "") {
  const caixaMensagem = obterElementoFuncionario("employeeFormMessage");

  if (!caixaMensagem) {
    return;
  }

  caixaMensagem.textContent = mensagem;
  caixaMensagem.classList.remove("is-error", "is-success");

  if (tipo === "error") {
    caixaMensagem.classList.add("is-error");
  }

  if (tipo === "success") {
    caixaMensagem.classList.add("is-success");
  }
}

function definirCarregandoEnviarFuncionario(botao, estaCarregando) {
  if (!botao) {
    return;
  }

  botao.disabled = estaCarregando;

  if (estaCarregando) {
    botao.replaceChildren(
      criarElementoFuncionario("span", "spinner-border spinner-border-sm"),
      criarElementoFuncionario("span", "", "Cadastrando funcionario..."),
    );
    return;
  }

  botao.replaceChildren(
    criarElementoFuncionario("i", "bi bi-person-plus-fill"),
    criarElementoFuncionario("span", "", "Cadastrar funcionario"),
  );
}

function montarDadosFuncionario() {
  return {
    nomeCompleto: obterElementoFuncionario("employeeFullName")?.value.trim() || "",
    email: obterElementoFuncionario("employeeEmail")?.value.trim() || "",
    senha: obterElementoFuncionario("employeePassword")?.value || "",
    rg: obterElementoFuncionario("employeeRg")?.value.trim() || "",
    cpf: obterElementoFuncionario("employeeCpf")?.value.trim() || "",
    celular: obterElementoFuncionario("employeeCellphone")?.value.trim() || "",
    dataNascimento: obterElementoFuncionario("employeeBirthDate")?.value || "",
    tipoUsuario: estadoCadastroFuncionario.role,
    departamento: obterElementoFuncionario("employeeDepartment")?.value || "",
    empresa: obterElementoFuncionario("employeeCompany")?.value.trim() || "",
  };
}

// O cadastro só altera a interface após uma resposta JSON bem-sucedida do servidor.
async function tratarCadastroFuncionario(evento) {
  evento.preventDefault();

  const formulario = evento.currentTarget;
  const botaoEnviar = obterElementoFuncionario("employeeSubmitButton");
  const dadosFuncionario = montarDadosFuncionario();
  const erroValidacao = validarCadastroFuncionario(dadosFuncionario);

  if (erroValidacao) {
    definirMensagemFormularioFuncionario(erroValidacao, "error");
    window.titechToast?.(erroValidacao, "error");
    return;
  }

  const confirmado = await confirmarCadastroFuncionario(dadosFuncionario);

  if (!confirmado) {
    return;
  }

  definirMensagemFormularioFuncionario("");
  definirCarregandoEnviarFuncionario(botaoEnviar, true);

  try {
    const resposta = await fetch(formulario.action, {
      method: "POST",
      body: new FormData(formulario),
      headers: {
        Accept: "application/json",
      },
    });

    const resultado = await resposta.json().catch(() => ({
      ok: false,
      message: "Resposta invalida do servidor.",
    }));

    if (resultado.redirect && resposta.status === 401) {
      window.location.href = resultado.redirect;
      return;
    }

    if (!resposta.ok || !resultado.ok) {
      throw new Error(
        resultado.message || "Nao foi possivel cadastrar o funcionario.",
      );
    }

    definirMensagemFormularioFuncionario(
      resultado.message || "Funcionario cadastrado com sucesso.",
      "success",
    );
    window.titechToast?.(
      resultado.message || "Funcionario cadastrado com sucesso.",
    );
    atualizarResumoFuncionario(resultado.usuario || null);
    inserirInicioFuncionarioRecente(resultado.usuario || null);
    formulario.reset();
    redefinirEstadoFormularioCadastroFuncionario();
  } catch (erro) {
    const mensagem =
      erro instanceof Error
        ? erro.message
        : "Nao foi possivel cadastrar o funcionario.";

    definirMensagemFormularioFuncionario(mensagem, "error");
    window.titechToast?.(mensagem, "error");
  } finally {
    definirCarregandoEnviarFuncionario(botaoEnviar, false);
  }
}

async function confirmarCadastroFuncionario(dadosFuncionario) {
  const nomeFuncionario = dadosFuncionario.nomeCompleto || "este funcionario";
  const tipoUsuarioFuncionario = dadosFuncionario.tipoUsuario || "Colaborador";

  if (typeof window.titechConfirm === "function") {
    return window.titechConfirm({
      title: "Cadastrar funcionario?",
      text: `Confirme para criar o acesso de ${nomeFuncionario} como ${tipoUsuarioFuncionario}.`,
      confirmButtonText: "Cadastrar funcionario",
      cancelButtonText: "Revisar dados",
      icon: "info",
    });
  }

  return window.confirm(`Criar o acesso de ${nomeFuncionario} como ${tipoUsuarioFuncionario}?`);
}

function configurarFormularioCadastroFuncionario() {
  const formulario = obterElementoFuncionario("employeeSignupForm");

  if (!formulario) {
    return;
  }

  formulario.addEventListener("submit", tratarCadastroFuncionario);
}

function configurarRedefinicaoFormularioFuncionario() {
  const formulario = obterElementoFuncionario("employeeSignupForm");

  if (!formulario) {
    return;
  }

  formulario.addEventListener("reset", () => {
    requestAnimationFrame(() => {
      redefinirEstadoFormularioCadastroFuncionario();
      definirMensagemFormularioFuncionario("");
    });
  });
}

function redefinirEstadoFormularioCadastroFuncionario() {
  definirTipoUsuarioFuncionario("Colaborador");
  atualizarForcaSenhaFuncionario();
}

// Mantém métricas e lista recente coerentes com o funcionário recém-cadastrado.
function atualizarResumoFuncionario(usuario) {
  if (!usuario || typeof usuario !== "object") {
    return;
  }

  incrementarMetricaFuncionario("employeeMetricTotal");

  if ((usuario.tipo_usuario || "") === "Administrador") {
    incrementarMetricaFuncionario("employeeMetricAdmins");
  } else {
    incrementarMetricaFuncionario("employeeMetricCollaborators");
  }

  const ultimaMetrica = obterElementoFuncionario("employeeMetricLast");

  if (ultimaMetrica && usuario.criado_em) {
    ultimaMetrica.textContent = formatarDataHoraFuncionario(usuario.criado_em);
  }
}

function incrementarMetricaFuncionario(id) {
  const elemento = obterElementoFuncionario(id);
  const valorAtual = Number.parseInt(elemento?.textContent || "0", 10);

  if (!elemento || Number.isNaN(valorAtual)) {
    return;
  }

  elemento.textContent = String(valorAtual + 1);
}

function inserirInicioFuncionarioRecente(usuario) {
  if (!usuario || typeof usuario !== "object") {
    return;
  }

  const lista = obterElementoFuncionario("recentEmployeeList");

  if (!lista) {
    return;
  }

  lista.querySelector(".compact-empty-state")?.remove();

  const artigo = criarElementoFuncionario(
    "article",
    "recent-asset-item recent-employee-card",
  );
  const linhaSuperior = criarElementoFuncionario("div", "recent-asset-topline");
  const nome = criarElementoFuncionario(
    "strong",
    "",
    usuario.nome_completo || "Funcionario",
  );
  const status = criarElementoFuncionario(
    "span",
    `status-badge ${String(usuario.status || "").toLowerCase() === "ativo" ? "status-active" : "status-neutral"}`,
    usuario.status || "Ativo",
  );
  const metadados = criarElementoFuncionario("div", "recent-asset-meta");
  const tipoUsuario = criarElementoFuncionario("span", "", usuario.tipo_usuario || "--");
  const departamento = criarElementoFuncionario(
    "span",
    "",
    usuario.departamento || "--",
  );
  const rodape = criarElementoFuncionario("div", "recent-asset-footer");
  const email = criarElementoFuncionario("span", "", usuario.email || "--");
  const tempo = document.createElement("time");

  tempo.dateTime = usuario.criado_em || "";
  tempo.textContent = formatarDataHoraFuncionario(usuario.criado_em || "");

  linhaSuperior.append(nome, status);
  metadados.append(tipoUsuario, departamento);
  rodape.append(email, tempo);
  artigo.append(linhaSuperior, metadados, rodape);

  lista.prepend(artigo);

  const cartoes = [...lista.querySelectorAll(".recent-employee-card")];

  cartoes.slice(6).forEach((cartao) => cartao.remove());
}

function formatarDataHoraFuncionario(valor) {
  if (!valor) {
    return "--";
  }

  const data = new Date(valor);

  if (Number.isNaN(data.getTime())) {
    return "--";
  }

  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "America/Sao_Paulo",
  }).format(data);
}
