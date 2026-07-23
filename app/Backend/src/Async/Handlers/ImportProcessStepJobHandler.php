<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;
use Config\Database;

/**
 * Rulează pașii unui job legacy import (preview / import queue) în worker CLI.
 */
final class ImportProcessStepJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        $pdo = Database::getDB();
        $this->bootImportLibs();

        $legacyJobId = trim((string) ($payload['legacy_job_id'] ?? ''));
        $stepKind = trim((string) ($payload['step_kind'] ?? 'import_queue'));
        if ($legacyJobId === '') {
            throw new \RuntimeException('legacy_job_id lipsă.');
        }

        $idleMs = max(50, (int) ($payload['idle_ms'] ?? 150));
        $lastProgress = -1;

        while (true) {
            $step = $stepKind === 'preview'
                ? import_preview_job_step($legacyJobId)
                : import_queue_job_step($legacyJobId, $pdo);

            if (empty($step['ok'])) {
                throw new \RuntimeException((string) ($step['error'] ?? 'Eroare la pasul job-ului.'));
            }

            if (!empty($step['cancelled'])) {
                throw new \RuntimeException('Proces oprit.');
            }

            $status = is_array($step['status'] ?? null) ? $step['status'] : [];
            $progress = (int) round((float) ($status['progress'] ?? 0));
            $message = (string) ($status['message'] ?? 'Procesare...');
            if ($progress !== $lastProgress || $message !== '') {
                $reportProgress(max(0, min(99, $progress)), $message);
                $lastProgress = $progress;
            }

            if (!empty($status['done'])) {
                $reportProgress(100, (string) ($status['message'] ?? 'Finalizat.'));
                $result = is_array($step['result'] ?? null) ? $step['result'] : [];
                if ($result === [] && is_array($status)) {
                    $result = array_diff_key($status, array_flip(['done', 'failed', 'cancelled', 'progress', 'message', 'phase']));
                }

                return array_merge(
                    ['legacy_job_id' => $legacyJobId, 'step_kind' => $stepKind],
                    $result
                );
            }

            if (!empty($status['failed'])) {
                throw new \RuntimeException((string) ($status['error'] ?? $status['message'] ?? 'Job eșuat.'));
            }

            usleep($idleMs * 1000);
        }
    }

    private function bootImportLibs(): void
    {
        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }

        $jobLib = dirname(__DIR__, 2) . '/Controllers/Produse/import_job_lib.php';
        if (is_file($jobLib)) {
            require_once $jobLib;
        }
    }
}
