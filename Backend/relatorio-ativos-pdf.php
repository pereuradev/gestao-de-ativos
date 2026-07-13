<?php

declare(strict_types=1);

final class RelatorioAtivosPdf
{
    private const PAGE_WIDTH_MM = 297.0;
    private const PAGE_HEIGHT_MM = 210.0;
    private const MARGIN_X_MM = 10.0;
    private const CONTENT_BOTTOM_MM = 196.0;
    private const MM_TO_PT = 72.0 / 25.4;

    private const NAVY = [0.024, 0.094, 0.161];
    private const NAVY_LIGHT = [0.055, 0.200, 0.306];
    private const TEAL = [0.310, 0.780, 0.694];
    private const WHITE = [1.0, 1.0, 1.0];
    private const TEXT = [0.075, 0.129, 0.180];
    private const MUTED = [0.365, 0.431, 0.490];
    private const BORDER = [0.827, 0.859, 0.886];
    private const PANEL = [0.945, 0.969, 0.976];
    private const ROW_ALT = [0.972, 0.980, 0.988];

    private const COLUMNS = [
        ["key" => "ativo", "title" => "Ativo", "width" => 53.0],
        ["key" => "categoria", "title" => "Categoria", "width" => 31.0],
        ["key" => "marca", "title" => "Marca", "width" => 27.0],
        ["key" => "numero_serie", "title" => "Nº de série", "width" => 37.0],
        ["key" => "status", "title" => "Status", "width" => 28.0],
        ["key" => "localizacao", "title" => "Local", "width" => 39.0],
        ["key" => "datasheet", "title" => "Datasheet", "width" => 27.0],
        ["key" => "criado_em", "title" => "Criado em", "width" => 35.0],
    ];

    private array $pages = [];
    private string $commands = "";
    private float $cursorY = 0.0;
    private int $rowIndex = 0;
    private DateTimeImmutable $generatedAt;
    private array $metrics = [];
    private array $filters = [];

    public function generate(
        array $assets,
        array $metrics,
        array $filters,
        DateTimeImmutable $generatedAt
    ): string {
        $this->pages = [];
        $this->commands = "";
        $this->cursorY = 0.0;
        $this->rowIndex = 0;
        $this->metrics = $metrics;
        $this->filters = $filters;
        $this->generatedAt = $generatedAt;

        $this->startPage(true);

        if ($assets === []) {
            $this->drawEmptyState();
        } else {
            foreach ($assets as $asset) {
                $cells = $this->prepareRow(is_array($asset) ? $asset : []);
                $rowHeight = $this->calculateRowHeight($cells);

                if ($this->cursorY + $rowHeight > self::CONTENT_BOTTOM_MM) {
                    $this->finishPage();
                    $this->startPage(false);
                }

                $this->drawRow($cells, $rowHeight);
            }
        }

        $this->finishPage();
        $this->addFooters();

        return $this->buildDocument();
    }

    private function startPage(bool $firstPage): void
    {
        $this->commands = "";

        if ($firstPage) {
            $this->drawMainHeader();
            return;
        }

        $this->drawContinuationHeader();
    }

    private function finishPage(): void
    {
        $this->pages[] = $this->commands;
        $this->commands = "";
    }

    private function drawMainHeader(): void
    {
        $this->rectangle(0.0, 0.0, self::PAGE_WIDTH_MM, 24.0, self::NAVY);
        $this->rectangle(0.0, 23.0, self::PAGE_WIDTH_MM, 1.0, self::TEAL);
        $this->text(10.0, 5.2, "TI TECH SOLUTIONS", 9.0, true, self::WHITE);
        $this->text(10.0, 11.4, "Relatório de ativos", 17.0, true, self::WHITE);
        $this->textRight(287.0, 6.0, "Gerado em", 7.2, false, self::TEAL);
        $this->textRight(287.0, 11.6, $this->generatedAt->format("d/m/Y H:i"), 9.0, true, self::WHITE);

        $this->text(10.0, 29.0, "Inventário consolidado", 10.5, true, self::NAVY);
        $this->text(
            10.0,
            34.0,
            "Indicadores e registros correspondentes aos filtros aplicados.",
            8.2,
            false,
            self::MUTED
        );

        $metricCards = [
            ["label" => "Total de ativos", "value" => (string) ($this->metrics["total"] ?? 0)],
            ["label" => "Em estoque", "value" => (string) ($this->metrics["disponiveis"] ?? 0)],
            ["label" => "Registros no relatório", "value" => (string) ($this->metrics["filtrados"] ?? 0)],
        ];

        foreach ($metricCards as $index => $metric) {
            $x = self::MARGIN_X_MM + ($index * 94.0);
            $this->rectangle($x, 41.0, 89.0, 20.0, self::PANEL, self::BORDER);
            $this->rectangle($x, 41.0, 2.0, 20.0, self::TEAL);
            $this->text($x + 5.0, 45.0, $metric["label"], 7.4, true, self::MUTED);
            $this->text($x + 5.0, 51.0, $metric["value"], 16.0, true, self::NAVY);
        }

        $filterText = $this->formatFilters();
        $filterLines = $this->wrapText($filterText, 265.0, 7.6, 3);
        $filterHeight = max(14.0, 9.0 + (count($filterLines) * 3.6));

        $this->rectangle(10.0, 66.0, 277.0, $filterHeight, self::PANEL, self::BORDER);
        $this->text(14.0, 69.0, "FILTROS DO RELATÓRIO", 7.0, true, self::NAVY_LIGHT);
        $this->wrappedText(14.0, 74.0, $filterLines, 7.6, 3.6, false, self::TEXT);

        $tableTop = 66.0 + $filterHeight + 6.0;
        $this->drawTableHeader($tableTop);
        $this->cursorY = $tableTop + 8.0;
    }

    private function drawContinuationHeader(): void
    {
        $this->rectangle(0.0, 0.0, self::PAGE_WIDTH_MM, 18.0, self::NAVY);
        $this->rectangle(0.0, 17.0, self::PAGE_WIDTH_MM, 1.0, self::TEAL);
        $this->text(10.0, 5.0, "Relatório de ativos - continuação", 11.0, true, self::WHITE);
        $this->textRight(
            287.0,
            5.4,
            $this->generatedAt->format("d/m/Y H:i"),
            8.0,
            false,
            self::WHITE
        );

        $this->drawTableHeader(24.0);
        $this->cursorY = 32.0;
    }

    private function drawTableHeader(float $top): void
    {
        $x = self::MARGIN_X_MM;

        foreach (self::COLUMNS as $column) {
            $width = (float) $column["width"];
            $this->rectangle($x, $top, $width, 8.0, self::NAVY_LIGHT, self::WHITE);
            $this->text($x + 1.6, $top + 2.2, (string) $column["title"], 7.0, true, self::WHITE);
            $x += $width;
        }
    }

    private function prepareRow(array $asset): array
    {
        $name = trim((string) ($asset["nome"] ?? ""));
        $property = trim((string) ($asset["propriedade"] ?? ""));
        $assetLabel = $name !== "" ? $name : "--";

        if ($property !== "") {
            $assetLabel .= "\n" . $property;
        }

        $values = [
            "ativo" => $assetLabel,
            "categoria" => $this->valueOrFallback($asset["categoria"] ?? null, "Sem categoria"),
            "marca" => $this->valueOrFallback($asset["marca"] ?? null),
            "numero_serie" => $this->valueOrFallback($asset["numero_serie"] ?? null),
            "status" => $this->valueOrFallback($asset["status"] ?? null),
            "localizacao" => $this->valueOrFallback($asset["localizacao"] ?? null),
            "datasheet" => trim((string) ($asset["datasheet"] ?? "")) !== "" ? "Disponível" : "--",
            "criado_em" => $this->valueOrFallback($asset["criado_em_formatado"] ?? null),
        ];

        $cells = [];

        foreach (self::COLUMNS as $column) {
            $key = (string) $column["key"];
            $cells[$key] = $this->wrapText(
                (string) ($values[$key] ?? "--"),
                (float) $column["width"] - 3.0,
                7.2,
                4
            );
        }

        return $cells;
    }

    private function calculateRowHeight(array $cells): float
    {
        $lineCount = 1;

        foreach ($cells as $lines) {
            $lineCount = max($lineCount, count($lines));
        }

        return max(9.5, 3.0 + ($lineCount * 3.4));
    }

    private function drawRow(array $cells, float $height): void
    {
        $background = $this->rowIndex % 2 === 0 ? self::WHITE : self::ROW_ALT;
        $x = self::MARGIN_X_MM;

        foreach (self::COLUMNS as $column) {
            $key = (string) $column["key"];
            $width = (float) $column["width"];
            $this->rectangle($x, $this->cursorY, $width, $height, $background, self::BORDER);
            $this->wrappedText(
                $x + 1.5,
                $this->cursorY + 2.0,
                $cells[$key] ?? ["--"],
                7.2,
                3.4,
                $key === "ativo",
                self::TEXT
            );
            $x += $width;
        }

        $this->cursorY += $height;
        $this->rowIndex++;
    }

    private function drawEmptyState(): void
    {
        $this->rectangle(
            self::MARGIN_X_MM,
            $this->cursorY,
            277.0,
            15.0,
            self::ROW_ALT,
            self::BORDER
        );
        $this->text(
            self::MARGIN_X_MM + 4.0,
            $this->cursorY + 5.0,
            "Nenhum ativo encontrado para os filtros aplicados.",
            9.0,
            true,
            self::MUTED
        );
        $this->cursorY += 15.0;
    }

    private function addFooters(): void
    {
        $totalPages = count($this->pages);

        foreach ($this->pages as $index => $page) {
            $this->commands = $page;
            $this->line(10.0, 199.0, 287.0, 199.0, self::BORDER, 0.25);
            $this->text(10.0, 202.0, "TI TECH Solutions | Inventário de ativos", 7.0, false, self::MUTED);
            $this->textRight(
                287.0,
                202.0,
                "Página " . ($index + 1) . " de " . $totalPages,
                7.0,
                true,
                self::MUTED
            );
            $this->pages[$index] = $this->commands;
        }

        $this->commands = "";
    }

    private function formatFilters(): string
    {
        if ($this->filters === []) {
            return "Nenhum filtro ativo - inventário completo.";
        }

        $items = [];

        foreach ($this->filters as $label => $value) {
            $items[] = $label . ": " . $value;
        }

        return implode(" | ", $items);
    }

    private function valueOrFallback(mixed $value, string $fallback = "--"): string
    {
        $text = trim((string) $value);

        return $text !== "" ? $text : $fallback;
    }

    private function wrapText(string $text, float $maxWidthMm, float $fontSize, int $maxLines): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $maxChars = max(4, (int) floor(($maxWidthMm * self::MM_TO_PT) / ($fontSize * 0.52)));
        $lines = [];
        $paragraphs = preg_split("/\n/u", $text) ?: [$text];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) (preg_replace("/[\t ]+/u", " ", $paragraph) ?? $paragraph));

            if ($paragraph === "") {
                continue;
            }

            $words = preg_split("/\s+/u", $paragraph) ?: [$paragraph];
            $current = "";

            foreach ($words as $word) {
                $word = (string) $word;

                while (mb_strlen($word, "UTF-8") > $maxChars) {
                    if ($current !== "") {
                        $lines[] = $current;
                        $current = "";
                    }

                    $lines[] = mb_substr($word, 0, $maxChars - 1, "UTF-8") . "-";
                    $word = mb_substr($word, $maxChars - 1, null, "UTF-8");
                }

                $candidate = $current === "" ? $word : $current . " " . $word;

                if (mb_strlen($candidate, "UTF-8") <= $maxChars) {
                    $current = $candidate;
                    continue;
                }

                $lines[] = $current;
                $current = $word;
            }

            if ($current !== "") {
                $lines[] = $current;
            }
        }

        if ($lines === []) {
            $lines = ["--"];
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastIndex = $maxLines - 1;
            $lastLine = rtrim((string) $lines[$lastIndex], ".-");
            $lines[$lastIndex] = mb_substr($lastLine, 0, max(1, $maxChars - 3), "UTF-8") . "...";
        }

        return $lines;
    }

    private function wrappedText(
        float $x,
        float $top,
        array $lines,
        float $fontSize,
        float $lineHeightMm,
        bool $bold,
        array $color
    ): void {
        foreach ($lines as $index => $line) {
            $this->text($x, $top + ($index * $lineHeightMm), (string) $line, $fontSize, $bold, $color);
        }
    }

    private function rectangle(
        float $x,
        float $top,
        float $width,
        float $height,
        array $fill,
        ?array $stroke = null
    ): void {
        $xPt = $this->mm($x);
        $yPt = $this->mm(self::PAGE_HEIGHT_MM - $top - $height);
        $widthPt = $this->mm($width);
        $heightPt = $this->mm($height);
        $command = $this->color($fill, "rg") . " ";

        if ($stroke !== null) {
            $command .= $this->color($stroke, "RG") . " 0.35 w ";
        }

        $command .= $this->number($xPt) . " " . $this->number($yPt) . " "
            . $this->number($widthPt) . " " . $this->number($heightPt) . " re "
            . ($stroke !== null ? "B" : "f");
        $this->commands .= $command . "\n";
    }

    private function line(
        float $x1,
        float $top1,
        float $x2,
        float $top2,
        array $color,
        float $width
    ): void {
        $this->commands .= $this->color($color, "RG") . " " . $this->number($width) . " w "
            . $this->number($this->mm($x1)) . " " . $this->number($this->mm(self::PAGE_HEIGHT_MM - $top1)) . " m "
            . $this->number($this->mm($x2)) . " " . $this->number($this->mm(self::PAGE_HEIGHT_MM - $top2)) . " l S\n";
    }

    private function text(
        float $x,
        float $top,
        string $text,
        float $fontSize,
        bool $bold,
        array $color
    ): void {
        $encoded = $this->encodeText($text);
        $escaped = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", "", " "], $encoded);
        $font = $bold ? "F2" : "F1";
        $xPt = $this->mm($x);
        $yPt = $this->mm(self::PAGE_HEIGHT_MM - $top) - $fontSize;

        $this->commands .= "BT " . $this->color($color, "rg") . " /{$font} "
            . $this->number($fontSize) . " Tf " . $this->number($xPt) . " " . $this->number($yPt)
            . " Td (" . $escaped . ") Tj ET\n";
    }

    private function textRight(
        float $right,
        float $top,
        string $text,
        float $fontSize,
        bool $bold,
        array $color
    ): void {
        $estimatedWidthMm = (mb_strlen($text, "UTF-8") * $fontSize * 0.50) / self::MM_TO_PT;
        $this->text(max(self::MARGIN_X_MM, $right - $estimatedWidthMm), $top, $text, $fontSize, $bold, $color);
    }

    private function encodeText(string $text): string
    {
        if (function_exists("iconv")) {
            $encoded = iconv("UTF-8", "Windows-1252//TRANSLIT//IGNORE", $text);

            if ($encoded !== false) {
                return $encoded;
            }
        }

        return strtr($text, [
            "á" => "a", "à" => "a", "ã" => "a", "â" => "a", "ä" => "a",
            "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
            "í" => "i", "ì" => "i", "î" => "i", "ï" => "i",
            "ó" => "o", "ò" => "o", "õ" => "o", "ô" => "o", "ö" => "o",
            "ú" => "u", "ù" => "u", "û" => "u", "ü" => "u",
            "ç" => "c", "Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U", "Ç" => "C",
        ]);
    }

    private function color(array $color, string $operator): string
    {
        return $this->number((float) $color[0]) . " "
            . $this->number((float) $color[1]) . " "
            . $this->number((float) $color[2]) . " " . $operator;
    }

    private function mm(float $value): float
    {
        return $value * self::MM_TO_PT;
    }

    private function number(float $value): string
    {
        return number_format($value, 3, ".", "");
    }

    private function buildDocument(): string
    {
        $pageCount = count($this->pages);

        if ($pageCount === 0) {
            throw new RuntimeException("O relatório não possui páginas.");
        }

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            3 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];
        $pageReferences = [];

        foreach ($this->pages as $index => $content) {
            $pageObjectId = 5 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $pageReferences[] = $pageObjectId . " 0 R";
            $stream = $content . "\n";

            $objects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 "
                . $this->number($this->mm(self::PAGE_WIDTH_MM)) . " "
                . $this->number($this->mm(self::PAGE_HEIGHT_MM))
                . "] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjectId} 0 R >>";
            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n"
                . $stream . "endstream";
        }

        $objects[2] = "<< /Type /Pages /Kids [" . implode(" ", $pageReferences) . "] /Count {$pageCount} >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        $maxObjectId = max(array_keys($objects));

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            if (!isset($objects[$objectId])) {
                continue;
            }

            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objects[$objectId] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $offset = $offsets[$objectId] ?? 0;
            $status = isset($offsets[$objectId]) ? "n" : "f";
            $generation = $status === "n" ? "00000" : "65535";
            $pdf .= sprintf("%010d %s %s \n", $offset, $generation, $status);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
