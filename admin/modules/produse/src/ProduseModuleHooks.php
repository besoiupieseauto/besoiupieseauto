<?php
declare(strict_types=1);

namespace Besoiu\Modules\Produse;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Core\Product\ProductHooks;
use Besoiu\Services\Products\ProduseService;

final class ProduseModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(ProductHooks::HOOK_CATALOG)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('produse')) {
            return;
        }

        ModuleHookRegistry::registerFactory(
            ProductHooks::HOOK_CATALOG,
            static fn (): ProduseService => new ProduseService()
        );
    }
}
