<?php
declare(strict_types=1);

require_once __DIR__ . '/MatcDb.php';

/**
 * Lookup simplu brand + cod in baza Matc activa.
 */
final class MatcLookup
{
    /**
     * @param list<string> $codeVariants
     * @return array{found:bool,product:?array<string,mixed>,compat_count:int,query_ms:float}
     */
    public static function find(string $brand, array $codeVariants, ?string $database = null): array
    {
        $t0 = microtime(true);
        $pdo = MatcDb::pdo($database);
        $brandUp = MatcDb::normalizeBrand($brand);

        $keys = [];
        foreach ($codeVariants as $variant) {
            $norm = MatcDb::normalizeCode($variant);
            if ($norm !== '') {
                $keys[] = $norm;
            }
        }
        $keys = array_values(array_unique($keys));

        if ($brandUp === '' || $keys === []) {
            return ['found' => false, 'product' => null, 'compat_count' => 0, 'query_ms' => 0.0];
        }

        $ph = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT p.id AS product_id, p.art_code_1, p.art_code_2, p.art_name, "
            . "p.art_ean, p.ttc_art_id, p.parts_info, p.terms_of_use, p.art_cross, "
            . "p.compat_count, p.is_qwp, p.source_set, b.name AS brand_name "
            . "FROM product_codes pc "
            . "JOIN products p ON p.id = pc.product_id "
            . "JOIN brands b ON b.id = p.brand_id "
            . "WHERE b.name = ? AND pc.code_norm IN ({$ph}) "
            . "LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$brandUp], $keys));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'found' => false,
                'product' => null,
                'compat_count' => 0,
                'query_ms' => round((microtime(true) - $t0) * 1000, 2),
            ];
        }

        $compatCount = (int) ($product['compat_count'] ?? 0);
        if ($compatCount <= 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_compatibilities WHERE product_id = ?');
            $stmt->execute([(int) $product['product_id']]);
            $compatCount = (int) $stmt->fetchColumn();
        }

        return [
            'found' => true,
            'product' => $product,
            'compat_count' => $compatCount,
            'query_ms' => round((microtime(true) - $t0) * 1000, 2),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function compatRows(int $productId, int $limit = 20, ?string $database = null): array
    {
        $pdo = MatcDb::pdo($database);
        $limit = max(1, min(500, $limit));
        $stmt = $pdo->prepare(
            "SELECT ttc_typ_id, car_brand, car_model, car_typ, car_body, "
            . "car_of_year, car_to_year, car_kw, car_pm, car_cc "
            . "FROM product_compatibilities WHERE product_id = ? LIMIT {$limit}"
        );
        $stmt->execute([$productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
