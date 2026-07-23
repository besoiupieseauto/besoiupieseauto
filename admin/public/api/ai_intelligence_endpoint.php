<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\AiIntelligence\AiIntelligenceDashboardService;
use Besoiu\Services\AiIntelligence\AiOrchestrator;
use Besoiu\Services\AiIntelligence\AiTaskRegistry;
use Besoiu\Services\AiIntelligence\EventAggregateService;
use Besoiu\Services\AiIntelligence\EventTrackingStore;
use Besoiu\Services\AiIntelligence\IntelligenceSearchService;
use Besoiu\Services\AiIntelligence\ProductIndexService;
use Besoiu\Services\AiIntelligence\VisitorAnalyticsService;
use Besoiu\Services\AiIntelligence\VisitorSessionEnricherService;

ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('ai_intelligence_endpoint');

try {
    ApiBootstrap::requireAuthenticatedSession();

    $siteRoot = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3);
    $orchestrator = AiOrchestrator::create($siteRoot);
    $dashboard = new AiIntelligenceDashboardService($siteRoot);
    $visitors = new VisitorAnalyticsService(new EventTrackingStore(null, $siteRoot), null, $siteRoot);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ApiBootstrap::releaseSession();
        $action = (string) ($_GET['action'] ?? 'status');

        if ($action === 'dashboard') {
            ApiBootstrap::json(['success' => true, 'data' => $dashboard->snapshot()]);
        }
        if ($action === 'sessions_list') {
            ApiBootstrap::json(['success' => true, 'data' => $visitors->listSessions([
                'days' => (int) ($_GET['days'] ?? 7),
                'page' => (int) ($_GET['page'] ?? 1),
                'limit' => (int) ($_GET['limit'] ?? 30),
                'country' => (string) ($_GET['country'] ?? ''),
                'ip' => (string) ($_GET['ip'] ?? ''),
                'q' => (string) ($_GET['q'] ?? ''),
            ])]);
        }
        if ($action === 'session_detail') {
            $sid = trim((string) ($_GET['session_id'] ?? ''));
            $detail = $visitors->sessionDetail($sid);
            if ($detail === null) {
                ApiBootstrap::json(['success' => false, 'message' => 'Sesiune negăsită.'], 404);
            }
            ApiBootstrap::json(['success' => true, 'data' => $detail]);
        }
        if ($action === 'visitor_stats') {
            ApiBootstrap::json(['success' => true, 'data' => $visitors->visitorStats((int) ($_GET['days'] ?? 7))]);
        }
        if ($action === 'enrich_sessions') {
            $enricher = new VisitorSessionEnricherService(new EventTrackingStore(null, $siteRoot), null, $siteRoot);
            ApiBootstrap::json(['success' => true, 'data' => $enricher->enrichMissing((int) ($_GET['limit'] ?? 200))]);
        }
        if ($action === 'geo_test') {
            $enricher = new VisitorSessionEnricherService(new EventTrackingStore(null, $siteRoot), null, $siteRoot);
            $ip = trim((string) ($_GET['ip'] ?? ''));
            ApiBootstrap::json(['success' => true, 'data' => $enricher->testGeoProvider($ip !== '' ? $ip : null)]);
        }
        if ($action === 'status') {
            ApiBootstrap::json(['success' => true, 'data' => $orchestrator->status()]);
        }
        if ($action === 'tasks') {
            ApiBootstrap::json(['success' => true, 'data' => AiTaskRegistry::describeForUi()]);
        }
        if ($action === 'routes_log') {
            $log = $siteRoot . '/admin/storage/ai_intelligence/orchestrator_routes.jsonl';
            $lines = [];
            if (is_file($log)) {
                $raw = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach (array_slice(array_reverse($raw), 0, 40) as $line) {
                    $json = json_decode($line, true);
                    if (is_array($json)) {
                        $lines[] = $json;
                    }
                }
            }
            ApiBootstrap::json(['success' => true, 'data' => $lines]);
        }

        ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }

    ApiBootstrap::beginBoundedJsonWork(120);
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($body)) {
        ApiBootstrap::json(['success' => false, 'message' => 'Payload invalid.'], 400);
    }

    $action = (string) ($body['action'] ?? $_GET['action'] ?? 'dispatch');
    if ($action === 'dispatch') {
        $taskType = trim((string) ($body['task_type'] ?? ''));
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        if ($taskType === '') {
            ApiBootstrap::json(['success' => false, 'message' => 'task_type obligatoriu.'], 400);
        }

        $result = $orchestrator->handleRequest($taskType, $payload);
        ApiBootstrap::json([
            'success' => !empty($result['ok']),
            'data' => $result,
        ], !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'test_search') {
        $query = trim((string) ($body['query'] ?? ''));
        if ($query === '') {
            ApiBootstrap::json(['success' => false, 'message' => 'query obligatoriu.'], 400);
        }
        $result = IntelligenceSearchService::create($siteRoot)->search($query, (int) ($body['limit'] ?? 12), [
            'popularity_weight' => (float) ($body['popularity_weight'] ?? 0.15),
        ]);
        ApiBootstrap::json(['success' => !empty($result['ok']), 'data' => $result]);
    }

    if ($action === 'index_products') {
        $result = ProductIndexService::create($siteRoot)->indexBatch((int) ($body['limit'] ?? 100));
        ApiBootstrap::json(['success' => true, 'data' => $result]);
    }

    if ($action === 'run_aggregate') {
        $store = new EventTrackingStore(null, $siteRoot);
        $agg = new EventAggregateService($store, null, $siteRoot);
        $result = $agg->runRolling((int) ($body['days'] ?? 1));
        ApiBootstrap::json(['success' => true, 'data' => $result]);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('ai_intelligence_endpoint', $e);
}
