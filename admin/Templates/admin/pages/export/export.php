<?php

declare(strict_types=1);

require __DIR__ . '/../_partials/fz-surface-styles.php';

use Evasystem\Core\AdminUrl;

$pdo = \Config\Database::getDB();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM produse WHERE status <> :inactive');
$stmt->execute([':inactive' => '0']);
$activeCount = (int) $stmt->fetchColumn();
$exportApiUrl = AdminUrl::api('export_action_endpoint.php');
?>

<style>
  .export-panel .export-card {
    margin-top: 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  }
  .export-panel .export-card h3 {
    margin: 0 0 8px;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
  }
  .export-panel .export-card p {
    margin: 0 0 16px;
    font-size: 0.84rem;
    color: #64748b;
    line-height: 1.5;
  }
  .export-panel .export-columns {
    display: grid;
    gap: 6px;
    margin: 0 0 18px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 0.78rem;
    color: #475569;
  }
  .export-panel .export-btn-autopro {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    border: 1px solid #0d9488;
    background: #f0fdfa;
    color: #0f766e;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
  }
  .export-panel .export-btn-autopro:hover:not(:disabled) {
    background: #ccfbf1;
    border-color: #14b8a6;
  }
  .export-panel .export-btn-autopro:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  .export-panel .export-toast {
    display: none;
    margin-top: 14px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
  }
  .export-panel .export-toast.is-visible { display: block; }
  .export-panel .export-toast.is-ok {
    background: #dcfce7;
    border: 1px solid #86efac;
    color: #166534;
  }
  .export-panel .export-toast.is-error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
  }
</style>

<div class="furnizori-page export-panel">
  <div class="fz-header">
    <div>
      <h2 class="fz-title">Export catalog</h2>
      <p class="fz-subtitle">Generare fișiere export produse pentru marketplace și integrări externe.</p>
    </div>
  </div>

  <p class="fz-summary-caption">Rezumat catalog</p>
  <div class="fz-summary-metrics" role="status">
    <div class="fz-metric">
      <span class="fz-metric-label">Produse active</span>
      <span class="fz-metric-value"><?= number_format($activeCount, 0, ',', '.') ?></span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Format Piese Autopro</span>
      <span class="fz-metric-value">CSV ;</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Coloane</span>
      <span class="fz-metric-value">7</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Monedă</span>
      <span class="fz-metric-value">RON</span>
    </div>
  </div>

  <div class="export-card">
    <h3>Piese Autopro — export produse</h3>
    <p>Descarcă un fișier CSV cu toate produsele active din magazin, în formatul acceptat de Piese Autopro.</p>
    <div class="export-columns">
      Coloane: ID, titlu, categorie, descriere, monedă, preț, cantitate
    </div>
    <button type="button" id="exportCatalogAutoproBtn" class="export-btn-autopro" title="CSV format Piese Autopro din catalog produse active">
      Generare fișier export produse Piese Autopro
    </button>
    <div id="exportCatalogAutoproToast" class="export-toast" role="status"></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const ENDPOINT = <?= json_encode($exportApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const btn = document.getElementById('exportCatalogAutoproBtn');
  const toast = document.getElementById('exportCatalogAutoproToast');

  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'export-toast is-visible ' + (isError ? 'is-error' : 'is-ok');
  }

  async function exportCatalogAutoproCsv() {
    const prevLabel = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Se generează fișierul...';
    }
    if (toast) {
      toast.className = 'export-toast';
      toast.textContent = '';
    }

    try {
      const response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'export_catalog_autopro_csv' }),
      });

      if (!response.ok) {
        let message = 'Export CSV Piese Autopro eșuat.';
        try {
          const json = JSON.parse(await response.text());
          message = json.message || message;
        } catch (e) {}
        throw new Error(message);
      }

      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      if (contentType.includes('application/json')) {
        const json = await response.json();
        throw new Error(json.message || 'Export CSV Piese Autopro eșuat.');
      }

      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'produse_piese_autopro_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      showToast('Fișier CSV Piese Autopro descărcat (' + <?= (int) $activeCount ?> + ' produse active).', false);
    } catch (error) {
      showToast((error && error.message) ? error.message : 'Export CSV Piese Autopro eșuat.', true);
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = prevLabel || 'Generare fișier export produse Piese Autopro';
      }
    }
  }

  btn?.addEventListener('click', exportCatalogAutoproCsv);
})();
</script>
