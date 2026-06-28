<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Besoiu\Controllers\Alerts\AlertsService;
use Besoiu\Controllers\Cron\CronService;
use Besoiu\Controllers\Dashboard\DashboardService;
use Besoiu\Controllers\Report\ReportService;
use Besoiu\Controllers\Scan\ScanCancelledException;
use Besoiu\Controllers\Scan\ScanService;
use Besoiu\Services\AdminOpsAlertsFixService;
use Besoiu\Services\AdminOpsAlertsService;
use Besoiu\Services\ComposerRepairAgentService;
use Besoiu\Services\SectionAssistantService;
use Besoiu\Services\SectionAssistantQueryLogService;
use Besoiu\Controllers\Settings\SettingsService;
use Besoiu\Controllers\Users\UsersService;
use Besoiu\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi();

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Eroare cron sync: ' . (string) ($error['message'] ?? 'fatal'),
    ], JSON_UNESCAPED_UNICODE);
});

try {
    ApiBootstrap::requireAuthenticatedSession();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (is_array($payload) && $action === '') {
            $action = trim((string) ($payload['action'] ?? ''));
        }
    }

    if ($action === '') {
        $action = 'overview';
    }

    $destructiveActions = ['ops_alert_fix', 'scan_unlock', 'scan_stop', 'cron_reset'];
    if (in_array($action, $destructiveActions, true)) {
        ApiBootstrap::requireAdminFeature('dashboard.home', true);
    }

    $hubPage = max(1, (int) ($_GET['page'] ?? 1));
    $hubPerPage = max(1, min(100, (int) ($_GET['per_page'] ?? 10)));

    switch ($action) {
        case 'settings':
            $all = (new SettingsService())->getAllSettingss();
            $pageData = \Besoiu\Core\Pagination::envelope(
                array_slice(is_array($all) ? $all : [], ($hubPage - 1) * $hubPerPage, $hubPerPage),
                count(is_array($all) ? $all : []),
                $hubPage,
                $hubPerPage
            );
            ApiBootstrap::json(['success' => true, 'data' => $pageData['items'], 'pagination' => $pageData]);

        case 'ops_alerts':
            ApiBootstrap::json(['success' => true, 'data' => (new AdminOpsAlertsService())->summary()]);

        case 'ops_alert_fix':
            if ($method !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Doar POST.'], 405);
            }
            $fixPayload = is_array($payload ?? null) ? $payload : [];
            $code = trim((string) ($fixPayload['code'] ?? ''));
            if ($code === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește codul alertei.'], 400);
            }
            $result = (new ComposerRepairAgentService())->fixAlert($code, $fixPayload, true);
            ApiBootstrap::json(array_merge($result, [
                'ops_alerts' => (new AdminOpsAlertsService())->summary(),
            ]), ($result['success'] ?? false) ? 200 : 422);

        case 'section_assist':
            if ($method !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Doar POST.'], 405);
            }
            $assistPayload = is_array($payload ?? null) ? $payload : [];
            $assist = SectionAssistantService::assistFromPayload($assistPayload);
            ApiBootstrap::json([
                'success' => !empty($assist['ok']),
                'message' => (string) ($assist['reply'] ?? $assist['message'] ?? ''),
                'data' => $assist,
            ], !empty($assist['ok']) ? 200 : 422);

        case 'section_assist_queries':
            ApiBootstrap::json([
                'success' => true,
                'data' => (new SectionAssistantQueryLogService())->recent($hubPerPage),
                'summary' => (new SectionAssistantQueryLogService())->summary(7),
            ]);

        case 'section_assist_feedback':
            if ($method !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Doar POST.'], 405);
            }
            $fbPayload = is_array($payload ?? null) ? $payload : [];
            $queryId = trim((string) ($fbPayload['query_id'] ?? ''));
            $rating = trim((string) ($fbPayload['rating'] ?? ''));
            $note = trim((string) ($fbPayload['note'] ?? ''));
            if ($queryId === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipseste query_id.'], 400);
            }
            $saved = (new SectionAssistantQueryLogService())->setOperatorRating($queryId, $rating, $note);
            ApiBootstrap::json(['success' => $saved, 'message' => $saved ? 'Feedback salvat.' : 'Query negasit.'], $saved ? 200 : 404);

        case 'section_assist_learn':
            if ($method !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'Doar POST.'], 405);
            }
            $learnPayload = is_array($payload ?? null) ? $payload : [];
            $learn = SectionAssistantService::learnFromPayload($learnPayload);
            ApiBootstrap::json([
                'success' => !empty($learn['ok']),
                'message' => (string) ($learn['reply'] ?? $learn['message'] ?? ''),
                'data' => $learn,
            ], !empty($learn['ok']) ? 200 : 422);

        case 'alerts':
            ApiBootstrap::json(['success' => true, 'data' => (new AdminOpsAlertsService())->feed()]);

        case 'reports':
            $all = (new ReportService())->getAllReports();
            $pageData = \Besoiu\Core\Pagination::envelope(
                array_slice(is_array($all) ? $all : [], ($hubPage - 1) * $hubPerPage, $hubPerPage),
                count(is_array($all) ? $all : []),
                $hubPage,
                $hubPerPage
            );
            $overview = (new DashboardService())->overview();
            ApiBootstrap::json([
                'success' => true,
                'data' => $pageData['items'],
                'pagination' => $pageData,
                'overview' => $overview,
            ]);

        case 'scan_activity':
            $scanService = new ScanService();
            $dashProgress = new \Besoiu\Controllers\Scan\SupplierScanDashboardService();
            ApiBootstrap::json([
                'success' => true,
                'activity' => $scanService->getActivityFeed(),
                'progress' => $dashProgress->readProgress(),
                'running' => $scanService->isScanRunning(),
                'lock_max_sec' => (static function (): int {
                    require_once dirname(__DIR__, 2) . '/config/cron_import.php';

                    return admin_cron_scan_lock_max_age();
                })(),
            ]);

        case 'scan_unlock':
            $scanService = new ScanService();
            $scanService->releaseScanLock();
            ScanService::clearStopRequest();
            // Reset UI progres (altfel panoul poate rămâne “running” vizual).
            (new \Besoiu\Controllers\Scan\SupplierScanDashboardService())->updateProgress([
                'running' => false,
                'pct' => 0,
                'phase' => 'stopped',
                'phase_label' => 'Oprit',
                'message' => 'Scan deblocat de utilizator',
                'supplier' => '',
                'supplier_index' => 0,
                'supplier_total' => 0,
            ]);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Lock scan eliberat — poți porni o scanare nouă.',
                'running' => false,
            ]);

        case 'scan_stop':
            $scanService = new ScanService();
            $stop = $scanService->stopRunningScan();
            ApiBootstrap::json([
                'success' => true,
                'message' => (string) ($stop['message'] ?? 'Scan oprit.'),
                'running' => false,
                'killed' => (bool) ($stop['killed'] ?? false),
                'cancelled_jobs' => (int) ($stop['cancelled_jobs'] ?? 0),
            ]);

        case 'cron':
            require_once dirname(__DIR__, 2) . '/config/cron_tasks.php';
            $scanService = new ScanService();
            // Curăță lock-uri vechi fără a bloca request-ul
            $scanRunning = $scanService->isScanRunning();
            $scanProgress = (new \Besoiu\Controllers\Scan\SupplierScanDashboardService())->readProgress();

            $mirrorCron = (string) ($_GET['mirror'] ?? '0') !== '0';
            $analyzeCron = (string) ($_GET['analyze'] ?? '0') !== '0';
            if ($mirrorCron || $analyzeCron) {
                $scanPanel = $scanService->buildDashboard($mirrorCron, $analyzeCron);
            } else {
                $scanPanel = $scanService->loadSavedDashboard();
            }

            $cronDashboard = is_array($scanPanel['cron_dashboard'] ?? null)
                ? $scanPanel['cron_dashboard']
                : [];

            // Fără DashboardService::overview() — evită probe TecDoc lente la încărcarea paginii
            $scriptsHealth = is_array($cronDashboard['scripts_health'] ?? null)
                ? $cronDashboard['scripts_health']
                : [];

            ApiBootstrap::json([
                'success' => true,
                'data' => [],
                'pagination' => \Besoiu\Core\Pagination::envelope([], 0, 1, 10),
                'tasks' => admin_cron_tasks_registry(),
                'modules' => admin_cron_modules_registry(),
                'setup_doc' => 'admin/docs/CRON_WINDOWS_SETUP.md',
                'health' => [
                    'database_ok' => true,
                    'supplier_rclone' => !empty($scriptsHealth['supplier_rclone']),
                    'scripts' => $scriptsHealth,
                ],
                'overview' => $scanPanel['overview'] ?? ($cronDashboard['overview'] ?? []),
                'scanned_at' => $scanPanel['scanned_at'] ?? '',
                'scanned_at_label' => $scanPanel['scanned_at_label'] ?? '',
                'cron_dashboard' => $cronDashboard,
                'furnizori' => $scanPanel['suppliers'] ?? [],
                'scan_running' => $scanRunning,
                'scan_progress' => $scanProgress,
            ]);

        case 'cron_reset':
            $reset = (new ScanService())->resetForTesting();
            ApiBootstrap::json([
                'success' => true,
                'message' => (string) ($reset['message'] ?? 'Reset finalizat.'),
                'overview' => $reset['overview'] ?? [],
                'cron_dashboard' => $reset['cron_dashboard'] ?? [],
                'data' => $reset,
            ]);

        case 'scan':
        case 'scan_run':
            @ini_set('max_execution_time', '900');
            @set_time_limit(900);
            @ini_set('memory_limit', '1024M');

            $scanService = new ScanService();
            $mirror = !isset($_GET['mirror']) || (string) $_GET['mirror'] !== '0';
            $analyze = !isset($_GET['analyze']) || (string) $_GET['analyze'] !== '0';

            if ($method === 'POST' || $action === 'scan_run') {
                $payload = [];
                if ($method === 'POST') {
                    $raw = file_get_contents('php://input') ?: '';
                    $decoded = json_decode($raw, true);
                    $payload = is_array($decoded) ? $decoded : [];
                }
                if (($payload['action'] ?? '') === 'cron_reset' || ($payload['reset'] ?? false)) {
                    $reset = $scanService->resetForTesting();
                    ApiBootstrap::json([
                        'success' => true,
                        'message' => (string) ($reset['message'] ?? 'Reset finalizat.'),
                        'overview' => $reset['overview'] ?? [],
                        'cron_dashboard' => $reset['cron_dashboard'] ?? [],
                        'data' => $reset,
                    ]);
                }
                $remoteFtp = !empty($payload['remote_ftp']) || (string) ($_GET['remote_ftp'] ?? '') === '1';

                if (!$scanService->tryAcquireScanLock()) {
                    ApiBootstrap::json([
                        'success' => false,
                        'message' => 'Un scan rulează deja. Urmărește jurnalul live.',
                    ], 409);
                }

                (new \Besoiu\Controllers\Scan\SupplierScanDashboardService())->updateProgress([
                    'running' => true,
                    'pct' => 2,
                    'phase' => 'run',
                    'phase_label' => 'Pornire',
                    'message' => 'Scan pornit — pregătesc motorul…',
                    'supplier' => '',
                    'supplier_index' => 0,
                    'supplier_total' => 0,
                ]);

                ScanService::clearStopRequest();

                ApiBootstrap::flushJsonAndContinue([
                    'success' => true,
                    'async' => true,
                    'message' => 'Scan pornit — jurnalul se actualizează live (FTP + import pot dura câteva minute).',
                ]);

                try {
                    $result = $scanService->runFullSync($remoteFtp, false);
                    $scanService->saveLastRunResult($result);
                } catch (ScanCancelledException $cancelled) {
                    $dashErr = new \Besoiu\Controllers\Scan\SupplierScanDashboardService();
                    $dashErr->log('Scan oprit de utilizator.', '', 'warn', 'run');
                    $dashErr->updateProgress([
                        'running' => false,
                        'pct' => 0,
                        'phase' => 'stopped',
                        'phase_label' => 'Oprit',
                        'message' => 'Scan oprit de utilizator',
                    ]);
                    $scanService->saveLastRunResult([
                        'success' => false,
                        'message' => 'Scan oprit de utilizator.',
                        'data' => [],
                        'cancelled' => true,
                    ]);
                } catch (Throwable $runError) {
                    $dashErr = new \Besoiu\Controllers\Scan\SupplierScanDashboardService();
                    $dashErr->log(
                        'Eroare scan: ' . $runError->getMessage(),
                        '',
                        'error',
                        'run'
                    );
                    $dashErr->updateProgress([
                        'running' => false,
                        'pct' => 0,
                        'phase' => 'error',
                        'phase_label' => 'Eroare',
                        'message' => 'Eroare scan: ' . $runError->getMessage(),
                    ]);
                    $scanService->saveLastRunResult([
                        'success' => false,
                        'message' => 'Eroare scan: ' . $runError->getMessage(),
                        'data' => [],
                    ]);
                } finally {
                    $scanService->releaseScanLock();
                }
                exit;
            }

            $dashboard = $scanService->buildDashboard($mirror, $analyze);
            ApiBootstrap::json([
                'success' => true,
                'data' => $dashboard['suppliers'],
                'furnizori' => $dashboard['suppliers'],
                'overview' => $dashboard['overview'],
                'scanned_at' => $dashboard['scanned_at'],
                'scanned_at_label' => $dashboard['scanned_at_label'],
                'cron_dashboard' => $dashboard['cron_dashboard'] ?? [],
            ]);

        case 'crossref':
            $oem = [];
            $total = 0;
            try {
                $pdo = Database::getDB();
                $total = (int) $pdo->query('SELECT COUNT(*) FROM products_oem')->fetchColumn();
                $meta = \Besoiu\Core\Pagination::normalize($hubPage, $hubPerPage);
                $stmt = $pdo->prepare(
                    'SELECT o.id, o.product_id, o.oem_code, o.brand, o.is_primary, o.source, p.pName AS product_name, p.pCode AS product_code
                     FROM products_oem o
                     LEFT JOIN produse p ON p.id = o.product_id
                     ORDER BY o.id DESC LIMIT :lim OFFSET :off'
                );
                $stmt->bindValue(':lim', $meta['limit'], PDO::PARAM_INT);
                $stmt->bindValue(':off', $meta['offset'], PDO::PARAM_INT);
                $stmt->execute();
                $oem = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $oem = [];
            }
            $pageData = \Besoiu\Core\Pagination::envelope($oem, $total, $hubPage, $hubPerPage);
            ApiBootstrap::json([
                'success' => true,
                'data' => $pageData['items'],
                'oem' => $pageData['items'],
                'pagination' => $pageData,
            ]);

        case 'users':
            $all = (new UsersService())->getAllUserss();
            $pageData = \Besoiu\Core\Pagination::envelope(
                array_slice(is_array($all) ? $all : [], ($hubPage - 1) * $hubPerPage, $hubPerPage),
                count(is_array($all) ? $all : []),
                $hubPage,
                $hubPerPage
            );
            ApiBootstrap::json([
                'success' => true,
                'data' => $pageData['items'],
                'pagination' => $pageData,
            ]);

        case 'overview':
        default:
            ApiBootstrap::json([
                'success' => true,
                'data' => (new DashboardService())->overview(),
            ]);
    }
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('admin_hub_endpoint', $e);
}
