<?php

declare(strict_types=1);

/**
 * Capa de acceso a base de datos (PDO).
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'miserver');
        $user = env('DB_USER', 'miserver');
        $pass = env('DB_PASS', '');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function db_prepare(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db_prepare($sql, $params);
    return $stmt->fetch() ?: null;
}

function db_all(string $sql, array $params = []): array
{
    return db_prepare($sql, $params)->fetchAll();
}

function db_run(string $sql, array $params = []): PDOStatement
{
    return db_prepare($sql, $params);
}

function db_last_id(): string
{
    return db()->lastInsertId();
}

function db_count(string $table, string $where = '1', array $params = []): int
{
    $sql = "SELECT COUNT(*) c FROM {$table} WHERE {$where}";
    return (int) db_one($sql, $params)['c'];
}