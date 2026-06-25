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
$rows = $stats->listWithStats();

echo "=== furnizori (cu stats) ===\n";
foreach ($rows as $r) {
    echo json_encode([
        'name' => $r['name'] ?? '',
        'code' => $r['supplier_code'] ?? $r['code'] ?? '',
        'published' => $r['products_published'] ?? 0,
        'queue' => $r['products_queue'] ?? 0,
        'import' => $r['is_import_supplier'] ?? false,
        'priority' => $r['import_priority'] ?? null,
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
