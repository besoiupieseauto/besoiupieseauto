<?php
declare(strict_types=1);

namespace Besoiu\Modules\__STUDLY__;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul __MODULE_ID__ — boot la pornirea adminului.
 */
final class __STUDLY__Module extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        // Decomentează dacă CORE trebuie să consume capabilități ale modulului:
        // __STUDLY__ModuleHooks::register();
    }
}
