<?php

declare(strict_types=1);

/**
 * Proxy imagini Poze/Autopartner pentru embed ERP.
 */
$erpRoot = dirname(__DIR__, 4);
require_once $erpRoot . '/app/Import/bootstrap.php';

use Besoiu\Import\Support\ImportPathResolver;

$target = ImportPathResolver::prelucrareRoot() . '/api/product-image.php';
if (!is_file($target)) {
    http_response_code(404);
    exit('product-image proxy missing: ' . $target);
}
require $target;
