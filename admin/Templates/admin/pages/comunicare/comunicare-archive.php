<?php declare(strict_types=1); ?>
<div class="bpa-com mt-6">
    <a href="/admin/comunicare" class="bpa-com-back">
        <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
        Înapoi la hub
    </a>
    <header class="bpa-com-page-head">
        <h2>Arhivă conversații</h2>
        <p>Conversații marcate citite / rezolvate — istoric comunicare cu clienții.</p>
    </header>
    <div id="archive-list" class="bpa-com-card bpa-com-card--pad"></div>
</div>
<script>
(function(){
  const END = '/admin/api/messages_endpoint.php';
  function esc(v){ return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  fetch(END, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ type_product:'conversations', page:1, per_page:50 })})
    .then(r=>r.json()).then(j=>{
      const items = (j.data?.items || j.data || []).filter(m => Number(m.is_read) === 1);
      const el = document.getElementById('archive-list');
      if (!items.length) { el.innerHTML = '<p class="text-center py-8 opacity-70">Arhiva este goală.</p>'; return; }
      el.innerHTML = items.map(m => `
        <div class="bpa-com-archive-row">
          <strong>${esc(m.name||'Client')}</strong>
          <span class="bpa-com-pill bpa-com-pill--cat">${esc(m.channel||'')}</span>
          <span class="text-sm opacity-70 flex-1 truncate">${esc(m.message_body||'')}</span>
          <span class="text-xs opacity-50">${esc((m.created_at||'').slice(0,16))}</span>
          <a href="/admin/messages" class="bpa-com-lead-card__link text-xs">Deschide</a>
        </div>`).join('');
    });
})();
</script>
