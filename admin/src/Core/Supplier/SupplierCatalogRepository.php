<?php

declare(strict_types=1);

namespace Besoiu\Core\Supplier;

use Besoiu\Core\Module\OptionalModuleBridge;

/**
 * Fațadă CORE — catalog furnizori (tabel furnizori), delegă la modul sau fallback DB.
 */
final class SupplierCatalogRepository
{
    private object $store;

    public function __construct()
    {
        $class = OptionalModuleBridge::resolveClass('furnizori', 'Model\\FurnizoriRepository');
        $this->store = $class !== null ? new $class() : new SupplierCatalogRepositoryFallback();
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        return $this->store->findAll();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->store->findPaginated($page, $perPage, $filters);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->store->findByCode($code);
    }

    /** @return array<string, mixed>|null */
    public function findByRandomId(int $randomId): ?array
    {
        return $this->store->findByRandomId($randomId);
    }

    public function existsByRandomId(int $randomId): bool
    {
        return $this->store->existsByRandomId($randomId);
    }

    /** @param array<string, string|int|float|null> $payload */
    public function insert(array $payload): bool
    {
        return $this->store->insert($payload);
    }

    /** @param array<string, string|int|float|null> $payload */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        return $this->store->updateByRandomId($randomId, $payload);
    }

    public function deleteByRandomId(int $randomId): bool
    {
        return $this->store->deleteByRandomId($randomId);
    }

    /** @param array<int, string> $allowedCodes */
    public function deleteNotInCodes(array $allowedCodes): int
    {
        return $this->store->deleteNotInCodes($allowedCodes);
    }

    /** @param array<string, string|int|float|null> $payload */
    public function upsertByCode(string $code, array $payload): bool
    {
        return $this->store->upsertByCode($code, $payload);
    }
}
