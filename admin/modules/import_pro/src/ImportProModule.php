<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul import_pro — boot la pornirea adminului.
 */
final class ImportProModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        ImportProModuleHooks::register();
    }
}
