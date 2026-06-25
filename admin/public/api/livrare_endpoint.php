<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Livrare\Livrare;
use Evasystem\Controllers\Livrare\LivrareService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Livrare\LivrareModel;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::runJsonEndpoint(static function (): void {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipsește type_product din payload.');
    }

    $controller = new Livrare(new LivrareService(new LivrareModel()));
    $action = (string) $payload['type_product'];

    switch ($action) {
        case 'list':
            $response = ['success' => true, 'message' => 'Livrări încărcate.', 'data' => $controller->list($payload)];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Livrare adăugată.', 'data' => $controller->add($payload)];
            break;
        case 'edit':
            $response = ['success' => true, 'message' => 'Livrare actualizată.', 'data' => $controller->update($payload)];
            break;
        case 'setstatus':
        case 'activate':
            $controller->changeStatus($payload);
            $response = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Livrare ștearsă.', 'data' => null];
            break;
        default:
            throw new ValidationException('Acțiune necunoscută: ' . $action);
    }

    ApiBootstrap::json($response);
}, 'livrare_endpoint');
