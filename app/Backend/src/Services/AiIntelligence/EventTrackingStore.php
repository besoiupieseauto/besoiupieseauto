<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Config\Database;
use PDO;
use Throwable;

/**
 * Schema tracking evenimente — ensureTable + verificări (Pas 2).
 *
 * Tabele: ai_intel_sessions, ai_intel_events, ai_intel_aggregates
 */
final class EventTrackingStore
{
    public const TABLE_SESSIONS = 'ai_intel_sessions';
    public const TABLE_EVENTS = 'ai_intel_events';
    public const TABLE_AGGREGATES = 'ai_intel_aggregates';

    private static bool $tablesEnsured = false;

    /** @var list<string> */
    public const ALLOWED_EVENT_TYPES = [
        'page_view',
        'search_query',
        'search_result_click',
        'product_view',
        'add_to_cart',
        'purchase',
        'recommendation_click',
        'image_check_result',
        'filter_applied',
        'ui_click',
    ];

    private PDO $pdo;
    private string $root;

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo();
        $this->ensureTables();
    }

    private function resolvePdo(): PDO
    {
        if (class_exists(Database::class) && Database::hasConnection()) {
            return Database::getDB();
        }
        $config = require $this->root . '/admin/config/config.php';
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return Database::getDB();
    }

    public function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        $migration = $this->root . '/modules/ai-intelligence/migrations/001_events_tracking.sql';
        if (is_file($migration)) {
            $sql = (string) file_get_contents($migration);
            $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;
            foreach ($this->splitStatements($sql) as $statement) {
                if (trim($statement) !== '') {
                    $this->execMigrationStatement($statement);
                }
            }
        } else {
            $this->ensureTablesInline();
        }

        $migration2 = $this->root . '/modules/ai-intelligence/migrations/002_session_visitor_context.sql';
        if (is_file($migration2)) {
            $sql2 = (string) file_get_contents($migration2);
            $sql2 = preg_replace('/--[^\n]*/', '', $sql2) ?? $sql2;
            foreach ($this->splitStatements($sql2) as $statement) {
                if (trim($statement) !== '') {
                    try {
                        $this->execMigrationStatement($statement);
                    } catch (Throwable) {
                        /* versiuni MySQL fără IF NOT EXISTS */
                    }
                }
            }
        }

        $this->ensureSessionVisitorColumns();

        self::$tablesEnsured = true;
    }

    /** Coloane vizitator (Pas 9) — idempotent. */
    public function ensureSessionVisitorColumns(): void
    {
        $columns = [
            'ip_address' => 'VARCHAR(45) NULL',
            'country_code' => 'CHAR(2) NULL',
            'country_name' => 'VARCHAR(80) NULL',
            'city' => 'VARCHAR(80) NULL',
            'region' => 'VARCHAR(80) NULL',
            'user_agent' => 'VARCHAR(512) NULL',
            'device_type' => 'VARCHAR(20) NULL',
            'browser' => 'VARCHAR(80) NULL',
            'os' => 'VARCHAR(80) NULL',
            'referrer' => 'VARCHAR(500) NULL',
            'landing_page' => 'VARCHAR(500) NULL',
            'last_seen_at' => 'DATETIME(3) NULL',
            'event_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        ];

        $existing = $this->tableColumnNames(self::TABLE_SESSIONS);

        foreach ($columns as $name => $definition) {
            if (in_array(strtolower($name), $existing, true)) {
                continue;
            }
            try {
                $this->pdo->exec(
                    'ALTER TABLE ' . self::TABLE_SESSIONS . ' ADD COLUMN ' . $name . ' ' . $definition
                );
                $existing[] = strtolower($name);
            } catch (Throwable) {
                /* ignore duplicate */
            }
        }

        $existingIndexes = $this->tableIndexNames(self::TABLE_SESSIONS);
        foreach (['idx_ai_intel_sess_last_seen' => 'last_seen_at', 'idx_ai_intel_sess_country' => 'country_code', 'idx_ai_intel_sess_ip' => 'ip_address'] as $idx => $col) {
            if (in_array(strtolower($idx), $existingIndexes, true)) {
                continue;
            }
            try {
                $this->pdo->exec(
                    'CREATE INDEX ' . $idx . ' ON ' . self::TABLE_SESSIONS . ' (' . $col . ')'
                );
                $existingIndexes[] = strtolower($idx);
            } catch (Throwable) {
                /* ignore */
            }
        }
    }

    /** @return list<string> lower-case column names */
    private function tableColumnNames(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t'
            );
            $stmt->execute([':t' => $table]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();

            return array_values(array_map(
                static fn (array $row): string => strtolower((string) ($row['column_name'] ?? '')),
                $rows
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> lower-case index names */
    private function tableIndexNames(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT index_name FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = :t
                 GROUP BY index_name'
            );
            $stmt->execute([':t' => $table]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();

            return array_values(array_map(
                static fn (array $row): string => strtolower((string) ($row['index_name'] ?? '')),
                $rows
            ));
        } catch (Throwable) {
            return [];
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return in_array(strtolower($column), $this->tableColumnNames($table), true);
    }

    private function indexExists(string $table, string $index): bool
    {
        return in_array(strtolower($index), $this->tableIndexNames($table), true);
    }

    private function ensureTablesInline(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_SESSIONS . ' (
                session_id CHAR(36) NOT NULL,
                user_id VARCHAR(64) NULL,
                started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                source VARCHAR(50) NOT NULL DEFAULT \'web\',
                PRIMARY KEY (session_id),
                KEY idx_ai_intel_sess_user (user_id),
                KEY idx_ai_intel_sess_started (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_EVENTS . ' (
                event_id CHAR(36) NOT NULL,
                session_id CHAR(36) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id VARCHAR(100) NULL,
                metadata JSON NOT NULL,
                ts DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (event_id),
                KEY idx_ai_intel_ev_type_ts (event_type, ts),
                KEY idx_ai_intel_ev_entity (entity_type, entity_id),
                KEY idx_ai_intel_ev_session (session_id),
                KEY idx_ai_intel_ev_ts (ts),
                CONSTRAINT fk_ai_intel_ev_session
                    FOREIGN KEY (session_id) REFERENCES ' . self::TABLE_SESSIONS . ' (session_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_AGGREGATES . ' (
                entity_id VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                period_date DATE NOT NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                purchases INT UNSIGNED NOT NULL DEFAULT 0,
                ctr DECIMAL(6,4) NULL,
                PRIMARY KEY (entity_id, entity_type, period_date),
                KEY idx_ai_intel_agg_date (period_date),
                KEY idx_ai_intel_agg_ctr (ctr),
                KEY idx_ai_intel_agg_views (views)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, mixed> */
    public function schemaStatus(): array
    {
        $tables = [
            self::TABLE_SESSIONS,
            self::TABLE_EVENTS,
            self::TABLE_AGGREGATES,
        ];
        $status = [];
        foreach ($tables as $table) {
            $status[$table] = $this->tableExists($table);
        }

        return [
            'ok' => !in_array(false, $status, true),
            'tables' => $status,
            'event_types' => self::ALLOWED_EVENT_TYPES,
        ];
    }

    public function isEventTypeAllowed(string $eventType): bool
    {
        return in_array($eventType, self::ALLOWED_EVENT_TYPES, true);
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            $exists = (bool) $stmt->fetchColumn();
            $stmt->closeCursor();

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $parts = preg_split('/;\s*\n/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    /** Exec DDL/DML; SELECT via query()+fetchAll ca să nu lase cursor PDO deschis (MySQL 2014). */
    private function execMigrationStatement(string $statement): void
    {
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $statement)) {
            $stmt = $this->pdo->query($statement);
            if ($stmt !== false) {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            }

            return;
        }

        $this->pdo->exec($statement);
    }
}
