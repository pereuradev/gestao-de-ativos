<?php

declare(strict_types=1);

// Endpoint de edicao de funcionarios. O e-mail fica fora da edicao para nao dessincronizar o login.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderFuncionario(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoFuncionario(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function csrfFuncionarioValido(): bool
{
    $tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");
    $tokenRequisicao = campoFuncionario("csrf_token");

    return $tokenSessao !== "" && $tokenRequisicao !== "" && hash_equals($tokenSessao, $tokenRequisicao);
}

function apenasNumerosFuncionario(string $valor): string
{
    return preg_replace("/\D+/", "", $valor) ?? "";
}

function cpfFuncionarioValido(string $valor): bool
{
    $cpf = apenasNumerosFuncionario($valor);

    if (strlen($cpf) !== 11 || preg_match("/^(\d)\1{10}$/", $cpf)) {
        return false;
    }

    $soma = 0;

    for ($indiceDigito = 0; $indiceDigito < 9; $indiceDigito++) {
        $soma += (int) $cpf[$indiceDigito] * (10 - $indiceDigito);
    }

    $primeiroDigito = ($soma * 10) % 11;
    $primeiroDigito = $primeiroDigito === 10 ? 0 : $primeiroDigito;

    if ($primeiroDigito !== (int) $cpf[9]) {
        return false;
    }

    $soma = 0;

    for ($indiceDigito = 0; $indiceDigito < 10; $indiceDigito++) {
        $soma += (int) $cpf[$indiceDigito] * (11 - $indiceDigito);
    }

    $segundoDigito = ($soma * 10) % 11;
    $segundoDigito = $segundoDigito === 10 ? 0 : $segundoDigito;

    return $segundoDigito === (int) $cpf[10];
}

function uuidFuncionarioValido(string $valor): bool
{
    return preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    ) === 1;
}

function dataFuncionarioValida(string $valor): bool
{
    $data = DateTimeImmutable::createFromFormat("Y-m-d", $valor);

    return $data instanceof DateTimeImmutable
        && $data->format("Y-m-d") === $valor
        && $data <= new DateTimeImmutable("today");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderFuncionario(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderFuncionario(false, "Sessao expirada. Entre novamente no portal.", 401);
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("editar_funcionarios", "Edicao de funcionarios");

if (!csrfFuncionarioValido()) {
    responderFuncionario(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 419);
}

$id = campoFuncionario("id");
$nomeCompleto = preg_replace("/\s+/u", " ", campoFuncionario("nome_completo")) ?? campoFuncionario("nome_completo");
$tipoUsuario = campoFuncionario("tipo_usuario") ?: "Colaborador";
$departamento = campoFuncionario("departamento");
$empresa = campoFuncionario("empresa");
$rg = campoFuncionario("rg");
$cpf = campoFuncionario("cpf");
$celular = campoFuncionario("celular");
$dataNascimento = campoFuncionario("data_nascimento");
$status = campoFuncionario("status") ?: "Ativo";

if (!uuidFuncionarioValido($id)) {
    responderFuncionario(false, "Funcionario invalido para edicao.", 422);
}

if (
    $nomeCompleto === "" ||
    $tipoUsuario === "" ||
    $departamento === "" ||
    $empresa === "" ||
    $rg === "" ||
    $cpf === "" ||
    $celular === "" ||
    $dataNascimento === "" ||
    $status === ""
) {
    responderFuncionario(false, "Preencha todos os campos obrigatorios.", 422);
}

if (count(preg_split("/\s+/", $nomeCompleto, -1, PREG_SPLIT_NO_EMPTY)) < 2) {
    responderFuncionario(false, "Informe nome e sobrenome.", 422);
}

if (!in_array($tipoUsuario, ["Colaborador", "Administrador"], true)) {
    responderFuncionario(false, "Perfil de acesso invalido.", 422);
}

if (!in_array($departamento, ["Comercial", "TI", "Administrativo"], true)) {
    responderFuncionario(false, "Departamento invalido.", 422);
}

if (!in_array($status, ["Ativo", "Inativo"], true)) {
    responderFuncionario(false, "Status invalido.", 422);
}

if (strlen(apenasNumerosFuncionario($rg)) < 7) {
    responderFuncionario(false, "Informe um RG valido.", 422);
}

if (!cpfFuncionarioValido($cpf)) {
    responderFuncionario(false, "Informe um CPF valido.", 422);
}

if (strlen(apenasNumerosFuncionario($celular)) !== 11) {
    responderFuncionario(false, "Informe um celular valido com DDD.", 422);
}

if (!dataFuncionarioValida($dataNascimento)) {
    responderFuncionario(false, "Informe uma data de nascimento valida.", 422);
}

$usuarioSessaoId = (string) ($_SESSION["usuario"]["id"] ?? "");

try {
    // Abre a conexão compartilhada somente quando esta etapa precisa acessar o banco.
    require_once __DIR__ . "/Conexao.php";

    $consultaPerfilAtual = $pdo->prepare("
        select tipo_usuario
          from public.perfis_usuarios
         where id = cast(:id as uuid)
         limit 1
    ");
    $consultaPerfilAtual->execute([":id" => $id]);
    $perfilAtual = $consultaPerfilAtual->fetch();

    if (!is_array($perfilAtual)) {
        responderFuncionario(false, "Funcionario nao encontrado.", 404);
    }

    $tipoUsuarioAtual = (string) ($perfilAtual["tipo_usuario"] ?? "Colaborador");
    $perfilAdministrativo = tipoUsuarioGrupoAcessoAdministrador($tipoUsuarioAtual)
        || tipoUsuarioGrupoAcessoAdministrador($tipoUsuario);

    exigirAdministradorParaGerenciarPerfilApi(
        $tipoUsuarioAtual,
        $tipoUsuario,
        "alteracoes de perfis administrativos"
    );

    $usuarioSessaoAdministrador = $perfilAdministrativo
        || usuarioGrupoAcessoAdministradorConfirmado($pdo);

    if ($usuarioSessaoId === $id && $usuarioSessaoAdministrador && ($status !== "Ativo" || $tipoUsuario !== "Administrador")) {
        responderFuncionario(false, "Voce nao pode remover seu proprio acesso de administrador.", 422);
    }

    $consultaDuplicado = $pdo->prepare("
        select cpf, rg
          from public.perfis_usuarios
         where (
                   regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
                or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
         )
           and id::text <> :id
         limit 1
    ");
    $consultaDuplicado->execute([
        ":cpf" => $cpf,
        ":rg" => $rg,
        ":id" => $id,
    ]);

    $duplicado = $consultaDuplicado->fetch();

    if ($duplicado) {
        if (apenasNumerosFuncionario((string) ($duplicado["cpf"] ?? "")) === apenasNumerosFuncionario($cpf)) {
            responderFuncionario(false, "Este CPF ja esta cadastrado para outro funcionario.", 409);
        }

        if (apenasNumerosFuncionario((string) ($duplicado["rg"] ?? "")) === apenasNumerosFuncionario($rg)) {
            responderFuncionario(false, "Este RG ja esta cadastrado para outro funcionario.", 409);
        }
    }

    $atribuicaoTipoUsuario = $usuarioSessaoAdministrador
        ? "tipo_usuario = :tipo_usuario,"
        : "";
    $restricaoPerfilAdministrativo = $usuarioSessaoAdministrador
        ? ""
        : "and lower(btrim(tipo_usuario)) not in ('adm', 'admin', 'administrador')";

    $consultaPreparada = $pdo->prepare("
        update public.perfis_usuarios
           set nome_completo = :nome_completo,
               {$atribuicaoTipoUsuario}
               departamento = :departamento,
               empresa = :empresa,
               rg = :rg,
               cpf = :cpf,
               celular = :celular,
               data_nascimento = :data_nascimento,
               status = :status,
               atualizado_em = now()
         where id::text = :id
               {$restricaoPerfilAdministrativo}
     returning
               id,
               nome_completo,
               email,
               tipo_usuario,
               departamento,
               empresa,
               rg,
               cpf,
               celular,
               data_nascimento,
               status,
               criado_em,
               atualizado_em
    ");
    $parametrosAtualizacao = [
        ":id" => $id,
        ":nome_completo" => $nomeCompleto,
        ":departamento" => $departamento,
        ":empresa" => $empresa,
        ":rg" => $rg,
        ":cpf" => $cpf,
        ":celular" => $celular,
        ":data_nascimento" => $dataNascimento,
        ":status" => $status,
    ];

    if ($usuarioSessaoAdministrador) {
        $parametrosAtualizacao[":tipo_usuario"] = $tipoUsuario;
    }

    $consultaPreparada->execute($parametrosAtualizacao);

    $funcionario = $consultaPreparada->fetch();

    if (!$funcionario) {
        responderFuncionario(false, "O perfil foi alterado ou exige autorizacao de administrador.", 409);
    }

    if ($usuarioSessaoId === $id) {
        $_SESSION["usuario"] = array_merge($_SESSION["usuario"], [
            "nome_completo" => (string) ($funcionario["nome_completo"] ?? $nomeCompleto),
            "tipo_usuario" => (string) ($funcionario["tipo_usuario"] ?? $tipoUsuario),
            "departamento" => (string) ($funcionario["departamento"] ?? $departamento),
            "empresa" => (string) ($funcionario["empresa"] ?? $empresa),
        ]);
    }

    responderFuncionario(true, "Funcionario atualizado com sucesso.", 200, [
        "funcionario" => $funcionario,
    ]);
} catch (PDOException $erro) {
    if (str_contains($erro->getMessage(), "perfis_usuarios_cpf_key")) {
        responderFuncionario(false, "Este CPF ja esta cadastrado para outro funcionario.", 409);
    }

    if (str_contains($erro->getMessage(), "perfis_usuarios_rg_key")) {
        responderFuncionario(false, "Este RG ja esta cadastrado para outro funcionario.", 409);
    }

    responderFuncionario(false, "Nao foi possivel atualizar o funcionario agora.", 500);
} catch (Throwable) {
    responderFuncionario(false, "Nao foi possivel atualizar o funcionario agora.", 500);
}
