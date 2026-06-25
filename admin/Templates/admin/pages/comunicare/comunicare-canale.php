<?php declare(strict_types=1); ?>
<div class="bpa-com mt-6">
    <a href="/admin/comunicare" class="bpa-com-back">
        <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
        Înapoi la hub
    </a>
    <header class="bpa-com-page-head">
        <h2>Canale comunicare</h2>
        <p>Distribuție mesaje per canal — vezi de unde vin clienții și deschide conversațiile direct.</p>
    </header>
    <div id="canale-grid" class="bpa-com-channel-grid bpa-com-animate"></div>
</div>
<script>
(function(){
  const grid = document.getElementById('canale-grid');
  const labels = { whatsapp:'WhatsApp', olx:'OLX', facebook:'Facebook', website:'Website', email:'Email', manual:'Manual', pieseauto:'PieseAuto.ro', dezro:'dez.ro' };
  const icons = { whatsapp:'message-circle', olx:'tag', facebook:'facebook', website:'globe', email:'mail', manual:'user' };
  function esc(v){ return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  fetch('/admin/api/comunicare_endpoint.php?action=hub').then(r=>r.json()).then(j=>{
    const ch = j.data?.channels || [];
    grid.innerHTML = ch.length ? ch.map(c=>`
      <div class="bpa-com-channel-stat">
        <div class="bpa-com-channel-stat__n">${esc(c.count)}</div>
        <div class="bpa-com-channel-stat__l">${esc(labels[c.channel]||c.channel)}</div>
        <a href="/admin/messages" class="text-xs font-bold mt-2 inline-block" style="color:#0d9488">Vezi mesaje →</a>
      </div>`).join('') : '<div class="bpa-com-card bpa-com-card--pad col-span-full text-center opacity-70">Nu există mesaje încă.</div>';
    if (window.lucide) window.lucide.createIcons();
  });
})();
</script>
