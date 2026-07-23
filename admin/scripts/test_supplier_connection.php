<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->boot();

try {
    \Besoiu\Core\Bootstrap\ApiBootstrap::bootJsonApi();
    $all = \Besoiu\Modules\SupplierSearch\Service\SupplierConnectionRegistry::all();
    echo json_encode(['ok' => true, 'suppliers' => $all], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
