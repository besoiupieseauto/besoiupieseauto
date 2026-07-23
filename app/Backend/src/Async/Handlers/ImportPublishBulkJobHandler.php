<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;
use Config\Database;

/**
 * Publică în magazin produse pending din import_produse — chunk 200, fără fetchAll total.
 */
final class ImportPublishBulkJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        $pdo = Database::getDB();
        $this->bootImportLibs();

        $publishMode = import_resolve_publish_mode((string) ($payload['publish_mode'] ?? 'skip'));
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $chunkSize = max(1, min(500, (int) ($payload['chunk_size'] ?? 200)));
        $lastId = max(0, (int) ($payload['last_id'] ?? 0));

        $stats = [
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'forced' => 0,
            'tecdoc' => 0,
            'conflicts' => [],
        ];
        $blockedCritical = 0;
        $processed = 0;

        $countSql = "SELECT COUNT(*) FROM import_produse WHERE status='pending'";
        $countParams = [];
        if ($supplier !== '') {
            $countSql .= ' AND pSupplier=?';
            $countParams[] = $supplier;
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalPending = (int) $countStmt->fetchColumn();

        if ($totalPending <= 0) {
            return [
                'message' => 'Nu există produse pending de publicat.',
                'added' => 0,
                'updated' => 0,
                'skipped' => 0,
                'redirect' => '/admin/importreview?status=pending',
            ];
        }

        while (true) {
            $sql = "SELECT * FROM import_produse WHERE status='pending'";
            $params = [];
            if ($supplier !== '') {
                $sql .= ' AND pSupplier=?';
                $params[] = $supplier;
            }
            if ($lastId > 0) {
                $sql .= ' AND id < ?';
                $params[] = $lastId;
            }
            $sql .= ' ORDER BY id DESC LIMIT ' . $chunkSize;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $chunk = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if ($chunk === []) {
                break;
            }

            $filtered = besoiu_import_filter_auto_publishable_rows($chunk);
            $blockedCritical += (int) ($filtered['blocked'] ?? 0);
            $chunkStats = import_process_publish_rows($pdo, $filtered['publishable'], $publishMode);
            foreach (['added', 'updated', 'skipped', 'forced', 'tecdoc'] as $key) {
                $stats[$key] = (int) ($stats[$key] ?? 0) + (int) ($chunkStats[$key] ?? 0);
            }
            if (!empty($chunkStats['conflicts']) && is_array($chunkStats['conflicts'])) {
                $stats['conflicts'] = array_merge($stats['conflicts'], $chunkStats['conflicts']);
            }

            $processed += count($chunk);
            $minId = min(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $chunk));
            $lastId = $minId;

            $pct = $totalPending > 0
                ? (int) min(99, round(($processed / $totalPending) * 100))
                : 100;
            $reportProgress(
                $pct,
                'Publicat ' . $processed . ' / ~' . $totalPending
                    . ' (+' . (int) ($chunkStats['added'] ?? 0)
                    . ' adăugate, +' . (int) ($chunkStats['updated'] ?? 0) . ' actualizate)'
            );

            if (count($chunk) < $chunkSize) {
                break;
            }
        }

        $reportProgress(100, 'Publicare finalizată.');

        $message = import_build_publish_message($stats);
        if ($blockedCritical > 0) {
            $message .= ' ' . $blockedCritical . ' produs'
                . ($blockedCritical === 1 ? '' : 'e')
                . ' sărit(e) — date critice lipsă (auto-publish blocat).';
        }

        $hasPendingConflicts = ($stats['skipped'] ?? 0) > 0;

        return [
            'message' => $message,
            'publish_mode' => $publishMode,
            'added' => (int) ($stats['added'] ?? 0),
            'updated' => (int) ($stats['updated'] ?? 0),
            'skipped' => (int) ($stats['skipped'] ?? 0),
            'forced' => (int) ($stats['forced'] ?? 0),
            'tecdoc' => (int) ($stats['tecdoc'] ?? 0),
            'blocked_critical' => $blockedCritical,
            'conflicts' => $stats['conflicts'] ?? [],
            'redirect' => $hasPendingConflicts
                ? '/admin/importreview?status=conflict_live'
                : '/admin/product',
        ];
    }

    private function bootImportLibs(): void
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
        $criticalLib = dirname(__DIR__, 4) . '/system/import-queue-critical.php';
        if (is_file($criticalLib)) {
            require_once $criticalLib;
        }
    }
}
