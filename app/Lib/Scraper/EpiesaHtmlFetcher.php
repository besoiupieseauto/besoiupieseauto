<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperLogger.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';

/**
 * Fetch HTML ePiesa — exclusiv stealth-browser-mcp (implicit).
 * scrape.do doar dacă SCRAPER_FALLBACK_SCRAPE_DO=1 în admin/.env.
 */
final class EpiesaHtmlFetcher
{
    private const HTML_MARKER = 'prod-card';

    /** Marker vechi layout ePiesa (fallback). */
    private const HTML_MARKER_LEGACY = 'lp-card';

    public static function fetch(string $url, int $timeoutSec = 90): string
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL ePiesa invalid.');
        }

        $timeoutSec = max(30, min(180, $timeoutSec));

        if (self::stealthAvailable()) {
            try {
                $html = self::fetchViaStealth($url, $timeoutSec);
                if ($html !== '') {
                    return $html;
                }
            } catch (Throwable $e) {
                ScraperLogger::log('warn', 'ePiesa stealth: ' . $e->getMessage());
            }
        }

        if (self::scrapeDoFallbackAllowed()) {
            try {
                require_once __DIR__ . '/ScrapeDoClient.php';
                require_once __DIR__ . '/ScrapeDoConfig.php';
                if (ScrapeDoConfig::hasToken() && !ScrapeDoConfig::isQuotaExceeded()) {
                    $html = (new ScrapeDoClient())->fetchWithRetry($url, $timeoutSec, false, false);
                    if (is_string($html) && trim($html) !== '' && !self::isCloudflareHtml($html)) {
                        return $html;
                    }
                }
            } catch (Throwable $e) {
                ScraperLogger::log('warn', 'ePiesa scrape.do fallback: ' . $e->getMessage());
            }
        }

        return self::fetchDirect($url);
    }

    public static function stealthAvailable(): bool
    {
        require_once __DIR__ . '/StealthBrowserClient.php';

        return StealthBrowserClient::isAvailable();
    }

    private static function fetchViaStealth(string $url, int $timeoutSec): string
    {
        require_once __DIR__ . '/StealthBrowserClient.php';
        require_once __DIR__ . '/ScraperHubTester.php';

        $result = StealthBrowserClient::fetch($url, [
            'timeout_sec' => (float) min(60, max(30, $timeoutSec)),
            'wait_sec' => 10.0,
            'html_marker' => self::HTML_MARKER,
            'save_raw' => true,
            'force_headless' => in_array(strtolower((string) getenv('STEALTH_BROWSER_HEADLESS')), ['1', 'true', 'yes'], true),
            'background_job' => true,
        ]);

        $preview = StealthBrowserClient::resolveHtmlBody($result, 65536);
        if ($preview === '' || ScraperHubTester::htmlIsCloudflareChallenge($preview)) {
            throw new RuntimeException('ePiesa: pagină blocată sau fără conținut (stealth)');
        }
        if (!EpiesaCategoryParser::htmlLooksLikeSearchPage($preview)) {
            throw new RuntimeException('ePiesa: pagină necunoscută — lipsește prod-card / Rezultate căutare (stealth)');
        }

        $html = StealthBrowserClient::resolveHtmlBody($result, 0);
        unset($result, $preview);
        if ($html === '') {
            throw new RuntimeException('ePiesa: HTML gol după stealth');
        }

        return $html;
    }

    private static function scrapeDoFallbackAllowed(): bool
    {
        require_once __DIR__ . '/ScraperHubTester.php';

        return ScraperHubTester::isScrapeDoFallbackEnabled();
    }

    private static function isCloudflareHtml(string $html): bool
    {
        require_once __DIR__ . '/ScraperHubTester.php';

        return ScraperHubTester::htmlIsCloudflareChallenge($html);
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
            CURLOPT_USERAGENT => 'BesoiuPieseAuto-Scraper/1.0',
            CURLOPT_HTTPHEADER => ['Accept-Language: ro-RO,ro;q=0.9'],
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code >= 400) {
            throw new RuntimeException('HTTP ' . $code . ' la ePiesa (curl direct)');
        }

        return $body;
    }
}
