<?php

declare(strict_types=1);

// Endpoint usado pela pagina inicial para montar os indicadores resumidos.
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Sem sessao valida, a tela deve pedir login novamente.
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

// Importa a camada compartilhada de autorização antes de executar esta rota.
require_once __DIR__ . "/permissoes-acesso.php";
exigirPermissaoApi("visualizar_dashboard", "Dashboard");

function consultarValorInteiro(PDO $pdo, string $sql, array $parametros = []): int
{
    // Atalho para contagens simples exibidas nos cards.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return (int)$stmt->fetchColumn();
}

function consultarLinhas(PDO $pdo, string $sql, array $parametros = []): array
{
    // Atalho para consultas que alimentam listas e graficos.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll();
}

function consultarEvolucaoAtivos(PDO $pdo, string $periodo): array
{
    // Cada periodo muda inicio, fim e passo da serie temporal.
    $configuracoes = [
        'hoje' => [
            'inicio' => "date_trunc('day', now())",
            'fim' => "date_trunc('hour', now())",
            'passo' => "interval '1 hour'",
            'label' => "HH24:00",
        ],
        'semana' => [
            'inicio' => "(current_date - interval '6 days')::timestamp",
            'fim' => "current_date::timestamp",
            'passo' => "interval '1 day'",
            'label' => "DD/MM",
        ],
        'mes' => [
            'inicio' => "(current_date - interval '29 days')::timestamp",
            'fim' => "current_date::timestamp",
            'passo' => "interval '1 day'",
            'label' => "DD/MM",
        ],
        'ano' => [
            'inicio' => "date_trunc('month', current_date) - interval '11 months'",
            'fim' => "date_trunc('month', current_date)",
            'passo' => "interval '1 month'",
            'label' => "Mon",
        ],
    ];

    $periodoSeguro = array_key_exists($periodo, $configuracoes) ? $periodo : 'semana';
    $config = $configuracoes[$periodoSeguro];

    return consultarLinhas(
        $pdo,
        "with pontos as (
            select generate_series(
                {$config['inicio']},
                {$config['fim']},
                {$config['passo']}
            ) as ponto
        )
        select
            to_char(p.ponto, '{$config['label']}') as label,
            count(a.id)::int as novos,
            (
                select count(*)::int
                  from public.ativos acumulado
                 where acumulado.criado_em <= case
                       when '{$periodoSeguro}' = 'hoje' then p.ponto + interval '59 minutes 59 seconds'
                       when '{$periodoSeguro}' = 'ano' then p.ponto + interval '1 month' - interval '1 second'
                       else p.ponto + interval '1 day' - interval '1 second'
                   end
            ) as total
          from pontos p
     left join public.ativos a
            on a.criado_em >= p.ponto
           and a.criado_em < case
               when '{$periodoSeguro}' = 'hoje' then p.ponto + interval '1 hour'
               when '{$periodoSeguro}' = 'ano' then p.ponto + interval '1 month'
               else p.ponto + interval '1 day'
           end
      group by p.ponto
      order by p.ponto"
    );
}

try {
    // A conexao vem pronta de Conexao.php e todas as consultas abaixo usam PDO.
    require __DIR__ . '/Conexao.php';

    // Indicadores principais da pagina inicial.
    $totalAtivos = consultarValorInteiro($pdo, 'select count(*) from public.ativos');

    $totalFuncionarios = consultarValorInteiro($pdo, 'select count(*) from public.perfis_usuarios');

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

    // Evolucao de ativos por varios periodos para a tela trocar sem nova chamada.
    $ativosEvolucao = [
        'hoje' => consultarEvolucaoAtivos($pdo, 'hoje'),
        'semana' => consultarEvolucaoAtivos($pdo, 'semana'),
        'mes' => consultarEvolucaoAtivos($pdo, 'mes'),
        'ano' => consultarEvolucaoAtivos($pdo, 'ano'),
    ];

    // Evolucao dos usuarios cadastrados nos ultimos dias.
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

    // Resposta unica para o frontend renderizar cards, graficos e listas.
    echo json_encode(
        [
            'total_ativos' => $totalAtivos,
            'total_funcionarios' => $totalFuncionarios,
            'funcionarios_ativos' => $funcionariosAtivos,
            'categorias' => $categorias,
            'status_ativos' => $statusAtivos,
            'estoque_evolucao' => $ativosEvolucao['semana'],
            'ativos_evolucao' => $ativosEvolucao,
            'cadastros_evolucao' => $cadastrosEvolucao,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable) {
    http_response_code(500);

    echo json_encode(
        [
            'error' => 'Nao foi possivel carregar as metricas da pagina inicial.',
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
