<?php

declare(strict_types=1);

/**
 * Test sursă imagine «tecdoc_api» pentru Scraper Hub.
 *
 * Fișier recreat (2026-07-07): originalul a fost pierdut (nu era în git).
 * Implementare defensivă — folosește cache-ul TecDoc din system/ dacă există,
 * altfel raportează sursa ca indisponibilă fără să blocheze pipeline-ul
 * (import, cron, scraper).
 */
final class TecDocApiTestRunner
{
    /**
     * @return array<string, mixed>
     */
    public static function run(string $query, int $limit = 5): array
    {
        $query = trim($query);
        $limit = max(1, min(20, $limit));

        $trace = [[
            'order' => 1,
            'label' => 'TecDoc API',
            'status' => 'info',
            'message' => 'Interogare TecDoc pentru: ' . ($query !== '' ? $query : '(gol)'),
        ]];

        if ($query === '') {
            $trace[] = [
                'order' => 2,
                'label' => 'Validare',
                'status' => 'warn',
                'message' => 'Query gol — nimic de căutat.',
            ];

            return self::result($query, [], $trace);
        }

        $items = self::searchViaSystemCache($query, $limit, $trace);

        return self::result($query, $items, $trace);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $trace
     * @return array<string, mixed>
     */
    private static function result(string $query, array $items, array $trace): array
    {
        return [
            'query' => $query,
            'items' => $items,
            'items_count' => count($items),
            'trace' => $trace,
        ];
    }

    /**
     * Caută în cache-ul TecDoc local (system/tecdoc_api_cache.php) dacă e disponibil.
     *
     * @param array<int, array<string, mixed>> $trace
     * @return array<int, array<string, mixed>>
     */
    private static function searchViaSystemCache(string $query, int $limit, array &$trace): array
    {
        $cacheLib = dirname(__DIR__, 2) . '/system/tecdoc_api_cache.php';
        if (!is_file($cacheLib)) {
            $trace[] = [
                'order' => 2,
                'label' => 'TecDoc API',
                'status' => 'warn',
                'message' => 'system/tecdoc_api_cache.php indisponibil — sursă API dezactivată.',
            ];

            return [];
        }

        try {
            require_once $cacheLib;
        } catch (\Throwable $e) {
            $trace[] = [
                'order' => 2,
                'label' => 'TecDoc API',
                'status' => 'error',
                'message' => 'Eroare la încărcarea cache-ului TecDoc: ' . $e->getMessage(),
            ];

            return [];
        }

        // Funcțiile expuse de tecdoc_api_cache.php diferă între versiuni —
        // detectăm defensiv ce e disponibil.
        $searchFns = [
            'besoiu_tecdoc_search_articles',
            'tecdoc_cache_search_articles',
            'tecdoc_search_articles',
        ];

        foreach ($searchFns as $fn) {
            if (!function_exists($fn)) {
                continue;
            }

            try {
                $raw = $fn($query, $limit);
            } catch (\Throwable $e) {
                $trace[] = [
                    'order' => 2,
                    'label' => 'TecDoc API',
                    'status' => 'error',
                    'message' => $fn . ' a eșuat: ' . $e->getMessage(),
                ];

                return [];
            }

            $items = self::normalizeItems(is_array($raw) ? $raw : [], $limit);
            $trace[] = [
                'order' => 2,
                'label' => 'TecDoc API',
                'status' => $items !== [] ? 'ok' : 'warn',
                'message' => count($items) . ' rezultat(e) via ' . $fn . '().',
            ];

            return $items;
        }

        $trace[] = [
            'order' => 2,
            'label' => 'TecDoc API',
            'status' => 'warn',
            'message' => 'Nicio funcție de căutare TecDoc disponibilă — sursă API inactivă.',
        ];

        return [];
    }

    /**
     * @param array<int, mixed> $raw
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeItems(array $raw, int $limit): array
    {
        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'title' => (string) ($row['title'] ?? $row['name'] ?? $row['articleName'] ?? ''),
                'image' => (string) ($row['image'] ?? $row['image_url'] ?? $row['imageUrl'] ?? ''),
                'oem' => (string) ($row['oem'] ?? $row['articleNo'] ?? $row['code'] ?? ''),
                'description' => (string) ($row['description'] ?? $row['genericArticleDescription'] ?? ''),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }
}
