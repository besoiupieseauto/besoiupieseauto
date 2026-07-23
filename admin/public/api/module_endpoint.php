<?php
declare(strict_types=1);

/**
 * Proxy API pentru module — servește admin/modules/{Folder}/api/{script}.php
 *
 * URL:
 *   /admin/api/module_endpoint.php?module=calculator&script=calculator_endpoint.php
 *   /admin/api/module/calculator/calculator_endpoint.php  (rewrite)
 */

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Module\ModuleApiDispatcher;

ApiBootstrap::bootJsonApi(true);

try {
    ApiBootstrap::requireAuthenticatedSession();

    $moduleId = strtolower(trim((string) ($_GET['module'] ?? '')));
    $script = basename(trim((string) ($_GET['script'] ?? '')));

    if ($moduleId === '' || $script === '') {
        ApiBootstrap::json(['success' => false, 'message' => 'Lipsesc module / script.'], 400);
    }

    ModuleApiDispatcher::dispatch($moduleId, $script);
} catch (Throwable $e) {
    ApiBootstrap::json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}

