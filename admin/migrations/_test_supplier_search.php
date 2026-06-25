<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
use Evasystem\Services\SupplierSearch\SupplierSearchService;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);
if (!empty($config['legacy_db_name'])) {
    Database::getInstance(
        $config['legacy_db_host'],
        $config['legacy_db_name'],
        $config['legacy_db_user'],
        $config['legacy_db_pass'],
        'legacy'
    );
}

$query = $argv[1] ?? 'GDB1330';
$suppliersArg = $argv[2] ?? 'all';
$suppliers = $suppliersArg === 'all'
    ? ['materom', 'elit', 'autopartner', 'autonet', 'autototal']
    : array_values(array_filter(array_map('trim', explode(',', $suppliersArg))));

$result = (new SupplierSearchService())->search($query, $suppliers, true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
