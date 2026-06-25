<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperLlmConfig.php';
require_once __DIR__ . '/CursorScraperAgentClient.php';
require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperHtmlSample.php';
require_once __DIR__ . '/ScraperHtmlAnalyzer.php';
require_once __DIR__ . '/ScraperStepRunner.php';
require_once __DIR__ . '/ScraperStepSchema.php';
require_once __DIR__ . '/ScraperSourceStore.php';
require_once __DIR__ . '/ScraperLogger.php';

/**
 * Agent AI — analizează HTML salvat, propune selectori sau extrage direct produse.
 */
final class ScraperAiAgent
{
    /**
     * @param list<string> $fieldsNeeded
     * @param array{prefer_cursor?: bool} $options
     */
    public static function analyze(
        string $html,
        string $sourceId,
        string $userGoals,
        array $fieldsNeeded = ['title', 'image', 'url', 'price', 'sku'],
        int $limit = 5,
        array $options = []
    ): array {
        $html = trim($html);
        if ($html === '') {
            throw new InvalidArgumentException('HTML gol — rulează mai întâi Pas 1 (fetch).');
        }

        $fieldsNeeded = array_values(array_filter(array_map('strval', $fieldsNeeded)));
        if ($fieldsNeeded === []) {
            $fieldsNeeded = ['title', 'image', 'url', 'price'];
        }

        $context = ScraperHtmlSample::buildContext($html);
        $heuristic = self::heuristicSelectors($sourceId, $context);
        $preferCursor = array_key_exists('prefer_cursor', $options)
            ? !empty($options['prefer_cursor'])
            : ScraperLlmConfig::hasCursorKey();

        if ($preferCursor && ScraperLlmConfig::hasCursorKey()) {
            try {
                $cursor = self::askCursor($context, $sourceId, $userGoals, $fieldsNeeded, $heuristic);
                $selectors = self::normalizeSelectors(
                    is_array($cursor['selectors'] ?? null) ? $cursor['selectors'] : [],
                    $fieldsNeeded
                );
                if (isset($heuristic['block']) && ($selectors['block'] ?? '') === '') {
                    $selectors['block'] = (string) $heuristic['block'];
                }
                $validated = self::validateSelectors($html, $sourceId, $selectors, $limit);
                $items = $validated['items'];
                $itemsCount = $validated['items_count'];

                if ($itemsCount === 0 && is_array($cursor['items'] ?? null) && $cursor['items'] !== []) {
                    $items = self::normalizeAiItems($cursor['items'], $limit);
                    $itemsCount = count($items);
                }

                if ($itemsCount > 0 || $selectors !== []) {
                    return [
                        'ok' => $itemsCount > 0 || $selectors !== [],
                        'mode' => 'cursor-composer-2.5',
                        'llm_used' => true,
                        'provider' => 'cursor',
                        'model' => (string) ($cursor['model'] ?? 'composer-2.5'),
                        'selectors' => $selectors,
                        'items' => $items,
                        'items_count' => $itemsCount,
                        'diagnostics' => $validated['diagnostics'],
                        'explanation_ro' => (string) ($cursor['explanation_ro'] ?? 'Analiză Cursor Composer 2.5 finalizată.'),
                        'compass' => $context['compass'],
                        'html_hints' => $context['html_hints'],
                        'user_goals' => $userGoals,
                        'raw_llm' => $cursor['raw'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                ScraperLogger::log('warn', 'ScraperAiAgent Cursor: ' . $e->getMessage());
            }
        }

        if ($heuristic !== null) {
            $validated = self::validateSelectors($html, $sourceId, $heuristic, $limit);
            if ($validated['items_count'] > 0) {
                return [
                    'ok' => true,
                    'mode' => 'heuristic',
                    'llm_used' => false,
                    'provider' => null,
                    'selectors' => $heuristic,
                    'items' => $validated['items'],
                    'items_count' => $validated['items_count'],
                    'diagnostics' => $validated['diagnostics'],
                    'explanation_ro' => 'Am recunoscut structura site-ului din HTML (busolă DOM) și am aplicat selectori cunoscuți.',
                    'compass' => $context['compass'],
                    'html_hints' => $context['html_hints'],
                    'user_goals' => $userGoals,
                ];
            }
        }

        if (!ScraperLlmConfig::hasAnyKey()) {
            return [
                'ok' => false,
                'mode' => 'none',
                'llm_used' => false,
                'error' => 'Lipsește CURSOR_API_KEY, GROQ_KEY sau OPENAI_KEY în admin/.env pentru analiză AI.',
                'selectors' => $heuristic ?? [],
                'compass' => $context['compass'],
                'html_hints' => $context['html_hints'],
                'suggestions' => self::suggestionsFromCompass($context),
                'user_goals' => $userGoals,
            ];
        }

        $llm = self::askLlm($context, $sourceId, $userGoals, $fieldsNeeded, $heuristic);
        $selectors = is_array($llm['selectors'] ?? null) ? $llm['selectors'] : [];
        $selectors = self::normalizeSelectors($selectors, $fieldsNeeded);

        $validated = self::validateSelectors($html, $sourceId, $selectors, $limit);
        $items = $validated['items'];
        $itemsCount = $validated['items_count'];

        if ($itemsCount === 0 && is_array($llm['items'] ?? null) && $llm['items'] !== []) {
            $items = self::normalizeAiItems($llm['items'], $limit);
            $itemsCount = count($items);
        }

        return [
            'ok' => $itemsCount > 0 || $selectors !== [],
            'mode' => 'llm',
            'llm_used' => true,
            'provider' => (string) ($llm['provider'] ?? ''),
            'selectors' => $selectors,
            'items' => $items,
            'items_count' => $itemsCount,
            'diagnostics' => $validated['diagnostics'],
            'explanation_ro' => (string) ($llm['explanation_ro'] ?? 'Analiză AI finalizată.'),
            'compass' => $context['compass'],
            'html_hints' => $context['html_hints'],
            'user_goals' => $userGoals,
            'raw_llm' => $llm['raw'] ?? null,
        ];
    }

    /** @param array<string, string> $selectors */
    public static function applySelectorsToConfig(array $config, array $selectors): array
    {
        $labels = [
            'block' => 'Bloc produs',
            'title' => 'Titlu',
            'image' => 'Imagine',
            'url' => 'URL produs',
            'price' => 'Preț',
            'sku' => 'Cod articol',
            'description' => 'Descriere',
            'oem' => 'Cod OEM',
        ];

        foreach ($config['steps'] as &$step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'extract_list') {
                continue;
            }
            $params = is_array($step['params'] ?? null) ? $step['params'] : [];
            $elements = [];
            foreach ($selectors as $key => $selector) {
                $key = (string) $key;
                $selector = trim((string) $selector);
                if ($selector === '') {
                    continue;
                }
                $elements[] = [
                    'id' => 'el_' . $key,
                    'key' => $key,
                    'label' => $labels[$key] ?? ucfirst($key),
                    'selector' => $selector,
                ];
            }
            if ($elements !== []) {
                $params['elements'] = $elements;
                $step['params'] = $params;
                $step['enabled'] = true;
            }
            break;
        }
        unset($step);

        $config['steps'] = ScraperStepSchema::migrateSteps(is_array($config['steps'] ?? null) ? $config['steps'] : []);

        return $config;
    }

    /** @param array<string, mixed> $context @return array<string, string>|null */
    private static function heuristicSelectors(string $sourceId, array $context): ?array
    {
        $hints = is_array($context['html_hints'] ?? null) ? $context['html_hints'] : [];

        if ($sourceId === 'autodoc' || (int) ($hints['autodoc_listing_wrap'] ?? 0) > 0) {
            return [
                'block' => 'div.listing-item__wrap',
                'title' => 'a.listing-item__name',
                'image' => '.listing-item__image-product img@src',
                'url' => 'a.listing-item__name@href',
                'price' => '.listing-item__price-new',
                'sku' => '.listing-item__article-item',
            ];
        }

        if ((int) ($hints['emag_card_v2'] ?? 0) > 0) {
            return [
                'block' => 'div.card-v2',
                'title' => '.card-v2-title',
                'image' => 'img[src*="akamaized.net/products"]@src',
                'url' => 'a.card-v2-thumb@href',
                'price' => '.product-new-price',
            ];
        }

        if ((int) ($hints['epiesa_sub_product'] ?? 0) > 0) {
            return [
                'block' => 'div.sub-product-inner',
                'title' => '.product-auto-title a',
                'image' => '.sub-product-img img@src',
                'url' => '.product-auto-title a@href',
                'price' => '.bricolaje-bottom-text h4',
            ];
        }

        $defaults = ScraperSourceStore::defaultConfig($sourceId);
        foreach ((array) ($defaults['steps'] ?? []) as $step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'extract_list') {
                continue;
            }
            $flat = ScraperStepSchema::flattenForRunner($step);
            if (trim((string) ($flat['block_selector'] ?? '')) === '') {
                continue;
            }
            $out = ['block' => (string) $flat['block_selector']];
            foreach ((array) ($flat['field_map'] ?? []) as $k => $v) {
                if (trim((string) $v) !== '') {
                    $out[(string) $k] = (string) $v;
                }
            }

            return $out;
        }

        return null;
    }

    /**
     * @param array<string, string> $selectors
     * @return array{items: list<array<string, mixed>>, items_count: int, diagnostics: array<string, mixed>}
     */
    private static function validateSelectors(string $html, string $sourceId, array $selectors, int $limit): array
    {
        $step = [
            'type' => 'parse_list',
            'block_selector' => (string) ($selectors['block'] ?? ''),
            'field_map' => [],
            'ignore_rules' => ['placeholder', 'star-fill', '360-icon', 'brands/thumbs'],
            'limit' => $limit,
        ];
        foreach ($selectors as $key => $sel) {
            if ($key === 'block') {
                continue;
            }
            $step['field_map'][(string) $key] = (string) $sel;
        }

        $items = ScraperStepRunner::parseListForTest($sourceId, $step, $html, $limit);
        $diag = ScraperHtmlAnalyzer::analyze($html, $step, $limit);

        return [
            'items' => $items,
            'items_count' => count($items),
            'diagnostics' => $diag,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $fieldsNeeded
     * @param array<string, string>|null $heuristic
     * @return array<string, mixed>
     */
    private static function askCursor(
        array $context,
        string $sourceId,
        string $userGoals,
        array $fieldsNeeded,
        ?array $heuristic
    ): array {
        $client = new CursorScraperAgentClient(ScraperPaths::projectRoot());
        $response = $client->analyzeHtml([
            'source_id' => $sourceId,
            'page_title' => (string) ($context['title'] ?? ''),
            'user_goals' => $userGoals,
            'fields_needed' => $fieldsNeeded,
            'compass' => $context['compass'] ?? [],
            'snippet' => (string) ($context['snippet'] ?? ''),
            'heuristic' => $heuristic,
        ]);

        if (empty($response['ok'])) {
            throw new RuntimeException((string) ($response['error'] ?? 'Cursor Composer 2.5 a eșuat.'));
        }

        $response['provider'] = 'cursor';
        $response['model'] = (string) ($response['model'] ?? 'composer-2.5');

        return $response;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $fieldsNeeded
     * @param array<string, string>|null $heuristic
     * @return array<string, mixed>
     */
    private static function askLlm(
        array $context,
        string $sourceId,
        string $userGoals,
        array $fieldsNeeded,
        ?array $heuristic
    ): array {
        $compassLines = [];
        foreach ((array) ($context['compass'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $compassLines[] = ($row['token'] ?? '') . ' (' . ($row['count'] ?? 0) . '×)';
        }

        $fieldsJson = json_encode($fieldsNeeded, JSON_UNESCAPED_UNICODE);
        $heuristicJson = $heuristic !== null ? json_encode($heuristic, JSON_UNESCAPED_UNICODE) : 'null';

        $prompt = <<<PROMPT
Ești agent de scraping e-commerce (România). Analizezi HTML de listă produse.

Sursă: {$sourceId}
Pagină: {$context['title']}
Cerința operatorului: {$userGoals}
Câmpuri obligatorii JSON: {$fieldsJson}

Busolă DOM (clase repetate în HTML):
PROMPT;
        $prompt .= "\n" . implode(', ', array_slice($compassLines, 0, 18));
        $prompt .= <<<PROMPT


Sugestie heuristică (poate fi corectă sau nu): {$heuristicJson}

Reguli:
- Răspunde DOAR cu JSON valid (fără markdown).
- "selectors": CSS pentru un produs din listă — chei: block (container produs), plus câmpurile cerute.
- Pentru imagine: ".class img@src" sau "img@src"; pentru URL: "a@href".
- "items": max 3 produse extrase direct din HTML (fallback dacă selectori greși).
- "explanation_ro": 1-3 propoziții în română ce ai făcut.

Schema:
{"selectors":{"block":"...","title":"...","image":"...","url":"...","price":"...","sku":"..."},"items":[{"title":"...","image":"...","url":"...","price":"...","sku":"..."}],"explanation_ro":"..."}

HTML (fragment listă):
PROMPT;
        $prompt .= "\n" . (string) ($context['snippet'] ?? '');

        $groqKey = ScraperLlmConfig::groqKey();
        if ($groqKey !== '') {
            try {
                $raw = self::curlChat('https://api.groq.com/openai/v1/chat/completions', $groqKey, ScraperLlmConfig::groqModel(), $prompt);

                return self::parseLlmResponse($raw, 'groq');
            } catch (Throwable $e) {
                ScraperLogger::log('warn', 'ScraperAiAgent Groq: ' . $e->getMessage());
            }
        }

        $openaiKey = ScraperLlmConfig::openaiKey();
        if ($openaiKey === '') {
            throw new RuntimeException('Nici Groq, nici OpenAI disponibil.');
        }

        $raw = self::curlChat('https://api.openai.com/v1/chat/completions', $openaiKey, ScraperLlmConfig::openaiModel(), $prompt);

        return self::parseLlmResponse($raw, 'openai');
    }

    private static function curlChat(string $endpoint, string $apiKey, string $model, string $prompt): string
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'system', 'content' => 'Răspunzi doar JSON valid pentru configurare scraper.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new RuntimeException('LLM cURL: ' . ($err ?: 'eșec'));
        }
        if ($code >= 400) {
            throw new RuntimeException('LLM HTTP ' . $code . ': ' . mb_substr((string) $body, 0, 240));
        }

        $data = json_decode((string) $body, true);
        $content = (string) ($data['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            throw new RuntimeException('LLM răspuns gol.');
        }

        return $content;
    }

    /** @return array<string, mixed> */
    private static function parseLlmResponse(string $raw, string $provider): array
    {
        $jsonText = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            $jsonText = trim($m[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $jsonText = $m[0];
        }

        $data = json_decode($jsonText, true);
        if (!is_array($data)) {
            throw new RuntimeException('LLM nu a returnat JSON valid.');
        }

        $data['provider'] = $provider;
        $data['raw'] = mb_substr($raw, 0, 2000);

        return $data;
    }

    /**
     * @param array<string, mixed> $selectors
     * @param list<string> $fieldsNeeded
     * @return array<string, string>
     */
    private static function normalizeSelectors(array $selectors, array $fieldsNeeded): array
    {
        $out = [];
        if (isset($selectors['block'])) {
            $out['block'] = trim((string) $selectors['block']);
        }
        foreach ($fieldsNeeded as $field) {
            if (isset($selectors[$field])) {
                $out[$field] = trim((string) $selectors[$field]);
            }
        }

        return $out;
    }

    /** @param list<mixed> $items @return list<array<string, mixed>> */
    private static function normalizeAiItems(array $items, int $limit): array
    {
        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $context @return list<string> */
    private static function suggestionsFromCompass(array $context): array
    {
        $out = [];
        $hints = is_array($context['html_hints'] ?? null) ? $context['html_hints'] : [];
        if ((int) ($hints['autodoc_listing_wrap'] ?? 0) > 0) {
            $out[] = ScraperLlmConfig::hasCursorKey()
                ? 'Detectat catalog Autodoc — agentul poate folosi Cursor Composer 2.5.'
                : 'Detectat catalog Autodoc — adaugă CURSOR_API_KEY sau GROQ_KEY pentru auto-configurare.';
        }

        return $out;
    }
}
