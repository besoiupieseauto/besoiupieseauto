<?php

declare(strict_types=1);

namespace Besoiu\Core\Crud;

/**
 * Bootstrap JSON pentru crudu.php legacy (Blog, Website, Categorii, Adaos, Produse).
 */
final class LegacyJsonCrud
{
    public static function prepare(): void
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

    /** @return array<string, mixed> */
    public static function readInput(): array
    {
        $input = file_get_contents('php://input') ?: '';
        $data = json_decode($input, true);

        return is_array($data) ? $data : ($_POST ?: []);
    }

    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::emit(['success' => false, 'message' => 'Metoda permisă este POST.']);
        }
    }

    /** @param array<string, mixed> $payload */
    public static function emit(array $payload, int $statusCode = 200, string $module = 'crud'): void
    {
        $hook = dirname(__DIR__, 4) . '/system/ai_admin_hook.php';
        if (is_file($hook)) {
            require_once $hook;
            if (function_exists('ai_admin_hook_json')) {
                if ($module === 'crud' && function_exists('ai_admin_hook_infer_module')) {
                    $module = ai_admin_hook_infer_module();
                }
                ai_admin_hook_json($module, $payload, $statusCode);
            }
        }

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

    public static function emitSuccess(array $data = [], string $message = 'OK'): void
    {
        self::emit(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function emitThrowable(\Throwable $exception): void
    {
        self::emit([
            'success' => false,
            'message' => 'Eroare: ' . $exception->getMessage(),
        ], 500);
    }
}
