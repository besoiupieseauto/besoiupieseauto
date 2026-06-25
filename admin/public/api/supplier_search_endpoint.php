<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Services\SupplierSearch\SupplierConnectionRegistry;
use Evasystem\Services\SupplierSearch\SupplierSearchService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
$config = ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    if (!empty($config['legacy_db_name'])) {
        Database::getInstance(
            $config['legacy_db_host'],
            $config['legacy_db_name'],
            $config['legacy_db_user'],
            $config['legacy_db_pass'],
            'legacy'
        );
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $queryAction = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET' && ($queryAction === 'ping' || $queryAction === 'status')) {
        ApiBootstrap::json([
            'success' => true,
            'message' => 'OK',
            'suppliers' => array_values(SupplierConnectionRegistry::all()),
            'connected' => SupplierConnectionRegistry::connectedKeys(),
        ]);
    }

    if ($method !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = trim((string) ($payload['action'] ?? ''));
    if ($action === 'ping' || $action === 'status') {
        ApiBootstrap::json([
            'success' => true,
            'message' => 'OK',
            'suppliers' => array_values(SupplierConnectionRegistry::all()),
            'connected' => SupplierConnectionRegistry::connectedKeys(),
        ]);
    }

    $query = trim((string) ($payload['query'] ?? ''));
    $suppliers = $payload['suppliers'] ?? ['materom', 'elit', 'autopartner'];
    if (!is_array($suppliers)) {
        $suppliers = array_map('trim', explode(',', (string) $suppliers));
    }

    $debugTimings = !empty($payload['debug_timings']);

    $service = new SupplierSearchService();
    $result = $service->search($query, $suppliers, $debugTimings);

    if (empty($result['success'])) {
        ApiBootstrap::json($result, 400);
    }

    ApiBootstrap::json($result);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('supplier_search_endpoint', $exception);
}
