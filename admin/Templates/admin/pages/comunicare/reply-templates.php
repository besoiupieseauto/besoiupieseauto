<?php

declare(strict_types=1);

?>
<div class="bpa-com mt-6" id="reply-templates-page">
    <a href="/admin/comunicare" class="bpa-com-back">
        <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
        Înapoi la hub
    </a>

    <header class="bpa-com-page-head">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2>Template-uri răspuns</h2>
                <p>Formulare pentru WhatsApp, email, OLX, Facebook — cu variabile dinamice și preview live.</p>
            </div>
            <button type="button" id="tpl-new-btn" class="bpa-com-btn bpa-com-btn--primary">
                <i data-lucide="plus" style="width:16px;height:16px"></i>
                Template nou
            </button>
        </div>
    </header>

    <div class="bpa-com-toolbar">
        <button type="button" class="bpa-com-tab is-active" data-tab="all">Toate</button>
        <button type="button" class="bpa-com-tab" data-tab="quick">Răspunsuri rapide</button>
        <button type="button" class="bpa-com-tab" data-tab="variables">Variabile</button>
        <select id="tpl-filter-channel" class="bpa-com-select ml-auto" style="width:auto;min-width:140px">
            <option value="">Toate canalele</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="email">Email</option>
            <option value="olx">OLX</option>
            <option value="facebook">Facebook</option>
            <option value="website">Website</option>
            <option value="all">Universal</option>
        </select>
        <input id="tpl-search" class="bpa-com-input" style="width:11rem" placeholder="Caută template...">
    </div>

    <div id="tpl-variables-panel" class="bpa-com-hidden bpa-com-card bpa-com-card--pad mb-5">
        <h3 class="font-bold mb-1" style="font-size:0.95rem">Variabile disponibile</h3>
        <p class="text-sm opacity-70 mb-3">Folosește în text: <code>{client_name}</code>, <code>{order_number}</code> etc. Click pe chip pentru copiere.</p>
        <div id="tpl-variables-list" class="flex flex-wrap gap-2"></div>
    </div>

    <div class="bpa-com-tpl-layout" id="tpl-main-layout">
        <div>
            <div id="tpl-list"></div>
        </div>
        <div class="bpa-com-card bpa-com-card--pad" style="position:sticky;top:1rem">
            <h3 class="font-bold mb-3" style="font-size:0.95rem" id="tpl-editor-title">
                <i data-lucide="edit-3" style="width:16px;height:16px;vertical-align:-2px"></i>
                Editor / Preview
            </h3>
            <form id="tpl-form" class="space-y-3">
                <input type="hidden" name="randomn_id">
                <input name="title" class="bpa-com-input" placeholder="Titlu template" required>
                <div class="grid grid-cols-2 gap-2">
                    <select name="category" class="bpa-com-select">
                        <option value="general">General</option>
                        <option value="comenzi">Comenzi</option>
                        <option value="stoc">Stoc</option>
                        <option value="oferte">Oferte</option>
                        <option value="livrare">Livrare</option>
                        <option value="followup">Follow-up</option>
                        <option value="marketplace">Marketplace</option>
                        <option value="social">Social</option>
                        <option value="postvanzare">Post-vânzare</option>
                    </select>
                    <select name="channel" class="bpa-com-select">
                        <option value="all">Toate canalele</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="olx">OLX</option>
                        <option value="facebook">Facebook</option>
                        <option value="website">Website</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="is_quick" value="1">
                    Răspuns rapid (snippet 1-click)
                </label>
                <textarea name="body_text" rows="8" class="bpa-com-textarea" placeholder="Text template cu {variabile}" required></textarea>
                <div class="text-xs font-bold uppercase tracking-wide opacity-50">Preview live</div>
                <div id="tpl-preview" class="bpa-com-preview"></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="bpa-com-btn bpa-com-btn--primary flex-1">Salvează</button>
                    <button type="button" id="tpl-delete-btn" class="bpa-com-btn bpa-com-btn--outline text-danger bpa-com-hidden">Dezactivează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const API = '/admin/api/comunicare_endpoint.php';
  let items = [];
  let variables = [];
  let activeTab = new URLSearchParams(location.search).get('tab') || 'all';
  let selectedId = '';

  const listEl = document.getElementById('tpl-list');
  const form = document.getElementById('tpl-form');
  const previewEl = document.getElementById('tpl-preview');
  const varsPanel = document.getElementById('tpl-variables-panel');
  const varsList = document.getElementById('tpl-variables-list');
  const mainLayout = document.getElementById('tpl-main-layout');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function channelPill(ch) {
    const labels = { whatsapp:'WhatsApp', email:'Email', olx:'OLX', facebook:'Facebook', website:'Website', all:'Universal' };
    return `<span class="bpa-com-pill bpa-com-pill--channel">${escapeHtml(labels[ch] || ch)}</span>`;
  }

  function renderPreview() {
    const body = form.body_text.value || '';
    let out = body;
    variables.forEach(v => { out = out.replace(new RegExp('\\{' + v + '\\}', 'gi'), '[' + v + ']'); });
    previewEl.textContent = out || 'Scrie textul template-ului pentru a vedea preview…';
  }

  function renderList() {
    const q = (document.getElementById('tpl-search')?.value || '').toLowerCase();
    const ch = document.getElementById('tpl-filter-channel')?.value || '';
    let filtered = items.slice();
    if (activeTab === 'quick') filtered = filtered.filter(t => Number(t.is_quick) === 1);
    if (ch) filtered = filtered.filter(t => t.channel === ch || t.channel === 'all');
    if (q) filtered = filtered.filter(t => (t.title + t.body_text).toLowerCase().includes(q));

    if (!filtered.length) {
      listEl.innerHTML = `<div class="bpa-com-card bpa-com-card--pad text-center opacity-70">Niciun template găsit.</div>`;
      return;
    }
    listEl.innerHTML = filtered.map(t => `
      <button type="button" class="bpa-com-tpl-item${selectedId === t.randomn_id ? ' is-selected' : ''}" data-id="${escapeHtml(t.randomn_id)}">
        <div class="bpa-com-tpl-item__tags">
          ${Number(t.is_quick) ? '<span class="bpa-com-pill bpa-com-pill--quick">rapid</span>' : ''}
          <span class="bpa-com-pill bpa-com-pill--cat">${escapeHtml(t.category)}</span>
          ${channelPill(t.channel)}
        </div>
        <div class="font-bold text-sm mb-1">${escapeHtml(t.title)}</div>
        <p class="text-xs opacity-70 line-clamp-2 m-0">${escapeHtml(t.body_text)}</p>
        <div class="text-xs opacity-50 mt-2">Folosit ${escapeHtml(t.use_count || 0)}×</div>
      </button>
    `).join('');
  }

  function renderVariables() {
    varsList.innerHTML = variables.map(v => `
      <button type="button" class="bpa-com-var-chip" data-var="${escapeHtml(v)}" title="Click pentru copiere">{${escapeHtml(v)}}</button>
    `).join('');
  }

  function setTab(tab) {
    activeTab = tab;
    const isVars = tab === 'variables';
    varsPanel.classList.toggle('bpa-com-hidden', !isVars);
    mainLayout.classList.toggle('bpa-com-hidden', isVars);
    document.querySelectorAll('.bpa-com-tab').forEach(btn => {
      btn.classList.toggle('is-active', btn.dataset.tab === tab);
    });
    renderList();
    if (window.lucide) window.lucide.createIcons();
  }

  async function load() {
    const params = new URLSearchParams({ action: 'list' });
    if (activeTab === 'quick') params.set('is_quick', '1');
    const res = await fetch(API + '?' + params);
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    items = json.data?.items || [];
    variables = json.data?.variables || [];
    renderVariables();
    setTab(activeTab);
    if (window.lucide) window.lucide.createIcons();
  }

  async function apiPost(action, payload) {
    const res = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...payload }) });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    return json.data;
  }

  listEl?.addEventListener('click', e => {
    const btn = e.target.closest('.bpa-com-tpl-item');
    if (!btn) return;
    const t = items.find(x => x.randomn_id === btn.dataset.id);
    if (!t) return;
    selectedId = t.randomn_id;
    form.randomn_id.value = t.randomn_id;
    form.title.value = t.title;
    form.category.value = t.category;
    form.channel.value = t.channel;
    form.is_quick.checked = Number(t.is_quick) === 1;
    form.body_text.value = t.body_text;
    document.getElementById('tpl-delete-btn').classList.remove('bpa-com-hidden');
    document.getElementById('tpl-editor-title').innerHTML = '<i data-lucide="edit-3" style="width:16px;height:16px;vertical-align:-2px"></i> Editează: ' + escapeHtml(t.title);
    renderPreview();
    renderList();
    if (window.lucide) window.lucide.createIcons();
  });

  varsList?.addEventListener('click', e => {
    const chip = e.target.closest('.bpa-com-var-chip');
    if (!chip) return;
    const v = '{' + chip.dataset.var + '}';
    navigator.clipboard?.writeText(v);
    chip.style.background = '#99f6e4';
    setTimeout(() => { chip.style.background = ''; }, 400);
  });

  document.getElementById('tpl-new-btn')?.addEventListener('click', () => {
    form.reset();
    selectedId = '';
    form.randomn_id.value = '';
    document.getElementById('tpl-delete-btn').classList.add('bpa-com-hidden');
    document.getElementById('tpl-editor-title').innerHTML = '<i data-lucide="plus" style="width:16px;height:16px;vertical-align:-2px"></i> Template nou';
    renderPreview();
    renderList();
    if (window.lucide) window.lucide.createIcons();
  });

  form?.addEventListener('input', renderPreview);
  form?.addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      title: form.title.value,
      category: form.category.value,
      channel: form.channel.value,
      body_text: form.body_text.value,
      is_quick: form.is_quick.checked ? 1 : 0,
    };
    try {
      if (form.randomn_id.value) {
        await apiPost('update', { randomn_id: form.randomn_id.value, ...payload });
      } else {
        const created = await apiPost('create', payload);
        form.randomn_id.value = created.randomn_id;
        selectedId = created.randomn_id;
        document.getElementById('tpl-delete-btn').classList.remove('bpa-com-hidden');
      }
      await load();
    } catch (err) { alert(err.message); }
  });

  document.getElementById('tpl-delete-btn')?.addEventListener('click', async () => {
    if (!form.randomn_id.value || !confirm('Dezactivezi template-ul?')) return;
    await apiPost('delete', { randomn_id: form.randomn_id.value });
    form.reset();
    selectedId = '';
    await load();
  });

  document.querySelectorAll('.bpa-com-tab').forEach(btn => btn.addEventListener('click', () => setTab(btn.dataset.tab)));
  document.getElementById('tpl-search')?.addEventListener('input', renderList);
  document.getElementById('tpl-filter-channel')?.addEventListener('change', renderList);

  load().catch(e => { listEl.innerHTML = `<div class="text-danger p-4">${escapeHtml(e.message)}</div>`; });
})();
</script>
