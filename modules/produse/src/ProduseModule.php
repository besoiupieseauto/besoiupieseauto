<?php
declare(strict_types=1);

namespace Besoiu\Modules\Produse;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul produse — boot la pornirea adminului.
 */
final class ProduseModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        ProduseModuleHooks::register();
    }
}
