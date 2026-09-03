<?php
declare(strict_types=1);

/**
 * Config DB — citește app/Config/.env (DB_HOST, DB_NAME, DB_USER, DB_PASS).
 */

const QWP_VIEWER_ROOT = __DIR__;

function qwp_load_dotenv(): void
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

qwp_load_dotenv();

function qwp_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }

    return (string) $v;
}

function qwp_db_host(): string
{
    $host = qwp_env('DB_HOST', '127.0.0.1');

    return $host === 'localhost' ? '127.0.0.1' : $host;
}

function qwp_db_name(): string
{
    return qwp_env('DB_NAME', 'besoiupieseauto.ro');
}

function qwp_db_user(): string
{
    return qwp_env('DB_USER', 'root');
}

function qwp_db_pass(): string
{
    return qwp_env('DB_PASS', '');
}

function qwp_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function qwp_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
