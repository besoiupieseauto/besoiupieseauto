<?php

declare(strict_types=1);

namespace Evasystem\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Coșuri abandonate — salvare lead checkout + listare admin.
 */
final class CartAbandonmentService
{
    private const TABLE = 'cart_abandonments';

    public static function tableExists(?PDO $pdo = null): bool
    {
        $pdo = $pdo ?? Database::getDB();

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '" . self::TABLE . "'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function countOpen(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        $pdo = Database::getDB();
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM " . self::TABLE . " WHERE status = 'open'"
        )->fetchColumn();

        return $count + self::countStaleServerCarts($pdo);
    }

    /** @param array<string, mixed> $payload */
    public static function upsertLead(array $payload): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        $pdo = Database::getDB();
        $sessionId = trim((string) ($payload['session_id'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $clientName = trim((string) ($payload['client_name'] ?? ''));
        if ($phone === '' && $clientName === '') {
            return 0;
        }

        $cartJson = json_encode($payload['cart'] ?? [], JSON_UNESCAPED_UNICODE);
        if (!is_string($cartJson)) {
            $cartJson = '[]';
        }

        $items = is_array($payload['cart'] ?? null) ? $payload['cart'] : [];
        $total = round((float) ($payload['total_amount'] ?? 0), 2);
        if ($total <= 0) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $price = (float) ($item['price'] ?? 0);
                $total += $qty * $price;
            }
            $total = round($total, 2);
        }

        $existingId = 0;
        if ($sessionId !== '') {
            $stmt = $pdo->prepare(
                'SELECT id FROM ' . self::TABLE . ' WHERE session_id = :sid AND status = \'open\' ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([':sid' => $sessionId]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);
        }

        $params = [
            ':client_name' => mb_substr($clientName, 0, 160),
            ':phone' => mb_substr($phone, 0, 50),
            ':email' => mb_substr(trim((string) ($payload['email'] ?? '')), 0, 255),
            ':cart_json' => $cartJson,
            ':total_amount' => $total,
            ':items_count' => max(0, (int) ($payload['items_count'] ?? count($items))),
            ':checkout_step' => max(1, min(3, (int) ($payload['checkout_step'] ?? 2))),
        ];

        if ($existingId > 0) {
            $sql = 'UPDATE ' . self::TABLE . ' SET
                client_name = :client_name, phone = :phone, email = :email,
                cart_json = :cart_json, total_amount = :total_amount, items_count = :items_count,
                checkout_step = :checkout_step, last_seen_at = NOW(), updated_at = NOW()
                WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params + [':id' => $existingId]);

            return $existingId;
        }

        $sql = 'INSERT INTO ' . self::TABLE . ' (
            session_id, client_name, phone, email, cart_json, total_amount, items_count, checkout_step, status, last_seen_at
        ) VALUES (
            :session_id, :client_name, :phone, :email, :cart_json, :total_amount, :items_count, :checkout_step, \'open\', NOW()
        )';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + [':session_id' => mb_substr($sessionId, 0, 128)]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public static function listForAdmin(int $page = 1, int $perPage = 20, string $status = 'open'): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $items = [];

        if (self::tableExists()) {
            $pdo = Database::getDB();
            $where = '';
            $params = [];
            if ($status !== '' && $status !== 'all') {
                $where = 'WHERE status = :status';
                $params[':status'] = $status;
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' ' . $where);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = 'SELECT * FROM ' . self::TABLE . ' ' . $where . ' ORDER BY last_seen_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = self::normalizeRow($row, 'lead');
            }
        } else {
            $total = 0;
        }

        foreach (self::staleServerCartSessions() as $row) {
            $items[] = $row;
            ++$total;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function updateStatus(int $id, string $status, string $notes = ''): bool
    {
        if (!self::tableExists() || $id <= 0) {
            return false;
        }

        $allowed = ['open', 'contacted', 'converted', 'dismissed'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET status = :status, notes = :notes, updated_at = NOW() WHERE id = :id'
        );

        return $stmt->execute([
            ':status' => $status,
            ':notes' => mb_substr($notes, 0, 2000),
            ':id' => $id,
        ]);
    }

    private static function countStaleServerCarts(PDO $pdo): int
    {
        try {
            $stmt = $pdo->query(
                "SELECT COUNT(DISTINCT session_id) FROM cart_items
                 WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );

            return (int) ($stmt?->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    private static function staleServerCartSessions(): array
    {
        $pdo = Database::getDB();
        try {
            $stmt = $pdo->query(
                "SELECT session_id,
                        MAX(updated_at) AS last_seen_at,
                        SUM(quantity) AS items_count,
                        SUM(quantity * unit_price) AS total_amount
                 FROM cart_items
                 WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                   AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY session_id
                 ORDER BY last_seen_at DESC
                 LIMIT 30"
            );
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => 0,
                'source' => 'server_cart',
                'session_id' => (string) ($row['session_id'] ?? ''),
                'client_name' => '',
                'phone' => '',
                'email' => '',
                'cart' => [],
                'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
                'items_count' => (int) ($row['items_count'] ?? 0),
                'checkout_step' => 1,
                'status' => 'open',
                'notes' => '',
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'created_at' => (string) ($row['last_seen_at'] ?? ''),
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function normalizeRow(array $row, string $source = 'lead'): array
    {
        $cart = json_decode((string) ($row['cart_json'] ?? '[]'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'source' => $source,
            'session_id' => (string) ($row['session_id'] ?? ''),
            'client_name' => (string) ($row['client_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'cart' => is_array($cart) ? $cart : [],
            'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
            'items_count' => (int) ($row['items_count'] ?? 0),
            'checkout_step' => (int) ($row['checkout_step'] ?? 1),
            'status' => (string) ($row['status'] ?? 'open'),
            'notes' => (string) ($row['notes'] ?? ''),
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
