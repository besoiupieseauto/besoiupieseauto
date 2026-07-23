<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Module\ModuleAssets;

require_once __DIR__ . '/_helpers.php';

$produseSectionActive = 'formare';
$produseNavVitrinaCount = $produseNavVitrinaCount ?? 0;
$produseNavScraperCount = $produseNavScraperCount ?? 0;
$pcfApiUrl = AdminUrl::api('product_card_formation_endpoint.php');
$pcfCsrf = \Besoiu\Core\Auth\AdminCsrf::token();
$pcfCssUrl = ModuleAssets::url('product_formation', 'css/product-formation.css');
$pcfJsUrl = ModuleAssets::url('product_formation', 'js/product-formation.js');
$produseListCssUrl = AdminUrl::publicAsset('css/admin-produse-list.css');
?>
<link rel="stylesheet" href="<?= produse_list_h(AdminUrl::fontAwesomeCssUrl()) ?>" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= produse_list_h($produseListCssUrl) ?>?v=20260718-pl-v12">
<link rel="stylesheet" href="<?= produse_list_h($pcfCssUrl) ?>?v=20260721-pcf-inputs1">

<div class="-mt-5 admin-content produse-list-page produse-section-page">
<div class="admin-panel">
    <div class="admin-panel__head">
        <div class="pl-head-main">
            <span class="pl-head-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
            <h2 class="mt-0 text-lg font-medium">Formare carte produs</h2>
        </div>
        <span class="pl-head-meta"><span class="pl-head-meta__dot" aria-hidden="true"></span><i class="fa-solid fa-wand-magic-sparkles"></i> Titlu + tab-uri site</span>
    </div>
    <?php include __DIR__ . '/_section-nav.php'; ?>

<div class="bpa-pcf-page" id="bpa-pcf-page">
    <header class="bpa-pcf-head">
        <div>
            <p class="bpa-pcf-head__desc">
                Definești dinamic cum se compune titlul produsului la import și cum apar
                <strong>tab-urile de descriere</strong> pe pagina produs din magazin
                (Descriere, Compatibilitate, Specificații, Recenzii).
            </p>
        </div>
        <div class="bpa-pcf-head__actions">
            <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost" id="bpa-pcf-reset-channel"><i class="fa-solid fa-rotate-left"></i> Reset canal</button>
            <button type="button" class="bpa-pcf-btn bpa-pcf-btn--primary" id="bpa-pcf-save"><i class="fa-solid fa-floppy-disk"></i> Salvează regulile</button>
        </div>
    </header>

    <section class="bpa-pcf-scope bpa-com-card bpa-com-card--pad" aria-label="Profil salvare">
        <div class="bpa-pcf-scope__row">
            <div class="bpa-pcf-scope__pick">
                <p class="bpa-pcf-sidebar__label">Salvezi structura pentru</p>
                <div class="bpa-pcf-scope__modes">
                    <label class="bpa-pcf-scope__mode">
                        <input type="radio" name="pcf_scope_mode" value="default" checked>
                        <span>Implicit (toate)</span>
                    </label>
                    <label class="bpa-pcf-scope__mode">
                        <input type="radio" name="pcf_scope_mode" value="category">
                        <span>Categorie</span>
                    </label>
                    <label class="bpa-pcf-scope__mode">
                        <input type="radio" name="pcf_scope_mode" value="project">
                        <span>Proiect / canal</span>
                    </label>
                </div>
            </div>
            <div class="bpa-pcf-scope__value bpa-pcf-is-hidden" id="bpa-pcf-scope-category-wrap">
                <label class="bpa-pcf-field">
                    <span>Categorie magazin</span>
                    <select id="bpa-pcf-scope-category" class="bpa-pcf-select"></select>
                </label>
            </div>
            <div class="bpa-pcf-scope__value bpa-pcf-is-hidden" id="bpa-pcf-scope-project-wrap">
                <label class="bpa-pcf-field">
                    <span>Proiect destinație</span>
                    <select id="bpa-pcf-scope-project" class="bpa-pcf-select"></select>
                </label>
            </div>
        </div>
        <p class="bpa-pcf-scope__badge" id="bpa-pcf-scope-badge">Profil activ: <strong>Implicit (toate produsele)</strong></p>
        <p class="bpa-pcf-hint" id="bpa-pcf-scope-hint">
            Regulile implicite se aplică tuturor produselor. Poți crea un profil separat per categorie (tab-uri + titlu)
            sau per proiect/canale de export (titlu la import).
        </p>
    </section>

    <div class="bpa-pcf-page-modes" role="tablist" aria-label="Secțiuni formare">
        <button type="button" class="bpa-pcf-page-mode is-active" data-page-mode="title">Titlu produs</button>
        <button type="button" class="bpa-pcf-page-mode" data-page-mode="tabs">Tab-uri descriere (site)</button>
    </div>

    <div id="bpa-pcf-mode-title">
    <div class="bpa-pcf-layout">
        <aside class="bpa-pcf-sidebar">
            <p class="bpa-pcf-sidebar__label">Canal destinație</p>
            <div class="bpa-pcf-channels" id="bpa-pcf-channels" role="tablist"></div>

            <div class="bpa-pcf-help bpa-com-card bpa-com-card--pad">
                <strong>Placeholdere template</strong>
                <ul id="bpa-pcf-placeholders" class="bpa-pcf-help__list"></ul>
            </div>
        </aside>

        <main class="bpa-pcf-main">
            <section class="bpa-com-card bpa-com-card--pad">
                <div class="bpa-pcf-mode">
                    <label class="bpa-pcf-mode__opt">
                        <input type="radio" name="pcf_mode" value="segments" checked> Segmente (bife + ordine)
                    </label>
                    <label class="bpa-pcf-mode__opt">
                        <input type="radio" name="pcf_mode" value="template"> Template liber (avansat)
                    </label>
                </div>

                <div id="bpa-pcf-segments-pane">
                    <p class="bpa-pcf-hint">Bifează ce apare în titlu și reordonează cu săgețile. Segmentele goale se omit automat.</p>
                    <ul id="bpa-pcf-segments" class="bpa-pcf-segments"></ul>
                </div>

                <div id="bpa-pcf-template-pane" class="bpa-pcf-is-hidden">
                    <label class="bpa-pcf-field">
                        <span>Șablon titlu</span>
                        <textarea id="bpa-pcf-template" rows="3" placeholder="{denumire} {brand_piesa} {cod} {pozitie}"></textarea>
                    </label>
                    <p class="bpa-pcf-hint">În modul template, orice placeholder din șablon este folosit — nu trebuie bifat și în lista „Segmente”. Exemplu: <code>{denumire} {categorie} {subcategorie}</code></p>
                </div>

                <div class="bpa-pcf-options">
                    <label class="bpa-pcf-field bpa-pcf-field--sm">
                        <span>Separator</span>
                        <input type="text" id="bpa-pcf-separator" value=" " maxlength="5">
                    </label>
                    <label class="bpa-pcf-field bpa-pcf-field--sm">
                        <span>Prefix vehicul</span>
                        <input type="text" id="bpa-pcf-vehicle-prefix" value="pentru">
                    </label>
                    <label class="bpa-pcf-field bpa-pcf-field--sm">
                        <span>Prefix compat</span>
                        <input type="text" id="bpa-pcf-compat-prefix" value="pentru">
                    </label>
                    <label class="bpa-pcf-field bpa-pcf-field--sm">
                        <span>Max caractere</span>
                        <input type="number" id="bpa-pcf-max-length" value="150" min="40" max="255">
                    </label>
                    <label class="bpa-pcf-check">
                        <input type="checkbox" id="bpa-pcf-uppercase"> MAJUSCULE
                    </label>
                </div>
            </section>

            <section class="bpa-com-card bpa-com-card--pad bpa-pcf-preview">
                <header class="bpa-pcf-preview__head">
                    <h2>Previzualizare live</h2>
                    <select id="bpa-pcf-sample" class="bpa-pcf-select">
                        <option value="default">Exemplu: Audi A3 + compat TecDoc</option>
                        <option value="fara_vehicul">Fără vehicul manual</option>
                        <option value="oem_only">Consumabil / filtru ulei</option>
                    </select>
                </header>
                <div class="bpa-pcf-preview__title" id="bpa-pcf-preview-title">—</div>
                <dl id="bpa-pcf-preview-segments" class="bpa-pcf-preview__segments"></dl>
            </section>
        </main>
    </div>
    </div>

    <div id="bpa-pcf-mode-tabs" class="bpa-pcf-is-hidden">
        <div class="bpa-pcf-layout bpa-pcf-layout--tabs">
            <aside class="bpa-pcf-sidebar">
                <p class="bpa-pcf-sidebar__label">Tab-uri pagină produs</p>
                <ul id="bpa-pcf-tabs-list" class="bpa-pcf-tabs-list"></ul>
                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--block" id="bpa-pcf-add-tab">+ Adaugă tab</button>
                <p class="bpa-pcf-hint">Bifează, redenumește, reordonează sau șterge tab-urile vizibile pe site.</p>
            </aside>

            <main class="bpa-pcf-main">
                <section class="bpa-com-card bpa-com-card--pad" id="bpa-pcf-tab-editor">
                    <p class="bpa-pcf-hint bpa-pcf-is-hidden" id="bpa-pcf-tab-empty">Selectează un tab din listă.</p>
                    <div id="bpa-pcf-tab-form" class="bpa-pcf-is-hidden">
                        <div class="bpa-pcf-tab-form__row">
                            <label class="bpa-pcf-field"><span>Nume tab (navigare)</span>
                                <input type="text" id="bpa-pcf-tab-label" maxlength="80">
                            </label>
                            <label class="bpa-pcf-check"><input type="checkbox" id="bpa-pcf-tab-enabled"> Activ pe site</label>
                            <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--danger" id="bpa-pcf-delete-tab">Șterge tab</button>
                        </div>
                        <div class="bpa-pcf-tab-form__row">
                            <label class="bpa-pcf-field"><span>Format conținut</span>
                                <select id="bpa-pcf-tab-format"></select>
                            </label>
                            <label class="bpa-pcf-field bpa-pcf-field--prose-words bpa-pcf-is-hidden"><span>Țintă cuvinte (text descriptiv)</span>
                                <input type="number" id="bpa-pcf-tab-prose-words" value="400" min="150" max="800" step="25">
                            </label>
                            <label class="bpa-pcf-field bpa-pcf-field--source"><span>Sursă date</span>
                                <select id="bpa-pcf-tab-source">
                                    <option value="field_rows">Câmpuri produs (configurabile)</option>
                                    <option value="tecdoc_note">HTML TecDoc complet (pNote)</option>
                                    <option value="compat_entries">Rânduri compatibilitate import</option>
                                </select>
                            </label>
                        </div>
                        <div id="bpa-pcf-tab-chart-pane" class="bpa-pcf-is-hidden">
                            <div class="bpa-pcf-tab-form__row">
                                <label class="bpa-pcf-field"><span>Sursă grafic</span>
                                    <select id="bpa-pcf-tab-chart-source"></select>
                                </label>
                                <label class="bpa-pcf-field"><span>Tip grafic</span>
                                    <select id="bpa-pcf-tab-chart-type"></select>
                                </label>
                                <label class="bpa-pcf-field bpa-pcf-field--chart-period"><span>Perioadă</span>
                                    <select id="bpa-pcf-tab-chart-period"></select>
                                </label>
                            </div>
                            <label class="bpa-pcf-field"><span>Titlu grafic (opțional)</span>
                                <input type="text" id="bpa-pcf-tab-chart-title" maxlength="120" placeholder="ex. Evoluție vânzări">
                            </label>
                            <p class="bpa-pcf-hint">Date afișate în grafic (bifează, redenumește, reordonează):</p>
                            <ul id="bpa-pcf-tab-chart-metrics" class="bpa-pcf-segments bpa-pcf-segments--chart"></ul>
                            <div class="bpa-pcf-row-add bpa-pcf-row-add--chart" id="bpa-pcf-chart-metrics-add">
                                <select id="bpa-pcf-add-chart-metric-id" class="bpa-pcf-select bpa-pcf-select--sm"></select>
                                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--sm" id="bpa-pcf-add-chart-metric-btn">+ Adaugă dată</button>
                                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--sm" id="bpa-pcf-add-chart-custom-btn">+ Dată manuală</button>
                                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--sm" id="bpa-pcf-add-all-chart-metrics-btn">+ Adaugă toate</button>
                            </div>
                            <p class="bpa-pcf-hint bpa-pcf-hint--sm">Poți adăuga metrici preț, câmpuri produs sau valori manuale (număr). Listă extinsă depinde de sursa grafic.</p>
                        </div>
                        <label class="bpa-pcf-field bpa-pcf-field--footnote bpa-pcf-is-hidden"><span>Notă sub tabel compatibilitate</span>
                            <input type="text" id="bpa-pcf-tab-footnote" maxlength="240">
                        </label>
                        <div id="bpa-pcf-tab-fields-pane">
                            <p class="bpa-pcf-hint bpa-pcf-is-hidden" id="bpa-pcf-prose-fields-hint">Carduri cu icon de sus + text descriptiv (~400 cuvinte) dedesubt. Bifează câmpurile dorite.</p>
                            <p class="bpa-pcf-hint" id="bpa-pcf-fields-hint">Câmpuri afișate (descriere dt/dd sau rânduri tabel):</p>
                            <ul id="bpa-pcf-tab-fields" class="bpa-pcf-segments"></ul>
                            <div class="bpa-pcf-row-add" id="bpa-pcf-fields-add">
                                <select id="bpa-pcf-add-field-id" class="bpa-pcf-select bpa-pcf-select--sm"></select>
                                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--sm" id="bpa-pcf-add-field-btn">+ Adaugă câmp</button>
                            </div>
                        </div>
                        <div id="bpa-pcf-tab-columns-pane" class="bpa-pcf-is-hidden">
                            <p class="bpa-pcf-hint">Coloane tabel compatibilitate:</p>
                            <ul id="bpa-pcf-tab-columns" class="bpa-pcf-segments"></ul>
                            <div class="bpa-pcf-row-add" id="bpa-pcf-columns-add">
                                <select id="bpa-pcf-add-column-id" class="bpa-pcf-select bpa-pcf-select--sm"></select>
                                <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost bpa-pcf-btn--sm" id="bpa-pcf-add-column-btn">+ Adaugă coloană</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bpa-com-card bpa-com-card--pad bpa-pcf-preview bpa-pcf-preview--tabs">
                    <header class="bpa-pcf-preview__head">
                        <h2>Previzualizare tab-uri site</h2>
                        <select id="bpa-pcf-tabs-sample" class="bpa-pcf-select">
                            <option value="default">Exemplu: Audi A3 + compat TecDoc</option>
                            <option value="fara_vehicul">Fără vehicul manual</option>
                            <option value="oem_only">Consumabil / filtru ulei</option>
                        </select>
                    </header>
                    <div class="bpa-pcf-tabs-preview" id="bpa-pcf-tabs-preview"></div>
                </section>
            </main>
        </div>
        <div class="bpa-pcf-tabs-actions">
            <button type="button" class="bpa-pcf-btn bpa-pcf-btn--ghost" id="bpa-pcf-reset-tabs">Reset tab-uri</button>
            <button type="button" class="bpa-pcf-btn bpa-pcf-btn--primary" id="bpa-pcf-save-tabs">Salvează tab-uri</button>
        </div>
    </div>

    <div id="bpa-pcf-toast" class="bpa-pcf-toast hidden" role="status"></div>
</div>

</div>
</div>

<script type="application/json" id="bpa-pcf-cfg"><?= json_encode(['api' => $pcfApiUrl, 'csrf' => $pcfCsrf], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
<script defer src="<?= produse_list_h($pcfJsUrl) ?>?v=20260721-pcf-save1"></script>
