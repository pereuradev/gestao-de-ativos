<?php

declare(strict_types=1);

// Gera o relatório XLSX diretamente no padrão Open XML, sem depender de biblioteca externa.
final class RelatorioAtivosXlsx
{
    public const CONTENT_TYPE = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    // Os índices precisam acompanhar a ordem dos estilos declarados em styles.xml.
    private const STYLE_DEFAULT = 0;
    private const STYLE_TITLE = 1;
    private const STYLE_SUBTITLE = 2;
    private const STYLE_GENERATED_LABEL = 3;
    private const STYLE_GENERATED_VALUE = 4;
    private const STYLE_BRAND_NAVY = 5;
    private const STYLE_BRAND_BLUE = 6;
    private const STYLE_BRAND_MINT = 7;
    private const STYLE_SECTION = 8;
    private const STYLE_METRIC_LABEL_NAVY = 9;
    private const STYLE_METRIC_LABEL_BLUE = 10;
    private const STYLE_METRIC_LABEL_MINT = 11;
    private const STYLE_METRIC_VALUE = 12;
    private const STYLE_FILTER_HEADER = 13;
    private const STYLE_FILTER_VALUE = 14;
    private const STYLE_TABLE_HEADER = 15;
    private const STYLE_TEXT = 16;
    private const STYLE_TEXT_ALT = 17;
    private const STYLE_WRAP = 18;
    private const STYLE_WRAP_ALT = 19;
    private const STYLE_DATE = 20;
    private const STYLE_DATE_ALT = 21;
    private const STYLE_LINK = 22;
    private const STYLE_LINK_ALT = 23;
    private const STYLE_STATUS_AVAILABLE = 24;
    private const STYLE_EMPTY = 25;

    // Monta as partes Open XML em arquivo temporário e sempre remove o artefato ao terminar.
    public function gerar(
        array $ativos,
        array $metricas,
        array $filtros,
        DateTimeImmutable $geradoEm,
        bool $incluirResponsavel
    ): string {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("A extensao ZIP do PHP nao esta disponivel.");
        }

        $caminhoLogo = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "logo-branca.png";

        if (!is_file($caminhoLogo) || !is_readable($caminhoLogo)) {
            throw new RuntimeException("O logotipo da TI TECH nao esta disponivel para a planilha.");
        }

        $colunas = $this->colunas($incluirResponsavel);
        $resumo = $this->montarPlanilhaResumo($metricas, $filtros, $geradoEm, count($colunas));
        $detalhes = $this->montarPlanilhaDetalhes($ativos, $colunas, $geradoEm);
        $caminhoTemporario = tempnam(sys_get_temp_dir(), "titech-xlsx-");

        if ($caminhoTemporario === false) {
            throw new RuntimeException("Nao foi possivel preparar a planilha.");
        }

        try {
            $arquivoZip = new ZipArchive();
            $resultadoAbertura = $arquivoZip->open($caminhoTemporario, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($resultadoAbertura !== true) {
                throw new RuntimeException("Nao foi possivel criar o arquivo XLSX.");
            }

            $this->adicionarParte($arquivoZip, "[Content_Types].xml", $this->tiposConteudoXml());
            $this->adicionarParte($arquivoZip, "_rels/.rels", $this->relacionamentosRaizXml());
            $this->adicionarParte($arquivoZip, "docProps/app.xml", $this->propriedadesAplicacaoXml());
            $this->adicionarParte($arquivoZip, "docProps/core.xml", $this->propriedadesPrincipaisXml($geradoEm));
            $this->adicionarParte(
                $arquivoZip,
                "xl/workbook.xml",
                $this->pastaTrabalhoXml($resumo["lastRow"], $detalhes["lastRow"], count($colunas))
            );
            $this->adicionarParte($arquivoZip, "xl/_rels/workbook.xml.rels", $this->relacionamentosPastaTrabalhoXml());
            $this->adicionarParte($arquivoZip, "xl/styles.xml", $this->estilosXml());
            $this->adicionarParte($arquivoZip, "xl/worksheets/sheet1.xml", $resumo["xml"]);
            $this->adicionarParte($arquivoZip, "xl/worksheets/_rels/sheet1.xml.rels", $this->relacionamentosPlanilhaXml(1, []));
            $this->adicionarParte($arquivoZip, "xl/worksheets/sheet2.xml", $detalhes["xml"]);
            $this->adicionarParte(
                $arquivoZip,
                "xl/worksheets/_rels/sheet2.xml.rels",
                $this->relacionamentosPlanilhaXml(2, $detalhes["relationships"])
            );
            $this->adicionarParte($arquivoZip, "xl/drawings/drawing1.xml", $this->desenhoXml("Logo TI TECH - Resumo"));
            $this->adicionarParte(
                $arquivoZip,
                "xl/drawings/_rels/drawing1.xml.rels",
                $this->relacionamentosDesenhoXml("logo-summary.png")
            );
            $this->adicionarParte($arquivoZip, "xl/drawings/drawing2.xml", $this->desenhoXml("Logo TI TECH - Ativos"));
            $this->adicionarParte(
                $arquivoZip,
                "xl/drawings/_rels/drawing2.xml.rels",
                $this->relacionamentosDesenhoXml("logo-details.png")
            );

            if (
                !$arquivoZip->addFile($caminhoLogo, "xl/media/logo-summary.png")
                || !$arquivoZip->addFile($caminhoLogo, "xl/media/logo-details.png")
            ) {
                throw new RuntimeException("Nao foi possivel incluir o logotipo na planilha.");
            }

            if (!$arquivoZip->close()) {
                throw new RuntimeException("Nao foi possivel finalizar o arquivo XLSX.");
            }

            $conteudoBinario = file_get_contents($caminhoTemporario);

            if ($conteudoBinario === false || $conteudoBinario === "") {
                throw new RuntimeException("A planilha gerada esta vazia.");
            }

            return $conteudoBinario;
        } finally {
            if (is_file($caminhoTemporario)) {
                @unlink($caminhoTemporario);
            }
        }
    }

    private function colunas(bool $incluirResponsavel): array
    {
        $colunas = [
            ["key" => "id", "title" => "ID", "width" => 38.0, "kind" => "text"],
            ["key" => "nome", "title" => "Nome", "width" => 30.0, "kind" => "wrap"],
            ["key" => "descricao", "title" => "Descri\xc3\xa7\xc3\xa3o", "width" => 42.0, "kind" => "wrap"],
            ["key" => "numero_serie", "title" => "N\xc3\xbamero de s\xc3\xa9rie", "width" => 22.0, "kind" => "text"],
            ["key" => "part_number", "title" => "PN", "width" => 20.0, "kind" => "text"],
            ["key" => "imei", "title" => "IMEI", "width" => 22.0, "kind" => "text"],
            ["key" => "categoria", "title" => "Categoria", "width" => 20.0, "kind" => "text"],
            ["key" => "marca", "title" => "Marca", "width" => 18.0, "kind" => "text"],
            ["key" => "propriedade", "title" => "Propriedade", "width" => 20.0, "kind" => "text"],
            ["key" => "localizacao", "title" => "Localiza\xc3\xa7\xc3\xa3o", "width" => 26.0, "kind" => "wrap"],
        ];

        if ($incluirResponsavel) {
            $colunas[] = [
                "key" => "responsavel",
                "title" => "Respons\xc3\xa1vel",
                "width" => 26.0,
                "kind" => "wrap",
            ];
        }

        return array_merge($colunas, [
            ["key" => "status", "title" => "Status", "width" => 18.0, "kind" => "status"],
            ["key" => "datasheet", "title" => "Datasheet", "width" => 34.0, "kind" => "link"],
            ["key" => "criado_em", "title" => "Data de cadastro", "width" => 20.0, "kind" => "date"],
        ]);
    }

    // A primeira planilha reúne identidade visual, métricas e filtros aplicados.
    private function montarPlanilhaResumo(
        array $metricas,
        array $filtros,
        DateTimeImmutable $geradoEm,
        int $indiceUltimaColuna
    ): array {
        $ultimaColuna = $this->letraColuna($indiceUltimaColuna);
        $linhas = [];
        $mesclagens = [
            "A1:B3",
            "C1:H1",
            "C2:H2",
            "I1:{$ultimaColuna}1",
            "I2:{$ultimaColuna}2",
            "A6:{$ultimaColuna}6",
            "A7:D7",
            "A8:D8",
            "E7:H7",
            "E8:H8",
            "I7:{$ultimaColuna}7",
            "I8:{$ultimaColuna}8",
            "A10:{$ultimaColuna}10",
            "A11:C11",
            "D11:{$ultimaColuna}11",
        ];

        $linhas[] = $this->linhaEstilizada(1, 28.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY, [
            3 => $this->celulaTexto(3, 1, "RELAT\xc3\x93RIO DE ATIVOS", self::STYLE_TITLE),
            9 => $this->celulaTexto(9, 1, "GERADO EM", self::STYLE_GENERATED_LABEL),
        ]);
        $linhas[] = $this->linhaEstilizada(2, 22.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY, [
            3 => $this->celulaTexto(3, 2, "Invent\xc3\xa1rio corporativo consolidado", self::STYLE_SUBTITLE),
            9 => $this->celulaTexto(
                9,
                2,
                $geradoEm->format("d/m/Y H:i"),
                self::STYLE_GENERATED_VALUE
            ),
        ]);
        $linhas[] = $this->linhaEstilizada(3, 18.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY);
        $linhas[] = $this->linhaBarraMarca(4, $indiceUltimaColuna);
        $linhas[] = $this->linhaEstilizada(5, 9.0, $indiceUltimaColuna, self::STYLE_DEFAULT);
        $linhas[] = $this->linhaEstilizada(6, 24.0, $indiceUltimaColuna, self::STYLE_SECTION, [
            1 => $this->celulaTexto(1, 6, "VIS\xc3\x83O GERAL DO INVENT\xc3\x81RIO", self::STYLE_SECTION),
        ]);

        $rotulosMetricas = [];
        $valoresMetricas = [];
        $this->preencherIntervalo(
            $rotulosMetricas,
            7,
            1,
            4,
            self::STYLE_METRIC_LABEL_NAVY,
            "Total de ativos"
        );
        $this->preencherIntervalo(
            $rotulosMetricas,
            7,
            5,
            8,
            self::STYLE_METRIC_LABEL_BLUE,
            "Em estoque"
        );
        $this->preencherIntervalo(
            $rotulosMetricas,
            7,
            9,
            $indiceUltimaColuna,
            self::STYLE_METRIC_LABEL_MINT,
            "Registros no relat\xc3\xb3rio"
        );
        $this->preencherIntervalo(
            $valoresMetricas,
            8,
            1,
            4,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metricas["total"] ?? 0)),
            true
        );
        $this->preencherIntervalo(
            $valoresMetricas,
            8,
            5,
            8,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metricas["disponiveis"] ?? 0)),
            true
        );
        $this->preencherIntervalo(
            $valoresMetricas,
            8,
            9,
            $indiceUltimaColuna,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metricas["filtrados"] ?? 0)),
            true
        );
        $linhas[] = $this->linhaEstilizada(7, 22.0, $indiceUltimaColuna, self::STYLE_DEFAULT, $rotulosMetricas);
        $linhas[] = $this->linhaEstilizada(8, 34.0, $indiceUltimaColuna, self::STYLE_DEFAULT, $valoresMetricas);
        $linhas[] = $this->linhaEstilizada(9, 10.0, $indiceUltimaColuna, self::STYLE_DEFAULT);
        $linhas[] = $this->linhaEstilizada(10, 24.0, $indiceUltimaColuna, self::STYLE_SECTION, [
            1 => $this->celulaTexto(1, 10, "FILTROS DO RELAT\xc3\x93RIO", self::STYLE_SECTION),
        ]);

        $cabecalhoFiltros = [];
        $this->preencherIntervalo($cabecalhoFiltros, 11, 1, 3, self::STYLE_FILTER_HEADER, "Filtro");
        $this->preencherIntervalo($cabecalhoFiltros, 11, 4, $indiceUltimaColuna, self::STYLE_FILTER_HEADER, "Valor");
        $linhas[] = $this->linhaEstilizada(11, 22.0, $indiceUltimaColuna, self::STYLE_DEFAULT, $cabecalhoFiltros);

        $linhasFiltros = $filtros === [] ? ["Filtros aplicados" => "Nenhum"] : $filtros;
        $numeroLinhaFiltro = 12;

        foreach ($linhasFiltros as $nome => $valorCelula) {
            $celulas = [];
            $this->preencherIntervalo(
                $celulas,
                $numeroLinhaFiltro,
                1,
                3,
                self::STYLE_FILTER_VALUE,
                (string) $nome
            );
            $this->preencherIntervalo(
                $celulas,
                $numeroLinhaFiltro,
                4,
                $indiceUltimaColuna,
                self::STYLE_FILTER_VALUE,
                (string) $valorCelula
            );
            $linhas[] = $this->linhaEstilizada(
                $numeroLinhaFiltro,
                28.0,
                $indiceUltimaColuna,
                self::STYLE_DEFAULT,
                $celulas
            );
            $mesclagens[] = "A{$numeroLinhaFiltro}:C{$numeroLinhaFiltro}";
            $mesclagens[] = "D{$numeroLinhaFiltro}:{$ultimaColuna}{$numeroLinhaFiltro}";
            $numeroLinhaFiltro++;
        }

        $ultimaLinha = $numeroLinhaFiltro - 1;
        $colunasXml = '<cols><col min="1" max="2" width="14" customWidth="1"/>'
            . '<col min="3" max="' . $indiceUltimaColuna . '" width="13" customWidth="1"/></cols>';
        $conteudoXml = $this->abrirXmlPlanilha("A1:{$ultimaColuna}{$ultimaLinha}", $colunasXml, null)
            . '<sheetData>' . implode("", $linhas) . '</sheetData>'
            . $this->mesclarCelulasXml($mesclagens)
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.35" right="0.35" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="1"/>'
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';

        return ["xml" => $conteudoXml, "lastRow" => $ultimaLinha];
    }

    // A segunda planilha contém os ativos e relacionamentos de hyperlinks externos.
    private function montarPlanilhaDetalhes(
        array $ativos,
        array $colunas,
        DateTimeImmutable $geradoEm
    ): array {
        $indiceUltimaColuna = count($colunas);
        $ultimaColuna = $this->letraColuna($indiceUltimaColuna);
        $linhas = [];
        $mesclagens = [
            "A1:B3",
            "C1:H1",
            "C2:H2",
            "I1:{$ultimaColuna}1",
            "I2:{$ultimaColuna}2",
        ];

        $linhas[] = $this->linhaEstilizada(1, 28.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY, [
            3 => $this->celulaTexto(3, 1, "DADOS DOS ATIVOS", self::STYLE_TITLE),
            9 => $this->celulaTexto(9, 1, "GERADO EM", self::STYLE_GENERATED_LABEL),
        ]);
        $linhas[] = $this->linhaEstilizada(2, 22.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY, [
            3 => $this->celulaTexto(3, 2, "Invent\xc3\xa1rio corporativo | Registros filtrados", self::STYLE_SUBTITLE),
            9 => $this->celulaTexto(
                9,
                2,
                $geradoEm->format("d/m/Y H:i"),
                self::STYLE_GENERATED_VALUE
            ),
        ]);
        $linhas[] = $this->linhaEstilizada(3, 18.0, $indiceUltimaColuna, self::STYLE_BRAND_NAVY);
        $linhas[] = $this->linhaBarraMarca(4, $indiceUltimaColuna);

        $celulasCabecalho = [];
        foreach ($colunas as $indice => $coluna) {
            $indiceColuna = $indice + 1;
            $celulasCabecalho[$indiceColuna] = $this->celulaTexto(
                $indiceColuna,
                5,
                (string) $coluna["title"],
                self::STYLE_TABLE_HEADER
            );
        }
        $linhas[] = $this->linhaEstilizada(5, 32.0, $indiceUltimaColuna, self::STYLE_TABLE_HEADER, $celulasCabecalho);

        $hiperlinks = [];
        $relacionamentos = [];
        $idRelacionamento = 2;
        $linhaDados = 6;

        foreach ($ativos as $indiceAtivo => $ativo) {
            $ativo = is_array($ativo) ? $ativo : [];
            $alternado = $indiceAtivo % 2 === 1;
            $celulas = [];

            foreach ($colunas as $indiceColunaZero => $coluna) {
                $indiceColuna = $indiceColunaZero + 1;
                $chaveItem = (string) $coluna["key"];
                $tipo = (string) $coluna["kind"];
                $valorCelula = $ativo[$chaveItem] ?? "";
                $celulas[$indiceColuna] = $this->celulaDados(
                    $indiceColuna,
                    $linhaDados,
                    $valorCelula,
                    $tipo,
                    $alternado,
                    $geradoEm,
                    $idRelacionamento,
                    $hiperlinks,
                    $relacionamentos
                );
            }

            $linhas[] = $this->linhaEstilizada(
                $linhaDados,
                30.0,
                $indiceUltimaColuna,
                $alternado ? self::STYLE_TEXT_ALT : self::STYLE_TEXT,
                $celulas
            );
            $linhaDados++;
        }

        if ($ativos === []) {
            $celulasVazias = [];
            $this->preencherIntervalo(
                $celulasVazias,
                6,
                1,
                $indiceUltimaColuna,
                self::STYLE_EMPTY,
                "Nenhum ativo encontrado para os filtros aplicados."
            );
            $linhas[] = $this->linhaEstilizada(6, 34.0, $indiceUltimaColuna, self::STYLE_EMPTY, $celulasVazias);
            $mesclagens[] = "A6:{$ultimaColuna}6";
            $ultimaLinha = 6;
        } else {
            $ultimaLinha = $linhaDados - 1;
        }

        $colunasXml = '<cols>';
        foreach ($colunas as $indice => $coluna) {
            $numeroColuna = $indice + 1;
            $colunasXml .= '<col min="' . $numeroColuna . '" max="' . $numeroColuna
                . '" width="' . $this->decimal((float) $coluna["width"])
                . '" customWidth="1"/>';
        }
        $colunasXml .= '</cols>';

        $hiperlinksXml = $hiperlinks === []
            ? ""
            : '<hyperlinks>' . implode("", $hiperlinks) . '</hyperlinks>';
        $errosIgnoradosXml = $ativos === []
            ? ""
            : '<ignoredErrors><ignoredError sqref="A6:F' . $ultimaLinha
                . '" numberStoredAsText="1"/></ignoredErrors>';
        $conteudoXml = $this->abrirXmlPlanilha(
            "A1:{$ultimaColuna}{$ultimaLinha}",
            $colunasXml,
            '<pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A6" sqref="A6"/>'
        )
            . '<sheetData>' . implode("", $linhas) . '</sheetData>'
            . '<autoFilter ref="A5:' . $ultimaColuna . $ultimaLinha . '"/>'
            . $this->mesclarCelulasXml($mesclagens)
            . $hiperlinksXml
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . $errosIgnoradosXml
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';

        return [
            "xml" => $conteudoXml,
            "lastRow" => $ultimaLinha,
            "relationships" => $relacionamentos,
        ];
    }

    // Escolhe tipo e estilo da célula sem permitir XML não escapado nos valores.
    private function celulaDados(
        int $indiceColuna,
        int $numeroLinha,
        mixed $valorCelula,
        string $tipo,
        bool $alternado,
        DateTimeImmutable $geradoEm,
        int &$idRelacionamento,
        array &$hiperlinks,
        array &$relacionamentos
    ): string {
        $estiloTexto = $alternado ? self::STYLE_TEXT_ALT : self::STYLE_TEXT;
        $estiloQuebra = $alternado ? self::STYLE_WRAP_ALT : self::STYLE_WRAP;

        if ($tipo === "date") {
            $data = $this->valorData($valorCelula, $geradoEm->getTimezone());

            if ($data !== null) {
                return $this->celulaNumero(
                    $indiceColuna,
                    $numeroLinha,
                    $this->numeroSerialDataExcel($data),
                    $alternado ? self::STYLE_DATE_ALT : self::STYLE_DATE
                );
            }

            return $this->celulaTexto($indiceColuna, $numeroLinha, "", $estiloTexto);
        }

        $texto = trim((string) $valorCelula);

        if ($tipo === "link" && $this->urlExternaValida($texto)) {
            $referenciaCelula = $this->letraColuna($indiceColuna) . $numeroLinha;
            $relacionamentoAtual = "rId" . $idRelacionamento;
            $hiperlinks[] = '<hyperlink ref="' . $referenciaCelula . '" r:id="'
                . $relacionamentoAtual . '"/>';
            $relacionamentos[] = ["id" => $relacionamentoAtual, "target" => $texto];
            $idRelacionamento++;

            return $this->celulaTexto(
                $indiceColuna,
                $numeroLinha,
                $texto,
                $alternado ? self::STYLE_LINK_ALT : self::STYLE_LINK
            );
        }

        if ($tipo === "status" && $this->statusDisponivel($texto)) {
            return $this->celulaTexto(
                $indiceColuna,
                $numeroLinha,
                $texto,
                self::STYLE_STATUS_AVAILABLE
            );
        }

        return $this->celulaTexto(
            $indiceColuna,
            $numeroLinha,
            $texto,
            $tipo === "wrap" ? $estiloQuebra : $estiloTexto
        );
    }

    private function valorData(mixed $valorCelula, DateTimeZone $fusoHorario): ?DateTimeImmutable
    {
        if ($valorCelula instanceof DateTimeImmutable) {
            return $valorCelula->setTimezone($fusoHorario);
        }

        if ($valorCelula instanceof DateTimeInterface) {
            return (new DateTimeImmutable($valorCelula->format(DateTimeInterface::ATOM)))->setTimezone($fusoHorario);
        }

        $texto = trim((string) $valorCelula);

        if ($texto === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable($texto))->setTimezone($fusoHorario);
        } catch (Throwable) {
            return null;
        }
    }

    private function numeroSerialDataExcel(DateTimeImmutable $data): float
    {
        $marcaTempoLocal = gmmktime(
            (int) $data->format("H"),
            (int) $data->format("i"),
            (int) $data->format("s"),
            (int) $data->format("m"),
            (int) $data->format("d"),
            (int) $data->format("Y")
        );

        return ($marcaTempoLocal / 86400) + 25569;
    }

    private function urlExternaValida(string $valorCelula): bool
    {
        if ($valorCelula === "" || filter_var($valorCelula, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($valorCelula, PHP_URL_SCHEME)), ["http", "https"], true);
    }

    private function statusDisponivel(string $status): bool
    {
        $normalizado = strtolower(trim($status));

        return str_starts_with($normalizado, "dispon") || $normalizado === "em estoque";
    }

    private function abrirXmlPlanilha(string $dimensao, string $colunasXml, ?string $painelXml): string
    {
        $visualizacaoPlanilha = '<sheetView showGridLines="0" zoomScale="90" zoomScaleNormal="90" workbookViewId="0">'
            . ($painelXml ?? '<selection activeCell="A1" sqref="A1"/>')
            . '</sheetView>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="' . $dimensao . '"/>'
            . '<sheetViews>' . $visualizacaoPlanilha . '</sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $colunasXml;
    }

    private function linhaEstilizada(
        int $numeroLinha,
        float $altura,
        int $indiceUltimaColuna,
        int $estiloBase,
        array $substituicoes = []
    ): string {
        $celulas = "";

        for ($coluna = 1; $coluna <= $indiceUltimaColuna; $coluna++) {
            $celulas .= $substituicoes[$coluna] ?? $this->celulaVazia($coluna, $numeroLinha, $estiloBase);
        }

        return '<row r="' . $numeroLinha . '" ht="' . $this->decimal($altura)
            . '" customHeight="1">' . $celulas . '</row>';
    }

    private function linhaBarraMarca(int $numeroLinha, int $indiceUltimaColuna): string
    {
        $primeiraQuebra = max(1, (int) floor($indiceUltimaColuna * 0.4));
        $segundaQuebra = max($primeiraQuebra + 1, (int) floor($indiceUltimaColuna * 0.7));
        $substituicoes = [];

        for ($coluna = 1; $coluna <= $indiceUltimaColuna; $coluna++) {
            $estilo = $coluna <= $primeiraQuebra
                ? self::STYLE_BRAND_NAVY
                : ($coluna <= $segundaQuebra ? self::STYLE_BRAND_BLUE : self::STYLE_BRAND_MINT);
            $substituicoes[$coluna] = $this->celulaVazia($coluna, $numeroLinha, $estilo);
        }

        return $this->linhaEstilizada($numeroLinha, 6.0, $indiceUltimaColuna, self::STYLE_DEFAULT, $substituicoes);
    }

    private function preencherIntervalo(
        array &$celulas,
        int $numeroLinha,
        int $colunaInicial,
        int $colunaFinal,
        int $estilo,
        string $valorCelula,
        bool $numerico = false
    ): void {
        for ($coluna = $colunaInicial; $coluna <= $colunaFinal; $coluna++) {
            if ($coluna === $colunaInicial) {
                $celulas[$coluna] = $numerico
                    ? $this->celulaNumero($coluna, $numeroLinha, (float) $valorCelula, $estilo)
                    : $this->celulaTexto($coluna, $numeroLinha, $valorCelula, $estilo);
                continue;
            }

            $celulas[$coluna] = $this->celulaVazia($coluna, $numeroLinha, $estilo);
        }
    }

    private function celulaVazia(int $coluna, int $numeroLinha, int $estilo): string
    {
        return '<c r="' . $this->letraColuna($coluna) . $numeroLinha . '" s="' . $estilo . '"/>';
    }

    private function celulaTexto(int $coluna, int $numeroLinha, string $valorCelula, int $estilo): string
    {
        return '<c r="' . $this->letraColuna($coluna) . $numeroLinha . '" s="' . $estilo
            . '" t="inlineStr"><is><t xml:space="preserve">'
            . $this->escaparXml($valorCelula) . '</t></is></c>';
    }

    private function celulaNumero(int $coluna, int $numeroLinha, float $valorCelula, int $estilo): string
    {
        return '<c r="' . $this->letraColuna($coluna) . $numeroLinha . '" s="' . $estilo
            . '"><v>' . $this->decimal($valorCelula, 10) . '</v></c>';
    }

    private function mesclarCelulasXml(array $mesclagens): string
    {
        if ($mesclagens === []) {
            return "";
        }

        $conteudoXml = '<mergeCells count="' . count($mesclagens) . '">';
        foreach ($mesclagens as $referencia) {
            $conteudoXml .= '<mergeCell ref="' . $referencia . '"/>';
        }

        return $conteudoXml . '</mergeCells>';
    }

    private function letraColuna(int $indice): string
    {
        if ($indice < 1) {
            throw new InvalidArgumentException("Indice de coluna invalido.");
        }

        $letras = "";
        while ($indice > 0) {
            $indice--;
            $letras = chr(65 + ($indice % 26)) . $letras;
            $indice = intdiv($indice, 26);
        }

        return $letras;
    }

    private function decimal(float $valorCelula, int $precisao = 2): string
    {
        return rtrim(rtrim(number_format($valorCelula, $precisao, ".", ""), "0"), ".");
    }

    private function escaparXml(string $valorCelula): string
    {
        $semCaracteresControle = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', "", $valorCelula) ?? "";

        return htmlspecialchars(
            $semCaracteresControle,
            ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,
            "UTF-8"
        );
    }

    private function adicionarParte(ZipArchive $arquivoZip, string $caminho, string $conteudo): void
    {
        if (!$arquivoZip->addFromString($caminho, $conteudo)) {
            throw new RuntimeException("Nao foi possivel incluir {$caminho} no arquivo XLSX.");
        }
    }

    private function tiposConteudoXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            . '<Override PartName="/xl/drawings/drawing2.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function relacionamentosRaizXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function relacionamentosPastaTrabalhoXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function pastaTrabalhoXml(int $ultimaLinhaResumo, int $ultimaLinhaDetalhes, int $indiceUltimaColuna): string
    {
        $ultimaColuna = $this->letraColuna($indiceUltimaColuna);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>'
            . '<sheet name="Resumo" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Ativos" sheetId="2" r:id="rId2"/>'
            . '</sheets>'
            . '<definedNames>'
            . '<definedName name="_xlnm.Print_Area" localSheetId="0">Resumo!$A$1:$'
            . $ultimaColuna . '$' . $ultimaLinhaResumo . '</definedName>'
            . '<definedName name="_xlnm.Print_Area" localSheetId="1">Ativos!$A$1:$'
            . $ultimaColuna . '$' . $ultimaLinhaDetalhes . '</definedName>'
            . '<definedName name="_xlnm.Print_Titles" localSheetId="1">Ativos!$5:$5</definedName>'
            . '</definedNames>'
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            . '</workbook>';
    }

    private function relacionamentosPlanilhaXml(int $numeroDesenho, array $relacionamentosHiperlinks): string
    {
        $conteudoXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" '
            . 'Target="../drawings/drawing' . $numeroDesenho . '.xml"/>';

        foreach ($relacionamentosHiperlinks as $relacionamento) {
            $conteudoXml .= '<Relationship Id="' . $this->escaparXml((string) $relacionamento["id"])
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" '
                . 'Target="' . $this->escaparXml((string) $relacionamento["target"])
                . '" TargetMode="External"/>';
        }

        return $conteudoXml . '</Relationships>';
    }

    private function relacionamentosDesenhoXml(string $nomeMidia): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'
            . $this->escaparXml($nomeMidia) . '"/>'
            . '</Relationships>';
    }

    private function desenhoXml(string $descricao): string
    {
        $larguraEmu = 185 * 9525;
        $alturaEmu = 64 * 9525;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>76200</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $larguraEmu . '" cy="' . $alturaEmu . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo TI TECH" descr="'
            . $this->escaparXml($descricao) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $larguraEmu
            . '" cy="' . $alturaEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function propriedadesAplicacaoXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>TI TECH Portal de Ativos</Application><AppVersion>1.0</AppVersion>'
            . '<TitlesOfParts><vt:vector size="2" baseType="lpstr"><vt:lpstr>Resumo</vt:lpstr>'
            . '<vt:lpstr>Ativos</vt:lpstr></vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private function propriedadesPrincipaisXml(DateTimeImmutable $geradoEm): string
    {
        $marcaTempo = $geradoEm->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:s\Z");

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Relatorio de Ativos</dc:title><dc:subject>Inventario corporativo</dc:subject>'
            . '<dc:creator>TI TECH Solutions</dc:creator><cp:lastModifiedBy>Portal de Ativos</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $marcaTempo . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $marcaTempo . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function estilosXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="165" formatCode="@"/></numFmts>
  <fonts count="11">
    <font><sz val="10"/><color rgb="FF1C2834"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/><family val="2"/></font>
    <font><sz val="10"/><color rgb="FFDDE8EF"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="8"/><color rgb="FF9EC7DF"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="10"/><color rgb="FF152D44"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="18"/><color rgb="FF152D44"/><name val="Aptos Display"/><family val="2"/></font>
    <font><u/><sz val="10"/><color rgb="FF2B78B8"/><name val="Aptos"/><family val="2"/></font>
    <font><b/><sz val="9"/><color rgb="FF174A3D"/><name val="Aptos"/><family val="2"/></font>
    <font><i/><sz val="10"/><color rgb="FF5D6E7C"/><name val="Aptos"/><family val="2"/></font>
  </fonts>
  <fills count="10">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF152D44"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF488AC3"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF54C4A2"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF2F7FA"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF7FAFC"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEAF3F9"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE2F6EE"/><bgColor indexed="64"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="3">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFCCD9E3"/></left><right style="thin"><color rgb="FFCCD9E3"/></right><top style="thin"><color rgb="FFCCD9E3"/></top><bottom style="thin"><color rgb="FFCCD9E3"/></bottom><diagonal/></border>
    <border><left/><right/><top/><bottom style="medium"><color rgb="FF54C4A2"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="26">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/>
    <xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>
    <xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"/>
    <xf numFmtId="0" fontId="5" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="6" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="7" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="6" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="165" fontId="0" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>
    <xf numFmtId="165" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>
    <xf numFmtId="165" fontId="0" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
    <xf numFmtId="165" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
    <xf numFmtId="164" fontId="0" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="164" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="165" fontId="8" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>
    <xf numFmtId="165" fontId="8" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>
    <xf numFmtId="165" fontId="9" fillId="8" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="165" fontId="10" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  </cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
  <dxfs count="0"/>
  <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>
XML;
    }
}
