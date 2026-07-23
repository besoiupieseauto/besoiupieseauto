<?php

declare(strict_types=1);

namespace Besoiu\Core\Client;

use Besoiu\Controllers\Clienti\Clienti as LegacyClientiController;
use Besoiu\Core\Clienti\ClientiModel;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\ClientiService as LegacyClientiService;

/**
 * Punct unic CORE → capabilități clienți (modul opțional modules/clienti/).
 */
final class ClientHooks
{
    public const HOOK_CONTROLLER = 'client.controller';

    public static function moduleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('clienti');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_CONTROLLER)) {
            return;
        }

        try {
            ModuleRegistry::instance()->boot();
        } catch (\Throwable) {
            // CLI fără admin
        }

        if (!ModuleHookRegistry::has(self::HOOK_CONTROLLER) && self::moduleAvailable()) {
            $hooksClass = OptionalModuleBridge::resolveClass('clienti', 'ClientiModuleHooks');
            if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
                $hooksClass::register();
            }
        }
    }

    public static function controller(): object
    {
        self::ensureHooks();
        $fromHook = ModuleHookRegistry::resolve(self::HOOK_CONTROLLER);
        if ($fromHook !== null) {
            return $fromHook;
        }

        return new LegacyClientiController(new LegacyClientiService(new ClientiModel()));
    }
}
