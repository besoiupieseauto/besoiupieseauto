<?php
declare(strict_types=1);

use Evasystem\Controllers\AdaosComercial\AdaosComercialService;
use Evasystem\Controllers\AdaosComercial\PriceFormationTraceService;
use Evasystem\Controllers\Produse\ProductFacetsService;
use Config\Database;
use Evasystem\Core\Produse\ProduseModel;

$ruleService = new AdaosComercialService();
$facetService = new ProductFacetsService();
$facetData = $facetService->getListFilters();
$commercialVatPercent = $ruleService->getCommercialVatPercent();
$globalCommercialMarkupPercent = $ruleService->getGlobalCommercialMarkupPercent();
$globalPriceRoundMode = $ruleService->getGlobalPriceRoundMode();
$globalPriceRoundValue = $ruleService->getGlobalPriceRoundValue();
$productCount = (new ProduseModel())->countActive();

$rules = $ruleService->getAll();

$categories = [];
$brands = [];
foreach ($facetData['categories'] ?? [] as $row) {
    $value = trim((string) ($row['label'] ?? ''));
    if ($value !== '') {
        $categories[$value] = true;
    }
}
foreach ($facetData['subcategories'] ?? [] as $row) {
    $value = trim((string) ($row['label'] ?? ''));
    if ($value !== '') {
        $categories[$value] = true;
    }
}
foreach ($facetService->getBrands() as $row) {
    $value = trim((string) ($row['label'] ?? ''));
    if ($value !== '') {
        $brands[$value] = true;
    }
}
foreach ($facetData['marci'] ?? [] as $row) {
    $value = trim((string) ($row['label'] ?? ''));
    if ($value !== '') {
        $brands[$value] = true;
    }
}

$categories = array_keys($categories);
$brands = array_keys($brands);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
sort($brands, SORT_NATURAL | SORT_FLAG_CASE);

$activeRules = array_values(array_filter($rules, static function (array $rule): bool {
    return (int)($rule['is_active'] ?? 0) === 1;
}));

function h_ac($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_rule_adjustment(array $rule): string
{
    $value = (float)($rule['adjustment_value'] ?? 0);
    $type = (string)($rule['adjustment_type'] ?? 'percentage');

    if ($type === 'fixed') {
        return '+' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' lei';
    }

    return '+' . rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
}

function format_rule_filters(array $rule): string
{
    $parts = [];

    if (!empty($rule['category_filter'])) {
        $parts[] = 'Categorie: ' . $rule['category_filter'];
    }
    if (!empty($rule['brand_filter'])) {
        $parts[] = 'Brand: ' . $rule['brand_filter'];
    }
    if ($rule['price_min'] !== null && $rule['price_min'] !== '') {
        $parts[] = 'Peste: ' . $rule['price_min'] . ' lei';
    }
    if ($rule['price_max'] !== null && $rule['price_max'] !== '') {
        $parts[] = 'Max: ' . $rule['price_max'] . ' lei';
    }

    return $parts ? implode(' | ', $parts) : 'Toate produsele';
}

$rulesJson = json_encode(array_values($rules), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$pflDeepImportId = isset($_GET['import_id']) ? max(0, (int) $_GET['import_id']) : 0;
$activeAdaosTab = (isset($_GET['tab']) && (string) $_GET['tab'] === 'price-log') || $pflDeepImportId > 0
    ? 'price-log'
    : 'rules';
$pflInitialMode = $pflDeepImportId > 0 ? 'import' : 'product';
$pflInitialTrace = null;
$pflInitialError = null;
if ($activeAdaosTab === 'price-log' && $pflDeepImportId > 0) {
    try {
        $pflTraceResult = (new PriceFormationTraceService())->traceByImportRowId($pflDeepImportId);
        if (($pflTraceResult['success'] ?? false) === true) {
            $pflInitialTrace = $pflTraceResult['data'] ?? null;
        } else {
            $pflInitialError = trim((string) ($pflTraceResult['message'] ?? ''))
                ?: 'Nu am putut încărca logul pentru rândul import #' . $pflDeepImportId . '.';
        }
    } catch (\Throwable $e) {
        $pflInitialError = 'Eroare la încărcarea logului: ' . $e->getMessage();
    }
}
try {
    $pflSupplierOptions = $activeAdaosTab === 'price-log'
        ? (new PriceFormationTraceService())->listImportQueueSuppliers()
        : [];
} catch (\Throwable $e) {
    $pflSupplierOptions = [];
}
?>
<style>
    .markup-card,
    .markup-table-row,
    .markup-btn-soft {
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background-color .16s ease;
    }
    .markup-card:hover,
    .markup-table-row:hover,
    .markup-btn-soft:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
    }
    .markup-kpi {
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 16px;
        padding: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.96));
    }
    .markup-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .markup-pill--active {
        background: #dcfce7;
        color: #15803d;
    }
    .markup-pill--inactive {
        background: #f1f5f9;
        color: #64748b;
    }
    .markup-preview-table td,
    .markup-preview-table th {
        padding: 10px 12px;
        text-align: left;
        white-space: nowrap;
    }
    .markup-preview-table tbody tr:nth-child(odd) {
        background: rgba(248, 250, 252, .8);
    }
    .adaos-page-tabs { display: flex; flex-wrap: wrap; gap: 0; margin-top: 20px; border-bottom: 1px solid #e5e7eb; }
    .adaos-page-tab {
        display: inline-flex; align-items: center; height: 40px; padding: 0 18px; margin-bottom: -1px;
        border: 1px solid transparent; border-radius: 10px 10px 0 0; background: transparent;
        font-size: 0.875rem; font-weight: 600; color: #64748b; cursor: pointer;
    }
    .adaos-page-tab:hover { color: #334155; background: #f8fafc; }
    .adaos-page-tab.active { color: #2563eb; background: #fff; border-color: #e5e7eb; border-bottom-color: #fff; }
    .adaos-page-pane { display: none; }
    .adaos-page-pane.active { display: block; }
</style>

<div class="-mt-5 adaos-comercial-page">
    <div>
        <h2 class="mt-10 text-lg font-medium">Adaus comercial</h2>
        <p class="mt-1 text-sm text-foreground/60">
            Singurul loc pentru adaosul comercial, TVA magazin și rotunjire globală. Compensatorul pre-import (0% / 5% / 10%) rămâne pe fiecare furnizor — tab Formare preț.
        </p>

        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-5 text-sm text-emerald-950">
            <strong>Formare preț în doi pași</strong>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li><strong>Preț furnizor (CSV)</strong> + compensator pre-import % → preț achiziție / preț bază (ex. 100 + 10% = 110 lei)</li>
                <li><strong>Preț bază</strong> + adaos comercial (regulile de mai jos) + TVA → preț final în magazin</li>
            </ol>
            <p class="mt-2 text-xs opacity-80">Nu configura adaos comercial pe profilul furnizorului — doar compensatorul feed.</p>
        </div>

        <div class="adaos-page-tabs" role="tablist" aria-label="Secțiuni adaos comercial">
            <button type="button" class="adaos-page-tab<?= $activeAdaosTab === 'rules' ? ' active' : '' ?>" data-adaos-page-tab="rules" aria-selected="<?= $activeAdaosTab === 'rules' ? 'true' : 'false' ?>">Reguli adaos</button>
            <button type="button" class="adaos-page-tab<?= $activeAdaosTab === 'price-log' ? ' active' : '' ?>" data-adaos-page-tab="price-log" aria-selected="<?= $activeAdaosTab === 'price-log' ? 'true' : 'false' ?>">Log formare preț</button>
        </div>

        <div id="adaos-page-pane-rules" class="adaos-page-pane<?= $activeAdaosTab === 'rules' ? ' active' : '' ?>" data-adaos-page-pane="rules" style="display:<?= $activeAdaosTab === 'rules' ? 'block' : 'none' ?>">

        <div class="mt-5 box rounded-xl border p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-medium">TVA pret final</h3>
                    <p class="mt-1 text-sm text-foreground/60">
                        Se aplica dupa adaosul comercial, pe pretul de baza (fara TVA din pasul furnizor).
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">TVA %</span>
                        <input id="commercial_vat_percent" class="box h-10 w-28 rounded-md border px-3" type="number" min="0" max="100" step="0.01" value="<?= h_ac((string)$commercialVatPercent) ?>">
                    </label>
                    <button type="button" id="saveCommercialVatBtn" class="markup-btn-soft box inline-flex h-10 items-center rounded-lg border bg-primary px-4 text-sm text-white">
                        Salveaza TVA
                    </button>
                </div>
            </div>
            <p class="mt-3 text-xs text-foreground/60">
                Exemplu: baza 100 lei + adaos 20% = 120 lei → + TVA <?= h_ac((string)$commercialVatPercent) ?>% = <?= h_ac(rtrim(rtrim(number_format(120 * (1 + $commercialVatPercent / 100), 2, '.', ''), '0'), '.')) ?> lei in magazin.
            </p>
        </div>

        <div class="mt-5 box rounded-xl border p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-medium">Adaos comercial global</h3>
                    <p class="mt-1 text-sm text-foreground/60">
                        Se aplică pe prețul de achiziție (BD) la import și la „Reaplică pe toate”. Exemplu: BD 110 lei + <?= h_ac(rtrim(rtrim(number_format($globalCommercialMarkupPercent, 2, '.', ''), '0'), '.')) ?>% → preț înainte de TVA magazin.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Adaos global %</span>
                        <input id="global_commercial_markup_percent" class="box h-10 w-28 rounded-md border px-3" type="number" min="0" max="1000" step="0.01" value="<?= h_ac(rtrim(rtrim(number_format($globalCommercialMarkupPercent, 2, '.', ''), '0'), '.')) ?>" placeholder="ex: 30">
                    </label>
                    <button type="button" id="saveGlobalCommercialMarkupBtn" class="markup-btn-soft box inline-flex h-10 items-center rounded-lg border bg-primary px-4 text-sm text-white">
                        Salvează adaos global
                    </button>
                    <button type="button" id="reapplyGlobalMarkupAllBtn" class="markup-btn-soft box inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm text-emerald-800">
                        Reaplică pe toate
                    </button>
                </div>
            </div>
            <p class="mt-3 text-xs text-foreground/60">
                Ordine formare preț: furnizor + adaos feed = BD → + adaos global % → + regulă condițională (dacă aplicată manual) → + TVA magazin → rotunjire.
            </p>
        </div>

        <div class="mt-5 box rounded-xl border p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-medium">Rotunjire preț magazin</h3>
                    <p class="mt-1 text-sm text-foreground/60">
                        Setare globală aplicată după TVA. Pe site, prețurile se afișează fără zecimale când rotunjirea este activă.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Mod rotunjire</span>
                        <select id="global_price_round_mode" class="box h-10 min-w-[220px] rounded-md border px-3">
                            <option value="none" <?= $globalPriceRoundMode === 'none' ? 'selected' : '' ?>>Fără rotunjire globală</option>
                            <option value="next_integer" <?= $globalPriceRoundMode === 'next_integer' ? 'selected' : '' ?>>La următorul întreg</option>
                            <option value="round_to" <?= $globalPriceRoundMode === 'round_to' ? 'selected' : '' ?>>Rotunjire la X lei</option>
                        </select>
                    </label>
                    <label class="block" id="global_price_round_value_wrap" style="<?= $globalPriceRoundMode === 'round_to' ? '' : 'display:none;' ?>">
                        <span class="mb-1 block text-sm font-medium">Rotunjire la (lei)</span>
                        <input id="global_price_round_value" class="box h-10 w-28 rounded-md border px-3" type="number" min="0.01" step="0.01" value="<?= h_ac(rtrim(rtrim(number_format($globalPriceRoundValue, 2, '.', ''), '0'), '.')) ?>" placeholder="ex: 5">
                    </label>
                    <button type="button" id="saveGlobalPriceRoundBtn" class="markup-btn-soft box inline-flex h-10 items-center rounded-lg border bg-primary px-4 text-sm text-white">
                        Salvează rotunjirea
                    </button>
                </div>
            </div>
            <p class="mt-3 text-xs text-foreground/60">
                Exemplu „La următorul întreg”: 1,29 lei → 2 lei. Exemplu „Rotunjire la 5 lei”: 23 lei → 25 lei.
            </p>
        </div>

        <div class="mt-5 grid grid-cols-12 gap-4">
            <div class="markup-kpi markup-card col-span-12 md:col-span-6 xl:col-span-3">
                <div class="text-xs uppercase tracking-wide text-foreground/60">Produse analizabile</div>
                <div class="mt-2 text-2xl font-semibold"><?= h_ac((string)$productCount) ?></div>
            </div>
            <div class="markup-kpi markup-card col-span-12 md:col-span-6 xl:col-span-3">
                <div class="text-xs uppercase tracking-wide text-foreground/60">Reguli salvate</div>
                <div class="mt-2 text-2xl font-semibold"><?= h_ac((string)count($rules)) ?></div>
            </div>
            <div class="markup-kpi markup-card col-span-12 md:col-span-6 xl:col-span-3">
                <div class="text-xs uppercase tracking-wide text-foreground/60">Reguli active</div>
                <div class="mt-2 text-2xl font-semibold"><?= h_ac((string)count($activeRules)) ?></div>
            </div>
            <div class="markup-kpi markup-card col-span-12 md:col-span-6 xl:col-span-3">
                <div class="text-xs uppercase tracking-wide text-foreground/60">Categorii găsite în produse</div>
                <div class="mt-2 text-2xl font-semibold"><?= h_ac((string)count($categories)) ?></div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-12 gap-6">
            <div class="col-span-12 xl:col-span-4">
                <div class="box p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 id="markupFormTitle" class="text-base font-medium">Regulă nouă</h3>
                            <p class="mt-1 text-sm text-foreground/60">Definește filtrele și adaosul comercial (pasul 2 din formarea prețului).</p>
                        </div>
                        <button type="button" onclick="resetMarkupForm()" class="markup-btn-soft rounded-lg border px-3 py-2 text-sm">Resetează</button>
                    </div>

                    <form id="markupForm" class="mt-5 grid grid-cols-12 gap-4">
                        <input type="hidden" id="rule_id" value="">

                        <label class="col-span-12">
                            <span class="mb-1 block text-sm font-medium">Nume regulă</span>
                            <input id="name" required class="box h-10 w-full rounded-md border px-3" type="text" placeholder="Ex: Motor BMW 15%">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Categorie</span>
                            <input id="category_filter" list="markupCategoryList" class="box h-10 w-full rounded-md border px-3" type="text" placeholder="Toate">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Brand</span>
                            <input id="brand_filter" list="markupBrandList" class="box h-10 w-full rounded-md border px-3" type="text" placeholder="Toate">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Preț minim (peste, strict)</span>
                            <input id="price_min" class="box h-10 w-full rounded-md border px-3" type="number" min="0" step="0.01" placeholder="ex: 2000 = peste 2000 RON">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Preț maxim</span>
                            <input id="price_max" class="box h-10 w-full rounded-md border px-3" type="number" min="0" step="0.01" placeholder="nelimitat">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Tip adaos</span>
                            <select id="adjustment_type" class="box h-10 w-full rounded-md border px-3">
                                <option value="percentage">Procentual</option>
                                <option value="fixed">Sumă fixă</option>
                            </select>
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Valoare adaos</span>
                            <input id="adjustment_value" required class="box h-10 w-full rounded-md border px-3" type="number" min="0" step="0.01" value="0">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Rotunjire la</span>
                            <input id="round_to" class="box h-10 w-full rounded-md border px-3" type="number" min="0.01" step="0.01" placeholder="Ex: 1 / 5 / 10">
                        </label>

                        <label class="col-span-12 md:col-span-6">
                            <span class="mb-1 block text-sm font-medium">Prioritate</span>
                            <input id="priority" class="box h-10 w-full rounded-md border px-3" type="number" step="1" value="100">
                        </label>

                        <label class="col-span-12">
                            <span class="mb-1 block text-sm font-medium">Notiță internă</span>
                            <textarea id="note" class="box min-h-[96px] w-full rounded-md border px-3 py-2" placeholder="Ex: se aplică doar la stocul nou intrat"></textarea>
                        </label>

                        <label class="col-span-12 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm">
                            <input id="is_active" type="checkbox" class="h-4 w-4">
                            Regula este activă (disponibilă pentru aplicare manuală — nu se aplică automat la import/salvare)
                        </label>

                        <div class="col-span-12 flex flex-wrap justify-end gap-2">
                            <button type="button" onclick="resetMarkupForm()" class="markup-btn-soft rounded-lg border px-4 py-2 text-sm">Anulează</button>
                            <button type="submit" class="markup-btn-soft rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white">Salvează regula</button>
                        </div>
                    </form>

                    <datalist id="markupCategoryList">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= h_ac($category) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <datalist id="markupBrandList">
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= h_ac($brand) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="col-span-12 xl:col-span-8">
                <div class="box p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-medium">Reguli salvate</h3>
                            <p class="mt-1 text-sm text-foreground/60">Previzualizezi întâi impactul, apoi aplici regula doar când ești sigur.</p>
                        </div>
                        <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            Total: <?= h_ac((string)count($rules)) ?> reguli
                        </div>
                    </div>

                    <?php if ($rules === []): ?>
                        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
                            Nu există reguli încă. Adaugă prima regulă din formularul din stânga.
                        </div>
                    <?php else: ?>
                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b text-foreground/60">
                                    <tr>
                                        <th class="px-3 py-3">Regulă</th>
                                        <th class="px-3 py-3">Filtre</th>
                                        <th class="px-3 py-3">Adaos</th>
                                        <th class="px-3 py-3">Rotunjire</th>
                                        <th class="px-3 py-3">Prioritate</th>
                                        <th class="px-3 py-3">Status</th>
                                        <th class="px-3 py-3 text-right">Acțiuni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $rule): ?>
                                        <?php $isActive = (int)($rule['is_active'] ?? 0) === 1; ?>
                                        <tr class="markup-table-row border-b align-top">
                                            <td class="px-3 py-4">
                                                <div class="font-medium text-slate-800"><?= h_ac($rule['name'] ?? '') ?></div>
                                                <?php if (!empty($rule['note'])): ?>
                                                    <div class="mt-1 text-xs text-slate-500"><?= h_ac($rule['note']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-4 text-slate-600"><?= h_ac(format_rule_filters($rule)) ?></td>
                                            <td class="px-3 py-4 font-medium text-slate-800"><?= h_ac(format_rule_adjustment($rule)) ?></td>
                                            <td class="px-3 py-4 text-slate-600">
                                                <?= ($rule['round_to'] !== null && $rule['round_to'] !== '') ? h_ac((string)$rule['round_to']) . ' lei' : 'Fără' ?>
                                            </td>
                                            <td class="px-3 py-4 text-slate-600"><?= h_ac((string)($rule['priority'] ?? 100)) ?></td>
                                            <td class="px-3 py-4">
                                                <span class="markup-pill <?= $isActive ? 'markup-pill--active' : 'markup-pill--inactive' ?>">
                                                    <?= $isActive ? 'Activă' : 'Inactivă' ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-4">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button type="button" onclick="editRuleById(<?= (int)$rule['id'] ?>)" class="markup-btn-soft rounded-lg border px-3 py-2 text-xs">Editează</button>
                                                    <button type="button" onclick="previewRule(<?= (int)$rule['id'] ?>)" class="markup-btn-soft rounded-lg border px-3 py-2 text-xs">Previzualizează</button>
                                                    <button type="button" onclick="applyRule(<?= (int)$rule['id'] ?>)" class="markup-btn-soft rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">Aplică</button>
                                                    <button type="button" onclick="toggleRule(<?= (int)$rule['id'] ?>, <?= $isActive ? '0' : '1' ?>)" class="markup-btn-soft rounded-lg border px-3 py-2 text-xs">
                                                        <?= $isActive ? 'Dezactivează' : 'Activează' ?>
                                                    </button>
                                                    <button type="button" onclick="deleteRule(<?= (int)$rule['id'] ?>)" class="markup-btn-soft rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-700">Șterge</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="box mt-6 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-medium">Previzualizare impact</h3>
                            <p class="mt-1 text-sm text-foreground/60">Aici vezi primele produse afectate și diferența de preț calculată.</p>
                        </div>
                        <button type="button" onclick="clearPreview()" class="markup-btn-soft rounded-lg border px-3 py-2 text-sm">Curăță</button>
                    </div>
                    <div id="previewPanel" class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
                        Selectează o regulă și apasă „Previzualizează” pentru a vedea ce produse vor fi modificate.
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div id="adaos-page-pane-price-log" class="adaos-page-pane<?= $activeAdaosTab === 'price-log' ? ' active' : '' ?>" data-adaos-page-pane="price-log" style="display:<?= $activeAdaosTab === 'price-log' ? 'block' : 'none' ?>">
            <?php include __DIR__ . '/_price-formation-log-tab.php'; ?>
        </div>
    </div>
</div>

<script>
const CRUD_URL = '/admin/crudadaoscomercial';
const rulesData = <?= $rulesJson ?: '[]' ?>;
const rulesMap = new Map(rulesData.map(rule => [String(rule.id), rule]));

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setFormTitle(text) {
    const title = document.getElementById('markupFormTitle');
    if (title) title.textContent = text;
}

function resetMarkupForm() {
    const form = document.getElementById('markupForm');
    form.reset();
    document.getElementById('rule_id').value = '';
    document.getElementById('priority').value = '100';
    document.getElementById('adjustment_value').value = '0';
    document.getElementById('is_active').checked = false;
    setFormTitle('Regulă nouă');
}

function editRule(rule) {
    document.getElementById('rule_id').value = rule.id || '';
    document.getElementById('name').value = rule.name || '';
    document.getElementById('category_filter').value = rule.category_filter || '';
    document.getElementById('brand_filter').value = rule.brand_filter || '';
    document.getElementById('price_min').value = rule.price_min ?? '';
    document.getElementById('price_max').value = rule.price_max ?? '';
    document.getElementById('adjustment_type').value = rule.adjustment_type || 'percentage';
    document.getElementById('adjustment_value').value = rule.adjustment_value ?? '0';
    document.getElementById('round_to').value = rule.round_to ?? '';
    document.getElementById('priority').value = rule.priority ?? '100';
    document.getElementById('note').value = rule.note || '';
    document.getElementById('is_active').checked = parseInt(rule.is_active || 0, 10) === 1;
    setFormTitle('Editează regula');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function editRuleById(id) {
    const rule = rulesMap.get(String(id));
    if (!rule) {
        alert('Regula selectată nu a fost găsită în listă.');
        return;
    }

    editRule(rule);
}

async function api(action, payload = {}) {
    const response = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: action, ...payload })
    });

    const json = await response.json();
    return json;
}

document.getElementById('saveCommercialVatBtn')?.addEventListener('click', async function () {
    const vat = document.getElementById('commercial_vat_percent')?.value ?? '21';
    const result = await api('save_vat', { commercial_vat_percent: vat });
    alert(result.message || 'TVA salvat.');
    if (result.success) {
        location.reload();
    }
});

function syncGlobalPriceRoundValueVisibility() {
    const mode = document.getElementById('global_price_round_mode')?.value ?? 'none';
    const wrap = document.getElementById('global_price_round_value_wrap');
    if (wrap) {
        wrap.style.display = mode === 'round_to' ? '' : 'none';
    }
}

document.getElementById('global_price_round_mode')?.addEventListener('change', syncGlobalPriceRoundValueVisibility);

document.getElementById('saveGlobalPriceRoundBtn')?.addEventListener('click', async function () {
    const mode = document.getElementById('global_price_round_mode')?.value ?? 'none';
    const value = document.getElementById('global_price_round_value')?.value ?? '1';
    const result = await api('save_price_round', {
        global_price_round_mode: mode,
        global_price_round_value: value
    });
    alert(result.message || 'Rotunjire salvată.');
    if (result.success) {
        location.reload();
    }
});

document.getElementById('saveGlobalCommercialMarkupBtn')?.addEventListener('click', async function () {
    const percent = document.getElementById('global_commercial_markup_percent')?.value ?? '0';
    const result = await api('save_global_markup', { global_commercial_markup_percent: percent });
    alert(result.message || 'Adaos global salvat.');
    if (result.success) {
        location.reload();
    }
});

document.getElementById('reapplyGlobalMarkupAllBtn')?.addEventListener('click', async function () {
    const percent = document.getElementById('global_commercial_markup_percent')?.value ?? '0';
    if (!confirm(`Reaplici adaosul comercial global (${percent}%) pe toate produsele cu preț de bază?`)) {
        return;
    }

    const btn = document.getElementById('reapplyGlobalMarkupAllBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Se reaplică…';
    }

    const result = await api('reapply_all');
    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Reaplică pe toate';
    }

    if (!result.success) {
        alert(result.message || 'Nu am putut reaplica adaosul global.');
        return;
    }

    const data = result.data || {};
    alert(
        (result.message || 'Adaos global reaplicat.') +
        '\nProduse actualizate: ' + (data.updated_count || 0) +
        '\nFără preț bază: ' + (data.zero_base_count || 0) +
        '\nDiferență totală: ' + (data.total_delta || 0) + ' lei'
    );
});

document.getElementById('markupForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const payload = {
        id: document.getElementById('rule_id').value || undefined,
        name: document.getElementById('name').value,
        category_filter: document.getElementById('category_filter').value,
        brand_filter: document.getElementById('brand_filter').value,
        price_min: document.getElementById('price_min').value,
        price_max: document.getElementById('price_max').value,
        adjustment_type: document.getElementById('adjustment_type').value,
        adjustment_value: document.getElementById('adjustment_value').value,
        round_to: document.getElementById('round_to').value,
        priority: document.getElementById('priority').value,
        note: document.getElementById('note').value,
        is_active: document.getElementById('is_active').checked ? 1 : 0
    };

    const result = await api('save', payload);
    alert(result.message || 'Regula a fost salvată.');

    if (result.success) {
        location.reload();
    }
});

async function toggleRule(id, isActive) {
    const result = await api('toggle', { id, is_active: isActive });
    alert(result.message || 'Starea regulii a fost schimbată.');

    if (result.success) {
        location.reload();
    }
}

async function deleteRule(id) {
    if (!confirm('Sigur vrei să ștergi această regulă de adaos?')) return;

    const result = await api('delete', { id });
    alert(result.message || 'Regula a fost ștearsă.');

    if (result.success) {
        location.reload();
    }
}

function clearPreview() {
    document.getElementById('previewPanel').innerHTML =
        '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">' +
        'Selectează o regulă și apasă „Previzualizează” pentru a vedea ce produse vor fi modificate.' +
        '</div>';
}

function formatDelta(value) {
    const numeric = Number(value || 0);
    if (Number.isNaN(numeric)) {
        return esc(value || 0);
    }

    return (numeric >= 0 ? '+' : '') + esc(value || 0);
}

async function previewRule(id) {
    const panel = document.getElementById('previewPanel');
    panel.innerHTML = '<div class="text-sm text-slate-500">Se calculează previzualizarea...</div>';

    const result = await api('preview', { id, limit: 25 });
    if (!result.success) {
        panel.innerHTML = '<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">' + esc(result.message || 'Nu am putut genera previzualizarea.') + '</div>';
        return;
    }

    const data = result.data || {};
    const products = Array.isArray(data.products) ? data.products : [];
    const zeroBaseCount = Number(data.zero_base_count || 0);
    const missingBaseCount = Number(data.missing_base_count || 0);

    let rows = '';
    products.forEach(product => {
        rows += '<tr>' +
            '<td>' + esc(product.name) + '</td>' +
            '<td>' + esc(product.category || '-') + '</td>' +
            '<td>' + esc(product.brand || '-') + '</td>' +
            '<td>' + esc(product.base_price) + ' lei</td>' +
            '<td class="font-medium text-emerald-700">' + esc(product.final_price) + ' lei</td>' +
            '<td class="font-medium text-sky-700">' + formatDelta(product.delta) + ' lei</td>' +
            '</tr>';
    });

    panel.innerHTML =
        '<div class="grid grid-cols-12 gap-4">' +
            '<div class="col-span-12 md:col-span-4 rounded-2xl border border-slate-200 bg-white p-4">' +
                '<div class="text-xs uppercase text-slate-500">Produse potrivite</div>' +
                '<div class="mt-2 text-2xl font-semibold text-slate-800">' + esc(data.matched_count || 0) + '</div>' +
            '</div>' +
            '<div class="col-span-12 md:col-span-4 rounded-2xl border border-slate-200 bg-white p-4">' +
                '<div class="text-xs uppercase text-slate-500">Produse modificate</div>' +
                '<div class="mt-2 text-2xl font-semibold text-slate-800">' + esc(data.changed_count || 0) + '</div>' +
            '</div>' +
            '<div class="col-span-12 md:col-span-4 rounded-2xl border border-slate-200 bg-white p-4">' +
                '<div class="text-xs uppercase text-slate-500">Creștere totală estimată</div>' +
                '<div class="mt-2 text-2xl font-semibold text-slate-800">' + esc(data.total_delta || 0) + ' lei</div>' +
            '</div>' +
        '</div>' +
        ((zeroBaseCount > 0 || missingBaseCount > 0)
            ? '<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">' +
              'Observație: ' + esc(zeroBaseCount) + ' produse au preț de bază 0, iar ' + esc(missingBaseCount) + ' nu au preț setat. Pentru acestea, adaosul procentual nu modifică prețul final.' +
              '</div>'
            : '') +
        '<div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">' +
            '<div class="mb-3 flex flex-wrap items-center justify-between gap-3">' +
                '<div>' +
                    '<div class="text-sm font-medium text-slate-800">' + esc((data.rule && data.rule.name) || 'Regulă') + '</div>' +
                    '<div class="mt-1 text-xs text-slate-500">Primele 25 produse afectate</div>' +
                '</div>' +
            '</div>' +
            (rows
                ? '<div class="overflow-x-auto"><table class="markup-preview-table w-full text-sm"><thead><tr class="border-b text-slate-500"><th>Produs</th><th>Categorie</th><th>Brand</th><th>Preț actual</th><th>Preț nou</th><th>Diferență</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                : '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Nu există produse care să corespundă regulii selectate.</div>') +
        '</div>';
}

async function applyRule(id) {
    if (!confirm('Aplici această regulă pe toate produsele eligibile?')) return;

    const result = await api('apply', { id });
    if (!result.success) {
        alert(result.message || 'Nu am putut aplica regula.');
        return;
    }

    const data = result.data || {};
    const zeroBaseCount = Number(data.zero_base_count || 0);
    const missingBaseCount = Number(data.missing_base_count || 0);
    const priceChangedCount = Number(data.price_changed_count || 0);
    const metadataUpdatedCount = Number(data.metadata_updated_count || 0);

    let message =
        (result.message || 'Regula a fost aplicată.') +
        '\nProduse potrivite: ' + (data.matched_count || 0) +
        '\nProduse actualizate: ' + (data.updated_count || 0) +
        '\n- preț schimbat: ' + priceChangedCount +
        '\n- metadate actualizate: ' + metadataUpdatedCount +
        '\nDiferență totală: ' + (data.total_delta || 0) + ' lei';

    if (zeroBaseCount > 0 || missingBaseCount > 0) {
        message +=
            '\n\nAtenție: ' + zeroBaseCount + ' produse au baza 0 și ' + missingBaseCount +
            ' produse nu au preț. La acestea, adaosul procentual nu schimbă prețul final.';
    }
    alert(message);

    previewRule(id);
}

function activateAdaosPageTab(id) {
    document.querySelectorAll('.adaos-page-tab').forEach((b) => {
        const active = b.dataset.adaosPageTab === id;
        b.classList.toggle('active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.adaos-page-pane').forEach((pane) => {
        const active = pane.dataset.adaosPagePane === id;
        pane.classList.toggle('active', active);
        pane.style.display = active ? 'block' : 'none';
    });
}

document.querySelectorAll('.adaos-page-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.adaosPageTab || 'rules';
        activateAdaosPageTab(id);
        if (id === 'price-log') {
            const u = new URL(window.location.href);
            u.searchParams.set('tab', 'price-log');
            window.history.replaceState({}, '', u.toString());
            if (typeof window.besoiuPflEnsureSuppliers === 'function') {
                void window.besoiuPflEnsureSuppliers();
            }
        }
    });
});

(function initAdaosPriceLogDeepLink() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') !== 'price-log' && !urlParams.get('import_id')) {
        return;
    }
    activateAdaosPageTab('price-log');
    const run = window.besoiuPflApplyDeepLink;
    if (typeof run === 'function') {
        void run().catch((error) => {
            console.error('PFL deep link failed', error);
        });
    }
})();
</script>
