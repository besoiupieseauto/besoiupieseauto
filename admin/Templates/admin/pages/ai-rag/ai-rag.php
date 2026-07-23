<?php declare(strict_types=1);

use Besoiu\Core\AdminUrl;

$ragApiUrl = AdminUrl::api('ai_rag_endpoint.php');
$intelApiUrl = AdminUrl::api('ai_intelligence_endpoint.php');
$agentApiUrl = AdminUrl::api('ai_agent_endpoint.php');
$settingsApiUrl = AdminUrl::api('settings_endpoint.php');
$settingsMetroUrl = AdminUrl::path('settings') . '?tab=metro';
$importProUrl = AdminUrl::path('import-pro');
$partials = __DIR__ . '/_partials';
?>
<div class="col-span-12 ai-hub-page" id="ai-hub-root"
     data-rag-api="<?= htmlspecialchars($ragApiUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-intel-api="<?= htmlspecialchars($intelApiUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-agent-api="<?= htmlspecialchars($agentApiUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-settings-api="<?= htmlspecialchars($settingsApiUrl, ENT_QUOTES, 'UTF-8') ?>">

    <div id="ai-hub-toast" class="ai-hub-toast hidden" role="status"></div>

    <header class="ai-hub-header">
        <div>
            <h1 class="ai-hub-header__title">🧠 Centru AI Besoiu</h1>
            <p class="ai-hub-header__desc">
                <strong>Chat</strong> cu agenți · <strong>Bibliotecă</strong> RAG · <strong>Intelligence</strong> (tracking, search) · <strong>Sistem</strong> (audit).
                AI propune — tu validezi.
            </p>
        </div>
        <div class="ai-hub-header__actions">
            <button type="button" id="ai-hub-refresh" class="ai-hub-btn ai-hub-btn--ghost">↻ Reîmprospătează</button>
            <a href="<?= htmlspecialchars($settingsMetroUrl, ENT_QUOTES, 'UTF-8') ?>" class="ai-hub-btn ai-hub-btn--outline">Setări LLM</a>
            <a href="<?= htmlspecialchars($importProUrl, ENT_QUOTES, 'UTF-8') ?>" class="ai-hub-btn ai-hub-btn--outline">Import Pro</a>
        </div>
    </header>

    <div class="ai-hub-stats ai-hub-stats--bar" aria-label="Status rapid">
        <article class="ai-hub-stat ai-hub-stat--compact"><span class="ai-hub-stat__label">Ollama</span><div id="ai-hub-stat-ollama" class="ai-hub-stat__val">…</div></article>
        <article class="ai-hub-stat ai-hub-stat--compact"><span class="ai-hub-stat__label">Agenți</span><div id="ai-hub-stat-agents" class="ai-hub-stat__val">—</div></article>
        <article class="ai-hub-stat ai-hub-stat--compact"><span class="ai-hub-stat__label">Bibliotecă</span><div id="ai-hub-stat-agent-lib" class="ai-hub-stat__val">—</div></article>
        <article class="ai-hub-stat ai-hub-stat--compact"><span class="ai-hub-stat__label">Corpus</span><div id="ai-hub-stat-corpus" class="ai-hub-stat__val">—</div></article>
        <article class="ai-hub-stat ai-hub-stat--compact ai-hub-stat--warn"><span class="ai-hub-stat__label">Alerte</span><div id="ai-hub-stat-alerts" class="ai-hub-stat__val">0</div></article>
    </div>

    <nav class="ai-hub-nav" role="tablist" aria-label="Secțiuni Centru AI">
        <button type="button" class="ai-hub-nav__btn is-active" data-hub-tab="chat">💬 Chat</button>
        <button type="button" class="ai-hub-nav__btn" data-hub-tab="library">📚 Bibliotecă</button>
        <button type="button" class="ai-hub-nav__btn" data-hub-tab="intelligence">📊 Intelligence</button>
        <button type="button" class="ai-hub-nav__btn" data-hub-tab="system">⚙️ Sistem</button>
    </nav>

    <!-- CHAT -->
    <section class="ai-hub-panel is-active" id="ai-hub-panel-chat" data-hub-panel="chat">
        <?php require $partials . '/agent-chat.php'; ?>
    </section>

    <!-- BIBLIOTECĂ — explorează · adaugă · instrumente (fără duplicate) -->
    <section class="ai-hub-panel" id="ai-hub-panel-library" data-hub-panel="library" hidden>
        <nav class="ai-hub-subnav" role="tablist" aria-label="Bibliotecă">
            <button type="button" class="ai-hub-subnav__btn is-active" data-lib-tab="explore">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Explorează &amp; întreabă
            </button>
            <button type="button" class="ai-hub-subnav__btn" data-lib-tab="add">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Adaugă cunoștințe
            </button>
            <button type="button" class="ai-hub-subnav__btn" data-lib-tab="tools">
                <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Instrumente
            </button>
        </nav>

        <div class="ai-hub-subpanel is-active ai-hub-lib-explore" data-lib-panel="explore">
            <div class="ai-hub-source-toggle" role="group" aria-label="Sursă bibliotecă">
                <button type="button" class="ai-hub-source-btn is-active" data-lib-source="agent">
                    <i class="fa-solid fa-robot" aria-hidden="true"></i> Per agent (chat)
                </button>
                <button type="button" class="ai-hub-source-btn" data-lib-source="global">
                    <i class="fa-solid fa-globe" aria-hidden="true"></i> Corpus global
                </button>
            </div>

            <div class="ai-hub-lib-main">
                <div id="ai-hub-lib-agent-view">
                    <?php require $partials . '/agent-library.php'; ?>
                </div>

                <div id="ai-hub-lib-global-view" class="hidden ai-lib-global">
                    <div class="ai-hub-stats ai-hub-stats--sm ai-lib-global-stats">
                        <article class="ai-hub-stat"><span class="ai-hub-stat__label">Total</span><div id="ai-rag-stat-total" class="ai-hub-stat__val">—</div></article>
                        <article class="ai-hub-stat"><span class="ai-hub-stat__label">Intern</span><div id="ai-rag-stat-intern" class="ai-hub-stat__val">—</div></article>
                        <article class="ai-hub-stat"><span class="ai-hub-stat__label">Extern</span><div id="ai-rag-stat-extern" class="ai-hub-stat__val">—</div></article>
                        <article class="ai-hub-stat"><span class="ai-hub-stat__label">SEO</span><div id="ai-rag-stat-seo" class="ai-hub-stat__val">—</div></article>
                    </div>
                    <div class="ai-hub-filters ai-lib-global-filters">
                        <label class="ai-lib-search ai-lib-search--inline">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="ai-rag-lib-search" placeholder="Caută în corpus…">
                        </label>
                        <select id="ai-rag-lib-origin" class="ai-hub-select">
                            <option value="">Intern + extern</option>
                            <option value="intern">Doar intern</option>
                            <option value="extern">Doar extern</option>
                        </select>
                        <select id="ai-rag-lib-type" class="ai-hub-select">
                            <option value="">Orice tip</option>
                            <option value="epiesa">ePiesa</option>
                            <option value="scrape">Scrape</option>
                            <option value="produse">Produse BD</option>
                            <option value="supplier">Furnizori</option>
                        </select>
                        <button type="button" id="ai-rag-lib-browse" class="ai-hub-btn ai-hub-btn--primary">
                            <i class="fa-solid fa-list" aria-hidden="true"></i> Afișează
                        </button>
                    </div>
                    <div id="ai-rag-lib-count" class="ai-lib-summary ai-lib-summary--compact">—</div>
                    <div id="ai-rag-lib-list" class="ai-rag-lib-list ai-lib-corpus-list"></div>
                    <button type="button" id="ai-rag-lib-more" class="ai-hub-btn ai-hub-btn--ghost hidden">Mai multe…</button>
                </div>
            </div>

            <article class="ai-hub-card ai-hub-card--ask">
                <h2><i class="fa-solid fa-comments" aria-hidden="true"></i> Întreabă biblioteca</h2>
                <p class="ai-hub-hint">Răspuns din corpus + biblioteca agent — același motor ca la Chat cu «Bibliotecă RAG».</p>
                <div class="ai-hub-search-row">
                    <label class="ai-lib-search ai-lib-search--grow">
                        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                        <input type="text" id="ai-rag-market-question" placeholder="Ex: Ce știi despre ZOLLEX T-522Z?" />
                    </label>
                    <button type="button" id="ai-rag-market-ask" class="ai-hub-btn ai-hub-btn--primary">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Întreabă
                    </button>
                </div>
                <div class="ai-hub-chips">
                    <button type="button" class="ai-hub-chip" data-q="ZOLLEX T-522Z">ZOLLEX</button>
                    <button type="button" class="ai-hub-chip" data-q="SEO ulei 5W30">SEO ulei</button>
                    <button type="button" class="ai-hub-chip" data-q="Ce furnizori interni am">Furnizori</button>
                </div>
                <div id="ai-rag-market-answer" class="ai-hub-answer hidden"></div>
                <div id="ai-rag-market-sources-used" class="ai-hub-sources"></div>
            </article>
        </div>

        <div class="ai-hub-subpanel" data-lib-panel="add" hidden>
            <?php require $partials . '/agent-learn.php'; ?>
        </div>

        <div class="ai-hub-subpanel" data-lib-panel="tools" hidden>
            <article class="ai-hub-card">
                <h2>Corpus global</h2>
                <div id="ai-rag-corpus-status" class="ai-hub-meta">—</div>
                <div id="ai-rag-vector-detail" class="ai-hub-meta ai-hub-meta--sm hidden"></div>
                <div class="ai-hub-actions">
                    <button type="button" id="ai-rag-seed-corpus" class="ai-hub-btn ai-hub-btn--primary">Generează 500 din sistem</button>
                    <button type="button" id="ai-rag-index-embeddings" class="ai-hub-btn ai-hub-btn--ghost">Index embeddings</button>
                </div>
                <pre id="ai-rag-seed-output" class="ai-hub-pre hidden"></pre>
            </article>

            <article class="ai-hub-card ai-hub-card--sites">
                <div class="ai-hub-card__head">
                    <div>
                        <h2>10 site-uri — scrape automat</h2>
                        <p class="ai-hub-hint">Misiuni batch → corpus. Pentru un singur URL folosește «Adaugă cunoștințe → Scrape».</p>
                    </div>
                    <span class="ai-hub-pill" id="ai-rag-sites-active-count">—</span>
                </div>
                <div class="ai-hub-sites-wrap">
                    <table class="ai-hub-sites-table ai-rag-sites-table" id="ai-rag-sites-table">
                        <thead>
                            <tr>
                                <th class="col-slot">#</th>
                                <th class="col-name">Nume</th>
                                <th class="col-url">URL</th>
                                <th class="col-category">Categorie</th>
                                <th class="col-mission">Misiune</th>
                                <th class="col-tip">Tip</th>
                                <th class="col-active">Activ</th>
                            </tr>
                        </thead>
                        <tbody id="ai-rag-sites-tbody"></tbody>
                    </table>
                </div>
                <div class="ai-hub-actions ai-hub-actions--sites">
                    <button type="button" id="ai-rag-sites-save" class="ai-hub-btn ai-hub-btn--ghost">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvează
                    </button>
                    <button type="button" id="ai-rag-sites-run" class="ai-hub-btn ai-hub-btn--primary">
                        <i class="fa-solid fa-play" aria-hidden="true"></i> Rulează active
                    </button>
                </div>

                <div id="ai-rag-sites-run-panel" class="ai-sites-run-panel hidden" aria-live="polite">
                    <div class="ai-sites-run-panel__head">
                        <div class="ai-sites-run-panel__title">
                            <span class="ai-sites-run-panel__dot" id="ai-rag-sites-run-dot" aria-hidden="true"></span>
                            <span id="ai-rag-sites-run-status">Pregătire job…</span>
                        </div>
                        <div class="ai-sites-run-panel__stats" id="ai-rag-sites-run-stats">—</div>
                    </div>
                    <div class="ai-sites-run-bar" role="progressbar"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
                         id="ai-rag-sites-run-bar-wrap">
                        <div class="ai-sites-run-bar__fill" id="ai-rag-sites-run-bar" style="width:0%"></div>
                    </div>
                    <div class="ai-sites-run-panel__current" id="ai-rag-sites-run-current">—</div>
                    <ol class="ai-sites-run-log" id="ai-rag-sites-run-log"></ol>
                </div>
                <div id="ai-rag-sites-progress" class="ai-hub-meta ai-hub-meta--progress">—</div>
            </article>

            <article class="ai-hub-card ai-hub-card--import-dict">
                <h2>Dicționar Import Pro</h2>
                <p class="ai-hub-hint">Alege produse din magazin — vezi imaginile din platformă, apoi generează structură / SEO pentru produsul selectat.</p>

                <div class="ai-import-toolbar">
                    <input type="search" id="ai-rag-import-filter" class="ai-hub-input ai-import-toolbar__search" placeholder="Filtrează listă (opțional)…" autocomplete="off">
                    <select id="ai-rag-import-image-filter" class="ai-hub-select ai-import-toolbar__select">
                        <option value="all">Toate produsele</option>
                        <option value="with">Cu imagine</option>
                        <option value="without">Fără imagine</option>
                    </select>
                    <button type="button" id="ai-rag-import-load" class="ai-hub-btn ai-hub-btn--primary">
                        <i class="fa-solid fa-th" aria-hidden="true"></i> Afișează produse
                    </button>
                </div>

                <div id="ai-rag-import-meta" class="ai-hub-meta ai-import-meta">Apasă «Afișează produse» pentru a încărca catalogul.</div>

                <div id="ai-rag-import-grid" class="ai-import-grid" hidden></div>

                <div class="ai-import-pagination hidden" id="ai-rag-import-pagination">
                    <button type="button" id="ai-rag-import-prev" class="ai-hub-btn ai-hub-btn--ghost">‹ Anterior</button>
                    <span id="ai-rag-import-page-label" class="ai-import-pagination__label">—</span>
                    <button type="button" id="ai-rag-import-next" class="ai-hub-btn ai-hub-btn--ghost">Următor ›</button>
                </div>

                <div id="ai-rag-import-selected" class="ai-import-selected hidden">
                    <div class="ai-import-selected__media">
                        <img id="ai-rag-import-selected-img" src="" alt="" loading="lazy">
                        <span id="ai-rag-import-selected-noimg" class="ai-import-selected__noimg hidden">Fără imagine</span>
                    </div>
                    <div class="ai-import-selected__body">
                        <h3 id="ai-rag-import-selected-name">—</h3>
                        <div id="ai-rag-import-selected-meta" class="ai-import-selected__meta">—</div>
                        <div class="ai-hub-actions ai-hub-actions--tight">
                            <button type="button" id="ai-rag-import-lookup" class="ai-hub-btn ai-hub-btn--primary">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Sugerează structură / SEO
                            </button>
                            <button type="button" id="ai-rag-import-clear" class="ai-hub-btn ai-hub-btn--ghost">Deselectează</button>
                        </div>
                    </div>
                </div>

                <pre id="ai-rag-import-output" class="ai-hub-pre hidden"></pre>
            </article>

            <div id="ai-import-suggest-modal" class="ai-import-modal hidden" role="dialog" aria-modal="true" aria-labelledby="ai-import-modal-title" hidden>
                <div class="ai-import-modal__backdrop" data-import-modal-close></div>
                <div class="ai-import-modal__dialog">
                    <header class="ai-import-modal__header">
                        <div>
                            <h2 id="ai-import-modal-title">Sugestie structură / SEO</h2>
                            <p id="ai-import-modal-subtitle" class="ai-import-modal__subtitle">—</p>
                        </div>
                        <button type="button" class="ai-import-modal__close" data-import-modal-close aria-label="Închide">×</button>
                    </header>

                    <div id="ai-import-modal-product" class="ai-import-modal__product"></div>

                    <div id="ai-import-modal-progress-wrap" class="ai-import-modal__progress-wrap">
                        <div class="ai-import-modal__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="ai-import-modal-bar-wrap">
                            <div class="ai-import-modal__bar-fill" id="ai-import-modal-bar" style="width:0%"></div>
                        </div>
                        <p id="ai-import-modal-step" class="ai-import-modal__step">Pornire…</p>
                        <ol id="ai-import-modal-steps" class="ai-import-modal__steps"></ol>
                    </div>

                    <div id="ai-import-modal-result" class="ai-import-modal__result hidden"></div>

                    <footer class="ai-import-modal__footer">
                        <button type="button" id="ai-import-modal-copy" class="ai-hub-btn ai-hub-btn--ghost hidden">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Copiază sugestia
                        </button>
                        <button type="button" class="ai-hub-btn ai-hub-btn--primary" data-import-modal-close>Închide</button>
                    </footer>
                </div>
            </div>
        </div>
    </section>

    <!-- INTELLIGENCE -->
    <section class="ai-hub-panel" id="ai-hub-panel-intelligence" data-hub-panel="intelligence" hidden>
        <?php require $partials . '/intelligence-dashboard.php'; ?>
    </section>

    <!-- SISTEM -->
    <section class="ai-hub-panel" id="ai-hub-panel-system" data-hub-panel="system" hidden>
        <link rel="stylesheet" href="<?= htmlspecialchars(AdminUrl::publicAsset('css/admin-aiwork.css'), ENT_QUOTES, 'UTF-8') ?>?v=20260719-aiwork6">
        <article class="ai-hub-card ai-sys-ollama-bar">
            <div class="ai-sys-ollama-bar__inner">
                <div class="ai-sys-ollama-bar__status">
                    <i class="fa-solid fa-server" aria-hidden="true"></i>
                    <div>
                        <strong>Ollama</strong>
                        <div id="ollama-status-pills" class="ollama-pills"><span class="ollama-pill ollama-pill--loading">Se verifică…</span></div>
                    </div>
                </div>
                <div id="ollama-connection-detail" class="ai-hub-meta ai-sys-ollama-bar__detail">—</div>
                <div class="ai-hub-actions ai-sys-ollama-bar__actions">
                    <button type="button" id="ollama-ping-btn" class="ai-hub-btn ai-hub-btn--ghost"><i class="fa-solid fa-plug"></i> Test</button>
                    <button type="button" id="ollama-daily-stats-btn" class="ai-hub-btn ai-hub-btn--ghost"><i class="fa-solid fa-chart-simple"></i> Raport</button>
                </div>
            </div>
            <pre id="ollama-ping-result" class="ai-hub-pre hidden"></pre>
        </article>
        <?php require $partials . '/agent-system.php'; ?>
    </section>

    <div id="aiwork-feedback-modal" class="aiwork-modal hidden" role="dialog" aria-modal="true" aria-labelledby="aiwork-feedback-title">
        <div class="aiwork-modal__backdrop" data-aiwork-close></div>
        <div class="aiwork-modal__panel">
            <header class="aiwork-modal__head">
                <h3 id="aiwork-feedback-title"><i class="fa-solid fa-thumbs-down" aria-hidden="true"></i> Nu e corect</h3>
                <button type="button" class="aiwork-modal__close" data-aiwork-close aria-label="Închide">×</button>
            </header>
            <p class="aiwork-modal__context" id="aiwork-feedback-context">—</p>
            <label class="aiwork-modal__label" for="aiwork-feedback-note">Spune ce nu e bine (ca să învețe AI-ul)</label>
            <textarea id="aiwork-feedback-note" class="aiwork-modal__textarea" rows="4" placeholder="Ex: răspuns greșit, produs inexistent, timeout inutil…"></textarea>
            <div class="aiwork-modal__actions">
                <button type="button" class="ai-hub-btn ai-hub-btn--ghost" data-aiwork-close>Anulează</button>
                <button type="button" id="aiwork-feedback-submit" class="ai-hub-btn ai-hub-btn--primary">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Trimite feedback
                </button>
            </div>
        </div>
    </div>

    <div id="ai-library-ask-modal" class="ai-import-modal hidden" role="dialog" aria-modal="true" aria-labelledby="ai-library-ask-title" hidden>
        <div class="ai-import-modal__backdrop" data-library-ask-close></div>
        <div class="ai-import-modal__dialog">
            <header class="ai-import-modal__header">
                <div>
                    <h2 id="ai-library-ask-title"><i class="fa-solid fa-comments" aria-hidden="true"></i> Întreabă biblioteca</h2>
                    <p id="ai-library-ask-subtitle" class="ai-import-modal__subtitle">—</p>
                </div>
                <button type="button" class="ai-import-modal__close" data-library-ask-close aria-label="Închide">×</button>
            </header>

            <div id="ai-library-ask-query" class="ai-library-ask-query"></div>

            <div id="ai-library-ask-progress-wrap" class="ai-import-modal__progress-wrap">
                <div class="ai-import-modal__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="ai-library-ask-bar-wrap">
                    <div class="ai-import-modal__bar-fill" id="ai-library-ask-bar" style="width:0%"></div>
                </div>
                <p id="ai-library-ask-step" class="ai-import-modal__step">Pornire…</p>
                <ol id="ai-library-ask-steps" class="ai-import-modal__steps"></ol>
            </div>

            <div id="ai-library-ask-result" class="ai-import-modal__result hidden"></div>

            <footer class="ai-import-modal__footer">
                <button type="button" id="ai-library-ask-copy" class="ai-hub-btn ai-hub-btn--ghost hidden">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i> Copiază răspuns
                </button>
                <button type="button" class="ai-hub-btn ai-hub-btn--primary" data-library-ask-close>Închide</button>
            </footer>
        </div>
    </div>
</div>

<script type="application/json" id="ai-hub-cfg"><?= json_encode([
    'ragApi' => $ragApiUrl,
    'agentApi' => $agentApiUrl,
    'settingsApi' => $settingsApiUrl,
    'categoriiApi' => AdminUrl::api('categorii_endpoint.php'),
], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="ollama-control-cfg"><?= json_encode([
    'agentApi' => $agentApiUrl,
    'settingsApi' => $settingsApiUrl,
], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= htmlspecialchars(AdminUrl::publicAsset('js/admin-ai-hub.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260719-hub15"></script>
<script src="<?= htmlspecialchars(AdminUrl::publicAsset('js/admin-ai-intelligence.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260719-intel6"></script>
<script src="<?= htmlspecialchars(AdminUrl::publicAsset('js/admin-ai-rag.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260719-rag24"></script>
<script src="<?= htmlspecialchars(AdminUrl::publicAsset('js/admin-ollama-control.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260719-ollama21"></script>
