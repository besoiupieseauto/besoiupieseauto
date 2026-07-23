<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Exceptions\ValidationException;
use Besoiu\Modules\ImportPro\Controller\ImportProController;
use JsonException;
use Throwable;

final class Crudimport_proHandler
{
    public static function handle(): void
    {
        ApiBootstrap::bootJsonApi();
        ApiBootstrap::requireAuthenticatedSession();

        try {
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Doar POST este permis.'], 405);
            }

            $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || empty($payload['type_product'])) {
                throw new ValidationException('Lipsește type_product din payload.');
            }

            $controller = new ImportProController();
            $action = (string) $payload['type_product'];

            $response = match ($action) {
                'bridge_status' => [
                    'success' => true,
                    'message' => 'Import & Matching Pro — status bridge.',
                    'data' => $controller->bridgeStatus(),
                ],
                'list' => [
                    'success' => true,
                    'message' => 'Import & Matching Pro — listă încărcată.',
                    'data' => $controller->list($payload),
                ],
                'add' => [
                    'success' => true,
                    'message' => 'Import & Matching Pro — înregistrare adăugată.',
                    'data' => $controller->add($payload),
                ],
                'ollama_status' => [
                    'success' => true,
                    'message' => 'Import & Matching Pro — status Ollama.',
                    'data' => $controller->ollamaStatus(),
                ],
                'ai_complete' => [
                    'success' => true,
                    'message' => 'Import & Matching Pro — răspuns AI.',
                    'data' => $controller->aiComplete($payload),
                ],
                default => throw new ValidationException('Acțiune necunoscută: ' . $action),
            };

            ApiBootstrap::json($response);
        } catch (ValidationException $e) {
            ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (JsonException) {
            ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('import_pro_endpoint', $e);
        }
    }
}
