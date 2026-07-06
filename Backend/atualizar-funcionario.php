<?php

declare(strict_types=1);

// Endpoint de edicao de funcionarios. O e-mail fica fora da edicao para nao dessincronizar o login.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderFuncionario(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoFuncionario(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function usuarioAdministradorFuncionario(): bool
{
    $tipoUsuario = strtolower(trim((string) ($_SESSION["usuario"]["tipo_usuario"] ?? "")));

    return in_array($tipoUsuario, ["adm", "admin", "administrador"], true);
}

function csrfFuncionarioValido(): bool
{
    $tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");
    $tokenPost = campoFuncionario("csrf_token");

    return $tokenSessao !== "" && $tokenPost !== "" && hash_equals($tokenSessao, $tokenPost);
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

    for ($i = 0; $i < 9; $i++) {
        $soma += (int) $cpf[$i] * (10 - $i);
    }

    $primeiroDigito = ($soma * 10) % 11;
    $primeiroDigito = $primeiroDigito === 10 ? 0 : $primeiroDigito;

    if ($primeiroDigito !== (int) $cpf[9]) {
        return false;
    }

    $soma = 0;

    for ($i = 0; $i < 10; $i++) {
        $soma += (int) $cpf[$i] * (11 - $i);
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

if (!usuarioAdministradorFuncionario()) {
    responderFuncionario(false, "Apenas administradores podem editar funcionarios.", 403);
}

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

if (!in_array($departamento, ["TI", "Operacao", "Financeiro", "Administrativo", "Gestao"], true)) {
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

if ($usuarioSessaoId === $id && ($status !== "Ativo" || $tipoUsuario !== "Administrador")) {
    responderFuncionario(false, "Voce nao pode remover seu proprio acesso de administrador.", 422);
}

try {
    require __DIR__ . "/Conexao.php";

    $duplicadoStmt = $pdo->prepare("
        select cpf, rg
          from public.perfis_usuarios
         where (cpf = :cpf or rg = :rg)
           and id::text <> :id
         limit 1
    ");
    $duplicadoStmt->execute([
        ":cpf" => $cpf,
        ":rg" => $rg,
        ":id" => $id,
    ]);

    $duplicado = $duplicadoStmt->fetch();

    if ($duplicado) {
        if (($duplicado["cpf"] ?? "") === $cpf) {
            responderFuncionario(false, "Este CPF ja esta cadastrado para outro funcionario.", 409);
        }

        if (($duplicado["rg"] ?? "") === $rg) {
            responderFuncionario(false, "Este RG ja esta cadastrado para outro funcionario.", 409);
        }
    }

    $stmt = $pdo->prepare("
        update public.perfis_usuarios
           set nome_completo = :nome_completo,
               tipo_usuario = :tipo_usuario,
               departamento = :departamento,
               empresa = :empresa,
               rg = :rg,
               cpf = :cpf,
               celular = :celular,
               data_nascimento = :data_nascimento,
               status = :status,
               atualizado_em = now()
         where id::text = :id
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
    $stmt->execute([
        ":id" => $id,
        ":nome_completo" => $nomeCompleto,
        ":tipo_usuario" => $tipoUsuario,
        ":departamento" => $departamento,
        ":empresa" => $empresa,
        ":rg" => $rg,
        ":cpf" => $cpf,
        ":celular" => $celular,
        ":data_nascimento" => $dataNascimento,
        ":status" => $status,
    ]);

    $funcionario = $stmt->fetch();

    if (!$funcionario) {
        responderFuncionario(false, "Funcionario nao encontrado.", 404);
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
