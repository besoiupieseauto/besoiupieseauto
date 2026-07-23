<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Arhivează jurnalul erori pentru AI — fără ștergere din DB.
 */
final class SystemErrorsArchiveService
{
    private string $contextDir;

    public function __construct(?string $contextDir = null)
    {
        $root = dirname(__DIR__, 3);
        $this->contextDir = $contextDir ?? ($root . '/robot/data/ai_context');
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $this->boot();

        $pdo = $this->pdo();
        $periods = [];
        foreach (\system_errors_valid_periods() as $period) {
            $periods[$period] = \system_errors_count_for_archive($pdo, $period, []);
        }

        return [
            'archive_dir' => $this->archiveRoot(),
            'pending' => $periods,
            'recent' => $this->listRecentArchives(8),
            'latest_summary_path' => $this->contextDir . '/system_errors_latest.json',
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function archiveForAi(string $period, array $filters = []): array
    {
        $this->boot();
        $period = \system_errors_normalize_period($period);
        if (!in_array($period, \system_errors_valid_periods(), true)) {
            throw new \InvalidArgumentException('Perioadă invalidă.');
        }

        $pdo = $this->pdo();
        $rows = \system_errors_fetch_for_archive($pdo, $period, $filters);
        if ($rows === []) {
            return [
                'success' => false,
                'count' => 0,
                'period' => $period,
                'path' => '',
                'message' => 'Nicio eroare de arhivat pentru perioada selectată.',
            ];
        }

        $manifest = $this->writeArchive($period, $rows, $filters);
        $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows);
        $marked = \system_errors_mark_archived($pdo, $ids);

        $this->persistLatestSummary($manifest);
        $this->appendAiLearning($manifest);

        return [
            'success' => $marked > 0,
            'count' => $marked,
            'period' => $period,
            'path' => (string) ($manifest['path'] ?? ''),
            'manifest' => $manifest,
            'message' => $marked > 0
                ? $marked . ' erori arhivate pentru AI — jurnalul activ a fost curățat.'
                : 'Arhiva scrisă, dar marcarea în DB a eșuat.',
        ];
    }

    /** @param list<array<string, mixed>> $rows @param array<string, mixed> $filters @return array<string, mixed> */
    private function writeArchive(string $period, array $rows, array $filters): array
    {
        $date = date('Y-m-d');
        $stamp = date('Y-m-d_His');
        $dest = $this->archiveRoot() . '/' . $date;
        if (!is_dir($dest)) {
            @mkdir($dest, 0775, true);
        }

        $fileBase = $stamp . '_' . $period;
        $jsonPath = $dest . '/' . $fileBase . '.json';
        $mdPath = $dest . '/' . $fileBase . '.md';

        $stats = $this->summarizeRows($rows);
        $manifest = [
            'archived_at' => date('c'),
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'count' => count($rows),
            'stats' => $stats,
            'filters' => array_filter([
                'channel' => trim((string) ($filters['channel'] ?? '')),
                'level' => trim((string) ($filters['level'] ?? '')),
                'q' => trim((string) ($filters['q'] ?? '')),
            ]),
            'path' => 'system_errors_archive/' . $date . '/' . $fileBase,
            'items' => $rows,
        ];

        file_put_contents(
            $jsonPath,
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        file_put_contents($mdPath, $this->buildMarkdown($manifest));

        $manifest['files'] = [
            'json' => basename($jsonPath),
            'markdown' => basename($mdPath),
        ];
        unset($manifest['items']);

        file_put_contents(
            $dest . '/' . $fileBase . '_manifest.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $manifest;
    }

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    private function summarizeRows(array $rows): array
    {
        $byLevel = [];
        $byChannel = [];
        $samples = [];

        foreach ($rows as $row) {
            $level = (string) ($row['level'] ?? 'info');
            $channel = (string) ($row['channel'] ?? 'general');
            $byLevel[$level] = ($byLevel[$level] ?? 0) + 1;
            $byChannel[$channel] = ($byChannel[$channel] ?? 0) + 1;

            if (count($samples) < 12) {
                $samples[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'level' => $level,
                    'channel' => $channel,
                    'message' => mb_substr((string) ($row['message'] ?? ''), 0, 220),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        }

        arsort($byLevel);
        arsort($byChannel);

        return [
            'by_level' => $byLevel,
            'by_channel' => $byChannel,
            'samples' => $samples,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function buildMarkdown(array $manifest): string
    {
        $stats = is_array($manifest['stats'] ?? null) ? $manifest['stats'] : [];
        $lines = [];
        $lines[] = '# Arhivă jurnal erori — Besoiu';
        $lines[] = '';
        $lines[] = '- **Perioadă:** ' . ($manifest['period_label'] ?? $manifest['period'] ?? '—');
        $lines[] = '- **Arhivat la:** ' . ($manifest['archived_at'] ?? '—');
        $lines[] = '- **Total erori:** ' . (int) ($manifest['count'] ?? 0);
        $lines[] = '';
        $lines[] = '## Pe nivel';
        foreach (($stats['by_level'] ?? []) as $level => $count) {
            $lines[] = '- `' . $level . '`: ' . $count;
        }
        $lines[] = '';
        $lines[] = '## Pe canal';
        foreach (($stats['by_channel'] ?? []) as $channel => $count) {
            $lines[] = '- `' . $channel . '`: ' . $count;
        }
        $lines[] = '';
        $lines[] = '## Mostre (pentru AI)';
        foreach (($stats['samples'] ?? []) as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $lines[] = '- **' . ($sample['level'] ?? '') . ' / ' . ($sample['channel'] ?? '') . '** — '
                . ($sample['message'] ?? '') . ' _' . ($sample['created_at'] ?? '') . '_';
        }
        $lines[] = '';
        $lines[] = '_Erorile rămân în DB cu `is_archived=1` — jurnalul activ afișează doar ne-arhivate._';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $manifest */
    private function persistLatestSummary(array $manifest): void
    {
        if (!is_dir($this->contextDir)) {
            @mkdir($this->contextDir, 0775, true);
        }

        $summary = [
            'updated_at' => date('c'),
            'period' => $manifest['period'] ?? '',
            'period_label' => $manifest['period_label'] ?? '',
            'count' => (int) ($manifest['count'] ?? 0),
            'stats' => $manifest['stats'] ?? [],
            'path' => $manifest['path'] ?? '',
        ];

        file_put_contents(
            $this->contextDir . '/system_errors_latest.json',
            json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @param array<string, mixed> $manifest */
    private function appendAiLearning(array $manifest): void
    {
        $helper = dirname(__DIR__, 3) . '/system/ai_action_events.php';
        if (is_file($helper)) {
            require_once $helper;
        }
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('system', 'system_errors_archived', (string) ($manifest['period'] ?? ''), [
                'count' => (int) ($manifest['count'] ?? 0),
                'path' => (string) ($manifest['path'] ?? ''),
                'by_channel' => $manifest['stats']['by_channel'] ?? [],
                'by_level' => $manifest['stats']['by_level'] ?? [],
            ]);
        }

        $learningHelper = dirname(__DIR__, 3) . '/system/ai_learning.php';
        if (is_file($learningHelper)) {
            require_once $learningHelper;
        }
        if (function_exists('ai_learning_record_system_errors_archive')) {
            ai_learning_record_system_errors_archive($manifest);
        }
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'Azi',
            'yesterday' => 'Ieri',
            'week' => 'Ultimele 7 zile',
            'all' => 'Tot timpul (jurnal activ)',
            default => $period,
        };
    }

    /** @return list<array<string, mixed>> */
    public function listRecentArchives(int $limit = 8): array
    {
        $root = $this->archiveRoot();
        if (!is_dir($root)) {
            return [];
        }

        $manifests = glob($root . '/*/*_manifest.json') ?: [];
        rsort($manifests);
        $out = [];

        foreach (array_slice($manifests, 0, max(1, min(30, $limit))) as $path) {
            $json = json_decode((string) file_get_contents($path), true);
            if (!is_array($json)) {
                continue;
            }
            $out[] = [
                'archived_at' => $json['archived_at'] ?? null,
                'period' => $json['period'] ?? null,
                'period_label' => $json['period_label'] ?? null,
                'count' => (int) ($json['count'] ?? 0),
                'path' => $json['path'] ?? null,
            ];
        }

        return $out;
    }

    private function archiveRoot(): string
    {
        $dir = $this->contextDir . '/system_errors_archive';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function boot(): void
    {
        require_once dirname(__DIR__, 3) . '/system/system_errors.php';
    }

    private function pdo(): \PDO
    {
        require_once dirname(__DIR__, 3) . '/system/tecdoc_stock.php';

        return tecdoc_db();
    }
}
