<?php declare(strict_types=1); ?>
<div class="bpa-com mt-6">
    <a href="/admin/comunicare" class="bpa-com-back">
        <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
        Înapoi la hub
    </a>
    <header class="bpa-com-page-head">
        <h2>Broadcast mesaje</h2>
        <p>Compune un mesaj în masă pornind de la template — copiază pentru WhatsApp Business sau email.</p>
    </header>

    <div class="bpa-com-tpl-layout">
        <div class="bpa-com-card bpa-com-card--pad">
            <label class="text-xs font-bold block mb-1">Template</label>
            <select id="bc-template" class="bpa-com-select mb-3"></select>
            <label class="text-xs font-bold block mb-1">Nume client (test)</label>
            <input id="bc-client" class="bpa-com-input mb-2" placeholder="Ion Popescu">
            <input id="bc-order" class="bpa-com-input mb-2" placeholder="Nr. comandă ORD-123">
            <input id="bc-total" class="bpa-com-input mb-3" placeholder="Total RON">
            <button type="button" id="bc-apply" class="bpa-com-btn bpa-com-btn--primary w-full">
                <i data-lucide="sparkles" style="width:16px;height:16px"></i>
                Generează mesaj
            </button>
        </div>
        <div class="bpa-com-card bpa-com-card--pad">
            <label class="text-xs font-bold block mb-2">Mesaj final</label>
            <textarea id="bc-output" rows="12" class="bpa-com-textarea" readonly placeholder="Mesajul generat apare aici…"></textarea>
            <button type="button" id="bc-copy" class="bpa-com-btn bpa-com-btn--outline mt-3">
                <i data-lucide="copy" style="width:14px;height:14px"></i>
                Copiază în clipboard
            </button>
        </div>
    </div>
</div>
<script>
(function(){
  const sel = document.getElementById('bc-template');
  const out = document.getElementById('bc-output');
  fetch('/admin/api/comunicare_endpoint.php?action=list').then(r=>r.json()).then(j=>{
    (j.data?.items||[]).forEach(t=>{
      const o = document.createElement('option');
      o.value = t.randomn_id; o.textContent = t.title + ' (' + t.channel + ')';
      sel.appendChild(o);
    });
    if (window.lucide) window.lucide.createIcons();
  });
  document.getElementById('bc-apply').onclick = async () => {
    const res = await fetch('/admin/api/comunicare_endpoint.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'apply', randomn_id: sel.value, variables: {
        client_name: document.getElementById('bc-client').value,
        order_number: document.getElementById('bc-order').value,
        total_amount: document.getElementById('bc-total').value,
        shop_url: 'https://besoiupieseauto.ro'
      }})
    });
    const j = await res.json();
    out.value = j.data?.rendered_text || '';
  };
  document.getElementById('bc-copy').onclick = async () => {
    try {
      await navigator.clipboard.writeText(out.value);
    } catch (e) {
      out.select();
      document.execCommand('copy');
    }
  };
})();
</script>
