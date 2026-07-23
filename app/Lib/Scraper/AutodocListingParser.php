<?php

declare(strict_types=1);

require_once __DIR__ . '/AutodocImageParser.php';
require_once __DIR__ . '/ScraperCssXPath.php';

/**
 * Parser listă căutare Autodoc24 — card div.listing-item__wrap.
 */
final class AutodocListingParser
{
    private const BASE = 'https://www.autodoc24.ro';

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
        $nodes = $xpath->query(ScraperCssXPath::toXPath('div.listing-item__wrap'));
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//div[contains(@class,'listing-item__wrap')]");
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

        return AutodocImageParser::sanitizeListingItems($out);
    }

    /** @return array<string, mixed>|null */
    private static function parseCard(DOMXPath $xpath, DOMElement $root): ?array
    {
        $titleNode = $xpath->query(".//a[contains(@class,'listing-item__name')]", $root)->item(0);
        if (!$titleNode instanceof DOMElement) {
            $titleNode = $xpath->query(".//a[contains(@class,'listing-item')][@href]", $root)->item(0);
        }

        $title = '';
        $url = '';
        if ($titleNode instanceof DOMElement) {
            $title = trim(preg_replace('/\s+/u', ' ', $titleNode->textContent ?? '') ?? '');
            $url = self::absUrl($titleNode->getAttribute('href'));
        }

        $imgNode = $xpath->query(".//*[contains(@class,'listing-item__image-product')]//img", $root)->item(0);
        if (!$imgNode instanceof DOMElement) {
            $imgNode = $xpath->query('.//img', $root)->item(0);
        }
        $image = $imgNode instanceof DOMElement ? self::imageFromNode($imgNode) : '';

        $priceNode = $xpath->query(".//*[contains(@class,'listing-item__price-new')]", $root)->item(0);
        $price = $priceNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $priceNode->textContent ?? '') ?? '')
            : '';

        $skuNode = $xpath->query(".//*[contains(@class,'listing-item__article-item')]", $root)->item(0);
        $sku = $skuNode instanceof DOMElement
            ? trim(preg_replace('/\s+/u', ' ', $skuNode->textContent ?? '') ?? '')
            : '';

        $ean = '';
        $specNodes = $xpath->query(".//li[contains(@class,'product-description__item')]", $root);
        if ($specNodes !== false) {
            foreach ($specNodes as $specNode) {
                if (!$specNode instanceof DOMElement) {
                    continue;
                }
                $label = mb_strtolower(trim($specNode->textContent ?? ''), 'UTF-8');
                if (!str_contains($label, 'ean')) {
                    continue;
                }
                $valueNode = $xpath->query(".//span[contains(@class,'product-description__item-value')]", $specNode)->item(0);
                if ($valueNode instanceof DOMElement) {
                    $ean = trim(preg_replace('/\s+/u', ' ', $valueNode->textContent ?? '') ?? '');
                }
                break;
            }
        }

        if ($title === '' && $url === '') {
            return null;
        }

        if ($image !== '') {
            $image = AutodocImageParser::upgradeImageUrl($image);
        }

        return [
            'title' => $title,
            'url' => $url,
            'image' => $image,
            'price' => $price,
            'sku' => $sku,
            'ean' => $ean,
        ];
    }

    private static function absUrl(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        return rtrim(self::BASE, '/') . '/' . ltrim($href, '/');
    }

    private static function imageFromNode(DOMElement $node): string
    {
        foreach (['data-src', 'data-srcset', 'srcset', 'src'] as $attr) {
            $raw = trim(html_entity_decode($node->getAttribute($attr), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($raw === '') {
                continue;
            }
            if (str_contains($attr, 'srcset') && preg_match_all('#https?://[^\s,]+#i', $raw, $matches)) {
                foreach ($matches[0] as $candidate) {
                    if (!self::isBadImage($candidate)) {
                        return $candidate;
                    }
                }
                continue;
            }
            if (!self::isBadImage($raw)) {
                return self::absUrl($raw);
            }
        }

        return '';
    }

    private static function isBadImage(string $url): bool
    {
        $lower = strtolower($url);

        return $url === ''
            || str_starts_with($lower, 'data:')
            || str_contains($lower, 'placeholder')
            || str_contains($lower, 'star-fill')
            || str_contains($lower, 'brands/thumbs')
            || str_contains($lower, '360-icon')
            || str_ends_with($lower, '.svg');
    }
}
