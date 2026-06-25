<?php
declare(strict_types=1);

$scraperProjectRoot = dirname(__DIR__, 5);
require_once $scraperProjectRoot . '/lib/Scraper/EpiesaCatalog.php';
require_once $scraperProjectRoot . '/lib/Scraper/EpiesaCategories.php';

$scraperCatalogCount = EpiesaCatalog::productCount();

if (!function_exists('scraper_admin_h')) {
    function scraper_admin_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<div class="mt-6 scraper-page" id="scraper-app" data-catalog-count="<?= (int) $scraperCatalogCount ?>">
    <div id="scraper-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <!-- VEDERE 1: Carduri surse web -->
    <div id="sc-view-cards">
        <div class="sc-header">
            <div>
                <h2 class="sc-title">Surse web scraping</h2>
                <p class="sc-subtitle">Alege un site, <strong>Configurează</strong> pașii, testează — sau șterge sursele nefolosite și adaugă altele noi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="sc-add-source" class="sc-btn-primary">+ Sursă nouă</button>
                <span id="scraper-token-badge" class="sc-token-badge">—</span>
            </div>
        </div>

        <div id="sc-cards-grid" class="sc-cards-grid">
            <p class="text-sm opacity-60 col-span-full">Se încarcă sursele…</p>
        </div>

        <div class="sc-footer-links mt-6 flex flex-wrap gap-3 text-sm">
            <button type="button" id="sc-open-pipeline" class="sc-btn-primary sc-btn-sm">Pipeline imagini & import (Plan 1→2→3)</button>
            <button type="button" id="sc-open-epiesa-vitrina" class="sc-btn-outline">Vitrină ePiesa homepage (<?= (int) $scraperCatalogCount ?> produse)</button>
            <button type="button" id="sc-open-logs" class="sc-btn-outline">Loguri scraper</button>
            <button type="button" id="sc-restore-presets" class="sc-btn-outline opacity-70">Readaugă preseturi șterse</button>
            <button type="button" id="sc-sync-all" class="sc-btn-outline opacity-70">Sincronizare completă</button>
        </div>
    </div>

    <!-- VEDERE 2: Configurare sursă (ca profil furnizor) -->
    <div id="sc-view-detail" class="hidden">
        <div class="sc-header">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="sc-back-cards" class="sc-btn-outline sc-btn-sm">← Înapoi la surse</button>
                <div id="sc-detail-avatar" class="sc-card-avatar">—</div>
                <div>
                    <h2 class="sc-title" id="sc-detail-title">—</h2>
                    <p class="sc-subtitle" id="sc-detail-domain">—</p>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm" title="Activează sursa în pipeline/cron — NU pornește pașii individuali">
                <input type="checkbox" id="sc-detail-enabled" class="rounded border">
                <span>Sursă activă (pipeline)</span>
            </label>
            <button type="button" id="sc-delete-source" class="sc-btn-danger sc-btn-sm" title="Șterge sursa">Șterge</button>
        </div>

        <nav class="sc-tabs" role="tablist">
            <button type="button" class="sc-tab is-active" data-sc-tab="logica">Logică pași</button>
            <button type="button" class="sc-tab" data-sc-tab="testare">Testare</button>
            <button type="button" class="sc-tab" data-sc-tab="agent">Agent AI</button>
            <button type="button" class="sc-tab" data-sc-tab="output">Ce extrage</button>
            <button type="button" class="sc-tab" data-sc-tab="integrare">Integrare import</button>
        </nav>

        <!-- Tab Logică -->
        <div class="sc-tab-panel is-active" data-sc-tab-panel="logica">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm opacity-70 m-0">Construiește fluxul dinamic: adaugă pași (fetch, login, extragere…) și în fiecare pas adaugă elementele de care ai nevoie.</p>
                <button type="button" id="sc-add-step" class="sc-btn-primary sc-btn-sm">+ Adaugă pas</button>
            </div>
            <div id="sc-steps-container" class="space-y-4"></div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" id="sc-save-config" class="sc-btn-primary">Salvează logica</button>
                <button type="button" id="sc-apply-autodoc-preset" class="sc-btn-outline hidden">Aplică selectori Autodoc</button>
                <button type="button" id="sc-reset-config" class="sc-btn-outline">Resetează la implicit</button>
            </div>
            <label class="mt-4 block">
                <span class="mb-1 block text-xs font-semibold uppercase opacity-60">Notițe operator</span>
                <textarea id="sc-detail-notes" rows="3" class="box w-full rounded-md border px-3 py-2 text-sm" placeholder="Particularități site, captcha, ore bune de scan…"></textarea>
            </label>
        </div>

        <!-- Tab Testare (per sursă) -->
        <div class="sc-tab-panel" data-sc-tab-panel="testare">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 lg:col-span-4">
                    <div class="sc-box">
                        <h4 class="sc-box-title">Rulează test</h4>
                        <label class="sc-field">
                            <span>Query / termen căutare</span>
                            <input type="text" id="sc-test-query" class="box h-10 w-full rounded-md border px-3 text-sm">
                        </label>
                        <label class="sc-field">
                            <span>Limită rezultate</span>
                            <input type="number" id="sc-test-limit" min="1" max="20" value="5" class="box h-10 w-full rounded-md border px-3 text-sm">
                        </label>
                        <div class="sc-fetch-opts mb-3 space-y-2 text-sm">
                            <label class="flex items-center gap-2"><input type="checkbox" id="sc-fetch-super"> super=true (proxy rezidențial)</label>
                            <label class="flex items-center gap-2"><input type="checkbox" id="sc-fetch-render"> render JS (obligatoriu Autodoc/eMAG)</label>
                        </div>
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-2">
                            Autodoc: bifează <strong>ambele</strong>. Fără render JS scrape.do returnează eroare 502.
                        </p>
                        <button type="button" id="sc-run-test" class="sc-btn-primary w-full">Testează cu logica salvată</button>
                        <button type="button" id="sc-analyze-html" class="sc-btn-outline w-full mt-2">Analizează ultimul HTML (fără fetch)</button>
                        <p class="mt-2 text-xs opacity-60">Test complet ~60–90s via scrape.do. «Analizează HTML» folosește fișierul salvat la Pas 1 — util pentru debug selectori.</p>
                    </div>
                </div>
                <div class="col-span-12 lg:col-span-8">
                    <div class="sc-box mb-4">
                        <h4 class="sc-box-title">Trace pași (1 → 2 → 3)</h4>
                        <div id="sc-test-trace" class="sc-trace">Niciun test rulat încă.</div>
                    </div>
                    <div class="sc-box mb-4">
                        <h4 class="sc-box-title">Cum funcționează (pe scurt)</h4>
                        <ol class="text-sm space-y-2 m-0 pl-4 opacity-90">
                            <li><strong>Pas 1</strong> — descarcă pagina (HTML). La tine: <em>merge</em> ✓</li>
                            <li><strong>Pas 2</strong> — îi spui unde e fiecare produs în HTML (selectori CSS). La tine: <em>selector bloc gol</em> ✗</li>
                            <li><strong>Pas 3</strong> — opțional, intră în pagina fiecărui produs</li>
                        </ol>
                        <p class="text-sm mt-3 mb-0 opacity-70">Analogie: ai primit o revistă (HTML). Pas 2 = «fiecare articol începe la rubrica X». Fără rubrică, nu știe ce să citească.</p>
                    </div>
                    <div class="sc-box mb-4">
                        <h4 class="sc-box-title">Diagnostic HTML (selectori)</h4>
                        <div id="sc-test-diagnostics" class="text-sm opacity-80">Rulează test sau «Analizează ultimul HTML».</div>
                        <button type="button" id="sc-apply-preset-from-diag" class="sc-btn-primary sc-btn-sm mt-3 hidden">Aplică selectori Autodoc + Salvează</button>
                    </div>
                    <div class="sc-box mb-4">
                        <h4 class="sc-box-title">Produse găsite</h4>
                        <div id="sc-test-items" class="sc-items-grid"></div>
                    </div>
                    <div class="sc-box">
                        <h4 class="sc-box-title">Câmpuri necesare vs găsite</h4>
                        <div id="sc-test-fields" class="text-sm">—</div>
                        <pre id="sc-test-json" class="sc-pre mt-3">—</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Agent AI -->
        <div class="sc-tab-panel" data-sc-tab-panel="agent">
            <div class="sc-box mb-4">
                <h4 class="sc-box-title">Agent AI — spune ce vrei, nu selectori CSS</h4>
                <p class="text-sm opacity-70 mb-3">Descrie în română ce date ai nevoie. Agentul citește HTML-ul salvat, folosește busola DOM (clase repetate) și propune selectori sau extrage direct produsele.</p>
                <label class="sc-field full">
                    <span>Ce să caute / extragă</span>
                    <textarea id="sc-ai-goals" rows="4" class="box w-full rounded-md border px-3 py-2 text-sm" placeholder="Ex: Din pagina de căutare vreau fiecare filtru ulei: titlu, preț în lei, poză, link produs și cod articol."></textarea>
                </label>
                <div class="flex flex-wrap gap-4 mt-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" id="sc-ai-enabled" checked> Agent activ</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="sc-ai-auto-fail" checked> Auto când 0 produse (după fetch)</label>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="button" id="sc-ai-run" class="sc-btn-primary">Analizează HTML cu Agent AI</button>
                    <button type="button" id="sc-ai-apply" class="sc-btn-outline">Aplică selectori + Salvează</button>
                </div>
                <p class="text-xs opacity-60 mt-2">Prioritate: <code>CURSOR_API_KEY</code> → Cursor (<code>CURSOR_MODEL</code> din Setări). Fallback: recunoaștere heuristică sau <code>GROQ_KEY</code> / <code>OPENAI_KEY</code>.</p>
            </div>
            <div class="sc-box mb-4">
                <h4 class="sc-box-title">Detalii analiză</h4>
                <div id="sc-ai-fields" class="text-sm mb-3">—</div>
                <div id="sc-ai-result" class="text-sm opacity-80">Rulează agentul după ce ai HTML salvat (Pas 1).</div>
                <button type="button" id="sc-ai-json-toggle" class="sc-ai-json-toggle hidden">Arată JSON tehnic</button>
                <pre id="sc-ai-json" class="sc-pre mt-3 hidden">—</pre>
            </div>
            <div class="sc-box">
                <h4 class="sc-box-title">Produse extrase</h4>
                <p class="text-sm opacity-60 mb-3">Cartele cu imagine, titlu, preț și cod articol — actualizate după fiecare analiză.</p>
                <div id="sc-ai-items" class="sc-items-grid">
                    <p class="text-sm opacity-60 col-span-full">Rulează agentul — vei vedea cartele aici.</p>
                </div>
            </div>
        </div>

        <!-- Tab Output -->
        <div class="sc-tab-panel" data-sc-tab-panel="output">
            <div class="sc-box">
                <h4 class="sc-box-title">Ce ai nevoie din această sursă</h4>
                <p class="mb-3 text-sm opacity-70">Bifează câmpurile pe care vrei să le obții la import. Testul verifică dacă scraperul le găsește.</p>
                <div id="sc-output-fields" class="flex flex-wrap gap-3"></div>
            </div>
            <div class="sc-box mt-4">
                <h4 class="sc-box-title">Setări fetch (scrape.do)</h4>
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-6 md:col-span-3 sc-field">
                        <span>Timeout sec</span>
                        <input type="number" id="sc-fetch-timeout" min="15" max="180" value="90" class="box h-9 w-full rounded-md border px-2 text-sm">
                    </label>
                </div>
            </div>
        </div>

        <!-- Tab Integrare import (per sursă) -->
        <div class="sc-tab-panel" data-sc-tab-panel="integrare">
            <div class="sc-box">
                <h4 class="sc-box-title">Obiective extragere text / date</h4>
                <p class="text-sm opacity-70 mb-3">Spune ce cauți în blocurile HTML: coduri OEM, descriere, validare TecDoc la adăugare produs.</p>
                <div id="sc-extraction-goals" class="space-y-2 mb-3"></div>
                <button type="button" id="sc-add-goal" class="sc-btn-outline sc-btn-sm">+ Adaugă obiectiv</button>
            </div>
            <div class="sc-box mt-4">
                <h4 class="sc-box-title">Agent AI — validare imagini (această sursă)</h4>
                <label class="flex items-center gap-2 text-sm mb-3">
                    <input type="checkbox" id="sc-src-ai-enabled" class="rounded border">
                    <span>Activează reguli AI pentru imaginile de la această sursă</span>
                </label>
                <label class="sc-field">
                    <span>Prompt / reguli suplimentare (română)</span>
                    <textarea id="sc-src-ai-prompt" rows="4" class="box w-full rounded-md border px-3 py-2 text-sm"
                              placeholder="ex: Acceptă fundal alb dacă produsul e clar. Respinge logo-uri. Pentru ulei verifică ambalajul 1L/4L."></textarea>
                </label>
            </div>
            <div class="sc-box mt-4">
                <h4 class="sc-box-title">TecDoc RapidAPI</h4>
                <label class="flex items-center gap-2 text-sm mb-2">
                    <input type="checkbox" id="sc-rapidapi-validate" class="rounded border">
                    <span>Validează cod/OEM cu RapidAPI la import și completează ce lipsește</span>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="sc-use-in-pipeline" class="rounded border" checked>
                    <span>Include sursa în pipeline imagini global (Plan 1→2→3)</span>
                </label>
            </div>
            <div class="mt-4">
                <button type="button" id="sc-save-integration" class="sc-btn-primary">Salvează integrare</button>
            </div>
        </div>
    </div>

    <!-- VEDERE: Pipeline imagini global -->
    <div id="sc-view-pipeline" class="hidden">
        <div class="sc-header">
            <button type="button" id="sc-back-from-pipeline" class="sc-btn-outline sc-btn-sm">← Înapoi la surse</button>
            <div>
                <h2 class="sc-title">Pipeline imagini & import</h2>
                <p class="sc-subtitle">Plan principal → secundar → plan 3… Dacă planul 1 nu găsește imagine, trece automat la următorul. Folosit în <strong>cron</strong> și <strong>import review</strong>.</p>
            </div>
        </div>

        <div class="sc-box mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h4 class="sc-box-title m-0">Planuri fallback (ordine)</h4>
                <button type="button" id="sc-add-plan" class="sc-btn-outline sc-btn-sm">+ Adaugă plan</button>
            </div>
            <div id="sc-image-plans" class="space-y-2"></div>
        </div>

        <div class="sc-box mb-4">
            <h4 class="sc-box-title">Agent AI imagini (global)</h4>
            <label class="flex items-center gap-2 text-sm mb-3">
                <input type="checkbox" id="sc-global-ai-enabled" class="rounded border">
                <span>Activează agent AI la audit imagini (panou produse + import review)</span>
            </label>
            <div class="grid grid-cols-12 gap-3 mb-3">
                <label class="col-span-6 md:col-span-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" id="sc-ai-white-bg" class="rounded border"> Fundal alb OK
                </label>
                <label class="col-span-6 md:col-span-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" id="sc-ai-product-match" class="rounded border"> Potrivire produs
                </label>
                <label class="col-span-6 md:col-span-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" id="sc-ai-on-import" class="rounded border"> La import review
                </label>
                <label class="col-span-6 md:col-span-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" id="sc-ai-on-cron" class="rounded border"> La cron (lent)
                </label>
            </div>
            <label class="sc-field">
                <span>Prompt global AI (ce să atragă atenția)</span>
                <textarea id="sc-global-ai-prompt" rows="4" class="box w-full rounded-md border px-3 py-2 text-sm"
                          placeholder="ex: Pentru piese frână: disc sau etrier vizibil. Respinge imagini cu mașină întreagă."></textarea>
            </label>
            <label class="col-span-6 md:col-span-3 sc-field mt-2">
                <span>Scor minim păstrare (0–100)</span>
                <input type="number" id="sc-ai-min-score" min="0" max="100" value="70" class="box h-9 w-full max-w-[120px] rounded-md border px-2 text-sm">
            </label>
            <label class="flex items-center gap-2 text-sm mt-3">
                <input type="checkbox" id="sc-ai-auto-pipeline-retry" class="rounded border" checked>
                <span>După audit Cursor (mismatch) — caută automat imagine nouă via Pipeline Plan 1→2→3</span>
            </label>
        </div>

        <div class="sc-box mb-4">
            <h4 class="sc-box-title">Test pipeline pe produs</h4>
            <div class="flex flex-wrap gap-2 items-end">
                <label class="sc-field flex-1 min-w-[200px]">
                    <span>Nume produs / query</span>
                    <input type="text" id="sc-pipeline-test-query" class="box h-10 w-full rounded-md border px-3 text-sm" placeholder="ex: filtru ulei MANN W712/75">
                </label>
                <button type="button" id="sc-test-pipeline" class="sc-btn-primary">Testează Plan 1→2→3</button>
            </div>
            <div id="sc-pipeline-quota-warn" class="sc-pipeline-quota-warn hidden"></div>
            <div id="sc-pipeline-progress-wrap" class="sc-pipeline-progress-wrap hidden">
                <div class="sc-pipeline-status-row">
                    <div class="sc-pipeline-status" id="sc-pipeline-status">Pornesc…</div>
                    <div class="sc-pipeline-pct" id="sc-pipeline-pct">0%</div>
                </div>
                <div class="sc-pipeline-bar-track"><div class="sc-pipeline-bar-fill" id="sc-pipeline-bar-fill"></div></div>
                <div class="sc-pipeline-segments" id="sc-pipeline-segments"></div>
                <div class="sc-pipeline-elapsed text-xs opacity-60 mt-1" id="sc-pipeline-elapsed"></div>
            </div>
            <div id="sc-pipeline-steps" class="sc-pipeline-steps hidden"></div>
            <div id="sc-pipeline-summary" class="sc-pipeline-summary hidden"></div>
            <div id="sc-pipeline-hit" class="sc-pipeline-hit hidden"></div>
            <button type="button" id="sc-pipeline-json-toggle" class="sc-ai-json-toggle hidden mt-2">Arată JSON tehnic</button>
            <pre id="sc-pipeline-test-result" class="sc-pre mt-3 text-xs hidden">—</pre>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" id="sc-save-pipeline" class="sc-btn-primary">Salvează pipeline</button>
            <label class="flex items-center gap-2 text-sm opacity-80">
                <input type="checkbox" id="sc-sync-env" class="rounded border">
                Sincronizează și în <code>IMAGE_SEARCH_SOURCES</code> (.env)
            </label>
        </div>
    </div>

    <!-- VEDERE 3: Vitrină ePiesa (legacy) -->
    <div id="sc-view-vitrina" class="hidden">
        <div class="sc-header">
            <button type="button" id="sc-back-from-vitrina" class="sc-btn-outline sc-btn-sm">← Înapoi</button>
            <h2 class="sc-title">Vitrină ePiesa — homepage</h2>
        </div>
        <?php require __DIR__ . '/_scraper-epiesa-vitrina.php'; ?>
    </div>

    <!-- VEDERE 4: Loguri -->
    <div id="sc-view-logs" class="hidden">
        <div class="sc-header">
            <button type="button" id="sc-back-from-logs" class="sc-btn-outline sc-btn-sm">← Înapoi</button>
            <h2 class="sc-title">Loguri scraper</h2>
        </div>
        <div class="sc-box">
            <pre id="scraper-log" class="sc-pre sc-log-pre">—</pre>
        </div>
    </div>
</div>

<div id="sc-modal-add-goal" class="sc-modal hidden" role="dialog">
    <div class="sc-modal-backdrop" data-close-goal-modal></div>
    <div class="sc-modal-box sc-modal-box--sm">
        <h3 class="sc-title text-base mb-3">Adaugă obiectiv extragere</h3>
        <div id="sc-goal-type-list" class="sc-type-list mb-3"></div>
        <label class="sc-field">
            <span>Denumire</span>
            <input type="text" id="sc-goal-label" class="box h-10 w-full rounded-md border px-3 text-sm" placeholder="ex: Coduri OEM din tabel compatibilitate">
        </label>
        <label class="sc-field">
            <span>Selector CSS / XPath (bloc text)</span>
            <input type="text" id="sc-goal-selector" class="box h-10 w-full rounded-md border px-3 text-sm" placeholder=".compat-table sau //div[@class='oem']">
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" id="sc-goal-rapidapi" class="rounded border">
            Validează și cu RapidAPI TecDoc
        </label>
        <div class="flex gap-2 mt-4">
            <button type="button" id="sc-confirm-add-goal" class="sc-btn-primary">Adaugă</button>
            <button type="button" class="sc-btn-outline" data-close-goal-modal>Anulează</button>
        </div>
    </div>
</div>

<div id="sc-modal-add-step" class="sc-modal hidden" role="dialog">
    <div class="sc-modal-backdrop" data-close-step-modal></div>
    <div class="sc-modal-box sc-modal-box--sm">
        <h3 class="sc-title text-base mb-3">Alege tipul pasului</h3>
        <div id="sc-step-type-list" class="sc-type-list"></div>
        <button type="button" class="sc-btn-outline mt-3" data-close-step-modal>Anulează</button>
    </div>
</div>

<div id="sc-modal-add-element" class="sc-modal hidden" role="dialog" aria-modal="true" aria-labelledby="sc-modal-add-element-title">
    <div class="sc-modal-backdrop" data-close-el-modal></div>
    <div class="sc-modal-box sc-modal-box--sm">
        <h3 id="sc-modal-add-element-title" class="sc-title text-base mb-1">Adaugă element</h3>
        <p class="sc-subtitle mb-4">Alege tipul, denumește elementul, apoi apasă Adaugă.</p>

        <div class="mb-3">
            <div class="text-xs font-semibold uppercase opacity-50 mb-2">1. Tip element</div>
            <div id="sc-element-type-list" class="sc-type-list"></div>
        </div>

        <label class="sc-field">
            <span>2. Denumire element</span>
            <input type="text" id="sc-el-display-name" class="box h-10 w-full rounded-md border px-3 text-sm"
                   placeholder="ex: Titlu produs, Imagine principală, Stoc">
        </label>

        <label class="sc-field">
            <span>Selector CSS / XPath <span class="font-normal opacity-60">(poți completa și după)</span></span>
            <input type="text" id="sc-el-selector-input" class="box h-10 w-full rounded-md border px-3 text-sm"
                   placeholder="ex: .product-title sau //div[@class='card']">
        </label>

        <p id="sc-el-type-hint" class="text-xs opacity-60 mt-1"></p>

        <div class="flex flex-wrap gap-2 mt-4">
            <button type="button" id="sc-confirm-add-element" class="sc-btn-primary">Adaugă element</button>
            <button type="button" class="sc-btn-outline" data-close-el-modal>Anulează</button>
        </div>
    </div>
</div>

<div id="sc-modal-create" class="sc-modal hidden" role="dialog" aria-modal="true">
    <div class="sc-modal-backdrop" data-close-modal></div>
    <div class="sc-modal-box">
        <h3 class="sc-title text-lg mb-1">Sursă scraping nouă</h3>
        <p class="sc-subtitle mb-4">Creezi un card nou — apoi configurezi pașii 1, 2, 3 și testezi.</p>
        <form id="sc-create-form" class="space-y-3">
            <label class="sc-field">
                <span>Nume afișat *</span>
                <input type="text" name="label" required placeholder="ex: Autodoc, AltMagazin" class="box h-10 w-full rounded-md border px-3 text-sm">
            </label>
            <label class="sc-field">
                <span>ID (opțional, auto din nume)</span>
                <input type="text" name="id" pattern="[a-z0-9_-]+" placeholder="ex: autodoc_ro" class="box h-10 w-full rounded-md border px-3 text-sm">
            </label>
            <label class="sc-field">
                <span>Domeniu</span>
                <input type="text" name="domain" placeholder="ex: autodoc.ro" class="box h-10 w-full rounded-md border px-3 text-sm">
            </label>
            <label class="sc-field">
                <span>URL căutare (Pas 1) — folosește <code>{query}</code></span>
                <input type="url" name="url_template" placeholder="https://site.ro/search?q={query}" class="box h-10 w-full rounded-md border px-3 text-sm">
            </label>
            <label class="sc-field">
                <span>Descriere scurtă</span>
                <textarea name="description" rows="2" class="box w-full rounded-md border px-3 py-2 text-sm"></textarea>
            </label>
            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="sc-btn-primary">Creează și configurează</button>
                <button type="button" class="sc-btn-outline" data-close-modal>Anulează</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_scraper-styles.php'; ?>
<?php require __DIR__ . '/_scraper-step-builder.js.php'; ?>
<?php require __DIR__ . '/_scraper-app.js.php'; ?>
