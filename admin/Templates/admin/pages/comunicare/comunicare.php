<?php

declare(strict_types=1);

?>
<div class="bpa-com mt-6" id="comunicare-hub">
    <header class="bpa-com-hero">
        <div class="bpa-com-hero__inner">
            <div>
                <div class="bpa-com-hero__badge">
                    <i data-lucide="radio" style="width:14px;height:14px"></i>
                    Departament activ
                </div>
                <h1>Comunicare &amp; Socializare</h1>
                <p>Mesaje, template-uri și canale într-un singur loc — clar, rapid, profesional. Răspunde clienților în câteva secunde.</p>
            </div>
            <div class="bpa-com-hero__actions">
                <a href="/admin/messages" class="bpa-com-btn bpa-com-btn--white">
                    <i data-lucide="messages-square" style="width:16px;height:16px"></i>
                    Deschide mesageria
                </a>
                <a href="/admin/reply-templates" class="bpa-com-btn bpa-com-btn--ghost">
                    <i data-lucide="file-text" style="width:16px;height:16px"></i>
                    Template-uri
                </a>
            </div>
        </div>
    </header>

    <div class="bpa-com-kpi-grid bpa-com-animate" id="comunicare-hub-kpi">
        <div class="bpa-com-kpi">
            <div class="bpa-com-kpi__icon"><i data-lucide="mail"></i></div>
            <div class="bpa-com-kpi__label">Mesaje necitite</div>
            <div class="bpa-com-kpi__value" data-kpi="messages_unread">—</div>
        </div>
        <div class="bpa-com-kpi">
            <div class="bpa-com-kpi__icon"><i data-lucide="files"></i></div>
            <div class="bpa-com-kpi__label">Template-uri</div>
            <div class="bpa-com-kpi__value" data-kpi="templates_total">—</div>
        </div>
        <div class="bpa-com-kpi">
            <div class="bpa-com-kpi__icon"><i data-lucide="zap"></i></div>
            <div class="bpa-com-kpi__label">Răspunsuri rapide</div>
            <div class="bpa-com-kpi__value" data-kpi="templates_quick">—</div>
        </div>
        <div class="bpa-com-kpi">
            <div class="bpa-com-kpi__icon"><i data-lucide="bar-chart-2"></i></div>
            <div class="bpa-com-kpi__label">Utilizări template</div>
            <div class="bpa-com-kpi__value" data-kpi="templates_uses">—</div>
        </div>
    </div>

    <h2 class="bpa-com-section-title">
        Instrumente tale
        <span></span>
    </h2>
    <div class="bpa-com-modules bpa-com-animate" id="comunicare-hub-ideas"></div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/comunicare_endpoint.php?action=hub';
  const ideasEl = document.getElementById('comunicare-hub-ideas');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  async function load() {
    const res = await fetch(ENDPOINT);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Eroare');
    const d = json.data || {};
    document.querySelectorAll('[data-kpi]').forEach(el => {
      const key = el.getAttribute('data-kpi');
      if (key && d[key] !== undefined) el.textContent = String(d[key]);
    });
    const ideas = d.ideas || [];
    ideasEl.innerHTML = ideas.map((idea, i) => `
      <a href="${escapeHtml(idea.url)}" class="bpa-com-module">
        <span class="bpa-com-module__num">${i + 1}</span>
        <span class="bpa-com-module__icon"><i data-lucide="${escapeHtml(idea.icon || 'circle')}"></i></span>
        <span class="bpa-com-module__body">
          <h3>${escapeHtml(idea.title)}</h3>
          <p>${escapeHtml(idea.desc)}</p>
        </span>
        <span class="bpa-com-module__arrow"><i data-lucide="arrow-right" style="width:18px;height:18px"></i></span>
      </a>
    `).join('');
    if (window.lucide) window.lucide.createIcons();
  }

  load().catch(e => { ideasEl.innerHTML = `<div class="text-danger p-4">${escapeHtml(e.message)}</div>`; });
})();
</script>
