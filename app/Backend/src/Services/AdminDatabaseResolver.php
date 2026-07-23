<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PDO admin — reutilizabil din API JSON când Database nu e încă inițializat.
 */
final class AdminDatabaseResolver
{
    private static bool $dotenvLoaded = false;

    public static function siteRoot(): string
    {
        if (defined('BESOIU_ROOT')) {
            return (string) BESOIU_ROOT;
        }

        return dirname(__DIR__, 4);
    }

    public static function pdo(): PDO
    {
        try {
            if (Database::hasConnection()) {
                return Database::getDB();
            }
        } catch (Throwable) {
            // init below
        }

        self::ensureDotenv();
        $config = self::loadConfig();
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return Database::getDB();
    }

    /** @return array<string, mixed> */
    public static function loadConfig(): array
    {
        $root = self::siteRoot();
        foreach ([
            $root . '/admin/config/config.php',
            $root . '/app/Backend/config/config.php',
            $root . '/app/Config/config.php',
        ] as $path) {
            if (is_file($path)) {
                $config = require $path;

                return is_array($config) ? $config : [];
            }
        }

        throw new RuntimeException('Config BD lipsă (admin/app/Backend/app/Config).');
    }

    public static function ensureDotenv(): void
    {
        if (self::$dotenvLoaded || !class_exists(\Dotenv\Dotenv::class)) {
            return;
        }

        $root = self::siteRoot();
        foreach ([
            $root . '/admin',
            $root . '/app/Backend',
            $root . '/app/Config',
        ] as $dir) {
            if (!is_file($dir . '/.env')) {
                continue;
            }
            try {
                \Dotenv\Dotenv::createImmutable($dir)->safeLoad();
            } catch (Throwable) {
                // optional env file
            }
        }

        self::$dotenvLoaded = true;
    }

    public static function hasTable(PDO $pdo, string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        try {
            // information_schema — fiabil cu prepared statements (SHOW TABLES LIKE ? poate da fals negativ în PDO/MySQL).
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute([$table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            try {
                $quoted = $pdo->quote($table);
                $stmt = $pdo->query('SHOW TABLES LIKE ' . $quoted);

                return (bool) $stmt?->fetchColumn();
            } catch (Throwable) {
                return false;
            }
        }
    }

    public static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ?');
            $stmt->execute([$column]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Snapshot rapid pentru Centru AI — produse, import, comenzi.
     *
     * @return array<string, mixed>
     */
    public static function aiLiveSnapshot(?PDO $pdo = null): array
    {
        try {
            $pdo = $pdo ?? self::pdo();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }

        $out = [
            'ok' => true,
            'database' => (string) ($pdo->query('SELECT DATABASE()')?->fetchColumn() ?: ''),
            'products' => null,
            'import_queue' => null,
            'orders' => null,
        ];

        try {
            if (self::hasTable($pdo, 'produse')) {
                $row = $pdo->query(
                    "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status IS NULL OR status <> '0' THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN (status IS NULL OR status <> '0')
                            AND (pImages IS NULL OR TRIM(pImages) = '' OR pImages IN ('[]', 'null')) THEN 1 ELSE 0 END) AS no_image,
                        SUM(CASE WHEN (status IS NULL OR status <> '0') AND pCategory <> '' THEN 1 ELSE 0 END) AS with_category
                     FROM produse"
                )?->fetch(PDO::FETCH_ASSOC) ?: [];

                $cats = (int) $pdo->query(
                    "SELECT COUNT(DISTINCT pCategory) FROM produse WHERE (status IS NULL OR status <> '0') AND pCategory <> ''"
                )?->fetchColumn();

                $vitrina = null;
                if (self::hasColumn($pdo, 'produse', 'pVitrina')) {
                    $vitrina = (int) $pdo->query(
                        "SELECT COUNT(*) FROM produse WHERE (status IS NULL OR status <> '0') AND pVitrina = 1"
                    )?->fetchColumn();
                }

                $out['products'] = [
                    'total' => (int) ($row['total'] ?? 0),
                    'active' => (int) ($row['active'] ?? 0),
                    'no_image' => (int) ($row['no_image'] ?? 0),
                    'categories' => $cats,
                    'vitrina' => $vitrina,
                ];
            }

            if (self::hasTable($pdo, 'import_produse') && self::hasColumn($pdo, 'import_produse', 'status')) {
                $pending = (int) $pdo->query("SELECT COUNT(*) FROM import_produse WHERE status = 'pending'")->fetchColumn();
                $conflict = 0;
                if (self::hasColumn($pdo, 'import_produse', 'status')) {
                    try {
                        $conflict = (int) $pdo->query(
                            "SELECT COUNT(*) FROM import_produse WHERE status = 'conflict_live'"
                        )->fetchColumn();
                    } catch (Throwable) {
                        $conflict = 0;
                    }
                }
                $out['import_queue'] = [
                    'pending' => $pending,
                    'conflict_live' => $conflict,
                ];
            }

            if (self::hasTable($pdo, 'comenzi')) {
                $total = (int) $pdo->query('SELECT COUNT(*) FROM comenzi')->fetchColumn();
                $week = self::countOrdersLastDays($pdo, 7);
                $out['orders'] = [
                    'total' => $total,
                    'last_7_days' => $week,
                ];
            }
        } catch (Throwable $e) {
            $out['ok'] = false;
            $out['error'] = $e->getMessage();
        }

        return $out;
    }

    public static function countOrdersLastDays(PDO $pdo, int $days): int
    {
        $days = max(1, min(90, $days));
        if (self::hasColumn($pdo, 'comenzi', 'created_at')) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM comenzi WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
            );
            $stmt->execute([$days]);

            return (int) $stmt->fetchColumn();
        }
        if (self::hasColumn($pdo, 'comenzi', 'data')) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM comenzi WHERE data >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'
            );
            $stmt->execute([$days]);

            return (int) $stmt->fetchColumn();
        }

        return 0;
    }

    /** @return list<string> */
    public static function comenziSelectColumns(PDO $pdo): array
    {
        $wanted = ['id', 'order_status', 'total_amount', 'total', 'created_at', 'data', 'notes', 'phone', 'email'];
        $out = [];
        foreach ($wanted as $col) {
            if (self::hasColumn($pdo, 'comenzi', $col)) {
                $out[] = $col;
            }
        }

        return $out !== [] ? $out : ['id'];
    }
}
