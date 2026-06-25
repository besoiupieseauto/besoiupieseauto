<?php
declare(strict_types=1);

use Evasystem\Controllers\Users\Users;
use Evasystem\Controllers\Users\UsersService;
use Evasystem\Core\Auth\SessionAuth;
use Evasystem\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

function json_response(array $payload, int $status = 200): void {
    LegacyJsonCrud::emit($payload, $status);
}

try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(['success' => false, 'message' => 'Metoda permisă este POST.'], 405);
    }

    // Citește JSON brut sau fallback pe POST (în caz că vine form-urlencoded)
    $raw  = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST ?: [];

    $type = $data['type_product'] ?? $data['type'] ?? null;
    if (!$type) {
        json_response(['success' => false, 'message' => 'Lipsește tipul acțiunii (type_product).'], 400);
    }

    // —— aici adunăm rezultatul, iar la final facem un singur echo
    $payload = null;
    $status  = 200;

    switch ($type) {
        case 'add': {
            $service    = new UsersService();
            $controller = new Users($service);

            $result = $controller->addProfileInfo($data);
            if (!is_array($result)) {
                $payload = ['success' => false, 'message' => 'Răspuns invalid de la controller.'];
                $status  = 500;
            } else {
                // propagăm exact succes/eroare din controller
                $payload = $result;
                $status  = !empty($result['success']) ? 200 : 422; // validare eșuată => 422
            }
            break;
        }
        case 'logout': {
            SessionAuth::logout();
            $payload = ['success' => true, 'message' => 'Deconectat.', 'redirect' => '/admin/login'];
            $status  = 200;
            break;
        }

        case 'login': {
           $service    = new UsersService();
            $controller = new Users($service);

            $result_login = $controller->login($data);
            if (!is_array($result_login)) {
                $payload = ['success' => false, 'message' => 'Răspuns invalid de la controller.'];
                $status  = 500;
            } else {
                // propagăm exact succes/eroare din controller
                $payload = $result_login;
                $status  = !empty($result_login['success']) ? 200 : 422; // validare eșuată => 422
            }
            break;

        }

        case 'edit': {
            $service    = new UsersService();
            $controller = new Users($service);

            // dacă metoda nu returnează array, considerăm succes dacă nu a aruncat
            $res     = $controller->editStatus($data);
            $payload = is_array($res) ? $res : ['success' => true, 'message' => 'Users editat.'];
            $status  = !empty($payload['success']) ? 200 : 422;
            break;
        }

        case 'delete': {
            if (empty($data['id'])) {
                $payload = ['success' => false, 'message' => 'ID lipsă pentru ștergere.'];
                $status  = 400;
                break;
            }
            $service = new UsersService();
            $res     = $service->deleteUsers((int)$data['id']);

            // normalizează răspunsul venind din model
            $success = is_array($res) ? (bool)($res['success'] ?? true) : true;
            $msg     = is_array($res) ? ($res['message'] ?? 'Users șters.') : 'Users șters.';

            $payload = ['success' => $success, 'message' => $msg, 'data' => $res];
            $status  = $success ? 200 : 400;
            break;
        }

        case 'activate': {
            if (empty($data['id'])) {
                $payload = ['success' => false, 'message' => 'ID lipsă pentru activare.'];
                $status  = 400;
                break;
            }
            $_SESSION['users'] = (int)$data['id'];
            $payload = [
                'success' => true,
                'message' => 'Users activat în sesiune.',
                'data'    => $_SESSION['users']
            ];
            $status = 200;
            break;
        }

        case 'setstatus': {
            $payload = [
                'success' => true,
                'message' => 'Status actualizat.',
                'data'    => null
            ];
            $status = 200;
            break;
        }

        default: {
            $payload = ['success' => false, 'message' => "Acțiune necunoscută: {$type}"];
            $status  = 400;
            break;
        }
    }

    // un singur echo aici:
    json_response($payload ?? ['success' => false, 'message' => 'Fără payload generat.'], $status);

} catch (\Throwable $e) {
    json_response(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
}
