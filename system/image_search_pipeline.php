<?php

declare(strict_types=1);

/**
 * Pipeline surse imagine — citește config/image-search-sources.php + .env
 */

/** @return array<string, mixed> */
function besoiu_image_search_config(): array
{
    static $cache = null;
    static $cacheMtime = null;

    $path = dirname(__DIR__) . '/config/image-search-sources.php';
    $mtime = is_file($path) ? (int) filemtime($path) : 0;
    if (is_array($cache) && $cacheMtime === $mtime) {
        return $cache;
    }

    $cache = is_file($path) ? (require $path) : ['sources' => [], 'audit' => []];
    $cacheMtime = $mtime;

    return is_array($cache) ? $cache : ['sources' => [], 'audit' => []];
}

/** @return array<int, string> */
function besoiu_image_search_env_order(): array
{
    $raw = trim((string) ($_ENV['IMAGE_SEARCH_SOURCES'] ?? getenv('IMAGE_SEARCH_SOURCES') ?: ''));
    if ($raw === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

/**
 * @param array<int, string>|null $productCategories ex. ['ulei','lichide']
 * @return array<int, array{id: string, label: string, priority: int, roles: array<int, string>}>
 */
function besoiu_image_search_sources_ordered(?array $productCategories = null): array
{
    $overlayPath = dirname(__DIR__) . '/storage/scraper/image_pipeline_overlay.json';
    if (is_file($overlayPath)) {
        $overlay = json_decode((string) file_get_contents($overlayPath), true);
        if (is_array($overlay['image_plans'] ?? null) && $overlay['image_plans'] !== []) {
            $rows = [];
            foreach ($overlay['image_plans'] as $plan) {
                if (!is_array($plan) || empty($plan['enabled'])) {
                    continue;
                }
                $id = trim((string) ($plan['source_id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $rows[] = [
                    'id' => $id,
                    'label' => (string) ($plan['label'] ?? $id),
                    'priority' => (int) ($plan['tier'] ?? 999),
                    'roles' => is_array($plan['roles'] ?? null) ? $plan['roles'] : ['image'],
                    'meta' => ['enabled' => true],
                ];
            }
            if ($rows !== []) {
                usort($rows, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

                return $rows;
            }
        }
    }

    $cfg = besoiu_image_search_config();
    $sources = is_array($cfg['sources'] ?? null) ? $cfg['sources'] : [];
    $envOrder = besoiu_image_search_env_order();
    $cats = $productCategories ?? [];

    $rows = [];
    foreach ($sources as $id => $meta) {
        if (!is_array($meta) || empty($meta['enabled'])) {
            continue;
        }
        if (!besoiu_image_search_source_env_ok($meta)) {
            continue;
        }
        $allowed = $meta['categories'] ?? ['*'];
        if (is_array($allowed) && $allowed !== ['*'] && $cats !== []) {
            if (array_intersect($cats, $allowed) === [] && !in_array('*', $allowed, true)) {
                continue;
            }
        }

        $rows[] = [
            'id' => (string) $id,
            'label' => (string) ($meta['label'] ?? $id),
            'priority' => (int) ($meta['priority'] ?? 999),
            'roles' => is_array($meta['roles'] ?? null) ? $meta['roles'] : [],
            'meta' => $meta,
        ];
    }

    if ($envOrder !== []) {
        usort($rows, static function (array $a, array $b) use ($envOrder): int {
            $pa = array_search($a['id'], $envOrder, true);
            $pb = array_search($b['id'], $envOrder, true);
            $pa = $pa === false ? 999 : (int) $pa;
            $pb = $pb === false ? 999 : (int) $pb;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return $a['priority'] <=> $b['priority'];
        });
    } else {
        usort($rows, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
    }

    return $rows;
}

/** @param array<string, mixed> $meta */
function besoiu_image_search_source_env_ok(array $meta): bool
{
    $required = $meta['env_required'] ?? [];
    if (!is_array($required) || $required === []) {
        return true;
    }
    foreach ($required as $key) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }
        $val = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));
        if ($val === '') {
            return false;
        }
    }

    return true;
}

function besoiu_image_search_source_enabled(string $sourceId): bool
{
    $syncPath = dirname(__DIR__) . '/lib/Scraper/ScraperImageSourcesSync.php';
    if (is_file($syncPath)) {
        require_once $syncPath;

        return ScraperImageSourcesSync::isSourceActive($sourceId);
    }

    foreach (besoiu_image_search_sources_ordered() as $row) {
        if ($row['id'] === $sourceId) {
            return true;
        }
    }

    return false;
}

/**
 * Coduri OEM / articol din produs (pCode, pOem, extras din titlu).
 *
 * @param array<string, mixed> $product
 * @return list<string>
 */
function besoiu_image_extract_oem_codes(array $product): array
{
    $codes = [];
    $push = static function (string $raw) use (&$codes): void {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) < 3) {
            return;
        }
        $codes[] = $raw;
    };

    $push((string) ($product['pCode'] ?? ''));

    $oemField = (string) ($product['pOem'] ?? '');
    foreach (preg_split('/[,;|]+/', $oemField) ?: [] as $part) {
        $push(trim($part));
    }

    $name = trim((string) ($product['pName'] ?? ''));
    if ($name !== '') {
        if (preg_match('/\b(\d{5,12})\s*$/', $name, $m)) {
            $push($m[1]);
        }
        if (preg_match_all('/\b([A-Z]{0,4}\d[A-Z0-9\-]{3,14})\b/i', $name, $matches)) {
            foreach ($matches[1] as $m) {
                $push((string) $m);
            }
        }
    }

    $unique = [];
    foreach ($codes as $c) {
        $key = strtoupper(preg_replace('/\s+/', '', $c) ?? $c);
        if ($key !== '' && !isset($unique[$key])) {
            $unique[$key] = $c;
        }
    }

    return array_values($unique);
}

/**
 * Mapare OEM numeric → număr articol IAM (ex. 5034724 + TRW → JTE280) via TecDoc cross.
 *
 * @param array<string, mixed> $product
 * @return list<string>
 */
function besoiu_image_lookup_iam_article_codes(array $product, int $maxCodes = 4): array
{
    $tecdocPath = dirname(__DIR__) . '/system/tecdoc_stock.php';
    if (!is_file($tecdocPath)) {
        return [];
    }
    require_once $tecdocPath;

    if (function_exists('tecdoc_api_is_unavailable') && tecdoc_api_is_unavailable()) {
        return [];
    }

    $brand = trim((string) ($product['pBrand'] ?? ''));
    if ($brand === '' && function_exists('besoiu_image_detect_brand_from_text')) {
        $brand = besoiu_image_detect_brand_from_text((string) ($product['pName'] ?? ''));
    }

    $found = [];
    foreach (besoiu_image_extract_oem_codes($product) as $oem) {
        $oem = trim($oem);
        if ($oem === '') {
            continue;
        }
        // Deja cod IAM alfanumeric — nu mai căutăm cross
        if (preg_match('/^[A-Z]{1,4}\d[A-Z0-9\-]{2,}$/i', $oem)) {
            $found[strtoupper($oem)] = $oem;
            continue;
        }
        $digits = preg_replace('/\D+/', '', $oem) ?? '';
        if ($digits === '' || strlen($digits) < 5) {
            continue;
        }

        $searchQuery = $brand !== '' ? ($brand . ' ' . $oem) : $oem;
        if (!function_exists('tecdoc_search_candidates')) {
            break;
        }

        foreach (tecdoc_search_candidates($searchQuery, 10) as $article) {
            if (!is_array($article)) {
                continue;
            }
            if ($brand !== '' && function_exists('tecdoc_brand_matches')) {
                $artBrand = function_exists('tecdoc_article_brand') ? tecdoc_article_brand($article) : '';
                if ($artBrand !== '' && !tecdoc_brand_matches($brand, $artBrand)) {
                    continue;
                }
            }
            $iam = function_exists('tecdoc_article_number') ? trim(tecdoc_article_number($article)) : '';
            if ($iam === '' || strlen($iam) < 3) {
                continue;
            }
            $found[strtoupper($iam)] = $iam;
            if (count($found) >= $maxCodes) {
                return array_values($found);
            }
        }
    }

    return array_values($found);
}

/** @param array<string, mixed> $product @return list<string> */
function besoiu_image_iam_codes_from_product(array $product): array
{
    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (is_array($raw['iam_article_codes'] ?? null) && $raw['iam_article_codes'] !== []) {
        return array_values(array_filter(array_map('strval', $raw['iam_article_codes'])));
    }

    return besoiu_image_lookup_iam_article_codes($product);
}

/**
 * Interogări: implicit titlu → OEM; pentru Autodoc OEM întâi (77-0018 înainte de titlu vag).
 *
 * @param array<string, mixed> $product
 * @return list<string>
 */
function besoiu_image_search_queries_for_product(array $product, ?string $sourceId = null): array
{
    $brand = trim((string) ($product['pBrand'] ?? ''));
    $name = trim((string) ($product['pName'] ?? ''));
    $marca = trim((string) ($product['pMarca'] ?? ''));
    $model = trim((string) ($product['pModel'] ?? ''));
    $titleQueries = [];
    $oemQueries = [];
    $iamQueries = [];
    $seen = [];

    $add = static function (string $q, bool $isOem, bool $isIam = false) use (&$titleQueries, &$oemQueries, &$iamQueries, &$seen): void {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
        if ($q === '') {
            return;
        }
        $k = strtolower($q);
        if (isset($seen[$k])) {
            return;
        }
        $seen[$k] = true;
        if ($isIam) {
            $iamQueries[] = $q;
        } elseif ($isOem) {
            $oemQueries[] = $q;
        } else {
            $titleQueries[] = $q;
        }
    };

    if ($name !== '') {
        $add($name, false);
    }

    $lowerName = mb_strtolower($name, 'UTF-8');
    $categoryBlob = mb_strtolower(trim(implode(' ', array_filter([
        (string) ($product['pCategory'] ?? ''),
        (string) ($product['pSubcategory'] ?? ''),
    ]))), 'UTF-8');
    $contextBlob = trim($lowerName . ' ' . $categoryBlob);

    $catWords = [];
    if (str_contains($contextBlob, 'filtru') || str_contains($contextBlob, 'filter')) {
        $catWords[] = 'filtru';
    }
    if (str_contains($contextBlob, 'ulei') || str_contains($contextBlob, 'oil')) {
        $catWords[] = 'ulei';
    }
    if (str_contains($contextBlob, 'frana') || str_contains($contextBlob, 'frână') || str_contains($contextBlob, 'brake')) {
        $catWords[] = 'frana';
    }
    if (str_contains($contextBlob, 'disc')) {
        $catWords[] = 'disc frana';
    }
    if (str_contains($contextBlob, 'cap bara') || str_contains($contextBlob, 'cap de bara')) {
        $catWords[] = 'cap bara';
    }
    if (str_contains($contextBlob, 'bieleta') || str_contains($contextBlob, 'bieletă')) {
        $catWords[] = 'bieleta';
    }
    if (str_contains($contextBlob, 'amortizor') || str_contains($contextBlob, 'suspensie')) {
        $catWords[] = 'amortizor';
    }

    $detectedBrand = besoiu_image_detect_brand_from_text($name);
    if ($detectedBrand === '' && $brand !== '') {
        $detectedBrand = $brand;
    }

    foreach (besoiu_image_iam_codes_from_product($product) as $iam) {
        if ($detectedBrand !== '') {
            $add(trim($detectedBrand . ' ' . $iam), true, true);
        }
        $add($iam, true, true);
        foreach ($catWords as $catWord) {
            if ($detectedBrand !== '') {
                $add(trim($detectedBrand . ' ' . $catWord . ' ' . $iam), true, true);
            }
        }
    }

    foreach (besoiu_image_extract_oem_codes($product) as $oem) {
        foreach ($catWords as $catWord) {
            if ($detectedBrand !== '') {
                $add(trim($detectedBrand . ' ' . $catWord . ' ' . $oem), true);
                $add(trim($catWord . ' ' . $detectedBrand . ' ' . $oem), true);
            }
            $add(trim($catWord . ' ' . $oem), true);
        }
    }

    if ($catWords !== [] && $name !== '') {
        foreach ($catWords as $catWord) {
            if ($detectedBrand !== '') {
                $add(trim($detectedBrand . ' ' . $catWord), false);
            }
            $add(trim($catWord . ' ' . $name), false);
        }
    }

    $autodocPath = dirname(__DIR__) . '/lib/Scraper/AutodocImageParser.php';
    $hasAutodoc = is_file($autodocPath);
    if ($hasAutodoc) {
        require_once $autodocPath;
    }

    foreach (besoiu_image_extract_oem_codes($product) as $oem) {
        if ($hasAutodoc) {
            foreach (AutodocImageParser::oemCodeVariants($oem) as $variant) {
                $add($variant, true);
                if ($brand !== '') {
                    $add($brand . ' ' . $variant, true);
                }
            }
        } else {
            $add($oem, true);
            if ($brand !== '') {
                $add($brand . ' ' . $oem, true);
            }
        }
    }

    if ($marca !== '' || $model !== '') {
        $code = trim((string) ($product['pCode'] ?? ''));
        if ($code !== '') {
            $add(trim($brand . ' ' . $code . ' ' . $marca . ' ' . $model), true);
        }
    }

    if (strtolower((string) $sourceId) === 'autodoc') {
        // IAM (JTE280) → OEM → titlu — evită 10k rezultate pe titlu vag
        return array_merge($iamQueries, $oemQueries, $titleQueries);
    }

    return array_merge($iamQueries, $titleQueries, $oemQueries);
}

/**
 * @param array<string, mixed> $product
 * @return array{url: string, source: string, query: string, api_error?: string}|null
 */
function besoiu_image_search_try_epiesa(array $product): ?array
{
    if (!besoiu_image_search_source_enabled('epiesa')) {
        return null;
    }

    $epiesaPath = dirname(__DIR__) . '/lib/Scraper/EpiesaSearch.php';
    if (!is_file($epiesaPath)) {
        return null;
    }

    require_once $epiesaPath;

    $queries = besoiu_image_search_queries_for_product($product);
    if ($queries === []) {
        return null;
    }

    $last = null;
    foreach ($queries as $query) {
        $match = EpiesaSearch::findFirst($query);
        if (!is_array($match)) {
            $last = ['url' => '', 'source' => 'epiesa', 'query' => $query, 'api_error' => 'ePiesa: fără potrivire pentru «' . $query . '»'];
            continue;
        }

        $image = trim((string) ($match['image'] ?? $match['image_url'] ?? ''));
        if ($image === '') {
            $last = ['url' => '', 'source' => 'epiesa', 'query' => $query, 'api_error' => 'ePiesa: produs fără imagine'];
            continue;
        }

        return [
            'url' => $image,
            'source' => 'epiesa_search',
            'query' => $query,
            'epiesa_title' => (string) ($match['title'] ?? ''),
            'epiesa_url' => (string) ($match['url'] ?? $match['product_url'] ?? ''),
        ];
    }

    return $last;
}

/** @return array<string, mixed> */
function besoiu_image_audit_config(): array
{
    $cfg = besoiu_image_search_config();
    $audit = is_array($cfg['audit'] ?? null) ? $cfg['audit'] : [];

    $raw = strtolower(trim((string) ($_ENV['IMAGE_AUDIT_ON_IMPORT'] ?? getenv('IMAGE_AUDIT_ON_IMPORT') ?: '')));
    if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
        $audit['on_import_review'] = true;
    }

    return $audit;
}

/**
 * Atașează verdict audit existent în raw_json (dacă există fișier by_product).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function besoiu_image_attach_audit_meta(array $row): array
{
    $publicId = trim((string) ($row['randomn_id'] ?? ''));
    if ($publicId === '') {
        return $row;
    }

    $auditPath = dirname(__DIR__) . '/admin/storage/image_audit/by_product/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $publicId) . '.json';
    if (!is_file($auditPath)) {
        return $row;
    }

    $audit = json_decode((string) file_get_contents($auditPath), true);
    if (!is_array($audit)) {
        return $row;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $raw['image_audit'] = [
        'verdict' => (string) ($audit['verdict'] ?? ''),
        'match_score' => (int) ($audit['match_score'] ?? 0),
        'recommendation' => (string) ($audit['recommendation'] ?? ''),
        'summary_ro' => (string) ($audit['summary_ro'] ?? ''),
        'analyzed_at' => (string) ($audit['analyzed_at'] ?? ''),
    ];
    $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $cfg = function_exists('besoiu_scraper_image_ai_rules')
        ? besoiu_scraper_image_ai_rules()
        : besoiu_image_audit_config();
    $minKeep = (int) ($cfg['min_score_keep'] ?? 70);
    $retryVerdicts = is_array($cfg['verdicts_retry'] ?? null) ? $cfg['verdicts_retry'] : ['mismatch'];
    $verdict = strtolower((string) ($audit['verdict'] ?? ''));
    $score = (int) ($audit['match_score'] ?? 0);
    $reco = strtolower((string) ($audit['recommendation'] ?? ''));

    if (!empty($cfg['auto_retry_on_mismatch'])
        && ($reco === 'replace' || in_array($verdict, $retryVerdicts, true) || $score < $minKeep)) {
        $row['__force_image_update'] = true;
        $row['__image_audit_retry'] = true;
    }

    return $row;
}

/**
 * Rezolvă imagine folosind planurile din scraper (Plan 1 → 2 → 3 …).
 *
 * @param array<string, mixed> $product
 * @param callable(string,string):void|null $log
 * @return array{product: array<string, mixed>, hit: array<string, mixed>|null, tried: list<array<string, mixed>>}
 */
function besoiu_image_search_resolve_product(array $product, ?callable $log = null): array
{
    $servicePath = dirname(__DIR__) . '/lib/Scraper/ImageSearchService.php';
    if (!is_file($servicePath)) {
        return ['product' => $product, 'hit' => null, 'tried' => []];
    }

    require_once $servicePath;
    \ImageSearchService::boot();

    return \ImageSearchService::resolve($product, ['log' => $log]);
}

/** @deprecated Folosește besoiu_image_search_resolve_product — alias pentru cod vechi. */
function besoiu_scraper_image_pipeline_resolve(array $product, ?callable $log = null): array
{
    return besoiu_image_search_resolve_product($product, $log);
}

/** @return array<string, mixed> */
function besoiu_scraper_image_ai_rules(): array
{
    $storePath = dirname(__DIR__) . '/lib/Scraper/ScraperIntegrationStore.php';
    if (!is_file($storePath)) {
        return besoiu_image_audit_config();
    }
    require_once $storePath;
    require_once dirname(__DIR__) . '/lib/Scraper/ScraperIntegrationSchema.php';

    $global = ScraperIntegrationStore::imageAiConfig();
    $audit = besoiu_image_audit_config();

    return array_replace_recursive($audit, [
        'enabled' => !empty($global['enabled']),
        'accept_white_background' => !empty($global['accept_white_background']),
        'accept_product_match' => !empty($global['accept_product_match']),
        'reject_placeholder' => !empty($global['reject_placeholder']),
        'reject_wrong_category' => !empty($global['reject_wrong_category']),
        'prompt_extra' => ScraperIntegrationSchema::buildImageAiPromptExtra($global, null),
        'min_score_keep' => (int) ($global['min_score_keep'] ?? ($audit['min_score_keep'] ?? 70)),
        'on_import_review' => !empty($global['on_import_review']) || !empty($audit['on_import_review']),
        'on_import_cron' => !empty($global['on_import_cron']) || !empty($audit['on_import_cron']),
        'auto_retry_on_mismatch' => !empty($global['auto_retry_on_mismatch']),
        'verdicts_retry' => is_array($global['verdicts_retry'] ?? null) ? $global['verdicts_retry'] : ($audit['verdicts_retry'] ?? []),
    ]);
}

/** @return list<string> */
function besoiu_image_known_brands(): array
{
    return [
        'TRW', 'MANN-FILTER', 'MANN', 'BOSCH', 'MEYLE', 'NK', 'SACHS', 'VALEO', 'GATES', 'RIDEX',
        'FEBI', 'BLUE PRINT', 'SKF', 'FAG', 'INA', 'LEMFÖRDER', 'LEMFORDER', 'CONTINENTAL', 'ATE',
        'BREMBO', 'MAHLE', 'KNECHT', 'PURFLUX', 'HENGST', 'FILTRON', 'WIX', 'CHAMPION', 'NGK',
    ];
}

function besoiu_image_detect_brand_from_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $upper = strtoupper($text);
    foreach (besoiu_image_known_brands() as $brand) {
        if (str_contains($upper, strtoupper($brand))) {
            return $brand;
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $product
 * @return array<string, mixed>
 */
function besoiu_image_enrich_product_context(array $product): array
{
    $name = trim((string) ($product['pName'] ?? ''));
    if (trim((string) ($product['pBrand'] ?? '')) === '') {
        $detected = besoiu_image_detect_brand_from_text($name);
        if ($detected !== '') {
            $product['pBrand'] = $detected;
        }
    }

    $lower = mb_strtolower($name, 'UTF-8');
    if (trim((string) ($product['pCategory'] ?? '')) === '') {
        if (str_contains($lower, 'filtru') || str_contains($lower, 'filter')) {
            $product['pCategory'] = 'Filtre';
        } elseif (str_contains($lower, 'lichid') || str_contains($lower, 'ulei') || str_contains($lower, 'antigel')) {
            $product['pCategory'] = 'Lichide auto';
        } elseif (str_contains($lower, 'frana') || str_contains($lower, 'frână') || str_contains($lower, 'disc')) {
            $product['pCategory'] = 'Frâne';
        } elseif (str_contains($lower, 'cap bara') || str_contains($lower, 'cap de bara') || str_contains($lower, 'bieleta') || str_contains($lower, 'rulment')) {
            $product['pCategory'] = 'Suspensie';
            if (trim((string) ($product['pSubcategory'] ?? '')) === '') {
                $product['pSubcategory'] = str_contains($lower, 'cap') ? 'Cap de bara' : 'Suspensie';
            }
        }
    }

    if (trim((string) ($product['pSubcategory'] ?? '')) === '' && str_contains($lower, 'filtru')) {
        if (str_contains($lower, 'ulei') || str_contains($lower, 'oil')) {
            $product['pSubcategory'] = 'Filtru ulei';
        } elseif (str_contains($lower, 'aer') || str_contains($lower, 'air')) {
            $product['pSubcategory'] = 'Filtru aer';
        } elseif (str_contains($lower, 'polen') || str_contains($lower, 'habitaclu')) {
            $product['pSubcategory'] = 'Filtru habitaclu';
        } elseif (str_contains($lower, 'combustibil') || str_contains($lower, 'motorina') || str_contains($lower, 'benzin')) {
            $product['pSubcategory'] = 'Filtru combustibil';
        }
    }

    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    if (empty($raw['iam_article_codes']) && empty($product['__skip_iam_lookup'])) {
        $iam = besoiu_image_lookup_iam_article_codes($product);
        if ($iam !== []) {
            $raw['iam_article_codes'] = $iam;
            $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    return $product;
}

/** Descarcă URL remote în /uploads/products/ dacă e posibil. */
function besoiu_image_store_lookup_url_locally(string $remoteUrl, string $code = ''): string
{
    $remoteUrl = trim($remoteUrl);
    if ($remoteUrl === '') {
        return '';
    }
    if (str_starts_with($remoteUrl, '/uploads/')) {
        return $remoteUrl;
    }
    if (!preg_match('#^https?://#i', $remoteUrl)) {
        return $remoteUrl;
    }

    $tecdocPath = dirname(__DIR__) . '/system/tecdoc_stock.php';
    if (!is_file($tecdocPath)) {
        return $remoteUrl;
    }
    require_once $tecdocPath;
    if (!function_exists('tecdoc_download_image')) {
        return $remoteUrl;
    }

    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $code !== '' ? $code : 'img') ?? 'img';
    $local = tecdoc_download_image($remoteUrl, $slug);

    return $local !== '' ? $local : $remoteUrl;
}

/** @param list<array<string, mixed>> $tried */
function besoiu_image_pipeline_tried_source(array $tried, string $sourceId): bool
{
    foreach ($tried as $step) {
        if (!is_array($step)) {
            continue;
        }
        if (strtolower((string) ($step['source_id'] ?? '')) === strtolower($sourceId)) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function besoiu_image_pipeline_env_keys_present(): array
{
    $keys = ['SCRAPE_DO_TOKEN', 'RAPIDAPI_AUTOPARTS_KEY', 'OPENAI_KEY'];
    $present = [];
    foreach ($keys as $key) {
        $val = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));
        if ($val !== '') {
            $present[] = $key;
        }
    }

    return $present;
}
