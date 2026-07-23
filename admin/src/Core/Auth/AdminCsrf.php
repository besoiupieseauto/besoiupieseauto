<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

/**
 * CSRF sesiune pentru acțiuni admin (formulare + API JSON).
 */
final class AdminCsrf
{
    private const SESSION_KEY = 'admin_csrf_token';

    public static function token(): string
    {
        self::ensureAdminSession();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        self::ensureAdminSession();

        $expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        if ($expected === '' || $token === null || trim($token) === '') {
            return false;
        }

        return hash_equals($expected, trim($token));
    }

    public static function rotate(): string
    {
        self::ensureAdminSession();

        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return (string) $_SESSION[self::SESSION_KEY];
    }

    private static function projectRoot(): string
    {
        if (defined('BESOIU_ROOT')) {
            return rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
        }

        return dirname(__DIR__, 5);
    }

    private static function resolveSessionBridge(): ?string
    {
        $root = self::projectRoot();
        foreach ([
            $root . '/system/session-bridge.php',
            $root . '/app/Legacy/session-bridge.php',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function ensureAdminSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'PHPSESSID') {
            return;
        }

        $bridge = self::resolveSessionBridge();
        if ($bridge !== null) {
            require_once $bridge;
            besoiu_admin_session_start();

            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_name('PHPSESSID');
            session_start();
        }
    }

    public static function metaTag(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
