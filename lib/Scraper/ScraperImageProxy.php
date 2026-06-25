<?php

declare(strict_types=1);

/** Proxy imagini surse scrape — evită blocarea hotlink în admin. */
final class ScraperImageProxy
{
    /** @var list<string> */
    private const ALLOWED_HOST_SUFFIXES = [
        'autodoc.de',
        'autodoc24.ro',
        'akamaized.net',
        'emag.ro',
        'epiesa.ro',
        'pieseauto.ro',
    ];

    public static function isAllowedUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return false;
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    public static function stream(string $url): void
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!self::isAllowedUrl($url)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'URL imagine nepermis.';

            return;
        }

        $cacheDir = dirname(__DIR__, 2) . '/storage/scraper/image_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $cacheFile = $cacheDir . '/' . md5($url);
        $maxAge = 86400;

        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $maxAge) {
            self::outputFile($cacheFile, $url);

            return;
        }

        $referer = self::guessReferer($url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            CURLOPT_HTTPHEADER => array_values(array_filter([
                'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                $referer !== '' ? 'Referer: ' . $referer : null,
            ])),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $code >= 400 || $body === '') {
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Imagine indisponibilă (' . $code . ').';

            return;
        }

        @file_put_contents($cacheFile, $body);

        header('Content-Type: ' . ($type !== '' ? $type : 'image/jpeg'));
        header('Cache-Control: public, max-age=' . $maxAge);
        header('X-Scraper-Proxy: 1');
        echo $body;
    }

    private static function guessReferer(string $url): string
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if (str_contains($host, 'autodoc')) {
            return 'https://www.autodoc24.ro/';
        }
        if (str_contains($host, 'emag') || str_contains($host, 'akamaized')) {
            return 'https://www.emag.ro/';
        }

        return '';
    }

    private static function outputFile(string $path, string $url): void
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $type = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=86400');
        header('X-Scraper-Proxy: cache');
        readfile($path);
    }
}
