<?php
declare(strict_types=1);

require_once __DIR__ . '/shop-cart.php';
require_once __DIR__ . '/shop-db.php';

function shop_payment_table_exists(PDO $pdo): bool
{
    return shop_db_table_exists($pdo, 'payment_sessions');
}

function shop_payment_generate_reference(): string
{
    return 'PAY-' . strtoupper(bin2hex(random_bytes(8)));
}

/** @return array<string, mixed> */
function shop_payment_create_session(PDO $pdo, int $orderRandomId, float $amount, string $provider = 'stub'): array
{
    if (!shop_payment_table_exists($pdo)) {
        throw new RuntimeException('Tabela payment_sessions lipseste. Ruleaza migrarea 032.');
    }

    $reference = shop_payment_generate_reference();
    $amount = round(max(0, $amount), 2);

    $stmt = $pdo->prepare(
        'INSERT INTO payment_sessions (order_randomn_id, provider, status, amount, currency, reference, payload_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $orderRandomId > 0 ? $orderRandomId : null,
        $provider,
        'pending',
        $amount,
        'RON',
        $reference,
        json_encode(['created_via' => 'website'], JSON_UNESCAPED_UNICODE),
    ]);

    $baseUrl = rtrim((string) (getenv('APP_URL') ?: 'https://besoiupieseauto.ro'), '/');
    $paymentUrl = $baseUrl . '/api/payment_endpoint.php?action=checkout&reference=' . urlencode($reference);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'reference' => $reference,
        'amount' => $amount,
        'currency' => 'RON',
        'status' => 'pending',
        'payment_url' => $paymentUrl,
        'provider' => $provider,
    ];
}

/** @return array<string, mixed>|null */
function shop_payment_find_by_reference(PDO $pdo, string $reference): ?array
{
    if (!shop_payment_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM payment_sessions WHERE reference = ? LIMIT 1');
    $stmt->execute([trim($reference)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function shop_payment_mark_status(PDO $pdo, string $reference, string $status, ?array $payload = null): void
{
    if (!shop_payment_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE payment_sessions
         SET status = ?, payload_json = COALESCE(?, payload_json), updated_at = NOW()
         WHERE reference = ?'
    );
    $stmt->execute([
        $status,
        $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        trim($reference),
    ]);

    $session = shop_payment_find_by_reference($pdo, $reference);
    if (!$session || empty($session['order_randomn_id'])) {
        return;
    }

    $orderRandomId = (int) $session['order_randomn_id'];
    if ($orderRandomId <= 0) {
        return;
    }

    if ($status === 'paid') {
        $upd = $pdo->prepare(
            'UPDATE comenzi
             SET payment_status = ?, payment_reference = ?, payment_status_detail = ?
             WHERE randomn_id = ?'
        );
        $upd->execute(['card_online', $reference, 'paid', $orderRandomId]);
    } elseif ($status === 'failed') {
        $upd = $pdo->prepare(
            'UPDATE comenzi SET payment_status_detail = ? WHERE randomn_id = ?'
        );
        $upd->execute(['failed', $orderRandomId]);
    }
}
