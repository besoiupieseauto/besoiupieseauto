<?php

declare(strict_types=1);

/**
 * Izolare sesiuni admin (PHPSESSID) vs magazin (bpa_shop).
 * Evită coruperea login-ului admin când rulează shop-auth, CSRF coș sau widget chat.
 */

if (!function_exists('besoiu_session_is_secure_cookie')) {
    function besoiu_session_is_secure_cookie(): bool
    {
        return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}

if (!function_exists('besoiu_admin_session_start')) {
    function besoiu_admin_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'PHPSESSID') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_name('PHPSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => besoiu_session_is_secure_cookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('besoiu_shop_session_start')) {
    function besoiu_shop_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'bpa_shop') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_name('bpa_shop');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => besoiu_session_is_secure_cookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('besoiu_session_peek')) {
    /**
     * Rulează callback-ul în sesiunea țintă, apoi restaurează sesiunea anterioară (dacă exista).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    function besoiu_session_peek(string $targetSessionName, callable $callback): mixed
    {
        $restoreName = session_status() === PHP_SESSION_ACTIVE ? session_name() : '';
        $restoreId = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($targetSessionName === 'bpa_shop') {
            besoiu_shop_session_start();
        } elseif ($targetSessionName === 'PHPSESSID') {
            besoiu_admin_session_start();
        } else {
            session_name($targetSessionName);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => besoiu_session_is_secure_cookie(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        try {
            return $callback();
        } finally {
            session_write_close();

            if ($restoreName !== '' && $restoreId !== '') {
                session_name($restoreName);
                session_id($restoreId);
                session_start();
            }
        }
    }
}

if (!function_exists('besoiu_admin_session_user_id')) {
    function besoiu_admin_session_user_id(): int
    {
        $id = besoiu_session_peek('PHPSESSID', static function (): int {
            return (int) ($_SESSION['user_id'] ?? 0);
        });

        return $id > 0 ? $id : 0;
    }
}

if (!function_exists('besoiu_admin_session_authenticated')) {
    function besoiu_admin_session_authenticated(): bool
    {
        return besoiu_admin_session_user_id() > 0;
    }
}
