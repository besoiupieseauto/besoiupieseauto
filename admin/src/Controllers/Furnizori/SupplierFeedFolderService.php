<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Director local per furnizor — depozit fisiere CSV (SFTP / upload manual).
 */
final class SupplierFeedFolderService
{
    private const SUBDIR = 'supplier_feeds';

    public function baseDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/' . self::SUBDIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public function slugFromSupplier(string $code, int $randomnId): string
    {
        $code = strtoupper(trim($code));
        if ($code !== '') {
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $code) ?: 'supplier';

            return strtolower($safe);
        }

        return 'supplier_' . max(1, $randomnId);
    }

    public function folderPath(string $code, int $randomnId): string
    {
        return $this->baseDir() . DIRECTORY_SEPARATOR . $this->slugFromSupplier($code, $randomnId);
    }

    public function folderRelative(string $code, int $randomnId): string
    {
        return 'storage/' . self::SUBDIR . '/' . $this->slugFromSupplier($code, $randomnId);
    }

    /** @return array{path:string,relative:string,exists:bool,created:bool,slug:string} */
    public function ensureFolder(string $code, int $randomnId): array
    {
        $path = $this->folderPath($code, $randomnId);
        $exists = is_dir($path);
        $created = false;
        if (!$exists) {
            $created = @mkdir($path, 0775, true);
            $exists = is_dir($path);
        }

        return [
            'path' => $path,
            'relative' => $this->folderRelative($code, $randomnId),
            'exists' => $exists,
            'created' => $created,
            'slug' => $this->slugFromSupplier($code, $randomnId),
        ];
    }

    public static function isFeedFilename(string $name): bool
    {
        $lower = strtolower(trim($name));

        return $lower !== '' && (bool) preg_match('/\.(csv|txt|tsv|xlsx)$/i', $lower);
    }

    /** @return array<int, array<string, mixed>> */
    public function listFiles(string $code, int $randomnId): array
    {
        $info = $this->ensureFolder($code, $randomnId);
        if (!$info['exists']) {
            return [];
        }

        $entries = [];
        foreach (scandir($info['path']) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $info['path'] . DIRECTORY_SEPARATOR . $name;
            if (!is_file($full)) {
                continue;
            }
            $entries[] = [
                'name' => $name,
                'type' => 'file',
                'size' => filesize($full) ?: 0,
                'source' => 'local_feed',
                'local_path' => $full,
                'mtime' => filemtime($full) ?: 0,
            ];
        }

        usort($entries, static fn (array $a, array $b): int => ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0)));

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    public function listFeedCsvFiles(string $code, int $randomnId): array
    {
        return array_values(array_filter(
            $this->listFiles($code, $randomnId),
            static fn (array $entry): bool => self::isFeedFilename((string) ($entry['name'] ?? ''))
        ));
    }

    /**
     * Copiază CSV-urile din staging import (storage/imports) în folderul furnizorului.
     *
     * @return array{copied:array<int,string>,skipped:array<int,string>,folder:string,path:string}
     */
    public function mirrorImportFilesForSupplier(string $code, int $randomnId): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $randomnId <= 0) {
            return ['copied' => [], 'skipped' => [], 'folder' => '', 'path' => ''];
        }

        if (!function_exists('list_uploaded_import_files')) {
            require_once dirname(__DIR__) . '/Produse/import_uploaded_files_lib.php';
        }
        if (!function_exists('import_supplier_file_matches_code')) {
            require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';
        }

        $folder = $this->ensureFolder($code, $randomnId);
        $copied = [];
        $skipped = [];

        foreach (list_uploaded_import_files() as $meta) {
            if (empty($meta['completed'])) {
                continue;
            }

            $name = trim((string) ($meta['original_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $kind = (string) ($meta['file_kind'] ?? $meta['resolved_role'] ?? '');
            if (!import_supplier_file_matches_code($code, $name, $kind)) {
                continue;
            }

            $fileId = (string) ($meta['file_id'] ?? '');
            if ($fileId === '') {
                continue;
            }

            $source = import_upload_temp_file_path($fileId);
            if (!is_file($source)) {
                continue;
            }

            $safeName = $this->safeFeedFilename($name);
            $dest = $folder['path'] . DIRECTORY_SEPARATOR . $safeName;
            $sourceSize = (int) (filesize($source) ?: 0);
            $destSize = is_file($dest) ? (int) (filesize($dest) ?: 0) : 0;

            if ($destSize > 0 && $destSize === $sourceSize) {
                $skipped[] = $safeName;
                continue;
            }

            if (!@copy($source, $dest)) {
                continue;
            }

            @touch($dest, filemtime($source) ?: time());
            $copied[] = $safeName;
        }

        return [
            'copied' => $copied,
            'skipped' => $skipped,
            'folder' => (string) ($folder['relative'] ?? ''),
            'path' => (string) ($folder['path'] ?? ''),
        ];
    }

    private function safeFeedFilename(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '_', $name));
        $name = preg_replace('/[^\p{L}\p{N}\s._\-()+]/u', '_', $name) ?? 'feed.csv';
        $name = trim($name, '._-');

        return $name !== '' ? $name : 'feed.csv';
    }
}
