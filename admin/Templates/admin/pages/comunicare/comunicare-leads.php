<?php declare(strict_types=1); ?>
<div class="bpa-com mt-6">
    <a href="/admin/comunicare" class="bpa-com-back">
        <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
        Înapoi la hub
    </a>
    <header class="bpa-com-page-head">
        <h2>Lead-uri &amp; formulare</h2>
        <p>Mesaje inbound necitite și coșuri abandonate — contactează clienții înaintea concurenței.</p>
    </header>

    <div class="bpa-com-lead-grid bpa-com-animate">
        <a href="/admin/messages" class="bpa-com-lead-card">
            <div class="bpa-com-lead-card__label">Mesaje necitite</div>
            <div class="bpa-com-lead-card__value" id="leads-unread">—</div>
            <span class="bpa-com-lead-card__link">Deschide mesageria →</span>
        </a>
        <a href="/admin/abandoned-carts" class="bpa-com-lead-card">
            <div class="bpa-com-lead-card__label">Coșuri abandonate</div>
            <div class="bpa-com-lead-card__value" style="font-size:1.1rem;margin-top:0.5rem">Checkout nefinalizat</div>
            <span class="bpa-com-lead-card__link">Vezi coșuri →</span>
        </a>
    </div>

    <div class="bpa-com-card bpa-com-card--pad">
        <h3 class="font-bold mb-3" style="font-size:0.9rem">Lead-uri recente (necitite)</h3>
        <div id="leads-list"></div>
    </div>
</div>
<script>
(function(){
  const END = '/admin/api/messages_endpoint.php';
  function esc(v){ return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  async function api(type, payload) {
    const r = await fetch(END, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ type_product: type, ...payload })});
    const j = await r.json();
    if (!j.success) throw new Error(j.message);
    return j.data;
  }
  Promise.all([
    fetch('/admin/api/comunicare_endpoint.php?action=hub').then(r=>r.json()),
    api('conversations', { page: 1, per_page: 15 })
  ]).then(([hub, conv]) => {
    document.getElementById('leads-unread').textContent = hub.data?.messages_unread ?? 0;
    const items = (conv.items || conv || []).filter(m => Number(m.is_read) === 0 && m.direction === 'inbound');
    const el = document.getElementById('leads-list');
    if (!items.length) { el.innerHTML = '<p class="opacity-70 text-center py-6">Niciun lead nou în listă.</p>'; return; }
    el.innerHTML = `<div class="bpa-com-table-wrap"><table class="bpa-com-table"><thead><tr><th>Client</th><th>Canal</th><th>Mesaj</th><th></th></tr></thead><tbody>` +
      items.map(m => `<tr><td><strong>${esc(m.name||'—')}</strong></td><td><span class="bpa-com-pill bpa-com-pill--cat">${esc(m.channel||'')}</span></td><td class="max-w-xs truncate">${esc(m.message_body||'')}</td><td><a class="bpa-com-lead-card__link" href="/admin/messages">Răspunde</a></td></tr>`).join('') + '</tbody></table></div>';
    if (window.lucide) window.lucide.createIcons();
  }).catch(e => { document.getElementById('leads-list').textContent = e.message; });
})();
</script>
