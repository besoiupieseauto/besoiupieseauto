<?php

declare(strict_types=1);

/**
 * Mod preview storefront — site vizibil doar cu cheie (?key=...) sau cookie valid.
 * Activare: SITE_PREVIEW_MODE=1 + SITE_PREVIEW_KEY în admin/.env
 */

if (!function_exists('besoiu_preview_load_env')) {
    function besoiu_preview_load_env(): void
    {
        if (function_exists('shop_auth_load_env')) {
            shop_auth_load_env();
        }
    }
}

if (!function_exists('besoiu_preview_env_bool')) {
    function besoiu_preview_env_bool(string $name, bool $default = false): bool
    {
        besoiu_preview_load_env();
        $raw = $_ENV[$name] ?? getenv($name);
        if ($raw === false || $raw === null || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOL);
    }
}

if (!function_exists('besoiu_preview_env_string')) {
    function besoiu_preview_env_string(string $name, string $default = ''): string
    {
        besoiu_preview_load_env();
        $raw = $_ENV[$name] ?? getenv($name);

        return is_string($raw) && $raw !== '' ? trim($raw) : $default;
    }
}

if (!function_exists('besoiu_preview_is_local_host')) {
    function besoiu_preview_is_local_host(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }
}

if (!function_exists('besoiu_preview_mode_enabled')) {
    function besoiu_preview_mode_enabled(): bool
    {
        if (!besoiu_preview_env_bool('SITE_PREVIEW_MODE', false)) {
            return false;
        }

        if (besoiu_preview_is_local_host() && !besoiu_preview_env_bool('SITE_PREVIEW_FORCE', false)) {
            return false;
        }

        return besoiu_preview_env_string('SITE_PREVIEW_KEY') !== '';
    }
}

if (!function_exists('besoiu_preview_cookie_name')) {
    function besoiu_preview_cookie_name(): string
    {
        return 'bpa_preview';
    }
}

if (!function_exists('besoiu_preview_expected_token')) {
    function besoiu_preview_expected_token(): string
    {
        $key = besoiu_preview_env_string('SITE_PREVIEW_KEY');
        $salt = besoiu_preview_env_string('APP_KEY', 'besoiu_preview_v1');

        return hash_hmac('sha256', $key, $salt);
    }
}

if (!function_exists('besoiu_preview_set_access_cookie')) {
    function besoiu_preview_set_access_cookie(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $days = max(1, (int) besoiu_preview_env_string('SITE_PREVIEW_COOKIE_DAYS', '30'));
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        setcookie(besoiu_preview_cookie_name(), besoiu_preview_expected_token(), [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[besoiu_preview_cookie_name()] = besoiu_preview_expected_token();
    }
}

if (!function_exists('besoiu_preview_grant_access')) {
    function besoiu_preview_grant_access(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['bpa_preview_ok'] = true;
        }

        besoiu_preview_set_access_cookie();
    }
}

if (!function_exists('besoiu_preview_key_is_valid')) {
    function besoiu_preview_key_is_valid(?string $candidate): bool
    {
        $expected = besoiu_preview_env_string('SITE_PREVIEW_KEY');
        if ($expected === '' || $candidate === null) {
            return false;
        }

        return hash_equals($expected, trim($candidate));
    }
}

if (!function_exists('besoiu_preview_has_valid_cookie')) {
    function besoiu_preview_has_valid_cookie(): bool
    {
        $cookie = (string) ($_COOKIE[besoiu_preview_cookie_name()] ?? '');

        return $cookie !== '' && hash_equals(besoiu_preview_expected_token(), $cookie);
    }
}

if (!function_exists('besoiu_preview_has_valid_session')) {
    function besoiu_preview_has_valid_session(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE
            && !empty($_SESSION['bpa_preview_ok']);
    }
}

if (!function_exists('besoiu_preview_try_unlock_from_request')) {
    function besoiu_preview_try_unlock_from_request(): bool
    {
        $key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
        if ($key === '' || !besoiu_preview_key_is_valid($key)) {
            return false;
        }

        besoiu_preview_grant_access();

        return true;
    }
}

if (!function_exists('besoiu_preview_is_allowed')) {
    function besoiu_preview_is_allowed(): bool
    {
        if (!besoiu_preview_mode_enabled()) {
            return true;
        }

        if (defined('BPA_CMS_EDIT_ACTIVE') && BPA_CMS_EDIT_ACTIVE) {
            return true;
        }

        if (besoiu_preview_try_unlock_from_request()) {
            return true;
        }

        if (besoiu_preview_has_valid_cookie() || besoiu_preview_has_valid_session()) {
            return true;
        }

        return false;
    }
}

if (!function_exists('besoiu_preview_lock_all_enabled')) {
    function besoiu_preview_lock_all_enabled(): bool
    {
        return besoiu_preview_mode_enabled()
            && besoiu_preview_env_bool('SITE_PREVIEW_LOCK_ALL', false);
    }
}

if (!function_exists('besoiu_preview_should_skip_request')) {
    function besoiu_preview_should_skip_request(): bool
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = $uri !== '' ? $uri : '/';

        if (!besoiu_preview_lock_all_enabled() && str_starts_with($uri, '/admin')) {
            return true;
        }

        if (besoiu_preview_lock_all_enabled()) {
            if (preg_match('#^/admin/(assets|public/assets)/#', $uri) === 1) {
                return true;
            }
        }

        if (preg_match('#\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|map|txt|xml)(\?.*)?$#i', $uri) === 1) {
            return true;
        }

        $skipPaths = [
            '/robot/webhook.php',
            '/robot/save-lead.php',
            '/admin/public/api/dashboard_snapshot_cron.php',
            '/admin/api/dashboard_snapshot_cron.php',
            '/admin/public/api/supplier_sync_endpoint.php',
            '/admin/api/supplier_sync_endpoint.php',
        ];
        foreach ($skipPaths as $path) {
            if (str_starts_with($uri, $path) || $uri === $path) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('besoiu_preview_render_coming_soon')) {
    function besoiu_preview_render_coming_soon(): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 3600');
        header('X-Robots-Tag: noindex, nofollow');

        $brand = 'Besoiu Piese Auto';
        $assetJs = '/assets/js/preview-gate.js?v=2';
        $urlKey = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
        $invalidPreviewKey = $urlKey !== '' && !besoiu_preview_key_is_valid($urlKey);
        $bodyAttrs = 'data-preview-page="1"';
        if ($invalidPreviewKey) {
            $bodyAttrs .= ' data-preview-invalid-key="1"';
        }

        echo '<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . ' — În curând</title>
<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:Segoe UI,system-ui,sans-serif;background:linear-gradient(160deg,#0f766e 0%,#134e4a 45%,#111827 100%);color:#f8fafc}
.card{max-width:520px;width:100%;background:rgba(255,255,255,.08);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:40px 32px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.35)}
.logo{font-size:1.35rem;font-weight:700;letter-spacing:.02em;color:#5eead4;margin-bottom:8px}
h1{margin:0 0 12px;font-size:1.75rem;line-height:1.25}
p{margin:0 0 10px;color:#cbd5e1;line-height:1.6}
.badge{display:inline-block;margin-top:18px;padding:8px 14px;border-radius:999px;background:rgba(26,188,156,.2);color:#99f6e4;font-size:.85rem;font-weight:600}
</style>
</head>
<body ' . $bodyAttrs . '>
<div class="card">
<div class="logo">' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</div>
<h1>Site în lucru</h1>
<p>Lucrăm la lansarea magazinului online. Revenim în curând cu piese auto verificate și livrare rapidă.</p>
<p>Mulțumim pentru răbdare!</p>
<span class="badge">În curând ne lansăm</span>
</div>
<script src="' . htmlspecialchars($assetJs, ENT_QUOTES, 'UTF-8') . '" defer></script>
</body>
</html>';
        exit;
    }
}

if (!function_exists('besoiu_preview_gate_enforce')) {
    /**
     * @param 'html'|'json' $format
     */
    function besoiu_preview_gate_enforce(string $format = 'html'): void
    {
        if (PHP_SAPI === 'cli' || besoiu_preview_should_skip_request()) {
            return;
        }

        // Laragon local (.test / .local): nu bloca storefront/admin — BOON smoke + dev fără cheie preview.
        if (besoiu_preview_is_local_host()) {
            return;
        }

        if (besoiu_preview_is_allowed()) {
            return;
        }

        if ($format === 'json') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow');
            echo json_encode([
                'success' => false,
                'error' => 'preview_locked',
                'message' => 'Magazinul nu este încă public.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        besoiu_preview_render_coming_soon();
    }
}

if (!function_exists('besoiu_preview_head_script')) {
    function besoiu_preview_head_script(): string
    {
        if (!besoiu_preview_mode_enabled()) {
            return '';
        }

        $src = '/assets/js/preview-gate.js?v=2';

        return '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }
}
