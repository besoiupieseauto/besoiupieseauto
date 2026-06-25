<?php

declare(strict_types=1);

require_once __DIR__ . '/../system/public-api-init.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../system/shop-auth.php';

header('Content-Type: application/json; charset=utf-8');

function cont_json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    shop_auth_session_start();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cont_json_response(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        cont_json_response(['success' => false, 'message' => 'JSON invalid.'], 400);
    }

    $action = (string) ($payload['action'] ?? '');
    $sessionUser = shop_auth_session_user();

    switch ($action) {
        case 'register':
            $customer = shop_auth_register($payload);
            cont_json_response([
                'success' => true,
                'message' => 'Cont creat cu succes. Ești autentificat.',
                'customer' => $customer,
            ]);
            break;

        case 'login':
            $customer = shop_auth_login(
                (string) ($payload['email'] ?? ''),
                (string) ($payload['password'] ?? '')
            );
            cont_json_response([
                'success' => true,
                'message' => 'Autentificare reușită.',
                'customer' => $customer,
            ]);
            break;

        case 'logout':
            shop_auth_logout();
            cont_json_response(['success' => true, 'message' => 'Te-ai deconectat.']);
            break;

        case 'me':
            if ($sessionUser === null) {
                cont_json_response(['success' => false, 'message' => 'Nu ești autentificat.'], 401);
            }
            $customer = shop_auth_find_by_id((int) $sessionUser['id']);
            if ($customer === null) {
                shop_auth_logout();
                cont_json_response(['success' => false, 'message' => 'Sesiune invalidă.'], 401);
            }
            cont_json_response([
                'success' => true,
                'customer' => shop_auth_public_customer($customer),
            ]);
            break;

        case 'update_profile':
            if ($sessionUser === null) {
                cont_json_response(['success' => false, 'message' => 'Nu ești autentificat.'], 401);
            }
            $customer = shop_auth_update_profile((int) $sessionUser['id'], $payload);
            cont_json_response([
                'success' => true,
                'message' => 'Profil actualizat.',
                'customer' => $customer,
            ]);
            break;

        case 'change_password':
            if ($sessionUser === null) {
                cont_json_response(['success' => false, 'message' => 'Nu ești autentificat.'], 401);
            }
            shop_auth_change_password((int) $sessionUser['id'], $payload);
            cont_json_response(['success' => true, 'message' => 'Parola a fost schimbată.']);
            break;

        case 'orders':
            if ($sessionUser === null) {
                cont_json_response(['success' => false, 'message' => 'Nu ești autentificat.'], 401);
            }
            $orders = shop_auth_orders_for_email((string) $sessionUser['email']);
            cont_json_response(['success' => true, 'orders' => $orders]);
            break;

        case 'cancel_order':
            if ($sessionUser === null) {
                cont_json_response(['success' => false, 'message' => 'Nu ești autentificat.'], 401);
            }
            $orderId = (int) ($payload['order_id'] ?? 0);
            if ($orderId <= 0) {
                cont_json_response(['success' => false, 'message' => 'Comandă invalidă.'], 400);
            }
            shop_auth_cancel_order((string) $sessionUser['email'], $orderId);
            cont_json_response(['success' => true, 'message' => 'Comanda a fost anulată.']);
            break;

        default:
            cont_json_response(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }
} catch (InvalidArgumentException $exception) {
    cont_json_response(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    error_log('[cont_endpoint] ' . $exception->getMessage());
    cont_json_response(['success' => false, 'message' => 'A apărut o problemă. Încearcă din nou.'], 500);
}
