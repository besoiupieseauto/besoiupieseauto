<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Backup\BackupService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
$config = ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new BackupService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $download = trim((string) ($_GET['download'] ?? ''));
        if ($download !== '') {
            $path = $service->resolveBackupPath($download);
            if ($path === null) {
                ApiBootstrap::json(['success' => false, 'message' => 'Fișier inexistent.'], 404);
            }

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        }

        ApiBootstrap::json([
            'success' => true,
            'message' => 'Backup list încărcat.',
            'stats' => $service->stats(),
            'data' => $service->listBackups(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['type_product'] ?? $payload['action'] ?? 'list');

    switch ($action) {
        case 'list':
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Backup list încărcat.',
                'stats' => $service->stats(),
                'data' => $service->listBackups(),
            ]);

        case 'run':
            $result = $service->runBackup($config);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Backup creat: ' . ($result['filename'] ?? ''),
                'data' => $result,
                'stats' => $service->stats(),
            ]);

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
    }
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('backup_endpoint', $exception);
}
