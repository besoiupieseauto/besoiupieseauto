<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb\Support;

use BesoiuImport\Scraper\Services\EnvironmentStatusService;
use BesoiuImport\Scraper\Services\ScraperGatewayService;
use BesoiuImport\Scraper\ViewModels\DashboardViewModel;
use Throwable;

final class ScraperWebPageRenderer
{
    public static function renderAdminPage(): void
    {
        try {
            ScraperWebBridge::boot();
            $apiUrl = ScraperWebBridge::apiEndpointUrl();
            $assetsBase = ScraperWebBridge::publicUrl('assets/');

            $envService = new EnvironmentStatusService();
            $gateway = new ScraperGatewayService();
            $base = $envService->buildDashboardViewModel();

            $dashboard = new DashboardViewModel(
                pageTitle: 'Scraper Web — Besoiu Admin',
                importToolUrl: $base->importToolUrl,
                moduleRootDirectory: $base->moduleRootDirectory,
                importProjectRoot: $base->importProjectRoot,
                imagesLibraryDirectory: $base->imagesLibraryDirectory,
                tecdocCsvDirectory: $base->tecdocCsvDirectory,
                pythonToolsRoot: $base->pythonToolsRoot,
                environmentFileExists: $base->environmentFileExists,
                stealthBrowserAvailable: $base->stealthBrowserAvailable,
                scrapeDoTokenConfigured: $base->scrapeDoTokenConfigured,
                rapidApiKeyConfigured: $base->rapidApiKeyConfigured,
                apiEndpointUrl: $apiUrl,
                epiesaCatalogProductCount: $gateway->countEpiesaCatalogProducts(),
            );

            $scraperApiEndpointUrl = $apiUrl;
            $scraperProjectRoot = defined('SCRAPER_ROOT') ? SCRAPER_ROOT : ScraperWebBridge::root();
            $scraperModuleWebBase = rtrim(ScraperWebBridge::publicUrl(''), '/');

            echo '<div class="scraper-web-erp-wrap" id="scraper-web-module">';
            echo '<div class="flex flex-wrap items-center justify-between gap-3 mb-4">';
            echo '<div><h2 class="text-lg font-semibold m-0">Scraper Web</h2>';
            echo '<p class="text-sm opacity-70 m-0">Motor: <code>' . htmlspecialchars(ScraperWebBridge::root(), ENT_QUOTES, 'UTF-8') . '</code></p></div>';
            echo '<div class="flex flex-wrap gap-2">';
            echo '<button type="button" id="toggle-env-panel" class="sc-btn-outline sc-btn-sm">Tokeni API</button>';
            echo '<a href="/besoiupieseimport/Scraper/" target="_blank" rel="noopener" class="sc-btn-outline sc-btn-sm">Deschide standalone</a>';
            echo '</div></div>';

            require SCRAPER_VIEWS . '/partials/_layout-styles.php';
            require SCRAPER_VIEWS . '/partials/_status-strip.php';
            require SCRAPER_VIEWS . '/partials/_env-help-panel.php';

            ob_start();
            require SCRAPER_VIEWS . '/partials/scraper.php';
            $html = (string) ob_get_clean();
            $html = str_replace('src="assets/', 'src="' . htmlspecialchars($assetsBase, ENT_QUOTES, 'UTF-8'), $html);
            echo $html;

            self::renderEnvScripts($apiUrl);
            echo '</div>';
        } catch (Throwable $e) {
            echo '<div class="admin-panel p-6"><p class="text-red-600"><strong>Scraper Web — eroare încărcare</strong></p>';
            echo '<p class="text-sm">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p class="text-sm opacity-70">Verifică că există <code>besoiupieseimport/Scraper/</code> și rulează <code>php tools/test_module_bridge.php</code> din arhivă.</p></div>';
        }
    }

    private static function renderEnvScripts(string $apiUrl): void
    {
        $apiJson = json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $partial = dirname(__DIR__, 2) . '/views/_erp-env-scripts.php';
        if (is_file($partial)) {
            require $partial;

            return;
        }
        echo '<script>document.getElementById("toggle-env-panel")?.addEventListener("click",function(){document.getElementById("env-keys")?.classList.toggle("hidden");});</script>';
    }
}
