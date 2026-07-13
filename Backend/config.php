<?php

declare(strict_types=1);

function caminhoEnvLocal(): string
{
    $arquivoEnv = getenv("APP_ENV_FILE");

    if (is_string($arquivoEnv) && trim($arquivoEnv) !== "") {
        return trim($arquivoEnv, "\"'");
    }

    // Mantem credenciais fora do DocumentRoot do XAMPP.
    return dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . "private"
        . DIRECTORY_SEPARATOR . "site-gestao-de-ativos"
        . DIRECTORY_SEPARATOR . "Backend"
        . DIRECTORY_SEPARATOR . ".env";
}

// Carrega variaveis de ambiente locais uma unica vez. Em producao, essas
// mesmas chaves podem vir direto do servidor.
function carregarEnvLocal(): void
{
    static $carregado = false;

    if ($carregado) {
        return;
    }

    $carregado = true;
    $arquivoEnv = caminhoEnvLocal();

    if (!is_file($arquivoEnv) || !is_readable($arquivoEnv)) {
        return;
    }

    $linhas = file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        // Ignora comentarios e linhas sem chave=valor para evitar configuracao quebrada.
        $linha = trim($linha);

        if ($linha === "" || str_starts_with($linha, "#") || !str_contains($linha, "=")) {
            continue;
        }

        [$chave, $valor] = array_map("trim", explode("=", $linha, 2));
        $valor = trim($valor, "\"'");

        if ($chave === "" || getenv($chave) !== false) {
            continue;
        }

        putenv($chave . "=" . $valor);
        $_ENV[$chave] = $valor;
    }
}

function configValor(string $chave, ?string $padrao = null): ?string
{
    // Primeiro garante que o .env local foi lido, depois consulta o ambiente.
    carregarEnvLocal();

    $valor = getenv($chave);

    if ($valor === false || $valor === "") {
        return $padrao;
    }

    return $valor;
}

function configObrigatoria(string $chave): string
{
    // Usado para credenciais sem as quais a aplicacao nao pode funcionar.
    $valor = configValor($chave);

    if ($valor === null || $valor === "") {
        throw new RuntimeException("Configuracao ausente: " . $chave);
    }

    return $valor;
}
