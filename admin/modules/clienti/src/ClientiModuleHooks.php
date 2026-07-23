<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Core\Client\ClientHooks;
use Besoiu\Modules\Clienti\Controller\ClientiController;
use Besoiu\Modules\Clienti\Service\ClientiService;

final class ClientiModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(ClientHooks::HOOK_CONTROLLER)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('clienti')) {
            return;
        }

        ModuleHookRegistry::registerFactory(ClientHooks::HOOK_CONTROLLER, static function (): object {
            return new ClientiController(new ClientiService());
        });
    }
}
