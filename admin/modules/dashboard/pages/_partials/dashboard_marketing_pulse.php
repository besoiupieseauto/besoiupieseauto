<?php
declare(strict_types=1);

/** Pulse marketing — doar statistici + link-uri rapide (dashboard workspace marketing). */
?>
<div class="col-span-12" data-besoiu-dash-panel="marketing-pulse">
    <div class="bmgh-dash-pulse" id="bmgh-dash-pulse">
        <div class="bmgh-dash-pulse__head">
            <div>
                <h3>Pulse marketing</h3>
                <p>Statistici magazin (modulul Marketing nu e portat încă — valorile se completează când API-ul e disponibil).</p>
            </div>
            <div class="bmgh-dash-pulse__links">
                <a href="/admin/dashboard" class="bmgh-dash-pulse__link">Dashboard</a>
                <a href="/admin/product" class="bmgh-dash-pulse__link">Produse</a>
                <a href="/admin/importreview" class="bmgh-dash-pulse__link">Coadă import</a>
            </div>
        </div>
        <div class="bmgh-dash-pulse__grid">
            <div class="bmgh-dash-pulse__item"><span>Căutări azi</span><strong id="bmgh-dash-searches">—</strong></div>
            <div class="bmgh-dash-pulse__item"><span>Rată succes</span><strong id="bmgh-dash-rate">—</strong></div>
            <div class="bmgh-dash-pulse__item"><span>OEM lipsă</span><strong id="bmgh-dash-missing">—</strong></div>
            <div class="bmgh-dash-pulse__item"><span>Comenzi azi</span><strong id="bmgh-dash-orders">—</strong></div>
            <div class="bmgh-dash-pulse__item"><span>Alerte marketing</span><strong id="bmgh-dash-alerts">—</strong></div>
        </div>
    </div>
</div>
<script>
(function () {
  'use strict';
  if (!document.getElementById('bmgh-dash-pulse')) return;
  fetch('/admin/api/marketing_hub_endpoint.php?action=insights', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j || !j.success || !j.data || !j.data.live) return;
      var l = j.data.live;
      var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
      set('bmgh-dash-searches', (l.searches_today || 0).toLocaleString('ro-RO'));
      set('bmgh-dash-rate', (l.success_rate || 0) + '%');
      set('bmgh-dash-missing', (l.missing_codes || 0).toLocaleString('ro-RO'));
      set('bmgh-dash-orders', (l.orders_today || 0).toLocaleString('ro-RO'));
    }).catch(function () {});
  fetch('/admin/api/marketing_hub_endpoint.php?action=alerts', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      var n = (j && j.data && j.data.alerts) ? j.data.alerts.length : 0;
      var el = document.getElementById('bmgh-dash-alerts');
      if (el) el.textContent = String(n);
    }).catch(function () {});
})();
</script>
