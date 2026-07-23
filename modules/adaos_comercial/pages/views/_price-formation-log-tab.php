<?php
declare(strict_types=1);
/** Tab: log formare preț — verificare pași per produs / per import. */
$pflInitialMode = $pflInitialMode ?? 'product';
$pflDeepImportId = $pflDeepImportId ?? 0;
$pflInitialTrace = $pflInitialTrace ?? null;
$pflInitialError = $pflInitialError ?? null;
$pflSupplierOptions = $pflSupplierOptions ?? [];
$pflProductFormHidden = $pflInitialMode === 'import' ? ' pfl-is-hidden' : '';
$pflImportFormHidden = $pflInitialMode === 'product' ? ' pfl-is-hidden' : '';
$pflHasInitialTrace = is_array($pflInitialTrace) && !empty($pflInitialTrace['steps']);
$pflHasInitialError = is_string($pflInitialError) && trim($pflInitialError) !== '';

if (!function_exists('pfl_format_step_amount')) {
    function pfl_format_step_amount(array $step): string
    {
        $key = (string) ($step['key'] ?? '');
        $amount = (string) ($step['amount'] ?? '0');
        $amountRaw = (float) ($step['amount_raw'] ?? 0);
        $deltaKeys = ['compensator', 'markup_global', 'markup_brand', 'vat'];
        if (in_array($key, $deltaKeys, true)) {
            return ($amountRaw > 0 ? '+' : '') . $amount . ' lei';
        }

        return $amount . ' lei';
    }
}

if (!function_exists('pfl_render_trace_meta_html')) {
    function pfl_render_trace_meta_html(array $trace): string
    {
        $meta = is_array($trace['meta'] ?? null) ? $trace['meta'] : [];
        $checksOk = (bool) ($trace['checks_ok'] ?? false);
        $checks = $checksOk
            ? '<span class="pfl-status-ok">Pași coerenți cu prețul salvat</span>'
            : '<span class="pfl-status-warn">Preț salvat diferă de simulare — verifică regulile</span>';

        $html = '<strong>' . h_ac((string) ($meta['code'] ?? '—')) . '</strong> · ' . h_ac((string) ($meta['name'] ?? ''));
        $html .= '<br>Furnizor: <strong>' . h_ac((string) ($meta['supplier'] ?? '—')) . '</strong> · Brand: <strong>' . h_ac((string) ($meta['brand'] ?? '—')) . '</strong>';
        if (!empty($meta['rule_name'])) {
            $html .= ' · Regulă: <strong>' . h_ac((string) $meta['rule_name']) . '</strong>';
        }
        $html .= '<br>' . $checks;

        return $html;
    }
}

if (!function_exists('pfl_render_trace_steps_html')) {
    function pfl_render_trace_steps_html(array $trace): string
    {
        $html = '';
        foreach ($trace['steps'] ?? [] as $step) {
            if (!is_array($step)) {
                continue;
            }
            $key = (string) ($step['key'] ?? '');
            $isTotal = $key === 'final' || $key === 'purchase';
            $html .= '<div class="pfl-step' . ($isTotal ? ' is-total' : '') . '" data-step-key="' . h_ac($key) . '">';
            $html .= '<div class="pfl-step-label">' . h_ac((string) ($step['label'] ?? '')) . '</div>';
            $html .= '<div class="pfl-step-detail">' . h_ac((string) ($step['detail'] ?? '')) . '</div>';
            $html .= '<div class="pfl-step-amount">' . h_ac(pfl_format_step_amount($step)) . '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
?>

<div id="price-formation-log-panel" class="pfl-log-panel" data-tm="084">
    <div class="pfl-log-panel__head">
        <div>
            <h3 class="pfl-log-panel__title">Log formare preț</h3>
            <p class="pfl-log-panel__desc">
                Verifică pașii aplicați în ordine pentru fiecare produs sau rând din coada import.
            </p>
        </div>
    </div>

    <div class="pfl-flow-hint" aria-hidden="true">
        <span class="pfl-flow-chip">Preț feed</span>
        <span class="pfl-flow-chip">Compensator</span>
        <span class="pfl-flow-chip">Achiziție</span>
        <span class="pfl-flow-chip">TVA</span>
        <span class="pfl-flow-chip">Adaos global</span>
        <span class="pfl-flow-chip">Adaos brand</span>
        <span class="pfl-flow-chip">Preț final</span>
    </div>

    <div class="pfl-mode-bar" role="tablist" aria-label="Mod vizualizare log preț">
        <button type="button" class="pfl-mode-btn<?= $pflInitialMode === 'product' ? ' active' : '' ?>" data-pfl-mode="product">Per produs</button>
        <button type="button" class="pfl-mode-btn<?= $pflInitialMode === 'import' ? ' active' : '' ?>" data-pfl-mode="import">Per import (coadă)</button>
    </div>

    <div class="pfl-search-row<?= $pflProductFormHidden ?>" data-pfl-form="product">
        <label class="ac-field">
            <span>Cod produs / OEM / ID</span>
            <input id="pflProductQuery" class="ac-input w-64" type="text" placeholder="ex: 0 986 424 098">
        </label>
        <button type="button" id="pflProductSearch" class="ac-btn ac-btn--primary">
            Verifică pașii
        </button>
    </div>

    <div class="pfl-search-row<?= $pflImportFormHidden ?>" data-pfl-form="import">
        <label class="ac-field">
            <span>Furnizor (coadă import)</span>
            <select id="pflImportSupplier" class="ac-select min-w-[14rem]">
                <option value="">— Toți furnizorii —</option>
                <?php foreach ($pflSupplierOptions as $supplierOption): ?>
                    <?php
                    $supplierCode = is_array($supplierOption)
                        ? trim((string) ($supplierOption['code'] ?? ''))
                        : trim((string) $supplierOption);
                    if ($supplierCode === '') {
                        continue;
                    }
                    $supplierLabel = is_array($supplierOption)
                        ? trim((string) ($supplierOption['label'] ?? $supplierCode))
                        : $supplierCode;
                    $supplierCount = is_array($supplierOption) ? (int) ($supplierOption['count'] ?? 0) : 0;
                    $supplierText = $supplierLabel !== $supplierCode
                        ? $supplierLabel . ' (' . $supplierCode . ')'
                        : $supplierLabel;
                    if ($supplierCount > 0) {
                        $supplierText .= ' · ' . number_format($supplierCount, 0, ',', '.') . ' rânduri';
                    }
                    ?>
                    <option value="<?= h_ac($supplierCode) ?>"><?= h_ac($supplierText) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="ac-field">
            <span>ID rând import (un produs)</span>
            <input id="pflImportRowId" class="ac-input w-36" type="number" min="1" placeholder="ex: 42" value="<?= $pflDeepImportId > 0 ? h_ac((string) $pflDeepImportId) : '' ?>">
        </label>
        <button type="button" id="pflImportSearch" class="ac-btn ac-btn--primary">
            Încarcă log import
        </button>
    </div>
    <p class="mt-2 text-xs text-slate-500<?= $pflImportFormHidden ?>">Alege furnizor pentru listă (click pe rând → pași detaliați) sau completează ID pentru un singur produs din coadă.</p>

    <div id="pflResult" class="mt-4<?= $pflHasInitialTrace ? '' : ' pfl-is-hidden' ?>">
        <div id="pflMeta" class="pfl-meta"><?= $pflHasInitialTrace ? pfl_render_trace_meta_html($pflInitialTrace) : '' ?></div>
        <div id="pflSteps" class="pfl-steps"><?= $pflHasInitialTrace ? pfl_render_trace_steps_html($pflInitialTrace) : '' ?></div>
        <div id="pflBatchWrap" class="mt-4 pfl-is-hidden overflow-x-auto"></div>
    </div>
    <div id="pflEmpty" class="pfl-empty<?= ($pflHasInitialTrace || $pflHasInitialError) ? ' pfl-is-hidden' : '' ?>">
        Caută un produs sau un rând din coada import pentru a vedea pașii de formare preț.
    </div>
    <div id="pflError" class="pfl-error<?= $pflHasInitialError ? '' : ' pfl-is-hidden' ?>">
        <?= $pflHasInitialError ? h_ac($pflInitialError) : '' ?>
    </div>
    <div id="pflLoading" class="pfl-loading pfl-is-hidden">Se încarcă logul formării preț…</div>
</div>

<script>
(function () {
    'use strict';
    const CRUD_URL = '/admin/crudadaoscomercial';
    const modeBtns = document.querySelectorAll('[data-pfl-mode]');
    const forms = document.querySelectorAll('[data-pfl-form]');
    const resultEl = document.getElementById('pflResult');
    const emptyEl = document.getElementById('pflEmpty');
    const errorEl = document.getElementById('pflError');
    const loadingEl = document.getElementById('pflLoading');
    const metaEl = document.getElementById('pflMeta');
    const stepsEl = document.getElementById('pflSteps');
    const batchWrap = document.getElementById('pflBatchWrap');
    let mode = <?= json_encode($pflInitialMode, JSON_THROW_ON_ERROR) ?>;

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }

    function setMode(next, options) {
        const keepResult = !!(options && options.keepResult);
        const sameMode = mode === next;
        mode = next;
        modeBtns.forEach((btn) => btn.classList.toggle('active', btn.dataset.pflMode === mode));
        forms.forEach((form) => form.classList.toggle('pfl-is-hidden', form.dataset.pflForm !== mode));
        if (mode === 'import') {
            ensureImportSuppliersLoaded();
        }
        if (!keepResult && !sameMode) {
            clearResult();
        }
    }

    function setLoading(isLoading) {
        loadingEl?.classList.toggle('pfl-is-hidden', !isLoading);
    }

    function showError(message) {
        if (errorEl) {
            errorEl.textContent = message || 'Nu am putut încărca logul.';
            errorEl.classList.remove('pfl-is-hidden');
        }
        resultEl?.classList.add('pfl-is-hidden');
        emptyEl?.classList.add('pfl-is-hidden');
        setLoading(false);
    }

    function clearError() {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('pfl-is-hidden');
        }
    }

    function clearResult() {
        resultEl?.classList.add('pfl-is-hidden');
        emptyEl?.classList.remove('pfl-is-hidden');
        clearError();
        setLoading(false);
        if (stepsEl) stepsEl.innerHTML = '';
        if (metaEl) metaEl.innerHTML = '';
        if (batchWrap) {
            batchWrap.innerHTML = '';
            batchWrap.classList.add('pfl-is-hidden');
        }
    }

    async function api(payload) {
        const res = await fetch(CRUD_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ type_product: 'price_formation_trace', ...payload }),
        });
        const text = await res.text();
        try {
            const json = JSON.parse(text);
            if (!res.ok && json && json.message) {
                throw new Error(json.message);
            }
            return json;
        } catch (error) {
            if (error instanceof Error && error.message && !String(error.message).includes('JSON')) {
                throw error;
            }
            if (text.trim().startsWith('<')) {
                throw new Error('Acces refuzat sau sesiune expirată. Reîncarcă pagina și autentifică-te din nou.');
            }
            throw new Error('Răspuns invalid de la server.');
        }
    }

    let importSuppliersLoaded = false;

    function importSupplierSelectHasOptions() {
        const select = document.getElementById('pflImportSupplier');
        return !!select && select.options.length > 1;
    }

    function renderImportSupplierOptions(suppliers) {
        const select = document.getElementById('pflImportSupplier');
        if (!select || !Array.isArray(suppliers)) {
            return;
        }
        const current = select.value;
        while (select.options.length > 1) {
            select.remove(1);
        }
        suppliers.forEach((item) => {
            const code = String(item?.code || '').trim();
            if (!code) {
                return;
            }
            const label = String(item?.label || code).trim();
            const count = Number(item?.count || 0);
            let text = label !== code ? label + ' (' + code + ')' : label;
            if (count > 0) {
                text += ' · ' + count.toLocaleString('ro-RO') + ' rânduri';
            }
            const option = document.createElement('option');
            option.value = code;
            option.textContent = text;
            select.appendChild(option);
        });
        if (current && Array.from(select.options).some((opt) => opt.value === current)) {
            select.value = current;
        }
    }

    async function ensureImportSuppliersLoaded() {
        if (importSuppliersLoaded || importSupplierSelectHasOptions()) {
            importSuppliersLoaded = true;
            return;
        }
        try {
            const json = await api({ list_import_suppliers: 1 });
            if (json.success && Array.isArray(json.suppliers) && json.suppliers.length > 0) {
                renderImportSupplierOptions(json.suppliers);
                importSuppliersLoaded = true;
            }
        } catch (error) {
            // dropdown rămâne cu opțiunea „Toți furnizorii”
        }
    }

    function renderStepHtml(step) {
        const total = step.key === 'final' || step.key === 'purchase';
        const amount = step.key === 'compensator' || step.key === 'markup_global' || step.key === 'markup_brand' || step.key === 'vat'
            ? (Number(step.amount_raw || 0) > 0 ? '+' + esc(step.amount) + ' lei' : esc(step.amount) + ' lei')
            : esc(step.amount) + ' lei';
        return '<div class="pfl-step' + (total ? ' is-total' : '') + '" data-step-key="' + esc(step.key || '') + '">' +
            '<div class="pfl-step-label">' + esc(step.label) + '</div>' +
            '<div class="pfl-step-detail">' + esc(step.detail || '') + '</div>' +
            '<div class="pfl-step-amount">' + amount + '</div></div>';
    }

    function renderSteps(trace) {
        if (!stepsEl || !metaEl || !resultEl || !emptyEl) return;
        clearError();
        const meta = trace.meta || {};
        const checks = trace.checks_ok ? '<span class="pfl-status-ok">Pași coerenți cu prețul salvat</span>' : '<span class="pfl-status-warn">Preț salvat diferă de simulare — verifică regulile</span>';
        metaEl.innerHTML =
            '<strong>' + esc(meta.code || '—') + '</strong> · ' + esc(meta.name || '') +
            '<br>Furnizor: <strong>' + esc(meta.supplier || '—') + '</strong> · Brand: <strong>' + esc(meta.brand || '—') + '</strong>' +
            (meta.rule_name ? ' · Regulă: <strong>' + esc(meta.rule_name) + '</strong>' : '') +
            '<br>' + checks;

        stepsEl.innerHTML = (trace.steps || []).map(renderStepHtml).join('');

        batchWrap?.classList.add('pfl-is-hidden');
        resultEl.classList.remove('pfl-is-hidden');
        emptyEl.classList.add('pfl-is-hidden');
        setLoading(false);
    }

    function renderBatch(json) {
        if (!batchWrap || !metaEl || !resultEl || !emptyEl || !stepsEl) return;
        clearError();
        stepsEl.innerHTML = '';
        const items = json.items || [];
        metaEl.innerHTML = 'Import — <strong>' + items.length + '</strong> rânduri (total filtrat: ' + esc(json.total || 0) + ')';
        if (items.length === 0) {
            batchWrap.innerHTML = '<p class="text-sm text-slate-500">Niciun rând în coada import pentru filtrul ales.</p>';
        } else {
            let html = '<table class="pfl-batch-table"><thead><tr><th>ID</th><th>Cod</th><th>Furnizor</th><th>Feed→Final</th><th>Pași (sumar)</th></tr></thead><tbody>';
            items.forEach((item) => {
                const t = item.trace || {};
                const sum = t.summary || {};
                const stepsShort = (t.steps || []).map((s) => esc(s.label) + ' ' + esc(s.amount)).join(' → ');
                html += '<tr class="pfl-batch-row" data-import-id="' + esc(item.import_id) + '" title="Click pentru pașii detaliați">' +
                    '<td>' + esc(item.import_id) + '</td><td>' + esc(item.code) + '</td><td>' + esc(item.supplier) + '</td>' +
                    '<td>' + esc(sum.feed) + ' → ' + esc(sum.final) + '</td>' +
                    '<td style="font-size:11px;color:#64748b;max-width:420px;">' + stepsShort + '</td></tr>';
            });
            html += '</tbody></table>';
            batchWrap.innerHTML = html;
        }
        batchWrap.classList.remove('pfl-is-hidden');
        resultEl.classList.remove('pfl-is-hidden');
        emptyEl.classList.add('pfl-is-hidden');
        setLoading(false);
    }

    async function loadImportTraceById(rowId) {
        if (!rowId || rowId <= 0) {
            showError('ID import invalid.');
            return false;
        }
        clearError();
        setLoading(true);
        emptyEl?.classList.add('pfl-is-hidden');
        try {
            const json = await api({ import_id: rowId });
            if (!json.success) {
                showError(json.message || 'Rând import negăsit.');
                return false;
            }
            renderSteps(json.data);
            return true;
        } catch (error) {
            showError(error instanceof Error ? error.message : 'Nu am putut încărca logul.');
            return false;
        } finally {
            setLoading(false);
        }
    }

    modeBtns.forEach((btn) => btn.addEventListener('click', () => setMode(btn.dataset.pflMode || 'product')));

    document.getElementById('pflProductSearch')?.addEventListener('click', async () => {
        const query = document.getElementById('pflProductQuery')?.value?.trim() || '';
        if (!query) {
            alert('Introduce cod produs sau ID.');
            return;
        }
        clearError();
        setLoading(true);
        emptyEl?.classList.add('pfl-is-hidden');
        try {
            const json = await api({ product: query });
            if (!json.success) {
                showError(json.message || 'Nu am putut încărca logul.');
                return;
            }
            renderSteps(json.data);
        } catch (error) {
            showError(error instanceof Error ? error.message : 'Nu am putut încărca logul.');
        } finally {
            setLoading(false);
        }
    });

    async function loadImportBatch(supplier) {
        clearError();
        setLoading(true);
        emptyEl?.classList.add('pfl-is-hidden');
        try {
            const json = await api({ import_batch: 1, supplier, limit: 25, offset: 0 });
            if (!json.success) {
                showError(json.message || 'Nu am putut încărca batch-ul import.');
                return false;
            }
            renderBatch(json);
            return true;
        } catch (error) {
            showError(error instanceof Error ? error.message : 'Nu am putut încărca batch-ul import.');
            return false;
        } finally {
            setLoading(false);
        }
    }

    document.getElementById('pflImportSearch')?.addEventListener('click', async () => {
        const supplier = document.getElementById('pflImportSupplier')?.value?.trim() || '';
        const rowId = parseInt(document.getElementById('pflImportRowId')?.value || '0', 10);
        if (supplier !== '') {
            await loadImportBatch(supplier);
            return;
        }
        if (rowId > 0) {
            await loadImportTraceById(rowId);
            return;
        }
        await loadImportBatch('');
    });

    batchWrap?.addEventListener('click', async (event) => {
        const row = event.target.closest('.pfl-batch-row');
        if (!row) {
            return;
        }
        const importId = parseInt(row.dataset.importId || '0', 10);
        if (!importId) {
            return;
        }
        batchWrap.querySelectorAll('.pfl-batch-row.is-active').forEach((el) => el.classList.remove('is-active'));
        row.classList.add('is-active');
        const rowInput = document.getElementById('pflImportRowId');
        if (rowInput) {
            rowInput.value = String(importId);
        }
        await loadImportTraceById(importId);
    });

    window.besoiuPflApplyDeepLink = async function () {
        if (stepsEl && stepsEl.children.length > 0) {
            return;
        }
        const urlParams = new URLSearchParams(window.location.search);
        const importId = parseInt(urlParams.get('import_id') || document.getElementById('pflImportRowId')?.value || '0', 10);
        if (!importId) {
            return;
        }
        setMode('import', { keepResult: true });
        const rowInput = document.getElementById('pflImportRowId');
        if (rowInput) {
            rowInput.value = String(importId);
        }
        await loadImportTraceById(importId);
    };

    if (mode === 'import') {
        ensureImportSuppliersLoaded();
    }

    window.besoiuPflEnsureSuppliers = ensureImportSuppliersLoaded;
})();
</script>
