<?php
require __DIR__ . '/../_partials/fz-surface-styles.php';

use Evasystem\Core\Marketplace\MarketplaceModel;
use Evasystem\Services\Marketplace\BaseLinkerProductMapper;

$marketplaceModel = new MarketplaceModel();
$blConnection = null;
foreach ($marketplaceModel->findAll() as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (strtolower(trim((string) ($row['platform'] ?? ''))) === 'baselinker') {
        $blConnection = $row;
        break;
    }
}

$connectionId = (int) ($blConnection['randomn_id'] ?? 0);
$defaultMapping = BaseLinkerProductMapper::defaultMapping();
$storedMapping = $defaultMapping;
if ($blConnection !== null) {
    $rawMapping = $blConnection['field_mapping'] ?? null;
    if (is_string($rawMapping) && trim($rawMapping) !== '') {
        $decoded = json_decode($rawMapping, true);
        if (is_array($decoded)) {
            $storedMapping = BaseLinkerProductMapper::resolveMapping($decoded);
        }
    }
}

$sourceFields = BaseLinkerProductMapper::allowedSourceFields();
$blInventoryId = (int) ($blConnection['bl_inventory_id'] ?? 0);
$lastTest = trim((string) ($blConnection['last_test_status'] ?? ''));
$lastTestMsg = trim((string) ($blConnection['last_test_message'] ?? ''));
$productsSynced = (int) ($blConnection['products_synced'] ?? 0);

$blLimits = \Evasystem\Services\Marketplace\BaseLinkerImportLimits::catalog();
$blActiveProducts = 0;
$blCatalogStrategy = ['recommended' => 'api_direct', 'estimated_api_batches' => 0, 'batch_size' => 50];
$blFeedInfo = null;
$blStoreImportInfo = null;
try {
    $pdoStats = \Config\Database::getDB();
    $stmtStats = $pdoStats->prepare('SELECT COUNT(*) FROM produse WHERE status <> :inactive');
    $stmtStats->execute([':inactive' => '0']);
    $blActiveProducts = (int) $stmtStats->fetchColumn();
    $blCatalogStrategy = \Evasystem\Services\Marketplace\BaseLinkerImportLimits::recommendStrategy($blActiveProducts);

    if ($connectionId > 0) {
        $feedLib = dirname(__DIR__, 5) . '/system/baselinker-feed.php';
        if (is_file($feedLib)) {
            require_once $feedLib;
            $blFeedInfo = baselinker_feed_info($pdoStats);
        }
    }

    $shopLib = dirname(__DIR__, 5) . '/system/baselinker-shop-integration.php';
    if (is_file($shopLib)) {
        require_once $shopLib;
        $shopInfo = baselinker_shop_info($pdoStats);
        $blStoreImportInfo = [
            'shop' => $shopInfo,
            'investigation' => \Evasystem\Services\Marketplace\BaseLinkerStoreImportInvestigation::report($blActiveProducts),
            'support_ticket' => \Evasystem\Services\Marketplace\BaseLinkerStoreImportInvestigation::buildSupportTicket(
                $shopInfo,
                $blActiveProducts
            ),
        ];
    }
} catch (Throwable $exception) {
    $blActiveProducts = 0;
}
?>

<style>
  /* BaseLinker catalog — izolare click/display față de shell admin (CSS retry tm_106) */
  body.besoiu-admin-2026 .furnizori-page.bl-panel {
    position: relative;
    z-index: 1;
    pointer-events: auto;
    isolation: isolate;
    max-width: 960px;
  }
  body.besoiu-admin-2026 .furnizori-page.bl-panel .rubick::before,
  body.besoiu-admin-2026 .furnizori-page.bl-panel .rubick::after {
    pointer-events: none !important;
  }
  .furnizori-page.bl-panel .bl-card {
    display: block !important;
    position: relative;
    z-index: 2;
    pointer-events: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    padding: 16px;
    margin-bottom: 16px;
  }
  .furnizori-page.bl-panel .bl-card h3 { margin: 0 0 8px; font-size: 1rem; font-weight: 800; color: #0f172a; }
  .furnizori-page.bl-panel .bl-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .furnizori-page.bl-panel .bl-field label { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: 4px; }
  .furnizori-page.bl-panel .bl-field input,
  .furnizori-page.bl-panel .bl-field select,
  .furnizori-page.bl-panel .bl-mapping-table select {
    display: block !important;
    width: 100%;
    pointer-events: auto !important;
    position: relative;
    z-index: 3;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: .88rem;
    background: #fff;
    color: #0f172a;
  }
  .furnizori-page.bl-panel .bl-actions {
    display: flex !important;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    position: relative;
    z-index: 4;
    pointer-events: auto;
  }
  .furnizori-page.bl-panel .bl-btn {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    pointer-events: auto !important;
    position: relative;
    z-index: 5;
    cursor: pointer;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .84rem;
    font-weight: 700;
    background: #fff;
    color: #334155;
  }
  body.besoiu-admin-2026 .furnizori-page.bl-panel .bl-btn-primary {
    background: linear-gradient(180deg, #10b981 0%, #059669 55%, #047857 100%) !important;
    border: 2px solid #047857 !important;
    color: #fff !important;
    box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35) !important;
  }
  body.besoiu-admin-2026 .furnizori-page.bl-panel .bl-btn-primary:hover {
    background: linear-gradient(180deg, #34d399 0%, #10b981 55%, #059669 100%) !important;
    color: #fff !important;
  }
  .furnizori-page.bl-panel .bl-btn-danger { color: #b91c1c; border-color: #fecaca; }
  .furnizori-page.bl-panel .bl-status { display: block; font-size: .82rem; padding: 8px 10px; border-radius: 8px; margin-top: 8px; pointer-events: auto; }
  .furnizori-page.bl-panel .bl-status.ok { background: #ecfdf5; color: #166534; border: 1px solid #86efac; }
  .furnizori-page.bl-panel .bl-status.bad { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
  .furnizori-page.bl-panel .bl-mapping-table { width: 100%; border-collapse: collapse; font-size: .84rem; pointer-events: auto; }
  .furnizori-page.bl-panel .bl-mapping-table th,
  .furnizori-page.bl-panel .bl-mapping-table td { border-bottom: 1px solid #e2e8f0; padding: 8px; text-align: left; }
  body.besoiu-admin-2026 #blToast.bl-toast {
    position: fixed;
    right: 16px;
    top: 16px;
    z-index: 100001;
    pointer-events: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
    display: none !important;
    visibility: hidden;
  }
  body.besoiu-admin-2026 #blToast.bl-toast.show {
    display: block !important;
    visibility: visible !important;
  }
  .furnizori-page.bl-panel #blSyncResult.is-visible {
    display: block !important;
    margin-top: 12px;
    font-size: .78rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px;
    white-space: pre-wrap;
    pointer-events: auto;
  }
  .furnizori-page.bl-panel .bl-limits {
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 16px;
    font-size: .84rem;
    color: #134e4a;
    pointer-events: auto;
  }
  .furnizori-page.bl-panel .bl-limits strong { color: #0f766e; }
  .furnizori-page.bl-panel .bl-limits ul { margin: 8px 0 0 18px; padding: 0; }
  .furnizori-page.bl-panel .bl-stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 0; }
  .furnizori-page.bl-panel .bl-stat {
    background: #fff;
    border: 1px solid #99f6e4;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: .78rem;
  }
  .furnizori-page.bl-panel .bl-stat b { display: block; font-size: 1rem; color: #0f172a; }
  .furnizori-page.bl-panel .bl-investigation {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 16px;
    font-size: .84rem;
    color: #78350f;
    pointer-events: auto;
  }
  .furnizori-page.bl-panel .bl-investigation strong { color: #92400e; }
  .furnizori-page.bl-panel .bl-investigation ul { margin: 8px 0 0 18px; padding: 0; }
  .furnizori-page.bl-panel .bl-ticket {
    width: 100%;
    min-height: 220px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px;
    font-size: .78rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    background: #f8fafc;
    color: #0f172a;
    resize: vertical;
    pointer-events: auto;
  }
  @media (max-width: 768px) { .furnizori-page.bl-panel .bl-grid { grid-template-columns: 1fr; } }
</style>

<div class="furnizori-page bl-panel">
  <div class="fz-header">
    <div>
      <a href="/admin/marketplace" class="fz-btn-outline" style="margin-bottom:12px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Înapoi la marketplace
      </a>
      <h2 class="fz-title">BaseLinker — Catalog produse</h2>
      <p class="fz-subtitle">Token API, test conexiune, mapare câmpuri și trimitere catalog în BaseLinker (API direct — fără limită 30MB fișier).</p>
    </div>
  </div>

  <div class="bl-limits" id="blLimitsPanel">
    <strong>Limită import BaseLinker:</strong>
    <?= (int) ($blLimits['csv_max_mb'] ?? 5) ?> MB CSV /
    <?= (int) ($blLimits['xml_max_mb'] ?? 30) ?> MB XML per fișier,
    max <?= (int) ($blLimits['daily_max_mb'] ?? 100) ?> MB/zi.
    Pentru ~100k piese auto, importul fișier depășește limita.
    <br><strong>Soluție Besoiu:</strong> sincronizare API direct (batch-uri mici în coadă) — evită upload CSV/XML.
    <ul>
      <li><strong>API direct</strong> (activ) — recomandat pentru catalog mare</li>
      <li><strong>Feed XML/JSON permanent</strong> (tm_108, activ) — URL fix sub 30MB/fragment, actualizat la publicare din coadă import</li>
      <li><strong>Import din magazin</strong> (tm_110, investigat) — Shops API, sincronizare continuă fără upload fișier</li>
    </ul>
    <?php if ($connectionId > 0): ?>
    <div class="bl-stats" id="blCatalogStats">
      <div class="bl-stat"><b id="blStatProducts"><?= number_format($blActiveProducts, 0, ',', '.') ?></b>produse active</div>
      <div class="bl-stat"><b id="blStatBatches"><?= (int) ($blCatalogStrategy['estimated_api_batches'] ?? 0) ?></b>batch-uri API estimate</div>
      <div class="bl-stat"><b><?= (int) ($blCatalogStrategy['batch_size'] ?? 50) ?></b>produse / batch</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($blStoreImportInfo !== null): ?>
    <?php
      $shopUrls = is_array($blStoreImportInfo['shop']['urls'] ?? null) ? $blStoreImportInfo['shop']['urls'] : [];
      $shopBlPass = trim((string) ($blStoreImportInfo['shop']['bl_pass'] ?? ''));
      $investigation = is_array($blStoreImportInfo['investigation'] ?? null) ? $blStoreImportInfo['investigation'] : [];
      $findings = is_array($investigation['findings'] ?? null) ? $investigation['findings'] : [];
      $supportTicket = trim((string) ($blStoreImportInfo['support_ticket'] ?? ''));
    ?>
    <div class="bl-card" id="blStoreImportCard">
      <h3>Import din magazin — investigare tm_110</h3>
      <p style="font-size:.84rem;color:#64748b;margin:0 0 12px;">
        Conectare magazin Besoiu ca sursă continuă în BaseLinker (Shops API) — ocolire limită 30MB fișier.
        <?= htmlspecialchars((string) ($investigation['conclusion'] ?? ''), ENT_QUOTES) ?>
      </p>
      <div class="bl-investigation">
        <strong>Concluzii investigare:</strong>
        <ul>
          <?php foreach ($findings as $finding): ?>
            <li><?= htmlspecialchars((string) $finding, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="bl-grid">
        <div class="bl-field">
          <label for="blShopIntegrationUrl">URL fișier integrare (Shops API)</label>
          <input id="blShopIntegrationUrl" type="text" readonly value="<?= htmlspecialchars((string) ($shopUrls['integration_file'] ?? ''), ENT_QUOTES) ?>">
        </div>
        <div class="bl-field">
          <label for="blShopBlPass">Parolă comunicare (bl_pass)</label>
          <input id="blShopBlPass" type="text" readonly value="<?= htmlspecialchars($shopBlPass, ENT_QUOTES) ?>">
        </div>
      </div>
      <p style="font-size:.78rem;color:#64748b;margin:10px 0 0;">
        Panou BaseLinker: <code><?= htmlspecialchars((string) ($investigation['baselinker_panel_path'] ?? ''), ENT_QUOTES) ?></code>
        · Documentație: <a href="https://developers.baselinker.com/shops_api/" target="_blank" rel="noopener">Shops API</a>
      </p>
      <div class="bl-actions">
        <button type="button" class="bl-btn" id="blCopyShopUrlBtn">Copiază URL integrare</button>
        <button type="button" class="bl-btn" id="blCopyBlPassBtn">Copiază bl_pass</button>
        <button type="button" class="bl-btn bl-btn-primary" id="blCopyTicketBtn">Copiază tichet suport</button>
      </div>
      <label for="blSupportTicket" style="display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:14px 0 6px;">Tichet suport BaseLinker (EN)</label>
      <textarea id="blSupportTicket" class="bl-ticket" readonly><?= htmlspecialchars($supportTicket, ENT_QUOTES) ?></textarea>
    </div>
  <?php endif; ?>

  <div id="blToast" class="bl-toast" role="status"></div>

  <?php if ($connectionId <= 0): ?>
    <div class="bl-card">
      <h3>Conexiune nouă BaseLinker</h3>
      <p style="font-size:.84rem;color:#64748b;margin:0 0 12px;">Nu există încă o conexiune BaseLinker. Creează una cu token-ul din panoul BaseLinker → Integrări → API.</p>
      <div class="bl-grid">
        <div class="bl-field">
          <label for="blNewName">Nume conexiune</label>
          <input id="blNewName" type="text" value="BaseLinker Besoiu">
        </div>
        <div class="bl-field">
          <label for="blNewToken">Token API</label>
          <input id="blNewToken" type="password" placeholder="Token din BaseLinker">
        </div>
      </div>
      <div class="bl-actions">
        <button type="button" class="bl-btn bl-btn-primary" id="blCreateBtn">Salvează conexiunea</button>
      </div>
    </div>
  <?php else: ?>
    <div class="bl-card">
      <h3>Conexiune API</h3>
      <div class="bl-grid">
        <div class="bl-field">
          <label for="blToken">Token API BaseLinker</label>
          <input id="blToken" type="password" placeholder="Lasă gol pentru a păstra tokenul existent">
        </div>
        <div class="bl-field">
          <label for="blInventory">Inventar (magazie)</label>
          <select id="blInventory">
            <option value="">— selectează după test —</option>
            <?php if ($blInventoryId > 0): ?>
              <option value="<?= (int) $blInventoryId ?>" selected>Inventar #<?= (int) $blInventoryId ?></option>
            <?php endif; ?>
          </select>
        </div>
      </div>
      <div class="bl-actions">
        <button type="button" class="bl-btn bl-btn-primary" id="blSaveTokenBtn">Salvează token</button>
        <button type="button" class="bl-btn" id="blTestBtn">Test conexiune</button>
        <button type="button" class="bl-btn" id="blSaveInventoryBtn">Salvează inventar</button>
      </div>
      <?php if ($lastTest !== ''): ?>
        <div class="bl-status <?= $lastTest === 'success' ? 'ok' : 'bad' ?>" id="blTestStatus">
          Ultim test: <?= htmlspecialchars($lastTest, ENT_QUOTES) ?><?= $lastTestMsg !== '' ? ' — ' . htmlspecialchars($lastTestMsg, ENT_QUOTES) : '' ?>
        </div>
      <?php else: ?>
        <div class="bl-status bad" id="blTestStatus" style="display:none;"></div>
      <?php endif; ?>
      <p style="font-size:.78rem;color:#64748b;margin:12px 0 0;">Produse sincronizate: <strong id="blSyncedCount"><?= $productsSynced ?></strong></p>
    </div>

    <div class="bl-card">
      <h3>Mapare câmpuri Besoiu → BaseLinker</h3>
      <table class="bl-mapping-table">
        <thead>
          <tr><th>Câmp BaseLinker</th><th>Câmp Besoiu (sursă)</th></tr>
        </thead>
        <tbody>
          <?php
          $mappingRows = [
              'name' => 'Nume produs',
              'sku' => 'SKU / cod',
              'price_brutto' => 'Preț brut',
              'description' => 'Descriere',
              'quantity' => 'Stoc',
              'images' => 'Imagini (JSON)',
          ];
          foreach ($mappingRows as $blKey => $label):
              $selected = (string) ($storedMapping[$blKey] ?? '');
          ?>
          <tr>
            <td><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
            <td>
              <select class="bl-map-field" data-bl-field="<?= htmlspecialchars($blKey, ENT_QUOTES) ?>">
                <option value="">—</option>
                <?php foreach ($sourceFields as $src): ?>
                  <option value="<?= htmlspecialchars($src, ENT_QUOTES) ?>"<?= $selected === $src ? ' selected' : '' ?>><?= htmlspecialchars($src, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <input type="hidden" id="blTextFields" value="<?= htmlspecialchars((string) ($storedMapping['text_fields'] ?? $defaultMapping['text_fields']), ENT_QUOTES) ?>">
      <div class="bl-actions">
        <button type="button" class="bl-btn bl-btn-primary" id="blSaveMappingBtn">Salvează maparea</button>
      </div>
    </div>

    <div class="bl-card">
      <h3>Sincronizare catalog</h3>
      <p style="font-size:.84rem;color:#64748b;margin:0 0 12px;">Trimite produse active către inventarul BaseLinker. Pentru catalog mare (~100k), folosește „Pune tot catalogul în coadă” — batch-uri API procesate de cron, fără limită 30MB fișier.</p>
      <div class="bl-grid">
        <div class="bl-field">
          <label for="blSyncLimit">Produse per batch</label>
          <input id="blSyncLimit" type="number" min="1" max="200" value="50">
        </div>
      </div>
      <div class="bl-actions">
        <button type="button" class="bl-btn bl-btn-primary" id="blSyncBtn">Sincronizează batch (manual)</button>
        <button type="button" class="bl-btn" id="blEnqueueBtn">Pune tot catalogul în coadă (API)</button>
      </div>
      <pre id="blSyncResult" class="bl-sync-result" hidden></pre>
    </div>

    <div class="bl-card" id="blFeedCard">
      <h3>Feed permanent BaseLinker (XML / JSON)</h3>
      <p style="font-size:.84rem;color:#64748b;margin:0 0 12px;">URL fix pe care BaseLinker îl poate citi ca wholesaler/feed extern. Se regenerează automat după fiecare publicare din coada import (fragmente &lt; 30MB).</p>
      <?php
        $feedUrls = is_array($blFeedInfo['urls'] ?? null) ? $blFeedInfo['urls'] : [];
        $feedMeta = is_array($blFeedInfo['meta'] ?? null) ? $blFeedInfo['meta'] : [];
        $feedGenerated = trim((string) ($feedMeta['generated_at'] ?? ''));
        $feedProducts = (int) ($feedMeta['product_count'] ?? 0);
        $feedParts = is_array($feedMeta['parts'] ?? null) ? count($feedMeta['parts']) : 0;
      ?>
      <div class="bl-grid">
        <div class="bl-field">
          <label for="blFeedXmlUrl">URL feed XML (principal)</label>
          <input id="blFeedXmlUrl" type="text" readonly value="<?= htmlspecialchars((string) ($feedUrls['xml'] ?? ''), ENT_QUOTES) ?>">
        </div>
        <div class="bl-field">
          <label for="blFeedJsonUrl">URL feed JSON</label>
          <input id="blFeedJsonUrl" type="text" readonly value="<?= htmlspecialchars((string) ($feedUrls['json'] ?? ''), ENT_QUOTES) ?>">
        </div>
      </div>
      <div class="bl-stats" style="margin-top:10px;">
        <div class="bl-stat"><b id="blFeedProducts"><?= number_format($feedProducts, 0, ',', '.') ?></b>produse în feed</div>
        <div class="bl-stat"><b id="blFeedParts"><?= (int) $feedParts ?></b>fragmente XML</div>
        <div class="bl-stat"><b id="blFeedUpdated"><?= $feedGenerated !== '' ? htmlspecialchars(substr($feedGenerated, 0, 19), ENT_QUOTES) : '—' ?></b>ultima regenerare (UTC)</div>
      </div>
      <?php if ($feedParts > 1): ?>
        <p style="font-size:.78rem;color:#64748b;margin:10px 0 0;">Catalog fragmentat: folosește <code>part=index</code> pentru manifest sau <code>part=1..N</code> per fragment.</p>
      <?php endif; ?>
      <div class="bl-actions">
        <button type="button" class="bl-btn" id="blCopyFeedXmlBtn">Copiază URL XML</button>
        <button type="button" class="bl-btn bl-btn-primary" id="blRegenFeedBtn">Regenerează feed acum</button>
      </div>
      <pre id="blFeedResult" class="bl-sync-result" hidden></pre>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/marketplace_endpoint.php';
  let connectionId = <?= (int) $connectionId ?>;

  function toast(msg, isError) {
    const el = document.getElementById('blToast');
    if (!el) return;
    el.textContent = msg;
    el.style.color = isError ? '#b91c1c' : '#166534';
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 3500);
  }

  async function api(action, payload) {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: action, ...payload }),
    });
    const raw = await res.text();
    let json;
    try { json = JSON.parse(raw); } catch (e) { throw new Error('Endpoint-ul nu a returnat JSON valid.'); }
    if (!res.ok || !json.success) throw new Error(json.message || 'Eroare API');
    return json.data;
  }

  function collectMapping() {
    const mapping = {};
    document.querySelectorAll('.bl-map-field').forEach((sel) => {
      const key = sel.getAttribute('data-bl-field');
      if (key) mapping[key] = sel.value || '';
    });
    const textFields = document.getElementById('blTextFields');
    if (textFields) mapping.text_fields = textFields.value || '';
    return mapping;
  }

  function renderInventories(list, selectedId) {
    const sel = document.getElementById('blInventory');
    if (!sel || !Array.isArray(list)) return;
    sel.innerHTML = '<option value="">— selectează —</option>';
    list.forEach((inv) => {
      const id = String(inv.inventory_id || inv.id || '');
      if (!id) return;
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = (inv.name || ('Inventar #' + id)) + ' (#' + id + ')';
      if (Number(id) === Number(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  document.getElementById('blCreateBtn')?.addEventListener('click', async () => {
    try {
      const name = document.getElementById('blNewName')?.value?.trim() || 'BaseLinker Besoiu';
      const token = document.getElementById('blNewToken')?.value?.trim() || '';
      if (!token) throw new Error('Token API obligatoriu.');
      const data = await api('add', {
        name,
        platform: 'baselinker',
        api_token: token,
        token_status: 'active',
        sync_mode: 'manual',
        notes: 'Catalog produse Besoiu → BaseLinker',
      });
      connectionId = Number(data.randomn_id || 0);
      toast('Conexiune BaseLinker creată.', false);
      window.location.reload();
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blSaveTokenBtn')?.addEventListener('click', async () => {
    try {
      const token = document.getElementById('blToken')?.value?.trim() || '';
      const payload = { randomn_id: connectionId };
      if (token) payload.api_token = token;
      await api('edit', payload);
      toast('Token salvat.', false);
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blTestBtn')?.addEventListener('click', async () => {
    try {
      const data = await api('baselinker_test', { randomn_id: connectionId });
      const box = document.getElementById('blTestStatus');
      if (box) {
        box.style.display = 'block';
        box.className = 'bl-status ' + (data.last_test_status === 'success' ? 'ok' : 'bad');
        box.textContent = 'Ultim test: ' + data.last_test_status + ' — ' + (data.last_test_message || '');
      }
      if (Array.isArray(data.inventories)) {
        const invSel = document.getElementById('blInventory');
        renderInventories(data.inventories, invSel?.value || 0);
      }
      toast('Test conexiune finalizat.', data.last_test_status !== 'success');
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blSaveInventoryBtn')?.addEventListener('click', async () => {
    try {
      const inv = Number(document.getElementById('blInventory')?.value || 0);
      if (!inv) throw new Error('Selectează un inventar.');
      await api('baselinker_save_inventory', { randomn_id: connectionId, bl_inventory_id: inv });
      toast('Inventar salvat.', false);
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blSaveMappingBtn')?.addEventListener('click', async () => {
    try {
      await api('baselinker_save_mapping', { randomn_id: connectionId, field_mapping: collectMapping() });
      toast('Mapare salvată.', false);
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blSyncBtn')?.addEventListener('click', async () => {
    try {
      const limit = Number(document.getElementById('blSyncLimit')?.value || 50);
      const data = await api('baselinker_sync_products', { randomn_id: connectionId, limit });
      const pre = document.getElementById('blSyncResult');
      if (pre) {
        pre.hidden = false;
        pre.classList.add('is-visible');
        pre.textContent = JSON.stringify(data, null, 2);
      }
      const syncedEl = document.getElementById('blSyncedCount');
      if (syncedEl && typeof data.synced === 'number') {
        syncedEl.textContent = String(Number(syncedEl.textContent || 0) + data.synced);
      }
      toast(data.message || 'Sincronizare finalizată.', data.status === 'failed');
    } catch (e) { toast(e.message, true); }
  });

  document.getElementById('blEnqueueBtn')?.addEventListener('click', async () => {
    try {
      const limit = Number(document.getElementById('blSyncLimit')?.value || 50);
      const data = await api('baselinker_enqueue_catalog', { randomn_id: connectionId, limit });
      const pre = document.getElementById('blSyncResult');
      if (pre) {
        pre.hidden = false;
        pre.classList.add('is-visible');
        pre.textContent = JSON.stringify(data, null, 2);
      }
      toast(data.message || 'Catalog pus în coadă.', data.status === 'failed');
    } catch (e) { toast(e.message, true); }
  });

  if (connectionId > 0) {
    api('baselinker_catalog_stats', { randomn_id: connectionId })
      .then((stats) => {
        const prodEl = document.getElementById('blStatProducts');
        const batchEl = document.getElementById('blStatBatches');
        if (prodEl && typeof stats.active_products === 'number') {
          prodEl.textContent = String(stats.active_products).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        if (batchEl && typeof stats.estimated_api_batches === 'number') {
          batchEl.textContent = String(stats.estimated_api_batches);
        }
      })
      .catch(() => {});

    api('baselinker_inventories', { randomn_id: connectionId })
      .then((list) => {
        const invSel = document.getElementById('blInventory');
        renderInventories(list, invSel?.querySelector('option[selected]')?.value || <?= (int) $blInventoryId ?>);
      })
      .catch(() => {});

    document.getElementById('blCopyFeedXmlBtn')?.addEventListener('click', async () => {
      const url = document.getElementById('blFeedXmlUrl')?.value || '';
      if (!url) { toast('URL feed indisponibil.', true); return; }
      try {
        await navigator.clipboard.writeText(url);
        toast('URL XML copiat.', false);
      } catch (e) {
        toast('Copiere eșuată — selectează manual URL-ul.', true);
      }
    });

    document.getElementById('blRegenFeedBtn')?.addEventListener('click', async () => {
      try {
        const data = await api('baselinker_feed_regenerate', { randomn_id: connectionId });
        const pre = document.getElementById('blFeedResult');
        if (pre) {
          pre.hidden = false;
          pre.classList.add('is-visible');
          pre.textContent = JSON.stringify(data, null, 2);
        }
        const urls = data.urls || {};
        if (urls.xml) {
          const xmlInput = document.getElementById('blFeedXmlUrl');
          if (xmlInput) xmlInput.value = urls.xml;
        }
        if (urls.json) {
          const jsonInput = document.getElementById('blFeedJsonUrl');
          if (jsonInput) jsonInput.value = urls.json;
        }
        const meta = data.meta || {};
        const prodEl = document.getElementById('blFeedProducts');
        const partsEl = document.getElementById('blFeedParts');
        const updEl = document.getElementById('blFeedUpdated');
        if (prodEl && typeof meta.product_count === 'number') {
          prodEl.textContent = String(meta.product_count).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        if (partsEl && Array.isArray(meta.parts)) {
          partsEl.textContent = String(meta.parts.length);
        }
        if (updEl && meta.generated_at) {
          updEl.textContent = String(meta.generated_at).slice(0, 19);
        }
        toast(data.message || 'Feed regenerat.', false);
      } catch (e) { toast(e.message, true); }
    });

    async function copyFieldValue(inputId, okMsg) {
      const el = document.getElementById(inputId);
      const value = el?.value?.trim() || '';
      if (!value) { toast('Valoare indisponibilă.', true); return; }
      try {
        await navigator.clipboard.writeText(value);
        toast(okMsg, false);
      } catch (e) {
        toast('Copiere eșuată — selectează manual.', true);
      }
    }

    document.getElementById('blCopyShopUrlBtn')?.addEventListener('click', () => {
      copyFieldValue('blShopIntegrationUrl', 'URL integrare copiat.');
    });
    document.getElementById('blCopyBlPassBtn')?.addEventListener('click', () => {
      copyFieldValue('blShopBlPass', 'bl_pass copiat.');
    });
    document.getElementById('blCopyTicketBtn')?.addEventListener('click', async () => {
      const ticket = document.getElementById('blSupportTicket')?.value?.trim() || '';
      if (!ticket) { toast('Tichet suport indisponibil.', true); return; }
      try {
        await navigator.clipboard.writeText(ticket);
        toast('Tichet suport copiat.', false);
      } catch (e) {
        toast('Copiere eșuată — selectează manual textul.', true);
      }
    });
  }
})();
</script>
