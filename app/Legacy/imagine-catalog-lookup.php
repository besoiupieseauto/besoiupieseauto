<?php

declare(strict_types=1);

/**
 * Lookup comun pentru imagine_produse / imagine_poze / imagine_autototal.
 */

function imagine_catalog_project_root(): string
{
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . '/app/Config/config.php') || is_file($dir . '/admin/bootstrap.php')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    return dirname(__DIR__, 2);
}

function imagine_catalog_load_env(?string $root = null): void
{
    $root = $root ?? imagine_catalog_project_root();
    $envFile = $root . '/app/Config/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k === '' || getenv($k) !== false) {
            continue;
        }
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
}

function imagine_catalog_norm(string $code): string
{
    if (function_exists('besoiu_normalize_product_code')) {
        return besoiu_normalize_product_code($code);
    }
    $code = strtoupper(trim($code));
    $code = str_replace([' ', '-', '.', '/', '_'], '', $code);

    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
}

function imagine_catalog_brand(string $brand): string
{
    $brand = strtoupper(trim($brand));
    $brand = str_replace([' ', '-', '.', '/', '_', ':'], '', $brand);

    return preg_replace('/[^A-Z0-9]/', '', $brand) ?? '';
}

function imagine_catalog_pdo(string $dbName): ?PDO
{
    imagine_catalog_load_env();
    $host = trim((string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1'));
    $user = trim((string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root'));
    $pass = (string) ($_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
    $dbName = trim($dbName);
    if ($dbName === '') {
        return null;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $pdo->query('SELECT 1 FROM images LIMIT 1');

        return $pdo;
    } catch (Throwable) {
        return null;
    }
}

/** @param list<string> $candidates */
function imagine_catalog_first_file(array $candidates): string
{
    foreach ($candidates as $path) {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        if (is_file($path) && filesize($path) >= 512) {
            return $path;
        }
    }

    return '';
}

function imagine_catalog_publish(string $absPath, string $storageDir, string $publicPrefix, string $destName): string
{
    if ($absPath === '' || !is_file($absPath) || filesize($absPath) < 512) {
        return '';
    }
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }
    $base = pathinfo($destName, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base) ?: 'img';
    $dest = $storageDir . '/' . $base . '.' . $ext;
    if (!is_file($dest) || filesize($dest) < 512) {
        if (!@link($absPath, $dest) && !@copy($absPath, $dest)) {
            return '';
        }
    }

    return rtrim($publicPrefix, '/') . '/' . rawurlencode($base . '.' . $ext);
}
