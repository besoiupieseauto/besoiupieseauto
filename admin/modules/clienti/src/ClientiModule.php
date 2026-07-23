<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

final class ClientiModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        ClientiModuleHooks::register();
    }
}
