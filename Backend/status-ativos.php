<?php

declare(strict_types=1);

// Centraliza os status aceitos para ativos. A estrutura da tabela e os dados
// A estrutura base deve estar provisionada no banco antes de iniciar a aplicacao.
function garantirStatusAtivos(PDO $pdo): void
{
    // Mantida para compatibilidade com chamadas antigas; nao executa DDL.
}

function nomesStatusAtivos(PDO $pdo): array
{
    $consultaPreparada = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
      order by ordem asc, nome asc
    ");
    $consultaPreparada->execute();

    return array_map(
        static fn(array $linhaResultado): string => (string)($linhaResultado["nome"] ?? ""),
        $consultaPreparada->fetchAll()
    );
}

function statusAtivoPadrao(): string
{
    // Valor usado quando o formulario nao envia status.
    return "Disponível";
}

function obterStatusAtivo(PDO $pdo, string $status): ?string
{
    // Recebe textos antigos ou digitados de formas diferentes e devolve o nome oficial.
    $valor = strtolower(trim($status));
    $slug = null;

    if (strpos($valor, "dispon") === 0) {
        $slug = "disponivel";
    } elseif ($valor === "em uso") {
        $slug = "em-uso";
    } elseif (strpos($valor, "homologa") === 0) {
        $slug = "homologacao";
    } elseif (strpos($valor, "manuten") === 0 || strpos($valor, "formata") === 0 || $valor === "baixado" || $valor === "perdido") {
        $slug = "manutencao";
    }

    if ($slug !== null) {
        $consultaPreparada = $pdo->prepare("
            select nome
              from public.status_ativos
             where ativo = true
               and slug = :slug
             limit 1
        ");
        $consultaPreparada->execute([":slug" => $slug]);

        $nome = $consultaPreparada->fetchColumn();

        return $nome !== false ? (string)$nome : null;
    }

    $consultaPreparada = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
           and lower(btrim(nome)) = lower(btrim(:status))
         limit 1
    ");
    $consultaPreparada->execute([":status" => trim($status)]);

    $nome = $consultaPreparada->fetchColumn();

    return $nome !== false ? (string)$nome : null;
}

function classeStatusAtivo(string $status): string
{
    // Traduz o status em uma classe visual reutilizada nas tabelas.
    $valor = strtolower(trim($status));

    if (strpos($valor, "dispon") === 0) {
        return "status-badge status-available";
    }

    if ($valor === "em uso") {
        return "status-badge status-in-use";
    }

    if (strpos($valor, "homologa") === 0) {
        return "status-badge status-homologation";
    }

    if (strpos($valor, "manuten") === 0) {
        return "status-badge status-maintenance";
    }

    return "status-badge status-neutral";
}
