<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->boot();

try {
    \Besoiu\Core\Bootstrap\ApiBootstrap::bootJsonApi();

    $controller = new Besoiu\Modules\Furnizori\Controller\FurnizoriController(
        new Besoiu\Modules\Furnizori\Service\FurnizoriService(
            new Besoiu\Modules\Furnizori\Model\FurnizoriRepository(),
            new Besoiu\Modules\Furnizori\Service\FurnizoriStatsService(
                new Besoiu\Modules\Furnizori\Model\FurnizoriRepository()
            )
        )
    );
    $result = $controller->list(['page' => 1, 'per_page' => 10]);
    echo json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
