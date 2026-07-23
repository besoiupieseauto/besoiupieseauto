<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/public-api-init.php';
require_once BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\EventIngestService;
use Besoiu\Services\AiIntelligence\EventQueueService;
use Besoiu\Services\AiIntelligence\EventTrackingStore;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Besoiu-Tracking: ai-intel-v1');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Doar POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Payload invalid.');
    }

    $store = new EventTrackingStore(null, BESOIU_ROOT);
    $ingest = new EventIngestService($store, new EventQueueService(), BESOIU_ROOT);
    $result = $ingest->ingest($payload);

    echo json_encode([
        'success' => true,
        'queued' => (int) ($result['queued'] ?? 0),
        'saved' => (int) ($result['queued'] ?? 0),
        'session_id' => (string) ($result['session_id'] ?? ''),
        'transport' => (string) ($result['transport'] ?? ''),
        'deprecated' => 'Folosește POST /api/events — acest endpoint redirecționează spre coada ai-intel.',
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'rate_limit') {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Prea multe evenimente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server evenimente.',
    ], JSON_UNESCAPED_UNICODE);
}
