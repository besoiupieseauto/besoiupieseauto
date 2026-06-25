<?php

declare(strict_types=1);

/**
 * Audit convenții SQL (11) pe baza de date activă.
 * Rulează: php tools/audit_sql_conventions.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$legacyHyphenTables = [];
$findings = [];

foreach ($tables as $tableName) {
    $issues = [];

    if (preg_match('/[-A-Z]/', $tableName)) {
        $issues[] = 'nume_tabel_non_standard';
    }

    if (!preg_match('/s$|_logs$|_items$|_oem$/', $tableName) && !in_array($tableName, $legacyHyphenTables, true)) {
        $issues[] = 'posibil_singular';
    }

    $columns = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '`')->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    $hasCreatedAt = in_array('created_at', $columnNames, true);
    $hasUpdatedAt = in_array('updated_at', $columnNames, true);

    if (!$hasCreatedAt && !in_array($tableName, ['routes', 'role_nav', 'migrations'], true)) {
        $issues[] = 'lipseste_created_at';
    }

    foreach ($columnNames as $columnName) {
        if (preg_match('/^(active|published|found|status)$/', $columnName) && strpos($columnName, 'is_') !== 0) {
            $issues[] = 'boolean_fara_prefix_is_: ' . $columnName;
        }
        if (preg_match('/_date$/', $columnName) && !preg_match('/^(created|updated|deleted)_at$/', $columnName)) {
            $issues[] = 'data_fara_sufix_at: ' . $columnName;
        }
    }

    if ($issues !== []) {
        $findings[$tableName] = [
            'issues' => $issues,
            'columns' => $columnNames,
            'has_created_at' => $hasCreatedAt,
            'has_updated_at' => $hasUpdatedAt,
            'legacy_exception' => in_array($tableName, $legacyHyphenTables, true),
        ];
    }
}

$reportDir = __DIR__ . '/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0775, true);
}

$reportPath = $reportDir . '/sql_conventions_audit.json';
file_put_contents($reportPath, json_encode([
    'generated_at' => date('c'),
    'database' => $config['db_name'],
    'tables_total' => count($tables),
    'tables_with_findings' => count($findings),
    'legacy_hyphen_tables' => $legacyHyphenTables,
    'findings' => $findings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Audit SQL salvat: ' . $reportPath . PHP_EOL;
echo 'Tabele totale: ' . count($tables) . PHP_EOL;
echo 'Tabele cu abateri: ' . count($findings) . PHP_EOL;
