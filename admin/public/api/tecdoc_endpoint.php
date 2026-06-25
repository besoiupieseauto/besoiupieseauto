<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';
require_once dirname(__DIR__, 3) . '/system/tecdoc_stock.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi();
ApiBootstrap::sendCorsHeaders('GET, OPTIONS', 'Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = (string) ($_GET['action'] ?? 'status');

try {
    switch ($action) {
        case 'status':
            ApiBootstrap::json([
                'success' => true,
                'cache' => tecdoc_db_cache_stats(),
                'api_unavailable' => tecdoc_api_is_unavailable(),
            ]);

        case 'test_cache':
            $sampleUrl = 'https://' . BESOiu_TECDOC_HOST . '/articles/article-id/123/lang-id/21';
            $testKey = 'boon_test_' . date('YmdHis');
            $payload = json_encode([
                'test' => true,
                'key' => $testKey,
                'items' => [],
            ], JSON_UNESCAPED_UNICODE);

            $written = tecdoc_db_cache_set($sampleUrl, $payload, 3600);
            $readBack = tecdoc_db_cache_get($sampleUrl, 3600);
            $decoded = is_string($readBack) ? json_decode($readBack, true) : null;

            ApiBootstrap::json([
                'success' => $written && is_array($decoded) && ($decoded['key'] ?? '') === $testKey,
                'written' => $written,
                'read_ok' => is_array($decoded) && ($decoded['key'] ?? '') === $testKey,
                'cache' => tecdoc_db_cache_stats(),
            ]);

        case 'probe':
            $manuId = (int) ($_GET['manuId'] ?? 16);
            $url = 'https://' . BESOiu_TECDOC_HOST
                . '/models/list/type-id/1/manufacturer-id/' . $manuId . '/lang-id/21/country-filter-id/63';
            $before = tecdoc_db_cache_get($url, 86400);
            $body = tecdoc_cached_response($url, 86400);
            $after = tecdoc_db_cache_get($url, 86400);
            $decoded = json_decode($body, true);

            ApiBootstrap::json([
                'success' => is_array($decoded) && !tecdoc_cache_body_is_error($body),
                'source' => $before !== null ? 'db_before' : ($after !== null ? 'db_after' : 'api_or_file'),
                'manuId' => $manuId,
                'cache' => tecdoc_db_cache_stats(),
                'http_error' => tecdoc_last_api_error(),
            ]);

        default:
            ApiBootstrap::json([
                'success' => false,
                'message' => 'Acțiune necunoscută. Folosește: status, test_cache, probe.',
            ]);
    }
} catch (Throwable $e) {
    error_log('[tecdoc_endpoint] ' . $e->getMessage());
    ApiBootstrap::json([
        'success' => false,
        'message' => 'Eroare server TecDoc cache.',
    ]);
}
