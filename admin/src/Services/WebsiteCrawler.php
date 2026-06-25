<?php

namespace Evasystem\Services;

class WebsiteCrawler {

    public function analyze($url) {
        $start = microtime(true);

        $context = stream_context_create([
            'http' => ['header' => "User-Agent: WebsiteCrawlerBot"]
        ]);

        $html = @file_get_contents($url, false, $context);
        $time = round((microtime(true) - $start) * 1000); // ms

        if (!$html) {
            return [
                'error' => 'Website inaccesibil',
                'status_code' => $this->getHttpStatus($url),
                'response_time_ms' => $time
            ];
        }

        $domain = parse_url($url, PHP_URL_HOST);
        $trafficData = $this->getEstimatedTraffic($domain);

        return [
            'status_code' => $this->getHttpStatus($url),
            'response_time_ms' => $time,
            'title' => $this->extractBetween($html, '<title>', '</title>'),
            'description' => $this->extractBetween($html, '<meta name="description" content="', '"'),
            'meta_keywords' => $this->extractBetween($html, '<meta name="keywords" content="', '"'),
            'robots' => $this->extractBetween($html, '<meta name="robots" content="', '"'),
            'h1' => $this->extractTagContent($html, 'h1'),
            'h2' => $this->extractTagContent($html, 'h2'),
            'cms' => $this->detectCMS($html),
            'social_links' => [
                'facebook' => str_contains($html, 'facebook.com'),
                'instagram' => str_contains($html, 'instagram.com'),
                'linkedin' => str_contains($html, 'linkedin.com'),
            ],
            'has_facebook_pixel' => str_contains($html, 'connect.facebook.net'),
            'has_google_ads' => str_contains($html, 'doubleclick.net') || str_contains($html, 'googletagmanager.com'),
            'has_google_analytics' => str_contains($html, 'google-analytics.com') || str_contains($html, 'gtag('),
            'has_favicon' => str_contains($html, 'rel="icon"') || str_contains($html, 'favicon.ico'),
            'robots_txt' => $this->checkUrl($url . '/robots.txt'),
            'sitemap_xml' => $this->checkUrl($url . '/sitemap.xml'),
            'estimated_traffic' => $trafficData
        ];
    }

    private function extractBetween($str, $start, $end) {
        $startPos = strpos($str, $start);
        if ($startPos === false) return '';
        $startPos += strlen($start);
        $endPos = strpos($str, $end, $startPos);
        if ($endPos === false) return '';
        return substr($str, $startPos, $endPos - $startPos);
    }

    private function extractTagContent($html, $tag) {
        preg_match_all('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/', $html, $matches);
        return $matches[1] ?? [];
    }

    private function detectCMS($html) {
        if (str_contains($html, 'wp-content')) return 'WordPress';
        if (str_contains($html, 'cdn.shopify.com')) return 'Shopify';
        if (str_contains($html, 'static.wixstatic.com')) return 'Wix';
        if (str_contains($html, 'squarespace.com')) return 'Squarespace';
        return 'Necunoscut';
    }

    private function checkUrl($url) {
        $headers = @get_headers($url);
        return ($headers && strpos($headers[0], '200')) !== false;
    }

    private function getHttpStatus($url) {
        $headers = @get_headers($url);
        if (!$headers || !isset($headers[0])) return 0;
        preg_match('/HTTP\/[0-9.]+\s+(\d+)/', $headers[0], $matches);
        return $matches[1] ?? 0;
    }

    private function getEstimatedTraffic($domain) {
        $apifyToken = trim((string) (getenv('APIFY_TOKEN') ?: ($_ENV['APIFY_TOKEN'] ?? '')));
        if ($apifyToken === '') {
            return ['error' => 'APIFY_TOKEN lipsă în admin/.env'];
        }
        $actorUrl = "https://api.apify.com/v2/acts/tri_angle~fast-similarweb-scraper/run-sync-get-dataset-items?token=$apifyToken";

        $postData = json_encode(["domain" => $domain]);

        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0] ?? ['error' => 'Nu s-au primit date din Fast Similarweb'];
    }
}
