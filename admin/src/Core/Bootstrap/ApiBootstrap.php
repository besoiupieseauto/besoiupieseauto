<?php

declare(strict_types=1);

namespace Evasystem\Core\Bootstrap;

use Config\Database;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;
use JsonException;
use Throwable;

/**
 * Bootstrap comun pentru endpoint-urile JSON din public/api/.
 */
final class ApiBootstrap
{
    private static bool $booted = false;

    /** @var array<string, mixed> */
    private static array $config = [];

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function registerProductionSafeErrors(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }

    /** @return array<string, mixed> */
    public static function boot(bool $withDatabase = false): array
    {
        if (!self::$booted) {
            self::registerProductionSafeErrors();
            require_once self::projectRoot() . '/vendor/autoload.php';

            $dotenv = \Dotenv\Dotenv::createImmutable(self::projectRoot());
            $dotenv->safeLoad();

            self::$config = require self::projectRoot() . '/config/config.php';
            self::$booted = true;

            $previewGate = dirname(self::projectRoot()) . '/system/preview-gate.php';
            if (is_file($previewGate)) {
                require_once $previewGate;
                self::ensureSession();
                besoiu_preview_gate_enforce('json');
            }
        }

        if ($withDatabase) {
            Database::getInstance(
                (string) self::$config['db_host'],
                (string) self::$config['db_name'],
                (string) self::$config['db_user'],
                (string) self::$config['db_pass']
            );
        }

        return self::$config;
    }

    /** @return array<string, mixed> */
    public static function bootJsonApi(bool $withDatabase = true): array
    {
        self::registerProductionSafeErrors();

        if (ob_get_level() === 0) {
            ob_start();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        return self::boot($withDatabase);
    }

    public static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function requireAuthenticatedSession(): void
    {
        self::ensureSession();

        if (empty($_SESSION['user_id'])) {
            self::json(['success' => false, 'message' => 'Autentificare necesară.'], 401);
        }
    }

    /** Eliberează lock-ul de sesiune — necesar la job-uri AJAX lungi (scan imagini import). */
    public static function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /** @param string ...$allowedMethods */
    public static function requireHttpMethod(string ...$allowedMethods): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $allowed = array_map('strtoupper', $allowedMethods);

        if (!in_array($method, $allowed, true)) {
            self::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
        }
    }

    /** @return array<string, mixed> */
    public static function readJsonPayload(bool $allowPostFallback = true): array
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $payload = json_decode($rawBody, true);

        if (is_array($payload)) {
            return $payload;
        }

        if ($allowPostFallback && $_POST !== []) {
            return $_POST;
        }

        throw new JsonException('JSON invalid.');
    }

    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $statusCode = 200): void
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

    /**
     * Trimite JSON imediat și continuă execuția PHP (scan lung în fundal).
     *
     * @param array<string, mixed> $payload
     */
    public static function flushJsonAndContinue(array $payload, int $statusCode = 200): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Connection: close');
            header('Content-Length: ' . strlen($body));
        }

        echo $body;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            @flush();
        }
    }

    public static function logError(string $context, Throwable $exception): void
    {
        error_log('[' . $context . '] ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
    }

    public static function isDevelopment(): bool
    {
        $env = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')));

        return in_array($env, ['development', 'local', 'dev'], true);
    }

    public static function internalErrorMessage(Throwable $exception, string $fallback = 'Eroare internă.'): string
    {
        if (self::isDevelopment()) {
            return $fallback . ' ' . $exception->getMessage();
        }

        return $fallback;
    }

    public static function respondInternalError(
        string $context,
        Throwable $exception,
        int $statusCode = 500,
        string $fallback = 'Eroare internă.'
    ): void {
        self::logError($context, $exception);
        self::json(['success' => false, 'message' => self::internalErrorMessage($exception, $fallback)], $statusCode);
    }

    /**
     * Auth pentru cron/agent — preferă header, fallback opțional ?key= (deprecated, compat cron wget).
     */
    public static function requireSharedSecret(
        string $envKey,
        string $headerName = 'X-Cron-Key',
        bool $allowQueryFallback = true
    ): void {
        $expected = trim((string) ($_ENV[$envKey] ?? getenv($envKey) ?: ''));
        if ($expected === '') {
            self::json(['success' => false, 'message' => 'Serviciu neconfigurat.'], 503);
        }

        $headerServerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
        $provided = trim((string) ($_SERVER[$headerServerKey] ?? ''));

        if ($provided === '' && $allowQueryFallback) {
            $provided = trim((string) ($_GET['key'] ?? ''));
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            self::json(['success' => false, 'message' => 'Forbidden'], 403);
        }
    }

    /**
     * CORS allowlist din .env — CORS_ALLOWED_ORIGINS (virgulă) sau * pentru dev.
     */
    public static function sendCorsHeaders(string $methods = 'GET, POST, OPTIONS', string $headers = 'Content-Type, Authorization'): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $configured = trim((string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? getenv('CORS_ALLOWED_ORIGINS') ?: ''));

        if ($configured === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($configured !== '' && $origin !== '') {
            $allowed = array_map('trim', explode(',', $configured));
            if (in_array($origin, $allowed, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: ' . $methods);
        header('Access-Control-Allow-Headers: ' . $headers);
    }

    /**
     * Wrapper standard pentru endpoint-uri POST JSON cu erori tipizate.
     *
     * @param callable(): void $handler
     */
    public static function runJsonEndpoint(
        callable $handler,
        string $logContext,
        bool $withDatabase = true,
        bool $requireAuth = true
    ): void {
        self::bootJsonApi($withDatabase);

        if ($requireAuth) {
            self::requireAuthenticatedSession();
        }

        try {
            $handler();
        } catch (JsonException $exception) {
            self::json(['success' => false, 'message' => 'JSON invalid.'], 400);
        } catch (ValidationException $exception) {
            self::json(['success' => false, 'message' => $exception->getMessage()], 400);
        } catch (NotFoundException $exception) {
            self::json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            self::logError($logContext, $exception);
            self::json(['success' => false, 'message' => self::internalErrorMessage($exception)], 500);
        }
    }
}
