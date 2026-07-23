<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;
use Besoiu\Services\ProductNameNormalizeService;
use Config\Database;

/**
 * Normalizare masivă coadă import (10k+): reguli în chunk-uri + Ollama în fundal (worker CLI).
 */
final class NameNormalizeBulkJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        @set_time_limit(3600);
        @ini_set('max_execution_time', '3600');

        $pdo = Database::getDB();
        $service = ProductNameNormalizeService::create();

        $phase = trim((string) ($payload['phase'] ?? 'rules'));
        if (!in_array($phase, ['rules', 'ollama'], true)) {
            $phase = 'rules';
        }

        $filters = [
            'status' => (string) ($payload['status'] ?? 'pending'),
            'supplier' => (string) ($payload['supplier'] ?? ''),
            'only_gaps' => !array_key_exists('only_gaps', $payload) || !empty($payload['only_gaps']),
        ];

        $cursorId = max(0, (int) ($payload['cursor_id'] ?? 0));
        $timeBudget = max(30.0, min(3300.0, (float) ($payload['time_budget_sec'] ?? 280.0)));
        $deadline = microtime(true) + $timeBudget;

        $stats = $service->bulkStats($pdo, $filters);
        $total = max(1, (int) ($stats['total'] ?? 0));

        $processedTotal = max(0, (int) ($payload['processed_total'] ?? 0));
        $updatedTotal = max(0, (int) ($payload['updated_total'] ?? 0));
        $ollamaTotal = max(0, (int) ($payload['ollama_total'] ?? 0));

        $chunkSize = max(100, min(800, (int) ($payload['chunk_size'] ?? 400)));
        $ollamaMax = max(1, min(25, (int) ($payload['ollama_max'] ?? 12)));
        $saveRules = !empty($payload['save_rules']);

        $done = false;
        $cursorOut = $cursorId;

        if ($phase === 'rules') {
            $reportProgress(1, 'Normalizare reguli — pornire (cursor id > ' . $cursorId . ')…');

            while (microtime(true) < $deadline) {
                $chunk = $service->processRulesBulkChunk($pdo, $filters, $cursorOut, $chunkSize);
                $cursorOut = (int) ($chunk['cursor_id'] ?? $cursorOut);
                $processedTotal += (int) ($chunk['processed'] ?? 0);
                $updatedTotal += (int) ($chunk['updated'] ?? 0);

                $pct = (int) min(99, round(($cursorOut > 0 ? min($processedTotal, $total) : 0) / $total * 100));
                $reportProgress(
                    max(1, $pct),
                    'Reguli: ~' . $processedTotal . ' procesate, ' . $updatedTotal . ' actualizate (id ' . $cursorOut . ')'
                );

                if (!empty($chunk['done'])) {
                    $done = true;
                    break;
                }
            }

            $reportProgress($done ? 100 : 99, $done
                ? ('Reguli finalizate: ' . $updatedTotal . ' actualizate din ~' . $total . '.')
                : ('Pauză reguli — continuă de la id ' . $cursorOut . '.'));

            return [
                'phase' => 'rules',
                'done' => $done,
                'needs_continue' => !$done,
                'cursor_id' => $cursorOut,
                'processed_total' => $processedTotal,
                'updated_total' => $updatedTotal,
                'total' => $total,
                'message' => $done
                    ? ('Reguli aplicate: ' . $updatedTotal . ' produse actualizate.')
                    : ('Batch reguli oprit la id ' . $cursorOut . ' — rulează din nou pentru continuare.'),
            ];
        }

        $reportProgress(1, 'Filtru Ollama — pornire (cursor id > ' . $cursorId . ')…');

        while (microtime(true) < $deadline) {
            $chunk = $service->processOllamaBulkChunk(
                $pdo,
                $filters,
                $cursorOut,
                $ollamaMax,
                $deadline,
                $saveRules
            );
            $cursorOut = (int) ($chunk['cursor_id'] ?? $cursorOut);
            $processedTotal += (int) ($chunk['processed'] ?? 0);
            $updatedTotal += (int) ($chunk['updated'] ?? 0);
            $ollamaTotal += (int) ($chunk['ollama_applied'] ?? 0);

            $pct = (int) min(99, round(min($processedTotal, $total) / $total * 100));
            $reportProgress(
                max(1, $pct),
                'Ollama: ' . $ollamaTotal . ' corectate, cursor id ' . $cursorOut
            );

            if (!empty($chunk['done'])) {
                $done = true;
                break;
            }

            if ((int) ($chunk['ollama_applied'] ?? 0) === 0 && (int) ($chunk['processed'] ?? 0) === 0) {
                $done = true;
                break;
            }
        }

        $reportProgress($done ? 100 : 99, $done
            ? ('Ollama finalizat: ' . $ollamaTotal . ' produse corectate.')
            : ('Pauză Ollama — continuă de la id ' . $cursorOut . '.'));

        return [
            'phase' => 'ollama',
            'done' => $done,
            'needs_continue' => !$done,
            'cursor_id' => $cursorOut,
            'processed_total' => $processedTotal,
            'updated_total' => $updatedTotal,
            'ollama_total' => $ollamaTotal,
            'total' => $total,
            'message' => $done
                ? ('Ollama: ' . $ollamaTotal . ' produse corectate.')
                : ('Batch Ollama oprit la id ' . $cursorOut . ' — rulează din nou pentru continuare.'),
        ];
    }
}
