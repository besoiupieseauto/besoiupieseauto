<?php

declare(strict_types=1);

$erpRoot = dirname(__DIR__, 4);
require_once $erpRoot . '/app/Import/bootstrap.php';

use Besoiu\Import\Support\ImportPathResolver;

$target = ImportPathResolver::scraperRoot() . '/assets/product-search-passes.js';
if (!is_file($target)) {
    http_response_code(404);
    exit('/* scraper passes missing: ' . $target . ' */');
}
header('Content-Type: application/javascript; charset=utf-8');
readfile($target);
