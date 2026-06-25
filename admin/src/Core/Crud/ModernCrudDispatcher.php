<?php

declare(strict_types=1);

namespace Evasystem\Core\Crud;

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
            $controller = CrudModuleFactory::createModernController($moduleKey);
            $response = self::dispatch($controller, (string) $action, $data, $meta);

            self::emitJson($response);
        } catch (\Throwable $exception) {
            if ($exception instanceof \Evasystem\Exceptions\ValidationException) {
                self::emitJson(['success' => false, 'message' => $exception->getMessage()], 400);
            }

            if (class_exists(\Evasystem\Core\Bootstrap\ApiBootstrap::class)) {
                \Evasystem\Core\Bootstrap\ApiBootstrap::logError('ModernCrudDispatcher:' . $moduleKey, $exception);
            }

            $message = class_exists(\Evasystem\Core\Bootstrap\ApiBootstrap::class)
                ? \Evasystem\Core\Bootstrap\ApiBootstrap::internalErrorMessage($exception, 'Eroare internă.')
                : 'Eroare internă.';

            self::emitJson(['success' => false, 'message' => $message], 500);
        }
    }

    /** Pregătire răspuns JSON curat — fără warning PHP în body (Regula de Aur). */
    private static function prepareJsonResponse(): void
    {
        if (class_exists(\Evasystem\Core\Bootstrap\ApiBootstrap::class)) {
            \Evasystem\Core\Bootstrap\ApiBootstrap::registerProductionSafeErrors();
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

    /** @param array<string, mixed> $data @param array{label: string, session_key: string} $meta @return array<string, mixed> */
    private static function dispatch(object $controller, string $action, array $data, array $meta): array
    {
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
}
