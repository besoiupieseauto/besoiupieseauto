<?php
declare(strict_types=1);

namespace Besoiu\Modules\CoadaImport\Handler;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Services\Import\ImportLibLoader;
use Throwable;

/**
 * Proxy acțiuni coadă import (importreview) — același flux ca proiectul vechi.
 */
final class CoadaImportQueueApiHandler
{
    public static function handle(): void
    {
        ApiBootstrap::bootJsonApi(true);
        ApiBootstrap::registerJsonFatalGuard('import_action_endpoint');

        try {
            if (!ModuleGate::enabled('coada_import')) {
                ApiBootstrap::json([
                    'success' => false,
                    'message' => 'Modulul coada_import este dezactivat.',
                ], 403);
            }

            ApiBootstrap::requireAuthenticatedSession();
            ApiBootstrap::beginBoundedJsonWork(300);
            ApiBootstrap::releaseSession();

            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
                http_response_code(204);
                exit;
            }

            ImportHooks::ensureHooks();

            // Încarcă librăria fără auto-dispatch (require_once poate fi deja făcut).
            if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
                define('IMPORT_ACTION_SKIP_HTTP', true);
            }
            ImportLibLoader::bootForQueueActions();

            if (!function_exists('import_dispatch_queue_http_action')) {
                ApiBootstrap::json([
                    'success' => false,
                    'message' => 'Dispatcher acțiuni import indisponibil.',
                ], 500);
            }

            $input = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($input)) {
                $input = $_POST;
            }
            // Dispatch explicit — repară butoanele când fișierul era deja încărcat.
            import_dispatch_queue_http_action($input);
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('import_action_endpoint', $e);
        }
    }
}
