<?php declare(strict_types=1); ?>
<div class="ai-hub-agent-block" id="ollama-control-root"
     data-agent-api="<?= htmlspecialchars($agentApiUrl ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-settings-api="<?= htmlspecialchars($settingsApiUrl ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div id="ollama-toast" class="ollama-toast hidden" role="status"></div>

    <div class="ollama-mode-guide ai-hub-mode-guide">
        <div class="ollama-mode-guide__item"><strong>Bibliotecă RAG</strong><span>Fragmente învățate — răspuns rapid</span></div>
        <div class="ollama-mode-guide__item"><strong>MySQL live</strong><span>Stoc, comenzi, import</span></div>
        <div class="ollama-mode-guide__item"><strong>Ollama</strong><span>Reformulare umană când lipsește potrivirea</span></div>
    </div>

    <div id="ollama-agent-status" class="ollama-inline-status" role="status">Se încarcă agenții…</div>

    <div class="ollama-chat-layout">
        <aside class="ollama-agents-list" id="ollama-agent-cards" aria-label="Agenți"></aside>
        <div class="ollama-chat-panel">
            <header class="ollama-chat-panel__head">
                <h3 id="ollama-chat-title">Selectează un agent</h3>
                <span id="ollama-chat-model" class="ollama-meta">—</span>
            </header>
            <div id="ollama-messages" class="ollama-messages">
                <p class="ollama-empty">Ex: «Ce știi despre spray ZOLLEX T-522Z?»</p>
            </div>
            <div id="ollama-quick" class="ollama-quick hidden"></div>
            <div class="ollama-compose">
                <div class="ollama-compose__toggles">
                    <label class="ollama-compose__mode"><input type="checkbox" id="ollama-use-knowledge" checked> Bibliotecă RAG</label>
                    <label class="ollama-compose__mode"><input type="checkbox" id="ollama-use-live-db" checked> MySQL live</label>
                </div>
                <textarea id="ollama-input" rows="3" placeholder="Întrebare pentru agent…"></textarea>
                <div class="ollama-compose__actions">
                    <button type="button" id="ollama-send" class="ollama-btn ollama-btn--live" disabled>Trimite</button>
                    <button type="button" id="ollama-send-smoke" class="ollama-btn ollama-btn--ghost ollama-btn--sm" disabled>Smoke</button>
                </div>
            </div>
        </div>
    </div>
</div>
