<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\AiAgentContextLibraryService;
use Besoiu\Services\OllamaLlmClient;
use Throwable;

/**
 * Scrape inteligent → RAG folosind motorul Scraper (fetch cascadă, linkuri, imagini, SEO).
 */
final class AiRagIntelligentScrapeService
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
     * @param array<string, mixed> $options source_id, follow_links, max_links, summarize, agent_slug, topic
     * @return array<string, mixed>
     */
    public function scrapeToCorpus(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'URL invalid'];
        }

        $this->bootScraper();
        if (!class_exists('ScraperHubTester')) {
            return ['ok' => false, 'error' => 'Modul Scraper indisponibil'];
        }

        $sourceId = trim((string) ($options['source_id'] ?? $this->guessSourceId($url)));
        $topic = trim((string) ($options['topic'] ?? ''));
        $corpus = new AiRagCorpusService($this->root);
        $added = [];

        // Pipeline sursă configurată (parse_list + follow_links)
        if ($sourceId !== '' && !empty($options['use_source_pipeline']) && class_exists('ScraperModule')) {
            try {
                $module = \ScraperModule::instance();
                $query = $topic !== '' ? $topic : $this->queryFromUrl($url);
                $run = $module->testSource($sourceId, [
                    'query' => $query,
                    'limit' => (int) ($options['limit'] ?? 8),
                    'url' => $url,
                ]);
                foreach ((array) ($run['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $entry = $this->itemToCorpusEntry($item, $sourceId, $url);
                    $saved = $corpus->append($entry);
                    if ($saved !== null) {
                        $added[] = $saved;
                    }
                }
                if ($added !== []) {
                    return $this->finalize($added, $corpus, $options, [
                        'mode' => 'scraper_source_pipeline',
                        'source_id' => $sourceId,
                        'trace' => $run['trace'] ?? [],
                    ]);
                }
            } catch (Throwable $e) {
                // fallback fetch direct
            }
        }

        // Fetch HTML complet via ScraperHubTester
        try {
            $fetch = \ScraperHubTester::testFetch($url, [
                'source_id' => $sourceId,
                'save_raw' => true,
                'timeout_sec' => (int) ($options['timeout_sec'] ?? 90),
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Fetch eșuat: ' . $e->getMessage()];
        }

        $html = $this->loadHtmlFromFetch($fetch);
        if ($html === '') {
            return ['ok' => false, 'error' => 'HTML gol după fetch Scraper'];
        }

        $pageParser = new \Besoiu\Services\AiLibraryPageParserService();
        $product = $pageParser->parseProductFromHtml($html, $url);
        if ($product !== null) {
            $line = $pageParser->formatProductLine($product);
            $seo = $this->extractSeo($html);
            $images = $this->extractImages($html, $url);
            $saved = $corpus->append([
                'source_type' => 'scrape',
                'source_id' => $sourceId !== '' ? $sourceId : parse_url($url, PHP_URL_HOST),
                'title' => (string) ($product['name'] ?? 'Produs'),
                'text' => $line,
                'url' => $url,
                'image_url' => $images[0] ?? ($product['image'] ?? ''),
                'seo' => $seo,
                'tags' => array_filter(['scrape', 'intelligent', 'product-parsed', $sourceId]),
                'pinned' => !empty($options['pinned']),
            ]);
            if ($saved !== null) {
                return $this->finalize([$saved], $corpus, $options, [
                    'mode' => 'scraper_product_parsed',
                    'product' => $product,
                    'seo' => $seo,
                ]);
            }
        }

        $seo = $this->extractSeo($html);
        $links = $this->extractLinks($html, $url, (int) ($options['max_links'] ?? 12));
        $images = $this->extractImages($html, $url);

        $mainText = $this->htmlToText($html);
        if ($topic !== '') {
            $mainText = "Temă: {$topic}\n\n" . $mainText;
        }

        $fragments = [];
        if (!empty($options['summarize']) && (new OllamaLlmClient($this->root . '/app'))->isEnabled()) {
            $fragments = $this->ollamaFragments($mainText, $url, $seo, $topic);
        }
        if ($fragments === []) {
            $fragments = $this->chunkText($mainText, 850);
        }

        foreach ($fragments as $i => $fragment) {
            $entry = [
                'source_type' => 'scrape',
                'source_id' => $sourceId !== '' ? $sourceId : parse_url($url, PHP_URL_HOST),
                'title' => $seo['title'] !== '' ? $seo['title'] : ($topic !== '' ? $topic : 'Scrape'),
                'text' => $fragment,
                'url' => $url,
                'image_url' => $images[0] ?? ($seo['og_image'] ?? ''),
                'seo' => $seo,
                'links' => array_slice($links, 0, 15),
                'html_chars' => strlen($html),
                'tags' => array_filter(['scrape', 'intelligent', $sourceId, $topic !== '' ? 'topic:' . mb_substr($topic, 0, 40) : '']),
                'pinned' => !empty($options['pinned']),
            ];
            if ($i === 0 && $seo['description'] !== '') {
                $entry['text'] = 'SEO: ' . $seo['description'] . "\n\n" . $entry['text'];
            }
            $saved = $corpus->append($entry);
            if ($saved !== null) {
                $added[] = $saved;
            }
        }

        // Navigare linkuri (pagini detaliu)
        if (!empty($options['follow_links']) && $links !== []) {
            $maxFollow = max(1, min(5, (int) ($options['max_follow'] ?? 3)));
            foreach (array_slice($links, 0, $maxFollow) as $link) {
                try {
                    $sub = \ScraperHubTester::testFetch($link, ['source_id' => $sourceId, 'save_raw' => true]);
                    $subHtml = $this->loadHtmlFromFetch($sub);
                    if ($subHtml === '') {
                        continue;
                    }
                    $subSeo = $this->extractSeo($subHtml);
                    $subText = mb_substr($this->htmlToText($subHtml), 0, 1200);
                    $subImages = $this->extractImages($subHtml, $link);
                    $saved = $corpus->append([
                        'source_type' => 'scrape',
                        'source_id' => $sourceId,
                        'title' => $subSeo['title'] !== '' ? $subSeo['title'] : 'Subpagină',
                        'text' => $subText,
                        'url' => $link,
                        'image_url' => $subImages[0] ?? ($subSeo['og_image'] ?? ''),
                        'seo' => $subSeo,
                        'tags' => ['scrape', 'follow_link', $sourceId],
                    ]);
                    if ($saved !== null) {
                        $added[] = $saved;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return $this->finalize($added, $corpus, $options, [
            'mode' => 'scraper_intelligent',
            'fetch' => [
                'via' => $fetch['request']['via'] ?? '',
                'html_length' => (int) ($fetch['html_length'] ?? 0),
                'duration_ms' => (int) ($fetch['duration_ms'] ?? 0),
            ],
            'seo' => $seo,
            'links_found' => count($links),
            'images_found' => count($images),
        ]);
    }

    /** @param list<array<string, mixed>> $added @param array<string, mixed> $meta */
    private function finalize(array $added, AiRagCorpusService $corpus, array $options, array $meta): array
    {
        $agentSlug = trim((string) ($options['agent_slug'] ?? ''));
        $agentSynced = 0;
        if ($agentSlug !== '') {
            $library = new AiAgentContextLibraryService($this->root);
            foreach ($added as $entry) {
                if ($library->appendEntry($agentSlug, [
                    'text' => (string) ($entry['text'] ?? ''),
                    'source' => 'operator',
                    'tags' => array_merge(['scrape-intelligent'], (array) ($entry['tags'] ?? [])),
                ]) !== null) {
                    ++$agentSynced;
                }
            }
        }

        return array_merge([
            'ok' => count($added) > 0,
            'fragments_added' => count($added),
            'entries' => $added,
            'corpus' => $corpus->status(),
            'agent_library_synced' => $agentSynced,
        ], $meta);
    }

    /** @param array<string, mixed> $fetch */
    private function loadHtmlFromFetch(array $fetch): string
    {
        $raw = trim((string) ($fetch['raw_saved'] ?? ''));
        if ($raw !== '') {
            $candidates = [];
            if (preg_match('#^[A-Za-z]:#', $raw) || str_starts_with($raw, '/')) {
                $candidates[] = $raw;
            }
            $scraperRoot = $this->root . '/app/Import/Scraper';
            $candidates[] = $scraperRoot . '/' . ltrim(str_replace('\\', '/', $raw), '/');
            if (class_exists('ScraperPaths')) {
                $candidates[] = \ScraperPaths::projectRoot() . '/' . ltrim(str_replace('\\', '/', $raw), '/');
            }
            foreach ($candidates as $abs) {
                if (is_file($abs)) {
                    return (string) file_get_contents($abs);
                }
            }
        }

        return (string) ($fetch['html_preview'] ?? '');
    }

    /** @return array{title:string,description:string,h1:string,og_image:string,canonical:string,og_title:string} */
    private function extractSeo(string $html): array
    {
        $seo = [
            'title' => '',
            'description' => '',
            'h1' => '',
            'og_image' => '',
            'og_title' => '',
            'canonical' => '',
        ];
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $seo['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/i', $html, $m)) {
            $seo['description'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $seo['og_title'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $seo['og_image'] = trim($m[1]);
        }
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m)) {
            $seo['canonical'] = trim($m[1]);
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $seo['h1'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $seo;
    }

    /** @return list<string> */
    private function extractLinks(string $html, string $baseUrl, int $max): array
    {
        if (!preg_match_all('/href=["\']([^"\'#]+)["\']/i', $html, $matches)) {
            return [];
        }
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $out = [];
        foreach ($matches[1] as $href) {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                continue;
            }
            $abs = $this->absoluteUrl($baseUrl, $href);
            $linkHost = (string) parse_url($abs, PHP_URL_HOST);
            if ($linkHost !== '' && $host !== '' && $linkHost !== $host) {
                continue;
            }
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|pdf)(\?|$)/i', $abs)) {
                continue;
            }
            if (!in_array($abs, $out, true)) {
                $out[] = $abs;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function extractImages(string $html, string $baseUrl): array
    {
        $out = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $abs = $this->absoluteUrl($baseUrl, trim($src));
                if ($abs !== '' && !in_array($abs, $out, true)) {
                    $out[] = $abs;
                }
                if (count($out) >= 8) {
                    break;
                }
            }
        }

        return $out;
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

    /** @return list<string> */
    private function chunkText(string $text, int $maxLen): array
    {
        $parts = preg_split('/\n{2,}/u', trim($text)) ?: [];
        $chunks = [];
        $buf = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (mb_strlen($p) > $maxLen) {
                if ($buf !== '') {
                    $chunks[] = $buf;
                    $buf = '';
                }
                for ($o = 0; $o < mb_strlen($p); $o += $maxLen) {
                    $chunks[] = mb_substr($p, $o, $maxLen);
                }
                continue;
            }
            if ($buf === '' || mb_strlen($buf) + 2 + mb_strlen($p) <= $maxLen) {
                $buf = $buf === '' ? $p : $buf . "\n\n" . $p;
            } else {
                $chunks[] = $buf;
                $buf = $p;
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }

        return array_slice($chunks, 0, 10);
    }

    /** @param array<string, string> $seo @return list<string> */
    private function ollamaFragments(string $plain, string $url, array $seo, string $topic): array
    {
        $client = new OllamaLlmClient($this->root . '/app');
        $prompt = "Extrage fragmente RAG pentru magazin piese auto Besoiu.\n"
            . "URL: {$url}\n"
            . ($topic !== '' ? "Temă: {$topic}\n" : '')
            . 'SEO title: ' . ($seo['title'] ?? '') . "\n"
            . 'SEO desc: ' . ($seo['description'] ?? '') . "\n"
            . "JSON: {\"fragments\":[\"...\"]}\nMax 6 fragmente, română, factual din text.\n\n"
            . mb_substr($plain, 0, 6000);
        try {
            $resp = $client->chat(
                [['role' => 'user', 'content' => $prompt]],
                'Extractor RAG Besoiu — doar JSON.',
                0.1,
                90
            );
            if (empty($resp['ok'])) {
                return [];
            }
            $json = json_decode(trim((string) ($resp['content'] ?? '')), true);
            $frags = is_array($json['fragments'] ?? null) ? $json['fragments'] : [];
            $out = [];
            foreach ($frags as $f) {
                $t = trim((string) $f);
                if ($t !== '') {
                    $out[] = $t;
                }
            }

            return array_slice($out, 0, 8);
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function itemToCorpusEntry(array $item, string $sourceId, string $referer): array
    {
        $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
        $price = trim((string) ($item['price'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? $item['code'] ?? ''));
        $url = trim((string) ($item['url'] ?? $referer));
        $image = trim((string) ($item['image'] ?? $item['image_url'] ?? ''));
        $parts = array_filter([
            $title !== '' ? 'Produs: ' . $title : '',
            $sku !== '' ? 'SKU: ' . $sku : '',
            $price !== '' ? 'Preț: ' . $price : '',
            $url !== '' ? 'URL: ' . $url : '',
        ]);

        return [
            'source_type' => 'scrape',
            'source_id' => $sourceId,
            'title' => $title !== '' ? $title : 'Item scraped',
            'text' => implode(' | ', $parts),
            'url' => $url,
            'image_url' => $image,
            'tags' => ['scraper', 'pipeline', $sourceId],
        ];
    }

    private function guessSourceId(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'epiesa')) {
            return 'epiesa';
        }
        if (str_contains($host, 'emag')) {
            return 'emag';
        }
        if (str_contains($host, 'pieseauto')) {
            return 'pieseauto';
        }

        return '';
    }

    private function queryFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $slug = trim(basename($path), '/');
        $slug = str_replace(['-', '_'], ' ', $slug);

        return $slug !== '' ? $slug : 'ulei motor';
    }

    private function absoluteUrl(string $base, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $href;
        }
        $parts = parse_url($base);
        if ($parts === false) {
            return $href;
        }
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        $dir = rtrim(dirname((string) ($parts['path'] ?? '/')), '/');

        return $origin . $dir . '/' . $href;
    }
}
