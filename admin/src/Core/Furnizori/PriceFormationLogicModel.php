<?php

declare(strict_types=1);

namespace Evasystem\Core\Furnizori;

use Config\Database;
use PDO;

final class PriceFormationLogicModel
{
    private const TABLE = 'price_formation_logic';

    public function ensureTable(): void
    {
        $pdo = Database::getDB();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `config_json` LONGTEXT NOT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, mixed>|null */
    public function loadConfig(): ?array
    {
        $this->ensureTable();
        $pdo = Database::getDB();
        $stmt = $pdo->query('SELECT config_json FROM `' . self::TABLE . '` ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            return null;
        }

        $decoded = json_decode((string) ($row['config_json'] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $config */
    public function saveConfig(array $config): bool
    {
        $this->ensureTable();
        $pdo = Database::getDB();
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $stmt = $pdo->query('SELECT id FROM `' . self::TABLE . '` ORDER BY id ASC LIMIT 1');
        $existing = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (is_array($existing) && !empty($existing['id'])) {
            $update = $pdo->prepare('UPDATE `' . self::TABLE . '` SET config_json = :json WHERE id = :id');

            return $update->execute([':json' => $json, ':id' => (int) $existing['id']]);
        }

        $insert = $pdo->prepare('INSERT INTO `' . self::TABLE . '` (config_json) VALUES (:json)');

        return $insert->execute([':json' => $json]);
    }
}
