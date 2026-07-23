<?php declare(strict_types=1); ?>
<div class="ai-hub-library-agent ai-lib-pro">
    <header class="ai-lib-head">
        <span class="ai-lib-head__icon" aria-hidden="true"><i class="fa-solid fa-book-bookmark"></i></span>
        <div class="ai-lib-head__text">
            <h3 class="ai-lib-head__title">Biblioteca per agent</h3>
            <p class="ai-lib-head__sub">Fragmente RAG din conversații și scrape — deschide secțiunile (▶) pentru detalii.</p>
        </div>
    </header>

    <div class="ai-lib-toolbar">
        <label class="ai-lib-search" for="ollama-library-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="ollama-library-search" placeholder="Caută: ZOLLEX, Mann, ulei…" autocomplete="off">
        </label>
        <div class="ai-lib-toolbar__actions">
            <button type="button" id="ollama-library-search-btn" class="ai-lib-btn ai-lib-btn--primary" title="Caută">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Caută</span>
            </button>
            <button type="button" id="ollama-library-refresh-btn" class="ai-lib-btn ai-lib-btn--ghost" title="Reîmprospătează">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
            </button>
            <button type="button" id="ollama-library-expand-all" class="ai-lib-btn ai-lib-btn--ghost" title="Deschide tot">
                <i class="fa-solid fa-angles-down" aria-hidden="true"></i><span class="ai-lib-btn__label">Deschide</span>
            </button>
            <button type="button" id="ollama-library-collapse-all" class="ai-lib-btn ai-lib-btn--ghost" title="Închide tot">
                <i class="fa-solid fa-angles-up" aria-hidden="true"></i><span class="ai-lib-btn__label">Închide</span>
            </button>
        </div>
    </div>

    <div id="ollama-library-summary" class="ai-lib-summary">Se încarcă…</div>
    <div id="ollama-library-sections" class="ollama-library-sections ai-lib-sections"></div>
</div>
