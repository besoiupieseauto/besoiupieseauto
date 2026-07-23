<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro\Handler;

use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Modules\ScraperWeb\Support\ScraperWebBridge;
use RuntimeException;
use Throwable;

/**
 * Proxy scraping Import Pro → modul scraper_web (motor app/Import/Scraper).
 */
final class ImportProScraperProxyHandler
{
    public static function handle(): void
    {
        self::bootAdmin();
        ApiBootstrap::bootJsonApi();
        ApiBootstrap::registerJsonFatalGuard('import_pro_scraper_proxy');

        try {
            ApiBootstrap::requireAuthenticatedSession();
            ApiBootstrap::beginBoundedJsonWork(180);
            self::requireScraperAccess();

            if (!OptionalModuleBridge::isOperational('scraper_web')) {
                throw new RuntimeException(
                    'Modulul Scraper Web nu este activ. Activează scraper_web din Module admin.'
                );
            }

            ScraperWebBridge::boot()->application()->apiController()->dispatch();
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('import_pro_scraper_proxy', $e);
        }
    }

    private static function bootAdmin(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        $erpRoot = dirname(__DIR__, 4);
        require_once $erpRoot . '/admin/bootstrap.php';
        require_once $erpRoot . '/admin/vendor/autoload.php';

        \Besoiu\Core\Module\ModuleRegistry::instance(
            ApiBootstrap::adminRoot()
        )->discover();

        $booted = true;
    }

    private static function requireScraperAccess(): void
    {
        $role = strtolower(trim((string) ($_SESSION['role'] ?? 'guest')));
        if (in_array($role, ['super_ambassador', 'manager'], true)) {
            return;
        }

        if (
            AdminPermissionCatalog::featureAllowed('module.import_pro', $role)
            || AdminPermissionCatalog::featureAllowed('automatizare.scraper_web', $role)
        ) {
            return;
        }

        ApiBootstrap::json([
            'success' => false,
            'message' => 'Acces interzis — necesită permisiunea Import Pro sau Scraper Web.',
        ], 403);
    }
}
