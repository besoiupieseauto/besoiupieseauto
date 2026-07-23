<?php

declare(strict_types=1);

$erpRoot = dirname(__DIR__, 4);
require_once $erpRoot . '/app/Import/bootstrap.php';

use Besoiu\Import\Support\ImportPathResolver;

$target = ImportPathResolver::fetchProductRoot() . '/api/index-status.php';
if (!is_file($target)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'fetch index-status missing: ' . $target], JSON_UNESCAPED_UNICODE);
    exit;
}
require $target;
