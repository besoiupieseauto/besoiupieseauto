<?php

declare(strict_types=1);

/**
 * tm_083 — seed regula exemplu BMW + peste 2000 RON.
 * Usage: php admin/migrations/run_056_seed_bmw_conditional_markup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$sqlFile = __DIR__ . '/056_seed_bmw_conditional_markup.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "FAIL: missing {$sqlFile}\n");
    exit(1);
}

$sql = (string) file_get_contents($sqlFile);
$pdo->exec($sql);

$stmt = $pdo->prepare(
    'SELECT id, name, brand_filter, price_min, adjustment_type, adjustment_value, is_active
     FROM adaos_comercial_rules
     WHERE name = :name
     LIMIT 1'
);
$stmt->execute([':name' => 'BMW peste 2000 RON +3000 fix']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($row)) {
    fwrite(STDERR, "FAIL: regula BMW nu a fost creata.\n");
    exit(1);
}

echo "OK: regula tm_083 id={$row['id']} brand={$row['brand_filter']} min={$row['price_min']} +{$row['adjustment_value']} active={$row['is_active']}\n";
exit(0);
