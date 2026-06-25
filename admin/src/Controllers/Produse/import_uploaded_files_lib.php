<?php
declare(strict_types=1);

/**
 * Listare rapidă fișiere import uploadate — fără încărcarea importproduse.php (TecDoc, joburi etc.).
 */
if (!function_exists('import_upload_temp_dir')) {
    function import_upload_temp_dir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('import_upload_temp_file_path')) {
    function import_upload_temp_file_path(string $fileId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $fileId) ?: md5($fileId);

        return import_upload_temp_dir() . '/' . $safe . '.part';
    }
}

if (!function_exists('import_temp_file_path')) {
    function import_temp_file_path(string $fileId): string
    {
        return import_upload_temp_file_path($fileId);
    }
}

if (!function_exists('list_uploaded_import_files')) {
    /** @return array<int, array<string, mixed>> */
    function list_uploaded_import_files(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $dir = import_upload_temp_dir();
        $metaFiles = glob($dir . '/*.json') ?: [];
        $result = [];

        foreach ($metaFiles as $metaPath) {
            $raw = @file_get_contents($metaPath);
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $meta = json_decode($raw, true);
            if (!is_array($meta)) {
                continue;
            }

            $fileId = (string) ($meta['file_id'] ?? '');
            if ($fileId === '') {
                continue;
            }

            $partPath = import_upload_temp_file_path($fileId);
            if (!is_file($partPath)) {
                continue;
            }

            $originalName = (string) ($meta['original_name'] ?? '');
            $kind = (string) ($meta['file_kind'] ?? '');
            if ($kind === '') {
                $kind = (string) ($meta['upload_role'] ?? 'unknown');
            }

            $result[] = [
                'file_id' => $fileId,
                'original_name' => $originalName,
                'size' => (int) (filesize($partPath) ?: 0),
                'completed' => !empty($meta['completed']),
                'total_chunks' => (int) ($meta['total_chunks'] ?? 0),
                'last_chunk' => (int) ($meta['last_chunk'] ?? 0),
                'updated_at' => date('Y-m-d H:i:s', filemtime($metaPath) ?: time()),
                'upload_role' => (string) ($meta['upload_role'] ?? ''),
                'resolved_role' => (string) ($meta['resolved_role'] ?? $meta['upload_role'] ?? ''),
                'file_kind' => $kind,
                'file_kind_label' => $kind,
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
        $cache = $result;

        return $result;
    }
}
