<?php
declare(strict_types=1);

/**
 * Proxy API → besoiupieseimport (fetch product, index, scrape).
 *
 * /admin/api/module/import/import_bridge_endpoint.php?action=status
 */

require_once dirname(__DIR__, 3) . '/public/api/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\Import\BesoiupieseimportBridge;

ApiBootstrap::bootJsonApi(true);

try {
    ApiBootstrap::requireAuthenticatedSession();

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

        case 'sync_feeds':
            $lib = dirname(__DIR__, 3) . '/tools/besoiupieseimport_sync_lib.php';
            if (!is_file($lib)) {
                ApiBootstrap::json(['success' => false, 'error' => 'Sync lib lipsă'], 500);
            }
            require_once $lib;
            $dry = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $result = besoiupieseimport_sync_run($dry);
            ApiBootstrap::json(['success' => true, 'result' => $result]);
            break;

        default:
            ApiBootstrap::json([
                'success' => false,
                'error' => 'Acțiune necunoscută: ' . $action,
                'actions' => ['status', 'index_status', 'list_files', 'build_cards', 'scrape_image', 'product_cards', 'sync_feeds'],
            ], 400);
    }
} catch (Throwable $e) {
    ApiBootstrap::json([
        'success' => false,
        'error' => $e->getMessage(),
    ], 500);
}
