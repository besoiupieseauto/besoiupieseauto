<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);
$pdo = Config\Database::getDB();

echo "=== ELIT in produse ===\n";
foreach ($pdo->query("SELECT id, pCode, pBrand, pName, pSupplier FROM produse WHERE UPPER(TRIM(pSupplier)) = 'ELIT'") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== ELIT in import_produse ===\n";
foreach ($pdo->query("SELECT id, pCode, pBrand, pName, pSupplier, status FROM import_produse WHERE UPPER(TRIM(pSupplier)) = 'ELIT' LIMIT 5") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== produse columns ===\n";
foreach ($pdo->query('SHOW COLUMNS FROM produse') as $r) {
    if (stripos($r['Field'], 'status') !== false || stripos($r['Field'], 'pCode') !== false) {
        echo $r['Field'] . "\n";
    }
}

$stats = new FurnizoriStatsService(new FurnizoriModel());
$elit = (new FurnizoriModel())->findByCode('ELIT');
echo "\n=== ELIT furnizor randomn_id ===\n";
echo json_encode(['randomn_id' => $elit['randomn_id'] ?? null], JSON_UNESCAPED_UNICODE) . "\n";

try {
    $products = $stats->listProductsForSupplier('ELIT', 100, 0);
    echo "\n=== listProductsForSupplier ===\n";
    echo json_encode($products, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
}
