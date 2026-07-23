<?php

declare(strict_types=1);

/**
 * Deconectare admin — fișier fizic (bypass router) pentru fiabilitate maximă.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Besoiu\Core\Auth\SessionAuth;

$bridge = BESOIU_LEGACY . '/session-bridge.php';
if (is_file($bridge)) {
    require_once $bridge;
    besoiu_admin_session_start();
}

SessionAuth::logout();
SessionAuth::redirectToLogin();
