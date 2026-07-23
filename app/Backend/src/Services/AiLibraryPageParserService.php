<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Parsare pagini produs + clasificare fragmente bibliotecă agent.
 */
final class AiLibraryPageParserService
{
    /** @return array{name:string,sku:string,price:string,url:string,brand:string,image:string,section:string}|null */
    public function parseProductFromHtml(string $html, string $url): ?array
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $fromJsonLd = $this->parseJsonLdProduct($html, $url);
        if ($fromJsonLd !== null) {
            return $fromJsonLd;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'epiesa')) {
            return $this->parseEpiesaProduct($html, $url);
        }

        return $this->parseGenericProduct($html, $url);
    }

    /** @return array{name:string,sku:string,price:string,url:string,brand:string,image:string,section:string}|null */
    public function parseStructuredText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || !preg_match('/^produs:\s*/iu', $text)) {
            return null;
        }

        $fields = [
            'name' => '',
            'sku' => '',
            'price' => '',
            'url' => '',
            'brand' => '',
        ];
        if (preg_match('/^Produs:\s*(.+?)\s*\|\s*SKU:\s*([^|]+)\s*\|\s*Pre[tț]:\s*([^|]+)\s*\|\s*URL:\s*(.+)$/iu', $text, $m)) {
            $fields['name'] = trim($m[1]);
            $fields['sku'] = trim($m[2]);
            $fields['price'] = trim($m[3]);
            $fields['url'] = trim($m[4]);
        } elseif (preg_match('/^Produs:\s*(.+?)\s*\|\s*SKU:\s*([^|]+)\s*\|\s*Pre[tț]:\s*(.+)$/iu', $text, $m)) {
            $fields['name'] = trim($m[1]);
            $fields['sku'] = trim($m[2]);
            $fields['price'] = trim($m[3]);
        } else {
            return null;
        }

        return array_merge($fields, ['image' => '', 'section' => 'products']);
    }

    /** @param array<string, mixed> $product */
    public function formatProductLine(array $product): string
    {
        $parts = [];
        $name = trim((string) ($product['name'] ?? ''));
        if ($name !== '') {
            $parts[] = 'Produs: ' . $name;
        }
        $sku = trim((string) ($product['sku'] ?? ''));
        if ($sku !== '') {
            $parts[] = 'SKU: ' . $sku;
        }
        $price = trim((string) ($product['price'] ?? ''));
        if ($price !== '') {
            $parts[] = 'Preț: ' . $price;
        }
        $url = trim((string) ($product['url'] ?? ''));
        if ($url !== '') {
            $parts[] = 'URL: ' . $url;
        }

        return implode(' | ', $parts);
    }

    /** @param array<string, mixed> $entry @return array{id:string,label:string,icon:string,sort:int} */
    public function classifySection(array $entry): array
    {
        $text = trim((string) ($entry['text'] ?? ''));
        $tags = is_array($entry['tags'] ?? null) ? $entry['tags'] : [];
        $tagStr = mb_strtolower(implode(' ', array_map('strval', $tags)));

        if (!empty($entry['pinned'])) {
            return ['id' => 'pinned', 'label' => 'Fixate (prioritare)', 'icon' => '📌', 'sort' => 1];
        }
        if ($this->parseStructuredText($text) !== null || str_starts_with($text, 'Produs:')) {
            return ['id' => 'products', 'label' => 'Produse parse', 'icon' => '📦', 'sort' => 2];
        }
        if (str_contains($tagStr, 'furnizor') || str_starts_with($text, 'Furnizor Besoiu:')) {
            return ['id' => 'suppliers', 'label' => 'Furnizori', 'icon' => '🏭', 'sort' => 5];
        }
        if (str_contains($text, 'INCLUDE vitrină') || str_contains($text, 'Semnal INCLUDE')) {
            return ['id' => 'vitrina_rules', 'label' => 'Reguli vitrină ePiesa', 'icon' => '📋', 'sort' => 4];
        }
        if (str_contains($tagStr, 'scrape') || str_contains($tagStr, 'url') || str_contains($tagStr, 'scraper')) {
            return ['id' => 'scrape', 'label' => 'Scrape URL', 'icon' => '🌐', 'sort' => 3];
        }
        if (str_contains($tagStr, 'corpus-seed')) {
            return ['id' => 'corpus_seed', 'label' => 'Seed corpus sistem', 'icon' => '🌱', 'sort' => 6];
        }
        if (str_contains($tagStr, 'manual') || str_contains($tagStr, 'din-chat') || str_contains($tagStr, 'operator-panel')) {
            return ['id' => 'manual', 'label' => 'Manual operator', 'icon' => '✏️', 'sort' => 7];
        }
        if (in_array((string) ($entry['source'] ?? ''), ['learned', 'event'], true)) {
            return ['id' => 'learned', 'label' => 'Învățat automat', 'icon' => '🤖', 'sort' => 8];
        }

        return ['id' => 'other', 'label' => 'Altele', 'icon' => '📄', 'sort' => 9];
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    public function enrichEntry(array $entry): array
    {
        $text = trim((string) ($entry['text'] ?? ''));
        $parsed = $this->parseStructuredText($text);
        $section = $this->classifySection($entry);
        $entry['section'] = $section['id'];
        $entry['section_label'] = $section['label'];
        $entry['section_icon'] = $section['icon'];
        $entry['is_noise'] = $this->isScrapeNoise($text) || $this->isBasicNoise($text);

        if ($parsed !== null) {
            $entry['parsed'] = [
                'type' => 'product',
                'name' => $parsed['name'],
                'sku' => $parsed['sku'],
                'price' => $parsed['price'],
                'url' => $parsed['url'],
            ];
        } elseif (preg_match('/^Furnizor Besoiu:\s*(.+?)\s*\|\s*Cod:\s*([^|]+)\s*\|\s*Status:\s*(.+)$/iu', $text, $m)) {
            $entry['parsed'] = [
                'type' => 'supplier',
                'name' => trim($m[1]),
                'code' => trim($m[2]),
                'status' => trim($m[3]),
            ];
        } elseif (str_contains($text, 'INCLUDE vitrină') || str_contains($text, 'Semnal INCLUDE')) {
            $entry['parsed'] = ['type' => 'rule', 'text' => mb_substr($text, 0, 200)];
        } else {
            $entry['parsed'] = ['type' => 'text', 'preview' => mb_substr($text, 0, 220)];
        }

        return $entry;
    }

    public function isScrapeNoise(string $text): bool
    {
        $lower = mb_strtolower(trim($text));
        if ($lower === '') {
            return true;
        }
        foreach ([
            'cookie-urile sunt',
            'cosul tau',
            'lista favorite',
            'activeaza-le in browser',
            'cauta cont salut',
            'toate categoriile',
            '×',
        ] as $noise) {
            if (str_contains($lower, $noise)) {
                return true;
            }
        }
        if (substr_count($text, "\n") > 8 && mb_strlen($text) > 600) {
            return true;
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) > 80) {
            $unique = count(array_unique(array_map('mb_strtolower', $words)));

            return $unique / max(1, count($words)) < 0.35;
        }

        return false;
    }

    private function isBasicNoise(string $text): bool
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        if ($lower === '' || mb_strlen($lower) < 8) {
            return true;
        }

        return preg_match('/\b(time_on_page|scroll|mousemove|heartbeat|visibility)\b/u', $lower) === 1;
    }

    /** @return array{name:string,sku:string,price:string,url:string,brand:string,image:string,section:string}|null */
    private function parseJsonLdProduct(string $html, string $url): ?array
    {
        if (!preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
            return null;
        }
        foreach ($blocks[1] as $raw) {
            $json = json_decode(trim($raw), true);
            if (!is_array($json)) {
                continue;
            }
            $items = isset($json['@type']) ? [$json] : (is_array($json['@graph'] ?? null) ? $json['@graph'] : []);
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = strtolower((string) ($item['@type'] ?? ''));
                if ($type !== 'product') {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $sku = trim((string) ($item['sku'] ?? $item['mpn'] ?? ''));
                $price = '';
                if (is_array($item['offers'] ?? null)) {
                    $price = trim((string) ($item['offers']['price'] ?? ''));
                    $currency = trim((string) ($item['offers']['priceCurrency'] ?? 'RON'));
                    if ($price !== '' && !str_contains($price, 'Lei') && !str_contains($price, $currency)) {
                        $price .= ' ' . ($currency === 'RON' ? 'Lei' : $currency);
                    }
                }
                $image = '';
                if (is_string($item['image'] ?? null)) {
                    $image = $item['image'];
                } elseif (is_array($item['image'] ?? null) && isset($item['image'][0])) {
                    $image = (string) $item['image'][0];
                }

                return [
                    'name' => $name,
                    'sku' => $sku,
                    'price' => $price,
                    'url' => trim((string) ($item['url'] ?? $url)),
                    'brand' => is_array($item['brand'] ?? null) ? (string) ($item['brand']['name'] ?? '') : (string) ($item['brand'] ?? ''),
                    'image' => $image,
                    'section' => 'products',
                ];
            }
        }

        return null;
    }

    /** @return array{name:string,sku:string,price:string,url:string,brand:string,image:string,section:string}|null */
    private function parseEpiesaProduct(string $html, string $url): ?array
    {
        $sku = '';
        if (preg_match('/mstrnid-(\d+)/i', $url, $m)) {
            $sku = $m[1];
        }

        $name = '';
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($name === '' && preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $name = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($name === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $name = preg_replace('/\s*[-|]\s*ePiesa.*$/iu', '', $name) ?? $name;
        }

        $price = '';
        if (preg_match('/itemprop=["\']price["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/content=["\']([^"\']+)["\'][^>]+itemprop=["\']price["\']/i', $html, $m)) {
            $price = trim($m[1]) . ' Lei';
        } elseif (preg_match('/(\d{1,6}(?:[.,]\d{2})?)\s*(?:Lei|RON)/iu', $html, $m)) {
            $price = str_replace('.', ',', $m[1]) . ' Lei';
        }

        if ($name === '' || ($sku === '' && $price === '')) {
            return null;
        }

        $brand = '';
        if (preg_match('/\b(ZOLLEX|MANN|BOSCH|MAHLE|FILTRON|CASTROL|MOTUL|ELF|TOTAL)\b/i', $name, $m)) {
            $brand = strtoupper($m[1]);
        }

        return [
            'name' => $name,
            'sku' => $sku,
            'price' => $price,
            'url' => $url,
            'brand' => $brand,
            'image' => $this->firstOgImage($html),
            'section' => 'products',
        ];
    }

    /** @return array{name:string,sku:string,price:string,url:string,brand:string,image:string,section:string}|null */
    private function parseGenericProduct(string $html, string $url): ?array
    {
        $seo = ['h1' => '', 'title' => ''];
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $seo['h1'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $seo['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $name = $seo['h1'] !== '' ? $seo['h1'] : $seo['title'];
        if ($name === '' || mb_strlen($name) < 5) {
            return null;
        }

        $price = '';
        if (preg_match('/(\d{1,6}(?:[.,]\d{2})?)\s*(?:Lei|RON|EUR)/iu', $html, $m)) {
            $price = str_replace('.', ',', $m[1]) . ' Lei';
        }

        if ($price === '' && !preg_match('/product|produs|mstrnid|sku|cod/i', $url . ' ' . $html)) {
            return null;
        }

        return [
            'name' => $name,
            'sku' => '',
            'price' => $price,
            'url' => $url,
            'brand' => '',
            'image' => $this->firstOgImage($html),
            'section' => 'products',
        ];
    }

    private function firstOgImage(string $html): string
    {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
