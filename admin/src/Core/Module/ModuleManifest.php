<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Parsează module.json dintr-un folder de modul.
 */
final class ModuleManifest
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $modulePath,
    ) {
    }

    public static function fromFile(string $jsonPath): self
    {
        if (!is_file($jsonPath)) {
            throw new \InvalidArgumentException('Lipsă module.json: ' . $jsonPath);
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false || $raw === '') {
            throw new \InvalidArgumentException('module.json gol: ' . $jsonPath);
        }

        // Strip UTF-8 BOM (Windows editors / some writers)
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('module.json invalid JSON: ' . $jsonPath);
        }

        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new \InvalidArgumentException('module.json fără id: ' . $jsonPath);
        }

        return new self($data, dirname($jsonPath));
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function name(): string
    {
        return (string) ($this->data['name'] ?? $this->id());
    }

    public function version(): string
    {
        return (string) ($this->data['version'] ?? '0.0.0');
    }

    /** core | optional */
    public function type(): string
    {
        $type = strtolower(trim((string) ($this->data['type'] ?? 'optional')));

        return in_array($type, ['core', 'optional'], true) ? $type : 'optional';
    }

    public function description(): string
    {
        return (string) ($this->data['description'] ?? '');
    }

    public function entryClass(): string
    {
        $class = trim((string) ($this->data['entry'] ?? ''));
        if ($class === '') {
            throw new \InvalidArgumentException('module.json fără entry (FQCN): ' . $this->id());
        }

        return $class;
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        $deps = $this->data['dependencies'] ?? [];
        if (!is_array($deps)) {
            return [];
        }

        $out = [];
        foreach ($deps as $dep) {
            $id = trim((string) $dep);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** Default enabled dacă lipsește din state.json */
    public function defaultEnabled(): bool
    {
        if (!array_key_exists('enabled', $this->data)) {
            return true;
        }

        return (bool) $this->data['enabled'];
    }

    /** @return list<array{id: string, label: string, href: string, icon?: string, group?: string}> */
    public function navItems(): array
    {
        $items = $this->data['nav'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($id === '' || $label === '' || $href === '') {
                continue;
            }
            $row = ['id' => $id, 'label' => $label, 'href' => $href];
            if (!empty($item['icon'])) {
                $row['icon'] = (string) $item['icon'];
            }
            if (!empty($item['group'])) {
                $row['group'] = (string) $item['group'];
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $perms = $this->data['permissions'] ?? [];
        if (!is_array($perms)) {
            return [];
        }

        $out = [];
        foreach ($perms as $perm) {
            $p = trim((string) $perm);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    /** @return list<string> path-uri relative la modul (ex: migrations/*.sql) */
    public function migrations(): array
    {
        $migs = $this->data['migrations'] ?? [];
        if (!is_array($migs)) {
            return [];
        }

        $out = [];
        foreach ($migs as $mig) {
            $p = trim((string) $mig);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Ce oferă modulul CORE-ului (rute, API, CRUD) — sursa de adevăr pentru ModuleGate.
     *
     * @return array{
     *   slugs: list<string>,
     *   paths: list<string>,
     *   crud_key: ?string,
     *   api_scripts: list<string>,
     *   quick_actions: list<string>,
     *   folder: ?string,
     *   workspace: ?string
     * }
     */
    public function provides(): array
    {
        $raw = $this->data['provides'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $slugs = [];
        foreach ($raw['slugs'] ?? [] as $slug) {
            $s = strtolower(trim((string) $slug));
            if ($s !== '') {
                $slugs[] = $s;
            }
        }
        if ($slugs === []) {
            foreach ($this->navItems() as $nav) {
                $id = strtolower(trim((string) ($nav['id'] ?? '')));
                if ($id !== '') {
                    $slugs[] = $id;
                }
            }
        }

        $paths = [];
        foreach ($raw['paths'] ?? [] as $path) {
            $p = '/' . trim((string) $path, '/');
            if ($p !== '/') {
                $paths[] = $p;
            }
        }
        if ($paths === []) {
            foreach ($slugs as $slug) {
                $paths[] = '/admin/' . $slug;
                $paths[] = '/admin/public/' . $slug;
            }
        }

        $apiScripts = [];
        foreach ($raw['api_scripts'] ?? [] as $script) {
            $s = basename(trim((string) $script));
            if ($s !== '') {
                $apiScripts[] = $s;
            }
        }

        $quick = [];
        foreach ($raw['quick_actions'] ?? [] as $qa) {
            $q = trim((string) $qa);
            if ($q !== '') {
                $quick[] = $q;
            }
        }

        $crud = $raw['crud_key'] ?? null;
        $crudKey = is_string($crud) && $crud !== '' ? $crud : null;

        $folder = isset($raw['folder']) ? trim((string) $raw['folder']) : '';
        if ($folder === '') {
            $folder = basename($this->modulePath);
        }

        $workspace = '';
        if (!empty($raw['workspace']) && is_string($raw['workspace'])) {
            $workspace = trim($raw['workspace']);
        } elseif (!empty($this->data['workspace']) && is_string($this->data['workspace'])) {
            $workspace = trim($this->data['workspace']);
        }

        return [
            'slugs' => array_values(array_unique($slugs)),
            'paths' => array_values(array_unique($paths)),
            'crud_key' => $crudKey,
            'api_scripts' => array_values(array_unique($apiScripts)),
            'quick_actions' => array_values(array_unique($quick)),
            'folder' => $folder !== '' ? $folder : null,
            'workspace' => $workspace !== '' ? $workspace : null,
        ];
    }

    public function pagesPath(): string
    {
        return $this->modulePath . DIRECTORY_SEPARATOR . 'pages';
    }

    public function srcPath(): string
    {
        return $this->modulePath . DIRECTORY_SEPARATOR . 'src';
    }

    public function assetsPath(): string
    {
        return $this->modulePath . DIRECTORY_SEPARATOR . 'assets';
    }

    public function apiPath(): string
    {
        return $this->modulePath . DIRECTORY_SEPARATOR . 'api';
    }

    public function hasOwnPages(): bool
    {
        return is_dir($this->pagesPath());
    }

    public function path(): string
    {
        return $this->modulePath;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->data;
    }
}
