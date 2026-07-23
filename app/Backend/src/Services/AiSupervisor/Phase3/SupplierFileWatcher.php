<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase3;

use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;

/**
 * Faza 3c — monitor furnizori: fișiere noi, scan, erori import.
 */
final class SupplierFileWatcher
{
    private string $adminStorage;

    public function __construct(
        private ?AiSupervisorStore $store = null,
        ?string $projectRoot = null,
    ) {
        $root = $projectRoot ?? dirname(__DIR__, 5);
        $this->store = $store ?? new AiSupervisorStore();
        $this->adminStorage = $root . '/admin/storage';
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $scanPath = $this->adminStorage . '/supplier_scan_last_run.json';
        $activityPath = $this->adminStorage . '/supplier_scan_activity.json';
        $scan = $this->readJson($scanPath);
        $activity = $this->readJson($activityPath);
        $previous = $this->store->read('supplier_watch');
        $prevFingerprint = (string) ($previous['fingerprint'] ?? '');

        $suppliers = is_array($scan['data']['suppliers'] ?? null) ? $scan['data']['suppliers'] : [];
        $overview = is_array($scan['data']['overview'] ?? null) ? $scan['data']['overview'] : [];

        $filesNew = [];
        $needsAttention = [];
        foreach ($suppliers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['code'] ?? '?');
            if (!empty($row['needs_attention']) || (int) ($row['validation_errors'] ?? 0) > 0) {
                $needsAttention[] = [
                    'supplier' => $name,
                    'errors' => (int) ($row['validation_errors'] ?? 0),
                    'detail' => (string) ($row['last_error'] ?? $row['status_message'] ?? ''),
                ];
            }
            $newFiles = (int) ($row['new_files_count'] ?? $row['files_new'] ?? 0);
            if ($newFiles > 0) {
                $filesNew[] = ['supplier' => $name, 'new_files' => $newFiles];
            }
        }

        $fingerprint = md5(json_encode([
            'message' => (string) ($scan['message'] ?? ''),
            'overview' => $overview,
            'files_new' => $filesNew,
        ], JSON_UNESCAPED_UNICODE) ?: '');

        $changed = $prevFingerprint !== '' && $prevFingerprint !== $fingerprint;
        $events = [];
        if ($changed && $filesNew !== []) {
            foreach ($filesNew as $item) {
                $events[] = [
                    'type' => 'supplier_file_new',
                    'supplier' => $item['supplier'],
                    'count' => $item['new_files'],
                ];
                $this->recordSupplierEvent('supplier_file_new', $item['supplier'], $item);
            }
        }

        $report = [
            'ok' => empty($needsAttention),
            'http' => empty($needsAttention) ? 200 : 207,
            'generated_at' => date('c'),
            'last_scan_message' => (string) ($scan['message'] ?? ''),
            'last_scan_at' => (string) ($scan['data']['finished_at'] ?? $activity['finished_at'] ?? ''),
            'overview' => $overview,
            'suppliers_total' => count($suppliers),
            'files_new' => $filesNew,
            'needs_attention' => $needsAttention,
            'changed_since_last' => $changed,
            'fingerprint' => $fingerprint,
            'new_events' => $events,
            'recommendations' => $this->recommendations($filesNew, $needsAttention, $scan),
        ];

        $this->store->write('supplier_watch', $report);

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->store->read('supplier_watch');
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /** @param list<array<string, mixed>> $filesNew @param list<array<string, mixed>> $needsAttention @param array<string, mixed> $scan @return list<string> */
    private function recommendations(array $filesNew, array $needsAttention, array $scan): array
    {
        $rec = [];
        if ($filesNew !== []) {
            $rec[] = 'Furnizori cu fișiere noi — verifică Import și publică produsele.';
        }
        if ($needsAttention !== []) {
            $rec[] = count($needsAttention) . ' furnizori necesită atenție — deschide /admin/furnizori.';
        }
        if ($scan === []) {
            $rec[] = 'Lipsește raport scan furnizori — rulează sync cron sau scan manual.';
        }
        if ($rec === []) {
            $rec[] = 'Scan furnizori — fără probleme detectate.';
        }

        return $rec;
    }

    /** @param array<string, mixed> $meta */
    private function recordSupplierEvent(string $action, string $subject, array $meta): void
    {
        $helper = dirname(__DIR__, 5) . '/system/ai_action_events.php';
        if (!is_file($helper)) {
            return;
        }
        require_once $helper;
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('cron', $action, $subject, array_merge($meta, ['phase' => 3]));
        }
    }
}
