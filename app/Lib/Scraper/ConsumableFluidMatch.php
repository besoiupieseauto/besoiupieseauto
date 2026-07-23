<?php

declare(strict_types=1);

/**
 * Potrivire fluide/consumabile — cod furnizor (INTERCARS) ≠ cod ePiesa/Autodoc.
 * Căutare după denumire + brand + volum/viscozitate.
 */
final class ConsumableFluidMatch
{
    /** Cod catalog furnizor (MOBILUBEHDA85W901L) — nu există pe ePiesa la căutare directă. */
    public static function isSupplierCatalogCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        if (strlen($code) >= 12 && preg_match('/^[A-Z0-9][A-Z0-9\-_.]{10,}$/i', $code) === 1) {
            return true;
        }
        if (strlen($code) >= 10 && preg_match('/^[A-Z]{3,}\d{2,}[A-Z0-9]*$/i', $code) === 1) {
            return true;
        }

        return false;
    }

    public static function stripSupplierCodeFromText(string $text, string $code): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $code = trim($code);
        if ($code !== '') {
            $text = trim(str_ireplace($code, '', $text));
        }
        $text = trim(preg_replace('/\b[A-Z]{4,}\d{2,}[A-Z0-9]{4,}\b/u', '', $text) ?? $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{brand: string, clean_name: string, viscosity: string, volume_l: ?float, fluid_family: ?string}
     */
    public static function extractSpecs(array $product): array
    {
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim(preg_replace('/\s+xxl$/iu', '', trim((string) ($product['pBrand'] ?? ''))) ?? '');
        $name = trim((string) ($product['pName'] ?? ''));
        $sub = trim((string) ($product['pSubcategory'] ?? ''));
        $clean = self::stripSupplierCodeFromText($name, $code);
        $blob = mb_strtolower(trim($clean . ' ' . $sub . ' ' . (string) ($product['pCategory'] ?? '') . ' ' . $code), 'UTF-8');

        $viscosity = '';
        if (preg_match('/\b(\d{1,2}\s*w[\s-]?\d{2})\b/iu', $blob, $m) === 1) {
            $viscosity = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? $m[1]);
        } elseif (preg_match('/(\d{1,2}w\d{2})/i', $code, $m) === 1) {
            $viscosity = strtoupper($m[1]);
        }

        $volumeL = null;
        if (preg_match('/(\d{1,2})\s*l\b/iu', $clean, $m) === 1) {
            $volumeL = (float) $m[1];
        } elseif (preg_match('/(\d{1,2})l$/i', $code, $m) === 1) {
            $volumeL = (float) $m[1];
        } elseif (preg_match('/(\d{1,2})l$/i', $name, $m) === 1) {
            $volumeL = (float) $m[1];
        }

        $fluidFamily = null;
        if (str_contains($blob, 'transmisie') || str_contains($blob, 'transmission') || preg_match('/\batf\b/iu', $blob) === 1) {
            $fluidFamily = 'atf';
        } elseif (str_contains($blob, 'lichid') && (str_contains($blob, 'fran') || str_contains($blob, 'frân'))) {
            $fluidFamily = 'brake_fluid';
        } elseif (str_contains($blob, 'antigel') || str_contains($blob, 'coolant')) {
            $fluidFamily = 'coolant';
        } else {
            $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
            if (is_file($pipelinePath)) {
                require_once $pipelinePath;
                if (function_exists('besoiu_detect_fluid_family')) {
                    $fluidFamily = besoiu_detect_fluid_family($blob);
                }
            }
        }

        return [
            'brand' => $brand,
            'clean_name' => $clean,
            'viscosity' => $viscosity,
            'volume_l' => $volumeL,
            'fluid_family' => $fluidFamily,
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @return list<string>
     */
    /**
     * Interogări consumabile/import: 1) cod OEM → 2) denumire → 3) brand + subcategorie.
     *
     * @param array<string, mixed> $product
     * @return list<string>
     */
    public static function buildConsumableSearchQueries(array $product): array
    {
        $specs = self::extractSpecs($product);
        $brand = $specs['brand'];
        $clean = $specs['clean_name'];
        $code = trim((string) ($product['pCode'] ?? ''));
        $fullName = trim((string) ($product['pName'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));
        $category = trim((string) ($product['pCategory'] ?? ''));

        $queries = [];
        $push = static function (string $q) use (&$queries): void {
            $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
            if ($q === '') {
                return;
            }
            foreach ($queries as $existing) {
                if (mb_strtolower($existing, 'UTF-8') === mb_strtolower($q, 'UTF-8')) {
                    return;
                }
            }
            $queries[] = $q;
        };

        // 1) Cod OEM / pCode
        if ($code !== '' && !self::isSupplierCatalogCode($code) && strlen($code) <= 20) {
            $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
            if (is_file($pipeline) && function_exists('besoiu_image_format_oem_search_code')) {
                require_once $pipeline;
                $push(besoiu_image_format_oem_search_code($code));
            } else {
                $push($code);
            }
        }

        // 2) Denumire completă magazin
        if ($fullName !== '' && mb_strlen($fullName, 'UTF-8') <= 120) {
            $push($fullName);
        }
        if ($clean !== '' && !self::isSupplierCatalogCode($clean)
            && mb_strtolower($clean, 'UTF-8') !== mb_strtolower($fullName, 'UTF-8')) {
            $push($clean);
        }

        // 3) Brand + subcategorie (apoi categorie)
        if ($brand !== '' && $subcategory !== '') {
            $push(trim($brand . ' ' . $subcategory));
            $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
            if (is_file($pipeline) && function_exists('besoiu_image_short_category_label')) {
                require_once $pipeline;
                $shortSub = besoiu_image_short_category_label($subcategory);
                if ($shortSub !== '' && mb_strtolower($shortSub, 'UTF-8') !== mb_strtolower($subcategory, 'UTF-8')) {
                    $push(trim($brand . ' ' . $shortSub));
                }
            }
        }
        if ($brand !== '' && $category !== '') {
            $catKey = mb_strtolower($category, 'UTF-8');
            $subKey = mb_strtolower($subcategory, 'UTF-8');
            if ($subKey === '' || $catKey !== $subKey) {
                $push(trim($brand . ' ' . $category));
            }
        }

        return $queries;
    }

    /**
     * @param array<string, mixed> $product
     * @return list<string>
     */
    public static function buildEpiesaQueries(array $product): array
    {
        return self::buildConsumableSearchQueries($product);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $hit
     */
    public static function scoreHit(array $product, array $hit): int
    {
        $specs = self::extractSpecs($product);
        $code = trim((string) ($product['pCode'] ?? ''));
        $title = mb_strtolower(trim((string) ($hit['title'] ?? $hit['name'] ?? '')), 'UTF-8');
        $hitCode = mb_strtolower(trim((string) ($hit['code'] ?? $hit['sku'] ?? '')), 'UTF-8');
        if ($title === '') {
            return 0;
        }

        $score = 40;

        if ($code !== '' && (str_contains($title, mb_strtolower($code, 'UTF-8')) || str_contains($hitCode, mb_strtolower($code, 'UTF-8')))) {
            $score += 45;
        }

        $brand = mb_strtolower($specs['brand'], 'UTF-8');
        if ($brand !== '') {
            if (str_contains($title, $brand)) {
                $score += 22;
            } else {
                $score -= 35;
            }
        }

        $viscosity = mb_strtolower($specs['viscosity'], 'UTF-8');
        if ($viscosity !== '') {
            $visNorm = preg_replace('/\s+/', '', $viscosity) ?? $viscosity;
            $titleNorm = preg_replace('/\s+/', '', $title) ?? $title;
            if (str_contains($titleNorm, $visNorm)) {
                $score += 18;
            }
        }

        $volumeL = $specs['volume_l'];
        if ($volumeL !== null && $volumeL > 0) {
            $volInt = (int) round($volumeL);
            if (preg_match('/\b' . preg_quote((string) $volInt, '/') . '\s*l\b/iu', $title) === 1) {
                $score += 16;
            } elseif (preg_match('/\b' . preg_quote((string) $volInt, '/') . 'l\b/iu', $title) === 1) {
                $score += 14;
            } else {
                $score -= 8;
            }
        }

        $clean = mb_strtolower($specs['clean_name'], 'UTF-8');
        if ($clean !== '') {
            $tokens = array_values(array_filter(preg_split('/\s+/u', $clean) ?: [], static fn ($t) => mb_strlen((string) $t, 'UTF-8') >= 4));
            $matched = 0;
            foreach ($tokens as $token) {
                if (str_contains($title, mb_strtolower((string) $token, 'UTF-8'))) {
                    ++$matched;
                }
            }
            if ($matched >= 2) {
                $score += min(15, $matched * 4);
            }
        }

        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
            if (function_exists('besoiu_detect_fluid_family') && function_exists('besoiu_fluid_family_conflict')) {
                $want = $specs['fluid_family'] ?? besoiu_detect_fluid_family($specs['clean_name'] . ' ' . (string) ($product['pSubcategory'] ?? ''));
                $hitFamily = besoiu_detect_fluid_family($title);
                if (besoiu_fluid_family_conflict($want, $hitFamily)) {
                    return 0;
                }
                if ($want !== null && $hitFamily === $want) {
                    $score += 12;
                }
            }
        }

        if (str_contains($title, 'transmis') && str_contains(mb_strtolower($specs['clean_name'], 'UTF-8'), 'transmis')) {
            $score += 8;
        }

        return max(0, min(100, $score));
    }

    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    public static function pickBestHit(array $product, array $items, int $minScore = 58): ?array
    {
        $best = null;
        $bestScore = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $image = trim((string) ($item['image'] ?? $item['image_url'] ?? ''));
            if ($image === '') {
                continue;
            }
            $score = self::scoreHit($product, $item);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
                $best['match_score'] = $score;
            }
        }

        if (!is_array($best) || $bestScore < $minScore) {
            return null;
        }

        return $best;
    }
}
