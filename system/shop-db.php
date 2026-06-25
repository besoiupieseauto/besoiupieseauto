<?php
declare(strict_types=1);

/**
 * Conexiune PDO ușoară pentru API-uri magazin (fără Composer autoload).
 */
function shop_db_bootstrap(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    shop_db_load_env();
    $config = shop_db_config();

    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

/** @return array{db_host:string,db_name:string,db_user:string,db_pass:string} */
function shop_db_config(): array
{
    $config = require __DIR__ . '/../admin/config/config.php';
    $dbName = trim((string) ($config['db_name'] ?? ''));
    if ($dbName === '' || $dbName === 'evasystem') {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (str_contains($host, 'besoiupieseauto')) {
            $config['db_name'] = 'besoiupieseauto.ro';
        }
    }

    return $config;
}

function shop_db_env_paths(): array
{
    $root = dirname(__DIR__);

    return array_values(array_unique([
        $root . '/admin/.env',
        $root . '/.env',
    ]));
}

function shop_db_load_env(?string $preferredPath = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $paths = $preferredPath !== null ? [$preferredPath] : shop_db_env_paths();
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }
        shop_db_parse_env_file($path);
    }

    $loaded = true;
}

function shop_db_parse_env_file(string $path): void
{
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function shop_db_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    $cache[$table] = $stmt !== false && $stmt->fetchColumn() !== false;

    return $cache[$table];
}
