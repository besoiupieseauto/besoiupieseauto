<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Listeaza fisiere de import deja primite pe server (sync agent / upload manual).
 */
final class SupplierImportFilesBrowser
{
    /** @param array<string, mixed>|string $furnizorOrCode @param array<string, mixed> $options @return array{success:bool,message:string,path:string,entries:array<int,array<string,mixed>>,engine?:string,mode?:string,local_path?:string,remote_path?:string,remote_host?:string,connection_type?:string} */
    public function browse(array|string $furnizorOrCode, string $requestedPath = '', array $options = []): array
    {
        $connectionType = '';
        $remotePath = '';
        $connHost = '';
        $furnizor = null;

        if (is_array($furnizorOrCode)) {
            $supplierCode = strtoupper(trim((string) ($furnizorOrCode['code'] ?? '')));
            $randomnId = (int) ($furnizorOrCode['randomn_id'] ?? 0);
            $connectionType = strtolower(trim((string) ($furnizorOrCode['connection_type'] ?? '')));
            $remotePath = trim((string) ($furnizorOrCode['conn_remote_path'] ?? ''));
            $connHost = trim((string) ($furnizorOrCode['conn_host'] ?? ''));
            $furnizor = $furnizorOrCode;
        } else {
            $supplierCode = strtoupper(trim($furnizorOrCode));
            $randomnId = 0;
        }

        $folderService = new SupplierFeedFolderService();
        $feedFolderRelative = $randomnId > 0
            ? $folderService->folderRelative($supplierCode, $randomnId)
            : 'storage/supplier_feeds/' . strtolower($supplierCode !== '' ? $supplierCode : 'feed');

        $mirrorResult = null;
        $autoMirror = !array_key_exists('auto_mirror', $options)
            || filter_var($options['auto_mirror'], FILTER_VALIDATE_BOOLEAN);
        if ($autoMirror && $randomnId > 0 && $supplierCode !== '') {
            $mirrorResult = $folderService->mirrorImportFilesForSupplier($supplierCode, $randomnId);
        }

        $entries = $this->listEntries($supplierCode);
        $localEntries = $randomnId > 0
            ? $folderService->listFiles($supplierCode, $randomnId)
            : [];
        $seen = [];
        foreach ($entries as $entry) {
            $seen[$this->entryKey($entry)] = true;
        }
        foreach ($localEntries as $entry) {
            $key = $this->entryKey($entry);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $entries[] = $entry;
            $seen[$key] = true;
        }

        $includeRemote = !array_key_exists('include_remote', $options)
            || filter_var($options['include_remote'], FILTER_VALIDATE_BOOLEAN);
        $isRemoteConnection = in_array($connectionType, ['sftp', 'ftp'], true);
        $path = $feedFolderRelative;
        $engine = 'local';
        $mode = 'Fisiere in folder furnizor (upload manual / sync agent)';
        $remoteListError = '';

        if ($isRemoteConnection && $includeRemote) {
            $engine = $connectionType;
            $path = $remotePath !== '' ? $remotePath : $feedFolderRelative;
            $mode = $remotePath !== ''
                ? strtoupper($connectionType) . ' remote → ' . $feedFolderRelative
                : strtoupper($connectionType) . ' — configureaza Folder remote; fisiere locale: ' . $feedFolderRelative;

            if ($furnizor !== null && $connHost !== '' && FtpConnectionClient::isAvailable()) {
                $resolved = function_exists('import_furnizori_resolve_credentials')
                    ? import_furnizori_resolve_credentials($furnizor)
                    : $furnizor;
                $browsePath = $requestedPath !== '' && $requestedPath !== '/'
                    ? $requestedPath
                    : ($remotePath !== '' ? $remotePath : '/');
                $remoteResult = (new FtpConnectionClient())->configure($resolved)->browse($browsePath, $remotePath);
                if (!empty($remoteResult['success'])) {
                    $path = (string) ($remoteResult['path'] ?? $path);
                    foreach ((array) ($remoteResult['entries'] ?? []) as $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        $entry['source'] = $connectionType . '_remote';
                        $key = $this->entryKey($entry);
                        if ($key === '' || isset($seen[$key])) {
                            continue;
                        }
                        $entries[] = $entry;
                        $seen[$key] = true;
                    }
                } else {
                    $remoteListError = trim((string) ($remoteResult['message'] ?? 'Listare remote indisponibila.'));
                }
            } elseif ($isRemoteConnection && $connHost === '') {
                $remoteListError = 'Completeaza host SFTP/FTP pentru listare remote.';
            }
        } else {
            $path = $feedFolderRelative;
            $mode = 'Folder local furnizor + import manual / sync agent';
        }

        usort($entries, static fn (array $a, array $b): int => strcmp((string) ($b['name'] ?? ''), (string) ($a['name'] ?? '')));

        if ($requestedPath !== '' && $requestedPath !== '/') {
            $fileName = basename(str_replace('\\', '/', $requestedPath));
            foreach ($entries as $entry) {
                if (($entry['name'] ?? '') === $fileName) {
                    return [
                        'success' => true,
                        'message' => 'OK',
                        'path' => $path,
                        'local_path' => $feedFolderRelative,
                        'remote_path' => $remotePath,
                        'remote_host' => $connHost,
                        'connection_type' => $connectionType,
                        'entries' => $entries,
                        'engine' => $engine,
                        'mode' => $mode,
                        'remote_list_error' => $remoteListError,
                        'preview' => $this->previewEntry($entry),
                    ];
                }
            }
        }

        $payload = [
            'success' => true,
            'message' => 'OK',
            'path' => $path,
            'local_path' => $feedFolderRelative,
            'remote_path' => $remotePath,
            'remote_host' => $connHost,
            'connection_type' => $connectionType,
            'entries' => $entries,
            'engine' => $engine,
            'mode' => $mode,
            'remote_list_error' => $remoteListError,
        ];

        if (is_array($mirrorResult)) {
            $payload['mirror'] = $mirrorResult;
            $copiedCount = count($mirrorResult['copied'] ?? []);
            if ($copiedCount > 0) {
                $payload['message'] = $copiedCount . ' fisier(e) copiate in folderul local.';
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $entry */
    private function entryKey(array $entry): string
    {
        $name = (string) ($entry['name'] ?? '');
        $source = (string) ($entry['source'] ?? 'import');

        return $name === '' ? '' : $source . ':' . $name;
    }

    /** @return array<int, array<string, mixed>> */
    private function listEntries(string $supplierCode): array
    {
        if (!function_exists('list_uploaded_import_files')) {
            return [];
        }

        $entries = [];
        foreach (list_uploaded_import_files() as $meta) {
            $kind = (string) ($meta['file_kind'] ?? $meta['resolved_role'] ?? '');
            $name = (string) ($meta['original_name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (!import_supplier_file_matches_code($supplierCode, $name, $kind)) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'type' => 'file',
                'size' => (int) ($meta['size'] ?? 0),
                'file_id' => (string) ($meta['file_id'] ?? ''),
                'source' => 'import',
                'completed' => !empty($meta['completed']),
            ];
        }

        usort($entries, static fn ($a, $b) => strcmp((string) ($b['name'] ?? ''), (string) ($a['name'] ?? '')));

        return $entries;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed>|null */
    private function previewEntry(array $entry): ?array
    {
        $path = '';
        if (($entry['source'] ?? '') === 'local_feed') {
            $path = (string) ($entry['local_path'] ?? '');
        } else {
            $fileId = (string) ($entry['file_id'] ?? '');
            if ($fileId === '' || !function_exists('import_temp_file_path')) {
                return null;
            }
            $path = import_temp_file_path($fileId);
        }

        if ($path === '' || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path, false, null, 0, 4096);

        return [
            'path' => (string) ($entry['name'] ?? ''),
            'bytes' => filesize($path) ?: 0,
            'content' => is_string($content) ? $content : '',
            'mime' => 'text/csv',
        ];
    }
}
