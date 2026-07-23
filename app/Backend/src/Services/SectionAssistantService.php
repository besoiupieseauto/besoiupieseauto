<?php



declare(strict_types=1);



namespace Besoiu\Services;



use Besoiu\Core\Auth\AdminChatPermissionGuard;
use Config\Database;

use PDO;

use Throwable;



/**

 * Asistent Composer 2.5 contextual — per secțiune admin (produse, furnizori, import).

 * Utilizatorul descrie liber ce are nevoie; AI mapează la funcțiile deja implementate.

 */

final class SectionAssistantService

{

    private string $projectRoot;

    /** @var array<string, mixed>|null */
    private ?array $llmReadinessCache = null;



    public function __construct(

        ?string $projectRoot = null,

        private ?AiAgentRouterService $router = null,

        private ?AiAgentRegistryService $registry = null,

        private ?LlmRouterService $llm = null,

        private ?AdminOpsAlertsService $alerts = null,

        private ?ComposerRepairAgentService $composer = null,

        private ?SectionAssistantActionService $actions = null,

        private ?SectionAssistantModuleService $modules = null,

        private ?SectionAssistantQueryLogService $queryLog = null,

        private ?SectionAssistantLearnService $learn = null,

        private ?SectionAssistantIntentRouterService $intentRouter = null,

        private ?ChatOrganismOrchestrator $organism = null,

        private ?SectionAssistantConversationService $conversation = null,

    ) {

        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);

        $this->router = $router ?? new AiAgentRouterService();

        $this->registry = $registry ?? new AiAgentRegistryService();

        $this->llm = $llm ?? LlmRouterService::create($this->projectRoot);

        $this->alerts = $alerts ?? new AdminOpsAlertsService();

        $this->composer = $composer ?? new ComposerRepairAgentService($this->projectRoot);

        $this->actions = $actions ?? new SectionAssistantActionService($this->projectRoot);

        $this->modules = $modules ?? new SectionAssistantModuleService();

        $this->queryLog = $queryLog ?? new SectionAssistantQueryLogService();

        $this->learn = $learn ?? new SectionAssistantLearnService($this->projectRoot);

        $this->intentRouter = $intentRouter ?? new SectionAssistantIntentRouterService($this->projectRoot, $this->llm);

        $this->organism = $organism ?? ChatOrganismOrchestrator::create($this->projectRoot);

        $this->conversation = $conversation ?? new SectionAssistantConversationService($this->projectRoot, $this->llm);

    }



    /** @return list<string> */

    public static function enabledSections(): array

    {

        return ['produse', 'furnizori', 'import', 'categorii', 'scraper', 'comenzi', 'adaos', 'comunicare', 'dashboard', 'automatizare', 'analiza', 'website', 'sistem', 'clienti'];

    }



    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function learnFromPayload(array $payload): array
    {
        return (new self())->assistLearn([
            'message' => (string) ($payload['message'] ?? ''),
            'admin_section' => (string) ($payload['admin_section'] ?? ''),
            'path' => (string) ($payload['path'] ?? ''),
            'query_id' => (string) ($payload['query_id'] ?? ''),
            'conversation_history' => is_array($payload['conversation_history'] ?? null) ? $payload['conversation_history'] : [],
            'session_id' => (string) ($payload['session_id'] ?? ''),
        ]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function assistFromPayload(array $payload): array
    {
        return (new self())->assist([
            'message' => (string) ($payload['message'] ?? ''),
            'admin_section' => (string) ($payload['admin_section'] ?? ''),
            'path' => (string) ($payload['path'] ?? ''),
            'alert_params' => is_array($payload['alert_params'] ?? null) ? $payload['alert_params'] : [],
            'execute_action' => !empty($payload['execute_action']) || !empty($payload['confirm_execute']),
            'confirm_execute' => !empty($payload['confirm_execute']),
            'pending_action' => is_array($payload['pending_action'] ?? null) ? $payload['pending_action'] : null,
            'selected_products' => is_array($payload['selected_products'] ?? null) ? $payload['selected_products'] : [],
            'conversation_history' => is_array($payload['conversation_history'] ?? null) ? $payload['conversation_history'] : [],
            'session_id' => (string) ($payload['session_id'] ?? ''),
        ]);
    }



    /** @param array<string, mixed> $input @return array<string, mixed> */

    public function assist(array $input): array

    {

        $section = $this->normalizeSection((string) ($input['admin_section'] ?? ''));

        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim((string) ($input['message'] ?? '')));

        if ($message === '') {

            return ['ok' => false, 'http' => 422, 'message' => 'Scrie ce ai nevoie (categorii, produse, template).'];

        }



        $catalog = $this->sectionCatalog()[$section] ?? $this->sectionCatalog()['produse'];

        $route = $this->router->resolve([

            'admin_section' => $section,

            'path' => (string) ($input['path'] ?? ''),

        ]);



        $context = $this->collectSectionContext($section);

        $conversationHistory = $this->normalizeConversationHistory($input['conversation_history'] ?? []);

        $sessionId = trim((string) ($input['session_id'] ?? ''));

        if ($sessionId === '') {

            $sessionId = bin2hex(random_bytes(8));

        }

        $context['conversation_thread'] = $conversationHistory;

        $input['conversation_history'] = $conversationHistory;

        $input['session_id'] = $sessionId;

        $wantsAction = SectionAssistantActionService::messageLooksLikeAction($message)
            || !empty($input['execute_action'])
            || !empty($input['confirm_execute'])
            || is_array($input['pending_action'] ?? null);
        if ($wantsAction || $this->messageWantsCatalogInventory($message) || $this->messageWantsCatalogStructure($message)) {
            $context = $this->withCatalogData($context, $this->messageWantsCatalogStructure($message));
        }

        $alertsFeed = $this->alerts->summary();

        $repairResult = null;



        if ($this->messageMentionsFix($message)) {
            $fixGuard = AdminChatPermissionGuard::fromSession();
            if (!$fixGuard->can('chat.repair')) {
                $repairResult = ['success' => false, 'message' => 'Nu ai permisiunea de reparații sistem din chat.'];
            } else {
                $code = $this->detectAlertCode($message, $alertsFeed);
                if ($code !== '') {
                    $repairResult = $this->composer->fixAlert($code, (array) ($input['alert_params'] ?? []), true);
                }
            }
        }



        $plan = null;

        $actionInput = array_merge($input, [
            'execute_action' => !empty($input['execute_action'])
                || !empty($input['confirm_execute'])
                || $this->messageRequestsImmediateAction($message),
        ]);
        $actionPlan = $this->actions->tryHandle($message, $section, $context, $actionInput);
        if ($actionPlan !== null) {
            $plan = $actionPlan;
        } elseif (($organismPlan = $this->organism->tryResolve($message, $section, $context, $input)) !== null) {
            $plan = $organismPlan;
        } elseif ($this->messageWantsCatalogStructure($message)) {
            $context = $this->withCatalogData($context, true);
            $plan = $this->buildCatalogStructureAnswer($context, $catalog, $message);
        } elseif (($convPlan = $this->conversation->tryHandle($message, $section, $catalog, $context, $conversationHistory)) !== null) {
            if (!empty($convPlan['attach_capabilities'])) {
                unset($convPlan['attach_capabilities']);
                $context = $this->withCatalogData($context, false);
                $capPlan = $this->buildSectionCapabilitiesAnswer($section, $catalog, $context, $message);
                $convPlan['cheat_sheet'] = $capPlan['cheat_sheet'];
            }
            $plan = $convPlan;
        } elseif (($codePlan = $this->modules->tryProductCodeLookup($message)) !== null) {
            $plan = $codePlan;
        } elseif (($modulePlan = $this->modules->tryAnswer($message, $section, $context)) !== null) {
            $plan = $modulePlan;
        } elseif (($intentPlan = $this->intentRouter->resolve($message, $section, $context, $this->modules)) !== null) {
            $plan = $intentPlan;
        } elseif (($learnedHandler = $this->learn->matchLearned($message)) !== null
            && ($learnedPlan = $this->modules->dispatchByHandlerKey($learnedHandler, $message, $section, $context)) !== null) {
            $learnedPlan['source'] = 'learned_pattern';
            $plan = $learnedPlan;
        } elseif ($this->messageWantsSectionCapabilities($message)) {
            $context = $this->withCatalogData($context, false);
            $plan = $this->buildSectionCapabilitiesAnswer($section, $catalog, $context, $message);
        } elseif ($this->messageWantsCatalogInventory($message)) {

            $context = $this->withCatalogData($context, false);
            $plan = $this->buildCatalogInventoryAnswer($context, $this->sectionCatalog()['produse'], $message);

        } elseif (($relaxedPlan = $this->modules->tryAnswerRelaxed($message, $section, $context)) !== null) {

            $plan = $relaxedPlan;

        } else {

            if ($this->messageLooksLikeLearnableInventory($message)) {

                $plan = $this->learn->buildUnknownLearnablePlan($message, $section);

            } else {

                $plan = $this->llmAssistWithFallback($section, $message, $catalog, $context, $route, $alertsFeed, $repairResult);

            }

        }



        $chatGuard = AdminChatPermissionGuard::fromSession();
        $plan = $chatGuard->filterPlan($plan, array_merge($input, ['message' => $message]));

        $source = (string) ($plan['source'] ?? 'heuristic');

        if ($source === 'client_orders') {
            $plan = $this->maybeEnhanceClientOrdersPlanWithLlm($plan, $message, $route);
            $source = (string) ($plan['source'] ?? 'client_orders');
        }

        $cursorReady = $this->getLlmReadiness();

        $response = [

            'ok' => ($plan['source'] ?? '') !== 'chat_permission_denied',

            'http' => ($plan['source'] ?? '') === 'chat_permission_denied' ? 403 : 200,

            'section' => $section,

            'session_id' => $sessionId,

            'agent_slug' => (string) ($route['slug'] ?? 'context-master'),

            'agent_name' => (string) ($route['name'] ?? ''),

            'model' => (string) ($plan['model'] ?? $this->llm->model()),

            'llm_used' => in_array($source, ['composer-2.5', 'openai', 'groq', 'client_orders+composer-2.5'], true)
                || str_starts_with($source, 'llm_intent+')
                || str_starts_with($source, 'conversation+'),

            'llm_status' => $this->buildLlmStatusBlock($source, $plan['llm_error'] ?? null, $cursorReady),

            'reply' => (string) ($plan['reply_ro'] ?? ''),

            'cheat_sheet' => is_array($plan['cheat_sheet'] ?? null) ? $plan['cheat_sheet'] : null,

            'action' => is_array($plan['action'] ?? null) ? $plan['action'] : null,

            'pending_action' => is_array($plan['pending_action'] ?? null) ? $plan['pending_action'] : null,

            'intent' => (string) ($plan['intent'] ?? 'explain'),

            'suggestions' => is_array($plan['suggestions'] ?? null) ? $plan['suggestions'] : [],

            'next_steps' => is_array($plan['next_steps'] ?? null) ? $plan['next_steps'] : [],

            'organism' => is_array($plan['organism'] ?? null) ? $plan['organism'] : null,

            'features_available' => $catalog['features'] ?? [],

            'catalog_snapshot' => $context['catalog_snapshot'] ?? null,

            'composer_repair' => $repairResult,

            'generated_at' => date('c'),

        ];



        $queryFeedback = $this->queryLog->recordTurn($message, $input, $plan, $context, $response);

        $response['query_feedback'] = $queryFeedback;

        $response['conversation_history'] = array_merge($conversationHistory, [[

            'role' => 'user',

            'message' => $message,

            'at' => date('c'),

        ], [

            'role' => 'assistant',

            'message' => mb_substr((string) ($response['reply'] ?? ''), 0, 500, 'UTF-8'),

            'source' => $source,

            'outcome' => (string) ($queryFeedback['status'] ?? ''),

            'at' => date('c'),

        ]]);



        if (is_array($response['cheat_sheet'])) {

            $response['cheat_sheet']['query_feedback'] = $queryFeedback;

        }



        return $response;

    }



    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function assistLearn(array $input): array
    {
        $section = $this->normalizeSection((string) ($input['admin_section'] ?? ''));
        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim((string) ($input['message'] ?? '')));
        $queryId = trim((string) ($input['query_id'] ?? ''));

        if ($message === '' && $queryId !== '') {
            $logged = $this->queryLog->findById($queryId);
            if (is_array($logged)) {
                $message = trim((string) ($logged['message'] ?? ''));
                if ($section === 'produse' && !empty($logged['section'])) {
                    $section = $this->normalizeSection((string) $logged['section']);
                }
            }
        }

        if ($message === '') {
            return ['ok' => false, 'http' => 422, 'message' => 'Lipseste intrebarea pentru invatare.'];
        }

        $learnGuard = AdminChatPermissionGuard::fromSession();
        if (!$learnGuard->can('chat.learn')) {
            $denied = $learnGuard->denialPlan('chat.learn', 'Nu ai permisiunea de antrenare chat (Învață).');
            return [
                'ok' => false,
                'http' => 403,
                'message' => (string) ($denied['reply'] ?? ''),
                'reply' => (string) ($denied['reply'] ?? ''),
                'data' => $denied,
            ];
        }

        $catalog = $this->sectionCatalog()[$section] ?? $this->sectionCatalog()['produse'];
        $route = $this->router->resolve([
            'admin_section' => $section,
            'path' => (string) ($input['path'] ?? ''),
        ]);
        $context = $this->collectSectionContext($section);
        $conversationHistory = $this->normalizeConversationHistory($input['conversation_history'] ?? []);
        $sessionId = trim((string) ($input['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = bin2hex(random_bytes(8));
        }
        $context['conversation_thread'] = $conversationHistory;
        $input['conversation_history'] = $conversationHistory;
        $input['session_id'] = $sessionId;
        $input['message'] = $message;

        $plan = $this->learn->learnAndAnswer($message, $section, $context, $this->modules);
        if ($plan === null) {
            $plan = $this->learn->buildUnknownLearnablePlan($message, $section);
            $plan['reply_ro'] = 'Nu am gasit inca o sursa live pentru asta. Reformuleaza sau deschide modulul din admin.';
            $plan['can_learn'] = false;
            $plan['source'] = 'learn_failed';
        }

        $source = (string) ($plan['source'] ?? 'learned_adapter');
        $cursorReady = $this->getLlmReadiness();

        $response = [
            'ok' => true,
            'http' => 200,
            'section' => $section,
            'session_id' => $sessionId,
            'agent_slug' => (string) ($route['slug'] ?? 'context-master'),
            'agent_name' => (string) ($route['name'] ?? ''),
            'model' => (string) ($plan['model'] ?? $this->llm->model()),
            'llm_used' => false,
            'llm_status' => $this->buildLlmStatusBlock($source, null, $cursorReady),
            'reply' => (string) ($plan['reply_ro'] ?? ''),
            'cheat_sheet' => is_array($plan['cheat_sheet'] ?? null) ? $plan['cheat_sheet'] : null,
            'action' => is_array($plan['action'] ?? null) ? $plan['action'] : null,
            'pending_action' => is_array($plan['pending_action'] ?? null) ? $plan['pending_action'] : null,
            'intent' => (string) ($plan['intent'] ?? 'learn'),
            'suggestions' => is_array($plan['suggestions'] ?? null) ? $plan['suggestions'] : [],
            'next_steps' => is_array($plan['next_steps'] ?? null) ? $plan['next_steps'] : [],
            'features_available' => $catalog['features'] ?? [],
            'catalog_snapshot' => $context['catalog_snapshot'] ?? null,
            'composer_repair' => null,
            'generated_at' => date('c'),
            'learned' => !empty($plan['learned']),
            'parent_query_id' => $queryId !== '' ? $queryId : null,
        ];

        $queryFeedback = $this->queryLog->recordTurn('[invata] ' . $message, $input, $plan, $context, $response);
        $response['query_feedback'] = $queryFeedback;
        $response['conversation_history'] = array_merge($conversationHistory, [[
            'role' => 'user',
            'message' => $message,
            'at' => date('c'),
        ], [
            'role' => 'assistant',
            'message' => mb_substr((string) ($response['reply'] ?? ''), 0, 500, 'UTF-8'),
            'source' => $source,
            'outcome' => (string) ($queryFeedback['status'] ?? ''),
            'at' => date('c'),
        ]]);

        if (is_array($response['cheat_sheet'])) {
            $response['cheat_sheet']['query_feedback'] = $queryFeedback;
        }

        if ($queryId !== '') {
            $this->queryLog->markLearnedFrom($queryId, $source);
        }

        return $response;
    }



    /** @return array<string, mixed> */

    private function collectSectionContext(string $section): array

    {

        $ctx = [

            'section' => $section,

            'counts' => [],

            'alerts' => $this->alerts->summary(),

            'llm_readiness' => $this->getLlmReadiness(),

            'ai_agents' => [],

            'catalog_snapshot' => null,

        ];



        try {

            foreach ($this->registry->listAgents() as $agent) {

                $ctx['ai_agents'][] = [

                    'slug' => (string) ($agent['slug'] ?? ''),

                    'name' => (string) ($agent['name'] ?? ''),

                ];

            }

        } catch (Throwable) {

            // registry opțional

        }

        try {
            $budgetFile = dirname(__DIR__, 2) . '/system/api_token_budget.php';
            if (is_file($budgetFile)) {
                require_once $budgetFile;
                if (function_exists('api_token_budget_hub_snapshot')) {
                    $ctx['api_token_budget'] = api_token_budget_hub_snapshot(Database::getDB());
                }
            }
        } catch (Throwable) {
            // buget opțional
        }



        try {

            $pdo = Database::getDB();

            $ctx['counts'] = match ($section) {

                'produse', 'categorii' => [

                    'produse_total' => $this->scalarCount($pdo, 'SELECT COUNT(*) FROM produse WHERE COALESCE(status,1)<>0'),

                    'produse_fara_imagine' => $this->scalarCount($pdo, "SELECT COUNT(*) FROM produse WHERE COALESCE(status,1)<>0 AND (pImages IS NULL OR pImages='' OR pImages='[]')"),

                    'import_queue' => $this->scalarCount($pdo, "SELECT COUNT(*) FROM import_produse WHERE status='pending'"),

                    'categorii_distinct' => $this->scalarCount($pdo, "SELECT COUNT(DISTINCT pCategory) FROM produse WHERE COALESCE(status,1)<>0 AND pCategory IS NOT NULL AND TRIM(pCategory)<>'' AND pCategory<>'0'"),

                ],

                'furnizori' => [

                    'furnizori_total' => $this->scalarCount($pdo, 'SELECT COUNT(*) FROM furnizori'),

                ],

                'import' => [

                    'import_pending' => $this->scalarCount($pdo, "SELECT COUNT(*) FROM import_produse WHERE status='pending'"),

                    'import_failed_jobs' => $this->countImportJobs('error'),

                    'import_running_jobs' => $this->countImportJobs('running'),

                ],

                default => [],

            };



            if (in_array($section, ['produse', 'categorii'], true)) {

                $ctx['catalog_snapshot'] = [

                    'categories_top' => $this->fetchTopCategories($pdo),

                    'sample_products' => $this->fetchSampleProducts($pdo, 50),

                ];

            }

        } catch (Throwable) {

            // context parțial OK

        }



        return $ctx;

    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function withCatalogData(array $ctx, bool $fullProducts = false): array
    {
        try {
            $pdo = Database::getDB();
            $total = $this->scalarCount($pdo, 'SELECT COUNT(*) FROM produse WHERE COALESCE(status,1)<>0');
            $productLimit = $fullProducts ? min(200, max(1, $total)) : min(50, max(1, $total));

            $ctx['counts'] = array_merge(is_array($ctx['counts'] ?? null) ? $ctx['counts'] : [], [
                'produse_total' => $total,
                'produse_fara_imagine' => $this->scalarCount($pdo, "SELECT COUNT(*) FROM produse WHERE COALESCE(status,1)<>0 AND (pImages IS NULL OR pImages='' OR pImages='[]')"),
                'import_queue' => $this->scalarCount($pdo, "SELECT COUNT(*) FROM import_produse WHERE status='pending'"),
                'categorii_distinct' => $this->scalarCount($pdo, "SELECT COUNT(DISTINCT pCategory) FROM produse WHERE COALESCE(status,1)<>0 AND pCategory IS NOT NULL AND TRIM(pCategory)<>'' AND pCategory<>'0'"),
                'subcategorii_distinct' => SectionAssistantCatalogQueries::countSubcategories($pdo),
            ]);
            $ctx['catalog_snapshot'] = [
                'categories_top' => $this->fetchTopCategories($pdo),
                'category_tree' => SectionAssistantCatalogQueries::categoryTree($pdo),
                'sample_products' => SectionAssistantCatalogQueries::activeProducts($pdo, $productLimit),
            ];
        } catch (Throwable) {
            // date parțiale OK
        }

        return $ctx;
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function mapProductRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['randomn_id'] ?? ''));
            $name = trim((string) ($row['pName'] ?? ''));
            $out[] = [
                'name' => $name !== '' ? $name : 'Fără denumire',
                'brand' => trim((string) ($row['pBrand'] ?? '')) ?: '-',
                'code' => trim((string) ($row['pCode'] ?? '')) ?: '-',
                'category' => trim((string) ($row['pCategory'] ?? '')) ?: '-',
                'subcategory' => trim((string) ($row['pSubcategory'] ?? '')) ?: '-',
                'price' => $this->formatRon((float) ($row['pPrice'] ?? 0)),
                'badge' => trim((string) ($row['pBadge'] ?? '')) ?: '-',
                'edit_url' => $id !== '' ? '/admin/editproduse?id=' . rawurlencode($id) : '/admin/product',
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $catalog @return array<string, mixed> */
    private function buildCatalogStructureAnswer(array $context, array $catalog, string $message): array
    {
        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];
        $snapshot = is_array($context['catalog_snapshot'] ?? null) ? $context['catalog_snapshot'] : [];
        $total = (int) ($counts['produse_total'] ?? 0);
        $catCount = (int) ($counts['categorii_distinct'] ?? 0);
        $subCount = (int) ($counts['subcategorii_distinct'] ?? 0);
        $queue = (int) ($counts['import_queue'] ?? 0);

        $tree = is_array($snapshot['category_tree'] ?? null) ? $snapshot['category_tree'] : [];
        $samples = is_array($snapshot['sample_products'] ?? null) ? $snapshot['sample_products'] : [];
        $productRows = $this->mapProductRows($samples);

        $stats = [
            ['key' => 'total', 'label' => 'Produse active', 'value' => (string) $total],
            ['key' => 'categories', 'label' => 'Categorii', 'value' => (string) $catCount],
            ['key' => 'subcategories', 'label' => 'Subcategorii', 'value' => (string) $subCount],
            ['key' => 'import_queue', 'label' => 'Coadă import', 'value' => (string) $queue, 'warn' => $queue > 0],
        ];

        $capabilities = $this->parseSectionCapabilities($catalog);
        $composerCmds = $this->modules->composerCommands();

        return [
            'source' => 'catalog_structure',
            'reply_ro' => sprintf(
                '%d produse in %d categorii si %d subcategorii.',
                $total,
                $catCount,
                $subCount
            ),
            'intent' => 'categories',
            'cheat_sheet' => [
                'kind' => 'catalog_structure',
                'title' => 'Categorii, subcategorii si produse',
                'subtitle' => 'Structura completa din baza de date',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => $stats,
                'category_tree' => $tree,
                'products' => $productRows,
                'products_note' => $total > 0
                    ? ($total <= count($productRows) ? 'Toate produsele active sunt listate mai jos.' : 'Afisate ' . count($productRows) . ' din ' . $total . ' produse.')
                    : 'Niciun produs activ.',
                'capabilities' => $capabilities,
                'composer_commands' => $composerCmds,
                'shortcuts' => [
                    ['label' => 'Lista produse', 'url' => '/admin/product'],
                    ['label' => 'Categorii admin', 'url' => '/admin/categorii'],
                    ['label' => 'Adauga produs', 'url' => '/admin/addproduse'],
                ],
                'hints' => $queue > 0 ? [$queue . ' produse in coada import (inca nepublicate).'] : [],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @param array<string, mixed> $catalog @param array<string, mixed> $context @return array<string, mixed> */
    private function buildSectionCapabilitiesAnswer(string $section, array $catalog, array $context, string $message = ''): array
    {
        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];
        $capabilities = SectionAssistantAdminCatalog::flatFeatures(
            $this->messageWantsFullCatalog($message) ? 'all' : $section
        );
        $capRows = [];
        foreach ($capabilities as $cap) {
            $capRows[] = [
                'label' => (string) ($cap['label'] ?? ''),
                'url' => (string) ($cap['url'] ?? '/admin/product'),
                'description' => (string) ($cap['phase'] ?? '') . ' — ' . (string) ($cap['description'] ?? ''),
            ];
        }
        $composerCmds = $this->modules->composerCommands();
        $agents = is_array($context['ai_agents'] ?? null) ? $context['ai_agents'] : [];

        $agentRows = [];
        foreach (array_slice($agents, 0, 12) as $agent) {
            if (!is_array($agent)) {
                continue;
            }
            $agentRows[] = [
                'name' => (string) ($agent['name'] ?? ''),
                'slug' => (string) ($agent['slug'] ?? ''),
            ];
        }

        $total = (int) ($counts['produse_total'] ?? 0);

        return [
            'source' => 'section_capabilities',
            'reply_ro' => 'Functii disponibile in sectiunea ' . ($catalog['label'] ?? $section)
                . ' si comenzi Composer pe care le inteleg.',
            'intent' => 'explain',
            'cheat_sheet' => [
                'kind' => 'section_capabilities',
                'title' => 'Functii sectiune: ' . ($catalog['label'] ?? $section),
                'subtitle' => 'Tot ce pot face Composer si admin-ul aici',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'features', 'label' => 'Functii admin', 'value' => (string) count($capRows)],
                    ['key' => 'commands', 'label' => 'Comenzi Composer', 'value' => (string) count($composerCmds)],
                    ['key' => 'agents', 'label' => 'Agenti AI', 'value' => (string) count($agentRows)],
                    ['key' => 'produse', 'label' => 'Produse online', 'value' => (string) $total],
                ],
                'capabilities' => $capRows,
                'composer_commands' => $composerCmds,
                'agents' => $agentRows,
                'shortcuts' => array_slice($capRows, 0, 8),
                'hints' => [
                    'Scrie liber in romana — ex: ce produse sunt online, badge HOT toate, categorii si subcategorii.',
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @param array<string, mixed> $catalog @return list<array{label:string,url:string,description:string}> */
    private function parseSectionCapabilities(array $catalog): array
    {
        $out = [];
        foreach ($catalog['features'] ?? [] as $feature) {
            $line = trim((string) $feature);
            if ($line === '') {
                continue;
            }
            $url = '/admin/product';
            if (preg_match('#(/admin/[a-z0-9\-/]+)#i', $line, $m)) {
                $url = $m[1];
            }
            $label = $line;
            if (preg_match('/^(.+?)\s*[—–-]\s*/u', $line, $m)) {
                $label = trim($m[1]);
            }
            $out[] = [
                'label' => $label,
                'url' => $url,
                'description' => $line,
            ];
        }

        return $out;
    }

    /** @return list<array{label:string,example:string}> */
    private function composerCommandCatalog(): array
    {
        return $this->modules->composerCommands();
    }

    private function messageWantsFullCatalog(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(tot|toate|full|complet\w*|admin|sistem|faze?|module|ultim\w*)\b/u', $lower);
    }

    /** @return array<string, array<string, mixed>> */
    private function sectionCatalog(): array
    {
        $all = SectionAssistantAdminCatalog::all();
        $out = [];
        foreach ($all as $key => $mod) {
            $out[$key] = [
                'label' => $mod['label'],
                'phase' => $mod['phase'],
                'features' => $mod['features'],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fetchTopCategories(PDO $pdo): array

    {

        try {

            $stmt = $pdo->query(

                "SELECT TRIM(pCategory) AS category, COUNT(*) AS cnt

                 FROM produse

                 WHERE COALESCE(status,1)<>0

                   AND pCategory IS NOT NULL AND TRIM(pCategory)<>'' AND pCategory<>'0'

                 GROUP BY TRIM(pCategory)

                 ORDER BY cnt DESC

                 LIMIT 8"

            );



            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        } catch (Throwable) {

            return [];

        }

    }



    /** @return list<array<string, mixed>> */

    private function fetchSampleProducts(PDO $pdo, int $limit = 10): array

    {

        $limit = max(1, min(50, $limit));

        try {

            $stmt = $pdo->query(

                "SELECT randomn_id, pName, pBrand, pCode, pCategory, pPrice

                 FROM produse

                 WHERE COALESCE(status,1)<>0

                 ORDER BY id DESC

                 LIMIT {$limit}"

            );



            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        } catch (Throwable) {

            return [];

        }

    }



    private function scalarCount(PDO $pdo, string $sql): int

    {

        try {

            $stmt = $pdo->query($sql);



            return (int) ($stmt ? $stmt->fetchColumn() : 0);

        } catch (Throwable) {

            return 0;

        }

    }



    private function countImportJobs(string $status): int

    {

        $dir = $this->projectRoot . '/admin/storage/imports/jobs';

        if (!is_dir($dir)) {

            return 0;

        }

        $n = 0;

        foreach (glob($dir . '/*.json') ?: [] as $path) {

            if (str_ends_with($path, '.state.json')) {

                continue;

            }

            $meta = json_decode((string) file_get_contents($path), true);

            if (is_array($meta) && (string) ($meta['status'] ?? '') === $status) {

                ++$n;

            }

        }



        return $n;

    }



    /** @param array<string, mixed> $context @param array<string, mixed> $catalog @return array<string, mixed> */
    private function buildCatalogInventoryAnswer(array $context, array $catalog, string $message = ''): array
    {
        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];
        $snapshot = is_array($context['catalog_snapshot'] ?? null) ? $context['catalog_snapshot'] : [];
        $agents = is_array($context['ai_agents'] ?? null) ? $context['ai_agents'] : [];
        $listFocus = $this->messageWantsProductList($message);

        $total = (int) ($counts['produse_total'] ?? 0);
        $noImg = (int) ($counts['produse_fara_imagine'] ?? 0);
        $queue = (int) ($counts['import_queue'] ?? 0);
        $catCount = (int) ($counts['categorii_distinct'] ?? 0);

        $categories = is_array($snapshot['categories_top'] ?? null) ? $snapshot['categories_top'] : [];
        $samples = is_array($snapshot['sample_products'] ?? null) ? $snapshot['sample_products'] : [];

        $productRows = $this->mapProductRows($samples);
        foreach ($productRows as &$pr) {
            unset($pr['subcategory'], $pr['badge']);
        }
        unset($pr);

        $categoryRows = [];
        foreach ($categories as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['category'] ?? ''));
            if ($name === '') {
                continue;
            }
            $categoryRows[] = [
                'name' => $name,
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        $stats = [
            ['key' => 'total', 'label' => 'Produse active', 'value' => (string) $total],
            ['key' => 'categories', 'label' => 'Categorii', 'value' => (string) $catCount],
            ['key' => 'no_image', 'label' => 'Fără imagine', 'value' => (string) $noImg, 'warn' => $noImg > 0],
            ['key' => 'import_queue', 'label' => 'Coadă import', 'value' => (string) $queue, 'warn' => $queue > 0],
        ];

        $shortcuts = [
            ['label' => 'Lista produse', 'url' => '/admin/product'],
            ['label' => 'Categorii', 'url' => '/admin/categorii'],
            ['label' => 'Import CSV', 'url' => '/admin/import'],
        ];
        if ($queue > 0) {
            $shortcuts[] = ['label' => 'Coada import', 'url' => '/admin/importreview'];
        }
        if ($noImg > 0) {
            $shortcuts[] = ['label' => 'Scraper imagini', 'url' => '/admin/scraper'];
        }

        $hints = [];
        if ($queue > 0) {
            $hints[] = $queue === 1
                ? '1 produs așteaptă în coada import.'
                : $queue . ' produse așteaptă în coada import.';
        }
        if ($total === 0) {
            $hints[] = 'Magazinul nu are încă produse publicate. Adaugă manual sau importă din CSV.';
        }

        $title = $this->messageMentionsOnline($message)
            ? 'Produse online acum'
            : ($listFocus ? 'Lista produse disponibile' : 'Inventar catalog');

        $cheatSheet = [
            'kind' => 'catalog_inventory',
            'title' => $title,
            'subtitle' => 'Date live din baza de date Besoiu',
            'updated_at' => date('d.m.Y H:i'),
            'stats' => $stats,
            'categories' => $categoryRows,
            'products' => $productRows,
            'products_note' => $total > count($productRows) && count($productRows) > 0
                ? 'Afișate ultimele ' . count($productRows) . ' din ' . $total . ' produse.'
                : ($total === 0 ? 'Niciun produs activ în magazin.' : null),
            'shortcuts' => $shortcuts,
            'hints' => $hints,
        ];

        if (!$listFocus && $agents !== []) {
            $cheatSheet['agents'] = array_map(static function (array $agent): array {
                return [
                    'name' => (string) ($agent['name'] ?? ''),
                    'slug' => (string) ($agent['slug'] ?? ''),
                ];
            }, array_slice($agents, 0, 8));
        }

        $replyParts = [];
        if ($total === 0) {
            $replyParts[] = 'Nu exista produse active in magazin.';
        } elseif ($total === 1 && isset($productRows[0])) {
            $replyParts[] = '1 produs activ: ' . $productRows[0]['name'] . ' (' . $productRows[0]['code'] . ').';
        } else {
            $replyParts[] = $total . ' produse active in ' . $catCount . ' categorii.';
        }
        if ($queue > 0) {
            $replyParts[] = $queue . ' in coada import.';
        }

        return [
            'source' => 'catalog_db',
            'reply_ro' => implode(' ', $replyParts),
            'cheat_sheet' => $cheatSheet,
            'intent' => 'explain',
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    private function formatRon(float $price): string
    {
        if ($price <= 0) {
            return '-';
        }

        return number_format($price, 2, ',', '.') . ' RON';
    }

    private function messageWantsProductList(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(lista|list[aă]|disponibil\w*|arata|arat[aă]|enumera)\b/u', $lower);
    }



    /** @param array<string, mixed> $catalog @param array<string, mixed> $context @param array<string, mixed> $route @param array<string, mixed> $alerts @param array<string, mixed>|null $repair @return array<string, mixed> */

    private function llmAssistWithFallback(

        string $section,

        string $message,

        array $catalog,

        array $context,

        array $route,

        array $alerts,

        ?array $repair,

    ): array {

        if (($relaxedPlan = $this->modules->tryAnswerRelaxed($message, $section, $context)) !== null) {
            return $relaxedPlan;
        }

        if ($this->messageLooksLikeLearnableInventory($message)) {
            return $this->learn->buildUnknownLearnablePlan($message, $section);
        }

        $agentSlug = (string) ($route['slug'] ?? 'context-master');
        $agentBundle = $this->registry->bundleForComposer($agentSlug, $message);

        $system = <<<'PROMPT'

Ești Composer 2.5 — asistent admin Besoiu Piese Auto. Utilizatorul descrie liber ce vrea (categorii, produse, template câmpuri, import, furnizori).

Mapezi cererea la funcțiile DEJA implementate în admin — nu inventa API-uri noi.

Folosește context.counts și context.catalog_snapshot când există — nu inventa numere.

Dacă există conversation_thread, folosește mesajele anterioare ca context (cod produs, cereri repetate).

Folosește agent_bundle.runtime_brief ca memorie proiect (agenți AI Besoiu, fluxuri, politici).

Folosește context.api_token_budget pentru Scrape.do și RapidAPI TecDoc — aceleași cifre ca în Setări → Tokeni API (cereri rămase, credite/cerere). Nu inventa cote.

Folosește agent_bundle.rag_lines ca fragmente relevante din biblioteca operator — nu le ignora.

Pentru comenzi, facturi, AWB, coș, categorii, furnizori — preferă răspunsuri live SQL (cheat_sheet); nu inventa liste sau totaluri.

Răspunde DOAR JSON valid:

{

  "reply_ro": "răspuns clar în română, 2-6 propoziții",

  "intent": "configure_products|import|suppliers|categories|fix|navigate|explain|orders",

  "suggestions": [

    {"type":"navigate","label":"...","url":"/admin/..."},

    {"type":"category","name":"...","reason":"..."},

    {"type":"product_template","name_pattern":"...","fields":["pName","pBrand","pCode","pCategory","pPrice","pImages"],"notes":"..."},

    {"type":"feature","label":"...","path":"/admin/..."}

  ],

  "next_steps": ["pas concret 1", "pas 2"]

}

Reguli: română; prețuri în RON; menționează pașii din UI existent; dacă e eroare import/job — sugerează Composer Repair.

PROMPT;



        $user = json_encode([

            'section' => $section,

            'user_message' => $message,

            'conversation_thread' => array_slice(is_array($context['conversation_thread'] ?? null) ? $context['conversation_thread'] : [], -10),

            'agent' => $route,

            'agent_bundle' => $agentBundle,

            'features' => $catalog['features'] ?? [],

            'context' => $context,

            'alerts_status' => $alerts['status'] ?? 'ok',

            'alert_items' => array_slice(is_array($alerts['items'] ?? null) ? $alerts['items'] : [], 0, 5),

            'composer_repair_done' => $repair,

        ], JSON_UNESCAPED_UNICODE);



        $llmError = null;
        $routerReady = $this->getLlmReadiness();

        if (!empty($routerReady['ready']) && $this->llm->isConfigured()) {

            $result = $this->llm->complete($system, (string) $user, 0.35, 18, 'section_assistant');

            if (!empty($result['ok']) && trim((string) ($result['content'] ?? '')) !== '') {

                $parsed = $this->parseJson((string) $result['content']);

                if ($parsed !== null) {

                    $parsed['source'] = (string) ($result['routed_via'] ?? $this->llm->primaryProvider());

                    $parsed['model'] = $this->llm->model();



                    return $parsed;

                }



                return [

                    'source' => (string) ($result['routed_via'] ?? $this->llm->primaryProvider()),

                    'model' => $this->llm->model(),

                    'reply_ro' => trim((string) ($result['content'] ?? '')),

                    'intent' => 'explain',

                    'suggestions' => [],

                    'next_steps' => [],

                ];

            }

            $llmError = $this->sanitizeLlmError((string) ($result['error'] ?? 'Metro LLM eșuat'));
        } elseif (!$this->llm->isConfigured()) {
            $llmError = 'Niciun provider LLM configurat (Ollama, Groq, OpenAI, Gemini, OpenRouter).';
        }



        $fallback = $this->completeViaOpenAiCompatible($system, (string) $user);

        if ($fallback !== '') {

            $parsed = $this->parseJson($fallback);

            if ($parsed !== null) {

                $parsed['source'] = str_contains($this->resolveFallbackKey(), 'gsk_') ? 'groq' : 'openai';

                $parsed['model'] = $this->resolveFallbackModel();



                return $parsed;

            }

        } elseif ($llmError === null && $this->resolveFallbackKey() === '') {

            $llmError = 'Niciun provider LLM disponibil — configurează Ollama, GROQ_KEY sau OPENAI_KEY.';

        }



        if ($this->messageWantsCatalogStructure($message)) {

            return $this->buildCatalogStructureAnswer($this->withCatalogData($context, true), $catalog, $message);

        }

        if (($modulePlan = $this->modules->tryAnswer($message, $section, $context)) !== null) {

            return $modulePlan;

        }

        if ($this->messageWantsSectionCapabilities($message)) {

            return $this->buildSectionCapabilitiesAnswer($section, $catalog, $context, $message);

        }

        if ($this->messageWantsCatalogInventory($message)) {

            return $this->buildCatalogInventoryAnswer(
                $this->withCatalogData($context, false),
                $this->sectionCatalog()['produse'],
                $message
            );

        }



        $heuristic = $this->heuristicAssist($section, $message, $catalog, $context, $this->sanitizeLlmError($llmError));

        if ($fallback !== '' && ($heuristic['reply_ro'] ?? '') !== '') {

            $heuristic['reply_ro'] .= "\n\n" . trim($fallback);

        }



        return $heuristic;

    }



    private function completeViaOpenAiCompatible(string $system, string $user): string

    {

        $guardPath = dirname(__DIR__, 3) . '/system/api_automation_guard.php';
        if (is_file($guardPath)) {
            require_once $guardPath;
            if (!besoiu_api_live_call_allowed('llm')) {
                return '';
            }
        }

        $key = $this->resolveFallbackKey();

        if ($key === '') {

            return '';

        }



        $payload = [

            'model' => $this->resolveFallbackModel(),

            'temperature' => 0.35,

            'max_tokens' => 900,

            'messages' => [

                ['role' => 'system', 'content' => $system],

                ['role' => 'user', 'content' => $user],

            ],

        ];



        $endpoint = str_contains($key, 'gsk_')

            ? 'https://api.groq.com/openai/v1/chat/completions'

            : 'https://api.openai.com/v1/chat/completions';



        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [

            CURLOPT_POST => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 45,

            CURLOPT_HTTPHEADER => [

                'Content-Type: application/json',

                'Authorization: Bearer ' . $key,

            ],

            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),

        ]);

        $raw = curl_exec($ch);

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);



        if (!is_string($raw) || $raw === '' || $httpCode !== 200) {

            return '';

        }



        $json = json_decode($raw, true);

        if (!is_array($json)) {

            return '';

        }



        return trim((string) ($json['choices'][0]['message']['content'] ?? ''));

    }



    private function resolveFallbackKey(): string

    {

        if (function_exists('env')) {

            $groq = trim((string) env('GROQ_KEY', ''));

            if ($groq !== '') {

                return $groq;

            }

            $openai = trim((string) env('OPENAI_KEY', ''));

            if ($openai !== '') {

                return $openai;

            }

        }



        return trim((string) (getenv('GROQ_KEY') ?: getenv('OPENAI_KEY') ?: ''));

    }



    private function resolveFallbackModel(): string

    {

        if (function_exists('env')) {

            $groqModel = trim((string) env('GROQ_MODEL', ''));

            if ($groqModel !== '') {

                return $groqModel;

            }

            $openaiModel = trim((string) env('OPENAI_MODEL', ''));

            if ($openaiModel !== '') {

                return $openaiModel;

            }

        }



        $key = $this->resolveFallbackKey();



        return str_contains($key, 'gsk_') ? 'llama-3.3-70b-versatile' : 'gpt-4o-mini';

    }



    /** @param array<string, mixed> $catalog @param array<string, mixed> $context @return array<string, mixed> */

    private function heuristicAssist(string $section, string $message, array $catalog, array $context, ?string $llmError = null): array

    {

        $lower = mb_strtolower($message, 'UTF-8');

        $suggestions = [];

        $next = [];

        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];

        $readiness = is_array($context['llm_readiness'] ?? null) ? $context['llm_readiness'] : $this->getLlmReadiness();



        if (str_contains($lower, 'categor')) {

            $suggestions[] = ['type' => 'navigate', 'label' => 'Deschide Categorii', 'url' => '/admin/categorii'];

            $next[] = 'Definește ierarhia categorie → subcategorie în /admin/categorii.';

        }

        if (str_contains($lower, 'import') || str_contains($lower, 'furnizor') || str_contains($lower, 'csv')) {

            $suggestions[] = ['type' => 'navigate', 'label' => 'Import CSV / Excel', 'url' => '/admin/import'];

            $next[] = 'Încarcă listele furnizor → Scanează consumabile → Importă pe site (pipeline Scraper).';

        }

        if (str_contains($lower, 'imagine') || str_contains($lower, 'scraper') || str_contains($lower, 'autodoc')) {

            $suggestions[] = ['type' => 'navigate', 'label' => 'Scraper pipeline', 'url' => '/admin/scraper'];

            $next[] = 'Testează Plan 1→3 pe produs, apoi importă cu pipeline activ.';

        }

        if (str_contains($lower, 'template') || str_contains($lower, 'sablon') || str_contains($lower, 'șablon') || str_contains($lower, 'campuri') || str_contains($lower, 'câmpuri')) {

            $suggestions[] = [

                'type' => 'product_template',

                'name_pattern' => 'Denumire + Brand + Cod',

                'fields' => ['pName', 'pBrand', 'pCode', 'pCategory', 'pSubcategory', 'pPrice', 'pImages', 'pOem', 'pSpecs'],

                'notes' => 'Titlu SEO: Denumire Brand Cod. Imagine via Scraper. Preț cu adaos din /admin/adaoscomercial.',

            ];

            $suggestions[] = ['type' => 'navigate', 'label' => 'Lista produse', 'url' => '/admin/product'];

        } elseif (str_contains($lower, 'produs') && !str_contains($lower, 'furnizor')) {

            $suggestions[] = ['type' => 'navigate', 'label' => 'Lista produse', 'url' => '/admin/product'];

        }



        $countBits = [];

        if (isset($counts['produse_total'])) {

            $countBits[] = (int) $counts['produse_total'] . ' produse in catalog';

        }

        if (isset($counts['import_pending'])) {

            $countBits[] = (int) $counts['import_pending'] . ' in coada import';

        }



        $reply = 'Am mapat cererea la functiile existente din sectiunea ' . ($catalog['label'] ?? $section) . '.';

        if ($countBits !== []) {

            $reply .= ' Acum: ' . implode(', ', $countBits) . '.';

        }



        if ($llmError !== null && $llmError !== '') {
            if (!$this->llm->isConfigured() && $this->resolveFallbackKey() === '') {
                $reply .= ' Configurează Metro LLM (Ollama, GROQ_KEY sau OPENAI_KEY) în Setări.';
            }
        }



        return [

            'source' => 'heuristic',

            'llm_error' => $llmError,

            'reply_ro' => $reply,

            'intent' => 'explain',

            'suggestions' => $suggestions,

            'next_steps' => $next !== [] ? $next : ['Deschide linkurile sugerate si completeaza pasii in UI.'],

        ];

    }



    private function normalizeSection(string $section): string

    {

        $section = strtolower(trim($section));

        if (str_contains($section, 'produs') || str_contains($section, 'product') || str_contains($section, 'catalog')) {

            return 'produse';

        }

        if (str_contains($section, 'furnizor') || str_contains($section, 'supplier')) {

            return 'furnizori';

        }

        if (str_contains($section, 'import')) {

            return 'import';

        }

        if (str_contains($section, 'categor')) {

            return 'categorii';

        }

        if (str_contains($section, 'scraper')) {

            return 'scraper';

        }

        if (str_contains($section, 'adaos') || str_contains($section, 'markup')) {

            return 'produse';

        }

        if (str_contains($section, 'comunicare') || str_contains($section, 'message')) {

            return 'comunicare';

        }

        if (str_contains($section, 'dashboard')) {

            return 'dashboard';

        }

        if (str_contains($section, 'order') || str_contains($section, 'comenz')) {

            return 'comenzi';

        }



        return in_array($section, self::enabledSections(), true) ? $section : 'produse';

    }

    /** @param array<string, mixed> $plan @param array<string, mixed> $route @return array<string, mixed> */
    private function maybeEnhanceClientOrdersPlanWithLlm(array $plan, string $message, array $route): array
    {
        $products = is_array($plan['cheat_sheet']['products'] ?? null) ? $plan['cheat_sheet']['products'] : [];
        if ($products === []) {
            return $plan;
        }

        $ready = $this->getLlmReadiness();
        if (empty($ready['ready']) || !$this->llm->isConfigured()) {
            return $plan;
        }

        $agentSlug = (string) ($route['slug'] ?? 'context-master');
        $bundle = $this->registry->bundleForComposer($agentSlug, $message);

        $system = <<<'PROMPT'
Reformulezi scurt (romana) un raspuns admin despre comenzi client.
Foloseste DOAR datele din JSON — nu inventa comenzi, sume sau statusuri.
Max 5 propozitii clare + bullet-uri daca sunt mai multe comenzi.
Raspunde DOAR text simplu (fara JSON, fara markdown code block).
PROMPT;

        $user = json_encode([
            'intrebare_operator' => $message,
            'date_live_comenzi' => $products,
            'stats' => $plan['cheat_sheet']['stats'] ?? [],
            'agent_bundle' => [
                'runtime_brief' => mb_substr((string) ($bundle['runtime_brief'] ?? ''), 0, 2000),
                'rag_lines' => array_slice(is_array($bundle['rag_lines'] ?? null) ? $bundle['rag_lines'] : [], 0, 4),
            ],
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->llm->complete($system, (string) $user, 0.25, 120, 'section_assistant');
        if (empty($result['ok']) || trim((string) ($result['content'] ?? '')) === '') {
            return $plan;
        }

        $plan['reply_ro'] = trim((string) $result['content']);
        $plan['source'] = 'client_orders+composer-2.5';
        $plan['model'] = $this->llm->model();

        return $plan;
    }



    private function messageLooksLikeLearnableInventory(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(ce|c[aâ]te|cite|c[iî]te|care|lista|list[aă]|sunt|exist[aă]|avem|acum[aă]?|generate|generat\w*|overview|rezumat|total|comenz\w*|comanda|sistem|d[aă]mi|dami)\b/u',
            $lower
        );
    }



    private function messageWantsCatalogInventory(string $message): bool

    {

        if (SectionAssistantActionService::messageLooksLikeAction($message)) {

            return false;

        }

        if ($this->messageWantsCatalogStructure($message) || $this->messageWantsSectionCapabilities($message)) {

            return false;

        }

        $lower = mb_strtolower($message, 'UTF-8');

        if ((bool) preg_match('/\b(cos|cart|cosul)\b/u', $lower)) {
            return false;
        }

        $asksAboutProducts = (bool) preg_match('/\b(produse?|catalog(?:ul)?|magazin(?:ul)?|site(?:-ul)?)\b/u', $lower);

        $asksInventory = (bool) preg_match(
            '/\b(ce|c[aâ]te|care|lista|list[aă]|inventar|instalat[aă]?|sunt|exist[aă]|aici|overview|rezumat|total|inteleg|disponibil\w*|arata|arat[aă]|online|live|acum[aă]?|publicat[eă]?)\b/u',
            $lower
        );

        if ((bool) preg_match('/\b(online|live)\b/u', $lower) && (bool) preg_match('/\b(produse?|ce|c[aâ]te)\b/u', $lower)) {

            return true;

        }

        return $asksAboutProducts && $asksInventory;

    }

    private function messageWantsCatalogStructure(string $message): bool

    {

        if (SectionAssistantActionService::messageLooksLikeAction($message)) {

            return false;

        }

        $lower = mb_strtolower($message, 'UTF-8');

        $hasTaxonomy = (bool) preg_match('/\b(categor\w*|subcategor\w*|ierarh\w*|structur\w*)\b/u', $lower);

        $hasProducts = (bool) preg_match('/\b(produse?|catalog|magazin|toate|lista|list[aă]|ce|c[aâ]te|care)\b/u', $lower);

        if (!$hasTaxonomy) {
            return false;
        }

        if ($hasProducts) {
            return true;
        }

        return (bool) preg_match('/\bsubcategor\w*\b/u', $lower)
            || (bool) preg_match('/\b(categor\w*)\b/u', $lower) && (bool) preg_match('/\b(lista|list[aă]|toate|structur\w*|ierarh\w*|arata|arat[aă]|ce|c[aâ]te|care|full|complet\w*)\b/u', $lower);

    }

    private function messageWantsSectionCapabilities(string $message): bool

    {

        $lower = mb_strtolower($message, 'UTF-8');

        if ((bool) preg_match('/\b(funct\w*|capabilit\w*|optiun\w*|meniu|comenzi\s+composer|ce\s+poti|ce\s+poți)\b/u', $lower)) {
            return true;
        }

        if ((bool) preg_match('/\b(composer|asistent|ai)\b/u', $lower) && (bool) preg_match('/\b(funct\w*|ce\s+poti|ce\s+poți|toate|full|complet\w*)\b/u', $lower)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(faci|poti|poți|comenzi|ajutor|help|ce\s+stii|ce\s+știi|ce\s+ai|instrument|composer|asistent)\b/u',
            $lower
        ) && (bool) preg_match('/\b(sectiune|sectiunea|admin|aici|disponibil|toate|ai)\b/u', $lower);

    }

    private function messageMentionsOnline(string $message): bool

    {

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(online|live|site|magazin|publicat[eă]?)\b/u', $lower);

    }

    /** @return array<string, mixed> */
    private function getLlmReadiness(): array
    {
        if ($this->llmReadinessCache === null) {
            $this->llmReadinessCache = $this->llm->readiness();
        }

        return $this->llmReadinessCache;
    }

    /** @param array<string, mixed> $routerReady */
    private function buildLlmStatusBlock(string $source, mixed $planError, array $routerReady): array
    {
        $ollama = is_array($routerReady['ollama'] ?? null) ? $routerReady['ollama'] : [];

        return [
            'source' => $source,
            'ollama_ready' => (bool) ($ollama['ready'] ?? false),
            'groq_ready' => (bool) (($routerReady['groq']['configured'] ?? false)),
            'primary' => (string) ($routerReady['primary'] ?? 'none'),
            'router_ready' => !empty($routerReady['ready']),
            'error' => $this->sanitizeLlmError(is_string($planError) ? $planError : null),
        ];
    }

    private function sanitizeLlmError(?string $error): ?string

    {

        if ($error === null || trim($error) === '') {

            return null;

        }

        $error = trim($error);

        if (preg_match('/traceback|import error|modulenotfound|systemexit/i', $error)) {
            return 'Provider LLM indisponibil pe server. Folosește comenzi inventar live sau configurează OPENAI_KEY/GROQ_KEY.';
        }

        if (preg_match('/\bpy\b.*not recognized|python.*not found|not recognized as an internal/i', $error)) {
            return 'Serviciu LLM indisponibil. Configurează Ollama local sau OPENAI_KEY/GROQ_KEY.';
        }

        if (!mb_check_encoding($error, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $error)) {
            return 'Metro LLM indisponibil. Răspuns din date locale.';
        }

        if (preg_match('/[\x{0400}-\x{04FF}]{3,}/u', $error)) {
            return 'Metro LLM indisponibil. Răspuns din baza de date.';
        }

        return mb_substr($error, 0, 160, 'UTF-8');

    }

    private function messageRequestsImmediateAction(string $message): bool

    {

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(mapez|map(e|a)z|aplic|setez|pune|execut|fa\s+asta|f[aă]\s+asta|toate\s+produs|tot\s+catalogul)\b/u',
            $lower
        );

    }



    private function messageMentionsFix(string $message): bool

    {

        $lower = mb_strtolower($message, 'UTF-8');



        return (bool) preg_match('/\b(repar|fix|eroare|eșuat|esuat|blocat|curăță|curata|oprește|opreste|job)\b/u', $lower);

    }



    /** @param mixed $raw @return list<array{role:string,message:string,at?:string,source?:string,outcome?:string}> */

    private function normalizeConversationHistory(mixed $raw): array

    {

        if (!is_array($raw)) {

            return [];

        }

        $out = [];

        foreach ($raw as $turn) {

            if (!is_array($turn)) {

                continue;

            }

            $role = (string) ($turn['role'] ?? '');

            $msg = trim((string) ($turn['message'] ?? ''));

            if ($msg === '' || !in_array($role, ['user', 'assistant'], true)) {

                continue;

            }

            $row = ['role' => $role, 'message' => mb_substr($msg, 0, 800, 'UTF-8')];

            if (!empty($turn['at'])) {

                $row['at'] = (string) $turn['at'];

            }

            if (!empty($turn['source'])) {

                $row['source'] = (string) $turn['source'];

            }

            if (!empty($turn['outcome'])) {

                $row['outcome'] = (string) $turn['outcome'];

            }

            $out[] = $row;

        }



        return array_slice($out, -16);

    }



    /** @param array<string, mixed> $alerts */

    private function detectAlertCode(string $message, array $alerts): string

    {

        $lower = mb_strtolower($message, 'UTF-8');

        if (str_contains($lower, 'blocat')) {

            return 'job_blocked';

        }

        if (str_contains($lower, 'import') && (str_contains($lower, 'eșuat') || str_contains($lower, 'esuat') || str_contains($lower, 'eroare'))) {

            return 'import_failed';

        }

        if (str_contains($lower, 'tecdoc') || str_contains($lower, 'rapidapi')) {

            return 'tecdoc_dead';

        }

        if (str_contains($lower, 'ai') || str_contains($lower, 'composer') || str_contains($lower, 'token')) {

            return 'ai_api_error';

        }



        foreach (is_array($alerts['items'] ?? null) ? $alerts['items'] : [] as $item) {

            if (!is_array($item) || ($item['level'] ?? '') !== 'critical') {

                continue;

            }



            return (string) ($item['code'] ?? '');

        }



        return '';

    }



    /** @return array<string, mixed>|null */

    private function parseJson(string $content): ?array

    {

        $content = trim($content);

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {

            return $decoded;

        }

        if (preg_match('/\{[\s\S]*\}/u', $content, $m)) {

            $decoded = json_decode($m[0], true);



            return is_array($decoded) ? $decoded : null;

        }



        return null;

    }

}

