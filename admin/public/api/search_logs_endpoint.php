<?php
declare(strict_types=1);

/**
 * Stub Search Logs — modulul searchlogs nu e portat; evită 404 pe dashboard.
 */
require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_GET ?: [];
    }
    $action = trim((string) ($payload['type_product'] ?? $payload['action'] ?? 'list'));

    ApiBootstrap::json([
        'success' => true,
        'message' => 'Jurnal căutări indisponibil (modul neportat).',
        'data' => [
            'items' => [],
            'total' => 0,
            'action' => $action,
            'unavailable' => true,
        ],
    ]);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('search_logs_endpoint', $e);
}
