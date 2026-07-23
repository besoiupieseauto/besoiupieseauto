<?php

declare(strict_types=1);

namespace Besoiu\Services;

use PDO;
use Throwable;

/** Interogări BD pentru cheat sheet-uri Composer (categorii, produse). */
final class SectionAssistantCatalogQueries
{
    /** @return list<array{category:string,count:int,subcategories:list<array{name:string,count:int}>}> */
    public static function categoryTree(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query(
                "SELECT TRIM(pCategory) AS category, TRIM(COALESCE(pSubcategory, '')) AS subcategory, COUNT(*) AS cnt
                 FROM produse
                 WHERE COALESCE(status, 1) <> 0
                   AND pCategory IS NOT NULL AND TRIM(pCategory) <> '' AND pCategory <> '0'
                 GROUP BY TRIM(pCategory), TRIM(COALESCE(pSubcategory, ''))
                 ORDER BY category ASC, subcategory ASC"
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }

        $tree = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cat = trim((string) ($row['category'] ?? ''));
            if ($cat === '') {
                continue;
            }
            if (!isset($tree[$cat])) {
                $tree[$cat] = ['category' => $cat, 'count' => 0, 'subcategories' => []];
            }
            $cnt = (int) ($row['cnt'] ?? 0);
            $tree[$cat]['count'] += $cnt;
            $sub = trim((string) ($row['subcategory'] ?? ''));
            if ($sub !== '' && $sub !== '0') {
                $tree[$cat]['subcategories'][] = ['name' => $sub, 'count' => $cnt];
            }
        }

        return array_values($tree);
    }

    public static function countSubcategories(PDO $pdo): int
    {
        try {
            $stmt = $pdo->query(
                "SELECT COUNT(DISTINCT CONCAT(TRIM(pCategory), '>', TRIM(COALESCE(pSubcategory, ''))))
                 FROM produse
                 WHERE COALESCE(status, 1) <> 0
                   AND pCategory IS NOT NULL AND TRIM(pCategory) <> ''
                   AND pSubcategory IS NOT NULL AND TRIM(pSubcategory) <> '' AND pSubcategory <> '0'"
            );

            return (int) ($stmt ? $stmt->fetchColumn() : 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function activeProducts(PDO $pdo, int $limit = 200): array
    {
        $limit = max(1, min(200, $limit));
        try {
            $stmt = $pdo->query(
                "SELECT randomn_id, pName, pBrand, pCode, pCategory, pSubcategory, pPrice, pBadge
                 FROM produse
                 WHERE COALESCE(status, 1) <> 0
                 ORDER BY pCategory ASC, pSubcategory ASC, id DESC
                 LIMIT {$limit}"
            );

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }
    }
}
