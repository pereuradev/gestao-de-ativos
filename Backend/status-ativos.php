<?php

declare(strict_types=1);

// Centraliza os status aceitos para ativos. A estrutura da tabela e os dados
// base agora sao aplicados por migrations em supabase/migrations.
function garantirStatusAtivos(PDO $pdo): void
{
    // Mantida para compatibilidade com chamadas antigas; nao executa DDL.
}

function nomesStatusAtivos(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
      order by ordem asc, nome asc
    ");
    $stmt->execute();

    return array_map(
        static fn(array $row): string => (string)($row["nome"] ?? ""),
        $stmt->fetchAll()
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
        $stmt = $pdo->prepare("
            select nome
              from public.status_ativos
             where ativo = true
               and slug = :slug
             limit 1
        ");
        $stmt->execute([":slug" => $slug]);

        $nome = $stmt->fetchColumn();

        return $nome !== false ? (string)$nome : null;
    }

    $stmt = $pdo->prepare("
        select nome
          from public.status_ativos
         where ativo = true
           and lower(btrim(nome)) = lower(btrim(:status))
         limit 1
    ");
    $stmt->execute([":status" => trim($status)]);

    $nome = $stmt->fetchColumn();

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
