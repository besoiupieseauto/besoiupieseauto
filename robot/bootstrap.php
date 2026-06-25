<?php
/**
 * robot/bootstrap.php
 *
 * Incarca .env-ul subaplicatiei "robot" si seteaza variabilele in $_ENV / getenv().
 * Fiecare script PHP din folderul `robot/` include acest fisier si apoi citeste
 * cheile cu env('NUME', 'fallback').
 *
 * Nu modifica logica scripturilor portate din aibotpiese.online — doar le hraneste
 * cu secretele din .env in loc de hardcoded.
 */

declare(strict_types=1);

if (!defined('ROBOT_BOOTSTRAP_LOADED')) {
    define('ROBOT_BOOTSTRAP_LOADED', true);

    $envPath = __DIR__ . '/.env';
    if (is_file($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $trim = ltrim($line);
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
                // strip wrapping quotes if any
                if (strlen($val) >= 2) {
                    $first = $val[0];
                    $last  = $val[strlen($val) - 1];
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
    }

    if (!function_exists('env')) {
        /**
         * Citeste o variabila din .env / $_ENV / getenv() cu fallback.
         */
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
        $previewGate = dirname(__DIR__) . '/system/preview-gate.php';
        if (is_file($previewGate)) {
            require_once dirname(__DIR__) . '/system/shop-auth.php';
            require_once $previewGate;
            shop_auth_load_env();
            shop_auth_session_start();

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

            besoiu_preview_gate_enforce($format);
        }
    }
}
