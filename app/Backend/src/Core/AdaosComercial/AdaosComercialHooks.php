<?php
declare(strict_types=1);

namespace Besoiu\Core\AdaosComercial;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\AdaosComercial\AdaosComercialService;

final class AdaosComercialHooks
{
    public const HOOK_SERVICE = 'adaos.service';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('adaos_comercial');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_SERVICE)) {
            return;
        }

        try {
            ModuleRegistry::instance()->discover();
        } catch (\Throwable) {
        }

        if (!self::moduleAvailable()) {
            return;
        }

        $hooksClass = OptionalModuleBridge::resolveClass('adaos_comercial', 'AdaosComercialModuleHooks');
        if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
            $hooksClass::register();
        }
    }

    public static function service(): AdaosComercialService
    {
        self::ensureHooks();
        $service = ModuleHookRegistry::resolve(self::HOOK_SERVICE);
        if ($service instanceof AdaosComercialService) {
            return $service;
        }

        return new AdaosComercialService();
    }
}
