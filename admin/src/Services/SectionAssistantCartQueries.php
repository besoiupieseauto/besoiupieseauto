<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

final class SectionAssistantCartQueries
{
    /** @return array{items:list<array<string,mixed>>,stats:array<string,int>,sessions:int,abandoned:int,supplier_lines:int} */
    public static function snapshot(?int $adminUserId = null, int $limit = 40): array
    {
        $limit = max(1, min(60, $limit));
        $shop = self::shopCartProducts($limit);
        $abandoned = self::abandonedCartProducts(max(0, $limit - count($shop)));
        $supplier = self::supplierCartProducts($adminUserId, max(0, $limit - count($shop) - count($abandoned)));

        $items = array_slice(array_merge($shop, $abandoned, $supplier), 0, $limit);
        $sessions = self::countDistinctSessions();
        $abandonedCount = self::countOpenAbandonments();
        $supplierLines = self::countSupplierLines($adminUserId);

        return [
            'items' => $items,
            'stats' => [
                'total_lines' => count($items),
                'shop_lines' => count($shop),
                'abandoned_lines' => count($abandoned),
                'supplier_lines' => count($supplier),
            ],
            'sessions' => $sessions,
            'abandoned' => $abandonedCount,
            'supplier_lines' => $supplierLines,
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function shopCartProducts(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT ci.session_id, ci.randomn_id, ci.quantity, ci.unit_price, ci.product_snapshot, ci.updated_at
                 FROM cart_items ci
                 INNER JOIN (
                     SELECT session_id, MAX(updated_at) AS last_at
                     FROM cart_items
                     WHERE updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                     GROUP BY session_id
                     ORDER BY last_at DESC
                     LIMIT 15
                 ) recent ON recent.session_id = ci.session_id
                 ORDER BY ci.updated_at DESC
                 LIMIT " . (int) $limit
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $snapshot = json_decode((string) ($row['product_snapshot'] ?? '{}'), true);
            if (!is_array($snapshot)) {
                $snapshot = [];
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $price = (float) ($row['unit_price'] ?? 0);
            $code = trim((string) ($row['randomn_id'] ?? ''));
            $name = trim((string) ($snapshot['product_name'] ?? ''));
            if ($name === '') {
                $name = $code !== '' ? ('Produs ' . $code) : 'Produs cos magazin';
            }

            $out[] = [
                'name' => $name,
                'brand' => 'Magazin',
                'code' => $code !== '' ? $code : '-',
                'category' => 'Sesiune ' . mb_substr((string) ($row['session_id'] ?? ''), 0, 8, 'UTF-8'),
                'price' => number_format($price, 2, ',', '.') . ' x' . $qty,
                'edit_url' => '/admin/abandoned-carts',
                'cart_source' => 'shop',
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function abandonedCartProducts(int $limit): array
    {
        if ($limit <= 0 || !CartAbandonmentService::tableExists()) {
            return [];
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT client_name, phone, cart_json, items_count, total_amount, last_seen_at
                 FROM cart_abandonments
                 WHERE status = 'open'
                 ORDER BY last_seen_at DESC
                 LIMIT 8"
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cart = json_decode((string) ($row['cart_json'] ?? '[]'), true);
            if (!is_array($cart)) {
                $cart = [];
            }
            $client = trim((string) ($row['client_name'] ?? ''));
            if ($client === '') {
                $client = trim((string) ($row['phone'] ?? ''));
            }
            if ($client === '') {
                $client = 'Lead anonim';
            }

            foreach ($cart as $item) {
                if (!is_array($item) || count($out) >= $limit) {
                    break;
                }
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $price = (float) ($item['price'] ?? 0);
                $name = trim((string) ($item['name'] ?? $item['product_name'] ?? ''));
                $code = trim((string) ($item['code'] ?? $item['randomn_id'] ?? $item['sku'] ?? ''));
                if ($name === '') {
                    $name = $code !== '' ? $code : 'Produs cos abandonat';
                }

                $out[] = [
                    'name' => $name,
                    'brand' => 'Abandonat',
                    'code' => $code !== '' ? $code : '-',
                    'category' => $client,
                    'price' => number_format($price, 2, ',', '.') . ' x' . $qty,
                    'edit_url' => '/admin/abandoned-carts',
                    'cart_source' => 'abandoned',
                ];
            }
        }

        return array_slice($out, 0, $limit);
    }

    /** @return list<array<string,mixed>> */
    private static function supplierCartProducts(?int $adminUserId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query('SELECT user_id, cart, updated_at FROM supplier_carts ORDER BY updated_at DESC LIMIT 5');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $userId = (int) ($row['user_id'] ?? 0);
            if ($adminUserId !== null && $adminUserId > 0 && $userId !== $adminUserId && count($rows) > 1) {
                continue;
            }
            $cart = json_decode((string) ($row['cart'] ?? ''), true);
            if (!is_array($cart)) {
                continue;
            }
            foreach ($cart as $supplier => $items) {
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (!is_array($item) || count($out) >= $limit) {
                        break 2;
                    }
                    $qty = max(1, (int) ($item['qty'] ?? 1));
                    $price = (float) ($item['price'] ?? 0);
                    $name = trim((string) ($item['product_name'] ?? $item['product_code'] ?? ''));
                    $code = trim((string) ($item['product_code'] ?? $item['variant_code'] ?? ''));
                    if ($name === '') {
                        $name = $code !== '' ? $code : 'Linie furnizor';
                    }

                    $out[] = [
                        'name' => $name,
                        'brand' => 'Furnizori B2B',
                        'code' => $code !== '' ? $code : '-',
                        'category' => (string) $supplier,
                        'price' => number_format($price, 2, ',', '.') . ' ' . trim((string) ($item['currency'] ?? 'RON')) . ' x' . $qty,
                        'edit_url' => '/admin/supplier-cart',
                        'cart_source' => 'supplier',
                    ];
                }
            }
        }

        return array_slice($out, 0, $limit);
    }

    private static function countDistinctSessions(): int
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT COUNT(DISTINCT session_id) FROM cart_items
                 WHERE updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );

            return (int) ($stmt?->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countOpenAbandonments(): int
    {
        if (!CartAbandonmentService::tableExists()) {
            return 0;
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query("SELECT COUNT(*) FROM cart_abandonments WHERE status = 'open'");

            return (int) ($stmt?->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countSupplierLines(?int $adminUserId): int
    {
        try {
            $pdo = Database::getDB();
            if ($adminUserId !== null && $adminUserId > 0) {
                $stmt = $pdo->prepare('SELECT cart FROM supplier_carts WHERE user_id = ? LIMIT 1');
                $stmt->execute([$adminUserId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    return 0;
                }

                return self::countLinesInSupplierJson((string) ($row['cart'] ?? ''));
            }
            $stmt = $pdo->query('SELECT cart FROM supplier_carts');
            $total = 0;
            foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $total += self::countLinesInSupplierJson((string) ($row['cart'] ?? ''));
            }

            return $total;
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countLinesInSupplierJson(string $json): int
    {
        $cart = json_decode($json, true);
        if (!is_array($cart)) {
            return 0;
        }
        $lines = 0;
        foreach ($cart as $items) {
            if (is_array($items)) {
                $lines += count($items);
            }
        }

        return $lines;
    }
}
