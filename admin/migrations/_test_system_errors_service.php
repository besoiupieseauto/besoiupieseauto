<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Evasystem\Services\SystemErrorsService;

require_once dirname(__DIR__, 2) . '/system/system_errors.php';

$pdo = (function () {
    require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
    return tecdoc_db();
})();

besoiu_system_error_log('warning', 'queue', 'Test jurnal erori BOON', ['task' => 'smoke']);

$service = new SystemErrorsService();
$result = $service->list(['limit' => 5, 'unresolved_only' => false]);

$count = count($result['items']);
if ($count < 1) {
    fwrite(STDERR, "FAIL: expected at least 1 system_errors row\n");
    exit(1);
}

echo json_encode([
    'ok' => true,
    'count' => $count,
    'total' => $result['total'],
    'stats' => $result['stats'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
