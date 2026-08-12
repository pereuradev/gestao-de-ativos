<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/funcoes-solicitacoes-acesso.php';
require_once __DIR__ . '/permissoes-acesso.php';

exigirPermissaoApi('gerenciar_solicitacoes_acesso', 'Fotos das solicitacoes de acesso');

$id = valorTextoSolicitacao($_GET, 'id');

if (!idSolicitacaoAcessoValido($id)) {
    responderSolicitacaoAcesso(false, 'Solicitacao invalida.', 422);
}

require_once __DIR__ . '/Conexao.php';

$consulta = $pdo->prepare("
    select
        s.status,
        s.foto_nome_arquivo,
        p.foto_cracha
      from public.solicitacoes_acesso s
      left join public.perfis_usuarios p
        on s.status = 'Aprovada'
       and lower(btrim(p.email)) = lower(btrim(s.email))
     where s.id = cast(:id as uuid)
     limit 1
");
$consulta->execute([':id' => $id]);
$solicitacao = $consulta->fetch();

if (!is_array($solicitacao)) {
    http_response_code(404);
    exit;
}

$nomeSolicitacao = is_string($solicitacao['foto_nome_arquivo'] ?? null)
    ? $solicitacao['foto_nome_arquivo']
    : null;
$nomeCracha = is_string($solicitacao['foto_cracha'] ?? null)
    ? $solicitacao['foto_cracha']
    : null;
$aprovada = ($solicitacao['status'] ?? '') === 'Aprovada';

if (nomeFotoSolicitacaoSeguro($nomeSolicitacao)) {
    $nomeArquivo = $nomeSolicitacao;
    $caminho = SOLICITACAO_ACESSO_PASTA_FOTOS . DIRECTORY_SEPARATOR . $nomeArquivo;
} elseif ($aprovada && nomeFotoCrachaSolicitacaoSeguro($nomeCracha)) {
    $nomeArquivo = $nomeCracha;
    $caminho = SOLICITACAO_ACESSO_PASTA_CRACHAS . DIRECTORY_SEPARATOR . $nomeArquivo;
} else {
    http_response_code(404);
    exit;
}

if (!is_file($caminho)) {
    http_response_code(404);
    exit;
}

$tipos = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));

header('Content-Type: ' . ($tipos[$extensao] ?? 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($caminho));
header('Cache-Control: private, max-age=120');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
