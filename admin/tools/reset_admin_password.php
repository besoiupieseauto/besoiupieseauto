<?php
declare(strict_types=1);

/**
 * Reset parolă admin (CLI) — users_connect.
 * Usage: php tools/reset_admin_password.php admin "NouaParola1"
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = dirname(__DIR__) . '/.env';
if (is_file($dotenv)) {
    foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

use Config\Database;
use Evasystem\Core\Users\UsersModel;

$login = trim($argv[1] ?? '');
$pass = (string) ($argv[2] ?? '');
if ($login === '' || $pass === '') {
    fwrite(STDERR, "Usage: php tools/reset_admin_password.php <login> <parola>\n");
    exit(1);
}

$rows = UsersModel::findByLogin($login);
$row = $rows[0] ?? null;
if (!$row || empty($row['randomn_id'])) {
    fwrite(STDERR, "User not found: {$login}\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$pdo = Database::getDB();
$stmt = $pdo->prepare('UPDATE `users_connect` SET `password` = :p WHERE `randomn_id` = :id');
$stmt->execute(['p' => $hash, 'id' => (int) $row['randomn_id']]);

echo "OK — parolă resetată pentru login={$login} (id {$row['randomn_id']})\n";
