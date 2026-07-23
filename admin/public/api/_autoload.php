<?php
declare(strict_types=1);

/**
 * Bootstrap admin + Composer — obligatoriu înainte de orice clasă Besoiu\ din public/api/.
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$sessionBridge = BESOIU_LEGACY . '/session-bridge.php';
if (is_file($sessionBridge)) {
    require_once $sessionBridge;
    besoiu_admin_session_start();
}

// Autoload PSR-4 pentru Besoiu\Modules\* înainte de handler-ele din modules/.
\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->discover();

\Besoiu\Core\Bootstrap\ApiBootstrap::registerProductionSafeErrors();
\Besoiu\Core\Bootstrap\ApiBootstrap::enforceDefaultApiAuthIfNeeded();
