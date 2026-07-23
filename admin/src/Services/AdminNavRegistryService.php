<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Module\ModuleManifest;
use Besoiu\Core\Module\ModulesPaths;
use RuntimeException;

/**
 * Fațadă registru navigație — sursa principală: app/Backend/storage/navigation/registry.json
 */
final class AdminNavRegistryService
{
    private readonly AdminNavJsonRegistry $json;

    public function __construct(?string $adminRoot = null)
    {
        $this->json = new AdminNavJsonRegistry($adminRoot);
    }

    public function registryPath(): string
    {
        return $this->json->registryPath();
    }

    public function isReady(): bool
    {
        return $this->json->isReady() || is_file($this->json->registryPath()) || is_file(
            ModulesPaths::adminRoot() . '/config/admin_nav_seed.php'
        );
    }

    public function ensureBootstrapped(bool $syncModules = false): void
    {
        $this->json->ensureBootstrapped($syncModules);
    }

    /** @return list<array<string, mixed>> */
    public function listTreeForAdmin(): array
    {
        return $this->json->listTreeForAdmin();
    }

    /** @param array{new_orders?: int, abandoned_carts?: int, unread_messages?: int} $badges */
    public function listTreeForRender(array $badges = []): array
    {
        return $this->json->listTreeForRender($badges);
    }

    /** @return array<string, mixed> */
    public function readRaw(): array
    {
        return $this->json->readRaw();
    }

    /** @param array<string, mixed> $data */
    public function writeRaw(array $data): void
    {
        $this->json->writeRaw($data);
    }

    /** @return array<string, mixed> */
    public function syncAllModules(): array
    {
        return $this->json->syncAllModules();
    }

    public function syncModule(string $moduleId, ?ModuleManifest $manifest = null, ?bool $enabled = null): bool
    {
        return $this->json->syncModule($moduleId, $manifest, $enabled);
    }

    public function onModuleDisabled(string $moduleId): void
    {
        $this->json->onModuleDisabled($moduleId);
    }

    public function onModuleUninstalled(string $moduleId): void
    {
        $this->json->onModuleUninstalled($moduleId);
    }

    /** @param array<string, mixed> $payload */
    public function updateItem(int|string $itemRef, array $payload): array
    {
        [$sectionKey, $itemKey] = $this->resolveItemKeys($itemRef, $payload);

        return $this->json->updateItem($sectionKey, $itemKey, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function updateSection(int|string $sectionRef, array $payload): array
    {
        $sectionKey = trim((string) ($payload['section_key'] ?? $sectionRef));
        if ($sectionKey === '') {
            throw new RuntimeException('Lipsește section_key.');
        }

        return $this->json->updateSection($sectionKey, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function addManualSection(array $payload): array
    {
        return $this->json->addManualSection($payload);
    }

    /** @param array<string, mixed> $payload */
    public function addManualItem(string $sectionKey, array $payload): array
    {
        return $this->json->addManualItem($sectionKey, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function deleteItem(int|string $itemRef, array $payload = []): bool
    {
        [$sectionKey, $itemKey] = $this->resolveItemKeys($itemRef, $payload);

        return $this->json->deleteItem($sectionKey, $itemKey);
    }

    public function resetModuleNav(string $moduleId): bool
    {
        return $this->json->resetModuleNav($moduleId);
    }

    /** @return array{workspace: string, sections: int, items: int} */
    public function hideWorkspace(string $workspaceId): array
    {
        return $this->json->hideWorkspace($workspaceId);
    }

    /** @param array<string, mixed> $decoded */
    public function importDecoded(array $decoded): void
    {
        $this->json->importDecoded($decoded);
    }

    /** @param array<string, mixed> $payload @return array{0: string, 1: string} */
    private function resolveItemKeys(int|string $itemRef, array $payload): array
    {
        $sectionKey = trim((string) ($payload['section_key'] ?? ''));
        $itemKey = trim((string) ($payload['item_key'] ?? ''));

        if ($sectionKey !== '' && $itemKey !== '') {
            return [$sectionKey, $itemKey];
        }

        $composite = trim((string) ($payload['item_id'] ?? $itemRef));
        if ($composite !== '' && str_contains($composite, '::')) {
            [$sectionKey, $itemKey] = explode('::', $composite, 2);

            return [trim($sectionKey), trim($itemKey)];
        }

        throw new RuntimeException('Lipsește item_id (section_key::item_key).');
    }
}
