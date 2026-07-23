<?php
declare(strict_types=1);

namespace Besoiu\Modules\AdaosComercial;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul adaos_comercial — boot la pornirea adminului.
 */
final class AdaosComercialModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        AdaosComercialModuleHooks::register();
    }
}
