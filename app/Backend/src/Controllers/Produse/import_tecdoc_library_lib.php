<?php

declare(strict_types=1);

/**
 * Bibliotecă permanentă CSV TecDoc UTF8 (TableUseCarsForParts).
 * Fișierele stau în admin/storage/tecdoc/ — fără re-upload la fiecare preview/import.
 */

if (!function_exists('import_tecdoc_library_dir')) {
    function import_tecdoc_library_dir(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/tecdoc';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException('Nu am putut crea folderul bibliotecă TecDoc.');
        }

        return $dir;
    }
}

if (!function_exists('import_tecdoc_library_is_tecdoc_name')) {
    function import_tecdoc_library_is_tecdoc_name(string $filename): bool
    {
        $name = strtolower(basename($filename));

        return str_contains($name, 'tableusecarsforparts')
            || str_contains($name, 'universal-csv-data');
    }
}

if (!function_exists('import_tecdoc_library_safe_basename')) {
    function import_tecdoc_library_safe_basename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $base = preg_replace('/[^A-Za-z0-9._\\-\\[\\] ]+/u', '_', $base) ?? 'tecdoc.csv';

        return trim($base) !== '' ? trim($base) : 'tecdoc.csv';
    }
}

if (!function_exists('import_tecdoc_library_brand_from_name')) {
    function import_tecdoc_library_brand_from_name(string $filename): string
    {
        if (preg_match('/-([A-Za-z0-9]+)-ro\\.csv$/i', basename($filename), $m)) {
            return strtoupper((string) $m[1]);
        }

        return '';
    }
}

if (!function_exists('import_tecdoc_library_file_path')) {
    function import_tecdoc_library_file_path(string $libraryKey): string
    {
        return import_tecdoc_library_dir() . '/' . import_tecdoc_library_safe_basename($libraryKey);
    }
}

if (!function_exists('import_tecdoc_library_promote_file')) {
    /** Copiază un fișier uploadat în biblioteca permanentă (dacă lipsește sau e mai nou). */
    function import_tecdoc_library_promote_file(string $sourcePath, string $originalName): ?string
    {
        if (!is_file($sourcePath) || !import_tecdoc_library_is_tecdoc_name($originalName)) {
            return null;
        }

        $destName = import_tecdoc_library_safe_basename($originalName);
        $destPath = import_tecdoc_library_dir() . '/' . $destName;

        if (is_file($destPath)) {
            $srcSize = filesize($sourcePath) ?: 0;
            $dstSize = filesize($destPath) ?: 0;
            $srcMtime = filemtime($sourcePath) ?: 0;
            $dstMtime = filemtime($destPath) ?: 0;
            if ($srcSize === $dstSize && $srcMtime <= $dstMtime) {
                return $destPath;
            }
        }

        if (!@copy($sourcePath, $destPath)) {
            return null;
        }

        @touch($destPath, filemtime($sourcePath) ?: time());

        return $destPath;
    }
}

if (!function_exists('import_tecdoc_library_sync_from_uploads')) {
    /** Promovează upload-urile TecDoc completate în biblioteca permanentă. */
    function import_tecdoc_library_sync_from_uploads(): int
    {
        if (!function_exists('list_uploaded_import_files')) {
            require_once __DIR__ . '/import_uploaded_files_lib.php';
        }

        $promoted = 0;
        foreach (list_uploaded_import_files() as $meta) {
            if (empty($meta['completed'])) {
                continue;
            }

            $name = (string) ($meta['original_name'] ?? '');
            $fileId = (string) ($meta['file_id'] ?? '');
            if ($name === '' || $fileId === '') {
                continue;
            }

            $kind = (string) ($meta['file_kind'] ?? '');
            if ($kind !== 'tecdoc' && !import_tecdoc_library_is_tecdoc_name($name)) {
                continue;
            }

            $source = import_temp_file_path($fileId);
            if (import_tecdoc_library_promote_file($source, $name) !== null) {
                ++$promoted;
            }
        }

        return $promoted;
    }
}

if (!function_exists('list_tecdoc_library_files')) {
    /** @return list<array{key:string,name:string,path:string,size:int,updated_at:string,brand:string,source:string}> */
    function list_tecdoc_library_files(bool $syncUploads = true): array
    {
        if ($syncUploads) {
            import_tecdoc_library_sync_from_uploads();
        }

        $dir = import_tecdoc_library_dir();
        $entries = [];
        $seen = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            if (!is_file($path) || !import_tecdoc_library_is_tecdoc_name($name)) {
                continue;
            }

            $key = import_tecdoc_library_safe_basename($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $entries[] = [
                'key' => $key,
                'name' => $name,
                'path' => $path,
                'size' => (int) (filesize($path) ?: 0),
                'updated_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
                'brand' => import_tecdoc_library_brand_from_name($name),
                'source' => 'library',
            ];
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $entries;
    }
}

if (!function_exists('import_tecdoc_library_dedupe_files')) {
    /** @param list<array{path:string,name:string}> $files */
    function import_tecdoc_library_dedupe_files(array $files): array
    {
        $out = [];
        $seen = [];
        foreach ($files as $file) {
            $key = strtolower(import_tecdoc_library_safe_basename((string) ($file['name'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $file;
        }

        return $out;
    }
}

if (!function_exists('import_collect_import_sources')) {
    /**
     * Rezolvă surse import: TecDoc din bibliotecă permanentă + upload opțional; furnizori doar din upload.
     *
     * @param list<array<string,mixed>> $filesMeta
     * @param array<string,mixed> $options tecdoc_library_keys?: list<string>|null
     * @return array{
     *   supplier_files:list<array{path:string,name:string,file_id?:string}>,
     *   tecdoc_files:list<array{path:string,name:string,file_id?:string,source?:string}>,
     *   generic_files:list<array{path:string,name:string,file_id?:string}>,
     *   missing_files:list<string>
     * }
     */
    function import_collect_import_sources(array $filesMeta, array $options = []): array
    {
        import_tecdoc_library_sync_from_uploads();

        $supplierFiles = [];
        $tecdocFiles = [];
        $genericFiles = [];
        $missingFiles = [];

        $libraryKeys = $options['tecdoc_library_keys'] ?? null;
        if ($libraryKeys !== null && !is_array($libraryKeys)) {
            $libraryKeys = null;
        }

        $libraryEntries = list_tecdoc_library_files(false);
        if ($libraryEntries !== []) {
            $useAll = $libraryKeys === null || $libraryKeys === [];
            foreach ($libraryEntries as $entry) {
                if (!$useAll && !in_array($entry['key'], $libraryKeys, true)) {
                    continue;
                }
                $tecdocFiles[] = [
                    'path' => (string) $entry['path'],
                    'name' => (string) $entry['name'],
                    'source' => 'library',
                ];
            }
        }

        foreach ($filesMeta as $fileMeta) {
            $fileId = (string) ($fileMeta['file_id'] ?? '');
            $originalName = (string) ($fileMeta['original_name'] ?? '');
            if ($fileId === '' || $originalName === '') {
                continue;
            }

            $path = import_temp_file_path($fileId);
            if (!is_file($path)) {
                $missingFiles[] = $originalName;
                continue;
            }

            $kind = import_resolve_upload_file_kind($path, $originalName, $fileMeta);
            $entry = ['path' => $path, 'name' => $originalName, 'file_id' => $fileId];

            if ($kind === 'tecdoc') {
                import_tecdoc_library_promote_file($path, $originalName);
                $tecdocFiles[] = array_merge($entry, ['source' => 'upload']);
            } elseif (str_starts_with($kind, 'supplier:')) {
                $supplierFiles[] = $entry;
            } else {
                $genericFiles[] = $entry;
            }
        }

        $tecdocFiles = import_tecdoc_library_dedupe_files($tecdocFiles);

        return [
            'supplier_files' => $supplierFiles,
            'tecdoc_files' => $tecdocFiles,
            'generic_files' => $genericFiles,
            'missing_files' => $missingFiles,
        ];
    }
}
