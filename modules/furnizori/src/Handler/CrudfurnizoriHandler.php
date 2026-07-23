<?php
declare(strict_types=1);



namespace Besoiu\Modules\Furnizori\Handler;



use Besoiu\Core\Auth\AdminCsrf;

use Besoiu\Core\Bootstrap\ApiBootstrap;

use Besoiu\Modules\Furnizori\Controller\FurnizoriController;
use Besoiu\Modules\Furnizori\Model\FurnizoriRepository;
use Besoiu\Modules\Furnizori\Service\FurnizoriService;
use Besoiu\Modules\Furnizori\Service\FurnizoriStatsService;

use Besoiu\Exceptions\NotFoundException;

use Besoiu\Exceptions\ValidationException;

use JsonException;

use Throwable;



/**

 * Handler JSON pentru furnizori — folosit după ApiBootstrap::bootJsonApi().

 */

final class CrudfurnizoriHandler

{

    /** @var list<string> */

    private const READ_ACTIONS = [

        'list', 'get', 'products', 'browseconnection', 'browse_connection',

        'getpricelogic', 'get_price_logic', 'testpricelogic', 'test_price_logic',

    ];



    public static function handle(): void

    {

        ApiBootstrap::bootJsonApi();

        ApiBootstrap::requireAuthenticatedSession();



        try {

            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {

                self::respond(['success' => false, 'message' => 'Doar POST este permis.'], 405);

            }



            $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload) || empty($payload['type_product'])) {

                throw new ValidationException('Lipseste type_product din payload.');

            }



            $action = strtolower((string) $payload['type_product']);

            if (!in_array($action, self::READ_ACTIONS, true)) {

                $csrf = (string) ($payload['csrf_token'] ?? $_SERVER['HTTP_X_ADMIN_CSRF'] ?? '');

                if (!AdminCsrf::validate($csrf)) {

                    self::respond(['success' => false, 'message' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);

                }

            }



            $controller = new FurnizoriController(
                new FurnizoriService(new FurnizoriRepository(), new FurnizoriStatsService(new FurnizoriRepository()))
            );

            $response = self::dispatch($controller, $action, $payload);

            self::respond($response);

        } catch (JsonException) {

            self::respond(['success' => false, 'message' => 'JSON invalid.'], 400);

        } catch (ValidationException $exception) {

            self::respond(['success' => false, 'message' => $exception->getMessage()], 400);

        } catch (NotFoundException $exception) {

            self::respond(['success' => false, 'message' => $exception->getMessage()], 404);

        } catch (Throwable $exception) {

            ApiBootstrap::respondInternalError('furnizori_endpoint', $exception);

        }

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */

    private static function dispatch(FurnizoriController $controller, string $action, array $payload): array

    {

        return match ($action) {

            'list' => ['success' => true, 'message' => 'Furnizori incarcati.', 'data' => $controller->list($payload)],

            'get' => ['success' => true, 'message' => 'Furnizor incarcat.', 'data' => $controller->find($payload)],

            'products' => ['success' => true, 'message' => 'Produse furnizor.', 'data' => $controller->products($payload)],

            'browseconnection', 'browse_connection' => ['success' => true, 'message' => 'Explorare conexiune.', 'data' => $controller->browse($payload)],

            'mirror_feed_files', 'mirrorfeedfiles' => self::mirrorResponse($controller, $payload),

            'add' => ['success' => true, 'message' => 'Furnizor adaugat.', 'data' => $controller->add($payload)],

            'edit' => ['success' => true, 'message' => 'Furnizor actualizat.', 'data' => $controller->update($payload)],

            'setstatus', 'block' => self::blockResponse($controller, $payload),

            'unblock', 'activate' => self::unblockResponse($controller, $payload),

            'testconnection' => ['success' => true, 'message' => 'Test conexiune executat.', 'data' => $controller->test($payload)],

            'syncnow', 'sync_now' => ['success' => true, 'message' => 'Sincronizare executata.', 'data' => $controller->sync($payload)],

            'delete' => self::deleteResponse($controller, $payload),

            'getpricelogic', 'get_price_logic' => ['success' => true, 'message' => 'Logica de pret incarcata.', 'data' => $controller->getPriceLogic()],

            'savepricelogic', 'save_price_logic' => ['success' => true, 'message' => 'Logica de pret salvata.', 'data' => $controller->savePriceLogic($payload)],

            'testpricelogic', 'test_price_logic' => ['success' => true, 'message' => 'Test logica de pret executat.', 'data' => $controller->testPriceLogic($payload)],

            default => throw new ValidationException('Actiune necunoscuta.'),

        };

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */

    private static function mirrorResponse(FurnizoriController $controller, array $payload): array

    {

        $mirror = $controller->mirrorFeedFiles($payload);

        $copied = count($mirror['copied'] ?? []);



        return [

            'success' => true,

            'message' => $copied > 0

                ? ($copied . ' fisier(e) copiate in folderul local al furnizorului.')

                : 'Niciun fisier nou de copiat (deja in folder sau lipsa din import).',

            'data' => $mirror,

        ];

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */

    private static function blockResponse(FurnizoriController $controller, array $payload): array

    {

        $controller->block($payload);



        return ['success' => true, 'message' => 'Furnizor blocat.', 'data' => null];

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */

    private static function unblockResponse(FurnizoriController $controller, array $payload): array

    {

        $controller->unblock($payload);



        return ['success' => true, 'message' => 'Furnizor activat.', 'data' => null];

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */

    private static function deleteResponse(FurnizoriController $controller, array $payload): array

    {

        $controller->delete($payload);



        return ['success' => true, 'message' => 'Furnizor sters.', 'data' => null];

    }



    /** @param array<string, mixed> $payload */

    private static function respond(array $payload, int $status = 200): void

    {

        ApiBootstrap::json($payload, $status);

    }

}

