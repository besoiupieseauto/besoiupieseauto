<?php

declare(strict_types=1);

require_once __DIR__ . '/EpiesaHtmlFetcher.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';
require_once __DIR__ . '/EpiesaImageCache.php';
require_once __DIR__ . '/ConsumableFluidMatch.php';
require_once __DIR__ . '/CatalogPartImageMatch.php';
require_once __DIR__ . '/ScraperLogger.php';

final class EpiesaSearch
{
    private const SEARCH_BASE = 'https://www.epiesa.ro/cautare-piesa/';

    /** @return array<string, mixed>|null */
    public static function findFirst(string $query): ?array
    {
        $items = self::searchItems($query, 1, false);

        return $items[0] ?? null;
    }

    /**
     * Căutare cu potrivire contextuală (denumire fără cod furnizor + volum/viscozitate).
     *
     * @param array<string, mixed> $product
     * @param list<string> $queries
     * @return array<string, mixed>|null
     */
    public static function findBestForProduct(array $product, array $queries = []): ?array
    {
        if ($queries === []) {
            $queries = ConsumableFluidMatch::buildEpiesaQueries($product);
        }

        $best = null;
        $bestScore = 0;
        $seenTitles = [];
        $isCatalog = CatalogPartImageMatch::isCatalogPart($product);
        $minScore = $isCatalog ? 52 : 58;

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            $items = self::searchItems($query, 15, false);
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $titleKey = mb_strtolower(trim((string) ($item['title'] ?? $item['name'] ?? '')), 'UTF-8');
                if ($titleKey === '' || isset($seenTitles[$titleKey])) {
                    continue;
                }
                $seenTitles[$titleKey] = true;
                $score = $isCatalog
                    ? CatalogPartImageMatch::scoreHit($product, $item, $query)
                    : ConsumableFluidMatch::scoreHit($product, $item);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $item;
                    $best['match_score'] = $score;
                    $best['search_query'] = $query;
                }
            }
            if ($bestScore >= 88) {
                break;
            }
        }

        if (!is_array($best) || $bestScore < $minScore) {
            return null;
        }

        return EpiesaImageCache::ensureCached($best);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function searchItems(string $query, int $limit = 10, bool $cacheImages = true): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $url = self::SEARCH_BASE . '?q=' . rawurlencode($query);

        try {
            $html = EpiesaHtmlFetcher::fetch($url);
        } catch (Throwable $e) {
            ScraperLogger::log('warn', 'EpiesaSearch: ' . $e->getMessage());

            return [];
        }

        if (trim($html) === '') {
            return [];
        }

        $items = EpiesaCategoryParser::parse($html, max(1, min(20, $limit)), $cacheImages);
        if ($items === []) {
            return [];
        }

        return $cacheImages ? $items : $items;
    }
}
