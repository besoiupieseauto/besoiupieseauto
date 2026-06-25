<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$stats = new FurnizoriStatsService(new FurnizoriModel());
$rows = $stats->seedCatalogConnections();

echo "Furnizori configurati (" . count($rows) . "):\n";
foreach ($rows as $row) {
    echo sprintf(
        "- %s [%s] %s %s\n",
        $row['code'],
        $row['connection_type'],
        $row['conn_host'] !== '' ? $row['conn_host'] : '(fara host)',
        $row['name']
    );
}
