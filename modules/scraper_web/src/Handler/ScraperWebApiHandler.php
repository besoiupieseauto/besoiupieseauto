<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\ScraperWeb\Support\ScraperWebBridge;
use Throwable;

/**
 * Proxy API ERP → motor Scraper (același contract ca Scraper/api/index.php).
 */
final class ScraperWebApiHandler
{
    public static function handle(): void
    {
        ApiBootstrap::bootJsonApi();
        ApiBootstrap::registerJsonFatalGuard('scraper_web_endpoint');

        try {
            ApiBootstrap::requireAuthenticatedSession();
            ApiBootstrap::beginBoundedJsonWork(180);
            ScraperWebBridge::boot();
            ScraperWebBridge::boot()->application()->apiController()->dispatch();
        } catch (Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            ApiBootstrap::respondInternalError('scraper_web_endpoint', $e);
        }
    }
}
