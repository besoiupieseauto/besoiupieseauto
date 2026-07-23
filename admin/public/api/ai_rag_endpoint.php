<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\AiRag\AiInteractionLogService;
use Besoiu\Services\AiRag\AiLogSummaryService;
use Besoiu\Services\AiRag\AiWorkFeedService;
use Besoiu\Services\AiRag\AiNormalizeDescriptionService;
use Besoiu\Services\AiRag\AiProductEmbeddingStore;
use Besoiu\Services\AiRag\AiRagConfigService;
use Besoiu\Services\AiRag\AiRagDashboardService;
use Besoiu\Services\AiRag\AiRagModuleRegistry;
use Besoiu\Services\AiRag\AiRagBulkSeedService;
use Besoiu\Services\AiRag\AiSupplierCatalogRagIndexerService;
use Besoiu\Services\AiRag\AiRagCorpusService;
use Besoiu\Services\AiRag\AiRagIntelligentScrapeService;
use Besoiu\Services\AiRag\AiKnowledgeBridgeService;
use Besoiu\Services\AiRag\AiMarketResearchJobService;
use Besoiu\Services\AiRag\AiMarketResearchQueryService;
use Besoiu\Services\AiRag\AiMarketResearchSourceService;
use Besoiu\Services\AiRag\AiMarketResearchStore;
use Besoiu\Services\AiRag\AiSemanticMatchService;
use Besoiu\Services\AiRag\AiSiteMissionRunnerService;
use Besoiu\Services\AiRag\AiUserSitesConfigService;
use Besoiu\Services\AiRag\AiVectorStoreService;

ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('ai_rag_endpoint');

try {
    ApiBootstrap::requireAdminFeature('automatizare.ai_rag');

    $siteRoot = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3);
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $dashboard = new AiRagDashboardService($siteRoot);
    $config = new AiRagConfigService($siteRoot);
    $logSummary = new AiLogSummaryService($siteRoot);
    $aiWorkFeed = new AiWorkFeedService($siteRoot);
    $vector = new AiVectorStoreService($siteRoot);

    $interactionLog = static function () use ($siteRoot): AiInteractionLogService {
        static $instance = null;
        $instance ??= new AiInteractionLogService(null, $siteRoot);

        return $instance;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ApiBootstrap::releaseSession();
        $action = (string) ($_GET['action'] ?? 'dashboard');

        if ($action === 'dashboard') {
            ApiBootstrap::json(['success' => true, 'data' => $dashboard->snapshot()]);
        }

        if ($action === 'modules') {
            ApiBootstrap::json(['success' => true, 'data' => $config->allModulesWithConfig()]);
        }

        if ($action === 'interaction_logs') {
            $limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
            $moduleId = trim((string) ($_GET['module_id'] ?? ''));
            ApiBootstrap::json([
                'success' => true,
                'data' => $interactionLog()->recent($limit, $moduleId !== '' ? $moduleId : null),
            ]);
        }

        if ($action === 'aiwork_feed') {
            $minutes = max(1, min(120, (int) ($_GET['minutes'] ?? 15)));
            $limit = max(10, min(300, (int) ($_GET['limit'] ?? 150)));
            ApiBootstrap::json([
                'success' => true,
                'data' => $aiWorkFeed->feed($minutes, $limit),
            ]);
        }

        if ($action === 'log_summaries') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $logSummary->listSummaries((int) ($_GET['limit'] ?? 20)),
            ]);
        }

        if ($action === 'log_sources_preview') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $logSummary->collectSources((int) ($_GET['lines'] ?? 80)),
            ]);
        }

        if ($action === 'vector_status') {
            ApiBootstrap::json(['success' => true, 'data' => $vector->status()]);
        }

        if ($action === 'corpus_status') {
            ApiBootstrap::json(['success' => true, 'data' => (new AiRagCorpusService($siteRoot))->status()]);
        }

        if ($action === 'market_dashboard') {
            $bridge = new AiKnowledgeBridgeService($siteRoot);
            ApiBootstrap::json([
                'success' => true,
                'data' => array_merge(
                    $bridge->overview(),
                    ['research' => (new AiMarketResearchQueryService($siteRoot))->dashboard()]
                ),
            ]);
        }

        if ($action === 'library_overview') {
            ApiBootstrap::json(['success' => true, 'data' => (new AiKnowledgeBridgeService($siteRoot))->overview()]);
        }

        if ($action === 'library_browse') {
            ApiBootstrap::json([
                'success' => true,
                'data' => (new AiKnowledgeBridgeService($siteRoot))->browse([
                    'q' => (string) ($_GET['q'] ?? ''),
                    'origin' => (string) ($_GET['origin'] ?? ''),
                    'source_type' => (string) ($_GET['source_type'] ?? ''),
                    'has_seo' => !empty($_GET['has_seo']),
                    'offset' => (int) ($_GET['offset'] ?? 0),
                    'limit' => (int) ($_GET['limit'] ?? 20),
                ]),
            ]);
        }

        if ($action === 'library_entry') {
            $id = trim((string) ($_GET['id'] ?? ''));
            $entry = (new AiKnowledgeBridgeService($siteRoot))->getEntry($id);
            ApiBootstrap::json([
                'success' => $entry !== null,
                'data' => $entry,
            ], $entry !== null ? 200 : 404);
        }

        if ($action === 'market_progress') {
            ApiBootstrap::json(['success' => true, 'data' => (new AiMarketResearchJobService($siteRoot))->progress()]);
        }

        if ($action === 'library_sites_config') {
            $cfg = new AiUserSitesConfigService($siteRoot);
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'config' => $cfg->all(),
                    'tip_options' => AiUserSitesConfigService::TIP_SURSA_OPTIONS,
                    'progress' => (new AiSiteMissionRunnerService($siteRoot))->progress(),
                ],
            ]);
        }

        if ($action === 'library_sites_progress') {
            ApiBootstrap::json(['success' => true, 'data' => (new AiSiteMissionRunnerService($siteRoot))->progress()]);
        }

        if ($action === 'library_product_search' || $action === 'library_product_browse') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(48, (int) ($_GET['limit'] ?? 24)));
            $imageFilter = trim((string) ($_GET['image'] ?? 'all'));
            $service = new \Besoiu\Services\Products\ProduseService();
            $filters = ['status' => 'active'];
            if ($q !== '') {
                $filters['q'] = $q;
            }
            if ($imageFilter === 'with') {
                $filters['image'] = 'present';
            } elseif ($imageFilter === 'without') {
                $filters['image'] = 'missing';
            }
            $pageData = $service->getProdusesPaginated($page, $limit, $filters);
            $items = [];
            foreach (($pageData['items'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['pName'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $imagesRaw = $row['pImages'] ?? '';
                $image = '';
                $decoded = is_string($imagesRaw) ? json_decode($imagesRaw, true) : null;
                if (is_array($decoded)) {
                    foreach ($decoded as $img) {
                        $img = trim((string) $img);
                        if ($img !== '') {
                            $image = $img;
                            break;
                        }
                    }
                } elseif (is_string($imagesRaw) && trim($imagesRaw) !== '' && !in_array(trim($imagesRaw), ['[]', 'null'], true)) {
                    $image = trim($imagesRaw);
                }
                $price = trim((string) ($row['pPrice'] ?? ''));
                $items[] = [
                    'id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
                    'name' => $name,
                    'code' => trim((string) ($row['pCode'] ?? '')),
                    'oem' => trim((string) ($row['pOem'] ?? '')),
                    'category' => trim((string) ($row['pCategory'] ?? '')),
                    'subcategory' => trim((string) ($row['pSubcategory'] ?? '')),
                    'brand' => trim((string) ($row['pBrand'] ?? '')),
                    'price' => $price,
                    'price_label' => $price !== '' ? (str_contains($price, 'RON') ? $price : $price . ' RON') : '',
                    'image' => $image,
                    'has_image' => $image !== '',
                    'image_source' => trim((string) ($row['pImageSource'] ?? '')),
                    'stock' => (int) ($row['pStock'] ?? 0),
                ];
            }
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'query' => $q,
                    'items' => $items,
                    'total' => (int) ($pageData['total'] ?? count($items)),
                    'page' => (int) ($pageData['page'] ?? $page),
                    'per_page' => (int) ($pageData['per_page'] ?? $limit),
                    'total_pages' => (int) ($pageData['total_pages'] ?? 1),
                ],
            ]);
        }

        if ($action === 'market_entry') {
            $uuid = trim((string) ($_GET['uuid'] ?? ''));
            $row = (new AiMarketResearchStore(null, $siteRoot))->getByUuid($uuid);
            ApiBootstrap::json([
                'success' => $row !== null,
                'data' => $row,
                'message' => $row !== null ? '' : 'Intrare inexistentă.',
            ], $row !== null ? 200 : 404);
        }

        if ($action === 'corpus_search') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $corpus = new AiRagCorpusService($siteRoot);
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'query' => $q,
                    'hits' => $corpus->search($q, (int) ($_GET['limit'] ?? 10)),
                    'status' => $corpus->status(),
                ],
            ]);
        }

        if ($action === 'registry') {
            ApiBootstrap::json(['success' => true, 'data' => AiRagModuleRegistry::all()]);
        }

        if ($action === 'import_pro_status') {
            $cfg = new AiRagConfigService($siteRoot);
            $m1 = $cfg->moduleRuntime('mod_01_semantic_match');
            $m2 = $cfg->moduleRuntime('mod_02_normalize_desc');
            $m6 = $cfg->moduleRuntime('mod_06_log_summary');
            ApiBootstrap::json([
                'success' => true,
                'data' => [
                    'mod_01' => $m1,
                    'mod_02' => $m2,
                    'mod_06' => $m6,
                    'vector' => $vector->status(),
                ],
            ]);
        }

        ApiBootstrap::json(['success' => false, 'message' => 'Acțiune GET necunoscută.'], 422);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
        if (!is_array($payload)) {
            $payload = [];
        }
    }

    $action = (string) ($payload['action'] ?? '');

    if ($action === 'save_module') {
        $moduleId = trim((string) ($payload['module_id'] ?? ''));
        $res = $config->saveModule($moduleId, $payload);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Config salvat.' : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'generate_log_summary') {
        ApiBootstrap::beginBoundedJsonWork(150);
        ApiBootstrap::releaseSession();
        $preview = !empty($payload['preview_only']);
        $res = $logSummary->generate([
            'preview_only' => $preview,
            'user_id' => $userId,
            'log_lines' => (int) ($payload['log_lines'] ?? 120),
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']) || $preview,
            'message' => !empty($res['ok'])
                ? (!empty($res['fallback']) ? 'Sumar generat (fallback local).' : 'Sumar generat.')
                : (string) ($res['error'] ?? 'Generare eșuată'),
            'data' => $res,
        ], !empty($res['ok']) || $preview ? 200 : 422);
    }

    if ($action === 'semantic_match_suggest') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : $payload;
        $semantic = new AiSemanticMatchService($siteRoot);
        $res = $semantic->suggest($input, [
            'user_id' => $userId,
            'auto_index' => !empty($payload['auto_index']),
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Sugestie semantică generată.' : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'normalize_description') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : $payload;
        $normalize = new AiNormalizeDescriptionService($siteRoot);
        $res = $normalize->normalizePreview($input, ['user_id' => $userId]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Normalizare generată (preview).' : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'index_products') {
        ApiBootstrap::beginBoundedJsonWork(300);
        ApiBootstrap::releaseSession();
        $store = new AiProductEmbeddingStore(null, $siteRoot);
        $res = $store->indexFromProduse((int) ($payload['limit'] ?? 400));
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Indexare embeddings finalizată.',
            'data' => array_merge($res, ['status' => $store->status()]),
        ]);
    }

    if ($action === 'index_suppliers') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $res = AiSupplierCatalogRagIndexerService::create($siteRoot)->indexAllFromDatabase();
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('Furnizori indexați în RAG: ' . (int) ($res['indexed'] ?? 0) . ' (total activi ' . (int) ($res['total_active'] ?? 0) . ').')
                : (string) ($res['error'] ?? 'Indexare furnizori eșuată'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'bulk_seed_corpus') {
        ApiBootstrap::beginBoundedJsonWork(300);
        ApiBootstrap::releaseSession();
        $seed = new AiRagBulkSeedService($siteRoot);
        $res = $seed->seed([
            'target' => (int) ($payload['target'] ?? 500),
            'clear' => !empty($payload['clear']),
            'index_embeddings' => !empty($payload['index_embeddings']),
            'agent_slug' => (string) ($payload['agent_slug'] ?? 'agent-produse'),
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => 'Corpus RAG: +' . (int) ($res['added'] ?? 0) . ' elemente (total ' . (int) ($res['status']['total'] ?? 0) . ').',
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'intelligent_scrape') {
        ApiBootstrap::beginBoundedJsonWork(300);
        ApiBootstrap::releaseSession();
        $url = trim((string) ($payload['url'] ?? ''));
        $scraper = new AiRagIntelligentScrapeService($siteRoot);
        $res = $scraper->scrapeToCorpus($url, [
            'source_id' => (string) ($payload['source_id'] ?? ''),
            'topic' => (string) ($payload['topic'] ?? ''),
            'follow_links' => !empty($payload['follow_links']),
            'use_source_pipeline' => !empty($payload['use_source_pipeline']),
            'summarize' => !empty($payload['summarize']),
            'agent_slug' => (string) ($payload['agent_slug'] ?? ''),
            'max_follow' => (int) ($payload['max_follow'] ?? 3),
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('Scrape inteligent: ' . (int) ($res['fragments_added'] ?? 0) . ' fragmente.')
                : (string) ($res['error'] ?? 'Scrape eșuat'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'corpus_search') {
        $q = trim((string) ($payload['query'] ?? $payload['q'] ?? ''));
        $corpus = new AiRagCorpusService($siteRoot);
        ApiBootstrap::json([
            'success' => true,
            'data' => [
                'query' => $q,
                'hits' => $corpus->search($q, (int) ($payload['limit'] ?? 10)),
                'status' => $corpus->status(),
            ],
        ]);
    }

    if ($action === 'test_module') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $moduleId = trim((string) ($payload['module_id'] ?? ''));
        if ($moduleId === 'mod_06_log_summary') {
            $res = $logSummary->generate(['user_id' => $userId, 'log_lines' => 60]);
            ApiBootstrap::json([
                'success' => !empty($res['ok']),
                'message' => !empty($res['ok']) ? 'Test Modul 6 OK' : (string) ($res['error'] ?? 'Eșec'),
                'data' => $res,
            ], !empty($res['ok']) ? 200 : 422);
        }
        if ($moduleId === 'mod_01_semantic_match') {
            $semantic = new AiSemanticMatchService($siteRoot);
            $res = $semantic->suggest([
                'name' => (string) ($payload['name'] ?? 'Filtru ulei Mann'),
                'sku' => (string) ($payload['sku'] ?? 'HU816X'),
            ], ['user_id' => $userId, 'auto_index' => true]);
            ApiBootstrap::json([
                'success' => !empty($res['ok']),
                'message' => !empty($res['ok']) ? 'Test Modul 1 OK' : (string) ($res['error'] ?? 'Eșec'),
                'data' => $res,
            ], !empty($res['ok']) ? 200 : 422);
        }
        if ($moduleId === 'mod_02_normalize_desc') {
            $normalize = new AiNormalizeDescriptionService($siteRoot);
            $res = $normalize->normalizePreview([
                'raw_name' => (string) ($payload['raw_name'] ?? 'FILTRU ULEI MANN HU816X BMW'),
                'raw_description' => (string) ($payload['raw_description'] ?? 'Filtru ulei original Mann pentru BMW'),
            ], ['user_id' => $userId]);
            ApiBootstrap::json([
                'success' => !empty($res['ok']),
                'message' => !empty($res['ok']) ? 'Test Modul 2 OK' : (string) ($res['error'] ?? 'Eșec'),
                'data' => $res,
            ], !empty($res['ok']) ? 200 : 422);
        }
        ApiBootstrap::json(['success' => false, 'message' => 'Test indisponibil pentru acest modul.'], 422);
    }

    if ($action === 'vector_search_test') {
        $query = trim((string) ($payload['query'] ?? ''));
        ApiBootstrap::json(['success' => true, 'data' => $vector->testSearch($query)]);
    }

    if ($action === 'market_run_scrape') {
        ApiBootstrap::beginBoundedJsonWork(600);
        ApiBootstrap::releaseSession();
        $job = new AiMarketResearchJobService($siteRoot);
        $res = $job->run([
            'test_mode' => !empty($payload['test_mode']),
            'source_id' => (string) ($payload['source_id'] ?? ''),
            'page_limit' => (int) ($payload['page_limit'] ?? 5),
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('Research: +' . (int) ($res['added'] ?? 0) . ' intrări, ' . (int) ($res['failed'] ?? 0) . ' eșecuri.')
                : (string) ($res['error'] ?? 'Job eșuat'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'market_ask') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $q = trim((string) ($payload['question'] ?? $payload['query'] ?? ''));
        $res = (new AiKnowledgeBridgeService($siteRoot))->ask($q);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Răspuns din biblioteca unificată (' . (int) ($res['library_total'] ?? 0) . ' intrări).' : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'library_import_lookup') {
        ApiBootstrap::beginBoundedJsonWork(120);
        ApiBootstrap::releaseSession();
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : $payload;
        $res = (new AiKnowledgeBridgeService($siteRoot))->importLookup($input);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Dicționar import: ' . (int) ($res['hits'] ?? 0) . ' potriviri.' : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'library_save_sites') {
        $sites = is_array($payload['sites'] ?? null) ? $payload['sites'] : [];
        $res = (new AiUserSitesConfigService($siteRoot))->save($sites);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => 'Config salvat — ' . (int) ($res['active_count'] ?? 0) . ' site-uri active.',
            'data' => $res,
        ]);
    }

    if ($action === 'library_run_sites') {
        ApiBootstrap::beginBoundedJsonWork(900);
        ApiBootstrap::releaseSession();
        if (is_array($payload['sites'] ?? null) && $payload['sites'] !== []) {
            (new AiUserSitesConfigService($siteRoot))->save($payload['sites']);
        }
        $runner = new AiSiteMissionRunnerService($siteRoot);
        $res = $runner->run([
            'slots' => is_array($payload['slots'] ?? null) ? $payload['slots'] : [],
        ]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('Misiuni: ' . (int) ($res['ok_count'] ?? 0) . ' OK, ' . (int) ($res['fragments_total'] ?? 0) . ' fragmente RAG.')
                : (string) ($res['error'] ?? 'Niciun site activ sau toate eșuate'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'library_add_sites') {
        ApiBootstrap::beginBoundedJsonWork(900);
        ApiBootstrap::releaseSession();
        $sites = is_array($payload['sites'] ?? null) ? $payload['sites'] : [];
        if ($sites !== []) {
            (new AiUserSitesConfigService($siteRoot))->save($sites);
        }
        $res = (new AiSiteMissionRunnerService($siteRoot))->run([]);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('+' . (int) ($res['fragments_total'] ?? 0) . ' fragmente stocate.')
                : (string) ($res['error'] ?? 'Eșec'),
            'data' => $res,
        ], !empty($res['ok']) ? 200 : 422);
    }

    if ($action === 'market_check_robots') {
        $url = trim((string) ($payload['url'] ?? ''));
        $src = new AiMarketResearchSourceService($siteRoot);
        ApiBootstrap::json(['success' => true, 'data' => $src->checkRobots($url)]);
    }

    if ($action === 'market_validate_entry') {
        $uuid = trim((string) ($payload['entry_uuid'] ?? ''));
        $status = trim((string) ($payload['status_validare'] ?? 'validat_manual'));
        $store = new AiMarketResearchStore(null, $siteRoot);
        $ok = $store->updateValidationStatus($uuid, $status);
        ApiBootstrap::json([
            'success' => $ok,
            'message' => $ok ? 'Status actualizat.' : 'Intrare negăsită sau status invalid.',
        ], $ok ? 200 : 404);
    }

    if ($action === 'record_human_action') {
        $logId = (int) ($payload['log_id'] ?? 0);
        $humanAction = trim((string) ($payload['human_action'] ?? ''));
        $ok = $interactionLog()->recordHumanAction($logId, $humanAction, $userId > 0 ? $userId : null);
        ApiBootstrap::json([
            'success' => $ok,
            'message' => $ok ? 'Acțiune umană înregistrată.' : 'Log inexistent sau acțiune invalidă.',
            'data' => ['log_id' => $logId, 'action' => $humanAction],
        ], $ok ? 200 : 422);
    }

    if ($action === 'aiwork_feedback') {
        $sourceType = trim((string) ($payload['source_type'] ?? ''));
        $sourceId = trim((string) ($payload['source_id'] ?? ''));
        $rating = trim((string) ($payload['rating'] ?? 'bad'));
        $note = trim((string) ($payload['note'] ?? ''));
        if ($sourceType === '' || $sourceId === '') {
            ApiBootstrap::json(['success' => false, 'message' => 'Lipsește source_type sau source_id.'], 422);
        }
        $ok = $aiWorkFeed->recordFeedback($sourceType, $sourceId, $rating, $note, $userId > 0 ? $userId : null);
        ApiBootstrap::json([
            'success' => $ok,
            'message' => $ok ? 'Feedback salvat — AI Work va folosi nota pentru învățare.' : 'Nu s-a putut salva feedback-ul.',
            'data' => [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'rating' => $rating,
            ],
        ], $ok ? 200 : 422);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('ai_rag_endpoint', $exception);
}
