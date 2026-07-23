<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Module\ModuleExporter;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Module\ModuleInstaller;
use Besoiu\Core\Module\ModuleRegistry;
use RuntimeException;

/**
 * Gestionare module opționale din Settings (enable / disable / uninstall / restore stub).
 */
final class ModulesAdminService
{
    private readonly string $adminRoot;

    public function __construct(?string $adminRoot = null)
    {
        $this->adminRoot = $adminRoot ?? dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $manifests = $registry->allManifests();
        $map = ModuleGate::optionalMap();

        /** @var list<array{id: string, name: string, note: string, why_core?: string}> $coreList */
        $coreList = require $this->adminRoot . '/config/core_modules.php';
        $core = [];
        foreach ($coreList as $row) {
            $core[] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'why_core' => (string) ($row['why_core'] ?? ''),
                'type' => 'core',
                'removable' => false,
                'enabled' => true,
            ];
        }

        $optional = [];
        $ids = array_unique(array_merge(array_keys($map), array_keys($manifests)));
        sort($ids);

        foreach ($ids as $id) {
            $meta = $map[$id] ?? [];
            $manifest = $manifests[$id] ?? null;
            $installed = $manifest !== null;
            $enabled = $installed && $registry->isEnabled($id);

            $optional[] = [
                'id' => $id,
                'name' => $installed ? $manifest->name() : $this->titleCase($id),
                'version' => $installed ? $manifest->version() : '—',
                'description' => $installed ? $manifest->description() : 'Modul mapat, folder lipsă — poți restaura stub-ul.',
                'type' => 'optional',
                'installed' => $installed,
                'enabled' => $enabled,
                'removable' => true,
                'external' => !empty($meta['external']),
                'path' => $installed ? $manifest->path() : ($this->adminRoot . '/modules/' . $this->folderName($id)),
                'slugs' => $meta['slugs'] ?? [],
                'crud_key' => $meta['crud_key'] ?? null,
                'api_scripts' => $meta['api_scripts'] ?? [],
                'nav' => $installed ? $manifest->navItems() : [],
            ];
        }

        return [
            'core' => $core,
            'optional' => $optional,
            'registry' => $registry->status(),
            'state_file' => $this->adminRoot . '/storage/modules/state.json',
            'package_spec' => [
                'required' => ['module.json'],
                'optional_dirs' => ['src/', 'pages/', 'migrations/'],
                'max_zip_mb' => 20,
                'docs' => '/admin/modules/PACKAGING.md',
            ],
        ];
    }

    /**
     * @param array{tmp_name: string, name?: string, error?: int, size?: int} $upload
     * @return array{message: string, catalog: array<string, mixed>, module_id: string}
     */
    public function installUpload(array $upload, bool $overwrite = false): array
    {
        $installer = new ModuleInstaller($this->adminRoot);
        $result = $installer->installFromUpload($upload, $overwrite);

        return [
            'message' => $result['message'],
            'module_id' => $result['module_id'],
            'catalog' => $this->catalog(),
        ];
    }

    /**
     * Export ZIP pentru clonare / șablon module noi.
     *
     * @return array{path: string, filename: string, module_id: string, bytes: int}
     */
    public function exportZip(string $id): array
    {
        $id = strtolower(trim($id));
        if ($id === '_template' || $id === 'hello_demo' || $id === 'template' || $id === 'mvp_kit_template') {
            return (new ModuleExporter($this->adminRoot))->exportExampleTemplate();
        }

        // mvp_kit poate fi instalat ca modul real — export normal
        if ($id === 'mvp_kit') {
            try {
                return (new ModuleExporter($this->adminRoot))->export($id);
            } catch (\Throwable) {
                return (new ModuleExporter($this->adminRoot))->exportExampleTemplate();
            }
        }

        $id = $this->assertOptionalId($id);

        return (new ModuleExporter($this->adminRoot))->export($id);
    }

    /** @return array{message: string, module: array<string, mixed>} */
    public function setEnabled(string $id, bool $enabled): array
    {
        $id = $this->assertOptionalId($id);
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();

        if (!isset($registry->allManifests()[$id])) {
            throw new RuntimeException('Modulul nu e instalat (lipsește folderul). Folosește „Restaurează”.');
        }

        ModuleRegistry::resetInstance();
        $reg = ModuleRegistry::instance($this->adminRoot);
        $reg->setEnabled($id, $enabled);
        ModuleRegistry::resetInstance();

        $migrationNote = '';
        if ($enabled) {
            try {
                $booted = ModuleRegistry::instance($this->adminRoot)->get($id);
                $booted?->install();
                $migrationNote = ' Migrări verificate.';
            } catch (\Throwable $e) {
                $migrationNote = ' Atenție migrări: ' . $e->getMessage();
            }
        }

        $cat = $this->catalog();
        $mod = $this->findOptional($cat['optional'], $id);

        return [
            'message' => $enabled
                ? ('Modulul „' . $id . '” a fost activat.' . $migrationNote)
                : ('Modulul „' . $id . '” a fost oprit.'),
            'module' => $mod ?? ['id' => $id, 'enabled' => $enabled],
            'catalog' => $cat,
        ];
    }

    /**
     * Șterge folderul modules/{Id}/ (unplug fizic) + marchează disabled în state.
     *
     * @return array{message: string, catalog: array<string, mixed>}
     */
    public function uninstall(string $id): array
    {
        $id = $this->assertOptionalId($id);
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $manifests = $registry->allManifests();

        if (!isset($manifests[$id])) {
            throw new RuntimeException('Modulul nu e instalat (deja lipsă).');
        }

        $path = $manifests[$id]->path();
        $modulesRoot = realpath($this->adminRoot . '/modules') ?: ($this->adminRoot . '/modules');
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $modulesRoot)) {
            throw new RuntimeException('Cale modul invalidă — ștergere refuzată.');
        }
        if (basename($real) === 'modules' || $real === $modulesRoot) {
            throw new RuntimeException('Refuz ștergere root modules/.');
        }

        $this->rrmdir($real);

        (new ModuleInstaller($this->adminRoot))->uninstallRegistration($id);

        ModuleRegistry::resetInstance();
        $reg = ModuleRegistry::instance($this->adminRoot);
        $reg->discover();
        // state: disabled (chiar dacă folderul lipsește, păstrăm preferința)
        $stateFile = $this->adminRoot . '/storage/modules/state.json';
        $state = ['updated_at' => date('c'), 'enabled' => []];
        if (is_file($stateFile)) {
            $raw = file_get_contents($stateFile);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }
        if (!isset($state['enabled']) || !is_array($state['enabled'])) {
            $state['enabled'] = [];
        }
        $state['enabled'][$id] = false;
        $state['updated_at'] = date('c');
        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $stateFile,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        ModuleRegistry::resetInstance();
        ModuleGate::resetCache();

        return [
            'message' => 'Modulul „' . $id . '” a fost șters (folder eliminat). Poți restaura stub-ul oricând (builtin) sau reinstala ZIP-ul (extern).',
            'catalog' => $this->catalog(),
        ];
    }

    /**
     * Recreează module.json stub din optional_modules (dacă folderul lipsește).
     *
     * @return array{message: string, catalog: array<string, mixed>}
     */
    public function restoreStub(string $id): array
    {
        $id = $this->assertOptionalId($id);
        $folder = $this->adminRoot . '/modules/' . $this->folderName($id);
        $jsonPath = $folder . '/module.json';

        if (is_file($jsonPath)) {
            ModuleRegistry::resetInstance();
            ModuleRegistry::instance($this->adminRoot)->setEnabled($id, true);
            $migrationNote = '';
            try {
                ModuleRegistry::instance($this->adminRoot)->get($id)?->install();
                $migrationNote = ' Migrări verificate.';
            } catch (\Throwable $e) {
                $migrationNote = ' Atenție migrări: ' . $e->getMessage();
            }
            ModuleRegistry::resetInstance();

            return [
                'message' => 'Modulul „' . $id . '” există deja — a fost reactivat.' . $migrationNote,
                'catalog' => $this->catalog(),
            ];
        }

        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('Nu pot crea folderul modulului.');
        }

        $meta = ModuleGate::optionalMap()[$id] ?? [];
        $payload = [
            'id' => $id,
            'name' => $this->titleCase($id),
            'version' => '1.0.0',
            'type' => 'optional',
            'enabled' => true,
            'description' => 'Stub restaurat din Settings.',
            'entry' => 'Besoiu\\Core\\Module\\OptionalModuleStub',
            'dependencies' => [],
            'permissions' => [],
            'nav' => [],
            'migrations' => [],
            'legacy' => [
                'note' => 'Cod live în src/ — unplug via ModuleGate.',
                'slugs' => $meta['slugs'] ?? [],
            ],
        ];

        $firstSlug = $meta['slugs'][0] ?? $id;
        $payload['nav'][] = [
            'id' => $firstSlug,
            'label' => $this->titleCase($id),
            'href' => '/admin/' . $firstSlug,
            'group' => 'optional',
        ];

        file_put_contents(
            $jsonPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        ModuleRegistry::resetInstance();
        ModuleRegistry::instance($this->adminRoot)->setEnabled($id, true);
        ModuleRegistry::resetInstance();

        return [
            'message' => 'Stub „' . $id . '” restaurat și activat.',
            'catalog' => $this->catalog(),
        ];
    }

    private function assertOptionalId(string $id): string
    {
        $id = strtolower(trim($id));
        if ($id === '') {
            throw new RuntimeException('Modul opțional necunoscut: (gol)');
        }

        if (isset(ModuleGate::optionalMap()[$id])) {
            return $id;
        }

        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        if (isset($registry->allManifests()[$id])) {
            return $id;
        }

        throw new RuntimeException('Modul opțional necunoscut: ' . $id);
    }

    /** @param list<array<string, mixed>> $list */
    private function findOptional(array $list, string $id): ?array
    {
        foreach ($list as $row) {
            if (($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    private function folderName(string $id): string
    {
        $map = [
            'blog' => 'Blog',
            'marketplace' => 'Marketplace',
            'messages' => 'Messages',
            'backup' => 'Backup',
            'searchlogs' => 'Searchlogs',
            'marketing' => 'Marketing',
            'import' => 'Import',
            'ai' => 'Ai',
            'scraper' => 'Scraper',
            'mvp_kit' => 'MvpKit',
        ];
        if (isset($map[$id])) {
            return $map[$id];
        }
        $meta = ModuleGate::optionalMap()[$id] ?? [];
        if (!empty($meta['folder'])) {
            return (string) $meta['folder'];
        }
        $parts = preg_split('/[^a-zA-Z0-9]+/', $id) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $out .= ucfirst(strtolower($part));
            }
        }

        return $out !== '' ? $out : 'Module';
    }

    private function titleCase(string $id): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $id));
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
