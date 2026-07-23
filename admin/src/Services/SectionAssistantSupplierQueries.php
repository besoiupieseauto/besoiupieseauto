<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Core\Supplier\SupplierHooks;
use PDO;
use Throwable;

final class SectionAssistantSupplierQueries
{
    /** @return array<string, mixed> */
    public static function compareSnapshot(): array
    {
        try {
            $service = SupplierHooks::priceLogic();
            if ($service === null) {
                throw new \RuntimeException('Modulul furnizori nu este disponibil.');
            }
            $config = $service->getConfig();
            $order = is_array($config['scan_order'] ?? null) ? $config['scan_order'] : [];
            $omit = is_array($config['omit_suppliers'] ?? null) ? $config['omit_suppliers'] : [];
            $strategy = (string) ($config['price_strategy'] ?? '');
            $tier = (int) ($config['compare_tier_size'] ?? 3);
            $brandVerify = (string) ($config['brand_verify'] ?? '');
            $stockVerify = (string) ($config['stock_verify'] ?? '');

            $positionRows = [];
            $rank = 1;
            foreach ($order as $code) {
                $code = trim((string) $code);
                if ($code === '') {
                    continue;
                }
                $positionRows[] = [
                    'rank' => $rank,
                    'supplier' => $code,
                    'omitted' => in_array($code, $omit, true),
                ];
                ++$rank;
            }

            return [
                'scan_order' => $order,
                'positions' => $positionRows,
                'omit_suppliers' => $omit,
                'price_strategy' => $strategy,
                'compare_tier_size' => $tier,
                'brand_verify' => $brandVerify,
                'stock_verify' => $stockVerify,
            ];
        } catch (Throwable) {
            return [
                'scan_order' => [],
                'positions' => [],
                'omit_suppliers' => [],
                'price_strategy' => '',
                'compare_tier_size' => 3,
                'brand_verify' => '',
                'stock_verify' => '',
            ];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listSuppliers(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT id, name, code, status FROM furnizori
                 WHERE LOWER(TRIM(COALESCE(status, 'active'))) NOT IN ('deleted', 'sters', '0')
                 ORDER BY name ASC LIMIT {$limit}"
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $out[] = [
                    'name' => trim((string) ($row['name'] ?? '')) ?: '-',
                    'code' => trim((string) ($row['code'] ?? '')) ?: '-',
                    'status' => trim((string) ($row['status'] ?? '')) ?: 'active',
                    'edit_url' => $id > 0 ? '/admin/profilefurnizori?id=' . $id : '/admin/suppliers',
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function searchByQuery(string $query, int $limit = 30): array
    {
        $query = SectionAssistantQueryHelper::sanitize($query);
        $all = self::listSuppliers(50);
        if ($query === '') {
            return array_slice($all, 0, max(1, min(50, $limit)));
        }

        $words = SectionAssistantQueryHelper::words($query);
        if ($words === []) {
            return array_slice($all, 0, max(1, min(50, $limit)));
        }

        $matched = array_values(array_filter(
            $all,
            static fn (array $s): bool => SectionAssistantQueryHelper::haystackContainsAllWords(
                (string) ($s['name'] ?? '') . ' ' . (string) ($s['code'] ?? ''),
                $words
            )
        ));
        if ($matched === []) {
            $matched = array_values(array_filter(
                $all,
                static function (array $s) use ($words): bool {
                    $hay = mb_strtolower(
                        (string) ($s['name'] ?? '') . ' ' . (string) ($s['code'] ?? ''),
                        'UTF-8'
                    );
                    foreach ($words as $word) {
                        if (str_contains($hay, $word)) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        return array_slice($matched, 0, max(1, min(50, $limit)));
    }

    public static function countSuppliers(): int
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM furnizori WHERE LOWER(TRIM(COALESCE(status, 'active'))) NOT IN ('deleted', 'sters', '0')"
            );

            return (int) ($stmt ? $stmt->fetchColumn() : 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
