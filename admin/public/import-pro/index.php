<?php

declare(strict_types=1);

/**
 * Front controller ERP — Import & Matching Pro (motor intern app/Import/MatchingPro).
 * URL: /admin/public/import-pro/
 */
define('BESOIU_IMPORT_PUBLIC_WRAPPER', true);
define('BESOIU_IMPORT_WEB_BASE', '/admin/public/import-pro/');
define('BESOIU_IMPORT_SCRAPER_API', '/admin/public/import-pro/_proxy/scraper-api.php');

$erpRoot = dirname(__DIR__, 3);
$importBootstrap = $erpRoot . '/app/Import/bootstrap.php';
if (is_file($importBootstrap)) {
    require_once $importBootstrap;
}

use Besoiu\Import\Support\ImportPathResolver;

ImportPathResolver::applyEnv();

$importIndex = ImportPathResolver::matchingProRoot() . '/index.php';
if (!is_file($importIndex)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ro"><body style="font-family:sans-serif;padding:24px">';
    echo '<h1>Import & Matching Pro — indisponibil</h1>';
    echo '<p>Motorul nu a fost găsit în <code>app/Import/MatchingPro/</code>.</p></body></html>';
    exit;
}

require $importIndex;
