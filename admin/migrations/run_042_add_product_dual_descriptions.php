<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$tables = ['produse', 'import_produse'];
$columns = ['pNoteWebsite', 'pNoteMarketplace'];
$added = 0;

foreach ($tables as $table) {
    foreach ($columns as $column) {
        if ($columnExists($pdo, $table, $column)) {
            echo "SKIP: {$table}.{$column} exista deja.\n";
            continue;
        }

        $after = $column === 'pNoteWebsite' ? 'pNote' : 'pNoteWebsite';
        $comment = $column === 'pNoteWebsite'
            ? 'Descriere curatata pentru website'
            : 'Descriere detaliata pentru marketplace';

        $pdo->exec(
            "ALTER TABLE `{$table}`
             ADD COLUMN `{$column}` LONGTEXT NULL COMMENT '{$comment}' AFTER `{$after}`"
        );
        echo "OK: {$table}.{$column} adaugat.\n";
        $added++;
    }
}

if ($added === 0) {
    echo "Migration 042: nimic de facut.\n";
} else {
    echo "Migration 042 completed ({$added} coloane).\n";
}
