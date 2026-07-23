<?php
declare(strict_types=1);

namespace Besoiu\Modules\Furnizori;

use Besoiu\Controllers\Furnizori\Furnizori;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Core\Supplier\AutoPartnerApiClient as CoreAutoPartnerApiClient;
use Besoiu\Core\Supplier\SupplierHooks;

/**
 * Înregistrare hook-uri furnizori — CORE consumă doar SupplierHooks / ModuleHookRegistry.
 */
final class FurnizoriModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(SupplierHooks::HOOK_STATS)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('furnizori')) {
            return;
        }

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_STATS, static function (): ?object {
            $statsClass = OptionalModuleBridge::resolveClass('furnizori', 'Service\\FurnizoriStatsService');
            $repoClass = OptionalModuleBridge::resolveClass('furnizori', 'Model\\FurnizoriRepository');
            if ($statsClass === null || $repoClass === null) {
                return null;
            }

            return new $statsClass(new $repoClass());
        });

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_FEED, static function (): ?object {
            $class = OptionalModuleBridge::resolveClass('furnizori', 'Service\\SupplierFeedFolderService');

            return $class !== null ? new $class() : null;
        });

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_SYNC_REPORTS, static function (): ?object {
            $class = OptionalModuleBridge::resolveClass('furnizori', 'Service\\FurnizoriDailySyncReportService');

            return $class !== null ? new $class() : null;
        });

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_REMOTE_SYNC, static function (): ?object {
            $class = OptionalModuleBridge::resolveClass('furnizori', 'Service\\FurnizoriRemoteSyncService');

            return $class !== null ? new $class() : null;
        });

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_PRICE_LOGIC, static function (): ?object {
            $class = OptionalModuleBridge::resolveClass('furnizori', 'Service\\PriceFormationLogicService');

            return $class !== null ? new $class() : null;
        });

        $scheduleClass = OptionalModuleBridge::resolveClass('furnizori', 'Service\\SupplierScanScheduleService');
        if ($scheduleClass !== null) {
            ModuleHookRegistry::registerClass(SupplierHooks::HOOK_SCAN_SCHEDULE, $scheduleClass);
        }

        ModuleHookRegistry::registerCallable(SupplierHooks::HOOK_TEST_CONNECTION, static function (int $randomnId, string $testTarget = 'api'): array {
            $controller = new Furnizori();

            return $controller->test([
                'randomn_id' => $randomnId,
                'test_target' => $testTarget,
            ]);
        });

        ModuleHookRegistry::registerFactory(SupplierHooks::HOOK_API_AUTOPARTNER, static function (): object {
            $class = OptionalModuleBridge::resolveClass('furnizori', 'Service\\AutoPartnerApiClient');
            if ($class !== null) {
                return new $class();
            }

            return new CoreAutoPartnerApiClient();
        });
    }
}
