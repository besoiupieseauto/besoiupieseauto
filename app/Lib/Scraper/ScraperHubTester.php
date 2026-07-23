<?php

declare(strict_types=1);

require_once __DIR__ . '/ScrapeDoClient.php';
require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';
require_once __DIR__ . '/EmagSearchParser.php';
require_once __DIR__ . '/EmagSearch.php';
require_once __DIR__ . '/EpiesaSearch.php';

/**
 * Testare fetch + parsare scraping — folosit din admin /admin/scraper.
 */
final class ScraperHubTester
{
    /** @return array<string, mixed> */
    public static function rulesConfig(): array
    {
        $path = ScraperPaths::projectRoot() . '/config/scraper-rules.php';

        return is_file($path) ? (require $path) : ['sources' => [], 'scrape_do' => []];
    }

    /** @return array<string, mixed> */
    public static function hubConfig(): array
    {
        ScraperPaths::ensureDirs();
        $path = ScraperPaths::storageDir() . '/hub_config.json';
        if (!is_file($path)) {
            return self::defaultHubConfig();
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? array_replace_recursive(self::defaultHubConfig(), $data) : self::defaultHubConfig();
    }

    /** @param array<string, mixed> $config */
    public static function saveHubConfig(array $config): array
    {
        ScraperPaths::ensureDirs();
        $merged = array_replace_recursive(self::defaultHubConfig(), $config);
        $path = ScraperPaths::storageDir() . '/hub_config.json';
        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $merged;
    }

    /** @return array<string, mixed> */
    public static function defaultHubConfig(): array
    {
        return [
            'scrape_do' => [
                'timeout_sec' => 90,
                'super' => false,
                'render' => false,
                'save_raw' => true,
            ],
            'test_defaults' => [
                'source_id' => 'epiesa',
                'query' => 'ulei motor 5W30',
                'limit' => 5,
            ],
            'agent' => [
                'enabled' => true,
                'min_items_for_ok' => 1,
            ],
            'notes' => '',
        ];
    }

    /**
     * Fetch HTML pentru hub / StepRunner.
     * Implicit: stealth-browser-mcp (options.via = stealth_browser|auto|'').
     * scrape.do doar dacă via=scrape_do SAU fallback SCRAPER_FALLBACK_SCRAPE_DO=1.
     *
     * @param array<string, mixed> $options via, timeout_sec, super, render, save_raw, source_id, html_marker, wait_sec
     * @return array<string, mixed>
     */
    public static function testFetch(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL invalid.');
        }

        $hub = self::hubConfig();
        $timeout = max(15, min(180, (int) ($options['timeout_sec'] ?? $hub['scrape_do']['timeout_sec'] ?? 90)));
        $super = !empty($options['super'] ?? $hub['scrape_do']['super']);
        $render = !empty($options['render'] ?? $hub['scrape_do']['render']);
        $saveRaw = !array_key_exists('save_raw', $options) || !empty($options['save_raw']);
        $via = strtolower(trim((string) ($options['via'] ?? 'stealth_browser')));
        if ($via === '' || $via === 'auto') {
            $via = 'stealth_browser';
        }

        $preferStealth = in_array($via, ['stealth_browser', 'stealth', 'local'], true);
        $forceScrapeDo = in_array($via, ['scrape_do', 'scrape.do', 'scrapedo'], true);

        $stealthError = null;
        if ($preferStealth) {
            require_once __DIR__ . '/StealthBrowserClient.php';
            if (StealthBrowserClient::isAvailable()) {
                try {
                    return self::testFetchViaStealth($url, $options, $timeout, $saveRaw);
                } catch (Throwable $e) {
                    $stealthError = $e->getMessage();
                    ScraperLogger::log('warn', 'Hub testFetch stealth failed: ' . $stealthError);
                    if (!self::isScrapeDoFallbackEnabled()) {
                        throw new RuntimeException(
                            'Stealth browser a eșuat: ' . $stealthError
                            . ' — activează SCRAPER_FALLBACK_SCRAPE_DO=1 doar dacă vrei scrape.do ca rezervă.'
                        );
                    }
                }
            } elseif (!self::isScrapeDoFallbackEnabled() && !$forceScrapeDo) {
                throw new RuntimeException(
                    'Stealth browser indisponibil (tools/stealth-browser-mcp/venv). '
                    . 'Instalează nodriver sau setează SCRAPER_FALLBACK_SCRAPE_DO=1 + SCRAPE_DO_TOKEN.'
                );
            }
        }

        if ($preferStealth && !$forceScrapeDo && !self::isScrapeDoFallbackEnabled()) {
            throw new RuntimeException(
                $stealthError !== null
                    ? ('Stealth browser a eșuat: ' . $stealthError)
                    : 'Stealth browser indisponibil și fallback scrape.do este dezactivat.'
            );
        }

        return self::testFetchViaScrapeDo($url, $timeout, $super, $render, $saveRaw, $stealthError);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function testFetchViaStealth(string $url, array $options, int $timeout, bool $saveRaw): array
    {
        require_once __DIR__ . '/StealthBrowserClient.php';

        $started = microtime(true);
        $stealthOpts = [
            'timeout_sec' => (float) $timeout,
            'wait_sec' => (float) ($options['wait_sec'] ?? 8.0),
            'save_raw' => $saveRaw,
            'source_id' => (string) ($options['source_id'] ?? ''),
            'load_html_body' => true,
        ];
        $marker = trim((string) ($options['html_marker'] ?? ''));
        if ($marker !== '') {
            $stealthOpts['html_marker'] = $marker;
        }
        if (array_key_exists('force_headless', $options)) {
            $stealthOpts['force_headless'] = !empty($options['force_headless']);
        }

        $result = StealthBrowserClient::fetch($url, $stealthOpts);
        $html = StealthBrowserClient::resolveHtmlBody($result, 0);
        if ($html === '') {
            throw new RuntimeException('Stealth browser: HTML gol pentru ' . $url);
        }

        $durationMs = (int) ($result['duration_ms'] ?? round((microtime(true) - $started) * 1000));
        $rawRel = (string) ($result['raw_saved'] ?? '');

        if ($saveRaw && $rawRel === '') {
            ScraperPaths::ensureDirs();
            $slug = preg_replace('/[^a-z0-9_-]+/i', '_', parse_url($url, PHP_URL_HOST) . '_' . date('Ymd_His')) ?? 'fetch';
            $rawPath = ScraperPaths::rawDir() . '/stealth_' . $slug . '.html';
            file_put_contents($rawPath, $html);
            $rawRel = str_replace(ScraperPaths::projectRoot(), '', $rawPath);
        }

        ScraperLogger::log('info', 'Hub testFetch OK (stealth) ' . $url . ' (' . strlen($html) . ' bytes, ' . $durationMs . 'ms)');

        $out = [
            'url' => $url,
            'final_url' => (string) ($result['final_url'] ?? $url),
            'html_length' => strlen($html),
            'duration_ms' => $durationMs,
            'raw_saved' => $rawRel,
            'html_preview' => self::htmlPreview($html, 12000),
            'markers' => self::detectHtmlMarkers($html),
            'browser_profile' => is_array($result['browser_profile'] ?? null) ? $result['browser_profile'] : [],
            'request' => [
                'via' => 'stealth_browser',
                'timeout_sec' => $timeout,
                'super' => false,
                'render' => true,
                'engine' => (string) ($result['engine'] ?? 'stealth_browser_mcp'),
            ],
        ];

        if (self::htmlIsCloudflareChallenge($html)) {
            $out['cloudflare_challenge'] = true;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function testFetchViaScrapeDo(
        string $url,
        int $timeout,
        bool $super,
        bool $render,
        bool $saveRaw,
        ?string $stealthError = null
    ): array {
        $client = new ScrapeDoClient();
        if (!$client->hasToken()) {
            $hint = $stealthError !== null
                ? ' (stealth a eșuat: ' . $stealthError . ')'
                : '';
            throw new RuntimeException(
                'SCRAPE_DO_TOKEN lipsește în admin/.env' . $hint
                . ' — pentru hub folosește stealth (implicit) sau completează tokenul dacă SCRAPER_FALLBACK_SCRAPE_DO=1.'
            );
        }

        $started = microtime(true);
        $html = $client->fetchWithRetry($url, $timeout, $super, $render);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        if (self::htmlLooksLikeCloudflareError($html)) {
            throw new RuntimeException('scrape.do HTTP 524 — pagină Cloudflare timeout (origin prea lent)');
        }

        $rawPath = '';
        if ($saveRaw) {
            ScraperPaths::ensureDirs();
            $slug = preg_replace('/[^a-z0-9_-]+/i', '_', parse_url($url, PHP_URL_HOST) . '_' . date('Ymd_His')) ?? 'fetch';
            $rawPath = ScraperPaths::rawDir() . '/test_' . $slug . '.html';
            file_put_contents($rawPath, $html);
        }

        ScraperLogger::log(
            'info',
            'Hub testFetch OK (scrape.do' . ($stealthError !== null ? ' fallback' : '') . ') '
            . $url . ' (' . strlen($html) . ' bytes, ' . $durationMs . 'ms)'
        );

        return [
            'url' => $url,
            'html_length' => strlen($html),
            'duration_ms' => $durationMs,
            'raw_saved' => $rawPath !== '' ? str_replace(ScraperPaths::projectRoot(), '', $rawPath) : '',
            'html_preview' => self::htmlPreview($html, 12000),
            'markers' => self::detectHtmlMarkers($html),
            'request' => [
                'via' => 'scrape.do',
                'timeout_sec' => $timeout,
                'super' => $super,
                'render' => $render,
                'fallback_from_stealth' => $stealthError !== null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function testParse(string $sourceId, ?string $html = null, array $options = []): array
    {
        $rules = self::rulesConfig();
        $sources = is_array($rules['sources'] ?? null) ? $rules['sources'] : [];
        if (!isset($sources[$sourceId])) {
            throw new InvalidArgumentException('Sursă necunoscută: ' . $sourceId);
        }

        $meta = $sources[$sourceId];
        $query = trim((string) ($options['query'] ?? ''));
        $limit = max(1, min(50, (int) ($options['limit'] ?? 5)));
        $fetchUrl = trim((string) ($options['url'] ?? ''));

        $fetchResult = null;
        if ($html === null || trim($html) === '') {
            if ($fetchUrl === '') {
                $fetchUrl = self::buildUrlForSource($sourceId, $query, $meta);
            }
            if ($fetchUrl === '') {
                throw new InvalidArgumentException('Lipsește URL sau query pentru fetch.');
            }
            $fetchResult = self::testFetch($fetchUrl, $options);
            $html = (string) ($fetchResult['html_preview'] ?? '');
            // Re-read full HTML from saved file if preview truncated
            $rawSaved = (string) ($fetchResult['raw_saved'] ?? '');
            if ($rawSaved !== '') {
                $fullPath = ScraperPaths::projectRoot() . $rawSaved;
                if (is_file($fullPath)) {
                    $html = (string) file_get_contents($fullPath);
                }
            }
        }

        $parsed = self::runParser($sourceId, $html, $limit, $query);
        $analysis = self::analyzeParsed($sourceId, $parsed, $html, $meta);

        return [
            'source_id' => $sourceId,
            'source_label' => (string) ($meta['label'] ?? $sourceId),
            'query' => $query,
            'fetch_url' => $fetchUrl,
            'fetch' => $fetchResult,
            'rules' => [
                'parser_class' => $meta['parser_class'] ?? null,
                'selectors' => $meta['selectors'] ?? [],
                'ignore' => $meta['ignore'] ?? [],
                'output_fields' => $meta['output_fields'] ?? [],
            ],
            'parsed_count' => count($parsed),
            'parsed' => $parsed,
            'analysis' => $analysis,
        ];
    }

    /**
     * @param array<int, string>|null $sourceIds
     * @return array<string, mixed>
     */
    public static function testPipeline(string $query, ?array $sourceIds = null, int $limit = 3): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new InvalidArgumentException('Query gol.');
        }

        $imageConfigPath = ScraperPaths::projectRoot() . '/config/image-search-sources.php';
        $imageCfg = is_file($imageConfigPath) ? (require $imageConfigPath) : ['sources' => []];
        $allSources = is_array($imageCfg['sources'] ?? null) ? $imageCfg['sources'] : [];

        if ($sourceIds === null || $sourceIds === []) {
            $sourceIds = [];
            foreach ($allSources as $id => $src) {
                if (is_array($src) && !empty($src['enabled']) && in_array($id, ['epiesa', 'emag'], true)) {
                    $sourceIds[] = (string) $id;
                }
            }
        }

        $steps = [];
        $winner = null;

        foreach ($sourceIds as $sourceId) {
            $sourceId = trim((string) $sourceId);
            if ($sourceId === '') {
                continue;
            }

            $step = [
                'source_id' => $sourceId,
                'label' => (string) ($allSources[$sourceId]['label'] ?? $sourceId),
                'status' => 'skip',
                'result' => null,
                'error' => null,
            ];

            try {
                if ($sourceId === 'epiesa') {
                    $match = EpiesaSearch::findFirst($query);
                    $step['status'] = is_array($match) ? 'ok' : 'empty';
                    $step['result'] = $match;
                    if ($winner === null && is_array($match) && trim((string) ($match['image'] ?? '')) !== '') {
                        $winner = ['source' => 'epiesa', 'data' => $match];
                    }
                } elseif ($sourceId === 'emag') {
                    $product = ['pName' => $query, 'pBrand' => '', 'pCode' => '', 'pMarca' => '', 'pModel' => ''];
                    $match = EmagSearch::searchForProduct($product);
                    $step['status'] = !empty($match['ok']) ? 'ok' : 'empty';
                    $step['result'] = $match;
                    if ($winner === null && !empty($match['ok']) && trim((string) ($match['image_url'] ?? '')) !== '') {
                        $winner = ['source' => 'emag', 'data' => $match];
                    }
                } else {
                    $step['status'] = 'unsupported';
                    $step['error'] = 'Test pipeline doar epiesa/emag în acest panou.';
                }
            } catch (Throwable $e) {
                $step['status'] = 'error';
                $step['error'] = $e->getMessage();
            }

            $steps[] = $step;
            if ($winner !== null) {
                break;
            }
        }

        return [
            'query' => $query,
            'sources_tried' => $sourceIds,
            'steps' => $steps,
            'winner' => $winner,
            'integration_hint' => $winner !== null
                ? 'Rezultat OK — poate fi conectat la import via besoiu_image_search_try_*'
                : 'Nicio sursă nu a returnat imagine — verifică token scrape.do sau query',
        ];
    }

    /** @return array<string, mixed> */
    public static function envStatus(): array
    {
        $keys = [
            'SCRAPE_DO_TOKEN' => 'scrape.do',
            'IMAGE_SEARCH_SOURCES' => 'Ordine surse imagine',
            'CRON_CHECK_EPIESA' => 'Cron verifică ePiesa',
            'OPENAI_KEY' => 'OpenAI / vision audit',
            'IMAGE_AUDIT_ENGINE' => 'Motor audit',
            'RAPIDAPI_AUTOPARTS_KEY' => 'TecDoc RapidAPI',
        ];

        $rows = [];
        foreach ($keys as $key => $label) {
            $val = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'set' => $val !== '',
                'preview' => $val !== '' ? self::maskSecret($val) : '',
            ];
        }

        return ['variables' => $rows, 'scrape_do_ok' => ScrapeDoConfig::hasToken()];
    }

    /**
     * @param array<int, array<string, mixed>> $parsed
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function analyzeParsed(string $sourceId, array $parsed, string $html, array $meta = []): array
    {
        if ($meta === []) {
            $rules = self::rulesConfig();
            $meta = is_array($rules['sources'][$sourceId] ?? null) ? $rules['sources'][$sourceId] : [];
        }

        $issues = [];
        $suggestions = [];
        $score = 0;

        if (trim($html) === '') {
            $issues[] = 'HTML gol — fetch eșuat sau pagină blocată';
            $suggestions[] = 'Verifică SCRAPE_DO_TOKEN și URL; încearcă super=true sau render=true';
        } else {
            $score += 20;
            $markers = self::detectHtmlMarkers($html);
            if (empty($markers['found'])) {
                $issues[] = 'Niciun marker HTML cunoscut în răspuns';
                $suggestions[] = 'Pagina poate fi captcha/redirect — salvează raw HTML și inspectează';
            } else {
                $score += 20;
            }
        }

        $count = count($parsed);
        if ($count === 0) {
            $issues[] = 'Parserul nu a găsit produse';
            $suggestions[] = 'Verifică selectori în config/scraper-rules.php — site-ul poate fi schimbat';
        } else {
            $score += 30;
        }

        $withImage = 0;
        foreach ($parsed as $item) {
            $img = trim((string) ($item['image'] ?? $item['image_url'] ?? ''));
            if ($img !== '') {
                $withImage++;
            }
        }
        if ($count > 0 && $withImage === 0) {
            $issues[] = 'Produse fără imagine extrasă';
            $suggestions[] = 'Actualizează selector imagine sau reguli ignore placeholder';
        } elseif ($withImage > 0) {
            $score += 30;
        }

        $integrationReady = $count > 0 && $withImage > 0 && empty(array_filter($issues, static fn ($i) => str_contains($i, 'HTML gol')));

        return [
            'quality_score' => min(100, $score),
            'items_found' => $count,
            'items_with_image' => $withImage,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'integration_ready' => $integrationReady,
            'agent_summary_ro' => self::buildAgentSummaryRo($sourceId, $parsed, $issues, $suggestions, $integrationReady),
        ];
    }

    /** @return array<int, string> */
    private static function buildAgentSummaryRo(
        string $sourceId,
        array $parsed,
        array $issues,
        array $suggestions,
        bool $ready
    ): array {
        $lines = [];
        $lines[] = 'Sursă: ' . $sourceId . ' — ' . count($parsed) . ' item(i) parsat(e).';
        if ($ready) {
            $lines[] = 'Verdict: OK pentru legare la import/cron (există imagine + titlu).';
        } else {
            $lines[] = 'Verdict: necesită ajustări înainte de producție.';
        }
        foreach ($issues as $issue) {
            $lines[] = 'Problemă: ' . $issue;
        }
        foreach ($suggestions as $sug) {
            $lines[] = 'Sugestie: ' . $sug;
        }
        if ($ready && isset($parsed[0]) && is_array($parsed[0])) {
            $first = $parsed[0];
            $lines[] = 'Primul rezultat: ' . trim((string) ($first['title'] ?? '')) . ' | ' . trim((string) ($first['image'] ?? $first['image_url'] ?? ''));
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    private static function runParser(string $sourceId, string $html, int $limit, string $query): array
    {
        if ($sourceId === 'emag') {
            $card = EmagSearchParser::parseFirstCard($html);
            if ($card === null) {
                return [];
            }

            return [$card];
        }

        if (in_array($sourceId, ['epiesa', 'epiesa_category'], true)) {
            return EpiesaCategoryParser::parse($html, $limit);
        }

        throw new InvalidArgumentException('Parser neimplementat pentru: ' . $sourceId);
    }

    /** @param array<string, mixed> $meta */
    private static function buildUrlForSource(string $sourceId, string $query, array $meta): string
    {
        if ($sourceId === 'emag') {
            return EmagSearch::buildSearchUrl($query !== '' ? $query : (string) ($meta['example_query'] ?? 'ulei'));
        }

        $tpl = (string) ($meta['url_template'] ?? '');
        if ($tpl === '') {
            return '';
        }

        if (str_contains($tpl, '{query}')) {
            $q = $query !== '' ? $query : (string) ($meta['example_query'] ?? 'test');

            return str_replace('{query}', rawurlencode($q), $tpl);
        }

        if (!empty($meta['example_url'])) {
            return (string) $meta['example_url'];
        }

        return $tpl;
    }

    /**
     * Cloudflare interstitial / Turnstile / „Just a moment” — nu e lista de produse.
     * Nu confunda scripturi CF pe pagini deja deblocate (cdn-cgi/challenge-platform/jsd,
     * turnstile pe autodoc) cu challenge-ul real.
     */
    public static function htmlIsCloudflareChallenge(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $lower = mb_strtolower($html, 'UTF-8');

        // Pagina are deja conținut de listă → nu e interstitial CF
        if (self::htmlLooksLikeProductListing($lower)) {
            return false;
        }

        if (str_contains($lower, 'just a moment')) {
            return true;
        }
        if (str_contains($lower, 'cf-browser-verification')
            || str_contains($lower, 'cf-challenge-running')
            || str_contains($lower, 'id="challenge-form"')
            || str_contains($lower, "id='challenge-form'")) {
            return true;
        }
        if (str_contains($lower, 'attention required') && str_contains($lower, 'cloudflare')) {
            return true;
        }
        if (str_contains($lower, 'checking your browser') && str_contains($lower, 'cloudflare')) {
            return true;
        }
        if (str_contains($lower, 'enable javascript and cookies') && str_contains($lower, 'cloudflare')) {
            return true;
        }
        // Turnstile / challenge-platform fără listă produse = încă blocat
        if (str_contains($lower, 'challenges.cloudflare.com/turnstile')
            || str_contains($lower, 'turnstilecontainer')
            || str_contains($lower, 'cf-turnstile')
            || str_contains($lower, 'cdn-cgi/challenge-platform')) {
            return true;
        }

        return false;
    }

    /** Marker-e că HTML-ul e deja o listă de produse (nu interstitial). */
    private static function htmlLooksLikeProductListing(string $lowerHtml): bool
    {
        return str_contains($lowerHtml, 'prod-card')
            || str_contains($lowerHtml, 'sub-product-inner')
            || str_contains($lowerHtml, 'listing-item__wrap')
            || str_contains($lowerHtml, 'card-v2')
            || (str_contains($lowerHtml, 'product-auto-title') && str_contains($lowerHtml, 'epiesa'));
    }

    /**
     * Fallback scrape.do activ doar dacă SCRAPER_FALLBACK_SCRAPE_DO=1 în admin/.env.
     * Implicit OFF — scraperul folosește stealth-browser-mcp.
     */
    public static function isScrapeDoFallbackEnabled(): bool
    {
        $raw = '';
        if (function_exists('besoiu_env_get')) {
            $raw = besoiu_env_get('SCRAPER_FALLBACK_SCRAPE_DO');
        }
        if ($raw === '' || $raw === null) {
            $raw = (string) (getenv('SCRAPER_FALLBACK_SCRAPE_DO') ?: ($_ENV['SCRAPER_FALLBACK_SCRAPE_DO'] ?? ''));
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function htmlLooksLikeCloudflareError(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $lower = mb_strtolower($html, 'UTF-8');

        return str_contains($lower, 'error 524')
            || str_contains($lower, 'http 524')
            || (str_contains($lower, 'cloudflare') && str_contains($lower, 'timeout occurred'))
            || str_contains($lower, 'a timeout occurred');
    }

    /** @return array<string, mixed> */
    private static function detectHtmlMarkers(string $html): array
    {
        $lower = mb_strtolower($html, 'UTF-8');
        $checks = [
            'epiesa_sub_product' => str_contains($lower, 'sub-product-inner'),
            'epiesa_product_title' => str_contains($lower, 'product-auto-title'),
            'emag_card_v2' => str_contains($lower, 'card-v2'),
            'emag_cdn' => str_contains($lower, 'akamaized.net/products'),
            'autodoc_listing' => str_contains($lower, 'listing-item__wrap'),
            'autodoc_turnstile' => str_contains($lower, 'turnstilecontainer') || str_contains($lower, 'challenges.cloudflare.com/turnstile'),
            'captcha' => str_contains($lower, 'captcha') || str_contains($lower, 'cf-challenge'),
            'blocked' => str_contains($lower, 'access denied') || str_contains($lower, '403 forbidden'),
        ];

        $found = [];
        foreach ($checks as $key => $ok) {
            if ($ok) {
                $found[] = $key;
            }
        }

        return ['checks' => $checks, 'found' => $found];
    }

    private static function htmlPreview(string $html, int $maxLen): string
    {
        if (strlen($html) <= $maxLen) {
            return $html;
        }

        return substr($html, 0, $maxLen) . "\n\n<!-- … truncat " . (strlen($html) - $maxLen) . ' bytes -->';
    }

    private static function maskSecret(string $value): string
    {
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
    }
}
