<?php
declare(strict_types=1);

namespace Besoiu\Core\Product;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\Products\ProduseService;

final class ProductHooks
{
    public const HOOK_CATALOG = 'product.catalog.service';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('produse');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_CATALOG)) {
            return;
        }

        try {
            ModuleRegistry::instance()->discover();
        } catch (\Throwable) {
        }

        if (!self::moduleAvailable()) {
            return;
        }

        $hooksClass = OptionalModuleBridge::resolveClass('produse', 'ProduseModuleHooks');
        if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
            $hooksClass::register();
        }
    }

    public static function catalog(): ProduseService
    {
        self::ensureHooks();
        $service = ModuleHookRegistry::resolve(self::HOOK_CATALOG);
        if ($service instanceof ProduseService) {
            return $service;
        }

        return new ProduseService();
    }
}
