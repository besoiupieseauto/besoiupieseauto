<?php

declare(strict_types=1);

namespace Evasystem\Core\Auth;

use Evasystem\Core\AdminUrl;

/**
 * Sesiune admin — login/logout partajat între login, logout și API.
 */
final class SessionAuth
{
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset(
            $_SESSION['user_id'],
            $_SESSION['role'],
            $_SESSION['user_login'],
            $_SESSION['user_name'],
            $_SESSION['users'],
            $_SESSION['oauth2state'],
            $_SESSION['admin_workspace'],
            $_SESSION['admin_permissions'],
            $_SESSION['admin_permissions_delegated']
        );
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        $expireOpts = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('UserAcces', '', $expireOpts);
        setcookie('token', '', $expireOpts);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        session_destroy();
    }

    public static function redirectToLogin(): never
    {
        if (!headers_sent()) {
            header('Location: ' . AdminUrl::path('login'), true, 302);
            exit;
        }
        echo '<script>location.href="' . htmlspecialchars(AdminUrl::path('login'), ENT_QUOTES, 'UTF-8') . '";</script>';
        exit;
    }
}
