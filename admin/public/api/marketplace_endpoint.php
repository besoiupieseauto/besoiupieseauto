<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Marketplace\Marketplace;
use Evasystem\Controllers\Marketplace\MarketplaceService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Marketplace\MarketplaceModel;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::runJsonEndpoint(static function (): void {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipseste type_product din payload.');
    }

    $controller = new Marketplace(new MarketplaceService(new MarketplaceModel()));

    switch ((string) $payload['type_product']) {
        case 'list':
            $response = ['success' => true, 'message' => 'Marketplace-uri incarcate.', 'data' => $controller->list()];
            break;
        case 'get':
            $response = ['success' => true, 'message' => 'Marketplace incarcat.', 'data' => $controller->find($payload)];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Marketplace adaugat.', 'data' => $controller->add($payload)];
            break;
        case 'edit':
            $response = ['success' => true, 'message' => 'Marketplace actualizat.', 'data' => $controller->update($payload)];
            break;
        case 'setstatus':
        case 'activate':
            $controller->changeStatus($payload);
            $response = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
            break;
        case 'testconnection':
        case 'testbot':
            $response = ['success' => true, 'message' => 'Test conexiune executat.', 'data' => $controller->test($payload)];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Marketplace sters.', 'data' => null];
            break;
        case 'baselinker_config':
            $response = ['success' => true, 'message' => 'Config BaseLinker.', 'data' => $controller->baselinkerConfig($payload)];
            break;
        case 'baselinker_test':
            $response = ['success' => true, 'message' => 'Test BaseLinker executat.', 'data' => $controller->testBaseLinker($payload)];
            break;
        case 'baselinker_inventories':
            $response = ['success' => true, 'message' => 'Inventare BaseLinker.', 'data' => $controller->baselinkerInventories($payload)];
            break;
        case 'baselinker_save_mapping':
            $response = ['success' => true, 'message' => 'Mapare salvata.', 'data' => $controller->baselinkerSaveMapping($payload)];
            break;
        case 'baselinker_save_inventory':
            $response = ['success' => true, 'message' => 'Inventar salvat.', 'data' => $controller->baselinkerSaveInventory($payload)];
            break;
        case 'baselinker_sync_products':
            $response = ['success' => true, 'message' => 'Sincronizare produse executata.', 'data' => $controller->baselinkerSyncProducts($payload)];
            break;
        case 'baselinker_catalog_stats':
            $response = ['success' => true, 'message' => 'Statistici catalog BaseLinker.', 'data' => $controller->baselinkerCatalogStats($payload)];
            break;
        case 'baselinker_enqueue_catalog':
            $response = ['success' => true, 'message' => 'Catalog pus in coada.', 'data' => $controller->baselinkerEnqueueCatalog($payload)];
            break;
        case 'baselinker_feed_info':
            $response = ['success' => true, 'message' => 'Feed BaseLinker.', 'data' => $controller->baselinkerFeedInfo($payload)];
            break;
        case 'baselinker_feed_regenerate':
            $response = ['success' => true, 'message' => 'Regenerare feed executata.', 'data' => $controller->baselinkerFeedRegenerate($payload)];
            break;
        case 'baselinker_store_import_info':
            $response = ['success' => true, 'message' => 'Investigare import din magazin BaseLinker.', 'data' => $controller->baselinkerStoreImportInfo($payload)];
            break;
        default:
            throw new ValidationException('Actiune necunoscuta.');
    }

    ApiBootstrap::json($response);
}, 'marketplace_endpoint');
