<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$current = (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE status <> '0' AND pVitrina = 1")->fetchColumn();
echo "vitrina_before={$current}\n";

if ($current >= 6) {
    echo "SKIP: already have {$current} vitrina products.\n";
    exit(0);
}

$need = 8 - $current;
$stmt = $pdo->prepare(
    "SELECT id FROM produse WHERE status <> '0' AND (pVitrina IS NULL OR pVitrina = 0)
     ORDER BY id DESC LIMIT {$need}"
);
$stmt->execute();
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($ids === []) {
    echo "WARN: no products available to mark as vitrina.\n";
    exit(1);
}

$update = $pdo->prepare('UPDATE produse SET pVitrina = 1 WHERE id = ?');
foreach ($ids as $id) {
    $update->execute([(int) $id]);
}

$after = (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE status <> '0' AND pVitrina = 1")->fetchColumn();
echo "marked=" . count($ids) . " vitrina_after={$after}\n";
