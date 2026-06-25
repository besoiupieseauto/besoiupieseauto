<?php

declare(strict_types=1);

/**
 * MIGRARE 037: redenumește tabele hub cu cratimă → convenție snake_case (11).
 * cross-reference → cross_reference
 * search-logs → search_logs_scaffold (evită conflict cu search_logs jurnal VIN/OEM)
 */

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

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND TABLE_TYPE = \'BASE TABLE\''
    );
    $stmt->execute([':t' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

$renames = [
    'cross-reference' => 'cross_reference',
    'search-logs' => 'search_logs_scaffold',
];

foreach ($renames as $from => $to) {
    if (!tableExists($pdo, $from)) {
        echo "SKIP {$from} (lipsă)\n";
        continue;
    }
    if (tableExists($pdo, $to)) {
        echo "SKIP {$from} → {$to} (destinația există deja)\n";
        continue;
    }

    $pdo->exec('RENAME TABLE `' . str_replace('`', '``', $from) . '` TO `' . str_replace('`', '``', $to) . '`');
    echo "OK RENAME {$from} → {$to}\n";
}

$pdo->exec("UPDATE schema_legacy_registry SET is_active = 0 WHERE table_name IN ('cross-reference', 'search-logs')");

$pdo->exec(
    "INSERT INTO schema_legacy_registry (table_name, reason, target_name, is_active)
     SELECT 'cross_reference', 'Redenumit din cross-reference (migrare 037)', 'cross_reference', 0
     WHERE NOT EXISTS (SELECT 1 FROM schema_legacy_registry WHERE table_name = 'cross_reference')"
);

$pdo->exec(
    "INSERT INTO schema_legacy_registry (table_name, reason, target_name, is_active)
     SELECT 'search_logs_scaffold', 'Redenumit din search-logs — scaffold CRUD hub', 'search_logs_scaffold', 0
     WHERE NOT EXISTS (SELECT 1 FROM schema_legacy_registry WHERE table_name = 'search_logs_scaffold')"
);

echo "Migration 037 completed.\n";
