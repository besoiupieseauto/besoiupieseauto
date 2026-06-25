<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\AdminSettingsService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new AdminSettingsService();
    $sessionUser = [
        'role' => (string) ($_SESSION['role'] ?? ''),
        'permissions' => $_SESSION['admin_permissions'] ?? null,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        ApiBootstrap::json(['success' => true, 'data' => $service->hubPayload($sessionUser)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['action'] ?? $payload['type_product'] ?? 'hub');

    if ($action === 'hub') {
        ApiBootstrap::json(['success' => true, 'data' => $service->hubPayload($sessionUser)]);
    }

    if ($action === 'save_user') {
        $data = $service->saveUser($payload, $sessionUser);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'delete_user') {
        $data = $service->deleteUser($payload, $sessionUser);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'save_token_budget') {
        $data = $service->saveTokenBudget($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'save_env_keys') {
        $data = $service->saveEnvKeys($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('settings_endpoint', $exception);
}
