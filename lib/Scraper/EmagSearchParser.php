<?php

declare(strict_types=1);

final class EmagSearchParser
{
    /**
     * @return array{image_url:string,title:string,product_url:string}|null
     */
    public static function parseFirstCard(string $html): ?array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($html) === '') {
            return null;
        }

        $block = self::extractFirstCardBlock($html);
        $imageUrl = $block !== '' ? self::extractImageUrl($block) : '';
        if ($imageUrl === '') {
            $imageUrl = self::extractImageUrl($html);
        }

        if ($imageUrl === '') {
            return null;
        }

        $title = '';
        $searchIn = $block !== '' ? $block : $html;
        if (preg_match('/class=["\']card-v2-title[^"\']*["\'][^>]*>([^<]+)</i', $searchIn, $match)) {
            $title = trim(html_entity_decode(strip_tags((string) $match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $productUrl = '';
        if (preg_match('/class=["\'][^"\']*card-v2-thumb[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i', $searchIn, $match)) {
            $productUrl = trim(html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return [
            'image_url' => $imageUrl,
            'title' => $title,
            'product_url' => $productUrl,
        ];
    }

    public static function htmlLooksLikeEmagSearch(string $html): bool
    {
        $lower = mb_strtolower($html, 'UTF-8');

        return str_contains($lower, 'emag.ro')
            || str_contains($lower, 'card-v2')
            || str_contains($lower, 'akamaized.net/products/');
    }

    private static function extractFirstCardBlock(string $html): string
    {
        foreach ([
            'class="card-v2"',
            "class='card-v2'",
            'class="card-v2-wrapper',
            "class='card-v2-wrapper",
            'card-v2-wrapper',
        ] as $marker) {
            $pos = stripos($html, $marker);
            if ($pos !== false) {
                return substr($html, $pos, 24000) ?: '';
            }
        }

        return '';
    }

    private static function extractImageUrl(string $fragment): string
    {
        $patterns = [
            '/class=["\']card-v2-thumb-inner["\'][^>]*>\s*<img[^>]+src=["\']([^"\']+)["\']/is',
            '/<img[^>]+src=["\'](https?:\/\/[^"\']*emagst\.akamaized\.net\/products\/[^"\']+)["\']/i',
            '/data-img=["\']([^"\']*emagst\.akamaized\.net\/products\/[^"\']+)["\']/i',
            '/(https?:\/\/s\d+emagst\.akamaized\.net\/products\/[^\s"\'<>]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $fragment, $match)) {
                continue;
            }
            $url = html_entity_decode(trim((string) ($match[1] ?? $match[0])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (self::isProductImageUrl($url)) {
                return self::normalizeImageUrl($url);
            }
        }

        return '';
    }

    private static function isProductImageUrl(string $url): bool
    {
        return $url !== ''
            && stripos($url, 'akamaized.net/products/') !== false
            && stripos($url, 'user-wallet') === false;
    }

    private static function normalizeImageUrl(string $url): string
    {
        $url = preg_replace('/width=\d+/i', 'width=720', $url) ?? $url;
        $url = preg_replace('/height=\d+/i', 'height=720', $url) ?? $url;

        return $url;
    }
}
