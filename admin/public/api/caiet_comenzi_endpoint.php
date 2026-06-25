<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Controllers\CaietComenzi\CaietComenzi;
use Evasystem\Controllers\CaietComenzi\CaietComenziService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\CaietComenzi\CaietComenziModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;
$config = ApiBootstrap::bootJsonApi();
ApiBootstrap::ensureSession();
ApiBootstrap::requireAuthenticatedSession();

try {

    $legacyName = trim((string) ($config['legacy_db_name'] ?? ''));
    if ($legacyName === '') {
        throw new ValidationException('Lipseste configurarea LEGACY_DB_NAME pentru Caiet de comenzi.');
    }

    Database::getInstance(
        (string) ($config['legacy_db_host'] ?? ''),
        $legacyName,
        (string) ($config['legacy_db_user'] ?? ''),
        (string) ($config['legacy_db_pass'] ?? ''),
        'legacy'
    );

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $rawBody = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipseste type_product din payload.');
    }

    $controller = new CaietComenzi(new CaietComenziService(new CaietComenziModel()));
    $model = new CaietComenziModel();
    $action = (string) $payload['type_product'];
    $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['usersveryfi'] ?? 0);

    switch ($action) {
        case 'stats':
            $response = [
                'success' => true,
                'message' => 'Statistici incarcate.',
                'data' => $controller->stats(),
            ];
            break;

        case 'stats_location':
            $location = trim((string) ($payload['location'] ?? ''));
            $response = [
                'success' => true,
                'message' => 'Statistici pe locatie incarcate.',
                'data' => $model->getStatsByLocation($location),
            ];
            break;

        case 'list':
            $response = [
                'success' => true,
                'message' => 'Comenzi incarcate.',
                'data' => $controller->list($payload),
            ];
            break;

        case 'details':
            $response = [
                'success' => true,
                'message' => 'Detalii comanda incarcate.',
                'data' => $controller->details($payload),
            ];
            break;

        case 'setstatus':
        case 'update-status':
            $response = [
                'success' => true,
                'message' => 'Status comanda actualizat.',
                'data' => $controller->changeStatus($payload, $userId),
            ];
            break;

        case 'clienti_list':
            $response = [
                'success' => true,
                'message' => 'Clienti incarcati.',
                'data' => $model->findClients($payload),
            ];
            break;

        case 'clienti_save':
            $response = [
                'success' => true,
                'message' => 'Client salvat.',
                'data' => $model->saveClient($payload),
            ];
            break;

        case 'clienti_delete':
            $clientId = isset($payload['idclienti']) ? (int) $payload['idclienti'] : 0;
            $response = [
                'success' => true,
                'message' => 'Client sters.',
                'data' => ['deleted' => $model->deleteClient($clientId)],
            ];
            break;

        case 'produse_list':
            $response = [
                'success' => true,
                'message' => 'Produse incarcate.',
                'data' => $model->findProduse($payload),
            ];
            break;

        case 'produse_save':
            $response = [
                'success' => true,
                'message' => 'Produs salvat.',
                'data' => $model->saveProduct($payload),
            ];
            break;

        case 'produse_delete':
            $productId = isset($payload['idprodus']) ? (int) $payload['idprodus'] : 0;
            $response = [
                'success' => true,
                'message' => 'Produs sters.',
                'data' => ['deleted' => $model->deleteProduct($productId)],
            ];
            break;

        case 'produse_update_tva':
            $tva = isset($payload['tva']) ? (float) $payload['tva'] : 0.0;
            $response = [
                'success' => true,
                'message' => 'TVA actualizat.',
                'data' => ['affected' => $model->updateAllProductsTva($tva)],
            ];
            break;

        case 'facturi_list':
            $response = [
                'success' => true,
                'message' => 'Facturi incarcate.',
                'data' => $model->findFacturi($payload),
            ];
            break;

        case 'facturi_save':
            $response = [
                'success' => true,
                'message' => 'Factura salvata.',
                'data' => $model->saveFactura($payload),
            ];
            break;

        case 'facturi_delete':
            $orderId = isset($payload['OrderID']) ? (int) $payload['OrderID'] : 0;
            $response = [
                'success' => true,
                'message' => 'Factura stearsa.',
                'data' => ['deleted' => $model->deleteFactura($orderId)],
            ];
            break;

        case 'factura_details':
            $orderId = isset($payload['OrderID']) ? (int) $payload['OrderID'] : 0;
            $response = [
                'success' => true,
                'message' => 'Detalii factura incarcate.',
                'data' => $model->getFacturaDetails($orderId),
            ];
            break;

        case 'incasari_list':
            $response = [
                'success' => true,
                'message' => 'Incasari incarcate.',
                'data' => $model->findIncasari($payload),
            ];
            break;

        case 'incasari_save':
            $response = [
                'success' => true,
                'message' => 'Incasare salvata.',
                'data' => $model->saveIncasare($payload, $userId),
            ];
            break;

        case 'incasari_delete':
            $incasareId = isset($payload['id']) ? (int) $payload['id'] : 0;
            $response = [
                'success' => true,
                'message' => 'Incasare stearsa.',
                'data' => ['deleted' => $model->deleteIncasare($incasareId)],
            ];
            break;

        case 'incasari_daily_price_get':
            $response = [
                'success' => true,
                'message' => 'Suma start zi incarcata.',
                'data' => $model->getDailyCash(),
            ];
            break;

        case 'incasari_daily_price_update':
            $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;
            $response = [
                'success' => true,
                'message' => 'Suma start zi actualizata.',
                'data' => $model->updateDailyCash($amount),
            ];
            break;

        default:
            throw new ValidationException('Actiune necunoscuta: ' . $action);
    }

    ApiBootstrap::json($response);
} catch (JsonException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
} catch (ValidationException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (NotFoundException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 404);
} catch (\InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('caiet_comenzi_endpoint', $exception);
}
