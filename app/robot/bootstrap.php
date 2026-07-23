<?php

declare(strict_types=1);

/**
 * Bootstrap sub-app robot — env + helpers pentru widget chat și catalog.
 */

if (!defined('ROBOT_BOOTSTRAP_LOADED')) {
    define('ROBOT_BOOTSTRAP_LOADED', true);

    $siteRoot = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 2);

    if (!defined('BESOIU_ROOT')) {
        define('BESOIU_ROOT', $siteRoot);
    }

    $envPaths = [
        $siteRoot . '/app/Config/.env',
        $siteRoot . '/admin/.env',
        __DIR__ . '/.env',
    ];

    foreach ($envPaths as $envPath) {
        if (!is_file($envPath)) {
            continue;
        }
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            $trim = ltrim((string) $line);
            if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            if ($key === '') {
                continue;
            }
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $val;
            }
            if (getenv($key) === false) {
                @putenv($key . '=' . $val);
            }
        }
    }

    if (!function_exists('env')) {
        function env(string $key, $default = null)
        {
            if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
                return $_ENV[$key];
            }
            $v = getenv($key);
            if ($v !== false && $v !== '') {
                return $v;
            }

            return $default;
        }
    }

    if (!function_exists('robot_data_dir')) {
        function robot_data_dir(): string
        {
            $p = __DIR__ . '/data';
            if (!is_dir($p)) {
                @mkdir($p, 0775, true);
            }

            return $p;
        }
    }

    if (!function_exists('robot_site_root')) {
        function robot_site_root(): string
        {
            return defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 2);
        }
    }

    if (!function_exists('robot_tecdoc_host')) {
        function robot_tecdoc_host(): string
        {
            return 'auto-parts-catalog.p.rapidapi.com';
        }
    }

    if (!function_exists('robot_tecdoc_lang_id')) {
        function robot_tecdoc_lang_id(): int
        {
            return 21;
        }
    }

    if (!function_exists('robot_tecdoc_url')) {
        function robot_tecdoc_url(string $path): string
        {
            return 'https://' . robot_tecdoc_host() . '/' . ltrim($path, '/');
        }
    }

    if (PHP_SAPI !== 'cli') {
        $previewGate = robot_site_root() . '/system/preview-gate.php';
        if (is_file($previewGate)) {
            $shopAuth = robot_site_root() . '/system/shop-auth.php';
            if (!is_file($shopAuth)) {
                $shopAuth = robot_site_root() . '/app/Legacy/shop-auth.php';
            }
            if (is_file($shopAuth)) {
                require_once $shopAuth;
            }
            require_once $previewGate;
            if (function_exists('shop_auth_load_env')) {
                shop_auth_load_env();
            }
            if (function_exists('shop_auth_session_start')) {
                shop_auth_session_start();
            }

            $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $format = 'html';
            if (
                str_ends_with($uri, '.js.php')
                || str_contains($uri, '_api.php')
                || str_ends_with($uri, 'api.php')
                || str_ends_with($uri, 'tecdoc_proxy.php')
            ) {
                $format = 'json';
            }

            if (function_exists('besoiu_preview_gate_enforce')) {
                besoiu_preview_gate_enforce($format);
            }
        }
    }
}
