<?php

declare(strict_types=1);

$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/public/api/furnizori_endpoint.php';
require dirname(__DIR__) . '/public/api/_autoload.php';

echo class_exists('Besoiu\Modules\Furnizori\Handler\CrudfurnizoriHandler')
    ? "endpoint bootstrap OK\n"
    : "FAIL\n";
