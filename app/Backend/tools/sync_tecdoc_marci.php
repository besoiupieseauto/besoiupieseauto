<?php
declare(strict_types=1);

/**
 * Sincronizează mărcile auto (type=marca) din TecDoc în categorii.
 *
 * Usage:
 *   php app/Backend/tools/sync_tecdoc_marci.php preview
 *   php app/Backend/tools/sync_tecdoc_marci.php sync [--db-only]
 */
$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

require_once BESOIU_LEGACY . '/shop-db.php';
require_once __DIR__ . '/besoiupieseimport_tecdoc_db.php';

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

if (!defined('BESOIU_API_MANUAL_CALL')) {
    define('BESOIU_API_MANUAL_CALL', true);
}
if (!defined('BESOIU_CACHE_TECDOC')) {
    define('BESOIU_CACHE_TECDOC', BESOIU_ROOT . '/app/Storage/cache/tecdoc');
}

use Besoiu\Services\CategoriiService;

$action = strtolower(trim($argv[1] ?? 'preview'));
$dbOnly = in_array('--db-only', $argv, true);
$service = new CategoriiService();

if ($action === 'preview') {
    $preview = $service->previewTecdocMarci(!$dbOnly);
    echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

if ($action === 'sync') {
    try {
        $result = $service->syncTecdocMarci(!$dbOnly, true);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

fwrite(STDERR, "Acțiune necunoscută. Folosește: preview | sync [--db-only]\n");
exit(1);
