<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responderImportacao(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(["ok" => $ok, "message" => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function campoImportacao(string $nome): string
{
    return trim((string) ($_POST[$nome] ?? ""));
}

function csrfImportacaoValido(): bool
{
    $tokenSessao = $_SESSION["csrf_token"] ?? "";
    $tokenPost = campoImportacao("csrf_token");

    return is_string($tokenSessao)
        && $tokenSessao !== ""
        && hash_equals($tokenSessao, $tokenPost);
}

function normalizarEspacosImportacao(string $valor): string
{
    return preg_replace("/\s+/u", " ", trim($valor)) ?? trim($valor);
}

function tamanhoTextoImportacao(string $valor): int
{
    return function_exists("mb_strlen") ? mb_strlen($valor, "UTF-8") : strlen($valor);
}

function valorOpcionalImportacao(string $valor): ?string
{
    $valor = normalizarEspacosImportacao($valor);

    return $valor !== "" ? $valor : null;
}

function limparValorCsv(string $valor): string
{
    $valor = preg_replace('/^\xEF\xBB\xBF/', "", $valor) ?? $valor;

    return trim($valor);
}

function validarNomeReferenciaImportacao(?string $valor, string $rotulo, int $minimo, int $maximo): array
{
    if ($valor === null) {
        return [];
    }

    $tamanho = tamanhoTextoImportacao($valor);

    if ($tamanho < $minimo || $tamanho > $maximo) {
        return ["{$rotulo} deve ter entre {$minimo} e {$maximo} caracteres."];
    }

    if (!preg_match("/^[\p{L}\p{N}\s.\-&+\/,\x{00BA}\x{00AA}\x{00B0}()]+$/u", $valor)) {
        return ["{$rotulo} possui caracteres nao permitidos."];
    }

    return [];
}

function validarUploadCsv(): array
{
    $arquivo = $_FILES["arquivo_csv"] ?? null;

    if (!is_array($arquivo) || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        responderImportacao(false, "Selecione um arquivo CSV para importar.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    if (($arquivo["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        responderImportacao(false, "Nao foi possivel receber o arquivo enviado.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    $nome = (string) ($arquivo["name"] ?? "");
    $tmp = (string) ($arquivo["tmp_name"] ?? "");
    $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    $mime = is_file($tmp) && function_exists("mime_content_type") ? (string) (mime_content_type($tmp) ?: "") : "";
    $mimesPermitidos = [
        "text/csv",
        "text/plain",
        "application/csv",
        "application/vnd.ms-excel",
        "application/vnd.ms-office",
        "text/comma-separated-values",
        "application/octet-stream",
    ];

    if ($extensao !== "csv") {
        responderImportacao(false, "Envie um arquivo com extensao .csv.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    if ($mime !== "" && !in_array($mime, $mimesPermitidos, true)) {
        responderImportacao(false, "O arquivo enviado nao parece ser um CSV valido.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    if ((int) ($arquivo["size"] ?? 0) > 5 * 1024 * 1024) {
        responderImportacao(false, "O CSV deve ter no maximo 5 MB.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    return $arquivo;
}

function linhaCsvVazia(array $linha): bool
{
    foreach ($linha as $valor) {
        if (trim((string) $valor) !== "") {
            return false;
        }
    }

    return true;
}

function normalizarCabecalhoCsv(array $cabecalho): array
{
    return array_map(static function ($valor): string {
        return strtolower(limparValorCsv((string) $valor));
    }, $cabecalho);
}

function montarRegistroCsv(array $cabecalhoEsperado, array $linha): array
{
    $linha = array_slice(array_pad($linha, count($cabecalhoEsperado), ""), 0, count($cabecalhoEsperado));

    return array_combine($cabecalhoEsperado, array_map(static function ($valor): string {
        return limparValorCsv((string) $valor);
    }, $linha)) ?: [];
}

function garantirEstruturaImportacao(PDO $pdo): void
{
    // Estas garantias deixam a importacao funcionar em bancos antigos sem tocar nas telas existentes.
    $pdo->exec("
        create table if not exists public.categorias_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");
    $pdo->exec("alter table public.categorias_ativos add column if not exists criado_em timestamptz not null default now()");
    $pdo->exec("alter table public.categorias_ativos add column if not exists atualizado_em timestamptz not null default now()");

    $pdo->exec("
        create table if not exists public.locais (
            id uuid primary key default gen_random_uuid(),
            nome text not null,
            endereco text,
            status text not null default 'Ativo',
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");
    $pdo->exec("alter table public.locais add column if not exists endereco text");
    $pdo->exec("alter table public.locais add column if not exists status text not null default 'Ativo'");
    $pdo->exec("alter table public.locais add column if not exists criado_em timestamptz not null default now()");
    $pdo->exec("alter table public.locais add column if not exists atualizado_em timestamptz not null default now()");

    $pdo->exec("
        create table if not exists public.marcas_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");
    $pdo->exec("
        create unique index if not exists marcas_ativos_nome_lower_unique
            on public.marcas_ativos (lower(nome))
    ");

    $pdo->exec("
        create table if not exists public.propriedade_ativos (
            id uuid primary key default gen_random_uuid(),
            nome text not null unique,
            status text not null default 'Ativa'
                check (status in ('Ativa', 'Inativa')),
            criado_em timestamptz not null default now(),
            atualizado_em timestamptz not null default now()
        )
    ");
    $pdo->exec("
        create unique index if not exists propriedade_ativos_nome_lower_unique
            on public.propriedade_ativos (lower(nome))
    ");
}

function buscarOuCriarCategoria(PDO $pdo, string $nome): string
{
    $stmt = $pdo->prepare("
        select id::text
          from public.categorias_ativos
         where lower(nome) = lower(:nome)
         limit 1
    ");
    $stmt->execute([":nome" => $nome]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (string) $id;
    }

    $stmt = $pdo->prepare("
        insert into public.categorias_ativos (nome, atualizado_em)
        values (:nome, now())
        returning id::text
    ");
    $stmt->execute([":nome" => $nome]);

    return (string) $stmt->fetchColumn();
}

function buscarOuCriarLocal(PDO $pdo, ?string $nome): ?string
{
    if ($nome === null) {
        return null;
    }

    $stmt = $pdo->prepare("
        select id::text
          from public.locais
         where lower(nome) = lower(:nome)
         limit 1
    ");
    $stmt->execute([":nome" => $nome]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (string) $id;
    }

    $stmt = $pdo->prepare("
        insert into public.locais (nome, status, atualizado_em)
        values (:nome, 'Ativo', now())
        returning id::text
    ");
    $stmt->execute([":nome" => $nome]);

    return (string) $stmt->fetchColumn();
}

function buscarMarcaAtiva(PDO $pdo, ?string $nome): ?string
{
    if ($nome === null) {
        return null;
    }

    $stmt = $pdo->prepare("
        select nome
          from public.marcas_ativos
         where lower(nome) = lower(:nome)
           and status = 'Ativa'
         limit 1
    ");
    $stmt->execute([":nome" => $nome]);
    $marca = $stmt->fetchColumn();

    return $marca !== false ? (string) $marca : null;
}

function buscarOuCriarPropriedade(PDO $pdo, ?string $nome): ?string
{
    if ($nome === null) {
        return null;
    }

    $stmt = $pdo->prepare("
        select nome
          from public.propriedade_ativos
         where lower(nome) = lower(:nome)
         limit 1
    ");
    $stmt->execute([":nome" => $nome]);
    $propriedade = $stmt->fetchColumn();

    if ($propriedade !== false) {
        return (string) $propriedade;
    }

    $stmt = $pdo->prepare("
        insert into public.propriedade_ativos (nome, status, atualizado_em)
        values (:nome, 'Ativa', now())
        returning nome
    ");
    $stmt->execute([":nome" => $nome]);

    return (string) $stmt->fetchColumn();
}

function mensagemDuplicidadeAtivo(PDO $pdo, ?string $numeroSerie, ?string $imei): ?string
{
    $where = [];
    $params = [];

    if ($numeroSerie !== null) {
        $where[] = "lower(numero_serie) = lower(:numero_serie)";
        $params[":numero_serie"] = $numeroSerie;
    }

    if ($imei !== null) {
        $where[] = "lower(imei) = lower(:imei)";
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

    if ($numeroSerie !== null && strcasecmp((string) ($ativo["numero_serie"] ?? ""), $numeroSerie) === 0) {
        return "Numero de serie ja cadastrado.";
    }

    return "IMEI ja cadastrado.";
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderImportacao(false, "Metodo nao permitido.", 405);
}

if (empty($_SESSION["usuario"]) || !is_array($_SESSION["usuario"])) {
    responderImportacao(false, "Sessao expirada. Faca login novamente.", 401);
}

require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("cadastrar_ativos", "Importacao de ativos");

if (!csrfImportacaoValido()) {
    responderImportacao(false, "Token de seguranca invalido. Atualize a pagina e tente novamente.", 403, [
        "importados" => 0,
        "ignorados" => 0,
        "erros" => [],
    ]);
}

$arquivo = validarUploadCsv();
$cabecalhoEsperado = [
    "nome",
    "descricao",
    "numero_serie",
    "imei",
    "categoria",
    "marca",
    "propriedade",
    "localizacao",
    "status",
    "datasheet",
];

try {
    require_once __DIR__ . "/Conexao.php";
    require_once __DIR__ . "/status-ativos.php";

    garantirEstruturaImportacao($pdo);
    garantirStatusAtivos($pdo);

    $handle = fopen((string) $arquivo["tmp_name"], "rb");

    if ($handle === false) {
        responderImportacao(false, "Nao foi possivel ler o arquivo enviado.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    $cabecalho = fgetcsv($handle, 0, ";");

    if ($cabecalho === false || normalizarCabecalhoCsv($cabecalho) !== $cabecalhoEsperado) {
        fclose($handle);
        responderImportacao(false, "Cabecalho invalido. Baixe o modelo CSV e mantenha a primeira linha igual.", 422, [
            "importados" => 0,
            "ignorados" => 0,
            "erros" => [],
        ]);
    }

    $linhaAtual = 1;
    $importados = 0;
    $ignorados = 0;
    $erros = [];

    while (($linha = fgetcsv($handle, 0, ";")) !== false) {
        $linhaAtual++;

        if (linhaCsvVazia($linha)) {
            $ignorados++;
            continue;
        }

        $registro = montarRegistroCsv($cabecalhoEsperado, $linha);
        $nome = normalizarEspacosImportacao($registro["nome"] ?? "");
        $descricao = valorOpcionalImportacao($registro["descricao"] ?? "");
        $numeroSerie = valorOpcionalImportacao($registro["numero_serie"] ?? "");
        $imei = valorOpcionalImportacao($registro["imei"] ?? "");
        $categoria = valorOpcionalImportacao($registro["categoria"] ?? "");
        $marcaCsv = valorOpcionalImportacao($registro["marca"] ?? "");
        $propriedadeCsv = valorOpcionalImportacao($registro["propriedade"] ?? "");
        $localizacaoCsv = valorOpcionalImportacao($registro["localizacao"] ?? "");
        $statusCsv = normalizarEspacosImportacao($registro["status"] ?? "");
        $datasheet = valorOpcionalImportacao($registro["datasheet"] ?? "");
        $errosLinha = [];

        if ($nome === "") {
            $errosLinha[] = "Nome e obrigatorio.";
        } elseif (tamanhoTextoImportacao($nome) < 2 || tamanhoTextoImportacao($nome) > 160) {
            $errosLinha[] = "Nome deve ter entre 2 e 160 caracteres.";
        }

        if ($categoria === null) {
            $errosLinha[] = "Categoria e obrigatoria.";
        }

        $errosLinha = array_merge(
            $errosLinha,
            validarNomeReferenciaImportacao($categoria, "Categoria", 2, 80),
            validarNomeReferenciaImportacao($marcaCsv, "Marca", 2, 80),
            validarNomeReferenciaImportacao($propriedadeCsv, "Propriedade", 2, 80),
            validarNomeReferenciaImportacao($localizacaoCsv, "Localizacao", 2, 100)
        );

        if ($descricao !== null && tamanhoTextoImportacao($descricao) > 1200) {
            $errosLinha[] = "Descricao deve ter no maximo 1200 caracteres.";
        }

        if ($numeroSerie !== null && tamanhoTextoImportacao($numeroSerie) > 100) {
            $errosLinha[] = "Numero de serie deve ter no maximo 100 caracteres.";
        }

        if ($imei !== null && tamanhoTextoImportacao($imei) > 80) {
            $errosLinha[] = "IMEI deve ter no maximo 80 caracteres.";
        }

        if ($datasheet !== null && tamanhoTextoImportacao($datasheet) > 500) {
            $errosLinha[] = "Datasheet deve ter no maximo 500 caracteres.";
        }

        $statusNormalizado = obterStatusAtivo($pdo, $statusCsv !== "" ? $statusCsv : statusAtivoPadrao());

        if ($statusNormalizado === null) {
            $errosLinha[] = "Status invalido.";
        }

        $marca = buscarMarcaAtiva($pdo, $marcaCsv);

        if ($marcaCsv !== null && $marca === null) {
            $errosLinha[] = "Marca nao encontrada ou inativa.";
        }

        $duplicidade = mensagemDuplicidadeAtivo($pdo, $numeroSerie, $imei);

        if ($duplicidade !== null) {
            $errosLinha[] = $duplicidade;
        }

        if ($errosLinha) {
            $ignorados++;
            $erros[] = [
                "linha" => $linhaAtual,
                "mensagem" => implode(" ", $errosLinha),
            ];
            continue;
        }

        try {
            $pdo->beginTransaction();

            $categoriaId = buscarOuCriarCategoria($pdo, (string) $categoria);
            $localId = buscarOuCriarLocal($pdo, $localizacaoCsv);
            $propriedade = buscarOuCriarPropriedade($pdo, $propriedadeCsv);

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
            ");
            $stmt->execute([
                ":nome" => $nome,
                ":descricao" => $descricao,
                ":numero_serie" => $numeroSerie,
                ":categoria_id" => $categoriaId,
                ":local_id" => $localId,
                ":status" => $statusNormalizado,
                ":marca" => $marca,
                ":propriedade" => $propriedade,
                ":imei" => $imei,
                ":datasheet" => $datasheet,
            ]);

            $pdo->commit();
            $importados++;
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $ignorados++;
            $erros[] = [
                "linha" => $linhaAtual,
                "mensagem" => "Nao foi possivel gravar esta linha.",
            ];
        }
    }

    fclose($handle);

    if ($importados === 0) {
        responderImportacao(false, "Nenhum ativo foi importado. Revise o arquivo e tente novamente.", 422, [
            "importados" => $importados,
            "ignorados" => $ignorados,
            "erros" => $erros,
        ]);
    }

    $mensagem = $erros
        ? "Importacao concluida com avisos."
        : "Importacao concluida com sucesso.";

    responderImportacao(true, $mensagem, 200, [
        "importados" => $importados,
        "ignorados" => $ignorados,
        "erros" => $erros,
    ]);
} catch (Throwable) {
    responderImportacao(false, "Nao foi possivel importar os ativos agora.", 500, [
        "importados" => 0,
        "ignorados" => 0,
        "erros" => [],
    ]);
}
