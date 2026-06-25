<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';

use Evasystem\Core\Furnizori\FurnizoriModel;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$code = strtoupper($argv[1] ?? 'AUTOPARTNER');
$row = (new FurnizoriModel())->findByCode($code);
if ($row === null) {
    fwrite(STDERR, "Furnizor {$code} negasit.\n");
    exit(1);
}

$secrets = import_furnizori_load_secrets();
$passFromLocal = !empty($secrets[$code]['conn_password']);
if ($passFromLocal) {
    $row['conn_password'] = (string) $secrets[$code]['conn_password'];
}

$host = trim((string) ($row['conn_host'] ?? ''));
$user = trim((string) ($row['conn_username'] ?? ''));
$pass = (string) ($row['conn_password'] ?? '');
$port = (int) ($row['conn_port'] ?? 21);

echo "=== FTP diagnostic: {$code} ===\n";
echo 'host=' . $host . ' port=' . $port . ' user=' . $user . "\n";
echo 'pass_len=' . strlen($pass) . ' pass_db=' . ($pass !== '' ? 'yes' : 'NO') . ' pass_local=' . ($passFromLocal ? 'yes' : 'no') . "\n";
echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . ' ftp_ext=' . (function_exists('ftp_connect') ? 'yes' : 'no') . "\n\n";

$url = 'ftp://' . $host . ($port === 21 ? '' : ':' . $port) . '/';

$strategies = [
    'EPSV' => [CURLOPT_FTP_USE_EPSV => true, CURLOPT_FTP_USE_EPRT => false],
    'PASV' => [CURLOPT_FTP_USE_EPSV => false, CURLOPT_FTP_USE_EPRT => false],
    'ACTIVE' => [CURLOPT_FTP_USE_EPSV => false, CURLOPT_FTP_USE_EPRT => true],
];

if (defined('CURLUSESSL_ALL')) {
    $strategies['FTPS_TLS'] = [
        CURLOPT_USE_SSL => CURLUSESSL_ALL,
        CURLOPT_FTP_USE_EPSV => true,
        CURLOPT_FTP_USE_EPRT => false,
    ];
}

foreach ($strategies as $label => $opts) {
    $ch = curl_init($url);
    if ($ch === false) {
        echo "{$label}: curl_init failed\n";
        continue;
    }
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 20,
    ] + $opts);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $errno = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $bodyLen = is_string($body) ? strlen($body) : 'false';
    $errOut = $err !== '' ? $err : '(empty)';
    echo "{$label}: errno={$errno} http_code={$code} err={$errOut} body_len={$bodyLen}\n";
    if (is_string($body) && $body !== '') {
        echo '  preview: ' . substr(str_replace("\n", ' | ', $body), 0, 120) . "\n";
    }
}

echo "\n=== Native PHP ftp ===\n";
if (!function_exists('ftp_connect')) {
    echo "ftp extension missing\n";
} else {
    $conn = @ftp_connect($host, $port, 20);
    if ($conn === false) {
        echo "ftp_connect failed\n";
    } else {
        $login = @ftp_login($conn, $user, $pass);
        echo 'ftp_login=' . ($login ? 'OK' : 'FAIL') . "\n";
        if ($login) {
            @ftp_pasv($conn, true);
            $list = @ftp_nlist($conn, '.');
            echo 'nlist_count=' . (is_array($list) ? count($list) : 0) . "\n";
        }
        @ftp_close($conn);
    }
}

echo "\n=== Outbound IP ===\n";
$ctx = stream_context_create(['http' => ['timeout' => 4]]);
$ip = @file_get_contents('https://api.ipify.org', false, $ctx);
echo 'ip=' . (is_string($ip) ? trim($ip) : 'unknown') . "\n";
