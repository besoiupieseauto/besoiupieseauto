<?php

declare(strict_types=1);

/**
 * Jurnal central erori / apeluri Ollama — JSONL în admin/storage/ollama/.
 */

/** Rădăcina site (besoiupieseauto.ro) — nu app/ sau app/Backend/. */
function ollama_site_root(?string $hint = null): string
{
    if ($hint !== null && $hint !== '') {
        $root = rtrim(str_replace('\\', '/', $hint), '/');
        if (is_dir($root . '/admin/public/api')) {
            return $root;
        }
        $parent = dirname($root);
        if (is_dir($parent . '/admin/public/api')) {
            return $parent;
        }
        $grand = dirname($root, 2);
        if (is_dir($grand . '/admin/public/api')) {
            return $grand;
        }
    }

    if (defined('BESOIU_ROOT') && is_dir(BESOIU_ROOT . '/admin/public/api')) {
        return rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
    }

    // app/Backend/system → 3 nivele sus = rădăcina site
    $candidate = dirname(__DIR__, 3);
    if (is_dir($candidate . '/admin/public/api')) {
        return $candidate;
    }

    return dirname(__DIR__, 3);
}

function ollama_error_log_dir(?string $projectRoot = null): string
{
    $root = ollama_site_root($projectRoot);
    $dir = rtrim($root, '/\\') . '/admin/storage/ollama';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function ollama_error_log_path(?string $projectRoot = null): string
{
    return ollama_error_log_dir($projectRoot) . '/errors.jsonl';
}

function ollama_telemetry_path(?string $projectRoot = null): string
{
    return ollama_error_log_dir($projectRoot) . '/telemetry.json';
}

function ollama_work_log_path(?string $projectRoot = null): string
{
    $root = ollama_site_root($projectRoot);
    $dir = rtrim($root, '/\\') . '/admin/storage/ai_work';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/work.jsonl';
}

/** @param array<string, mixed> $entry */
function ollama_work_log_append(array $entry, ?string $projectRoot = null): void
{
    if (!ollama_error_log_enabled()) {
        return;
    }

    $entry['id'] = $entry['id'] ?? ('ow_' . bin2hex(random_bytes(6)));
    $entry['ts'] = $entry['ts'] ?? $entry['at'] ?? date('c');
    $entry['at'] = $entry['at'] ?? $entry['ts'];
    if (!isset($entry['source_type']) || (string) $entry['source_type'] === '') {
        $entry['source_type'] = 'ollama_call';
    }
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }

    $path = ollama_work_log_path($projectRoot);
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);

    if (!is_file($path) || filesize($path) <= 768000) {
        return;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || count($lines) <= 1500) {
        return;
    }
    @file_put_contents($path, implode("\n", array_slice($lines, -1200)) . "\n", LOCK_EX);
}

/** Jurnal pipeline (import, teste) — același fișier work.jsonl, fără autoload Composer. */
function aiwork_pipeline_log(array $entry, ?string $projectRoot = null): void
{
    ollama_work_log_append($entry, $projectRoot);
}

/** @return list<array<string, mixed>> */
function ollama_work_log_recent(int $limit = 100, ?string $projectRoot = null): array
{
    $path = ollama_work_log_path($projectRoot);
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $out = [];
    foreach (array_slice(array_reverse($lines), 0, max(1, min(400, $limit))) as $line) {
        $row = json_decode((string) $line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

function ollama_error_log_enabled(): bool
{
    $raw = strtolower(trim((string) ($_ENV['OLLAMA_ERROR_LOG'] ?? getenv('OLLAMA_ERROR_LOG') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** @param array<string, mixed> $entry */
function ollama_error_log_append(array $entry, ?string $projectRoot = null): void
{
    if (!ollama_error_log_enabled()) {
        return;
    }

    $entry['ts'] = $entry['ts'] ?? date('c');
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }

    $path = ollama_error_log_path($projectRoot);
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);

    if (!is_file($path) || filesize($path) <= 512000) {
        return;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || count($lines) <= 400) {
        return;
    }
    @file_put_contents($path, implode("\n", array_slice($lines, -300)) . "\n", LOCK_EX);
}

/** @return list<array<string, mixed>> */
function ollama_error_log_recent(int $limit = 50, ?string $projectRoot = null): array
{
    $path = ollama_error_log_path($projectRoot);
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $out = [];
    foreach (array_slice(array_reverse($lines), 0, max(1, min(200, $limit))) as $line) {
        $row = json_decode((string) $line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }

    return $out;
}

/** @param array<string, mixed> $entry */
function ollama_telemetry_record(array $entry, ?string $projectRoot = null): void
{
    $module = trim((string) ($entry['module'] ?? 'erp'));
    $path = ollama_telemetry_path($projectRoot);
    $data = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $prev = is_array($data[$module] ?? null) ? $data[$module] : [];
    $samples = is_array($prev['latency_samples'] ?? null) ? $prev['latency_samples'] : [];
    $latency = (int) ($entry['latency_ms'] ?? 0);
    if ($latency > 0) {
        $samples[] = $latency;
        $samples = array_slice($samples, -10);
    }

    $data[$module] = [
        'module' => $module,
        'url' => (string) ($entry['url'] ?? ($prev['url'] ?? '')),
        'model' => (string) ($entry['model'] ?? ($prev['model'] ?? '')),
        'vision_model' => (string) ($entry['vision_model'] ?? ($prev['vision_model'] ?? '')),
        'last_ok' => !empty($entry['ok']),
        'last_at' => date('c'),
        'last_latency_ms' => $latency,
        'last_error' => empty($entry['ok']) ? mb_substr((string) ($entry['error'] ?? ''), 0, 200) : '',
        'latency_samples' => $samples,
        'avg_latency_ms' => $samples !== [] ? (int) round(array_sum($samples) / count($samples)) : 0,
    ];

    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** @return array<string, array<string, mixed>> */
function ollama_telemetry_all(?string $projectRoot = null): array
{
    $path = ollama_telemetry_path($projectRoot);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}
