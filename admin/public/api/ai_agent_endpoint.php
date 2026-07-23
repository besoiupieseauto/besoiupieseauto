<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\AiActionEventService;
use Besoiu\Services\AiAgentContextLibraryService;
use Besoiu\Services\AiAgentKnowledgeIngestService;
use Besoiu\Services\AiAgentRegistryService;
use Besoiu\Services\AiBotAgentLinkService;
use Besoiu\Services\AiContextAgentService;
use Besoiu\Services\AiAgentRouterService;
use Besoiu\Services\AiAutonomyEngine;
use Besoiu\Services\AiContextArchiveService;
use Besoiu\Services\AdminOpsAlertsService;
use Besoiu\Services\AdminOpsAlertsFixService;
use Besoiu\Services\ComposerRepairAgentService;
use Besoiu\Services\SectionAssistantService;
use Besoiu\Services\SectionAssistantQueryLogService;
use Besoiu\Services\AiLibraryPageParserService;
use Besoiu\Services\AiOllamaAgentRunnerService;
use Besoiu\Services\AiSupervisor\AiSupervisorApiService;
use Besoiu\Services\AiSupervisor\Config\AiSupervisorConfig;

ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('ai_agent_endpoint');

try {
    ApiBootstrap::requireAdminFeature('automatizare.ai_agent');

    $siteRoot = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3);
    $appRoot = $siteRoot . '/app';

    $destructiveActions = [
        'supervisor_config_set',
        'supervisor_run_cycle',
        'composer_repair_run',
        'composer_repair_execute',
        'ops_alert_fix',
        'agent_delete',
        'agent_context_migrate',
    ];

    $service = new AiContextAgentService();
    $events = new AiActionEventService();
    $registry = new AiAgentRegistryService();
    $botLinks = new AiBotAgentLinkService();
    $router = new AiAgentRouterService($registry, $events);
    $autonomy = new AiAutonomyEngine(null, $events);
    $archiver = new AiContextArchiveService(null, $events);
    $opsAlerts = new AdminOpsAlertsService();
    $supervisorApi = new AiSupervisorApiService();
    $composerRepair = new ComposerRepairAgentService();
    $sectionAssistant = new SectionAssistantService();
    $ollamaRunner = new AiOllamaAgentRunnerService($siteRoot);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $action = (string) ($_GET['action'] ?? 'status');

        // Eliberează sesiunea — panoul Ollama face request-uri paralele (health + agenți).
        ApiBootstrap::releaseSession();

        if ($action === 'supervisor_status') {
            ApiBootstrap::json($supervisorApi->getStatus());
        }

        if ($action === 'metro_llm_hub') {
            ApiBootstrap::json([
                'success' => true,
                'data' => (new \Besoiu\Services\MetroLlmHubService())->snapshot(),
            ]);
        }

        if ($action === 'metro_ai_orchestra') {
            $root = $appRoot;
            $orch = \Besoiu\Services\MetroAiOrchestrator::create($root);
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'readiness' => $orch->readiness(),
                    'statistics' => $orch->statistics(),
                ],
            ]);
        }

        if ($action === 'metro_ai_orchestra_test') {
            ApiBootstrap::json([
                'success' => false,
                'message' => 'Testele orchestră AI au fost dezactivate.',
            ], 410);
        }

        if ($action === 'llm_router_log') {
            $limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
            ApiBootstrap::json([
                'success' => true,
                'data' => (new \Besoiu\Services\MetroLlmHubService())->recentRoutes($limit),
            ]);
        }

        if ($action === 'composer_repair_status') {
            ApiBootstrap::json(['success' => true, 'data' => $composerRepair->status()]);
        }

        if ($action === 'section_assist_queries') {
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
            ApiBootstrap::json([
                'success' => true,
                'data' => (new SectionAssistantQueryLogService())->recent($limit),
                'summary' => (new SectionAssistantQueryLogService())->summary(7),
            ]);
        }

        if ($action === 'brain_rules_status') {
            ApiBootstrap::json([
                'success' => true,
                'data' => (new \Besoiu\Services\ShopChatKnowledgeService())->brainRulesStatus(),
            ]);
        }

        if ($action === 'brain_rules_verify') {
            $ruleId = max(1, min(20, (int) ($_GET['rule_id'] ?? 0)));
            ApiBootstrap::json([
                'success' => true,
                'data' => (new \Besoiu\Services\ShopChatKnowledgeService())->verifyBrainRule($ruleId),
            ]);
        }

        if ($action === 'templates') {
            ApiBootstrap::json(['success' => true, 'data' => $registry->listTemplates()]);
        }
        if ($action === 'catalog') {
            ApiBootstrap::json(['success' => true, 'data' => $registry->listTemplateCatalog()]);
        }
        if ($action === 'bots') {
            ApiBootstrap::json(['success' => true, 'data' => $botLinks->listBotsWithAgents()]);
        }
        if ($action === 'agent_ollama_status') {
            ApiBootstrap::json(['success' => true, 'data' => $ollamaRunner->status()]);
        }
        if ($action === 'db_live_snapshot') {
            ApiBootstrap::json([
                'success' => true,
                'data' => \Besoiu\Services\AdminDatabaseResolver::aiLiveSnapshot(),
            ]);
        }
        if ($action === 'agent_ollama_audit') {
            ApiBootstrap::beginBoundedJsonWork(180);
            ApiBootstrap::releaseSession();
            $audit = new \Besoiu\Services\AiOllamaAuditService($siteRoot);
            ApiBootstrap::json(['success' => true, 'data' => $audit->run()]);
        }
        if ($action === 'shop_chat_escalations') {
            $esc = new \Besoiu\Services\ShopChatEscalationService();
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'open_count' => $esc->countOpen(),
                    'items' => $esc->listOpen(max(1, min(50, (int) ($_GET['limit'] ?? 20)))),
                ],
            ]);
        }
        if ($action === 'agent_intelligence_kpi') {
            $days = max(1, min(30, (int) ($_GET['days'] ?? 7)));
            $kpi = new \Besoiu\Services\AiIntelligenceKpiService();
            ApiBootstrap::json(['success' => true, 'data' => $kpi->snapshot(null, $days)]);
        }
        if ($action === 'agent_besoiu_model_status') {
            $modelSvc = new \Besoiu\Services\BesoiuOllamaModelService();
            ApiBootstrap::json(['success' => true, 'data' => $modelSvc->status()]);
        }

        if ($action === 'agents') {
            ApiBootstrap::json(['success' => true, 'data' => $registry->listAgents()]);
        }
        if ($action === 'agent') {
            $slug = (string) ($_GET['slug'] ?? '');
            $agent = $registry->getAgent($slug);
            if ($agent === null) {
                ApiBootstrap::json(['success' => false, 'message' => 'Agent negăsit.'], 404);
            }
            $library = new AiAgentContextLibraryService($siteRoot);
            $agent['context_library'] = [
                'summary' => $library->summary($slug),
                'entries' => $library->listEntries($slug, 40),
            ];
            ApiBootstrap::json(['success' => true, 'data' => $agent]);
        }
        if ($action === 'agent_context_library') {
            $slug = (string) ($_GET['slug'] ?? '');
            $q = trim((string) ($_GET['q'] ?? ''));
            $library = new AiAgentContextLibraryService($siteRoot);
            if ($q !== '') {
                $hits = array_map(
                    static fn ($e) => (new AiLibraryPageParserService())->enrichEntry($e),
                    $library->retrieve($slug, $q, 30)
                );
                ApiBootstrap::json([
                    'success' => true,
                    'data' => [
                        'summary' => $library->summary($slug),
                        'sections' => [[
                            'id' => 'search',
                            'label' => 'Rezultate căutare',
                            'icon' => '🔍',
                            'count' => count($hits),
                            'entries' => $hits,
                        ]],
                        'query' => $q,
                        'total_visible' => count($hits),
                        'total_noise' => 0,
                    ],
                ]);
            }
            ApiBootstrap::json([
                'success' => true,
                'data' => $library->browseGrouped($slug, 25),
            ]);
        }
        if ($action === 'agent_knowledge_test') {
            $slug = (string) ($_GET['slug'] ?? '');
            $q = trim((string) ($_GET['q'] ?? ''));
            $ingest = new AiAgentKnowledgeIngestService($siteRoot);
            ApiBootstrap::json([
                'success' => true,
                'data' => $ingest->testRetrieval($slug, $q, (int) ($_GET['limit'] ?? 8)),
            ]);
        }
        if ($action === 'ops_alerts') {
            ApiBootstrap::json(['success' => true, 'data' => $opsAlerts->summary()]);
        }
        if ($action === 'autonomy') {
            ApiBootstrap::json([
                'success' => true,
                'data' => array_merge($autonomy->status(), ['archive' => $archiver->status()]),
            ]);
        }
        if ($action === 'archive_now') {
            $manifest = $archiver->archiveAndReset();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Arhivă creată și buffer golit.',
                'data' => $manifest,
            ]);
        }
        if ($action === 'agent_runtime') {
            $slugParam = (string) ($_GET['slug'] ?? 'auto');
            $route = null;
            $eventList = $events->readEventsForDisplay(30);

            if ($slugParam === 'auto' || $slugParam === '') {
                $route = $router->resolve([
                    'path' => (string) ($_GET['path'] ?? ''),
                    'admin_section' => (string) ($_GET['admin_section'] ?? 'ai-agent'),
                    'events' => $eventList,
                ]);
                $slug = $route['slug'];
            } else {
                $slug = $slugParam;
            }

            $agent = $registry->getAgent($slug);
            if ($agent === null) {
                ApiBootstrap::json(['success' => false, 'message' => 'Agent negăsit.'], 404);
            }

            $autoSync = isset($_GET['auto']) && filter_var($_GET['auto'], FILTER_VALIDATE_BOOLEAN);
            $syncMeta = ['ran' => false, 'reason' => 'off', 'agents' => []];
            if ($autoSync) {
                $toSync = $route !== null
                    ? $route['agents_to_sync']
                    : array_unique(['context-master', $slug]);
                foreach ($toSync as $syncSlug) {
                    if ($registry->getAgent($syncSlug) === null) {
                        continue;
                    }
                    $one = $registry->maybeAutoRunIfNeeded($syncSlug, $events, 40);
                    $syncMeta['agents'][$syncSlug] = $one;
                    if (!empty($one['ran'])) {
                        $syncMeta['ran'] = true;
                    }
                }
                $syncMeta['reason'] = $syncMeta['ran'] ? 'synced' : 'fresh';
                $agent = $registry->getAgent($slug) ?? $agent;
            }

            $latestPath = $appRoot . '/robot/data/ai_context/latest.json';
            $latest = is_file($latestPath)
                ? json_decode((string) file_get_contents($latestPath), true)
                : null;

            $runtimeMarkdown = $slugParam === 'auto' || $slugParam === ''
                ? $router->buildCompositeRuntimeMarkdown($slug)
                : $registry->loadRuntimeMarkdown($slug);

            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'slug' => $slug,
                    'name' => $agent['name'] ?? $slug,
                    'temperature' => (float) ($agent['temperature'] ?? 1),
                    'runtime_markdown' => $runtimeMarkdown,
                    'runtime_chars' => strlen($runtimeMarkdown),
                    'last_run_at' => ($agent['state'] ?? [])['last_run_at'] ?? null,
                    'core' => $events->getCore(),
                    'events' => $eventList,
                    'sources' => is_array($latest['signals']['sources'] ?? null)
                        ? $latest['signals']['sources']
                        : [],
                    'context_generated_at' => $latest['generated_at'] ?? null,
                    'auto_sync' => $syncMeta,
                    'autonomy' => array_merge($autonomy->status(), ['archive' => $archiver->status()]),
                    'ops_alerts' => $opsAlerts->summary(),
                    'route' => $route ?? [
                        'slug' => $slug,
                        'name' => $agent['name'] ?? $slug,
                        'reason' => 'Agent selectat manual',
                        'section' => $slug,
                        'agents_to_sync' => ['context-master', $slug],
                    ],
                    'orchestra' => $router->buildOrchestra($route ?? [
                        'slug' => $slug,
                        'agents_to_sync' => ['context-master', $slug],
                        'scores' => [$slug => 1],
                    ]),
                ],
            ]);
        }
        if ($action === 'importable_mdc') {
            ApiBootstrap::json(['success' => true, 'data' => $registry->listImportableMdc()]);
        }
        if ($action === 'history') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $service->history((int) ($_GET['limit'] ?? 10)),
            ]);
        }
        if ($action === 'events') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $events->readEvents((int) ($_GET['limit'] ?? 40)),
            ]);
        }
        if ($action === 'core') {
            ApiBootstrap::json(['success' => true, 'data' => $events->getCore()]);
        }

        $latestPath = $appRoot . '/robot/data/ai_context/latest.json';
        $latest = is_file($latestPath)
            ? json_decode((string) file_get_contents($latestPath), true)
            : null;

        $route = $router->resolve(['admin_section' => 'ai-agent', 'events' => $events->readEvents(20)]);
        $syncOnStatus = isset($_GET['sync']) && filter_var($_GET['sync'], FILTER_VALIDATE_BOOLEAN);
        if ($syncOnStatus) {
            foreach ($route['agents_to_sync'] as $syncSlug) {
                if ($registry->getAgent($syncSlug) !== null) {
                    $registry->maybeAutoRunIfNeeded($syncSlug, $events, 60);
                }
            }
            if ((new AiSupervisorConfig())->isAutonomyEnabled()) {
                $autonomy->tick();
            }
        }
        $latest = is_file($latestPath)
            ? json_decode((string) file_get_contents($latestPath), true)
            : null;

        ApiBootstrap::json([
            'success' => true,
            'status' => $service->status(),
            'core' => $events->getCore(),
            'events' => $events->readEventsForDisplay(20),
            'autonomy' => array_merge($autonomy->status(), ['archive' => $archiver->status()]),
            'ops_alerts' => $opsAlerts->summary(),
            'agents' => $registry->listAgents(),
            'latest' => is_array($latest) ? [
                'generated_at' => $latest['generated_at'] ?? null,
                'markdown' => $latest['markdown'] ?? '',
                'robot_brief' => $latest['robot_brief'] ?? '',
                'sources' => $latest['signals']['sources'] ?? [],
            ] : null,
        ]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    ApiBootstrap::beginBoundedJsonWork(130);

    $input = file_get_contents('php://input') ?: '';
    $data = json_decode($input, true);
    if (!is_array($data)) {
        $data = $_POST ?: [];
    }

    $action = (string) ($data['action'] ?? '');
    $useLlm = !isset($data['use_llm']) || filter_var($data['use_llm'], FILTER_VALIDATE_BOOLEAN);

    if (in_array($action, $destructiveActions, true) || str_starts_with($action, 'supervisor_')) {
        ApiBootstrap::requireAdminFeature('automatizare.ai_agent', true);
    }

    if (str_starts_with($action, 'supervisor_')) {
        ApiBootstrap::json($supervisorApi->postAction($action, $data));
    }

    switch ($action) {
        case 'agent_create_from_template':
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent creat din template.',
                'data' => $registry->createFromTemplate(
                    (string) ($data['template_id'] ?? ''),
                    isset($data['slug']) ? (string) $data['slug'] : null
                ),
            ]);
            break;

        case 'bot_set_agent':
            $botLinks->setBotAgent(
                (int) ($data['randomn_id'] ?? 0),
                isset($data['ai_agent_slug']) && (string) $data['ai_agent_slug'] !== ''
                    ? (string) $data['ai_agent_slug']
                    : null
            );
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent legat de bot.',
                'data' => $botLinks->listBotsWithAgents(),
            ]);
            break;

        case 'agent_create':
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent creat.',
                'data' => $registry->createAgent($data),
            ]);
            break;

        case 'agent_update':
            $slug = (string) ($data['slug'] ?? '');
            if ($slug === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug agent.'], 400);
            }
            try {
                $updated = $registry->updateAgent($slug, $data);
            } catch (Throwable $e) {
                ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent actualizat.',
                'data' => $updated,
            ]);
            break;

        case 'agent_context_add':
            $slug = (string) ($data['slug'] ?? '');
            $text = trim((string) ($data['text'] ?? ''));
            if ($slug === '' || $text === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug sau text fragment.'], 400);
            }
            $library = new AiAgentContextLibraryService($siteRoot);
            $entry = $library->appendEntry($slug, [
                'text' => $text,
                'source' => 'operator',
                'tags' => is_array($data['tags'] ?? null) ? $data['tags'] : ['manual'],
                'pinned' => !empty($data['pinned']),
            ]);
            ApiBootstrap::json([
                'success' => $entry !== null,
                'message' => $entry !== null ? 'Fragment adăugat în bibliotecă.' : 'Eroare salvare.',
                'data' => [
                    'entry' => $entry,
                    'summary' => $library->summary($slug),
                    'entries' => $library->listEntries($slug, 80),
                ],
            ], $entry !== null ? 200 : 422);
            break;

        case 'agent_context_migrate':
            $slug = (string) ($data['slug'] ?? '');
            $library = new AiAgentContextLibraryService($siteRoot);
            $safeSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
            $path = $siteRoot . '/robot/data/ai_agents/' . $safeSlug . '/context.library.jsonl';
            if (is_file($path)) {
                $backup = $path . '.bak.' . date('Ymd_His');
                @copy($path, $backup);
                @unlink($path);
            }
            $count = $library->migrateFromLearnedIfEmpty($slug);
            ApiBootstrap::json([
                'success' => true,
                'message' => $count > 0 ? ('Migrate ' . $count . ' fragmente din context învățat.') : 'Nimic de migrat.',
                'data' => [
                    'migrated' => $count,
                    'summary' => $library->summary($slug),
                    'entries' => $library->listEntries($slug, 40),
                ],
            ]);
            break;

        case 'agent_context_promote':
            $slug = (string) ($data['slug'] ?? '');
            $entryId = trim((string) ($data['id'] ?? ''));
            if ($slug === '' || $entryId === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug sau id fragment.'], 400);
            }
            $library = new AiAgentContextLibraryService($siteRoot);
            $entry = $library->promoteEntry($slug, $entryId, !empty($data['pinned']));
            ApiBootstrap::json([
                'success' => $entry !== null,
                'message' => $entry !== null ? 'Fragment promovat ca operator (prioritar RAG).' : 'Fragment negăsit.',
                'data' => [
                    'entry' => $entry,
                    'summary' => $library->summary($slug),
                    'entries' => $library->listEntries($slug, 80),
                ],
            ], $entry !== null ? 200 : 404);
            break;

        case 'agent_context_delete':
            $slug = (string) ($data['slug'] ?? '');
            $entryId = trim((string) ($data['id'] ?? ''));
            if ($slug === '' || $entryId === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug sau id fragment.'], 400);
            }
            $library = new AiAgentContextLibraryService($siteRoot);
            $deleted = $library->deleteEntry($slug, $entryId);
            ApiBootstrap::json([
                'success' => $deleted,
                'message' => $deleted ? 'Fragment șters din bibliotecă.' : 'Fragment negăsit.',
                'data' => [
                    'summary' => $library->summary($slug),
                    'entries' => $library->listEntries($slug, 80),
                ],
            ], $deleted ? 200 : 404);
            break;

        case 'agent_context_add_bulk':
            $slug = (string) ($data['slug'] ?? '');
            $texts = is_array($data['texts'] ?? null) ? $data['texts'] : [];
            if ($slug === '' || $texts === []) {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug sau lista de fragmente.'], 400);
            }
            $library = new AiAgentContextLibraryService($siteRoot);
            $added = 0;
            foreach ($texts as $text) {
                $text = trim((string) $text);
                if ($text === '') {
                    continue;
                }
                if ($library->appendEntry($slug, [
                    'text' => $text,
                    'source' => 'operator',
                    'tags' => ['manual', 'din-jurnal'],
                    'pinned' => !empty($data['pinned']),
                ]) !== null) {
                    ++$added;
                }
            }
            ApiBootstrap::json([
                'success' => $added > 0,
                'message' => $added > 0 ? ('Adăugate ' . $added . ' fragmente în bibliotecă.') : 'Nimic de adăugat.',
                'data' => [
                    'added' => $added,
                    'summary' => $library->summary($slug),
                    'entries' => $library->listEntries($slug, 80),
                ],
            ], $added > 0 ? 200 : 422);
            break;

        case 'agent_context_scrape_url':
            ApiBootstrap::beginBoundedJsonWork(300);
            ApiBootstrap::releaseSession();
            $slug = (string) ($data['slug'] ?? '');
            $url = trim((string) ($data['url'] ?? ''));
            if (!empty($data['use_scraper']) || !empty($data['intelligent'])) {
                $scraper = new \Besoiu\Services\AiRag\AiRagIntelligentScrapeService($siteRoot);
                $res = $scraper->scrapeToCorpus($url, [
                    'source_id' => (string) ($data['source_id'] ?? ''),
                    'topic' => (string) ($data['topic'] ?? $data['note'] ?? ''),
                    'follow_links' => !empty($data['follow_links']),
                    'use_source_pipeline' => !empty($data['use_source_pipeline']),
                    'summarize' => !empty($data['summarize']),
                    'agent_slug' => $slug,
                    'max_follow' => (int) ($data['max_follow'] ?? 3),
                ]);
                ApiBootstrap::json([
                    'success' => !empty($res['ok']),
                    'message' => !empty($res['ok'])
                        ? ('Scrape inteligent: ' . (int) ($res['fragments_added'] ?? 0) . ' fragmente în corpus + agent.')
                        : (string) ($res['error'] ?? 'Scrape eșuat'),
                    'data' => array_merge($res, [
                        'entries' => (new AiAgentContextLibraryService($siteRoot))->listEntries($slug, 80),
                    ]),
                ], !empty($res['ok']) ? 200 : 422);
                break;
            }
            $ingest = new AiAgentKnowledgeIngestService($siteRoot);
            $res = $ingest->scrapeUrl($slug, $url, [
                'summarize' => !empty($data['summarize']),
                'pinned' => !empty($data['pinned']),
                'note' => (string) ($data['note'] ?? ''),
                'max_chars' => (int) ($data['max_chars'] ?? 12000),
            ]);
            ApiBootstrap::json([
                'success' => !empty($res['ok']),
                'message' => !empty($res['ok'])
                    ? ('Scrape OK — ' . (int) ($res['fragments_added'] ?? 0) . ' fragmente adăugate.')
                    : (string) ($res['error'] ?? 'Scrape eșuat'),
                'data' => array_merge($res, [
                    'entries' => (new AiAgentContextLibraryService($siteRoot))->listEntries($slug, 80),
                ]),
            ], !empty($res['ok']) ? 200 : 422);
            break;

        case 'agent_knowledge_test':
            $slug = (string) ($data['slug'] ?? '');
            $q = trim((string) ($data['query'] ?? $data['q'] ?? ''));
            $ingest = new AiAgentKnowledgeIngestService($siteRoot);
            $res = $ingest->testRetrieval($slug, $q, (int) ($data['limit'] ?? 8));
            ApiBootstrap::json([
                'success' => !empty($res['ok']),
                'message' => !empty($res['ok']) ? 'Test RAG OK' : (string) ($res['error'] ?? 'Eșec'),
                'data' => $res,
            ], !empty($res['ok']) ? 200 : 422);
            break;

        case 'agent_run_refresh':
            $slug = (string) ($data['slug'] ?? '');
            if ($slug === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește slug agent.'], 400);
            }
            try {
                $runtime = $registry->runAgent($slug, true);
                ApiBootstrap::json([
                    'success' => true,
                    'message' => 'Runtime agent regenerat cu biblioteca actualizată.',
                    'data' => [
                        'runtime_chars' => strlen((string) ($runtime['markdown'] ?? '')),
                        'generated_at' => (string) ($runtime['generated_at'] ?? ''),
                    ],
                ]);
            } catch (Throwable $e) {
                ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            break;

        case 'agent_delete':
            $registry->deleteAgent((string) ($data['slug'] ?? ''));
            ApiBootstrap::json(['success' => true, 'message' => 'Agent șters.']);
            break;

        case 'agent_clone':
            $slug = (string) ($data['slug'] ?? '');
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent duplicat.',
                'data' => $registry->cloneAgent($slug, isset($data['new_slug']) ? (string) $data['new_slug'] : null),
            ]);
            break;

        case 'agent_export_mdc':
            $slug = (string) ($data['slug'] ?? '');
            ApiBootstrap::json([
                'success' => true,
                'data' => $registry->exportAgentMdc($slug),
            ]);
            break;

        case 'agent_import_mdc':
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Import .mdc reușit.',
                'data' => $registry->importFromBesMdc(
                    (string) ($data['file'] ?? ''),
                    isset($data['slug']) ? (string) $data['slug'] : null
                ),
            ]);
            break;

        case 'agent_run':
            $slug = (string) ($data['slug'] ?? 'context-master');
            if ($slug === 'ops-composer-repair') {
                $supervisorCfg = new AiSupervisorConfig();
                $report = $composerRepair->run([
                    'force' => true,
                    'auto_execute' => !empty($data['auto_execute'])
                        && !empty($supervisorCfg->get()['composer_repair_auto_execute']),
                ]);
                ApiBootstrap::json([
                    'success' => true,
                    'message' => (string) ($report['summary'] ?? 'Agent Composer finalizat.'),
                    'data' => $report,
                    'agent' => $registry->getAgent($slug),
                ]);
                break;
            }
            $runtime = $registry->runAgent($slug, !empty($data['auto_collect']));
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Agent rulat — context salvat.',
                'data' => $runtime,
                'agent' => $registry->getAgent($slug),
            ]);
            break;

        case 'collect':
            ApiBootstrap::json(['success' => true, 'data' => $service->collectSignals()]);
            break;

        case 'composer_repair_run':
            $supervisorCfg = new AiSupervisorConfig();
            $report = $composerRepair->run([
                'force' => true,
                'auto_execute' => !empty($data['auto_execute'])
                    && !empty($supervisorCfg->get()['composer_repair_auto_execute']),
                'code' => trim((string) ($data['code'] ?? '')),
            ]);
            ApiBootstrap::json([
                'success' => !empty($report['ok']),
                'message' => (string) ($report['summary'] ?? 'Ciclu Composer finalizat.'),
                'data' => $report,
                'ops_alerts' => $opsAlerts->summary(),
            ]);
            break;

        case 'composer_repair_analyze':
            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește codul alertei.'], 400);
            }
            ApiBootstrap::json($composerRepair->analyzeOne($code));
            break;

        case 'composer_repair_execute':
            $item = is_array($data['item'] ?? null) ? $data['item'] : [];
            if ($item === [] && trim((string) ($data['code'] ?? '')) !== '') {
                $an = $composerRepair->analyzeOne((string) $data['code']);
                $item = is_array($an['data'] ?? null) ? $an['data'] : [];
            }
            if ($item === []) {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește planul de reparare.'], 400);
            }
            $executed = $composerRepair->executeItem($item, isset($data['min_confidence']) ? (float) $data['min_confidence'] : null);
            ApiBootstrap::json([
                'success' => in_array($executed['outcome'] ?? '', ['repaired', 'partial'], true),
                'message' => (string) ($executed['execute_message'] ?? 'Executat.'),
                'data' => $executed,
                'ops_alerts' => $opsAlerts->summary(),
            ], in_array($executed['outcome'] ?? '', ['repaired', 'partial'], true) ? 200 : 422);
            break;

        case 'ops_alert_fix':
            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '') {
                ApiBootstrap::json(['success' => false, 'message' => 'Lipsește codul alertei.'], 400);
            }
            $result = $composerRepair->fixAlert($code, $data, true);
            ApiBootstrap::json(array_merge($result, [
                'ops_alerts' => $opsAlerts->summary(),
            ]), ($result['success'] ?? false) ? 200 : 422);
            break;

        case 'agent_ollama_chat':
            ApiBootstrap::beginBoundedJsonWork(180);
            ApiBootstrap::releaseSession();
            $slug = (string) ($data['slug'] ?? '');
            $message = trim((string) ($data['message'] ?? ''));
            $smokeOnly = !empty($data['smoke_only']);
            $useKnowledge = !$smokeOnly && !empty($data['use_knowledge']);
            $useLiveDb = !$smokeOnly && !empty($data['use_live_db']);
            $options = [
                'randomn_id' => trim((string) ($data['randomn_id'] ?? '')),
                'mode' => trim((string) ($data['mode'] ?? '')),
                'preset' => trim((string) ($data['preset'] ?? '')),
                'smoke_only' => $smokeOnly,
                'fast_test' => $smokeOnly,
                'use_knowledge' => $useKnowledge,
                'use_live_db' => $useLiveDb,
            ];
            $result = $ollamaRunner->chat($slug, $message, $options);
            ApiBootstrap::json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['content'] ?? $result['error'] ?? ''),
                'data' => $result,
            ], !empty($result['ok']) ? 200 : 422);
            break;

        case 'agent_ollama_daily_stats':
            ApiBootstrap::releaseSession();
            $result = $ollamaRunner->runDailyStatsReport();
            ApiBootstrap::json([
                'success' => !empty($result['ok']),
                'message' => (string) ($result['content'] ?? $result['error'] ?? ''),
                'data' => $result,
            ], !empty($result['ok']) ? 200 : 422);
            break;

        case 'shop_chat_escalation_resolve':
            $escId = (int) ($data['id'] ?? 0);
            $status = (string) ($data['status'] ?? 'resolved');
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $esc = new \Besoiu\Services\ShopChatEscalationService();
            $ok = $esc->resolve($escId, $userId, $status);
            ApiBootstrap::json([
                'success' => $ok,
                'message' => $ok ? 'Escaladare marcată.' : 'Escaladare negăsită.',
                'data' => ['open_count' => $esc->countOpen()],
            ], $ok ? 200 : 404);
            break;

        case 'finetuning_export_faq':
            $limit = max(10, min(2000, (int) ($data['limit'] ?? 500)));
            $export = new \Besoiu\Services\FinetuningFaqExportService();
            $result = $export->exportToJsonl(null, $limit, true);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Export FAQ: ' . (int) ($result['written'] ?? 0) . ' exemple noi, total ' . (int) ($result['total'] ?? 0) . '.',
                'data' => $result,
            ]);
            break;

        case 'besoiu_model_deploy':
            $modelSvc = new \Besoiu\Services\BesoiuOllamaModelService();
            $gguf = trim((string) ($data['gguf_path'] ?? ''));
            $result = $modelSvc->deploy($gguf !== '' ? $gguf : null);
            ApiBootstrap::json([
                'success' => !empty($result['ok']),
                'message' => !empty($result['ok'])
                    ? 'Model ' . ($result['model_name'] ?? 'besoiu-llama') . ' instalat în Ollama.'
                    : (string) ($result['error'] ?? 'Deploy eșuat'),
                'data' => $result,
            ], !empty($result['ok']) ? 200 : 422);
            break;

        case 'section_assist':
            ApiBootstrap::releaseSession();
            $assist = SectionAssistantService::assistFromPayload(is_array($data) ? $data : []);
            ApiBootstrap::json([
                'success' => !empty($assist['ok']),
                'message' => (string) ($assist['reply'] ?? $assist['message'] ?? ''),
                'data' => $assist,
            ], !empty($assist['ok']) ? 200 : 422);
            break;

        case 'section_assist_learn':
            $learn = SectionAssistantService::learnFromPayload(is_array($data) ? $data : []);
            ApiBootstrap::json([
                'success' => !empty($learn['ok']),
                'message' => (string) ($learn['reply'] ?? $learn['message'] ?? ''),
                'data' => $learn,
            ], !empty($learn['ok']) ? 200 : 422);
            break;

        case 'synthesize':
            $signals = is_array($data['signals'] ?? null)
                ? $data['signals']
                : $service->collectSignals();
            ApiBootstrap::json(['success' => true, 'data' => $service->synthesize($signals, $useLlm)]);
            break;

        case 'full':
            $runtime = $registry->runAgent('context-master', true);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Context agent salvat pentru roboți.',
                'data' => $runtime,
                'status' => $service->status(),
            ]);
            break;

        case 'brain_rules_seed_one':
            $ruleId = max(1, min(20, (int) ($data['rule_id'] ?? 0)));
            $update = !array_key_exists('update_existing', $data) || !empty($data['update_existing']);
            $chatKnowledge = new \Besoiu\Services\ShopChatKnowledgeService();
            $seed = $chatKnowledge->seedBrainRule($ruleId, $update);
            $verify = $chatKnowledge->verifyBrainRule($ruleId);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Regula #' . $ruleId . ' instalată în RAG chat.',
                'data' => [
                    'seed' => $seed,
                    'verify' => $verify,
                    'status' => $chatKnowledge->brainRulesStatus(),
                ],
            ]);
            break;

        case 'brain_rules_seed_all':
            $update = !array_key_exists('update_existing', $data) || !empty($data['update_existing']);
            $chatKnowledge = new \Besoiu\Services\ShopChatKnowledgeService();
            $seed = $chatKnowledge->seedAllBrainRules($update);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Toate regulile creier instalate în RAG.',
                'data' => [
                    'seed' => $seed,
                    'status' => $chatKnowledge->brainRulesStatus(),
                ],
            ]);
            break;

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('ai_agent_endpoint', $exception);
}
