<?php
declare(strict_types=1);

require_once __DIR__ . '/shop-auth.php';
require_once __DIR__ . '/shop-db.php';

function shop_coupon_table_exists(PDO $pdo): bool
{
    return shop_db_table_exists($pdo, 'coupons');
}

function shop_coupon_normalize_code(string $code): string
{
    return strtoupper(trim($code));
}

/** @return array<string, mixed>|null */
function shop_coupon_find(PDO $pdo, string $code): ?array
{
    if (!shop_coupon_table_exists($pdo)) {
        return null;
    }

    $code = shop_coupon_normalize_code($code);
    if ($code === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array{valid:bool, message:string, coupon?:array<string,mixed>, discount?:float, total_after?:float} */
function shop_coupon_validate(PDO $pdo, string $code, float $subtotal): array
{
    $subtotal = round(max(0, $subtotal), 2);
    $coupon = shop_coupon_find($pdo, $code);

    if ($coupon === null) {
        return ['valid' => false, 'message' => 'Codul promotional nu exista.'];
    }

    if ((int) ($coupon['is_active'] ?? 0) !== 1) {
        return ['valid' => false, 'message' => 'Cuponul nu mai este activ.'];
    }

    $now = time();
    $validFrom = !empty($coupon['valid_from']) ? strtotime((string) $coupon['valid_from']) : null;
    $validUntil = !empty($coupon['valid_until']) ? strtotime((string) $coupon['valid_until']) : null;

    if ($validFrom !== null && $validFrom !== false && $now < $validFrom) {
        return ['valid' => false, 'message' => 'Cuponul nu este inca activ.'];
    }
    if ($validUntil !== null && $validUntil !== false && $now > $validUntil) {
        return ['valid' => false, 'message' => 'Cuponul a expirat.'];
    }

    $minOrder = round((float) ($coupon['min_order'] ?? 0), 2);
    if ($subtotal < $minOrder) {
        return [
            'valid' => false,
            'message' => 'Comanda minima pentru acest cupon este ' . number_format($minOrder, 2, '.', '') . ' RON.',
        ];
    }

    $maxUses = $coupon['max_uses'] ?? null;
    if ($maxUses !== null && (int) $maxUses > 0 && (int) ($coupon['used_count'] ?? 0) >= (int) $maxUses) {
        return ['valid' => false, 'message' => 'Cuponul a atins numarul maxim de utilizari.'];
    }

    $discount = shop_coupon_calculate_discount($coupon, $subtotal);
    $totalAfter = round(max(0, $subtotal - $discount), 2);

    return [
        'valid' => true,
        'message' => 'Cupon aplicat.',
        'coupon' => $coupon,
        'discount' => $discount,
        'total_after' => $totalAfter,
    ];
}

/** @param array<string, mixed> $coupon */
function shop_coupon_calculate_discount(array $coupon, float $subtotal): float
{
    $subtotal = round(max(0, $subtotal), 2);
    $type = (string) ($coupon['discount_type'] ?? 'percent');
    $value = round((float) ($coupon['discount_value'] ?? 0), 2);

    if ($value <= 0) {
        return 0.0;
    }

    if ($type === 'fixed') {
        return round(min($subtotal, $value), 2);
    }

    return round(min($subtotal, $subtotal * ($value / 100)), 2);
}

function shop_coupon_increment_usage(PDO $pdo, string $code): void
{
    if (!shop_coupon_table_exists($pdo)) {
        return;
    }

    $code = shop_coupon_normalize_code($code);
    if ($code === '') {
        return;
    }

    $stmt = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?');
    $stmt->execute([$code]);
}
