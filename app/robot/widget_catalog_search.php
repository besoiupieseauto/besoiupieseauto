<?php

declare(strict_types=1);

/**
 * Căutare catalog widget — intenții clare: motor / filtru / cod OEM / text liber.
 */

require_once __DIR__ . '/oem_lib.php';

const WIDGET_CATALOG_SEARCH_VERSION = 4;

/** @return list<string> */
function widget_catalog_stop_words(): array
{
    return [
        'buna', 'bună', 'ziua', 'salut', 'hey', 'hello', 'multumesc', 'mulțumesc',
        'ce', 'care', 'produse', 'produs', 'piesa', 'piese', 'aveti', 'aveți', 'ai',
        'stoc', 'pentru', 'despre', 'masina', 'mașina', 'am', 'nevoie', 'auto',
        'de', 'la', 'un', 'o', 'cu', 'si', 'și', 'sau', 'va', 'rog', 'as', 'aș',
        'vrea', 'caut', 'cauta', 'caută', 'spune', 'verifica', 'verifică', 'momentan',
        'lucru', 'magazin', 'online', 'liber', 'libere', 'tot', 'toate', 'avem',
        'dar', 'sunt', 'este', 'e', 'nu', 'corect', 'greșit', 'gresit', 'alea', 'asta', 'aceea',
    ];
}

function widget_catalog_is_browse_query(string $message): bool
{
    return (bool) preg_match(
        '/\b(ce|care|aveti|aveți|produse|lista|listă|arata|arată|ai\s+pentru|ce\s+ai|aveti\s+pentru)\b/ui',
        $message
    );
}

/** @return list<string> */
function widget_catalog_extract_terms(string $message): array
{
    $lower = mb_strtolower(trim($message), 'UTF-8');
    if ($lower === '') {
        return [];
    }

    $terms = [];
    if (preg_match('/\bfiltr/u', $lower)) {
        $terms[] = 'filtru';
    }
    if (preg_match('/\bmotor\b/u', $lower) && !preg_match('/\bmotoras\b/u', $lower)) {
        $terms[] = 'motor';
    }
    foreach (['frana', 'frână', 'suspensie', 'ulei', 'turbo', 'baterie', 'rulment'] as $kw) {
        if (str_contains($lower, $kw) && !in_array($kw === 'frână' ? 'frana' : $kw, $terms, true)) {
            $terms[] = $kw === 'frână' ? 'frana' : $kw;
        }
    }

    $clean = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $lower) ?? $lower;
    foreach (preg_split('/\s+/u', trim($clean)) ?: [] as $part) {
        $part = trim($part);
        if ($part === '' || mb_strlen($part, 'UTF-8') < 3) {
            continue;
        }
        if (in_array($part, widget_catalog_stop_words(), true)) {
            continue;
        }
        if (!in_array($part, $terms, true)) {
            $terms[] = $part;
        }
    }

    return array_values(array_unique($terms));
}

function widget_catalog_is_engine_motor_query(array $terms, string $message): bool
{
    if (!preg_match('/\bmotor\b/ui', $message)) {
        return false;
    }
    if (preg_match('/\b(motoras|motorizare|motor\s+geam|geam|stergator|ștergător)\b/ui', $message)) {
        return false;
    }

    return (bool) preg_match(
        '/\b(pentru\s+motor|piese?\s+(de\s+)?motor|motor\s+auto|la\s+motor|motorul|aveti\s+pentru\s+motor|produse\s+.*\bmotor)\b/ui',
        $message
    ) || (widget_catalog_is_browse_query($message) && preg_match('/\bmotor\b/ui', $message));
}

/** Mesaj cu cerere nouă de produs — nu reafișa lista anterioară (clarify/context). */
function widget_catalog_is_new_search_message(string $message): bool
{
    $lower = mb_strtolower(trim($message), 'UTF-8');
    if ($lower === '') {
        return false;
    }
    if (fb_extract_oem_codes($message) !== []) {
        return true;
    }
    if (preg_match('/\b(ulei|uleiuri|lubrifiant|ulei\s+motor)\b/ui', $lower)) {
        return true;
    }
    if (preg_match('/\b(filtru|filtre|placute|plăcuțe|disc|frana|frână|rulment|ambreiaj|baterie|piston|turbo|amortizor|bujie)\b/ui', $lower)) {
        return true;
    }
    if (preg_match('/\b(piese?\s+(de\s+)?motor|motor\s+auto)\b/ui', $lower)) {
        return true;
    }
    if (preg_match('/\b(aveti|aveți|ai\s+stoc|nevoie|caut|cauta|caută)\b/ui', $lower)
        && preg_match('/\b(ulei|filtru|piesa|piese|produs|motor|rulment|disc)\b/ui', $lower)) {
        return true;
    }

    return (bool) preg_match(
        '/\b(rulment|filtru|disc|placute|ambreiaj|baterie|ulei|piesa|piese|produse|motor|stoc|pret|preț|aveti|aveți|caut|oem|catalog)\b/u',
        $lower
    );
}

function widget_catalog_resolve_intent(string $message): string
{
    $lower = mb_strtolower(trim($message), 'UTF-8');

    if (fb_extract_oem_codes($message) !== []) {
        return 'code';
    }
    if (preg_match('/\b(ulei|uleiuri|lubrifiant)\b/ui', $lower)) {
        if (preg_match('/\bfiltru\b/ui', $lower) && !preg_match('/\bcombustibil\b/ui', $lower)) {
            return 'filter';
        }

        return 'oil';
    }
    if (preg_match('/\b(nu\s+(sunt|e)|gre[sș]it|corect|dar\s+filtr|filtru\s+sunt|alea\s+nu)\b/u', $lower)) {
        return 'filter';
    }
    if (preg_match('/\b(filtru|filtre)\b/ui', $lower) && !widget_catalog_is_engine_motor_query(widget_catalog_extract_terms($message), $message)) {
        return 'filter';
    }
    if (widget_catalog_is_engine_motor_query(widget_catalog_extract_terms($message), $message)) {
        return 'engine';
    }
    if (preg_match('/\b(rulment|suspensie|frana|frână|disc|placute|ambreiaj|baterie|pivot)\b/ui', $lower)) {
        return 'category';
    }

    return 'text';
}

/** @param array<string, mixed> $product */
function widget_catalog_product_haystack(array $product): string
{
    return mb_strtolower(implode(' ', array_filter([
        (string) ($product['name'] ?? ''),
        (string) ($product['code'] ?? ''),
        (string) ($product['oem'] ?? ''),
        (string) ($product['brand'] ?? ''),
        (string) ($product['description'] ?? ''),
        (string) ($product['category'] ?? ''),
        (string) ($product['subcategory'] ?? ''),
        (string) ($product['specs'] ?? ''),
        (string) ($product['car'] ?? ''),
    ])), 'UTF-8');
}

/** @param array<string, mixed> $product */
function widget_catalog_is_suspension_or_wheel_part(array $product): bool
{
    $hay = widget_catalog_product_haystack($product);
    $name = mb_strtolower((string) ($product['name'] ?? ''), 'UTF-8');

    if (preg_match('/\b(rulment\s+roata|rulment\s+roată|pivot|bieleta|bieletă|amortizor|suspensie)\b/u', $hay)) {
        return true;
    }
    if (str_contains($name, 'rulment') && (str_contains($hay, ' roata') || str_contains($hay, ' roată') || str_contains($hay, 'suspensie'))) {
        return true;
    }

    return false;
}

/** @param array<string, mixed> $product */
function widget_catalog_is_fuel_filter(array $product): bool
{
    $hay = widget_catalog_product_haystack($product);

    return str_contains($hay, 'filtru combustibil')
        || str_contains($hay, 'filtru carburant')
        || (str_contains($hay, 'combustibil') && str_contains($hay, 'filtru'));
}

/** @param array<string, mixed> $product */
function widget_catalog_is_engine_part_name(array $product): bool
{
    $name = mb_strtolower((string) ($product['name'] ?? ''), 'UTF-8');
    if ($name === '') {
        return false;
    }
    if (widget_catalog_is_suspension_or_wheel_part($product) || widget_catalog_is_fuel_filter($product)) {
        return false;
    }

    $patterns = [
        '/\bfiltru\s+(ulei|aer|polen|habitaclu|motor)\b/u',
        '/\bulei\s+motor\b/u',
        '/\b(piston|biela|cuzinet|simering|chiulasa|chiulasă|segment)\b/u',
        '/\b(curea|lant)\s+distribut/u',
        '/\bdistribut(u|ie)\b/u',
        '/\b(termostat|turbo|injector|diuza|duza|bujie|bobina|tachet)\b/u',
        '/\b(pompa\s+(apa|apă)|radiator|ventilator)\b/u',
        '/\b(garnitur[aă]|sablon|bloc\s+motor|motor\s+complet|motor\s+ambielat)\b/u',
        '/\bulei\b/u',
    ];

    foreach ($patterns as $pat) {
        if (preg_match($pat, $name)) {
            return true;
        }
    }

    $cat = mb_strtolower((string) ($product['category'] ?? '') . ' ' . ($product['subcategory'] ?? ''), 'UTF-8');
    if (preg_match('/\b(ulei|motor|distribut|chiulasa|chiulasă)\b/u', $cat)) {
        return true;
    }

    return false;
}

/** @param array<string, mixed> $product */
function widget_catalog_is_filter_product_name(array $product, string $message): bool
{
    $name = mb_strtolower((string) ($product['name'] ?? ''), 'UTF-8');
    if (!preg_match('/\bfiltru\b/u', $name)) {
        return false;
    }
    if (widget_catalog_is_suspension_or_wheel_part($product)) {
        return false;
    }

    $wantOil = (bool) preg_match('/\bfiltru\s+ulei\b/ui', $message);
    $wantAir = (bool) preg_match('/\bfiltru\s+aer\b/ui', $message);
    $wantCombustibil = (bool) preg_match('/\b(combustibil|carburant|benzina|motorina|diesel|glont)\b/ui', $message);
    $specificType = $wantOil || $wantAir || $wantCombustibil;

    if (!$specificType && widget_catalog_is_fuel_filter($product)) {
        return true;
    }
    if (!$wantCombustibil && widget_catalog_is_fuel_filter($product)) {
        return false;
    }

    if ($wantOil && !str_contains($name, 'ulei')) {
        return false;
    }
    if ($wantAir && !str_contains($name, 'aer')) {
        return false;
    }

    return true;
}

/** @param array<string, mixed> $product */
function widget_catalog_is_oil_product(array $product): bool
{
    if (widget_catalog_is_fuel_filter($product)) {
        return false;
    }
    $hay = widget_catalog_product_haystack($product);

    return (bool) preg_match(
        '/(ulei\s+motor|\bulei\b|lubrif|motor\s+oil|sintetic|semisintetic)/u',
        $hay
    );
}

/**
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_oil_search(array $products, int $limit = 8): array
{
    $hits = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        if (!widget_catalog_is_oil_product($p)) {
            continue;
        }
        $hits[] = $p;
        if (count($hits) >= $limit) {
            break;
        }
    }

    if ($hits === []) {
        foreach ($products as $p) {
            if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
                continue;
            }
            $hay = widget_catalog_product_haystack($p);
            if ((str_contains($hay, 'lubrifiant') || str_contains($hay, 'ulei')) && !widget_catalog_is_fuel_filter($p)) {
                $hits[] = $p;
            }
            if (count($hits) >= $limit) {
                break;
            }
        }
    }

    return $hits;
}

/**
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_engine_search(array $products, int $limit = 8): array
{
    $hits = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        if (!widget_catalog_is_engine_part_name($p)) {
            continue;
        }
        $hits[] = $p;
        if (count($hits) >= $limit) {
            break;
        }
    }

    return $hits;
}

/**
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_filter_search(string $message, array $products, int $limit = 8): array
{
    $hits = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        if (!widget_catalog_is_filter_product_name($p, $message)) {
            continue;
        }
        $hits[] = $p;
        if (count($hits) >= $limit) {
            break;
        }
    }

    return $hits;
}

/**
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_category_search(string $message, array $products, int $limit = 8): array
{
    $terms = widget_catalog_extract_terms($message);
    $hits = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        $hay = widget_catalog_product_haystack($p);
        $ok = false;
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($hay, $term)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            continue;
        }
        $hits[] = $p;
        if (count($hits) >= $limit) {
            break;
        }
    }

    return $hits;
}

/**
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_text_search(string $message, array $products, int $limit = 8): array
{
    $terms = widget_catalog_extract_terms($message);
    if ($terms === []) {
        return [];
    }

    $scored = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        $hay = widget_catalog_product_haystack($p);
        $name = mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8');
        $score = 0;
        foreach ($terms as $term) {
            if ($term === '' || !str_contains($hay, $term)) {
                continue;
            }
            $score += str_contains($name, $term) ? 12 : 4;
        }
        if ($score <= 0) {
            continue;
        }
        $key = (string) ($p['code'] ?? '') . '|' . (string) ($p['randomn_id'] ?? '');
        $scored[$key] = ['score' => $score, 'product' => $p];
    }

    if ($scored === []) {
        return [];
    }
    uasort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

    return array_slice(array_map(static fn ($r) => $r['product'], array_values($scored)), 0, $limit);
}

/** @return list<array<string, mixed>> */
function widget_catalog_search_message(string $message, array $products, int $limit = 8): array
{
    $message = trim($message);
    if ($message === '' || $products === []) {
        return [];
    }

    $intent = widget_catalog_resolve_intent($message);

    if ($intent === 'code') {
        $hits = [];
        foreach (fb_extract_oem_codes($message) as $code) {
            foreach (fb_search_catalog_products($code, $products, 5) as $row) {
                $hits[] = $row;
            }
        }

        return array_slice($hits, 0, $limit);
    }

    return match ($intent) {
        'engine' => widget_catalog_engine_search($products, $limit),
        'oil' => widget_catalog_oil_search($products, $limit),
        'filter' => widget_catalog_filter_search($message, $products, $limit),
        'category' => widget_catalog_category_search($message, $products, $limit),
        default => widget_catalog_text_search($message, $products, $limit),
    };
}

function widget_catalog_query_label(string $message): string
{
    $intent = widget_catalog_resolve_intent($message);
    if ($intent === 'engine') {
        return 'piese de motor';
    }
    if ($intent === 'oil') {
        return 'ulei motor';
    }
    if ($intent === 'filter') {
        if (preg_match('/\b(nu\s+(sunt|e)|dar\s+filtr|filtru\s+sunt)\b/ui', $message)) {
            return 'filtre auto';
        }
        if (preg_match('/\bfiltru\s+ulei\b/ui', $message)) {
            return 'filtru ulei';
        }
        if (preg_match('/\bfiltru\s+aer\b/ui', $message)) {
            return 'filtru aer';
        }

        return 'filtre auto';
    }
    if ($intent === 'code') {
        $codes = fb_extract_oem_codes($message);

        return $codes !== [] ? implode(', ', array_slice($codes, 0, 2)) : 'cod OEM';
    }

    $terms = widget_catalog_extract_terms($message);
    if ($terms !== []) {
        return implode(', ', array_slice($terms, 0, 3));
    }

    return 'căutare';
}

/**
 * Filtre cu stoc > 0 din catalog (pentru răspunsuri oneste când lipsește tipul cerut).
 *
 * @param list<array<string, mixed>> $products
 * @return list<string>
 */
function widget_catalog_filters_in_stock(array $products): array
{
    $out = [];
    foreach ($products as $p) {
        if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
            continue;
        }
        $name = trim((string) ($p['name'] ?? ''));
        if ($name !== '' && preg_match('/\bfiltru\b/ui', $name)) {
            $out[] = $name;
        }
    }

    return array_values(array_unique($out));
}

/**
 * @param list<array<string, mixed>> $catalog
 */
function widget_catalog_empty_filter_hint(string $message, array $catalog): string
{
    $available = widget_catalog_filters_in_stock($catalog);
    if ($available === []) {
        return ' Momentan nu avem filtre listate online. Spune cod OEM, VIN sau contactează-ne pe WhatsApp.';
    }
    $list = implode('; ', array_slice($available, 0, 3));
    if (preg_match('/\bfiltru\s+ulei\b/ui', $message)) {
        return ' Nu avem filtru ulei listat acum. În stoc avem: ' . $list
            . '. Spune cod OEM, VIN sau mașina pentru compatibilitate.';
    }
    if (preg_match('/\bfiltru\s+aer\b/ui', $message)) {
        return ' Nu avem filtru aer listat acum. Alte filtre în stoc: ' . $list . '.';
    }

    return ' Filtre disponibile acum: ' . $list . '.';
}

/** @param list<array<string, mixed>> $hits */
function widget_catalog_hits_context(array $hits): string
{
    if ($hits === []) {
        return '';
    }

    $lines = ['REZULTATE CĂUTARE LIVE (catalog magazin):'];
    foreach ($hits as $p) {
        $line = '- ' . (string) ($p['name'] ?? 'Produs');
        if (!empty($p['code'])) {
            $line .= ' | Cod: ' . $p['code'];
        }
        if (!empty($p['price'])) {
            $line .= ' | Pret: ' . $p['price'] . ' RON';
        }
        $lines[] = $line;
    }

    return implode("\n", $lines);
}

/** @return array{intent:string,label:string,version:int} */
function widget_catalog_search_meta(string $message): array
{
    return [
        'intent' => widget_catalog_resolve_intent($message),
        'label' => widget_catalog_query_label($message),
        'version' => WIDGET_CATALOG_SEARCH_VERSION,
    ];
}

/**
 * Execută plan structurat (AI / chip / reguli) → produse din catalog.
 *
 * @param array<string, mixed> $plan
 * @param list<array<string, mixed>> $products
 * @return list<array<string, mixed>>
 */
function widget_catalog_search_from_plan(array $plan, array $products, int $limit = 8): array
{
    if ($products === []) {
        return [];
    }

    $action = (string) ($plan['action'] ?? 'search');
    if ($action !== 'search' && $action !== 'clarify') {
        return [];
    }

    $intent = (string) ($plan['intent'] ?? 'general');
    $terms = is_array($plan['search_terms'] ?? null) ? $plan['search_terms'] : [];
    $exclude = is_array($plan['exclude'] ?? null) ? $plan['exclude'] : [];
    $oemCodes = is_array($plan['oem_codes'] ?? null) ? $plan['oem_codes'] : [];

    $hits = [];
    foreach ($oemCodes as $code) {
        foreach (fb_search_catalog_products((string) $code, $products, 5) as $row) {
            $hits[] = $row;
        }
    }
    if ($hits !== []) {
        return widget_catalog_apply_excludes(array_slice($hits, 0, $limit), $exclude);
    }

    $synthetic = implode(' ', $terms);
    if ($synthetic === '' && ($plan['user_meaning'] ?? '') !== '') {
        $synthetic = (string) $plan['user_meaning'];
    }

    if ($intent === 'engine') {
        $hits = widget_catalog_engine_search($products, $limit * 2);
    } elseif ($intent === 'oil') {
        $hits = widget_catalog_oil_search($products, $limit * 2);
        if ($hits === []) {
            $hits = widget_catalog_text_search(
                $synthetic !== '' ? $synthetic : 'ulei lubrifiant',
                $products,
                $limit * 2
            );
        }
    } elseif ($intent === 'filter') {
        $hits = widget_catalog_filter_search($synthetic !== '' ? $synthetic : 'filtru', $products, $limit * 2);
    } elseif ($intent === 'brake') {
        $hits = widget_catalog_category_search('placute frana disc frana', $products, $limit * 2);
    } elseif ($intent === 'suspension') {
        $hits = widget_catalog_category_search('suspensie amortizor rulment roata', $products, $limit * 2);
    } elseif ($intent === 'code' && $terms !== []) {
        foreach ($terms as $code) {
            foreach (fb_search_catalog_products((string) $code, $products, 5) as $row) {
                $hits[] = $row;
            }
        }
    } else {
        $hits = widget_catalog_text_search($synthetic, $products, $limit * 2);
        if ($hits === [] && $terms !== []) {
            foreach ($terms as $term) {
                foreach (widget_catalog_text_search((string) $term, $products, 3) as $row) {
                    $hits[] = $row;
                }
            }
        }
    }

    $hits = widget_catalog_apply_excludes($hits, $exclude);

    if ($intent === 'engine') {
        $engineOnly = [];
        foreach ($hits as $p) {
            if (is_array($p) && widget_catalog_is_engine_part_name($p)) {
                $engineOnly[] = $p;
            }
        }
        if ($engineOnly !== []) {
            $hits = $engineOnly;
        }
    }

    return array_slice($hits, 0, $limit);
}

/**
 * @param list<array<string, mixed>> $products
 * @param list<string> $exclude
 * @return list<array<string, mixed>>
 */
function widget_catalog_apply_excludes(array $products, array $exclude): array
{
    if ($exclude === []) {
        return $products;
    }

    $out = [];
    foreach ($products as $p) {
        if (!is_array($p)) {
            continue;
        }
        $hay = widget_catalog_product_haystack($p);
        $skip = false;
        foreach ($exclude as $ex) {
            $ex = mb_strtolower(trim($ex), 'UTF-8');
            if ($ex !== '' && str_contains($hay, $ex)) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $out[] = $p;
        }
    }

    return $out;
}
