<?php
declare(strict_types=1);
?>
<div class="settings-page settings-page--fullbleed" id="settings-hub-app">
    <div id="settings-toast" class="st-toast hidden" role="status" aria-live="polite"></div>

    <header class="st-hero">
        <div class="st-hero__main">
            <div class="st-hero__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </div>
            <div>
                <h1 class="st-hero__title">Setări sistem</h1>
                <p class="st-hero__sub">Echipă admin, permisiuni pe module și monitorizare buget API — totul într-un singur loc.</p>
            </div>
        </div>
        <div class="st-hero__actions">
            <button type="button" id="settings-refresh" class="st-btn st-btn--ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                Reîncarcă
            </button>
        </div>
    </header>

    <nav class="st-tabs" role="tablist" aria-label="Secțiuni setări">
        <button type="button" class="st-tab is-active" data-tab="users" role="tab" aria-selected="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Utilizatori
        </button>
        <button type="button" class="st-tab" data-tab="usage" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Consum live
        </button>
        <button type="button" class="st-tab" data-tab="tokens" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Tokeni API
        </button>
        <button type="button" class="st-tab" data-tab="ollama" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a4 4 0 0 0-4 4v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2h-2V6a4 4 0 0 0-4-4z"/></svg>
            Ollama Control
        </button>
        <button type="button" class="st-tab" data-tab="keys" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            Chei secrete
        </button>
        <button type="button" class="st-tab" data-tab="modules" role="tab" aria-selected="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Module
        </button>
    </nav>

    <div id="settings-alerts" class="st-alerts"></div>

    <!-- TAB Utilizatori -->
    <section class="st-panel is-active" data-panel="users" role="tabpanel">
        <div id="settings-users-denied" class="st-empty hidden">
            <div class="st-empty__icon">🔒</div>
            <p>Nu ai permisiunea de a gestiona utilizatori.<br>Contactează un super ambassador.</p>
        </div>

        <div id="settings-users-wrap" class="st-users-layout">
            <div class="st-card st-card--table st-card--users-full">
                <div class="st-card__head">
                    <div>
                        <h2 class="st-card__title">Echipă admin</h2>
                        <p class="st-card__desc" id="settings-users-count">Se încarcă…</p>
                    </div>
                    <button type="button" id="settings-user-add" class="st-btn st-btn--primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Utilizator nou
                    </button>
                </div>
                <div class="st-table-wrap">
                    <table class="st-table st-table--users">
                        <thead>
                        <tr>
                            <th>Utilizator</th>
                            <th>Rol</th>
                            <th>Module</th>
                            <th>Chat</th>
                            <th class="st-tc">Status</th>
                            <th class="st-tr">Acțiuni</th>
                        </tr>
                        </thead>
                        <tbody id="settings-users-tbody">
                        <tr><td colspan="6" class="st-table-empty">Se încarcă…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div id="settings-user-modal" class="st-user-modal hidden" role="dialog" aria-modal="true" aria-labelledby="settings-user-form-title">
        <div class="st-user-modal__backdrop" data-settings-modal-close></div>
        <div class="st-user-modal__dialog">
            <header class="st-user-modal__header">
                <div>
                    <h2 class="st-user-modal__title" id="settings-user-form-title">Utilizator nou</h2>
                    <p class="st-user-modal__meta">
                        <span class="st-badge st-badge--soft" id="settings-form-mode">Creare</span>
                    </p>
                </div>
                <button type="button" class="st-user-modal__close" data-settings-modal-close aria-label="Închide">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </header>

            <nav class="st-user-modal__tabs" role="tablist" aria-label="Secțiuni profil utilizator">
                <button type="button" class="st-user-modal__tab is-active" data-editor-tab="identity" role="tab" aria-selected="true">Identitate</button>
                <button type="button" class="st-user-modal__tab" data-editor-tab="access" role="tab" aria-selected="false">Acces delegat</button>
                <button type="button" class="st-user-modal__tab" data-editor-tab="chat" role="tab" aria-selected="false">Chat intern</button>
            </nav>

            <form id="settings-user-form" class="st-user-modal__form">
                <input type="hidden" name="id" id="settings-user-id" value="0">

                <div class="st-user-modal__scroll">
                    <div class="st-user-editor__pane is-active" data-editor-pane="identity" role="tabpanel">
                        <p class="st-user-editor__pane-lead">Date de autentificare și rol preset.</p>
                        <div class="st-form st-form--identity">
                            <label class="st-field">
                                <span class="st-label">Nume complet</span>
                                <input type="text" name="fullname" required class="st-input" placeholder="ex. Maria Popescu" autocomplete="name">
                            </label>
                            <label class="st-field">
                                <span class="st-label">Login / email</span>
                                <input type="text" name="login" required class="st-input" placeholder="ex. maria@besoiu.ro" autocomplete="username">
                            </label>
                            <label class="st-field">
                                <span class="st-label">Parolă <span class="st-label-hint" id="settings-pw-hint">obligatorie</span></span>
                                <input type="password" name="password" class="st-input" autocomplete="new-password" placeholder="Min. 8 caractere">
                            </label>
                            <label class="st-field">
                                <span class="st-label">Profil rapid</span>
                                <select name="role" id="settings-user-role" class="st-input st-select">
                                    <option value="operator">Operator</option>
                                    <option value="manager">Manager</option>
                                    <option value="executive">Executive</option>
                                    <option value="super_ambassador">Super ambassador</option>
                                    <option value="custom">Personalizat</option>
                                </select>
                            </label>
                            <label class="st-field">
                                <span class="st-label">Status</span>
                                <select name="status" class="st-input st-select">
                                    <option value="1">Activ</option>
                                    <option value="0">Dezactivat</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="st-user-editor__pane" data-editor-pane="access" role="tabpanel" hidden>
                        <div class="st-user-editor__section-head">
                            <div>
                                <h3 class="st-user-editor__section-title">Acces delegat</h3>
                                <p class="st-user-editor__section-desc">Secțiune admin → funcții individuale</p>
                            </div>
                            <button type="button" id="settings-perm-all" class="st-btn st-btn--ghost st-btn--sm">Selectează tot</button>
                        </div>
                        <div class="st-perms-deleg st-perms-deleg--modal">
                            <nav class="st-perms-deleg__nav" id="settings-perm-nav" aria-label="Secțiuni acces"></nav>
                            <div class="st-perms-deleg__panel" id="settings-perm-panel">
                                <p class="st-perms-deleg__empty">Selectează o secțiune.</p>
                            </div>
                        </div>
                    </div>

                    <div class="st-user-editor__pane" data-editor-pane="chat" role="tabpanel" hidden>
                        <div class="st-user-editor__section-head">
                            <div>
                                <h3 class="st-user-editor__section-title">Chat intern (Composer)</h3>
                                <p class="st-user-editor__section-desc">Ce poate modifica din conversație — nu schimbă accesul la pagini admin</p>
                            </div>
                            <button type="button" id="settings-chat-perm-all" class="st-btn st-btn--ghost st-btn--sm">Selectează tot</button>
                        </div>
                        <div class="st-perms-deleg st-perms-deleg--chat st-perms-deleg--modal" id="settings-chat-perms"></div>
                    </div>
                </div>

                <footer class="st-user-modal__footer">
                    <button type="button" id="settings-user-cancel" class="st-btn st-btn--ghost">Anulează</button>
                    <button type="submit" class="st-btn st-btn--primary">Salvează utilizatorul</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- TAB Consum live -->
    <section class="st-panel" data-panel="usage" role="tabpanel" hidden>
        <p class="st-lead">
            Monitorizare <strong>în timp real</strong> a apelurilor către resurse externe — Scrape.do, RapidAPI TecDoc, Cursor și tokeni AI.
            Se actualizează automat la fiecare 5 secunde cât timp ești pe acest tab.
        </p>
        <div id="settings-usage-live" class="st-usage-live">
            <div class="st-usage-live__loading">Se încarcă consumul live…</div>
        </div>
    </section>

    <!-- TAB Tokeni -->
    <section class="st-panel" data-panel="tokens" role="tabpanel" hidden>
        <p class="st-lead">
            Monitorizezi <strong>API cloud</strong> (Scrape.do, RapidAPI, Cursor) și <strong>Metro LLM local</strong> (Ollama).
            Vezi ce consumă <strong>automat</strong> vs <strong>manual</strong>, oprește tot dintr-un click.
        </p>

        <div id="settings-automation-panel" class="st-automation-panel"></div>

        <div id="settings-metro-llm-panel" class="st-metro-panel" aria-labelledby="settings-metro-title"></div>

        <h2 class="st-section-title">Consum lunar pe provider</h2>
        <div id="settings-token-cards" class="st-token-grid"></div>

        <div class="st-card st-card--budget">
            <div class="st-card__head">
                <div>
                    <h2 class="st-card__title">Configurare buget</h2>
                    <p class="st-card__desc">Cotă tokeni, cost per query și prag alertă</p>
                </div>
            </div>
            <form id="settings-budget-form" class="st-budget-form">
                <div class="st-budget-status" id="settings-budget-status" role="status" aria-live="polite">
                    <div class="st-budget-status__info">
                        <span class="st-budget-status__eyebrow">Situație lună curentă</span>
                        <div class="st-budget-status__row">
                            <input type="number" name="remaining_override" id="settings-budget-remaining-input" class="st-budget-status__value st-budget-status__value--input" min="0" step="1" inputmode="numeric" aria-label="Tokeni rămași">
                            <span class="st-budget-status__meta" id="settings-budget-remaining-meta">tokeni rămași</span>
                            <button type="button" id="settings-budget-remaining-auto" class="st-budget-status__auto-btn" title="Calculează automat din consum">Auto</button>
                        </div>
                        <p class="st-budget-status__detail" id="settings-budget-remaining-detail">Selectează un provider.</p>
                    </div>
                    <div class="st-budget-status__meter">
                        <div class="st-budget-status__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                            <div class="st-budget-status__bar-fill" id="settings-budget-remaining-bar" style="width:100%"></div>
                        </div>
                        <span class="st-budget-status__pct" id="settings-budget-remaining-pct">100%</span>
                    </div>
                </div>

                <div id="settings-cursor-billing" class="st-cursor-billing hidden" aria-live="polite"></div>

                <div class="st-budget-grid">
                    <label class="st-field st-field--span2">
                        <span class="st-label">Provider</span>
                        <select name="provider_key" id="settings-budget-provider" class="st-input st-select"></select>
                    </label>
                    <label class="st-field">
                        <span class="st-label">Cotă lunară (tokeni)</span>
                        <input type="number" name="monthly_quota" min="1" class="st-input" required placeholder="Scrape.do: 1000 · RapidAPI: 100">
                        <span class="st-hint">Scrape.do: 1.000 credite/lună. RapidAPI TecDoc: 100 cereri/lună.</span>
                    </label>
                    <label class="st-field">
                        <span class="st-label">Tokeni / query</span>
                        <input type="number" name="tokens_per_request" min="1" class="st-input" required placeholder="Scrape.do: 20 · RapidAPI: 1">
                        <span class="st-hint">Scrape.do: 20 credite/cerere (și la eșec). RapidAPI: 1/căutare. La token nou revin valorile de bază.</span>
                    </label>
                    <label class="st-field">
                        <span class="st-label">Alertă la % consum</span>
                        <input type="number" name="warning_pct" min="50" max="99" value="80" class="st-input" required>
                        <span class="st-hint">Notificare când se apropie de epuizare</span>
                    </label>
                    <label class="st-field st-field--optional">
                        <span class="st-label">Cost / request <span class="st-label-hint">opțional</span></span>
                        <input type="number" name="cost_per_unit" min="0" step="0.0001" class="st-input" placeholder="lăsați gol dacă nu folosiți">
                        <span class="st-hint">RON — doar pentru estimare cost</span>
                    </label>
                    <label class="st-field st-field--check st-field--switch-row">
                        <span class="st-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="st-switch__track"></span>
                        </span>
                        <span class="st-label st-label--inline">API live activ (apeluri permise)</span>
                    </label>
                </div>

                <div class="st-budget-form__footer">
                    <button type="submit" class="st-btn st-btn--primary">Salvează buget</button>
                </div>
            </form>
        </div>
    </section>

    <!-- TAB Ollama Control -->
    <section class="st-panel" data-panel="ollama" role="tabpanel" hidden>
        <p class="st-lead">
            Panou de verificare <strong>Ollama</strong> înainte de orice reparare: status pe modul, diff configurare,
            hartă integrări și test manual per apel.
        </p>
        <div class="st-ollama-toolbar">
            <button type="button" id="settings-ollama-refresh" class="st-btn st-btn--ghost st-btn--sm">↻ Reîmprospătează</button>
            <span id="settings-ollama-generated" class="st-ollama-meta"></span>
        </div>
        <div id="settings-ollama-control" class="st-ollama-control">
            <div class="st-ollama-loading">Se încarcă panoul Ollama…</div>
        </div>
    </section>

    <!-- TAB Chei -->
    <section class="st-panel" data-panel="keys" role="tabpanel" hidden>
        <p class="st-lead st-lead--keys">
            Fiecare cheie are consum <strong>automat</strong> (cron, site, import fără click) și <strong>manual</strong> (acțiuni tale în admin).
            Oprește automatul din tab <a href="?tab=tokens">Tokeni API</a>.
            <br><span class="st-lead__sub">Metro LLM: <strong>Ollama</strong> (local, tab Consum live) → <strong>Groq</strong> → <strong>Gemini</strong> → <strong>OpenRouter</strong> → <strong>Cursor</strong> (escaladare).</span>
        </p>
        <div class="st-card">
            <div class="st-card__head">
                <div>
                    <h2 class="st-card__title">Chei API secrete</h2>
                    <p class="st-card__desc">Salvate în <code class="st-code">admin/.env</code> — scraping, TecDoc, Metro LLM (Groq, Gemini, OpenRouter, Cursor)</p>
                </div>
            </div>
            <form id="settings-env-form" class="st-env-grid"></form>
            <div id="settings-env-tests" class="st-env-tests hidden" aria-live="polite"></div>
            <div class="st-form__actions st-form__actions--inline st-form__actions--env">
                <button type="button" id="settings-env-test-all" class="st-btn st-btn--ghost">
                    ▶ Testează toate modulele (+ Ollama)
                </button>
                <button type="button" id="settings-env-save" class="st-btn st-btn--primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Salvează chei și modele
                </button>
            </div>
        </div>
    </section>

    <!-- TAB Module (plug / unplug) -->
    <section class="st-panel" data-panel="modules" role="tabpanel" hidden>
        <p class="st-lead">
            Modulele <strong>opționale</strong> sunt pachete MVP (clase + pagini + assets) care se conectează la CORE.
            Le poți opri, exporta ca ZIP-șablon, șterge sau instala din ZIP.
            Modulele <strong>CORE</strong> nu se scot — fără ele magazinul nu funcționează.
        </p>

        <div class="st-card st-card--modules-install mb-4">
            <div class="st-card__head">
                <div>
                    <h2 class="st-card__title">Adaugă modul extern</h2>
                    <p class="st-card__desc">Încarcă un pachet <code>.zip</code> (max 20 MB) cu arhitectura Besoiu Module.</p>
                </div>
            </div>
            <form id="settings-module-install-form" class="st-module-install" enctype="multipart/form-data">
                <label class="st-field st-module-install__file">
                    <span class="st-label">Fișier ZIP</span>
                    <input type="file" name="module_zip" id="settings-module-zip" accept=".zip,application/zip" required class="st-input">
                </label>
                <label class="st-field st-field--check st-field--switch-row">
                    <span class="st-switch">
                        <input type="checkbox" name="overwrite" id="settings-module-overwrite" value="1">
                        <span class="st-switch__track"></span>
                    </span>
                    <span class="st-label st-label--inline">Suprascrie dacă există deja</span>
                </label>
                <div class="st-module-install__actions">
                    <button type="submit" id="settings-module-install-btn" class="st-btn st-btn--primary">
                        Instalează modul
                    </button>
                    <button type="button" id="settings-module-export-template" class="st-btn st-btn--ghost st-btn--sm">
                        Descarcă șablon MVP
                    </button>
                    <a class="st-btn st-btn--ghost st-btn--sm" href="/admin/modules/PACKAGING.md" target="_blank" rel="noopener">Spec pachet →</a>
                </div>
            </form>
        </div>

        <div class="st-card st-card--modules">
            <div class="st-card__head">
                <div>
                    <h2 class="st-card__title">Module opționale</h2>
                    <p class="st-card__desc" id="settings-modules-optional-count">Se încarcă…</p>
                </div>
                <button type="button" id="settings-modules-refresh" class="st-btn st-btn--ghost st-btn--sm">↻ Reîncarcă</button>
            </div>
            <div id="settings-modules-optional" class="st-modules-grid">
                <p class="st-table-empty">Se încarcă…</p>
            </div>
        </div>

        <div class="st-card st-card--modules-core mt-4">
            <div class="st-card__head">
                <div>
                    <h2 class="st-card__title">Module CORE (sistem)</h2>
                    <p class="st-card__desc">Nu se dezactivează din UI — sunt „OS-ul” Adminului.</p>
                </div>
            </div>
            <div id="settings-modules-core" class="st-modules-core-list">
                <p class="st-table-empty">Se încarcă…</p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/_settings-styles.php'; ?>
<?php require __DIR__ . '/_settings-app.js.php'; ?>
