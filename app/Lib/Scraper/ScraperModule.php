<?php

declare(strict_types=1);

/**
 * Modul scraper — punct unic de intrare (CRUD surse, pipeline imagini, ePiesa, hub).
 * Apel extern: ScraperModule::boot(); ScraperModule::instance()->…
 */
final class ScraperModule
{
    private static ?self $instance = null;

    private string $root;

    public static function boot(): void
    {
        require_once __DIR__ . '/bootstrap.php';
    }

    public static function instance(): self
    {
        self::boot();
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        self::boot();
        $this->root = ScraperPaths::projectRoot();
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
                // Nu suprascrie cu paИ™i goi din formular (bug UI).
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
        $options['test_mode'] = true;
        $options['source_id'] = $sourceId;

        $result = \ScraperStepRunner::runSource($sourceId, $config, $options);
        if ((int) ($result['items_count'] ?? 0) === 0) {
            $result = $this->maybeParseFromCachedHtml($sourceId, $config, $result, $options);
        }

        return $this->maybeRunAiAgentAfterFail($sourceId, $config, $result, $options);
    }

    /**
     * Agent AI вЂ” analizeazДѓ HTML salvat, propune selectori, opИ›ional salveazДѓ.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function agentAnalyzeHtml(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? ''));
        if ($sourceId === '') {
            throw new \InvalidArgumentException('source_id lipsДѓ.');
        }

        $config = \ScraperSourceStore::load($sourceId);
        $aiCfg = is_array($config['ai_agent'] ?? null) ? $config['ai_agent'] : [];
        $goals = trim((string) ($input['goals'] ?? $aiCfg['goals'] ?? ''));
        if ($goals === '') {
            $goals = 'Extrage fiecare produs din listДѓ: titlu, preИ› RON, imagine, link paginДѓ produs, cod articol.';
        }

        $fields = is_array($input['fields'] ?? null)
            ? array_values(array_filter(array_map('strval', $input['fields'])))
            : (is_array($config['output']['fields_needed'] ?? null) ? $config['output']['fields_needed'] : ['title', 'image', 'url', 'price']);

        $rawPath = trim((string) ($input['raw_saved'] ?? ''));
        if ($rawPath === '') {
            $rawPath = $this->latestValidRawPathForSource($sourceId);
        }
        if ($rawPath === '') {
            throw new \InvalidArgumentException('Niciun HTML salvat вЂ” ruleazДѓ Pas 1 (fetch) mai Г®ntГўi.');
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            throw new \InvalidArgumentException('FiИ™ier HTML inexistent: ' . $rawPath);
        }

        $html = (string) file_get_contents($full);
        $limit = max(1, min(10, (int) ($input['limit'] ?? $config['test']['limit'] ?? 5)));

        $result = \ScraperAiAgent::analyze($html, $sourceId, $goals, $fields, $limit, [
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
            $rawPath = $this->latestValidRawPathForSource($sourceId);
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
     * AnalizeazДѓ ultimul HTML salvat (fДѓrДѓ scrape.do) вЂ” diagnostic selectori.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function analyzeSavedHtml(array $input): array
    {
        $sourceId = trim((string) ($input['source_id'] ?? ''));
        if ($sourceId === '') {
            throw new \InvalidArgumentException('source_id lipsДѓ.');
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
            $rawPath = $this->latestValidRawPathForSource($sourceId);
        }
        if ($rawPath === '') {
            throw new \InvalidArgumentException('Niciun fiИ™ier HTML salvat вЂ” ruleazДѓ mai Г®ntГўi testul (Pas 1 fetch).');
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            throw new \InvalidArgumentException('FiИ™ier HTML inexistent: ' . $rawPath);
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
            ]);
            if (!empty($out['ai_agent']['items_count'])) {
                $out['items'] = $out['ai_agent']['items'] ?? $items;
                $out['items_count'] = (int) ($out['ai_agent']['items_count'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Când fetch-ul live eșuează (Cloudflare), parsează ultimul HTML valid salvat — cu avertisment clar.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function maybeParseFromCachedHtml(string $sourceId, array $config, array $result, array $options): array
    {
        $fetchFailed = false;
        foreach ((array) ($result['trace'] ?? []) as $step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'fetch') {
                continue;
            }
            if ((string) ($step['status'] ?? '') === 'error') {
                $fetchFailed = true;
                break;
            }
        }
        if (!$fetchFailed) {
            return $result;
        }

        $rawPath = $this->latestValidRawPathForSource($sourceId);
        if ($rawPath === '') {
            return $result;
        }

        $full = \ScraperPaths::projectRoot() . $rawPath;
        if (!is_file($full)) {
            return $result;
        }

        $html = (string) file_get_contents($full);
        if ($html === '' || \ScraperHubTester::htmlIsCloudflareChallenge($html)) {
            return $result;
        }

        $limit = max(1, min(20, (int) ($options['limit'] ?? 5)));
        $parseStep = null;
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
        if (!is_array($parseStep)) {
            return $result;
        }

        $items = \ScraperStepRunner::parseListForTest($sourceId, $parseStep, $html, $limit);
        if ($items === []) {
            return $result;
        }

        $diag = \ScraperHtmlAnalyzer::analyze($html, $parseStep, $limit);
        $cacheMsg = count($items) . ' produse din HTML salvat (cache) — fetch-ul live a eșuat';
        $hasParseTrace = false;
        foreach ($result['trace'] as &$traceStep) {
            if (!is_array($traceStep) || (string) ($traceStep['type'] ?? '') !== 'parse_list') {
                continue;
            }
            $hasParseTrace = true;
            $traceStep['status'] = 'warn';
            $traceStep['message'] = $cacheMsg;
            $traceStep['data'] = ['items' => $items, 'diagnostics' => $diag, 'from_cache' => true];
        }
        unset($traceStep);

        if (!$hasParseTrace) {
            $result['trace'][] = [
                'order' => 2,
                'label' => 'Pas 2 — Scanează blocul listă (cache)',
                'type' => 'parse_list',
                'status' => 'warn',
                'message' => $cacheMsg,
                'data' => ['items' => $items, 'diagnostics' => $diag, 'from_cache' => true],
            ];
        }

        $result['items'] = $items;
        $result['items_count'] = count($items);
        $result['matched_fields'] = $this->matchedFieldsFromItems($items, $config);
        $result['analysis'] = \ScraperHubTester::analyzeParsed($sourceId, $items, $html);
        $result['parsed_from_cache'] = true;
        $result['cache_raw_saved'] = $rawPath;
        $result['cache_warning_ro'] = 'Produsele afișate provin dintr-un HTML salvat anterior ('
            . basename($rawPath)
            . '), nu din fetch-ul curent blocat de Cloudflare.';

        return $result;
    }

    private function latestValidRawPathForSource(string $sourceId): string
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

        $files = array_merge(glob($dir . '/test_*.html') ?: [], glob($dir . '/stealth_*.html') ?: []);
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

        foreach ($matched as $file) {
            $html = (string) @file_get_contents($file);
            if ($html === '' || \ScraperHubTester::htmlIsCloudflareChallenge($html)) {
                continue;
            }
            if (strlen($html) < 15000 && !str_contains($html, 'listing-item__wrap') && !str_contains($html, 'prod-card')) {
                continue;
            }

            return str_replace(\ScraperPaths::projectRoot(), '', $file);
        }

        return '';
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
     * Test pipeline imagini Plan 1в†’2в†’3 pe un query produs.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function testImagePipeline(array $input): array
    {
        $product = $this->buildPipelineTestProduct($input);

        $tried = [];
        $result = $this->resolveProductImage($product, [
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
     * TesteazДѓ un singur plan din pipeline (progres real Г®n UI).
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
        $step = $this->resolveProductImagePlan($plan, $product, false, [
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
            if (str_contains($lower, 'frana') || str_contains($lower, 'frГўnДѓ')) {
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

        $pipelinePath = $this->root . '/system/image_search_pipeline.php';
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
        require_once $this->root . '/lib/Scraper/bootstrap.php';
        $tecdocPath = $this->root . '/system/tecdoc_stock.php';
        if (is_file($tecdocPath)) {
            require_once $tecdocPath;
        }

        $usage = \ScrapeDoConfig::budgetUsage();
        $queriesLeft = is_array($usage)
            ? (int) ($usage['requests_left'] ?? $usage['queries_left'] ?? 0)
            : null;
        $rapidKey = besoiu_env_get('RAPIDAPI_AUTOPARTS_KEY');
        if ($rapidKey === '') {
            $rapidKey = besoiu_env_get('RAPIDAPI_TECDOC_KEY');
        }

        return [
            'stealth_browser_available' => \StealthBrowserClient::isAvailable(),
            'scrape_do_token' => \ScrapeDoConfig::hasToken(),
            'scrape_do_quota_exceeded' => \ScrapeDoConfig::isQuotaExceeded(),
            'scrape_do_queries_left' => $queriesLeft,
            'scrape_do_fallback' => \ScraperHubTester::isScrapeDoFallbackEnabled(),
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

        $imagePath = $this->root . '/config/image-search-sources.php';
        $imageCfg = is_file($imagePath) ? (require $imagePath) : ['sources' => [], 'audit' => []];

        $sources = [];
        if (is_array($imageCfg['sources'] ?? null)) {
            foreach ($imageCfg['sources'] as $id => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $envOk = true;
                foreach ((array) ($meta['env_required'] ?? []) as $key) {
                    if (besoiu_env_get((string) $key) === '') {
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

        $envOrder = besoiu_env_get('IMAGE_SEARCH_SOURCES');

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
     * AnalizДѓ agent (heuristicДѓ PHP) вЂ” pe HTML + rezultat parsare.
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
        $analysis['next_steps'] = [];

        if (!empty($analysis['integration_ready'])) {
            $analysis['next_steps'][] = 'ActiveazДѓ sursa Г®n IMAGE_SEARCH_SOURCES dacДѓ nu e deja';
            $analysis['next_steps'][] = 'RuleazДѓ Test pipeline cu acelaИ™i query';
            $analysis['next_steps'][] = 'DupДѓ OK: import cron va folosi besoiu_image_search_try_' . $sourceId;
        } else {
            $analysis['next_steps'][] = 'AjusteazДѓ selectori Г®n config/scraper-rules.php';
            $analysis['next_steps'][] = 'Re-testeazДѓ cu super/render Г®n tab Configurare';
        }

        if (!empty($parsed[0])) {
            $analysis['next_steps'][] = 'Pentru validare imagine vs titlu: Audit imagini din lista produse (OpenAI/Ollama sau manual Composer IDE)';
        }

        return [
            'source_id' => $sourceId,
            'analysis' => $analysis,
            'parsed_sample' => array_slice($parsed, 0, 3),
        ];
    }

    /**
     * Alerte operaționale scraper — Dashboard red_flags, clopoțel live, AI Agent.
     *
     * @return list<array<string, mixed>>
     */
    public function operationalAlerts(): array
    {
        $flags = [];
        $ctx = $this->pipelineTestContext();
        $needsHtml = $this->hasActiveHtmlScrapeSources();

        if ($needsHtml && empty($ctx['stealth_browser_available'])) {
            $flags[] = [
                'code' => 'stealth_browser_missing',
                'level' => 'danger',
                'critical' => true,
                'title' => 'Stealth browser indisponibil',
                'detail' => 'Toate sursele HTML folosesc stealth-browser-mcp — instalează tools/stealth-browser-mcp/venv cu nodriver.',
                'url' => '/admin/scraper',
            ];
        }

        if ($needsHtml && !empty($ctx['scrape_do_fallback']) && empty($ctx['scrape_do_token'])) {
            $flags[] = [
                'code' => 'scrape_do_token_missing',
                'level' => 'warning',
                'critical' => false,
                'title' => 'Fallback scrape.do fără token',
                'detail' => 'SCRAPER_FALLBACK_SCRAPE_DO=1 dar lipsește SCRAPE_DO_TOKEN.',
                'url' => besoiu_env_settings_keys_url(),
            ];
        } elseif ($needsHtml && !empty($ctx['scrape_do_fallback']) && !empty($ctx['scrape_do_quota_exceeded'])) {
            $left = $ctx['scrape_do_queries_left'];
            $detail = 'Fallback scrape.do: cotă epuizată.';
            if ($left !== null) {
                $detail .= ' Cereri rămase: ' . $left . '.';
            }
            $flags[] = [
                'code' => 'scrape_do_quota',
                'level' => 'warning',
                'critical' => false,
                'title' => 'Cotă scrape.do (fallback)',
                'detail' => $detail,
                'url' => besoiu_env_settings_keys_url(),
            ];
        }

        if (!$this->hasActiveImagePlans()) {
            $flags[] = [
                'code' => 'scraper_no_plans',
                'level' => 'warning',
                'title' => 'Pipeline imagini gol',
                'detail' => 'Niciun plan activ în /admin/scraper — „Caută imagine” la import nu rulează.',
                'url' => '/admin/scraper',
            ];
        }

        if ($needsHtml && empty($ctx['rapidapi_key_set']) && $this->hasActiveTecdocPlan()) {
            $flags[] = [
                'code' => 'scraper_rapidapi_key',
                'level' => 'warning',
                'title' => 'Cheie RapidAPI lipsă',
                'detail' => 'Planul TecDoc din pipeline imagini necesită RapidAPI TecDoc în Setări → Tokeni API.',
                'url' => besoiu_env_settings_keys_url(),
            ];
        }

        return $flags;
    }

    private function hasActiveHtmlScrapeSources(): bool
    {
        foreach ($this->activeImagePlans() as $plan) {
            $id = trim((string) ($plan['source_id'] ?? ''));
            if (in_array($id, ['autodoc', 'epiesa', 'emag', 'pieseauto', 'autovit'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @deprecated folosește hasActiveHtmlScrapeSources */
    private function hasActiveScrapeDoSources(): bool
    {
        return $this->hasActiveHtmlScrapeSources();
    }

    private function hasActiveTecdocPlan(): bool
    {
        foreach ($this->activeImagePlans() as $plan) {
            $id = trim((string) ($plan['source_id'] ?? ''));
            if (in_array($id, ['tecdoc', 'tecdoc_api', 'rapidapi'], true)) {
                return true;
            }
        }

        return false;
    }

    // ─── Pipeline imagini (import / cron) ───────────────────────────────────

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts
     * @return array{product: array<string, mixed>, hit: array<string, mixed>|null, tried: list<array<string, mixed>>}
     */
    public function resolveProductImage(array $product, array $opts = []): array
    {
        ImageSearchService::boot();

        return ImageSearchService::resolve($product, $opts);
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts
     * @return array{tried: array<string, mixed>, hit: array<string, mixed>|null, product?: array<string, mixed>}
     */
    public function resolveProductImagePlan(array $plan, array $product, bool $force = false, array $opts = []): array
    {
        ImageSearchService::boot();

        return ImageSearchService::resolvePlan($plan, $product, $force, $opts);
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $opts @return array<string, mixed> */
    public function findProductImage(array $product, array $opts = []): array
    {
        ImageSearchService::boot();

        return ImageSearchService::findImage($product, $opts);
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $hit @return array<string, mixed> */
    public function applyImageHit(array $product, array $hit): array
    {
        return ImportImageBridge::applyHit($product, ImportImageBridge::normalizeHit($hit));
    }

    /** @return list<array<string, mixed>> */
    public function activeImagePlans(?array $categories = null): array
    {
        return ScraperImageResolver::activeImagePlans($categories ?? []);
    }

    /** @return list<string> */
    public function syncAllSources(): array
    {
        ScraperImageSourcesSync::rebuild();

        return ScraperImageSourcesSync::activeSourceIds();
    }

    public function streamImageProxy(string $url): void
    {
        ScraperImageProxy::stream($url);
    }

    // ─── ePiesa ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function epiesaVehicleTreeStatus(): array
    {
        require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeCrawler.php';

        return EpiesaVehicleTreeCrawler::status();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function epiesaVehicleTreeCrawl(array $input): array
    {
        require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeCrawler.php';

        if (!empty($input['background']) || !empty($input['async'])) {
            require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeLauncher.php';

            return EpiesaVehicleTreeLauncher::spawnBackground($input);
        }

        return EpiesaVehicleTreeCrawler::crawl($input);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function epiesaVehicleTreeStart(array $input): array
    {
        require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeLauncher.php';

        return EpiesaVehicleTreeLauncher::spawnBackground($input);
    }

    /** @return array<string, mixed> */
    public function epiesaVehicleTreeStop(): array
    {
        require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeCrawler.php';
        EpiesaVehicleTreeCrawler::requestCancel();

        return [
            'ok' => true,
            'message' => 'Cerere oprire trimisă — botul se oprește după marca curentă.',
            'status' => EpiesaVehicleTreeCrawler::status(),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function epiesaVehicleTreePreview(array $input): array
    {
        require_once $this->root . '/lib/Scraper/EpiesaVehicleTreeCrawler.php';
        $mfa = isset($input['marca_id']) ? (int) $input['marca_id'] : null;

        return EpiesaVehicleTreeCrawler::previewFirstChain($mfa > 0 ? $mfa : null);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function epiesaRunScan(array $input): array
    {
        $url = trim((string) ($input['url'] ?? EpiesaScrapeJob::DEFAULT_CATEGORY_URL));
        $limit = max(1, min(50, (int) ($input['limit'] ?? 10)));

        require_once $this->root . '/lib/Scraper/EpiesaHtmlFetcher.php';
        if (!EpiesaHtmlFetcher::stealthAvailable()) {
            throw new InvalidArgumentException(
                'Stealth browser indisponibil — rulează: php admin/tools/setup_stealth_browser.php'
            );
        }

        return EpiesaScrapeJob::run($url, $limit);
    }

    /** @return array<string, mixed> */
    public function epiesaLatest(): array
    {
        return EpiesaScrapeJob::loadLatest();
    }

    /** @return array<string, mixed> */
    public function epiesaStats(): array
    {
        $stats = EpiesaCatalog::stats();
        require_once $this->root . '/lib/Scraper/EpiesaHtmlFetcher.php';
        $stats['has_token'] = \ScrapeDoConfig::hasToken();
        $stats['stealth_browser_ok'] = EpiesaHtmlFetcher::stealthAvailable();
        $stats['categories_presets'] = array_values(EpiesaCategories::presets());

        return $stats;
    }

    /** @return array<string, mixed> */
    public function epiesaCatalog(?string $categorySlug = null): array
    {
        $products = EpiesaCatalog::listProducts($categorySlug);

        return [
            'success' => true,
            'product_count' => count($products),
            'category' => $categorySlug ?? 'toate',
            'products' => $products,
        ];
    }

    /** @return array<string, mixed> */
    public function epiesaCacheImages(): array
    {
        $updated = EpiesaCatalog::refreshAllImages();

        return [
            'success' => true,
            'message' => $updated . ' imagini actualizate local.',
            'updated' => $updated,
            'total' => count(EpiesaCatalog::listProducts()),
        ];
    }

    public function epiesaLogs(int $lines = 120): string
    {
        return ScraperLogger::tail($lines);
    }

    public function epiesaProductCount(): int
    {
        return EpiesaCatalog::productCount();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function epiesaListProducts(?string $categorySlug = null): array
    {
        return EpiesaCatalog::listProducts($categorySlug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function epiesaListProductsLite(?string $categorySlug, int $limit, bool $withImages = true): array
    {
        return EpiesaCatalog::listProductsLite($categorySlug, $limit, $withImages);
    }

    /**
     * @return array<string, mixed>
     */
    public function epiesaListProductsPaginated(int $page, int $perPage, ?string $categorySlug, string $search = ''): array
    {
        return EpiesaCatalog::listProductsPaginated($page, $perPage, $categorySlug, $search);
    }

    /** @return array<string, int> */
    public function epiesaCategorySlugCounts(): array
    {
        return EpiesaCatalog::categorySlugCounts();
    }

    /** @return array<string, array<string, mixed>> */
    public function epiesaCategoryPresets(): array
    {
        return EpiesaCategories::presets();
    }

    public function epiesaCategoryLabel(string $slug): string
    {
        return EpiesaCategories::labelForSlug($slug);
    }

    /** @return array<string, mixed> */
    public function epiesaClearAll(): array
    {
        return EpiesaCatalog::clearAll();
    }

    public function hasActiveImagePlans(array $categories = []): bool
    {
        ImageSearchService::boot();

        return ImageSearchService::hasActiveImagePlans($categories);
    }

    /**
     * Router HTTP admin — scraper_endpoint.php delegă aici.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     * @return array{mode: 'json', status: int, body: array<string, mixed>}|array{mode: 'stream', url: string}
     */
    public function handleApiRequest(string $method, array $query = [], array $payload = []): array
    {
        $method = strtoupper($method);

        if ($method === 'GET') {
            $view = (string) ($query['view'] ?? 'latest');

            if ($view === 'stats') {
                return $this->jsonOk($this->epiesaStats());
            }
            if ($view === 'product_count') {
                return $this->jsonOk([
                    'product_count' => $this->epiesaProductCount(),
                ]);
            }
            if ($view === 'catalog') {
                $cat = trim((string) ($query['category'] ?? ''));

                return $this->jsonOk($this->epiesaCatalog($cat !== '' ? $cat : null));
            }
            if ($view === 'logs') {
                $lines = max(20, min(500, (int) ($query['lines'] ?? 120)));

                return $this->jsonOk(['log' => $this->epiesaLogs($lines)]);
            }
            if ($view === 'hub') {
                return $this->jsonOk($this->dashboard());
            }
            if ($view === 'sources') {
                return $this->jsonOk(['cards' => $this->listSourceCards()]);
            }
            if ($view === 'source') {
                $sid = trim((string) ($query['source_id'] ?? ''));
                if ($sid === '') {
                    throw new InvalidArgumentException('Lipseste source_id.');
                }

                return $this->jsonOk($this->getSourceConfig($sid));
            }
            if ($view === 'step_catalog') {
                return $this->jsonOk($this->getStepCatalog());
            }
            if ($view === 'integration') {
                return $this->jsonOk($this->getIntegrationConfig());
            }
            if ($view === 'image_proxy') {
                return ['mode' => 'stream', 'url' => (string) ($query['url'] ?? '')];
            }
            if ($view === 'rules') {
                return $this->jsonOk($this->getRules());
            }
            if ($view === 'vehicle_tree') {
                return $this->jsonOk($this->epiesaVehicleTreeStatus());
            }

            $data = $this->epiesaLatest();

            return [
                'mode' => 'json',
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => $data['message'] ?? 'OK',
                    'data' => $data,
                ],
            ];
        }

        if ($method !== 'POST') {
            return $this->jsonFail('Metoda nepermisa.', 405);
        }

        $action = (string) ($payload['action'] ?? $payload['type_product'] ?? 'scan');

        return match ($action) {
            'latest' => $this->jsonOk($this->epiesaLatest()),
            'stats' => $this->jsonOk($this->epiesaStats()),
            'catalog' => $this->jsonOk($this->epiesaCatalog(
                trim((string) ($payload['category'] ?? '')) !== '' ? trim((string) $payload['category']) : null
            )),
            'scan' => $this->jsonOkMessage(
                $this->epiesaRunScan($payload),
                fn (array $d): string => (string) ($d['message'] ?? 'Scan finalizat.')
            ),
            'cache_images' => $this->jsonOkMessage(
                $this->epiesaCacheImages(),
                fn (array $d): string => (string) ($d['message'] ?? 'Imagini actualizate.')
            ),
            'source_save' => $this->handleSourceSave($payload),
            'source_test' => $this->handleSourceTest($payload),
            'source_create' => $this->jsonOkMessage(
                $this->createSource($payload),
                fn (array $d): string => 'Sursa creata: ' . ($d['id'] ?? '')
            ),
            'source_delete' => $this->handleSourceDelete($payload),
            'source_restore_presets' => $this->handleRestorePresets(),
            'sync_all_sources' => $this->jsonOkMessage(
                ['active' => $this->syncAllSources()],
                static fn (): string => 'Sincronizare completa: image-search-sources.php + pipeline + cron.'
            ),
            'hub_save' => $this->jsonOkMessage(
                $this->saveConfig($payload),
                static fn (): string => 'Configurare salvata.'
            ),
            'test_fetch' => $this->jsonOkMessage(
                $this->testFetch($payload),
                fn (array $d): string => 'Fetch OK (' . ($d['html_length'] ?? 0) . ' bytes).'
            ),
            'agent_analyze_html' => $this->handleAgentAnalyzeHtml($payload),
            'analyze_saved_html' => $this->handleAnalyzeSavedHtml($payload),
            'test_parse' => $this->jsonOkMessage(
                $this->testParse($payload),
                fn (array $d): string => 'Parsare: ' . ($d['parsed_count'] ?? 0) . ' item(i).'
            ),
            'test_pipeline' => $this->jsonOkMessage(
                $this->testPipeline($payload),
                fn (array $d): string => ($d['winner'] ?? null) !== null
                    ? 'Pipeline: sursa castigatoare gasita.'
                    : 'Pipeline: fara rezultat.'
            ),
            'integration_save' => $this->jsonOkMessage(
                $this->saveIntegrationConfig($payload),
                static fn (): string => 'Pipeline imagini salvat — cron si import folosesc planurile 1→2→3.'
            ),
            'test_image_pipeline' => $this->handleTestImagePipeline($payload),
            'test_image_pipeline_step' => $this->handleTestImagePipelineStep($payload),
            'analyze_agent' => $this->jsonOkMessage(
                $this->analyzeAgent($payload),
                static fn (): string => 'Analiza finalizata.'
            ),
            'epiesa_vehicle_tree_crawl' => $this->jsonOkMessage(
                $this->epiesaVehicleTreeCrawl($payload),
                fn (array $d): string => (string) ($d['message'] ?? 'Arbore vehicule sincronizat.')
            ),
            'epiesa_vehicle_tree_start' => $this->jsonOkMessage(
                $this->epiesaVehicleTreeStart($payload),
                fn (array $d): string => (string) ($d['message'] ?? 'Bot arbore vehicule pornit.')
            ),
            'epiesa_vehicle_tree_stop' => $this->jsonOkMessage(
                $this->epiesaVehicleTreeStop(),
                static fn (array $d): string => (string) ($d['message'] ?? 'Oprire bot.')
            ),
            'epiesa_vehicle_tree_preview' => $this->jsonOk($this->epiesaVehicleTreePreview($payload)),
            default => $this->jsonFail('Actiune necunoscuta.', 422),
        };
    }

    /** @param array<string, mixed> $data */
    private function jsonOk(array $data): array
    {
        return ['mode' => 'json', 'status' => 200, 'body' => ['success' => true, 'data' => $data]];
    }

    /** @param callable(array<string, mixed>): string $messageFn @param array<string, mixed> $data */
    private function jsonOkMessage(array $data, callable $messageFn): array
    {
        return [
            'mode' => 'json',
            'status' => 200,
            'body' => ['success' => true, 'message' => $messageFn($data), 'data' => $data],
        ];
    }

    private function jsonFail(string $message, int $status = 422): array
    {
        return ['mode' => 'json', 'status' => $status, 'body' => ['success' => false, 'message' => $message]];
    }

    /** @param array<string, mixed> $payload */
    private function handleSourceSave(array $payload): array
    {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        if ($sid === '') {
            throw new InvalidArgumentException('Lipseste source_id.');
        }
        $cfg = is_array($payload['config'] ?? null) ? $payload['config'] : $payload;
        unset($cfg['source_id'], $cfg['action']);
        $saved = $this->saveSourceConfig($sid, $cfg);

        return $this->jsonOkMessage($saved, fn (): string => 'Configurare salvata pentru ' . $sid);
    }

    /** @param array<string, mixed> $payload */
    private function handleSourceTest(array $payload): array
    {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        $execLimit = (str_contains(strtolower($sid), 'autodoc')) ? 420 : 300;
        @set_time_limit($execLimit);
        @ini_set('max_execution_time', (string) $execLimit);
        if ($sid === '') {
            throw new InvalidArgumentException('Lipseste source_id.');
        }
        $data = $this->testSource($sid, $payload);

        return $this->jsonOkMessage(
            $data,
            fn (array $d): string => ($d['items_count'] ?? 0) . ' rezultate · scor ' . ($d['analysis']['quality_score'] ?? 0)
        );
    }

    /** @param array<string, mixed> $payload */
    private function handleSourceDelete(array $payload): array
    {
        $sid = trim((string) ($payload['source_id'] ?? ''));
        if ($sid === '') {
            throw new InvalidArgumentException('Lipseste source_id.');
        }
        $this->deleteSource($sid);

        return $this->jsonOkMessage(
            ['source_id' => $sid],
            static fn (): string => 'Sursa stearsa peste tot: scraper, pipeline, cron si image-search-sources.php.'
        );
    }

    private function handleRestorePresets(): array
    {
        $added = $this->restoreBuiltinPresets();

        return [
            'mode' => 'json',
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => $added > 0 ? "Au fost readaugate {$added} surse preset." : 'Toate preseturile exista deja.',
                'data' => ['added' => $added, 'cards' => $this->listSourceCards()],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function handleAgentAnalyzeHtml(array $payload): array
    {
        $data = $this->agentAnalyzeHtml($payload);

        return [
            'mode' => 'json',
            'status' => 200,
            'body' => [
                'success' => !empty($data['ok']) || !empty($data['selectors']['block']),
                'message' => !empty($data['items_count'])
                    ? 'Agent AI: ' . $data['items_count'] . ' produs(e) — ' . ($data['mode'] ?? 'analiza')
                    : ((string) ($data['explanation_ro'] ?? $data['error'] ?? 'Agent AI a analizat HTML.')),
                'data' => $data,
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function handleAnalyzeSavedHtml(array $payload): array
    {
        $data = $this->analyzeSavedHtml($payload);

        return $this->jsonOkMessage(
            $data,
            fn (array $d): string => 'Analiza HTML: ' . ($d['items_count'] ?? 0) . ' produs(e), '
                . (($d['diagnostics']['blocks_found'] ?? 0)) . ' bloc(uri) gasite.'
        );
    }

    /** @param array<string, mixed> $payload */
    private function handleTestImagePipeline(array $payload): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        $data = $this->testImagePipeline($payload);
        $hit = is_array($data['hit'] ?? null) ? $data['hit'] : null;
        $tried = is_array($data['tried'] ?? null) ? $data['tried'] : [];

        return $this->jsonOkMessage(
            $data,
            fn (): string => $hit && trim((string) ($hit['url'] ?? '')) !== ''
                ? 'Imagine gasita la plan ' . ($tried[count($tried) - 1]['tier'] ?? '?')
                : 'Nicio sursa nu a returnat imagine.'
        );
    }

    /** @param array<string, mixed> $payload */
    private function handleTestImagePipelineStep(array $payload): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        $data = $this->testImagePipelineStep($payload);
        $hit = is_array($data['hit'] ?? null) ? $data['hit'] : null;
        $tried = is_array($data['tried'] ?? null) ? $data['tried'] : [];

        return $this->jsonOkMessage(
            $data,
            fn (): string => $hit && trim((string) ($hit['url'] ?? '')) !== ''
                ? 'Plan ' . ($tried['tier'] ?? '?') . ': imagine gasita.'
                : 'Plan ' . ($tried['tier'] ?? '?') . ': ' . ($tried['message'] ?? 'fara rezultat')
        );
    }
}


