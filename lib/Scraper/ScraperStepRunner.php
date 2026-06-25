<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperSourceStore.php';
require_once __DIR__ . '/ScraperStepSchema.php';
require_once __DIR__ . '/ScraperCssXPath.php';
require_once __DIR__ . '/ScraperHtmlAnalyzer.php';
require_once __DIR__ . '/ScraperHubTester.php';
require_once __DIR__ . '/ScrapeDoClient.php';
require_once __DIR__ . '/ScraperLogger.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';
require_once __DIR__ . '/EmagSearchParser.php';
require_once __DIR__ . '/EpiesaSearch.php';
require_once __DIR__ . '/EmagSearch.php';

/**
 * Execută pașii configurați per sursă (fetch → parse → follow).
 */
final class ScraperStepRunner
{
    /**
     * @param array<string, mixed> $options query, limit overrides
     * @return array<string, mixed>
     */
    public static function runSource(string $sourceId, ?array $config = null, array $options = []): array
    {
        $config = $config ?? ScraperSourceStore::load($sourceId);
        $query = trim((string) ($options['query'] ?? $config['test']['query'] ?? ''));
        $limit = max(1, min(20, (int) ($options['limit'] ?? $config['test']['limit'] ?? 5)));

        $registry = ScraperSourceStore::registry();
        $meta = is_array($registry[$sourceId] ?? null) ? $registry[$sourceId] : [];

        if (!empty($meta['no_html'])) {
            return self::runApiSource($sourceId, $query);
        }

        $steps = self::orderedSteps($config);
        $trace = [];
        $context = [
            'html' => '',
            'url' => '',
            'items' => [],
            'links' => [],
        ];

        foreach ($steps as $rawStep) {
            $step = ScraperStepSchema::flattenForRunner($rawStep);
            if (empty($step['enabled'])) {
                $trace[] = self::traceStep($step, 'skipped', 'Pas dezactivat — bifează «Activ pas» în Logică pași');
                continue;
            }

            $type = (string) ($step['type'] ?? '');
            try {
                if ($type === 'fetch' || !empty($step['is_login'])) {
                    $url = self::buildUrl((string) ($step['url_template'] ?? ''), $query);
                    $fetchOpts = self::resolveFetchOptions($config, $options);
                    if (!empty($step['is_login'])) {
                        $fetchOpts['render'] = true;
                        $fetchOpts['super'] = true;
                    }
                    $fetch = ScraperHubTester::testFetch($url, $fetchOpts);
                    $context['url'] = $url;
                    $context['html'] = self::loadFullHtml($fetch);
                    if (!empty($step['is_login'])) {
                        $login = is_array($step['login'] ?? null) ? $step['login'] : [];
                        $trace[] = self::traceStep($step, 'ok', 'Pagină login încărcată — verifică selectori user/parolă', [
                            'url' => $url,
                            'username_selector' => $login['username_selector'] ?? '',
                            'password_selector' => $login['password_selector'] ?? '',
                            'submit_selector' => $login['submit_selector'] ?? '',
                            'note' => 'Login automat complet necesită render JS; testează pasul următor după sesiune.',
                        ]);
                    } else {
                        $trace[] = self::traceStep($step, 'ok', strlen($context['html']) . ' bytes HTML', $fetch);
                    }
                } elseif ($type === 'parse_list' || $type === 'parse_detail') {
                    if ($context['html'] === '') {
                        throw new RuntimeException('Lipsește HTML — rulează Pas 1 (fetch) mai întâi.');
                    }
                    $items = self::parseList($sourceId, $step, $context['html'], $limit);
                    if ($type === 'parse_detail' && count($items) === 1) {
                        $context['items'] = array_merge($context['items'], $items);
                    } elseif ($type === 'parse_list') {
                        $context['items'] = $items;
                    } else {
                        $context['items'] = $items;
                    }
                    $context['links'] = array_values(array_filter(array_map(
                        static fn ($it) => (string) ($it['url'] ?? $it['product_url'] ?? ''),
                        $items
                    )));
                    $diag = ScraperHtmlAnalyzer::analyze($context['html'], $step, $limit);
                    $trace[] = self::traceStep($step, 'ok', count($items) . ' produse extrase', [
                        'items' => $items,
                        'diagnostics' => $diag,
                    ]);
                } elseif ($type === 'follow_links') {
                    if (empty($step['save_links']) && empty($step['enabled'])) {
                        $trace[] = self::traceStep($step, 'skipped', 'follow_links dezactivat');
                        continue;
                    }
                    $max = max(1, min(5, (int) ($step['max_follow'] ?? 1)));
                    $followed = [];
                    $links = array_slice($context['links'], 0, $max);
                    if ($links === []) {
                        $trace[] = self::traceStep($step, 'warn', 'Niciun link de urmat din pasul anterior');
                        continue;
                    }
                    $fetchOpts = self::resolveFetchOptions($config, $options);
                    foreach ($links as $link) {
                        if ($link === '') {
                            continue;
                        }
                        try {
                            $detailFetch = ScraperHubTester::testFetch($link, $fetchOpts);
                            $detailHtml = self::loadFullHtml($detailFetch);
                            $context['html'] = $detailHtml;
                            $context['url'] = $link;
                            $followed[] = [
                                'url' => $link,
                                'html_length' => strlen($detailHtml),
                                'preview' => substr($detailHtml, 0, 500),
                            ];
                        } catch (Throwable $e) {
                            $followed[] = ['url' => $link, 'error' => $e->getMessage()];
                        }
                    }
                    if (!empty($step['save_links'])) {
                        self::saveLinks($sourceId, $links);
                    }
                    $trace[] = self::traceStep($step, 'ok', count($followed) . ' pagini detaliu', ['followed' => $followed]);
                } else {
                    $trace[] = self::traceStep($step, 'skipped', 'Tip necunoscut: ' . $type);
                }
            } catch (Throwable $e) {
                $trace[] = self::traceStep($step, 'error', $e->getMessage());
                ScraperLogger::log('error', "StepRunner {$sourceId}: " . $e->getMessage());
                break;
            }
        }

        $analysis = ScraperHubTester::analyzeParsed($sourceId, $context['items'], $context['html']);

        $result = [
            'source_id' => $sourceId,
            'query' => $query,
            'trace' => $trace,
            'items' => $context['items'],
            'items_count' => count($context['items']),
            'analysis' => $analysis,
            'fields_needed' => $config['output']['fields_needed'] ?? [],
            'matched_fields' => self::matchedFields($context['items'], $config),
        ];

        ScraperSourceStore::saveLastTestMeta($sourceId, [
            'status' => !empty($analysis['integration_ready']) ? 'ok' : (count($context['items']) > 0 ? 'partial' : 'fail'),
            'items' => count($context['items']),
            'score' => (int) ($analysis['quality_score'] ?? 0),
            'query' => $query,
        ]);

        return $result;
    }

    /** @param array<string, mixed> $config @return list<array<string, mixed>> */
    private static function orderedSteps(array $config): array
    {
        $steps = is_array($config['steps'] ?? null) ? $config['steps'] : [];
        usort($steps, static fn ($a, $b) => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

        return $steps;
    }

    /** @param array<string, mixed> $fetch */
    private static function loadFullHtml(array $fetch): string
    {
        $raw = (string) ($fetch['raw_saved'] ?? '');
        if ($raw !== '') {
            $full = ScraperPaths::projectRoot() . $raw;
            if (is_file($full)) {
                return (string) file_get_contents($full);
            }
        }

        return (string) ($fetch['html_preview'] ?? '');
    }

    /** @param array<string, mixed> $config @param array<string, mixed> $options @return array<string, mixed> */
    private static function resolveFetchOptions(array $config, array $options): array
    {
        $base = is_array($config['fetch'] ?? null) ? $config['fetch'] : [];
        $out = [
            'timeout_sec' => max(15, min(180, (int) ($base['timeout_sec'] ?? 90))),
            'super' => !empty($base['super']),
            'render' => !empty($base['render']),
            'save_raw' => !isset($base['save_raw']) || !empty($base['save_raw']),
        ];
        if (array_key_exists('super', $options)) {
            $out['super'] = !empty($options['super']);
        }
        if (array_key_exists('render', $options)) {
            $out['render'] = !empty($options['render']);
        }
        if (isset($options['timeout_sec'])) {
            $out['timeout_sec'] = max(15, min(180, (int) $options['timeout_sec']));
        }

        return $out;
    }

    private static function buildUrl(string $template, string $query): string
    {
        $template = trim($template);
        if ($template === '') {
            throw new InvalidArgumentException('url_template gol în Pas 1.');
        }
        $q = $query !== '' ? $query : 'test';
        if (str_contains($template, '{query}')) {
            return str_replace('{query}', rawurlencode($q), $template);
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $step
     * @return list<array<string, mixed>>
     */
    public static function parseListForTest(string $sourceId, array $step, string $html, int $limit): array
    {
        return self::parseList($sourceId, $step, $html, $limit);
    }

    /**
     * @param array<string, mixed> $step
     * @return list<array<string, mixed>>
     */
    private static function parseList(string $sourceId, array $step, string $html, int $limit): array
    {
        $builtin = (string) ($step['parser_builtin'] ?? '');
        if ($builtin === 'EpiesaCategoryParser' || $sourceId === 'epiesa') {
            return EpiesaCategoryParser::parse($html, $limit);
        }
        if ($builtin === 'EmagSearchParser' || $sourceId === 'emag') {
            $card = EmagSearchParser::parseFirstCard($html);

            return $card !== null ? [$card] : [];
        }

        return self::parseListGeneric($html, $step, $limit);
    }

    /**
     * @param array<string, mixed> $step
     * @return list<array<string, mixed>>
     */
    private static function parseListGeneric(string $html, array $step, int $limit): array
    {
        $blockSel = trim((string) ($step['block_selector'] ?? ''));
        if ($blockSel === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $xpathQuery = ScraperCssXPath::toXPath($blockSel);
        $nodes = $xpath->query($xpathQuery);
        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $fieldMap = is_array($step['field_map'] ?? null) ? $step['field_map'] : [];
        $ignore = is_array($step['ignore_rules'] ?? null) ? $step['ignore_rules'] : [];
        $out = [];

        foreach ($nodes as $node) {
            if (count($out) >= $limit) {
                break;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }
            $item = [];
            foreach ($fieldMap as $field => $sel) {
                $item[$field] = self::extractField($xpath, $node, (string) $sel);
            }
            $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
            $skip = false;
            foreach ($ignore as $rule) {
                if ($rule !== '' && str_contains($blob, strtolower((string) $rule))) {
                    $skip = true;
                    break;
                }
            }
            if ($skip || trim((string) ($item['title'] ?? $item['url'] ?? '')) === '') {
                continue;
            }
            if ($sourceId === 'autodoc') {
                require_once __DIR__ . '/AutodocImageParser.php';
                $item = AutodocImageParser::sanitizeListingItem($item);
            }
            $out[] = $item;
        }

        return $out;
    }

    private static function extractField(DOMXPath $xpath, DOMElement $root, string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return '';
        }

        $attr = null;
        if (str_contains($selector, '@')) {
            [$selector, $attr] = explode('@', $selector, 2);
            $selector = trim($selector);
            $attr = trim((string) $attr);
        }

        $rel = ScraperCssXPath::toXPath($selector, true);
        $node = $xpath->query($rel, $root)?->item(0);

        if (!$node instanceof DOMElement) {
            return '';
        }

        if ($attr !== null && $attr !== '') {
            if ($attr === 'src' && strtolower($node->tagName) === 'img') {
                return self::resolveImageUrl($node);
            }

            return trim(html_entity_decode($node->getAttribute($attr), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (strtolower($node->tagName) === 'img') {
            return self::resolveImageUrl($node);
        }

        return trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
    }

    private static function resolveImageUrl(DOMElement $node): string
    {
        $fromSrcset = '';
        foreach (['data-srcset', 'srcset'] as $attr) {
            $srcset = trim($node->getAttribute($attr));
            if ($srcset === '') {
                continue;
            }
            if (preg_match_all('#https?://[^\s,]+#i', $srcset, $matches)) {
                foreach ($matches[0] as $candidate) {
                    $candidate = html_entity_decode(trim($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (!self::isRejectedImageUrl($candidate)) {
                        $fromSrcset = $candidate;
                        break 2;
                    }
                }
            }
        }

        $candidates = [
            $fromSrcset,
            html_entity_decode(trim($node->getAttribute('data-src')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            html_entity_decode(trim($node->getAttribute('src')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];

        foreach ($candidates as $url) {
            if ($url !== '' && !self::isRejectedImageUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    private static function isRejectedImageUrl(string $url): bool
    {
        $lower = strtolower($url);

        return $url === ''
            || str_starts_with($lower, 'data:')
            || str_contains($lower, 'lazyload.php')
            || str_contains($lower, 'brands/thumbs')
            || str_contains($lower, '360-icon')
            || str_ends_with($lower, '.svg');
    }

    /** @param list<string> $links */
    private static function saveLinks(string $sourceId, array $links): void
    {
        $path = ScraperSourceStore::sourcesDir() . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) . '_links.json';
        file_put_contents($path, json_encode(['saved_at' => date('c'), 'links' => $links], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @param list<array<string, mixed>> $items @param array<string, mixed> $config */
    private static function matchedFields(array $items, array $config): array
    {
        $needed = is_array($config['output']['fields_needed'] ?? null) ? $config['output']['fields_needed'] : [];
        $first = $items[0] ?? [];
        $matched = [];
        foreach ($needed as $field) {
            $field = (string) $field;
            $aliases = [$field, $field . '_url', 'product_' . $field];
            $val = '';
            foreach ($aliases as $key) {
                if (isset($first[$key]) && trim((string) $first[$key]) !== '') {
                    $val = (string) $first[$key];
                    break;
                }
            }
            $matched[$field] = ['found' => $val !== '', 'value' => $val !== '' ? mb_substr($val, 0, 120) : ''];
        }

        return $matched;
    }

  /** @return array<string, mixed> */
  private static function runApiSource(string $sourceId, string $query): array
  {
    return [
      'source_id' => $sourceId,
      'query' => $query,
      'trace' => [[
        'order' => 1,
        'label' => 'TecDoc API',
        'status' => 'info',
        'message' => 'Sursă API — test HTML nu se aplică. Folosește import TecDoc / image_search_pipeline.',
      ]],
      'items' => [],
      'items_count' => 0,
      'analysis' => ['quality_score' => 0, 'integration_ready' => false, 'issues' => ['Sursă API fără pași HTML']],
      'fields_needed' => [],
      'matched_fields' => [],
    ];
  }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    private static function traceStep(array $step, string $status, string $message, ?array $data = null): array
    {
        return [
            'order' => (int) ($step['order'] ?? 0),
            'label' => (string) ($step['label'] ?? ''),
            'type' => (string) ($step['type'] ?? ''),
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }
}
