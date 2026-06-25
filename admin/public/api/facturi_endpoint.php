<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Facturi\Facturi;
use Evasystem\Controllers\Facturi\FacturiService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Facturi\FacturiModel;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::runJsonEndpoint(static function (): void {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipsește type_product din payload.');
    }

    $controller = new Facturi(new FacturiService(new FacturiModel()));
    $action = (string) $payload['type_product'];

    switch ($action) {
        case 'list':
            $response = ['success' => true, 'message' => 'Facturi încărcate.', 'data' => $controller->list($payload)];
            break;
        case 'stats':
            $response = ['success' => true, 'message' => 'Statistici facturi.', 'data' => $controller->stats()];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Factură adăugată.', 'data' => $controller->add($payload)];
            break;
        case 'edit':
            $response = ['success' => true, 'message' => 'Factură actualizată.', 'data' => $controller->update($payload)];
            break;
        case 'setstatus':
        case 'activate':
            $controller->changeStatus($payload);
            $response = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Factură ștearsă.', 'data' => null];
            break;
        default:
            throw new ValidationException('Acțiune necunoscută: ' . $action);
    }

    ApiBootstrap::json($response);
}, 'facturi_endpoint');
