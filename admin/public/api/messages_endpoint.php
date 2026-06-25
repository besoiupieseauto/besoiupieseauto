<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Messages\Messages;
use Evasystem\Controllers\Messages\MessagesService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Messages\MessagesModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::bootJsonApi();

require_once dirname(ApiBootstrap::projectRoot()) . '/system/shop-order-guard.php';

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($payload) || empty($payload['type_product'])) {
        throw new ValidationException('Lipsește type_product din payload.');
    }

    $action = (string) $payload['type_product'];
    $isPublicWebsiteContact = $action === 'add'
        && mb_strtolower(trim((string) ($payload['channel'] ?? ''))) === 'website'
        && mb_strtolower(trim((string) ($payload['direction'] ?? ''))) === 'inbound';

    if (!$isPublicWebsiteContact) {
        ApiBootstrap::requireAuthenticatedSession();
    } elseif (!shop_order_rate_limit_check(20)) {
        ApiBootstrap::json(['success' => false, 'message' => 'Prea multe mesaje trimise. Încearcă din nou peste câteva minute.'], 429);
    }

    $controller = new Messages(new MessagesService(new MessagesModel()));

    switch ($action) {
        case 'list':
            $response = ['success' => true, 'message' => 'Mesaje încărcate.', 'data' => $controller->list($payload)];
            break;
        case 'conversations':
            $response = ['success' => true, 'message' => 'Conversații încărcate.', 'data' => $controller->conversations($payload)];
            break;
        case 'conversation':
            $response = ['success' => true, 'message' => 'Conversație încărcată.', 'data' => $controller->conversation($payload)];
            break;
        case 'add':
            $response = ['success' => true, 'message' => 'Mesaj adăugat.', 'data' => $controller->add($payload)];
            break;
        case 'setstatus':
        case 'markread':
            $controller->markAsRead($payload);
            $response = ['success' => true, 'message' => 'Mesaj marcat ca citit.', 'data' => null];
            break;
        case 'delete':
            $controller->delete($payload);
            $response = ['success' => true, 'message' => 'Mesaj șters.', 'data' => null];
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
    ApiBootstrap::logError('messages_endpoint', $exception);
    ApiBootstrap::json(['success' => false, 'message' => ApiBootstrap::internalErrorMessage($exception)], 500);
}
