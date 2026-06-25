<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
\Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

use Evasystem\Controllers\Users\Users;
use Evasystem\Controllers\Users\UsersService;

$login = $argv[1] ?? 'admin';
$pass = $argv[2] ?? '';
if ($pass === '') {
    fwrite(STDERR, "Usage: php tools/test_login_local.php <login> <password>\n");
    exit(1);
}
session_start();
$r = (new Users(new UsersService()))->login(['login' => $login, 'password' => $pass]);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
