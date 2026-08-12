<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/funcoes-solicitacoes-acesso.php';
require_once __DIR__ . '/permissoes-acesso.php';

exigirPermissaoApi('gerenciar_solicitacoes_acesso', 'Solicitacoes de acesso');

function corpoJsonSolicitacoesAcesso(): array
{
    $conteudo = file_get_contents('php://input');

    if (!is_string($conteudo) || trim($conteudo) === '') {
        return [];
    }

    $dados = json_decode($conteudo, true);

    if (!is_array($dados) || json_last_error() !== JSON_ERROR_NONE) {
        responderSolicitacaoAcesso(false, 'Envie um JSON valido.', 400);
    }

    return $dados;
}

function csrfInternoSolicitacaoValido(array $dados): bool
{
    $tokenSessao = $_SESSION['csrf_token'] ?? '';
    $tokenCabecalho = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $tokenCorpo = valorTextoSolicitacao($dados, 'csrf_token');
    $tokenEnviado = is_string($tokenCabecalho) && $tokenCabecalho !== '' ? $tokenCabecalho : $tokenCorpo;

    return is_string($tokenSessao)
        && $tokenSessao !== ''
        && is_string($tokenEnviado)
        && $tokenEnviado !== ''
        && hash_equals($tokenSessao, $tokenEnviado);
}

function obterSolicitacaoBloqueada(PDO $pdo, string $id): array
{
    $consulta = $pdo->prepare("
        select *
          from public.solicitacoes_acesso
         where id = cast(:id as uuid)
         for update
    ");
    $consulta->execute([':id' => $id]);
    $solicitacao = $consulta->fetch();

    if (!is_array($solicitacao)) {
        responderSolicitacaoAcesso(false, 'Solicitacao nao encontrada.', 404);
    }

    return $solicitacao;
}

function validarSolicitacaoPendente(array $solicitacao, int $versao): void
{
    if (($solicitacao['status'] ?? '') !== 'Pendente') {
        responderSolicitacaoAcesso(false, 'Esta solicitacao ja foi analisada.', 409);
    }

    if ((int) ($solicitacao['versao'] ?? 0) !== $versao) {
        responderSolicitacaoAcesso(false, 'Os dados foram alterados por outra pessoa. Atualize a lista.', 409);
    }
}

function garantirDadosDisponiveisParaPerfil(PDO $pdo, array $dados, ?string $solicitacaoId = null): void
{
    $consultaPerfil = $pdo->prepare("
        select email, cpf, rg
          from public.perfis_usuarios
         where lower(btrim(email)) = lower(btrim(:email))
            or regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
            or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
         limit 1
    ");
    $consultaPerfil->execute([
        ':email' => $dados['email'],
        ':cpf' => $dados['cpf'],
        ':rg' => $dados['rg'],
    ]);

    if ($consultaPerfil->fetch()) {
        responderSolicitacaoAcesso(false, 'Ja existe um usuario com o e-mail, CPF ou RG informado.', 409);
    }

    if ($solicitacaoId === null) {
        return;
    }

    $consultaPendente = $pdo->prepare("
        select 1
          from public.solicitacoes_acesso
         where status = 'Pendente'
           and id <> cast(:id as uuid)
           and (
                lower(btrim(email)) = lower(btrim(:email))
                or regexp_replace(cpf, '[^0-9]', '', 'g') = regexp_replace(:cpf, '[^0-9]', '', 'g')
                or regexp_replace(rg, '[^0-9]', '', 'g') = regexp_replace(:rg, '[^0-9]', '', 'g')
           )
         limit 1
    ");
    $consultaPendente->execute([
        ':id' => $solicitacaoId,
        ':email' => $dados['email'],
        ':cpf' => $dados['cpf'],
        ':rg' => $dados['rg'],
    ]);

    if ($consultaPendente->fetchColumn() !== false) {
        responderSolicitacaoAcesso(false, 'Outra solicitacao pendente ja usa o e-mail, CPF ou RG informado.', 409);
    }
}

function listarSolicitacoesAcesso(PDO $pdo): void
{
    $status = valorTextoSolicitacao($_GET, 'status', 'Pendente');
    $busca = valorTextoSolicitacao($_GET, 'busca');
    $statusPermitidos = ['Todas', 'Pendente', 'Aprovada', 'Recusada'];

    if (!in_array($status, $statusPermitidos, true)) {
        $status = 'Pendente';
    }

    $filtros = [];
    $parametros = [];

    if ($status !== 'Todas') {
        $filtros[] = 's.status = :status';
        $parametros[':status'] = $status;
    }

    if ($busca !== '') {
        $filtros[] = "(s.nome_completo ilike :busca or s.email ilike :busca or s.cpf ilike :busca)";
        $parametros[':busca'] = '%' . $busca . '%';
    }

    $onde = $filtros ? 'where ' . implode(' and ', $filtros) : '';
    $consulta = $pdo->prepare("
        select
            s.id::text,
            s.nome_completo,
            s.email,
            s.tipo_usuario,
            s.departamento,
            s.empresa,
            s.rg,
            s.cpf,
            s.celular,
            s.data_nascimento::text,
            s.status,
            s.motivo_recusa,
            s.versao,
            s.criado_em,
            s.atualizado_em,
            s.analisada_em,
            coalesce(a.nome_completo, '') as analisada_por_nome,
            (s.foto_nome_arquivo is not null or p.foto_cracha is not null) as possui_foto
          from public.solicitacoes_acesso s
          left join public.perfis_usuarios a on a.id = s.analisada_por
          left join public.perfis_usuarios p
            on s.status = 'Aprovada'
           and lower(btrim(p.email)) = lower(btrim(s.email))
          {$onde}
      order by
            case s.status when 'Pendente' then 0 when 'Aprovada' then 1 else 2 end,
            s.criado_em desc
         limit 150
    ");
    $consulta->execute($parametros);
    $solicitacoes = array_map(static function (array $linha): array {
        $linha['versao'] = (int) ($linha['versao'] ?? 1);
        $linha['possui_foto'] = filter_var($linha['possui_foto'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $linha['foto_url'] = $linha['possui_foto']
            ? '../Backend/foto-solicitacao-acesso.php?id=' . rawurlencode((string) $linha['id'])
                . '&v=' . rawurlencode((string) $linha['versao'])
            : null;

        return $linha;
    }, $consulta->fetchAll());

    responderSolicitacaoAcesso(true, 'Solicitacoes carregadas.', 200, [
        'solicitacoes' => $solicitacoes,
        'total' => count($solicitacoes),
    ]);
}

function resumirSolicitacoesAcesso(PDO $pdo): void
{
    $consulta = $pdo->query("
        select
            count(*) filter (where status = 'Pendente')::int as pendentes,
            count(*) filter (where status = 'Aprovada')::int as aprovadas,
            count(*) filter (where status = 'Recusada')::int as recusadas,
            max(criado_em) filter (where status = 'Pendente') as ultima_pendente_em
          from public.solicitacoes_acesso
    ");
    $resumo = $consulta->fetch() ?: [];

    responderSolicitacaoAcesso(true, 'Resumo carregado.', 200, [
        'resumo' => [
            'pendentes' => (int) ($resumo['pendentes'] ?? 0),
            'aprovadas' => (int) ($resumo['aprovadas'] ?? 0),
            'recusadas' => (int) ($resumo['recusadas'] ?? 0),
            'ultima_pendente_em' => $resumo['ultima_pendente_em'] ?? null,
        ],
    ]);
}

function alterarSolicitacaoAcesso(PDO $pdo, array $corpo): void
{
    $id = valorTextoSolicitacao($corpo, 'id');
    $versao = (int) ($corpo['versao'] ?? 0);

    if (!idSolicitacaoAcessoValido($id) || $versao < 1) {
        responderSolicitacaoAcesso(false, 'Solicitacao invalida.', 422);
    }

    try {
        $dados = validarDadosSolicitacaoAcesso($corpo);
        $novaSenha = valorTextoSolicitacao($corpo, 'nova_senha');
        $novoHash = $novaSenha !== '' ? gerarHashSenhaSolicitacao($novaSenha) : null;

        $pdo->beginTransaction();
        $solicitacao = obterSolicitacaoBloqueada($pdo, $id);
        validarSolicitacaoPendente($solicitacao, $versao);
        exigirAdministradorParaGerenciarPerfilApi(
            (string) ($solicitacao['tipo_usuario'] ?? ''),
            $dados['tipo_usuario'],
            'alteracoes de solicitacoes administrativas'
        );
        garantirDadosDisponiveisParaPerfil($pdo, $dados, $id);

        $atualizar = $pdo->prepare("
            update public.solicitacoes_acesso
               set nome_completo = :nome_completo,
                   email = :email,
                   tipo_usuario = :tipo_usuario,
                   departamento = :departamento,
                   empresa = :empresa,
                   rg = :rg,
                   cpf = :cpf,
                   celular = :celular,
                   data_nascimento = :data_nascimento,
                   senha_hash = coalesce(:senha_hash, senha_hash),
                   versao = versao + 1,
                   atualizado_em = now()
             where id = cast(:id as uuid)
         returning versao
        ");
        $atualizar->execute([
            ':nome_completo' => $dados['nome_completo'],
            ':email' => $dados['email'],
            ':tipo_usuario' => $dados['tipo_usuario'],
            ':departamento' => $dados['departamento'],
            ':empresa' => $dados['empresa'],
            ':rg' => $dados['rg'],
            ':cpf' => $dados['cpf'],
            ':celular' => $dados['celular'],
            ':data_nascimento' => $dados['data_nascimento'],
            ':senha_hash' => $novoHash,
            ':id' => $id,
        ]);
        $novaVersao = (int) $atualizar->fetchColumn();
        $pdo->commit();

        responderSolicitacaoAcesso(true, 'Informacoes atualizadas.', 200, ['versao' => $novaVersao]);
    } catch (InvalidArgumentException $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        responderSolicitacaoAcesso(false, $erro->getMessage(), 422);
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro ao alterar solicitacao de acesso: ' . $erro->getMessage());
        responderSolicitacaoAcesso(false, 'Nao foi possivel alterar a solicitacao.', 500);
    }
}

function aprovarSolicitacaoAcesso(PDO $pdo, array $corpo): void
{
    $id = valorTextoSolicitacao($corpo, 'id');
    $versao = (int) ($corpo['versao'] ?? 0);
    $analisadorId = (string) ($_SESSION['usuario']['id'] ?? '');

    if (!idSolicitacaoAcessoValido($id) || $versao < 1 || !idSolicitacaoAcessoValido($analisadorId)) {
        responderSolicitacaoAcesso(false, 'Solicitacao ou usuario analisador invalido.', 422);
    }

    $movimentacaoFoto = null;

    try {
        $pdo->beginTransaction();
        $solicitacao = obterSolicitacaoBloqueada($pdo, $id);
        validarSolicitacaoPendente($solicitacao, $versao);
        exigirAdministradorParaGerenciarPerfilApi(
            (string) ($solicitacao['tipo_usuario'] ?? ''),
            (string) ($solicitacao['tipo_usuario'] ?? ''),
            'a aprovacao de solicitacoes administrativas'
        );
        garantirDadosDisponiveisParaPerfil($pdo, $solicitacao);

        $usuarioId = gerarUuidSolicitacaoAcesso();
        $movimentacaoFoto = promoverFotoSolicitacao(
            is_string($solicitacao['foto_nome_arquivo'] ?? null) ? $solicitacao['foto_nome_arquivo'] : null,
            $usuarioId
        );
        $nomeCracha = $movimentacaoFoto['nome_cracha'] ?? null;

        $inserirPerfil = $pdo->prepare("
            insert into public.perfis_usuarios (
                id,
                nome_completo,
                email,
                tipo_usuario,
                departamento,
                empresa,
                rg,
                cpf,
                celular,
                data_nascimento,
                senha,
                status,
                foto_cracha
            ) values (
                cast(:id as uuid),
                :nome_completo,
                :email,
                :tipo_usuario,
                :departamento,
                :empresa,
                :rg,
                :cpf,
                :celular,
                :data_nascimento,
                :senha,
                'Ativo',
                :foto_cracha
            )
        ");
        $inserirPerfil->execute([
            ':id' => $usuarioId,
            ':nome_completo' => $solicitacao['nome_completo'],
            ':email' => $solicitacao['email'],
            ':tipo_usuario' => $solicitacao['tipo_usuario'],
            ':departamento' => $solicitacao['departamento'],
            ':empresa' => $solicitacao['empresa'],
            ':rg' => $solicitacao['rg'],
            ':cpf' => $solicitacao['cpf'],
            ':celular' => $solicitacao['celular'],
            ':data_nascimento' => $solicitacao['data_nascimento'],
            ':senha' => $solicitacao['senha_hash'],
            ':foto_cracha' => $nomeCracha,
        ]);

        $atualizar = $pdo->prepare("
            update public.solicitacoes_acesso
               set status = 'Aprovada',
                   senha_hash = null,
                   foto_nome_arquivo = null,
                   analisada_por = cast(:analisada_por as uuid),
                   analisada_em = now(),
                   atualizado_em = now(),
                   versao = versao + 1
             where id = cast(:id as uuid)
        ");
        $atualizar->execute([
            ':analisada_por' => $analisadorId,
            ':id' => $id,
        ]);
        $pdo->commit();

        responderSolicitacaoAcesso(true, 'Acesso aprovado e usuario ativado.');
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        reverterPromocaoFotoSolicitacao($movimentacaoFoto);
        error_log('Erro ao aprovar solicitacao de acesso: ' . $erro->getMessage());

        if ($erro instanceof PDOException && $erro->getCode() === '23505') {
            responderSolicitacaoAcesso(false, 'Ja existe um usuario com estes dados.', 409);
        }

        responderSolicitacaoAcesso(false, 'Nao foi possivel aprovar a solicitacao.', 500);
    }
}

function recusarSolicitacaoAcesso(PDO $pdo, array $corpo): void
{
    $id = valorTextoSolicitacao($corpo, 'id');
    $versao = (int) ($corpo['versao'] ?? 0);
    $motivo = valorTextoSolicitacao($corpo, 'motivo');
    $analisadorId = (string) ($_SESSION['usuario']['id'] ?? '');

    if (!idSolicitacaoAcessoValido($id) || $versao < 1 || !idSolicitacaoAcessoValido($analisadorId)) {
        responderSolicitacaoAcesso(false, 'Solicitacao ou usuario analisador invalido.', 422);
    }

    if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 500) {
        responderSolicitacaoAcesso(false, 'Informe um motivo de recusa entre 3 e 500 caracteres.', 422);
    }

    $nomeFoto = null;

    try {
        $pdo->beginTransaction();
        $solicitacao = obterSolicitacaoBloqueada($pdo, $id);
        validarSolicitacaoPendente($solicitacao, $versao);
        $nomeFoto = is_string($solicitacao['foto_nome_arquivo'] ?? null)
            ? $solicitacao['foto_nome_arquivo']
            : null;

        $atualizar = $pdo->prepare("
            update public.solicitacoes_acesso
               set status = 'Recusada',
                   motivo_recusa = :motivo,
                   senha_hash = null,
                   foto_nome_arquivo = null,
                   analisada_por = cast(:analisada_por as uuid),
                   analisada_em = now(),
                   atualizado_em = now(),
                   versao = versao + 1
             where id = cast(:id as uuid)
        ");
        $atualizar->execute([
            ':motivo' => $motivo,
            ':analisada_por' => $analisadorId,
            ':id' => $id,
        ]);
        $pdo->commit();
        removerFotoSolicitacao($nomeFoto);

        responderSolicitacaoAcesso(true, 'Solicitacao recusada.');
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Erro ao recusar solicitacao de acesso: ' . $erro->getMessage());
        responderSolicitacaoAcesso(false, 'Nao foi possivel recusar a solicitacao.', 500);
    }
}

require_once __DIR__ . '/Conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $acao = valorTextoSolicitacao($_GET, 'acao', 'listar');

    if ($acao === 'resumo') {
        resumirSolicitacoesAcesso($pdo);
    }

    listarSolicitacoesAcesso($pdo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderSolicitacaoAcesso(false, 'Metodo nao permitido.', 405);
}

$corpo = corpoJsonSolicitacoesAcesso();

if (!csrfInternoSolicitacaoValido($corpo)) {
    responderSolicitacaoAcesso(false, 'Token de seguranca invalido. Atualize a pagina.', 403);
}

$acao = valorTextoSolicitacao($corpo, 'acao');

switch ($acao) {
    case 'alterar':
        alterarSolicitacaoAcesso($pdo, $corpo);
        break;
    case 'aprovar':
        aprovarSolicitacaoAcesso($pdo, $corpo);
        break;
    case 'recusar':
        recusarSolicitacaoAcesso($pdo, $corpo);
        break;
    default:
        responderSolicitacaoAcesso(false, 'Acao invalida.', 422);
}
