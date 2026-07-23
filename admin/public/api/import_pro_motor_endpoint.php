<?php
declare(strict_types=1);

/**
 * Proxy autentificat → app/Import/MatchingPro/api/*.php
 * Evită timeout-uri / HTML Cloudflare pe /admin/public/import-pro/api/ direct.
 */
require_once __DIR__ . '/_autoload.php';

$importBootstrap = (defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3)) . '/app/Import/bootstrap.php';
if (!is_file($importBootstrap)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Bootstrap Import lipsă (app/Import/bootstrap.php).'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $importBootstrap;

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Import\Support\ImportPathResolver;
use Throwable;

ApiBootstrap::bootJsonApi(true);
ApiBootstrap::registerJsonFatalGuard('import_pro_motor_endpoint');

try {
    if (!ModuleGate::enabled('import_pro')) {
        ApiBootstrap::json(['success' => false, 'error' => 'Modulul Import Pro este dezactivat.'], 403);
    }

    ApiBootstrap::requireAuthenticatedSession();

    $script = basename((string) ($_GET['script'] ?? ''));
    if ($script === '' || !preg_match('/^[a-z0-9\-_]+\.php$/i', $script)) {
        ApiBootstrap::json(['success' => false, 'error' => 'Script API invalid'], 400);
    }

    $veryLongRunning = in_array($script, [
        'showcase-scan.php',
        'build-stored.php',
        'scan-stored.php',
        'stage-cards.php',
    ], true);
    $longRunning = $veryLongRunning || in_array($script, [
        'scan.php',
        'cron-start.php',
        'enrich-tecdoc-cards.php',
        'inspect-file.php',
    ], true);

    ApiBootstrap::beginBoundedJsonWork($veryLongRunning ? 600 : ($longRunning ? 300 : 120));

    if (!defined('BESOIU_IMPORT_WEB_BASE')) {
        define('BESOIU_IMPORT_WEB_BASE', ImportPathResolver::matchingProPublicUrl());
    }
    if (!defined('BESOIU_API_MANUAL_CALL')) {
        define('BESOIU_API_MANUAL_CALL', true);
    }
    ImportPathResolver::applyEnv();

    $target = ImportPathResolver::matchingProRoot() . '/api/' . $script;
    if (!is_file($target)) {
        ApiBootstrap::json(['success' => false, 'error' => 'Endpoint inexistent: ' . $script], 404);
    }

    require $target;

    ApiBootstrap::json(['success' => false, 'error' => 'Script fără răspuns: ' . $script], 500);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('import_pro_motor_endpoint', $e);
}
