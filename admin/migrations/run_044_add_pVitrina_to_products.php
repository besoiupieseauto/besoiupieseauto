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

foreach (['produse', 'import_produse'] as $table) {
    if ($columnExists($pdo, $table, 'pVitrina')) {
        echo "SKIP: {$table}.pVitrina exista deja.\n";
        continue;
    }
    $pdo->exec(
        "ALTER TABLE `{$table}`
         ADD COLUMN `pVitrina` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT 'Afisare vitrina homepage' AFTER `pBadge`"
    );
    echo "OK: {$table}.pVitrina adaugat.\n";
}

$routePath = '/admin/produse-selective';
$stmt = $pdo->prepare('SELECT COUNT(*) FROM routes WHERE method = ? AND path = ?');
$stmt->execute(['GET', $routePath]);
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->prepare(
        'INSERT INTO routes (method, path, controller, action, load_type, dir, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    )->execute(['GET', $routePath, 'Admin', 'index', 'loadPage', '/admin/Templates/admin/pages/produse/']);
    echo "OK: route produse-selective.\n";
} else {
    echo "SKIP: route produse-selective.\n";
}

foreach (['super_ambassador', 'manager'] as $role) {
    $navStmt = $pdo->prepare('SELECT COUNT(*) FROM role_nav WHERE role_slug = ? AND url = ?');
    $navStmt->execute([$role, $routePath]);
    if ((int) $navStmt->fetchColumn() === 0) {
        $pdo->prepare(
            'INSERT INTO role_nav (role_slug, label, path, url, parent_id, sort_order, icon, is_active)
             VALUES (?, ?, ?, ?, NULL, 47, ?, 1)'
        )->execute([$role, 'Produse selective', $routePath, $routePath, 'bx bx-store']);
        echo "OK: nav {$role}.\n";
    }
}

echo "Migration 044 completed.\n";
