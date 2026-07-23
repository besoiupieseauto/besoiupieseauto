<?php
declare(strict_types=1);

namespace Besoiu\Modules\Categorii;

use Besoiu\Core\Categorii\CategoriiHooks;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\CategoriiService;

final class CategoriiModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(CategoriiHooks::HOOK_SERVICE)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('categorii')) {
            return;
        }

        ModuleHookRegistry::registerFactory(
            CategoriiHooks::HOOK_SERVICE,
            static fn (): CategoriiService => new CategoriiService()
        );
    }
}
