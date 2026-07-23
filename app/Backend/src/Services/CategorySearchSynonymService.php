<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;

/**
 * Dicționar sinonime căutare: termen popular → ART_NAME (frunză categorie).
 */
final class CategorySearchSynonymService
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function resolveTerm(string $query): ?string
    {
        $term = mb_strtolower(trim($query), 'UTF-8');
        if ($term === '') {
            return null;
        }

        $map = self::loadMap();

        return $map[$term] ?? null;
    }

    /**
     * @return array<string, string> term_norm => target_art_name
     */
    public static function loadMap(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $map = [];

        $jsonPath = BesoiuCategoryTreeImportService::synonymsJsonPath();
        if (is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            $rows = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $term = mb_strtolower(trim((string) ($row['term'] ?? '')), 'UTF-8');
                $target = trim((string) ($row['target_art_name'] ?? ''));
                if ($term !== '' && $target !== '') {
                    $map[$term] = $target;
                }
            }
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                'SELECT term, target_art_name FROM categorii_sinonime WHERE is_active = 1'
            );
            if ($stmt instanceof \PDOStatement) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $term = mb_strtolower(trim((string) ($row['term'] ?? '')), 'UTF-8');
                    $target = trim((string) ($row['target_art_name'] ?? ''));
                    if ($term !== '' && $target !== '') {
                        $map[$term] = $target;
                    }
                }
            }
        } catch (\Throwable) {
            // tabel poate lipsi înainte de migrare
        }

        self::$cache = $map;

        return $map;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
