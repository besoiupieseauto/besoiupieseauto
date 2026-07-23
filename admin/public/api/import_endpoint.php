<?php
declare(strict_types=1);

/**
 * API import produse — upload CSV, preview, staging în coadă.
 * URL: /admin/api/import_endpoint.php
 */

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Services\Import\ImportLibLoader;
use Throwable;

ApiBootstrap::bootJsonApi(true);
ApiBootstrap::registerJsonFatalGuard('import_endpoint');

try {
    if (!ModuleGate::enabled('import')) {
        ApiBootstrap::json([
            'success' => false,
            'message' => 'Modulul import este dezactivat.',
        ], 403);
    }

    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::beginBoundedJsonWork(120);

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    ImportHooks::ensureHooks();
    ImportLibLoader::bootForHttp();
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('import_endpoint', $e);
}
