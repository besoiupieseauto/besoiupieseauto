<?php

declare(strict_types=1);

/**
 * Potrivire produs + imagine corectă din listă / pagină detaliu Autodoc24.
 */
final class AutodocImageParser
{
    /** @param array<string, mixed> $product */
    public static function buildSearchQuery(array $product): string
    {
        $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipeline)) {
            require_once $pipeline;
            $queries = besoiu_image_search_queries_for_product($product);
            if ($queries !== []) {
                return $queries[0];
            }
        }

        $brand = trim((string) ($product['pBrand'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        $name = trim((string) ($product['pName'] ?? ''));

        if ($name !== '') {
            return $name;
        }
        if ($brand !== '' && $code !== '') {
            return $brand . ' ' . self::formatArticleCode($code);
        }
        if ($code !== '') {
            return self::formatArticleCode($code);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $product
     * @return list<string>
     */
    public static function buildSearchQueries(array $product): array
    {
        $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipeline)) {
            require_once $pipeline;

            return besoiu_image_search_queries_for_product($product, 'autodoc');
        }

        $single = self::buildSearchQuery($product);

        return $single !== '' ? [$single] : [];
    }

    public static function formatArticleCode(string $code): string
    {
        $digits = self::normalizeCode($code);
        if (strlen($digits) === 10) {
            return substr($digits, 0, 1) . ' ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3);
        }
        // OEM VAG/Renault 6 cifre: 770018 → 77-0018 (format Autodoc)
        if (strlen($digits) === 6) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4);
        }

        return trim($code);
    }

    /**
     * Variante căutare pentru același OEM (cu/fără cratimă, spații).
     *
     * @return list<string>
     */
    public static function oemCodeVariants(string $code): array
    {
        $raw = trim($code);
        $digits = self::normalizeCode($raw);
        if ($digits === '') {
            return $raw !== '' ? [$raw] : [];
        }

        $variants = [$digits];
        if ($raw !== '' && $raw !== $digits) {
            $variants[] = $raw;
        }

        $formatted = self::formatArticleCode($raw);
        if ($formatted !== '' && !in_array($formatted, $variants, true)) {
            $variants[] = $formatted;
        }

        if (strlen($digits) === 6) {
            $spaced = substr($digits, 0, 2) . ' ' . substr($digits, 2, 3) . ' ' . substr($digits, 5, 1);
            if (!in_array($spaced, $variants, true)) {
                $variants[] = $spaced;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @param array<string, mixed> $product
     * @return list<string> coduri normalizate (doar cifre) pentru scoring
     */
    public static function collectWantCodes(array $product): array
    {
        $list = [];
        $add = static function (string $code) use (&$list): void {
            foreach (self::oemCodeVariants($code) as $variant) {
                $n = self::normalizeCode($variant);
                if ($n !== '' && !in_array($n, $list, true)) {
                    $list[] = $n;
                }
                $ref = self::normalizeArticleRef($variant);
                if ($ref !== '' && strlen($ref) >= 4 && !in_array($ref, $list, true)) {
                    $list[] = $ref;
                }
            }
        };

        $add((string) ($product['pCode'] ?? ''));

        $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipeline)) {
            require_once $pipeline;
            foreach (besoiu_image_extract_oem_codes($product) as $oem) {
                $add((string) $oem);
            }
            if (function_exists('besoiu_image_iam_codes_from_product')) {
                foreach (besoiu_image_iam_codes_from_product($product) as $iam) {
                    $add((string) $iam);
                }
            }
        }

        return $list;
    }

    public static function normalizeCode(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /** Cod articol IAM alfanumeric (JTE280) — păstrează litere+cifre. */
    public static function normalizeArticleRef(string $value): string
    {
        $value = self::cleanSkuLabel($value);

        return strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '');
    }

    /** Curăță SKU/price din HTML Autodoc (ex. „Numărul articolului: 13787-PCS-MS”). */
    public static function sanitizeListingItem(array $item): array
    {
        if (isset($item['sku'])) {
            $item['sku'] = self::cleanSkuLabel((string) $item['sku']);
        }
        if (isset($item['price'])) {
            $item['price'] = self::cleanPrice((string) $item['price']);
        }
        if (isset($item['title'])) {
            $item['title'] = trim(preg_replace('/\s+/u', ' ', (string) $item['title']) ?? '');
        }

        return $item;
    }

    public static function cleanSkuLabel(string $raw): string
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($raw === '') {
            return '';
        }

        $patterns = [
            '/^(?:numărul?\s*articolului|numar\s*articolului|număr\s*articol|numar\s*articol|article\s*number|artikelnummer|art\.?\s*nr\.?)\s*:?\s*/iu',
            '/^(?:cod\s*articol|cod\s*produs)\s*:?\s*/iu',
        ];
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $raw);
            if (is_string($cleaned) && $cleaned !== $raw) {
                $raw = trim($cleaned);
                break;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    }

    public static function cleanPrice(string $raw): string
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($raw === '') {
            return '';
        }

        // „25, 99 lei” → „25,99 lei”
        if (preg_match('/^([\d][\d\s]*,\s*[\d]{2})\s*(.*)$/u', $raw, $m)) {
            $num = preg_replace('/\s+/', '', $m[1]);
            $suffix = trim($m[2]);

            return $suffix !== '' ? ($num . ' ' . $suffix) : $num;
        }

        return trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function sanitizeListingItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = self::sanitizeListingItem($item);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    public static function pickBestMatch(array $items, array $product, ?string $searchQuery = null): ?array
    {
        $ranked = self::rankMatches($items, $product, $searchQuery);

        return $ranked[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $product
     * @return list<array<string, mixed>>
     */
    public static function rankMatches(array $items, array $product, ?string $searchQuery = null): array
    {
        if ($items === []) {
            return [];
        }

        $wantCodes = self::collectWantCodes($product);
        if ($searchQuery !== null && trim($searchQuery) !== '') {
            foreach (self::oemCodeVariants((string) $searchQuery) as $variant) {
                $n = self::normalizeCode($variant);
                if ($n !== '' && !in_array($n, $wantCodes, true)) {
                    $wantCodes[] = $n;
                }
            }
        }

        $brand = strtolower(trim((string) ($product['pBrand'] ?? '')));
        if ($brand === '') {
            $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
            if (is_file($pipelinePath)) {
                require_once $pipelinePath;
                if (function_exists('besoiu_image_detect_brand_from_text')) {
                    $brand = strtolower(besoiu_image_detect_brand_from_text((string) ($product['pName'] ?? '')));
                }
            }
        }

        $items = self::sanitizeListingItems($items);

        $scored = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $score = self::scoreItem($item, $wantCodes, $brand, $product);
            if ($score < 0) {
                continue;
            }
            $scored[] = ['score' => $score, 'item' => $item];
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']));

        $minScore = 35;
        $queryTrim = trim((string) $searchQuery);
        $queryDigits = self::normalizeCode($queryTrim);
        if ($queryTrim !== '' && (str_contains($queryTrim, ' ') || strlen($queryTrim) > 12)) {
            $minScore = 70;
        } elseif ($queryDigits !== '' && strlen($queryDigits) < 8) {
            $minScore = 85;
        }

        $out = [];
        foreach ($scored as $row) {
            if ((int) $row['score'] < $minScore) {
                continue;
            }
            $out[] = $row['item'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $product
     * @return list<string>
     */
    private static function productContextTokens(array $product): array
    {
        $blob = strtolower(trim(implode(' ', [
            (string) ($product['pName'] ?? ''),
            (string) ($product['pCategory'] ?? ''),
            (string) ($product['pSubcategory'] ?? ''),
        ])));

        if ($blob === '') {
            return [];
        }

        $tokens = [];
        foreach (preg_split('/[^a-z0-9ăâîșțáéíóúüöä]+/iu', $blob) ?: [] as $part) {
            $part = trim((string) $part);
            if (strlen($part) >= 3) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    /** @param array<string, mixed> $product */
    private static function categoryRelevanceAdjust(string $title, array $product): int
    {
        $nameBlob = strtolower(trim(implode(' ', [
            (string) ($product['pName'] ?? ''),
            (string) ($product['pCategory'] ?? ''),
            (string) ($product['pSubcategory'] ?? ''),
        ])));
        $titleLower = strtolower($title);
        if ($nameBlob === '' || $titleLower === '') {
            return 0;
        }

        $groups = [
            'fluid' => ['lichid', 'ulei', 'antigel', 'frana', 'frână', 'brake', 'fluid', 'coolant'],
            'bearing' => ['rulment', 'bearing', 'suport', 'arc', 'sarcina'],
            'filter' => ['filtru', 'filter'],
            'brake_part' => ['placute', 'plăcuțe', 'disc', 'etrier'],
        ];

        $want = [];
        foreach ($groups as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($nameBlob, $word)) {
                    $want[$key] = true;
                    break;
                }
            }
        }

        if ($want === []) {
            return 0;
        }

        $adjust = 0;
        foreach ($groups as $key => $words) {
            $inTitle = false;
            foreach ($words as $word) {
                if (str_contains($titleLower, $word)) {
                    $inTitle = true;
                    break;
                }
            }
            if (!$inTitle) {
                continue;
            }
            if (!empty($want[$key])) {
                $adjust += 18;
            } elseif (count($want) === 1) {
                // Produs clar (ex. lichid frână) dar titlu Autodoc e altă familie
                $adjust -= 45;
            }
        }

        return $adjust;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $wantCodes coduri normalizate
     * @param array<string, mixed> $product
     */
    private static function scoreItem(array $item, array $wantCodes, string $brand, array $product = []): int
    {
        $title = strtolower((string) ($item['title'] ?? ''));
        $skuRaw = (string) ($item['sku'] ?? '');
        $sku = self::normalizeCode($skuRaw);
        $skuRef = self::normalizeArticleRef($skuRaw);
        $titleRef = self::normalizeArticleRef($title);
        $url = strtolower((string) ($item['url'] ?? ''));
        $image = strtolower((string) ($item['image'] ?? ''));

        if (str_contains($image, 'brands/thumbs') || str_contains($image, '360-icon')) {
            return -5;
        }

        if ($wantCodes === []) {
            $base = $brand !== '' && str_contains($title, $brand) ? 20 : 5;

            return $base + self::categoryRelevanceAdjust($title, $product);
        }

        $brand = strtolower(trim((string) ($product['pBrand'] ?? '')));
        if ($brand === '') {
            $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
            if (is_file($pipelinePath)) {
                require_once $pipelinePath;
                if (function_exists('besoiu_image_detect_brand_from_text')) {
                    $detected = besoiu_image_detect_brand_from_text((string) ($product['pName'] ?? ''));
                    if ($detected !== '') {
                        $brand = strtolower($detected);
                    }
                }
            }
        }

        $foreign = ['trw', 'mann', 'bosch', 'meyle', 'nk', 'sachs', 'valeo', 'gates', 'ridex', 'febi', 'skf'];
        if ($brand !== '') {
            foreach ($foreign as $other) {
                if ($other !== $brand && str_contains($title, $other)) {
                    return -10;
                }
            }
        }

        $bestForItem = 8;

        foreach ($wantCodes as $rawWant) {
            $wantCode = (string) $rawWant;
            if ($wantCode === '') {
                continue;
            }

            $wantRef = self::normalizeArticleRef($wantCode);
            if ($wantRef !== '' && strlen($wantRef) >= 4) {
                if ($skuRef === $wantRef || str_contains($titleRef, $wantRef)) {
                    return 100 + self::categoryRelevanceAdjust($title, $product);
                }
            }

            if ($sku === $wantCode) {
                if ($brand !== '' && !str_contains($title, $brand)) {
                    return -10;
                }

                return 100 + self::categoryRelevanceAdjust($title, $product);
            }

            $titleDigits = self::normalizeCode($title . ' ' . $skuRaw);
            if ($titleDigits === $wantCode) {
                $bestForItem = max($bestForItem, 92);
                continue;
            }

            // Cod scurt: fără potrivire parțială în șiruri lungi (360047 în alt OEM)
            if (strlen($wantCode) < 8) {
                if (preg_match('/\b' . preg_quote($wantCode, '/') . '\b/i', $title . ' ' . $skuRaw)) {
                    $bestForItem = max($bestForItem, 88);
                }
                continue;
            }

            if (str_contains($titleDigits, $wantCode)) {
                $bestForItem = max($bestForItem, 92);
                continue;
            }

            if ($sku !== '' && strlen($wantCode) >= 8 && strlen($sku) >= 8) {
                if (str_ends_with($wantCode, $sku) || str_ends_with($sku, $wantCode)) {
                    $bestForItem = max($bestForItem, 88);
                }
                if (substr($wantCode, -6) === substr($sku, -6)) {
                    $bestForItem = max($bestForItem, 85);
                }
            }

            $wantPlain = str_replace('-', '', $wantCode);
            if (str_contains($url, $wantCode) || ($wantPlain !== '' && str_contains($url, $wantPlain))) {
                $urlScore = strlen($wantCode) >= 8 ? 70 : 45;
                $bestForItem = max($bestForItem, $urlScore);
            }
        }

        if ($brand !== '' && str_contains($title, $brand)) {
            $bestForItem = max($bestForItem, 25);
        }

        $contextTokens = self::productContextTokens($product);
        if ($contextTokens !== []) {
            $hits = 0;
            foreach ($contextTokens as $token) {
                if (strlen($token) >= 4 && str_contains($title, $token)) {
                    ++$hits;
                }
            }
            if ($hits > 0) {
                $bestForItem += min(20, $hits * 6);
            }
        }

        return $bestForItem + self::categoryRelevanceAdjust($title, $product);
    }

    public static function cleanProductUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $url = preg_replace('/#.*$/', '', $url) ?? $url;

        return $url;
    }

    public static function upgradeImageUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        if (preg_match('#/360_photos/(\d+)/h-preview\.jpg#i', $url, $m)) {
            return 'https://media.autodoc.de/360_photos/' . $m[1] . '/preview.jpg';
        }

        if (preg_match('#/360_photos/(\d+)/preview\.jpg#i', $url)) {
            return $url;
        }

        if (preg_match('#/thumbs/(\d+)/[^/]+\.jpg#i', $url, $m)) {
            return 'https://media.autodoc.de/thumbs/' . $m[1] . '/0.jpg';
        }

        return $url;
    }

    public static function extractDetailPageImage(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)#i', $html, $m)) {
            return self::upgradeImageUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image#i', $html, $m)) {
            return self::upgradeImageUrl(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $selectors = [
            "//img[contains(@class,'product-gallery')][@src]",
            "//a[contains(@class,'product-gallery')]//img[@src]",
            "//div[contains(@class,'product-gallery')]//img[@src]",
            "//img[contains(@data-src,'media.autodoc.de')]",
            "//img[contains(@src,'media.autodoc.de')]",
        ];

        foreach ($selectors as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false) {
                continue;
            }
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $url = self::imageFromNode($node);
                if ($url !== '') {
                    return self::upgradeImageUrl($url);
                }
            }
        }

        return '';
    }

    private static function imageFromNode(DOMElement $node): string
    {
        $attrs = ['data-src', 'data-srcset', 'srcset', 'src'];
        foreach ($attrs as $attr) {
            $raw = trim(html_entity_decode($node->getAttribute($attr), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($raw === '') {
                continue;
            }
            if (str_contains($attr, 'srcset') && preg_match_all('#https?://[^\s,]+#i', $raw, $matches)) {
                foreach ($matches[0] as $candidate) {
                    if (!self::isBadImageUrl($candidate)) {
                        return $candidate;
                    }
                }
                continue;
            }
            if (!self::isBadImageUrl($raw)) {
                return $raw;
            }
        }

        return '';
    }

    private static function isBadImageUrl(string $url): bool
    {
        $lower = strtolower($url);

        return $url === ''
            || str_starts_with($lower, 'data:')
            || str_contains($lower, 'lazyload.php')
            || str_contains($lower, 'brands/thumbs')
            || str_contains($lower, '360-icon')
            || str_contains($lower, '/assets/')
            || str_contains($lower, '.svg');
    }
}
