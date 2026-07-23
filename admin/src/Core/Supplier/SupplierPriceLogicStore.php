<?php

declare(strict_types=1);

namespace Besoiu\Core\Supplier;

use Besoiu\Core\Module\OptionalModuleBridge;

/**
 * Fațadă CORE — config formare preț / adaos, delegă la modul sau fallback DB.
 */
final class SupplierPriceLogicStore
{
    private object $store;

    public function __construct()
    {
        $class = OptionalModuleBridge::resolveClass('furnizori', 'Model\\PriceFormationLogicRepository');
        $this->store = $class !== null ? new $class() : new SupplierPriceLogicStoreFallback();
    }

    public function ensureTable(): void
    {
        $this->store->ensureTable();
    }

    /** @return array<string, mixed>|null */
    public function loadConfig(): ?array
    {
        return $this->store->loadConfig();
    }

    /** @param array<string, mixed> $config */
    public function saveConfig(array $config): bool
    {
        return $this->store->saveConfig($config);
    }
}
