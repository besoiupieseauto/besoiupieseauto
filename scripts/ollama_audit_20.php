<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/admin/bootstrap.php';
require_once $root . '/admin/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'Besoiu\\')) {
        return;
    }
    $path = $root . '/app/Backend/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once $root . '/app/Backend/src/Services/AiOllamaAuditService.php';

$report = (new Besoiu\Services\AiOllamaAuditService($root))->run();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($report['summary']['fail'] ?? 0) > 0 ? 1 : 0);
