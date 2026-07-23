<?php

declare(strict_types=1);

namespace Besoiu\Core\Supplier;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;

/**
 * Punct unic CORE → capabilități furnizori (hook-uri din modul, fără namespace modul în consumatori).
 */
final class SupplierHooks
{
    public const HOOK_STATS = 'supplier.stats';
    public const HOOK_FEED = 'supplier.feed';
    public const HOOK_SYNC_REPORTS = 'supplier.sync_reports';
    public const HOOK_REMOTE_SYNC = 'supplier.remote_sync';
    public const HOOK_PRICE_LOGIC = 'supplier.price_logic';
    public const HOOK_SCAN_SCHEDULE = 'supplier.scan_schedule';
    public const HOOK_TEST_CONNECTION = 'supplier.test_connection';
    public const HOOK_API_AUTOPARTNER = 'supplier.api.autopartner';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('furnizori');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_STATS)) {
            return;
        }

        try {
            ModuleRegistry::instance()->boot();
        } catch (\Throwable) {
            // CLI fără admin — înregistrare lazy din modul
        }

        if (!ModuleHookRegistry::has(self::HOOK_STATS) && self::moduleAvailable()) {
            $hooksClass = OptionalModuleBridge::resolveClass('furnizori', 'FurnizoriModuleHooks');
            if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
                $hooksClass::register();
            }
        }
    }

    public static function catalog(): SupplierCatalogRepository
    {
        return new SupplierCatalogRepository();
    }

    public static function priceLogicStore(): SupplierPriceLogicStore
    {
        return new SupplierPriceLogicStore();
    }

    /** @return object|null */
    public static function stats(): ?object
    {
        if (!self::moduleAvailable()) {
            return null;
        }

        self::ensureHooks();

        return ModuleHookRegistry::resolve(self::HOOK_STATS);
    }

    /** @return object|null */
    public static function feed(): ?object
    {
        if (!self::moduleAvailable()) {
            return null;
        }

        self::ensureHooks();

        return ModuleHookRegistry::resolve(self::HOOK_FEED);
    }

    /** @return object|null */
    public static function syncReports(): ?object
    {
        if (!self::moduleAvailable()) {
            return null;
        }

        self::ensureHooks();

        return ModuleHookRegistry::resolve(self::HOOK_SYNC_REPORTS);
    }

    /** @return object|null */
    public static function remoteSync(): ?object
    {
        if (!self::moduleAvailable()) {
            return null;
        }

        self::ensureHooks();

        return ModuleHookRegistry::resolve(self::HOOK_REMOTE_SYNC);
    }

    /** @return object|null */
    public static function priceLogic(): ?object
    {
        if (!self::moduleAvailable()) {
            return null;
        }

        self::ensureHooks();

        return ModuleHookRegistry::resolve(self::HOOK_PRICE_LOGIC);
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function scanScheduleFormatLabel(array $supplier): string
    {
        self::ensureHooks();
        $label = ModuleHookRegistry::invokeStatic(
            self::HOOK_SCAN_SCHEDULE,
            'formatLabel',
            [$supplier]
        );

        return is_string($label) ? $label : ScanScheduleFallback::formatLabel($supplier);
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function scanScheduleShouldRunAuto(
        array $supplier,
        array $agentState = [],
        ?\DateTimeImmutable $now = null
    ): bool {
        self::ensureHooks();
        $result = ModuleHookRegistry::invokeStatic(
            self::HOOK_SCAN_SCHEDULE,
            'shouldRunAuto',
            [$supplier, $agentState, $now],
            null
        );

        return is_bool($result) ? $result : ScanScheduleFallback::shouldRunAuto($supplier, $agentState, $now);
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function scanScheduleEstimateNextRunAt(
        array $supplier,
        array $agentState = [],
        ?\DateTimeImmutable $now = null
    ): ?\DateTimeImmutable {
        self::ensureHooks();
        $result = ModuleHookRegistry::invokeStatic(
            self::HOOK_SCAN_SCHEDULE,
            'estimateNextRunAt',
            [$supplier, $agentState, $now]
        );

        return $result instanceof \DateTimeImmutable
            ? $result
            : ScanScheduleFallback::estimateNextRunAt($supplier, $agentState, $now);
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function scanScheduleFormatNextRunLabel(
        array $supplier,
        array $agentState = [],
        ?\DateTimeImmutable $now = null
    ): string {
        self::ensureHooks();
        $result = ModuleHookRegistry::invokeStatic(
            self::HOOK_SCAN_SCHEDULE,
            'formatNextRunLabel',
            [$supplier, $agentState, $now]
        );

        return is_string($result) ? $result : ScanScheduleFallback::formatNextRunLabel($supplier, $agentState, $now);
    }

    /**
     * @return array<string, mixed>
     */
    public static function testConnection(int $randomnId, string $testTarget = 'api'): array
    {
        if (!self::moduleAvailable()) {
            throw new \RuntimeException('Modulul furnizori nu este instalat sau este dezactivat.');
        }

        self::ensureHooks();
        $result = ModuleHookRegistry::invoke(self::HOOK_TEST_CONNECTION, [$randomnId, $testTarget]);

        return is_array($result) ? $result : [];
    }

    /** @return object */
    public static function autoPartnerClient(): object
    {
        self::ensureHooks();
        $client = ModuleHookRegistry::resolve(self::HOOK_API_AUTOPARTNER);
        if ($client !== null) {
            return $client;
        }

        return new AutoPartnerApiClient();
    }
}
