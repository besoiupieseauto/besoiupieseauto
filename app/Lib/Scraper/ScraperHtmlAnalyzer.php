<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperCssXPath.php';

/**
 * Diagnostic parsare HTML — ce găsește fiecare selector, de ce se sare itemi.
 */
final class ScraperHtmlAnalyzer
{
    /** @return array<string, mixed> */
    public static function analyze(string $html, array $step, int $limit = 5, int $sampleBlocks = 3): array
    {
        $blockSel = trim((string) ($step['block_selector'] ?? ''));
        $fieldMap = is_array($step['field_map'] ?? null) ? $step['field_map'] : [];
        $ignore = is_array($step['ignore_rules'] ?? null) ? $step['ignore_rules'] : [];
        $hints = self::scanHtmlHints($html);

        if ($blockSel === '') {
            return [
                'ok' => false,
                'problem' => 'Selector bloc produs gol — adaugă element «Bloc produs» în Pas 2.',
                'blocks_found' => 0,
                'items_valid' => 0,
                'html_hints' => $hints,
                'suggestions' => self::suggestionsFromHints($hints),
            ];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $xpathBlock = ScraperCssXPath::toXPath($blockSel);
        $nodes = $xpath->query($xpathBlock);
        $blocksFound = ($nodes !== false) ? $nodes->length : 0;

        if ($blocksFound === 0) {
            return [
                'ok' => false,
                'problem' => 'Niciun bloc nu se potrivește cu selectorul «' . $blockSel . '».',
                'block_selector' => $blockSel,
                'xpath_block' => $xpathBlock,
                'blocks_found' => 0,
                'items_valid' => 0,
                'html_hints' => $hints,
                'suggestions' => self::suggestionsFromHints($hints, $blockSel),
            ];
        }

        $fieldStats = [];
        foreach ($fieldMap as $field => $sel) {
            $sel = (string) $sel;
            $fieldStats[$field] = [
                'selector' => $sel,
                'xpath' => $sel !== '' ? ScraperCssXPath::toXPath(preg_replace('/@[^@]+$/', '', $sel) ?: $sel, true) : '',
                'non_empty' => 0,
            ];
        }

        $skipReasons = [];
        $samples = [];
        $valid = 0;
        $idx = 0;

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $item = [];
                foreach ($fieldMap as $field => $sel) {
                    $val = self::extractField($xpath, $node, (string) $sel);
                    $item[$field] = $val;
                    if (trim($val) !== '' && isset($fieldStats[$field])) {
                        $fieldStats[$field]['non_empty']++;
                    }
                }

                $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
                $reason = null;
                foreach ($ignore as $rule) {
                    $rule = (string) $rule;
                    if ($rule !== '' && str_contains($blob, strtolower($rule))) {
                        $reason = 'ignore:' . $rule;
                        break;
                    }
                }
                if ($reason === null && trim((string) ($item['title'] ?? $item['url'] ?? '')) === '') {
                    $reason = 'lipsă titlu și URL';
                }

                if ($reason !== null) {
                    $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;
                } else {
                    $valid++;
                    if (count($samples) < $sampleBlocks && count($samples) < $limit) {
                        $samples[] = [
                            'index' => $idx + 1,
                            'fields' => array_map(
                                static fn (string $v): string => mb_strlen($v) > 160 ? mb_substr($v, 0, 160) . '…' : $v,
                                $item
                            ),
                        ];
                    }
                }

                $idx++;
                if ($idx >= max($limit, $sampleBlocks) * 4) {
                    break;
                }
            }
        }

        $ok = $valid > 0;

        return [
            'ok' => $ok,
            'problem' => $ok ? null : self::mainProblem($skipReasons, $fieldStats, $blocksFound),
            'block_selector' => $blockSel,
            'xpath_block' => $xpathBlock,
            'blocks_found' => $blocksFound,
            'blocks_scanned' => $idx,
            'items_valid' => $valid,
            'field_stats' => $fieldStats,
            'skip_reasons' => $skipReasons,
            'samples' => $samples,
            'ignore_rules' => $ignore,
            'html_hints' => $hints,
            'suggestions' => $ok ? [] : self::suggestionsFromHints($hints, $blockSel, $skipReasons, $fieldStats),
        ];
    }

    /** @return array<string, int|bool> */
    private static function scanHtmlHints(string $html): array
    {
        $lower = mb_strtolower($html, 'UTF-8');

        return [
            'bytes' => strlen($html),
            'autodoc_listing_wrap' => substr_count($lower, 'listing-item__wrap'),
            'autodoc_listing_item' => substr_count($lower, 'listing-item'),
            'autodoc_turnstile' => str_contains($lower, 'turnstile') || str_contains($lower, 'challenges.cloudflare.com'),
            'epiesa_sub_product' => substr_count($lower, 'sub-product-inner'),
            'emag_card_v2' => substr_count($lower, 'card-v2'),
            'product_links' => substr_count($lower, 'data-article-id'),
        ];
    }

    /** @param array<string, int> $skipReasons @param array<string, array<string, mixed>> $fieldStats */
    private static function mainProblem(array $skipReasons, array $fieldStats, int $blocksFound): string
    {
        if ($blocksFound === 0) {
            return 'Selector bloc nu găsește nimic în HTML.';
        }
        if (isset($skipReasons['lipsă titlu și URL'])) {
            return 'Blocuri găsite, dar titlu/URL lipsesc — verifică selectori title și url.';
        }
        if ($skipReasons !== []) {
            $top = array_key_first($skipReasons);

            return 'Blocuri găsite, dar filtrate: ' . $top . ' (' . $skipReasons[$top] . '×).';
        }
        foreach (['title', 'url'] as $key) {
            if (isset($fieldStats[$key]) && (int) ($fieldStats[$key]['non_empty'] ?? 0) === 0) {
                return 'Selector «' . ($fieldStats[$key]['selector'] ?? $key) . '» nu extrage date.';
            }
        }

        return 'Blocuri găsite dar niciun produs valid după reguli.';
    }

    /**
     * @param array<string, int|bool> $hints
     * @param array<string, int>|null $skipReasons
     * @param array<string, array<string, mixed>>|null $fieldStats
     * @return list<string>
     */
    private static function suggestionsFromHints(array $hints, string $blockSel = '', ?array $skipReasons = null, ?array $fieldStats = null): array
    {
        $out = [];
        if ((int) ($hints['autodoc_listing_wrap'] ?? 0) > 0 && !str_contains($blockSel, 'listing-item__wrap')) {
            $out[] = 'HTML conține listing-item__wrap (' . $hints['autodoc_listing_wrap'] . '×) — folosește bloc: div.listing-item__wrap';
        }
        if (!empty($hints['autodoc_turnstile'])) {
            $out[] = 'Pagina are Cloudflare Turnstile — la fetch bifează super + render JS.';
        }
        if ($blockSel === '') {
            $out[] = 'Salvează logica după ce completezi elementele din Pas 2.';
        }
        if ($skipReasons !== null) {
            foreach (array_keys($skipReasons) as $reason) {
                if (str_starts_with($reason, 'ignore:')) {
                    $out[] = 'Regula ignore «' . substr($reason, 7) . '» elimină produse — ajustează câmpul Ignoră.';
                }
            }
        }
        if ($fieldStats !== null) {
            foreach ($fieldStats as $field => $stat) {
                if ((string) ($stat['selector'] ?? '') === '') {
                    $out[] = 'Câmp «' . $field . '» fără selector.';
                }
            }
        }

        return array_values(array_unique($out));
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
            return trim($node->getAttribute($attr));
        }

        if ($node->tagName === 'img') {
            return trim($node->getAttribute('src') ?: $node->getAttribute('data-src'));
        }

        return trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
    }
}
