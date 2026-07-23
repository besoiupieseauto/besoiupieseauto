<?php
declare(strict_types=1);

namespace Besoiu\Modules\Users\Handler;

use Besoiu\Core\Auth\AdminCsrf;
use Besoiu\Core\Auth\SessionAuth;
use Besoiu\Core\Crud\LegacyJsonCrud;
use Besoiu\Modules\Users\Controller\UsersController;
use Besoiu\Modules\Users\Service\UsersService;

/**
 * Handler JSON pentru /admin/addusersadd — login, logout, CRUD utilizatori.
 */
final class CruduUsersHandler
{
    public static function handle(): void
    {
        LegacyJsonCrud::prepare();

        try {
            self::ensureAdminSession();

            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                self::respond(['success' => false, 'message' => 'Metoda permisă este POST.'], 405);
            }

            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $data = $_POST ?: [];
            }

            $type = $data['type_product'] ?? $data['type'] ?? null;
            if (!$type) {
                self::respond(['success' => false, 'message' => 'Lipsește tipul acțiunii (type_product).'], 400);
            }

            $publicActions = ['login', 'logout'];
            if (!in_array((string) $type, $publicActions, true)) {
                if (empty($_SESSION['user_id'])) {
                    self::respond(['success' => false, 'message' => 'Autentificare necesară.'], 401);
                }
                $csrf = (string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_ADMIN_CSRF'] ?? '');
                if (!AdminCsrf::validate($csrf)) {
                    self::respond(['success' => false, 'message' => 'Token CSRF invalid. Reîncarcă pagina.'], 403);
                }
            }

            $service = new UsersService();
            $controller = new UsersController($service);
            $payload = null;
            $status = 200;

            switch ($type) {
                case 'add':
                    $payload = $controller->addProfileInfo($data);
                    $status = !empty($payload['success']) ? 200 : 422;
                    break;

                case 'logout':
                    SessionAuth::logout();
                    $payload = ['success' => true, 'message' => 'Deconectat.', 'redirect' => '/admin/login'];
                    break;

                case 'login':
                    $payload = $controller->login($data);
                    $status = !empty($payload['success']) ? 200 : 422;
                    break;

                case 'edit':
                    $controller->editStatus($data);
                    $payload = ['success' => true, 'message' => 'Users editat.'];
                    break;

                case 'delete':
                    if (empty($data['id'])) {
                        self::respond(['success' => false, 'message' => 'ID lipsă pentru ștergere.'], 400);
                    }
                    $payload = $service->deleteUser((int) $data['id']);
                    $status = !empty($payload['success']) ? 200 : 400;
                    break;

                case 'activate':
                    if (empty($data['id'])) {
                        self::respond(['success' => false, 'message' => 'ID lipsă pentru activare.'], 400);
                    }
                    $_SESSION['users'] = (int) $data['id'];
                    $payload = [
                        'success' => true,
                        'message' => 'Users activat în sesiune.',
                        'data' => $_SESSION['users'],
                    ];
                    break;

                case 'setstatus':
                    $payload = ['success' => true, 'message' => 'Status actualizat.', 'data' => null];
                    break;

                default:
                    self::respond(['success' => false, 'message' => "Acțiune necunoscută: {$type}"], 400);
            }

            self::respond($payload ?? ['success' => false, 'message' => 'Fără payload generat.'], $status);
        } catch (\Throwable $e) {
            error_log('[CruduUsersHandler] ' . $e->getMessage());
            $message = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Eroare: ' . $e->getMessage()
                : 'Eroare internă.';
            self::respond(['success' => false, 'message' => $message], 500);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function respond(array $payload, int $status = 200): void
    {
        LegacyJsonCrud::emit($payload, $status);
    }

    private static function ensureAdminSession(): void
    {
        $root = defined('BESOIU_ROOT')
            ? (string) BESOIU_ROOT
            : dirname(__DIR__, 4);
        $bridge = $root . '/app/Legacy/session-bridge.php';
        if (is_file($bridge)) {
            require_once $bridge;
            besoiu_admin_session_start();

            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('PHPSESSID');
            session_start();
        }
    }
}
