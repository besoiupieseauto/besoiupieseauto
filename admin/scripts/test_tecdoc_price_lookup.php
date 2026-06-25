<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Controllers/Produse/import_lib.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_supplier_lib.php';

$row = [
    'art code 1' => '558009210',
    'art brand' => 'INA',
    'art cross' => 'OEM::123456',
];

$codes = import_lookup_codes_for_row($row);
echo json_encode($codes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
