<?php
declare(strict_types=1);

namespace Besoiu\Modules\Scraper;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

final class ScraperModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        if (!$context->isCoreAvailable()) {
            return;
        }

        ScraperModuleHooks::register();
    }
}
