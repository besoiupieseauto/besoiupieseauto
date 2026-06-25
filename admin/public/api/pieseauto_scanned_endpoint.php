<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\PieseAuto\PieseAutoScannedService;

ApiBootstrap::runJsonEndpoint(static function (): void {
    ApiBootstrap::requireHttpMethod('GET');
    ApiBootstrap::requireAuthenticatedSession();

    $q = trim((string) ($_GET['q'] ?? ''));
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 200)));

    $items = (new PieseAutoScannedService())->scannedItems($q, $limit);

    ApiBootstrap::json([
        'status' => 'ok',
        'count' => count($items),
        'items' => $items,
    ]);
}, 'pieseauto_scanned_endpoint', true);
