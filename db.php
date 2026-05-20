<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbFetch(string $sql, array $params = []): ?array {
    return dbQuery($sql, $params)->fetch() ?: null;
}

function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

function dbInsert(string $sql, array $params = []): int {
    dbQuery($sql, $params);
    return (int) getDB()->lastInsertId();
}
