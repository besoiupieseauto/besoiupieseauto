<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Client\ClientHooks;
use Besoiu\Exceptions\ValidationException;
use JsonException;
use Throwable;

final class CrudclientiHandler
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

            $controller = ClientHooks::controller();
            $action = (string) $payload['type_product'];

            $response = match ($action) {
                'list' => [
                    'success' => true,
                    'message' => 'Clienți încărcați.',
                    'data' => $controller->list($payload),
                ],
                'add' => [
                    'success' => true,
                    'message' => 'Client adăugat.',
                    'data' => $controller->add($payload),
                ],
                'edit' => [
                    'success' => true,
                    'message' => 'Client actualizat.',
                    'data' => $controller->update($payload),
                ],
                'setstatus', 'activate' => self::statusResponse($controller, $payload),
                'delete' => self::deleteResponse($controller, $payload),
                default => throw new ValidationException('Acțiune necunoscută: ' . $action),
            };

            ApiBootstrap::json($response);
        } catch (ValidationException $e) {
            ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (JsonException) {
            ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('clienti_endpoint', $e);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function statusResponse(object $controller, array $payload): array
    {
        $controller->changeStatus($payload);

        return ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
    }

    /** @param array<string, mixed> $payload */
    private static function deleteResponse(object $controller, array $payload): array
    {
        $controller->delete($payload);

        return ['success' => true, 'message' => 'Client șters.', 'data' => null];
    }
}
