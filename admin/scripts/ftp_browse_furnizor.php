<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

use Evasystem\Core\Furnizori\FurnizoriModel;

$code = strtoupper($argv[1] ?? 'AUTONET');
$row = (new FurnizoriModel())->findByCode($code);
if ($row === null) {
    fwrite(STDERR, "Furnizor $code negasit\n");
    exit(1);
}

$host = trim((string)($row['conn_host'] ?? ''));
$user = trim((string)($row['conn_username'] ?? ''));
$secrets = import_furnizori_load_secrets();
$pass = (string)($secrets[$code]['conn_password'] ?? $row['conn_password'] ?? '');
$port = (int)($row['conn_port'] ?? 21);
$passive = !empty($row['conn_passive']);
$remotePath = trim((string)($row['conn_remote_path'] ?? ''));

echo "=== FTP probe: $code ===\n";
echo "Host: $host:$port passive=" . ($passive ? 'yes' : 'no') . "\n";
echo "User: $user\n";
echo "Configured path: " . ($remotePath !== '' ? $remotePath : '(empty)') . "\n\n";

$conn = @ftp_connect($host, $port, 15);
if ($conn === false) {
    fwrite(STDERR, "FAIL: connect\n");
    exit(2);
}

$loggedIn = @ftp_login($conn, $user, $pass);
if (!$loggedIn) {
    echo "Login failed, retry with alternate passive mode...\n";
    @ftp_pasv($conn, !$passive);
    $loggedIn = @ftp_login($conn, $user, $pass);
}
if (!$loggedIn) {
    @ftp_close($conn);
    fwrite(STDERR, "FAIL: login (verifica user/parola)\n");
    exit(3);
}

@ftp_pasv($conn, $passive);

function listDir($conn, string $label, string $dir): void
{
    echo "--- $label: $dir ---\n";
    $pwd = @ftp_pwd($conn);
    if (!@ftp_chdir($conn, $dir)) {
        echo "  (nu pot accesa)\n\n";
        if ($pwd) {
            @ftp_chdir($conn, $pwd);
        }
        return;
    }
    $items = @ftp_nlist($conn, '.');
    if (!is_array($items)) {
        echo "  (listare esuata)\n\n";
        return;
    }
    sort($items);
    foreach ($items as $item) {
        $name = basename(str_replace('\\', '/', (string)$item));
        $size = @ftp_size($conn, $name);
        $sizeLabel = $size >= 0 ? number_format($size) . ' bytes' : 'dir?';
        echo "  $name  [$sizeLabel]\n";
    }
    echo "\n";
    if ($pwd) {
        @ftp_chdir($conn, $pwd);
    }
}

function peekFile($conn, string $path, int $bytes = 2048): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'ftppeek_');
    if ($tmp === false) {
        return;
    }
    echo "--- Preview: $path (first {$bytes} bytes) ---\n";
    if (!@ftp_get($conn, $tmp, $path, FTP_BINARY)) {
        echo "  (download esuat)\n\n";
        @unlink($tmp);
        return;
    }
    $content = file_get_contents($tmp, false, null, 0, $bytes) ?: '';
    @unlink($tmp);
    echo substr($content, 0, $bytes) . "\n\n";
}

listDir($conn, 'Root', '/');
listDir($conn, 'Export folder', '/export');

if ($remotePath !== '') {
    if (str_contains($remotePath, '.')) {
        peekFile($conn, $remotePath);
        $parent = dirname(str_replace('\\', '/', $remotePath));
        if ($parent !== '.' && $parent !== '') {
            listDir($conn, 'Parent of configured file', $parent);
        }
    } else {
        listDir($conn, 'Configured path', $remotePath);
    }
}

@ftp_close($conn);
echo "Done.\n";
