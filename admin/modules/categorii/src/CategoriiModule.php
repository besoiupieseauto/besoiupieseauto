<?php
declare(strict_types=1);

namespace Besoiu\Modules\Categorii;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul categorii — boot la pornirea adminului.
 */
final class CategoriiModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        CategoriiModuleHooks::register();
    }
}
