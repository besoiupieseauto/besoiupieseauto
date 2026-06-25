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
     * @param array<string, mixed> $options
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

        $client = new ScrapeDoClient();
        if (!$client->hasToken()) {
            throw new RuntimeException('SCRAPE_DO_TOKEN lipsește în admin/.env');
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

        ScraperLogger::log('info', 'Hub testFetch OK ' . $url . ' (' . strlen($html) . ' bytes, ' . $durationMs . 'ms)');

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
            'CURSOR_API_KEY' => 'Cursor agent audit',
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
