<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Store;

/**
 * Persistență artefacte supervizor — JSON separat per modul.
 */
final class AiSupervisorStore
{
    private string $dir;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? dirname(__DIR__, 5);
        $this->dir = $root . '/robot/data/ai_supervisor';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /** @return array<string, mixed> */
    public function read(string $name): array
    {
        $path = $this->path($name);
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $data */
    public function write(string $name, array $data): void
    {
        $data['stored_at'] = date('c');
        file_put_contents(
            $this->path($name),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @return array{ran: bool, reason: string} */
    public function shouldRun(string $jobKey, int $intervalSec): array
    {
        $state = $this->read('scheduler_state');
        $jobs = is_array($state['jobs'] ?? null) ? $state['jobs'] : [];
        $job = is_array($jobs[$jobKey] ?? null) ? $jobs[$jobKey] : [];
        $lastSuccess = (string) ($job['last_success_at'] ?? '');
        $lastTs = $lastSuccess !== '' ? (int) strtotime($lastSuccess) : 0;
        $now = time();

        if ($lastTs > 0 && ($now - $lastTs) < $intervalSec) {
            return ['ran' => false, 'reason' => 'interval'];
        }

        return ['ran' => true, 'reason' => 'due'];
    }

    public function markRun(string $jobKey, string $status = 'ok', ?string $detail = null): void
    {
        $state = $this->read('scheduler_state');
        $jobs = is_array($state['jobs'] ?? null) ? $state['jobs'] : [];
        $previous = is_array($jobs[$jobKey] ?? null) ? $jobs[$jobKey] : [];
        $entry = [
            'last_run_at' => date('c'),
            'status' => $status,
            'detail' => $detail,
        ];
        if ($status === 'ok' || $status === 'warn') {
            $entry['last_success_at'] = date('c');
        } else {
            $entry['last_error_at'] = date('c');
            $entry['last_error'] = $detail;
            if (isset($previous['last_success_at'])) {
                $entry['last_success_at'] = $previous['last_success_at'];
            }
        }
        $jobs[$jobKey] = array_merge($previous, $entry);
        $state['jobs'] = $jobs;
        $state['updated_at'] = date('c');
        $this->write('scheduler_state', $state);
    }

    /** @return array<string, mixed> */
    public function statusSummary(): array
    {
        $files = [
            'catalog_audit' => 'catalog_audit.json',
            'pipeline_health' => 'pipeline_health.json',
            'token_budget' => 'token_budget.json',
            'supplier_watch' => 'supplier_watch.json',
            'diagnostics' => 'diagnostics.json',
            'conversations' => 'conversations.json',
            'daily_report' => 'daily_report.json',
            'last_cycle' => 'last_cycle.json',
        ];
        $out = [];
        foreach ($files as $key => $file) {
            $path = $this->dir . '/' . $file;
            $out[$key] = [
                'exists' => is_file($path),
                'size' => is_file($path) ? filesize($path) : 0,
                'mtime' => is_file($path) ? date('c', (int) filemtime($path)) : null,
            ];
        }
        $scheduler = $this->read('scheduler_state');

        return [
            'dir' => $this->dir,
            'artifacts' => $out,
            'scheduler' => $scheduler,
        ];
    }

    private function path(string $name): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/', '_', strtolower($name)) ?? 'data';

        return $this->dir . '/' . $safe . '.json';
    }
}
