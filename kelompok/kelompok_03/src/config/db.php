<?php
/**
 * DB utility for `src` pages.
 * Usage: require_once __DIR__ . '/config/db.php'; then call db_fetch_all()/db_fetch()/db_execute()
 */

// load environment variables defined in src/config/.env (or src/.env)
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

function get_db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $host = env('DB_HOST');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS');

    if ($host && $name && $user !== null) {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    $sqlitePath = __DIR__ . '/../database.sqlite';
    if (file_exists($sqlitePath)) {
        $pdo = new PDO('sqlite:' . realpath($sqlitePath));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    throw new Exception('No database configuration found. Set DB_HOST/DB_NAME/DB_USER or provide database.sqlite at project root.');
}

function db_query(string $sql, array $params = []) {
    $pdo = get_db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(string $sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

function db_fetch_all(string $sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

function db_execute(string $sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->rowCount();
}
