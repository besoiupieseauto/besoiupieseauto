<?php

declare(strict_types=1);

namespace Evasystem\Core\Comenzi;

use Config\Database;
use PDO;

/**
 * Acces la tabela order_items.
 */
final class OrderItemsModel
{
    private const TABLE = 'order_items';

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $pdo = Database::getDB();
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(self::TABLE));
        $cache = $stmt !== false && $stmt->fetchColumn() !== false;

        return $cache;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function insertLines(int $orderId, array $lines): void
    {
        if ($orderId <= 0 || $lines === [] || !$this->tableExists()) {
            return;
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                order_id, product_id, randomn_id, product_name, product_image,
                oem_code, unit_price, quantity, line_total
             ) VALUES (
                :order_id, :product_id, :randomn_id, :product_name, :product_image,
                :oem_code, :unit_price, :quantity, :line_total
             )'
        );

        foreach ($lines as $line) {
            $stmt->execute([
                ':order_id' => $orderId,
                ':product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null,
                ':randomn_id' => isset($line['randomn_id']) ? (string) $line['randomn_id'] : null,
                ':product_name' => (string) ($line['product_name'] ?? ''),
                ':product_image' => isset($line['product_image']) ? (string) $line['product_image'] : null,
                ':oem_code' => isset($line['oem_code']) ? (string) $line['oem_code'] : null,
                ':unit_price' => round((float) ($line['unit_price'] ?? 0), 2),
                ':quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                ':line_total' => round((float) ($line['line_total'] ?? 0), 2),
            ]);
        }
    }

    /**
     * @param array<int, int> $orderIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function findGroupedByOrderIds(array $orderIds): array
    {
        if (!$this->tableExists() || $orderIds === []) {
            return [];
        }

        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        $orderIds = array_filter($orderIds, static fn (int $id): bool => $id > 0);
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::TABLE . '
             WHERE order_id IN (' . $placeholders . ')
             ORDER BY id ASC'
        );
        $stmt->execute($orderIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $grouped[$orderId][] = $this->normalizeRow($row);
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByOrderId(int $orderId): array
    {
        $grouped = $this->findGroupedByOrderIds([$orderId]);

        return $grouped[$orderId] ?? [];
    }

    /**
     * Fallback pentru comenzi vechi (notes / name agregat).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parseLegacyLines(array $order): array
    {
        $productName = trim((string) ($order['product_name'] ?? $order['name'] ?? ''));
        $notes = trim((string) ($order['notes'] ?? ''));
        $fallbackImage = trim((string) ($order['product_image'] ?? ''));
        $items = [];

        if ($notes !== '' && preg_match('/__BPA_ITEMS__(.+)$/s', $notes, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = trim((string) ($item['product_name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $price = round((float) ($item['price'] ?? 0), 2);
                    $items[] = [
                        'product_id' => (int) ($item['product_id'] ?? 0) ?: null,
                        'randomn_id' => trim((string) ($item['product_id'] ?? '')),
                        'product_name' => $name,
                        'product_image' => trim((string) ($item['product_image'] ?? '')) ?: $fallbackImage,
                        'oem_code' => trim((string) ($item['oem'] ?? '')),
                        'unit_price' => $price,
                        'quantity' => $qty,
                        'line_total' => round($price * $qty, 2),
                    ];
                }
            }
        }

        if ($items !== []) {
            return $items;
        }

        $parts = preg_split('/;\s*/', $productName) ?: [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*x\s*(.+)$/iu', $part, $match)) {
                $items[] = [
                    'product_id' => null,
                    'randomn_id' => '',
                    'product_name' => trim($match[2]),
                    'product_image' => $fallbackImage,
                    'oem_code' => '',
                    'unit_price' => 0.0,
                    'quantity' => max(1, (int) $match[1]),
                    'line_total' => 0.0,
                ];
                continue;
            }

            $items[] = [
                'product_id' => null,
                'randomn_id' => '',
                'product_name' => $part,
                'product_image' => $fallbackImage,
                'oem_code' => '',
                'unit_price' => 0.0,
                'quantity' => 1,
                'line_total' => 0.0,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'order_id' => (int) ($row['order_id'] ?? 0),
            'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
            'randomn_id' => (string) ($row['randomn_id'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'product_image' => (string) ($row['product_image'] ?? ''),
            'oem_code' => (string) ($row['oem_code'] ?? ''),
            'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            'line_total' => round((float) ($row['line_total'] ?? 0), 2),
        ];
    }
}
