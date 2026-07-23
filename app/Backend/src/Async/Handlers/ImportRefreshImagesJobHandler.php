<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;
use Besoiu\Async\JobStore;
use Config\Database;

/**
 * Scanare imagini import review — înlocuiește polling refresh_images_step din browser.
 */
final class ImportRefreshImagesJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        $pdo = Database::getDB();
        $this->bootLibs();

        $filterIds = array_values(array_filter(array_map('intval', (array) ($payload['ids'] ?? []))));
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $force = !empty($payload['force']) || $filterIds !== [];
        $asyncJobId = trim((string) ($payload['_async_job_id'] ?? $payload['async_job_id'] ?? ''));

        $start = import_image_job_start($pdo, $filterIds, $supplier, $force);
        if (empty($start['ok'])) {
            throw new \RuntimeException((string) ($start['message'] ?? 'Nu pot porni scanarea imaginilor.'));
        }

        $legacyJobId = (string) ($start['job_id'] ?? '');
        if ($legacyJobId === '') {
            throw new \RuntimeException('legacy job_id lipsă.');
        }

        if ($asyncJobId !== '') {
            $store = new JobStore();
            $job = $store->get($asyncJobId);
            if ($job !== null) {
                $job['legacy_job_id'] = $legacyJobId;
                $store->save($job);
            }
        }

        $total = (int) ($start['total'] ?? 0);
        $reportProgress(0, "Scanare imagini: 0 / $total");

        $idleMs = max(50, (int) ($payload['idle_ms'] ?? 100));

        while (true) {
            if ($asyncJobId !== '' && (new JobStore())->isCancelRequested($asyncJobId)) {
                import_job_cancel($legacyJobId);
                throw new \RuntimeException('cancelled');
            }

            $step = import_image_job_step($pdo, $legacyJobId);
            if (empty($step['ok'])) {
                throw new \RuntimeException((string) ($step['error'] ?? 'Eroare la pasul scanării.'));
            }

            if (!empty($step['cancelled'])) {
                throw new \RuntimeException('cancelled');
            }

            $status = is_array($step['status'] ?? null) ? $step['status'] : [];
            $progress = (int) round((float) ($status['progress'] ?? 0));
            $message = (string) ($status['message'] ?? 'Scanare imagini...');
            $liveLog = is_array($status['live_log'] ?? null) ? $status['live_log'] : [];
            $reportProgress(max(0, min(99, $progress)), $message);

            if ($asyncJobId !== '' && $liveLog !== []) {
                $store = new JobStore();
                $asyncJob = $store->get($asyncJobId);
                if ($asyncJob !== null) {
                    $asyncJob['live_log'] = $liveLog;
                    $store->save($asyncJob);
                }
            }

            if (!empty($status['done']) || !empty($status['failed'])) {
                $result = is_array($step['result'] ?? null) ? $step['result'] : [];
                $reportProgress(100, (string) ($status['message'] ?? 'Scanare finalizată.'));

                return array_merge($result, [
                    'legacy_job_id' => $legacyJobId,
                    'api_status' => (string) ($result['api_status'] ?? 'ok'),
                ]);
            }

            usleep($idleMs * 1000);
        }
    }

    private function bootLibs(): void
    {
        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }

        $base = dirname(__DIR__, 2) . '/Controllers/Produse';
        foreach (['import_job_lib.php', 'import_image_pipeline_lib.php', 'import_image_job_lib.php', 'importproduse.php'] as $file) {
            $path = $base . '/' . $file;
            if (is_file($path)) {
                require_once $path;
            }
        }

        import_require_system_file('tecdoc_stock.php');
    }
}
