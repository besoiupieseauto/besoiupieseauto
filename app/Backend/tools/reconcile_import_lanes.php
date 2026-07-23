<?php
declare(strict_types=1);

/**
 * Repară import_lane pentru produse pending (mută cu imagine din no_image → standard/showcase).
 * Usage: php app/Backend/tools/reconcile_import_lanes.php
 */
$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';
require_once BESOIU_LEGACY . '/shop-db.php';

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

use Besoiu\Services\Import\ImportLibLoader;
use Config\Database;

ImportLibLoader::bootFull(true);

$pdo = Database::getDB();
$result = import_reconcile_all_pending_lanes($pdo);

echo 'Scanate: ' . (int) ($result['scanned'] ?? 0) . "\n";
echo 'Actualizate: ' . (int) ($result['updated'] ?? 0) . "\n";
