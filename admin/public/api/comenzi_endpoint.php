<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Controllers\Comenzi\Comenzi;
use Evasystem\Controllers\Comenzi\ComenziService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;
ApiBootstrap::bootJsonApi();

require_once dirname(ApiBootstrap::projectRoot()) . '/system/shop-order-guard.php';

try {
    ensureComenziProductImageColumn();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET' && (string) ($_GET['action'] ?? '') === 'csrf_token') {
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Token CSRF generat.',
            'data' => ['token' => shop_order_csrf_token()],
        ]);
    }

    if ($method !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $rawBody = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipsește type_product din payload.');
    }

    $action = (string) $payload['type_product'];
    $isWebsiteCheckout = $action === 'add'
        && !empty($payload['items'])
        && is_array($payload['items'])
        && (string) ($payload['channel'] ?? 'website') === 'website';

    if (!$isWebsiteCheckout) {
        ApiBootstrap::requireAuthenticatedSession();
    }

    if ($isWebsiteCheckout) {
        $csrfToken = (string) ($payload['csrf_token'] ?? $_SERVER['HTTP_X_BPA_CSRF'] ?? '');
        if (!shop_order_csrf_validate($csrfToken)) {
            ApiBootstrap::json(['success' => false, 'message' => 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.'], 403);
        }

        if (!shop_order_rate_limit_check(15)) {
            ApiBootstrap::json(['success' => false, 'message' => 'Prea multe comenzi trimise. Încearcă din nou peste câteva minute.'], 429);
        }
    }

    $controller = new Comenzi(new ComenziService(new ComenziModel()));

    switch ($action) {
        case 'list':
            $response = [
                'success' => true,
                'message' => 'Comenzi încărcate.',
                'data' => $controller->list($payload),
            ];
            break;

        case 'add':
            $response = [
                'success' => true,
                'message' => 'Comandă adăugată.',
                'data' => $controller->add($payload),
            ];
            if ($isWebsiteCheckout) {
                shop_order_csrf_rotate();
            }
            break;

        case 'edit':
            $response = [
                'success' => true,
                'message' => 'Comandă actualizată.',
                'data' => $controller->update($payload),
            ];
            break;

        case 'setstatus':
        case 'activate':
            $controller->changeStatus($payload);
            $response = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
            break;

        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Comandă ștearsă.', 'data' => null];
            break;

        case 'fulfillment':
            $response = [
                'success' => true,
                'message' => 'Legaturi comanda incarcate.',
                'data' => $controller->fulfillment($payload),
            ];
            break;

        case 'create_invoice':
            $response = [
                'success' => true,
                'message' => 'Factura procesata.',
                'data' => $controller->createInvoice($payload),
            ];
            break;

        case 'create_delivery':
            $response = [
                'success' => true,
                'message' => 'Livrare procesata.',
                'data' => $controller->createDelivery($payload),
            ];
            break;

        default:
            throw new ValidationException('Acțiune necunoscută: ' . $action);
    }

    ApiBootstrap::json($response);
} catch (JsonException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
} catch (ValidationException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (NotFoundException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('comenzi_endpoint', $exception);
}

function ensureComenziProductImageColumn(): void
{
    $pdo = Database::getDB();
    $statement = $pdo->query("SHOW COLUMNS FROM comenzi LIKE 'product_image'");

    if ($statement !== false && $statement->fetch() === false) {
        $pdo->exec('ALTER TABLE comenzi ADD COLUMN product_image VARCHAR(500) NULL AFTER name');
    }
}
