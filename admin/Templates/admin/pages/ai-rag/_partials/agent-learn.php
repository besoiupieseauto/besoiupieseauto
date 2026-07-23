<?php declare(strict_types=1); ?>
<div class="ai-hub-learn">
    <p class="ai-hub-hint">Adaugi fragmente pentru agentul selectat în <strong>Chat</strong>. Scrape-ul folosește același parser ca instrumentele batch.</p>
    <span class="ollama-meta" id="ollama-train-agent-label">Agent: agent-produse</span>

    <div class="ollama-train-tabs" role="tablist">
        <button type="button" class="ollama-train-tab is-active" data-train-tab="manual">Text manual</button>
        <button type="button" class="ollama-train-tab" data-train-tab="scrape">Scrape URL</button>
    </div>

    <div class="ollama-train-panel" id="ollama-train-manual" data-train-panel="manual">
        <textarea id="ollama-train-text" rows="4" placeholder="Ex: Filtru Mann HU816X — compatibil BMW N47"></textarea>
        <label class="ollama-check"><input type="checkbox" id="ollama-train-pin"> Fixează (prioritar la căutare)</label>
        <div class="ollama-status-row">
            <button type="button" id="ollama-train-add" class="ollama-btn ollama-btn--primary ollama-btn--sm">Adaugă fragment</button>
            <button type="button" id="ollama-train-migrate" class="ollama-btn ollama-btn--ghost ollama-btn--sm">Migrează learned</button>
        </div>
    </div>

    <div class="ollama-train-panel hidden" id="ollama-train-scrape" data-train-panel="scrape">
        <input type="url" id="ollama-scrape-url" class="ollama-input" placeholder="https://www.epiesa.ro/… pagină produs">
        <input type="text" id="ollama-scrape-topic" class="ollama-input" placeholder="Temă opțională">
        <select id="ollama-scrape-source" class="ollama-input">
            <option value="">Sursă — detectare automată</option>
            <option value="epiesa">ePiesa</option>
            <option value="emag">eMAG</option>
            <option value="pieseauto">PieseAuto</option>
        </select>
        <label class="ollama-check"><input type="checkbox" id="ollama-scrape-intelligent" checked> Parser produs (SKU, preț, URL)</label>
        <label class="ollama-check"><input type="checkbox" id="ollama-scrape-follow" checked> Urmează linkuri (max 3)</label>
        <label class="ollama-check"><input type="checkbox" id="ollama-scrape-pin"> Fixează fragmentele</label>
        <div class="ollama-status-row">
            <button type="button" id="ollama-scrape-btn" class="ollama-btn ollama-btn--primary ollama-btn--sm">Scrape &amp; învață</button>
        </div>
        <pre id="ollama-scrape-result" class="ollama-pre hidden"></pre>
    </div>
</div>
