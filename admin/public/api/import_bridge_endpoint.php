<?php
declare(strict_types=1);

/**
 * Proxy API → besoiupieseimport (fetch product, index, scrape).
 */

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Modules\Import\BesoiupieseimportBridge;
use Throwable;

ApiBootstrap::bootJsonApi(true);
ApiBootstrap::registerJsonFatalGuard('import_bridge_endpoint');

try {
    if (!ModuleGate::enabled('import')) {
        ApiBootstrap::json(['success' => false, 'message' => 'Modulul import este dezactivat.'], 403);
    }

    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::beginBoundedJsonWork(90);

    $action = strtolower(trim((string) ($_GET['action'] ?? $_POST['action'] ?? '')));

    if ($action === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $action = 'status';
    }

    if (!BesoiupieseimportBridge::isAvailable()) {
        ApiBootstrap::json([
            'success' => false,
            'error' => 'Proiectul besoiupieseimport nu este disponibil la: ' . BesoiupieseimportBridge::importRoot(),
            'status' => BesoiupieseimportBridge::status(),
        ], 503);
    }

    switch ($action) {
        case 'status':
            ApiBootstrap::json([
                'success' => true,
                'status' => BesoiupieseimportBridge::status(),
            ]);
            break;

        case 'index_status':
            BesoiupieseimportBridge::runScript('fetch product/api/index-status.php');
            break;

        case 'list_files':
            BesoiupieseimportBridge::runScript('fetch product/api/list-matc-files.php');
            break;

        case 'build_cards':
            BesoiupieseimportBridge::runScript('fetch product/api/build-cards.php');
            break;

        case 'scrape_image':
            BesoiupieseimportBridge::runScript('fetch product/api/scrape-image.php');
            break;

        case 'product_cards':
            BesoiupieseimportBridge::runScript('Prelucrare fisiere bovsoft-base/api/product-cards.php');
            break;

        default:
            ApiBootstrap::json([
                'success' => false,
                'error' => 'Acțiune necunoscută: ' . $action,
                'actions' => ['status', 'index_status', 'list_files', 'build_cards', 'scrape_image', 'product_cards'],
            ], 400);
    }
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('import_bridge_endpoint', $e);
}
