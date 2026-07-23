<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb;

use Besoiu\Core\Module\ModuleHookRegistry;

/**
 * Hook-uri opționale — înregistrează capabilități consumate de CORE prin helper dedicat.
 * Exemplu: ModuleHookRegistry::registerFactory('scraper_web.stats', fn () => new ScraperWebService());
 */
final class ScraperWebModuleHooks
{
    public static function register(): void
    {
        // ModuleHookRegistry::registerFactory('scraper_web.example', static fn () => new ScraperWebService());
    }
}
