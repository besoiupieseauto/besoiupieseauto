<?php

declare(strict_types=1);

require_once __DIR__ . '/ConsumableFluidMatch.php';

/**
 * Potrivire imagini piese catalog (pivot, rulment, frână…) — nu fluide/consumabile.
 * ePiesa/Autodoc returnează adesea echivalente cross-brand la căutare după cod OEM;
 * nu aplicăm penalizarea -35 brand din ConsumableFluidMatch.
 */
final class CatalogPartImageMatch
{
    /** Piese răcire (nu fluide) — denumire conține „lichid de racire” dar e vas/rezervor. */
    public static function isCoolingSystemHardware(string $blob): bool
    {
        $blob = mb_strtolower(trim($blob), 'UTF-8');

        return preg_match(
            '/\b(vas(\s+de)?\s+expansiune|rezervor(\s+de)?\s+racire|expansion\s+tank|'
            . 'termostat|senzor(\s+de)?\s+temperatur|garnitura(\s+vas)?\s+expansiune|'
            . 'cap(\s+de)?\s+vas|flansa(\s+vas)?\s+expansiune)\b/iu',
            $blob
        ) === 1;
    }

    /**
     * Piesă hardware (nu produsul fluid în sine) — denumirea conține „ulei"/„lichid"/„antigel"
     * doar ca atribut (radiator ULEI, pompă ULEI, filtru ULEI), nu ca substantiv principal.
     * Fără asta, orice piesă legată de circuitul de ulei/lichid era clasificată greșit
     * drept consumabil și trimisă pe pipeline-ul restricționat (fără ePiesa/Autodoc).
     */
    public static function isFluidRelatedHardware(string $blob): bool
    {
        $blob = mb_strtolower(trim($blob), 'UTF-8');

        return preg_match(
            '/\b(radiator|racitor|schimbator(\s+de)?\s+caldura|pompa|pompă|filtru|senzor|'
            . 'presostat|furtun|conduct[a\x{103}]|racord|garnitura|garnitur[a\x{103}]|'
            . 'capac|baie|carter|joja|proba|sonda|comutator|intrerupator|întrerupător|'
            . 'radiator[- ]racitor|termocontact)'
            . '(\s+(de\s+)?\w+){0,2}?\s+(de\s+)?(ulei|lichid|antigel)\b/iu',
            $blob
        ) === 1;
    }

    /** @param array<string, mixed> $product */
    public static function isCatalogPart(array $product): bool
    {
        $specs = ConsumableFluidMatch::extractSpecs($product);

        $blob = mb_strtolower(trim($specs['clean_name'] . ' '
            . (string) ($product['pCategory'] ?? '') . ' '
            . (string) ($product['pSubcategory'] ?? '')), 'UTF-8');

        $subcategory = mb_strtolower(trim((string) ($product['pSubcategory'] ?? '')), 'UTF-8');
        foreach ([
            'starter', 'electromotor', 'alternator', 'compresor', 'turbo', 'ambreiaj',
            'radiator', 'pompa', 'filtru', 'far', 'stop', 'amortizor', 'arc', 'bieleta',
        ] as $hardware) {
            if ($subcategory !== '' && str_contains($subcategory, $hardware)) {
                return true;
            }
        }

        if (self::isCoolingSystemHardware($blob) || self::isFluidRelatedHardware($blob)) {
            return true;
        }

        if ($specs['viscosity'] !== '' || $specs['volume_l'] !== null || $specs['fluid_family'] !== null) {
            return false;
        }

        foreach ([
            'ulei', 'uleiuri', 'lichid', 'antigel', 'coolant', 'atf ', 'transmisie automata',
            'pasta', 'montaj', 'consumabil', 'spray', 'vaselina', 'adeziv', 'curatare', 'degresant',
        ] as $fluid) {
            if (str_contains($blob, $fluid)) {
                return false;
            }
        }

        $category = mb_strtolower(trim((string) ($product['pCategory'] ?? '')), 'UTF-8');
        if (str_contains($category, 'consumabil') || str_contains($category, 'lichid') || str_contains($category, 'ulei')) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $hit
     */
    public static function scoreHit(array $product, array $hit, ?string $searchQuery = null): int
    {
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = mb_strtolower(trim((string) ($product['pBrand'] ?? '')), 'UTF-8');
        $title = mb_strtolower(trim((string) ($hit['title'] ?? $hit['name'] ?? '')), 'UTF-8');
        $hitCode = mb_strtolower(trim((string) ($hit['code'] ?? $hit['sku'] ?? '')), 'UTF-8');

        if ($title === '') {
            return 0;
        }

        $score = 38;
        $codeDigits = preg_replace('/\D+/', '', $code) ?? '';
        $titleBlob = $title . ' ' . $hitCode;
        $titleDigits = preg_replace('/\D+/', '', $titleBlob) ?? '';

        $codeMatched = false;
        if ($code !== '') {
            $codeLower = mb_strtolower($code, 'UTF-8');
            if (str_contains($title, $codeLower) || str_contains($hitCode, $codeLower)) {
                $codeMatched = true;
                $score += 42;
            } elseif ($codeDigits !== '' && strlen($codeDigits) >= 5
                && (str_contains($titleDigits, $codeDigits) || preg_match('/\b' . preg_quote($codeDigits, '/') . '\b/', $titleBlob) === 1)) {
                $codeMatched = true;
                $score += 40;
            }
        }

        if ($searchQuery !== null) {
            $qDigits = preg_replace('/\D+/', '', trim($searchQuery)) ?? '';
            if ($qDigits !== '' && strlen($qDigits) >= 5 && str_contains($titleDigits, $qDigits)) {
                $codeMatched = true;
                $score += 12;
            }
        }

        if ($brand !== '') {
            if (str_contains($title, $brand)) {
                $score += 28;
            } elseif ($codeMatched) {
                // Cod OEM potrivit — alt brand în listă (QWP/NK…) e normal pe ePiesa
                $score += 4;
            } else {
                $score -= 12;
            }
        }

        $category = mb_strtolower(trim((string) ($product['pCategory'] ?? '')), 'UTF-8');
        $subcategory = mb_strtolower(trim((string) ($product['pSubcategory'] ?? '')), 'UTF-8');
        if ($subcategory !== '' && str_contains($title, $subcategory)) {
            $score += 24;
        } elseif ($subcategory !== '') {
            foreach (preg_split('/\s+/u', $subcategory) ?: [] as $token) {
                $token = trim((string) $token);
                if ($token !== '' && mb_strlen($token, 'UTF-8') >= 4 && str_contains($title, $token)) {
                    $score += 14;
                    break;
                }
            }
        }
        if ($category !== '' && str_contains($title, $category)) {
            $score += 16;
        } elseif ($category !== '' && $subcategory === '') {
            foreach (preg_split('/\s+/u', $category) ?: [] as $token) {
                $token = trim((string) $token);
                if ($token !== '' && mb_strlen($token, 'UTF-8') >= 4 && str_contains($title, $token)) {
                    $score += 10;
                    break;
                }
            }
        }

        $clean = mb_strtolower(trim((string) (ConsumableFluidMatch::extractSpecs($product)['clean_name'] ?? '')), 'UTF-8');
        if ($clean !== '') {
            $tokens = array_values(array_filter(
                preg_split('/\s+/u', $clean) ?: [],
                static fn ($t) => mb_strlen((string) $t, 'UTF-8') >= 4
            ));
            $matched = 0;
            foreach ($tokens as $token) {
                if (str_contains($title, mb_strtolower((string) $token, 'UTF-8'))) {
                    ++$matched;
                }
            }
            if ($matched >= 1) {
                $score += min(18, $matched * 8);
            }
        }

        if (!self::hardwareTitleMatchesProduct(
            $product,
            $title,
            (string) ($hit['code'] ?? $hit['sku'] ?? '')
        )) {
            return 0;
        }

        $productBlob = mb_strtolower(trim((string) ($product['pName'] ?? '') . ' '
            . (string) ($product['pCategory'] ?? '') . ' '
            . (string) ($product['pSubcategory'] ?? '')), 'UTF-8');
        if (str_contains($productBlob, 'baterie') && !str_contains($productBlob, 'pila')) {
            foreach (['cr2032', 'cr2025', 'cr2016', '3v', 'pila litio', 'pila litiu', 'buton', 'button cell'] as $buttonPart) {
                if (str_contains($title, $buttonPart)) {
                    return 0;
                }
            }
            if (str_contains($title, 'baterie') || str_contains($title, 'acumulator')) {
                $score += 12;
            }
        }

        return max(0, min(100, $score));
    }

    /** @param array<string, mixed> $product */
    public static function productCodeMatchesHit(array $product, string $title, string $hitCode = ''): bool
    {
        $code = trim((string) ($product['pCode'] ?? ''));
        if ($code === '') {
            return false;
        }
        $codeLower = mb_strtolower($code, 'UTF-8');
        $titleLower = mb_strtolower(trim($title), 'UTF-8');
        $hitCodeLower = mb_strtolower(trim($hitCode), 'UTF-8');
        $codeDigits = preg_replace('/\D+/', '', $code) ?? '';
        $blobDigits = preg_replace('/\D+/', '', $titleLower . $hitCodeLower) ?? '';

        if (str_contains($titleLower, $codeLower) || ($hitCodeLower !== '' && str_contains($hitCodeLower, $codeLower))) {
            return true;
        }

        return $codeDigits !== '' && strlen($codeDigits) >= 5 && str_contains($blobDigits, $codeDigits);
    }

    /** @param array<string, mixed> $product */
    public static function hardwareTitleMatchesProduct(array $product, string $title, string $hitCode = ''): bool
    {
        $title = mb_strtolower(trim($title), 'UTF-8');
        if ($title === '') {
            return false;
        }

        if (self::productCodeMatchesHit($product, $title, $hitCode)) {
            return true;
        }

        $blob = mb_strtolower(trim((string) ($product['pSubcategory'] ?? '') . ' '
            . (string) ($product['pName'] ?? '') . ' '
            . (string) (ConsumableFluidMatch::extractSpecs($product)['clean_name'] ?? '')), 'UTF-8');

        $rules = [
            'starter' => ['starter', 'electromotor', 'demaror'],
            'electromotor' => ['starter', 'electromotor', 'demaror'],
            'alternator' => ['alternator', 'generator'],
        ];
        foreach ($rules as $needle => $allowed) {
            if (!str_contains($blob, $needle)) {
                continue;
            }
            foreach ($allowed as $token) {
                if (str_contains($title, $token)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    public static function pickBestHit(array $product, array $items, ?string $searchQuery = null, int $minScore = 52): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $image = trim((string) ($item['image'] ?? $item['image_url'] ?? ''));
            if ($image === '' || str_contains(mb_strtolower($image, 'UTF-8'), 'placeholder')
                || str_contains(mb_strtolower($image, 'UTF-8'), 'fara-imagine')) {
                continue;
            }

            $itemScore = self::scoreHit($product, $item, $searchQuery);
            if ($itemScore > $bestScore) {
                $bestScore = $itemScore;
                $best = $item;
                $best['match_score'] = $itemScore;
            }
        }

        if (!is_array($best) || $bestScore < $minScore) {
            return null;
        }

        return $best;
    }
}
