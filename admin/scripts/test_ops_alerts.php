<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';

try {
    \Besoiu\Core\Bootstrap\ApiBootstrap::bootJsonApi();
    $summary = (new Besoiu\Services\AdminOpsAlertsService())->summary();
    echo json_encode(['ok' => true, 'data' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
