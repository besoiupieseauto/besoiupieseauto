<?php

declare(strict_types=1);

/**
 * API acțiuni coadă import (importreview).
 * URL: /admin/api/import_action_endpoint.php
 */

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi(true);
ApiBootstrap::requireAuthenticatedSession();
ApiBootstrap::releaseSession();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require ApiBootstrap::projectRoot() . '/src/Controllers/Produse/importproduse_action.php';
