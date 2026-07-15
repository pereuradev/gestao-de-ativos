<?php

declare(strict_types=1);

final class RelatorioAtivosXlsx
{
    public const CONTENT_TYPE = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

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

    public function generate(
        array $assets,
        array $metrics,
        array $filters,
        DateTimeImmutable $generatedAt,
        bool $includeResponsible
    ): string {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("A extensao ZIP do PHP nao esta disponivel.");
        }

        $logoPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "logo-branca.png";

        if (!is_file($logoPath) || !is_readable($logoPath)) {
            throw new RuntimeException("O logotipo da TI TECH nao esta disponivel para a planilha.");
        }

        $columns = $this->columns($includeResponsible);
        $summary = $this->buildSummarySheet($metrics, $filters, $generatedAt, count($columns));
        $details = $this->buildDetailsSheet($assets, $columns, $generatedAt);
        $temporaryPath = tempnam(sys_get_temp_dir(), "titech-xlsx-");

        if ($temporaryPath === false) {
            throw new RuntimeException("Nao foi possivel preparar a planilha.");
        }

        try {
            $zip = new ZipArchive();
            $opened = $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($opened !== true) {
                throw new RuntimeException("Nao foi possivel criar o arquivo XLSX.");
            }

            $this->addPart($zip, "[Content_Types].xml", $this->contentTypesXml());
            $this->addPart($zip, "_rels/.rels", $this->rootRelationshipsXml());
            $this->addPart($zip, "docProps/app.xml", $this->appPropertiesXml());
            $this->addPart($zip, "docProps/core.xml", $this->corePropertiesXml($generatedAt));
            $this->addPart(
                $zip,
                "xl/workbook.xml",
                $this->workbookXml($summary["lastRow"], $details["lastRow"], count($columns))
            );
            $this->addPart($zip, "xl/_rels/workbook.xml.rels", $this->workbookRelationshipsXml());
            $this->addPart($zip, "xl/styles.xml", $this->stylesXml());
            $this->addPart($zip, "xl/worksheets/sheet1.xml", $summary["xml"]);
            $this->addPart($zip, "xl/worksheets/_rels/sheet1.xml.rels", $this->sheetRelationshipsXml(1, []));
            $this->addPart($zip, "xl/worksheets/sheet2.xml", $details["xml"]);
            $this->addPart(
                $zip,
                "xl/worksheets/_rels/sheet2.xml.rels",
                $this->sheetRelationshipsXml(2, $details["relationships"])
            );
            $this->addPart($zip, "xl/drawings/drawing1.xml", $this->drawingXml("Logo TI TECH - Resumo"));
            $this->addPart(
                $zip,
                "xl/drawings/_rels/drawing1.xml.rels",
                $this->drawingRelationshipsXml("logo-summary.png")
            );
            $this->addPart($zip, "xl/drawings/drawing2.xml", $this->drawingXml("Logo TI TECH - Ativos"));
            $this->addPart(
                $zip,
                "xl/drawings/_rels/drawing2.xml.rels",
                $this->drawingRelationshipsXml("logo-details.png")
            );

            if (
                !$zip->addFile($logoPath, "xl/media/logo-summary.png")
                || !$zip->addFile($logoPath, "xl/media/logo-details.png")
            ) {
                throw new RuntimeException("Nao foi possivel incluir o logotipo na planilha.");
            }

            if (!$zip->close()) {
                throw new RuntimeException("Nao foi possivel finalizar o arquivo XLSX.");
            }

            $binary = file_get_contents($temporaryPath);

            if ($binary === false || $binary === "") {
                throw new RuntimeException("A planilha gerada esta vazia.");
            }

            return $binary;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function columns(bool $includeResponsible): array
    {
        $columns = [
            ["key" => "id", "title" => "ID", "width" => 38.0, "kind" => "text"],
            ["key" => "nome", "title" => "Nome", "width" => 30.0, "kind" => "wrap"],
            ["key" => "descricao", "title" => "Descri\xc3\xa7\xc3\xa3o", "width" => 42.0, "kind" => "wrap"],
            ["key" => "numero_serie", "title" => "N\xc3\xbamero de s\xc3\xa9rie", "width" => 22.0, "kind" => "text"],
            ["key" => "imei", "title" => "IMEI", "width" => 22.0, "kind" => "text"],
            ["key" => "categoria", "title" => "Categoria", "width" => 20.0, "kind" => "text"],
            ["key" => "marca", "title" => "Marca", "width" => 18.0, "kind" => "text"],
            ["key" => "propriedade", "title" => "Propriedade", "width" => 20.0, "kind" => "text"],
            ["key" => "localizacao", "title" => "Localiza\xc3\xa7\xc3\xa3o", "width" => 26.0, "kind" => "wrap"],
        ];

        if ($includeResponsible) {
            $columns[] = [
                "key" => "responsavel",
                "title" => "Respons\xc3\xa1vel",
                "width" => 26.0,
                "kind" => "wrap",
            ];
        }

        return array_merge($columns, [
            ["key" => "status", "title" => "Status", "width" => 18.0, "kind" => "status"],
            ["key" => "datasheet", "title" => "Datasheet", "width" => 34.0, "kind" => "link"],
            ["key" => "criado_em", "title" => "Data de cadastro", "width" => 20.0, "kind" => "date"],
        ]);
    }

    private function buildSummarySheet(
        array $metrics,
        array $filters,
        DateTimeImmutable $generatedAt,
        int $lastColumnIndex
    ): array {
        $lastColumn = $this->columnLetter($lastColumnIndex);
        $rows = [];
        $merges = [
            "A1:B3",
            "C1:H1",
            "C2:H2",
            "I1:{$lastColumn}1",
            "I2:{$lastColumn}2",
            "A6:{$lastColumn}6",
            "A7:D7",
            "A8:D8",
            "E7:H7",
            "E8:H8",
            "I7:{$lastColumn}7",
            "I8:{$lastColumn}8",
            "A10:{$lastColumn}10",
            "A11:C11",
            "D11:{$lastColumn}11",
        ];

        $rows[] = $this->styledRow(1, 28.0, $lastColumnIndex, self::STYLE_BRAND_NAVY, [
            3 => $this->stringCell(3, 1, "RELAT\xc3\x93RIO DE ATIVOS", self::STYLE_TITLE),
            9 => $this->stringCell(9, 1, "GERADO EM", self::STYLE_GENERATED_LABEL),
        ]);
        $rows[] = $this->styledRow(2, 22.0, $lastColumnIndex, self::STYLE_BRAND_NAVY, [
            3 => $this->stringCell(3, 2, "Invent\xc3\xa1rio corporativo consolidado", self::STYLE_SUBTITLE),
            9 => $this->stringCell(
                9,
                2,
                $generatedAt->format("d/m/Y H:i"),
                self::STYLE_GENERATED_VALUE
            ),
        ]);
        $rows[] = $this->styledRow(3, 18.0, $lastColumnIndex, self::STYLE_BRAND_NAVY);
        $rows[] = $this->brandBarRow(4, $lastColumnIndex);
        $rows[] = $this->styledRow(5, 9.0, $lastColumnIndex, self::STYLE_DEFAULT);
        $rows[] = $this->styledRow(6, 24.0, $lastColumnIndex, self::STYLE_SECTION, [
            1 => $this->stringCell(1, 6, "VIS\xc3\x83O GERAL DO INVENT\xc3\x81RIO", self::STYLE_SECTION),
        ]);

        $metricLabels = [];
        $metricValues = [];
        $this->fillRange(
            $metricLabels,
            7,
            1,
            4,
            self::STYLE_METRIC_LABEL_NAVY,
            "Total de ativos"
        );
        $this->fillRange(
            $metricLabels,
            7,
            5,
            8,
            self::STYLE_METRIC_LABEL_BLUE,
            "Em estoque"
        );
        $this->fillRange(
            $metricLabels,
            7,
            9,
            $lastColumnIndex,
            self::STYLE_METRIC_LABEL_MINT,
            "Registros no relat\xc3\xb3rio"
        );
        $this->fillRange(
            $metricValues,
            8,
            1,
            4,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metrics["total"] ?? 0)),
            true
        );
        $this->fillRange(
            $metricValues,
            8,
            5,
            8,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metrics["disponiveis"] ?? 0)),
            true
        );
        $this->fillRange(
            $metricValues,
            8,
            9,
            $lastColumnIndex,
            self::STYLE_METRIC_VALUE,
            (string) max(0, (int) ($metrics["filtrados"] ?? 0)),
            true
        );
        $rows[] = $this->styledRow(7, 22.0, $lastColumnIndex, self::STYLE_DEFAULT, $metricLabels);
        $rows[] = $this->styledRow(8, 34.0, $lastColumnIndex, self::STYLE_DEFAULT, $metricValues);
        $rows[] = $this->styledRow(9, 10.0, $lastColumnIndex, self::STYLE_DEFAULT);
        $rows[] = $this->styledRow(10, 24.0, $lastColumnIndex, self::STYLE_SECTION, [
            1 => $this->stringCell(1, 10, "FILTROS DO RELAT\xc3\x93RIO", self::STYLE_SECTION),
        ]);

        $filterHeader = [];
        $this->fillRange($filterHeader, 11, 1, 3, self::STYLE_FILTER_HEADER, "Filtro");
        $this->fillRange($filterHeader, 11, 4, $lastColumnIndex, self::STYLE_FILTER_HEADER, "Valor");
        $rows[] = $this->styledRow(11, 22.0, $lastColumnIndex, self::STYLE_DEFAULT, $filterHeader);

        $filterRows = $filters === [] ? ["Filtros aplicados" => "Nenhum"] : $filters;
        $rowNumber = 12;

        foreach ($filterRows as $name => $value) {
            $cells = [];
            $this->fillRange(
                $cells,
                $rowNumber,
                1,
                3,
                self::STYLE_FILTER_VALUE,
                (string) $name
            );
            $this->fillRange(
                $cells,
                $rowNumber,
                4,
                $lastColumnIndex,
                self::STYLE_FILTER_VALUE,
                (string) $value
            );
            $rows[] = $this->styledRow(
                $rowNumber,
                28.0,
                $lastColumnIndex,
                self::STYLE_DEFAULT,
                $cells
            );
            $merges[] = "A{$rowNumber}:C{$rowNumber}";
            $merges[] = "D{$rowNumber}:{$lastColumn}{$rowNumber}";
            $rowNumber++;
        }

        $lastRow = $rowNumber - 1;
        $columnsXml = '<cols><col min="1" max="2" width="14" customWidth="1"/>'
            . '<col min="3" max="' . $lastColumnIndex . '" width="13" customWidth="1"/></cols>';
        $xml = $this->worksheetOpenXml("A1:{$lastColumn}{$lastRow}", $columnsXml, null)
            . '<sheetData>' . implode("", $rows) . '</sheetData>'
            . $this->mergeCellsXml($merges)
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.35" right="0.35" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="1"/>'
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';

        return ["xml" => $xml, "lastRow" => $lastRow];
    }

    private function buildDetailsSheet(
        array $assets,
        array $columns,
        DateTimeImmutable $generatedAt
    ): array {
        $lastColumnIndex = count($columns);
        $lastColumn = $this->columnLetter($lastColumnIndex);
        $rows = [];
        $merges = [
            "A1:B3",
            "C1:H1",
            "C2:H2",
            "I1:{$lastColumn}1",
            "I2:{$lastColumn}2",
        ];

        $rows[] = $this->styledRow(1, 28.0, $lastColumnIndex, self::STYLE_BRAND_NAVY, [
            3 => $this->stringCell(3, 1, "DADOS DOS ATIVOS", self::STYLE_TITLE),
            9 => $this->stringCell(9, 1, "GERADO EM", self::STYLE_GENERATED_LABEL),
        ]);
        $rows[] = $this->styledRow(2, 22.0, $lastColumnIndex, self::STYLE_BRAND_NAVY, [
            3 => $this->stringCell(3, 2, "Invent\xc3\xa1rio corporativo | Registros filtrados", self::STYLE_SUBTITLE),
            9 => $this->stringCell(
                9,
                2,
                $generatedAt->format("d/m/Y H:i"),
                self::STYLE_GENERATED_VALUE
            ),
        ]);
        $rows[] = $this->styledRow(3, 18.0, $lastColumnIndex, self::STYLE_BRAND_NAVY);
        $rows[] = $this->brandBarRow(4, $lastColumnIndex);

        $headerCells = [];
        foreach ($columns as $index => $column) {
            $columnIndex = $index + 1;
            $headerCells[$columnIndex] = $this->stringCell(
                $columnIndex,
                5,
                (string) $column["title"],
                self::STYLE_TABLE_HEADER
            );
        }
        $rows[] = $this->styledRow(5, 32.0, $lastColumnIndex, self::STYLE_TABLE_HEADER, $headerCells);

        $hyperlinks = [];
        $relationships = [];
        $relationshipId = 2;
        $dataRow = 6;

        foreach ($assets as $assetIndex => $asset) {
            $asset = is_array($asset) ? $asset : [];
            $isAlternate = $assetIndex % 2 === 1;
            $cells = [];

            foreach ($columns as $columnIndexZero => $column) {
                $columnIndex = $columnIndexZero + 1;
                $key = (string) $column["key"];
                $kind = (string) $column["kind"];
                $value = $asset[$key] ?? "";
                $cells[$columnIndex] = $this->dataCell(
                    $columnIndex,
                    $dataRow,
                    $value,
                    $kind,
                    $isAlternate,
                    $generatedAt,
                    $relationshipId,
                    $hyperlinks,
                    $relationships
                );
            }

            $rows[] = $this->styledRow(
                $dataRow,
                30.0,
                $lastColumnIndex,
                $isAlternate ? self::STYLE_TEXT_ALT : self::STYLE_TEXT,
                $cells
            );
            $dataRow++;
        }

        if ($assets === []) {
            $emptyCells = [];
            $this->fillRange(
                $emptyCells,
                6,
                1,
                $lastColumnIndex,
                self::STYLE_EMPTY,
                "Nenhum ativo encontrado para os filtros aplicados."
            );
            $rows[] = $this->styledRow(6, 34.0, $lastColumnIndex, self::STYLE_EMPTY, $emptyCells);
            $merges[] = "A6:{$lastColumn}6";
            $lastRow = 6;
        } else {
            $lastRow = $dataRow - 1;
        }

        $columnsXml = '<cols>';
        foreach ($columns as $index => $column) {
            $columnNumber = $index + 1;
            $columnsXml .= '<col min="' . $columnNumber . '" max="' . $columnNumber
                . '" width="' . $this->decimal((float) $column["width"])
                . '" customWidth="1"/>';
        }
        $columnsXml .= '</cols>';

        $hyperlinksXml = $hyperlinks === []
            ? ""
            : '<hyperlinks>' . implode("", $hyperlinks) . '</hyperlinks>';
        $ignoredErrorsXml = $assets === []
            ? ""
            : '<ignoredErrors><ignoredError sqref="A6:E' . $lastRow
                . '" numberStoredAsText="1"/></ignoredErrors>';
        $xml = $this->worksheetOpenXml(
            "A1:{$lastColumn}{$lastRow}",
            $columnsXml,
            '<pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A6" sqref="A6"/>'
        )
            . '<sheetData>' . implode("", $rows) . '</sheetData>'
            . '<autoFilter ref="A5:' . $lastColumn . $lastRow . '"/>'
            . $this->mergeCellsXml($merges)
            . $hyperlinksXml
            . '<printOptions horizontalCentered="1"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . $ignoredErrorsXml
            . '<drawing r:id="rId1"/>'
            . '</worksheet>';

        return [
            "xml" => $xml,
            "lastRow" => $lastRow,
            "relationships" => $relationships,
        ];
    }

    private function dataCell(
        int $columnIndex,
        int $row,
        mixed $value,
        string $kind,
        bool $isAlternate,
        DateTimeImmutable $generatedAt,
        int &$relationshipId,
        array &$hyperlinks,
        array &$relationships
    ): string {
        $textStyle = $isAlternate ? self::STYLE_TEXT_ALT : self::STYLE_TEXT;
        $wrapStyle = $isAlternate ? self::STYLE_WRAP_ALT : self::STYLE_WRAP;

        if ($kind === "date") {
            $date = $this->dateValue($value, $generatedAt->getTimezone());

            if ($date !== null) {
                return $this->numberCell(
                    $columnIndex,
                    $row,
                    $this->excelDateSerial($date),
                    $isAlternate ? self::STYLE_DATE_ALT : self::STYLE_DATE
                );
            }

            return $this->stringCell($columnIndex, $row, "", $textStyle);
        }

        $text = trim((string) $value);

        if ($kind === "link" && $this->validExternalUrl($text)) {
            $cellReference = $this->columnLetter($columnIndex) . $row;
            $currentRelationship = "rId" . $relationshipId;
            $hyperlinks[] = '<hyperlink ref="' . $cellReference . '" r:id="'
                . $currentRelationship . '"/>';
            $relationships[] = ["id" => $currentRelationship, "target" => $text];
            $relationshipId++;

            return $this->stringCell(
                $columnIndex,
                $row,
                $text,
                $isAlternate ? self::STYLE_LINK_ALT : self::STYLE_LINK
            );
        }

        if ($kind === "status" && $this->isAvailableStatus($text)) {
            return $this->stringCell(
                $columnIndex,
                $row,
                $text,
                self::STYLE_STATUS_AVAILABLE
            );
        }

        return $this->stringCell(
            $columnIndex,
            $row,
            $text,
            $kind === "wrap" ? $wrapStyle : $textStyle
        );
    }

    private function dateValue(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof DateTimeInterface) {
            return (new DateTimeImmutable($value->format(DateTimeInterface::ATOM)))->setTimezone($timezone);
        }

        $text = trim((string) $value);

        if ($text === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable($text))->setTimezone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function excelDateSerial(DateTimeImmutable $date): float
    {
        $localTimestamp = gmmktime(
            (int) $date->format("H"),
            (int) $date->format("i"),
            (int) $date->format("s"),
            (int) $date->format("m"),
            (int) $date->format("d"),
            (int) $date->format("Y")
        );

        return ($localTimestamp / 86400) + 25569;
    }

    private function validExternalUrl(string $value): bool
    {
        if ($value === "" || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ["http", "https"], true);
    }

    private function isAvailableStatus(string $status): bool
    {
        $normalized = strtolower(trim($status));

        return str_starts_with($normalized, "dispon") || $normalized === "em estoque";
    }

    private function worksheetOpenXml(string $dimension, string $columnsXml, ?string $paneXml): string
    {
        $sheetView = '<sheetView showGridLines="0" zoomScale="90" zoomScaleNormal="90" workbookViewId="0">'
            . ($paneXml ?? '<selection activeCell="A1" sqref="A1"/>')
            . '</sheetView>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="' . $dimension . '"/>'
            . '<sheetViews>' . $sheetView . '</sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . $columnsXml;
    }

    private function styledRow(
        int $row,
        float $height,
        int $lastColumnIndex,
        int $baseStyle,
        array $overrides = []
    ): string {
        $cells = "";

        for ($column = 1; $column <= $lastColumnIndex; $column++) {
            $cells .= $overrides[$column] ?? $this->blankCell($column, $row, $baseStyle);
        }

        return '<row r="' . $row . '" ht="' . $this->decimal($height)
            . '" customHeight="1">' . $cells . '</row>';
    }

    private function brandBarRow(int $row, int $lastColumnIndex): string
    {
        $firstBreak = max(1, (int) floor($lastColumnIndex * 0.4));
        $secondBreak = max($firstBreak + 1, (int) floor($lastColumnIndex * 0.7));
        $overrides = [];

        for ($column = 1; $column <= $lastColumnIndex; $column++) {
            $style = $column <= $firstBreak
                ? self::STYLE_BRAND_NAVY
                : ($column <= $secondBreak ? self::STYLE_BRAND_BLUE : self::STYLE_BRAND_MINT);
            $overrides[$column] = $this->blankCell($column, $row, $style);
        }

        return $this->styledRow($row, 6.0, $lastColumnIndex, self::STYLE_DEFAULT, $overrides);
    }

    private function fillRange(
        array &$cells,
        int $row,
        int $startColumn,
        int $endColumn,
        int $style,
        string $value,
        bool $numeric = false
    ): void {
        for ($column = $startColumn; $column <= $endColumn; $column++) {
            if ($column === $startColumn) {
                $cells[$column] = $numeric
                    ? $this->numberCell($column, $row, (float) $value, $style)
                    : $this->stringCell($column, $row, $value, $style);
                continue;
            }

            $cells[$column] = $this->blankCell($column, $row, $style);
        }
    }

    private function blankCell(int $column, int $row, int $style): string
    {
        return '<c r="' . $this->columnLetter($column) . $row . '" s="' . $style . '"/>';
    }

    private function stringCell(int $column, int $row, string $value, int $style): string
    {
        return '<c r="' . $this->columnLetter($column) . $row . '" s="' . $style
            . '" t="inlineStr"><is><t xml:space="preserve">'
            . $this->xml($value) . '</t></is></c>';
    }

    private function numberCell(int $column, int $row, float $value, int $style): string
    {
        return '<c r="' . $this->columnLetter($column) . $row . '" s="' . $style
            . '"><v>' . $this->decimal($value, 10) . '</v></c>';
    }

    private function mergeCellsXml(array $merges): string
    {
        if ($merges === []) {
            return "";
        }

        $xml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $reference) {
            $xml .= '<mergeCell ref="' . $reference . '"/>';
        }

        return $xml . '</mergeCells>';
    }

    private function columnLetter(int $index): string
    {
        if ($index < 1) {
            throw new InvalidArgumentException("Indice de coluna invalido.");
        }

        $letters = "";
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function decimal(float $value, int $precision = 2): string
    {
        return rtrim(rtrim(number_format($value, $precision, ".", ""), "0"), ".");
    }

    private function xml(string $value): string
    {
        $withoutControls = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', "", $value) ?? "";

        return htmlspecialchars(
            $withoutControls,
            ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,
            "UTF-8"
        );
    }

    private function addPart(ZipArchive $zip, string $path, string $contents): void
    {
        if (!$zip->addFromString($path, $contents)) {
            throw new RuntimeException("Nao foi possivel incluir {$path} no arquivo XLSX.");
        }
    }

    private function contentTypesXml(): string
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

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(int $summaryLastRow, int $detailsLastRow, int $lastColumnIndex): string
    {
        $lastColumn = $this->columnLetter($lastColumnIndex);

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
            . $lastColumn . '$' . $summaryLastRow . '</definedName>'
            . '<definedName name="_xlnm.Print_Area" localSheetId="1">Ativos!$A$1:$'
            . $lastColumn . '$' . $detailsLastRow . '</definedName>'
            . '<definedName name="_xlnm.Print_Titles" localSheetId="1">Ativos!$5:$5</definedName>'
            . '</definedNames>'
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            . '</workbook>';
    }

    private function sheetRelationshipsXml(int $drawingNumber, array $hyperlinkRelationships): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" '
            . 'Target="../drawings/drawing' . $drawingNumber . '.xml"/>';

        foreach ($hyperlinkRelationships as $relationship) {
            $xml .= '<Relationship Id="' . $this->xml((string) $relationship["id"])
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" '
                . 'Target="' . $this->xml((string) $relationship["target"])
                . '" TargetMode="External"/>';
        }

        return $xml . '</Relationships>';
    }

    private function drawingRelationshipsXml(string $mediaName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'
            . $this->xml($mediaName) . '"/>'
            . '</Relationships>';
    }

    private function drawingXml(string $description): string
    {
        $widthEmu = 185 * 9525;
        $heightEmu = 64 * 9525;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>76200</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo TI TECH" descr="'
            . $this->xml($description) . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu
            . '" cy="' . $heightEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>TI TECH Portal de Ativos</Application><AppVersion>1.0</AppVersion>'
            . '<TitlesOfParts><vt:vector size="2" baseType="lpstr"><vt:lpstr>Resumo</vt:lpstr>'
            . '<vt:lpstr>Ativos</vt:lpstr></vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private function corePropertiesXml(DateTimeImmutable $generatedAt): string
    {
        $timestamp = $generatedAt->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:s\Z");

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Relatorio de Ativos</dc:title><dc:subject>Inventario corporativo</dc:subject>'
            . '<dc:creator>TI TECH Solutions</dc:creator><cp:lastModifiedBy>Portal de Ativos</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function stylesXml(): string
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
