<?php

declare(strict_types=1);

final class RelatorioAtivosPdf
{
    private const PAGE_WIDTH_MM = 297.0;
    private const PAGE_HEIGHT_MM = 210.0;
    private const MARGIN_X_MM = 10.0;
    private const CONTENT_BOTTOM_MM = 195.5;
    private const MM_TO_PT = 72.0 / 25.4;

    // Paleta principal definida no manual de identidade visual da TI TECH.
    private const NAVY = [0.082, 0.176, 0.267];
    private const BLUE = [0.282, 0.541, 0.765];
    private const MINT = [0.329, 0.769, 0.635];
    private const WHITE = [1.0, 1.0, 1.0];
    private const TEXT = [0.110, 0.157, 0.204];
    private const MUTED = [0.365, 0.431, 0.490];
    private const BORDER = [0.804, 0.851, 0.890];
    private const PANEL = [0.949, 0.969, 0.980];
    private const ROW_ALT = [0.965, 0.976, 0.984];

    private const COLUMNS = [
        ["key" => "ativo", "title" => "Ativo", "width" => 47.0],
        ["key" => "categoria", "title" => "Categoria", "width" => 28.0],
        ["key" => "marca", "title" => "Marca", "width" => 24.0],
        ["key" => "part_number", "title" => "PN", "width" => 31.0],
        ["key" => "numero_serie", "title" => "Nº de série", "width" => 32.0],
        ["key" => "status", "title" => "Status", "width" => 25.0],
        ["key" => "localizacao", "title" => "Local", "width" => 34.0],
        ["key" => "datasheet", "title" => "Datasheet", "width" => 24.0],
        ["key" => "criado_em", "title" => "Criado em", "width" => 32.0],
    ];

    private array $paginas = [];
    private string $comandos = "";
    private float $cursorVertical = 0.0;
    private int $indiceLinha = 0;
    private DateTimeImmutable $geradoEm;
    private array $metricas = [];
    private array $filtros = [];
    private ?array $imagemLogo = null;

    public function gerar(
        array $ativos,
        array $metricas,
        array $filtros,
        DateTimeImmutable $geradoEm
    ): string {
        $this->paginas = [];
        $this->comandos = "";
        $this->cursorVertical = 0.0;
        $this->indiceLinha = 0;
        $this->metricas = $metricas;
        $this->filtros = $filtros;
        $this->geradoEm = $geradoEm;
        $this->imagemLogo = $this->carregarImagemLogo();

        $this->iniciarPagina(true);

        if ($ativos === []) {
            $this->desenharEstadoVazio();
        } else {
            foreach ($ativos as $ativo) {
                $celulas = $this->prepararLinha(is_array($ativo) ? $ativo : []);
                $alturaLinha = $this->calcularAlturaLinha($celulas);

                if ($this->cursorVertical + $alturaLinha > self::CONTENT_BOTTOM_MM) {
                    $this->finalizarPagina();
                    $this->iniciarPagina(false);
                }

                $this->desenharLinha($celulas, $alturaLinha);
            }
        }

        $this->finalizarPagina();
        $this->adicionarRodapes();

        return $this->montarDocumento();
    }

    private function iniciarPagina(bool $primeiraPagina): void
    {
        $this->comandos = "";

        if ($primeiraPagina) {
            $this->desenharCabecalhoPrincipal();
            return;
        }

        $this->desenharCabecalhoContinuacao();
    }

    private function finalizarPagina(): void
    {
        $this->paginas[] = $this->comandos;
        $this->comandos = "";
    }

    private function desenharCabecalhoPrincipal(): void
    {
        $this->desenharLogo(10.0, 4.2, 42.0);
        $this->texto(59.0, 5.0, "RELATÓRIO DE ATIVOS", 16.0, true, self::NAVY);
        $this->texto(59.0, 12.8, "Inventário corporativo consolidado", 8.2, false, self::MUTED);
        $this->textoDireita(287.0, 5.2, "GERADO EM", 7.2, true, self::BLUE);
        $this->textoDireita(287.0, 10.8, $this->geradoEm->format("d/m/Y H:i"), 9.0, true, self::NAVY);
        $this->desenharBarraMarca(26.0, 3.0);

        $this->texto(10.0, 34.0, "Visão geral do inventário", 10.5, true, self::NAVY);
        $this->texto(
            10.0,
            39.0,
            "Indicadores e registros correspondentes aos filtros aplicados.",
            8.2,
            false,
            self::MUTED
        );

        $cartoesMetricas = [
            [
                "label" => "Total de ativos",
                "value" => (string) ($this->metricas["total"] ?? 0),
                "accent" => self::NAVY,
            ],
            [
                "label" => "Em estoque",
                "value" => (string) ($this->metricas["disponiveis"] ?? 0),
                "accent" => self::BLUE,
            ],
            [
                "label" => "Registros no relatório",
                "value" => (string) ($this->metricas["filtrados"] ?? 0),
                "accent" => self::MINT,
            ],
        ];

        foreach ($cartoesMetricas as $indice => $metrica) {
            $x = self::MARGIN_X_MM + ($indice * 94.0);
            $this->retangulo($x, 45.0, 89.0, 20.0, self::PANEL, self::BORDER);
            $this->retangulo($x, 45.0, 2.2, 20.0, $metrica["accent"]);
            $this->retangulo($x + 2.2, 45.0, 86.8, 1.0, $metrica["accent"]);
            $this->texto($x + 6.0, 49.0, $metrica["label"], 7.4, true, self::MUTED);
            $this->texto($x + 6.0, 55.0, $metrica["value"], 16.0, true, self::NAVY);
        }

        $textoFiltros = $this->formatarFiltros();
        $linhasFiltros = $this->quebrarTexto($textoFiltros, 265.0, 7.6, 3);
        $alturaFiltros = max(14.0, 9.0 + (count($linhasFiltros) * 3.6));

        $this->retangulo(10.0, 70.0, 277.0, $alturaFiltros, self::PANEL, self::BORDER);
        $this->retangulo(10.0, 70.0, 2.0, $alturaFiltros, self::BLUE);
        $this->texto(15.0, 73.0, "FILTROS DO RELATÓRIO", 7.0, true, self::BLUE);
        $this->desenharTextoQuebrado(15.0, 78.0, $linhasFiltros, 7.6, 3.6, false, self::TEXT);

        $topoTabela = 70.0 + $alturaFiltros + 6.0;
        $this->desenharCabecalhoTabela($topoTabela);
        $this->cursorVertical = $topoTabela + 8.0;
    }

    private function desenharCabecalhoContinuacao(): void
    {
        $this->desenharLogo(10.0, 3.4, 28.0);
        $this->texto(44.0, 4.2, "RELATÓRIO DE ATIVOS", 11.0, true, self::NAVY);
        $this->texto(44.0, 10.0, "Inventário corporativo | Continuação", 7.2, false, self::MUTED);
        $this->textoDireita(287.0, 4.0, "GERADO EM", 6.8, true, self::BLUE);
        $this->textoDireita(
            287.0,
            9.5,
            $this->geradoEm->format("d/m/Y H:i"),
            8.0,
            true,
            self::NAVY
        );
        $this->desenharBarraMarca(18.0, 2.5);

        $this->desenharCabecalhoTabela(24.0);
        $this->cursorVertical = 32.0;
    }

    private function desenharCabecalhoTabela(float $topo): void
    {
        $x = self::MARGIN_X_MM;

        foreach (self::COLUMNS as $coluna) {
            $largura = (float) $coluna["width"];
            $this->retangulo($x, $topo, $largura, 8.0, self::NAVY, self::WHITE);
            $this->retangulo($x, $topo, $largura, 0.8, self::MINT);
            $this->texto($x + 1.6, $topo + 2.2, (string) $coluna["title"], 7.0, true, self::WHITE);
            $x += $largura;
        }
    }

    private function prepararLinha(array $ativo): array
    {
        $nome = trim((string) ($ativo["nome"] ?? ""));
        $propriedade = trim((string) ($ativo["propriedade"] ?? ""));
        $rotuloAtivo = $nome !== "" ? $nome : "--";

        if ($propriedade !== "") {
            $rotuloAtivo .= "\n" . $propriedade;
        }

        $valores = [
            "ativo" => $rotuloAtivo,
            "categoria" => $this->valorOuPadrao($ativo["categoria"] ?? null, "Sem categoria"),
            "marca" => $this->valorOuPadrao($ativo["marca"] ?? null),
            "part_number" => $this->valorOuPadrao($ativo["part_number"] ?? null),
            "numero_serie" => $this->valorOuPadrao($ativo["numero_serie"] ?? null),
            "status" => $this->valorOuPadrao($ativo["status"] ?? null),
            "localizacao" => $this->valorOuPadrao($ativo["localizacao"] ?? null),
            "datasheet" => trim((string) ($ativo["datasheet"] ?? "")) !== "" ? "Disponível" : "--",
            "criado_em" => $this->valorOuPadrao($ativo["criado_em_formatado"] ?? null),
        ];

        $celulas = [];

        foreach (self::COLUMNS as $coluna) {
            $chaveItem = (string) $coluna["key"];
            $celulas[$chaveItem] = $this->quebrarTexto(
                (string) ($valores[$chaveItem] ?? "--"),
                (float) $coluna["width"] - 3.0,
                7.2,
                4
            );
        }

        return $celulas;
    }

    private function calcularAlturaLinha(array $celulas): float
    {
        $quantidadeLinhas = 1;

        foreach ($celulas as $linhas) {
            $quantidadeLinhas = max($quantidadeLinhas, count($linhas));
        }

        return max(9.5, 3.0 + ($quantidadeLinhas * 3.4));
    }

    private function desenharLinha(array $celulas, float $altura): void
    {
        $fundo = $this->indiceLinha % 2 === 0 ? self::WHITE : self::ROW_ALT;
        $x = self::MARGIN_X_MM;

        foreach (self::COLUMNS as $coluna) {
            $chaveItem = (string) $coluna["key"];
            $largura = (float) $coluna["width"];
            $this->retangulo($x, $this->cursorVertical, $largura, $altura, $fundo, self::BORDER);
            $this->desenharTextoQuebrado(
                $x + 1.5,
                $this->cursorVertical + 2.0,
                $celulas[$chaveItem] ?? ["--"],
                7.2,
                3.4,
                $chaveItem === "ativo",
                self::TEXT
            );
            $x += $largura;
        }

        $this->cursorVertical += $altura;
        $this->indiceLinha++;
    }

    private function desenharEstadoVazio(): void
    {
        $this->retangulo(
            self::MARGIN_X_MM,
            $this->cursorVertical,
            277.0,
            15.0,
            self::ROW_ALT,
            self::BORDER
        );
        $this->texto(
            self::MARGIN_X_MM + 4.0,
            $this->cursorVertical + 5.0,
            "Nenhum ativo encontrado para os filtros aplicados.",
            9.0,
            true,
            self::MUTED
        );
        $this->cursorVertical += 15.0;
    }

    private function adicionarRodapes(): void
    {
        $totalPaginas = count($this->paginas);

        foreach ($this->paginas as $indice => $conteudoPagina) {
            $this->comandos = $conteudoPagina;
            $this->linha(10.0, 198.5, 287.0, 198.5, self::BORDER, 0.25);
            $this->poligono(
                [[238.0, 210.0], [248.0, 202.5], [297.0, 195.0], [297.0, 210.0]],
                self::MINT
            );
            $this->poligono(
                [[249.0, 210.0], [260.0, 204.0], [297.0, 198.5], [297.0, 210.0]],
                self::NAVY
            );
            $this->texto(10.0, 202.0, "TI TECH Solutions | Inventário de ativos", 7.0, true, self::NAVY);
            $this->textoDireita(
                287.0,
                202.0,
                "Página " . ($indice + 1) . " de " . $totalPaginas,
                7.0,
                true,
                self::WHITE
            );
            $this->paginas[$indice] = $this->comandos;
        }

        $this->comandos = "";
    }

    private function formatarFiltros(): string
    {
        if ($this->filtros === []) {
            return "Nenhum filtro ativo - inventário completo.";
        }

        $itens = [];

        foreach ($this->filtros as $rotulo => $valorEntrada) {
            $itens[] = $rotulo . ": " . $valorEntrada;
        }

        return implode(" | ", $itens);
    }

    private function valorOuPadrao(mixed $valorEntrada, string $valorPadrao = "--"): string
    {
        $texto = trim((string) $valorEntrada);

        return $texto !== "" ? $texto : $valorPadrao;
    }

    private function quebrarTexto(string $texto, float $larguraMaximaMm, float $tamanhoFonte, int $maximoLinhas): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", trim($texto));
        $maximoCaracteres = max(4, (int) floor(($larguraMaximaMm * self::MM_TO_PT) / ($tamanhoFonte * 0.52)));
        $linhas = [];
        $paragrafos = preg_split("/\n/u", $texto) ?: [$texto];

        foreach ($paragrafos as $paragrafo) {
            $paragrafo = trim((string) (preg_replace("/[\t ]+/u", " ", $paragrafo) ?? $paragrafo));

            if ($paragrafo === "") {
                continue;
            }

            $palavras = preg_split("/\s+/u", $paragrafo) ?: [$paragrafo];
            $objetoAtual = "";

            foreach ($palavras as $palavra) {
                $palavra = (string) $palavra;

                while (mb_strlen($palavra, "UTF-8") > $maximoCaracteres) {
                    if ($objetoAtual !== "") {
                        $linhas[] = $objetoAtual;
                        $objetoAtual = "";
                    }

                    $linhas[] = mb_substr($palavra, 0, $maximoCaracteres - 1, "UTF-8") . "-";
                    $palavra = mb_substr($palavra, $maximoCaracteres - 1, null, "UTF-8");
                }

                $candidato = $objetoAtual === "" ? $palavra : $objetoAtual . " " . $palavra;

                if (mb_strlen($candidato, "UTF-8") <= $maximoCaracteres) {
                    $objetoAtual = $candidato;
                    continue;
                }

                $linhas[] = $objetoAtual;
                $objetoAtual = $palavra;
            }

            if ($objetoAtual !== "") {
                $linhas[] = $objetoAtual;
            }
        }

        if ($linhas === []) {
            $linhas = ["--"];
        }

        if (count($linhas) > $maximoLinhas) {
            $linhas = array_slice($linhas, 0, $maximoLinhas);
            $ultimoIndice = $maximoLinhas - 1;
            $ultimaLinha = rtrim((string) $linhas[$ultimoIndice], ".-");
            $linhas[$ultimoIndice] = mb_substr($ultimaLinha, 0, max(1, $maximoCaracteres - 3), "UTF-8") . "...";
        }

        return $linhas;
    }

    private function desenharTextoQuebrado(
        float $x,
        float $topo,
        array $linhas,
        float $tamanhoFonte,
        float $alturaLinhaMm,
        bool $negrito,
        array $cor
    ): void {
        foreach ($linhas as $indice => $linhaTexto) {
            $this->texto($x, $topo + ($indice * $alturaLinhaMm), (string) $linhaTexto, $tamanhoFonte, $negrito, $cor);
        }
    }

    private function desenharBarraMarca(float $topo, float $altura): void
    {
        $this->retangulo(0.0, $topo, 170.0, $altura, self::NAVY);
        $this->retangulo(170.0, $topo, 70.0, $altura, self::BLUE);
        $this->retangulo(240.0, $topo, 57.0, $altura, self::MINT);
    }

    private function desenharLogo(float $x, float $topo, float $largura): void
    {
        if ($this->imagemLogo === null) {
            return;
        }

        $altura = $largura * ((float) $this->imagemLogo["height"] / (float) $this->imagemLogo["width"]);
        $xPontos = $this->milimetros($x);
        $yPontos = $this->milimetros(self::PAGE_HEIGHT_MM - $topo - $altura);
        $larguraPontos = $this->milimetros($largura);
        $alturaPontos = $this->milimetros($altura);

        $this->comandos .= "q " . $this->numero($larguraPontos) . " 0 0 " . $this->numero($alturaPontos) . " "
            . $this->numero($xPontos) . " " . $this->numero($yPontos) . " cm /Logo Do Q\n";
    }

    private function poligono(array $pontos, array $preenchimento): void
    {
        if (count($pontos) < 3) {
            return;
        }

        $comando = $this->cor($preenchimento, "rg") . " ";

        foreach ($pontos as $indice => $ponto) {
            $xPontos = $this->milimetros((float) ($ponto[0] ?? 0.0));
            $yPontos = $this->milimetros(self::PAGE_HEIGHT_MM - (float) ($ponto[1] ?? 0.0));
            $comando .= $this->numero($xPontos) . " " . $this->numero($yPontos)
                . ($indice === 0 ? " m " : " l ");
        }

        $this->comandos .= $comando . "h f\n";
    }

    private function carregarImagemLogo(): array
    {
        // A versão opaca evita conversão de imagem em tempo de execução no XAMPP.
        $caminho = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets"
            . DIRECTORY_SEPARATOR . "logo-relatorio-pdf.jpg";

        if (!is_readable($caminho)) {
            throw new RuntimeException("O logotipo da TI TECH não está disponível para o relatório.");
        }

        $informacoesImagem = getimagesize($caminho);
        $conteudos = file_get_contents($caminho);

        if (
            $informacoesImagem === false
            || $conteudos === false
            || ($informacoesImagem["mime"] ?? "") !== "image/jpeg"
        ) {
            throw new RuntimeException("O logotipo preparado para o PDF é inválido.");
        }

        return [
            "width" => (int) $informacoesImagem[0],
            "height" => (int) $informacoesImagem[1],
            "data" => $conteudos,
        ];
    }

    private function retangulo(
        float $x,
        float $topo,
        float $largura,
        float $altura,
        array $preenchimento,
        ?array $contorno = null
    ): void {
        $xPontos = $this->milimetros($x);
        $yPontos = $this->milimetros(self::PAGE_HEIGHT_MM - $topo - $altura);
        $larguraPontos = $this->milimetros($largura);
        $alturaPontos = $this->milimetros($altura);
        $comando = $this->cor($preenchimento, "rg") . " ";

        if ($contorno !== null) {
            $comando .= $this->cor($contorno, "RG") . " 0.35 w ";
        }

        $comando .= $this->numero($xPontos) . " " . $this->numero($yPontos) . " "
            . $this->numero($larguraPontos) . " " . $this->numero($alturaPontos) . " re "
            . ($contorno !== null ? "B" : "f");
        $this->comandos .= $comando . "\n";
    }

    private function linha(
        float $x1,
        float $topo1,
        float $x2,
        float $topo2,
        array $cor,
        float $largura
    ): void {
        $this->comandos .= $this->cor($cor, "RG") . " " . $this->numero($largura) . " w "
            . $this->numero($this->milimetros($x1)) . " " . $this->numero($this->milimetros(self::PAGE_HEIGHT_MM - $topo1)) . " m "
            . $this->numero($this->milimetros($x2)) . " " . $this->numero($this->milimetros(self::PAGE_HEIGHT_MM - $topo2)) . " l S\n";
    }

    private function texto(
        float $x,
        float $topo,
        string $texto,
        float $tamanhoFonte,
        bool $negrito,
        array $cor
    ): void {
        $textoCodificado = $this->codificarTexto($texto);
        $textoEscapado = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", "", " "], $textoCodificado);
        $fonte = $negrito ? "F2" : "F1";
        $xPontos = $this->milimetros($x);
        $yPontos = $this->milimetros(self::PAGE_HEIGHT_MM - $topo) - $tamanhoFonte;

        $this->comandos .= "BT " . $this->cor($cor, "rg") . " /{$fonte} "
            . $this->numero($tamanhoFonte) . " Tf " . $this->numero($xPontos) . " " . $this->numero($yPontos)
            . " Td (" . $textoEscapado . ") Tj ET\n";
    }

    private function textoDireita(
        float $posicaoDireita,
        float $topo,
        string $texto,
        float $tamanhoFonte,
        bool $negrito,
        array $cor
    ): void {
        $larguraEstimadaMm = (mb_strlen($texto, "UTF-8") * $tamanhoFonte * 0.50) / self::MM_TO_PT;
        $this->texto(max(self::MARGIN_X_MM, $posicaoDireita - $larguraEstimadaMm), $topo, $texto, $tamanhoFonte, $negrito, $cor);
    }

    private function codificarTexto(string $texto): string
    {
        if (function_exists("iconv")) {
            $textoCodificado = iconv("UTF-8", "Windows-1252//TRANSLIT//IGNORE", $texto);

            if ($textoCodificado !== false) {
                return $textoCodificado;
            }
        }

        return strtr($texto, [
            "á" => "a", "à" => "a", "ã" => "a", "â" => "a", "ä" => "a",
            "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
            "í" => "i", "ì" => "i", "î" => "i", "ï" => "i",
            "ó" => "o", "ò" => "o", "õ" => "o", "ô" => "o", "ö" => "o",
            "ú" => "u", "ù" => "u", "û" => "u", "ü" => "u",
            "ç" => "c", "Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U", "Ç" => "C",
        ]);
    }

    private function cor(array $cor, string $operador): string
    {
        return $this->numero((float) $cor[0]) . " "
            . $this->numero((float) $cor[1]) . " "
            . $this->numero((float) $cor[2]) . " " . $operador;
    }

    private function milimetros(float $valorEntrada): float
    {
        return $valorEntrada * self::MM_TO_PT;
    }

    private function numero(float $valorEntrada): string
    {
        return number_format($valorEntrada, 3, ".", "");
    }

    private function montarDocumento(): string
    {
        $quantidadePaginas = count($this->paginas);

        if ($quantidadePaginas === 0) {
            throw new RuntimeException("O relatório não possui páginas.");
        }

        if ($this->imagemLogo === null) {
            throw new RuntimeException("O relatório não possui um logotipo válido.");
        }

        $objetos = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            3 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];
        $idObjetoLogo = 5;
        $dadosLogo = (string) ($this->imagemLogo["data"] ?? "");

        if ($dadosLogo === "") {
            throw new RuntimeException("O logotipo preparado para o PDF está vazio.");
        }

        $objetos[$idObjetoLogo] = "<< /Type /XObject /Subtype /Image /Width "
            . (int) $this->imagemLogo["width"]
            . " /Height " . (int) $this->imagemLogo["height"]
            . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($dadosLogo)
            . " >>\nstream\n" . $dadosLogo . "\nendstream";
        $referenciasPaginas = [];

        foreach ($this->paginas as $indice => $conteudo) {
            $idObjetoPagina = 6 + ($indice * 2);
            $idObjetoConteudo = $idObjetoPagina + 1;
            $referenciasPaginas[] = $idObjetoPagina . " 0 R";
            $fluxo = $conteudo . "\n";

            $objetos[$idObjetoPagina] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 "
                . $this->numero($this->milimetros(self::PAGE_WIDTH_MM)) . " "
                . $this->numero($this->milimetros(self::PAGE_HEIGHT_MM))
                . "] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Logo {$idObjetoLogo} 0 R >> >>"
                . " /Contents {$idObjetoConteudo} 0 R >>";
            $objetos[$idObjetoConteudo] = "<< /Length " . strlen($fluxo) . " >>\nstream\n"
                . $fluxo . "endstream";
        }

        $objetos[2] = "<< /Type /Pages /Kids [" . implode(" ", $referenciasPaginas) . "] /Count {$quantidadePaginas} >>";
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $deslocamentos = [0 => 0];
        $idMaximoObjeto = max(array_keys($objetos));

        for ($idObjeto = 1; $idObjeto <= $idMaximoObjeto; $idObjeto++) {
            if (!isset($objetos[$idObjeto])) {
                continue;
            }

            $deslocamentos[$idObjeto] = strlen($pdf);
            $pdf .= $idObjeto . " 0 obj\n" . $objetos[$idObjeto] . "\nendobj\n";
        }

        $deslocamentoXref = strlen($pdf);
        $pdf .= "xref\n0 " . ($idMaximoObjeto + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($idObjeto = 1; $idObjeto <= $idMaximoObjeto; $idObjeto++) {
            $deslocamento = $deslocamentos[$idObjeto] ?? 0;
            $status = isset($deslocamentos[$idObjeto]) ? "n" : "f";
            $numeroGeracao = $status === "n" ? "00000" : "65535";
            $pdf .= sprintf("%010d %s %s \n", $deslocamento, $numeroGeracao, $status);
        }

        $pdf .= "trailer\n<< /Size " . ($idMaximoObjeto + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$deslocamentoXref}\n%%EOF";

        return $pdf;
    }
}
