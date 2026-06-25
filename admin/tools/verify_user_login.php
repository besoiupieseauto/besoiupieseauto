<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
\Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

use Evasystem\Controllers\Users\Users;
use Evasystem\Controllers\Users\UsersService;
use Evasystem\Core\Users\UsersModel;

$login = $argv[1] ?? 'galacradu1992@gmail.com';
$pass = $argv[2] ?? '';

$rows = UsersModel::findByLogin($login);
if (!$rows) {
    echo "USER_NOT_FOUND\n";
    exit(1);
}
$row = $rows[0];
$stored = rtrim((string) ($row['password'] ?? ''), " \t\n\r\0\x0B");
$info = password_get_info($stored);

echo "found: yes\n";
echo "randomn_id: " . ($row['randomn_id'] ?? '') . "\n";
echo "login_col: " . ($row['login'] ?? '') . "\n";
echo "status: " . ($row['status'] ?? '') . "\n";
echo "pw_len: " . strlen($stored) . "\n";
echo "pw_type: " . ($info['algo'] !== 0 ? ($info['algoName'] ?? 'bcrypt') : (strlen($stored) === 32 && ctype_xdigit($stored) ? 'md5' : 'other')) . "\n";
echo "pw_hex: " . (ctype_xdigit($stored) ? 'yes' : 'no') . "\n";
echo "pw_prefix: " . substr($stored, 0, 4) . "…\n";
if (strlen($stored) === 32 && ctype_xdigit($stored) && $pass !== '') {
    echo "md5_given_pass: " . (hash_equals($stored, md5($pass)) ? 'OK' : 'FAIL') . "\n";
}

if ($pass !== '') {
    $ok = false;
    if ($info['algo'] !== 0) {
        $ok = password_verify($pass, $stored);
        echo "password_verify: " . ($ok ? 'OK' : 'FAIL') . "\n";
    } elseif (strlen($stored) === 32 && ctype_xdigit($stored)) {
        $md5 = md5($pass);
        $ok = hash_equals($stored, $md5);
        echo "md5_match: " . ($ok ? 'OK' : 'FAIL') . "\n";
        if (!$ok) {
            echo "hint: hash in DB is not md5 of given password\n";
        }
    } else {
        $ok = hash_equals($stored, $pass);
        echo "plain_match: " . ($ok ? 'OK' : 'FAIL') . "\n";
    }

    session_start();
    $r = (new Users(new UsersService()))->login(['login' => $login, 'password' => $pass]);
    echo "controller_login: " . (($r['success'] ?? false) ? 'OK' : 'FAIL') . " — " . ($r['message'] ?? '') . "\n";
}
