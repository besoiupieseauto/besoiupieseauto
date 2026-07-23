<?php
declare(strict_types=1);

/**
 * Migrare product_compatibilities (besoiu_tecdoc_base) → tecdoc_product_compatibilities (shop).
 *
 * Usage:
 *   php app/Backend/tools/migrate_tecdoc_compatibilities.php
 *   php app/Backend/tools/migrate_tecdoc_compatibilities.php --force
 *   php app/Backend/tools/migrate_tecdoc_compatibilities.php --batch=250000
 *
 * Țintă: ~32M rânduri în < 90 minute (INSERT SELECT pe batch-uri, același product_id).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Rulează doar din CLI.\n");
    exit(1);
}

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

require __DIR__ . '/besoiupieseimport_tecdoc_db.php';

$force = in_array('--force', $argv, true);
$batchSize = 500000;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batchSize = max(10000, (int) substr($arg, 8));
    }
}

$maxMinutes = 85;
$deadline = microtime(true) + ($maxMinutes * 60);

$result = besoiupieseimport_tecdoc_migrate_compatibilities([
    'force' => $force,
    'batch_size' => $batchSize,
    'deadline_ts' => $deadline,
    'logger' => static function (string $msg): void {
        echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    },
]);

echo PHP_EOL;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
