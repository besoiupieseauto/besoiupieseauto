<?php
declare(strict_types=1);

/**
 * Config Matc — baze TecDoc importate din Fisierile Matc.
 * Citeste .env din app/Config daca exista.
 */

const MATC_TOOLS_ROOT = __DIR__;

function matc_load_dotenv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envFile = dirname(__DIR__, 3) . '/app/Config/.env';
    if (!is_file($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

matc_load_dotenv();

function matc_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }

    return (string) $v;
}

/** Snapshot full import (2.15M+ produse). */
const MATC_DB_SNAPSHOT = 'besoiu_tecdoc_matc_20260826';

/** Baza de lucru scurta (dupa activate). */
const MATC_DB_ACTIVE_NAME = 'besoiu_tecdoc_matc';

/** Toate bazele Matc cunoscute pe Laragon. */
function matc_known_databases(): array
{
    return [
        MATC_DB_SNAPSHOT,
        MATC_DB_ACTIVE_NAME,
        'besoiu_tecdoc_matc_lipsa',
        'besoiu_tecdoc_matc_verificare',
        'besoiu_tecdoc_matc_testlock',
    ];
}

function matc_active_config_path(): string
{
    return MATC_TOOLS_ROOT . '/active_db.json';
}

function matc_read_active_db(): string
{
    $path = matc_active_config_path();
    if (is_file($path)) {
        $json = json_decode((string) file_get_contents($path), true);
        $db = trim((string) ($json['database'] ?? ''));
        if ($db !== '') {
            return $db;
        }
    }

    $env = matc_env('TECDOC_MATC_DB', '');
    if ($env !== '') {
        return $env;
    }

    return MATC_DB_SNAPSHOT;
}

function matc_write_active_db(string $database, string $note = ''): void
{
    $payload = [
        'database' => $database,
        'updated_at' => date('c'),
        'note' => $note,
    ];
    file_put_contents(
        matc_active_config_path(),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function matc_mysql_host(): string
{
    $host = matc_env('TECDOC_MATC_HOST', matc_env('DB_HOST', '127.0.0.1'));
    if ($host === 'localhost') {
        return '127.0.0.1';
    }

    return $host;
}

function matc_mysql_dsn(string $database): string
{
    return 'mysql:host=' . matc_mysql_host() . ';dbname=' . $database . ';charset=utf8mb4';
}

function matc_mysql_credentials(): array
{
    // Baze Matc locale Laragon — implicit root fara parola.
    $user = matc_env('TECDOC_MATC_USER', 'root');
    $pass = matc_env('TECDOC_MATC_PASS', '');

    return [$user, $pass];
}
