<?php

declare(strict_types=1);

// Endpoint de criacao de ativos. Recebe o formulario, valida tudo e grava no banco.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    // Saida padrao em JSON para o JavaScript exibir mensagens sem recarregar a pagina.
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campo(string $nome): string
{
    // Busca um campo enviado via POST e remove espacos nas extremidades.
    return trim((string) ($_POST[$nome] ?? ""));
}

function campoNulo(string $nome): ?string
{
    // Campos opcionais viram null quando chegam vazios, combinando com o banco.
    $valor = campo($nome);

    return $valor !== "" ? $valor : null;
}

function csrfValido(): bool
{
    // Confere se o formulario veio da pagina atual e nao de uma requisicao externa.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function uuidValido(?string $valor): bool
{
    // Categoria e local sao UUIDs; valores vazios sao permitidos quando o campo e opcional.
    if ($valor === null || $valor === "") {
        return true;
    }

    return (bool) preg_match(
        "/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i",
        $valor
    );
}

function imeiValido(?string $valor): bool
{
    if ($valor === null) {
        return true;
    }

    return preg_match("/^[0-9]{8,20}$/", $valor) === 1;
}

function statusPermitido(string $status): bool
{
    // Mantido como apoio para a regra de status, embora o nome oficial venha de status-ativos.php.
    return in_array($status, [
        "Disponível",
        "Em uso",
        "Manutenção",
        "Formatação",
        "Homologação"
    ], true);
}

function garantirIndicesUnicosAtivos(PDO $pdo): void
{
    // Indices criados por migration. Mantido para compatibilidade.
}

function categoriaExiste(PDO $pdo, string $categoriaId): bool
{
    $stmt = $pdo->prepare("
        select 1
          from public.categorias_ativos
         where id = cast(:id as uuid)
         limit 1
    ");
    $stmt->execute([":id" => $categoriaId]);

    return $stmt->fetchColumn() !== false;
}

function localExiste(PDO $pdo, ?string $localId): bool
{
    if ($localId === null) {
        return true;
    }

    $stmt = $pdo->prepare("
        select 1
          from public.locais
         where id = cast(:id as uuid)
           and lower(coalesce(status, 'ativo')) = 'ativo'
         limit 1
    ");
    $stmt->execute([":id" => $localId]);

    return $stmt->fetchColumn() !== false;
}

function mensagemDuplicidadeAtivo(PDO $pdo, ?string $numeroSerie, ?string $imei): ?string
{
    $where = [];
    $params = [];

    if ($numeroSerie !== null) {
        $where[] = "lower(trim(numero_serie)) = lower(trim(:numero_serie))";
        $params[":numero_serie"] = $numeroSerie;
    }

    if ($imei !== null) {
        $where[] = "btrim(imei) ~ '^[0-9]{8,20}$' and btrim(imei) = btrim(:imei)";
        $params[":imei"] = $imei;
    }

    if (!$where) {
        return null;
    }

    $stmt = $pdo->prepare("
        select numero_serie, imei
          from public.ativos
         where " . implode(" or ", $where) . "
         limit 1
    ");
    $stmt->execute($params);
    $ativo = $stmt->fetch();

    if (!$ativo) {
        return null;
    }

    if ($numeroSerie !== null && strcasecmp(trim((string) ($ativo["numero_serie"] ?? "")), $numeroSerie) === 0) {
        return "Numero de serie ja cadastrado.";
    }

    return "IMEI ja cadastrado.";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responder(false, "Sessao expirada. Faca login novamente.", 401);
}

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_ativos", "Cadastro de ativos");

if (!csrfValido()) {
    responder(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403);
}

// Campos principais do ativo enviados pelo formulario.
$nome = campo("nome");
$descricao = campoNulo("descricao");
$numeroSerie = campoNulo("numero_serie");
$categoriaId = campoNulo("categoria_id");
$localId = campoNulo("local_id");
$status = campo("status") ?: "Disponível";
$marca = campoNulo("marca");
$propriedade = campoNulo("propriedade");
$imei = campoNulo("imei");
$datasheet = campoNulo("datasheet");

if ($nome === "") {
    responder(false, "Informe o nome do ativo.", 422);
}

if (strlen($nome) < 2) {
    responder(false, "O nome do ativo precisa ter pelo menos 2 caracteres.", 422);
}

if ($categoriaId === null) {
    responder(false, "Selecione a categoria do ativo.", 422);
}

if (!uuidValido($categoriaId) || !uuidValido($localId)) {
    responder(false, "Categoria ou local invalido.", 422);
}

if (!imeiValido($imei)) {
    responder(false, "IMEI deve conter apenas numeros, com 8 a 20 digitos.", 422);
}

try {
    // Carrega a conexão e as regras centralizadas de status usadas nesta operação.
    require __DIR__ . "/Conexao.php";
    require __DIR__ . "/status-ativos.php";

    garantirIndicesUnicosAtivos($pdo);

    // Converte o status recebido para o nome oficial cadastrado no banco.
    $statusNormalizado = obterStatusAtivo($pdo, $status);

    if ($statusNormalizado === null) {
        responder(false, "Status invalido para ativos.", 422);
    }

    $status = $statusNormalizado;

    if (!categoriaExiste($pdo, (string) $categoriaId)) {
        responder(false, "Categoria nao encontrada. Atualize a pagina e tente novamente.", 422);
    }

    if (!localExiste($pdo, $localId)) {
        responder(false, "Local nao encontrado. Atualize a pagina e tente novamente.", 422);
    }

    if ($marca !== null) {
        // So permite cadastrar ativo com marca ativa ja cadastrada.
        $marcaStmt = $pdo->prepare("
            select nome
              from public.marcas_ativos
             where lower(btrim(nome)) = lower(btrim(:marca))
               and status = :status
             limit 1
        ");
        $marcaStmt->execute([
            ":marca" => $marca,
            ":status" => "Ativa",
        ]);

        $marcaAtiva = $marcaStmt->fetchColumn();

        if ($marcaAtiva === false) {
            responder(false, "Selecione uma marca ativa cadastrada.", 422);
        }

        $marca = (string) $marcaAtiva;
    }

    $duplicidade = mensagemDuplicidadeAtivo($pdo, $numeroSerie, $imei);

    if ($duplicidade !== null) {
        responder(false, $duplicidade, 409);
    }

    // Insere o ativo e devolve os campos basicos para o frontend atualizar a tela.
    $stmt = $pdo->prepare("
        insert into public.ativos (
            nome,
            descricao,
            numero_serie,
            categoria_id,
            local_id,
            status,
            marca,
            propriedade,
            imei,
            datasheet
        ) values (
            :nome,
            :descricao,
            :numero_serie,
            :categoria_id,
            :local_id,
            :status,
            :marca,
            :propriedade,
            :imei,
            :datasheet
        )
        returning
            id,
            nome,
            status,
            criado_em
    ");

    $stmt->execute([
        ":nome" => $nome,
        ":descricao" => $descricao,
        ":numero_serie" => $numeroSerie,
        ":categoria_id" => $categoriaId,
        ":local_id" => $localId,
        ":status" => $status,
        ":marca" => $marca,
        ":propriedade" => $propriedade,
        ":imei" => $imei,
        ":datasheet" => $datasheet,
    ]);

    $ativo = $stmt->fetch();

    responder(true, "Ativo cadastrado com sucesso.", 201, [
        "ativo" => $ativo,
    ]);
} catch (PDOException $erro) {
    if ($erro->getCode() === "23505") {
        responder(false, "Ja existe um ativo com esses dados de identificacao.", 409);
    }

    if ($erro->getCode() === "23503") {
        responder(false, "Categoria, local ou status invalido para o ativo.", 422);
    }

    responder(false, "Nao foi possivel cadastrar o ativo agora.", 500);
} catch (Throwable) {
    responder(false, "Nao foi possivel cadastrar o ativo agora.", 500);
}
