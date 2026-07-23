<?php
declare(strict_types=1);

namespace Besoiu\Modules\Import;

use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

final class ImportModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        ImportModuleHooks::register();
    }
}
