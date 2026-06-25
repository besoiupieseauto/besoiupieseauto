<?php

declare(strict_types=1);

/**
 * API import produse — bypass router DB (evită 404 la POST /admin/import).
 * URL: /admin/api/import_endpoint.php
 */

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi(true);
ApiBootstrap::requireAuthenticatedSession();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require ApiBootstrap::projectRoot() . '/src/Controllers/Produse/importproduse.php';
