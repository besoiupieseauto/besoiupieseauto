<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';

final class EpiesaImageCache
{
    private const MESTERINO_BASE = 'https://www.mesterino.ro/poze_produse';

    /** @param array<string, mixed> $product @return array<string, mixed> */
    public static function withPublicImage(array $product): array
    {
        $local = trim((string) ($product['image_local'] ?? ''));
        $image = trim((string) ($product['image'] ?? ''));
        $candidate = $local !== '' ? $local : $image;

        if ($candidate !== '' && str_starts_with($candidate, '/assets/scraper/')) {
            $path = ScraperPaths::projectRoot() . $candidate;
            if (is_file($path) && (int) filesize($path) >= 64) {
                $product['image'] = $candidate;

                return $product;
            }
        }

        return self::ensureCached($product);
    }

    /** @param array<string, mixed> $product @return array<string, mixed> */
    public static function ensureCached(array $product): array
    {
        $remote = self::resolveBestRemoteUrl($product);
        if ($remote === '') {
            return $product;
        }

        $id = self::productId($product);
        if ($id === '') {
            return $product;
        }

        ScraperPaths::ensureDirs();

        $ext = self::extensionFromUrl($remote);
        $filename = $id . '.' . $ext;
        $localPath = ScraperPaths::imagesDir() . '/' . $filename;
        $publicUrl = ScraperPaths::imagesPublicUrl() . '/' . $filename;

        if (!is_file($localPath) || (int) filesize($localPath) < 64) {
            if (!self::download($remote, $localPath)) {
                ScraperLogger::log('warn', 'Imagine ePiesa eșuată | id=' . $id . ' | ' . $remote);

                return $product;
            }
        }

        $product['image_remote'] = $remote;
        $product['image_local'] = $publicUrl;
        $product['image'] = $publicUrl;
        if (str_contains($remote, 'mesterino.ro')) {
            $product['image_source'] = 'mesterino';
        }

        return $product;
    }

    /**
     * URL imagine HD — ePiesa folosește poze de pe mesterino.ro (mstrnid în link).
     *
     * @param array<string, mixed> $product
     */
    public static function resolveBestRemoteUrl(array $product): string
    {
        $existing = trim((string) ($product['image_remote'] ?? ''));
        if ($existing !== '' && preg_match('#^https?://#i', $existing) && !self::isLowQualityEpiesaThumb($existing)) {
            return $existing;
        }

        $mstrnId = self::mstrnIdFromProduct($product);
        if ($mstrnId !== null) {
            $mesterino = self::probeMesterinoImage($mstrnId);
            if ($mesterino !== '') {
                return $mesterino;
            }
        }

        $image = trim((string) ($product['image'] ?? ''));
        if ($image !== '' && preg_match('#^https?://#i', $image) && !self::isLowQualityEpiesaThumb($image)) {
            return $image;
        }

        if ($mstrnId !== null) {
            return self::mesterinoUrl($mstrnId, 1, 'png');
        }

        return '';
    }

    /** @param array<string, mixed> $product */
    public static function mstrnIdFromProduct(array $product): ?int
    {
        foreach (['url_path', 'url'] as $key) {
            $value = trim((string) ($product[$key] ?? ''));
            if ($value !== '' && preg_match('/mstrnid-(\d+)/i', $value, $matches)) {
                return (int) $matches[1];
            }
        }

        $image = trim((string) ($product['image'] ?? ''));
        if ($image !== '' && preg_match('#/(\d+)_\d+\.(?:jpe?g|png|webp|gif)#i', $image, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /** @return list<string> */
    public static function mesterinoCandidates(int $mstrnId): array
    {
        $urls = [];
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $urls[] = self::mesterinoUrl($mstrnId, 1, $ext);
        }

        return $urls;
    }

    public static function mesterinoUrl(int $mstrnId, int $variant = 1, string $ext = 'png'): string
    {
        $ext = strtolower($ext);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return self::MESTERINO_BASE . '/' . $mstrnId . '_' . max(1, $variant) . '.' . $ext;
    }

    public static function probeMesterinoImage(int $mstrnId): string
    {
        foreach (self::mesterinoCandidates($mstrnId) as $url) {
            if (self::urlExists($url)) {
                return $url;
            }
        }

        return '';
    }

    private static function isLowQualityEpiesaThumb(string $url): bool
    {
        $lower = strtolower($url);

        return self::isPlaceholderImage($url)
            || str_contains($lower, '/thumb')
            || str_contains($lower, '/thumbs/')
            || str_contains($lower, 'resize')
            || preg_match('/width=(?:50|60|80|100)(?:&|$)/', $lower) === 1;
    }

    private static function isPlaceholderImage(string $url): bool
    {
        $lower = strtolower($url);

        return str_starts_with($lower, 'data:image')
            || str_contains($lower, 'placeholder')
            || str_contains($lower, 'star-fill')
            || str_contains($lower, '/blank.')
            || str_contains($lower, 'spinner')
            || str_contains($lower, 'loader');
    }

    /** @param array<string, mixed> $product */
    private static function productId(array $product): string
    {
        $mstrnId = self::mstrnIdFromProduct($product);
        if ($mstrnId !== null) {
            return 'epiesa_' . $mstrnId;
        }

        $path = trim((string) ($product['url_path'] ?? ''));
        $url = trim((string) ($product['url'] ?? ''));
        $seed = $path !== '' ? $path : $url;

        return $seed !== '' ? 'epiesa_' . substr(md5($seed), 0, 12) : '';
    }

    private static function extensionFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }

    private static function urlExists(string $url): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'BesoiuEpiesaScraper/1.0',
        ]);

        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        return $code >= 200 && $code < 400 && ($size <= 0 || $size >= 64);
    }

    private static function download(string $url, string $dest): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'BesoiuEpiesaScraper/1.0',
        ]);

        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($data) || strlen($data) < 64 || $code >= 400) {
            return false;
        }

        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return file_put_contents($dest, $data, LOCK_EX) !== false;
    }
}
