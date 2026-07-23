<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use RuntimeException;
use ZipArchive;

/**
 * Exportă un modul instalat ca ZIP — șablon pentru module noi.
 *
 * Structură arhivă:
 *   {id}/
 *     module.json
 *     pages/… (stub dacă lipsea)
 *     src/… (dacă există)
 *     HOW_TO_CLONE.md
 *     optional_modules.snippet.php
 */
final class ModuleExporter
{
    public function __construct(
        private readonly string $adminRoot,
    ) {
    }

    /**
     * @return array{path: string, filename: string, module_id: string, bytes: int}
     */
    public function export(string $moduleId): array
    {
        $moduleId = strtolower(trim($moduleId));
        if ($moduleId === '') {
            throw new RuntimeException('module_id lipsă.');
        }

        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $manifests = $registry->allManifests();

        if (!isset($manifests[$moduleId])) {
            throw new RuntimeException('Modulul „' . $moduleId . '” nu e instalat (folder lipsă).');
        }

        $source = $manifests[$moduleId]->path();
        $real = realpath($source);
        $modulesRoot = realpath($this->adminRoot . '/modules') ?: ($this->adminRoot . '/modules');
        if ($real === false || !is_dir($real) || !str_starts_with($real, $modulesRoot)) {
            throw new RuntimeException('Cale modul invalidă.');
        }
        if (!is_file($real . DIRECTORY_SEPARATOR . 'module.json')) {
            throw new RuntimeException('Lipsește module.json în modul.');
        }

        $exportsDir = $this->adminRoot . '/storage/modules/exports';
        if (!is_dir($exportsDir) && !mkdir($exportsDir, 0775, true) && !is_dir($exportsDir)) {
            throw new RuntimeException('Nu pot crea storage/modules/exports.');
        }

        $staging = $exportsDir . '/staging_' . bin2hex(random_bytes(6));
        $packageRoot = $staging . '/' . $moduleId;
        if (!mkdir($packageRoot, 0775, true) && !is_dir($packageRoot)) {
            throw new RuntimeException('Nu pot crea staging export.');
        }

        try {
            $this->recurseCopy($real, $packageRoot);
            $this->enrichScaffold($packageRoot, $moduleId, $manifests[$moduleId]);

            $version = preg_replace('/[^a-zA-Z0-9._-]/', '', $manifests[$moduleId]->version()) ?: '1.0.0';
            $filename = $moduleId . '-v' . $version . '.zip';
            $zipPath = $exportsDir . '/' . $filename;

            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            $this->createZip($staging, $zipPath);
            $this->rrmdir($staging);

            clearstatcache(true, $zipPath);
            if (!is_file($zipPath)) {
                throw new RuntimeException('ZIP export nu a fost creat.');
            }

            return [
                'path' => $zipPath,
                'filename' => $filename,
                'module_id' => $moduleId,
                'bytes' => (int) filesize($zipPath),
            ];
        } catch (\Throwable $e) {
            $this->rrmdir($staging);
            throw $e;
        }
    }

    /**
     * Exportă șablonul MVP Kit (arhitectură completă) sau hello_demo.
     *
     * @return array{path: string, filename: string, module_id: string, bytes: int}
     */
    public function exportExampleTemplate(): array
    {
        $candidates = [
            $this->adminRoot . '/modules/MvpKit',
            $this->adminRoot . '/modules/_examples/mvp_kit',
            $this->adminRoot . '/modules/_examples/hello_demo',
        ];
        $example = null;
        foreach ($candidates as $cand) {
            if (is_dir($cand) && is_file($cand . '/module.json')) {
                $example = $cand;
                break;
            }
        }
        if ($example === null) {
            throw new RuntimeException('Șablonul MVP Kit / hello_demo lipsește.');
        }

        $exportsDir = $this->adminRoot . '/storage/modules/exports';
        if (!is_dir($exportsDir) && !mkdir($exportsDir, 0775, true) && !is_dir($exportsDir)) {
            throw new RuntimeException('Nu pot crea storage/modules/exports.');
        }

        $staging = $exportsDir . '/staging_' . bin2hex(random_bytes(6));
        $packageRoot = $staging . '/mvp_kit';
        if (!mkdir($packageRoot, 0775, true) && !is_dir($packageRoot)) {
            throw new RuntimeException('Nu pot crea staging.');
        }

        try {
            $this->recurseCopy($example, $packageRoot);
            // Normalize id to mvp_kit for template download
            $manifestPath = $packageRoot . '/module.json';
            if (is_file($manifestPath)) {
                $data = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($data)) {
                    $data['id'] = 'mvp_kit';
                    $data['name'] = $data['name'] ?? 'MVP Kit (șablon modul)';
                    file_put_contents(
                        $manifestPath,
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                }
            }
            $manifest = ModuleManifest::fromFile($manifestPath);
            $this->enrichScaffold($packageRoot, 'mvp_kit', $manifest);

            $filename = 'mvp_kit-template.zip';
            $zipPath = $exportsDir . '/' . $filename;
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            $this->createZip($staging, $zipPath);
            $this->rrmdir($staging);

            return [
                'path' => $zipPath,
                'filename' => $filename,
                'module_id' => 'mvp_kit',
                'bytes' => (int) filesize($zipPath),
            ];
        } catch (\Throwable $e) {
            $this->rrmdir($staging);
            throw $e;
        }
    }

    private function enrichScaffold(string $packageRoot, string $moduleId, ModuleManifest $manifest): void
    {
        $pagesDir = $packageRoot . '/pages';
        if (!is_dir($pagesDir)) {
            mkdir($pagesDir, 0775, true);
        }
        $index = $pagesDir . '/index.php';
        if (!is_file($index)) {
            $name = htmlspecialchars($manifest->name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $idEsc = htmlspecialchars($moduleId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            file_put_contents($index, <<<PHP
<?php
declare(strict_types=1);
?>
<div class="box p-6" style="max-width:720px">
    <h1 class="text-xl font-semibold mb-2">{$name}</h1>
    <p class="text-sm opacity-80 mb-4">
        Pagină stub pentru modulul <code>{$idEsc}</code>. Înlocuiește cu UI-ul tău.
        La unplug din Setări → Module, ruta și meniul dispar.
    </p>
</div>

PHP);
        }

        $meta = ModuleGate::optionalMap()[$moduleId] ?? [];
        $slugs = $meta['slugs'] ?? null;
        if (!is_array($slugs) || $slugs === []) {
            $slugs = array_values(array_filter(array_map(
                static fn (array $n): string => (string) ($n['id'] ?? ''),
                $manifest->navItems()
            )));
        }
        if ($slugs === []) {
            $slugs = [$moduleId];
        }

        $paths = $meta['paths'] ?? [];
        if (!is_array($paths) || $paths === []) {
            $paths = [];
            foreach ($slugs as $slug) {
                $slug = (string) $slug;
                if ($slug === '') {
                    continue;
                }
                $paths[] = '/admin/' . $slug;
                $paths[] = '/admin/public/' . $slug;
            }
        }

        $provides = [
            'slugs' => $slugs,
            'paths' => $paths,
            'crud_key' => $meta['crud_key'] ?? null,
            'api_scripts' => $meta['api_scripts'] ?? [],
            'quick_actions' => $meta['quick_actions'] ?? [],
            'folder' => $meta['folder'] ?? null,
        ];

        $snippet = "<?php\n\n// Fragment pentru app/Backend/config/optional_modules.php (sursă canonică; admin/config/optional_modules.php e doar mirror)\n// Copiază cheia de mai jos în array-ul returnat.\n\nreturn [\n    "
            . var_export($moduleId, true) . ' => ' . var_export([
                'slugs' => $provides['slugs'],
                'paths' => $provides['paths'],
                'crud_key' => $provides['crud_key'],
                'api_scripts' => $provides['api_scripts'],
                'quick_actions' => $provides['quick_actions'],
                'folder' => $provides['folder'],
            ], true)
            . ",\n];\n";
        file_put_contents($packageRoot . '/optional_modules.snippet.php', $snippet);

        $howTo = <<<MD
# Cum creezi / clonezi un modul (START)

## Cel mai clar drum (recomandat)

Nu redenumi manual. Pe PC-ul cu proiectul:

```bash
php admin/generate_module_v2.php Note --workspace=company
```

Apoi editezi doar:
- `src/NoteService.php` — logica
- `pages/index.php` — ecranul
- `api/note_endpoint.php` — API

Ghid complet în proiect: `admin/modules/START_HERE.md`

## Dacă ai doar acest ZIP (fără generator)

1. Dezarhivează ZIP-ul (trebuie să vezi `module.json` imediat).
2. Redenumește folderul + `id` în `module.json` + namespace în `src/`.
3. Actualizează `nav` / `provides.slugs` / `provides.paths` / `api_scripts`.
4. Copiază în `admin/modules/{NumeStudly}/` SAU Instalează ZIP din Setări → Module.
5. Activează → deschide `/admin/{slug}`.

## Structură

```
{id}/
  module.json
  src/XxxModule.php + XxxService.php
  pages/index.php
  assets/
  api/
  migrations/   (opțional)
  HOW_TO_CLONE.md
```

Checklist: `admin/modules/CHECKLIST_LANSARE.md`
MD;
        file_put_contents($packageRoot . '/HOW_TO_CLONE.md', $howTo);
    }

    private function createZip(string $stagingDir, string $zipPath): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Nu pot crea arhiva ZIP.');
            }
            $this->addDirToZip($zip, $stagingDir, '');
            $zip->close();

            return;
        }

        // Fallback Windows: Compress-Archive
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $psSrc = str_replace("'", "''", $stagingDir . '\\*');
            $psDest = str_replace("'", "''", $zipPath);
            $cmd = 'powershell -NoProfile -Command "Compress-Archive -Path \'' . $psSrc . '\' -DestinationPath \'' . $psDest . '\' -Force"';
            exec($cmd, $out, $code);
            if ($code !== 0 || !is_file($zipPath)) {
                throw new RuntimeException('Export ZIP eșuat (PowerShell). Activează extensia php_zip.');
            }

            return;
        }

        $cmd = 'cd ' . escapeshellarg($stagingDir) . ' && zip -r -q ' . escapeshellarg($zipPath) . ' .';
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($zipPath)) {
            throw new RuntimeException('Export ZIP eșuat (zip). Instalează php-zip.');
        }
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $item;
            $local = $prefix === '' ? $item : ($prefix . '/' . $item);
            if (is_dir($full)) {
                $zip->addEmptyDir($local);
                $this->addDirToZip($zip, $full, $local);
            } else {
                $zip->addFile($full, $local);
            }
        }
    }

    private function recurseCopy(string $src, string $dst): void
    {
        if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
            throw new RuntimeException('Nu pot crea ' . $dst);
        }
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                $this->recurseCopy($from, $to);
            } else {
                if (!copy($from, $to)) {
                    throw new RuntimeException('Copiere eșuată: ' . $item);
                }
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
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
