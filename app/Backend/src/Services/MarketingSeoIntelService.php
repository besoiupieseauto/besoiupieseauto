<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * SEO concurență (epiesa, autodoc…), keywords din Search Logs, metrici postări.
 */
final class MarketingSeoIntelService
{
    private static function ensureScrapeDoLoaded(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $path = dirname(__DIR__, 3) . '/lib/Scraper/ScrapeDoClient.php';
        if (is_file($path)) {
            require_once $path;
        }
        $loaded = true;
    }
    /** @return list<array{domain: string, label: string, is_own: bool}> */
    public static function competitorCatalog(): array
    {
        return [
            ['domain' => 'besoiupieseauto.ro', 'label' => 'Besoiu (tu)', 'is_own' => true],
            ['domain' => 'epiesa.ro', 'label' => 'ePiesa', 'is_own' => false],
            ['domain' => 'autodoc24.ro', 'label' => 'Autodoc24', 'is_own' => false],
            ['domain' => 'autopieseonline24.ro', 'label' => 'AutopieseOnline24', 'is_own' => false],
            ['domain' => 'pieseauto.ro', 'label' => 'PieseAuto.ro', 'is_own' => false],
            ['domain' => 'automobilus.ro', 'label' => 'Automobilus', 'is_own' => false],
        ];
    }

    public static function tablesExist(): bool
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query("SHOW TABLES LIKE 'marketing_seo_snapshots'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    public static function bundle(): array
    {
        if (!self::tablesExist()) {
            return [
                'snapshots' => [],
                'keywords' => [],
                'competitors' => self::competitorCatalog(),
                'tables_exist' => false,
            ];
        }

        try {
            return [
                'snapshots' => self::latestSnapshotsByDomain(),
                'keywords' => self::fetchKeywords(),
                'competitors' => self::competitorCatalog(),
                'tables_exist' => true,
            ];
        } catch (Throwable $e) {
            error_log('[MarketingSeoIntelService] bundle: ' . $e->getMessage());

            return [
                'snapshots' => [],
                'keywords' => [],
                'competitors' => self::competitorCatalog(),
                'tables_exist' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function latestSnapshotsByDomain(): array
    {
        $pdo = Database::getDB();
        $sql = 'SELECT s.* FROM marketing_seo_snapshots s
                INNER JOIN (
                    SELECT domain, MAX(fetched_at) AS max_at
                    FROM marketing_seo_snapshots
                    GROUP BY domain
                ) t ON s.domain = t.domain AND s.fetched_at = t.max_at
                ORDER BY s.is_own_site DESC, s.domain ASC';

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapSnapshot($r), $rows);
    }

    /**
     * @return array{success: bool, domain: string, snapshot?: array<string, mixed>, message?: string}
     */
    public static function scrapeDomain(string $domain, ?int $userId = null): array
    {
        $domain = self::normalizeDomain($domain);
        if ($domain === '') {
            return ['success' => false, 'domain' => '', 'message' => 'Domeniu invalid.'];
        }

        if (!self::tablesExist()) {
            return ['success' => false, 'domain' => $domain, 'message' => 'Rulează migrarea 068_marketing_seo_intel.'];
        }

        $url = 'https://' . $domain . '/';
        $fetch = self::fetchPage($url);
        if ($fetch['html'] === '') {
            return ['success' => false, 'domain' => $domain, 'message' => $fetch['error'] ?? 'Pagina nu a putut fi descărcată.'];
        }

        $parsed = self::parseSeoHtml($fetch['html'], $url);
        $catalog = self::competitorCatalog();
        $label = $domain;
        $isOwn = $domain === 'besoiupieseauto.ro';
        foreach ($catalog as $c) {
            if ($c['domain'] === $domain) {
                $label = $c['label'];
                $isOwn = (bool) $c['is_own'];
                break;
            }
        }

        $snapshot = self::insertSnapshot([
            'domain' => $domain,
            'label' => $label,
            'is_own_site' => $isOwn ? 1 : 0,
            'http_status' => $fetch['http_status'],
            'page_title' => $parsed['title'],
            'meta_description' => $parsed['meta_description'],
            'h1' => $parsed['h1'],
            'canonical_url' => $parsed['canonical'],
            'og_title' => $parsed['og_title'],
            'word_count' => $parsed['word_count'],
            'internal_links' => $parsed['internal_links'],
            'fetch_source' => $fetch['source'],
            'raw_json' => json_encode($parsed['extras'], JSON_UNESCAPED_UNICODE),
            'user_id' => $userId,
        ]);

        self::upsertCompetitorIndicator($domain, $label, $parsed, $isOwn, $userId);

        return ['success' => true, 'domain' => $domain, 'snapshot' => $snapshot];
    }

    /** @return list<array{domain: string, success: bool, message?: string}> */
    public static function scrapeAllCompetitors(?int $userId = null, int $delayMs = 400): array
    {
        $results = [];
        foreach (self::competitorCatalog() as $item) {
            $res = self::scrapeDomain($item['domain'], $userId);
            $results[] = [
                'domain' => $item['domain'],
                'success' => (bool) ($res['success'] ?? false),
                'message' => $res['message'] ?? null,
            ];
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return $results;
    }

    public static function syncKeywordsFromSearchLogs(?int $userId = null): int
    {
        if (!self::tablesExist()) {
            return 0;
        }

        $insightsPath = dirname(__DIR__, 2) . '/system/search_logs.php';
        if (!is_file($insightsPath)) {
            return 0;
        }
        require_once $insightsPath;

        $pdo = Database::getDB();
        if (!function_exists('search_logs_table_exists') || !search_logs_table_exists($pdo)) {
            return 0;
        }

        $synced = 0;
        $topFound = search_logs_insights_top_queries($pdo, 25, 30);
        foreach ($topFound as $row) {
            $kw = trim((string) ($row['query_value'] ?? ''));
            if ($kw === '') {
                continue;
            }
            self::upsertKeyword([
                'keyword' => $kw,
                'keyword_type' => (string) ($row['query_type'] ?? 'name'),
                'searches_30d' => (int) ($row['search_count'] ?? 0),
                'found_rate' => 100,
                'source' => 'search_logs',
                'notes' => 'Top căutări găsite pe site (30 zile)',
            ], $userId);
            $synced++;
        }

        if (function_exists('search_logs_top_missing')) {
            foreach (search_logs_top_missing($pdo, 20) as $row) {
                $code = trim((string) ($row['code'] ?? $row['oem'] ?? ''));
                if ($code === '') {
                    continue;
                }
                self::upsertKeyword([
                    'keyword' => $code,
                    'keyword_type' => 'oem',
                    'searches_30d' => (int) ($row['count'] ?? $row['hits'] ?? 0),
                    'found_rate' => 0,
                    'source' => 'search_logs_missing',
                    'notes' => 'OEM căutat dar lipsă din stoc — oportunitate SEO',
                ], $userId);
                $synced++;
            }
        }

        self::syncKeywordIndicators($userId);

        return $synced;
    }

    /** @return list<array<string, mixed>> */
    public static function fetchKeywords(): array
    {
        if (!self::tablesExist()) {
            return [];
        }
        $pdo = Database::getDB();
        $rows = $pdo->query(
            'SELECT * FROM marketing_keywords ORDER BY searches_30d DESC, updated_at DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r): array => self::mapKeyword($r), $rows);
    }

    /** @param array<string, mixed> $row */
    public static function saveKeyword(array $row, ?int $userId = null): array
    {
        if (!self::tablesExist()) {
            return [];
        }
        self::upsertKeyword($row, $userId);
        self::syncKeywordIndicators($userId);

        return self::fetchKeywords();
    }

    /** @return array{html: string, http_status: int, source: string, error?: string} */
    private static function fetchPage(string $url): array
    {
        $html = '';
        $status = 0;
        $source = 'curl';
        $error = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BesoiuMarketingBot/1.0; +https://besoiupieseauto.ro)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: ro-RO,ro;q=0.9'],
        ]);
        $html = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($html === '' || $status >= 400) {
            $error = $curlErr !== '' ? $curlErr : ('HTTP ' . $status);
            try {
                self::ensureScrapeDoLoaded();
                if (class_exists(\ScrapeDoClient::class, false)) {
                    $client = new \ScrapeDoClient();
                    if ($client->hasToken()) {
                        $html = $client->fetch($url, 60, false, false);
                        $source = 'scrape_do';
                        $status = 200;
                        $error = '';
                    }
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'html' => $html,
            'http_status' => $status,
            'source' => $source,
            'error' => $error !== '' ? $error : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function parseSeoHtml(string $html, string $url): array
    {
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $title = trim($xpath->evaluate('string(//title)'));
        $metaDesc = '';
        $metaNodes = $xpath->query("//meta[@name='description']/@content");
        if ($metaNodes && $metaNodes->length > 0) {
            $metaDesc = trim((string) $metaNodes->item(0)?->nodeValue);
        }
        $h1 = trim($xpath->evaluate('string(//h1)'));
        $canonical = trim($xpath->evaluate("string(//link[@rel='canonical']/@href)"));
        $ogTitle = trim($xpath->evaluate("string(//meta[@property='og:title']/@content)"));

        $bodyText = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $wordCount = $bodyText !== '' ? count(preg_split('/\s+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY) ?: []) : 0;

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $internalLinks = 0;
        $links = $xpath->query('//a[@href]');
        if ($links) {
            foreach ($links as $link) {
                $href = trim((string) $link->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    continue;
                }
                if (str_starts_with($href, '/') || str_contains($href, $host)) {
                    $internalLinks++;
                }
            }
        }

        $robots = trim($xpath->evaluate("string(//meta[@name='robots']/@content)"));

        return [
            'title' => mb_substr($title, 0, 500, 'UTF-8'),
            'meta_description' => mb_substr($metaDesc, 0, 2000, 'UTF-8'),
            'h1' => mb_substr($h1, 0, 500, 'UTF-8'),
            'canonical' => mb_substr($canonical, 0, 500, 'UTF-8'),
            'og_title' => mb_substr($ogTitle, 0, 500, 'UTF-8'),
            'word_count' => min(999999, $wordCount),
            'internal_links' => $internalLinks,
            'extras' => [
                'robots' => $robots,
                'url' => $url,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private static function insertSnapshot(array $data): array
    {
        $pdo = Database::getDB();
        $id = 'seo_' . bin2hex(random_bytes(8));
        $stmt = $pdo->prepare(
            'INSERT INTO marketing_seo_snapshots
            (id, domain, label, is_own_site, http_status, page_title, meta_description, h1, canonical_url, og_title, word_count, internal_links, fetch_source, raw_json, user_id, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $id,
            $data['domain'],
            $data['label'],
            (int) ($data['is_own_site'] ?? 0),
            (int) ($data['http_status'] ?? 0),
            $data['page_title'] ?? '',
            $data['meta_description'] ?? '',
            $data['h1'] ?? '',
            $data['canonical_url'] ?? '',
            $data['og_title'] ?? '',
            (int) ($data['word_count'] ?? 0),
            (int) ($data['internal_links'] ?? 0),
            $data['fetch_source'] ?? 'curl',
            $data['raw_json'] ?? null,
            $data['user_id'] ?? null,
        ]);

        $row = $pdo->query('SELECT * FROM marketing_seo_snapshots WHERE id = ' . $pdo->quote($id))->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapSnapshot($row) : ['id' => $id, 'domain' => $data['domain']];
    }

    /** @param array<string, mixed> $row */
    private static function upsertKeyword(array $row, ?int $userId): void
    {
        $pdo = Database::getDB();
        $keyword = mb_substr(trim((string) ($row['keyword'] ?? '')), 0, 255, 'UTF-8');
        if ($keyword === '') {
            return;
        }

        $existing = $pdo->prepare('SELECT id FROM marketing_keywords WHERE keyword = ? LIMIT 1');
        $existing->execute([$keyword]);
        $id = $existing->fetchColumn();

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE marketing_keywords SET keyword_type = ?, searches_30d = ?, found_rate = ?, target_position = ?,
                 impressions_30d = ?, clicks_30d = ?, source = ?, notes = ?, user_id = COALESCE(?, user_id), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $row['keyword_type'] ?? 'oem',
                (int) ($row['searches_30d'] ?? 0),
                (float) ($row['found_rate'] ?? 0),
                (float) ($row['target_position'] ?? 10),
                (int) ($row['impressions_30d'] ?? 0),
                (int) ($row['clicks_30d'] ?? 0),
                $row['source'] ?? 'manual',
                $row['notes'] ?? '',
                $userId,
                $id,
            ]);
        } else {
            $newId = 'kw_' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare(
                'INSERT INTO marketing_keywords (id, keyword, keyword_type, searches_30d, found_rate, target_position, impressions_30d, clicks_30d, source, notes, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $newId,
                $keyword,
                $row['keyword_type'] ?? 'oem',
                (int) ($row['searches_30d'] ?? 0),
                (float) ($row['found_rate'] ?? 0),
                (float) ($row['target_position'] ?? 10),
                (int) ($row['impressions_30d'] ?? 0),
                (int) ($row['clicks_30d'] ?? 0),
                $row['source'] ?? 'manual',
                $row['notes'] ?? '',
                $userId,
            ]);
        }
    }

    private static function syncKeywordIndicators(?int $userId): void
    {
        if (!MarketingHubService::tablesExist()) {
            return;
        }

        $keywords = self::fetchKeywords();
        $state = MarketingHubService::loadAll();
        $indicators = $state['indicators'];
        $byAuto = [];
        foreach ($indicators as $ind) {
            $key = (string) ($ind['autoKey'] ?? $ind['auto_key'] ?? '');
            if ($key !== '') {
                $byAuto[$key] = $ind;
            }
        }

        foreach (array_slice($keywords, 0, 15) as $kw) {
            $autoKey = 'kw:' . md5((string) $kw['keyword']);
            $payload = [
                'platform' => 'Google / Site',
                'url' => 'https://besoiupieseauto.ro',
                'metric' => 'Keyword: ' . $kw['keyword'],
                'value' => (float) ($kw['searches_30d'] ?? 0),
                'target' => max(10, (float) ($kw['searches_30d'] ?? 0) * 1.2),
                'source' => 'auto',
                'category' => 'keyword',
                'notes' => ($kw['keyword_type'] ?? '') . ' · ' . ($kw['notes'] ?? ''),
            ];
            if (isset($byAuto[$autoKey])) {
                $byAuto[$autoKey]['value'] = $payload['value'];
                $byAuto[$autoKey]['updatedAt'] = date('c');
            } else {
                $indicators[] = array_merge($payload, [
                    'id' => 'ind_' . bin2hex(random_bytes(6)),
                    'autoKey' => $autoKey,
                    'updatedAt' => date('c'),
                ]);
            }
        }

        MarketingHubService::saveState(['indicators' => $indicators], $userId);
    }

    /** @param array<string, mixed> $parsed */
    private static function upsertCompetitorIndicator(string $domain, string $label, array $parsed, bool $isOwn, ?int $userId): void
    {
        if (!MarketingHubService::tablesExist()) {
            return;
        }

        $state = MarketingHubService::loadAll();
        $indicators = $state['indicators'];
        $autoKey = 'seo:' . $domain;
        $seoScore = self::seoScore($parsed);
        $found = false;
        foreach ($indicators as &$ind) {
            if (($ind['autoKey'] ?? '') === $autoKey) {
                $ind['value'] = $seoScore;
                $ind['notes'] = 'Title: ' . ($parsed['title'] ?? '') . ' · Cuvinte: ' . ($parsed['word_count'] ?? 0);
                $ind['updatedAt'] = date('c');
                $found = true;
                break;
            }
        }
        unset($ind);
        if (!$found) {
            $indicators[] = [
                'id' => 'ind_' . bin2hex(random_bytes(6)),
                'platform' => $label,
                'url' => 'https://' . $domain,
                'metric' => 'Scor SEO pagină principală',
                'value' => $seoScore,
                'target' => 85,
                'source' => 'scrape',
                'category' => 'competitor',
                'autoKey' => $autoKey,
                'notes' => 'Title: ' . ($parsed['title'] ?? ''),
                'updatedAt' => date('c'),
            ];
        }
        MarketingHubService::saveState(['indicators' => $indicators], $userId);
    }

    /** @param array<string, mixed> $parsed */
    private static function seoScore(array $parsed): float
    {
        $score = 0.0;
        if (($parsed['title'] ?? '') !== '') {
            $score += 25;
        }
        if (($parsed['meta_description'] ?? '') !== '') {
            $score += 25;
        }
        if (($parsed['h1'] ?? '') !== '') {
            $score += 20;
        }
        $wc = (int) ($parsed['word_count'] ?? 0);
        if ($wc >= 300) {
            $score += 15;
        } elseif ($wc >= 100) {
            $score += 8;
        }
        if ((int) ($parsed['internal_links'] ?? 0) >= 20) {
            $score += 15;
        } elseif ((int) ($parsed['internal_links'] ?? 0) >= 5) {
            $score += 8;
        }

        return min(100, round($score, 1));
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0] ?? $domain;
        $domain = explode(':', $domain)[0] ?? $domain;

        return preg_match('#^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$#', $domain) ? $domain : '';
    }

    /** @param array<string, mixed> $r */
    private static function mapSnapshot(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'domain' => (string) ($r['domain'] ?? ''),
            'label' => (string) ($r['label'] ?? ''),
            'isOwnSite' => (bool) ($r['is_own_site'] ?? false),
            'httpStatus' => (int) ($r['http_status'] ?? 0),
            'pageTitle' => (string) ($r['page_title'] ?? ''),
            'metaDescription' => (string) ($r['meta_description'] ?? ''),
            'h1' => (string) ($r['h1'] ?? ''),
            'canonicalUrl' => (string) ($r['canonical_url'] ?? ''),
            'ogTitle' => (string) ($r['og_title'] ?? ''),
            'wordCount' => (int) ($r['word_count'] ?? 0),
            'internalLinks' => (int) ($r['internal_links'] ?? 0),
            'fetchSource' => (string) ($r['fetch_source'] ?? ''),
            'seoScore' => self::seoScore([
                'title' => $r['page_title'] ?? '',
                'meta_description' => $r['meta_description'] ?? '',
                'h1' => $r['h1'] ?? '',
                'word_count' => $r['word_count'] ?? 0,
                'internal_links' => $r['internal_links'] ?? 0,
            ]),
            'fetchedAt' => (string) ($r['fetched_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $r */
    private static function mapKeyword(array $r): array
    {
        return [
            'id' => (string) ($r['id'] ?? ''),
            'keyword' => (string) ($r['keyword'] ?? ''),
            'keywordType' => (string) ($r['keyword_type'] ?? 'oem'),
            'searches30d' => (int) ($r['searches_30d'] ?? 0),
            'foundRate' => (float) ($r['found_rate'] ?? 0),
            'googlePosition' => $r['google_position'] !== null ? (float) $r['google_position'] : null,
            'targetPosition' => (float) ($r['target_position'] ?? 10),
            'impressions30d' => (int) ($r['impressions_30d'] ?? 0),
            'clicks30d' => (int) ($r['clicks_30d'] ?? 0),
            'source' => (string) ($r['source'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'updatedAt' => (string) ($r['updated_at'] ?? ''),
        ];
    }
}
