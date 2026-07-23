<?php

declare(strict_types=1);

namespace Besoiu\Core\Crud;

/**
 * Dispatcher JSON pentru module CRUD moderne (Controller::add/update/delete).
 */
final class ModernCrudDispatcher
{
    public static function handle(string $moduleKey): void
    {
        self::prepareJsonResponse();

        $input = file_get_contents('php://input') ?: '';
        $data = json_decode($input, true);
        if (!is_array($data)) {
            $data = $_POST ?: [];
        }

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                throw new \Exception('Metoda permisă este POST.');
            }

            $action = $data['type_product'] ?? null;
            if (!$action) {
                throw new \Exception('Lipsește tipul acțiunii (type_product).');
            }

            $meta = CrudModuleFactory::modernModuleMeta($moduleKey);
            $meta['_module_key'] = $moduleKey;
            $controller = CrudModuleFactory::createModernController($moduleKey);
            $response = self::dispatch($controller, (string) $action, $data, $meta);

            self::recordActionEvent($moduleKey, (string) $action, $data);

            self::emitJson($response);
        } catch (\Throwable $exception) {
            if ($exception instanceof \Besoiu\Exceptions\ValidationException) {
                self::recordActionError($moduleKey, (string) ($data['type_product'] ?? 'validation'), $data, 400, $exception->getMessage());
                self::emitJson(['success' => false, 'message' => $exception->getMessage()], 400);
            }

            self::recordActionError(
                $moduleKey,
                (string) ($data['type_product'] ?? 'error'),
                $data,
                500,
                $exception->getMessage()
            );

            if (class_exists(\Besoiu\Core\Bootstrap\ApiBootstrap::class)) {
                \Besoiu\Core\Bootstrap\ApiBootstrap::logError('ModernCrudDispatcher:' . $moduleKey, $exception);
            }

            $message = class_exists(\Besoiu\Core\Bootstrap\ApiBootstrap::class)
                ? \Besoiu\Core\Bootstrap\ApiBootstrap::internalErrorMessage($exception, 'Eroare internă.')
                : 'Eroare internă.';

            self::emitJson(['success' => false, 'message' => $message], 500);
        }
    }

    /** @param array<string, mixed> $data */
    private static function recordActionEvent(string $moduleKey, string $action, array $data): void
    {
        try {
            if (class_exists(\Besoiu\Services\AiSupervisor\Phase1\AdminEventBridge::class)) {
                (new \Besoiu\Services\AiSupervisor\Phase1\AdminEventBridge())->recordSuccess(
                    $moduleKey,
                    array_merge($data, ['success' => true]),
                    200,
                    $action
                );

                return;
            }
            if (!class_exists(\Besoiu\Services\AiActionEventService::class)) {
                return;
            }
            $actorId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
            $subject = '';
            if (isset($data['id']) && $data['id'] !== '') {
                $subject = 'id=' . (string) $data['id'];
            } elseif (isset($data['name']) && is_string($data['name'])) {
                $subject = mb_substr($data['name'], 0, 120);
            }
            (new \Besoiu\Services\AiActionEventService())->record(
                'admin',
                $action,
                $subject,
                [
                    'module' => $moduleKey,
                    'route' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                ],
                $actorId
            );
        } catch (\Throwable) {
            // non-blocking
        }
    }

    /** @param array<string, mixed> $data */
    private static function recordActionError(string $moduleKey, string $action, array $data, int $status, string $message): void
    {
        try {
            $payload = array_merge($data, ['success' => false, 'message' => $message]);
            if (class_exists(\Besoiu\Services\AiSupervisor\Phase1\AdminEventBridge::class)) {
                (new \Besoiu\Services\AiSupervisor\Phase1\AdminEventBridge())->recordFailure(
                    $moduleKey,
                    $payload,
                    $status,
                    'admin_error'
                );

                return;
            }
            if (class_exists(\Besoiu\Services\AiActionEventService::class)) {
                (new \Besoiu\Services\AiActionEventService())->record(
                    'admin',
                    'admin_error',
                    $moduleKey . ':' . $action,
                    ['module' => $moduleKey, 'message' => $message, 'error' => true],
                    isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null
                );
            }
        } catch (\Throwable) {
            // non-blocking
        }
    }

    /** Pregătire răspuns JSON curat — fără warning PHP în body (Regula de Aur). */
    private static function prepareJsonResponse(): void
    {
        if (class_exists(\Besoiu\Core\Bootstrap\ApiBootstrap::class)) {
            \Besoiu\Core\Bootstrap\ApiBootstrap::registerProductionSafeErrors();
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function emitJson(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string, mixed> $data @param array{label: string, session_key: string, mode?: string, actions?: array<string, string>} $meta @return array<string, mixed> */
    private static function dispatch(object $controller, string $action, array $data, array $meta): array
    {
        $mode = (string) ($meta['mode'] ?? 'standard');
        if ($mode === 'extended') {
            return self::dispatchExtended($controller, $action, $data, $meta);
        }

        switch ($action) {
            case 'add':
                return [
                    'success' => true,
                    'message' => $meta['label'] . ' adăugat.',
                    'data' => $controller->add($data),
                ];

            case 'edit':
                if (!method_exists($controller, 'update')) {
                    throw new \Exception('Editare neimplementată pentru acest modul.');
                }

                return [
                    'success' => true,
                    'message' => $meta['label'] . ' editat.',
                    'data' => $controller->update($data),
                ];

            case 'delete':
                if (!method_exists($controller, 'delete')) {
                    throw new \Exception('Ștergere neimplementată pentru acest modul.');
                }

                $controller->delete($data);

                return [
                    'success' => true,
                    'message' => $meta['label'] . ' șters.',
                    'data' => null,
                ];

            case 'activate':
                if (!isset($data['id']) || $data['id'] === '' || $data['id'] === null) {
                    throw new \Exception('ID lipsă pentru activare.');
                }

                $_SESSION[$meta['session_key']] = $data['id'];

                return [
                    'success' => true,
                    'message' => $meta['label'] . ' activat în sesiune.',
                    'data' => $_SESSION[$meta['session_key']],
                ];

            case 'setstatus':
                if (method_exists($controller, 'changeStatus')) {
                    $controller->changeStatus($data);
                }

                return [
                    'success' => true,
                    'message' => 'Status actualizat.',
                    'data' => '',
                ];

            default:
                throw new \Exception('Acțiune necunoscută: ' . $action);
        }
    }

    /**
     * Module ex-Legacy: hartă acțiuni → metode; răspunsul controllerului e deja {success, message, …}.
     *
     * @param array<string, mixed> $data
     * @param array{label: string, session_key: string, mode?: string, actions?: array<string, string>} $meta
     * @return array<string, mixed>
     */
    private static function dispatchExtended(object $controller, string $action, array $data, array $meta): array
    {
        $moduleKey = self::resolveModuleKeyFromMeta($meta);
        $actions = is_array($meta['actions'] ?? null) ? $meta['actions'] : [];
        if (!isset($actions[$action])) {
            throw new \Exception('Acțiune necunoscută: ' . $action);
        }

        $method = (string) $actions[$action];
        if ($method === '__extended__') {
            $special = CrudModuleFactory::handleExtendedAction($moduleKey, $action, $data);
            if (!is_array($special)) {
                throw new \Exception('Acțiune extended neimplementată: ' . $action);
            }

            return self::normalizeControllerResponse($special, $meta['label']);
        }

        if (!method_exists($controller, $method)) {
            throw new \Exception('Metoda ' . $method . ' lipsește pe controller.');
        }

        $ref = new \ReflectionMethod($controller, $method);
        $result = $ref->getNumberOfParameters() === 0
            ? $controller->{$method}()
            : $controller->{$method}($data);
        if (!is_array($result)) {
            return [
                'success' => true,
                'message' => $meta['label'] . ' OK.',
                'data' => $result,
            ];
        }

        return self::normalizeControllerResponse($result, $meta['label']);
    }

    /** @param array{label: string, session_key: string} $meta */
    private static function resolveModuleKeyFromMeta(array $meta): string
    {
        // handle() are moduleKey — îl stocăm pe meta la apel
        return (string) ($meta['_module_key'] ?? '');
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private static function normalizeControllerResponse(array $result, string $label): array
    {
        if (array_key_exists('success', $result)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $label . ' OK.',
            'data' => $result,
        ];
    }
}
