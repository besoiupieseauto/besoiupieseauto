<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 *
 * Un modul e un mini-MVP: src/, pages/, assets/, api/ — se conectează la CORE prin acest context.
 */
final class ModuleContext
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly string $adminRoot,
    ) {
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    public function adminRoot(): string
    {
        return $this->adminRoot;
    }

    public function hasModule(string $id): bool
    {
        return $this->registry->isEnabled($id);
    }

    /** CORE e mereu prezent (produse, comenzi, auth…). */
    public function isCoreAvailable(): bool
    {
        $backend = ModulesPaths::backendRoot();

        return is_dir($backend . '/src/Core') && is_dir($backend . '/config');
    }

    /**
     * URL public pentru asset din modules/{Folder}/assets/{file}.
     * Servit prin /admin/modules/{Folder}/assets/… (document root Laragon).
     */
    public function assetUrl(string $moduleId, string $relativeFile): string
    {
        $manifest = $this->registry->allManifests()[$moduleId] ?? null;
        if ($manifest === null) {
            return '';
        }
        $folder = basename($manifest->path());
        $rel = ltrim(str_replace('\\', '/', $relativeFile), '/');

        return '/admin/modules/' . rawurlencode($folder) . '/assets/' . $rel;
    }

    public function modulePagesUrl(string $moduleId): string
    {
        $manifest = $this->registry->allManifests()[$moduleId] ?? null;
        if ($manifest === null) {
            return '';
        }

        return '/admin/modules/' . basename($manifest->path()) . '/pages/';
    }
}
