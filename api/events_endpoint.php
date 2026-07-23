<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/public-api-init.php';
require_once BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\EventIngestService;
use Besoiu\Services\AiIntelligence\EventQueueService;
use Besoiu\Services\AiIntelligence\EventTrackingStore;
use Besoiu\Services\AiIntelligence\EventWriterService;
use Besoiu\Services\AiIntelligence\VisitorContextResolver;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

    if (empty($payload['user_id']) && function_exists('shop_auth_session_user')) {
        $shopUser = shop_auth_session_user();
        if (is_array($shopUser) && !empty($shopUser['id'])) {
            $payload['user_id'] = (string) $shopUser['id'];
        }
    }

    $sessionId = $ingest->sanitizeSessionId((string) ($payload['session_id'] ?? ''));
    if ($sessionId === '') {
        $sessionId = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
        $payload['session_id'] = $sessionId;
    }

    $clientHints = is_array($payload['client_context'] ?? null) ? $payload['client_context'] : [];
    $visitor = VisitorContextResolver::fromRequest($_SERVER, $clientHints);

    $landing = null;
    foreach ($payload['events'] ?? [] as $ev) {
        if (!is_array($ev) || ($ev['event_type'] ?? '') !== 'page_view') {
            continue;
        }
        $meta = is_array($ev['metadata'] ?? null) ? $ev['metadata'] : [];
        $landing = (string) ($meta['path'] ?? $meta['url'] ?? '');
        if ($landing !== '') {
            break;
        }
    }

    $writer = new EventWriterService($store, null, BESOIU_ROOT);
    $writer->upsertVisitorContext(
        $sessionId,
        isset($payload['user_id']) && $payload['user_id'] !== '' ? (string) $payload['user_id'] : null,
        (string) ($payload['source'] ?? 'web'),
        $visitor,
        $landing !== '' ? $landing : null,
    );

    $result = $ingest->ingest($payload, $visitor);

    echo json_encode([
        'success' => true,
        'queued' => (int) ($result['queued'] ?? 0),
        'session_id' => (string) ($result['session_id'] ?? ''),
        'transport' => (string) ($result['transport'] ?? ''),
        'visitor' => [
            'ip' => $visitor['ip_address'] ?? null,
            'country' => $visitor['country_name'] ?? null,
            'city' => $visitor['city'] ?? null,
            'geo_provider' => 'ip-api.com',
        ],
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
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server evenimente.',
    ], JSON_UNESCAPED_UNICODE);
}
