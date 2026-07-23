<?php
declare(strict_types=1);

namespace Besoiu\Core\Categorii;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\CategoriiService;

final class CategoriiHooks
{
    public const HOOK_SERVICE = 'categorii.service';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('categorii');
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

        $hooksClass = OptionalModuleBridge::resolveClass('categorii', 'CategoriiModuleHooks');
        if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
            $hooksClass::register();
        }
    }

    public static function service(): CategoriiService
    {
        self::ensureHooks();
        $service = ModuleHookRegistry::resolve(self::HOOK_SERVICE);
        if ($service instanceof CategoriiService) {
            return $service;
        }

        return new CategoriiService();
    }

    /** @return list<array<string, mixed>> */
    public static function activeTaxonomy(): array
    {
        return self::service()->getActive();
    }
}
