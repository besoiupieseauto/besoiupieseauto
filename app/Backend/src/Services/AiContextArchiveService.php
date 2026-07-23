<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Arhivare orară jurnal AI — documentează, arhivează, golește buffer-ul activ.
 */
final class AiContextArchiveService
{
    private const KEEP_HOURS = 168; // 7 zile

    private string $contextDir;
    private AiActionEventService $events;

    public function __construct(?string $contextDir = null, ?AiActionEventService $events = null)
    {
        $root = dirname(__DIR__, 3);
        $this->contextDir = $contextDir ?? ($root . '/robot/data/ai_context');
        $this->events = $events ?? new AiActionEventService();
    }

    public function currentHourKey(): string
    {
        return date('Y-m-d-H');
    }

    public function shouldArchiveHourly(): bool
    {
        $state = $this->readState();

        return (string) ($state['last_archive_hour'] ?? '') !== $this->currentHourKey();
    }

    /**
     * @return array<string, mixed>|null null dacă nu e ora sau buffer gol
     */
    public function archiveHourlyIfDue(bool $force = false): ?array
    {
        if (!$force && !$this->shouldArchiveHourly()) {
            return null;
        }

        $eventsPath = $this->contextDir . '/events.jsonl';
        $lineCount = 0;
        if (is_file($eventsPath)) {
            $lineCount = count(@file($eventsPath, FILE_IGNORE_NEW_LINES) ?: []);
        }

        if (!$force && $lineCount === 0) {
            $this->touchStateSkipped();

            return null;
        }

        return $this->archiveAndReset($lineCount);
    }

    /** @return array<string, mixed> */
    public function archiveAndReset(int $eventCountHint = 0): array
    {
        $hourKey = $this->currentHourKey();
        $slotTs = strtotime('-1 hour');
        $date = date('Y-m-d', $slotTs);
        $hour = date('H', $slotTs);
        $slotKey = $date . '-' . $hour;
        $dest = $this->contextDir . '/archive/' . $date . '/' . $hour . '-00';
        if (!is_dir($dest)) {
            @mkdir($dest, 0775, true);
        }

        $core = $this->events->getCore();
        $files = [];

        $targets = [
            'events.jsonl' => $this->contextDir . '/events.jsonl',
            'core.json' => $this->contextDir . '/core.json',
            'core.snapshot.json' => $this->contextDir . '/core.snapshot.json',
            'learning_state.json' => $this->contextDir . '/learning_state.json',
            'sync_requested.at' => $this->contextDir . '/sync_requested.at',
            'autonomy/actions.jsonl' => $this->contextDir . '/autonomy/actions.jsonl',
            'autonomy/proposals.json' => $this->contextDir . '/autonomy/proposals.json',
            'autonomy/last_cycle.json' => $this->contextDir . '/autonomy/last_cycle.json',
            'latest.json' => $this->contextDir . '/latest.json',
            'latest.md' => $this->contextDir . '/latest.md',
        ];

        foreach ($targets as $name => $src) {
            if (!is_file($src)) {
                continue;
            }
            $bytes = filesize($src);
            if ($bytes === false || $bytes < 1) {
                continue;
            }
            $destFile = $dest . '/' . str_replace('/', '__', $name);
            @copy($src, $destFile);
            $files[$name] = ['bytes' => $bytes, 'archived_as' => basename($destFile)];
        }

        $sessionCount = $this->archiveDirectory(
            $this->contextDir . '/sessions',
            $dest . '/sessions'
        );
        if ($sessionCount > 0) {
            $files['sessions/'] = ['count' => $sessionCount];
        }

        $rateCount = $this->archiveDirectory(
            $this->contextDir . '/client_rate',
            $dest . '/client_rate'
        );
        if ($rateCount > 0) {
            $files['client_rate/'] = ['count' => $rateCount];
        }

        $stats = $this->summarizeEvents($dest . '/' . str_replace('/', '__', 'events.jsonl'));

        $manifest = [
            'archived_at' => date('c'),
            'hour_key' => $slotKey,
            'closed_at_hour' => $hourKey,
            'path' => 'archive/' . $date . '/' . $hour . '-00',
            'event_count' => $stats['total'] ?: $eventCountHint,
            'stats' => $stats,
            'core_summary' => [
                'event_count' => (int) ($core['event_count'] ?? 0),
                'actors' => $core['actors'] ?? [],
                'directives_count' => count($core['robot_directives'] ?? []),
            ],
            'files' => $files,
        ];

        file_put_contents(
            $dest . '/manifest.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        file_put_contents($dest . '/RAPORT.md', $this->buildReportMarkdown($manifest));

        $this->resetActiveBuffers();
        $this->events->rebuildCore();
        $this->pruneOldArchives(self::KEEP_HOURS);
        $this->writeState($hourKey, $dest);

        return $manifest;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $state = $this->readState();
        $archives = $this->listRecentArchives(8);

        return [
            'last_archive_hour' => $state['last_archive_hour'] ?? null,
            'last_archive_at' => $state['last_archive_at'] ?? null,
            'last_archive_path' => $state['last_archive_path'] ?? null,
            'next_archive_hour' => $this->nextHourLabel(),
            'keep_hours' => self::KEEP_HOURS,
            'recent_archives' => $archives,
        ];
    }

    private function resetActiveBuffers(): void
    {
        @file_put_contents($this->contextDir . '/events.jsonl', '');
        @unlink($this->contextDir . '/sync_requested.at');

        $wipe = [
            $this->contextDir . '/autonomy/actions.jsonl',
        ];
        foreach ($wipe as $path) {
            @file_put_contents($path, '');
        }

        @file_put_contents(
            $this->contextDir . '/autonomy/proposals.json',
            json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->clearDirectory($this->contextDir . '/sessions');
        $this->clearDirectory($this->contextDir . '/client_rate');
    }

    private function archiveDirectory(string $srcDir, string $destDir): int
    {
        if (!is_dir($srcDir)) {
            return 0;
        }
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $count = 0;
        foreach (glob($srcDir . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            @copy($file, $destDir . '/' . basename($file));
            $count++;
        }

        return $count;
    }

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function pruneOldArchives(int $keepHours = self::KEEP_HOURS): int
    {
        $root = $this->contextDir . '/archive';
        if (!is_dir($root)) {
            return 0;
        }
        $dirs = [];
        foreach (glob($root . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $dirs[] = $dir;
        }
        rsort($dirs);
        $removed = 0;
        foreach (array_slice($dirs, $keepHours) as $old) {
            $this->removeDirectory($old);
            $removed++;
        }

        return $removed;
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }

    /** @return array{total: int, client: int, admin: int, system: int, top_actions: array<string, int>} */
    private function summarizeEvents(string $archivedEventsPath): array
    {
        $stats = ['total' => 0, 'client' => 0, 'admin' => 0, 'system' => 0, 'top_actions' => []];
        if (!is_file($archivedEventsPath)) {
            return $stats;
        }
        $lines = @file($archivedEventsPath, FILE_IGNORE_NEW_LINES) ?: [];
        $actions = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $stats['total']++;
            $actor = (string) ($row['actor_type'] ?? 'system');
            if (isset($stats[$actor])) {
                $stats[$actor]++;
            }
            $act = (string) ($row['action'] ?? 'unknown');
            $actions[$act] = ($actions[$act] ?? 0) + 1;
        }
        arsort($actions);
        $stats['top_actions'] = array_slice($actions, 0, 12, true);

        return $stats;
    }

    /** @param array<string, mixed> $manifest */
    private function buildReportMarkdown(array $manifest): string
    {
        $stats = is_array($manifest['stats'] ?? null) ? $manifest['stats'] : [];
        $lines = [];
        $lines[] = '# Arhivă orară AI — Besoiu';
        $lines[] = '';
        $lines[] = '- **Ora:** ' . ($manifest['hour_key'] ?? '—');
        $lines[] = '- **Arhivat la:** ' . ($manifest['archived_at'] ?? '—');
        $lines[] = '- **Evenimente:** ' . (int) ($manifest['event_count'] ?? 0);
        $lines[] = '';
        $lines[] = '## Statistici';
        $lines[] = '- Client: ' . (int) ($stats['client'] ?? 0);
        $lines[] = '- Admin: ' . (int) ($stats['admin'] ?? 0);
        $lines[] = '- System: ' . (int) ($stats['system'] ?? 0);
        $lines[] = '';
        $lines[] = '## Acțiuni frecvente';
        foreach (($stats['top_actions'] ?? []) as $action => $count) {
            $lines[] = '- `' . $action . '`: ' . $count;
        }
        $lines[] = '';
        $lines[] = '## Fișiere arhivate';
        foreach (($manifest['files'] ?? []) as $name => $info) {
            if (!is_array($info)) {
                continue;
            }
            $lines[] = '- `' . $name . '`'
                . (isset($info['bytes']) ? ' (' . $info['bytes'] . ' B)' : '')
                . (isset($info['count']) ? ' (' . $info['count'] . ' fișiere)' : '');
        }
        $lines[] = '';
        $lines[] = '_După arhivare buffer-ul activ a fost golit — înregistrarea repornește de la zero._';

        return implode("\n", $lines);
    }

    /** @return list<array<string, mixed>> */
    private function listRecentArchives(int $limit): array
    {
        $root = $this->contextDir . '/archive';
        if (!is_dir($root)) {
            return [];
        }
        $manifests = glob($root . '/*/*/manifest.json') ?: [];
        rsort($manifests);
        $out = [];
        foreach (array_slice($manifests, 0, max(1, min(30, $limit))) as $path) {
            $json = json_decode((string) file_get_contents($path), true);
            if (!is_array($json)) {
                continue;
            }
            $out[] = [
                'hour_key' => $json['hour_key'] ?? null,
                'archived_at' => $json['archived_at'] ?? null,
                'event_count' => (int) ($json['event_count'] ?? 0),
                'path' => $json['path'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        $path = $this->contextDir . '/archive_state.json';
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    private function writeState(string $hourKey, string $destPath): void
    {
        file_put_contents($this->contextDir . '/archive_state.json', json_encode([
            'last_archive_hour' => $hourKey,
            'last_archive_at' => date('c'),
            'last_archive_path' => str_replace($this->contextDir . '/', '', $destPath),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function touchStateSkipped(): void
    {
        @file_put_contents(
            $this->contextDir . '/archive_state.json',
            json_encode([
                'last_archive_hour' => $this->currentHourKey(),
                'last_archive_at' => null,
                'last_skip_at' => date('c'),
                'last_skip_reason' => 'buffer_gol',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function nextHourLabel(): string
    {
        return date('Y-m-d H:00', strtotime('+1 hour'));
    }
}
