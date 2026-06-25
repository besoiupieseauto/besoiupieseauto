<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperIntegrationStore.php';
require_once __DIR__ . '/ScraperImageSourcesSync.php';
require_once __DIR__ . '/ScrapeDoConfig.php';
require_once __DIR__ . '/ScraperSourceStore.php';
require_once __DIR__ . '/ScraperStepRunner.php';
require_once __DIR__ . '/AutodocImageParser.php';
require_once __DIR__ . '/ScraperHubTester.php';
require_once __DIR__ . '/PipelineImageAiGate.php';
require_once __DIR__ . '/ImportImageBridge.php';

/**
 * Rezolvă imagine + date produs — plan 1 → 2 → 3 (fallback).
 */
final class ScraperImageResolver
{
    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts categories, force, log callback
     * @return array{product: array<string, mixed>, hit: array<string, mixed>|null, tried: list<array<string, mixed>>}
     */
    public static function resolve(array $product, array $opts = []): array
    {
        $tried = [];
        $categories = is_array($opts['categories'] ?? null) ? $opts['categories'] : [];
        $force = !empty($opts['force']);
        $log = $opts['log'] ?? null;
        $stepStarted = microtime(true);
        $budgetSec = !empty($opts['background_job'])
            ? max(30, min(110, (int) ($opts['step_budget_sec'] ?? 90)))
            : 0;

        $plans = self::plansForProduct($categories);
        foreach ($plans as $plan) {
            if ($budgetSec > 0 && (microtime(true) - $stepStarted) >= $budgetSec) {
                self::log($log, 'Buget timp pas fundal atins — opresc planurile rămase.', 'warn');
                break;
            }
            $step = self::tryImagePlan($plan, $product, $force, $opts);
            $tried[] = $step['tried'];
            $tierLog = (int) ($step['tried']['tier'] ?? 0);
            $labelLog = (string) ($step['tried']['label'] ?? '');
            $sourceLog = (string) ($step['tried']['source_id'] ?? '');
            self::log($log, "Plan {$tierLog} ({$labelLog}): {$sourceLog} — " . ($step['tried']['message'] ?? ''), (string) ($step['tried']['status'] ?? 'info'));

            if ($step['hit'] !== null) {
                $hit = ImportImageBridge::normalizeHit($step['hit']);
                $product = self::applyHit($product, $hit, (string) ($plan['source_id'] ?? ''));

                return ['product' => $product, 'hit' => $hit, 'tried' => $tried];
            }
        }

        return ['product' => $product, 'hit' => null, 'tried' => $tried];
    }

    /**
     * Încearcă un singur plan din pipeline (pentru progres UI pas-cu-pas).
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts test_mode, force
     * @return array{tried: array<string, mixed>, hit: array<string, mixed>|null}
     */
    public static function tryImagePlan(array $plan, array $product, bool $force = false, array $opts = []): array
    {
        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
            if (empty($product['__image_enriched']) && function_exists('besoiu_image_enrich_product_context')) {
                $product = besoiu_image_enrich_product_context($product);
                $product['__image_enriched'] = true;
            }
        }

        $sourceId = trim((string) ($plan['source_id'] ?? ''));
        $tier = (int) ($plan['tier'] ?? 0);
        $label = trim((string) ($plan['label'] ?? 'Plan ' . $tier));

        if ($sourceId === '') {
            return [
                'tried' => [
                    'tier' => $tier,
                    'source_id' => '',
                    'label' => $label,
                    'status' => 'skipped',
                    'message' => 'Plan fără sursă selectată',
                ],
                'hit' => null,
            ];
        }

        if (!self::sourceIntegrationAllowsPipeline($sourceId)) {
            return [
                'tried' => [
                    'tier' => $tier,
                    'source_id' => $sourceId,
                    'label' => $label,
                    'status' => 'skipped',
                    'message' => 'Dezactivat în config sursă',
                ],
                'hit' => null,
            ];
        }

        $hit = self::trySource($sourceId, $product, $force, $opts);
        $status = is_array($hit) && trim((string) ($hit['url'] ?? '')) !== '' ? 'ok' : 'miss';
        $itemsFound = is_array($hit) ? (int) ($hit['items_found'] ?? 0) : 0;
        $message = is_array($hit)
            ? (string) ($hit['api_error'] ?? ($status === 'ok'
                ? ('Imagine găsită' . (trim((string) ($hit['query'] ?? '')) !== '' ? ' — «' . $hit['query'] . '»' : ''))
                : 'Fără rezultat'))
            : 'Handler indisponibil';
        if ($status === 'miss' && $message !== '' && $message !== 'Fără rezultat' && self::isFatalScrapeError($message)) {
            $status = 'error';
        }

        // Autodoc: produse găsite dar filtrate (ex. MASTER-SPORT vs TRW) — nu e eroare fatală
        if ($status === 'error' && $itemsFound > 0) {
            $status = 'miss';
        }
        if ($status === 'error' && str_contains($message, 'produse la') && str_contains($message, 'TRW/potrivit')) {
            $status = 'miss';
        }

        if ($status === 'ok' && is_array($hit) && empty($hit['ai_verdict'])) {
            $gate = PipelineImageAiGate::filterHit($product, $hit, $opts);
            if (is_array($hit)) {
                $hit['ai_score'] = (int) ($gate['score'] ?? 0);
                $hit['ai_verdict'] = (string) ($gate['verdict'] ?? '');
            }
            if (empty($gate['accepted'])) {
                $status = 'miss';
                $message = (string) ($gate['message'] ?? 'Imagine respinsă de regulile AI.');
                $hit = null;
            } else {
                $message = 'Imagine validată AI — ' . (string) ($gate['message'] ?? 'OK');
                if (is_array($hit) && trim((string) ($hit['query'] ?? '')) !== '') {
                    $message .= ' — «' . $hit['query'] . '»';
                }
            }
        } elseif ($status === 'ok' && is_array($hit) && !empty($hit['ai_verdict'])) {
            $message = 'Imagine validată AI — scor ' . (int) ($hit['ai_score'] ?? 0) . '/100 (' . (string) $hit['ai_verdict'] . ')';
            if (trim((string) ($hit['query'] ?? '')) !== '') {
                $message .= ' — «' . $hit['query'] . '»';
            }
        }

        return [
            'tried' => [
                'tier' => $tier,
                'source_id' => $sourceId,
                'label' => $label,
                'status' => $status,
                'message' => $message,
                'query_used' => is_array($hit) ? trim((string) ($hit['query'] ?? '')) : '',
                'duration_ms' => is_array($hit) ? (int) ($hit['duration_ms'] ?? 0) : 0,
            ],
            'hit' => ($status === 'ok' && is_array($hit)) ? $hit : null,
        ];
    }

    /**
     * @param array<int, string> $categories
     * @return list<array<string, mixed>>
     */
    public static function activeImagePlans(array $categories = []): array
    {
        return self::plansForProduct($categories);
    }

    /**
     * @param array<int, string> $categories
     * @return list<array<string, mixed>>
     */
    private static function plansForProduct(array $categories): array
    {
        $overlayPath = dirname(__DIR__, 2) . '/storage/scraper/image_pipeline_overlay.json';
        if (is_file($overlayPath)) {
            $overlay = json_decode((string) file_get_contents($overlayPath), true);
            if (is_array($overlay['image_plans'] ?? null) && $overlay['image_plans'] !== []) {
                $plans = [];
                foreach ($overlay['image_plans'] as $plan) {
                    if (!is_array($plan) || empty($plan['enabled'])) {
                        continue;
                    }
                    $plans[] = $plan;
                }
                if ($plans !== []) {
                    usort($plans, static fn (array $a, array $b): int => ((int) ($a['tier'] ?? 0)) <=> ((int) ($b['tier'] ?? 0)));

                    return self::dedupePlansBySource($plans);
                }
            }
        }

        if (class_exists('ScraperIntegrationStore', false)) {
            return ScraperIntegrationStore::activeImagePlans();
        }

        if (function_exists('besoiu_image_search_sources_ordered')) {
            $rows = besoiu_image_search_sources_ordered($categories);
            $plans = [];
            foreach ($rows as $i => $row) {
                $plans[] = [
                    'tier' => $i + 1,
                    'source_id' => $row['id'],
                    'label' => $row['label'],
                    'enabled' => true,
                    'roles' => $row['roles'] ?? ['image'],
                ];
            }

            return $plans;
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return list<array<string, mixed>>
     */
    private static function dedupePlansBySource(array $plans): array
    {
        $seen = [];
        $out = [];
        $tier = 0;
        foreach ($plans as $plan) {
            $id = trim((string) ($plan['source_id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $tier++;
            $plan['tier'] = $tier;
            $out[] = $plan;
        }

        return $out;
    }

    private static function sourceIntegrationAllowsPipeline(string $sourceId): bool
    {
        try {
            $cfg = ScraperSourceStore::load($sourceId);
            $int = is_array($cfg['integration'] ?? null) ? $cfg['integration'] : [];

            return !isset($int['use_in_image_pipeline']) || !empty($int['use_in_image_pipeline']);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    /**
     * @param array<string, mixed> $opts test_mode
     * @return array<string, mixed>|null
     */
    private static function trySource(string $sourceId, array $product, bool $force, array $opts = []): ?array
    {
        if (!ScraperImageSourcesSync::isSourceActive($sourceId)) {
            return ['url' => '', 'source' => $sourceId, 'api_error' => 'Sursă dezactivată / ștearsă din scraper'];
        }

        if (in_array($sourceId, ['epiesa', 'emag', 'autodoc', 'pieseauto', 'autovit'], true)) {
            if (ScrapeDoConfig::isQuotaExceeded()) {
                $usage = ScrapeDoConfig::budgetUsage();
                $left = is_array($usage) ? (int) ($usage['queries_left'] ?? 0) : 0;
                $hint = $left > 0
                    ? ''
                    : ' — actualizează tokenii rămași în Setări → Tokeni API → Scrape.do';

                return [
                    'url' => '',
                    'source' => $sourceId,
                    'api_error' => 'scrape.do: cotă epuizată în monitorul local' . $hint . ' — trec la planul următor',
                ];
            }
        }

        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
        }

        if ($sourceId === 'epiesa' && function_exists('besoiu_image_search_try_epiesa')) {
            return besoiu_image_search_try_epiesa($product);
        }

        if ($sourceId === 'emag') {
            if (!function_exists('besoiu_image_search_source_enabled')
                || !besoiu_image_search_source_enabled('emag')) {
                return ['url' => '', 'source' => 'emag', 'api_error' => 'eMAG dezactivat (lipsește din pipeline Scraper)'];
            }
            $emagPath = dirname(__DIR__, 2) . '/system/emag_image_search.php';
            if (is_file($emagPath)) {
                require_once $emagPath;
                if (function_exists('emag_find_image_for_product')) {
                    $r = emag_find_image_for_product($product);

                    return is_array($r) ? $r : null;
                }
            }
        }

        if ($sourceId === 'tecdoc_api') {
            $tecdocPath = dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
            if (is_file($tecdocPath)) {
                require_once $tecdocPath;
            }
            if (!empty($opts['test_mode']) && function_exists('tecdoc_maybe_clear_stale_quota_flag')) {
                tecdoc_maybe_clear_stale_quota_flag(1800);
            }
            if (function_exists('tecdoc_api_is_unavailable') && tecdoc_api_is_unavailable()) {
                $msg = function_exists('tecdoc_api_unavailable_message')
                    ? tecdoc_api_unavailable_message()
                    : 'Cota RapidAPI depășită.';

                return ['url' => '', 'source' => 'tecdoc_api', 'api_error' => $msg];
            }

            $fast = !empty($opts['fast_mode']);
            $r = ImportImageBridge::findFromOemCrossList($product, $fast);
            if (!is_array($r)) {
                return ['url' => '', 'source' => 'tecdoc_api', 'api_error' => 'TecDoc: răspuns invalid'];
            }
            if (trim((string) ($r['url'] ?? '')) !== '') {
                return $r;
            }
            $err = trim((string) ($r['api_error'] ?? ''));

            return array_merge($r, [
                'source' => 'tecdoc_api',
                'api_error' => $err !== '' ? $err : 'TecDoc: niciun cod OEM nu a returnat imagine',
            ]);
        }

        if ($sourceId === 'caietcomenzi' || $sourceId === 'tecdoc_csv') {
            return self::tryLocalImageSources($product, $sourceId);
        }

        return self::tryScraperSource($sourceId, $product, $opts);
    }

    /** @param array<string, mixed> $product @return array<string, mixed>|null */
    private static function tryLocalImageSources(array $product, string $sourceId): ?array
    {
        ImportImageBridge::boot();

        if ($sourceId === 'caietcomenzi' && function_exists('import_apply_caietcomenzi_image')) {
            $before = function_exists('import_row_image_url') ? import_row_image_url($product) : '';
            $p = import_apply_caietcomenzi_image($product);
            $after = function_exists('import_row_image_url') ? import_row_image_url($p) : '';
            if ($after !== '' && $after !== $before) {
                return ['url' => $after, 'source' => 'caietcomenzi'];
            }
        }

        if ($sourceId === 'tecdoc_csv') {
            $rawPayload = json_decode((string) ($product['raw_json'] ?? '{}'), true);
            $tecdocRows = is_array($rawPayload['rows'] ?? null) ? $rawPayload['rows'] : [];
            if ($tecdocRows !== [] && function_exists('import_base_resolve_image_from_rows')) {
                $imageMeta = import_base_resolve_image_from_rows($tecdocRows);
                $url = trim((string) ($imageMeta['url'] ?? ''));
                if ($url !== '') {
                    return ['url' => $url, 'source' => 'tecdoc_csv'];
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $opts */
    private static function tryScraperSource(string $sourceId, array $product, array $opts = []): ?array
    {
        if ($sourceId === 'autodoc') {
            return self::tryAutodocSource($product, $opts);
        }

        try {
            $registry = ScraperSourceStore::registry();
            if (!isset($registry[$sourceId])) {
                return null;
            }
            $config = ScraperSourceStore::load($sourceId);
            if (empty($config['enabled'])) {
                return ['url' => '', 'source' => $sourceId, 'api_error' => 'Sursă dezactivată'];
            }

            $queries = self::searchQueriesForProduct($product);
            if ($queries === []) {
                return null;
            }

            $last = null;
            foreach ($queries as $query) {
                $result = ScraperStepRunner::runSource($sourceId, $config, [
                    'query' => $query,
                    'limit' => 1,
                ]);
                $items = is_array($result['items'] ?? null) ? $result['items'] : [];
                $first = $items[0] ?? null;
                if (!is_array($first)) {
                    $last = ['url' => '', 'source' => $sourceId, 'api_error' => 'Scraper: fără rezultate pentru «' . $query . '»', 'query' => $query];
                    continue;
                }

                $image = trim((string) ($first['image'] ?? $first['image_url'] ?? ''));
                if ($image === '') {
                    $last = ['url' => '', 'source' => $sourceId, 'api_error' => 'Scraper: produs fără imagine', 'query' => $query];
                    continue;
                }

                return [
                    'url' => $image,
                    'source' => $sourceId . '_scraper',
                    'title' => (string) ($first['title'] ?? $first['name'] ?? ''),
                    'url_product' => (string) ($first['url'] ?? ''),
                    'query' => $query,
                ];
            }

            return $last;
        } catch (Throwable $e) {
            return ['url' => '', 'source' => $sourceId, 'api_error' => 'Scraper: ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts test_mode
     * @return array<string, mixed>|null
     */
    private static function tryAutodocSource(array $product, array $opts = []): ?array
    {
        $started = microtime(true);
        $testMode = !empty($opts['test_mode']);

        try {
            $config = ScraperSourceStore::load('autodoc');
            if (empty($config['enabled'])) {
                return ['url' => '', 'source' => 'autodoc', 'api_error' => 'Sursă dezactivată'];
            }

            $maxQueries = !empty($opts['background_job']) ? 2 : ($testMode ? 3 : 8);
            $maxRanked = $testMode ? 5 : 8;
            $listLimit = $testMode ? 8 : 12;
            $fetchOpts = is_array($config['fetch'] ?? null) ? $config['fetch'] : [];
            $defaultTimeout = !empty($opts['background_job']) ? 45 : 90;
            // Test pipeline: același timeout ca testul rapid din hub
            $scrapeTimeout = (int) ($fetchOpts['timeout_sec'] ?? $defaultTimeout);
            if (!empty($opts['background_job'])) {
                $scrapeTimeout = min($scrapeTimeout, 45);
            }

            if (ScrapeDoConfig::isQuotaExceeded()) {
                return [
                    'url' => '',
                    'source' => 'autodoc',
                    'api_error' => 'scrape.do: cotă lunară depășită — trec la planul următor',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }

            $queries = AutodocImageParser::buildSearchQueries($product);
            if ($testMode) {
                $userQuery = trim((string) ($opts['pipeline_test_query'] ?? $product['pName'] ?? ''));
                $prioritized = [];
                foreach ($queries as $q) {
                    if (preg_match('/\b(JTE\d|TRW\s+[A-Z0-9][A-Z0-9\-]{2,})/i', $q)) {
                        $prioritized[] = $q;
                    }
                }
                foreach ($queries as $q) {
                    $len = strlen($q);
                    $digits = preg_replace('/\D+/', '', $q) ?? '';
                    if ($len > 0 && $len <= 36 && strlen($digits) >= 5) {
                        $prioritized[] = $q;
                    }
                }
                if ($userQuery !== '') {
                    $prioritized[] = $userQuery;
                }
                foreach ($queries as $q) {
                    $prioritized[] = $q;
                }
                $queries = array_values(array_unique(array_filter(array_map('strval', $prioritized))));
            }
            if ($queries === []) {
                return null;
            }
            if ($testMode && count($queries) > $maxQueries) {
                $queries = array_slice($queries, 0, $maxQueries);
            } elseif (!$testMode && count($queries) > $maxQueries) {
                $queries = array_slice($queries, 0, $maxQueries);
            }

            $last = null;
            $attempt = 0;
            foreach ($queries as $query) {
                if (ScrapeDoConfig::isQuotaExceeded()) {
                    return array_merge($last ?? ['url' => '', 'source' => 'autodoc'], [
                        'api_error' => 'scrape.do: cotă lunară depășită — trec la planul următor',
                        'query' => $query,
                        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    ]);
                }

                $attempt++;
                if ($attempt > $maxQueries) {
                    break;
                }

                $runConfig = $config;
                $runConfig['fetch'] = array_merge($fetchOpts, ['timeout_sec' => $scrapeTimeout]);

                $result = ScraperStepRunner::runSource('autodoc', $runConfig, [
                    'query' => $query,
                    'limit' => $listLimit,
                    'timeout_sec' => $scrapeTimeout,
                ]);

                $scrapeErr = self::traceScrapeError(is_array($result['trace'] ?? null) ? $result['trace'] : []);
                if ($scrapeErr !== '') {
                    ScrapeDoConfig::noteQuotaExceededFromMessage($scrapeErr);
                    if (ScrapeDoConfig::isQuotaExceeded() || self::isFatalScrapeError($scrapeErr)) {
                        return [
                            'url' => '',
                            'source' => 'autodoc',
                            'api_error' => self::humanizeScrapeError($scrapeErr),
                            'query' => $query,
                            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                        ];
                    }
                }
                $items = is_array($result['items'] ?? null) ? $result['items'] : [];
                $items = AutodocImageParser::sanitizeListingItems($items);
                $ranked = array_slice(AutodocImageParser::rankMatches($items, $product, $query), 0, $maxRanked);
                if ($ranked === []) {
                    $foundN = count($items);
                    if ($foundN > 0 && !empty($opts['listing_fallback'])) {
                        $fallback = self::pickAutodocListingFallback($items, $product, $query, $started);
                        if ($fallback !== null) {
                            return $fallback;
                        }
                    }
                    $samples = [];
                    foreach (array_slice($items, 0, 3) as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $t = trim((string) ($row['title'] ?? ''));
                        if ($t !== '') {
                            $samples[] = mb_strlen($t) > 48 ? (mb_substr($t, 0, 45) . '…') : $t;
                        }
                    }
                    $sampleTxt = $samples !== [] ? (' Ex: ' . implode('; ', $samples) . '.') : '';
                    $hint = $foundN > 0
                        ? ('Autodoc: ' . $foundN . ' produse la «' . $query . '», dar niciunul TRW/potrivit.' . $sampleTxt . ' Trec la query/plan următor.')
                        : ('Autodoc: fără rezultate pentru «' . $query . '»');
                    $last = ['url' => '', 'source' => 'autodoc', 'api_error' => $hint, 'query' => $query, 'items_found' => $foundN];
                    continue;
                }

                foreach ($ranked as $best) {
                    if (!is_array($best)) {
                        continue;
                    }

                    $detailUrl = AutodocImageParser::cleanProductUrl((string) ($best['url'] ?? ''));
                    $image = '';

                    // Test UI: doar imagine din listă (evită 2× scrape.do → timeout Cloudflare 524)
                    if (!$testMode && $detailUrl !== '') {
                        $detailFetch = ScraperHubTester::testFetch($detailUrl, [
                            'timeout_sec' => $scrapeTimeout,
                            'super' => !empty($fetchOpts['super']),
                            'render' => !empty($fetchOpts['render']),
                            'save_raw' => false,
                        ]);
                        $detailHtml = (string) ($detailFetch['html_preview'] ?? '');
                        if ($detailHtml === '' && !empty($detailFetch['raw_saved'])) {
                            $full = dirname(__DIR__, 2) . (string) $detailFetch['raw_saved'];
                            if (is_file($full)) {
                                $detailHtml = (string) file_get_contents($full);
                            }
                        }
                        $image = AutodocImageParser::extractDetailPageImage($detailHtml);
                    }

                    if ($image === '') {
                        $image = AutodocImageParser::upgradeImageUrl(trim((string) ($best['image'] ?? $best['image_url'] ?? '')));
                    }

                    if ($image === '') {
                        $last = ['url' => '', 'source' => 'autodoc', 'api_error' => 'Autodoc: produs găsit dar fără imagine validă', 'query' => $query];
                        continue;
                    }

                    $candidate = [
                        'url' => $image,
                        'source' => 'autodoc_scraper',
                        'title' => (string) ($best['title'] ?? $best['name'] ?? ''),
                        'url_product' => $detailUrl,
                        'sku' => (string) ($best['sku'] ?? ''),
                        'query' => $query,
                        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    ];

                    $gateOpts = $opts;
                    if ($testMode) {
                        $gateOpts['skip_vision'] = true;
                    }
                    $gate = PipelineImageAiGate::filterHit($product, $candidate, $gateOpts);
                    if (!empty($gate['accepted'])) {
                        $candidate['ai_score'] = (int) ($gate['score'] ?? 0);
                        $candidate['ai_verdict'] = (string) ($gate['verdict'] ?? '');

                        return $candidate;
                    }

                    $last = [
                        'url' => '',
                        'source' => 'autodoc',
                        'api_error' => (string) ($gate['message'] ?? 'Imagine respinsă de regulile AI'),
                        'query' => $query,
                    ];
                }
            }

            if (is_array($last)) {
                $last['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
            }

            return $last;
        } catch (Throwable $e) {
            ScrapeDoConfig::noteQuotaExceededFromMessage($e->getMessage());

            return [
                'url' => '',
                'source' => 'autodoc',
                'api_error' => 'Autodoc: ' . self::humanizeScrapeError($e->getMessage()),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    /**
     * Produs fără imagine în BD — acceptă prima poză validă din listă Autodoc (ca testul manual din Scraper).
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private static function pickAutodocListingFallback(array $items, array $product, string $query, float $started): ?array
    {
        $items = AutodocImageParser::sanitizeListingItems($items);
        if ($items === []) {
            return null;
        }

        $nameLower = mb_strtolower(trim((string) ($product['pName'] ?? '')), 'UTF-8');
        $catLower = mb_strtolower(trim((string) ($product['pCategory'] ?? '') . ' ' . (string) ($product['pSubcategory'] ?? '')), 'UTF-8');
        $keywords = [];
        foreach (['cap de bara', 'cap bara', 'bucsa', 'bucșă', 'brat', 'disc frana', 'sonda', 'lichid', 'filtru', 'amortizor'] as $kw) {
            if (str_contains($nameLower, $kw) || str_contains($catLower, $kw)) {
                $keywords[] = $kw;
            }
        }
        if ($keywords === [] && $nameLower !== '') {
            foreach (preg_split('/\s+/', $nameLower) ?: [] as $word) {
                $word = trim((string) $word);
                if (mb_strlen($word, 'UTF-8') >= 4) {
                    $keywords[] = $word;
                }
            }
        }

        $best = null;
        $bestScore = -1;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['name'] ?? ''));
            $titleLower = mb_strtolower($title, 'UTF-8');
            $image = AutodocImageParser::upgradeImageUrl(trim((string) ($row['image'] ?? $row['image_url'] ?? '')));
            if ($image === '' || $title === '') {
                continue;
            }

            $score = 10;
            foreach ($keywords as $kw) {
                if (str_contains($titleLower, $kw)) {
                    $score += 25;
                } elseif ($kw === 'cap bara' && str_contains($titleLower, 'cap de bara')) {
                    $score += 25;
                } elseif ($kw === 'bucsa' && str_contains($titleLower, 'bucșă')) {
                    $score += 25;
                }
            }
            if (str_contains($titleLower, 'cap') && str_contains($nameLower, 'cap')) {
                $score += 15;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'url' => $image,
                    'source' => 'autodoc_scraper',
                    'title' => $title,
                    'url_product' => AutodocImageParser::cleanProductUrl((string) ($row['url'] ?? '')),
                    'sku' => (string) ($row['sku'] ?? ''),
                    'query' => $query,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'ai_verdict' => 'partial',
                    'ai_score' => 72,
                    'listing_fallback' => true,
                ];
            }
        }

        return $best;
    }

    /** @param list<array<string, mixed>> $trace */
    private static function traceScrapeError(array $trace): string
    {
        foreach ($trace as $step) {
            if (!is_array($step)) {
                continue;
            }
            if (($step['status'] ?? '') === 'error') {
                return trim((string) ($step['message'] ?? ''));
            }
        }

        return '';
    }

    private static function isFatalScrapeError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'http 401')
            || str_contains($lower, 'http 402')
            || str_contains($lower, 'http 502')
            || str_contains($lower, 'http 503')
            || str_contains($lower, 'http 520')
            || str_contains($lower, 'http 524')
            || str_contains($lower, 'error 524')
            || str_contains($lower, 'cloudflare')
            || str_contains($lower, 'rotation_failed')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'limit exceeded')
            || str_contains($lower, 'lipsește scrape_do_token')
            || str_contains($lower, 'scrape_do_token lipsește');
    }

    private static function humanizeScrapeError(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'http 524') || str_contains($lower, 'error 524')) {
            return 'Autodoc/scrape.do timeout (524) — serverul a răspuns prea greu; trec la planul următor';
        }
        if (str_contains($lower, 'http 502') || str_contains($lower, 'rotation')) {
            return 'scrape.do indisponibil temporar (502) — reîncearcă sau trec la TecDoc';
        }
        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return 'Timeout scrape.do — Autodoc prea lent; trec la planul următor';
        }
        if (str_contains($lower, 'monthly request limit') || str_contains($lower, 'limit exceeded')) {
            return 'scrape.do: cotă lunară depășită — upgrade sau așteaptă perioada nouă';
        }
        if (str_contains($lower, 'http 401')) {
            return 'scrape.do: token invalid sau cotă depășită (HTTP 401)';
        }
        if (str_contains($lower, 'scrape_do_token')) {
            return 'Lipsește SCRAPE_DO_TOKEN în admin/.env';
        }

        return $message;
    }

    /** @return list<string> */
    private static function searchQueriesForProduct(array $product): array
    {
        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;

            return besoiu_image_search_queries_for_product($product);
        }

        $name = trim((string) ($product['pName'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));

        return array_values(array_filter([$name, $code]));
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    private static function applyHit(array $product, array $hit, string $sourceId): array
    {
        $hit['source'] = trim((string) ($hit['source'] ?? $sourceId));

        return ImportImageBridge::applyHit($product, $hit);
    }

    /**
     * Aplică obiective extragere + validare RapidAPI după scrape.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $scraped
     * @param array<string, mixed> $integration
     * @return array<string, mixed>
     */
    public static function applyExtractionGoals(array $product, array $scraped, array $integration): array
    {
        $goals = is_array($integration['extraction_goals'] ?? null) ? $integration['extraction_goals'] : [];
        $rapidapi = is_array($integration['rapidapi'] ?? null) ? $integration['rapidapi'] : [];

        foreach ($goals as $goal) {
            if (!is_array($goal) || empty($goal['enabled'])) {
                continue;
            }
            $type = (string) ($goal['type'] ?? '');
            $mapTo = trim((string) ($goal['map_to'] ?? ''));

            if ($type === 'oem_codes' && isset($scraped['oem'])) {
                $product['pOem'] = (string) $scraped['oem'];
            }
            if ($type === 'description' && isset($scraped['description'])) {
                $product['pNote'] = (string) $scraped['description'];
            }
            if ($type === 'sku' && isset($scraped['sku'])) {
                $product['pCode'] = (string) $scraped['sku'];
            }
            if ($type === 'gap_fill') {
                foreach (['title' => 'pName', 'description' => 'pNote', 'oem' => 'pOem', 'sku' => 'pCode'] as $sk => $pk) {
                    if (isset($scraped[$sk]) && trim((string) ($product[$pk] ?? '')) === '') {
                        $product[$pk] = (string) $scraped[$sk];
                    }
                }
            }
            if ($type === 'custom_text' && $mapTo !== '' && $mapTo !== 'custom' && isset($scraped[$mapTo])) {
                $product[$mapTo] = (string) $scraped[$mapTo];
            }
        }

        if (!empty($rapidapi['validate_on_import']) || self::goalEnabled($goals, 'rapidapi_validate')) {
            $product = self::validateWithRapidApi($product, $scraped);
        }

        return $product;
    }

    /** @param list<array<string, mixed>> $goals */
    private static function goalEnabled(array $goals, string $type): bool
    {
        foreach ($goals as $g) {
            if (is_array($g) && ($g['type'] ?? '') === $type && !empty($g['enabled'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $scraped
     * @return array<string, mixed>
     */
    private static function validateWithRapidApi(array $product, array $scraped): array
    {
        $tecdocPath = dirname(__DIR__, 2) . '/system/tecdoc_stock.php';
        if (!is_file($tecdocPath)) {
            return $product;
        }
        require_once $tecdocPath;

        $code = trim((string) ($scraped['sku'] ?? $scraped['oem'] ?? $product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? $scraped['brand'] ?? ''));
        if ($code === '' || !function_exists('tecdoc_find_article_for_import')) {
            return $product;
        }

        $article = tecdoc_find_article_for_import($code, $brand);
        $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $raw['scraper_validation'] = [
            'validated_at' => date('c'),
            'query_code' => $code,
            'brand' => $brand,
            'found' => is_array($article) && !empty($article),
            'article_id' => is_array($article) ? ($article['articleId'] ?? $article['article_id'] ?? null) : null,
        ];

        if (is_array($article)) {
            if (trim((string) ($product['pOem'] ?? '')) === '' && !empty($article['oemNumbers'])) {
                $oems = is_array($article['oemNumbers']) ? $article['oemNumbers'] : [];
                $product['pOem'] = implode(', ', array_slice(array_map('strval', $oems), 0, 20));
            }
            if (function_exists('tecdoc_find_image_payload_from_search_codes')) {
                $img = tecdoc_find_image_payload_from_search_codes([$code], $brand);
                $imgUrl = trim((string) ($img['url'] ?? ''));
                if ($imgUrl !== '' && trim((string) ($product['pImages'] ?? '[]')) === '[]') {
                    $product['pImages'] = json_encode([$imgUrl], JSON_UNESCAPED_UNICODE);
                    $product['pImageSource'] = 'tecdoc_api';
                }
            }
        }

        $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $product;
    }

    /** @param callable|null $log */
    private static function log($log, string $msg, string $level = 'info'): void
    {
        if (is_callable($log)) {
            $log($msg, $level);
        }
    }
}
