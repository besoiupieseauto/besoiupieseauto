<?php

declare(strict_types=1);

require_once __DIR__ . '/ScrapeDoClient.php';
require_once __DIR__ . '/ScrapeDoConfig.php';
require_once __DIR__ . '/EmagSearchParser.php';

final class EmagSearch
{
    public static function buildSearchUrl(string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($query === '') {
            return '';
        }

        $encoded = str_replace(' ', '+', $query);

        return 'https://www.emag.ro/search/' . $encoded . '?ref=effective_search';
    }

    /**
     * @param array<string, mixed> $product
     * @return array<int, string>
     */
    public static function queriesFromProduct(array $product): array
    {
        $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipeline)) {
            require_once $pipeline;
            $queries = besoiu_image_search_queries_for_product($product);
            if ($queries !== []) {
                return $queries;
            }
        }

        $queries = [];
        $add = static function (string $value) use (&$queries): void {
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            if ($value !== '' && !in_array($value, $queries, true)) {
                $queries[] = $value;
            }
        };

        $name = trim((string) ($product['pName'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        $marca = trim((string) ($product['pMarca'] ?? ''));
        $model = trim((string) ($product['pModel'] ?? ''));

        $add($name);
        $add(trim($brand . ' ' . $code . ' ' . $marca . ' ' . $model));
        $add(trim($brand . ' ' . $code . ' ' . $marca));
        $add(trim($brand . ' ' . $code));

        if ($code !== '' && $marca !== '') {
            $add('piese auto ' . $brand . ' ' . $code . ' ' . $marca);
        }

        return $queries;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{ok:bool,image_url?:string,title?:string,product_url?:string,search_url?:string,error?:string,query?:string}
     */
    public static function searchForProduct(array $product): array
    {
        $lastError = 'eMAG: nu am găsit imagine';
        $lastSearchUrl = '';

        $queries = self::queriesFromProduct($product);
        $extra = $product['__emag_extra_queries'] ?? [];
        if (is_array($extra) && $extra !== []) {
            $queries = array_values(array_unique(array_merge(
                array_map(static fn ($q): string => trim((string) $q), $extra),
                $queries
            )));
        }

        $queryLimit = (int) ($product['__emag_query_limit'] ?? 0);
        if ($queryLimit > 0 && count($queries) > $queryLimit) {
            $queries = array_slice($queries, 0, $queryLimit);
        }

        foreach ($queries as $query) {
            $result = self::search($query);
            $lastSearchUrl = (string) ($result['search_url'] ?? $lastSearchUrl);
            if (!empty($result['ok'])) {
                $result['query'] = $query;

                return $result;
            }
            $lastError = trim((string) ($result['error'] ?? $lastError));
        }

        return [
            'ok' => false,
            'search_url' => $lastSearchUrl,
            'error' => $lastError,
            'query' => (string) ($product['pName'] ?? $product['pCode'] ?? ''),
        ];
    }

    /**
     * @return array{ok:bool,image_url?:string,title?:string,product_url?:string,search_url?:string,error?:string}
     */
    public static function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'error' => 'eMAG: denumire produs goală'];
        }

        $searchUrl = self::buildSearchUrl($query);
        $token = ScrapeDoConfig::token();
        if ($token === '') {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'SCRAPE_DO_TOKEN lipsește din admin/.env',
            ];
        }

        try {
            $client = new ScrapeDoClient($token);
            $html = $client->fetch($searchUrl, 120, true, true);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'scrape.do: ' . $e->getMessage(),
            ];
        }

        if (trim($html) === '') {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'scrape.do: răspuns gol pentru ' . $query,
            ];
        }

        if (str_starts_with(trim($html), '{') && str_contains($html, 'error')) {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'scrape.do: ' . mb_substr(trim($html), 0, 220, 'UTF-8'),
            ];
        }

        if (!EmagSearchParser::htmlLooksLikeEmagSearch($html)) {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'eMAG: HTML neașteptat (lungime ' . strlen($html) . ' bytes) — verifică scrape.do',
            ];
        }

        $parsed = EmagSearchParser::parseFirstCard($html);
        if (!is_array($parsed) || trim((string) ($parsed['image_url'] ?? '')) === '') {
            return [
                'ok' => false,
                'search_url' => $searchUrl,
                'error' => 'eMAG: fără imagine în rezultate pentru «' . $query . '»',
            ];
        }

        return [
            'ok' => true,
            'image_url' => (string) $parsed['image_url'],
            'title' => (string) ($parsed['title'] ?? ''),
            'product_url' => (string) ($parsed['product_url'] ?? ''),
            'search_url' => $searchUrl,
        ];
    }

    /**
     * @return array{image_url:string,title:string,product_url:string,search_url:string}|null
     */
    public static function findFirst(string $query): ?array
    {
        $result = self::search($query);
        if (empty($result['ok'])) {
            return null;
        }

        return [
            'image_url' => (string) ($result['image_url'] ?? ''),
            'title' => (string) ($result['title'] ?? ''),
            'product_url' => (string) ($result['product_url'] ?? ''),
            'search_url' => (string) ($result['search_url'] ?? ''),
        ];
    }
}
