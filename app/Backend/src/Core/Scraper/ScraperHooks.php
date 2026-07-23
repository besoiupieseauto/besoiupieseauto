<?php

declare(strict_types=1);

namespace Besoiu\Core\Scraper;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\Scraper\ScraperService;

/**
 * Punct unic CORE → capabilități scraper (modul opțional modules/scraper/).
 */
final class ScraperHooks
{
    public const HOOK_HAS_ACTIVE_PLANS = 'scraper.has_active_image_plans';
    public const HOOK_ACTIVE_PLANS = 'scraper.active_image_plans';
    public const HOOK_OPERATIONAL_ALERTS = 'scraper.operational_alerts';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('scraper');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_HAS_ACTIVE_PLANS)) {
            return;
        }

        try {
            ModuleRegistry::instance()->boot();
        } catch (\Throwable) {
            // CLI / context fără admin complet
        }

        if (!ModuleHookRegistry::has(self::HOOK_HAS_ACTIVE_PLANS) && self::moduleAvailable()) {
            $hooksClass = OptionalModuleBridge::resolveClass('scraper', 'ScraperModuleHooks');
            if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
                $hooksClass::register();
            }
        }
    }

    /** @param list<string> $categories */
    public static function hasActiveImagePlans(array $categories = []): bool
    {
        self::ensureHooks();

        $fromHook = ModuleHookRegistry::invoke(self::HOOK_HAS_ACTIVE_PLANS, [$categories], null);
        if ($fromHook !== null) {
            return (bool) $fromHook;
        }

        return self::legacyHasActiveImagePlans($categories);
    }

    /** @param list<string>|null $categories @return list<array<string, mixed>> */
    public static function activeImagePlans(?array $categories = null): array
    {
        self::ensureHooks();

        $fromHook = ModuleHookRegistry::invoke(self::HOOK_ACTIVE_PLANS, [$categories], null);
        if (is_array($fromHook)) {
            return $fromHook;
        }

        return self::legacyActiveImagePlans($categories ?? []);
    }

    /** @return list<array<string, mixed>> */
    public static function operationalAlerts(): array
    {
        self::ensureHooks();

        $fromHook = ModuleHookRegistry::invoke(self::HOOK_OPERATIONAL_ALERTS, [], null);
        if (is_array($fromHook)) {
            return $fromHook;
        }

        return self::legacyOperationalAlerts();
    }

    /** @param list<string> $categories */
    private static function legacyHasActiveImagePlans(array $categories): bool
    {
        $servicePath = self::legacyImageSearchServicePath();
        if ($servicePath === null) {
            return false;
        }

        require_once $servicePath;

        return \ImageSearchService::hasActiveImagePlans($categories);
    }

    /** @param list<string> $categories @return list<array<string, mixed>> */
    private static function legacyActiveImagePlans(array $categories): array
    {
        try {
            $service = new ScraperService();

            return $service->module()->activeImagePlans($categories);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private static function legacyOperationalAlerts(): array
    {
        try {
            return (new ScraperService())->module()->operationalAlerts();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function legacyImageSearchServicePath(): ?string
    {
        if (defined('BESOIU_APP')) {
            $import = BESOIU_APP . '/Import/Scraper/lib/ImageSearchService.php';
            if (is_file($import)) {
                return $import;
            }
            $path = BESOIU_APP . '/Lib/Scraper/ImageSearchService.php';
            if (is_file($path)) {
                return $path;
            }
        }

        $import = dirname(__DIR__, 4) . '/Import/Scraper/lib/ImageSearchService.php';
        if (is_file($import)) {
            return $import;
        }

        $path = dirname(__DIR__, 4) . '/Lib/Scraper/ImageSearchService.php';

        return is_file($path) ? $path : null;
    }
}
