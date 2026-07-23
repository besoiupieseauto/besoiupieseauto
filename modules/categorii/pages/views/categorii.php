<?php
declare(strict_types=1);

use Besoiu\Services\CategoriiService;
use Besoiu\Services\CategoryIconService;
use Besoiu\Services\BesoiuCategoryTreeExcelReader;
use Besoiu\Services\BesoiuCategoryTreeImportService;

$service = new CategoriiService();
$iconService = new CategoryIconService();
$page = max(1, (int) ($_GET['page'] ?? 1));
$catalogView = trim((string) ($_GET['view'] ?? 'besoiu'));
if (!in_array($catalogView, ['besoiu', 'all', 'other'], true)) {
    $catalogView = 'besoiu';
}
$activeType = trim((string) ($_GET['type'] ?? ''));
$searchQ = trim((string) ($_GET['q'] ?? ''));
$allCategorii = $service->getAll();
$besoiuFlat = [];
$categorii = [];
$total = 0;
$totalPages = 1;
$currentPage = 1;
$iconIndex = $iconService->buildIndex($allCategorii);
$iconPresets = CategoryIconService::presetIcons();
$grandTotal = count($allCategorii);
$tecdocStructureImportEnabled = $service->isTecdocStructureImportEnabled();
$tecdocStructureImportNotice = $service->tecdocStructureImportBlockedMessage();

function h_cat($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function fmt_cat_num(int|float $value): string
{
    if (is_float($value) && fmod($value, 1.0) !== 0.0) {
        return number_format($value, 1, ',', '.');
    }

    return number_format((int) $value, 0, ',', '.');
}

function cat_meta_source(array $cat): string {
    return (string) (cat_meta($cat)['source'] ?? '');
}

function cat_meta(array $cat): array {
    $meta = $cat['meta'] ?? '';
    if (is_array($meta)) {
        return $meta;
    }
    if (!is_string($meta) || trim($meta) === '') {
        return [];
    }
    $decoded = json_decode($meta, true);
    return is_array($decoded) ? $decoded : [];
}

function cat_tree_id(array $cat): int {
    return (int) (cat_meta($cat)['tree_id'] ?? 0);
}

function cat_parent_tree_id(array $cat): int {
    return (int) (cat_meta($cat)['parent_tree_id'] ?? 0);
}

function cat_level(array $cat): int {
    return (int) (cat_meta($cat)['level'] ?? 0);
}

function cat_path(array $cat): string {
    return (string) (cat_meta($cat)['path'] ?? '');
}

function cat_art_name(array $cat): string {
    $meta = cat_meta($cat);
    if (!empty($meta['is_leaf'])) {
        return (string) ($meta['art_name'] ?? $cat['label'] ?? '');
    }
    return (string) ($meta['art_name'] ?? '');
}

function cat_display_label(array $cat): string {
    $meta = cat_meta($cat);
    return (string) ($meta['display_label'] ?? $cat['label'] ?? '');
}

function cat_workspace_item(array $cat, CategoryIconService $iconService, array $iconIndex, array $parentById, array $typeMeta): array
{
    $catType = (string) ($cat['type'] ?? 'categorie');
    $typeKey = isset($typeMeta[$catType]) ? $catType : 'categorie';
    $displayIcon = $iconService->resolve($cat, $iconIndex);
    $src = cat_meta_source($cat);
    $searchBlob = mb_strtolower(
        cat_display_label($cat) . ' '
        . ($cat['slug'] ?? '') . ' '
        . cat_art_name($cat) . ' '
        . cat_path($cat) . ' '
        . cat_tree_id($cat) . ' '
        . ($typeMeta[$typeKey]['label'] ?? $catType) . ' '
        . $src
    );

    return [
        'id' => (int) ($cat['id'] ?? 0),
        'label' => (string) ($cat['label'] ?? ''),
        'slug' => (string) ($cat['slug'] ?? ''),
        'type' => $catType,
        'type_label' => (string) ($typeMeta[$typeKey]['label'] ?? $catType),
        'source' => $src,
        'icon' => $displayIcon,
        'sort_order' => (int) ($cat['sort_order'] ?? 0),
        'parent_label' => cat_parent_label($cat['parent_id'] ?? 0, $parentById),
        'is_active' => (int) ($cat['is_active'] ?? 0) === 1,
        'search' => $searchBlob,
        'edit' => array_merge($cat, ['icon' => $displayIcon]),
    ];
}

$typeMeta = [
    'categorie' => ['label' => 'Categorie piese', 'short' => 'Cat. piese', 'group' => 'catalog'],
    'familie' => ['label' => 'Familie / subgrup', 'short' => 'Familie', 'group' => 'catalog'],
    'subcategorie' => ['label' => 'Subcategorie piese', 'short' => 'Subcat. piese', 'group' => 'catalog'],
    'marca' => ['label' => 'Marcă auto', 'short' => 'Marcă', 'group' => 'vehicul'],
    'model' => ['label' => 'Model vehicul', 'short' => 'Model', 'group' => 'vehicul'],
    'motorizare' => ['label' => 'Motorizare', 'short' => 'Motorizare', 'group' => 'vehicul'],
];
$besoiuTreePreview = $service->previewBesoiuCategoryTree();
$besoiuDbMetrics = $service->getBesoiuDashboardMetrics();
$besoiuExcelAvailable = BesoiuCategoryTreeExcelReader::isAvailable();
$besoiuExcelPath = BesoiuCategoryTreeImportService::excelPath();
$types = array_map(static fn (array $m): string => $m['label'], $typeMeta);

$parentById = [];
foreach ($allCategorii as $row) {
    $parentById[(int) ($row['id'] ?? 0)] = $row;
}

function cat_parent_label(mixed $parentId, array $parentById): string
{
    $id = (int) ($parentId ?? 0);
    if ($id <= 0) {
        return '—';
    }
    $parent = $parentById[$id] ?? null;
    if (!is_array($parent)) {
        return '#' . $id;
    }
    $label = trim((string) ($parent['label'] ?? ''));
    return $label !== '' ? $label : ('#' . $id);
}

$stats = ['categorie' => 0, 'subcategorie' => 0, 'familie' => 0, 'marca' => 0, 'model' => 0, 'motorizare' => 0];
$besoiuCount = 0;
$otherCatalogCount = 0;
$tecdocLinkedCount = 0;
$besoiuLevelStats = [1 => 0, 2 => 0, 3 => 0];
$besoiuLeavesDb = 0;
foreach ($allCategorii as $c) {
    $t = (string) ($c['type'] ?? 'categorie');
    if (isset($stats[$t])) {
        $stats[$t]++;
    } else {
        $stats['categorie']++;
    }
    if (in_array($t, ['categorie', 'subcategorie', 'familie'], true)) {
        if (cat_meta_source($c) === 'besoiu_tree') {
            $besoiuCount++;
        } else {
            $otherCatalogCount++;
        }
    }
    if (cat_meta_source($c) === 'besoiu_tree') {
        $lv = cat_level($c);
        if ($lv >= 1 && $lv <= 3) {
            $besoiuLevelStats[$lv]++;
        }
        if (!empty(cat_meta($c)['is_leaf'])) {
            $besoiuLeavesDb++;
        }
    }
    if ((int) ($c['tecdoc_id'] ?? 0) > 0) {
        $tecdocLinkedCount++;
    }
}
$besoiuExpectedTotal = max((int) ($besoiuTreePreview['total'] ?? 0), $besoiuCount, 629);
$besoiuDbPct = $besoiuExpectedTotal > 0 ? min(100, (int) round(($besoiuCount / $besoiuExpectedTotal) * 100)) : 0;
$besoiuSyncOk = $besoiuCount >= $besoiuExpectedTotal && $besoiuExpectedTotal > 0;
$besoiuLeavesExpected = (int) ($besoiuTreePreview['leaves'] ?? $besoiuLeavesDb);
$besoiuRootsExpected = (int) ($besoiuTreePreview['roots'] ?? 18);
$besoiuSynonymsDb = (int) ($besoiuDbMetrics['synonyms_db'] ?? 0);
$besoiuSecondaryDb = (int) ($besoiuDbMetrics['secondary_db'] ?? 0);
$besoiuSynonymsExcel = (int) ($besoiuTreePreview['synonyms_count'] ?? 0);
$besoiuSecondaryExcel = (int) ($besoiuTreePreview['secondary_count'] ?? 0);
$besoiuSynonymsShow = max($besoiuSynonymsDb, $besoiuSynonymsExcel);
$besoiuSecondaryShow = max($besoiuSecondaryDb, $besoiuSecondaryExcel);
$effectiveView = ($activeType !== '' && $catalogView === 'besoiu') ? 'all' : $catalogView;
if (!in_array($effectiveView, ['besoiu', 'all', 'other'], true)) {
    $effectiveView = 'besoiu';
}
$besoiuFlat = array_values(array_filter(
    $allCategorii,
    static fn (array $c): bool => cat_meta_source($c) === 'besoiu_tree'
));
usort($besoiuFlat, static fn (array $a, array $b): int => cat_tree_id($a) <=> cat_tree_id($b));
$workspaceItems = [];
foreach ($allCategorii as $row) {
    $workspaceItems[] = cat_workspace_item($row, $iconService, $iconIndex, $parentById, $typeMeta);
}
$tabQuery = ($searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '') . ($catalogView !== 'besoiu' ? '&view=' . rawurlencode($catalogView) : '');
$moduleCss = dirname(__DIR__, 2) . '/assets/css/module.css';
$moduleTreeJs = dirname(__DIR__, 2) . '/assets/js/besoiu-tree.js';
$moduleWorkspaceJs = dirname(__DIR__, 2) . '/assets/js/categorii-workspace.js';
?>
<?php if (is_file($moduleCss)): ?><style><?= file_get_contents($moduleCss) ?></style><?php endif; ?>
<div class="categorii-pro" data-page-title="Taxonomie catalog">



    <div id="tecdoc-structure-deferred-banner" class="cp-notice" data-tm021="reference-only">
        <strong>TecDoc — doar referință (tm_021):</strong>
        <?= h_cat($tecdocStructureImportNotice) ?>
        <br><span class="cp-notice__sub">Potrivirea produselor folosește <code>art_name</code> TecDoc ↔ frunză ART_NAME din arborele Besoiu (match exact).</span>
    </div>

    <?php if ($otherCatalogCount > 0): ?>
    <div id="cp-alternate-catalog-banner" class="cp-notice cp-notice--warn">
        <strong>Catalog amestecat:</strong>
        există <strong><?= (int) $otherCatalogCount ?></strong> intrări vechi/alternative (PieseAuto, manual, import implicit)
        lângă arborele Besoiu (<strong><?= (int) $besoiuCount ?></strong> noduri).
        Doar arborele Besoiu (Excel) este sursa canonică pentru clasificare.
        <div class="cp-notice__actions" style="margin-top:0.65rem;display:flex;flex-wrap:wrap;gap:0.5rem;">
            <button type="button" onclick="purgeAlternateCatalog()" class="cp-btn cp-btn--ghost">
                <i class="fa-solid fa-broom" aria-hidden="true"></i> Curăță catalog alternativ
            </button>
            <button type="button" onclick="renewBesoiuTree()" class="cp-btn cp-btn--primary">
                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Reînnoiește arbore Besoiu
            </button>
        </div>
    </div>
    <?php endif; ?>

    <section id="besoiu-dashboard" class="cp-besoiu-dashboard">
        <div class="cp-bdash-head">
            <div class="cp-bdash-head__text">
                <div class="cp-bdash-eyebrow"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Catalog piese Besoiu</div>
                <h2 class="cp-bdash-title">Dashboard arbore categorii</h2>
                <p class="cp-bdash-sub">Două surse distincte: <strong>catalog produse</strong> (milions de rânduri, GB) și <strong>arbore taxonomie</strong> (629 noduri fixe din Excel pentru clasificare L1→L2→L3).</p>
            </div>
            <div class="cp-bdash-head__badge <?= $besoiuSyncOk ? 'is-ok' : 'is-warn' ?>">
                <span class="cp-bdash-head__badge-icon"><i class="fa-solid <?= $besoiuSyncOk ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i></span>
                <span><?= $besoiuSyncOk ? 'DB la zi' : 'Reînnoiește din Excel' ?></span>
            </div>
        </div>

        <div class="cp-bdash-db" aria-label="Statistici baza de date produse">
            <div class="cp-bdash-db__title"><i class="fa-solid fa-server" aria-hidden="true"></i> Catalog produse — date reale din baza de date</div>
            <div class="cp-bdash-db__grid">
                <div class="cp-bdash-db__card cp-bdash-db__card--primary">
                    <div class="cp-bdash-db__icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></div>
                    <div class="cp-bdash-db__val"><?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_active'] ?? 0))) ?></div>
                    <div class="cp-bdash-db__lbl">Produse active</div>
                    <div class="cp-bdash-db__hint">din <?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_total'] ?? 0))) ?> total înregistrate</div>
                </div>
                <div class="cp-bdash-db__card">
                    <div class="cp-bdash-db__icon"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i></div>
                    <div class="cp-bdash-db__val"><?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['import_total'] ?? 0))) ?></div>
                    <div class="cp-bdash-db__lbl">Import staging</div>
                    <div class="cp-bdash-db__hint"><?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['import_pending'] ?? 0))) ?> în așteptare (pending)</div>
                </div>
                <div class="cp-bdash-db__card">
                    <div class="cp-bdash-db__icon"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i></div>
                    <div class="cp-bdash-db__val"><?= h_cat(fmt_cat_num((float) ($besoiuDbMetrics['db_size_gb'] ?? 0))) ?> <span class="cp-bdash-db__unit">GB</span></div>
                    <div class="cp-bdash-db__lbl">Dimensiune bază date</div>
                    <div class="cp-bdash-db__hint">tabele + indexuri MySQL</div>
                </div>
                <div class="cp-bdash-db__card">
                    <div class="cp-bdash-db__icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
                    <div class="cp-bdash-db__val"><?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_classified_both'] ?? 0))) ?></div>
                    <div class="cp-bdash-db__lbl">Produse clasificate L1+L2</div>
                    <div class="cp-bdash-db__hint">L1: <?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_with_l1'] ?? 0))) ?> · L2: <?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_with_l2'] ?? 0))) ?></div>
                </div>
            </div>
        </div>

        <div class="cp-bdash-hero">
            <div class="cp-bdash-hero__main">
                <div class="cp-bdash-hero__icon" aria-hidden="true"><i class="fa-solid fa-folder-tree"></i></div>
                <div class="cp-bdash-hero__copy">
                    <div class="cp-bdash-hero__num"><?= h_cat(fmt_cat_num((int) $besoiuCount)) ?></div>
                    <div class="cp-bdash-hero__lbl">noduri arbore categorii (taxonomie Besoiu)</div>
                </div>
            </div>
            <div class="cp-bdash-hero__meter">
                <div class="cp-bdash-meter__top">
                    <span><i class="fa-solid fa-database" aria-hidden="true"></i> Completare DB</span>
                    <strong><?= (int) $besoiuDbPct ?>%</strong>
                </div>
                <div class="cp-bdash-meter__track"><div class="cp-bdash-meter__fill" style="width:<?= (int) $besoiuDbPct ?>%"></div></div>
                <div class="cp-bdash-meter__hint"><i class="fa-solid fa-file-excel" aria-hidden="true"></i> <?= (int) $besoiuCount ?> din <?= (int) $besoiuExpectedTotal ?> așteptate · sursă <?= h_cat(($besoiuTreePreview['source'] ?? 'visual_tree') === 'excel' ? 'Excel' : 'vizual.txt') ?></div>
            </div>
        </div>

        <div class="cp-bdash-flow" aria-label="Niveluri arbore">
            <div class="cp-bdash-flow__title"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> Cum arată arborele (3 niveluri)</div>
            <div class="cp-bdash-flow__row">
                <div class="cp-bdash-level cp-bdash-level--l1">
                    <div class="cp-bdash-level__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>1</span></div>
                    <div class="cp-bdash-level__body">
                        <div class="cp-bdash-level__name">Grupă mare (L1)</div>
                        <div class="cp-bdash-level__num"><?= (int) ($besoiuLevelStats[1] ?: $besoiuRootsExpected) ?></div>
                        <div class="cp-bdash-level__hint">ex. Filtre, Motor, Frâne</div>
                        <div class="cp-bdash-bar"><span style="width:<?= $besoiuCount > 0 ? min(100, round((($besoiuLevelStats[1] ?: $besoiuRootsExpected) / $besoiuCount) * 100)) : 0 ?>%"></span></div>
                    </div>
                </div>
                <div class="cp-bdash-flow__arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="cp-bdash-level cp-bdash-level--l2">
                    <div class="cp-bdash-level__icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span>2</span></div>
                    <div class="cp-bdash-level__body">
                        <div class="cp-bdash-level__name">Familie (L2)</div>
                        <div class="cp-bdash-level__num"><?= (int) $besoiuLevelStats[2] ?></div>
                        <div class="cp-bdash-level__hint">sub-grup în grupă</div>
                        <div class="cp-bdash-bar"><span style="width:<?= $besoiuCount > 0 ? min(100, round($besoiuLevelStats[2] / $besoiuCount * 100)) : 0 ?>%"></span></div>
                    </div>
                </div>
                <div class="cp-bdash-flow__arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="cp-bdash-level cp-bdash-level--l3">
                    <div class="cp-bdash-level__icon"><i class="fa-solid fa-tag" aria-hidden="true"></i><span>3</span></div>
                    <div class="cp-bdash-level__body">
                        <div class="cp-bdash-level__name">Nume produs (L3)</div>
                        <div class="cp-bdash-level__num"><?= (int) ($besoiuLeavesDb ?: $besoiuLeavesExpected) ?></div>
                        <div class="cp-bdash-level__hint">ART_NAME — găsit în denumire</div>
                        <div class="cp-bdash-bar"><span style="width:<?= $besoiuCount > 0 ? min(100, round((($besoiuLeavesDb ?: $besoiuLeavesExpected) / $besoiuCount) * 100)) : 0 ?>%"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cp-bdash-cards">
            <div class="cp-bdash-cards__head"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i> Arbore taxonomie &amp; dicționare (Excel → DB)</div>
            <div class="cp-bdash-cards__grid">
            <div class="cp-bdash-card">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--teal"><i class="fa-solid fa-sitemap" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num((int) $besoiuExpectedTotal)) ?></div>
                <div class="cp-bdash-card__lbl">Noduri arbore</div>
            </div>
            <div class="cp-bdash-card">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--green"><i class="fa-solid fa-leaf" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num((int) ($besoiuLeavesDb ?: $besoiuLeavesExpected))) ?></div>
                <div class="cp-bdash-card__lbl">Frunze ART_NAME (L3)</div>
            </div>
            <div class="cp-bdash-card">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--violet"><i class="fa-solid fa-language" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num($besoiuSynonymsShow)) ?></div>
                <div class="cp-bdash-card__lbl">Sinonime căutare</div>
                <div class="cp-bdash-card__sub"><?= $besoiuSynonymsDb > 0 ? h_cat(fmt_cat_num($besoiuSynonymsDb)) . ' în DB' : 'doar dicționar Excel' ?></div>
            </div>
            <div class="cp-bdash-card">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--amber"><i class="fa-solid fa-link" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num($besoiuSecondaryShow)) ?></div>
                <div class="cp-bdash-card__lbl">Categorii secundare</div>
                <div class="cp-bdash-card__sub"><?= $besoiuSecondaryDb > 0 ? h_cat(fmt_cat_num($besoiuSecondaryDb)) . ' în DB' : 'mapări Excel' ?></div>
            </div>
            <div class="cp-bdash-card cp-bdash-card--veh">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--blue"><i class="fa-solid fa-store" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num((int) ($besoiuDbMetrics['produse_vitrina'] ?? 0))) ?></div>
                <div class="cp-bdash-card__lbl">Produse în vitrină</div>
            </div>
            <div class="cp-bdash-card cp-bdash-card--veh">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--blue"><i class="fa-solid fa-car" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num((int) ($stats['marca'] ?? 0))) ?></div>
                <div class="cp-bdash-card__lbl">Mărci fitment</div>
                <div class="cp-bdash-card__sub"><?= h_cat(fmt_cat_num((int) ($stats['model'] ?? 0))) ?> modele · <?= h_cat(fmt_cat_num((int) ($stats['motorizare'] ?? 0))) ?> motorizări</div>
            </div>
            <div class="cp-bdash-card">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--slate"><i class="fa-solid fa-gears" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= h_cat(fmt_cat_num((int) $tecdocLinkedCount)) ?></div>
                <div class="cp-bdash-card__lbl">Legături TecDoc</div>
            </div>
            <div class="cp-bdash-card <?= $besoiuExcelAvailable ? 'is-ok' : 'is-warn' ?>">
                <div class="cp-bdash-card__icon cp-bdash-card__icon--<?= $besoiuExcelAvailable ? 'ok' : 'warn' ?>"><i class="fa-solid <?= $besoiuExcelAvailable ? 'fa-file-circle-check' : 'fa-file-circle-exclamation' ?>" aria-hidden="true"></i></div>
                <div class="cp-bdash-card__val"><?= $besoiuExcelAvailable ? 'OK' : 'Lipsă' ?></div>
                <div class="cp-bdash-card__lbl">Fișier Excel sursă</div>
            </div>
            </div>
        </div>

        <div class="cp-bdash-foot">
            <div class="cp-bdash-foot__path">
                <span class="cp-bdash-foot__lbl"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Sursă:</span>
                <code><?= h_cat(str_replace('\\', '/', $besoiuExcelPath)) ?></code>
            </div>
            <div class="cp-bdash-foot__actions">
                <label class="cp-btn cp-btn--ghost" style="cursor:pointer;">
                    <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Încarcă Excel
                    <input type="file" id="besoiuExcelFile" accept=".xlsx" hidden onchange="uploadBesoiuExcel(this)">
                </label>
                <button type="button" onclick="previewBesoiuTree()" class="cp-btn cp-btn--ghost"><i class="fa-solid fa-eye" aria-hidden="true"></i> Previzualizare</button>
                <button type="button" onclick="renewBesoiuTree()" class="cp-btn cp-btn--primary"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Reînnoiește din Excel</button>
            </div>
        </div>
        <p id="besoiu-tree-status" class="cp-status-line"></p>
    </section>

    <div class="cp-tools-grid cp-tools-grid--single">
        <div id="ollama-category-test" class="cp-panel cp-panel--ai">
            <div class="cp-panel__head">
                <div>
                    <h3 class="cp-panel__title">Test clasificare produs</h3>
                    <p class="cp-panel__desc">Verifică legătura ca la Import Pro: găsește <strong>L3 (ART_NAME)</strong> în denumire → urcă la <strong>L2</strong> și <strong>L1</strong> → afișează ce intră în coadă (<code>pCategory</code> / <code>pSubcategory</code>).</p>
                </div>
                <span id="ollamaCatStatusBadge" class="cp-badge cp-badge--warn">Se verifică Ollama…</span>
            </div>
            <div class="cp-panel__body">
                <div class="cp-form-grid">
                    <div class="cp-field cp-field--wide">
                        <label for="ollamaTestName">Denumire produs *</label>
                        <input id="ollamaTestName" type="text" placeholder="ex. MANN W712/75 Filtru ulei motor BMW">
                    </div>
                    <div class="cp-field">
                        <label for="ollamaTestBrand">Brand</label>
                        <input id="ollamaTestBrand" type="text" placeholder="ex. MANN-FILTER">
                    </div>
                    <div class="cp-field">
                        <label for="ollamaTestOem">OEM / cod</label>
                        <input id="ollamaTestOem" type="text" placeholder="ex. W712/75">
                    </div>
                    <div class="cp-field">
                        <label for="ollamaTestSpecs">Descriere / specs</label>
                        <input id="ollamaTestSpecs" type="text" placeholder="opțional">
                    </div>
                </div>
                <div class="cp-test-footer">
                    <label class="cp-check"><input id="ollamaTestBesoiuOnly" type="checkbox" checked> Doar arbore Besoiu (fără Ollama / TecDoc)</label>
                    <label class="cp-check"><input id="ollamaTestUseOllama" type="checkbox"> Fallback Ollama dacă Besoiu nu găsește</label>
                    <label class="cp-check"><input id="ollamaTestUseTecdoc" type="checkbox" checked> Fallback TecDoc OEM</label>
                    <button type="button" id="btnTestCategoryMatch" onclick="testCategoryMatch()" class="cp-btn cp-btn--primary">▶ Rulează test legătură</button>
                </div>
                <div id="ollamaTestResult" class="cp-result" style="display:none"></div>
            </div>
        </div>
    </div>

    <div class="cp-workspace">
        <div class="cp-workspace__toolbar">
            <button type="button" onclick="openAddModal()" data-bpa-help="Adaugă categorie" class="cp-btn cp-btn--primary">
                <i data-lucide="plus" class="h-4 w-4"></i> Adaugă categorie
            </button>
            <button type="button" id="btn-tecdoc-reference" data-action="open-tecdoc-reference" onclick="openTecdocModal()" class="cp-btn cp-btn--ghost">
                <i data-lucide="book-open" class="h-4 w-4"></i> Referință TecDoc
            </button>
            <?php if ($besoiuCount <= 0): ?>
            <button type="button" onclick="importDefaults()" class="cp-btn cp-btn--ghost">
                <i data-lucide="download" class="h-4 w-4"></i> Import categorii implicite
            </button>
            <?php endif; ?>
            <button type="button" onclick="backfillIcons()" class="cp-btn cp-btn--ghost">
                <i data-lucide="image" class="h-4 w-4"></i> Atribuie iconițe automat
            </button>
        </div>

        <nav class="cp-tabs" id="cpTabs" role="tablist" aria-label="Vizualizări catalog">
            <button type="button" role="tab" class="cp-tab cp-tab--besoiu <?= $effectiveView === 'besoiu' ? 'is-active' : '' ?>" data-view="besoiu" data-type="">
                Arbore Besoiu <span class="cp-tab__count">(<?= (int) $besoiuCount ?>)</span>
            </button>
            <button type="button" role="tab" class="cp-tab cp-tab--all <?= ($effectiveView === 'all' && $activeType === '') ? 'is-active' : '' ?>" data-view="all" data-type="">
                Catalog piese <span class="cp-tab__count">(<?= (int) ($besoiuCount + $otherCatalogCount) ?>)</span>
            </button>
            <?php if ($otherCatalogCount > 0): ?>
            <button type="button" role="tab" class="cp-tab cp-tab--other <?= $effectiveView === 'other' ? 'is-active' : '' ?>" data-view="other" data-type="">
                Catalog alternativ <span class="cp-tab__count">(<?= (int) $otherCatalogCount ?>)</span>
            </button>
            <?php endif; ?>
      
            <?php foreach (['marca', 'model', 'motorizare'] as $vehType): ?>
            <button type="button" role="tab" class="cp-tab cp-tab--<?= h_cat($vehType) ?> <?= ($effectiveView === 'all' && $activeType === $vehType) ? 'is-active' : '' ?>" data-view="all" data-type="<?= h_cat($vehType) ?>">
                <?= h_cat($types[$vehType] ?? $vehType) ?> <span class="cp-tab__count">(<?= (int) ($stats[$vehType] ?? 0) ?>)</span>
            </button>
            <?php endforeach; ?>
        </nav>

        <div class="cp-workspace-filters" id="cpWorkspaceFilters">
            <div class="cp-search-wrap cp-workspace-filters__search">
                <i class="fa-solid fa-magnifying-glass cp-workspace-filters__ico" aria-hidden="true"></i>
                <input id="searchBox" type="search" placeholder="Caută…" value="<?= h_cat($searchQ) ?>" autocomplete="off">
            </div>
            <label class="cp-workspace-filters__field">
                <span>Sursă</span>
                <select id="cpFilterSource">
                    <option value="all">Toate sursele</option>
                    <option value="besoiu_tree">Excel Besoiu</option>
                    <option value="pieseauto">PieseAuto</option>
                    <option value="local">Manual / local</option>
                </select>
            </label>
            <label class="cp-workspace-filters__field">
                <span>Tip catalog</span>
                <select id="cpFilterType">
                    <option value="all">Toate tipurile</option>
                    <?php foreach ($typeMeta as $tKey => $tMeta): ?>
                    <option value="<?= h_cat($tKey) ?>"><?= h_cat($tMeta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="cp-workspace-filters__field">
                <span>Vizibilitate</span>
                <select id="cpFilterStatus">
                    <option value="all">Toate</option>
                    <option value="active">Doar active</option>
                    <option value="inactive">Doar inactive</option>
                </select>
            </label>
        </div>

        <div id="cpPanelBesoiu" class="cp-tab-panel" data-panel="besoiu" <?= $effectiveView !== 'besoiu' ? 'hidden' : '' ?>>
        <p class="cp-panel__desc cp-panel__desc--tree">
            Arbore interactiv cu <strong><?= (int) $besoiuCount ?> noduri</strong> — expand/collapse animat, indentare clară pe nivel, ID · Parinte · Cale · ART_NAME.
        </p>
        <?php
        $categorii = $besoiuFlat;
        $total = count($besoiuFlat);
        include __DIR__ . '/_besoiu_tree.php';
        ?>
        </div>

        <div id="cpPanelTable" class="cp-tab-panel" data-panel="table" <?= $effectiveView === 'besoiu' ? 'hidden' : '' ?>>
        <?php if ($effectiveView === 'other'): ?>
        <p class="cp-panel__desc cp-panel__desc--tree">
            Intrări vechi sau duplicate (PieseAuto, manual, import implicit) — <strong>nu fac parte din arborele Besoiu</strong>.
            Folosește „Curăță catalog alternativ” sau „Reînnoiește arbore Besoiu” din panoul de sus.
        </p>
        <?php elseif ($effectiveView === 'all' && $activeType === 'marca'): ?>
        <div class="cp-notice cp-notice--info" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
            <div>
                <strong>Mărci auto din TecDoc.</strong>
                Ai <strong><?= (int) ($stats['marca'] ?? 0) ?></strong> mărci în DB.
                Sincronizarea adaugă mărcile din catalogul TecDoc (API + baza <code>tecdoc_product_compatibilities</code>) care lipsesc.
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <button type="button" onclick="previewTecdocMarci()" class="cp-btn cp-btn--ghost"><i class="fa-solid fa-eye" aria-hidden="true"></i> Previzualizare</button>
                <button type="button" onclick="syncTecdocMarci()" class="cp-btn cp-btn--primary"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Sincronizează din TecDoc</button>
            </div>
        </div>
        <p id="tecdoc-marci-status" class="cp-status-line"></p>
        <?php elseif ($effectiveView === 'all' && $activeType === 'model'): ?>
        <div class="cp-notice cp-notice--info" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
            <div>
                <strong>Modele vehicul din TecDoc.</strong>
                Ai <strong><?= (int) ($stats['model'] ?? 0) ?></strong> modele în DB.
                Sincronizarea adaugă perechi marcă+model din <code>tecdoc_product_compatibilities</code> și API TecDoc, legate de mărcile existente.
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <button type="button" onclick="previewTecdocModele()" class="cp-btn cp-btn--ghost"><i class="fa-solid fa-eye" aria-hidden="true"></i> Previzualizare</button>
                <button type="button" onclick="syncTecdocModele()" class="cp-btn cp-btn--primary"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Sincronizează din TecDoc</button>
            </div>
        </div>
        <p id="tecdoc-modele-status" class="cp-status-line"></p>
        <?php elseif ($effectiveView === 'all' && $activeType === ''): ?>
        <p class="cp-panel__desc cp-panel__desc--tree">
            Toate categoriile de piese (Besoiu + alternative). Mărcile/modelele/motorizările au tab-uri separate.
        </p>
        <?php endif; ?>
        <div class="cp-table-wrap">
            <table class="cp-table" id="categoriiTable">
                <thead>
                    <tr>
                        <th class="cp-num">#</th>
                        <th>Denumire</th>
                        <th>Slug URL</th>
                        <th>Tip taxonomie</th>
                        <th>Sursă</th>
                        <th>Icon</th>
                        <th style="text-align:center">Ord.</th>
                        <th>Părinte</th>
                        <th style="text-align:center">Vizibil</th>
                        <th style="text-align:center">Acțiuni</th>
                    </tr>
                </thead>
                <tbody id="cpTableBody"></tbody>
            </table>
            <div class="cp-empty" id="cpTableEmpty" hidden>
                <p class="cp-empty__title">Nu există categorii pentru filtrele curente.</p>
                <p>Schimbă filtrul sau importă arborele Besoiu din panoul de sus.</p>
            </div>
        </div>
        <div class="cp-pagination" id="cpTablePagination" hidden></div>
        </div>
    </div>
</div>

<!-- ═══ Modal Add/Edit Categorie ═══ -->
<div id="catModal" class="categorii-overlay-modal cp-modal-overlay" aria-hidden="true" hidden style="display:none">
    <div class="categorii-overlay-modal__panel cp-modal" role="dialog" aria-labelledby="modalTitle">
        <div class="cp-modal__header">
            <div>
                <h3 id="modalTitle" class="cp-modal-title">Adaugă intrare taxonomie</h3>
                <p class="cp-modal-sub">Completează denumirea și tipul — intrarea se sincronizează automat în DB și JSON.</p>
            </div>
            <button type="button" class="cp-modal__close" onclick="closeModal()" aria-label="Închide">×</button>
        </div>

        <form id="catForm" class="cp-modal__form" onsubmit="return saveCat(event)">
            <input type="hidden" id="cat_id" value="">
            <input type="hidden" id="cat_icon_path" value="">

            <div class="cp-modal__scroll">
                <section class="cp-modal-section cp-modal-section--identity">
                    <div class="cp-modal-section__title">Identitate</div>
                    <div class="cp-modal-grid cp-modal-grid--2">
                        <div class="cp-form-field">
                            <label for="cat_label">Denumire *</label>
                            <input type="text" id="cat_label" required placeholder="ex. Frâne, BMW, Seria 3">
                        </div>
                        <div class="cp-form-field">
                            <label for="cat_slug">Slug URL</label>
                            <input type="text" id="cat_slug" placeholder="auto-generat din denumire">
                        </div>
                        <div class="cp-form-field cp-form-field--full">
                            <label for="cat_type">Tip taxonomie</label>
                            <select id="cat_type">
                                <?php foreach ($typeMeta as $val => $meta): ?>
                                    <option value="<?= h_cat($val) ?>"><?= h_cat($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="cp-modal-section cp-modal-section--icon">
                    <div class="cp-modal-section__title">Iconiță</div>
                    <div class="cp-icon-picker">
                        <div class="cp-icon-picker__toolbar">
                            <label class="cp-btn cp-btn--ghost cp-icon-picker__upload">
                                Alege fișier
                                <input type="file" id="cat_icon_file" accept=".svg,.png,.jpg,.webp" onchange="previewIcon(this)">
                            </label>
                            <img id="cat_icon_preview" src="" alt="" class="cp-icon-picker__preview">
                            <span id="cat_icon_name" class="cp-icon-picker__name"></span>
                        </div>
                        <div id="cat_icon_presets" class="cp-icon-picker__grid">
                            <?php foreach ($iconPresets as $preset): ?>
                                <button type="button" class="cat-icon-preset cp-icon-preset" data-icon-path="<?= h_cat($preset['path']) ?>" title="<?= h_cat($preset['label']) ?>">
                                    <img src="/<?= h_cat($preset['path']) ?>" alt="<?= h_cat($preset['label']) ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="cp-modal-section cp-modal-section--hierarchy">
                    <div class="cp-modal-section__title">Ierarhie &amp; referințe</div>
                    <div class="cp-modal-grid cp-modal-grid--2">
                        <div class="cp-form-field">
                            <label for="cat_parent">ID părinte</label>
                            <input type="number" id="cat_parent" placeholder="gol = rădăcină">
                            <span class="cp-field-hint">Subcategorie → ID categorie; Model → ID marcă; Motorizare → ID model</span>
                        </div>
                        <div class="cp-form-field">
                            <label for="cat_sort">Ordine sortare</label>
                            <input type="number" id="cat_sort" value="0">
                        </div>
                        <div class="cp-form-field">
                            <label for="cat_tecdoc">TecDoc ID</label>
                            <input type="number" id="cat_tecdoc" placeholder="opțional">
                            <span class="cp-field-hint">Referință externă TecDoc (dacă există)</span>
                        </div>
                        <div class="cp-form-field cp-form-field--check">
                            <label class="cp-check cp-check--block">
                                <input type="checkbox" id="cat_active" checked>
                                <span>Vizibil pe site</span>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="cp-modal-section cp-modal-section--json">
                    <div class="cp-modal-section__title">Meta JSON (opțional)</div>
                    <div class="cp-form-field cp-form-field--full">
                        <label for="cat_meta">Meta</label>
                        <textarea id="cat_meta" rows="3" placeholder='{"source":"besoiu"}'></textarea>
                    </div>
                </section>
            </div>
        </form>

        <div class="cp-modal-footer">
            <button type="button" onclick="closeModal()" class="cp-btn cp-btn--ghost">Anulează</button>
            <button type="submit" form="catForm" class="cp-btn cp-btn--primary">Salvează &amp; sincronizează</button>
        </div>
    </div>
</div>

<!-- ═══ Modal Referință TecDoc (consultare — import structuri amânat tm_021) ═══ -->
<div id="tecdocModal" class="categorii-overlay-modal cp-modal-overlay" data-tm021="reference-only" aria-hidden="true" hidden style="display:none">
    <div class="categorii-overlay-modal__panel cp-modal cp-modal--wide" role="dialog" aria-labelledby="tecdocModalTitle">
        <div class="cp-modal__header">
            <div>
                <h3 id="tecdocModalTitle" class="cp-modal-title">Referință catalog TecDoc</h3>
                <p class="cp-modal-sub">Consultă mărci, modele, motorizări și categorii din catalogul TecDoc. La import, TecDoc intervine doar ca fallback după cod OEM — structura nu se importă în DB până la tm_021.</p>
            </div>
            <button type="button" class="cp-modal__close" onclick="closeTecdocModal()" aria-label="Închide">×</button>
        </div>

        <div class="cp-modal__scroll">
            <section class="cp-modal-section cp-modal-section--tecdoc-notice">
                <div class="cp-modal-section__title">Rol în pipeline import</div>
                <div class="cp-tecdoc-pipeline">
                    <span class="cp-tecdoc-pipeline__step">1. Match local</span>
                    <span class="cp-tecdoc-pipeline__arrow">→</span>
                    <span class="cp-tecdoc-pipeline__step">2. Ollama</span>
                    <span class="cp-tecdoc-pipeline__arrow">→</span>
                    <span class="cp-tecdoc-pipeline__step cp-tecdoc-pipeline__step--active">3. TecDoc OEM</span>
                    <span class="cp-tecdoc-pipeline__arrow">→</span>
                    <span class="cp-tecdoc-pipeline__step">4. Coadă import</span>
                </div>
                <p id="tecdoc-reference-notice" class="cp-tecdoc-notice" data-tm021="deferred-import"><?= h_cat($tecdocStructureImportNotice) ?></p>
            </section>

            <section class="cp-modal-section cp-modal-section--vehicul">
                <div class="cp-modal-section__title">Selectare vehicul (consultare)</div>
                <div class="cp-modal-grid cp-modal-grid--3">
                    <div class="cp-form-field">
                        <label for="td_marca">Marcă auto</label>
                        <select id="td_marca" onchange="tdLoadModels()">
                            <option value="0">Selectează marca…</option>
                            <option value="2">ALFA ROMEO</option><option value="5">AUDI</option><option value="16">BMW</option>
                            <option value="21">CITROEN</option><option value="139">DACIA</option><option value="35">FIAT</option>
                            <option value="36">FORD</option><option value="45">HONDA</option><option value="183">HYUNDAI</option>
                            <option value="184">KIA</option><option value="72">MAZDA</option><option value="74">MERCEDES-BENZ</option>
                            <option value="80">NISSAN</option><option value="84">OPEL</option><option value="88">PEUGEOT</option>
                            <option value="92">PORSCHE</option><option value="93">RENAULT</option><option value="104">SEAT</option>
                            <option value="106">SKODA</option><option value="109">SUZUKI</option><option value="111">TOYOTA</option>
                            <option value="120">VOLVO</option><option value="121">VW</option>
                        </select>
                    </div>
                    <div class="cp-form-field">
                        <label for="td_model">Model vehicul</label>
                        <select id="td_model" onchange="tdLoadMotor()" disabled>
                            <option value="0">Selectează modelul…</option>
                        </select>
                    </div>
                    <div class="cp-form-field">
                        <label for="td_motor">Motorizare</label>
                        <select id="td_motor" disabled>
                            <option value="0">Selectează motorizarea…</option>
                        </select>
                    </div>
                </div>
                <div class="cp-tecdoc-actions">
                    <button type="button" onclick="tdLoadCategories()" id="td_load_btn" class="cp-btn cp-btn--blue">Consultă categorii TecDoc</button>
                    <span id="td_status" class="cp-tecdoc-status"></span>
                </div>
            </section>

            <section id="td_categories_list" class="cp-modal-section cp-modal-section--catalog cp-tecdoc-results" style="display:none">
                <div class="cp-modal-section__title">Categorii disponibile (doar referință)</div>
                <div id="td_cat_items" class="cp-tecdoc-tree"></div>
            </section>
        </div>

        <div class="cp-modal-footer">
            <button type="button" onclick="closeTecdocModal()" class="cp-btn cp-btn--ghost">Închide</button>
        </div>
    </div>
</div>

<script>
const CRUD_URL = '/admin/api/categorii_endpoint.php';
const API_URL = '/admin/api/categorii_endpoint.php';
const TECDOC_URL = '/tecdoc_proxy.php';
const TECDOC_STRUCTURE_IMPORT_ENABLED = <?= $tecdocStructureImportEnabled ? 'true' : 'false' ?>;
const TECDOC_STRUCTURE_IMPORT_NOTICE = <?= json_encode($tecdocStructureImportNotice, JSON_UNESCAPED_UNICODE) ?>;

window.CP_WORKSPACE = <?= json_encode([
    'items' => $workspaceItems,
    'perPage' => 25,
    'initial' => [
        'view' => $effectiveView,
        'type' => $activeType,
        'q' => $searchQ,
    ],
    'typeMeta' => array_map(static fn (array $m): string => $m['label'], $typeMeta),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function editCatFromId(id) {
    if (!window.CP_WORKSPACE || !Array.isArray(window.CP_WORKSPACE.items)) return;
    const row = window.CP_WORKSPACE.items.find(function (x) { return Number(x.id) === Number(id); });
    if (row && row.edit) editCat(row.edit);
}

/* ═══ Icon Preview ═══ */
function setPresetIcon(path, label) {
    document.getElementById('cat_icon_path').value = path;
    const preview = document.getElementById('cat_icon_preview');
    const nameEl = document.getElementById('cat_icon_name');
    preview.src = '/' + path;
    preview.style.display = 'block';
    nameEl.textContent = label || path.split('/').pop();
    document.getElementById('cat_icon_file').value = '';
}

function previewIcon(input) {
    const preview = document.getElementById('cat_icon_preview');
    const nameEl = document.getElementById('cat_icon_name');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        nameEl.textContent = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

function fixCatModalScroll() {
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal || !modal.classList.contains('is-open')) return;
        const panel = modal.querySelector('.cp-modal');
        const header = modal.querySelector('.cp-modal__header');
        const footer = modal.querySelector('.cp-modal-footer');
        const scroll = modal.querySelector('.cp-modal__scroll');
        if (!panel || !header || !footer || !scroll) return;

        const panelRect = panel.getBoundingClientRect();
        const available = panelRect.height - header.offsetHeight - footer.offsetHeight - 2;
        if (available > 80) {
            scroll.style.maxHeight = available + 'px';
            scroll.style.height = available + 'px';
            scroll.style.overflowY = 'scroll';
        }
    });
}

/* ═══ Modal Add/Edit — mutat pe body via JS pentru scroll corect ═══ */
function mountCategoriiModalsOnBody() {
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });
}

/* ═══ Modal Add/Edit ═══ */
function setCategoriiModalOpen(modalId, open) {
    mountCategoriiModalsOnBody();
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.toggle('is-open', !!open);
    modal.hidden = !open;
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    modal.style.display = open ? 'flex' : 'none';
    const anyOpen = document.querySelector('#tecdocModal.is-open, #catModal.is-open');
    document.body.classList.toggle('categorii-modal-open', !!anyOpen);
    if (open) {
        requestAnimationFrame(function () {
            const scrollEl = modal.querySelector('.cp-modal__scroll');
            if (scrollEl) scrollEl.scrollTop = 0;
            fixCatModalScroll();
            setTimeout(fixCatModalScroll, 50);
            setTimeout(fixCatModalScroll, 200);
        });
    }
}

window.addEventListener('resize', function () {
    fixCatModalScroll();
});

(function () {
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.style.display = 'none';
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    mountCategoriiModalsOnBody();
    setCategoriiModalOpen('catModal', false);
    setCategoriiModalOpen('tecdocModal', false);
    loadOllamaCategoryStatus();
    document.getElementById('ollamaTestName')?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            testCategoryMatch();
        }
    });
    document.querySelectorAll('.cat-icon-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setPresetIcon(btn.getAttribute('data-icon-path') || '', btn.getAttribute('title') || '');
        });
    });
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                setCategoriiModalOpen(modalId, false);
            }
        });
    });
});

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Adaugă intrare taxonomie';
    document.getElementById('catForm').reset();
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_icon_path').value = '';
    document.getElementById('cat_active').checked = true;
    document.getElementById('cat_icon_preview').style.display = 'none';
    document.getElementById('cat_icon_preview').removeAttribute('src');
    document.getElementById('cat_icon_name').textContent = '';
    setCategoriiModalOpen('catModal', true);
}

function closeModal() {
    setCategoriiModalOpen('catModal', false);
}

function editCat(cat) {
    document.getElementById('modalTitle').textContent = 'Editează intrare taxonomie';
    document.getElementById('cat_id').value = cat.id || '';
    document.getElementById('cat_label').value = cat.label || '';
    document.getElementById('cat_slug').value = cat.slug || '';
    document.getElementById('cat_type').value = cat.type || 'categorie';
    document.getElementById('cat_icon_path').value = cat.icon || '';
    document.getElementById('cat_parent').value = cat.parent_id || '';
    document.getElementById('cat_sort').value = cat.sort_order || '0';
    document.getElementById('cat_tecdoc').value = cat.tecdoc_id || '';
    document.getElementById('cat_active').checked = parseInt(cat.is_active) === 1;
    document.getElementById('cat_meta').value = cat.meta || '';

    const preview = document.getElementById('cat_icon_preview');
    const nameEl = document.getElementById('cat_icon_name');
    if (cat.icon) {
        preview.src = '/' + cat.icon;
        preview.style.display = 'block';
        nameEl.textContent = cat.icon.split('/').pop();
    } else {
        preview.style.display = 'none';
        nameEl.textContent = '';
    }

    setCategoriiModalOpen('catModal', true);
}

async function saveCat(e) {
    e.preventDefault();
    const id = document.getElementById('cat_id').value;
    const fileInput = document.getElementById('cat_icon_file');
    const existingIcon = document.getElementById('cat_icon_path').value;

    let iconPath = existingIcon;

    if (fileInput.files && fileInput.files[0]) {
        const formData = new FormData();
        formData.append('icon', fileInput.files[0]);
        formData.append('action', 'upload_icon');
        const uploadRes = await fetch(API_URL + '?action=upload_icon', { method: 'POST', body: formData });
        const uploadJson = await uploadRes.json();
        if (uploadJson.success) {
            iconPath = uploadJson.path;
        } else {
            alert('Eroare upload icon: ' + (uploadJson.message || 'necunoscută'));
            return false;
        }
    }

    const payload = {
        type_product: id ? 'edit' : 'add',
        id: id || undefined,
        label: document.getElementById('cat_label').value,
        slug: document.getElementById('cat_slug').value,
        type: document.getElementById('cat_type').value,
        icon: iconPath,
        parent_id: document.getElementById('cat_parent').value || null,
        sort_order: document.getElementById('cat_sort').value || '0',
        tecdoc_id: document.getElementById('cat_tecdoc').value || null,
        is_active: document.getElementById('cat_active').checked ? 1 : 0,
        meta: document.getElementById('cat_meta').value || null,
    };

    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
        alert(json.message || 'Salvat.');
        location.reload();
    } else {
        alert(json.message || 'Eroare la salvare.');
    }
    return false;
}

/* ═══ CRUD actions ═══ */
async function deleteCat(id) {
    if (!confirm('Sigur vrei să ștergi această categorie?')) return;
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'delete', id })
    });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message || 'Eroare la ștergere.');
}

async function toggleCat(id, newValue) {
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'toggle', id, is_active: newValue })
    });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message || 'Eroare.');
}

async function importDefaults() {
    if (!confirm('Importă cele 8 categorii standard (Frâne, Filtre, etc.)? Iconițele lipsă vor fi completate.')) return;
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'import_defaults' })
    });
    const json = await res.json();
    alert(json.message || 'Done');
    if (json.success) location.reload();
}

async function backfillIcons() {
    if (!confirm('Atribuie automat iconițe pentru toate categoriile/subcategoriile fără icon?')) return;
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'backfill_icons' })
    });
    const json = await res.json();
    alert(json.message || 'Done');
    if (json.success) location.reload();
}

async function crudAction(typeProduct, extra = {}) {
    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(Object.assign({ action: typeProduct, type_product: typeProduct }, extra))
    });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        return { success: false, message: 'Răspuns invalid de la server (' + res.status + ').' };
    }
}

async function previewBesoiuTree() {
    const statusEl = document.getElementById('besoiu-tree-status');
    if (statusEl) statusEl.textContent = 'Parsez arborele Besoiu…';
    const json = await crudAction('preview_besoiu_tree');
    if (statusEl) statusEl.textContent = json.message || '';
    if (!json.success) alert(json.message || 'Eroare previzualizare.');
}

async function renewBesoiuTree() {
    if (!confirm('Reînnoiește complet catalogul piese?\n\n• Șterge toate categoriile vechi (PieseAuto, duplicate, manual)\n• Importă 629 noduri Besoiu din Excel (sau vizual.txt dacă Excel lipsește)\n• Reîncarcă sinonime + categorii secundare')) return;
    const statusEl = document.getElementById('besoiu-tree-status');
    if (statusEl) statusEl.textContent = 'Reînnoire din Excel…';
    const json = await crudAction('import_besoiu_tree', { renew: true });
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) location.href = '?view=besoiu&page=1';
    else alert(json.message || 'Eroare reînnoire.');
}

async function importBesoiuTree(keepExisting) {
    const msg = keepExisting
        ? 'Actualizează arborele Besoiu fără ștergerea intrărilor existente?'
        : 'Importă arborele Besoiu?\n\nSe șterg TOATE categoriile de piese (inclusiv PieseAuto/manual) și se reimportă arborele canonic.';
    if (!confirm(msg)) return;
    const statusEl = document.getElementById('besoiu-tree-status');
    if (statusEl) statusEl.textContent = 'Import arbore Besoiu…';
    const json = await crudAction('import_besoiu_tree', { keep_existing: keepExisting });
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) location.reload();
    else alert(json.message || 'Eroare import arbore Besoiu.');
}

async function uploadBesoiuExcel(input) {
    const file = input?.files?.[0];
    if (!file) return;
    const statusEl = document.getElementById('besoiu-tree-status');
    if (statusEl) statusEl.textContent = 'Upload Excel…';
    const formData = new FormData();
    formData.append('excel', file);
    formData.append('action', 'upload_besoiu_excel');
    formData.append('type_product', 'upload_besoiu_excel');
    const res = await fetch(API_URL, { method: 'POST', body: formData, credentials: 'same-origin' });
    const json = await res.json();
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) {
        alert(json.message || 'Excel încărcat. Rulează Import arbore Besoiu.');
        location.reload();
    } else {
        alert(json.message || 'Eroare upload Excel.');
    }
    input.value = '';
}

async function previewTecdocMarci() {
    const statusEl = document.getElementById('tecdoc-marci-status');
    if (statusEl) statusEl.textContent = 'Previzualizare mărci TecDoc…';
    const json = await crudAction('preview_tecdoc_marci');
    if (statusEl) statusEl.textContent = json.message || '';
    if (!json.success) alert(json.message || 'Eroare previzualizare.');
}

async function syncTecdocMarci() {
    if (!confirm('Sincronizezi mărcile auto din TecDoc?\n\n• Citește catalogul TecDoc (API dacă e armat + baza MySQL)\n• Adaugă mărcile care lipsesc din tab-ul Marcă auto\n• Actualizează ID-urile TecDoc unde e posibil')) return;
    const statusEl = document.getElementById('tecdoc-marci-status');
    if (statusEl) statusEl.textContent = 'Sincronizare mărci TecDoc…';
    const json = await crudAction('sync_tecdoc_marci');
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) location.href = '?view=all&type=marca&page=1';
    else alert(json.message || 'Eroare sincronizare TecDoc.');
}

async function previewTecdocModele() {
    const statusEl = document.getElementById('tecdoc-modele-status');
    if (statusEl) statusEl.textContent = 'Previzualizare modele TecDoc…';
    const json = await crudAction('preview_tecdoc_modele');
    if (statusEl) statusEl.textContent = json.message || '';
    if (!json.success) alert(json.message || 'Eroare previzualizare.');
}

async function syncTecdocModele() {
    if (!confirm('Sincronizezi modelele vehicul din TecDoc?\n\n• Citește perechi marcă+model din baza TecDoc MySQL\n• Leagă fiecare model de marca părinte\n• Poate dura câteva minute (mii de modele)')) return;
    const statusEl = document.getElementById('tecdoc-modele-status');
    if (statusEl) statusEl.textContent = 'Sincronizare modele TecDoc… (poate dura câteva minute)';
    const json = await crudAction('sync_tecdoc_modele');
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) location.href = '?view=all&type=model&page=1';
    else alert(json.message || 'Eroare sincronizare modele TecDoc.');
}

async function purgeAlternateCatalog() {
    if (!confirm('Ștergi toate categoriile de piese care NU provin din arborele Besoiu?\n\n(PieseAuto, import manual, categorii implicite vechi)\n\nArborele Besoiu rămâne neschimbat.')) return;
    const statusEl = document.getElementById('besoiu-tree-status');
    if (statusEl) statusEl.textContent = 'Curățare catalog alternativ…';
    const json = await crudAction('purge_alternate_catalog');
    if (statusEl) statusEl.textContent = json.message || '';
    if (json.success) location.href = '?view=besoiu&page=1';
    else alert(json.message || 'Eroare curățare.');
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] || ch;
    });
}

async function loadOllamaCategoryStatus() {
    const badge = document.getElementById('ollamaCatStatusBadge');
    if (!badge) return;
    try {
        const json = await crudAction('ollama_category_status');
        if (!json.success) {
            badge.textContent = json.message || 'Ollama: verificare eșuată';
            badge.className = 'cp-badge cp-badge--warn';
            return;
        }
        const st = json.status || {};
        badge.textContent = st.ready ? ('Ollama OK · ' + (st.text_model || 'model')) : (st.message_ro || 'Ollama indisponibil — testul local funcționează');
        badge.className = st.ready ? 'cp-badge cp-badge--ok' : 'cp-badge cp-badge--warn';
    } catch (e) {
        badge.textContent = 'Ollama: verificare offline — poți rula test local';
        badge.className = 'cp-badge cp-badge--warn';
    }
}

async function testCategoryMatch() {
    const btn = document.getElementById('btnTestCategoryMatch');
    const resultEl = document.getElementById('ollamaTestResult');
    const name = (document.getElementById('ollamaTestName')?.value || '').trim();
    if (!name) {
        alert('Introdu denumirea produsului.');
        return;
    }
    const besoiuOnly = document.getElementById('ollamaTestBesoiuOnly')?.checked === true;
    resultEl.style.display = 'block';
    resultEl.className = 'cp-result';
    resultEl.innerHTML = '<span class="cp-result__meta">Se analizează legătura L3 → L2 → L1…</span>';
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Se analizează…';
    }
    try {
        const json = await crudAction('match_category', {
            name: name,
            brand: (document.getElementById('ollamaTestBrand')?.value || '').trim(),
            oem: (document.getElementById('ollamaTestOem')?.value || '').trim(),
            specs: (document.getElementById('ollamaTestSpecs')?.value || '').trim(),
            use_ollama: besoiuOnly ? false : (document.getElementById('ollamaTestUseOllama')?.checked === true),
            use_tecdoc: besoiuOnly ? false : (document.getElementById('ollamaTestUseTecdoc')?.checked !== false),
        });
        if (!json.success || !json.match) {
            resultEl.className = 'cp-result cp-result--error';
            resultEl.innerHTML = escapeHtml(json.message || 'Eroare test.');
            return;
        }
        const m = json.match;
        const ok = !!m.ok;
        const l1 = m.level_1 || m.category || '—';
        const l2 = m.level_2 || m.subcategory || '—';
        const l3 = m.level_3 || m.art_name || '—';
        let html = '<div class="cp-result__meta"><strong>' + (ok ? 'Legătură găsită' : 'Fără potrivire Besoiu') + '</strong></div>';
        html += '<div class="cp-match-chain">';
        html += '<div class="cp-match-chain__step cp-match-chain__step--l3"><span class="cp-match-chain__lbl">L3 · ART_NAME în denumire</span><strong>' + escapeHtml(l3) + '</strong></div>';
        html += '<div class="cp-match-chain__arrow">↑</div>';
        html += '<div class="cp-match-chain__step cp-match-chain__step--l2"><span class="cp-match-chain__lbl">L2 · Familie</span><strong>' + escapeHtml(l2) + '</strong></div>';
        html += '<div class="cp-match-chain__arrow">↑</div>';
        html += '<div class="cp-match-chain__step cp-match-chain__step--l1"><span class="cp-match-chain__lbl">L1 · Grupă</span><strong>' + escapeHtml(l1) + '</strong></div>';
        html += '</div>';
        html += '<div class="cp-result__meta" style="margin-top:0.75rem;"><strong>Import Pro / coadă</strong></div>';
        html += '<div class="cp-match-import"><div><code>pCategory</code> = ' + escapeHtml(m.category || '—') + '</div>';
        html += '<div><code>pSubcategory</code> = ' + escapeHtml(m.subcategory || '—') + '</div></div>';
        html += '<div class="cp-result__meta">Metodă: <code>' + escapeHtml(m.method || '—') + '</code> · scor ' + (Math.round((m.confidence || 0) * 100)) + '%</div>';
        if (m.leaf_id || m.category_id || m.subcategory_id) {
            html += '<div class="cp-result__meta">ID: leaf #' + (m.leaf_id || '—') + ' · L1 #' + (m.category_id || '—') + ' · L2 #' + (m.subcategory_id || '—') + '</div>';
        }
        if (m.path) {
            html += '<div class="cp-result__meta">Cale: ' + escapeHtml(m.path) + '</div>';
        }
        if (m.reasoning) {
            html += '<div class="cp-result__meta" style="margin-top:0.5rem;">' + escapeHtml(m.reasoning) + '</div>';
        }
        const ollama = m.ollama || null;
        if (ollama && (ollama.ok || ollama.error)) {
            html += '<div class="cp-result__meta" style="margin-top:0.5rem;">Ollama: ' + (ollama.ok ? escapeHtml((ollama.category || '') + ' / ' + (ollama.subcategory || '')) : escapeHtml(ollama.error || '—')) + '</div>';
        }
        const tecdoc = m.tecdoc || null;
        if (tecdoc && (tecdoc.ok || tecdoc.error)) {
            html += '<div class="cp-result__meta">TecDoc: ' + (tecdoc.ok ? escapeHtml(tecdoc.art_name || '—') : escapeHtml(tecdoc.error || '—')) + '</div>';
        }
        resultEl.className = 'cp-result ' + (ok ? 'cp-result--success' : 'cp-result--error');
        resultEl.innerHTML = html;
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '▶ Rulează test legătură';
        }
    }
}

/* ═══ TecDoc Reference Modal (import amânat tm_021) ═══ */
function openTecdocModal() {
    setCategoriiModalOpen('tecdocModal', true);
}
function closeTecdocModal() {
    setCategoriiModalOpen('tecdocModal', false);
}

async function tdLoadModels() {
    const manuId = document.getElementById('td_marca').value;
    const selModel = document.getElementById('td_model');
    const selMotor = document.getElementById('td_motor');
    selModel.innerHTML = '<option value="0">Se încarcă...</option>';
    selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>';
    selMotor.disabled = true;

    if (manuId === '0') { selModel.disabled = true; selModel.innerHTML = '<option value="0">Alege modelul...</option>'; return; }
    selModel.disabled = false;

    try {
        const res = await fetch(TECDOC_URL + '?action=get_models&manuId=' + manuId);
        const data = await res.json();
        selModel.innerHTML = '<option value="0">Alege modelul...</option>';
        if (data && Array.isArray(data.models)) {
            data.models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.modelId;
                const yf = m.modelYearFrom ? String(m.modelYearFrom).substring(0, 4) : '';
                const yt = m.modelYearTo ? String(m.modelYearTo).substring(0, 4) : 'Prezent';
                opt.textContent = m.modelName + ' (' + yf + ' - ' + yt + ')';
                selModel.appendChild(opt);
            });
        }
    } catch (err) {
        selModel.innerHTML = '<option value="0">Eroare la server</option>';
    }
}

async function tdLoadMotor() {
    const modelId = document.getElementById('td_model').value;
    const selMotor = document.getElementById('td_motor');
    selMotor.innerHTML = '<option value="0">Se încarcă...</option>';

    if (modelId === '0') { selMotor.disabled = true; selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>'; return; }
    selMotor.disabled = false;

    try {
        const res = await fetch(TECDOC_URL + '?action=get_vehicles&modelId=' + modelId);
        const data = await res.json();
        selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>';
        if (data && Array.isArray(data.vehicles)) {
            data.vehicles.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.carId;
                opt.textContent = (v.typeName || v.typeEngineName || '') + ' (' + v.powerPs + ' CP / ' + v.powerKw + ' KW) - ' + (v.fuelType || '');
                selMotor.appendChild(opt);
            });
        } else if (data && data.vehicleTypeDetails) {
            const v = data.vehicleTypeDetails;
            const opt = document.createElement('option');
            opt.value = v.carId || modelId;
            opt.textContent = (v.typeEngineName || '') + ' (' + v.powerPs + ' CP / ' + v.powerKw + ' KW) - ' + (v.fuelType || '');
            selMotor.appendChild(opt);
        }
    } catch (err) {
        selMotor.innerHTML = '<option value="0">Eroare</option>';
    }
}

async function tdLoadCategories() {
    const carId = document.getElementById('td_motor').value;
    const statusEl = document.getElementById('td_status');
    const listEl = document.getElementById('td_categories_list');
    const itemsEl = document.getElementById('td_cat_items');

    if (!carId || carId === '0') {
        statusEl.textContent = 'Selectează marca, modelul și motorizarea mai întâi.';
        statusEl.className = 'cp-tecdoc-status cp-tecdoc-status--error';
        return;
    }

    statusEl.textContent = 'Se încarcă categoriile din TecDoc…';
    statusEl.className = 'cp-tecdoc-status cp-tecdoc-status--loading';
    itemsEl.innerHTML = '';
    listEl.style.display = 'none';

    try {
        const res = await fetch(TECDOC_URL + '?action=get_parts&carId=' + carId);
        const data = await res.json();

        if (!data || !data.categories || typeof data.categories !== 'object') {
            statusEl.textContent = 'Nu s-au găsit categorii pentru această motorizare.';
            statusEl.className = 'cp-tecdoc-status cp-tecdoc-status--error';
            return;
        }

        const categories = data.categories;
        let html = '';
        let count = 0;

        for (const [id, cat] of Object.entries(categories)) {
            const label = cat.text || cat.assemblyGroupName || cat.name || 'Categorie #' + id;
            const cleanLabel = label.replace(/\s*\([^)]*\)\s*$/, '').trim();
            html += '<div class="cp-tecdoc-tree__row cp-tecdoc-tree__row--parent">' +
                '<span class="cp-tecdoc-tree__label">' + escapeHtml(cleanLabel) + '</span>' +
                '<span class="cp-tecdoc-tree__id">ID ' + escapeHtml(String(id)) + '</span>' +
                '</div>';
            count++;

            if (cat.children && typeof cat.children === 'object') {
                for (const [childId, child] of Object.entries(cat.children)) {
                    const childLabel = child.text || child.assemblyGroupName || child.name || 'Sub #' + childId;
                    const cleanChild = childLabel.replace(/\s*\([^)]*\)\s*$/, '').trim();
                    html += '<div class="cp-tecdoc-tree__row cp-tecdoc-tree__row--child">' +
                        '<span class="cp-tecdoc-tree__label">↳ ' + escapeHtml(cleanChild) + '</span>' +
                        '<span class="cp-tecdoc-tree__id">ID ' + escapeHtml(String(childId)) + '</span>' +
                        '</div>';
                    count++;
                }
            }
        }

        itemsEl.innerHTML = html;
        listEl.style.display = 'block';
        statusEl.textContent = count + ' categorii găsite (doar referință).';
        statusEl.className = 'cp-tecdoc-status cp-tecdoc-status--ok';

    } catch (err) {
        statusEl.textContent = 'Eroare la încărcarea categoriilor: ' + err.message;
        statusEl.className = 'cp-tecdoc-status cp-tecdoc-status--error';
    }
}

</script>
<?php if (is_file($moduleTreeJs)): ?><script><?= file_get_contents($moduleTreeJs) ?></script><?php endif; ?>
<?php if (is_file($moduleWorkspaceJs)): ?><script><?= file_get_contents($moduleWorkspaceJs) ?></script><?php endif; ?>
<style id="cat-modal-scroll-fix">
/* Override terminal — după admin-mobile.css */
body.besoiu-admin-2026 #catModal.categorii-overlay-modal.is-open:not([hidden]),
body.besoiu-admin-2026 #tecdocModal.categorii-overlay-modal.is-open:not([hidden]) {
  display: flex !important;
  overflow-y: auto !important;
}
body.besoiu-admin-2026 #catModal.categorii-overlay-modal.is-open:not([hidden]) > .cp-modal,
body.besoiu-admin-2026 #catModal.categorii-overlay-modal.is-open:not([hidden]) > .categorii-overlay-modal__panel,
body.besoiu-admin-2026 #tecdocModal.categorii-overlay-modal.is-open:not([hidden]) > .cp-modal,
body.besoiu-admin-2026 #tecdocModal.categorii-overlay-modal.is-open:not([hidden]) > .categorii-overlay-modal__panel {
  display: grid !important;
  grid-template-rows: auto minmax(0, 1fr) auto !important;
  height: calc(100dvh - 2rem) !important;
  max-height: calc(100dvh - 2rem) !important;
  overflow: hidden !important;
}
body.besoiu-admin-2026 #catModal .cp-modal__scroll,
body.besoiu-admin-2026 #tecdocModal .cp-modal__scroll {
  overflow-y: scroll !important;
  min-height: 0 !important;
  touch-action: pan-y !important;
}
</style>
