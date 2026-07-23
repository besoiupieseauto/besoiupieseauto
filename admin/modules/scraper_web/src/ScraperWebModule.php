<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Entry class modul scraper_web — boot la pornirea adminului.
 */
final class ScraperWebModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        // Decomentează dacă CORE trebuie să consume capabilități ale modulului:
        // ScraperWebModuleHooks::register();
    }
}
