<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$row = (new Evasystem\Core\Furnizori\FurnizoriModel())->findByCode('AUTONET');
$secrets = import_furnizori_load_secrets();
$pass = (string)($secrets['AUTONET']['conn_password'] ?? $row['conn_password'] ?? '');

$host = trim((string)($row['conn_host'] ?? 'caietcomenzi.ro'));
$user = trim((string)($row['conn_username'] ?? ''));
echo "user=$user host=$host pass_len=" . strlen($pass) . "\n";

function curlFtpList(string $url, string $user, string $pass, bool $passive = true): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FTP_USE_EPSV => $passive,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return ['body' => is_string($body) ? $body : '', 'error' => $err];
}

foreach (['ftp://' . $host . '/', 'ftp://' . $host . '/export/', 'ftps://' . $host . '/'] as $url) {
    echo "\n=== LIST $url ===\n";
    $r = curlFtpList($url, $user, $pass);
    if ($r['error'] !== '') {
        echo "ERR: {$r['error']}\n";
        continue;
    }
    echo trim($r['body']) !== '' ? $r['body'] : "(gol)\n";
}
