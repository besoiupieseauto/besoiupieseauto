<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';

use Evasystem\Controllers\Furnizori\FtpConnectionClient;
use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$code = strtoupper($argv[1] ?? 'AUTOPARTNER');
$row = (new FurnizoriModel())->findByCode($code);
$secrets = import_furnizori_load_secrets();
if (!empty($secrets[$code]['conn_password'])) {
    $row['conn_password'] = $secrets[$code]['conn_password'];
}

echo "curl=" . (function_exists('curl_init') ? 'yes' : 'no') . " ftp_ext=" . (function_exists('ftp_connect') ? 'yes' : 'no') . "\n";

$result = (new FtpConnectionClient())->configure($row)->browse('/', '');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
