<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use Config\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Rulează migrations/*.sql din pachetul unui modul (idempotent pe fișier).
 */
final class ModuleMigrationRunner
{
    public function __construct(
        private readonly string $adminRoot,
    ) {
    }

    /**
     * @return array{ran: list<string>, skipped: list<string>, errors: list<string>}
     */
    public function runForManifest(ModuleManifest $manifest): array
    {
        $ran = [];
        $skipped = [];
        $errors = [];

        $migrations = $manifest->migrations();
        if ($migrations === []) {
            // Auto-discover migrations/*.sql
            $dir = $manifest->path() . DIRECTORY_SEPARATOR . 'migrations';
            if (is_dir($dir)) {
                $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
                sort($files);
                foreach ($files as $file) {
                    $migrations[] = 'migrations/' . basename($file);
                }
            }
        }

        if ($migrations === []) {
            return ['ran' => [], 'skipped' => [], 'errors' => []];
        }

        try {
            $pdo = $this->pdo();
        } catch (Throwable $e) {
            return [
                'ran' => [],
                'skipped' => [],
                'errors' => ['DB indisponibil: ' . $e->getMessage()],
            ];
        }

        $this->ensureLogTable($pdo);
        $moduleId = $manifest->id();

        foreach ($migrations as $rel) {
            $rel = str_replace('\\', '/', trim($rel));
            $file = $manifest->path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($file)) {
                $errors[] = 'Lipsește: ' . $rel;
                continue;
            }

            $hash = hash_file('sha256', $file) ?: '';
            if ($this->alreadyRan($pdo, $moduleId, $rel, $hash)) {
                $skipped[] = $rel;
                continue;
            }

            $sql = (string) file_get_contents($file);
            $sql = $this->stripBom($sql);
            if (trim($sql) === '') {
                $skipped[] = $rel . ' (gol)';
                continue;
            }

            try {
                // DDL (CREATE TABLE) face commit implicit în MySQL — fără beginTransaction.
                $this->execMulti($pdo, $sql);
                $stmt = $pdo->prepare(
                    'INSERT INTO module_migrations (module_id, migration, checksum, ran_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), ran_at = VALUES(ran_at)'
                );
                $stmt->execute([$moduleId, $rel, $hash]);
                $ran[] = $rel;
            } catch (Throwable $e) {
                $errors[] = $rel . ': ' . $e->getMessage();
            }
        }

        return compact('ran', 'skipped', 'errors');
    }

    private function pdo(): PDO
    {
        if (Database::hasConnection('default')) {
            return Database::getDB('default');
        }

        $config = require $this->adminRoot . '/config/config.php';
        Database::getInstance(
            (string) $config['db_host'],
            (string) $config['db_name'],
            (string) $config['db_user'],
            (string) $config['db_pass']
        );

        return Database::getDB('default');
    }

    private function ensureLogTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `module_migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `module_id` VARCHAR(64) NOT NULL,
                `migration` VARCHAR(255) NOT NULL,
                `checksum` CHAR(64) NOT NULL DEFAULT \'\',
                `ran_at` DATETIME NOT NULL,
                UNIQUE KEY `uq_module_migration` (`module_id`, `migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function alreadyRan(PDO $pdo, string $moduleId, string $rel, string $hash): bool
    {
        $stmt = $pdo->prepare(
            'SELECT checksum FROM module_migrations WHERE module_id = ? AND migration = ? LIMIT 1'
        );
        $stmt->execute([$moduleId, $rel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return (string) ($row['checksum'] ?? '') === $hash;
    }

    private function execMulti(PDO $pdo, string $sql): void
    {
        // Split pe ; urmat de newline sau EOF (simplu, fără parser SQL complet)
        $parts = preg_split('/;\s*(?=[\r\n]|$)/', $sql) ?: [];
        foreach ($parts as $part) {
            $part = $this->stripSqlComments(trim($part));
            if ($part === '') {
                continue;
            }
            $pdo->exec($part);
        }
    }

    private function stripSqlComments(string $sql): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    private function stripBom(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        return $raw;
    }
}

