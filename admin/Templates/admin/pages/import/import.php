<?php declare(strict_types=1);

use Evasystem\Core\AdminUrl;
use Evasystem\Controllers\AdaosComercial\AdaosComercialService;

$importApiUrl = AdminUrl::api('import_endpoint.php');
$importMarkupRules = (new AdaosComercialService())->getAll();
?>
<div class="-mt-5">
    <div>
        <h2 class="mt-10 text-lg font-medium">Import Produse</h2>
        <p class="mt-1 text-sm text-foreground/60">Cu fișiere UTF8 TecDoc + liste furnizor: produsele vin din CSV TecDoc (nume, OEM, compatibilități), prețul doar din Autonet/Elit. Fără CSV TecDoc, se folosește modul vechi (listă furnizor).</p>

        <div class="mt-6 grid grid-cols-12 gap-6">
            <!-- Fișiere recente -->
            <div class="col-span-12">
                <div style="border:1px solid #e5e7eb;border-radius:16px;padding:20px;background:#f8fafc;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
                        <strong style="font-size:15px;color:#1f2937;">Fișiere recent încărcate</strong>
                        <button type="button" onclick="loadUploadedFiles()" style="background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;">Reîncarcă lista</button>
                    </div>
                    <div id="recent-files-list" style="display:grid;gap:8px;min-height:48px;">
                        <div style="font-size:12px;color:#94a3b8;">Se încarcă lista fișierelor...</div>
                    </div>
                </div>
            </div>

            <!-- Pas 1: Furnizori -->
            <div class="col-span-12 lg:col-span-6">
                <div style="border:2px solid #bbf7d0;border-radius:16px;padding:24px;background:linear-gradient(180deg,#f0fdf4 0%,#fff 100%);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#059669;color:#fff;font-size:13px;font-weight:700;">1</span>
                        <strong style="font-size:16px;color:#065f46;">Liste preț furnizori</strong>
                    </div>
                    <p style="font-size:13px;color:#475569;margin-bottom:16px;">Autonet, Elit etc. — folosite <strong>doar pentru preț</strong> când ai CSV TecDoc UTF8 încărcat.</p>
                    <label style="display:inline-block;padding:10px 20px;background:#059669;color:#fff;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                        Încarcă fișiere furnizori
                        <input type="file" id="supplierFiles" accept=".csv,.xlsx,.txt" multiple style="display:none;" onchange="handleRoleUpload('supplier', this)">
                    </label>
                    <div id="supplier-files-list" style="margin-top:14px;display:grid;gap:8px;"></div>
                </div>
            </div>

            <!-- Pas 2: Opțiuni -->
            <div class="col-span-12 lg:col-span-6">
                <div style="border:2px solid #e9d5ff;border-radius:16px;padding:24px;background:linear-gradient(180deg,#faf5ff 0%,#fff 100%);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#7c3aed;color:#fff;font-size:13px;font-weight:700;">2</span>
                        <strong style="font-size:16px;color:#5b21b6;">Opțiuni import</strong>
                    </div>
                    <p style="font-size:13px;color:#475569;margin-bottom:16px;">Filtru brand, limită preview și modul de combinare fișiere.</p>
                    <div style="display:grid;gap:12px;">
                        <label style="display:grid;gap:6px;font-size:13px;color:#374151;">
                            <span>Mod import (când ai ambele tipuri de fișiere)</span>
                            <select id="importMode" onchange="updateStepStatus()" style="padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;">
                                <option value="tecdoc_master" selected>CSV TecDoc = produs, furnizor = preț (recomandat)</option>
                                <option value="supplier_master">Listă furnizor = produs, TecDoc = completare (vechi)</option>
                            </select>
                        </label>
                        <label style="display:grid;gap:6px;font-size:13px;color:#374151;">
                            <span>Filtru brand (opțional)</span>
                            <input type="text" id="brandFilter" placeholder="ex: GATES, BOSCH" style="padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
                        </label>
                        <label style="display:grid;gap:6px;font-size:13px;color:#374151;">
                            <span>Limită preview</span>
                            <select id="maxPreview" style="padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;">
                                <option value="100">100 produse</option>
                                <option value="250">250 produse</option>
                                <option value="500" selected>500 produse</option>
                            </select>
                        </label>
                    </div>
                    <details style="margin-top:16px;">
                        <summary style="font-size:13px;font-weight:600;color:#1d4ed8;cursor:pointer;">CSV TecDoc UTF8 (TableUseCarsForParts)</summary>
                        <p style="font-size:12px;color:#64748b;margin:10px 0;">Sursa principală pentru denumire, OEM, compatibilități și descriere SEO. Prețul vine exclusiv din listele furnizor.</p>
                        <label style="display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                            Încarcă fișier TecDoc CSV
                            <input type="file" id="tecdocFile" accept=".csv,.xlsx" style="display:none;" onchange="handleRoleUpload('tecdoc', this)">
                        </label>
                        <div id="tecdoc-files-list" style="margin-top:12px;display:grid;gap:8px;"></div>
                    </details>
                </div>
            </div>

            <!-- Consumabile: ulei, lichide, electrice -->
            <div class="col-span-12">
                <div style="border:2px solid #99f6e4;border-radius:16px;padding:24px;background:linear-gradient(180deg,#f0fdfa 0%,#fff 100%);">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                        <div>
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#0d9488;color:#fff;font-size:13px;font-weight:700;">★</span>
                                <strong style="font-size:16px;color:#115e59;">Scanează & importă consumabile (ulei · lichide · electrice)</strong>
                            </div>
                            <p style="font-size:13px;color:#475569;max-width:720px;margin:0;">
                                Folosește <strong>listele furnizor</strong> deja încărcate (Pas 1). Parcurge
                                <strong>întregul CSV</strong> (nu doar primele rânduri) și filtrează strict
                                <strong>uleiuri și lubrifianți</strong>, <strong>lichide auto</strong> (antigel, DOT, AdBlue) și
                                <strong>electrice auto</strong> (becuri, baterii, siguranțe fuzibile) — fără bujii, papuci/fișe bujie, lămpi, alternatoare, relee.
                                Descrierea: <strong>CSV TecDoc</strong> (ca Base.html — specs + compatibilități) sau <strong>TecDoc API</strong> (foaie dl/dt/dd).
                                Cataloagele tip Autonet/Elit sunt în mare parte piese de schimb; uleiurile apar mai rar (ex. Autototal).
                                <strong>Uleiuri și lichide:</strong> imaginile se iau din <strong>pipeline-ul Scraper</strong>
                                (planurile active din /admin/scraper — ePiesa, Autodoc, TecDoc API etc.).
                                Opțional verificare vitrină pe
                                <a href="https://www.epiesa.ro" target="_blank" rel="noopener" style="color:#0d9488;">ePiesa.ro</a>.
                            </p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" id="consumableScanBtn" onclick="runConsumableScan()" style="padding:10px 18px;background:#0d9488;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                                Scanează fișiere
                            </button>
                            <button type="button" id="consumableImportBtn" onclick="publishConsumables()" disabled style="padding:10px 18px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;opacity:.45;">
                                Importă pe site
                            </button>
                        </div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:16px 24px;margin-bottom:16px;align-items:flex-end;">
                        <fieldset style="border:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:12px 18px;">
                            <legend style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Categorii</legend>
                            <label style="font-size:13px;color:#374151;cursor:pointer;"><input type="checkbox" class="consumable-cat" value="ulei" checked style="margin-right:6px;"> Uleiuri și lubrifianți</label>
                            <label style="font-size:13px;color:#374151;cursor:pointer;"><input type="checkbox" class="consumable-cat" value="lichide" checked style="margin-right:6px;"> Lichide auto</label>
                            <label style="font-size:13px;color:#374151;cursor:pointer;"><input type="checkbox" class="consumable-cat" value="electrice" checked style="margin-right:6px;"> Electrice auto</label>
                        </fieldset>
                        <label style="display:grid;gap:4px;font-size:12px;color:#374151;">
                            <span>Limită import / rulare</span>
                            <select id="consumableLimit" style="padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#fff;">
                                <option value="10" selected>10 produse</option>
                                <option value="25">25 produse</option>
                                <option value="50">50 produse</option>
                            </select>
                        </label>
                        <label style="font-size:13px;color:#374151;cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="consumableEpiesa" checked> Verifică ePiesa + vitrină homepage
                        </label>
                    </div>

                    <div id="consumable-status" style="display:none;font-size:13px;padding:10px 14px;border-radius:8px;margin-bottom:12px;"></div>

                    <div id="consumable-log-wrap" style="display:none;margin-bottom:14px;">
                        <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Jurnal acțiuni</div>
                        <div id="consumable-log" style="max-height:180px;overflow:auto;background:#0f172a;color:#e2e8f0;border-radius:10px;padding:10px 12px;font-family:Consolas,monospace;font-size:11px;line-height:1.5;"></div>
                    </div>

                    <div id="consumable-preview-wrap" style="display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                            <span style="font-size:14px;font-weight:700;color:#1e293b;">Rezultat scanare <span id="consumable-count" style="color:#64748b;font-weight:500;"></span></span>
                            <label style="font-size:12px;color:#64748b;cursor:pointer;"><input type="checkbox" id="consumableSelectAll" checked onchange="toggleConsumableSelectAll()" style="margin-right:4px;"> Selectează tot</label>
                        </div>
                        <div style="overflow:auto;max-height:360px;border:1px solid #e5e7eb;border-radius:12px;">
                            <table style="width:100%;text-align:left;font-size:12px;border-collapse:collapse;" id="consumableTable">
                                <thead>
                                    <tr style="background:#f8fafc;border-bottom:1px solid #e5e7eb;">
                                        <th style="padding:8px 10px;width:32px;">✓</th>
                                        <th style="padding:8px 10px;">Cod</th>
                                        <th style="padding:8px 10px;">Denumire</th>
                                        <th style="padding:8px 10px;">Categorie</th>
                                        <th style="padding:8px 10px;">Brand</th>
                                        <th style="padding:8px 10px;">Preț</th>
                                        <th style="padding:8px 10px;">Furnizor</th>
                                        <th style="padding:8px 10px;">Stoc</th>
                                    </tr>
                                </thead>
                                <tbody id="consumableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pas 3: Generează -->
            <div class="col-span-12">
                <div style="border:2px solid #e5e7eb;border-radius:16px;padding:24px;background:#fff;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#7c3aed;color:#fff;font-size:13px;font-weight:700;">3</span>
                        <strong style="font-size:16px;color:#1e293b;">Generează lista de produse</strong>
                    </div>
                    <div id="step-status" style="font-size:13px;color:#64748b;margin-bottom:14px;">Încarcă listele furnizor, apoi apasă butonul de mai jos.</div>
                    <button type="button" id="generateBtn" onclick="generateProductList()" disabled style="padding:12px 28px;background:#7c3aed;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;opacity:.5;">
                        Generează lista de produse
                    </button>
                    <div id="file-names" style="margin-top:12px;font-size:13px;color:#059669;"></div>
                    <div id="job-progress-wrap" style="display:none;margin-top:16px;max-width:640px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#6b7280;margin-bottom:4px;gap:12px;">
                            <span id="job-progress-label">Procesare în fundal...</span>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span id="job-progress-pct">0%</span>
                                <button type="button" id="jobCancelBtn" onclick="cancelActiveJob()" style="display:none;padding:4px 10px;border:1px solid #fca5a5;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:600;cursor:pointer;">Oprește</button>
                            </div>
                        </div>
                        <div style="width:100%;height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                            <div id="job-progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg,#7c3aed,#2563eb);border-radius:999px;transition:width .25s;"></div>
                        </div>
                        <div id="job-progress-detail" style="font-size:11px;color:#9ca3af;margin-top:6px;"></div>
                    </div>
                </div>
            </div>

            <!-- Progress + debug -->
            <div class="col-span-12" id="upload-zone">
                <div id="progress-wrap" style="display:none;margin-top:0;max-width:520px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:4px;">
                        <span id="progress-label">Se încarcă fișierul...</span>
                        <span id="progress-pct">0%</span>
                    </div>
                    <div style="width:100%;height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                        <div id="progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg,#2563eb,#059669);border-radius:999px;transition:width .2s;"></div>
                    </div>
                    <div id="progress-size" style="font-size:11px;color:#9ca3af;margin-top:4px;"></div>
                </div>
                <div id="import-debug" style="display:none;margin-top:16px;max-width:760px;background:#111827;color:#e5e7eb;border-radius:12px;padding:12px 14px;text-align:left;box-shadow:0 10px 30px rgba(0,0,0,.2);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <strong style="font-size:12px;letter-spacing:.03em;">Debug Upload</strong>
                        <button type="button" onclick="clearDebugLog()" style="background:transparent;color:#9ca3af;border:1px solid #374151;border-radius:8px;padding:4px 8px;font-size:11px;cursor:pointer;">Curăță</button>
                    </div>
                    <div id="import-debug-log" style="font-family:Consolas,monospace;font-size:11px;line-height:1.5;max-height:220px;overflow:auto;white-space:pre-wrap;"></div>
                </div>
            </div>

            <!-- Preview table -->
            <div class="col-span-12" id="preview-section" style="display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div>
                        <span style="font-size:15px;font-weight:700;color:#1e293b;">Lista produse de importat</span>
                        <span id="preview-count" style="font-size:13px;color:#6b7280;margin-left:8px;"></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <label style="font-size:13px;color:#6b7280;cursor:pointer;" onclick="toggleSelectAll()">
                            <input type="checkbox" id="selectAllCb" checked style="margin-right:4px;"> Selectează tot
                        </label>
                        <button type="button" onclick="setPriceFilter('all')" id="filterAllBtn" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:12px;cursor:pointer;">Toate</button>
                        <button type="button" onclick="setPriceFilter('priced')" id="filterPricedBtn" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:12px;cursor:pointer;">Cu preț</button>
                        <button type="button" onclick="setPriceFilter('missing')" id="filterMissingBtn" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:12px;cursor:pointer;">Fără preț</button>
                        <label style="display:grid;gap:4px;font-size:12px;color:#374151;min-width:220px;">
                            <span>Regulă adaos la import (opțional)</span>
                            <select id="importMarkupRuleId" style="padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#fff;">
                                <option value="">Doar TVA comercial (fără regulă)</option>
                                <?php foreach ($importMarkupRules as $importRuleRow): ?>
                                    <?php
                                    $importRuleId = (int) ($importRuleRow['id'] ?? 0);
                                    if ($importRuleId <= 0) {
                                        continue;
                                    }
                                    $importRuleName = trim((string) ($importRuleRow['name'] ?? ('Regulă #' . $importRuleId)));
                                    $importRuleActive = (int) ($importRuleRow['is_active'] ?? 0) === 1;
                                    ?>
                                    <option value="<?= htmlspecialchars((string) $importRuleId, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($importRuleName, ENT_QUOTES, 'UTF-8') ?><?= $importRuleActive ? '' : ' (inactivă)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="button" onclick="importSelected()" id="importBtn" style="padding:10px 24px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                            Trimite selectate in coada
                        </button>
                    </div>
                </div>

                <div style="overflow:auto;max-height:520px;border:1px solid #e5e7eb;border-radius:12px;">
                    <table style="width:100%;text-align:left;font-size:13px;border-collapse:collapse;" id="previewTable">
                        <thead style="position:sticky;top:0;z-index:2;">
                            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                                <th style="padding:10px 12px;width:40px;text-align:center;">✓</th>
                                <th style="padding:10px 12px;">#</th>
                                <th style="padding:10px 12px;">Cod</th>
                                <th style="padding:10px 12px;">Denumire</th>
                                <th style="padding:10px 12px;">Brand</th>
                                <th style="padding:10px 12px;">Marcă auto</th>
                                <th style="padding:10px 12px;">Model</th>
                                <th style="padding:10px 12px;">Motorizare</th>
                                <th style="padding:10px 12px;">Preț final</th>
                                <th style="padding:10px 12px;">Preț bază</th>
                                <th style="padding:10px 12px;">Furnizor preț</th>
                                <th style="padding:10px 12px;">Regulă adaos</th>
                                <th style="padding:10px 12px;">TecDoc</th>
                                <th style="padding:10px 12px;">Caracteristici</th>
                                <th style="padding:10px 12px;">Categorie</th>
                                <th style="padding:10px 12px;">Subcategorie</th>
                                <th style="padding:10px 12px;">OEM</th>
                                <th style="padding:10px 12px;">KM</th>
                                <th style="padding:10px 12px;">Date tehnice</th>
                                <th style="padding:10px 12px;">Stoc</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>

                <div id="import-status" style="margin-top:16px;padding:12px 16px;border-radius:8px;display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
const IMPORT_URL = <?= json_encode($importApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const CHUNK_SIZE = 5 * 1024 * 1024;
const JOB_STEP_DELAY_MS = 80;
const JOB_FETCH_TIMEOUT_MS = 120000;
let parsedProducts = [];
let consumableProducts = [];
let uploadedFilesMeta = [];
let cachedUploadedFiles = [];
let priceFilter = 'all';
let activeJobAbort = false;
let activeJobId = '';
let activeJobController = null;

function setJobProgress(visible, pct = 0, label = '', detail = '') {
    const wrap = document.getElementById('job-progress-wrap');
    const bar = document.getElementById('job-progress-bar');
    const pctEl = document.getElementById('job-progress-pct');
    const labelEl = document.getElementById('job-progress-label');
    const detailEl = document.getElementById('job-progress-detail');
    const cancelBtn = document.getElementById('jobCancelBtn');
    if (!wrap || !bar || !pctEl || !labelEl || !detailEl) return;
    wrap.style.display = visible ? 'block' : 'none';
    if (cancelBtn) cancelBtn.style.display = visible ? 'inline-block' : 'none';
    const safePct = Math.max(0, Math.min(100, Number(pct) || 0));
    bar.style.width = safePct + '%';
    pctEl.textContent = safePct.toFixed(1).replace(/\.0$/, '') + '%';
    labelEl.textContent = label || 'Procesare în fundal...';
    detailEl.textContent = detail || '';
}

async function cancelJobOnServer(jobId) {
    if (!jobId) return;
    try {
        await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'import_job_cancel', job_id: jobId })
        });
    } catch (e) {
        debugLog('Nu am putut opri job-ul pe server: ' + e.message, 'warn');
    }
}

async function stopAllBackgroundJobs(showAlert = true) {
    activeJobAbort = true;
    if (activeJobController) {
        activeJobController.abort();
        activeJobController = null;
    }
    if (activeJobId) {
        await cancelJobOnServer(activeJobId);
        activeJobId = '';
    }
    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'import_job_cancel_all' })
        });
        const json = await parseJsonResponse(res, 'Oprire joburi');
        if (showAlert && json.message) {
            debugLog(json.message, 'warn');
        }
    } catch (e) {
        debugLog('Oprire joburi: ' + e.message, 'warn');
    }
    setJobProgress(false);
    resetJobButtons();
}

function cancelActiveJob() {
    stopAllBackgroundJobs(false).then(() => {
        debugLog('Proces oprit.', 'warn');
        alert('Proces oprit. Poți relua importul când vrei.');
    });
}

function resetJobButtons() {
    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.textContent = 'Generează lista de produse';
    }
    const importBtn = document.getElementById('importBtn');
    if (importBtn) {
        importBtn.disabled = false;
        importBtn.textContent = 'Trimite selectate in coada';
    }
}

async function parseJsonResponse(res, contextLabel) {
    const raw = await res.text();
    if (!raw.trim()) {
        throw new Error('Serverul a returnat răspuns gol la ' + contextLabel);
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        debugLog(contextLabel + ': JSON invalid', 'error');
        debugLog(raw.slice(0, 500), 'warn');
        if (raw.includes('524') || raw.toLowerCase().includes('timeout')) {
            throw new Error('Timeout server (524). Procesul rulează prea mult într-un singur pas — reîncearcă.');
        }
        throw new Error('Răspuns JSON invalid la ' + contextLabel);
    }
}

function jobFetchSignal(parentSignal) {
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
        return AbortSignal.timeout(JOB_FETCH_TIMEOUT_MS);
    }
    return parentSignal || undefined;
}

async function runBackgroundJob(startMode, stepMode, startPayload, contextLabel) {
    activeJobAbort = false;
    activeJobId = '';
    activeJobController = new AbortController();
    setJobProgress(true, 1, 'Pornesc ' + contextLabel + '...', '');

    const startRes = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ mode: startMode }, startPayload)),
        signal: jobFetchSignal(activeJobController.signal)
    });
    const startJson = await parseJsonResponse(startRes, contextLabel + ' (start)');
    if (!startJson.success || !startJson.job_id) {
        throw new Error(startJson.message || 'Nu am putut porni job-ul.');
    }

    const jobId = startJson.job_id;
    activeJobId = jobId;
    debugLog(contextLabel + ' pornit (job ' + jobId + ')', 'info');

    while (!activeJobAbort) {
        await new Promise(r => setTimeout(r, JOB_STEP_DELAY_MS));
        if (activeJobAbort) break;

        const stepRes = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: stepMode, job_id: jobId }),
            signal: jobFetchSignal(activeJobController.signal)
        });
        const stepJson = await parseJsonResponse(stepRes, contextLabel);

        if (!stepJson.success) {
            throw new Error(stepJson.message || 'Eroare la pasul job-ului.');
        }

        const status = stepJson.status || {};
        setJobProgress(
            true,
            status.progress || 0,
            status.message || 'Procesare...',
            status.phase ? ('Fază: ' + status.phase) : ''
        );

        if (status.cancelled || stepJson.cancelled) {
            throw new Error('Proces oprit.');
        }

        if (status.done) {
            setJobProgress(true, 100, status.message || 'Finalizat.', '');
            debugLog(contextLabel + ' finalizat.', 'ok');
            activeJobId = '';
            activeJobController = null;
            return stepJson;
        }

        if (status.failed) {
            throw new Error(status.error || status.message || 'Job eșuat.');
        }
    }

    throw new Error('Proces oprit.');
}

function formatPrice(val) {
    const v = String(val || '').trim();
    return v ? v + ' RON' : '—';
}

function hasProductPrice(product) {
    return String(product?.pPrice || '').trim() !== '';
}

function setPriceFilter(mode) {
    priceFilter = mode;
    ['all', 'priced', 'missing'].forEach(key => {
        const btn = document.getElementById('filter' + key.charAt(0).toUpperCase() + key.slice(1) + 'Btn');
        if (!btn) return;
        const active = (key === 'all' && mode === 'all') || (key === 'priced' && mode === 'priced') || (key === 'missing' && mode === 'missing');
        btn.style.background = active ? '#eef2ff' : '#fff';
        btn.style.borderColor = active ? '#6366f1' : '#d1d5db';
        btn.style.color = active ? '#4338ca' : '#374151';
    });
    renderPreview();
}

function debugLog(message, type = 'info') {
    const wrap = document.getElementById('import-debug');
    const log = document.getElementById('import-debug-log');
    if (!wrap || !log) return;
    wrap.style.display = 'block';
    const now = new Date().toLocaleTimeString('ro-RO');
    const color = type === 'error'
        ? '#fca5a5'
        : type === 'ok'
            ? '#86efac'
            : type === 'warn'
                ? '#fcd34d'
                : '#93c5fd';
    log.innerHTML += `<div style="color:${color}">[${now}] ${esc(message)}</div>`;
    log.scrollTop = log.scrollHeight;
}

function clearDebugLog() {
    const log = document.getElementById('import-debug-log');
    if (log) log.innerHTML = '';
}

function isTecdocFile(file) {
    if (file.resolved_role === 'tecdoc' || file.upload_role === 'tecdoc') return true;
    if (file.file_kind === 'tecdoc') return true;
    const name = String(file.original_name || '').toLowerCase();
    return name.includes('tableusecarsforparts')
        || name.includes('universal-csv-data')
        || name.includes('tecdoc');
}

function isSupplierFile(file) {
    if (file.resolved_role === 'supplier' || file.upload_role === 'supplier') return true;
    return typeof file.file_kind === 'string' && file.file_kind.startsWith('supplier:');
}

function roleBadge(file) {
    if (isTecdocFile(file)) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;">TecDoc</span>';
    }
    if (isSupplierFile(file)) {
        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;">Furnizor</span>';
    }
    return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:700;">Necategorizat</span>';
}

function renderFileItem(file, showBadge = false) {
    const status = file.completed
        ? '<span style="color:#059669;">complet</span>'
        : '<span style="color:#d97706;">incomplet</span>';
    return `
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;">
            <div style="min-width:0;flex:1;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    ${showBadge ? roleBadge(file) : ''}
                    <div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(file.original_name)}</div>
                </div>
                <div style="font-size:11px;color:#6b7280;">${formatSize(file.size)} | ${esc(file.updated_at)} | ${status}${file.file_kind_label ? ' | ' + esc(file.file_kind_label) : ''}</div>
            </div>
            <button type="button" onclick="deleteUploadedFile('${String(file.file_id).replace(/'/g, "\\'")}', '${String(file.original_name).replace(/'/g, "\\'")}')" style="padding:7px 12px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">Șterge</button>
        </div>
    `;
}

function updateStepStatus() {
    const tecdocFiles = cachedUploadedFiles.filter(f => f.completed && isTecdocFile(f));
    const supplierFiles = cachedUploadedFiles.filter(f => f.completed && isSupplierFile(f));
    const importMode = String(document.getElementById('importMode')?.value || 'tecdoc_master');
    const statusEl = document.getElementById('step-status');
    const generateBtn = document.getElementById('generateBtn');
    if (!statusEl || !generateBtn) return;

    const supplierOk = supplierFiles.length > 0;
    const tecdocOk = tecdocFiles.length > 0;
    const tecdocRequired = importMode === 'tecdoc_master';
    const ready = supplierOk && (!tecdocRequired || tecdocOk);

    statusEl.innerHTML = `
        <span style="color:${supplierOk ? '#059669' : '#dc2626'};">${supplierOk ? '✓' : '✗'} Furnizori (preț): ${supplierFiles.length}</span>
        &nbsp;|&nbsp;
        <span style="color:${tecdocOk ? '#2563eb' : (tecdocRequired ? '#dc2626' : '#64748b')};">${tecdocOk ? '✓' : (tecdocRequired ? '✗' : '○')} CSV TecDoc (produs): ${tecdocFiles.length}</span>
        &nbsp;|&nbsp;
        <span style="color:#64748b;">Mod: ${tecdocRequired ? 'TecDoc master' : 'Furnizor master'}</span>
    `;

    generateBtn.disabled = !ready;
    generateBtn.style.opacity = ready ? '1' : '.5';
    generateBtn.style.cursor = ready ? 'pointer' : 'not-allowed';
}

async function loadUploadedFiles() {
    const tecdocList = document.getElementById('tecdoc-files-list');
    const supplierList = document.getElementById('supplier-files-list');
    const recentList = document.getElementById('recent-files-list');
    if (!tecdocList || !supplierList || !recentList) return;

    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'list_uploaded' })
        });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.files)) {
            cachedUploadedFiles = [];
            tecdocList.innerHTML = '<div style="font-size:12px;color:#94a3b8;">Niciun fișier TecDoc încărcat.</div>';
            supplierList.innerHTML = '<div style="font-size:12px;color:#94a3b8;">Niciun fișier furnizor încărcat.</div>';
            recentList.innerHTML = '<div style="font-size:12px;color:#94a3b8;">Nu există fișiere încărcate recent.</div>';
            updateStepStatus();
            return;
        }

        cachedUploadedFiles = json.files;
        const completedFiles = json.files.filter(f => f.completed);
        const tecdocFiles = completedFiles.filter(isTecdocFile);
        const supplierFiles = completedFiles.filter(isSupplierFile);

        tecdocList.innerHTML = tecdocFiles.length
            ? tecdocFiles.map(f => renderFileItem(f)).join('')
            : '<div style="font-size:12px;color:#94a3b8;">Niciun fișier TecDoc încărcat.</div>';

        supplierList.innerHTML = supplierFiles.length
            ? supplierFiles.map(f => renderFileItem(f)).join('')
            : '<div style="font-size:12px;color:#94a3b8;">Niciun fișier furnizor încărcat.</div>';

        recentList.innerHTML = json.files.length
            ? json.files.map(f => renderFileItem(f, true)).join('')
            : '<div style="font-size:12px;color:#94a3b8;">Nu există fișiere încărcate recent.</div>';

        updateStepStatus();
    } catch (e) {
        debugLog('Nu am putut încărca lista fișierelor: ' + e.message, 'error');
        if (recentList) {
            recentList.innerHTML = '<div style="font-size:12px;color:#dc2626;">Eroare la încărcarea listei: ' + esc(e.message) + '</div>';
        }
    }
}

async function fetchUploadedFilesMeta() {
    const res = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'list_uploaded' })
    });
    const json = await res.json();
    if (!json.success || !Array.isArray(json.files)) {
        return [];
    }
    return json.files
        .filter(file => file.completed)
        .map(file => ({
            file_id: file.file_id,
            original_name: file.original_name,
            file_kind: file.file_kind || '',
            upload_role: file.upload_role || ''
        }));
}

async function previewUploadedFiles(fileMetas, options = {}) {
    const previewRes = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            mode: 'preview_uploaded',
            uploaded_files: fileMetas,
            brand_filter: options.brandFilter || '',
            max_preview: options.maxPreview || 500,
            force_supplier_api: !!options.forceSupplierApi,
            skip_tecdoc_csv_scan: options.skipTecdocCsvScan !== false
        })
    });
    const previewRaw = await previewRes.text();
    if (!previewRaw.trim()) {
        throw new Error('Serverul a returnat răspuns gol la preview');
    }
    let previewJson;
    try {
        previewJson = JSON.parse(previewRaw);
    } catch (e) {
        debugLog('Preview: JSON invalid', 'error');
        debugLog(previewRaw.slice(0, 500), 'warn');
        throw new Error('Răspuns JSON invalid la preview');
    }
    if (!previewJson.success) {
        throw new Error(previewJson.message || 'Nu am putut genera preview-ul');
    }
    return previewJson;
}

async function generateProductList() {
    const generateBtn = document.getElementById('generateBtn');
    const fileNames = document.getElementById('file-names');
    const supplierFiles = cachedUploadedFiles.filter(f => f.completed && isSupplierFile(f));
    const tecdocFiles = cachedUploadedFiles.filter(f => f.completed && isTecdocFile(f));
    const importMode = String(document.getElementById('importMode')?.value || 'tecdoc_master');

    if (supplierFiles.length === 0) {
        alert('Încarcă listele furnizor (Autonet, Elit) — necesare pentru preț.');
        return;
    }

    if (importMode === 'tecdoc_master' && tecdocFiles.length === 0) {
        alert('Mod TecDoc master: încarcă fișiere CSV UTF8 (TableUseCarsForParts) cu datele produselor.');
        return;
    }

    const brandFilter = String(document.getElementById('brandFilter')?.value || '').trim();
    const maxPreview = parseInt(document.getElementById('maxPreview')?.value || '500', 10) || 500;
    const hasTecdocCsv = tecdocFiles.length > 0;
    const useTecdocMaster = importMode === 'tecdoc_master' && hasTecdocCsv;

    try {
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.textContent = useTecdocMaster
                ? 'Procesare CSV TecDoc + preț furnizor...'
                : (hasTecdocCsv ? 'Procesare în fundal (mod furnizor)...' : 'Procesare în fundal...');
        }
        debugLog(useTecdocMaster
            ? 'Mod TecDoc master: produse din UTF8, preț din furnizori...'
            : (hasTecdocCsv
                ? 'Mod furnizor master: listă Autonet/Elit + completare TecDoc...'
                : 'Preview: doar listele furnizor...'), 'info');

        const allFiles = await fetchUploadedFilesMeta();
        const previewJson = await runBackgroundJob(
            'preview_job_start',
            'preview_job_step',
            {
                uploaded_files: allFiles,
                brand_filter: brandFilter,
                max_preview: maxPreview,
                tecdoc_max_rows_per_code: 30,
                import_mode: importMode,
                require_supplier_price: true
            },
            'Generare listă produse'
        );
        uploadedFilesMeta = allFiles;

        if (fileNames) {
            fileNames.textContent = previewJson.message || ((previewJson.products || []).length + ' produse gata de import');
            fileNames.style.color = '#059669';
        }

        parsedProducts = previewJson.products || [];
        renderPreview();
        debugLog('Lista de produse generată cu succes.', 'ok');

        document.getElementById('preview-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
        if (e.name === 'AbortError' || String(e.message || '').includes('Proces oprit')) {
            debugLog('Proces oprit de utilizator.', 'warn');
        } else {
            debugLog('Generare listă eșuată: ' + e.message, 'error');
            alert('Eroare: ' + e.message);
        }
        if (fileNames && e.name !== 'AbortError' && !String(e.message || '').includes('Proces oprit')) {
            fileNames.textContent = 'Eroare: ' + e.message;
            fileNames.style.color = '#dc2626';
        }
    } finally {
        activeJobId = '';
        activeJobController = null;
        setTimeout(() => setJobProgress(false), 800);
        updateStepStatus();
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generează lista de produse';
        }
    }
}

async function deleteUploadedFile(fileId, originalName) {
    if (!confirm('Sigur vrei să ștergi fișierul încărcat "' + originalName + '"?')) {
        return;
    }
    try {
        debugLog('Șterg fișierul încărcat: ' + originalName, 'warn');
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'delete_uploaded', file_id: fileId })
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Nu s-a putut șterge');
        }
        debugLog('Fișier șters: ' + originalName, 'ok');
        await loadUploadedFiles();
        updateStepStatus();
    } catch (e) {
        debugLog('Ștergerea a eșuat: ' + e.message, 'error');
        alert('Eroare la ștergere: ' + e.message);
    }
}

async function handleRoleUpload(role, input) {
    if (!input.files || input.files.length === 0) return;

    const files = role === 'tecdoc'
        ? [input.files[0]]
        : Array.from(input.files);

    await uploadFiles(files, role);
    input.value = '';
}

async function uploadFiles(files, uploadRole) {
    const fileNames = document.getElementById('file-names');
    const progressWrap = document.getElementById('progress-wrap');
    const progressBar = document.getElementById('progress-bar');
    const progressPct = document.getElementById('progress-pct');
    const progressLabel = document.getElementById('progress-label');
    const progressSize = document.getElementById('progress-size');

    const names = files.map(f => f.name).join(', ');
    const totalSize = files.reduce((s, f) => s + f.size, 0);
    if (fileNames) {
        fileNames.textContent = 'Se încarcă: ' + names;
        fileNames.style.color = '#64748b';
    }
    debugLog('Upload ' + uploadRole + ': ' + names, 'info');

    progressWrap.style.display = 'block';
    progressBar.style.width = '0%';
    progressPct.textContent = '0%';
    progressLabel.textContent = 'Se încarcă fișierul...';
    progressSize.textContent = formatSize(0) + ' / ' + formatSize(totalSize);
    progressBar.style.background = 'linear-gradient(90deg,#2563eb,#059669)';

    try {
        let totalUploaded = 0;

        for (const file of files) {
            const fileId = 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            debugLog(`Fișier: ${file.name} | bucăți: ${totalChunks} | mărime: ${formatSize(file.size)}`, 'info');

            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('mode', 'upload_chunk');
                formData.append('file_id', fileId);
                formData.append('original_name', file.name);
                formData.append('chunk_index', String(chunkIndex));
                formData.append('total_chunks', String(totalChunks));
                formData.append('upload_role', uploadRole);
                formData.append('chunk', chunk, file.name + '.part');

                const res = await fetch(IMPORT_URL, { method: 'POST', body: formData });
                const rawText = await res.text();
                if (!rawText.trim()) {
                    throw new Error('Serverul a returnat răspuns gol la chunk-ul ' + (chunkIndex + 1));
                }
                const json = JSON.parse(rawText);
                if (!json.success) {
                    throw new Error(json.message || 'Eroare upload chunk');
                }

                totalUploaded += chunk.size;
                const pct = Math.min(100, Math.round((totalUploaded / totalSize) * 100));
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
                progressSize.textContent = formatSize(totalUploaded) + ' / ' + formatSize(totalSize);
                progressLabel.textContent = 'Se încarcă: ' + file.name + ' (' + (chunkIndex + 1) + '/' + totalChunks + ')';
            }

            debugLog(`Fișier complet uploadat: ${file.name}`, 'ok');
        }

        progressWrap.style.display = 'none';
        if (fileNames) {
            fileNames.textContent = 'Upload finalizat. Apasă „Generează lista de produse”.';
            fileNames.style.color = '#059669';
        }
        await loadUploadedFiles();
    } catch (err) {
        progressLabel.textContent = 'Eroare la upload';
        progressBar.style.background = '#dc2626';
        if (fileNames) {
            fileNames.textContent = 'Eroare: ' + err.message;
            fileNames.style.color = '#dc2626';
        }
        debugLog('Upload oprit: ' + err.message, 'error');
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function renderPreview() {
    const section = document.getElementById('preview-section');
    const tbody = document.getElementById('previewBody');
    const countEl = document.getElementById('preview-count');

    const visibleProducts = parsedProducts.filter(p => {
        if (priceFilter === 'priced') return hasProductPrice(p);
        if (priceFilter === 'missing') return !hasProductPrice(p);
        return true;
    });

    if (visibleProducts.length === 0) {
        section.style.display = parsedProducts.length > 0 ? 'block' : 'none';
        if (tbody) tbody.innerHTML = '';
        if (countEl) countEl.textContent = parsedProducts.length > 0 ? '(0 produse în filtrul curent)' : '';
        return;
    }

    section.style.display = 'block';
    const pricedCount = parsedProducts.filter(hasProductPrice).length;
    countEl.textContent = '(' + visibleProducts.length + ' afișate din ' + parsedProducts.length + ', ' + pricedCount + ' cu preț)';

    tbody.innerHTML = visibleProducts.map((p) => {
        const i = parsedProducts.indexOf(p);
        const meta = extractProductMeta(p);
        const km = meta.km ? `${meta.km} km` : '';
        const technicalLabel = meta.technicalCount > 0 ? `${meta.technicalCount} câmpuri` : '';
        const specs = p.pSpecs || meta.specs || '';
        const priceMatch = meta.priceMatch || '';
        const priceClass = hasProductPrice(p) ? '#059669' : '#dc2626';
        const tecdocApiLabel = meta.tecdocFileFound
            ? '<span style="color:#059669;font-weight:600;">CSV</span>'
            : (meta.tecdocSkipped
                ? '<span style="color:#64748b;">—</span>'
                : '<span style="color:#dc2626;">Lipsă</span>');
        const oemDisplay = p.pOem || meta.oemText || '';
        return `
        <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:8px 12px;text-align:center;">
                <input type="checkbox" class="prod-cb" data-idx="${i}" checked style="width:16px;height:16px;">
            </td>
            <td style="padding:8px 12px;color:#9ca3af;">${i + 1}</td>
            <td style="padding:8px 12px;font-family:monospace;font-size:12px;">${esc(p.pCode)}</td>
            <td style="padding:8px 12px;font-weight:500;">${esc(p.pName)}</td>
            <td style="padding:8px 12px;">${esc(p.pBrand)}</td>
            <td style="padding:8px 12px;">${esc(p.pMarca)}</td>
            <td style="padding:8px 12px;">${esc(p.pModel)}</td>
            <td style="padding:8px 12px;">${esc(p.pMotorizare)}</td>
            <td style="padding:8px 12px;font-weight:600;color:${priceClass};">${formatPrice(p.pPrice)}</td>
            <td style="padding:8px 12px;color:#64748b;">${formatPrice(p.pBasePrice)}</td>
            <td style="padding:8px 12px;" title="${esc(priceMatch)}">${esc(p.pSupplier)}${priceMatch ? '<div style="font-size:11px;color:#64748b;">' + esc(priceMatch) + '</div>' : ''}</td>
            <td style="padding:8px 12px;font-size:12px;color:#64748b;">${esc(p.pMarkupRuleName) || '—'}</td>
            <td style="padding:8px 12px;font-size:12px;">${tecdocApiLabel}</td>
            <td style="padding:8px 12px;font-size:12px;color:#475569;max-width:260px;" title="${esc(specs)}">${esc(specs)}</td>
            <td style="padding:8px 12px;">${esc(p.pCategory)}</td>
            <td style="padding:8px 12px;">${esc(p.pSubcategory)}</td>
            <td style="padding:8px 12px;">${esc(oemDisplay)}</td>
            <td style="padding:8px 12px;">${esc(km)}</td>
            <td style="padding:8px 12px;">${esc(technicalLabel)}</td>
            <td style="padding:8px 12px;">${esc(p.pStock)}</td>
        </tr>
    `;
    }).join('');
}

function extractProductMeta(product) {
    try {
        const raw = JSON.parse(product.raw_json || '{}');
        const summary = raw.product_summary || {};
        const vehicle = summary.vehicle || {};
        const technical = Array.isArray(summary.technical_data) ? summary.technical_data : [];
        const supplierPrice = raw.supplier_price || {};
        const tecdocApi = raw.tecdoc_api || {};
        const tecdocFile = raw.tecdoc_file || {};
        let priceMatch = '';
        if (supplierPrice.matched_code) {
            priceMatch = 'Match: ' + supplierPrice.matched_code;
            if (supplierPrice.matched_via) {
                priceMatch += ' (' + supplierPrice.matched_via + ')';
            }
        }
        const codes = summary.codes || {};
        const oemList = Array.isArray(codes.coduri_oem) ? codes.coduri_oem.filter(Boolean) : [];
        let tecdocLabel = 'missing';
        if (tecdocFile.found) {
            tecdocLabel = 'file';
        } else if (tecdocApi.found) {
            tecdocLabel = 'api';
        } else if (tecdocApi.skipped) {
            tecdocLabel = 'skipped';
        }
        return {
            km: vehicle.kilometraj_km ? String(vehicle.kilometraj_km) : '',
            technicalCount: technical.length,
            specs: summary.specs || product.pSpecs || '',
            priceMatch,
            tecdocFound: tecdocLabel === 'api',
            tecdocFileFound: tecdocLabel === 'file',
            tecdocSkipped: tecdocLabel === 'skipped',
            oemText: oemList.length ? oemList.join(', ') : ''
        };
    } catch (e) {
        return { km: '', technicalCount: 0, specs: product.pSpecs || '', priceMatch: '', tecdocFound: false, tecdocFileFound: false, tecdocSkipped: false, oemText: '' };
    }
}

function esc(val) {
    if (!val) return '';
    return String(val).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAllCb').checked;
    document.querySelectorAll('.prod-cb').forEach(cb => cb.checked = checked);
}

async function importSelected() {
    const selected = [];
    document.querySelectorAll('.prod-cb:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        if (parsedProducts[idx]) selected.push(parsedProducts[idx]);
    });

    if (selected.length === 0) {
        alert('Selectează cel puțin un produs.');
        return;
    }

    const btn = document.getElementById('importBtn');
    const statusEl = document.getElementById('import-status');
    btn.disabled = true;
    btn.textContent = 'Procesare în fundal... (' + selected.length + ' produse)';
    statusEl.style.display = 'block';
    statusEl.style.background = '#f0fdf4';
    statusEl.style.color = '#059669';
    statusEl.textContent = 'Pregatesc ' + selected.length + ' produse in coada (proces in fundal)...';

    try {
        debugLog('Pornesc import în fundal pentru ' + selected.length + ' produse...', 'info');
        const json = await runBackgroundJob(
            'import_job_start',
            'import_job_step',
            {
                products: selected,
                markup_rule_id: parseInt(document.getElementById('importMarkupRuleId')?.value || '0', 10) || 0
            },
            'Import in coada'
        );

        statusEl.style.background = '#f0fdf4';
        statusEl.style.color = '#059669';
        statusEl.textContent = json.message || (json.count + ' produse importate cu succes!');
        btn.textContent = 'Trimitere finalizată!';
        debugLog('Import finalizat: ' + (json.message || ''), 'ok');
        if (json.redirect) {
            setTimeout(function() {
                window.location.href = json.redirect;
            }, 700);
        }
    } catch (err) {
        if (err.name === 'AbortError' || String(err.message || '').includes('Proces oprit')) {
            statusEl.style.background = '#fff7ed';
            statusEl.style.color = '#c2410c';
            statusEl.textContent = 'Proces oprit.';
            debugLog('Import întrerupt.', 'warn');
        } else {
            statusEl.style.background = '#fef2f2';
            statusEl.style.color = '#dc2626';
            statusEl.textContent = 'Eroare: ' + err.message;
            btn.disabled = false;
            btn.textContent = 'Trimite selectate in coada';
            debugLog('Import întrerupt: ' + err.message, 'error');
        }
    } finally {
        activeJobId = '';
        activeJobController = null;
        setTimeout(() => setJobProgress(false), 800);
    }
}

stopAllBackgroundJobs(false);

loadUploadedFiles();
setPriceFilter('all');

function getSelectedConsumableCategories() {
    return Array.from(document.querySelectorAll('.consumable-cat:checked')).map(cb => cb.value);
}

function setConsumableStatus(message, type = 'info') {
    const el = document.getElementById('consumable-status');
    if (!el) return;
    el.style.display = message ? 'block' : 'none';
    if (!message) return;
    const styles = {
        info: { bg: '#ecfeff', color: '#0e7490' },
        ok: { bg: '#f0fdf4', color: '#059669' },
        warn: { bg: '#fff7ed', color: '#c2410c' },
        error: { bg: '#fef2f2', color: '#dc2626' },
    };
    const s = styles[type] || styles.info;
    el.style.background = s.bg;
    el.style.color = s.color;
    el.textContent = message;
}

function appendConsumableLog(entries) {
    const wrap = document.getElementById('consumable-log-wrap');
    const log = document.getElementById('consumable-log');
    if (!wrap || !log || !Array.isArray(entries) || entries.length === 0) return;
    wrap.style.display = 'block';
    const colors = { ok: '#86efac', warn: '#fcd34d', error: '#fca5a5', info: '#93c5fd' };
    entries.forEach(entry => {
        const level = entry.level || 'info';
        const time = entry.at ? new Date(entry.at).toLocaleTimeString('ro-RO') : '';
        const line = document.createElement('div');
        line.style.color = colors[level] || colors.info;
        line.textContent = (time ? '[' + time + '] ' : '') + (entry.message || '');
        log.appendChild(line);
    });
    log.scrollTop = 0;
}

function clearConsumableLog() {
    const log = document.getElementById('consumable-log');
    const wrap = document.getElementById('consumable-log-wrap');
    if (log) log.innerHTML = '';
    if (wrap) wrap.style.display = 'none';
}

function renderConsumablePreview() {
    const wrap = document.getElementById('consumable-preview-wrap');
    const tbody = document.getElementById('consumableBody');
    const countEl = document.getElementById('consumable-count');
    const importBtn = document.getElementById('consumableImportBtn');
    if (!wrap || !tbody) return;

    if (!consumableProducts.length) {
        wrap.style.display = 'none';
        if (importBtn) {
            importBtn.disabled = true;
            importBtn.style.opacity = '.45';
        }
        return;
    }

    wrap.style.display = 'block';
    if (countEl) countEl.textContent = '(' + consumableProducts.length + ' găsite)';
    if (importBtn) {
        importBtn.disabled = false;
        importBtn.style.opacity = '1';
    }

    tbody.innerHTML = consumableProducts.map((p, i) => {
        const labels = (p.__consumable_labels || []).join(', ') || '—';
        return `<tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:8px 10px;text-align:center;"><input type="checkbox" class="consumable-cb" data-idx="${i}" checked></td>
            <td style="padding:8px 10px;font-family:monospace;">${esc(p.pCode)}</td>
            <td style="padding:8px 10px;font-weight:500;">${esc(p.pName)}</td>
            <td style="padding:8px 10px;">${esc(labels)}</td>
            <td style="padding:8px 10px;">${esc(p.pBrand)}</td>
            <td style="padding:8px 10px;font-weight:600;color:#059669;">${formatPrice(p.pPrice)}</td>
            <td style="padding:8px 10px;">${esc(p.pSupplier)}</td>
            <td style="padding:8px 10px;">${esc(p.pStock)}</td>
        </tr>`;
    }).join('');
}

function toggleConsumableSelectAll() {
    const checked = document.getElementById('consumableSelectAll')?.checked;
    document.querySelectorAll('.consumable-cb').forEach(cb => { cb.checked = !!checked; });
}

async function runConsumableScan() {
    const supplierFiles = cachedUploadedFiles.filter(f => f.completed && isSupplierFile(f));
    if (supplierFiles.length === 0) {
        alert('Încarcă mai întâi listele furnizor (Pas 1 — Autonet, Autototal, Elit etc.).');
        return;
    }

    const categories = getSelectedConsumableCategories();
    if (categories.length === 0) {
        alert('Selectează cel puțin o categorie (ulei, lichide, electrice).');
        return;
    }

    const btn = document.getElementById('consumableScanBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Scanare…';
    }
    clearConsumableLog();
    setConsumableStatus('Scanare fișiere furnizor…', 'info');

    try {
        const fileMetas = supplierFiles.map(f => ({
            file_id: f.file_id,
            original_name: f.original_name,
            file_kind: f.file_kind || '',
            upload_role: f.upload_role || ''
        }));

        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mode: 'consumable_scan_preview',
                uploaded_files: fileMetas,
                categories,
                brand_filter: String(document.getElementById('brandFilter')?.value || '').trim(),
                max_preview: 200
            })
        });
        const json = await parseJsonResponse(res, 'Scan consumabile');
        if (!json.success) {
            throw new Error(json.message || 'Scanare eșuată');
        }

        consumableProducts = json.products || [];
        renderConsumablePreview();
        const stats = json.stats || {};
        setConsumableStatus(
            json.message + (stats.total_scanned ? (' (din ' + stats.total_scanned + ' rânduri furnizor)') : ''),
            consumableProducts.length > 0 ? 'ok' : 'warn'
        );
        debugLog('Consumabile: ' + consumableProducts.length + ' produse filtrate.', consumableProducts.length ? 'ok' : 'warn');
        document.getElementById('consumable-preview-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) {
        consumableProducts = [];
        renderConsumablePreview();
        setConsumableStatus('Eroare: ' + e.message, 'error');
        debugLog('Scan consumabile: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Scanează fișiere';
        }
    }
}

async function publishConsumables() {
    const selected = [];
    document.querySelectorAll('.consumable-cb:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx, 10);
        if (consumableProducts[idx]) selected.push(consumableProducts[idx]);
    });

    if (selected.length === 0) {
        alert('Selectează cel puțin un produs din listă.');
        return;
    }

    const limit = parseInt(document.getElementById('consumableLimit')?.value || '10', 10) || 10;
    const checkEpiesa = !!document.getElementById('consumableEpiesa')?.checked;
    const importBtn = document.getElementById('consumableImportBtn');
    if (importBtn) {
        importBtn.disabled = true;
        importBtn.textContent = 'Import…';
    }
    clearConsumableLog();
    setConsumableStatus('Import pe site (max ' + limit + ')…', 'info');

    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mode: 'consumable_scan_publish',
                products: selected,
                limit,
                check_epiesa: checkEpiesa
            })
        });
        const json = await parseJsonResponse(res, 'Import consumabile');
        if (!json.success && !(json.stats && json.stats.published > 0)) {
            throw new Error(json.message || 'Import eșuat');
        }

        appendConsumableLog(json.log || []);
        setConsumableStatus(json.message || 'Import finalizat.', 'ok');
        debugLog(json.message || 'Import consumabile finalizat.', 'ok');
        if (importBtn) importBtn.textContent = 'Import finalizat ✓';
    } catch (e) {
        setConsumableStatus('Eroare: ' + e.message, 'error');
        debugLog('Import consumabile: ' + e.message, 'error');
        if (importBtn) {
            importBtn.disabled = false;
            importBtn.textContent = 'Importă pe site';
        }
    }
}
</script>
