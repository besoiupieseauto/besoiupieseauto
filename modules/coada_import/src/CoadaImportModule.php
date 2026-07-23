<?php
declare(strict_types=1);

namespace Besoiu\Modules\CoadaImport;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul coada_import — boot la pornirea adminului.
 */
final class CoadaImportModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        CoadaImportModuleHooks::register();
    }
}
