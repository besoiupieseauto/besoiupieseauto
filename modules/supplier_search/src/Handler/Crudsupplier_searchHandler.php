<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\SupplierSearch\Controller\SupplierSearchController;
use Besoiu\Exceptions\ValidationException;
use JsonException;
use Throwable;

final class Crudsupplier_searchHandler
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
                throw new ValidationException('Lipseste type_product din payload.');
            }

            $controller = new SupplierSearchController();
            $type = (string) $payload['type_product'];

            $response = match ($type) {
                'list' => [
                    'success' => true,
                    'message' => 'Supplier Search B2B încărcate.',
                    'data' => $controller->list($payload),
                ],
                'get' => [
                    'success' => true,
                    'message' => 'Înregistrare găsită.',
                    'data' => $controller->find($payload),
                ],
                default => throw new ValidationException('type_product necunoscut: ' . $type),
            };

            ApiBootstrap::json($response);
        } catch (ValidationException $e) {
            ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (JsonException) {
            ApiBootstrap::json(['success' => false, 'message' => 'JSON invalid.'], 400);
        } catch (Throwable $e) {
            ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
