<?php

declare(strict_types=1);

function sanitizarCelulaCsv(mixed $valor): string
{
    $texto = str_replace("\0", "", (string) $valor);
    $texto = preg_replace('/^\xEF\xBB\xBF/', "", $texto) ?? $texto;

    if ($texto === "") {
        return "";
    }

    $primeiroConteudo = ltrim($texto, " \t\r\n\v\f");
    $primeiroCaractere = $primeiroConteudo[0] ?? "";
    $comecaComControle = preg_match('/^[\x00-\x1F]/', $texto) === 1;
    $comecaComFormula = in_array($primeiroCaractere, ["=", "+", "-", "@"], true);

    // O apostrofo faz Excel e similares tratarem a celula como texto, evitando formula injection.
    return $comecaComControle || $comecaComFormula ? "'" . $texto : $texto;
}

function gerarCsvAtivos(array $ativos, bool $incluirResponsavel): string
{
    $cabecalho = [
        "ID",
        "Nome",
        "Descri\xc3\xa7\xc3\xa3o",
        "N\xc3\xbamero de s\xc3\xa9rie",
        "IMEI",
        "Categoria",
        "Marca",
        "Propriedade",
        "Localiza\xc3\xa7\xc3\xa3o",
    ];

    if ($incluirResponsavel) {
        $cabecalho[] = "Respons\xc3\xa1vel";
    }

    $cabecalho = array_merge($cabecalho, [
        "Status",
        "Datasheet",
        "Data de cadastro",
    ]);

    $output = fopen("php://temp", "w+b");

    if ($output === false) {
        throw new RuntimeException("Nao foi possivel preparar o arquivo CSV.");
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $cabecalho, ";");

    foreach ($ativos as $ativo) {
        $linha = [
            $ativo["id"] ?? "",
            $ativo["nome"] ?? "",
            $ativo["descricao"] ?? "",
            $ativo["numero_serie"] ?? "",
            $ativo["imei"] ?? "",
            $ativo["categoria"] ?? "",
            $ativo["marca"] ?? "",
            $ativo["propriedade"] ?? "",
            $ativo["localizacao"] ?? "",
        ];

        if ($incluirResponsavel) {
            $linha[] = $ativo["responsavel"] ?? "";
        }

        $linha[] = $ativo["status"] ?? "";
        $linha[] = $ativo["datasheet"] ?? "";
        $linha[] = $ativo["criado_em_formatado"] ?? "";
        $linha = array_map("sanitizarCelulaCsv", $linha);

        if (fputcsv($output, $linha, ";") === false) {
            fclose($output);
            throw new RuntimeException("Nao foi possivel montar o arquivo CSV.");
        }
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    if ($csv === false) {
        throw new RuntimeException("Nao foi possivel finalizar o arquivo CSV.");
    }

    return $csv;
}
