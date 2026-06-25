<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';

use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$code = 'AUTOPARTNER';
$row = (new FurnizoriModel())->findByCode($code);
if ($row === null) {
    fwrite(STDERR, "Furnizor {$code} negasit.\n");
    exit(1);
}

$secrets = import_furnizori_resolve_credentials(array_merge($row, import_furnizori_catalog()[$code] ?? []));
$payload = [
    'connection_type' => (string) ($secrets['connection_type'] ?? 'api'),
    'api_base_url' => (string) ($secrets['api_base_url'] ?? ''),
    'api_token' => (string) ($secrets['api_token'] ?? ''),
    'conn_username' => (string) ($secrets['conn_username'] ?? '3208129'),
    'notes' => (string) ($secrets['notes'] ?? 'Auto Partner API + sync local pentru CSV.'),
];

$randomId = (int) ($row['randomn_id'] ?? 0);
$ok = (new FurnizoriModel())->updateByRandomId($randomId, $payload);

echo ($ok ? 'OK' : 'FAIL') . ' — AUTOPARTNER actualizat la mod API.' . PHP_EOL;
echo 'connection_type=' . $payload['connection_type'] . PHP_EOL;
echo 'api_base_url=' . $payload['api_base_url'] . PHP_EOL;
echo 'api_token_len=' . strlen($payload['api_token']) . PHP_EOL;
