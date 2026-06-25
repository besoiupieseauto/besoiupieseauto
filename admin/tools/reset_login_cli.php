<?php
declare(strict_types=1);

/**
 * Reset parolă utilizator admin (CLI, pe server sau local cu același .env).
 *
 *   cd E:\laragon\www\besoiupieseauto.ro\admin
 *   php tools/reset_login_cli.php email@sau_user "ParolaNoua2026!"
 *
 * Listă conturi:
 *   php tools/reset_login_cli.php --list
 */
require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

use Evasystem\Core\Users\UsersModel;

if (($argv[1] ?? '') === '--list') {
    $pdo = \Config\Database::getDB();
    $rows = $pdo->query(
        'SELECT randomn_id, login, contact, nikname, role, status, LENGTH(password) AS pw_len
         FROM users_connect ORDER BY randomn_id DESC LIMIT 30'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    exit(0);
}

$login = trim($argv[1] ?? '');
$pass = (string) ($argv[2] ?? '');
if ($login === '' || $pass === '') {
    fwrite(STDERR, "Usage: php tools/reset_login_cli.php <login_sau_email> \"Parola\"\n");
    fwrite(STDERR, "       php tools/reset_login_cli.php --list\n");
    exit(1);
}

$rows = UsersModel::findByLogin($login);
$row = $rows[0] ?? null;
if (!$row || empty($row['randomn_id'])) {
    fwrite(STDERR, "Utilizator negăsit pentru: {$login}\nRulează: php tools/reset_login_cli.php --list\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$ok = UsersModel::updatePasswordHash((int) $row['randomn_id'], $hash);
if (!$ok) {
    fwrite(STDERR, "Update parolă eșuat.\n");
    exit(1);
}

echo "OK — parolă resetată pentru login={$row['login']} (randomn_id={$row['randomn_id']})\n";
echo "Testează: https://besoiupieseauto.ro/admin/login\n";
