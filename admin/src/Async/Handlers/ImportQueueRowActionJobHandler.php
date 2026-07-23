<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;
use Config\Database;

/**
 * Re-procesare / sync TecDoc pentru un rând din coada importreview — rulează în worker CLI (fără timeout Cloudflare).
 */
final class ImportQueueRowActionJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $pdo = Database::getDB();
        $this->bootLibs();

        $action = trim((string) ($payload['action'] ?? ''));
        $id = (int) ($payload['id'] ?? 0);

        if ($id <= 0) {
            throw new \RuntimeException('ID produs invalid.');
        }

        if ($action === 'reprocess_one') {
            $reportProgress(5, 'Pornesc re-procesarea (TecDoc + imagine + descriere)...');
            $result = import_action_reprocess_queue_row($pdo, $id);
        } elseif ($action === 'sync_tecdoc_one') {
            $reportProgress(5, 'Sincronizez date TecDoc...');
            $result = import_action_sync_tecdoc_queue_row($pdo, $id);
        } else {
            throw new \RuntimeException('Actiune necunoscuta: ' . ($action !== '' ? $action : '(gol)'));
        }

        if (empty($result['ok'])) {
            throw new \RuntimeException((string) ($result['message'] ?? 'Operatiune esuata.'));
        }

        $reportProgress(100, (string) ($result['message'] ?? 'Gata.'));

        return [
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Gata.'),
            'row' => $result['row'] ?? null,
            'action' => $action,
            'id' => $id,
        ];
    }

    private function bootLibs(): void
    {
        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }
        if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
            define('IMPORT_ACTION_SKIP_HTTP', true);
        }

        $actionLib = dirname(__DIR__, 2) . '/Controllers/Produse/importproduse_action.php';
        if (is_file($actionLib)) {
            require_once $actionLib;
        }
    }
}
