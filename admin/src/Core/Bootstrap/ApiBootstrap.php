<?php

declare(strict_types=1);

namespace Besoiu\Core\Bootstrap;

use Config\Database;
use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Exceptions\NotFoundException;
use Besoiu\Exceptions\ValidationException;
use Besoiu\Services\AiSupervisor\Phase1\AdminEventBridge;
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

    /** CSRF obligatoriu pe mutații API admin autentificate. */
    public static function requireAdminCsrfForMutation(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (empty($_SESSION['user_id'])) {
            return;
        }

        $token = trim((string) ($_SERVER['HTTP_X_ADMIN_CSRF'] ?? $_POST['csrf_token'] ?? ''));
        if (!\Besoiu\Core\Auth\AdminCsrf::validate($token)) {
            self::json(['success' => false, 'message' => 'Token CSRF invalid. Reîncarcă pagina admin.'], 403);
        }
    }

    /**
     * Verifică permisiunea granulară admin (feature key din AdminPermissionCatalog).
     */
    public static function requireAdminFeature(string $featureKey, bool $destructive = false): void
    {
        self::requireAuthenticatedSession();

        $role = strtolower(trim((string) ($_SESSION['role'] ?? 'guest')));

        if ($destructive && !in_array($role, ['super_ambassador', 'manager'], true)) {
            self::json([
                'success' => false,
                'message' => 'Acțiune restricționată — necesită rol manager sau super ambassador.',
            ], 403);
        }

        if (!\Besoiu\Core\Auth\AdminPermissionCatalog::featureAllowed($featureKey, $role)) {
            self::json([
                'success' => false,
                'message' => 'Acces interzis — lipsește permisiunea: ' . $featureKey,
            ], 403);
        }
    }

    /**
     * Auth implicit pentru toate scripturile admin/public/api/* (except allowlist).
     */
    public static function enforceDefaultApiAuthIfNeeded(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($script === '' || $script === '_autoload.php') {
            return;
        }

        $allowlistPath = self::projectRoot() . '/config/api_public_allowlist.php';
        if (!is_file($allowlistPath)) {
            self::requireAuthenticatedSession();

            return;
        }

        /** @var list<string> $allowlist */
        $allowlist = require $allowlistPath;
        if (in_array($script, $allowlist, true)) {
            return;
        }

        self::requireAuthenticatedSession();
        self::enforceApiWorkspaceIfNeeded();
        self::requireAdminCsrfForMutation();
    }

    /**
     * Izolare departamente pe API (#45) — user din social nu poate apela import/furnizori etc.
     */
    public static function enforceApiWorkspaceIfNeeded(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        self::ensureSession();

        if (empty($_SESSION['user_id'])) {
            return;
        }

        $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($script === '' || $script === '_autoload.php') {
            return;
        }

        $mapPath = self::projectRoot() . '/config/api_workspace_features.php';
        if (!is_file($mapPath)) {
            return;
        }

        /** @var array<string, string> $map */
        $map = require $mapPath;
        $featureKey = $map[$script] ?? '';
        if ($featureKey === '') {
            if (!in_array($role, ['super_ambassador', 'manager'], true)) {
                self::json([
                    'success' => false,
                    'message' => 'API neconfigurat — acces interzis.',
                ], 403);
            }

            return;
        }

        $role = strtolower(trim((string) ($_SESSION['role'] ?? 'guest')));

        if ($role !== 'super_ambassador' && !AdminPermissionCatalog::featureAllowed($featureKey, $role)) {
            self::json([
                'success' => false,
                'message' => 'Acces interzis — lipsește permisiunea: ' . $featureKey,
            ], 403);
        }

        if ($role === 'super_ambassador' || AdminWorkspace::isCrossWorkspaceFeature($featureKey)) {
            return;
        }

        $current = AdminWorkspace::getCurrent();
        if ($current === null) {
            $allowed = AdminWorkspace::allowedWorkspacesForSession();
            if (count($allowed) === 1) {
                AdminWorkspace::setCurrent($allowed[0]);
                $current = $allowed[0];
            } else {
                self::json([
                    'success' => false,
                    'message' => 'Selectează departamentul activ înainte de a apela acest API.',
                ], 403);
            }
        }

        if (!AdminWorkspace::featureAllowedInWorkspace($featureKey, (string) $current)) {
            self::json([
                'success' => false,
                'message' => 'API indisponibil în departamentul activ.',
                'workspace' => $current,
                'feature' => $featureKey,
            ], 403);
        }
    }

    /**
     * Înregistrare eveniment AI supervisor (#49) — import/scraper/bots/marketplace.
     *
     * @param array<string, mixed> $payload
     */
    public static function recordAdminEvent(
        array $payload,
        int $statusCode,
        string $module,
        string $actionHint = '',
        bool $forceError = false
    ): void {
        if (!class_exists(AdminEventBridge::class)) {
            return;
        }

        $bridge = new AdminEventBridge();
        if (!$forceError && $statusCode >= 200 && $statusCode < 300 && !empty($payload['success'])) {
            $bridge->recordSuccess($module, $payload, $statusCode, $actionHint);
        } else {
            $bridge->recordFailure(
                $module,
                $payload,
                $statusCode,
                $actionHint !== '' ? $actionHint : 'api_error',
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function jsonAndRecordEvent(
        array $payload,
        int $statusCode,
        string $module,
        string $actionHint = ''
    ): void {
        self::recordAdminEvent($payload, $statusCode, $module, $actionHint);
        self::json($payload, $statusCode);
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
        bool $allowQueryFallback = false
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
        bool $requireAuth = true,
        ?string $eventModule = null
    ): void {
        self::bootJsonApi($withDatabase);

        if ($requireAuth) {
            self::requireAuthenticatedSession();
        } elseif (!empty($_SESSION['user_id'])) {
            self::enforceApiWorkspaceIfNeeded();
        }

        try {
            $handler();
        } catch (JsonException $exception) {
            if ($eventModule !== null) {
                self::recordAdminEvent(
                    ['success' => false, 'message' => 'JSON invalid.'],
                    400,
                    $eventModule,
                    'json_error',
                    true
                );
            }
            self::json(['success' => false, 'message' => 'JSON invalid.'], 400);
        } catch (ValidationException $exception) {
            if ($eventModule !== null) {
                self::recordAdminEvent(
                    ['success' => false, 'message' => $exception->getMessage()],
                    400,
                    $eventModule,
                    'validation_error',
                    true
                );
            }
            self::json(['success' => false, 'message' => $exception->getMessage()], 400);
        } catch (NotFoundException $exception) {
            if ($eventModule !== null) {
                self::recordAdminEvent(
                    ['success' => false, 'message' => $exception->getMessage()],
                    404,
                    $eventModule,
                    'not_found',
                    true
                );
            }
            self::json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            self::logError($logContext, $exception);
            if ($eventModule !== null) {
                self::recordAdminEvent(
                    ['success' => false, 'message' => $exception->getMessage()],
                    500,
                    $eventModule,
                    'internal_error',
                    true
                );
            }
            self::json(['success' => false, 'message' => self::internalErrorMessage($exception)], 500);
        }
    }
}
