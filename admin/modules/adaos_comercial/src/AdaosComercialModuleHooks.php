<?php
declare(strict_types=1);

namespace Besoiu\Modules\AdaosComercial;

use Besoiu\Core\AdaosComercial\AdaosComercialHooks;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\AdaosComercial\AdaosComercialService;

final class AdaosComercialModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(AdaosComercialHooks::HOOK_SERVICE)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('adaos_comercial')) {
            return;
        }

        ModuleHookRegistry::registerFactory(
            AdaosComercialHooks::HOOK_SERVICE,
            static fn (): AdaosComercialService => new AdaosComercialService()
        );
    }
}
