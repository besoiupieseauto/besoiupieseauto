<?php
declare(strict_types=1);

require_once __DIR__ . '/EpiesaImageCache.php';

final class EpiesaCategoryParser
{
    private const BASE = 'https://www.epiesa.ro';

    /** Pagină căutare validă — cu sau fără produse (layout 2026: div.prod-card). */
    public static function htmlLooksLikeSearchPage(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }
        $lower = mb_strtolower($html, 'UTF-8');

        if (str_contains($lower, 'prod-card')
            || str_contains($lower, 'lp-card')
            || str_contains($lower, 'pc-name')
            || str_contains($lower, 'prod-grid')
            || str_contains($lower, 'srch-head')) {
            return true;
        }

        return str_contains($lower, 'rezultate cautare')
            || str_contains($lower, 'niciun rezultat')
            || str_contains($lower, 'cautare-piesa');
    }

    /** Marker stealth/HTTP — layout curent listă ePiesa. */
    public static function stealthHtmlMarker(): string
    {
        return 'prod-card';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $html, int $limit = 10, bool $cacheImages = true): array
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
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' lp-card ')]");
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//article[contains(@class,'lp-card')]");
        }
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' prod-card ')]");
        }
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//div[contains(@class,'prod-card')]");
        }
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' sub-product-inner ')]");
        }
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

        return $cacheImages ? EpiesaImageCache::cacheItems($out) : $out;
    }

    /** @return array<string, mixed>|null */
    private static function parseCard(DOMXPath $xpath, DOMElement $root): ?array
    {
        $classAttr = ' ' . ($root->getAttribute('class') ?? '') . ' ';
        if (str_contains($classAttr, ' lp-card ')) {
            return self::parseLpCard($xpath, $root);
        }

        $isProdCard = str_contains($classAttr, ' prod-card ');

        if ($isProdCard) {
            return self::parseProdCard($xpath, $root);
        }

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

    /** Card nou ePiesa (2026): article.lp-card */
    /** @return array<string, mixed>|null */
    private static function parseLpCard(DOMXPath $xpath, DOMElement $root): ?array
    {
        $favBtn = $xpath->query(".//button[contains(@class,'js-fav')][@data-cod]", $root)->item(0);
        $denumire = $favBtn instanceof DOMElement ? trim((string) $favBtn->getAttribute('data-denumire')) : '';
        $code = $favBtn instanceof DOMElement ? trim((string) $favBtn->getAttribute('data-cod')) : '';
        $poza = $favBtn instanceof DOMElement ? trim((string) $favBtn->getAttribute('data-poza')) : '';
        $href = $favBtn instanceof DOMElement ? trim((string) $favBtn->getAttribute('data-url')) : '';

        $brand = trim((string) $root->getAttribute('data-brand'));
        if ($brand === '') {
            $brandNode = $xpath->query(".//*[contains(@class,'lp-brand')]", $root)->item(0);
            $brand = $brandNode instanceof DOMElement
                ? trim(preg_replace('/\s+/u', ' ', $brandNode->textContent ?? '') ?? '')
                : '';
        }

        $nameNode = $xpath->query(".//a[contains(@class,'lp-name')]", $root)->item(0);
        $shortName = $nameNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $nameNode->textContent ?? '') ?? '')
            : '';

        $codNode = $xpath->query(".//*[contains(@class,'lp-cod')]", $root)->item(0);
        if ($codNode instanceof DOMElement) {
            $codText = trim(preg_replace('/^Cod:\s*/ui', '', $codNode->textContent ?? '') ?? '');
            if ($code === '' && $codText !== '') {
                $code = $codText;
            }
        }

        $title = $denumire !== '' ? $denumire : trim($brand . ' ' . $shortName);

        if ($href === '') {
            $linkNode = $xpath->query(".//a[contains(@class,'lp-img')][@href]", $root)->item(0);
            if ($linkNode instanceof DOMElement) {
                $href = trim((string) $linkNode->getAttribute('href'));
            }
        }

        $image = $poza !== '' ? self::absoluteUrl($poza) : '';
        if ($image === '') {
            $imgNode = $xpath->query(".//*[contains(@class,'lp-img')]//img", $root)->item(0);
            $image = self::extractImageUrl($imgNode instanceof DOMElement ? $imgNode : null);
        }

        $priceNode = $xpath->query(".//*[contains(@class,'lp-pret')]", $root)->item(0);
        $price = $priceNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $priceNode->textContent ?? '') ?? '')
            : '';

        $techNode = $xpath->query(".//*[contains(@class,'lp-tech')]", $root)->item(0);
        $tech = $techNode instanceof DOMElement ? trim($techNode->textContent ?? '') : '';

        if ($href === '' && $title === '') {
            return null;
        }

        return [
            'title'       => $title,
            'url'         => self::absoluteUrl($href),
            'url_path'    => $href,
            'image'       => $image,
            'price'       => $price,
            'code'        => $code,
            'brand'       => $brand,
            'details'     => $tech !== '' ? [$tech] : [],
            'description' => $tech !== '' ? $tech : $title,
        ];
    }

    /** Card ePiesa layout intermediar: div.prod-card */
    /** @return array<string, mixed>|null */
    private static function parseProdCard(DOMXPath $xpath, DOMElement $root): ?array
    {
        $linkNode = $xpath->query(".//a[contains(@class,'pc-name')][@href]", $root)->item(0);
        if (!$linkNode instanceof DOMElement) {
            $linkNode = $xpath->query(".//a[contains(@class,'pc-img')][@href]", $root)->item(0);
        }
        if (!$linkNode instanceof DOMElement) {
            return null;
        }

        $href = trim((string) $linkNode->getAttribute('href'));
        if ($href === '' || $href === '#') {
            return null;
        }

        $titleNode = $xpath->query(".//a[contains(@class,'pc-name')]", $root)->item(0);
        $title = $titleNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $titleNode->textContent ?? '') ?? '')
            : '';
        if ($title === '') {
            $title = trim((string) $linkNode->getAttribute('title'));
        }

        $imgNode = $xpath->query(".//*[contains(@class,'pc-img')]//img", $root)->item(0);
        if (!$imgNode instanceof DOMElement) {
            $imgNode = $xpath->query('.//img', $root)->item(0);
        }
        $image = self::extractImageUrl($imgNode instanceof DOMElement ? $imgNode : null);

        $priceNode = $xpath->query(".//*[contains(@class,'pc-price')]", $root)->item(0);
        $price = $priceNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $priceNode->textContent ?? '') ?? '')
            : '';

        $code = '';
        $favBtn = $xpath->query(".//button[contains(@class,'js-fav')][@data-cod]", $root)->item(0);
        if ($favBtn instanceof DOMElement) {
            $code = trim((string) $favBtn->getAttribute('data-cod'));
        }

        $absoluteUrl = self::absoluteUrl($href);

        return [
            'title'       => $title,
            'url'         => $absoluteUrl,
            'url_path'    => $href,
            'image'       => $image,
            'price'       => $price,
            'code'        => $code,
            'details'     => [],
            'description' => $title,
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
