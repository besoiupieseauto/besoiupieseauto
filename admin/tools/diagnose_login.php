<?php
declare(strict_types=1);

/**
 * Diagnostic login — fără parole în output.
 * Usage: php tools/diagnose_login.php [login]
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

use Config\Database;
use Evasystem\Core\Users\UsersModel;

$probeLogin = trim($argv[1] ?? 'admin');

echo "=== Diagnose login Besoiu admin ===\n";
echo 'DB_HOST: ' . ($_ENV['DB_HOST'] ?? '?') . "\n";
echo 'DB_NAME: ' . ($_ENV['DB_NAME'] ?? '?') . "\n";
echo 'Probe login: ' . $probeLogin . "\n\n";

try {
    $pdo = Database::getDB();
    $pdo->query('SELECT 1');
    echo "DB connection: OK\n";
} catch (Throwable $e) {
    echo "DB connection: FAIL — " . $e->getMessage() . "\n";
    exit(1);
}

$rows = UsersModel::findByLogin($probeLogin);
echo 'findByLogin rows: ' . count($rows) . "\n";

if ($rows === []) {
    echo "\nUsers in users_connect (login column sample):\n";
    $all = $pdo->query('SELECT randomn_id, login, role, status, LENGTH(password) AS pw_len, password AS pw_preview FROM users_connect ORDER BY randomn_id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all as $r) {
        $pw = (string) ($r['pw_preview'] ?? '');
        $algo = password_get_info($pw)['algoName'] ?? (strlen($pw) === 32 && ctype_xdigit($pw) ? 'md5' : 'unknown/empty');
        unset($r['pw_preview']);
        $r['pw_algo'] = $algo;
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
    exit(0);
}

$row = $rows[0];
$stored = rtrim((string) ($row['password'] ?? ''), " \t\n\r\0\x0B");
$info = password_get_info($stored);
echo "\nUser found:\n";
echo '  randomn_id: ' . ($row['randomn_id'] ?? '?') . "\n";
echo '  login: ' . ($row['login'] ?? '?') . "\n";
echo '  role: ' . ($row['role'] ?? '?') . "\n";
echo '  status: ' . ($row['status'] ?? '?') . "\n";
echo '  password len: ' . strlen($stored) . "\n";
echo '  password algo: ' . ($info['algo'] !== 0 ? ($info['algoName'] ?? 'bcrypt') : (strlen($stored) === 32 && ctype_xdigit($stored) ? 'md5' : 'empty/unknown')) . "\n";

if (getenv('BOON_TEST_PASSWORD') !== false && getenv('BOON_TEST_PASSWORD') !== '') {
    $test = (string) getenv('BOON_TEST_PASSWORD');
    $ok = false;
    if ($info['algo'] !== 0) {
        $ok = password_verify($test, $stored);
    } elseif (strlen($stored) === 32 && ctype_xdigit($stored)) {
        $ok = hash_equals($stored, md5($test));
    }
    echo '  BOON_TEST_PASSWORD verify: ' . ($ok ? 'OK' : 'FAIL') . "\n";
} else {
    echo "  (set BOON_TEST_PASSWORD env to test verify without printing hash)\n";
}

echo "\nDone.\n";
