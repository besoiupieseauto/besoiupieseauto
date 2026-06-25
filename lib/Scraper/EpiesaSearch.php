<?php

declare(strict_types=1);

require_once __DIR__ . '/ScrapeDoClient.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';
require_once __DIR__ . '/EpiesaImageCache.php';
require_once __DIR__ . '/ScraperLogger.php';

final class EpiesaSearch
{
    private const SEARCH_BASE = 'https://www.epiesa.ro/cautare-piesa/';

    /** @return array<string, mixed>|null */
    public static function findFirst(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $url = self::SEARCH_BASE . '?find=' . rawurlencode($query);

        try {
            $token = trim((string) (getenv('SCRAPE_DO_TOKEN') ?: ''));
            if ($token !== '') {
                $html = (new ScrapeDoClient($token))->fetch($url);
            } else {
                $html = self::fetchDirect($url);
            }
        } catch (Throwable $e) {
            ScraperLogger::log('warn', 'EpiesaSearch: ' . $e->getMessage());

            return null;
        }

        if (!is_string($html) || trim($html) === '') {
            return null;
        }

        $items = EpiesaCategoryParser::parse($html, 1);
        $first = $items[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        return EpiesaImageCache::ensureCached($first);
    }

    private static function fetchDirect(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'BesoiuPieseAuto-CronImport/1.0',
            CURLOPT_HTTPHEADER => ['Accept-Language: ro-RO,ro;q=0.9'],
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code >= 400) {
            throw new RuntimeException('HTTP ' . $code . ' la cautare ePiesa');
        }

        return $body;
    }
}
