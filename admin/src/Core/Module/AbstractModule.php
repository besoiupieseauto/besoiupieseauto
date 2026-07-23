<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Bază pentru module — citește metadata din module.json.
 */
abstract class AbstractModule implements ModuleInterface
{
    public function __construct(
        protected readonly ModuleManifest $manifest,
    ) {
    }

    public function id(): string
    {
        return $this->manifest->id();
    }

    public function name(): string
    {
        return $this->manifest->name();
    }

    public function version(): string
    {
        return $this->manifest->version();
    }

    public function type(): string
    {
        return $this->manifest->type();
    }

    public function manifest(): ModuleManifest
    {
        return $this->manifest;
    }

    public function boot(ModuleContext $context): void
    {
        // Override în module concrete (listeners, CRUD register, etc.)
    }

    public function navItems(): array
    {
        return $this->manifest->navItems();
    }

    public function permissions(): array
    {
        return $this->manifest->permissions();
    }

    public function dependencies(): array
    {
        return $this->manifest->dependencies();
    }

    public function install(): void
    {
        $adminRoot = dirname($this->manifest->path(), 2);
        // path = admin/modules/{Folder} → adminRoot = admin/
        if (basename(dirname($this->manifest->path())) === 'modules') {
            $adminRoot = dirname($this->manifest->path(), 2);
        } else {
            $adminRoot = dirname(__DIR__, 3);
        }

        $runner = new ModuleMigrationRunner($adminRoot);
        $result = $runner->runForManifest($this->manifest);
        if ($result['errors'] !== []) {
            throw new \RuntimeException(
                'Migrări eșuate pentru „' . $this->id() . '”: ' . implode('; ', $result['errors'])
            );
        }
    }

    public function uninstall(): void
    {
        // Soft: nu DROP TABLE automat (datele rămân). Override în module concrete.
    }
}
