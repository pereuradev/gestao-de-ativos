<?php

declare(strict_types=1);

const SOLICITACAO_ACESSO_FOTO_MAX_BYTES = 2097152;
const SOLICITACAO_ACESSO_PASTA_FOTOS = __DIR__ . "/../uploads/solicitacoes-acesso";
const SOLICITACAO_ACESSO_PASTA_CRACHAS = __DIR__ . "/../uploads/crachas";

function responderSolicitacaoAcesso(
    bool $sucesso,
    string $mensagem,
    int $codigoHttp = 200,
    array $dadosAdicionais = []
): void {
    http_response_code($codigoHttp);
    echo json_encode(
        array_merge(["ok" => $sucesso, "message" => $mensagem], $dadosAdicionais),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function valorTextoSolicitacao(array $origem, string $campo, string $padrao = ""): string
{
    $valor = $origem[$campo] ?? $padrao;

    return is_array($valor) || is_object($valor) ? $padrao : trim((string) $valor);
}

function apenasNumerosSolicitacao(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function cpfSolicitacaoValido(string $valor): bool
{
    $cpf = apenasNumerosSolicitacao($valor);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($tamanho = 9; $tamanho < 11; $tamanho++) {
        $soma = 0;

        for ($indice = 0; $indice < $tamanho; $indice++) {
            $soma += (int) $cpf[$indice] * (($tamanho + 1) - $indice);
        }

        $digito = ($soma * 10) % 11;
        $digito = $digito === 10 ? 0 : $digito;

        if ($digito !== (int) $cpf[$tamanho]) {
            return false;
        }
    }

    return true;
}

function emailCorporativoSolicitacaoValido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && str_ends_with(strtolower($email), '@titechsolutions.com.br');
}

function validarDadosSolicitacaoAcesso(array $origem): array
{
    $dados = [
        'nome_completo' => valorTextoSolicitacao($origem, 'nome_completo'),
        'email' => strtolower(valorTextoSolicitacao($origem, 'email')),
        'tipo_usuario' => valorTextoSolicitacao($origem, 'tipo_usuario', 'Colaborador'),
        'departamento' => valorTextoSolicitacao($origem, 'departamento'),
        'empresa' => valorTextoSolicitacao($origem, 'empresa'),
        'rg' => valorTextoSolicitacao($origem, 'rg'),
        'cpf' => valorTextoSolicitacao($origem, 'cpf'),
        'celular' => valorTextoSolicitacao($origem, 'celular'),
        'data_nascimento' => valorTextoSolicitacao($origem, 'data_nascimento'),
    ];

    if (in_array('', $dados, true)) {
        throw new InvalidArgumentException('Preencha todos os campos obrigatorios.');
    }

    $partesNome = preg_split('/\s+/', $dados['nome_completo'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (count($partesNome) < 2 || mb_strlen($dados['nome_completo']) > 150) {
        throw new InvalidArgumentException('Informe nome e sobrenome validos.');
    }

    if (!emailCorporativoSolicitacaoValido($dados['email'])) {
        throw new InvalidArgumentException('Use um e-mail corporativo @titechsolutions.com.br.');
    }

    if (!in_array($dados['tipo_usuario'], ['Colaborador', 'Administrador'], true)) {
        throw new InvalidArgumentException('Selecione um perfil de acesso valido.');
    }

    if (!in_array($dados['departamento'], ['TI', 'Administrativo', 'Comercial'], true)) {
        throw new InvalidArgumentException('Selecione um departamento valido.');
    }

    if (mb_strlen($dados['empresa']) < 2 || mb_strlen($dados['empresa']) > 150) {
        throw new InvalidArgumentException('Informe uma empresa valida.');
    }

    if (strlen(apenasNumerosSolicitacao($dados['rg'])) < 7) {
        throw new InvalidArgumentException('Informe um RG valido.');
    }

    if (!cpfSolicitacaoValido($dados['cpf'])) {
        throw new InvalidArgumentException('Informe um CPF valido.');
    }

    if (strlen(apenasNumerosSolicitacao($dados['celular'])) !== 11) {
        throw new InvalidArgumentException('Informe um celular valido com DDD.');
    }

    $nascimento = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['data_nascimento']);
    $errosData = DateTimeImmutable::getLastErrors();

    if (
        !$nascimento
        || ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0))
        || $nascimento > new DateTimeImmutable('today')
    ) {
        throw new InvalidArgumentException('Informe uma data de nascimento valida.');
    }

    return $dados;
}

function gerarHashSenhaSolicitacao(string $senha): string
{
    if (strlen($senha) < 6) {
        throw new InvalidArgumentException('A senha precisa ter pelo menos 6 caracteres.');
    }

    $hash = password_hash($senha, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2,
    ]);

    if ($hash === false) {
        throw new RuntimeException('Nao foi possivel proteger a senha.');
    }

    return $hash;
}

function gerarUuidSolicitacaoAcesso(): string
{
    $dados = random_bytes(16);
    $dados[6] = chr((ord($dados[6]) & 0x0f) | 0x40);
    $dados[8] = chr((ord($dados[8]) & 0x3f) | 0x80);
    $hex = bin2hex($dados);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20)
    );
}

function idSolicitacaoAcessoValido(string $id): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) === 1;
}

function extensaoFotoSolicitacao(string $caminhoTemporario): ?string
{
    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = '';

    if (class_exists('finfo')) {
        $leitorMime = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $leitorMime->file($caminhoTemporario);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($caminhoTemporario);
    }

    return $tiposPermitidos[$mime] ?? null;
}

function nomeFotoSolicitacaoSeguro(?string $nome): bool
{
    return is_string($nome)
        && preg_match('/^solicitacao-[a-f0-9]{32}\.(jpg|png|webp)$/', $nome) === 1;
}

function nomeFotoCrachaSolicitacaoSeguro(?string $nome): bool
{
    return is_string($nome)
        && preg_match('/^cracha-[A-Za-z0-9_-]+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nome) === 1;
}

function salvarFotoSolicitacao(array $arquivo): string
{
    $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
    $caminhoTemporario = $arquivo['tmp_name'] ?? '';
    $tamanho = $arquivo['size'] ?? 0;

    if (is_array($erro) || is_array($caminhoTemporario) || is_array($tamanho)) {
        throw new InvalidArgumentException('Envie apenas uma foto por vez.');
    }

    if ((int) $erro !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Selecione uma foto JPG, PNG ou WebP.');
    }

    if (!is_uploaded_file((string) $caminhoTemporario)) {
        throw new InvalidArgumentException('O upload da foto e invalido.');
    }

    if ((int) $tamanho <= 0 || (int) $tamanho > SOLICITACAO_ACESSO_FOTO_MAX_BYTES) {
        throw new InvalidArgumentException('Envie uma foto de ate 2 MB.');
    }

    $extensao = extensaoFotoSolicitacao((string) $caminhoTemporario);

    if ($extensao === null || @getimagesize((string) $caminhoTemporario) === false) {
        throw new InvalidArgumentException('Use uma imagem JPG, PNG ou WebP valida.');
    }

    if (!is_dir(SOLICITACAO_ACESSO_PASTA_FOTOS) && !mkdir(SOLICITACAO_ACESSO_PASTA_FOTOS, 0775, true)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de fotos.');
    }

    $nomeArquivo = 'solicitacao-' . bin2hex(random_bytes(16)) . '.' . $extensao;
    $destino = SOLICITACAO_ACESSO_PASTA_FOTOS . DIRECTORY_SEPARATOR . $nomeArquivo;

    if (!move_uploaded_file((string) $caminhoTemporario, $destino)) {
        throw new RuntimeException('Nao foi possivel salvar a foto enviada.');
    }

    @chmod($destino, 0644);

    return $nomeArquivo;
}

function removerFotoSolicitacao(?string $nomeArquivo): void
{
    if (!nomeFotoSolicitacaoSeguro($nomeArquivo)) {
        return;
    }

    $caminho = SOLICITACAO_ACESSO_PASTA_FOTOS . DIRECTORY_SEPARATOR . $nomeArquivo;

    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

function promoverFotoSolicitacao(?string $nomeArquivo, string $usuarioId): ?array
{
    if (!nomeFotoSolicitacaoSeguro($nomeArquivo)) {
        return null;
    }

    if (!is_dir(SOLICITACAO_ACESSO_PASTA_CRACHAS) && !mkdir(SOLICITACAO_ACESSO_PASTA_CRACHAS, 0775, true)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de crachas.');
    }

    $extensao = pathinfo((string) $nomeArquivo, PATHINFO_EXTENSION);
    $usuarioSeguro = preg_replace('/[^A-Za-z0-9_-]/', '', $usuarioId) ?: 'usuario';
    $nomeCracha = 'cracha-' . $usuarioSeguro . '-' . bin2hex(random_bytes(8)) . '.' . $extensao;
    $origem = SOLICITACAO_ACESSO_PASTA_FOTOS . DIRECTORY_SEPARATOR . $nomeArquivo;
    $destino = SOLICITACAO_ACESSO_PASTA_CRACHAS . DIRECTORY_SEPARATOR . $nomeCracha;

    if (!is_file($origem) || !rename($origem, $destino)) {
        throw new RuntimeException('Nao foi possivel preparar a foto do novo usuario.');
    }

    return [
        'nome_cracha' => $nomeCracha,
        'origem' => $origem,
        'destino' => $destino,
    ];
}

function reverterPromocaoFotoSolicitacao(?array $movimentacao): void
{
    if (!$movimentacao || !is_file((string) ($movimentacao['destino'] ?? ''))) {
        return;
    }

    @rename((string) $movimentacao['destino'], (string) $movimentacao['origem']);
}
