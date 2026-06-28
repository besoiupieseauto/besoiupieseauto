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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        if ($expected === '' || $token === null || trim($token) === '') {
            return false;
        }

        return hash_equals($expected, trim($token));
    }

    public static function rotate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function metaTag(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
