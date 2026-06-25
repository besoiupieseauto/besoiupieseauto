<?php
declare(strict_types=1);

/**
 * Test minimal cache TecDoc în DB.
 * Usage: php admin/scripts/test_tecdoc_cache.php
 */

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$stats = tecdoc_db_cache_stats();
if (!$stats['table_ready']) {
    fwrite(STDERR, "FAIL: tabela tecdoc_api_cache lipsește. Rulează: php admin/migrations/run_040_create_tecdoc_api_cache.php\n");
    exit(1);
}

$sampleUrl = 'https://' . BESOiu_TECDOC_HOST . '/articles/article-id/999/lang-id/21';
$testKey = 'cli_test_' . time();
$payload = json_encode(['test' => true, 'key' => $testKey, 'items' => []], JSON_UNESCAPED_UNICODE);

if (!tecdoc_db_cache_set($sampleUrl, $payload, 3600)) {
    fwrite(STDERR, "FAIL: tecdoc_db_cache_set\n");
    exit(1);
}

$read = tecdoc_db_cache_get($sampleUrl, 3600);
$decoded = is_string($read) ? json_decode($read, true) : null;
if (!is_array($decoded) || ($decoded['key'] ?? '') !== $testKey) {
    fwrite(STDERR, "FAIL: tecdoc_db_cache_get — payload invalid\n");
    exit(1);
}

echo "OK: cache DB functional\n";
echo json_encode(tecdoc_db_cache_stats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(0);
