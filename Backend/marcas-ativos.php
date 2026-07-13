<?php

declare(strict_types=1);

// Mantido para compatibilidade com includes antigos. A tabela de marcas,
// indices, limpeza de dados e FKs ficam em supabase/migrations.
function garantirMarcasAtivos(PDO $pdo): void
{
    // Nao executa DDL em runtime.
}
