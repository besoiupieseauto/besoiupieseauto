<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperHtmlAnalyzer.php';

/**
 * Pregătește eșantion HTML + „busolă DOM” pentru agentul AI.
 */
final class ScraperHtmlSample
{
    /** @return array<string, mixed> */
    public static function buildContext(string $html, int $maxSnippetChars = 42000): array
    {
        $hints = ScraperHtmlAnalyzer::analyze($html, ['block_selector' => 'body', 'field_map' => [], 'ignore_rules' => []]);
        $htmlHints = is_array($hints['html_hints'] ?? null) ? $hints['html_hints'] : [];

        return [
            'bytes' => strlen($html),
            'html_hints' => $htmlHints,
            'compass' => self::domCompass($html),
            'snippet' => self::extractListingSnippet($html, $maxSnippetChars),
            'title' => self::pageTitle($html),
        ];
    }

    /** @return list<array{token: string, count: int}> */
    public static function domCompass(string $html): array
    {
        $counts = [];
        if (preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $classAttr) {
                foreach (preg_split('/\s+/', trim((string) $classAttr)) ?: [] as $cls) {
                    $cls = trim((string) $cls);
                    if ($cls === '' || strlen($cls) < 3) {
                        continue;
                    }
                    if (preg_match('/^(col-|d-|p-|m-|text-|btn-|form-|is-|has-)/', $cls)) {
                        continue;
                    }
                    $counts[$cls] = ($counts[$cls] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $out = [];
        foreach ($counts as $token => $count) {
            if ($count < 3) {
                continue;
            }
            $out[] = ['token' => (string) $token, 'count' => (int) $count];
            if (count($out) >= 24) {
                break;
            }
        }

        return $out;
    }

    private static function pageTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    private static function extractListingSnippet(string $html, int $maxChars): string
    {
        $markers = [
            'data-listing-products',
            'listing-list',
            'listing-item__wrap',
            'data-list-item-product',
            'class="listing-item"',
            'card-v2',
            'sub-product-inner',
            'product-item',
        ];

        $bestPos = null;
        foreach ($markers as $marker) {
            $pos = stripos($html, $marker);
            if ($pos !== false && ($bestPos === null || $pos < $bestPos)) {
                $bestPos = $pos;
            }
        }

        if ($bestPos !== null) {
            $start = max(0, $bestPos - 800);

            return substr($html, $start, $maxChars);
        }

        return substr($html, 0, $maxChars);
    }
}
