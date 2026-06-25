<?php

declare(strict_types=1);

/**
 * API export catalog (tm_105).
 * URL: /admin/api/export_action_endpoint.php
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

require ApiBootstrap::projectRoot() . '/src/Controllers/Export/exportproduse_action.php';
