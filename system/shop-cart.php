<?php
declare(strict_types=1);

require_once __DIR__ . '/shop-auth.php';
require_once __DIR__ . '/shop-db.php';

function shop_cart_bootstrap_db(): PDO
{
    return shop_db_bootstrap();
}

function shop_cart_session_id(): string
{
    shop_auth_session_start();

    return session_id();
}

function shop_cart_table_exists(PDO $pdo): bool
{
    return shop_db_table_exists($pdo, 'cart_items');
}

function shop_cart_parse_price($value): float
{
    $normalized = str_replace(',', '.', trim((string) $value));
    if (!is_numeric($normalized)) {
        return 0.0;
    }

    return round(max(0, (float) $normalized), 2);
}

function shop_cart_product_image(array $product): string
{
    $decoded = json_decode((string) ($product['pImages'] ?? '[]'), true);
    if (is_array($decoded)) {
        foreach ($decoded as $image) {
            $url = trim((string) $image);
            if ($url !== '') {
                return $url;
            }
        }
    }

    return '';
}

/** @return array<string, mixed>|null */
function shop_cart_load_product(PDO $pdo, string $randomId): ?array
{
    $randomId = trim($randomId);
    if ($randomId === '') {
        return null;
    }

    if (ctype_digit($randomId)) {
        $stmt = $pdo->prepare('SELECT * FROM produse WHERE id = ? AND status <> \'0\' LIMIT 1');
        $stmt->execute([(int) $randomId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM produse WHERE randomn_id = ? AND status <> \'0\' LIMIT 1');
        $stmt->execute([$randomId]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<int, array<string, mixed>> */
function shop_cart_get_items(PDO $pdo, ?string $sessionId = null): array
{
    if (!shop_cart_table_exists($pdo)) {
        return [];
    }

    $sessionId = $sessionId ?: shop_cart_session_id();
    if ($sessionId === '') {
        return [];
    }

    $stmt = $pdo->prepare('SELECT * FROM cart_items WHERE session_id = ? ORDER BY id ASC');
    $stmt->execute([$sessionId]);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $snapshot = json_decode((string) ($row['product_snapshot'] ?? '{}'), true);
        if (!is_array($snapshot)) {
            $snapshot = [];
        }

        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $price = shop_cart_parse_price($row['unit_price'] ?? 0);

        $items[] = [
            'product_id' => (string) ($row['randomn_id'] ?? ''),
            'randomn_id' => (string) ($row['randomn_id'] ?? ''),
            'product_name' => (string) ($snapshot['product_name'] ?? 'Produs'),
            'product_image' => (string) ($snapshot['product_image'] ?? ''),
            'oem' => (string) ($snapshot['oem'] ?? ''),
            'quantity' => $quantity,
            'price' => $price,
            'total_amount' => round($price * $quantity, 2),
            'source' => (string) ($snapshot['source'] ?? 'server'),
        ];
    }

    return $items;
}

function shop_cart_add_item(PDO $pdo, string $randomId, int $quantity = 1): array
{
    if (!shop_cart_table_exists($pdo)) {
        throw new InvalidArgumentException('Cosul server-side nu este disponibil.');
    }

    $quantity = max(1, $quantity);
    $product = shop_cart_load_product($pdo, $randomId);
    if ($product === null) {
        throw new InvalidArgumentException('Produsul nu este disponibil.');
    }

    $sessionId = shop_cart_session_id();
    $price = shop_cart_parse_price($product['pPrice'] ?? 0);
    $randomnId = (string) ($product['randomn_id'] ?? $randomId);
    $snapshot = [
        'product_name' => (string) ($product['pName'] ?? 'Produs'),
        'product_image' => shop_cart_product_image($product),
        'oem' => (string) ($product['pCode'] ?? ''),
        'source' => 'server',
    ];

    $check = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE session_id = ? AND randomn_id = ? LIMIT 1');
    $check->execute([$sessionId, $randomnId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = max(1, (int) $existing['quantity']) + $quantity;
        $upd = $pdo->prepare('UPDATE cart_items SET quantity = ?, unit_price = ?, product_snapshot = ? WHERE id = ?');
        $upd->execute([
            $newQty,
            $price,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            (int) $existing['id'],
        ]);
    } else {
        $customerId = (int) ($_SESSION['shop_customer_id'] ?? 0);
        $ins = $pdo->prepare(
            'INSERT INTO cart_items (session_id, shop_customer_id, product_id, randomn_id, quantity, unit_price, product_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $sessionId,
            $customerId > 0 ? $customerId : null,
            (int) ($product['id'] ?? 0),
            $randomnId,
            $quantity,
            $price,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        ]);
    }

    $items = shop_cart_get_items($pdo, $sessionId);

    return [
        'items' => $items,
        'count' => array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
    ];
}

function shop_cart_update_quantity(PDO $pdo, string $randomId, int $quantity): array
{
    if (!shop_cart_table_exists($pdo)) {
        throw new InvalidArgumentException('Cosul server-side nu este disponibil.');
    }

    $quantity = max(0, $quantity);
    $sessionId = shop_cart_session_id();

    if ($quantity === 0) {
        return shop_cart_remove_item($pdo, $randomId);
    }

    $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE session_id = ? AND randomn_id = ?');
    $stmt->execute([$quantity, $sessionId, trim($randomId)]);

    $items = shop_cart_get_items($pdo, $sessionId);

    return [
        'items' => $items,
        'count' => array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
    ];
}

function shop_cart_remove_item(PDO $pdo, string $randomId): array
{
    $sessionId = shop_cart_session_id();
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE session_id = ? AND randomn_id = ?');
    $stmt->execute([$sessionId, trim($randomId)]);

    $items = shop_cart_get_items($pdo, $sessionId);

    return [
        'items' => $items,
        'count' => array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
    ];
}

function shop_cart_clear(PDO $pdo): void
{
    if (!shop_cart_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE session_id = ?');
    $stmt->execute([shop_cart_session_id()]);
}

/** @param array<int, array<string, mixed>> $localItems */
function shop_cart_sync_from_client(PDO $pdo, array $localItems): array
{
    if (!shop_cart_table_exists($pdo)) {
        return ['items' => [], 'count' => 0, 'synced' => false];
    }

    shop_cart_clear($pdo);

    foreach ($localItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $randomId = trim((string) ($item['product_id'] ?? $item['randomn_id'] ?? ''));
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        if ($randomId === '') {
            continue;
        }
        try {
            shop_cart_add_item($pdo, $randomId, $quantity);
        } catch (Throwable $exception) {
        }
    }

    $items = shop_cart_get_items($pdo);

    return [
        'items' => $items,
        'count' => array_sum(array_map(static fn (array $row): int => (int) ($row['quantity'] ?? 0), $items)),
        'synced' => true,
    ];
}
