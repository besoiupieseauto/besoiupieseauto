<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Import\ImportHooks;
use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Besoiu\Services\ImportReviewQueueService;

require_once dirname(__DIR__, 4) . '/app/Legacy/import-image-validate.php';
require_once dirname(__DIR__, 4) . '/app/Legacy/import-queue-critical.php';
require_once dirname(__DIR__, 4) . '/app/Legacy/product_dual_title.php';
require_once dirname(__DIR__, 4) . '/app/Backend/src/Controllers/Produse/import_base_lib.php';

$importActionApiUrl = AdminUrl::api('import_action_endpoint.php');
$asyncClientUrl = AdminUrl::asset('js/besoiu-async-client.js');
$refreshImagesButtonLabel = 'Caută imagini (pipeline Scraper)';
$refreshImagesConfirmSingle = 'Caut imagine via pipeline Scraper pentru produsul selectat?';
$refreshImagesConfirmMany = 'Caut imagini via pipeline Scraper pentru cele %d produse?';

$queuePage = ImportHooks::queueService()->loadPage($_GET);
$supplier = $queuePage['supplier'];
$status = $queuePage['status'];
$lane = $queuePage['lane'] ?? 'standard';
$page = $queuePage['page'];
$perPage = $queuePage['per_page'];
$importTotal = $queuePage['total'];
$importTotalPages = $queuePage['total_pages'];
$suppliers = $queuePage['suppliers'];
$rows = $queuePage['rows'];
$importQueueCategories = $queuePage['categories'];
$importQueueSubcategoriesByCategory = $queuePage['subcategories_by_category'];
$filterMarca = (string) ($queuePage['marca'] ?? '');
$filterModel = (string) ($queuePage['model'] ?? '');
$filterMotorizare = (string) ($queuePage['motorizare'] ?? '');
$filterBrand = (string) ($queuePage['brand'] ?? '');
$filterAn = (string) ($queuePage['an'] ?? '');
$filterFacets = is_array($queuePage['filter_facets'] ?? null) ? $queuePage['filter_facets'] : [];
$facetsDeferred = !empty($queuePage['facets_deferred']);
$totalApprox = !empty($queuePage['total_approx']);
$queueHasMore = !empty($queuePage['has_more']);
$laneCounts = is_array($queuePage['lane_counts'] ?? null) ? $queuePage['lane_counts'] : [];
$markupService = new AdaosComercialService();
// Doar ce trebuie pentru popup-ul de adaos — getAll() complet e costisitor pe fiecare GET.
$activeMarkupRules = array_values(array_filter(
    $markupService->getAll(),
    static fn(array $rule): bool => (int) ($rule['is_active'] ?? 0) === 1
));
$commercialVatPercent = $markupService->getCommercialVatPercent();
$globalPriceRoundMode = $markupService->getGlobalPriceRoundMode();
$globalPriceRoundValue = $markupService->getGlobalPriceRoundValue();
$globalPriceRoundLabel = match ($globalPriceRoundMode) {
    'next_integer' => 'La următorul întreg',
    'round_to' => 'Rotunjire la ' . rtrim(rtrim(number_format($globalPriceRoundValue, 2, '.', ''), '0'), '.') . ' lei',
    default => 'Fără rotunjire globală',
};
$offset = ($page - 1) * $perPage;

function irv_queue_query(array $overrides = []): string
{
    global $supplier, $status, $lane, $filterMarca, $filterModel, $filterMotorizare, $filterBrand, $filterAn;

    $params = array_filter([
        'supplier' => $supplier !== '' ? $supplier : null,
        // pending = default (omit); all/imported/deleted/conflict_live rămân în URL
        'status' => ($status !== '' && $status !== 'pending') ? $status : null,
        'lane' => $lane !== 'standard' ? $lane : null,
        'marca' => $filterMarca !== '' ? $filterMarca : null,
        'model' => $filterModel !== '' ? $filterModel : null,
        'motorizare' => $filterMotorizare !== '' ? $filterMotorizare : null,
        'brand' => $filterBrand !== '' ? $filterBrand : null,
        'an' => $filterAn !== '' ? $filterAn : null,
    ], static fn($value): bool => $value !== null && $value !== '');

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return http_build_query($params);
}

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function irv_format_price_lei(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '0 lei';
    }
    $num = (float) str_replace(',', '.', $raw);
    if ($num <= 0) {
        return '0 lei';
    }
    if (abs($num - round($num)) < 0.001) {
        return number_format($num, 0, ',', '.') . ' lei';
    }

    return number_format($num, 2, ',', '.') . ' lei';
}

function irv_format_markup_rule_adjustment(array $rule): string
{
    $value = (float) ($rule['adjustment_value'] ?? 0);
    $type = (string) ($rule['adjustment_type'] ?? 'percentage');
    if ($type === 'fixed') {
        return '+' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' lei';
    }

    return '+' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
}
function first_image($value): string {
    $decoded = json_decode((string)$value, true);
    if (!is_array($decoded)) {
        return '';
    }
    foreach ($decoded as $candidate) {
        $url = trim((string) $candidate);
        if ($url !== '') {
            return $url;
        }
    }
    return '';
}
function review_image_is_trusted(array $row): bool {
    return besoiu_import_row_has_trusted_image($row);
}
function review_image_src(array $row): string {
    if (function_exists('besoiu_import_row_resolve_preview_image_url')) {
        $url = besoiu_import_row_resolve_preview_image_url($row);
        if ($url !== '') {
            return $url;
        }
    }

    $url = besoiu_import_row_image_url($row);
    if ($url !== '') {
        return $url;
    }
    $stored = function_exists('besoiu_import_row_stored_image_url')
        ? besoiu_import_row_stored_image_url($row)
        : first_image((string) ($row['pImages'] ?? '[]'));

    return $stored !== '' ? $stored : '/admin/dist/images/fakers/preview-12.jpg';
}
function review_image_has_display(array $row): bool {
    $src = review_image_src($row);
    return $src !== '' && !besoiu_import_image_is_placeholder($src);
}
function short_text($value, int $limit = 140): string {
    $text = trim((string)$value);
    if ($text === '') return '—';
    return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
}
function review_vehicle_line(array $row): string
{
    $marca = trim((string) ($row['pMarca'] ?? ''));
    $model = trim((string) ($row['pModel'] ?? ''));
    $motor = trim((string) ($row['pMotorizare'] ?? ''));

    // Curăță dump-uri vechi tip „RENAULT, VOLVO, …” din coadă.
    if (str_contains($marca, ',')) {
        $marca = trim((string) explode(',', $marca)[0]);
    }
    if (str_contains($model, ',')) {
        $model = trim((string) explode(',', $model)[0]);
    }
    if (str_contains($motor, "\n")) {
        $motor = trim((string) explode("\n", $motor)[0]);
    }
    if (mb_strlen($motor, 'UTF-8') > 48) {
        $motor = mb_substr($motor, 0, 45, 'UTF-8') . '…';
    }

    $parts = array_filter([$marca, $model, $motor], static fn(string $v): bool => $v !== '');

    return $parts !== [] ? implode(' · ', $parts) : '—';
}
function review_taxonomy_line(array $row): string
{
    $cat = trim((string) ($row['pCategory'] ?? ''));
    $sub = trim((string) ($row['pSubcategory'] ?? ''));
    if ($cat === '' && $sub === '') {
        return '—';
    }
    if ($cat !== '' && $sub !== '' && $cat !== $sub) {
        return $cat . ' › ' . $sub;
    }

    return $cat !== '' ? $cat : $sub;
}

/** @return list<array{label: string, value: string, field?: string}> */
function review_product_list_items(array $row, array $summary = []): array
{
    $items = [];
    $brand = trim((string) ($row['pBrand'] ?? ''));
    if ($brand !== '') {
        $items[] = ['label' => 'Brand', 'value' => $brand, 'field' => 'brand'];
    }

    $vehicle = review_vehicle_line($row);
    if ($vehicle !== '—') {
        $items[] = ['label' => 'Vehicul', 'value' => $vehicle, 'field' => 'model'];
    }

    $oem = trim((string) ($row['pOem'] ?? ''));
    if ($oem !== '') {
        $items[] = ['label' => 'OEM', 'value' => $oem, 'field' => 'oem'];
    }

    $km = trim((string) ($summary['vehicle']['kilometraj_km'] ?? ''));
    if ($km !== '') {
        $items[] = ['label' => 'KM', 'value' => $km];
    }

    $specs = trim((string) ($summary['specs'] ?? ''));
    if ($specs !== '') {
        $items[] = ['label' => 'Specs', 'value' => $specs];
    }

    $compat = trim((string) ($row['pCompatibilitati'] ?? ''));
    if ($compat !== '') {
        $items[] = ['label' => 'Compatibilități', 'value' => $compat];
    }

    $technicalCount = is_array($summary['technical_data'] ?? null) ? count($summary['technical_data']) : 0;
    if ($technicalCount > 0) {
        $items[] = ['label' => 'Date tehnice', 'value' => (string) $technicalCount . ' câmpuri'];
    }

    return $items;
}

/**
 * Titluri pe canalele din /admin/product-formation:
 * website (marcă/model/motor), oem (căutare cod OEM), marketplace (pieseauto).
 *
 * @return array{website:string,oem:string,marketplace:string}
 */
function review_formed_titles(array $row): array
{
    static $cache = [];
    $id = (int) ($row['id'] ?? 0);
    if ($id > 0 && isset($cache[$id])) {
        return $cache[$id];
    }

    $website = '';
    $oem = '';
    $marketplace = '';

    if (class_exists(\Besoiu\Services\ProductCardFormationService::class)) {
        $dual = \Besoiu\Services\ProductCardFormationService::resolveDualTitlesFromRow($row);
        $website = trim((string) ($dual['website'] ?? ''));
        $oem = trim((string) ($dual['oem'] ?? ''));
        $marketplace = trim((string) ($dual['marketplace'] ?? ''));
    }

    if ($website === '') {
        $website = trim((string) ($row['pName'] ?? ''));
    }
    if ($oem === '') {
        $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
        if (is_array($raw) && is_array($raw['product_formation'] ?? null)) {
            $oem = trim((string) ($raw['product_formation']['title_oem'] ?? ''));
        }
    }
    if ($oem === '') {
        $oem = $website;
    }
    if ($marketplace === '') {
        $marketplace = review_marketplace_title_stored($row);
    }
    if ($marketplace === '') {
        $marketplace = $website;
    }

    $out = ['website' => $website, 'oem' => $oem, 'marketplace' => $marketplace];
    if ($id > 0) {
        $cache[$id] = $out;
    }

    return $out;
}

/** Titlu MP stocat (fără recalcul) — fallback. */
function review_marketplace_title_stored(array $row): string
{
    $direct = trim((string) ($row['pNameMarketplace'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return '';
    }
    $formation = $raw['product_formation'] ?? null;
    if (!is_array($formation)) {
        return '';
    }

    return trim((string) ($formation['title_pieseauto'] ?? ''));
}

function review_marketplace_title(array $row): string
{
    return review_formed_titles($row)['marketplace'];
}

function review_website_title(array $row): string
{
    return review_formed_titles($row)['website'];
}

/**
 * Rezumat compatibilități pentru previzualizare Marketplace:
 * „RENAULT (MEGANE III) · VOLVO (S60 I, V70 II)” — fără dump OE.
 */
function review_compat_preview_summary(array $row): string
{
    $compat = trim((string) ($row['pCompatibilitati'] ?? ''));
    if ($compat === '') {
        $summary = import_product_summary($row);
        $compat = trim((string) ($summary['compat_text'] ?? ''));
    }
    if ($compat === '') {
        return '';
    }

    $compat = trim((string) (preg_split('/Coduri\s+OE\b/iu', $compat)[0] ?? $compat));
    $parts = [];
    foreach (preg_split('/\r?\n/u', $compat) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || !str_contains($line, ':')) {
            continue;
        }
        [$brand, $rest] = array_pad(explode(':', $line, 2), 2, '');
        $brand = trim($brand);
        if ($brand === '' || preg_match('/^(CODURI|OEM|OE)$/iu', $brand)) {
            continue;
        }

        $models = [];
        foreach (preg_split('/\s*;\s*/u', (string) $rest) ?: [] as $item) {
            $item = trim((string) preg_replace('/\([^)]*\)/u', '', (string) $item));
            $item = trim((string) preg_replace('/\s+\d{4}\s*[-–]\s*(\d{4}|Prezent).*$/iu', '', $item));
            $item = trim((string) preg_replace('/\s+/u', ' ', $item));
            if ($item === '') {
                continue;
            }
            // Seria model: „MEGANE III”, „S60 I”, „V70 II”.
            if (preg_match('/^([A-Z0-9][A-Za-z0-9\-\/]*(?:\s+[A-Z0-9][A-Za-z0-9\-\/]*){0,2})/u', $item, $m)) {
                $label = trim((string) $m[1]);
                // Oprește la cuvinte tip caroserie/motor lungi.
                $label = trim((string) preg_replace('/\s+(cupe|sedan|hatchback|combi|break|tce|dci|awd)\b.*$/iu', '', $label));
                if ($label !== '') {
                    $models[$label] = true;
                }
            }
            if (count($models) >= 4) {
                break;
            }
        }

        $modelList = array_keys($models);
        $parts[] = $modelList !== []
            ? $brand . ' (' . implode(', ', $modelList) . ')'
            : $brand;
        if (count($parts) >= 8) {
            break;
        }
    }

    return implode(' · ', $parts);
}

function image_meta(array $row): array {
    $raw = json_decode((string)($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) $raw = [];
    $images = json_decode((string)($row['pImages'] ?? '[]'), true);
    $source = (string)($row['pImageSource'] ?? ($raw['__image_source'] ?? (is_array($images) && !empty($images[0]) ? 'csv' : 'missing')));
    $hasStoredImage = function_exists('besoiu_import_row_resolve_preview_image_url')
        ? besoiu_import_row_resolve_preview_image_url($row) !== ''
        : (function_exists('besoiu_import_row_stored_image_url')
            ? besoiu_import_row_stored_image_url($row) !== ''
            : first_image((string)($row['pImages'] ?? '[]')) !== '');
    if (!review_image_is_trusted($row)) {
        if ($hasStoredImage && $source !== 'missing') {
            // Păstrează sursa reală (Poze, Import Pro, TecDoc) pentru badge UI
        } else {
            $source = 'missing';
        }
    } elseif ($source === 'import') {
        $source = 'missing';
    } elseif ($source === 'caietcomenzi') {
        $source = 'CSV TecDoc';
    } elseif ($source === 'emag_search') {
        $source = 'eMAG search';
    }
    $query = (string)($raw['__image_query'] ?? ($row['pCode'] ?? ''));
    $emagSearchUrl = trim((string)($raw['__emag_search_url'] ?? ''));
    if ($emagSearchUrl !== '' && $source === 'eMAG search') {
        $query = $emagSearchUrl;
    }
    $oemMatch = trim((string)($raw['__oem_matched_code'] ?? ''));
    if ($oemMatch !== '') {
        $oemBrand = trim((string)($raw['__oem_matched_brand'] ?? ''));
        $query = $oemBrand !== '' ? ($oemBrand . ' : ' . $oemMatch) : $oemMatch;
    }
    return ['source' => $source, 'query' => $query];
}

function import_product_summary(array $row): array {
    $raw = json_decode((string)($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return [];
    }

    $summary = $raw['product_summary'] ?? null;
    if (is_array($summary)) {
        return $summary;
    }

    if (isset($raw['raw_json']) && is_string($raw['raw_json'])) {
        $nested = json_decode($raw['raw_json'], true);
        if (is_array($nested) && is_array($nested['product_summary'] ?? null)) {
            return $nested['product_summary'];
        }
    }

    return [];
}

/**
 * Descriere Marketplace = HTML Base.html (Compatibil nested + OE).
 */
function import_resolve_description_marketplace(array $row): string
{
    foreach (['pNoteMarketplace', 'pNote'] as $key) {
        $note = trim((string) ($row[$key] ?? ''));
        if ($note !== '') {
            return $note;
        }
    }

    $summary = import_product_summary($row);
    foreach (['description_marketplace', 'description'] as $key) {
        $fromSummary = trim((string) ($summary[$key] ?? ''));
        if ($fromSummary !== '') {
            return $fromSummary;
        }
    }

    return '';
}

/**
 * Descriere Website = antet + specificații (fără format nested Base marketplace).
 * Taburile Website (Marcă/Model/Motor vs OEM) controlează titlul, nu acest HTML.
 */
function import_resolve_description_website(array $row): string
{
    $web = trim((string) ($row['pNoteWebsite'] ?? ''));
    if ($web !== '') {
        // Dacă greșit a fost salvat HTML-ul Base complet pe website, taie Compatibil/OE.
        if (str_contains($web, 'Compatibil cu urmatoarele modele auto')
            && function_exists('import_base_description_website_from_marketplace')
        ) {
            $trimmed = trim((string) import_base_description_website_from_marketplace($web));
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $web;
    }

    $summary = import_product_summary($row);
    $fromSummary = trim((string) ($summary['description_website'] ?? ''));
    if ($fromSummary !== '') {
        return $fromSummary;
    }

    $mp = import_resolve_description_marketplace($row);
    if ($mp !== '' && function_exists('import_base_description_website_from_marketplace')) {
        return trim((string) import_base_description_website_from_marketplace($mp));
    }

    return $mp;
}

/** @deprecated Folosește import_resolve_description_marketplace / _website. */
function import_resolve_description(array $row): string
{
    return import_resolve_description_marketplace($row);
}

/**
 * Tab-uri descriere Website (Formare carte) — simulare cartă produs detaliat.
 *
 * @return list<array{id:string,label:string,content_html:string}>
 */
function review_website_description_tabs(array $row): array
{
    if (!class_exists(\Besoiu\Services\ProductDescriptionTabsService::class)) {
        return [];
    }

    try {
        $product = [
            'pName' => (string) ($row['pName'] ?? ''),
            'pBrand' => (string) ($row['pBrand'] ?? ''),
            'pCode' => (string) ($row['pCode'] ?? ''),
            'pMarca' => (string) ($row['pMarca'] ?? ''),
            'pModel' => (string) ($row['pModel'] ?? ''),
            'pMotorizare' => (string) ($row['pMotorizare'] ?? ''),
            'pCategory' => (string) ($row['pCategory'] ?? ''),
            'pSubcategory' => (string) ($row['pSubcategory'] ?? ''),
            'pOem' => (string) ($row['pOem'] ?? ''),
            'pCompatibilitati' => (string) ($row['pCompatibilitati'] ?? ''),
            'pNote' => import_resolve_description_marketplace($row),
            'pNoteWebsite' => import_resolve_description_website($row),
            'raw_json' => (string) ($row['raw_json'] ?? '{}'),
        ];
        $descHtml = import_resolve_description_marketplace($row);
        if ($descHtml === '') {
            $descHtml = import_resolve_description_website($row);
        }
        $tabs = (new \Besoiu\Services\ProductDescriptionTabsService())->buildForProduct($product, $descHtml);
        $out = [];
        foreach ($tabs as $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $out[] = [
                'id' => (string) ($tab['id'] ?? ''),
                'label' => (string) ($tab['label'] ?? ''),
                'content_html' => (string) ($tab['content_html'] ?? ''),
            ];
        }

        return $out;
    } catch (\Throwable) {
        return [];
    }
}

/** @return array<int, array{code: string, label: string}> */
function review_critical_flags(array $row): array
{
    return besoiu_import_row_critical_flags($row);
}

function review_row_needs_reprocess(array $row): bool
{
    if ((string) ($row['status'] ?? '') !== 'pending') {
        return false;
    }
    if (besoiu_import_row_critical_flags($row) !== []) {
        return true;
    }
    if (!besoiu_import_row_has_trusted_image($row)) {
        return true;
    }
    if (import_resolve_description($row) === '') {
        return true;
    }

    return false;
}

$queueCriticalBlocked = 0;
$importReviewPlaceholder = '/admin/dist/images/fakers/preview-12.jpg';
if ($status === 'pending' && $rows !== []) {
    foreach ($rows as $queueRow) {
        if (is_array($queueRow) && besoiu_import_row_blocks_auto_publish($queueRow)) {
            ++$queueCriticalBlocked;
        }
    }
}
$statusTabs = [
    'pending' => ['label' => 'De publicat', 'icon' => 'clock'],
    'imported' => ['label' => 'Publicate', 'icon' => 'check-circle'],
    'conflict_live' => ['label' => 'Conflicte', 'icon' => 'alert-triangle'],
    'deleted' => ['label' => 'Șterse', 'icon' => 'trash-2'],
    'all' => ['label' => 'Toate', 'icon' => 'layers'],
];
$laneTabs = [
    'standard' => ['label' => 'Produse normale', 'icon' => 'package'],
    'showcase' => ['label' => 'Produse vitrină', 'icon' => 'sparkles'],
    'no_image' => ['label' => 'Produse fără imagine', 'icon' => 'image-off'],
];
?>
<div class="import-review-page irv-layout">
    <div class="admin-panel irv-shell">
    <nav class="irv-main-tabs" aria-label="Secțiuni import review">
        <a href="/admin/importreview" class="irv-main-tabs__item is-active" aria-current="page">
            <i data-lucide="list" class="irv-main-tabs__icon"></i>
            Coadă import
        </a>
        <a href="/admin/importreview?view=normalize" class="irv-main-tabs__item">
            <i data-lucide="text-cursor-input" class="irv-main-tabs__icon"></i>
            Normalizare denumiri
        </a>
    </nav>
    <header class="irv-hero">
        <div class="irv-hero__main">
            <div class="irv-hero__eyebrow">Import · Staging</div>
            <h1 class="irv-hero__title">Coadă import produse</h1>
            <p class="irv-hero__desc">Produse pregătite pentru publicare. Verifică datele, completează lipsurile și publică în magazin sau exportă către canale externe.</p>
            <div class="irv-hero__stats">
                <div class="irv-stat">
                    <span class="irv-stat__value" data-irv-queue-total><?= h((string) $importTotal) ?><?= !empty($totalApprox) ? '+' : '' ?></span>
                    <span class="irv-stat__label">în filtrul curent<?= !empty($totalApprox) ? ' (se calculează…)' : '' ?></span>
                </div>
                <div class="irv-stat">
                    <span class="irv-stat__value"><?= h((string) count($rows)) ?></span>
                    <span class="irv-stat__label">pe pagina <?= h((string) $page) ?></span>
                </div>
                <?php if ($status === 'pending' && $queueCriticalBlocked > 0): ?>
                <div class="irv-stat irv-stat--warn">
                    <span class="irv-stat__value"><?= h((string) $queueCriticalBlocked) ?></span>
                    <span class="irv-stat__label">cu date critice</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="irv-hero__actions">
            <a href="/admin/import" class="irv-btn irv-btn--primary">
                <i data-lucide="file-up" class="irv-btn__icon"></i>
                Import nou
            </a>
        </div>
    </header>

    <section class="irv-card irv-toolbar">
        <div class="irv-toolbar__row">
            <div class="irv-toolbar__group">
                <span class="irv-toolbar__label">Tip coadă</span>
                <nav class="irv-seg" aria-label="Tip coadă import">
                    <?php foreach ($laneTabs as $laneKey => $laneMeta):
                        $laneQs = irv_queue_query(['lane' => $laneKey]);
                        $laneActive = $lane === $laneKey;
                        $laneCount = (int) ($laneCounts[$laneKey] ?? 0);
                    ?>
                    <a href="?<?= h($laneQs) ?>" class="irv-seg__item<?= $laneActive ? ' is-active' : '' ?>"<?= $laneActive ? ' aria-current="page"' : '' ?>>
                        <i data-lucide="<?= h($laneMeta['icon']) ?>" class="irv-seg__icon"></i>
                        <?= h($laneMeta['label']) ?><?= $laneCount > 0 ? ' (' . h((string) $laneCount) . ')' : '' ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
        <div class="irv-toolbar__row irv-toolbar__row--status">
            <div class="irv-toolbar__group irv-toolbar__group--full">
                <span class="irv-toolbar__label">Status</span>
                <nav class="irv-seg irv-seg--wrap" aria-label="Status coadă import">
                    <?php foreach ($statusTabs as $statusKey => $statusMeta):
                        $statusQs = irv_queue_query([
                            'status' => $statusKey,
                        ]);
                        $statusActive = $status === $statusKey;
                    ?>
                    <a href="?<?= h($statusQs) ?>" class="irv-seg__item irv-seg__item--sm<?= $statusActive ? ' is-active' : '' ?>"<?= $statusActive ? ' aria-current="page"' : '' ?>>
                        <i data-lucide="<?= h($statusMeta['icon']) ?>" class="irv-seg__icon"></i>
                        <?= h($statusMeta['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </section>

    <section class="irv-card irv-filters">
        <div class="irv-card__head">
            <div class="irv-card__title">
                <i data-lucide="sliders-horizontal" class="irv-card__title-icon"></i>
                <span>Filtre</span>
            </div>
            <span class="irv-card__hint">Filtrează coada după furnizor, vehicul, brand sau an. Prețul bază se formează la import (Import Pro); aici poți reaplica doar reguli de adaos dacă e nevoie.</span>
        </div>
        <form class="irv-filters__body" method="get">
            <input type="hidden" name="lane" value="<?= h($lane) ?>">
            <input type="hidden" name="status" value="<?= h($status) ?>">
            <div class="irv-filters__grid irv-filters__grid--wide">
                <div class="irv-field">
                    <label class="irv-field__label" for="irvSupplier">Furnizor</label>
                    <select id="irvSupplier" name="supplier" class="irv-input irv-select">
                        <option value="">Toți furnizorii</option>
                        <?php foreach ($suppliers as $s):
                            $code = (string)($s['supplier_code'] ?? '');
                            $label = (string)($s['supplier_label'] ?? $code);
                            if ($code === '') continue;
                        ?><option value="<?= h($code) ?>" <?= strtoupper($supplier) === $code ? 'selected' : '' ?>><?= h($label) ?> (<?= h($code) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="irv-field">
                    <label class="irv-field__label" for="irvMarca">Marca auto</label>
                    <input id="irvMarca" name="marca" class="irv-input" list="irvMarcaList" value="<?= h($filterMarca) ?>" placeholder="Toate">
                    <datalist id="irvMarcaList">
                        <?php foreach ($filterFacets['marci'] ?? [] as $facetValue): ?>
                            <option value="<?= h((string) $facetValue) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="irv-field">
                    <label class="irv-field__label" for="irvModel">Model</label>
                    <input id="irvModel" name="model" class="irv-input" list="irvModelList" value="<?= h($filterModel) ?>" placeholder="Toate">
                    <datalist id="irvModelList">
                        <?php foreach ($filterFacets['modele'] ?? [] as $facetValue): ?>
                            <option value="<?= h((string) $facetValue) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="irv-field">
                    <label class="irv-field__label" for="irvMotorizare">Motorizare</label>
                    <input id="irvMotorizare" name="motorizare" class="irv-input" list="irvMotorizareList" value="<?= h($filterMotorizare) ?>" placeholder="Toate">
                    <datalist id="irvMotorizareList">
                        <?php foreach ($filterFacets['motorizari'] ?? [] as $facetValue): ?>
                            <option value="<?= h((string) $facetValue) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="irv-field">
                    <label class="irv-field__label" for="irvBrand">Brand piesă</label>
                    <input id="irvBrand" name="brand" class="irv-input" list="irvBrandList" value="<?= h($filterBrand) ?>" placeholder="Toate">
                    <datalist id="irvBrandList">
                        <?php foreach ($filterFacets['brands'] ?? [] as $facetValue): ?>
                            <option value="<?= h((string) $facetValue) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="irv-field">
                    <label class="irv-field__label" for="irvAn">An</label>
                    <input id="irvAn" name="an" class="irv-input" list="irvAnList" value="<?= h($filterAn) ?>" placeholder="ex: 2015">
                    <datalist id="irvAnList">
                        <?php foreach ($filterFacets['ani'] ?? [] as $facetValue): ?>
                            <option value="<?= h((string) $facetValue) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="irv-filters__actions">
                    <button class="irv-btn irv-btn--secondary" type="submit">
                        <i data-lucide="filter" class="irv-btn__icon"></i>
                        Aplică filtre
                    </button>
                    <?php if ($supplier !== '' || $filterMarca !== '' || $filterModel !== '' || $filterMotorizare !== '' || $filterBrand !== '' || $filterAn !== ''): ?>
                    <a href="?<?= h(irv_queue_query(['supplier' => null, 'marca' => null, 'model' => null, 'motorizare' => null, 'brand' => null, 'an' => null])) ?>" class="irv-btn irv-btn--ghost">Resetează filtre</a>
                    <?php endif; ?>
                </div>
                <?php if ($status === 'pending'): ?>
                <div class="irv-filters__publish">
                    <div class="irv-field">
                        <label class="irv-field__label" for="publishModeBulk">La duplicate</label>
                        <select id="publishModeBulk" class="irv-input irv-select irv-select--sm">
                            <option value="skip" selected>Omitere</option>
                            <option value="update">Actualizare</option>
                            <option value="force">Adăugare forțată</option>
                        </select>
                    </div>
                    <button id="addAll" class="irv-btn irv-btn--accent" type="button" title="Produsele cu date critice lipsă sunt excluse automat">
                        <i data-lucide="upload-cloud" class="irv-btn__icon"></i>
                        Publică toate filtrate
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($status === 'pending' && $queueCriticalBlocked > 0): ?>
        <div class="irv-alert irv-alert--warn" role="status">
            <i data-lucide="alert-circle" class="irv-alert__icon"></i>
            <div class="irv-alert__body">
                <strong><?= h((string) $queueCriticalBlocked) ?> produs<?= $queueCriticalBlocked === 1 ? '' : 'e' ?> cu date critice lipsă</strong>
                <span>Marcate cu badge roșu: fără categorie, brand, preț 0 sau imagine. Publicarea automată este blocată până la completare.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($status === 'pending'): ?>
    <section class="irv-card irv-bulk">
        <div class="irv-card__head">
            <div class="irv-card__title">
                <i data-lucide="zap" class="irv-card__title-icon"></i>
                <span>Acțiuni în masă</span>
            </div>
            <label class="irv-check">
                <input type="checkbox" id="selectAllRows">
                <span>Selectează toate rândurile din pagină</span>
            </label>
        </div>
        <div class="irv-bulk__toolbar">
            <div class="irv-bulk__row">
                <span class="irv-bulk__tag">Categorii</span>
                <button id="applyTaxonomySelected" type="button" class="irv-btn irv-btn--sky" title="Potrivește categorie/subcategorie din denumire (local, fără API)">
                    <i data-lucide="folder-tree" class="irv-btn__icon"></i>
                    Categorii pe selectate
                </button>
                <button id="applyTaxonomyFiltered" type="button" class="irv-btn irv-btn--violet" title="Completează categorii pe tot filtrul curent (max. 500)">
                    <i data-lucide="folder-sync" class="irv-btn__icon"></i>
                    Categorii pe filtrate<?= $totalApprox ? '' : ' (' . h((string) $importTotal) . ')' ?>
                </button>
            </div>
            <div class="irv-bulk__row">
                <span class="irv-bulk__tag">Reguli adaos</span>
                <button id="applyMarkupSelected" type="button" class="irv-btn irv-btn--teal" title="Reaplică reguli de adaos pe selecție (preț bază deja calculat la import)">
                    <i data-lucide="badge-percent" class="irv-btn__icon"></i>
                    Reguli pe selectate
                </button>
                <button id="applyMarkupFiltered" type="button" class="irv-btn irv-btn--violet" title="Reaplică reguli pe tot filtrul curent (max. 5000)">
                    <i data-lucide="layers" class="irv-btn__icon"></i>
                    Reguli pe filtrate (<?= h((string) $importTotal) ?>)
                </button>
            </div>
            <div class="irv-bulk__row">
                <span class="irv-bulk__tag">Publicare</span>
                <div class="irv-field irv-field--compact">
                    <label class="irv-field__label" for="publishMode">Duplicate</label>
                    <select id="publishMode" class="irv-input irv-select irv-select--sm">
                        <option value="skip" selected>Omitere (recomandat)</option>
                        <option value="update">Actualizare existent</option>
                        <option value="force">Adăugare forțată</option>
                    </select>
                </div>
                <button id="addSelected" type="button" class="irv-btn irv-btn--success">
                    <i data-lucide="check-square" class="irv-btn__icon"></i>
                    Publică selectate
                </button>
            </div>
            <div class="irv-bulk__row">
                <span class="irv-bulk__tag">Imagini</span>
                <button id="refreshImages" type="button" data-besoiu-action="refresh-images" class="irv-btn irv-btn--sky">
                    <span id="refreshImagesIcon" aria-hidden="true" class="irv-btn__icon-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </span>
                    <span id="refreshImagesLabel"><?= htmlspecialchars($refreshImagesButtonLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
            <div class="irv-bulk__row">
                <span class="irv-bulk__tag">Export</span>
                <button id="exportValidatedCsv" type="button" class="irv-btn irv-btn--violet" title="CSV intern (toate câmpurile) — doar produse validate">
                    <i data-lucide="file-spreadsheet" class="irv-btn__icon"></i>
                    CSV validat
                </button>
                <button id="exportAutoproCsv" type="button" class="irv-btn irv-btn--teal" title="CSV PieseAuto Autopro: ID; titlu MP; categorie; descriere; monedă; preț; cantitate">
                    <i data-lucide="file-down" class="irv-btn__icon"></i>
                    Excel marketplace
                </button>
                <button id="exportBaselinkerBtn" type="button" class="irv-btn irv-btn--orange" title="API BaseLinker — logică separată (nu CSV PieseAuto)">
                    <i data-lucide="send" class="irv-btn__icon"></i>
                    BaseLinker
                </button>
            </div>
            <div class="irv-bulk__row irv-bulk__row--danger">
                <span class="irv-bulk__tag">Curățare</span>
                <button id="deleteSelected" type="button" class="irv-btn irv-btn--danger">
                    <i data-lucide="trash-2" class="irv-btn__icon"></i>
                    Șterge selectate
                </button>
                <button id="deleteAllQueue" type="button" class="irv-btn irv-btn--danger" title="Golește complet coada de import, indiferent de filtre sau selecție">
                    <i data-lucide="trash" class="irv-btn__icon"></i>
                    Șterge tot
                </button>
            </div>
        </div>
        <div id="imageScanStatus" class="import-image-scan-status import-image-scan-dock" data-besoiu-block="image-scan-dock" hidden role="status" aria-live="polite">
            <span id="imageScanIcon" class="import-image-scan-status__icon"></span>
            <div class="import-image-scan-status__body">
                <div id="imageScanTitle" class="import-image-scan-status__title"></div>
                <div id="imageScanDetail" class="import-image-scan-status__detail"></div>
                <div class="import-image-scan-status__progress-head">
                    <span id="imageScanProgressLabel">Progres scanare imagini</span>
                    <span id="imageScanProgressPct" data-besoiu-block="image-scan-progress-pct">0%</span>
                </div>
                <div id="imageScanProgress" data-besoiu-block="image-scan-progress" class="import-image-scan-status__progress" hidden>
                    <div id="imageScanProgressBar" data-besoiu-block="image-scan-progress-bar" class="import-image-scan-status__progress-bar" style="width:0%"></div>
                </div>
            </div>
            <button id="refreshImagesStop" type="button" data-besoiu-action="refresh-images-stop" class="import-image-scan-status__stop" hidden>Oprește</button>
        </div>
    </section>
    <?php endif; ?>

    <section class="irv-card irv-table-card">
        <div class="irv-card__head irv-card__head--table">
            <div class="irv-card__title">
                <i data-lucide="list" class="irv-card__title-icon"></i>
                <span>Produse în coadă</span>
            </div>
            <span class="irv-card__hint"><?= min($offset + 1, $importTotal) ?>–<?= min($offset + $perPage, $importTotal) ?> din <?= h((string) $importTotal) ?></span>
        </div>
    <div class="admin-table-wrap irv-table-wrap">
        <table class="irv-table w-full text-left text-sm">
            <thead>
                <tr class="irv-table__head-row">
                    <?php if ($status === 'pending'): ?><th class="irv-th irv-th--check">✓</th><?php endif; ?>
                    <th class="irv-th">Imagine</th>
                    <th class="irv-th irv-th--code">Cod</th>
                    <th class="irv-th irv-th--product">Produs</th>
                    <th class="irv-th">Alerte</th>
                    <th class="irv-th">Preț</th>
                    <th class="irv-th">Stoc</th>
                    <th class="irv-th">Categorie</th>
                    <th class="irv-th">Sursă</th>
                    <?php if ($status === ''): ?><th class="irv-th">Status</th><?php endif; ?>
                    <th class="irv-th irv-th--actions">Acțiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $imgMeta = image_meta($row); ?>
                    <?php $summary = import_product_summary($row); ?>
                    <?php $criticalFlags = review_critical_flags($row); ?>
                    <?php $hasCriticalGaps = $criticalFlags !== []; ?>
                    <?php $km = trim((string)($summary['vehicle']['kilometraj_km'] ?? '')); ?>
                    <?php $specs = trim((string)($summary['specs'] ?? '')); ?>
                    <?php $technicalCount = is_array($summary['technical_data'] ?? null) ? count($summary['technical_data']) : 0; ?>
                    <?php $technicalLines = is_array($summary['technical_data'] ?? null) ? array_slice($summary['technical_data'], 0, 4) : []; ?>
                    <?php $altCodesCount = is_array($summary['codes']['coduri_alternative'] ?? null) ? count($summary['codes']['coduri_alternative']) : 0; ?>
                    <?php
                    $formedTitles = review_formed_titles($row);
                    $queueEditWebTitle = rtrim(trim($formedTitles['website']), "|\t ");
                    $queueEditOemTitle = rtrim(trim($formedTitles['oem'] ?? ''), "|\t ");
                    $queueEditMpTitle = rtrim(trim($formedTitles['marketplace']), "|\t ");
                    if ($queueEditWebTitle === '') {
                        $queueEditWebTitle = rtrim(trim((string) ($row['pName'] ?? '')), "|\t ");
                    }
                    if ($queueEditOemTitle === '') {
                        $queueEditOemTitle = $queueEditWebTitle;
                    }
                    if ($queueEditMpTitle === '') {
                        $queueEditMpTitle = $queueEditWebTitle;
                    }
                    $queueEditMotor = trim((string) ($row['pMotorizare'] ?? ''));
                    if ($queueEditMotor !== '') {
                        $queueEditMotor = trim((string) preg_replace('/\([^)]*\)/u', '', $queueEditMotor));
                        $queueEditMotor = trim((string) preg_replace('/\s+/u', ' ', $queueEditMotor));
                        if (function_exists('import_sanitize_motorizare_value')) {
                            $queueEditMotor = import_sanitize_motorizare_value($queueEditMotor);
                            $queueEditMotor = trim(str_replace("\n", ', ', $queueEditMotor));
                        }
                    }
                    $queueEditPayload = [
                        'id' => (int) ($row['id'] ?? 0),
                        'pCode' => (string) ($row['pCode'] ?? ''),
                        'pName' => $queueEditWebTitle,
                        'pNameOem' => $queueEditOemTitle,
                        'pNameMarketplace' => $queueEditMpTitle,
                        'pBrand' => (string) ($row['pBrand'] ?? ''),
                        'pMarca' => (string) ($row['pMarca'] ?? ''),
                        'pModel' => (string) ($row['pModel'] ?? ''),
                        'pMotorizare' => $queueEditMotor,
                        'pPrice' => (string) ($row['pPrice'] ?? ''),
                        'pBasePrice' => (string) ($row['pBasePrice'] ?? ''),
                        'pStock' => (string) ($row['pStock'] ?? '0'),
                        'pCategory' => (string) ($row['pCategory'] ?? ''),
                        'pSubcategory' => (string) ($row['pSubcategory'] ?? ''),
                        'pNote' => import_resolve_description_marketplace($row),
                        'pNoteWebsite' => import_resolve_description_website($row),
                        'pNoteMarketplace' => import_resolve_description_marketplace($row),
                        'websiteTabs' => review_website_description_tabs($row),
                        'pOem' => (string) ($row['pOem'] ?? ''),
                        'pCompatibilitati' => (string) ($row['pCompatibilitati'] ?? ''),
                        'compatSummary' => review_compat_preview_summary($row),
                        'image' => review_image_src($row),
                        'imageSource' => (string) ($imgMeta['source'] ?? ''),
                        'imageTrusted' => review_image_is_trusted($row),
                        'imageHasDisplay' => review_image_has_display($row),
                        'status' => (string) ($row['status'] ?? ''),
                        'criticalFlags' => array_map(
                            static fn(array $flag): string => (string) ($flag['label'] ?? ''),
                            $criticalFlags
                        ),
                        'needsReprocess' => review_row_needs_reprocess($row),
                    ];
                    $rowCanQueueEdit = ($row['status'] ?? '') === 'pending';
                    $queueEditJson = json_encode(
                        $queueEditPayload,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                    );
                    if ($queueEditJson === false) {
                        $queueEditJson = json_encode(['id' => (int) ($row['id'] ?? 0)], JSON_UNESCAPED_UNICODE);
                    }
                    // Un singur payload (base64) — JSON duplicat în HTML umfla pagina ×2 per rând.
                    $queueEditB64 = base64_encode((string) $queueEditJson);
                    ?>
                    <tr class="import-row<?= $rowCanQueueEdit ? ' import-row--queue-edit' : '' ?> border-b align-top hover:bg-slate-50<?= $hasCriticalGaps ? ' import-row--critical-gaps' : '' ?>" data-id="<?= h($row['id']) ?>"<?= $rowCanQueueEdit ? ' data-queue-edit-b64="' . h($queueEditB64) . '"' : '' ?><?= $hasCriticalGaps ? ' data-critical-gaps="1"' : '' ?>>
                        <?php if ($status === 'pending'): ?>
                            <td class="irv-td irv-td--check">
                            <input type="checkbox" class="row-check irv-check-input" value="<?= h($row['id']) ?>">
                        </td>
                        <?php endif; ?>
                        <td class="irv-td irv-td--image">
                            <?php
                            $hasTrustedImage = review_image_is_trusted($row);
                            $hasDisplayImage = review_image_has_display($row);
                            $imgSrc = review_image_src($row);
                            ?>
                            <div class="irv-thumb<?= $hasDisplayImage ? '' : ' irv-thumb--missing' ?><?= ($hasDisplayImage && !$hasTrustedImage) ? ' irv-thumb--preview' : '' ?>">
                                <img src="<?= h($imgSrc) ?>" alt="<?= h($row['pName'] ?? '') ?>" data-fallback="<?= h($importReviewPlaceholder) ?>" onerror="importReviewImageOnError(this)" class="irv-thumb__img">
                                <?php if (!$hasDisplayImage): ?>
                                    <span class="irv-thumb__badge irv-thumb__badge--error">Lipsă</span>
                                <?php elseif (!$hasTrustedImage): ?>
                                    <span class="irv-thumb__badge irv-thumb__badge--warn">Preview</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="irv-td irv-td--code">
                            <span class="irv-code" title="<?= h($row['pCode'] ?? '') ?>"><?= h(trim((string)($row['pCode'] ?? '')) !== '' ? (string)$row['pCode'] : '—') ?></span>
                        </td>
                        <td class="irv-td irv-td--product">
                            <?php $prodListItems = review_product_list_items($row, $summary); ?>
                            <div class="irv-prod">
                                <?php
                                $formedInline = review_formed_titles($row);
                                $siteNameInline = $formedInline['website'];
                                $marketplaceTitleInline = $formedInline['marketplace'];
                                if ($siteNameInline === '') {
                                    $siteNameInline = trim((string) ($row['pName'] ?? ''));
                                }
                                if ($marketplaceTitleInline === '') {
                                    $marketplaceTitleInline = $siteNameInline;
                                }
                                ?>
                                <div class="irv-prod__name" title="<?= h($siteNameInline) ?>">
                                    <span style="display:inline-block;min-width:2.6rem;font-size:10px;font-weight:700;color:#0f766e;">WEB</span>
                                    <?= h($siteNameInline !== '' ? $siteNameInline : 'Fără nume') ?>
                                </div>
                                <div class="irv-prod__marketplace muted" title="<?= h($marketplaceTitleInline) ?>" style="font-size:12px;margin-top:2px;opacity:.9;">
                                    <span style="display:inline-block;min-width:2.6rem;font-size:10px;font-weight:700;color:#b45309;">MP</span>
                                    <?= h($marketplaceTitleInline !== '' ? $marketplaceTitleInline : '—') ?>
                                </div>
                                <?php if ($prodListItems !== []): ?>
                                <ul class="irv-prod__list">
                                    <?php foreach ($prodListItems as $prodItem):
                                        $itemTitle = mb_strlen((string)$prodItem['value'], 'UTF-8') > 60
                                            ? (string)$prodItem['value']
                                            : '';
                                    ?>
                                    <li class="irv-prod__item"<?= !empty($prodItem['field']) ? ' data-queue-field="' . h((string)$prodItem['field']) . '"' : '' ?><?= $itemTitle !== '' ? ' title="' . h($itemTitle) . '"' : '' ?>>
                                        <span class="irv-prod__item-label"><?= h($prodItem['label']) ?></span>
                                        <span class="irv-prod__item-value"><?= h($prodItem['value']) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="irv-td irv-td--alerts">
                            <?php if ($criticalFlags === []): ?>
                                <span class="import-critical-ok" title="Date minime OK pentru publicare automată">OK</span>
                            <?php else: ?>
                                <div class="import-critical-badges flex flex-wrap gap-1">
                                    <?php foreach ($criticalFlags as $flag): ?>
                                        <span class="import-critical-badge" title="Blochează publicarea automată"><?= h($flag['label']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (($row['status'] ?? '') === 'pending'): ?>
                                    <div class="mt-1 text-[11px] font-medium text-amber-800">Auto-publish blocat</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="irv-td irv-td--price">
                            <?php
                            $priceValue = trim((string)($row['pPrice'] ?? ''));
                            $hasPrice = $priceValue !== '' && (float)$priceValue > 0;
                            $basePrice = trim((string)($row['pBasePrice'] ?? ''));
                            $hasBase = $basePrice !== '' && (float)$basePrice > 0;
                            $priceTitle = $hasPrice && $hasBase
                                ? 'Bază achiziție: ' . irv_format_price_lei($basePrice) . ' · Final (adaos + TVA): ' . irv_format_price_lei($priceValue)
                                : ($hasBase ? 'Doar achiziție: ' . irv_format_price_lei($basePrice) . ' — aplică Adaos comercial pentru preț final' : '');
                            $markupRuleName = trim((string) ($row['pMarkupRuleName'] ?? ''));
                            ?>
                            <?php if ($hasPrice): ?>
                                <div class="irv-price irv-price--ok" data-queue-field="price"<?= $priceTitle !== '' ? ' title="' . h($priceTitle) . '"' : '' ?>>
                                    <?= h(irv_format_price_lei($priceValue)) ?>
                                </div>
                                <?php if ($hasBase && $basePrice !== $priceValue): ?>
                                    <div class="irv-price-sub">Bază: <?= h(irv_format_price_lei($basePrice)) ?></div>
                                <?php endif; ?>
                                <?php if ($markupRuleName !== ''): ?>
                                    <div class="irv-price-rule" title="Regulă adaos aplicată"><?= h($markupRuleName) ?></div>
                                <?php endif; ?>
                            <?php elseif ($hasBase): ?>
                                <div class="irv-price irv-price--ok" data-queue-field="price"<?= $priceTitle !== '' ? ' title="' . h($priceTitle) . '"' : '' ?>>
                                    Achiziție: <?= h(irv_format_price_lei($basePrice)) ?>
                                </div>
                                <div class="irv-price-sub irv-price-sub--hint">Preț final → Adaos comercial</div>
                            <?php else: ?>
                                <div class="irv-price irv-price--missing" data-queue-field="price">
                                    Fără preț
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="irv-td irv-td--stock" data-queue-field="stock"><?= h($row['pStock'] ?? '0') ?></td>
                        <td class="irv-td irv-td--cat<?= in_array('missing_category', array_column($criticalFlags, 'code'), true) ? ' import-critical-cell' : '' ?>" data-queue-field="category">
                            <span title="<?= h(review_taxonomy_line($row)) ?>"><?= h(short_text(review_taxonomy_line($row), 32)) ?></span>
                        </td>
                        <td class="irv-td irv-td--imgsrc">
                            <?php
                            $imgSource = $imgMeta['source'];
                            $imgLabel = match ($imgSource) {
                                'csv' => 'CSV',
                                'preview' => 'Preview',
                                'tecdoc', 'tecdoc_api' => 'TecDoc',
                                'caietcomenzi', 'CSV TecDoc' => 'CaietComenzi',
                                'search_api' => 'Search API',
                                'import' => 'Import',
                                'poze', 'local_ttc_poze' => 'Poze TecDoc',
                                'autopartner', 'autopartner_local' => 'Autopartner',
                                'import_pro' => 'Import Pro',
                                default => 'Lipsă',
                            };
                            ?>
                            <span class="irv-pill irv-pill--<?= h(match ($imgSource) {
                                'csv', 'preview' => 'blue',
                                'tecdoc', 'tecdoc_api', 'poze', 'local_ttc_poze', 'autopartner', 'autopartner_local', 'import_pro' => 'green',
                                'caietcomenzi', 'CSV TecDoc' => 'indigo',
                                'search_api' => 'amber',
                                default => 'red',
                            }) ?>" title="<?= h($imgMeta['query']) ?>">
                                <?= h($imgLabel) ?>
                            </span>
                        </td>
                        <?php if ($status === ''): ?>
                        <td class="irv-td irv-td--status">
                            <span class="irv-pill irv-pill--<?= h(match ($row['status'] ?? '') {
                                'pending' => 'amber',
                                'imported' => 'green',
                                'conflict_live' => 'orange',
                                default => 'red',
                            }) ?>">
                                <?= h(match ($row['status'] ?? '') {
                                    'conflict_live' => 'Conflict',
                                    'pending' => 'Pending',
                                    'imported' => 'Importat',
                                    'deleted' => 'Șters',
                                    default => (string)($row['status'] ?? '—'),
                                }) ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <td class="irv-td irv-td--actions">
                            <?php if (($row['status'] ?? '') === 'pending'): ?>
                                <div class="irv-row-actions">
                                    <button class="irv-row-btn irv-row-btn--violet queue-edit-one" type="button" data-besoiu-action="queue-edit-one" title="Deschide fereastra de editare">Editare</button>
                                    <button class="irv-row-btn irv-row-btn--sky queue-preview-one" type="button" data-besoiu-action="queue-preview-one" title="Previzualizare Website + Marketplace">Previzualizare</button>
                                    <button class="irv-row-btn irv-row-btn--success add-one" type="button" title="<?= $hasCriticalGaps ? 'Publicare manuală permisă; auto-publish blocat' : 'Publică în magazin' ?>">Publică</button>
                                    <button class="irv-row-btn irv-row-btn--orange exclude-one" type="button" title="Elimină din draft">Exclude</button>
                                </div>
                            <?php elseif (($row['status'] ?? '') === 'deleted'): ?>
                                <div class="irv-row-actions">
                                    <button class="irv-row-btn irv-row-btn--teal restore-one" type="button" title="Readu în coada de publicat">Restaurează</button>
                                </div>
                            <?php else: ?>
                                <span class="irv-muted">Fără acțiuni</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($importTotalPages > 1 || $queueHasMore || $page > 1): ?>
    <footer class="irv-pagination">
        <span class="irv-pagination__info"><?= min($offset + 1, $importTotal) ?>–<?= min($offset + count($rows), $importTotal) ?><?= $totalApprox ? '+' : '' ?> din <?= h((string) $importTotal) ?><?= $totalApprox ? '+' : '' ?></span>
        <div class="irv-pagination__pages">
        <?php
        $baseQs = irv_queue_query([]);
        if ($page > 1):
            $prevHref = '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . ($page - 1);
        ?>
            <a href="<?= h($prevHref) ?>" class="irv-pagenum" aria-label="Pagina anterioară">‹</a>
        <?php endif; ?>
        <?php
        for ($p = max(1, $page - 2); $p <= min($importTotalPages, $page + 2); $p++):
            $href = '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . $p;
        ?>
            <a href="<?= h($href) ?>" class="irv-pagenum<?= $p === $page ? ' is-active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($queueHasMore || $page < $importTotalPages):
            $nextHref = '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . ($page + 1);
        ?>
            <a href="<?= h($nextHref) ?>" class="irv-pagenum" aria-label="Pagina următoare">›</a>
        <?php endif; ?>
        </div>
    </footer>
    <?php endif; ?>
    </section>
    </div>
</div>

<div id="queueConfirmModal" class="irv-markup-modal irv-confirm-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="queueConfirmModalTitle" aria-modal="true">
    <div class="irv-markup-modal__backdrop" data-close-queue-confirm></div>
    <div class="irv-markup-modal__panel">
        <div class="irv-markup-modal__head">
            <div>
                <h3 id="queueConfirmModalTitle" class="irv-markup-modal__title">Confirmă acțiunea</h3>
                <p id="queueConfirmModalSubtitle" class="irv-markup-modal__subtitle"></p>
            </div>
            <button type="button" class="irv-markup-modal__close" data-close-queue-confirm aria-label="Închide">&times;</button>
        </div>
        <div class="irv-markup-modal__body">
            <div id="queueConfirmModalBody" class="irv-confirm-modal__body"></div>
            <p id="queueConfirmModalWarning" class="irv-markup-modal__notice irv-confirm-modal__warning hidden"></p>
        </div>
        <div class="irv-markup-modal__actions">
            <button type="button" class="irv-btn irv-btn--ghost" data-close-queue-confirm id="queueConfirmModalCancel">Anulează</button>
            <button type="button" id="queueConfirmModalOk" class="irv-btn irv-btn--danger">
                Confirmă
            </button>
        </div>
    </div>
</div>

<div id="queueTaxonomyProgressModal" class="irv-markup-modal irv-taxonomy-progress-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="queueTaxonomyProgressTitle" aria-modal="true">
    <div class="irv-markup-modal__backdrop"></div>
    <div class="irv-markup-modal__panel irv-taxonomy-progress-modal__panel">
        <div class="irv-markup-modal__head">
            <div>
                <h3 id="queueTaxonomyProgressTitle" class="irv-markup-modal__title">Mapare categorii</h3>
                <p id="queueTaxonomyProgressSubtitle" class="irv-markup-modal__subtitle">Potrivire din arborele de categorii (ART_NAME TecDoc)</p>
            </div>
        </div>
        <div class="irv-markup-modal__body">
            <div class="irv-taxonomy-progress__stats">
                <div class="irv-taxonomy-progress__stat">
                    <span id="queueTaxonomyProgressCount" class="irv-taxonomy-progress__stat-value">0 / 0</span>
                    <span class="irv-taxonomy-progress__stat-label">produse procesate</span>
                </div>
                <div class="irv-taxonomy-progress__stat">
                    <span id="queueTaxonomyProgressPct" class="irv-taxonomy-progress__stat-value">0%</span>
                    <span class="irv-taxonomy-progress__stat-label">progres</span>
                </div>
            </div>
            <div class="import-queue-edit-modal__progress" id="queueTaxonomyProgressWrap">
                <div id="queueTaxonomyProgressBar" class="import-queue-edit-modal__progress-bar"></div>
            </div>
            <p id="queueTaxonomyProgressDetail" class="irv-taxonomy-progress__detail">Pornesc maparea…</p>
            <div id="queueTaxonomyProgressLog" class="import-queue-edit-modal__log" hidden></div>
        </div>
        <div class="irv-markup-modal__actions">
            <button type="button" id="queueTaxonomyProgressClose" class="irv-btn irv-btn--ghost" hidden>Închide</button>
        </div>
    </div>
</div>

<div id="queueMarkupModal" class="irv-markup-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="queueMarkupModalTitle">
    <div class="irv-markup-modal__backdrop" data-close-markup-modal></div>
    <div class="irv-markup-modal__panel">
        <div class="irv-markup-modal__head">
            <div>
                <h3 id="queueMarkupModalTitle" class="irv-markup-modal__title">Reaplică reguli adaos</h3>
                <p id="queueMarkupModalSubtitle" class="irv-markup-modal__subtitle">Prețul bază (achiziție) vine de la Import Pro — aici recalculezi doar prețul final magazin.</p>
            </div>
            <button type="button" class="irv-markup-modal__close" data-close-markup-modal aria-label="Închide">&times;</button>
        </div>
        <div class="irv-markup-modal__body">
            <p class="irv-markup-modal__notice">
                La import se salvează doar prețul de achiziție (<code>pBasePrice</code>).
                Prețul final <strong>nu</strong> se calculează automat — alege produsele și aplică aici adaosul comercial dorit.
            </p>
            <div class="irv-markup-modal__info">
                <span>TVA magazin: <strong><?= h(rtrim(rtrim(number_format($commercialVatPercent, 2, '.', ''), '0'), '.')) ?>%</strong></span>
                <span>Rotunjire: <strong><?= h($globalPriceRoundLabel) ?></strong></span>
            </div>
            <div class="irv-markup-modal__options" role="radiogroup" aria-label="Tip reaplicare adaos">
                <label class="irv-markup-option">
                    <input type="radio" name="queue_markup_mode" value="conditional" checked>
                    <span class="irv-markup-option__card">
                        <strong>Reguli active (auto)</strong>
                        <small>Recalculează preț final: setări Adaos comercial + prima regulă potrivită (brand/categorie/prag preț). Preț bază neschimbat.</small>
                    </span>
                </label>
                <label class="irv-markup-option">
                    <input type="radio" name="queue_markup_mode" value="rule">
                    <span class="irv-markup-option__card">
                        <strong>Regulă concretă</strong>
                        <small>Aplici manual o regulă salvată în Adaos comercial</small>
                    </span>
                </label>
            </div>
            <div id="queueMarkupRulePicker" class="irv-markup-modal__rule-picker hidden">
                <label class="irv-field">
                    <span class="irv-field__label">Regulă de adaos</span>
                    <select id="queueMarkupModalRuleId" class="irv-input irv-select">
                        <option value="">— Alege regula —</option>
                        <?php foreach ($activeMarkupRules as $markupRule): ?>
                            <option value="<?= h((string) ($markupRule['id'] ?? '')) ?>">
                                <?= h((string) ($markupRule['name'] ?? 'Regulă')) ?> (<?= h(irv_format_markup_rule_adjustment($markupRule)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($activeMarkupRules === []): ?>
                    <p class="irv-markup-modal__hint">Nu există reguli active. Creează reguli în <a href="/admin/adaoscomercial" class="font-medium underline">Adaos comercial</a>.</p>
                <?php endif; ?>
            </div>
            <p class="irv-markup-modal__hint">Prețul final (<code>pPrice</code>) = preț bază existent + adaos comercial + TVA + rotunjire (conform Adaos comercial).</p>
        </div>
        <div class="irv-markup-modal__actions">
            <button type="button" class="irv-btn irv-btn--ghost" data-close-markup-modal>Anulează</button>
            <button type="button" id="queueMarkupModalConfirm" class="irv-btn irv-btn--teal">
                <i data-lucide="check" class="irv-btn__icon"></i>
                Recalculează preț final
            </button>
        </div>
    </div>
</div>

<div id="importQueuePreviewModal" class="import-queue-preview-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="importQueuePreviewTitle" aria-modal="true">
    <div class="import-queue-preview-modal__backdrop" data-close-queue-preview></div>
    <div class="import-queue-preview-modal__panel">
        <div class="import-queue-preview-modal__head">
            <div>
                <h3 id="importQueuePreviewTitle" class="import-queue-preview-modal__title">Previzualizare produs</h3>
                <p class="import-queue-preview-modal__subtitle">Website: titlu pe canale + tab-uri descriere (Formare carte). Marketplace: descriere Base.html.</p>
            </div>
            <button type="button" class="import-queue-preview-modal__close" data-close-queue-preview aria-label="Închide">&times;</button>
        </div>
        <div class="import-queue-preview-tabs" role="tablist" aria-label="Canal previzualizare">
            <button type="button" class="import-queue-preview-tab is-active" role="tab" aria-selected="true" data-preview-tab="website">Website</button>
            <button type="button" class="import-queue-preview-tab" role="tab" aria-selected="false" data-preview-tab="marketplace">Marketplace</button>
        </div>
        <div class="import-queue-preview-modal__body">
            <section id="importQueuePreviewPaneWebsite" class="import-queue-preview-pane is-active" role="tabpanel" data-preview-pane="website">
                <div class="import-queue-preview-subtabs" role="tablist" aria-label="Moment căutare website">
                    <button type="button" class="import-queue-preview-subtab is-active" role="tab" aria-selected="true" data-preview-web-mode="vehicle">Marcă / Model / Motor</button>
                    <button type="button" class="import-queue-preview-subtab" role="tab" aria-selected="false" data-preview-web-mode="oem">Cod OEM</button>
                </div>
                <p id="irvPreviewWebModeHint" class="import-queue-preview-mode-hint">Titlu format pentru căutare după marcă, model și motorizare (canal Website din Formare carte).</p>
                <article class="irv-preview-card irv-preview-card--web" id="irvPreviewWebCard">
                    <div class="irv-preview-card__media">
                        <img id="irvPreviewWebImage" src="/admin/dist/images/fakers/preview-12.jpg" alt="">
                        <span class="irv-preview-card__badge" id="irvPreviewWebBadge">Magazin online</span>
                    </div>
                    <div class="irv-preview-card__body">
                        <h4 id="irvPreviewWebTitle" class="irv-preview-card__title"></h4>
                        <div class="irv-preview-card__meta">
                            <span id="irvPreviewWebBrand"></span>
                            <span id="irvPreviewWebCode" class="irv-preview-card__code"></span>
                        </div>
                        <div id="irvPreviewWebVehicle" class="irv-preview-card__vehicle"></div>
                        <div id="irvPreviewWebOemLine" class="irv-preview-card__oem" hidden></div>
                        <div id="irvPreviewWebPrice" class="irv-preview-card__price"></div>
                        <div id="irvPreviewWebCategory" class="irv-preview-card__category"></div>
                        <div id="irvPreviewWebDesc" class="irv-preview-card__desc"></div>
                    </div>
                </article>
                <div class="irv-preview-web-detail">
                    <p class="import-queue-preview-mode-hint">Simulare cartă produs detaliat — tab-uri din Formare carte (Descriere / Compatibilitate / Specificații…)</p>
                    <div id="irvPreviewWebProductTabs" class="irv-preview-product-tabs" aria-label="Tab-uri descriere website"></div>
                </div>
            </section>
            <section id="importQueuePreviewPaneMarketplace" class="import-queue-preview-pane" role="tabpanel" data-preview-pane="marketplace" hidden>
                <article class="irv-preview-card irv-preview-card--mp">
                    <div class="irv-preview-card__media">
                        <img id="irvPreviewMpImage" src="/admin/dist/images/fakers/preview-12.jpg" alt="">
                        <span class="irv-preview-card__badge irv-preview-card__badge--mp">PieseAuto / export</span>
                    </div>
                    <div class="irv-preview-card__body">
                        <h4 id="irvPreviewMpTitle" class="irv-preview-card__title"></h4>
                        <div class="irv-preview-card__meta">
                            <span id="irvPreviewMpBrand"></span>
                            <span id="irvPreviewMpCode" class="irv-preview-card__code"></span>
                        </div>
                        <div id="irvPreviewMpVehicle" class="irv-preview-card__vehicle"></div>
                        <div id="irvPreviewMpPrice" class="irv-preview-card__price"></div>
                        <div id="irvPreviewMpCategory" class="irv-preview-card__category"></div>
                        <div id="irvPreviewMpDesc" class="irv-preview-card__desc"></div>
                    </div>
                </article>
            </section>
        </div>
        <div class="import-queue-preview-modal__foot">
            <button type="button" class="irv-btn irv-btn--ghost" data-close-queue-preview>Închide</button>
            <button type="button" class="irv-btn irv-btn--secondary" id="importQueuePreviewOpenEdit">Deschide editarea</button>
        </div>
    </div>
</div>

<div id="importQueueEditModal" class="import-queue-edit-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="importQueueEditTitle">
    <div class="import-queue-edit-modal__backdrop" data-close-queue-edit></div>
    <div class="import-queue-edit-modal__panel">
        <div class="import-queue-edit-modal__head">
            <div>
                <h3 id="importQueueEditTitle" class="text-lg font-semibold text-slate-900">Editare produs din coada</h3>
                <div id="importQueueEditCode" class="mt-1 text-xs font-mono text-slate-500"></div>
            </div>
            <button type="button" class="import-queue-edit-modal__close" data-close-queue-edit aria-label="Inchide">&times;</button>
        </div>
        <form id="importQueueEditForm" class="import-queue-edit-modal__body">
            <input type="hidden" id="importQueueEditId" name="id" value="">
            <div class="import-queue-edit-modal__top">
                <div class="import-queue-edit-modal__image-wrap">
                    <img id="importQueueEditImage" src="/admin/dist/images/fakers/preview-12.jpg" alt="" class="import-queue-edit-modal__image">
                    <div id="importQueueEditImageSource" class="mt-2 text-xs text-slate-500"></div>
                </div>
                <div id="importQueueEditAlerts" class="import-queue-edit-modal__alerts hidden"></div>
            </div>
            <div class="import-queue-edit-modal__sections">
                <section class="import-queue-edit-section">
                    <h4 class="import-queue-edit-section__title">Identificare</h4>
                    <div class="import-queue-edit-section__grid">
                        <label class="import-queue-edit-field">
                            <span>Cod</span>
                            <input type="text" id="importQueueEditCodeInput" class="h-10 w-full rounded-md border bg-slate-50 px-3 py-2 text-sm font-mono" readonly>
                        </label>
                        <label class="import-queue-edit-field import-queue-edit-field--wide">
                            <span>Titlu site (web)</span>
                            <input type="text" id="importQueueEditName" name="pName" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" required>
                        </label>
                        <label class="import-queue-edit-field import-queue-edit-field--wide">
                            <span>Titlu marketplace</span>
                            <input type="text" id="importQueueEditNameMarketplace" name="pNameMarketplace" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Titlu pentru export PieseAuto / BaseLinker">
                        </label>
                    </div>
                </section>
                <section class="import-queue-edit-section">
                    <h4 class="import-queue-edit-section__title">Vehicul si brand</h4>
                    <div class="import-queue-edit-section__grid">
                        <label class="import-queue-edit-field">
                            <span>Brand produs</span>
                            <input type="text" id="importQueueEditBrand" name="pBrand" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Marca auto</span>
                            <input type="text" id="importQueueEditMarca" name="pMarca" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Model</span>
                            <input type="text" id="importQueueEditModel" name="pModel" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Motorizare</span>
                            <input type="text" id="importQueueEditMotorizare" name="pMotorizare" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                        </label>
                    </div>
                </section>
                <section class="import-queue-edit-section">
                    <h4 class="import-queue-edit-section__title">Comercial</h4>
                    <div class="import-queue-edit-section__grid">
                        <label class="import-queue-edit-field">
                            <span>Pret baza / achiziție (fara TVA)</span>
                            <input type="text" id="importQueueEditBasePrice" name="pBasePrice" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" inputmode="decimal">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Pret final (lei)</span>
                            <input type="text" id="importQueueEditPrice" name="pPrice" readonly placeholder="Se setează din Adaos comercial" class="h-10 w-full rounded-md border bg-slate-50 px-3 py-2 text-sm text-slate-600" inputmode="decimal" title="Nu se calculează automat. Selectează produsele și aplică Adaos comercial.">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Stoc</span>
                            <input type="text" id="importQueueEditStock" name="pStock" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" inputmode="numeric">
                        </label>
                    </div>
                </section>
                <section class="import-queue-edit-section">
                    <h4 class="import-queue-edit-section__title">Taxonomie</h4>
                    <div class="import-queue-edit-section__grid">
                        <label class="import-queue-edit-field">
                            <span>Categorie</span>
                            <select id="importQueueEditCategory" name="pCategory" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">— Alege categorie —</option>
                                <?php foreach ($importQueueCategories as $importQueueCategory): ?>
                                    <option value="<?= h((string) ($importQueueCategory['label'] ?? '')) ?>"><?= h((string) ($importQueueCategory['label'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Subcategorie</span>
                            <select id="importQueueEditSubcategory" name="pSubcategory" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">— Alege subcategorie —</option>
                            </select>
                        </label>
                    </div>
                </section>
                <section class="import-queue-edit-section">
                    <h4 class="import-queue-edit-section__title">Detalii produs</h4>
                    <label class="import-queue-edit-field">
                        <span>Caracteristici</span>
                        <textarea id="importQueueEditNote" name="pNote" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm"></textarea>
                    </label>
                    <label class="import-queue-edit-field">
                        <span>OEM / Cross</span>
                        <textarea id="importQueueEditOem" name="pOem" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono text-xs"></textarea>
                    </label>
                    <label class="import-queue-edit-field">
                        <span>Compatibilitati</span>
                        <textarea id="importQueueEditCompatibilitati" name="pCompatibilitati" rows="2" class="w-full rounded-md border bg-background px-3 py-2 text-sm"></textarea>
                    </label>
                </section>
            </div>
            <div id="importQueueEditStatus" class="import-queue-edit-modal__status hidden" role="status" aria-live="polite">
                <div class="import-queue-edit-modal__status-inner">
                    <span id="importQueueEditStatusSpinner" class="import-queue-edit-modal__status-spinner" hidden aria-hidden="true"></span>
                    <span id="importQueueEditStatusText"></span>
                </div>
                <div id="importQueueEditProgressWrap" class="import-queue-edit-modal__progress hidden" aria-hidden="true">
                    <div id="importQueueEditProgressBar" class="import-queue-edit-modal__progress-bar is-indeterminate"></div>
                </div>
                <div id="importQueueEditStatusHint" class="import-queue-edit-modal__status-hint hidden"></div>
                <div id="importQueueEditImageLog" class="import-queue-edit-modal__log hidden" aria-live="polite"></div>
            </div>
            <div class="import-queue-edit-modal__actions">
                <div class="import-queue-edit-modal__tools">
                    <a href="#" id="importQueueEditPriceLog" class="import-queue-edit-modal__tool-btn import-queue-edit-modal__tool-btn--violet" target="_blank" rel="noopener">Log preț</a>
                    <button type="button" id="importQueueEditReprocess" class="import-queue-edit-modal__tool-btn import-queue-edit-modal__tool-btn--sky">Re-procesează</button>
                    <button type="button" id="importQueueEditRefreshImage" class="import-queue-edit-modal__tool-btn import-queue-edit-modal__tool-btn--teal">Caută imagine</button>
                </div>
                <div class="import-queue-edit-modal__primary-actions">
                    <button type="button" id="importQueueEditSyncTecdoc" class="inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm text-emerald-700 hover:bg-emerald-100" title="Re-sincronizează titlu, vehicul, compatibilități și imagine din MySQL TecDoc">Sync TecDoc</button>
                    <button type="submit" id="importQueueEditSave" class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-violet-50 px-4 text-sm text-violet-700 hover:bg-violet-100">Salvează</button>
                    <button type="button" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm hover:bg-foreground/5" data-close-queue-edit>Închide</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="importQueueSubcategoriesByCategory"><?= json_encode($importQueueSubcategoriesByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php require __DIR__ . '/_importreview-shell-styles.php'; ?>
<style>
/* ── Import Review — layout PRO (full-width, vertical stack) ── */
.import-review-page.irv-layout,
.irv-layout {
    --irv-teal: #0d9488;
    --irv-teal-dark: #0f766e;
    --irv-ink: #0f172a;
    --irv-muted: #64748b;
    --irv-line: #e2e8f0;
    --irv-bg: #f8fafc;
    --irv-radius: 14px;
    --irv-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.05);
    display: block;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin-top: -0.5rem;
}
.import-review-page .admin-panel.irv-shell {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
    max-width: 100%;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}
.irv-shell > * { width: 100%; max-width: 100%; box-sizing: border-box; }
.irv-filters__grid .irv-field { flex: 0 1 260px; min-width: 200px; }
.irv-filters__grid .irv-input { width: 100%; }
.irv-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 22px 24px;
    border-radius: 18px;
    border: 1px solid var(--irv-line);
    background: linear-gradient(135deg, #ecfdf5 0%, #fff 55%, #f0f9ff 100%);
    box-shadow: var(--irv-shadow);
}
.irv-hero__eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--irv-teal-dark);
    margin-bottom: 4px;
}
.irv-hero__title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--irv-ink);
    line-height: 1.2;
}
.irv-hero__desc {
    margin: 8px 0 0;
    max-width: 52ch;
    font-size: 13px;
    line-height: 1.55;
    color: var(--irv-muted);
}
.irv-hero__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}
.irv-stat {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 14px;
    border-radius: 12px;
    background: rgba(255,255,255,.85);
    border: 1px solid var(--irv-line);
    min-width: 100px;
}
.irv-stat--warn { border-color: #fcd34d; background: #fffbeb; }
.irv-stat__value { font-size: 1.25rem; font-weight: 800; color: var(--irv-ink); line-height: 1; }
.irv-stat--warn .irv-stat__value { color: #b45309; }
.irv-stat__label { font-size: 11px; color: var(--irv-muted); }
.irv-hero__actions { display: flex; gap: 8px; flex-shrink: 0; }

/* Toolbar: tab-uri tip + status */
.irv-toolbar { padding: 0; }
.irv-toolbar__row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 20px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--irv-line);
}
.irv-toolbar__row:last-child { border-bottom: none; }
.irv-toolbar__row--status { background: var(--irv-bg); }
.irv-toolbar__group { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.irv-toolbar__group--full { flex: 1; width: 100%; }
.irv-toolbar__label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--irv-muted);
}
.irv-seg {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 4px;
    border-radius: 12px;
    border: 1px solid var(--irv-line);
    background: #fff;
}
.irv-seg--wrap { width: 100%; }
.irv-seg__item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    border: 1px solid transparent;
    white-space: nowrap;
    transition: background-color 0.15s ease, color 0.15s ease;
    transform: none;
    box-shadow: none;
    outline: none;
    -webkit-tap-highlight-color: transparent;
}
.irv-seg__item:active,
.irv-seg__item:focus,
.irv-seg__item:focus-visible {
    transform: none;
    outline: none;
}
.irv-seg__item--sm { padding: 7px 12px; font-size: 12px; }
.irv-seg__icon { width: 15px; height: 15px; flex-shrink: 0; }
.irv-seg__item:hover:not(.is-active) { background: #f1f5f9; color: var(--irv-ink); }
.irv-seg__item.is-active,
.irv-seg__item.is-active:hover {
    background: linear-gradient(135deg, var(--irv-teal), #059669) !important;
    color: #fff !important;
    box-shadow: 0 3px 12px rgba(13,148,136,.25);
    transform: none !important;
}
.irv-toolbar__row--status .irv-seg__item.is-active,
.irv-toolbar__row--status .irv-seg__item.is-active:hover {
    background: linear-gradient(135deg, #0a3d31, #14b8a6) !important;
    box-shadow: 0 3px 12px rgba(20,184,166,.22);
}

.irv-card {
    border: 1px solid var(--irv-line);
    border-radius: var(--irv-radius);
    background: #fff;
    box-shadow: var(--irv-shadow);
    overflow: hidden;
}
.irv-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 18px;
    border-bottom: 1px solid var(--irv-line);
    background: linear-gradient(180deg, var(--irv-bg), #fff);
}
.irv-card__head--table { padding: 12px 18px; }
.irv-card__title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--irv-ink);
}
.irv-card__title-icon { width: 18px; height: 18px; color: var(--irv-teal-dark); }
.irv-card__hint { font-size: 12px; color: var(--irv-muted); }

.irv-filters__body { padding: 0; }
.irv-filters__grid {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 16px;
    padding: 16px 18px;
}
.irv-filters__grid--wide .irv-field { flex: 1 1 160px; min-width: 140px; max-width: 220px; }
.irv-filters__grid--wide .irv-filters__actions { flex: 1 1 100%; }
.irv-filters__actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.irv-filters__publish {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    margin-left: auto;
}
@media (max-width: 768px) {
    .irv-filters__publish { margin-left: 0; width: 100%; }
}

.irv-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.irv-field--inline, .irv-field--compact { flex-direction: column; }
.irv-field__label { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--irv-muted); }
.irv-input {
    height: 40px;
    padding: 0 12px;
    border-radius: 10px;
    border: 1px solid var(--irv-line);
    background: #fff;
    font-size: 13px;
    color: var(--irv-ink);
}
.irv-select { cursor: pointer; }
.irv-select--sm { height: 36px; font-size: 12px; }

.irv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: transform .15s, box-shadow .15s, background .15s;
}
.irv-btn__icon { width: 16px; height: 16px; flex-shrink: 0; }
.irv-btn__icon-wrap { display: inline-flex; }
.irv-btn:hover { transform: translateY(-1px); }
.irv-btn--primary { background: linear-gradient(135deg, var(--irv-teal), #059669); color: #fff; box-shadow: 0 4px 14px rgba(13,148,136,.25); }
.irv-btn--secondary { background: #fff; color: #334155; border-color: var(--irv-line); }
.irv-btn--ghost { background: transparent; color: var(--irv-teal-dark); border-color: #99f6e4; }
.irv-btn--accent { background: linear-gradient(135deg, #0f766e, #14b8a6); color: #fff; box-shadow: 0 4px 14px rgba(20,184,166,.22); }
.irv-btn--success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.irv-btn--sky { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
.irv-btn--violet { background: #f0fdfa; color: #0f766e; border-color: #ddd6fe; }
.irv-btn--teal { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.irv-btn--orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.irv-btn--danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.irv-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

.irv-alert {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
}
.irv-alert--warn { border: 1px solid #fcd34d; background: #fffbeb; color: #92400e; }
.irv-alert__icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }
.irv-alert__body { display: flex; flex-direction: column; gap: 4px; }
.irv-alert__body strong { color: #78350f; }

.irv-bulk__toolbar {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0;
}
.irv-bulk__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--irv-line);
}
.irv-bulk__row:last-child { border-bottom: none; }
.irv-bulk__row--danger { background: #fffafa; }
.irv-bulk__tag {
    flex: 0 0 88px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--irv-muted);
}
@media (max-width: 640px) {
    .irv-bulk__tag { flex: 0 0 100%; margin-bottom: 2px; }
}
.irv-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--irv-muted);
    cursor: pointer;
}
.irv-check-input { width: 16px; height: 16px; accent-color: var(--irv-teal); }

.irv-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.irv-table-card { overflow: hidden; }
.irv-table {
    min-width: 960px;
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.import-review-page .irv-table thead,
.import-review-page .admin-table-wrap .irv-table thead {
    background: linear-gradient(90deg, #047857, #0f172a) !important;
}
.import-review-page .irv-table thead th,
.import-review-page .irv-table .irv-th,
.import-review-page .admin-table-wrap .irv-table th {
    position: sticky;
    top: 0;
    z-index: 3;
    padding: 11px 12px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #fff !important;
    background: transparent !important;
    border-bottom: 1px solid rgba(255,255,255,.15) !important;
    white-space: nowrap;
    opacity: 1 !important;
}
.irv-th--check { text-align: center; width: 40px; }
.irv-th--code { min-width: 145px; }
.irv-th--product { min-width: 280px; }
.irv-th--actions { width: 156px; min-width: 156px; }
.irv-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .12s;
}
.irv-table tbody tr:hover { background: #f8fafc; }
.irv-td { padding: 11px 12px; vertical-align: top; font-size: 14px; line-height: 1.5; color: #334155; }
.irv-td--check { text-align: center; width: 40px; }
.irv-td--image { width: 76px; }
.irv-td--code { width: 155px; min-width: 145px; max-width: 180px; }
.irv-td--product { min-width: 280px; max-width: 440px; }
.irv-td--alerts { width: 110px; }
.irv-td--price { width: 100px; white-space: nowrap; }
.irv-td--stock { width: 52px; text-align: center; font-weight: 600; }
.irv-td--cat { max-width: 160px; }
.irv-td--imgsrc { width: 100px; }
.irv-td--actions {
    width: 118px;
    min-width: 118px;
    max-width: none;
    padding: 11px 8px;
    vertical-align: top;
}

.irv-code {
    display: block;
    font-family: ui-monospace, monospace;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}
.irv-prod__name {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.45;
    word-break: break-word;
}
.irv-prod__list {
    list-style: none;
    margin: 8px 0 0;
    padding: 0;
}
.irv-prod__item {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
    font-size: 14px;
    line-height: 1.45;
    color: #475569;
}
.irv-prod__item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.irv-prod__item-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #94a3b8;
}
.irv-prod__item-value {
    font-size: 14px;
    color: #334155;
    word-break: break-word;
    line-height: 1.45;
}
.irv-prod__item[data-queue-field="brand"] .irv-prod__item-value { font-weight: 600; }
.irv-prod__item[data-queue-field="oem"] .irv-prod__item-value {
    font-family: ui-monospace, monospace;
    font-size: 14px;
}
.irv-price {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.4;
}
.irv-price--ok { color: #047857; }
.irv-price--missing { color: #dc2626; }
.irv-price-sub {
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}
.irv-price-rule {
    margin-top: 2px;
    display: inline-block;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 2px 6px;
    border-radius: 999px;
    background: #f0fdfa;
    color: #0f766e;
    font-size: 10px;
    font-weight: 700;
}

.irv-markup-modal {
    position: fixed;
    inset: 0;
    z-index: 100010;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.irv-markup-modal.hidden { display: none !important; }
.irv-markup-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
}
.irv-markup-modal__panel {
    position: relative;
    z-index: 1;
    width: min(520px, 100%);
    max-height: min(90vh, 720px);
    overflow: auto;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
    border: 1px solid #e2e8f0;
}
.irv-markup-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.irv-markup-modal__title {
    margin: 0 0 4px;
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
}
.irv-markup-modal__subtitle {
    margin: 0;
    font-size: 0.82rem;
    color: #64748b;
}
.irv-markup-modal__close {
    border: none;
    background: #f8fafc;
    color: #64748b;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}
.irv-markup-modal__notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-size: 13px;
    line-height: 1.45;
}
.irv-markup-modal__notice code {
    font-size: 12px;
    background: rgba(255,255,255,.7);
    padding: 1px 4px;
    border-radius: 4px;
}
.irv-markup-modal__body { padding: 16px 20px; }
.irv-markup-modal__info {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin-bottom: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.78rem;
}
.irv-markup-modal__options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.irv-markup-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}
.irv-markup-option input { margin-top: 14px; flex-shrink: 0; }
.irv-markup-option__card {
    flex: 1;
    display: block;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    transition: border-color .14s, background .14s, box-shadow .14s;
}
.irv-markup-option__card strong {
    display: block;
    font-size: 0.88rem;
    color: #0f172a;
}
.irv-markup-option__card small {
    display: block;
    margin-top: 4px;
    font-size: 0.75rem;
    line-height: 1.4;
    color: #64748b;
}
.irv-markup-option:has(input:checked) .irv-markup-option__card {
    border-color: #0d9488;
    background: #f0fdfa;
    box-shadow: 0 0 0 1px rgba(13, 148, 136, 0.15);
}
.irv-markup-modal__rule-picker {
    margin-top: 12px;
    padding: 12px 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
}
.irv-markup-modal__rule-picker.hidden { display: none !important; }
.irv-markup-modal__hint {
    margin: 12px 0 0;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #64748b;
}
.irv-markup-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px 18px;
    border-top: 1px solid #f1f5f9;
}
body.irv-markup-modal-open { overflow: hidden; }
.irv-confirm-modal__body {
    font-size: 0.9rem;
    line-height: 1.5;
    color: #334155;
}
.irv-confirm-modal__body ul {
    margin: 8px 0 0;
    padding-left: 1.2rem;
}
.irv-confirm-modal__body li { margin: 4px 0; }
.irv-confirm-modal__warning {
    background: #fef2f2 !important;
    border-color: #fecaca !important;
    color: #991b1b !important;
}
.irv-confirm-modal__warning.hidden { display: none !important; }
.irv-confirm-modal #queueConfirmModalOk.irv-btn--teal {
    background: #0f766e;
    color: #fff;
    border-color: #0f766e;
}

.irv-thumb {
    position: relative;
    width: 68px;
    height: 68px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--irv-line);
    background: #fff;
    box-shadow: 0 2px 8px rgba(15,23,42,.06);
}
.irv-thumb__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.irv-thumb--missing .irv-thumb__img { opacity: .4; filter: grayscale(.6); }
.irv-thumb--preview .irv-thumb__img { opacity: .85; }
.irv-thumb__badge {
    position: absolute;
    left: 4px;
    bottom: 4px;
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .02em;
    text-transform: uppercase;
    line-height: 1.2;
}
.irv-thumb__badge--error { background: #fee2e2; color: #991b1b; }
.irv-thumb__badge--warn { background: #fef3c7; color: #92400e; }

.irv-pill {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.35;
    white-space: nowrap;
}
.irv-pill--green { background: #dcfce7; color: #166534; }
.irv-pill--blue { background: #ccfbf1; color: #0f766e; }
.irv-pill--indigo { background: #ccfbf1; color: #0f766e; }
.irv-pill--amber { background: #fef3c7; color: #92400e; }
.irv-pill--orange { background: #ffedd5; color: #c2410c; }
.irv-pill--red { background: #fee2e2; color: #991b1b; }

.irv-row-actions {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    width: 100%;
    min-width: 0;
}
.irv-row-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 40px;
    height: auto;
    box-sizing: border-box;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.3;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    white-space: normal;
    word-break: break-word;
    hyphens: auto;
    flex-shrink: 0;
    transition: background .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
}
.irv-row-btn--violet { background: #f0fdfa; color: #0f766e; border-color: #ddd6fe; }
.irv-row-btn--indigo { background: #f0fdfa; color: #4338ca; border-color: #99f6e4; }
.irv-row-btn--sky { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
.irv-row-btn--success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.irv-row-btn--orange { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.irv-row-btn--violet:hover:not(:disabled) { background: #ede9fe; border-color: #c4b5fd; color: #5b21b6; }
.irv-row-btn--indigo:hover:not(:disabled) { background: #ccfbf1; border-color: #a5b4fc; color: #0f766e; }
.irv-row-btn--sky:hover:not(:disabled) { background: #e0f2fe; border-color: #7dd3fc; color: #075985; }
.irv-row-btn--success:hover:not(:disabled) { background: #d1fae5; border-color: #6ee7b7; color: #065f46; }
.irv-row-btn--orange:hover:not(:disabled) { background: #ffedd5; border-color: #fdba74; color: #9a3412; }
.irv-muted { font-size: 14px; color: var(--irv-muted); }

/* Forțează 14px+ în tabel — tema admin nu mai micșorează datele */
.import-review-page .irv-table tbody td,
.import-review-page .irv-table .irv-td,
.import-review-page .irv-table .irv-code,
.import-review-page .irv-table .irv-price,
.import-review-page .irv-table .irv-prod__item-value,
.import-review-page .irv-table .irv-pill,
.import-review-page .admin-table-wrap .irv-table td {
    font-size: 14px !important;
}
.import-review-page .irv-table .irv-row-btn {
    font-size: 13px !important;
    line-height: 1.3 !important;
}
.import-review-page .irv-table .irv-prod__name {
    font-size: 15px !important;
}

.irv-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 18px;
    border-top: 1px solid var(--irv-line);
    background: var(--irv-bg);
}
.irv-pagination__info { font-size: 12px; color: var(--irv-muted); }
.irv-pagination__pages { display: flex; flex-wrap: wrap; gap: 6px; }
.irv-pagenum {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--irv-line);
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    background: #fff;
    transform: none;
    box-shadow: none;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.irv-pagenum:hover:not(.is-active) {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.irv-pagenum:active,
.irv-pagenum:focus,
.irv-pagenum:focus-visible {
    transform: none;
    outline: none;
}
.irv-pagenum.is-active,
.irv-pagenum.is-active:hover {
    background: var(--irv-teal) !important;
    border-color: var(--irv-teal) !important;
    color: #fff !important;
    transform: none !important;
}

@keyframes imageScanSpin { to { transform: rotate(360deg); } }
@keyframes imageScanIndeterminate {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(250%); }
}
.image-scan-spin { animation: imageScanSpin 0.9s linear infinite; }
.import-image-scan-status {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-top: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid #bae6fd;
    background: #f0f9ff;
    color: #0c4a6e;
    font-size: 14px;
}
.import-image-scan-status__progress-bar.is-indeterminate {
    width: 38% !important;
    animation: imageScanIndeterminate 1.25s ease-in-out infinite;
}
.import-image-scan-status.is-success { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
.import-image-scan-status.is-partial { border-color: #fde68a; background: #fffbeb; color: #92400e; }
.import-image-scan-status.is-error { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
.import-image-scan-status__body { min-width: 0; flex: 1; }
.import-image-scan-status__title { font-weight: 600; }
.import-image-scan-status__detail { margin-top: 4px; font-size: 12px; line-height: 1.45; opacity: 0.85; }
.import-image-scan-status__progress-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    margin-bottom: 4px;
    font-size: 11px;
    color: #0369a1;
}
.import-image-scan-status__progress {
    width: 100%;
    max-width: 28rem;
    height: 8px;
    border-radius: 999px;
    background: #e0f2fe;
    overflow: hidden;
}
.import-image-scan-status__progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #0284c7, #0ea5e9);
    transition: width 0.25s ease;
}
.import-image-scan-status__stop {
    flex-shrink: 0;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    cursor: pointer;
}
.import-image-scan-status__stop:hover { background: #fee2e2; }
.import-row--critical-gaps { background: #fffbeb; box-shadow: inset 3px 0 0 #f59e0b; }
.import-critical-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.35;
    white-space: nowrap;
}
.import-critical-ok {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 14px;
    font-weight: 700;
}
.import-critical-cell { background: #fef2f2; color: #991b1b !important; font-weight: 600; }
.import-critical-cell-inline { font-weight: 700; }
.import-row--queue-edit { cursor: pointer; }
.import-row--queue-edit td:last-child,
.import-row--queue-edit .row-check,
.import-row--queue-edit a,
.import-row--queue-edit button { cursor: default; }
.import-queue-edit-modal {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.import-queue-edit-modal.is-open {
    display: flex !important;
    visibility: visible !important;
    pointer-events: auto !important;
}
.import-queue-edit-modal.hidden:not(.is-open) { display: none !important; }

.import-queue-preview-modal {
    position: fixed;
    inset: 0;
    z-index: 10060;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.import-queue-preview-modal.is-open {
    display: flex !important;
    visibility: visible !important;
    pointer-events: auto !important;
}
.import-queue-preview-modal.hidden:not(.is-open) { display: none !important; }
.import-queue-preview-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(2px);
}
.import-queue-preview-modal__panel {
    position: relative;
    z-index: 1;
    width: min(720px, 100%);
    max-height: min(92vh, 900px);
    overflow: auto;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.28);
    border: 1px solid #e2e8f0;
}
.import-queue-preview-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 8px;
}
.import-queue-preview-modal__title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
}
.import-queue-preview-modal__subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: #64748b;
}
.import-queue-preview-modal__close {
    border: 1px solid #d1d5db;
    background: #fff;
    border-radius: 10px;
    width: 36px;
    height: 36px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}
.import-queue-preview-tabs {
    display: flex;
    gap: 8px;
    padding: 0 20px 12px;
    border-bottom: 1px solid #e2e8f0;
}
.import-queue-preview-tab {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.import-queue-preview-tab.is-active {
    background: #0f766e;
    border-color: #0f766e;
    color: #fff;
}
.import-queue-preview-subtabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0 0 10px;
}
.import-queue-preview-subtab {
    border: 1px solid #94a3b8;
    background: #fff;
    color: #334155;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.import-queue-preview-subtab.is-active {
    background: #134e4a;
    border-color: #134e4a;
    color: #fff;
}
.import-queue-preview-mode-hint {
    margin: 0 0 12px;
    font-size: 12px;
    line-height: 1.4;
    color: #64748b;
}
.irv-preview-card__oem {
    margin-top: 4px;
    font-size: 12px;
    color: #0f766e;
    font-weight: 600;
}
.irv-preview-card--web-oem .irv-preview-card__badge {
    background: #0e7490;
}
.import-queue-preview-modal__body { padding: 16px 20px; }
.import-queue-preview-pane[hidden] { display: none !important; }
.irv-preview-card {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 40%);
}
.irv-preview-card--mp {
    background: linear-gradient(180deg, #f0fdfa 0%, #fff 45%);
}
.irv-preview-card__media {
    position: relative;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}
.irv-preview-card__media img {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
}
.irv-preview-card__badge {
    position: absolute;
    left: 8px;
    top: 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    background: #0f766e;
    color: #fff;
    border-radius: 999px;
    padding: 3px 8px;
}
.irv-preview-card__badge--mp { background: #0369a1; }
.irv-preview-card__body { padding: 14px 14px 16px 0; min-width: 0; }
.irv-preview-card__title {
    margin: 0 0 8px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
}
.irv-preview-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    font-size: 12px;
    color: #475569;
    margin-bottom: 6px;
}
.irv-preview-card__code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    background: #f1f5f9;
    border-radius: 6px;
    padding: 1px 6px;
}
.irv-preview-card__vehicle,
.irv-preview-card__category {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
}
.irv-preview-card__price {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f766e;
    margin: 8px 0;
}
.irv-preview-card--mp .irv-preview-card__price { color: #0369a1; }
/* Website: descriere simplă (antet + specs); taburile controlează titlul */
.irv-preview-card__desc {
    margin-top: 10px;
    font-size: 13px;
    line-height: 1.45;
    color: #374151;
    max-height: 220px;
    overflow: auto;
    border-top: 1px solid #f3f4f6;
    padding-top: 10px;
}
.irv-preview-card__desc p { margin: 0.45em 0 0.2em; }
.irv-preview-card__desc p:first-child { margin-top: 0; }
.irv-preview-card__desc ul {
    margin: 6px 0 0 18px;
    padding: 0;
    list-style-type: disc;
}
.irv-preview-card__desc li { margin: 0 0 0.3em; }

/* Marketplace: HTML Base.html / htmlviewer (nested disc → circle → square) */
.irv-preview-card--mp .irv-preview-card__desc {
    max-height: 320px;
}
.irv-preview-card--mp .irv-preview-card__desc ul ul {
    list-style-type: circle;
}
.irv-preview-card--mp .irv-preview-card__desc ul ul ul {
    list-style-type: square;
}
.irv-preview-card--mp .irv-preview-card__desc i {
    font-style: italic;
}
.irv-preview-web-detail {
    margin-top: 14px;
}
.irv-preview-product-tabs {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}
.irv-preview-product-tabs__navs {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}
.irv-preview-product-tabs__nav {
    border: 0;
    background: transparent;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}
.irv-preview-product-tabs__nav.is-active {
    color: #0f766e;
    border-bottom-color: #1abc9c;
    background: #fff;
}
.irv-preview-product-tabs__body {
    padding: 12px 14px;
    max-height: 280px;
    overflow: auto;
    font-size: 13px;
    color: #334155;
}
.irv-preview-product-tabs__pane { display: none; }
.irv-preview-product-tabs__pane.is-active { display: block; }
.irv-preview-product-tabs__pane table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.irv-preview-product-tabs__pane th,
.irv-preview-product-tabs__pane td {
    border: 1px solid #e2e8f0;
    padding: 6px 8px;
    text-align: left;
}
.irv-preview-product-tabs__pane ul {
    margin: 6px 0 0 18px;
    padding: 0;
    list-style-type: disc;
}
.irv-preview-product-tabs__pane ul ul { list-style-type: circle; }
.irv-preview-product-tabs__pane ul ul ul { list-style-type: square; }
.irv-preview-product-tabs__empty {
    padding: 14px;
    font-size: 13px;
    color: #94a3b8;
}
/* Carduri + prose din Formare carte (altfel Descriere arată haotic) */
.irv-preview-product-tabs__pane .besoiu-product-tab-prose-wrap .besoiu-product-tab-cards {
    margin-bottom: 1rem;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.65rem;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-card {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 0.7rem;
    background: linear-gradient(145deg, #fff 0%, #f8fafc 100%);
}
.irv-preview-product-tabs__pane .besoiu-product-tab-card__icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(26, 188, 156, 0.12);
    color: #0f766e;
    font-size: 13px;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-card__value {
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
    line-height: 1.3;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-card__label {
    font-size: 0.65rem;
    color: #64748b;
    margin-top: 0.15rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-prose {
    font-size: 0.86rem;
    line-height: 1.7;
    color: #334155;
    max-width: 72ch;
}
.irv-preview-product-tabs__pane .besoiu-product-tab-prose p {
    margin: 0 0 0.85rem;
}
.irv-preview-product-tabs__pane .tecdoc-desc-sheet {
    display: grid;
    grid-template-columns: minmax(110px, 32%) 1fr;
    gap: 0.35rem 0.75rem;
    margin: 0;
}
.irv-preview-product-tabs__pane .tecdoc-desc-sheet dt {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}
.irv-preview-product-tabs__pane .tecdoc-desc-sheet dd {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 600;
    color: #0f172a;
}
.import-queue-preview-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px 18px;
    border-top: 1px solid #e2e8f0;
}
@media (max-width: 640px) {
    .irv-preview-card { grid-template-columns: 1fr; }
    .irv-preview-card__media { border-right: 0; border-bottom: 1px solid #e2e8f0; }
    .irv-preview-card__body { padding: 12px; }
}
body.import-queue-preview-open { overflow: hidden; }
.import-queue-edit-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
}
.import-queue-edit-modal__panel {
    position: relative;
    z-index: 1;
    width: min(960px, 100%);
    max-height: calc(100vh - 32px);
    overflow: auto;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
}
.import-queue-edit-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 20px 0;
}
.import-queue-edit-modal__close {
    border: 1px solid #d1d5db;
    background: #fff;
    border-radius: 10px;
    width: 36px;
    height: 36px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}
.import-queue-edit-modal__body { padding: 16px 20px 20px; }
.import-queue-edit-modal__top {
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
@media (max-width: 640px) {
    .import-queue-edit-modal__top { grid-template-columns: 1fr; }
}
.import-queue-edit-modal__sections {
    display: grid;
    gap: 16px;
}
.import-queue-edit-section {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    background: #f8fafc;
}
.import-queue-edit-section__title {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}
.import-queue-edit-section__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
@media (max-width: 640px) {
    .import-queue-edit-section__grid { grid-template-columns: 1fr; }
}
.import-queue-edit-field--wide { grid-column: 1 / -1; }
.import-queue-edit-modal__alerts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-content: flex-start;
}
.import-queue-edit-modal__alerts.hidden { display: none; }
.import-queue-edit-alert-ok,
.import-queue-edit-alert-bad {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.import-queue-edit-alert-ok { background: #dcfce7; color: #166534; }
.import-queue-edit-alert-bad { background: #fee2e2; color: #991b1b; }
.import-queue-edit-modal__image-wrap { text-align: center; }
.import-queue-edit-modal__image {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
}
.import-queue-edit-field {
    display: grid;
    gap: 6px;
    font-size: 13px;
    color: #475569;
}
.import-queue-edit-modal__status {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
}
.import-queue-edit-modal__status-inner {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.import-queue-edit-modal__status-spinner {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 1px;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: importQueueEditSpin 0.75s linear infinite;
    opacity: 0.85;
}
.import-queue-edit-modal__status-spinner[hidden] { display: none; }
.import-queue-edit-modal__progress {
    margin-top: 10px;
    height: 6px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    overflow: hidden;
}
.import-queue-edit-modal__progress[hidden] { display: none; }
.import-queue-edit-modal__progress-bar {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #0d9488, #14b8a6);
    transition: width 0.25s ease;
}
.import-queue-edit-modal__progress-bar.is-indeterminate {
    width: 42%;
    animation: importQueueEditProgressPulse 1.4s ease-in-out infinite;
}
.import-queue-edit-modal__status-hint {
    margin-top: 8px;
    font-size: 12px;
    opacity: 0.85;
}
.import-queue-edit-modal__status-hint[hidden] { display: none; }
.import-queue-edit-modal__log {
    margin-top: 10px;
    max-height: 168px;
    overflow-y: auto;
    padding: 10px 12px;
    border-radius: 8px;
    background: #0f172a;
    color: #cbd5e1;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 11px;
    line-height: 1.45;
}
.import-queue-edit-modal__log[hidden] { display: none; }
.import-queue-edit-modal__log-line + .import-queue-edit-modal__log-line {
    margin-top: 4px;
}
.import-queue-edit-modal__log-time {
    color: #64748b;
}
.import-queue-edit-modal__log-line.is-warn { color: #fcd34d; }
.import-queue-edit-modal__log-line.is-ok { color: #6ee7b7; }

.irv-taxonomy-progress-modal__panel {
    max-width: 520px;
}
.irv-taxonomy-progress__stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 14px;
}
.irv-taxonomy-progress__stat {
    padding: 12px 14px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.irv-taxonomy-progress__stat-value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.irv-taxonomy-progress__stat-label {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}
.irv-taxonomy-progress__detail {
    margin: 12px 0 0;
    font-size: 13px;
    color: #334155;
    line-height: 1.45;
}
.irv-taxonomy-progress-modal.is-complete .import-queue-edit-modal__progress-bar {
    background: linear-gradient(90deg, #059669, #10b981);
}
.irv-taxonomy-progress-modal.is-error .import-queue-edit-modal__progress-bar {
    background: linear-gradient(90deg, #dc2626, #ef4444);
}
.import-queue-edit-modal__status.is-busy {
    display: block;
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    color: #0a3d31;
}
.import-queue-edit-modal__status.is-ok {
    display: block;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}
.import-queue-edit-modal__status.is-error {
    display: block;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.import-queue-edit-modal__image-btn {
    margin-top: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 12px;
    border-radius: 8px;
    border: 1px solid #7dd3fc;
    background: #f0f9ff;
    color: #0369a1;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.import-queue-edit-modal__image-btn:hover:not(:disabled) {
    background: #e0f2fe;
}
.import-queue-edit-modal__image-btn:disabled {
    opacity: 0.55;
    cursor: wait;
}
.import-queue-edit-modal__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 16px;
}
.import-queue-edit-modal__tools {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.import-queue-edit-modal__primary-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.import-queue-edit-modal__tool-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 0 12px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    background: #fff;
    transition: background 0.15s ease;
}
.import-queue-edit-modal__tool-btn--violet { border-color: #c4b5fd; color: #0f766e; }
.import-queue-edit-modal__tool-btn--violet:hover { background: #f0fdfa; }
.import-queue-edit-modal__tool-btn--sky { border-color: #7dd3fc; color: #0369a1; background: #f0f9ff; }
.import-queue-edit-modal__tool-btn--sky:hover:not(:disabled) { background: #e0f2fe; }
.import-queue-edit-modal__tool-btn--teal { border-color: #5eead4; color: #0f766e; background: #f0fdfa; }
.import-queue-edit-modal__tool-btn--teal:hover:not(:disabled) { background: #ccfbf1; }
.import-queue-edit-modal__tool-btn:disabled,
.import-queue-edit-modal__tool-btn.is-busy {
    opacity: 0.55;
    cursor: wait;
}
.import-queue-edit-modal__actions button.is-busy {
    opacity: 0.7;
    cursor: wait;
}
.import-queue-edit-modal__actions button,
.import-queue-edit-modal__primary-actions button {
    width: 100%;
    min-height: 40px;
    justify-content: center;
    text-align: center;
}
@media (max-width: 480px) {
    .import-queue-edit-modal__primary-actions { grid-template-columns: 1fr; }
}
@keyframes importQueueEditSpin { to { transform: rotate(360deg); } }
@keyframes importQueueEditProgressPulse {
    0% { transform: translateX(-120%); }
    50% { transform: translateX(80%); }
    100% { transform: translateX(220%); }
}
body.import-queue-edit-open { overflow: hidden; }
</style>
<script src="<?= htmlspecialchars($asyncClientUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
function currentPublishMode() {
    const bulk = document.getElementById('publishModeBulk');
    if (bulk) {
        return bulk.value;
    }
    const select = document.getElementById('publishMode');
    return select ? select.value : 'skip';
}

async function publishAllPendingAsync(publishMode, supplier) {
    const job = await BesoiuAsync.submitJob({
        type: 'import.publish_bulk',
        queue: 'import',
        payload: {
            publish_mode: publishMode,
            supplier: supplier || '',
            chunk_size: 200,
        },
        timeout_sec: 300,
        idempotencyKey: 'publish-bulk-' + (supplier || 'all') + '-' + publishMode,
    });

    if (!job.success || !job.job_id) {
        throw new Error(job.message || 'Nu am putut porni publicarea în fundal.');
    }

    return new Promise((resolve, reject) => {
        BesoiuAsync.watchJob(job.job_id, {
            onDone: (snap) => resolve((snap.result && typeof snap.result === 'object') ? snap.result : snap),
            onFailed: (snap) => reject(new Error(String(snap.error || 'Publicare eșuată.'))),
            onError: (err) => reject(err instanceof Error ? err : new Error(String(err))),
        });
    });
}

const IMPORT_REVIEW_PLACEHOLDER = <?= json_encode($importReviewPlaceholder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function importReviewImageOnError(img) {
    if (!img) return;
    const fallback = img.dataset.fallback || IMPORT_REVIEW_PLACEHOLDER;
    if (!img.dataset.errored && fallback && img.src !== fallback && !img.src.endsWith(fallback)) {
        img.dataset.errored = '1';
        img.src = fallback;
        return;
    }
    img.onerror = null;
    img.style.opacity = '0.35';
    img.style.background = '#f3f4f6';
}

function formatPriceLei(value) {
    const raw = String(value ?? '').trim().replace(',', '.');
    const num = parseFloat(raw);
    if (!Number.isFinite(num) || num <= 0) return '0 lei';
    if (Math.abs(num - Math.round(num)) < 0.001) {
        return Math.round(num).toLocaleString('ro-RO') + ' lei';
    }
    return num.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
}

let QUEUE_FILTER_TOTAL = <?= (int) $importTotal ?>;
const QUEUE_FACETS_DEFERRED = <?= !empty($facetsDeferred) ? 'true' : 'false' ?>;
const QUEUE_TOTAL_APPROX = <?= !empty($totalApprox) ? 'true' : 'false' ?>;
const QUEUE_FACETS_CONTEXT = <?= json_encode([
    'status' => $status,
    'lane' => $lane,
    'supplier' => $supplier,
    'marca' => $filterMarca,
    'model' => $filterModel,
    'motorizare' => $filterMotorizare,
    'brand' => $filterBrand,
    'an' => $filterAn,
], JSON_UNESCAPED_UNICODE) ?>;

function fillFacetDatalist(listId, values) {
    const list = document.getElementById(listId);
    if (!list || !Array.isArray(values)) return;
    list.innerHTML = values.map((value) => {
        const opt = document.createElement('option');
        opt.value = String(value);
        return opt.outerHTML;
    }).join('');
}

function applyExactQueueTotal(total) {
    const n = Number(total);
    if (!Number.isFinite(n) || n < 0) return;
    QUEUE_FILTER_TOTAL = n;
    document.querySelectorAll('[data-irv-queue-total]').forEach((el) => {
        el.textContent = String(n);
        const label = el.parentElement && el.parentElement.querySelector('.irv-stat__label');
        if (label && /se calculează/i.test(label.textContent || '')) {
            label.textContent = 'în filtrul curent';
        }
    });
}

async function loadQueueFilterFacetsLazy() {
    if (!QUEUE_FACETS_DEFERRED && !QUEUE_TOTAL_APPROX) return;
    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: 'queue_filter_facets' }, QUEUE_FACETS_CONTEXT)),
        });
        const raw = await response.text();
        const data = raw.trim() ? JSON.parse(raw) : {};
        if (!data.success) return;
        if (data.facets) {
            fillFacetDatalist('irvMarcaList', data.facets.marci || []);
            fillFacetDatalist('irvModelList', data.facets.modele || []);
            fillFacetDatalist('irvMotorizareList', data.facets.motorizari || []);
            fillFacetDatalist('irvBrandList', data.facets.brands || []);
            fillFacetDatalist('irvAnList', data.facets.ani || []);
        }
        if (typeof data.total === 'number') {
            applyExactQueueTotal(data.total);
        }
    } catch (err) {
        console.warn('[importreview] facets/count lazy load', err);
    }
}

if (QUEUE_FACETS_DEFERRED || QUEUE_TOTAL_APPROX) {
    if (window.requestIdleCallback) {
        window.requestIdleCallback(() => { loadQueueFilterFacetsLazy(); }, { timeout: 2500 });
    } else {
        window.setTimeout(() => { loadQueueFilterFacetsLazy(); }, 400);
    }
}

const __importReviewReinit = !!window.__importReviewPageBound;
if (__importReviewReinit) {
    console.warn('[importreview] Script re-executat — listenerii globali nu se mai atașează o a doua oară.');
}
window.__importReviewPageBound = true;

const queueConfirmModal = document.getElementById('queueConfirmModal');
const queueConfirmModalTitle = document.getElementById('queueConfirmModalTitle');
const queueConfirmModalSubtitle = document.getElementById('queueConfirmModalSubtitle');
const queueConfirmModalBody = document.getElementById('queueConfirmModalBody');
const queueConfirmModalWarning = document.getElementById('queueConfirmModalWarning');
const queueConfirmModalOk = document.getElementById('queueConfirmModalOk');
const queueConfirmModalCancel = document.getElementById('queueConfirmModalCancel');
let queueConfirmResolver = null;
let queueNotifyReloadTimer = null;

function clearQueueNotifyReloadTimer() {
    if (queueNotifyReloadTimer) {
        window.clearTimeout(queueNotifyReloadTimer);
        queueNotifyReloadTimer = null;
    }
}

function closeQueueConfirmModal(result) {
    if (!queueConfirmModal) return;
    queueConfirmModal.classList.add('hidden');
    queueConfirmModal.classList.remove('is-open');
    queueConfirmModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('irv-markup-modal-open');
    if (typeof queueConfirmResolver === 'function') {
        const resolve = queueConfirmResolver;
        queueConfirmResolver = null;
        resolve(!!result);
    }
}

function isQueueConfirmOpen() {
    return !!(queueConfirmModal && !queueConfirmModal.classList.contains('hidden'));
}

/**
 * Popup confirmare / informare pentru acțiunile din coadă.
 * @param {{title?:string,subtitle?:string,bodyHtml?:string,warning?:string,confirmLabel?:string,cancelLabel?:string,danger?:boolean,notifyOnly?:boolean}} opts
 * @returns {Promise<boolean>}
 */
function queueConfirm(opts) {
    opts = opts || {};
    return new Promise((resolve) => {
        if (!queueConfirmModal || !queueConfirmModalOk) {
            const fallback = opts.notifyOnly
                ? true
                : window.confirm([opts.title, opts.subtitle, opts.warning].filter(Boolean).join('\n\n'));
            resolve(!!fallback);
            return;
        }
        // Închide confirmarea anterioară (evită Promise orphan).
        if (typeof queueConfirmResolver === 'function') {
            const prev = queueConfirmResolver;
            queueConfirmResolver = null;
            prev(false);
        }
        queueConfirmResolver = resolve;
        if (queueConfirmModalTitle) {
            queueConfirmModalTitle.textContent = opts.title || 'Confirmă acțiunea';
        }
        if (queueConfirmModalSubtitle) {
            queueConfirmModalSubtitle.textContent = opts.subtitle || '';
            queueConfirmModalSubtitle.hidden = !opts.subtitle;
        }
        if (queueConfirmModalBody) {
            queueConfirmModalBody.innerHTML = opts.bodyHtml || '';
        }
        if (queueConfirmModalWarning) {
            const warn = String(opts.warning || '').trim();
            queueConfirmModalWarning.textContent = warn;
            queueConfirmModalWarning.classList.toggle('hidden', warn === '');
        }
        queueConfirmModalOk.textContent = opts.confirmLabel || (opts.notifyOnly ? 'OK' : 'Confirmă');
        queueConfirmModalOk.classList.toggle('irv-btn--danger', opts.danger !== false && !opts.notifyOnly);
        queueConfirmModalOk.classList.toggle('irv-btn--teal', !!opts.notifyOnly || opts.danger === false);
        if (queueConfirmModalCancel) {
            queueConfirmModalCancel.textContent = opts.cancelLabel || 'Anulează';
            queueConfirmModalCancel.hidden = !!opts.notifyOnly;
        }
        if (queueConfirmModal.parentElement !== document.body) {
            document.body.appendChild(queueConfirmModal);
        }
        queueConfirmModal.classList.remove('hidden');
        queueConfirmModal.classList.add('is-open');
        queueConfirmModal.setAttribute('aria-hidden', 'false');
        queueConfirmModal.style.removeProperty('display');
        document.body.classList.add('irv-markup-modal-open');
        queueConfirmModalOk.focus();
    });
}

async function queueNotify(opts) {
    opts = opts || {};
    const autoMs = Number(opts.autoReloadMs || 0);
    clearQueueNotifyReloadTimer();
    await queueConfirm({
        title: opts.title || 'Rezultat',
        subtitle: opts.subtitle || (opts.reload && autoMs > 0 ? 'Pagina se reîncarcă automat…' : ''),
        bodyHtml: opts.bodyHtml || ('<p>' + (opts.message || 'Gata.') + '</p>'),
        warning: opts.warning || '',
        confirmLabel: opts.confirmLabel || (opts.reload ? 'OK — reîncarcă' : 'OK'),
        notifyOnly: true,
        danger: false,
    });
    // Reload doar după dismiss (OK / Escape) — timer-ul nu mai pornește „în aer”.
    if (!opts.reload) return;
    if (autoMs > 0) {
        queueNotifyReloadTimer = window.setTimeout(() => {
            queueNotifyReloadTimer = null;
            window.location.reload();
        }, autoMs);
        return;
    }
    window.location.reload();
}

if (queueConfirmModal && !__importReviewReinit) {
    queueConfirmModal.querySelectorAll('[data-close-queue-confirm]').forEach((el) => {
        el.addEventListener('click', () => closeQueueConfirmModal(false));
    });
    queueConfirmModalOk?.addEventListener('click', () => closeQueueConfirmModal(true));
}

async function importAction(payload, options) {
    options = options || {};
    const addActions = ['add_one', 'add_all_pending', 'add_selected'];
    const imageJobActions = ['refresh_images_start', 'refresh_images_step', 'refresh_images_cancel', 'refresh_images_live'];
    if (addActions.includes(payload.action) && !payload.publish_mode) {
        payload.publish_mode = currentPublishMode();
    }
    const busyLabel = addActions.includes(payload.action)
        ? 'Se publica in magazin...'
        : null;

    if (busyLabel) {
        document.querySelectorAll('#addAll, #addSelected, .add-one').forEach(btn => {
            btn.dataset.prevText = btn.textContent;
            btn.disabled = true;
            if (btn.id === 'addAll' || btn.id === 'addSelected') {
                btn.textContent = busyLabel;
            }
        });
    }

    const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload),
        signal: imageJobActions.includes(payload.action)
            ? imageScanFetchSignal(payload.action)
            : undefined,
    }).catch(networkError => {
        alert('Cererea a esuat: ' + (networkError && networkError.message ? networkError.message : 'retea'));
        if (busyLabel) {
            document.querySelectorAll('#addAll, #addSelected, .add-one').forEach(btn => {
                btn.disabled = false;
                if (btn.dataset.prevText) btn.textContent = btn.dataset.prevText;
            });
        }
        return null;
    });
    if (!response) {
        return { success: false, message: 'Cererea a esuat.' };
    }
    const raw = await response.text();
    let result;
    try {
        result = raw.trim() ? JSON.parse(raw) : {};
    } catch (parseError) {
        const snippet = raw.replace(/\s+/g, ' ').trim().slice(0, 240);
        const message = typeof importQueueEditFriendlyApiMessage === 'function'
            ? importQueueEditFriendlyApiMessage(snippet)
            : (snippet.includes('<!DOCTYPE') || snippet.includes('<html')
                ? 'Serverul a returnat HTML in loc de JSON (timeout proxy).'
                : ('Raspuns invalid de la server: ' + (snippet || 'gol')));
        if (!imageJobActions.includes(payload.action) && !options.quiet) {
            if (typeof queueNotify === 'function') {
                await queueNotify({ title: 'Eroare', message: message });
            } else {
                alert(message);
            }
        }
        if (busyLabel) {
            document.querySelectorAll('#addAll, #addSelected, .add-one').forEach(btn => {
                btn.disabled = false;
                if (btn.dataset.prevText) btn.textContent = btn.dataset.prevText;
            });
        }
        return { success: false, message };
    }
    if (!imageJobActions.includes(payload.action) && !options.quiet) {
        if (typeof queueNotify === 'function') {
            await queueNotify({
                title: result.success ? 'Gata' : 'Atenție',
                message: result.message || 'Gata.',
                reload: !!(result.success && !addActions.includes(payload.action) && !options.skipReload),
            });
            if (result.success && addActions.includes(payload.action)) {
                window.location.href = result.redirect || '/admin/product';
            }
            return result;
        }
        alert(result.message || 'Gata.');
    }
    if (result.success) {
        if (!imageJobActions.includes(payload.action) && Array.isArray(result.errors) && result.errors.length) {
            alert('Erori RapidAPI:\n\n' + result.errors.join('\n') + (result.log_file ? '\n\nLog: ' + result.log_file : ''));
        }
        if (addActions.includes(payload.action)) {
            window.location.href = result.redirect || '/admin/product';
            return result;
        }
        if (!imageJobActions.includes(payload.action) && !options.skipReload) {
            window.location.reload();
        }
        return result;
    }

    if (imageJobActions.includes(payload.action)) {
        return result;
    }

    if (busyLabel) {
        document.querySelectorAll('#addAll, #addSelected, .add-one').forEach(btn => {
            btn.disabled = false;
            if (btn.dataset.prevText) {
                btn.textContent = btn.dataset.prevText;
            }
        });
    }
    return result;
}

const IMAGE_JOB_DELAY_MS = 400;
const IMAGE_JOB_STEP_MAX_RETRIES = 4;

async function imageScanAsyncWorkersAvailable() {
    try {
        const res = await fetch('/admin/api/jobs_health_endpoint.php', {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!res.ok) {
            return false;
        }
        const data = await res.json();
        // Doar workerii pe coada „images” — altfel job-ul rămâne pending la 0%.
        return Number(data?.workers_active?.images || 0) > 0;
    } catch (error) {
        console.warn('[importreview] jobs health check', error);
        return false;
    }
}

function imageScanNowClock() {
    try {
        return new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (error) {
        return new Date().toLocaleTimeString();
    }
}
const REFRESH_IMAGES_BUTTON_LABEL = <?= json_encode($refreshImagesButtonLabel, JSON_UNESCAPED_UNICODE) ?>;
const REFRESH_IMAGES_CONFIRM_SINGLE = <?= json_encode($refreshImagesConfirmSingle, JSON_UNESCAPED_UNICODE) ?>;
const REFRESH_IMAGES_CONFIRM_MANY = <?= json_encode($refreshImagesConfirmMany, JSON_UNESCAPED_UNICODE) ?>;
const IMAGE_SCAN_API_URL = <?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

let imageScanNavAbort = null;
let imageScanReloadTimer = null;
let imageScanPageLeaving = false;

function imageScanBeginSession() {
    imageScanPageLeaving = false;
    if (imageScanReloadTimer) {
        clearTimeout(imageScanReloadTimer);
        imageScanReloadTimer = null;
    }
    if (imageScanNavAbort) {
        imageScanNavAbort.abort();
    }
    imageScanNavAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
}

function imageScanCancelJobBeacon(jobId) {
    const id = String(jobId || '').trim();
    if (!id || !IMAGE_SCAN_API_URL) return;
    const body = JSON.stringify({ action: 'refresh_images_cancel', job_id: id });
    try {
        if (typeof navigator.sendBeacon === 'function') {
            navigator.sendBeacon(IMAGE_SCAN_API_URL, new Blob([body], { type: 'application/json' }));
            return;
        }
        fetch(IMAGE_SCAN_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body,
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    } catch (e) {}
}

function imageScanTeardownOnLeave() {
    imageScanPageLeaving = true;
    imageScanUi.abortScan = true;
    if (imageScanReloadTimer) {
        clearTimeout(imageScanReloadTimer);
        imageScanReloadTimer = null;
    }
    if (imageScanNavAbort) {
        imageScanNavAbort.abort();
        imageScanNavAbort = null;
    }
    if (imageScanUi.jobId) {
        imageScanCancelJobBeacon(imageScanUi.jobId);
        imageScanUi.jobId = '';
    }
    imageScanSetBodyActive(false);
    imageScanDismissPageLoader();
    if (imageScanUi.panel) {
        imageScanUi.panel.hidden = true;
        imageScanUi.panel.style.removeProperty('display');
    }
}

function imageScanScheduleReload(delayMs) {
    if (imageScanPageLeaving) return;
    if (imageScanReloadTimer) {
        clearTimeout(imageScanReloadTimer);
    }
    imageScanReloadTimer = setTimeout(() => {
        imageScanReloadTimer = null;
        if (!imageScanPageLeaving) {
            window.location.reload();
        }
    }, delayMs);
}

function imageScanIsFetchTimeout(error) {
    if (!error) return false;
    if (error.name === 'TimeoutError') return true;
    const msg = String(error.message || error.reason || '').toLowerCase();
    return msg.includes('timeout') || msg.includes('timed out');
}

function imageScanIsRetriableFetchError(error) {
    if (!error || error.name === 'AbortError') return false;
    if (imageScanIsFetchTimeout(error)) return true;
    const msg = String(error.message || '').toLowerCase();
    return msg.includes('network') || msg.includes('failed to fetch') || msg.includes('load failed');
}

function imageScanFetchSignal(action) {
    /* Fără AbortSignal.timeout — pasul step poate dura până la max_execution_time PHP (~120s).
       Timeout client produce „signal timed out" la 0% deși job-ul rulează AJAX pe server. */
    if (imageScanNavAbort && imageScanNavAbort.signal) {
        return imageScanNavAbort.signal;
    }
    return undefined;
}

function imageScanBindNavCleanup() {
    if (window.__imageScanNavBound) return;
    window.__imageScanNavBound = true;

    window.addEventListener('pagehide', () => imageScanTeardownOnLeave());
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        imageScanPageLeaving = false;
        imageScanUi.abortScan = true;
        imageScanSetBodyActive(false);
        imageScanDismissPageLoader();
        if (imageScanUi.panel) {
            imageScanUi.panel.hidden = true;
            imageScanUi.panel.style.removeProperty('display');
        }
        imageScanUi.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
    });

    document.addEventListener('click', event => {
        if (!document.body.classList.contains('import-image-scan-active')) return;
        const link = event.target.closest('a[href]');
        if (!link) return;
        const href = (link.getAttribute('href') || '').trim();
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.closest('#imageScanStatus[data-besoiu-block="image-scan-dock"]')) return;
        imageScanTeardownOnLeave();
    }, true);
}
imageScanBindNavCleanup();

function paintBeforeAsync() {
    return new Promise(resolve => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
}

function imageScanSetDisplay(el, value) {
    if (!el) return;
    if (!value || value === 'none') {
        el.hidden = true;
        el.style.removeProperty('display');
        return;
    }
    el.hidden = false;
    el.style.setProperty('display', value, 'important');
}

function imageScanDismissPageLoader() {
    document.querySelectorAll('.page-loader').forEach(loader => {
        loader.classList.add('hidden', 'opacity-0');
        loader.setAttribute('aria-hidden', 'true');
    });
}

function imageScanSetBodyActive(active) {
    document.body.classList.toggle('import-image-scan-active', !!active);
    if (active) {
        document.body.setAttribute('data-image-scan-active', '1');
    } else {
        document.body.removeAttribute('data-image-scan-active');
    }
}

const imageScanUi = {
    panel: null,
    icon: null,
    title: null,
    detail: null,
    progressWrap: null,
    progressBar: null,
    progressPct: null,
    button: null,
    stopButton: null,
    buttonIcon: null,
    buttonLabel: null,
    jobId: '',
    legacyJobId: '',
    legacyPollTimer: null,
    abortScan: false,
    isScanning: false,
    init() {
        this.panel = document.querySelector('#imageScanStatus[data-besoiu-block="image-scan-dock"]')
            || document.getElementById('imageScanStatus');
        this.icon = document.getElementById('imageScanIcon');
        this.title = document.getElementById('imageScanTitle');
        this.detail = document.getElementById('imageScanDetail');
        this.progressWrap = document.querySelector('[data-besoiu-block="image-scan-progress"]')
            || document.getElementById('imageScanProgress');
        this.progressBar = document.querySelector('[data-besoiu-block="image-scan-progress-bar"]')
            || document.getElementById('imageScanProgressBar');
        this.progressPct = document.querySelector('[data-besoiu-block="image-scan-progress-pct"]')
            || document.getElementById('imageScanProgressPct');
        this.button = document.querySelector('[data-besoiu-action="refresh-images"]')
            || document.getElementById('refreshImages');
        this.stopButton = document.querySelector('[data-besoiu-action="refresh-images-stop"]')
            || document.getElementById('refreshImagesStop');
        this.buttonIcon = document.getElementById('refreshImagesIcon');
        this.buttonLabel = document.getElementById('refreshImagesLabel');
        if (this.panel && this.panel.parentElement !== document.body) {
            document.body.appendChild(this.panel);
        }
    },
    svg(name) {
        const icons = {
            spin: '<svg class="image-scan-spin text-sky-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
            ok: '<svg class="text-emerald-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
            warn: '<svg class="text-amber-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
            error: '<svg class="text-red-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
            search: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>',
        };
        return icons[name] || '';
    },
    setProgress(value) {
        if (!this.progressWrap || !this.progressBar) return;
        this.progressWrap.hidden = false;
        this.progressWrap.removeAttribute('hidden');
        this.progressWrap.style.setProperty('display', 'block', 'important');
        const pct = Math.max(0, Math.min(100, Number(value) || 0));
        this.progressBar.classList.remove('is-indeterminate');
        this.progressBar.style.width = pct + '%';
        if (this.progressPct) {
            this.progressPct.textContent = pct.toFixed(1).replace(/\.0$/, '') + '%';
        }
    },
    setFetching(active) {
        if (!this.progressWrap || !this.progressBar) return;
        this.progressWrap.hidden = false;
        this.progressWrap.removeAttribute('hidden');
        this.progressWrap.style.setProperty('display', 'block', 'important');
        this.progressBar.classList.toggle('is-indeterminate', !!active);
        if (active && this.progressPct) {
            this.progressPct.textContent = '…';
        }
    },
    setButtonState(mode, label) {
        if (!this.button || !this.buttonIcon || !this.buttonLabel) return;
        this.button.disabled = mode === 'scanning';
        this.buttonLabel.textContent = label;
        this.buttonIcon.innerHTML = mode === 'scanning' ? this.svg('spin') : this.svg('search');
        if (this.stopButton) {
            this.stopButton.hidden = mode !== 'scanning';
        }
    },
    show(state, title, detail) {
        if (!this.panel) return;
        const stateClass = {
            scanning: 'is-scanning',
            success: 'is-success',
            partial: 'is-partial',
            error: 'is-error',
            idle: '',
        };
        this.panel.className = 'import-image-scan-status import-image-scan-dock' + (stateClass[state] ? (' ' + stateClass[state]) : '');
        this.panel.setAttribute('data-besoiu-block', 'image-scan-dock');
        if (state === 'idle') {
            this.panel.hidden = true;
            this.panel.style.removeProperty('display');
        } else {
            this.panel.hidden = false;
            this.panel.style.setProperty('display', 'flex', 'important');
        }
        imageScanSetBodyActive(state === 'scanning');
        if (state === 'scanning' && this.progressWrap) {
            this.progressWrap.hidden = false;
            this.progressWrap.removeAttribute('hidden');
            this.progressWrap.style.setProperty('display', 'block', 'important');
        }
        if (this.icon) {
            this.icon.innerHTML = this.svg(state === 'scanning' ? 'spin' : state === 'success' ? 'ok' : state === 'partial' ? 'warn' : 'error');
        }
        if (this.title) this.title.textContent = title;
        if (this.detail) this.detail.textContent = detail;
        if (state !== 'scanning' && this.stopButton) {
            this.stopButton.hidden = true;
        }
    },
    finishFromResult(result) {
        const totals = {
            updated: Number(result?.updated || 0),
            scanned: Number(result?.scanned || 0),
            failed: Number(result?.failed || 0),
            kept: Number(result?.kept || 0),
            skipped: Number(result?.skipped || 0),
        };
        const apiStatus = result?.api_status || 'ok';
        const allErrors = Array.isArray(result?.errors) ? result.errors : [];
        const stats = 'Scanate: ' + totals.scanned + ' | Gasite: ' + totals.updated + ' | Pastrate: ' + totals.kept + ' | Fara imagine: ' + totals.failed + ' | Deja cu imagine: ' + totals.skipped;

        this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
        this.setProgress(100);

        if (totals.updated > 0) {
            this.show(totals.failed === 0 && apiStatus === 'ok' ? 'success' : 'partial',
                totals.failed === 0 ? 'Merge — imagini gasite' : 'Partial — unele imagini gasite',
                stats + (imageScanPageLeaving ? '.' : '. Se reincarca pagina...'));
            if (!imageScanPageLeaving) {
                imageScanScheduleReload(1500);
            }
            return;
        }

        if (totals.kept > 0 && totals.failed === 0) {
            this.show('success', 'Imagini existente pastrate',
                stats + '. Pipeline-ul n-a inlocuit imaginile curente (sunt deja valide).');
            if (!imageScanPageLeaving) {
                imageScanScheduleReload(1500);
            }
            return;
        }

        if (apiStatus === 'not_subscribed') {
            this.show('error', 'Nu merge — RapidAPI neabonat', stats + '. Aboneaza-te la auto-parts-catalog pe rapidapi.com.');
        } else if (allErrors.some(e => /SCRAPE_DO_TOKEN|scrape\.do/i.test(e))) {
            this.show('error', 'Nu merge — token scrape.do', stats + '. Adauga SCRAPE_DO_TOKEN in admin/.env si reincearca.');
        } else if (apiStatus === 'rate_limit') {
            this.show('error', 'Nu merge acum — limita API', stats + '. Limita depasita — incearca mai tarziu.');
        } else if (totals.scanned === 0) {
            this.show('partial', 'Nimic de scanat', 'Toate produsele filtrate au deja imagine.');
        } else {
            this.show('error', 'Nu merge — nici o imagine noua', stats);
        }

        if (allErrors.length && this.detail) {
            this.detail.textContent += ' Erori: ' + allErrors.slice(0, 5).join(' | ');
        }
    },
    async pollLegacyLiveStatus(jobId) {
        const id = String(jobId || '').trim();
        if (!id || this.abortScan || imageScanPageLeaving) return;
        try {
            const live = await importAction({ action: 'refresh_images_live', job_id: id });
            if (!live || !live.success || !live.status) return;
            const st = live.status;
            const pct = Number(st.progress || 0);
            if (pct > 0) {
                this.setProgress(pct);
            }
            const logs = Array.isArray(st.live_log) ? st.live_log : [];
            const last = logs.length ? logs[logs.length - 1] : null;
            const lastMsg = last && last.msg ? String(last.msg) : '';
            const msg = String(st.message || lastMsg || 'Procesez...');
            this.show(
                'scanning',
                'Scanare activa — ' + (pct > 0 ? (pct.toFixed(0) + '%') : 'în curs'),
                msg + (lastMsg && lastMsg !== msg ? ' · ' + lastMsg : '')
            );
        } catch (e) {
            /* ignore transient live poll errors */
        }
    },
    async stopJob() {
        this.abortScan = true;
        if (this.jobId) {
            try {
                if (this.legacyJobId) {
                    await importAction({ action: 'refresh_images_cancel', job_id: this.legacyJobId });
                }
                await fetch('/admin/api/jobs_endpoint.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'cancel', job_id: this.jobId }),
                }).catch(() => {});
            } catch (e) {}
        }
        this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
        imageScanSetBodyActive(false);
        this.show('partial', 'Scanare oprita', 'Procesul a fost oprit. Poti reporni scanarea oricand.');
    },
    buildImageScanIdempotencyKey(payload) {
        const ids = Array.isArray(payload.ids) ? payload.ids.slice().sort().join(',') : '';
        const supplier = String(payload.supplier || '');
        const force = payload.force ? '1' : '0';
        return 'refresh-images-' + supplier + '-' + force + '-' + (ids || 'all');
    },
    async run(payload) {
        if (this.isScanning) return;
        this.isScanning = true;
        this.init();
        imageScanBeginSession();
        this.abortScan = false;
        this.jobId = '';
        this.legacyJobId = '';
        const countLabel = payload.ids && payload.ids.length
            ? (payload.ids.length === 1 ? '1 produs selectat' : payload.ids.length + ' produse selectate')
            : 'produsele filtrate';

        imageScanDismissPageLoader();
        imageScanSetBodyActive(true);
        this.setButtonState('scanning', 'Se scaneaza...');
        this.setProgress(0);
        this.show('scanning', 'Pornesc scanarea...', 'Pregatesc job-ul pentru ' + countLabel + '.');
        await paintBeforeAsync();

        try {
            // Fără worker pe coada images, job-ul async rămâne pending la 0% (ca în screenshot).
            // Legacy start+step pornește runner CLI dedicat și actualizează progresul real.
            const hasImageWorkers = typeof BesoiuAsync !== 'undefined'
                && await imageScanAsyncWorkersAvailable();
            if (!hasImageWorkers) {
                this.show(
                    'scanning',
                    'Scanare activa (mod direct)',
                    'Nu e worker async pe coada images — rulez scanarea pas cu pas + runner CLI. Vezi progresul aici.'
                );
                await this.runLegacyPolling(payload);
                return;
            }

            const created = await BesoiuAsync.submitJob({
                type: 'import.refresh_images',
                queue: 'images',
                payload: {
                    ids: payload.ids || [],
                    supplier: payload.supplier || '',
                    force: !!payload.force,
                    circuit_service: 'rapidapi',
                },
                timeout_sec: 600,
                idempotencyKey: this.buildImageScanIdempotencyKey(payload),
            });

            if (!created.success || !created.job_id) {
                throw new Error(created.message || 'Nu pot porni scanarea in fundal.');
            }

            this.jobId = created.job_id;
            this.show('scanning', 'Scanare in fundal activa', 'Worker pe coada images proceseaza. Poti ramane pe pagina.');

            let pendingSince = Date.now();
            let lastStatus = 'pending';
            await new Promise((resolve, reject) => {
                const watcher = BesoiuAsync.watchJob(this.jobId, {
                    onStatus: (snap) => {
                        if (this.abortScan || imageScanPageLeaving) {
                            watcher.close();
                            resolve(null);
                            return;
                        }
                        if (snap.legacy_job_id) {
                            this.legacyJobId = snap.legacy_job_id;
                        }
                        const st = String(snap.status || '');
                        if (st !== lastStatus) {
                            lastStatus = st;
                            if (st === 'pending') pendingSince = Date.now();
                        }
                        // Job blocat în pending >12s fără worker → fallback legacy.
                        if (st === 'pending' && (Date.now() - pendingSince) > 12000) {
                            watcher.close();
                            reject(new Error('WORKER_PENDING_TIMEOUT'));
                            return;
                        }
                        this.setProgress(snap.progress || 0);
                        const detail = st === 'pending'
                            ? 'In coada (pending) — astept worker... ' + (snap.message || '')
                            : (snap.message || 'Procesez produse...');
                        this.show('scanning', 'Scanare in fundal...', detail);
                    },
                    onDone: (snap) => resolve(snap),
                    onFailed: (snap) => reject(new Error(String(snap.error || 'Scanare esuata.'))),
                    onError: (err) => reject(err instanceof Error ? err : new Error(String(err))),
                });
            }).then((snap) => {
                if (!snap || this.abortScan || imageScanPageLeaving) {
                    return;
                }
                imageScanSetBodyActive(false);
                this.finishFromResult((snap.result && typeof snap.result === 'object') ? snap.result : {});
            });
        } catch (error) {
            if (imageScanPageLeaving || (error && error.name === 'AbortError')) {
                imageScanSetBodyActive(false);
                return;
            }
            // Fallback automat dacă async rămâne blocat.
            if (error && String(error.message || '') === 'WORKER_PENDING_TIMEOUT') {
                this.show('scanning', 'Worker indisponibil', 'Job-ul async a ramas la 0% — trec pe scanare directa...');
                await this.runLegacyPolling(payload);
                return;
            }
            this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
            imageScanSetBodyActive(false);
            const errMsg = (error && error.message) ? error.message : 'Cererea a esuat.';
            this.show('error', 'Eroare la scanare', errMsg);
        } finally {
            this.isScanning = false;
            if (imageScanPageLeaving) {
                imageScanSetBodyActive(false);
                imageScanDismissPageLoader();
            }
        }
    },
    async runLegacyPolling(payload) {
        try {
            let start = null;
            let startAttempts = 0;
            while (startAttempts <= IMAGE_JOB_STEP_MAX_RETRIES) {
                try {
                    this.setFetching(true);
                    start = await importAction(Object.assign({}, payload, {
                        action: 'refresh_images_start',
                        force: !!payload.force,
                    }));
                    this.setFetching(false);
                    break;
                } catch (startError) {
                    this.setFetching(false);
                    if (this.abortScan || imageScanPageLeaving) return;
                    if (!imageScanIsRetriableFetchError(startError) || startAttempts >= IMAGE_JOB_STEP_MAX_RETRIES) {
                        throw startError;
                    }
                    startAttempts++;
                    this.show(
                        'scanning',
                        'Pornesc scanarea in fundal...',
                        'Astept raspuns server (incercare ' + startAttempts + '/' + IMAGE_JOB_STEP_MAX_RETRIES + ') — job-ul ruleaza pe server, nu in browser.'
                    );
                    await new Promise(resolve => setTimeout(resolve, 2500));
                }
            }
            if (!start?.success) {
                this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
                imageScanSetBodyActive(false);
                this.show(start?.skipped > 0 ? 'partial' : 'error',
                    start?.skipped > 0 ? 'Nimic de scanat' : 'Nu pot porni scanarea',
                    start?.message || 'Incearca din nou.');
                return;
            }

            this.jobId = start.job_id || '';
            this.legacyJobId = this.jobId;
            const total = Number(start.total || 0);
            const spawnedHint = start.spawned
                ? 'Runner CLI pornit.'
                : 'Browserul avansează pas cu pas (AJAX).';
            this.show(
                'scanning',
                'Scanare activa — merge',
                '0 / ' + total + ' produse. ' + spawnedHint + ' Poti ramane pe pagina.'
            );
            this.setProgress(total > 0 ? 1 : 0);

            let steps = 0;
            const MAX_STEPS = 500;
            while (!this.abortScan && !imageScanPageLeaving && steps < MAX_STEPS) {
                steps++;
                await new Promise(resolve => setTimeout(resolve, IMAGE_JOB_DELAY_MS));
                if (this.abortScan || imageScanPageLeaving) break;

                let step = null;
                let stepAttempts = 0;
                while (stepAttempts <= IMAGE_JOB_STEP_MAX_RETRIES) {
                    let liveTimer = null;
                    try {
                        this.setFetching(true);
                        // În timpul pasului AJAX (poate dura 30–120s) citim meta live ca să nu rămână „0/1”.
                        liveTimer = window.setInterval(() => {
                            this.pollLegacyLiveStatus(this.jobId);
                        }, 1500);
                        this.pollLegacyLiveStatus(this.jobId);
                        step = await importAction({
                            action: 'refresh_images_step',
                            job_id: this.jobId,
                        });
                        this.setFetching(false);
                        break;
                    } catch (stepError) {
                        this.setFetching(false);
                        if (this.abortScan || imageScanPageLeaving) break;
                        if (!imageScanIsRetriableFetchError(stepError) || stepAttempts >= IMAGE_JOB_STEP_MAX_RETRIES) {
                            throw stepError;
                        }
                        stepAttempts++;
                        this.show(
                            'scanning',
                            'Scanare in fundal...',
                            'Serverul proceseaza produsul — reincerc pasul '
                                + stepAttempts + '/' + IMAGE_JOB_STEP_MAX_RETRIES + '...'
                        );
                        await new Promise(resolve => setTimeout(resolve, 2000));
                    } finally {
                        if (liveTimer) {
                            window.clearInterval(liveTimer);
                        }
                    }
                }
                if (this.abortScan || imageScanPageLeaving) break;
                if (!step) break;

                if (!step?.success) {
                    this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
                    imageScanSetBodyActive(false);
                    this.show('error', 'Eroare la scanare', step?.message || 'Pasul job-ului a esuat.');
                    return;
                }

                if (step.cancelled) {
                    imageScanSetBodyActive(false);
                    this.show('partial', 'Scanare oprita', 'Job-ul a fost anulat.');
                    this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
                    return;
                }

                const status = step.status || {};
                this.setProgress(status.progress || 0);
                this.show('scanning', 'Scanare in fundal...', status.message || 'Procesez produse...');

                if (status.done || status.failed) {
                    imageScanSetBodyActive(false);
                    this.finishFromResult(step.result || {});
                    return;
                }
            }
            if (!this.abortScan && !imageScanPageLeaving && steps >= MAX_STEPS) {
                this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
                imageScanSetBodyActive(false);
                this.show('error', 'Limita pasi atinsa', 'Scanarea legacy a atins limita de ' + MAX_STEPS + ' pasi. Foloseste workerul async sau reporneste scanarea.');
            }
        } catch (error) {
            this.setFetching(false);
            if (imageScanPageLeaving || (error && error.name === 'AbortError')) {
                imageScanSetBodyActive(false);
                return;
            }
            this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
            imageScanSetBodyActive(false);
            const errMsg = (error && error.message) ? error.message : 'Cererea a esuat.';
            const friendlyMsg = imageScanIsRetriableFetchError(error)
                ? 'Conexiunea cu serverul s-a intrerupt temporar — job-ul ruleaza AJAX pas cu pas; reincarca pagina dupa 1-2 minute sau apasa din nou В«Cauta imaginiВ».'
                : errMsg;
            this.show('error', 'Eroare la scanare', friendlyMsg);
        } finally {
            this.isScanning = false;
            if (imageScanPageLeaving) {
                imageScanSetBodyActive(false);
                imageScanDismissPageLoader();
            }
        }
    }
};

const refreshImagesStop = document.querySelector('[data-besoiu-action="refresh-images-stop"]')
    || document.getElementById('refreshImagesStop');
if (refreshImagesStop) {
    refreshImagesStop.addEventListener('click', () => imageScanUi.stopJob());
}
document.addEventListener('click', event => {
    const row = event.target.closest('.import-row');
    if (event.target.closest('[data-besoiu-action="refresh-one-image"], .refresh-one-image') && row) {
        event.preventDefault();
        event.stopPropagation();
        imageScanUi.run({
            ids: [row.dataset.id],
            force: true,
            supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>
        });
        return;
    }
    if (event.target.closest('[data-besoiu-action="reprocess-one"], .reprocess-one') && row) {
        event.preventDefault();
        event.stopPropagation();
        if (confirm('Re-procesez produsul (TecDoc + imagine + descriere)?')) {
            importAction({ action: 'reprocess_one', id: row.dataset.id });
        }
        return;
    }
    if (event.target.closest('.add-one') && row) importAction({action: 'add_one', id: row.dataset.id});
    if (event.target.closest('.exclude-one') && row && confirm('Excluzi acest produs din coada de import (draft)?')) importAction({action: 'exclude_one', id: row.dataset.id});
});
const addAll = document.getElementById('addAll');
if (addAll) addAll.addEventListener('click', async () => {
    const publishMode = currentPublishMode();
    const modeLabels = {
        skip: 'Omitere (sare duplicatele)',
        update: 'Actualizare (suprascrie duplicatele)',
        force: 'Adăugare forțată (creează și duplicate)',
    };
    const modeLabel = modeLabels[publishMode] || publishMode;
    const confirmed = await queueConfirm({
        title: 'Publică toate filtrate',
        subtitle: 'Produsele din filtrul curent vor fi publicate în magazin (job în fundal).',
        bodyHtml: '<p><strong>La duplicate:</strong> ' + modeLabel + '</p>'
            + '<p>Produsele cu date critice lipsă sunt excluse automat.</p>',
        confirmLabel: 'Publică acum',
        cancelLabel: 'Anulează',
        danger: false,
    });
    if (!confirmed) {
        return;
    }
    const supplier = <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>;
    const prevText = addAll.textContent;
    addAll.disabled = true;
    addAll.textContent = 'Se publica in magazin...';
    try {
        const result = await publishAllPendingAsync(publishMode, supplier);
        await queueNotify({
            title: 'Publicare',
            message: result.message || 'Publicare finalizată.',
            reload: true,
            confirmLabel: 'OK — deschide produse',
        });
        if (result.redirect) {
            window.location.href = result.redirect;
            return;
        }
        window.location.href = '/admin/product';
    } catch (err) {
        await queueNotify({
            title: 'Publicare eșuată',
            message: err.message || 'Publicare eșuată.',
            warning: 'Verifică job-urile async / log-urile import.',
            reload: false,
        });
        addAll.disabled = false;
        addAll.textContent = prevText;
    }
});

const selectAllRows = document.getElementById('selectAllRows');
if (selectAllRows) {
    selectAllRows.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    });
}

function selectedIds() {
    return Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
}

const addSelected = document.getElementById('addSelected');
if (addSelected) {
    addSelected.addEventListener('click', () => {
        const ids = selectedIds();
        if (!ids.length) return alert('Selecteaza cel putin un produs.');
        if (confirm('Publici produsele selectate in magazin?')) {
            importAction({action: 'add_selected', ids});
        }
    });
}

const deleteSelected = document.getElementById('deleteSelected');
const refreshImages = document.querySelector('[data-besoiu-action="refresh-images"]')
    || document.getElementById('refreshImages');
if (refreshImages) {
    refreshImages.addEventListener('click', () => {
        const ids = selectedIds();
        if (!ids.length) {
            return alert('Selecteaza cel putin un produs, sau foloseste butonul Cauta imagine de pe rand.');
        }
        const label = ids.length === 1
            ? REFRESH_IMAGES_CONFIRM_SINGLE
            : REFRESH_IMAGES_CONFIRM_MANY.replace('%d', String(ids.length));
        if (!confirm(label)) return;
        imageScanUi.run({
            ids,
            force: true,
            supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>
        });
    });
}
if (deleteSelected) {
    deleteSelected.addEventListener('click', () => {
        const ids = selectedIds();
        if (!ids.length) return alert('Selecteaza cel putin un produs.');
        if (confirm('Stergi produsele selectate din coada?')) {
            importAction({action: 'delete_selected', ids});
        }
    });
}

async function exportValidatedCsv() {
    const ids = selectedIds();
    const payload = {
        action: 'export_validated_csv',
        supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>,
    };
    if (ids.length) {
        payload.ids = ids;
    }

    const exportBtn = document.getElementById('exportValidatedCsv');
    const prevLabel = exportBtn ? exportBtn.textContent : '';
    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.textContent = 'Se genereazДѓ CSV...';
    }

    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = 'Export CSV eИ™uat.';
            try {
                const json = JSON.parse(await response.text());
                message = json.message || message;
            } catch (e) {}
            throw new Error(message);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            const json = await response.json();
            throw new Error(json.message || 'Export CSV eИ™uat.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const supplierSuffix = payload.supplier ? '_' + String(payload.supplier).toUpperCase() : '';
        link.download = 'import_queue_validated' + supplierSuffix + '_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        alert('CSV unificat descДѓrcat (' + (ids.length ? ids.length + ' selectate validate' : 'toate validate filtrate') + ').');
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export CSV eИ™uat.');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.textContent = prevLabel || 'Export CSV validat';
        }
    }
}

async function exportAutoproCsv() {
    const ids = selectedIds();
    const payload = {
        action: 'export_autopro_csv',
        supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>,
    };
    if (ids.length) {
        payload.ids = ids;
    }

    const exportBtn = document.getElementById('exportAutoproCsv');
    const prevLabel = exportBtn ? exportBtn.textContent : '';
    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.textContent = 'Se generează Excel marketplace...';
    }

    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = 'Export Excel marketplace eșuat.';
            try {
                const json = JSON.parse(await response.text());
                message = json.message || message;
            } catch (e) {}
            throw new Error(message);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            const json = await response.json();
            throw new Error(json.message || 'Export Excel marketplace eșuat.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const supplierSuffix = payload.supplier ? '_' + String(payload.supplier).toUpperCase() : '';
        const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        link.download = 'pieseauto_marketplace' + supplierSuffix + '_' + stamp + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        alert('Excel marketplace (PieseAuto) descărcat (' + (ids.length ? ids.length + ' selectate validate' : 'toate validate filtrate') + ').');
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export Excel marketplace eșuat.');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.textContent = prevLabel || 'Excel marketplace';
        }
    }
}

const exportValidatedCsvBtn = document.getElementById('exportValidatedCsv');
if (exportValidatedCsvBtn) {
    exportValidatedCsvBtn.addEventListener('click', () => {
        exportValidatedCsv();
    });
}

const exportAutoproCsvBtn = document.getElementById('exportAutoproCsv');
if (exportAutoproCsvBtn) {
    exportAutoproCsvBtn.addEventListener('click', () => {
        exportAutoproCsv();
    });
}

async function exportBaselinkerProducts() {
    const ids = selectedIds();
    const payload = {
        action: 'export_baselinker',
        supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>,
    };
    if (ids.length) {
        payload.ids = ids;
    }

    const exportBtn = document.getElementById('exportBaselinkerBtn');
    const prevLabel = exportBtn ? exportBtn.textContent : '';
    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.textContent = 'Se trimit produsele spre BaseLinker...';
    }

    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        let json = {};
        try {
            json = await response.json();
        } catch (e) {
            throw new Error('Export BaseLinker eИ™uat.');
        }

        if (!response.ok || !json.success) {
            throw new Error(json.message || 'Export BaseLinker eИ™uat.');
        }

        const sent = Number(json.sent ?? 0);
        const errors = Number(json.errors ?? 0);
        let message = sent + ' produse trimise, ' + errors + ' erori.';
        if (Array.isArray(json.error_details) && json.error_details.length) {
            message += '\n\n' + json.error_details.slice(0, 5).join('\n');
        }
        alert(message);
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export BaseLinker eИ™uat.');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.textContent = prevLabel || 'ExportДѓ produse spre BaseLinker';
        }
    }
}

const exportBaselinkerBtn = document.getElementById('exportBaselinkerBtn');
if (exportBaselinkerBtn) {
    exportBaselinkerBtn.addEventListener('click', () => {
        exportBaselinkerProducts();
    });
}

const importQueueEditModal = document.getElementById('importQueueEditModal');
const importQueueEditForm = document.getElementById('importQueueEditForm');
const importQueueEditStatus = document.getElementById('importQueueEditStatus');
const importQueueEditFields = {
    id: document.getElementById('importQueueEditId'),
    code: document.getElementById('importQueueEditCode'),
    codeInput: document.getElementById('importQueueEditCodeInput'),
    image: document.getElementById('importQueueEditImage'),
    imageSource: document.getElementById('importQueueEditImageSource'),
    alerts: document.getElementById('importQueueEditAlerts'),
    name: document.getElementById('importQueueEditName'),
    nameMarketplace: document.getElementById('importQueueEditNameMarketplace'),
    brand: document.getElementById('importQueueEditBrand'),
    marca: document.getElementById('importQueueEditMarca'),
    model: document.getElementById('importQueueEditModel'),
    motorizare: document.getElementById('importQueueEditMotorizare'),
    price: document.getElementById('importQueueEditPrice'),
    basePrice: document.getElementById('importQueueEditBasePrice'),
    stock: document.getElementById('importQueueEditStock'),
    category: document.getElementById('importQueueEditCategory'),
    subcategory: document.getElementById('importQueueEditSubcategory'),
    note: document.getElementById('importQueueEditNote'),
    oem: document.getElementById('importQueueEditOem'),
    compatibilitati: document.getElementById('importQueueEditCompatibilitati'),
};
const importQueueSubcategoriesMap = (() => {
    const el = document.getElementById('importQueueSubcategoriesByCategory');
    if (!el) return {};
    try {
        return JSON.parse(el.textContent || '{}');
    } catch (error) {
        return {};
    }
})();

function importQueueEditEnsureCategoryOption(categoryLabel) {
    const categorySelect = importQueueEditFields.category;
    if (!categorySelect || categoryLabel === '') return;
    const exists = Array.from(categorySelect.options).some(option => option.value === categoryLabel);
    if (exists) return;
    const option = document.createElement('option');
    option.value = categoryLabel;
    option.textContent = categoryLabel + ' (curenta)';
    categorySelect.appendChild(option);
}

function importQueueEditPopulateSubcategories(categoryLabel, selectedSubcategory) {
    const subcategorySelect = importQueueEditFields.subcategory;
    if (!subcategorySelect) return;
    const subcategories = Array.isArray(importQueueSubcategoriesMap[categoryLabel])
        ? importQueueSubcategoriesMap[categoryLabel]
        : [];
    subcategorySelect.innerHTML = '<option value="">— Alege subcategorie —</option>';
    let hasSelected = false;
    subcategories.forEach(subcategory => {
        const option = document.createElement('option');
        const label = String(subcategory.label || '');
        option.value = label;
        option.textContent = label;
        if (selectedSubcategory && label === selectedSubcategory) {
            option.selected = true;
            hasSelected = true;
        }
        subcategorySelect.appendChild(option);
    });
    if (selectedSubcategory && !hasSelected) {
        const currentOption = document.createElement('option');
        currentOption.value = selectedSubcategory;
        currentOption.textContent = selectedSubcategory + ' (curenta)';
        currentOption.selected = true;
        subcategorySelect.appendChild(currentOption);
    }
}

function importQueueEditSetStatus(message, ok) {
    if (!importQueueEditStatus) return;
    importQueueEditStatus.textContent = message || '';
    importQueueEditStatus.classList.remove('hidden', 'is-ok', 'is-error');
    if (!message) {
        importQueueEditStatus.classList.add('hidden');
        return;
    }
    importQueueEditStatus.classList.add(ok ? 'is-ok' : 'is-error');
}

function importQueueEditRenderAlerts(flags) {
    const alertsEl = importQueueEditFields.alerts;
    if (!alertsEl) return;
    const list = Array.isArray(flags) ? flags.filter(Boolean) : [];
    if (list.length === 0) {
        alertsEl.innerHTML = '<span class="import-queue-edit-alert-ok">Date minime OK</span>';
        alertsEl.classList.remove('hidden');
        return;
    }
    alertsEl.innerHTML = list.map(label =>
        '<span class="import-queue-edit-alert-bad">' + String(label).replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch])) + '</span>'
    ).join('');
    alertsEl.classList.remove('hidden');
}

function importQueueEditImageSourceLabel(source, trusted) {
    const normalized = String(source || '').trim();
    if (!trusted || normalized === '' || normalized === 'missing') {
        return 'Sursa imagine: Lipsa';
    }
    return 'Sursa imagine: ' + normalized;
}

function importQueueEditFill(row) {
    if (!row) return;
    if (importQueueEditFields.id) importQueueEditFields.id.value = String(row.id || '');
    const codeText = row.pCode ? ('Cod: ' + row.pCode) : '';
    if (importQueueEditFields.code) importQueueEditFields.code.textContent = codeText;
    if (importQueueEditFields.codeInput) importQueueEditFields.codeInput.value = row.pCode || '';
    if (importQueueEditFields.image) {
        importQueueEditFields.image.src = row.image || '/admin/dist/images/fakers/preview-12.jpg';
        importQueueEditFields.image.alt = row.pName || '';
        importQueueEditFields.image.style.opacity = row.imageTrusted ? '1' : '0.45';
    }
    if (importQueueEditFields.imageSource) {
        importQueueEditFields.imageSource.textContent = importQueueEditImageSourceLabel(row.imageSource, row.imageTrusted);
    }
    importQueueEditRenderAlerts(row.criticalFlags);
    const webTitle = String(row.pName || '').replace(/[|\s]+$/g, '').trim();
    const mpTitle = String(row.pNameMarketplace || row.pName || '').replace(/[|\s]+$/g, '').trim();
    if (importQueueEditFields.name) importQueueEditFields.name.value = webTitle;
    if (importQueueEditFields.nameMarketplace) importQueueEditFields.nameMarketplace.value = mpTitle;
    if (importQueueEditFields.brand) importQueueEditFields.brand.value = row.pBrand || '';
    if (importQueueEditFields.marca) importQueueEditFields.marca.value = row.pMarca || '';
    if (importQueueEditFields.model) importQueueEditFields.model.value = row.pModel || '';
    if (importQueueEditFields.motorizare) importQueueEditFields.motorizare.value = row.pMotorizare || '';
    if (importQueueEditFields.price) importQueueEditFields.price.value = row.pPrice || '';
    if (importQueueEditFields.basePrice) importQueueEditFields.basePrice.value = row.pBasePrice || '';
    if (importQueueEditFields.stock) importQueueEditFields.stock.value = row.pStock || '0';
    const categoryLabel = row.pCategory || '';
    const subcategoryLabel = row.pSubcategory || '';
    importQueueEditEnsureCategoryOption(categoryLabel);
    if (importQueueEditFields.category) importQueueEditFields.category.value = categoryLabel;
    importQueueEditPopulateSubcategories(categoryLabel, subcategoryLabel);
    if (importQueueEditFields.note) importQueueEditFields.note.value = row.pNote || '';
    if (importQueueEditFields.oem) importQueueEditFields.oem.value = row.pOem || '';
    if (importQueueEditFields.compatibilitati) importQueueEditFields.compatibilitati.value = row.pCompatibilitati || '';
}

function importQueueEditB64ToJson(b64) {
    const bin = atob(b64);
    // JSON din PHP e UTF-8 — atob singur strică diacriticele (ă/ș/ț).
    if (typeof TextDecoder !== 'undefined') {
        const bytes = Uint8Array.from(bin, (ch) => ch.charCodeAt(0));
        return new TextDecoder('utf-8').decode(bytes);
    }
    try {
        return decodeURIComponent(Array.prototype.map.call(bin, (ch) => {
            return '%' + ('00' + ch.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
    } catch (e) {
        return bin;
    }
}

function importQueueEditParseRowPayload(rowEl) {
    if (!rowEl) return null;
    // Preferă data-queue-edit (după save), altfel data-queue-edit-b64 (render PHP).
    const direct = String(rowEl.dataset.queueEdit || '').trim();
    if (direct !== '') {
        try {
            const parsed = JSON.parse(direct);
            if (parsed && parsed.id) return parsed;
        } catch (e) { /* fallthrough */ }
    }
    const b64 = String(rowEl.dataset.queueEditB64 || '').trim();
    if (b64 === '') return null;
    try {
        const parsed = JSON.parse(importQueueEditB64ToJson(b64));
        return parsed && parsed.id ? parsed : null;
    } catch (e) {
        return null;
    }
}

function importQueueEditMountToBody(modalEl) {
    if (!modalEl || !document.body || modalEl.parentElement === document.body) return;
    document.body.appendChild(modalEl);
}

function importQueueEditOpen(row) {
    if (!importQueueEditModal || !row) return;
    importQueueEditMountToBody(importQueueEditModal);
    importQueueEditFill(row);
    importQueueEditSetStatus('', true);
    importQueueEditModal.classList.remove('hidden');
    importQueueEditModal.classList.add('is-open');
    importQueueEditModal.setAttribute('aria-hidden', 'false');
    importQueueEditModal.style.removeProperty('display');
    importQueueEditModal.style.removeProperty('pointer-events');
    importQueueEditModal.style.removeProperty('visibility');
    document.body.classList.add('import-queue-edit-open');
    importQueueEditFields.name?.focus();
}

function importQueueEditClose() {
    if (!importQueueEditModal) return;
    importQueueEditModal.classList.add('hidden');
    importQueueEditModal.classList.remove('is-open');
    importQueueEditModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('import-queue-edit-open');
    importQueueEditSetStatus('', true);
}

function importQueueEditPayloadFromForm() {
    const webTitle = String(importQueueEditFields.name?.value || '').replace(/[|\s]+$/g, '').trim();
    let mpTitle = String(importQueueEditFields.nameMarketplace?.value || '').replace(/[|\s]+$/g, '').trim();
    if (mpTitle === '') {
        mpTitle = webTitle;
    }
    return {
        action: 'queue_row_save',
        id: importQueueEditFields.id?.value || '',
        pName: webTitle,
        pNameMarketplace: mpTitle,
        pBrand: importQueueEditFields.brand?.value || '',
        pMarca: importQueueEditFields.marca?.value || '',
        pModel: importQueueEditFields.model?.value || '',
        pMotorizare: importQueueEditFields.motorizare?.value || '',
        pPrice: importQueueEditFields.price?.value || '',
        pBasePrice: importQueueEditFields.basePrice?.value || '',
        pStock: importQueueEditFields.stock?.value || '',
        pCategory: importQueueEditFields.category?.value || '',
        pSubcategory: importQueueEditFields.subcategory?.value || '',
        pNote: importQueueEditFields.note?.value || '',
        pOem: importQueueEditFields.oem?.value || '',
        pCompatibilitati: importQueueEditFields.compatibilitati?.value || '',
    };
}

function importQueueEditShortText(value, limit) {
    const text = String(value || '').trim();
    if (text === '') return '—';
    return text.length > limit ? text.slice(0, limit) + '...' : text;
}

function importQueueEditApplyRowToTable(row) {
    if (!row || !row.id) return;
    const tr = document.querySelector('.import-row[data-id="' + row.id + '"]');
    if (!tr) return;
    tr.dataset.queueEdit = JSON.stringify(row);
    const webNameEl = tr.querySelector('.irv-prod__name');
    const mpNameEl = tr.querySelector('.irv-prod__marketplace');
    if (webNameEl && row.pName) {
        webNameEl.title = row.pName;
        // Păstrează eticheta WEB + titlul format.
        webNameEl.innerHTML = '<span style="display:inline-block;min-width:2.6rem;font-size:10px;font-weight:700;color:#0f766e;">WEB</span> '
            + String(row.pName).replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }
    if (mpNameEl) {
        const mp = String(row.pNameMarketplace || row.pName || '').trim() || '—';
        mpNameEl.title = mp;
        mpNameEl.innerHTML = '<span style="display:inline-block;min-width:2.6rem;font-size:10px;font-weight:700;color:#b45309;">MP</span> '
            + mp.replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }
    const nameCell = tr.querySelector('td:nth-child(' + (tr.querySelector('.row-check') ? '5' : '4') + ') .font-medium');
    if (nameCell && row.pName && !webNameEl) nameCell.textContent = row.pName;
    const brandCell = tr.querySelector('[data-queue-field="brand"]');
    const marcaCell = tr.querySelector('[data-queue-field="marca"]');
    const modelCell = tr.querySelector('[data-queue-field="model"]');
    const motorizareCell = tr.querySelector('[data-queue-field="motorizare"]');
    const priceCell = tr.querySelector('[data-queue-field="price"]');
    const stockCell = tr.querySelector('[data-queue-field="stock"]');
    const categoryCell = tr.querySelector('[data-queue-field="category"]');
    const subcategoryCell = tr.querySelector('[data-queue-field="subcategory"]');
    const noteCell = tr.querySelector('[data-queue-field="note"]');
    const oemCell = tr.querySelector('[data-queue-field="oem"]');
    if (brandCell) {
        brandCell.textContent = '';
        const strong = document.createElement('strong');
        strong.textContent = row.pBrand || '—';
        brandCell.appendChild(strong);
    }
    if (marcaCell) marcaCell.textContent = row.pMarca || '—';
    if (modelCell) modelCell.textContent = row.pModel || '—';
    if (motorizareCell) {
        motorizareCell.textContent = importQueueEditShortText(row.pMotorizare, 120);
        motorizareCell.title = row.pMotorizare || '';
    }
    if (priceCell) {
        const priceValue = String(row.pPrice || '').trim();
        const hasPrice = priceValue !== '' && Number(priceValue) > 0;
        priceCell.textContent = hasPrice ? (priceValue + ' lei') : '— (0)';
        priceCell.classList.toggle('text-emerald-700', hasPrice);
        priceCell.classList.toggle('text-red-600', !hasPrice);
        priceCell.classList.toggle('import-critical-cell-inline', !hasPrice);
    }
    if (stockCell) stockCell.textContent = row.pStock || '0';
    if (categoryCell) categoryCell.textContent = row.pCategory || '—';
    if (subcategoryCell) subcategoryCell.textContent = row.pSubcategory || '—';
    if (noteCell) {
        noteCell.title = row.pNote || '';
        noteCell.textContent = '';
        if (row.pNote) {
            const noteDiv = document.createElement('div');
            noteDiv.className = 'text-slate-700';
            noteDiv.textContent = importQueueEditShortText(row.pNote, 220);
            noteCell.appendChild(noteDiv);
        }
    }
    if (oemCell) {
        oemCell.title = row.pOem || '';
        oemCell.textContent = importQueueEditShortText(row.pOem, 160);
    }
}

if (importQueueEditFields.category) {
    importQueueEditFields.category.addEventListener('change', () => {
        importQueueEditPopulateSubcategories(importQueueEditFields.category.value || '', '');
    });
}

async function importQueueEditRunAction(action, busyLabel) {
    const id = Number(importQueueEditFields.id?.value || 0);
    if (!id) return;
    const buttons = [
        document.getElementById('importQueueEditReprocess'),
        document.getElementById('importQueueEditSyncTecdoc'),
        document.getElementById('importQueueEditSave'),
    ].filter(Boolean);
    buttons.forEach(btn => { btn.disabled = true; });
    importQueueEditSetStatus(busyLabel || 'Se proceseaza...', true);
    try {
        const payload = action === 'queue_row_save'
            ? importQueueEditPayloadFromForm()
            : { action, id };
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (result.success && result.row) {
            importQueueEditFill(result.row);
            importQueueEditApplyRowToTable(result.row);
            importQueueEditSetStatus(result.message || 'Gata.', true);
            return;
        }
        importQueueEditSetStatus(result.message || 'Eroare.', false);
    } catch (error) {
        importQueueEditSetStatus((error && error.message) ? error.message : 'Cererea a esuat.', false);
    } finally {
        buttons.forEach(btn => { btn.disabled = false; });
    }
}

document.querySelectorAll('[data-close-queue-edit]').forEach(el => {
    el.addEventListener('click', () => importQueueEditClose());
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && importQueueEditModal && !importQueueEditModal.classList.contains('hidden')) {
        importQueueEditClose();
    }
});

const importQueuePreviewModal = document.getElementById('importQueuePreviewModal');
let importQueuePreviewCurrentRow = null;

function importQueuePreviewFormatPrice(row) {
    const finalPrice = String(row.pPrice || '').trim();
    const base = String(row.pBasePrice || '').trim();
    if (finalPrice !== '' && Number(finalPrice) > 0) {
        return Number(finalPrice).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
    }
    if (base !== '' && Number(base) > 0) {
        return 'Achiziție: ' + Number(base).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
    }
    return 'Preț nedefinit';
}

function importQueuePreviewVehicle(row, channel) {
    // Marketplace: toate mărcile/modelele din compat (nu doar vehiculul principal).
    if (channel === 'marketplace') {
        const summary = String(row.compatSummary || '').trim();
        if (summary !== '') return summary;
        const compat = String(row.pCompatibilitati || '').trim();
        if (compat !== '') {
            const cut = compat.split(/Coduri\s+OE/i)[0] || compat;
            const brands = [];
            cut.split(/\r?\n/).forEach((line) => {
                const m = String(line || '').trim().match(/^([A-Z0-9][A-Z0-9\-\s]{1,24})\s*:/i);
                if (m && m[1]) brands.push(m[1].trim().toUpperCase());
            });
            if (brands.length) return [...new Set(brands)].join(' / ');
        }
    }
    const motor = String(row.pMotorizare || '').replace(/\([^)]*\)/g, '').replace(/\s+/g, ' ').trim();
    return [row.pMarca, row.pModel, motor].map((v) => String(v || '').trim()).filter(Boolean).join(' · ') || '—';
}

function importQueuePreviewCategory(row) {
    const cat = String(row.pCategory || '').trim();
    const sub = String(row.pSubcategory || '').trim();
    if (cat && sub && cat !== sub) return cat + ' › ' + sub;
    return cat || sub || '—';
}

function importQueuePreviewSetDesc(el, html) {
    if (!el) return;
    const raw = String(html || '').trim();
    if (raw === '') {
        el.innerHTML = '<em class="text-slate-400">Fără descriere</em>';
        return;
    }
    // Descrierea din coadă e HTML controlat (Base) — afișăm ca pe site.
    el.innerHTML = raw;
}

function importQueuePreviewRenderWebTabs(tabs) {
    const wrap = document.getElementById('irvPreviewWebProductTabs');
    if (!wrap) return;
    const list = Array.isArray(tabs) ? tabs.filter((t) => t && String(t.label || '').trim() !== '') : [];
    if (!list.length) {
        wrap.innerHTML = '<div class="irv-preview-product-tabs__empty">Nu sunt tab-uri active în Formare carte. Configurează-le la /admin/product-formation → Tab-uri descriere.</div>';
        return;
    }
    const nav = list.map((tab, idx) => (
        '<button type="button" class="irv-preview-product-tabs__nav' + (idx === 0 ? ' is-active' : '') + '" data-web-desc-tab="' + idx + '">'
        + String(tab.label || ('Tab ' + (idx + 1))).replace(/</g, '&lt;')
        + '</button>'
    )).join('');
    const panes = list.map((tab, idx) => (
        '<div class="irv-preview-product-tabs__pane' + (idx === 0 ? ' is-active' : '') + '" data-web-desc-pane="' + idx + '">'
        + (tab.content_html || '<em class="text-slate-400">Fără conținut</em>')
        + '</div>'
    )).join('');
    wrap.innerHTML = '<div class="irv-preview-product-tabs__navs">' + nav + '</div>'
        + '<div class="irv-preview-product-tabs__body">' + panes + '</div>';
    wrap.querySelectorAll('[data-web-desc-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const i = btn.getAttribute('data-web-desc-tab');
            wrap.querySelectorAll('[data-web-desc-tab]').forEach((b) => {
                b.classList.toggle('is-active', b.getAttribute('data-web-desc-tab') === i);
            });
            wrap.querySelectorAll('[data-web-desc-pane]').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-web-desc-pane') === i);
            });
        });
    });
}

function importQueuePreviewSetTab(channel) {
    const tab = channel === 'marketplace' ? 'marketplace' : 'website';
    document.querySelectorAll('.import-queue-preview-tab').forEach((btn) => {
        const active = btn.getAttribute('data-preview-tab') === tab;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.import-queue-preview-pane').forEach((pane) => {
        const active = pane.getAttribute('data-preview-pane') === tab;
        pane.classList.toggle('is-active', active);
        pane.hidden = !active;
    });
}

function importQueuePreviewSetWebMode(mode) {
    const webMode = mode === 'oem' ? 'oem' : 'vehicle';
    document.querySelectorAll('.import-queue-preview-subtab').forEach((btn) => {
        const active = btn.getAttribute('data-preview-web-mode') === webMode;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    const row = importQueuePreviewCurrentRow;
    if (!row) return;

    const webTitleVehicle = String(row.pName || '').trim() || '—';
    const webTitleOem = String(row.pNameOem || row.pName || '').trim() || '—';
    const title = webMode === 'oem' ? webTitleOem : webTitleVehicle;
    const brand = String(row.pBrand || '').trim() || '—';
    const code = String(row.pCode || '').trim() || '—';
    const oemCode = String(row.pOem || '').trim();
    const vehicleWeb = importQueuePreviewVehicle(row, 'website');

    const titleEl = document.getElementById('irvPreviewWebTitle');
    const brandEl = document.getElementById('irvPreviewWebBrand');
    const codeEl = document.getElementById('irvPreviewWebCode');
    const vehicleEl = document.getElementById('irvPreviewWebVehicle');
    const oemLine = document.getElementById('irvPreviewWebOemLine');
    const hint = document.getElementById('irvPreviewWebModeHint');
    const badge = document.getElementById('irvPreviewWebBadge');
    const card = document.getElementById('irvPreviewWebCard');
    const webImg = document.getElementById('irvPreviewWebImage');

    if (titleEl) titleEl.textContent = title;
    if (brandEl) brandEl.textContent = brand;
    if (codeEl) codeEl.textContent = code;
    if (vehicleEl) {
        vehicleEl.textContent = vehicleWeb;
        vehicleEl.hidden = webMode === 'oem' && oemCode !== '';
    }
    if (oemLine) {
        if (webMode === 'oem' && oemCode !== '') {
            oemLine.hidden = false;
            oemLine.textContent = 'Cod OEM: ' + oemCode;
        } else {
            oemLine.hidden = true;
            oemLine.textContent = '';
        }
    }
    if (hint) {
        hint.textContent = webMode === 'oem'
            ? 'Titlu format pentru căutare după cod OEM (canal OEM din Formare carte).'
            : 'Titlu format pentru căutare după marcă, model și motorizare (canal Website din Formare carte).';
    }
    if (badge) {
        badge.textContent = webMode === 'oem' ? 'Căutare OEM' : 'Magazin online';
    }
    if (card) {
        card.classList.toggle('irv-preview-card--web-oem', webMode === 'oem');
    }
    if (webImg) webImg.alt = title;
}

function importQueuePreviewFill(row) {
    importQueuePreviewCurrentRow = row;
    const img = row.image || '/admin/dist/images/fakers/preview-12.jpg';
    const mpTitle = String(row.pNameMarketplace || row.pName || '').trim() || '—';
    const brand = String(row.pBrand || '').trim() || '—';
    const code = String(row.pCode || '').trim() || '—';
    const vehicleMp = importQueuePreviewVehicle(row, 'marketplace');
    const category = importQueuePreviewCategory(row);
    const price = importQueuePreviewFormatPrice(row);
    // Website ≠ Marketplace: Base nested doar pe marketplace.
    const descWeb = String(row.pNoteWebsite || '').trim();
    const descMp = String(row.pNoteMarketplace || row.pNote || '').trim();

    const webImg = document.getElementById('irvPreviewWebImage');
    const mpImg = document.getElementById('irvPreviewMpImage');
    if (webImg) { webImg.src = img; }
    if (mpImg) { mpImg.src = img; mpImg.alt = mpTitle; }

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText('irvPreviewMpTitle', mpTitle);
    setText('irvPreviewMpBrand', brand);
    setText('irvPreviewMpCode', code);
    setText('irvPreviewMpVehicle', vehicleMp);
    setText('irvPreviewWebPrice', price);
    setText('irvPreviewMpPrice', price);
    setText('irvPreviewWebCategory', category);
    setText('irvPreviewMpCategory', category);
    importQueuePreviewSetDesc(document.getElementById('irvPreviewWebDesc'), descWeb);
    importQueuePreviewSetDesc(document.getElementById('irvPreviewMpDesc'), descMp);
    importQueuePreviewRenderWebTabs(row.websiteTabs || []);
    importQueuePreviewSetWebMode('vehicle');
}

function importQueuePreviewOpen(row) {
    if (!importQueuePreviewModal || !row) return;
    if (importQueuePreviewModal.parentElement !== document.body) {
        document.body.appendChild(importQueuePreviewModal);
    }
    importQueuePreviewFill(row);
    importQueuePreviewSetTab('website');
    importQueuePreviewSetWebMode('vehicle');
    importQueuePreviewModal.classList.remove('hidden');
    importQueuePreviewModal.classList.add('is-open');
    importQueuePreviewModal.setAttribute('aria-hidden', 'false');
    importQueuePreviewModal.style.removeProperty('display');
    document.body.classList.add('import-queue-preview-open');
}

function importQueuePreviewClose() {
    if (!importQueuePreviewModal) return;
    importQueuePreviewModal.classList.add('hidden');
    importQueuePreviewModal.classList.remove('is-open');
    importQueuePreviewModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('import-queue-preview-open');
    importQueuePreviewCurrentRow = null;
}

document.querySelectorAll('[data-close-queue-preview]').forEach((el) => {
    el.addEventListener('click', () => importQueuePreviewClose());
});
document.querySelectorAll('.import-queue-preview-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
        importQueuePreviewSetTab(btn.getAttribute('data-preview-tab') || 'website');
    });
});
document.querySelectorAll('.import-queue-preview-subtab').forEach((btn) => {
    btn.addEventListener('click', () => {
        importQueuePreviewSetTab('website');
        importQueuePreviewSetWebMode(btn.getAttribute('data-preview-web-mode') || 'vehicle');
    });
});
const importQueuePreviewOpenEditBtn = document.getElementById('importQueuePreviewOpenEdit');
if (importQueuePreviewOpenEditBtn) {
    importQueuePreviewOpenEditBtn.addEventListener('click', () => {
        const row = importQueuePreviewCurrentRow;
        importQueuePreviewClose();
        if (row) importQueueEditOpen(row);
    });
}
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && importQueuePreviewModal && !importQueuePreviewModal.classList.contains('hidden')) {
        importQueuePreviewClose();
    }
});

document.addEventListener('click', event => {
    if (event.target.closest('#importQueueEditModal, #importQueuePreviewModal')) return;

    // Butonul „Previzualizare” — Website / Marketplace.
    const previewBtn = event.target.closest('.queue-preview-one, [data-besoiu-action="queue-preview-one"]');
    if (previewBtn) {
        const rowFromBtn = previewBtn.closest('.import-row--queue-edit, .import-row');
        const payloadFromBtn = importQueueEditParseRowPayload(rowFromBtn);
        if (payloadFromBtn) {
            event.preventDefault();
            event.stopPropagation();
            importQueuePreviewOpen(payloadFromBtn);
        }
        return;
    }

    // Butonul „Editare” — deschide popup-ul (nu e acoperit de handlerul pe rând).
    const editBtn = event.target.closest('.queue-edit-one, [data-besoiu-action="queue-edit-one"]');
    if (editBtn) {
        const rowFromBtn = editBtn.closest('.import-row--queue-edit, .import-row');
        const payloadFromBtn = importQueueEditParseRowPayload(rowFromBtn);
        if (payloadFromBtn) {
            event.preventDefault();
            event.stopPropagation();
            importQueueEditOpen(payloadFromBtn);
        }
        return;
    }

    const row = event.target.closest('.import-row--queue-edit');
    if (!row) return;
    if (event.target.closest('a, button, input, label, select, .import-price-formation-log-link')) return;
    const payload = importQueueEditParseRowPayload(row);
    if (payload) {
        importQueueEditOpen(payload);
    }
});

if (importQueueEditForm) {
    importQueueEditForm.addEventListener('submit', event => {
        event.preventDefault();
        importQueueEditRunAction('queue_row_save', 'Se salveaza...');
    });
}

const importQueueEditReprocess = document.getElementById('importQueueEditReprocess');
if (importQueueEditReprocess) {
    importQueueEditReprocess.addEventListener('click', () => {
        if (confirm('Re-procesez produsul (TecDoc + imagine + descriere)?')) {
            importQueueEditRunAction('reprocess_one', 'Re-procesez produsul...');
        }
    });
}

const importQueueEditSyncTecdoc = document.getElementById('importQueueEditSyncTecdoc');
if (importQueueEditSyncTecdoc) {
    importQueueEditSyncTecdoc.addEventListener('click', () => {
        importQueueEditRunAction('sync_tecdoc_one', 'Sincronizez TecDoc...');
    });
}


const deleteAllQueue = document.getElementById('deleteAllQueue');
if (deleteAllQueue) {
    deleteAllQueue.addEventListener('click', async () => {
        const ok = typeof queueConfirm === 'function'
            ? await queueConfirm({
                title: 'Șterge tot din coadă',
                subtitle: 'Doar ștergere — NU publică în magazin',
                bodyHtml: '<p>Golești <strong>complet</strong> tabela <code>import_produse</code> (toate statusurile). Produsele dispar din coadă; <strong>nu</strong> sunt adăugate în magazin. Pentru publicare folosește «Publică selectate» / «Adaugă».</p>',
                warning: 'Nu se poate anula. Continuă doar dacă ești sigur.',
                confirmLabel: 'Da, șterge tot',
                danger: true,
            })
            : confirm('Golești COMPLET coada de import (toate statusurile)? Această acțiune nu se poate anula.');
        if (!ok) return;
        await importAction({ action: 'delete_all' });
    });
}

const applyTaxonomySelected = document.getElementById('applyTaxonomySelected');
if (applyTaxonomySelected) {
    applyTaxonomySelected.addEventListener('click', async () => {
        const ids = selectedIds();
        if (!ids.length) {
            return alert('Selectează cel puțin un produs.');
        }
        if (!confirm('Potrivesc categorii pe ' + ids.length + ' produse selectate?')) return;
        await importAction({ action: 'apply_taxonomy_pending', ids, scope: 'selected' });
    });
}
const applyTaxonomyFiltered = document.getElementById('applyTaxonomyFiltered');
if (applyTaxonomyFiltered) {
    applyTaxonomyFiltered.addEventListener('click', async () => {
        if (!confirm('Completez categorii pe tot filtrul curent (max. 500)?')) return;
        await importAction({
            action: 'apply_taxonomy_pending',
            scope: 'filtered',
            status: <?= json_encode($status === 'all' ? 'pending' : $status, JSON_UNESCAPED_UNICODE) ?>,
            lane: <?= json_encode($lane, JSON_UNESCAPED_UNICODE) ?>,
            supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>,
        });
    });
}
const applyMarkupSelected = document.getElementById('applyMarkupSelected');
if (applyMarkupSelected) {
    applyMarkupSelected.addEventListener('click', async () => {
        const ids = selectedIds();
        if (!ids.length) {
            return alert('Selectează cel puțin un produs.');
        }
        if (!confirm('Reaplic adaos comercial pe ' + ids.length + ' produse?')) return;
        await importAction({ action: 'apply_markup_selected', ids });
    });
}
const applyMarkupFiltered = document.getElementById('applyMarkupFiltered');
if (applyMarkupFiltered) {
    applyMarkupFiltered.addEventListener('click', async () => {
        if (!confirm('Reaplic adaos comercial pe tot filtrul curent (max. 5000)?')) return;
        await importAction({
            action: 'apply_markup_filtered',
            supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>,
        });
    });
}

document.addEventListener('click', event => {
    const row = event.target.closest('.import-row');
    if (!row) return;
    if (event.target.closest('.restore-one')) {
        event.preventDefault();
        if (confirm('Restaurezi produsul în coada pending?')) {
            importAction({ action: 'restore_one', id: row.dataset.id });
        }
    }
});

(function initImportReviewButtonFx() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pressReleaseMs = 160;
    const rippleDurationMs = 550;

    function bindPress(el) {
        if (!el || el.dataset.besoiuBtnFx === '1') {
            return;
        }
        el.dataset.besoiuBtnFx = '1';

        el.addEventListener('pointerdown', (event) => {
            if (el.disabled) {
                return;
            }
            if (!reduceMotion) {
                el.classList.add('besoiu-btn-is-pressed');
            }
            if (event.button !== 0 || reduceMotion) {
                return;
            }
            const rect = el.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'besoiu-btn-ripple';
            const size = Math.max(rect.width, rect.height) * 1.25;
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
            ripple.style.background = 'currentColor';
            el.appendChild(ripple);
            window.setTimeout(() => ripple.remove(), rippleDurationMs);
        });

        const releasePress = () => {
            window.setTimeout(() => el.classList.remove('besoiu-btn-is-pressed'), pressReleaseMs);
        };
        el.addEventListener('pointerup', releasePress);
        el.addEventListener('pointercancel', releasePress);
        el.addEventListener('pointerleave', releasePress);
    }

    function collectImportReviewInteractive() {
        return document.querySelectorAll(
            '.import-review-page button, ' +
            '.import-review-page a.box, ' +
            '.import-review-page a.inline-flex, ' +
            '.import-review-page a.import-price-formation-log-link, ' +
            '#importQueueEditModal button, ' +
            '#importQueueEditModal a.inline-flex, ' +
            '#imageScanStatus button, ' +
            '#refreshImagesStop'
        );
    }

    collectImportReviewInteractive().forEach(bindPress);

    const imageScanPanel = document.getElementById('imageScanStatus');
    if (imageScanPanel && typeof MutationObserver !== 'undefined') {
        const rebind = () => collectImportReviewInteractive().forEach(bindPress);
        const observer = new MutationObserver(rebind);
        observer.observe(imageScanPanel, { attributes: true, attributeFilter: ['hidden'] });
        observer.observe(document.body, { childList: true });
    }
})();
</script>