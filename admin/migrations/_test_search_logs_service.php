<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Evasystem\Controllers\SearchLogs\SearchLogsService;

$service = new SearchLogsService();
$result = $service->list(['limit' => 5, 'not_found_only' => false]);

echo json_encode([
    'count' => count($result['items']),
    'total' => $result['total'],
    'stats' => $result['stats'],
    'top_missing_count' => count($result['top_missing']),
    'sample' => array_slice($result['items'], 0, 2),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
