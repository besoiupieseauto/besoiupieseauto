<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/public-api-init.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../system/shop-payment.php';
require_once __DIR__ . '/../system/shop-order-guard.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = shop_cart_bootstrap_db();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET' && $action === 'checkout') {
        $reference = trim((string) ($_GET['reference'] ?? ''));
        $session = shop_payment_find_by_reference($pdo, $reference);
        if ($session === null) {
            throw new InvalidArgumentException('Sesiunea de plata nu exista.');
        }

        header('Content-Type: text/html; charset=utf-8');
        $amount = number_format((float) ($session['amount'] ?? 0), 2, '.', '');
        echo '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><title>Plata online</title></head><body style="font-family:Inter,sans-serif;padding:40px;max-width:520px;margin:0 auto;">';
        echo '<h1>Plata online (stub)</h1>';
        echo '<p>Referinta: <strong>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<p>Total: <strong>' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . ' RON</strong></p>';
        echo '<p>Gateway-ul real se configureaza ulterior. Pentru test, apasa butonul de mai jos.</p>';
        $stubWebhookKey = trim((string) (getenv('PAYMENT_WEBHOOK_KEY') ?: ''));
        $stubKeyField = $stubWebhookKey !== ''
            ? '<input type="hidden" name="webhook_key" value="' . htmlspecialchars($stubWebhookKey, ENT_QUOTES, 'UTF-8') . '">'
            : '';
        echo '<form method="POST" action="/api/payment_endpoint.php?action=webhook"><input type="hidden" name="reference" value="' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="status" value="paid">' . $stubKeyField . '<button type="submit" style="background:#059669;color:#fff;border:0;padding:12px 20px;border-radius:8px;font-weight:700;cursor:pointer;">Simuleaza plata reusita</button></form>';
        echo '</body></html>';
        exit;
    }

    if ($method === 'POST' && $action === 'init') {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Payload invalid.');
        }

        $orderRandomId = (int) ($payload['order_randomn_id'] ?? 0);
        $amount = round(max(0, (float) ($payload['amount'] ?? 0)), 2);
        if ($orderRandomId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException('Comanda sau suma invalida.');
        }

        $session = shop_payment_create_session($pdo, $orderRandomId, $amount, 'stub');
        echo json_encode([
            'success' => true,
            'message' => 'Sesiune plata creata.',
            'data' => $session,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'webhook') {
        $rawBody = file_get_contents('php://input') ?: '';
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'paid'));
        $webhookKey = trim((string) (getenv('PAYMENT_WEBHOOK_KEY') ?: ''));
        $providedKey = trim((string) ($payload['webhook_key'] ?? $_SERVER['HTTP_X_PAYMENT_KEY'] ?? ''));
        $appEnv = strtolower(trim((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production'))));
        $paymentDevMode = in_array($appEnv, ['development', 'local', 'dev'], true);

        if ($webhookKey === '') {
            if (!$paymentDevMode) {
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'message' => 'Webhook plată neconfigurat (PAYMENT_WEBHOOK_KEY lipsă).',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } elseif (!hash_equals($webhookKey, $providedKey)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Webhook neautorizat.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($reference === '') {
            throw new InvalidArgumentException('Referinta lipseste.');
        }

        $normalizedStatus = in_array($status, ['paid', 'failed', 'cancelled'], true) ? $status : 'paid';
        shop_payment_mark_status($pdo, $reference, $normalizedStatus, [
            'webhook_at' => date('c'),
            'raw' => $payload,
        ]);

        if (isset($_POST['reference'])) {
            header('Location: /cart.php?payment=success');
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Webhook procesat.',
            'data' => ['reference' => $reference, 'status' => $normalizedStatus],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Actiune plata necunoscuta.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
