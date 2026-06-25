<?php

declare(strict_types=1);

/**
 * Deconectare admin — fișier fizic (bypass router) pentru fiabilitate maximă.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Evasystem\Core\Auth\SessionAuth;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

SessionAuth::logout();
SessionAuth::redirectToLogin();
