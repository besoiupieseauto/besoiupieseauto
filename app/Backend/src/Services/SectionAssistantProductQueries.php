<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Services\Products\ProduseService;
use PDO;
use Throwable;

final class SectionAssistantProductQueries
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    private static function bootstrapCodeNormalize(): void
    {
        if (function_exists('besoiu_normalize_product_code')) {
            return;
        }

        $path = dirname(__DIR__, 3) . '/system/product-code-normalize.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

    private static function tableHasColumn(PDO $pdo, string $column): bool
    {
        if (array_key_exists($column, self::$columnCache)) {
            return self::$columnCache[$column];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute(['produse', $column]);
            self::$columnCache[$column] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$columnCache[$column] = false;
        }

        return self::$columnCache[$column];
    }

    /** @return list<string> */
    private static function selectColumns(PDO $pdo): array
    {
        $cols = ['id', 'randomn_id', 'pName', 'pBrand', 'pCode', 'pCategory', 'pSubcategory', 'pPrice', 'status'];
        foreach (['pStock', 'pBadge', 'pVitrina', 'pOem', 'pCodeNorm', 'pNote', 'pImages'] as $optional) {
            if (self::tableHasColumn($pdo, $optional)) {
                $cols[] = $optional;
            }
        }

        return array_values(array_unique($cols));
    }

    private static function onlineStatusSql(): string
    {
        return "(status IS NULL OR TRIM(CAST(status AS CHAR)) = '' OR TRIM(CAST(status AS CHAR)) <> '0')";
    }

    /** @return list<string> */
    private static function codeMatchSql(PDO $pdo, string $code, string $norm): array
    {
        self::bootstrapCodeNormalize();
        $pCodeNormExpr = function_exists('besoiu_sql_normalized_pcode_expr')
            ? besoiu_sql_normalized_pcode_expr('pCode')
            : "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pCode), ' ', ''), '-', ''), '.', ''), '/', ''), '_', ''))";

        $nameNormExpr = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pName), ' ', ''), '-', ''), '.', ''), '/', ''), '_', ''))";

        $parts = [
            "{$pCodeNormExpr} = :codeNorm",
            'UPPER(TRIM(pCode)) = :codeUpper',
            'TRIM(pCode) = :codeRaw',
            'UPPER(TRIM(randomn_id)) = :codeUpperRand',
            "{$nameNormExpr} LIKE :nameNormLike",
            'pName LIKE :codeLike',
            'pName LIKE :spacedLike',
        ];

        if (self::tableHasColumn($pdo, 'pCodeNorm')) {
            $parts[] = 'pCodeNorm = :codeNormCol';
        }
        if (self::tableHasColumn($pdo, 'pOem')) {
            $parts[] = '(pOem IS NOT NULL AND UPPER(pOem) LIKE :oemLike)';
        }

        return $parts;
    }

    /** @return array<string, string> */
    private static function codeMatchParams(PDO $pdo, string $code, string $norm): array
    {
        $spaced = self::boschSpacedCode($norm);

        $params = [
            'codeNorm' => $norm,
            'codeUpper' => strtoupper($code),
            'codeUpperRand' => strtoupper($code),
            'codeRaw' => $code,
            'nameNormLike' => '%' . $norm . '%',
            'codeLike' => '%' . $code . '%',
            'spacedLike' => $spaced !== '' ? '%' . $spaced . '%' : '%' . $code . '%',
            'oemLike' => '%' . strtoupper($code) . '%',
        ];

        if (self::tableHasColumn($pdo, 'pCodeNorm')) {
            $params['codeNormCol'] = $norm;
        }

        return $params;
    }

    private static function boschSpacedCode(string $norm): string
    {
        if (preg_match('/^(\d)(\d{3})(\d{3})(\d{3})$/', $norm, $m)) {
            return $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
        }

        return '';
    }

    private static function normalizeLookupCode(string $code): string
    {
        self::bootstrapCodeNormalize();

        if (function_exists('besoiu_normalize_product_code')) {
            return besoiu_normalize_product_code($code);
        }

        return strtoupper((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($code)));
    }

    /** @param array<string, mixed> $filters @return array{total:int,items:list<array<string,mixed>>} */
    public static function search(array $filters, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        try {
            $pdo = Database::getDB();
        } catch (Throwable) {
            return ['total' => 0, 'items' => []];
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $norm = self::normalizeLookupCode($keyword);
            if ($norm !== '' && preg_match('/^[A-Z0-9]{4,20}$/', $norm)) {
                $items = self::lookupByCode($keyword, $limit);
                if ($items !== []) {
                    return ['total' => count($items), 'items' => $items];
                }
            }
        }

        $where = [self::onlineStatusSql()];
        $params = [];

        if (!empty($filters['no_image'])) {
            $where[] = "(pImages IS NULL OR pImages = '' OR pImages = '[]')";
        }
        if (!empty($filters['vitrina_only']) && self::tableHasColumn($pdo, 'pVitrina')) {
            $where[] = 'pVitrina = 1';
        }
        if (!empty($filters['category'])) {
            $where[] = 'TRIM(pCategory) LIKE :cat';
            $params['cat'] = '%' . trim((string) $filters['category']) . '%';
        }
        if (!empty($filters['subcategory'])) {
            $where[] = 'TRIM(pSubcategory) LIKE :subcat';
            $params['subcat'] = '%' . trim((string) $filters['subcategory']) . '%';
        }
        if (!empty($filters['brand'])) {
            $where[] = 'TRIM(pBrand) LIKE :brand';
            $params['brand'] = '%' . trim((string) $filters['brand']) . '%';
        }
        if (!empty($filters['badge']) && self::tableHasColumn($pdo, 'pBadge')) {
            $where[] = 'TRIM(pBadge) = :badge';
            $params['badge'] = trim((string) $filters['badge']);
        }
        if (!empty($filters['keyword'])) {
            $kw = trim((string) $filters['keyword']);
            $norm = self::normalizeLookupCode($kw);
            if ($norm !== '' && preg_match('/^[A-Z0-9]{4,20}$/', $norm)) {
                $matchParts = self::codeMatchSql($pdo, $kw, $norm);
                $where[] = '(' . implode(' OR ', $matchParts) . ')';
                $params = array_merge($params, self::codeMatchParams($pdo, $kw, $norm));
            } else {
                $where[] = '(pName LIKE :kw OR pCode LIKE :kw OR pBrand LIKE :kw OR pCategory LIKE :kw OR pSubcategory LIKE :kw)';
                $params['kw'] = '%' . $kw . '%';
            }
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $select = implode(', ', self::selectColumns($pdo));

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM produse {$sqlWhere}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT {$select}
                 FROM produse {$sqlWhere}
                 ORDER BY id DESC LIMIT {$limit}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return ['total' => 0, 'items' => []];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = self::formatProductRow($row);
        }

        return ['total' => $total, 'items' => $items];
    }

    /** @return array{count:int,max:int,items:list<array<string,mixed>>} */
    public static function vitrinaSnapshot(int $limit = 15): array
    {
        try {
            $service = new ProduseService();
            $max = $service->vitrinaHomepageMax();
            $count = $service->countVitrinaProducts();
            $paged = $service->getVitrinaProductsPaginated(1, max(1, min(20, $limit)), '');
            $items = [];
            foreach ($paged['items'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['randomn_id'] ?? ''));
                $price = (float) ($row['pPrice'] ?? 0);
                $items[] = [
                    'name' => trim((string) ($row['pName'] ?? '')) ?: '-',
                    'brand' => trim((string) ($row['pBrand'] ?? '')) ?: '-',
                    'code' => trim((string) ($row['pCode'] ?? '')) ?: '-',
                    'price' => $price > 0 ? number_format($price, 2, ',', '.') . ' RON' : '-',
                    'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/vitrina',
                ];
            }

            return ['count' => $count, 'max' => $max, 'items' => $items];
        } catch (Throwable) {
            return ['count' => 0, 'max' => 10, 'items' => []];
        }
    }

    /** @return array{total:int,no_image:int,vitrina:int,categories:int} */
    public static function inventorySnapshot(): array
    {
        try {
            $pdo = Database::getDB();
            $base = self::onlineStatusSql();
            $total = (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE {$base}")->fetchColumn();
            $noImg = (int) $pdo->query(
                "SELECT COUNT(*) FROM produse WHERE {$base}
                 AND (pImages IS NULL OR TRIM(pImages) = '' OR pImages = '[]')"
            )->fetchColumn();
            $vitrina = self::tableHasColumn($pdo, 'pVitrina')
                ? (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE {$base} AND pVitrina = 1")->fetchColumn()
                : 0;
            $cats = (int) $pdo->query(
                "SELECT COUNT(DISTINCT TRIM(pCategory)) FROM produse
                 WHERE {$base} AND TRIM(COALESCE(pCategory, '')) <> ''"
            )->fetchColumn();

            return [
                'total' => $total,
                'no_image' => $noImg,
                'vitrina' => $vitrina,
                'categories' => $cats,
            ];
        } catch (Throwable) {
            return ['total' => 0, 'no_image' => 0, 'vitrina' => 0, 'categories' => 0];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function findByKeyword(string $keyword, int $limit = 10): array
    {
        $result = self::search(['keyword' => $keyword], $limit);

        return $result['items'];
    }

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $items = self::lookupByCode($code, 1);

        return $items[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function findByRandomnId(string $randomnId): ?array
    {
        $randomnId = trim($randomnId);
        if ($randomnId === '') {
            return null;
        }

        try {
            $pdo = Database::getDB();
        } catch (Throwable) {
            return null;
        }

        $select = implode(', ', self::selectColumns($pdo));
        $sql = "SELECT {$select} FROM produse WHERE randomn_id = :id LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $randomnId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return null;
        }

        return is_array($row) ? self::formatProductRow($row) : null;
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public static function findManyByRandomnIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $ids)));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $row = self::findByRandomnId($id);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Căutare rapidă după cod OEM — aceeași logică ca lista admin / import.
     *
     * @return list<array<string, mixed>>
     */
    public static function lookupByCode(string $code, int $limit = 10): array
    {
        $code = trim($code);
        if ($code === '') {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $norm = self::normalizeLookupCode($code);
        if ($norm === '') {
            return [];
        }

        try {
            $pdo = Database::getDB();
        } catch (Throwable) {
            return [];
        }

        self::bootstrapCodeNormalize();
        $pCodeNormExpr = function_exists('besoiu_sql_normalized_pcode_expr')
            ? besoiu_sql_normalized_pcode_expr('pCode')
            : "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pCode), ' ', ''), '-', ''), '.', ''), '/', ''), '_', ''))";

        $matchSql = implode(' OR ', self::codeMatchSql($pdo, $code, $norm));
        $params = self::codeMatchParams($pdo, $code, $norm);
        $select = implode(', ', self::selectColumns($pdo));

        $sql = "SELECT {$select}
                FROM produse
                WHERE ({$matchSql})
                ORDER BY
                  CASE
                    WHEN {$pCodeNormExpr} = :sortNorm THEN 0
                    WHEN UPPER(TRIM(pCode)) = :sortUpper THEN 1
                    WHEN UPPER(TRIM(randomn_id)) = :sortUpper2 THEN 2
                    ELSE 3
                  END,
                  id DESC
                LIMIT {$limit}";

        $params['sortNorm'] = $norm;
        $params['sortUpper'] = strtoupper($code);
        $params['sortUpper2'] = strtoupper($code);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = self::formatProductRow($row);
        }

        return $items;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function formatProductRow(array $row): array
    {
        $id = trim((string) ($row['randomn_id'] ?? ''));
        $price = (float) ($row['pPrice'] ?? 0);
        $stock = trim((string) ($row['pStock'] ?? ''));

        return [
            'randomn_id' => $id,
            'name' => trim((string) ($row['pName'] ?? '')) ?: '-',
            'brand' => trim((string) ($row['pBrand'] ?? '')) ?: '-',
            'code' => trim((string) ($row['pCode'] ?? '')) ?: '-',
            'category' => trim((string) ($row['pCategory'] ?? '')) ?: '-',
            'subcategory' => trim((string) ($row['pSubcategory'] ?? '')) ?: '-',
            'price' => $price > 0 ? number_format($price, 2, ',', '.') . ' RON' : '-',
            'stock' => $stock !== '' ? $stock : '-',
            'badge' => trim((string) ($row['pBadge'] ?? '')) ?: '-',
            'vitrina' => (int) ($row['pVitrina'] ?? 0) === 1 ? 'Da' : 'Nu',
            'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',
            'pName' => trim((string) ($row['pName'] ?? '')),
            'pBrand' => trim((string) ($row['pBrand'] ?? '')),
            'pCode' => trim((string) ($row['pCode'] ?? '')),
            'pCategory' => trim((string) ($row['pCategory'] ?? '')),
            'pSubcategory' => trim((string) ($row['pSubcategory'] ?? '')),
            'pPrice' => (string) ($row['pPrice'] ?? ''),
            'pStock' => $stock,
            'pNote' => trim((string) ($row['pNote'] ?? '')),
            'pImages' => trim((string) ($row['pImages'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '1')),
        ];
    }
}
