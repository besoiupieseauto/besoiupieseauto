<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

require_once __DIR__ . '/../src/Controllers/Produse/import_lib.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_supplier_lib.php';

use Evasystem\Controllers\Furnizori\PriceFormationLogicService;

$service = new PriceFormationLogicService();
$original = $service->getConfig();

try {
    $config = $service->saveConfig(array_merge($original, [
        'ignore_brands_by_supplier' => ['ELIT' => ['MAN']],
    ]));

    import_price_logic_reset_cache();

    $checks = [
        ['ELIT', 'MAN', true],
        ['ELIT', 'BOSCH', false],
        ['AUTOTOTAL', 'MAN', false],
    ];

    foreach ($checks as [$supplier, $brand, $expected]) {
        $actual = $service->isBrandIgnoredForSupplier($supplier, $brand);
        if ($actual !== $expected) {
            throw new RuntimeException("isBrandIgnoredForSupplier($supplier, $brand) failed");
        }
    }

    $row = ['lkq brand name' => 'MAN', 'supplier catalog nr.' => 'X1', 'net price' => '10'];
    if (import_supplier_row_passes_logic_filters($row, 'ELIT')) {
        throw new RuntimeException('import_supplier_row_passes_logic_filters should skip ELIT/MAN');
    }

    $test = $service->testConfig($config);
    $eliteMan = null;
    foreach ($test['trace'] as $entry) {
        if (($entry['code'] ?? '') === 'MAN001' && ($entry['supplier'] ?? '') === 'ELIT') {
            $eliteMan = $entry;
            break;
        }
    }

    if (!is_array($eliteMan) || ($eliteMan['action'] ?? '') !== 'omis') {
        throw new RuntimeException('testConfig should omit ELIT/MAN001');
    }

    echo "BRAND_IGNORE_TEST_PASSED\n";
} finally {
    $service->saveConfig($original);
    import_price_logic_reset_cache();
}
