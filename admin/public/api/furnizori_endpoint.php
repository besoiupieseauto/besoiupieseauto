<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Controllers\Furnizori\Furnizori;
use Evasystem\Controllers\Furnizori\FurnizoriService;
use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Furnizori\FurnizoriModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;
ApiBootstrap::bootJsonApi();
ApiBootstrap::requireAuthenticatedSession();

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipseste type_product din payload.');
    }

    $controller = new Furnizori(new FurnizoriService(new FurnizoriModel(), new FurnizoriStatsService(new FurnizoriModel())));

    switch ((string) $payload['type_product']) {
        case 'list':
            $response = ['success' => true, 'message' => 'Furnizori incarcati.', 'data' => $controller->list($payload)];
            break;
        case 'get':
            $response = ['success' => true, 'message' => 'Furnizor incarcat.', 'data' => $controller->find($payload)];
            break;
        case 'products':
            $response = ['success' => true, 'message' => 'Produse furnizor.', 'data' => $controller->products($payload)];
            break;
        case 'browseconnection':
        case 'browse_connection':
            $response = ['success' => true, 'message' => 'Explorare conexiune.', 'data' => $controller->browse($payload)];
            break;
        case 'mirror_feed_files':
        case 'mirrorfeedfiles':
            $mirror = $controller->mirrorFeedFiles($payload);
            $copied = count($mirror['copied'] ?? []);
            $response = [
                'success' => true,
                'message' => $copied > 0
                    ? ($copied . ' fisier(e) copiate in folderul local al furnizorului.')
                    : 'Niciun fisier nou de copiat (deja in folder sau lipsa din import).',
                'data' => $mirror,
            ];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Furnizor adaugat.', 'data' => $controller->add($payload)];
            break;
        case 'edit':
            $response = ['success' => true, 'message' => 'Furnizor actualizat.', 'data' => $controller->update($payload)];
            break;
        case 'setstatus':
        case 'block':
            $controller->block($payload);
            $response = ['success' => true, 'message' => 'Furnizor blocat.', 'data' => null];
            break;
        case 'unblock':
        case 'activate':
            $controller->unblock($payload);
            $response = ['success' => true, 'message' => 'Furnizor activat.', 'data' => null];
            break;
        case 'testconnection':
            $response = ['success' => true, 'message' => 'Test conexiune executat.', 'data' => $controller->test($payload)];
            break;
        case 'syncnow':
        case 'sync_now':
            $response = ['success' => true, 'message' => 'Sincronizare executata.', 'data' => $controller->sync($payload)];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Furnizor sters.', 'data' => null];
            break;
        case 'getpricelogic':
        case 'get_price_logic':
            $response = ['success' => true, 'message' => 'Logica de pret incarcata.', 'data' => $controller->getPriceLogic()];
            break;
        case 'savepricelogic':
        case 'save_price_logic':
            $response = ['success' => true, 'message' => 'Logica de pret salvata.', 'data' => $controller->savePriceLogic($payload)];
            break;
        case 'testpricelogic':
        case 'test_price_logic':
            $response = ['success' => true, 'message' => 'Test logica de pret executat.', 'data' => $controller->testPriceLogic($payload)];
            break;
        default:
            throw new ValidationException('Actiune necunoscuta.');
    }

    ApiBootstrap::json($response);
} catch (JsonException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
} catch (ValidationException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (NotFoundException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('furnizori_endpoint', $exception);
}
