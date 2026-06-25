<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Controllers\Furnizori\Furnizori;
use Evasystem\Controllers\Furnizori\FurnizoriService;
use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$row = (new FurnizoriModel())->findByCode('AUTONET');
$randomId = (int)($row['randomn_id'] ?? 0);

$controller = new Furnizori(new FurnizoriService(new FurnizoriModel(), new FurnizoriStatsService(new FurnizoriModel())));

try {
    $result = $controller->browse(['randomn_id' => $randomId, 'path' => '/']);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
