<?php
declare(strict_types=1);

namespace Besoiu\Modules\Nav;

use Besoiu\Services\AdminNavRegistryService;

final class NavHubService
{
    private AdminNavRegistryService $registry;

    public function __construct(?AdminNavRegistryService $registry = null)
    {
        $this->registry = $registry ?? new AdminNavRegistryService();
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $this->registry->ensureBootstrapped(false);

        return [
            'path' => $this->registry->registryPath(),
            'tree' => $this->registry->listTreeForAdmin(),
            'raw' => $this->registry->readRaw(),
        ];
    }

    public function registry(): AdminNavRegistryService
    {
        return $this->registry;
    }
}
