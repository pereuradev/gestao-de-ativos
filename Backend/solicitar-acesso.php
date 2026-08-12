<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/funcoes-solicitacoes-acesso.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderSolicitacaoAcesso(false, 'Metodo nao permitido.', 405);
}

$tokenSessao = is_string($_SESSION['csrf_solicitacao_acesso'] ?? null)
    ? $_SESSION['csrf_solicitacao_acesso']
    : '';
$tokenEnviado = valorTextoSolicitacao($_POST, 'csrf_token');

if ($tokenSessao === '' || $tokenEnviado === '' || !hash_equals($tokenSessao, $tokenEnviado)) {
    responderSolicitacaoAcesso(false, 'Sessao de seguranca expirada. Atualize a pagina.', 403);
}

$ultimaSolicitacao = (int) ($_SESSION['ultima_solicitacao_acesso_em'] ?? 0);

if ($ultimaSolicitacao > 0 && time() - $ultimaSolicitacao < 30) {
    responderSolicitacaoAcesso(false, 'Aguarde alguns segundos antes de enviar outra solicitacao.', 429);
}

$nomeFoto = null;

try {
    $dados = validarDadosSolicitacaoAcesso($_POST);
    $senhaHash = gerarHashSenhaSolicitacao((string) ($_POST['senha'] ?? ''));
    $arquivoFoto = $_FILES['foto'] ?? null;

    if (!is_array($arquivoFoto)) {
        throw new InvalidArgumentException('Selecione uma foto para continuar.');
    }

    require __DIR__ . '/Conexao.php';

    $consultaDuplicidade = $pdo->prepare("
        select 'perfil' as origem
          from public.perfis_usuarios
         where lower(btrim(email)) = lower(btrim(:email))
            or regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
            or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
        union all
        select 'solicitacao' as origem
          from public.solicitacoes_acesso
         where status = 'Pendente'
           and (
                lower(btrim(email)) = lower(btrim(:email))
                or regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
                or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
           )
         limit 1
    ");
    $consultaDuplicidade->execute([
        ':email' => $dados['email'],
        ':cpf' => $dados['cpf'],
        ':rg' => $dados['rg'],
    ]);

    if ($consultaDuplicidade->fetchColumn() !== false) {
        responderSolicitacaoAcesso(
            false,
            'Ja existe um usuario ou uma solicitacao pendente com estes dados.',
            409
        );
    }

    $nomeFoto = salvarFotoSolicitacao($arquivoFoto);

    $inserir = $pdo->prepare("
        insert into public.solicitacoes_acesso (
            nome_completo,
            email,
            senha_hash,
            tipo_usuario,
            departamento,
            empresa,
            rg,
            cpf,
            celular,
            data_nascimento,
            foto_nome_arquivo
        ) values (
            :nome_completo,
            :email,
            :senha_hash,
            :tipo_usuario,
            :departamento,
            :empresa,
            :rg,
            :cpf,
            :celular,
            :data_nascimento,
            :foto_nome_arquivo
        )
        returning id::text
    ");
    $inserir->execute([
        ':nome_completo' => $dados['nome_completo'],
        ':email' => $dados['email'],
        ':senha_hash' => $senhaHash,
        ':tipo_usuario' => $dados['tipo_usuario'],
        ':departamento' => $dados['departamento'],
        ':empresa' => $dados['empresa'],
        ':rg' => $dados['rg'],
        ':cpf' => $dados['cpf'],
        ':celular' => $dados['celular'],
        ':data_nascimento' => $dados['data_nascimento'],
        ':foto_nome_arquivo' => $nomeFoto,
    ]);

    $_SESSION['ultima_solicitacao_acesso_em'] = time();
    $_SESSION['csrf_solicitacao_acesso'] = bin2hex(random_bytes(32));

    responderSolicitacaoAcesso(true, 'Solicitacao enviada. Aguarde a analise da equipe.', 201);
} catch (InvalidArgumentException $erro) {
    removerFotoSolicitacao($nomeFoto);
    responderSolicitacaoAcesso(false, $erro->getMessage(), 422);
} catch (PDOException $erro) {
    removerFotoSolicitacao($nomeFoto);

    if ($erro->getCode() === '23505') {
        responderSolicitacaoAcesso(false, 'Ja existe uma solicitacao pendente com estes dados.', 409);
    }

    error_log('Erro ao criar solicitacao de acesso: ' . $erro->getMessage());
    responderSolicitacaoAcesso(false, 'Nao foi possivel enviar a solicitacao agora.', 500);
} catch (Throwable $erro) {
    removerFotoSolicitacao($nomeFoto);
    error_log('Erro inesperado na solicitacao de acesso: ' . $erro->getMessage());
    responderSolicitacaoAcesso(false, 'Nao foi possivel enviar a solicitacao agora.', 500);
}
