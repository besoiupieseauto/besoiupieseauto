<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/public-api-init.php';
require_once BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\IntelligenceSearchService;
use InvalidArgumentException;
use Throwable;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Doar GET/POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $payload = [];
    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Payload invalid.');
        }
    }

    $query = trim((string) ($payload['query'] ?? $_GET['query'] ?? ''));
    if ($query === '') {
        throw new InvalidArgumentException('Parametrul query este obligatoriu.');
    }

    $limit = (int) ($payload['limit'] ?? $_GET['limit'] ?? 20);
    $popularityWeight = (float) ($payload['popularity_weight'] ?? $_GET['popularity_weight'] ?? 0.15);
    $semanticWeight = isset($payload['semantic_weight']) || isset($_GET['semantic_weight'])
        ? (float) ($payload['semantic_weight'] ?? $_GET['semantic_weight'] ?? 0.45)
        : 0.45;
    $keywordWeight = isset($payload['keyword_weight']) || isset($_GET['keyword_weight'])
        ? (float) ($payload['keyword_weight'] ?? $_GET['keyword_weight'] ?? 0.55)
        : 0.55;

    $service = IntelligenceSearchService::create(BESOIU_ROOT);
    $result = $service->search($query, $limit, [
        'popularity_weight' => $popularityWeight,
        'semantic_weight' => $semanticWeight,
        'keyword_weight' => $keywordWeight,
        'signal_days' => (int) ($payload['signal_days'] ?? $_GET['signal_days'] ?? 7),
    ]);

    echo json_encode([
        'success' => !empty($result['ok']),
        'query' => (string) ($result['query'] ?? $query),
        'hits' => is_array($result['hits'] ?? null) ? $result['hits'] : [],
        'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eroare search intelligence.',
    ], JSON_UNESCAPED_UNICODE);
}
