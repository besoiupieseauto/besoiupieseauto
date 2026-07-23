<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Throwable;

/**
 * Scraping research piață — stealth-browser-mcp + rate limiting + robots.txt.
 */
final class AiMarketResearchScrapeService
{
    private string $root;
    private bool $scraperBooted = false;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    private function bootScraper(): void
    {
        if ($this->scraperBooted) {
            return;
        }
        $bootstrap = $this->root . '/app/Import/Scraper/lib/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
        $this->scraperBooted = true;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function scrapeSourcePage(array $source, ?string $urlOverride = null): array
    {
        $url = trim($urlOverride ?? (string) ($source['url_template'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'URL sursă lipsă sau invalid'];
        }

        $sourceSvc = new AiMarketResearchSourceService($this->root);
        $robots = $sourceSvc->checkRobots($url);
        if (!$robots['allowed']) {
            return [
                'ok' => false,
                'error' => 'Blocat robots.txt: ' . $robots['reason'],
                'robots' => $robots,
            ];
        }

        $useStealth = !empty($source['use_stealth']);
        $this->bootScraper();

        try {
            if ($useStealth && class_exists('StealthBrowserClient') && \StealthBrowserClient::isAvailable()) {
                $fetch = \StealthBrowserClient::fetch($url, [
                    'wait_sec' => random_int(2, 5),
                    'timeout_sec' => 120,
                    'save_raw' => true,
                ]);
                $html = \StealthBrowserClient::resolveHtmlBody($fetch, 0);
                $via = 'stealth_browser';
            } elseif (class_exists('ScraperHubTester')) {
                $fetch = \ScraperHubTester::testFetch($url, ['save_raw' => true, 'timeout_sec' => 90]);
                $html = is_string($fetch['html'] ?? null) ? $fetch['html'] : '';
                if ($html === '' && !empty($fetch['raw_path']) && is_file((string) $fetch['raw_path'])) {
                    $html = (string) file_get_contents((string) $fetch['raw_path']);
                }
                $via = 'scraper_hub';
            } else {
                return ['ok' => false, 'error' => 'Nici stealth browser, nici ScraperHub disponibil'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Fetch eșuat: ' . $e->getMessage(), 'url' => $url];
        }

        if (trim($html) === '') {
            return ['ok' => false, 'error' => 'HTML gol', 'url' => $url, 'via' => $via ?? ''];
        }

        $plain = $this->htmlToText($html);
        $seo = $this->extractSeo($html);

        return [
            'ok' => true,
            'url' => $url,
            'via' => $via ?? '',
            'html_length' => strlen($html),
            'continut_brut' => mb_substr($plain, 0, 50000),
            'seo' => $seo,
            'robots' => $robots,
        ];
    }

    /** @return array<string, string> */
    private function extractSeo(string $html): array
    {
        $seo = ['title' => '', 'description' => '', 'h1' => ''];
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $seo['title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $seo['description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
            $seo['h1'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $seo;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|iframe)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = str_replace(['</p>', '</div>', '</li>', '<br>', '<br/>', '</h1>', '</h2>', '</h3>'], "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x0B\f\r]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
