<?php

declare(strict_types=1);

/**
 * Alias Legacy → pipeline canonic din Import/Scraper (evită Cannot redeclare).
 */
$canonical = dirname(__DIR__) . '/Import/Scraper/system/image_search_pipeline.php';
if (!is_file($canonical)) {
    throw new RuntimeException('image_search_pipeline canonic lipsă: ' . $canonical);
}

require_once $canonical;
