<?php
declare(strict_types=1);

use Config\Database;
use Evasystem\Controllers\Categorii\CategoriiService;
use Evasystem\Core\AdminUrl;

require_once dirname(__DIR__, 5) . '/system/import-image-validate.php';
require_once dirname(__DIR__, 5) . '/system/import-queue-critical.php';
require_once dirname(__DIR__, 5) . '/system/image_search_pipeline.php';

$importActionApiUrl = AdminUrl::api('import_action_endpoint.php');
$refreshImagesButtonLabel = 'Caută imagini (pipeline Scraper)';
$refreshImagesConfirmSingle = 'Caut imagine via pipeline Scraper pentru produsul selectat?';
$refreshImagesConfirmMany = 'Caut imagini via pipeline Scraper pentru cele %d produse?';

$pdo = Database::getDB();
$supplier = trim((string)($_GET['supplier'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'pending'));

$suppliersMap = [];
foreach ($pdo->query(
    "SELECT UPPER(TRIM(code)) AS supplier_code, TRIM(name) AS supplier_label
     FROM furnizori
     WHERE code IS NOT NULL AND TRIM(code) <> ''"
) as $row) {
    $code = (string) ($row['supplier_code'] ?? '');
    if ($code === '') {
        continue;
    }
    $suppliersMap[$code] = (string) ($row['supplier_label'] ?? $code);
}
foreach (['import_produse', 'produse'] as $table) {
    foreach ($pdo->query(
        "SELECT DISTINCT UPPER(TRIM(pSupplier)) AS supplier_code
         FROM {$table}
         WHERE pSupplier IS NOT NULL AND TRIM(pSupplier) <> ''"
    ) as $row) {
        $code = (string) ($row['supplier_code'] ?? '');
        if ($code === '') {
            continue;
        }
        $suppliersMap[$code] = $suppliersMap[$code] ?? $code;
    }
}
$suppliers = [];
foreach ($suppliersMap as $code => $label) {
    $suppliers[] = ['supplier_code' => $code, 'supplier_label' => $label !== '' ? $label : $code];
}
usort($suppliers, static fn ($a, $b) => strcasecmp((string) ($a['supplier_label'] ?? ''), (string) ($b['supplier_label'] ?? '')));
$where = [];
$params = [];
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
if ($supplier !== '') { $where[] = 'pSupplier = ?'; $params[] = $supplier; }
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM import_produse' . $whereSql);
$countStmt->execute($params);
$importTotal = (int) $countStmt->fetchColumn();
$importTotalPages = max(1, (int) ceil($importTotal / $perPage));
$sql = 'SELECT * FROM import_produse' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$importQueueCategories = [];
$importQueueSubcategoriesByCategory = [];
try {
    $importQueueTaxonomyRows = (new CategoriiService())->getActive();
    $importQueueCatById = [];
    foreach ($importQueueTaxonomyRows as $taxonomyRow) {
        $importQueueCatById[(int) ($taxonomyRow['id'] ?? 0)] = $taxonomyRow;
    }
    foreach ($importQueueTaxonomyRows as $taxonomyRow) {
        $taxonomyType = (string) ($taxonomyRow['type'] ?? '');
        $taxonomyLabel = trim((string) ($taxonomyRow['label'] ?? ''));
        if ($taxonomyLabel === '') {
            continue;
        }
        if ($taxonomyType === 'categorie' && (int) ($taxonomyRow['parent_id'] ?? 0) === 0) {
            $importQueueCategories[] = [
                'id' => (int) ($taxonomyRow['id'] ?? 0),
                'label' => $taxonomyLabel,
            ];
            continue;
        }
        if ($taxonomyType !== 'subcategorie') {
            continue;
        }
        $parentId = (int) ($taxonomyRow['parent_id'] ?? 0);
        $parentLabel = trim((string) ($importQueueCatById[$parentId]['label'] ?? ''));
        if ($parentLabel === '') {
            continue;
        }
        $importQueueSubcategoriesByCategory[$parentLabel][] = [
            'id' => (int) ($taxonomyRow['id'] ?? 0),
            'label' => $taxonomyLabel,
            'parent_id' => $parentId,
        ];
    }
    usort($importQueueCategories, static fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));
    foreach ($importQueueSubcategoriesByCategory as $parentLabel => $subRows) {
        usort($subRows, static fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));
        $importQueueSubcategoriesByCategory[$parentLabel] = $subRows;
    }
} catch (Throwable $e) {
    $importQueueCategories = [];
    $importQueueSubcategoriesByCategory = [];
}

if ($status === 'pending' && $rows !== []) {
    $clearBadImageStmt = $pdo->prepare(
        "UPDATE import_produse SET pImages = '[]', pImageSource = 'missing' WHERE id = ? AND status = 'pending'"
    );
    foreach ($rows as $index => $row) {
        if (!is_array($row) || besoiu_import_row_has_trusted_image($row)) {
            continue;
        }
        $rawImages = json_decode((string) ($row['pImages'] ?? '[]'), true);
        if (!is_array($rawImages) || $rawImages === []) {
            continue;
        }
        $clearBadImageStmt->execute([(int) ($row['id'] ?? 0)]);
        $rows[$index]['pImages'] = '[]';
        $rows[$index]['pImageSource'] = 'missing';
    }
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
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
    $url = besoiu_import_row_image_url($row);
    return $url !== '' ? $url : '/admin/dist/images/fakers/preview-12.jpg';
}
function short_text($value, int $limit = 140): string {
    $text = trim((string)$value);
    if ($text === '') return '—';
    return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
}
function image_meta(array $row): array {
    $raw = json_decode((string)($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) $raw = [];
    $images = json_decode((string)($row['pImages'] ?? '[]'), true);
    $source = (string)($row['pImageSource'] ?? ($raw['__image_source'] ?? (is_array($images) && !empty($images[0]) ? 'csv' : 'missing')));
    if (!review_image_is_trusted($row)) {
        $source = 'missing';
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
    if (trim((string) ($row['pNote'] ?? '')) === '') {
        return true;
    }

    return false;
}

$queueCriticalBlocked = 0;
if ($status === 'pending' && $rows !== []) {
    foreach ($rows as $queueRow) {
        if (is_array($queueRow) && besoiu_import_row_blocks_auto_publish($queueRow)) {
            ++$queueCriticalBlocked;
        }
    }
}
?>
<div class="-mt-5 import-review-page">
    <div class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2>Coada import produse</h2>
                <div class="mt-1 text-xs opacity-70">Produsele sunt deja finalizate. Publica-le in magazin sau elimina-le din coada.</div>
            </div>
            <a href="/admin/import" class="ml-auto inline-flex h-10 items-center rounded-lg border px-4 text-sm hover:bg-foreground/5"><i data-lucide="file-up" class="mr-2 size-4"></i>Import nou</a>
        </div>

    <form class="flex flex-wrap items-center gap-3">
        <select name="supplier" class="h-10 rounded-md border bg-background px-3 py-2">
            <option value="">Toti furnizorii</option>
            <?php foreach ($suppliers as $s):
                $code = (string)($s['supplier_code'] ?? '');
                $label = (string)($s['supplier_label'] ?? $code);
                if ($code === '') continue;
            ?><option value="<?= h($code) ?>" <?= strtoupper($supplier) === $code ? 'selected' : '' ?>><?= h($label) ?> (<?= h($code) ?>)</option><?php endforeach; ?>
        </select>
        <select name="status" class="h-10 rounded-md border bg-background px-3 py-2">
            <?php foreach (['pending' => 'De publicat', 'imported' => 'Publicate', 'conflict_live' => 'Conflicte magazin', 'deleted' => 'Sterse', '' => 'Toate'] as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="inline-flex h-10 items-center rounded-lg border px-4" type="submit">Filtreaza</button>
        <?php if ($status === 'pending'): ?>
            <button id="addAll" class="ml-auto inline-flex h-10 items-center rounded-lg border bg-(--color)/20 px-4 text-(--color) [--color:var(--color-primary)]" type="button" title="Produsele cu date critice lipsă (categorie, brand, preț 0, imagine) sunt excluse automat">Publica toate filtrate</button>
            <select id="publishModeBulk" class="h-10 rounded-md border bg-background px-3 py-2 text-sm">
                <option value="skip" selected>Duplicate: omitere</option>
                <option value="update">Duplicate: actualizare</option>
                <option value="force">Duplicate: adaugare fortata</option>
            </select>
        <?php endif; ?>
    </form>

    <?php if ($status === 'pending' && $queueCriticalBlocked > 0): ?>
        <div class="import-queue-critical-banner mt-4 flex flex-wrap items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            <span class="inline-flex shrink-0 items-center rounded-full bg-amber-200 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-amber-900">Date critice</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold"><?= h((string) $queueCriticalBlocked) ?> produs<?= $queueCriticalBlocked === 1 ? '' : 'e' ?> cu date critice lipsă pe pagina curentă</div>
                <div class="mt-1 text-xs leading-relaxed opacity-90">Marcate cu badge roșu: fără categorie, fără brand, preț 0 sau fără imagine. Publicarea automată (cron și «Publica toate filtrate») este blocată până la completare.</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($status === 'pending'): ?>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm opacity-80">
                <input type="checkbox" id="selectAllRows">
                Selecteaza toate randurile din tabel
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <span class="opacity-70">La duplicate:</span>
                <select id="publishMode" class="h-10 rounded-md border bg-background px-3 py-2 text-sm">
                    <option value="skip" selected>Omitere (recomandat)</option>
                    <option value="update">Actualizare produs existent</option>
                    <option value="force">Adaugare fortata (duplicate)</option>
                </select>
            </label>
            <button id="addSelected" type="button" class="inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm text-emerald-700 hover:bg-emerald-100">Publica selectate</button>
            <button id="refreshImages" type="button" data-besoiu-action="refresh-images" class="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm text-sky-700 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60">
                <span id="refreshImagesIcon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                </span>
                <span id="refreshImagesLabel"><?= htmlspecialchars($refreshImagesButtonLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <button id="deleteSelected" type="button" class="inline-flex h-10 items-center rounded-lg border border-red-300 bg-red-50 px-4 text-sm text-red-700 hover:bg-red-100">Sterge selectate</button>
            <button id="exportValidatedCsv" type="button" class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-violet-50 px-4 text-sm text-violet-700 hover:bg-violet-100" title="CSV unificat gata de distribuție (website, Piese Auto) — doar produse validate">Export CSV validat</button>
            <button id="exportAutoproCsv" type="button" class="inline-flex h-10 items-center rounded-lg border border-teal-300 bg-teal-50 px-4 text-sm text-teal-700 hover:bg-teal-100" title="CSV format Piese Autopro: ID, titlu, categorie, descriere, monedă, preț, cantitate — doar produse validate">Export CSV Piese Autopro</button>
            <button id="exportBaselinkerBtn" type="button" class="inline-flex h-10 items-center rounded-lg border border-orange-300 bg-orange-50 px-4 text-sm text-orange-700 hover:bg-orange-100" title="Trimite produse validate către inventarul BaseLinker via API">Exportă produse spre BaseLinker</button>
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
            <button id="refreshImagesStop" type="button" data-besoiu-action="refresh-images-stop" class="import-image-scan-status__stop" hidden>Opreste</button>
        </div>
    <?php endif; ?>

    <div class="admin-table-wrap">
        <table class="w-full text-left text-sm" style="min-width:1180px;border-collapse:collapse;">
            <thead>
                <tr class="border-b">
                    <?php if ($status === 'pending'): ?><th class="px-3 py-3 text-center" style="width:42px;">✓</th><?php endif; ?>
                    <th class="px-3 py-3" style="width:84px;">Imagine</th>
                    <th class="px-3 py-3" style="width:140px;">Cod</th>
                    <th class="px-3 py-3" style="min-width:280px;">Denumire</th>
                    <th class="px-3 py-3" style="width:210px;">Alerte date</th>
                    <th class="px-3 py-3" style="width:140px;">Brand produs</th>
                    <th class="px-3 py-3" style="width:160px;">Marca auto</th>
                    <th class="px-3 py-3" style="width:190px;">Model</th>
                    <th class="px-3 py-3" style="width:220px;">Motorizare</th>
                    <th class="px-3 py-3" style="width:110px;">Pret</th>
                    <th class="px-3 py-3" style="width:90px;">Stoc</th>
                    <th class="px-3 py-3" style="width:140px;">Categorie</th>
                    <th class="px-3 py-3" style="width:160px;">Subcategorie</th>
                    <th class="px-3 py-3" style="min-width:320px;">Caracteristici</th>
                    <th class="px-3 py-3" style="min-width:220px;">OEM / Cross</th>
                    <th class="px-3 py-3" style="width:130px;">Sursa imagine</th>
                    <th class="px-3 py-3" style="width:110px;">Status</th>
                    <th class="px-3 py-3" style="width:170px;">Actiuni</th>
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
                    $queueEditPayload = [
                        'id' => (int) ($row['id'] ?? 0),
                        'pCode' => (string) ($row['pCode'] ?? ''),
                        'pName' => (string) ($row['pName'] ?? ''),
                        'pBrand' => (string) ($row['pBrand'] ?? ''),
                        'pMarca' => (string) ($row['pMarca'] ?? ''),
                        'pModel' => (string) ($row['pModel'] ?? ''),
                        'pMotorizare' => (string) ($row['pMotorizare'] ?? ''),
                        'pPrice' => (string) ($row['pPrice'] ?? ''),
                        'pBasePrice' => (string) ($row['pBasePrice'] ?? ''),
                        'pStock' => (string) ($row['pStock'] ?? '0'),
                        'pCategory' => (string) ($row['pCategory'] ?? ''),
                        'pSubcategory' => (string) ($row['pSubcategory'] ?? ''),
                        'pNote' => (string) ($row['pNote'] ?? ''),
                        'pOem' => (string) ($row['pOem'] ?? ''),
                        'pCompatibilitati' => (string) ($row['pCompatibilitati'] ?? ''),
                        'image' => review_image_src($row),
                        'imageSource' => (string) ($imgMeta['source'] ?? ''),
                        'imageTrusted' => review_image_is_trusted($row),
                        'status' => (string) ($row['status'] ?? ''),
                        'criticalFlags' => array_map(
                            static fn(array $flag): string => (string) ($flag['label'] ?? ''),
                            $criticalFlags
                        ),
                    ];
                    $rowCanQueueEdit = ($row['status'] ?? '') === 'pending';
                    ?>
                    <tr class="import-row<?= $rowCanQueueEdit ? ' import-row--queue-edit' : '' ?> border-b align-top hover:bg-slate-50<?= $hasCriticalGaps ? ' import-row--critical-gaps' : '' ?>" data-id="<?= h($row['id']) ?>"<?= $rowCanQueueEdit ? ' data-queue-edit="' . h(json_encode($queueEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"' : '' ?><?= $hasCriticalGaps ? ' data-critical-gaps="1"' : '' ?>>
                        <?php if ($status === 'pending'): ?>
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" class="row-check" value="<?= h($row['id']) ?>">
                            </td>
                        <?php endif; ?>
                        <td class="px-3 py-3">
                            <?php $hasTrustedImage = review_image_is_trusted($row); ?>
                            <?php $imgSrc = review_image_src($row); ?>
                            <div class="relative inline-block">
                                <img src="<?= h($imgSrc) ?>" alt="<?= h($row['pName'] ?? '') ?>" onerror="this.onerror=null;this.src='/admin/dist/images/fakers/preview-12.jpg';" style="width:56px;height:56px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;<?= $hasTrustedImage ? '' : 'opacity:.45;' ?>">
                                <?php if (!$hasTrustedImage): ?>
                                    <span style="position:absolute;left:4px;bottom:4px;padding:1px 6px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;">Lipsa</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-3 font-mono text-xs"><?= h($row['pCode'] ?? '—') ?></td>
                        <td class="px-3 py-3">
                            <div class="font-medium text-slate-900"><?= h($row['pName'] ?? 'Fara nume') ?></div>
                            <?php if ($km !== '' || $technicalCount > 0): ?>
                                <div class="mt-1 text-xs text-slate-500">
                                    <?php if ($km !== ''): ?>KM: <?= h($km) ?><?php endif; ?>
                                    <?php if ($km !== '' && $technicalCount > 0): ?> | <?php endif; ?>
                                    <?php if ($technicalCount > 0): ?>Date tehnice: <?= h((string)$technicalCount) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 align-top">
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
                        <td class="px-3 py-3 text-xs opacity-80<?= in_array('missing_brand', array_column($criticalFlags, 'code'), true) ? ' import-critical-cell' : '' ?>" data-queue-field="brand"><strong><?= h($row['pBrand'] ?? '—') ?></strong></td>
                        <td class="px-3 py-3 text-xs opacity-80" data-queue-field="marca"><?= h($row['pMarca'] ?? '—') ?></td>
                        <td class="px-3 py-3 text-xs opacity-80" data-queue-field="model"><?= h($row['pModel'] ?? '—') ?></td>
                        <td class="px-3 py-3 text-xs opacity-80" data-queue-field="motorizare" title="<?= h($row['pMotorizare'] ?? '') ?>"><?= h(short_text($row['pMotorizare'] ?? '', 120)) ?></td>
                        <td class="px-3 py-3">
                            <?php
                            $priceValue = trim((string)($row['pPrice'] ?? ''));
                            $hasPrice = $priceValue !== '' && (float)$priceValue > 0;
                            $rawMeta = json_decode((string)($row['raw_json'] ?? '{}'), true);
                            $supplierPrice = is_array($rawMeta) ? ($rawMeta['supplier_price'] ?? []) : [];
                            ?>
                            <div class="font-semibold <?= $hasPrice ? 'text-emerald-700' : 'text-red-600 import-critical-cell-inline' ?>" data-queue-field="price"><?= $hasPrice ? h($priceValue) . ' lei' : '— (0)' ?></div>
                            <?php if (!empty($row['pBasePrice'])): ?>
                                <div class="mt-1 text-xs text-slate-500">Baza (fara TVA): <?= h($row['pBasePrice']) ?> lei</div>
                            <?php endif; ?>
                            <?php if (!empty($row['pMarkupRuleName'])): ?>
                                <div class="mt-1 text-xs text-sky-700">Regula: <?= h($row['pMarkupRuleName']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplierPrice['supplier'])): ?>
                                <div class="mt-1 text-xs text-slate-500">Furnizor: <?= h((string)$supplierPrice['supplier']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplierPrice['matched_code'])): ?>
                                <div class="mt-1 text-xs text-slate-500">Match: <?= h((string)$supplierPrice['matched_code']) ?> (<?= h((string)($supplierPrice['matched_via'] ?? '')) ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3" data-queue-field="stock"><?= h($row['pStock'] ?? '0') ?></td>
                        <td class="px-3 py-3<?= in_array('missing_category', array_column($criticalFlags, 'code'), true) ? ' import-critical-cell' : '' ?>" data-queue-field="category"><?= h($row['pCategory'] ?? '—') ?></td>
                        <td class="px-3 py-3" data-queue-field="subcategory"><?= h($row['pSubcategory'] ?? '—') ?></td>
                        <td class="px-3 py-3 text-xs opacity-80" data-queue-field="note" title="<?= h($row['pNote'] ?? '') ?>">
                            <?php if ($specs !== ''): ?>
                                <div class="text-slate-700"><?= h(short_text($specs, 220)) ?></div>
                            <?php endif; ?>
                            <?php foreach ($technicalLines as $pair): ?>
                                <?php if (!empty($pair['label']) && !empty($pair['value'])): ?>
                                    <div class="mt-1 text-[11px] text-sky-700"><?= h((string)$pair['label']) ?>: <?= h((string)$pair['value']) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($technicalCount > 0): ?>
                                <div class="mt-1 text-[11px] text-slate-600">JSON tehnic: <?= h((string)$technicalCount) ?> câmpuri</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-xs opacity-80" data-queue-field="oem" title="<?= h($row['pOem'] ?? '') ?>">
                            <?= h(short_text($row['pOem'] ?? '', 160)) ?>
                            <?php if ($altCodesCount > 0): ?>
                                <div class="mt-1 text-[11px] text-slate-600">Coduri alternative: <?= h((string)$altCodesCount) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <?php
                            $imgSource = $imgMeta['source'];
                            $imgStyle = match ($imgSource) {
                                'csv', 'preview' => 'background:#dbeafe;color:#1d4ed8;',
                                'tecdoc', 'tecdoc_api' => 'background:#dcfce7;color:#166534;',
                                'caietcomenzi' => 'background:#e0e7ff;color:#3730a3;',
                                'search_api' => 'background:#fef3c7;color:#92400e;',
                                default => 'background:#fee2e2;color:#991b1b;',
                            };
                            $imgLabel = match ($imgSource) {
                                'csv' => 'CSV',
                                'preview' => 'Preview',
                                'tecdoc', 'tecdoc_api' => 'TecDoc',
                                'caietcomenzi' => 'CaietComenzi',
                                'search_api' => 'Search API',
                                'import' => 'Import',
                                default => 'Lipsă',
                            };
                            ?>
                            <span title="<?= h($imgMeta['query']) ?>" style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;<?= $imgStyle ?>">
                                <?= h($imgLabel) ?>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;<?=
                                ($row['status'] ?? '') === 'pending' ? 'background:#fef3c7;color:#92400e;' :
                                (($row['status'] ?? '') === 'imported' ? 'background:#dcfce7;color:#166534;' :
                                (($row['status'] ?? '') === 'conflict_live' ? 'background:#ffedd5;color:#c2410c;' : 'background:#fee2e2;color:#991b1b;'))
                            ?>">
                                <?= h(match ($row['status'] ?? '') {
                                    'conflict_live' => 'Conflict magazin',
                                    default => (string)($row['status'] ?? '—'),
                                }) ?>
                            </span>
                            <?php if (($row['status'] ?? '') === 'conflict_live' && !empty($row['conflict_product_id'])): ?>
                                <div class="mt-1 text-xs text-orange-700">Exista deja #<?= h($row['conflict_product_id']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3">
                            <?php if (($row['status'] ?? '') === 'pending'): ?>
                                <?php $rowNeedsReprocess = review_row_needs_reprocess($row); ?>
                                <div class="flex flex-wrap gap-2">
                                    <a href="/admin/adaoscomercial?tab=price-log&amp;import_id=<?= h((string)($row['id'] ?? '')) ?>" class="import-price-formation-log-link inline-flex items-center rounded-lg border border-violet-300 bg-violet-50 px-3 py-1.5 text-xs text-violet-700" title="Verifică pașii formare preț">Log preț</a>
                                    <?php if ($rowNeedsReprocess): ?>
                                        <button class="reprocess-one inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs text-indigo-700" type="button" data-besoiu-action="reprocess-one" title="Re-triggerează TecDoc/RapidAPI pentru imagini și descrieri">Re-procesează</button>
                                    <?php endif; ?>
                                    <button class="refresh-one-image inline-flex items-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs text-sky-700" type="button" data-besoiu-action="refresh-one-image">Cauta imagine</button>
                                    <button class="add-one inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs text-emerald-700" type="button" title="<?= $hasCriticalGaps ? 'Publicare manuală permisă; auto-publish blocat' : 'Publica in magazin' ?>">Publica</button>
                                    <button class="exclude-one inline-flex items-center rounded-lg border border-orange-300 bg-orange-50 px-3 py-1.5 text-xs text-orange-800" type="button" title="Elimină produsul din draft (date greșite, imagine lipsă, preț zero)">Exclude</button>
                                </div>
                            <?php else: ?>
                                <span class="text-xs opacity-70">Fara actiuni</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($importTotalPages > 1): ?>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="text-xs opacity-70"><?= min($offset + 1, $importTotal) ?>–<?= min($offset + $perPage, $importTotal) ?> din <?= $importTotal ?></span>
        <?php
        $baseQs = http_build_query(array_filter(['supplier' => $supplier, 'status' => $status]));
        for ($p = max(1, $page - 2); $p <= min($importTotalPages, $page + 2); $p++):
            $href = '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . $p;
        ?>
            <a href="<?= h($href) ?>" class="box h-9 min-w-9 rounded-md border px-3 py-1 text-sm text-center <?= $p === $page ? 'bg-primary text-white' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
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
                            <span>Titlu</span>
                            <input type="text" id="importQueueEditName" name="pName" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" required>
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
                            <span>Pret (lei)</span>
                            <input type="text" id="importQueueEditPrice" name="pPrice" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" inputmode="decimal">
                        </label>
                        <label class="import-queue-edit-field">
                            <span>Pret baza (fara TVA)</span>
                            <input type="text" id="importQueueEditBasePrice" name="pBasePrice" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" inputmode="decimal">
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
            <div id="importQueueEditStatus" class="import-queue-edit-modal__status hidden" role="status" aria-live="polite"></div>
            <div class="import-queue-edit-modal__actions">
                <button type="button" id="importQueueEditReprocess" class="inline-flex h-10 items-center rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm text-sky-700 hover:bg-sky-100">Re-proceseaza</button>
                <button type="button" id="importQueueEditSyncTecdoc" class="inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm text-emerald-700 hover:bg-emerald-100">Sync TecDoc</button>
                <button type="submit" id="importQueueEditSave" class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-violet-50 px-4 text-sm text-violet-700 hover:bg-violet-100">Salveaza</button>
                <button type="button" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm hover:bg-foreground/5" data-close-queue-edit>Inchide</button>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="importQueueSubcategoriesByCategory"><?= json_encode($importQueueSubcategoriesByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<style>
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
    padding: 2px 8px;
    border-radius: 999px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
}
.import-critical-ok {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
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
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.import-queue-edit-modal.hidden { display: none; }
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
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 13px;
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
.import-queue-edit-modal__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}
body.import-queue-edit-open { overflow: hidden; }
</style>
<script>
function currentPublishMode() {
    const bulk = document.getElementById('publishModeBulk');
    if (bulk) {
        return bulk.value;
    }
    const select = document.getElementById('publishMode');
    return select ? select.value : 'skip';
}

async function importAction(payload) {
    const addActions = ['add_one', 'add_all_pending', 'add_selected'];
    const imageJobActions = ['refresh_images_start', 'refresh_images_step', 'refresh_images_cancel'];
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
    });
    const raw = await response.text();
    let result;
    try {
        result = raw.trim() ? JSON.parse(raw) : {};
    } catch (parseError) {
        if (imageJobActions.includes(payload.action)) {
            return {
                success: false,
                message: 'Raspuns invalid de la server. Scanarea continua in fundal — reincearca pasul.',
            };
        }
        throw parseError;
    }
    if (!imageJobActions.includes(payload.action)) {
        alert(result.message || 'Gata.');
    }
    if (result.success) {
        if (!imageJobActions.includes(payload.action) && Array.isArray(result.errors) && result.errors.length) {
            alert('Erori RapidAPI:\n\n' + result.errors.join('\n') + (result.log_file ? '\n\nLog: ' + result.log_file : ''));
        }
        if (addActions.includes(payload.action)) {
            window.location.href = result.redirect || '/admin/product';
            return;
        }
        if (!imageJobActions.includes(payload.action)) {
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
    async stopJob() {
        this.abortScan = true;
        if (this.jobId) {
            try {
                await importAction({ action: 'refresh_images_cancel', job_id: this.jobId });
            } catch (e) {}
        }
        this.setButtonState('idle', REFRESH_IMAGES_BUTTON_LABEL);
        imageScanSetBodyActive(false);
        this.show('partial', 'Scanare oprita', 'Procesul a fost oprit. Poti reporni scanarea oricand.');
    },
    async run(payload) {
        if (this.isScanning) return;
        this.isScanning = true;
        this.init();
        imageScanBeginSession();
        this.abortScan = false;
        this.jobId = '';
        const countLabel = payload.ids && payload.ids.length
            ? (payload.ids.length === 1 ? '1 produs selectat' : payload.ids.length + ' produse selectate')
            : 'produsele filtrate';

        imageScanDismissPageLoader();
        imageScanSetBodyActive(true);
        this.setButtonState('scanning', 'Se scaneaza...');
        this.setProgress(0);
        this.show('scanning', 'Pornesc scanarea in fundal...', 'Pregatesc job-ul pentru ' + countLabel + '. Poti continua sa folosesti pagina.');
        await paintBeforeAsync();

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
            const total = Number(start.total || 0);
            this.show('scanning', 'Scanare in fundal activa', '0 / ' + total + ' produse. Poti ramane pe pagina — browserul nu se blocheaza.');

            let steps = 0;
            const MAX_STEPS = 500;
            while (!this.abortScan && !imageScanPageLeaving && steps < MAX_STEPS) {
                steps++;
                await new Promise(resolve => setTimeout(resolve, IMAGE_JOB_DELAY_MS));
                if (this.abortScan || imageScanPageLeaving) break;

                let step = null;
                let stepAttempts = 0;
                while (stepAttempts <= IMAGE_JOB_STEP_MAX_RETRIES) {
                    try {
                        this.setFetching(true);
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
                            'Serverul proceseaza produsul (poate dura pana la 2 min) — reincerc pasul '
                                + stepAttempts + '/' + IMAGE_JOB_STEP_MAX_RETRIES + '...'
                        );
                        await new Promise(resolve => setTimeout(resolve, 2000));
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
                ? 'Conexiunea cu serverul s-a intrerupt temporar — job-ul ruleaza AJAX pas cu pas; reincarca pagina dupa 1-2 minute sau apasa din nou «Cauta imagini».'
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
if (addAll) addAll.addEventListener('click', () => {
    if (confirm('Publici toate produsele filtrate in magazin?')) {
        importAction({action: 'add_all_pending', supplier: <?= json_encode($supplier, JSON_UNESCAPED_UNICODE) ?>});
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
        exportBtn.textContent = 'Se generează CSV...';
    }

    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = 'Export CSV eșuat.';
            try {
                const json = JSON.parse(await response.text());
                message = json.message || message;
            } catch (e) {}
            throw new Error(message);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            const json = await response.json();
            throw new Error(json.message || 'Export CSV eșuat.');
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
        alert('CSV unificat descărcat (' + (ids.length ? ids.length + ' selectate validate' : 'toate validate filtrate') + ').');
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export CSV eșuat.');
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
        exportBtn.textContent = 'Se generează CSV Piese Autopro...';
    }

    try {
        const response = await fetch(<?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = 'Export CSV Piese Autopro eșuat.';
            try {
                const json = JSON.parse(await response.text());
                message = json.message || message;
            } catch (e) {}
            throw new Error(message);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (contentType.includes('application/json')) {
            const json = await response.json();
            throw new Error(json.message || 'Export CSV Piese Autopro eșuat.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const supplierSuffix = payload.supplier ? '_' + String(payload.supplier).toUpperCase() : '';
        link.download = 'import_queue_piese_autopro' + supplierSuffix + '_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        alert('CSV Piese Autopro descărcat (' + (ids.length ? ids.length + ' selectate validate' : 'toate validate filtrate') + ').');
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export CSV Piese Autopro eșuat.');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.textContent = prevLabel || 'Export CSV Piese Autopro';
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
            throw new Error('Export BaseLinker eșuat.');
        }

        if (!response.ok || !json.success) {
            throw new Error(json.message || 'Export BaseLinker eșuat.');
        }

        const sent = Number(json.sent ?? 0);
        const errors = Number(json.errors ?? 0);
        let message = sent + ' produse trimise, ' + errors + ' erori.';
        if (Array.isArray(json.error_details) && json.error_details.length) {
            message += '\n\n' + json.error_details.slice(0, 5).join('\n');
        }
        alert(message);
    } catch (error) {
        alert((error && error.message) ? error.message : 'Export BaseLinker eșuat.');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.textContent = prevLabel || 'Exportă produse spre BaseLinker';
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
    if (importQueueEditFields.name) importQueueEditFields.name.value = row.pName || '';
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

function importQueueEditOpen(row) {
    if (!importQueueEditModal || !row) return;
    importQueueEditFill(row);
    importQueueEditSetStatus('', true);
    importQueueEditModal.classList.remove('hidden');
    importQueueEditModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('import-queue-edit-open');
    importQueueEditFields.name?.focus();
}

function importQueueEditClose() {
    if (!importQueueEditModal) return;
    importQueueEditModal.classList.add('hidden');
    importQueueEditModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('import-queue-edit-open');
    importQueueEditSetStatus('', true);
}

function importQueueEditPayloadFromForm() {
    return {
        action: 'queue_row_save',
        id: importQueueEditFields.id?.value || '',
        pName: importQueueEditFields.name?.value || '',
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
    const nameCell = tr.querySelector('td:nth-child(' + (tr.querySelector('.row-check') ? '5' : '4') + ') .font-medium');
    if (nameCell && row.pName) nameCell.textContent = row.pName;
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

document.addEventListener('click', event => {
    if (event.target.closest('#importQueueEditModal')) return;
    const row = event.target.closest('.import-row--queue-edit');
    if (!row) return;
    if (event.target.closest('a, button, input, label, select, .import-price-formation-log-link')) return;
    let payload = null;
    try {
        payload = JSON.parse(row.dataset.queueEdit || '{}');
    } catch (e) {
        payload = null;
    }
    if (payload && payload.id) {
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