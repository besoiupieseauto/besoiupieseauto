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

use Evasystem\Controllers\Furnizori\PriceFormationLogicService;

$service = new PriceFormationLogicService();
$config = $service->getConfig();
$test = $service->testConfig($config);
$map = $service->getPriorityMap();

echo "CONFIG_OK\n";
echo json_encode($config, JSON_UNESCAPED_UNICODE) . "\n";
echo "PRIORITY_MAP_OK\n";
echo json_encode($map, JSON_UNESCAPED_UNICODE) . "\n";
echo "TEST_WINNERS=" . count($test['winners'] ?? []) . "\n";
echo "TEST_TRACE=" . count($test['trace'] ?? []) . "\n";

if (($test['winners'] ?? []) === []) {
    fwrite(STDERR, "FAIL: no winners in test\n");
    exit(1);
}

echo "PRICE_LOGIC_TEST_PASSED\n";
