<?php

declare(strict_types=1);

/**
 * Shim compat — sursa canonică: app/Import/Scraper/lib/ScraperLlmConfig.php
 * Evită "Cannot declare class ScraperLlmConfig" când Import + Backend încarcă ambele căi.
 */
if (class_exists('ScraperLlmConfig', false)) {
    return;
}

$canonical = dirname(__DIR__, 2) . '/Import/Scraper/lib/ScraperLlmConfig.php';
if (!is_file($canonical)) {
    throw new RuntimeException('Lipsește ScraperLlmConfig canonic: ' . $canonical);
}

require_once $canonical;
