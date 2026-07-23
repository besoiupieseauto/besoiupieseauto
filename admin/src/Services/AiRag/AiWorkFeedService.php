<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\SectionAssistantQueryLogService;
use PDO;
use Throwable;

/**
 * Feed unificat — toate interacțiunile Ollama / AI din ultimele N minute (monitor live AI Work).
 */
final class AiWorkFeedService
{
    private string $root;
    private string $workPath;
    private string $feedbackPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_work';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->workPath = $dir . '/work.jsonl';
        $this->feedbackPath = $dir . '/feedback.jsonl';
    }

    /** @param array<string, mixed> $entry */
    public function append(array $entry): string
    {
        $id = trim((string) ($entry['id'] ?? ''));
        if ($id === '') {
            $id = 'aw_' . bin2hex(random_bytes(8));
        }

        $row = [
            'id' => $id,
            'source_type' => (string) ($entry['source_type'] ?? 'aiwork'),
            'at' => (string) ($entry['at'] ?? date('c')),
            'section' => (string) ($entry['section'] ?? ''),
            'action' => (string) ($entry['action'] ?? ''),
            'summary' => mb_substr((string) ($entry['summary'] ?? ''), 0, 320),
            'status' => (string) ($entry['status'] ?? 'unknown'),
            'ollama_actuated' => !empty($entry['ollama_actuated']),
            'model' => (string) ($entry['model'] ?? ''),
            'latency_ms' => (int) ($entry['latency_ms'] ?? 0),
            'success' => array_key_exists('success', $entry) ? (bool) $entry['success'] : null,
            'error' => mb_substr((string) ($entry['error'] ?? ''), 0, 240),
            'meta' => is_array($entry['meta'] ?? null) ? $entry['meta'] : [],
        ];

        $line = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($line !== false) {
            @file_put_contents($this->workPath, $line . "\n", FILE_APPEND | LOCK_EX);
            $this->trimFile($this->workPath, 2000);
        }

        return $id;
    }

    /**
     * Jurnal vizibil în AI Work când produse intră în coadă (inclusiv mod rapid fără Ollama).
     *
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $meta
     */
    public function logImportBatch(array $stats, array $meta = []): string
    {
        $queued = (int) ($stats['queued'] ?? 0);
        $updated = (int) ($stats['updated_existing'] ?? 0);
        $total = $queued + $updated;
        if ($total <= 0) {
            return '';
        }

        $withImage = (int) ($stats['with_image'] ?? 0);
        $noImage = (int) ($stats['queued_no_image'] ?? 0);
        $showcase = (int) ($stats['queued_showcase'] ?? 0);
        $errors = (int) ($stats['stage_errors'] ?? 0);
        $fast = !empty($meta['matching_pro_fast']);

        $parts = [$total . ' produse în coadă'];
        if ($withImage > 0) {
            $parts[] = $withImage . ' cu imagine';
        }
        if ($noImage > 0) {
            $parts[] = $noImage . ' fără imagine';
        }
        if ($showcase > 0) {
            $parts[] = $showcase . ' vitrină';
        }
        if ($errors > 0) {
            $parts[] = $errors . ' erori';
        }

        $summary = implode(' · ', $parts);
        $output = $fast
            ? 'Mod rapid Import Pro — Ollama la scraping imagini / reprocess în coadă'
            : 'Produse pregătite în coada de import';

        $path = (string) ($meta['path'] ?? '/admin/importreview');
        if ($path === '' && !empty($meta['matching_pro_fast'])) {
            $path = '/admin/import-pro';
        }

        return $this->append([
            'source_type' => 'import_batch',
            'section' => 'import',
            'action' => 'Coadă import',
            'summary' => $summary,
            'status' => $errors > 0 && $total <= $errors ? 'fail' : 'ok',
            'ollama_actuated' => false,
            'success' => $errors < $total,
            'meta' => array_merge($meta, [
                'path' => $path,
                'input_excerpt' => $summary,
                'output_excerpt' => $output,
                'task_label' => 'Trimite în coadă',
            ]),
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    public function feed(int $minutes = 15, int $limit = 150): array
    {
        $minutes = max(1, min(120, $minutes));
        $limit = max(10, min(300, $limit));
        $cutoff = time() - ($minutes * 60);
        $feedback = $this->feedbackIndex();

        $rows = [];
        foreach ($this->collectSources($cutoff) as $row) {
            $key = (string) ($row['source_type'] ?? '') . ':' . (string) ($row['id'] ?? '');
            if (isset($feedback[$key])) {
                $fb = $feedback[$key];
                $row['operator_rating'] = (string) ($fb['rating'] ?? '');
                $row['operator_note'] = (string) ($fb['note'] ?? '');
                $row['operator_rated_at'] = (string) ($fb['at'] ?? '');
            } else {
                $row['operator_rating'] = (string) ($row['operator_rating'] ?? '');
                $row['operator_note'] = (string) ($row['operator_note'] ?? '');
            }
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        $rows = array_slice($rows, 0, $limit);
        $rows = array_map(fn (array $row): array => $this->enrichDisplayRow($row), $rows);

        $archiveFallback = false;
        if ($rows === []) {
            $archiveRows = [];
            $seenArchive = [];
            foreach ($this->collectSources(0) as $row) {
                $key = (string) ($row['source_type'] ?? '') . ':' . (string) ($row['id'] ?? '');
                if ($key === ':' || isset($seenArchive[$key])) {
                    continue;
                }
                $seenArchive[$key] = true;
                $archiveRows[] = $row;
            }
            unset($seenArchive);
            usort($archiveRows, static function (array $a, array $b): int {
                return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
            });
            $rows = array_map(
                fn (array $row): array => $this->enrichDisplayRow($row),
                array_slice($archiveRows, 0, min($limit, 30))
            );
            $archiveFallback = $rows !== [];
        }

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($rows, $minutes, $archiveFallback),
        ];
    }

    public function recordFeedback(
        string $sourceType,
        string $sourceId,
        string $rating,
        string $note = '',
        ?int $userId = null,
    ): bool {
        $sourceType = trim($sourceType);
        $sourceId = trim($sourceId);
        if ($sourceType === '' || $sourceId === '') {
            return false;
        }

        $rating = in_array($rating, ['ok', 'bad', 'partial'], true) ? $rating : 'bad';
        $note = trim($note);

        if ($sourceType === 'section_assist') {
            $entry = (new SectionAssistantQueryLogService($this->root))->setOperatorRating($sourceId, $rating, $note);

            return $entry !== null;
        }

        if ($sourceType === 'rag_module') {
            $logId = (int) $sourceId;
            if ($logId <= 0) {
                return false;
            }
            $humanAction = $rating === 'ok' ? 'confirmed' : ($rating === 'partial' ? 'reviewed' : 'rejected');
            $ok = (new AiInteractionLogService(null, $this->root))->recordHumanAction($logId, $humanAction, $userId);
            if (!$ok) {
                return false;
            }
        }

        $line = json_encode([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'rating' => $rating,
            'note' => $note,
            'user_id' => $userId,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return false;
        }
        @file_put_contents($this->feedbackPath, $line . "\n", FILE_APPEND | LOCK_EX);
        $this->trimFile($this->feedbackPath, 800);

        return true;
    }

    /** @return list<array<string, mixed>> */
    private function collectSources(int $cutoffTs): array
    {
        $rows = [];
        $seen = [];

        foreach ($this->readWorkLog($cutoffTs) as $row) {
            $key = (string) ($row['source_type'] ?? 'aiwork') . ':' . (string) ($row['id'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        foreach ($this->fromInteractionLogs($cutoffTs) as $row) {
            $key = (string) ($row['source_type'] ?? 'rag_module') . ':' . (string) ($row['id'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        foreach ($this->fromSectionAssistant($cutoffTs) as $row) {
            $key = (string) ($row['source_type'] ?? 'section_assist') . ':' . (string) ($row['id'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        foreach ($this->fromOllamaErrors($cutoffTs) as $row) {
            $key = (string) ($row['source_type'] ?? 'ollama_call') . ':' . (string) ($row['id'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        foreach ($this->fromOrchestrator($cutoffTs) as $row) {
            $key = (string) ($row['source_type'] ?? 'orchestrator') . ':' . (string) ($row['id'] ?? '');
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function readWorkLog(int $cutoffTs): array
    {
        if (!is_file($this->workPath)) {
            return [];
        }

        $out = [];
        $lines = @file($this->workPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach (array_reverse($lines) as $line) {
            $json = json_decode((string) $line, true);
            if (!is_array($json)) {
                continue;
            }
            $atRaw = (string) ($json['at'] ?? $json['ts'] ?? '');
            $ts = strtotime($atRaw);
            if ($atRaw !== '' && $ts !== false && $ts < $cutoffTs) {
                continue;
            }
            $out[] = $this->normalizeRow($json, 'aiwork');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromInteractionLogs(int $cutoffTs): array
    {
        try {
            $logs = new AiInteractionLogService(null, $this->root);
            $rows = $logs->recentSince($cutoffTs, 120);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $success = in_array($status, ['accepted', 'proposed', 'reviewed', 'fallback_ok'], true);
            if ($status === 'failed' || $status === 'schema_invalid') {
                $success = false;
            }
            $out[] = $this->normalizeRow([
                'id' => (string) ($row['id'] ?? ''),
                'source_type' => 'rag_module',
                'at' => (string) ($row['created_at'] ?? ''),
                'section' => 'ai-rag',
                'action' => (string) ($row['action'] ?? 'propose') . ' · ' . (string) ($row['module_id'] ?? ''),
                'summary' => (string) ($row['input_summary'] ?? ''),
                'status' => $status,
                'ollama_actuated' => true,
                'model' => (string) ($row['model'] ?? ''),
                'latency_ms' => (int) ($row['latency_ms'] ?? 0),
                'success' => $success,
                'operator_rating' => $this->humanActionToRating((string) ($row['human_action'] ?? '')),
                'meta' => [
                    'module_id' => (string) ($row['module_id'] ?? ''),
                    'input_excerpt' => (string) ($row['input_summary'] ?? ''),
                    'output_excerpt' => mb_substr((string) ($row['output_raw'] ?? ''), 0, 240),
                    'path' => '/admin/ai-rag',
                ],
            ], 'rag_module');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromSectionAssistant(int $cutoffTs): array
    {
        $items = (new SectionAssistantQueryLogService($this->root))->recent(200);
        $out = [];

        foreach ($items as $row) {
            $ts = strtotime((string) ($row['at'] ?? ''));
            if ($ts !== false && $ts < $cutoffTs) {
                continue;
            }

            $source = (string) ($row['response_source'] ?? '');
            $fb = is_array($row['feedback'] ?? null) ? $row['feedback'] : [];
            $fbStatus = (string) ($fb['status'] ?? '');
            $ollamaUsed = stripos($source, 'ollama') !== false;

            $out[] = $this->normalizeRow([
                'id' => (string) ($row['id'] ?? ''),
                'source_type' => 'section_assist',
                'at' => (string) ($row['at'] ?? ''),
                'section' => (string) ($row['section'] ?? ($fb['section'] ?? '')),
                'action' => (string) ($row['response_intent'] ?? $row['action_type'] ?? 'chat'),
                'summary' => mb_substr((string) ($row['message'] ?? ''), 0, 120)
                    . ' → ' . mb_substr((string) ($row['reply_excerpt'] ?? ''), 0, 160),
                'status' => $fbStatus !== '' ? $fbStatus : 'unknown',
                'ollama_actuated' => $ollamaUsed,
                'model' => $ollamaUsed ? 'ollama' : '',
                'latency_ms' => 0,
                'success' => $fbStatus === 'ok' ? true : ($fbStatus === 'fail' ? false : null),
                'operator_rating' => (string) ($row['operator_rating'] ?? ''),
                'operator_note' => (string) ($row['operator_note'] ?? ''),
                'meta' => [
                    'path' => (string) ($row['path'] ?? ''),
                    'source' => $source,
                    'user' => (string) ($row['user'] ?? ''),
                    'input_excerpt' => mb_substr((string) ($row['message'] ?? ''), 0, 220),
                    'output_excerpt' => mb_substr((string) ($row['reply_excerpt'] ?? ''), 0, 240),
                ],
            ], 'section_assist');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromOllamaErrors(int $cutoffTs): array
    {
        $helper = $this->root . '/app/Backend/system/ollama_error_log.php';
        if (!is_file($helper)) {
            return [];
        }
        require_once $helper;

        $out = [];
        foreach (ollama_work_log_recent(200, $this->root) as $row) {
            $ts = strtotime((string) ($row['ts'] ?? $row['at'] ?? ''));
            if ($ts !== false && $ts < $cutoffTs) {
                continue;
            }
            $source = (string) ($row['source'] ?? '');
            $section = (string) ($row['section'] ?? '');
            if ($section === '' && str_contains($source, '.')) {
                $section = explode('.', $source, 2)[0];
            }
            $ok = !empty($row['ok']);
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $out[] = $this->normalizeRow([
                'id' => (string) ($row['id'] ?? md5(json_encode($row) ?: '')),
                'source_type' => 'ollama_call',
                'at' => (string) ($row['at'] ?? $row['ts'] ?? date('c')),
                'section' => $section !== '' ? $section : 'ollama',
                'action' => (string) ($row['action'] ?? ($source !== '' ? $source : 'ollama.call')),
                'summary' => (string) ($row['summary'] ?? ($ok
                    ? ('Apel Ollama OK · ' . (string) ($row['model'] ?? ''))
                    : ('Eșec Ollama: ' . mb_substr((string) ($row['error'] ?? ''), 0, 180)))),
                'status' => $ok ? 'ok' : 'fail',
                'ollama_actuated' => true,
                'model' => (string) ($row['model'] ?? ''),
                'latency_ms' => (int) ($row['latency_ms'] ?? 0),
                'success' => $ok,
                'error' => (string) ($row['error'] ?? ''),
                'meta' => $meta,
            ], 'ollama_call');
        }

        // Erori vechi (doar eșecuri) — fallback dacă work log lipsește
        if ($out === []) {
            foreach (ollama_error_log_recent(80, $this->root) as $row) {
                $ts = strtotime((string) ($row['ts'] ?? ''));
                if ($ts !== false && $ts < $cutoffTs) {
                    continue;
                }
                $source = (string) ($row['source'] ?? '');
                $section = str_contains($source, '.') ? explode('.', $source, 2)[0] : 'ollama';
                $out[] = $this->normalizeRow([
                    'id' => md5(json_encode($row) ?: (string) microtime(true)),
                    'source_type' => 'ollama_call',
                    'at' => (string) ($row['ts'] ?? date('c')),
                    'section' => $section,
                    'action' => $source,
                    'summary' => 'Eșec: ' . mb_substr((string) ($row['error'] ?? ''), 0, 180),
                    'status' => 'fail',
                    'ollama_actuated' => true,
                    'model' => (string) ($row['model'] ?? ''),
                    'latency_ms' => (int) ($row['latency_ms'] ?? 0),
                    'success' => false,
                    'error' => (string) ($row['error'] ?? ''),
                ], 'ollama_call');
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromOrchestrator(int $cutoffTs): array
    {
        $path = $this->root . '/app/Backend/storage/ai_intelligence/orchestrator_routes.jsonl';
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $json = json_decode((string) $line, true);
            if (!is_array($json)) {
                continue;
            }
            $provider = (string) ($json['provider'] ?? '');
            if ($provider !== 'ollama') {
                continue;
            }
            $ts = strtotime((string) ($json['ts'] ?? ''));
            if ($ts !== false && $ts < $cutoffTs) {
                continue;
            }
            $ok = !empty($json['ok']);
            $task = (string) ($json['task_type'] ?? 'task');
            $query = (string) ($json['query'] ?? $json['input'] ?? $json['message'] ?? '');
            $out[] = $this->normalizeRow([
                'id' => md5((string) ($json['ts'] ?? '') . $task . ($json['error'] ?? '')),
                'source_type' => 'orchestrator',
                'at' => (string) ($json['ts'] ?? date('c')),
                'section' => 'intelligence',
                'action' => $task,
                'summary' => $ok
                    ? ('Orchestrator ' . $task . ' OK')
                    : ('Orchestrator ' . $task . ': ' . mb_substr((string) ($json['error'] ?? ''), 0, 160)),
                'status' => $ok ? 'ok' : 'fail',
                'ollama_actuated' => true,
                'model' => (string) ($json['model'] ?? 'ollama'),
                'latency_ms' => (int) ($json['latency_ms'] ?? 0),
                'success' => $ok,
                'error' => (string) ($json['error'] ?? ''),
                'meta' => [
                    'input_excerpt' => mb_substr($query, 0, 220),
                    'path' => '/admin/ai-rag',
                ],
            ], 'orchestrator');
            if (count($out) >= 80) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalizeRow(array $row, string $defaultSourceType): array
    {
        $sourceType = (string) ($row['source_type'] ?? $defaultSourceType);
        $id = (string) ($row['id'] ?? '');

        return [
            'id' => $id,
            'source_type' => $sourceType,
            'at' => (string) ($row['at'] ?? $row['ts'] ?? ''),
            'section' => (string) ($row['section'] ?? '—'),
            'action' => (string) ($row['action'] ?? '—'),
            'summary' => (string) ($row['summary'] ?? ''),
            'status' => (string) ($row['status'] ?? 'unknown'),
            'ollama_actuated' => !empty($row['ollama_actuated']),
            'model' => (string) ($row['model'] ?? ''),
            'latency_ms' => (int) ($row['latency_ms'] ?? 0),
            'success' => array_key_exists('success', $row) ? $row['success'] : null,
            'error' => (string) ($row['error'] ?? ''),
            'operator_rating' => (string) ($row['operator_rating'] ?? ''),
            'operator_note' => (string) ($row['operator_note'] ?? ''),
            'can_feedback' => $id !== '' && in_array($sourceType, ['section_assist', 'rag_module', 'ollama_call', 'orchestrator', 'aiwork'], true),
            'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : [],
        ];
    }

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    private function buildStats(array $rows, int $minutes, bool $archiveFallback = false): array
    {
        $total = count($rows);
        $ollamaRows = array_values(array_filter($rows, static fn (array $r): bool => !empty($r['ollama_actuated'])));
        $withOutcome = array_values(array_filter($ollamaRows, static fn (array $r): bool => $r['success'] !== null));
        $ok = 0;
        $fail = 0;
        foreach ($withOutcome as $row) {
            if (!empty($row['success'])) {
                ++$ok;
            } else {
                ++$fail;
            }
        }
        $rated = array_values(array_filter($rows, static fn (array $r): bool => (string) ($r['operator_rating'] ?? '') !== ''));

        return [
            'window_minutes' => $minutes,
            'archive_fallback' => $archiveFallback,
            'total' => $total,
            'ollama_events' => count($ollamaRows),
            'with_outcome' => count($withOutcome),
            'success_count' => $ok,
            'fail_count' => $fail,
            'success_rate_pct' => count($withOutcome) > 0 ? (int) round(($ok / count($withOutcome)) * 100) : null,
            'operator_rated' => count($rated),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function feedbackIndex(): array
    {
        if (!is_file($this->feedbackPath)) {
            return [];
        }
        $index = [];
        $lines = @file($this->feedbackPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $json = json_decode((string) $line, true);
            if (!is_array($json)) {
                continue;
            }
            $key = (string) ($json['source_type'] ?? '') . ':' . (string) ($json['source_id'] ?? '');
            if ($key === ':') {
                continue;
            }
            $index[$key] = $json;
        }

        return $index;
    }

    private function humanActionToRating(string $action): string
    {
        return match ($action) {
            'confirmed' => 'ok',
            'rejected' => 'bad',
            'reviewed' => 'partial',
            default => '',
        };
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function enrichDisplayRow(array $row): array
    {
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $input = $this->resolveInputText($row, $meta);
        $output = $this->resolveOutputText($row, $meta);
        $where = $this->resolveWhereLabel($row, $meta);
        $actionLabel = $this->resolveActionLabel($row, $meta);

        $row['where'] = $where;
        $row['section_label'] = $where;
        $row['action_label'] = $actionLabel;
        $row['input_text'] = $input;
        $row['output_text'] = $output;
        $row['detail'] = $this->buildDetailText($row, $input, $output, $meta);

        return $row;
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function resolveWhereLabel(array $row, array $meta): string
    {
        $path = mb_strtolower((string) ($meta['path'] ?? ''));
        if ($path !== '') {
            $fromPath = $this->labelFromAdminPath($path);
            if ($fromPath !== '') {
                return $fromPath;
            }
        }

        $agent = (string) ($meta['agent'] ?? '');
        if ($agent !== '') {
            return 'Centru AI · ' . $this->agentSlugLabel($agent);
        }

        $section = (string) ($row['section'] ?? '');
        $moduleId = (string) ($meta['module_id'] ?? '');
        if ($moduleId !== '') {
            return 'Centru AI · ' . $this->moduleIdLabel($moduleId);
        }

        return match ($section) {
            'import' => 'Import · coadă produse',
            'ai-rag', 'intelligence' => 'Centru AI',
            'scraper' => 'Scraper web',
            'produse', 'product' => 'Produse',
            'furnizori' => 'Furnizori',
            'categorii' => 'Categorii',
            'comenzi' => 'Comenzi',
            'erp' => 'Admin ERP',
            default => $section !== '' && $section !== '—' ? ucfirst($section) : 'Admin',
        };
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function resolveActionLabel(array $row, array $meta): string
    {
        $taskLabel = trim((string) ($meta['task_label'] ?? ''));
        if ($taskLabel !== '') {
            return $taskLabel;
        }

        $action = (string) ($row['action'] ?? '');
        $sourceType = (string) ($row['source_type'] ?? '');
        $moduleId = (string) ($meta['module_id'] ?? '');

        if ($moduleId !== '') {
            return $this->moduleIdLabel($moduleId);
        }

        if ($sourceType === 'section_assist') {
            return 'Asistent pe pagină';
        }

        return match (true) {
            str_contains($action, 'Coadă import'), str_contains($action, 'Trimite') => 'Trimite în coadă',
            str_contains($action, 'image_relevance'), str_contains($action, 'Verificare imagine') => 'Verificare imagine',
            str_contains($action, 'category_match'), str_contains($action, 'Potrivire categorie') => 'Potrivire categorie',
            str_contains($action, 'product_name_normalize'), str_contains($action, 'Normalizare denumire') => 'Normalizare denumire',
            str_contains($action, 'import.chat_json'), str_contains($action, 'Import Pro') => 'Import Pro · clasificare',
            str_contains($action, 'agent-produse'), str_contains($action, 'Chat agent produse') => 'Chat catalog produse',
            str_contains($action, 'log_summary'), str_contains($action, 'mod_06') => 'Rezumat jurnale AI',
            str_contains($action, 'hybrid_search') => 'Căutare hibridă',
            str_contains($action, 'vision'), str_contains($action, 'imagini') => 'Analiză imagini',
            str_contains($action, 'erp.chat'), str_contains($action, 'chat') => 'Chat LLM',
            str_contains($action, 'propose') => 'Propunere AI',
            default => $action !== '' && $action !== '—' ? $action : 'Procesare AI',
        };
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function resolveInputText(array $row, array $meta): string
    {
        foreach (['input_excerpt', 'message', 'query'] as $key) {
            $text = trim((string) ($meta[$key] ?? ''));
            if ($text !== '') {
                return mb_substr($text, 0, 280);
            }
        }

        $summary = trim((string) ($row['summary'] ?? ''));
        if (str_contains($summary, ' → ')) {
            return mb_substr(explode(' → ', $summary, 2)[0], 0, 280);
        }

        if ($summary !== '' && !str_starts_with($summary, 'Apel Ollama OK') && !str_starts_with($summary, 'Orchestrator')) {
            return mb_substr($summary, 0, 280);
        }

        return '';
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function resolveOutputText(array $row, array $meta): string
    {
        foreach (['output_excerpt', 'reply_excerpt'] as $key) {
            $text = trim((string) ($meta[$key] ?? ''));
            if ($text !== '') {
                return mb_substr($text, 0, 280);
            }
        }

        $summary = trim((string) ($row['summary'] ?? ''));
        if (str_contains($summary, ' → ')) {
            return mb_substr(explode(' → ', $summary, 2)[1] ?? '', 0, 280);
        }

        if (!empty($row['error'])) {
            return mb_substr((string) $row['error'], 0, 280);
        }

        $model = trim((string) ($row['model'] ?? ''));
        if (!empty($row['success']) && $model !== '') {
            return 'Răspuns generat · ' . $model;
        }

        return '';
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $meta */
    private function buildDetailText(array $row, string $input, string $output, array $meta): string
    {
        if ($input !== '' && $output !== '') {
            return $input . ' → ' . $output;
        }
        if ($input !== '') {
            return $input;
        }
        if ($output !== '') {
            return $output;
        }

        $summary = trim((string) ($row['summary'] ?? ''));
        if ($summary !== '' && !str_starts_with($summary, 'Apel Ollama OK')) {
            return $summary;
        }

        $model = trim((string) ($row['model'] ?? ''));
        if ($model !== '') {
            return 'Apel model ' . $model . ' (fără text salvat în jurnal)';
        }

        return 'Activitate AI fără detaliu — verifică sursa';
    }

    private function labelFromAdminPath(string $path): string
    {
        $path = mb_strtolower($path);

        return match (true) {
            str_contains($path, '/ai-rag') => 'Centru AI',
            str_contains($path, '/import') => 'Import produse',
            str_contains($path, '/scraper') => 'Scraper web',
            str_contains($path, '/product'), str_contains($path, '/produse') => 'Produse',
            str_contains($path, '/furnizori') => 'Furnizori',
            str_contains($path, '/categorii') => 'Categorii',
            str_contains($path, '/caietcomenzi'), str_contains($path, '/comenzi') => 'Comenzi',
            str_contains($path, '/clienti') => 'Clienți',
            str_contains($path, '/settings') => 'Setări',
            default => '',
        };
    }

    private function agentSlugLabel(string $slug): string
    {
        return match ($slug) {
            'agent-produse' => 'Agent Produse',
            'agent-statistici' => 'Agent Statistici',
            'agent-imagini' => 'Agent Imagini',
            'agent-import' => 'Agent Import',
            'agent-furnizori' => 'Agent Furnizori',
            'agent-comenzi' => 'Agent Comenzi',
            default => ucfirst(str_replace(['agent-', '-'], ['', ' '], $slug)),
        };
    }

    private function moduleIdLabel(string $moduleId): string
    {
        return match ($moduleId) {
            'mod_06_log_summary' => 'Rezumat jurnale',
            'mod_01_category_match' => 'Potrivire categorii',
            'mod_market_research' => 'Cercetare piață',
            default => str_replace(['mod_', '_'], ['', ' '], $moduleId),
        };
    }

    private function trimFile(string $path, int $maxLines): void
    {
        if (!is_file($path) || filesize($path) <= 512000) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || count($lines) <= $maxLines) {
            return;
        }
        @file_put_contents($path, implode("\n", array_slice($lines, -$maxLines)) . "\n", LOCK_EX);
    }
}
