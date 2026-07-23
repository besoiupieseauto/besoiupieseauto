<?php
declare(strict_types=1);

namespace Besoiu\Modules\Nav;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;
use Besoiu\Services\AdminNavRegistryService;

final class NavModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        try {
            (new AdminNavRegistryService())->ensureBootstrapped(false);
        } catch (\Throwable) {
            // Registry JSON se inițializează lazy la primul render.
        }
    }

    public function install(): void
    {
        (new AdminNavRegistryService())->ensureBootstrapped(false);
    }

    public function uninstall(): void
    {
    }
}
