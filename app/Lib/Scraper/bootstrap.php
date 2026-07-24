<?php

declare(strict_types=1);

/**
 * Încărcare unică a componentelor interne scraper.
 * Nu include din exterior — folosește ScraperModule::boot().
 */
$scraperLib = __DIR__;
// EpiesaCatalog e necesar pe home chiar dacă alt bootstrap a marcat deja modulul ca „booted”.
if (!class_exists('EpiesaCatalog', false) && is_file($scraperLib . '/EpiesaCatalog.php')) {
    require_once $scraperLib . '/EpiesaCatalog.php';
}

if (defined('SCRAPER_MODULE_BOOTED')) {
    return;
}
// Dacă motorul Import/Scraper a încărcat deja clasele omonime, nu reincărca tot Lib/.
if (class_exists('ScraperPaths', false) || class_exists('ScraperLogger', false)) {
    if (!defined('SCRAPER_MODULE_BOOTED')) {
        define('SCRAPER_MODULE_BOOTED', true);
    }

    return;
}
define('SCRAPER_MODULE_BOOTED', true);

$envSettings = dirname(__DIR__, 2) . '/admin/system/env_settings.php';
if (is_file($envSettings)) {
    require_once $envSettings;
}

require_once $scraperLib . '/ScraperPaths.php';
require_once $scraperLib . '/ScraperLogger.php';
require_once $scraperLib . '/ScrapeDoConfig.php';
require_once $scraperLib . '/ScrapeDoClient.php';
require_once $scraperLib . '/StealthBrowserClient.php';
require_once $scraperLib . '/HttpScraperClient.php';
require_once $scraperLib . '/ScraperCssXPath.php';
require_once $scraperLib . '/ScraperHtmlAnalyzer.php';
require_once $scraperLib . '/ScraperStepSchema.php';
require_once $scraperLib . '/ScraperIntegrationSchema.php';
require_once $scraperLib . '/ScraperIntegrationStore.php';
require_once $scraperLib . '/ScraperImageSourcesSync.php';
require_once $scraperLib . '/ScraperSourceStore.php';
require_once $scraperLib . '/ScraperHubTester.php';
require_once $scraperLib . '/ScraperStepRunner.php';
require_once $scraperLib . '/AutodocImageParser.php';
require_once $scraperLib . '/ImportImageBridge.php';
require_once $scraperLib . '/PipelineImageAiGate.php';
require_once $scraperLib . '/ScraperImageResolver.php';
require_once $scraperLib . '/ImageSearchService.php';
require_once $scraperLib . '/ScraperLlmConfig.php';
require_once $scraperLib . '/ScraperHtmlSample.php';
require_once $scraperLib . '/ScraperAiAgent.php';
require_once $scraperLib . '/ScraperImageProxy.php';
require_once $scraperLib . '/EpiesaHtmlFetcher.php';
require_once $scraperLib . '/EpiesaScrapeJob.php';
require_once $scraperLib . '/EpiesaCatalog.php';
require_once $scraperLib . '/EpiesaCategories.php';
require_once $scraperLib . '/EpiesaImageCache.php';
require_once $scraperLib . '/EpiesaSearch.php';
require_once $scraperLib . '/EpiesaCategoryParser.php';
require_once $scraperLib . '/EpiesaVehicleSelectorClient.php';
require_once $scraperLib . '/EpiesaVehicleTreeStore.php';
require_once $scraperLib . '/EpiesaVehicleTreeCrawler.php';
require_once $scraperLib . '/EmagSearch.php';
require_once $scraperLib . '/EmagSearchParser.php';
