<?php

declare(strict_types=1);

/**
 * Proxy scraping pentru Import Pro — delegă la modulul scraper_web (ScraperWebBridge).
 */
require_once dirname(__DIR__, 4) . '/modules/import_pro/src/Handler/ImportProScraperProxyHandler.php';

\Besoiu\Modules\ImportPro\Handler\ImportProScraperProxyHandler::handle();
