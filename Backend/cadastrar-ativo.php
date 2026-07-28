<?php

declare(strict_types=1);

// Endpoint de criacao de ativos. Recebe o formulario, valida tudo e grava no banco.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

const QUANTIDADE_PN_MINIMA = 1;
const QUANTIDADE_PN_MAXIMA = 100;

function responder(bool $sucesso, string $mensagemResposta, int $codigoStatusHttp = 200, array $dadosAdicionais = []): void
{
    // Saida padrao em JSON para o JavaScript exibir mensagens sem recarregar a pagina.
    http_response_code($codigoStatusHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagemResposta], $dadosAdicionais),
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

function rastreabilidadePermitida(string $valor): bool
{
    return in_array($valor, ["nao_possui", "somente_pn", "somente_sn", "ambos"], true);
}

function mensagemRastreabilidadeAtivo(string $rastreabilidade, ?string $numeroSerie, ?string $numeroParte): ?string
{
    // A escolha de rastreabilidade define quais identificadores sao obrigatorios.
    if (in_array($rastreabilidade, ["somente_pn", "ambos"], true) && $numeroParte === null) {
        return "Informe o PN para a rastreabilidade escolhida.";
    }

    if (in_array($rastreabilidade, ["somente_sn", "ambos"], true) && $numeroSerie === null) {
        return "Informe o numero de serie para a rastreabilidade escolhida.";
    }

    return null;
}

function normalizarRastreabilidadeAtivo(string $rastreabilidade, ?string $numeroSerie, ?string $numeroParte): array
{
    // Campos fora da opcao marcada sao descartados para evitar identificadores ocultos no cadastro.
    return match ($rastreabilidade) {
        "somente_pn" => [null, $numeroParte],
        "somente_sn" => [$numeroSerie, null],
        "ambos" => [$numeroSerie, $numeroParte],
        default => [null, null],
    };
}

function quantidadeCadastroAtivo(string $rastreabilidade): int
{
    if ($rastreabilidade !== "somente_pn") {
        return QUANTIDADE_PN_MINIMA;
    }

    $quantidade = filter_input(
        INPUT_POST,
        "quantidade",
        FILTER_VALIDATE_INT,
        [
            "options" => [
                "min_range" => QUANTIDADE_PN_MINIMA,
                "max_range" => QUANTIDADE_PN_MAXIMA,
            ],
        ]
    );

    return is_int($quantidade) ? $quantidade : 0;
}

function csrfValido(): bool
{
    // Confere se o formulario veio da pagina atual e nao de uma requisicao externa.
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenRequisicao = campo("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenRequisicao);
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
    $consultaPreparada = $pdo->prepare("
        select 1
          from public.categorias_ativos
         where id = cast(:id as uuid)
         limit 1
    ");
    $consultaPreparada->execute([":id" => $categoriaId]);

    return $consultaPreparada->fetchColumn() !== false;
}

function localExiste(PDO $pdo, ?string $localId): bool
{
    if ($localId === null) {
        return true;
    }

    $consultaPreparada = $pdo->prepare("
        select 1
          from public.locais
         where id = cast(:id as uuid)
           and lower(coalesce(status, 'ativo')) = 'ativo'
         limit 1
    ");
    $consultaPreparada->execute([":id" => $localId]);

    return $consultaPreparada->fetchColumn() !== false;
}

function mensagemDuplicidadeAtivo(PDO $pdo, ?string $numeroSerie, ?string $imei): ?string
{
    $condicoesSql = [];
    $parametrosConsulta = [];

    if ($numeroSerie !== null) {
        $condicoesSql[] = "lower(trim(numero_serie)) = lower(trim(:numero_serie))";
        $parametrosConsulta[":numero_serie"] = $numeroSerie;
    }

    if ($imei !== null) {
        $condicoesSql[] = "btrim(imei) ~ '^[0-9]{8,20}$' and btrim(imei) = btrim(:imei)";
        $parametrosConsulta[":imei"] = $imei;
    }

    if (!$condicoesSql) {
        return null;
    }

    $consultaPreparada = $pdo->prepare("
        select numero_serie, imei
          from public.ativos
         where " . implode(" or ", $condicoesSql) . "
         limit 1
    ");
    $consultaPreparada->execute($parametrosConsulta);
    $ativo = $consultaPreparada->fetch();

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
$rastreabilidade = campo("rastreabilidade") ?: "nao_possui";
$numeroSerieEnviado = campoNulo("numero_serie");
$numeroParteEnviado = campoNulo("part_number");
$quantidadeCadastro = quantidadeCadastroAtivo($rastreabilidade);
$categoriaId = campoNulo("categoria_id");
$localId = campoNulo("local_id");
$status = campo("status") ?: "Disponível";
$marca = campoNulo("marca");
$propriedade = campoNulo("propriedade");
$imei = campoNulo("imei");
$fichaTecnica = campoNulo("datasheet");

if ($nome === "") {
    responder(false, "Informe o nome do ativo.", 422);
}

if (strlen($nome) < 2) {
    responder(false, "O nome do ativo precisa ter pelo menos 2 caracteres.", 422);
}

if (!rastreabilidadePermitida($rastreabilidade)) {
    responder(false, "Selecione uma opcao de rastreabilidade valida.", 422);
}

$mensagemRastreabilidade = mensagemRastreabilidadeAtivo($rastreabilidade, $numeroSerieEnviado, $numeroParteEnviado);

if ($mensagemRastreabilidade !== null) {
    responder(false, $mensagemRastreabilidade, 422);
}

if ($quantidadeCadastro === 0) {
    responder(
        false,
        "Informe uma quantidade entre " . QUANTIDADE_PN_MINIMA . " e " . QUANTIDADE_PN_MAXIMA . ".",
        422
    );
}

[$numeroSerie, $numeroParte] = normalizarRastreabilidadeAtivo(
    $rastreabilidade,
    $numeroSerieEnviado,
    $numeroParteEnviado
);

if ($numeroParte !== null && strlen($numeroParte) > 120) {
    responder(false, "PN deve ter no maximo 120 caracteres.", 422);
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

if ($quantidadeCadastro > 1 && $imei !== null) {
    responder(false, "Para cadastrar mais de uma unidade por PN, deixe o IMEI vazio.", 422);
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
        $consultaMarca = $pdo->prepare("
            select nome
              from public.marcas_ativos
             where lower(btrim(nome)) = lower(btrim(:marca))
               and status = :status
             limit 1
        ");
        $consultaMarca->execute([
            ":marca" => $marca,
            ":status" => "Ativa",
        ]);

        $marcaAtiva = $consultaMarca->fetchColumn();

        if ($marcaAtiva === false) {
            responder(false, "Selecione uma marca ativa cadastrada.", 422);
        }

        $marca = (string) $marcaAtiva;
    }

    $duplicidade = mensagemDuplicidadeAtivo($pdo, $numeroSerie, $imei);

    if ($duplicidade !== null) {
        responder(false, $duplicidade, 409);
    }

    // Somente PN representa varias unidades do mesmo modelo; cada unidade vira uma linha independente.
    $consultaPreparada = $pdo->prepare("
        insert into public.ativos (
            nome,
            descricao,
            numero_serie,
            part_number,
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
            :part_number,
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
            part_number,
            status,
            criado_em
    ");

    $ativosCriados = [];
    $pdo->beginTransaction();

    for ($indice = 0; $indice < $quantidadeCadastro; $indice++) {
        $consultaPreparada->execute([
            ":nome" => $nome,
            ":descricao" => $descricao,
            ":numero_serie" => $numeroSerie,
            ":part_number" => $numeroParte,
            ":categoria_id" => $categoriaId,
            ":local_id" => $localId,
            ":status" => $status,
            ":marca" => $marca,
            ":propriedade" => $propriedade,
            ":imei" => $imei,
            ":datasheet" => $fichaTecnica,
        ]);

        $ativoCriado = $consultaPreparada->fetch();

        if (is_array($ativoCriado)) {
            $ativosCriados[] = $ativoCriado;
        }
    }

    $pdo->commit();

    $mensagemSucesso = $quantidadeCadastro === 1
        ? "Ativo cadastrado com sucesso."
        : $quantidadeCadastro . " ativos cadastrados com sucesso.";

    responder(true, $mensagemSucesso, 201, [
        "ativo" => $ativosCriados[0] ?? null,
        "ativos" => $ativosCriados,
    ]);
} catch (PDOException $erro) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($erro->getCode() === "23505") {
        responder(false, "Ja existe um ativo com esses dados de identificacao.", 409);
    }

    if ($erro->getCode() === "23503") {
        responder(false, "Categoria, local ou status invalido para o ativo.", 422);
    }

    responder(false, "Nao foi possivel cadastrar o ativo agora.", 500);
} catch (Throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder(false, "Nao foi possivel cadastrar o ativo agora.", 500);
}
