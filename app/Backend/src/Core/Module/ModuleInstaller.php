<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use RuntimeException;
use ZipArchive;

/**
 * Instalează un pachet modul extern (.zip) în admin/modules/{Folder}/.
 *
 * Structură pachet (rădăcină ZIP sau un singur subfolder):
 *   module.json          (obligatoriu)
 *   src/…                (opțional — clase Besoiu\Modules\{StudlyId}\)
 *   pages/index.php      (opțional — pagină admin pentru primul slug)
 *   migrations/*.sql     (opțional)
 */
final class ModuleInstaller
{
    private const RESERVED_IDS = [
        'auth', 'router', 'produse', 'furnizori', 'categorii', 'comenzi',
        'settings', 'dashboard', 'users', 'alerts', 'core', 'admin', 'system',
    ];

    public function __construct(
        private readonly string $adminRoot,
    ) {
    }

    /**
     * @param array{tmp_name: string, name?: string, error?: int, size?: int} $upload $_FILES['module_zip']
     * @return array{message: string, module_id: string, path: string, catalog_hint: bool}
     */
    public function installFromUpload(array $upload, bool $overwrite = false): array
    {
        $err = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload eșuat (cod ' . $err . ').');
        }

        $tmp = (string) ($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('Fișier ZIP lipsă.');
        }

        $name = (string) ($upload['name'] ?? 'module.zip');
        if (!preg_match('/\.zip$/i', $name)) {
            throw new RuntimeException('Doar arhive .zip sunt acceptate.');
        }

        $size = (int) ($upload['size'] ?? filesize($tmp) ?: 0);
        if ($size > 20 * 1024 * 1024) {
            throw new RuntimeException('ZIP prea mare (max 20 MB).');
        }

        return $this->installFromZipPath($tmp, $overwrite);
    }

    /**
     * @return array{message: string, module_id: string, path: string, catalog_hint: bool}
     */
    public function installFromZipPath(string $zipPath, bool $overwrite = false): array
    {
        if (!class_exists(ZipArchive::class)) {
            return $this->installFromZipPathWithoutZipArchive($zipPath, $overwrite);
        }

        $staging = $this->adminRoot . '/storage/modules/staging/' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0775, true) && !is_dir($staging)) {
            throw new RuntimeException('Nu pot crea staging.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('ZIP invalid sau corupt.');
            }

            $this->assertZipSafe($zip);
            if (!$zip->extractTo($staging)) {
                $zip->close();
                throw new RuntimeException('Extragere ZIP eșuată.');
            }
            $zip->close();

            return $this->finalizeFromStaging($staging, $overwrite);
        } catch (\Throwable $e) {
            $this->rrmdir($staging);
            throw $e;
        }
    }

    /**
     * Fallback Windows/Laragon când php_zip nu e activat în CLI.
     *
     * @return array{message: string, module_id: string, path: string, catalog_hint: bool}
     */
    private function installFromZipPathWithoutZipArchive(string $zipPath, bool $overwrite): array
    {
        $staging = $this->adminRoot . '/storage/modules/staging/' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0775, true) && !is_dir($staging)) {
            throw new RuntimeException('Nu pot crea staging.');
        }

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $localZip = $staging . DIRECTORY_SEPARATOR . 'upload.zip';
                if (!copy($zipPath, $localZip)) {
                    throw new RuntimeException('Nu pot copia ZIP pentru extragere.');
                }
                $psZip = str_replace("'", "''", $localZip);
                $psDest = str_replace("'", "''", $staging);
                $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command '
                    . '"Expand-Archive -LiteralPath \'' . $psZip . '\' -DestinationPath \'' . $psDest . '\' -Force"';
                exec($cmd, $out, $code);
                if ($code !== 0) {
                    $hint = trim(implode(' ', array_map('strval', $out)));
                    throw new RuntimeException(
                        'Extragere ZIP eșuată (PowerShell). Activează extensia php_zip în php.ini'
                        . ($hint !== '' ? ': ' . $hint : '.')
                    );
                }
            } else {
                $cmd = 'unzip -q -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($staging);
                exec($cmd, $out, $code);
                if ($code !== 0) {
                    throw new RuntimeException('Extragere ZIP eșuată (unzip). Instalează php-zip.');
                }
            }

            // safety: reject .. in extracted names
            $this->assertExtractedSafe($staging);

            return $this->finalizeFromStaging($staging, $overwrite);
        } catch (\Throwable $e) {
            $this->rrmdir($staging);
            throw $e;
        }
    }

    /**
     * @return array{message: string, module_id: string, path: string, catalog_hint: bool}
     */
    private function finalizeFromStaging(string $staging, bool $overwrite): array
    {
        try {
            $packageRoot = $this->findPackageRoot($staging);
            $manifestPath = $packageRoot . '/module.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException('Pachet invalid: lipsește module.json în rădăcină.');
            }

            $manifest = ModuleManifest::fromFile($manifestPath);
            $id = strtolower($manifest->id());
            $this->assertInstallableId($id);

            if ($manifest->type() !== 'optional') {
                throw new RuntimeException('Doar module type=optional pot fi instalate.');
            }

            $folderName = $this->folderNameFromId($id);
            $target = $this->adminRoot . '/modules/' . $folderName;

            if (is_dir($target)) {
                if (!$overwrite) {
                    throw new RuntimeException('Modulul „' . $id . '” există deja. Bifează „Suprascrie” ca să reinstalzi.');
                }
                $this->rrmdir($target);
            }

            if (!rename($packageRoot, $target)) {
                $this->recurseCopy($packageRoot, $target);
            }

            $this->ensureMinimalPage($target, $manifest);

            $provides = $this->buildProvides($manifest);
            $this->registerInstalledMap($id, $provides, $folderName);
            $this->setEnabledState($id, true);

            ModuleGate::resetCache();
            ModuleRegistry::resetInstance();
            ModuleRegistry::instance($this->adminRoot)->boot();

            $booted = ModuleRegistry::instance($this->adminRoot)->get($id);
            $migrationNote = '';
            if ($booted !== null) {
                try {
                    $booted->install();
                    $migrationNote = ' Migrări SQL rulate (dacă existau).';
                } catch (\Throwable $e) {
                    $migrationNote = ' Atenție migrări: ' . $e->getMessage();
                }
            }

            return [
                'message' => 'Modulul „' . $manifest->name() . '” (' . $id . ') a fost instalat și activat.' . $migrationNote,
                'module_id' => $id,
                'path' => $target,
                'catalog_hint' => true,
            ];
        } finally {
            $this->rrmdir($staging);
        }
    }

    private function assertExtractedSafe(string $staging): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($staging)));
            if (str_contains($rel, '..')) {
                throw new RuntimeException('ZIP nesigur după extragere: ' . $rel);
            }
        }
    }

    /** @return array<string, mixed> */
    private function buildProvides(ModuleManifest $manifest): array
    {
        $raw = $manifest->raw();
        $provides = is_array($raw['provides'] ?? null) ? $raw['provides'] : [];

        $slugs = $provides['slugs'] ?? ($raw['slugs'] ?? []);
        if (!is_array($slugs) || $slugs === []) {
            $slugs = [];
            foreach ($manifest->navItems() as $nav) {
                $href = (string) ($nav['href'] ?? '');
                if (preg_match('#/admin/([a-z0-9\-]+)#i', $href, $m)) {
                    $slugs[] = strtolower($m[1]);
                }
            }
        }
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            $slugs
        ))));

        if ($slugs === []) {
            $slugs = [$manifest->id()];
        }

        $paths = $provides['paths'] ?? [];
        if (!is_array($paths) || $paths === []) {
            $paths = [];
            foreach ($slugs as $slug) {
                $paths[] = '/admin/' . $slug;
                $paths[] = '/admin/public/' . $slug;
            }
        }

        return [
            'slugs' => $slugs,
            'paths' => array_values($paths),
            'crud_key' => isset($provides['crud_key']) ? $provides['crud_key'] : ($raw['crud_key'] ?? null),
            'api_scripts' => array_values($provides['api_scripts'] ?? []),
            'quick_actions' => array_values($provides['quick_actions'] ?? $slugs),
            'folder' => $this->folderNameFromId($manifest->id()),
            'workspace' => $this->resolveModuleWorkspace($manifest, $provides),
            'external' => true,
            'installed_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $provides
     */
    private function resolveModuleWorkspace(ModuleManifest $manifest, array $provides): string
    {
        $raw = $manifest->raw();
        $ws = (string) ($provides['workspace'] ?? $raw['workspace'] ?? '');
        if ($ws !== '' && \Besoiu\Core\Auth\AdminWorkspaceCatalog::resolveId($ws) !== null) {
            return (string) \Besoiu\Core\Auth\AdminWorkspaceCatalog::resolveId($ws);
        }

        return \Besoiu\Core\Auth\AdminWorkspaceCatalog::workspaceForModuleId($manifest->id(), $provides);
    }

    /** @param array<string, mixed> $provides */
    private function registerInstalledMap(string $id, array $provides, string $folder): void
    {
        $file = $this->installedMapPath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $map = [];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if (is_string($raw) && str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            $decoded = is_string($raw) ? json_decode($raw ?: '[]', true) : null;
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }

        $provides['folder'] = $folder;
        $map[$id] = $provides;

        file_put_contents(
            $file,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function setEnabledState(string $id, bool $enabled): void
    {
        $file = $this->adminRoot . '/storage/modules/state.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $state = ['updated_at' => date('c'), 'enabled' => []];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if (is_string($raw) && str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            $decoded = is_string($raw) ? json_decode($raw ?: '{}', true) : null;
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }
        if (!isset($state['enabled']) || !is_array($state['enabled'])) {
            $state['enabled'] = [];
        }
        $state['enabled'][$id] = $enabled;
        $state['updated_at'] = date('c');
        file_put_contents(
            $file,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function uninstallRegistration(string $id): void
    {
        $file = $this->installedMapPath();
        if (!is_file($file)) {
            return;
        }
        $raw = file_get_contents($file);
        if (is_string($raw) && str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $map = is_string($raw) ? json_decode($raw ?: '{}', true) : null;
        if (!is_array($map) || !isset($map[$id])) {
            return;
        }
        unset($map[$id]);
        file_put_contents(
            $file,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        ModuleGate::resetCache();
    }

    public function installedMapPath(): string
    {
        return $this->adminRoot . '/storage/modules/installed_map.json';
    }

    private function assertInstallableId(string $id): void
    {
        if ($id === '' || !preg_match('/^[a-z][a-z0-9_]{1,40}$/', $id)) {
            throw new RuntimeException('id modul invalid (a-z, 0-9, _, 2–41 chars).');
        }
        if (in_array($id, self::RESERVED_IDS, true)) {
            throw new RuntimeException('id „' . $id . '” e rezervat CORE — nu poate fi instalat ca plugin.');
        }
    }

    private function folderNameFromId(string $id): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $id) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $out .= ucfirst(strtolower($part));
        }

        return $out !== '' ? $out : 'Module';
    }

    private function findPackageRoot(string $staging): string
    {
        if (is_file($staging . '/module.json')) {
            return $staging;
        }

        $dirs = array_values(array_filter(scandir($staging) ?: [], static function (string $d) use ($staging): bool {
            return $d !== '.' && $d !== '..' && is_dir($staging . '/' . $d);
        }));

        if (count($dirs) === 1 && is_file($staging . '/' . $dirs[0] . '/module.json')) {
            return $staging . '/' . $dirs[0];
        }

        foreach ($dirs as $d) {
            if (is_file($staging . '/' . $d . '/module.json')) {
                return $staging . '/' . $d;
            }
        }

        throw new RuntimeException('Nu găsesc module.json în arhivă.');
    }

    private function assertZipSafe(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || preg_match('#^[a-zA-Z]:#', $name) || str_starts_with($name, '/')) {
                throw new RuntimeException('ZIP nesigur (path traversal): ' . $name);
            }
        }
        if ($zip->numFiles > 500) {
            throw new RuntimeException('ZIP prea multe fișiere (max 500).');
        }
    }

    private function ensureMinimalPage(string $target, ModuleManifest $manifest): void
    {
        $pagesDir = $target . '/pages';
        $index = $pagesDir . '/index.php';
        if (is_file($index)) {
            return;
        }
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0775, true);
        }

        $name = htmlspecialchars($manifest->name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = htmlspecialchars($manifest->id(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = <<<PHP
<?php
declare(strict_types=1);
?>
<div class="box p-6">
    <h1 class="text-xl font-semibold mb-2">{$name}</h1>
    <p class="text-sm opacity-70">Modul extern <code>{$id}</code> — instalat și activ. Înlocuiește <code>pages/index.php</code> din pachet cu UI-ul tău.</p>
</div>
PHP;
        file_put_contents($index, $html);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dst . '/' . $item;
            if (is_dir($from)) {
                $this->recurseCopy($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
