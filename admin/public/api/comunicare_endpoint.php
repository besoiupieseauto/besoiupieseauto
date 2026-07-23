<?php
declare(strict_types=1);

/**
 * Stub Comunicare — marker DOM / section assistant lookup fără 404.
 * Modulul Comunicare nu e portat; răspunsuri goale sigure.
 */
require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));
    $payload = [];
    if ($method === 'POST') {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        $payload = is_array($decoded) ? $decoded : [];
        if ($action === '') {
            $action = trim((string) ($payload['action'] ?? ''));
        }
    }

    if ($action === '') {
        $action = 'ping';
    }

    ApiBootstrap::json([
        'success' => true,
        'message' => 'Comunicare indisponibil (modul neportat).',
        'action' => $action,
        'data' => [
            'unavailable' => true,
            'items' => [],
            'knowledge' => null,
            'reply' => null,
        ],
    ]);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('comunicare_endpoint', $e);
}
