<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Bots\Bots;
use Evasystem\Controllers\Bots\BotsService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Bots\BotsModel;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::runJsonEndpoint(static function (): void {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipsește type_product din payload.');
    }

    $controller = new Bots(new BotsService(new BotsModel()));

    switch ((string) $payload['type_product']) {
        case 'list':
            $response = ['success' => true, 'message' => 'Boți încărcați.', 'data' => $controller->list($payload)];
            break;
        case 'get':
            $response = ['success' => true, 'message' => 'Bot incarcat.', 'data' => $controller->find($payload)];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Bot adăugat.', 'data' => $controller->add($payload)];
            break;
        case 'edit':
            $response = ['success' => true, 'message' => 'Bot actualizat.', 'data' => $controller->update($payload)];
            break;
        case 'setstatus':
            $controller->changeStatus($payload);
            $response = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
            break;
        case 'testbot':
            $response = ['success' => true, 'message' => 'Test bot executat.', 'data' => $controller->test($payload)];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Bot șters.', 'data' => null];
            break;
        default:
            throw new ValidationException('Acțiune necunoscută.');
    }

    ApiBootstrap::json($response);
}, 'bots_endpoint');
