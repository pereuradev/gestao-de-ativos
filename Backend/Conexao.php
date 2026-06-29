<?php

declare(strict_types=1);

// Arquivo unico de conexao com o banco. As outras rotas incluem este arquivo
// e passam a usar a variavel $pdo ja configurada.
require_once __DIR__ . "/config.php";

// As credenciais ficam no ambiente/.env para nao ficarem espalhadas pelo codigo.
$host = configObrigatoria("DB_HOST");
$port = configValor("DB_PORT", "5432");
$dbname = configValor("DB_NAME", "postgres");
$user = configObrigatoria("DB_USER");
$password = configObrigatoria("DB_PASSWORD");
$sslMode = configValor("DB_SSLMODE", "require");

try {
    // PDO com erros por excecao deixa as rotas tratarem falhas de banco no catch.
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslMode}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
} catch (PDOException $erro) {
    throw new RuntimeException("Erro ao conectar com o banco de dados.", 0, $erro);
}
