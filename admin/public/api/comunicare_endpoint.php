<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Core\Comunicare\ReplyTemplateRenderer;
use Evasystem\Services\ComunicareHubService;
use Evasystem\Services\ReplyTemplateService;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;

ApiBootstrap::bootJsonApi();

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $hub = new ComunicareHubService();
    $templates = new ReplyTemplateService();

    if ($method === 'GET') {
        $action = trim((string) ($_GET['action'] ?? 'hub'));

        if ($action === 'hub') {
            ApiBootstrap::requireAuthenticatedSession();
            ApiBootstrap::json(['success' => true, 'data' => $hub->hubStats()]);
        }

        if ($action === 'list') {
            ApiBootstrap::requireAuthenticatedSession();
            $filters = [
                'q' => $_GET['q'] ?? '',
                'channel' => $_GET['channel'] ?? '',
                'category' => $_GET['category'] ?? '',
            ];
            if (isset($_GET['is_quick'])) {
                $filters['is_quick'] = (int) $_GET['is_quick'];
            }
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'items' => $templates->list($filters),
                    'variables' => ReplyTemplateRenderer::availableVariables(),
                ],
            ]);
        }

        ApiBootstrap::json(['success' => false, 'message' => 'Acțiune GET necunoscută.'], 400);
    }

    ApiBootstrap::requireAuthenticatedSession();

    if ($method !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new ValidationException('Payload invalid.');
    }

    $action = trim((string) ($payload['action'] ?? ''));

    switch ($action) {
        case 'create':
            ApiBootstrap::json(['success' => true, 'data' => $templates->create($payload)]);
            break;
        case 'update':
            $id = (string) ($payload['randomn_id'] ?? '');
            ApiBootstrap::json(['success' => true, 'data' => $templates->update($id, $payload)]);
            break;
        case 'delete':
            $templates->delete((string) ($payload['randomn_id'] ?? ''));
            ApiBootstrap::json(['success' => true, 'message' => 'Template dezactivat.']);
            break;
        case 'apply':
            $vars = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
            ApiBootstrap::json([
                'success' => true,
                'data' => $templates->apply((string) ($payload['randomn_id'] ?? ''), $vars),
            ]);
            break;
        default:
            throw new ValidationException('Acțiune necunoscută.');
    }
} catch (ValidationException $e) {
    ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 400);
} catch (NotFoundException $e) {
    ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 404);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('comunicare_endpoint', $e);
}
