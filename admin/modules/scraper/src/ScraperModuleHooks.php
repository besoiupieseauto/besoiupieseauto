<?php
declare(strict_types=1);

namespace Besoiu\Modules\Scraper;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Core\Scraper\ScraperHooks;
use Besoiu\Services\Scraper\ScraperService;

final class ScraperModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(ScraperHooks::HOOK_HAS_ACTIVE_PLANS)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('scraper')) {
            return;
        }

        ModuleHookRegistry::registerCallable(
            ScraperHooks::HOOK_HAS_ACTIVE_PLANS,
            static function (array $categories = []): bool {
                return self::service()->module()->hasActiveImagePlans($categories);
            }
        );

        ModuleHookRegistry::registerCallable(
            ScraperHooks::HOOK_ACTIVE_PLANS,
            static function (?array $categories = null): array {
                return self::service()->module()->activeImagePlans($categories ?? []);
            }
        );

        ModuleHookRegistry::registerCallable(
            ScraperHooks::HOOK_OPERATIONAL_ALERTS,
            static function (): array {
                return self::service()->module()->operationalAlerts();
            }
        );
    }

    private static function service(): ScraperService
    {
        return new ScraperService();
    }
}
