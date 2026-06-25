<?php

declare(strict_types=1);

namespace Evasystem\Services;

/**
 * Centru control scraper — configurare, testare, analiză, integrări.
 */
final class ScraperHubService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->bootLib();
    }

    private function bootLib(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        require_once $this->projectRoot . '/lib/Scraper/ScraperHubTester.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperPaths.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperSourceStore.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperStepRunner.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperStepSchema.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperHtmlAnalyzer.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperAiAgent.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperIntegrationSchema.php';
        require_once $this->projectRoot . '/lib/Scraper/ScraperIntegrationStore.php';
        require_once $this->projectRoot . '/lib/Scraper/ImageSearchService.php';
        $loaded = true;
    }

    /** @return list<array<string, mixed>> */
    public function listSourceCards(): array
    {
        return \ScraperSourceStore::listCards();
    }

    /** @return array<string, mixed> */
    public function getSourceConfig(string $sourceId): array
    {
        $config = \ScraperSourceStore::load($sourceId);
        $registry = \ScraperSourceStore::registry();
        $meta = is_array($registry[$sourceId] ?? null) ? $registry[$sourceId] : [];

        return [
            'config' => $config,
            'meta' => $meta,
            'last_test' => \ScraperSourceStore::lastTestMeta($sourceId),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveSourceConfig(string $sourceId, array $input): array
    {
        return \ScraperSourceStore::save($sourceId, $input);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function testSource(string $sourceId, array $input = []): array
    {
        $config = \ScraperSourceStore::load($sourceId);

        if (!empty($input['config']) && is_array($input['config'])) {
            $incoming = $input['config'];
            $config = array_replace_recursive($config, $incoming);
            if (!empty($incoming['fetch']) && is_array($incoming['fetch'])) {
                $config['fetch'] = array_replace($config['fetch'] ?? [], $incoming['fetch']);
            }
            if (!empty($incoming['steps']) && is_array($incoming['steps'])) {
                $incomingSteps = \ScraperStepSchema::migrateSteps($incoming['steps']);
                // Nu suprascrie cu pași goi din formular (bug UI).
                if (\ScraperStepSchema::listBlockSelector($incomingSteps) !== '') {
                    $config['steps'] = $incomingSteps;
                }
            }
        }

        $config['steps'] = \ScraperStepSchema::repairStepsFromDefaults($sourceId, $config['steps'] ?? []);

        $options = [
            'query' => trim((string) ($input['query'] ?? '')),
            'limit' => max(1, min(20, (int) ($input['limit'] ?? 5))),
        ];
        if (array_key_exists('super', $input)) {
            $options['super'] = !empty($input['super']);
        }
        if (array_key_exists('render', $input)) {
            $options['render'] = !empty($input['render']);
        }

        return $this->maybeRunAiAgentAfterFail($sourceId, $config, \ScraperStepRunner::runSource($sourceId, $config, $options), $options);
    }

    /**
     * Agent AI — analizează HTML salvat, propune selectori, opțional salvează.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function agentAnalyzeHtml(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? ''));
        if ($sourceId === '') {
            throw new \InvalidArgumentException('source_id lipsă.');
        }

        $config = \ScraperSourceStore::load($sourceId);
        $aiCfg = is_array($config['ai_agent'] ?? null) ? $config['ai_agent'] : [];
        $goals = trim((string) ($input['goals'] ?? $aiCfg['goals'] ?? ''));
        if ($goals === '') {
            $goals = 'Extrage fiecare produs din listă: titlu, preț RON, imagine, link pagină produs, cod articol.';
        }

        $fields = is_array($input['fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $input['fields'])))
            : (is_array($config['output']['fields_needed'] ?? null) ? $config['output']['fields_needed'] : ['title', 'image', 'url', 'price']);

        $rawPath = trim((string) ($input['raw_saved'] ?? ''));
        if ($rawPath === '') {
            $rawPath = $this->latestRawPathForSource($sourceId);
        }
        if ($rawPath === '') {
            throw new \InvalidArgumentException('Niciun HTML salvat — rulează Pas 1 (fetch) mai întâi.');
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            throw new \InvalidArgumentException('Fișier HTML inexistent: ' . $rawPath);
        }

        $html = (string) file_get_contents($full);
        $limit = max(1, min(10, (int) ($input['limit'] ?? $config['test']['limit'] ?? 5)));

        $result = \ScraperAiAgent::analyze($html, $sourceId, $goals, $fields, $limit, [
            'prefer_cursor' => !array_key_exists('prefer_cursor', $input) || !empty($input['prefer_cursor']),
        ]);
        $result['source_id'] = $sourceId;
        $result['raw_saved'] = $rawPath;
        $result['html_bytes'] = strlen($html);

        $apply = !empty($input['apply']) || !empty($input['apply_and_save']);
        if ($apply && !empty($result['selectors']['block'])) {
            $config = \ScraperAiAgent::applySelectorsToConfig($config, $result['selectors']);
            $config['ai_agent'] = array_replace([
                'enabled' => true,
                'auto_on_fail' => true,
                'goals' => $goals,
            ], is_array($config['ai_agent'] ?? null) ? $config['ai_agent'] : []);
            $config['ai_agent']['goals'] = $goals;
            \ScraperSourceStore::save($sourceId, $config);
            $result['saved'] = true;

            $flat = null;
            foreach ($config['steps'] as $rawStep) {
                if (!is_array($rawStep)) {
                    continue;
                }
                $f = \ScraperStepSchema::flattenForRunner($rawStep);
                if (($f['type'] ?? '') === 'parse_list') {
                    $flat = $f;
                    break;
                }
            }
            if (is_array($flat)) {
                $items = \ScraperStepRunner::parseListForTest($sourceId, $flat, $html, $limit);
                $result['items'] = $items;
                $result['items_count'] = count($items);
                $result['diagnostics'] = \ScraperHtmlAnalyzer::analyze($html, $flat, $limit);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function maybeRunAiAgentAfterFail(string $sourceId, array $config, array $result, array $options): array
    {
        if ((int) ($result['items_count'] ?? 0) > 0) {
            return $result;
        }

        $aiCfg = is_array($config['ai_agent'] ?? null) ? $config['ai_agent'] : [];
        if (empty($aiCfg['enabled']) || empty($aiCfg['auto_on_fail'])) {
            return $result;
        }

        $rawPath = '';
        foreach ((array) ($result['trace'] ?? []) as $step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'fetch') {
                continue;
            }
            $rawPath = (string) (($step['data']['raw_saved'] ?? '') ?: '');
            if ($rawPath !== '') {
                break;
            }
        }
        if ($rawPath === '') {
            $rawPath = $this->latestRawPathForSource($sourceId);
        }
        if ($rawPath === '') {
            return $result;
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            return $result;
        }

        try {
            $html = (string) file_get_contents($full);
            $goals = trim((string) ($aiCfg['goals'] ?? ''));
            $fields = is_array($config['output']['fields_needed'] ?? null) ? $config['output']['fields_needed'] : ['title', 'image', 'url', 'price'];
            $limit = max(1, min(20, (int) ($options['limit'] ?? 5)));

            $agent = \ScraperAiAgent::analyze($html, $sourceId, $goals, $fields, $limit, [
                'prefer_cursor' => false,
            ]);
            if (empty($agent['selectors']['block'])) {
                $result['ai_agent'] = $agent;
                return $result;
            }

            $config = \ScraperAiAgent::applySelectorsToConfig($config, $agent['selectors']);
            \ScraperSourceStore::save($sourceId, $config);

            $flat = null;
            foreach ($config['steps'] as $rawStep) {
                if (!is_array($rawStep)) {
                    continue;
                }
                $f = \ScraperStepSchema::flattenForRunner($rawStep);
                if (($f['type'] ?? '') === 'parse_list') {
                    $flat = $f;
                    break;
                }
            }

            if (is_array($flat)) {
                $items = \ScraperStepRunner::parseListForTest($sourceId, $flat, $html, $limit);
                $result['items'] = $items;
                $result['items_count'] = count($items);
                $result['matched_fields'] = $this->matchedFieldsFromItems($items, $config);
                $result['analysis'] = \ScraperHubTester::analyzeParsed($sourceId, $items, $html);

                foreach ($result['trace'] as &$traceStep) {
                    if (!is_array($traceStep) || (string) ($traceStep['type'] ?? '') !== 'parse_list') {
                        continue;
                    }
                    $diag = \ScraperHtmlAnalyzer::analyze($html, $flat, $limit);
                    $traceStep['status'] = count($items) > 0 ? 'ok' : 'warn';
                    $traceStep['message'] = count($items) . ' produse extrase (Agent AI)';
                    $traceStep['data'] = ['items' => $items, 'diagnostics' => $diag];
                }
                unset($traceStep);
            }

            $agent['auto_applied'] = true;
            $result['ai_agent'] = $agent;
        } catch (\Throwable $e) {
            $result['ai_agent'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $items @param array<string, mixed> $config @return array<string, mixed> */
    private function matchedFieldsFromItems(array $items, array $config): array
    {
        $needed = is_array($config['output']['fields_needed'] ?? null) ? $config['output']['fields_needed'] : [];
        $first = $items[0] ?? [];
        $matched = [];
        foreach ($needed as $field) {
            $field = (string) $field;
            $val = trim((string) ($first[$field] ?? ''));
            $matched[$field] = ['found' => $val !== '', 'value' => $val !== '' ? mb_substr($val, 0, 120) : ''];
        }

        return $matched;
    }

    /**
     * Analizează ultimul HTML salvat (fără scrape.do) — diagnostic selectori.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function analyzeSavedHtml(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? ''));
        if ($sourceId === '') {
            throw new \InvalidArgumentException('source_id lipsă.');
        }

        $config = \ScraperSourceStore::load($sourceId);
        if (!empty($input['config']) && is_array($input['config'])) {
            $incoming = $input['config'];
            $config = array_replace_recursive($config, $incoming);
            if (!empty($incoming['steps']) && is_array($incoming['steps'])) {
                $incomingSteps = \ScraperStepSchema::migrateSteps($incoming['steps']);
                if (\ScraperStepSchema::listBlockSelector($incomingSteps) !== '') {
                    $config['steps'] = $incomingSteps;
                }
            }
        }
        $config['steps'] = \ScraperStepSchema::repairStepsFromDefaults($sourceId, $config['steps'] ?? []);

        $rawPath = trim((string) ($input['raw_saved'] ?? ''));
        if ($rawPath === '') {
            $rawPath = $this->latestRawPathForSource($sourceId);
        }
        if ($rawPath === '') {
            throw new \InvalidArgumentException('Niciun fișier HTML salvat — rulează mai întâi testul (Pas 1 fetch).');
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            throw new \InvalidArgumentException('Fișier HTML inexistent: ' . $rawPath);
        }

        $html = (string) file_get_contents($full);
        $limit = max(1, min(20, (int) ($input['limit'] ?? $config['test']['limit'] ?? 5)));

        $parseStep = null;
        foreach ($config['steps'] as $rawStep) {
            if (!is_array($rawStep) || empty($rawStep['enabled'])) {
                continue;
            }
            $flat = \ScraperStepSchema::flattenForRunner($rawStep);
            if (($flat['type'] ?? '') === 'parse_list') {
                $parseStep = $flat;
                break;
            }
        }
        if ($parseStep === null) {
            foreach ($config['steps'] as $rawStep) {
                if (!is_array($rawStep)) {
                    continue;
                }
                $flat = \ScraperStepSchema::flattenForRunner($rawStep);
                if (($flat['type'] ?? '') === 'parse_list') {
                    $parseStep = $flat;
                    break;
                }
            }
        }

        $stepForDiag = is_array($parseStep) ? $parseStep : ['block_selector' => '', 'field_map' => [], 'ignore_rules' => []];
        $diagnostics = \ScraperHtmlAnalyzer::analyze($html, $stepForDiag, $limit);
        $items = is_array($parseStep)
            ? \ScraperStepRunner::parseListForTest($sourceId, $parseStep, $html, $limit)
            : [];

        $out = [
            'source_id' => $sourceId,
            'raw_saved' => $rawPath,
            'html_bytes' => strlen($html),
            'selectors_used' => [
                'block' => (string) ($stepForDiag['block_selector'] ?? ''),
                'fields' => is_array($stepForDiag['field_map'] ?? null) ? $stepForDiag['field_map'] : [],
                'ignore' => is_array($stepForDiag['ignore_rules'] ?? null) ? $stepForDiag['ignore_rules'] : [],
            ],
            'diagnostics' => $diagnostics,
            'items' => $items,
            'items_count' => count($items),
        ];

        if (count($items) === 0 && !empty($input['run_agent'])) {
            $out['ai_agent'] = $this->agentAnalyzeHtml([
                'source_id' => $sourceId,
                'raw_saved' => $rawPath,
                'goals' => $input['goals'] ?? null,
                'apply' => !empty($input['apply_agent']),
                'limit' => $limit,
                'prefer_cursor' => false,
            ]);
            if (!empty($out['ai_agent']['items_count'])) {
                $out['items'] = $out['ai_agent']['items'] ?? $items;
                $out['items_count'] = (int) ($out['ai_agent']['items_count'] ?? 0);
            }
        }

        return $out;
    }

    private function latestRawPathForSource(string $sourceId): string
    {
        $meta = \ScraperSourceStore::registry()[$sourceId] ?? [];
        $domain = (string) ($meta['domain'] ?? '');
        $needles = array_values(array_unique(array_filter([
            strtolower($sourceId),
            strtolower(str_replace('.', '', $domain)),
            strtolower((string) (explode('.', $domain)[0] ?? '')),
        ])));

        $dir = \ScraperPaths::rawDir();
        if (!is_dir($dir)) {
            return '';
        }

        $files = glob($dir . '/test_*.html') ?: [];
        $matched = [];
        foreach ($files as $file) {
            $base = strtolower(basename($file));
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($base, $needle)) {
                    $matched[] = $file;
                    break;
                }
            }
        }

        if ($matched === []) {
            return '';
        }

        usort($matched, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return str_replace(\ScraperPaths::projectRoot(), '', $matched[0]);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createSource(array $input): array
    {
        return \ScraperSourceStore::createSource($input);
    }

    public function deleteSource(string $sourceId): void
    {
        \ScraperSourceStore::deleteSource($sourceId);
    }

    public function restoreBuiltinPresets(): int
    {
        return \ScraperSourceStore::restoreBuiltinPresets();
    }

    /** @return array<string, mixed> */
    public function getStepCatalog(): array
    {
        return [
            'step_types' => \ScraperStepSchema::stepTypeCatalog(),
            'element_types' => \ScraperStepSchema::elementTypeCatalog(),
            'extraction_goals' => \ScraperIntegrationSchema::extractionGoalCatalog(),
        ];
    }

    /** @return array<string, mixed> */
    public function getIntegrationConfig(): array
    {
        $cfg = \ScraperIntegrationStore::load();
        $cards = $this->listSourceCards();
        $sourceIds = array_map(static fn (array $c): string => (string) ($c['id'] ?? ''), $cards);

        return [
            'config' => $cfg,
            'available_sources' => $sourceIds,
            'extraction_goal_catalog' => \ScraperIntegrationSchema::extractionGoalCatalog(),
            'pipeline_context' => $this->pipelineTestContext(),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveIntegrationConfig(array $input): array
    {
        $cfg = is_array($input['config'] ?? null) ? $input['config'] : $input;

        return \ScraperIntegrationStore::save($cfg);
    }

    /**
     * Test pipeline imagini Plan 1→2→3 pe un query produs.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function testImagePipeline(array $input): array
    {
        $product = $this->buildPipelineTestProduct($input);

        $tried = [];
        $result = \ImageSearchService::resolve($product, [
            'test_mode' => true,
            'pipeline_test_query' => trim((string) ($input['query'] ?? $input['product_name'] ?? '')),
            'log' => static function (string $msg, string $level = 'info') use (&$tried): void {
                $tried[] = ['message' => $msg, 'level' => $level];
            },
        ]);

        return [
            'query' => (string) ($product['pName'] ?? ''),
            'hit' => $result['hit'],
            'tried' => $result['tried'],
            'log' => $tried,
            'product_after' => $result['product'],
            'context' => $this->pipelineTestContext(),
        ];
    }

    /**
     * Testează un singur plan din pipeline (progres real în UI).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function testImagePipelineStep(array $input): array
    {
        $product = $this->buildPipelineTestProduct($input);
        $plan = [
            'tier' => (int) ($input['tier'] ?? 0),
            'source_id' => trim((string) ($input['source_id'] ?? '')),
            'label' => trim((string) ($input['label'] ?? '')),
            'enabled' => true,
        ];

        $started = microtime(true);
        $step = \ImageSearchService::resolvePlan($plan, $product, false, [
            'test_mode' => true,
            'pipeline_test_query' => trim((string) ($input['query'] ?? $input['product_name'] ?? '')),
        ]);
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        if (($step['tried']['duration_ms'] ?? 0) <= 0) {
            $step['tried']['duration_ms'] = $durationMs;
        }

        return [
            'query' => (string) ($product['pName'] ?? ''),
            'plan' => $plan,
            'tried' => $step['tried'],
            'hit' => $step['hit'],
            'duration_ms' => $durationMs,
            'context' => $this->pipelineTestContext(),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function buildPipelineTestProduct(array $input): array
    {
        $name = trim((string) ($input['query'] ?? $input['product_name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '' && $name !== '' && preg_match('/\b(\d{5,12})\s*$/', $name, $m)) {
            $code = (string) $m[1];
        }

        $category = trim((string) ($input['category'] ?? ''));
        $subcategory = trim((string) ($input['subcategory'] ?? ''));
        if ($category === '' && $name !== '') {
            $lower = mb_strtolower($name, 'UTF-8');
            if (str_contains($lower, 'lichid') || str_contains($lower, 'ulei') || str_contains($lower, 'antigel')) {
                $category = 'Lichide auto';
            }
            if (str_contains($lower, 'frana') || str_contains($lower, 'frână')) {
                $subcategory = 'Lichid de frana';
            }
        }

        $product = [
            'pName' => $name,
            'pCode' => $code,
            'pBrand' => trim((string) ($input['brand'] ?? '')),
            'pCategory' => $category,
            'pSubcategory' => $subcategory,
            'raw_json' => '{}',
        ];

        $pipelinePath = $this->projectRoot . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
            if (function_exists('besoiu_image_enrich_product_context')) {
                return besoiu_image_enrich_product_context($product);
            }
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function pipelineTestContext(): array
    {
        require_once $this->projectRoot . '/lib/Scraper/ScrapeDoConfig.php';
        $tecdocPath = $this->projectRoot . '/system/tecdoc_stock.php';
        if (is_file($tecdocPath)) {
            require_once $tecdocPath;
        }

        $usage = \ScrapeDoConfig::budgetUsage();
        $queriesLeft = is_array($usage) ? (int) ($usage['queries_left'] ?? 0) : null;
        $rapidKey = trim((string) ($_ENV['RAPIDAPI_AUTOPARTS_KEY'] ?? getenv('RAPIDAPI_AUTOPARTS_KEY') ?: ''));
        if ($rapidKey === '') {
            $rapidKey = trim((string) ($_ENV['RAPIDAPI_TECDOC_KEY'] ?? getenv('RAPIDAPI_TECDOC_KEY') ?: ''));
        }

        return [
            'scrape_do_token' => \ScrapeDoConfig::hasToken(),
            'scrape_do_quota_exceeded' => \ScrapeDoConfig::isQuotaExceeded(),
            'scrape_do_queries_left' => $queriesLeft,
            'rapidapi_key_set' => $rapidKey !== '',
            'rapidapi_quota_blocked' => function_exists('tecdoc_api_is_unavailable') && tecdoc_api_is_unavailable(),
            'rapidapi_message' => function_exists('tecdoc_api_unavailable_message') ? tecdoc_api_unavailable_message() : '',
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $hub = \ScraperHubTester::hubConfig();
        $rules = \ScraperHubTester::rulesConfig();
        $env = \ScraperHubTester::envStatus();

        $imagePath = $this->projectRoot . '/config/image-search-sources.php';
        $imageCfg = is_file($imagePath) ? (require $imagePath) : ['sources' => [], 'audit' => []];

        $sources = [];
        if (is_array($imageCfg['sources'] ?? null)) {
            foreach ($imageCfg['sources'] as $id => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $envOk = true;
                foreach ((array) ($meta['env_required'] ?? []) as $key) {
                    if (trim((string) ($_ENV[$key] ?? getenv((string) $key) ?: '')) === '') {
                        $envOk = false;
                        break;
                    }
                }
                $sources[] = [
                    'id' => (string) $id,
                    'label' => (string) ($meta['label'] ?? $id),
                    'enabled' => !empty($meta['enabled']),
                    'priority' => (int) ($meta['priority'] ?? 999),
                    'roles' => is_array($meta['roles'] ?? null) ? $meta['roles'] : [],
                    'categories' => is_array($meta['categories'] ?? null) ? $meta['categories'] : [],
                    'env_ok' => $envOk,
                    'note' => (string) ($meta['note'] ?? ''),
                ];
            }
            usort($sources, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        }

        $envOrder = trim((string) ($_ENV['IMAGE_SEARCH_SOURCES'] ?? getenv('IMAGE_SEARCH_SOURCES') ?: ''));

        return [
            'hub_config' => $hub,
            'env' => $env,
            'image_sources' => $sources,
            'image_search_order_env' => $envOrder,
            'audit_config' => is_array($imageCfg['audit'] ?? null) ? $imageCfg['audit'] : [],
            'scrape_rules_sources' => array_keys(is_array($rules['sources'] ?? null) ? $rules['sources'] : []),
            'integrations' => is_array($rules['integrations'] ?? null) ? $rules['integrations'] : [],
            'storage' => [
                'hub_config' => '/storage/scraper/hub_config.json',
                'raw_html' => '/storage/scraper/raw/',
                'logs' => '/storage/scraper/logs/scraper.log',
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function saveConfig(array $input): array
    {
        $allowed = ['scrape_do', 'test_defaults', 'agent', 'notes'];
        $patch = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $patch[$key] = $input[$key];
            }
        }

        return \ScraperHubTester::saveHubConfig($patch);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function testFetch(array $input): array
    {
        $url = trim((string) ($input['url'] ?? ''));

        return \ScraperHubTester::testFetch($url, $input);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function testParse(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? 'epiesa'));
        $html = isset($input['html']) ? (string) $input['html'] : null;
        if ($html !== null && trim($html) === '') {
            $html = null;
        }

        return \ScraperHubTester::testParse($sourceId, $html, $input);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function testPipeline(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $sources = null;
        if (isset($input['sources']) && is_array($input['sources'])) {
            $sources = array_values(array_filter(array_map('strval', $input['sources'])));
        }
        $limit = max(1, min(10, (int) ($input['limit'] ?? 3)));

        return \ScraperHubTester::testPipeline($query, $sources, $limit);
    }

    /** @return array<string, mixed> */
    public function getRules(): array
    {
        return \ScraperHubTester::rulesConfig();
    }

    /**
     * Analiză agent (heuristică PHP) — pe HTML + rezultat parsare.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function analyzeAgent(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? 'epiesa'));
        $html = (string) ($input['html'] ?? '');
        $parsed = is_array($input['parsed'] ?? null) ? $input['parsed'] : [];

        if ($parsed === [] && $html !== '') {
            $parsed = \ScraperHubTester::testParse($sourceId, $html, $input)['parsed'] ?? [];
        } elseif ($parsed === [] && !empty($input['query'])) {
            $result = \ScraperHubTester::testParse($sourceId, null, $input);
            $html = (string) ($result['fetch']['html_preview'] ?? '');
            $parsed = is_array($result['parsed'] ?? null) ? $result['parsed'] : [];
        }

        $rules = \ScraperHubTester::rulesConfig();
        $meta = is_array($rules['sources'][$sourceId] ?? null) ? $rules['sources'][$sourceId] : [];
        $analysis = \ScraperHubTester::analyzeParsed($sourceId, $parsed, $html, $meta);

        $cursor = new CursorImageAuditClient($this->projectRoot);
        $analysis['cursor_available'] = $cursor->isConfigured();
        $analysis['next_steps'] = [];

        if (!empty($analysis['integration_ready'])) {
            $analysis['next_steps'][] = 'Activează sursa în IMAGE_SEARCH_SOURCES dacă nu e deja';
            $analysis['next_steps'][] = 'Rulează Test pipeline cu același query';
            $analysis['next_steps'][] = 'După OK: import cron va folosi besoiu_image_search_try_' . $sourceId;
        } else {
            $analysis['next_steps'][] = 'Ajustează selectori în config/scraper-rules.php';
            $analysis['next_steps'][] = 'Re-testează cu super/render în tab Configurare';
        }

        if ($cursor->isConfigured() && !empty($parsed[0])) {
            $analysis['next_steps'][] = 'Pentru validare imagine vs titlu: Audit imagini din lista produse (Cursor)';
        }

        return [
            'source_id' => $sourceId,
            'analysis' => $analysis,
            'parsed_sample' => array_slice($parsed, 0, 3),
        ];
    }
}
