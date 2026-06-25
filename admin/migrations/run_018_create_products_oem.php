<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/system/products_oem.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/018_create_products_oem.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

$pdo->exec($sql);
echo "OK: tabela products_oem creata.\n";

$totalProcessed = 0;
$totalSynced = 0;
$totalCodes = 0;
$offset = 0;

while (true) {
    $batch = products_oem_backfill_all($pdo, 500, $offset);
    if (($batch['processed'] ?? 0) === 0) {
        break;
    }
    $totalProcessed += (int) $batch['processed'];
    $totalSynced += (int) $batch['synced'];
    $totalCodes += (int) $batch['codes'];
    $offset += 500;
    echo "Backfill batch: processed={$batch['processed']} synced={$batch['synced']} codes={$batch['codes']}\n";
}

$stats = products_oem_stats($pdo);
echo "Backfill total: processed={$totalProcessed} synced={$totalSynced} codes={$totalCodes}\n";
echo "Stats: products={$stats['total_products']} oem_codes={$stats['total_codes']}\n";
echo "Migration 018 completed.\n";
