<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(
        [
            'ok' => false,
            'message' => 'Sessao expirada. Faca login novamente.',
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function consultarValorInteiro(PDO $pdo, string $sql, array $parametros = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return (int) $stmt->fetchColumn();
}

function consultarLinhas(PDO $pdo, string $sql, array $parametros = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll();
}

try {
    require __DIR__ . '/Conexao.php';

    $totalAtivos = consultarValorInteiro(
        $pdo,
        'select count(*) from public.ativos'
    );

    $totalFuncionarios = consultarValorInteiro(
        $pdo,
        'select count(*) from public.perfis_usuarios'
    );

    $funcionariosAtivos = consultarValorInteiro(
        $pdo,
        "select count(*) from public.perfis_usuarios where lower(coalesce(status, '')) = 'ativo'"
    );

    $categorias = consultarLinhas(
        $pdo,
        'select c.nome, count(a.id)::int as total
           from public.categorias_ativos c
      left join public.ativos a on a.categoria_id = c.id
       group by c.id, c.nome
       order by total desc, c.nome asc'
    );

    $statusAtivos = consultarLinhas(
        $pdo,
        "select coalesce(nullif(trim(a.status), ''), 'Sem status') as status,
                count(*)::int as total
           from public.ativos a
       group by coalesce(nullif(trim(a.status), ''), 'Sem status')
       order by total desc, status asc"
    );

    $estoqueEvolucao = consultarLinhas(
        $pdo,
        "with dias as (
            select generate_series(
                (current_date - interval '6 days')::date,
                current_date::date,
                interval '1 day'
            )::date as dia
        )
        select
            to_char(d.dia, 'DD/MM') as label,
            (
                select count(*)::int
                  from public.ativos a
                 where lower(a.status) in ('disponível', 'disponivel', 'estoque', 'em estoque')
                   and a.criado_em::date <= d.dia
            ) as total
          from dias d
      order by d.dia"
    );

    $cadastrosEvolucao = consultarLinhas(
        $pdo,
        "with dias as (
            select generate_series(
                (current_date - interval '6 days')::date,
                current_date::date,
                interval '1 day'
            )::date as dia
        )
        select
            to_char(d.dia, 'DD/MM') as label,
            (
                select count(*)::int
                  from public.perfis_usuarios p
                 where p.criado_em::date <= d.dia
            ) as total
          from dias d
      order by d.dia"
    );

    echo json_encode(
        [
            'total_ativos' => $totalAtivos,
            'total_funcionarios' => $totalFuncionarios,
            'funcionarios_ativos' => $funcionariosAtivos,
            'categorias' => $categorias,
            'status_ativos' => $statusAtivos,
            'estoque_evolucao' => $estoqueEvolucao,
            'cadastros_evolucao' => $cadastrosEvolucao,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode(
        [
            'error' => 'N\u00e3o foi poss\u00edvel carregar as m\u00e9tricas da p\u00e1gina inicial.',
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

