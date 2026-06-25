<?php
declare(strict_types=1);

final class EpiesaCategoryParser
{
    private const BASE = 'https://www.epiesa.ro';

    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $html, int $limit = 10): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' sub-product-inner ')]");
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//div[contains(@class,'sub-product-inner')]");
        }
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//div[contains(@class,'single-sub-product')]");
        }

        $out = [];
        if ($nodes === false) {
            return $out;
        }

        foreach ($nodes as $node) {
            if (count($out) >= $limit) {
                break;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }

            $item = self::parseCard($xpath, $node);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private static function parseCard(DOMXPath $xpath, DOMElement $root): ?array
    {
        $linkNode = $xpath->query(".//*[contains(@class,'product-auto-title')]//a[@href]", $root)->item(0);
        if (!$linkNode instanceof DOMElement) {
            $linkNode = $xpath->query(".//*[contains(@class,'sub-product-text')]//a[@href]", $root)->item(0);
        }
        if (!$linkNode instanceof DOMElement) {
            $linkNode = $xpath->query('.//a[@href]', $root)->item(0);
        }
        if (!$linkNode instanceof DOMElement) {
            return null;
        }

        $href = trim((string) $linkNode->getAttribute('href'));
        if ($href === '' || $href === '#') {
            return null;
        }

        $title = trim((string) $linkNode->getAttribute('title'));
        if ($title === '') {
            $title = trim(preg_replace('/\s+/u', ' ', $linkNode->textContent ?? '') ?? '');
        }

        $imgNode = $xpath->query(".//*[contains(@class,'sub-product-img')]//img", $root)->item(0);
        if (!$imgNode instanceof DOMElement) {
            foreach ($xpath->query('.//img', $root) as $candidate) {
                if (!$candidate instanceof DOMElement) {
                    continue;
                }
                $src = strtolower(trim((string) $candidate->getAttribute('src')));
                if ($src === '' || str_contains($src, 'star-fill') || str_contains($src, 'placeholder')) {
                    continue;
                }
                $imgNode = $candidate;
                break;
            }
        }
        $image = self::extractImageUrl($imgNode);

        $priceNode = $xpath->query(".//*[contains(@class,'bricolaje-bottom-text')]//h4", $root)->item(0);
        $price = $priceNode ? trim(preg_replace('/\s+/u', ' ', $priceNode->textContent ?? '') ?? '') : '';

        $details = [];
        foreach ($xpath->query(".//*[contains(@class,'sub-product-detail')]//p", $root) as $p) {
            $t = trim(preg_replace('/\s+/u', ' ', $p->textContent ?? '') ?? '');
            if ($t !== '') {
                $details[] = $t;
            }
        }

        $absoluteUrl = self::absoluteUrl($href);

        return [
            'title'       => $title,
            'url'         => $absoluteUrl,
            'url_path'    => $href,
            'image'       => self::absoluteUrl($image),
            'price'       => $price,
            'details'     => $details,
            'description' => implode(' · ', $details),
        ];
    }

    private static function extractImageUrl(?DOMElement $imgNode): string
    {
        if (!$imgNode instanceof DOMElement) {
            return '';
        }

        foreach (['src', 'data-src', 'data-lazy-src', 'data-original', 'data-lazy'] as $attr) {
            $value = trim((string) $imgNode->getAttribute($attr));
            if ($value !== '' && !self::isPlaceholderImage($value)) {
                return self::absoluteUrl($value);
            }
        }

        $srcset = trim((string) $imgNode->getAttribute('srcset'));
        if ($srcset !== '') {
            $first = trim(explode(',', $srcset)[0] ?? '');
            $url = trim(explode(' ', $first)[0] ?? '');
            if ($url !== '' && !self::isPlaceholderImage($url)) {
                return self::absoluteUrl($url);
            }
        }

        return '';
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

    private static function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return rtrim(self::BASE, '/') . '/' . ltrim($url, '/');
    }
}
