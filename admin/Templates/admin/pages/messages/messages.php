<div class="bpa-msg mt-6" id="messages-app">
    <div id="messages-toast" class="bpa-msg-toast bpa-com-hidden" role="status"></div>

    <div class="bpa-msg-layout">
        <aside class="bpa-msg-sidebar">
            <div class="bpa-msg-sidebar__head">
                <div class="flex items-center gap-2">
                    <h2>Mesagerie</h2>
                    <button id="messages-new-conversation" type="button" class="bpa-com-btn bpa-com-btn--primary ml-auto" style="padding:0.4rem 0.75rem;font-size:0.72rem">
                        <i data-lucide="plus" style="width:14px;height:14px"></i>
                        Nou
                    </button>
                </div>
                <div class="bpa-msg-search">
                    <input id="messages-search" type="text" placeholder="Caută client sau mesaj...">
                    <i data-lucide="search"></i>
                </div>
            </div>
            <div id="messages-conversations" class="bpa-msg-conv-list"></div>
            <div id="messages-pagination" class="p-3 border-t"></div>
        </aside>

        <section class="bpa-msg-main">
            <div class="bpa-msg-main__head">
                <div class="bpa-msg-avatar" id="messages-active-avatar">?</div>
                <div class="min-w-0 flex-1">
                    <div id="messages-active-name" class="font-bold text-sm truncate">Selectează o conversație</div>
                    <div id="messages-active-status" class="text-xs opacity-60 truncate">—</div>
                </div>
                <a href="/admin/reply-templates" class="bpa-com-btn bpa-com-btn--outline" style="padding:0.4rem 0.7rem;font-size:0.72rem" title="Template-uri">
                    <i data-lucide="file-text" style="width:14px;height:14px"></i>
                </a>
            </div>

            <div id="messages-thread" class="bpa-msg-thread">
                <div class="bpa-msg-empty">
                    <i data-lucide="messages-square"></i>
                    <p>Alege o conversație din stânga sau creează una nouă.</p>
                </div>
            </div>

            <form id="messages-form" class="bpa-msg-compose">
                <div id="messages-template-bar" class="bpa-msg-templates"></div>
                <div class="bpa-msg-compose__row">
                    <input type="hidden" name="conversation_id">
                    <input type="hidden" name="name">
                    <input type="hidden" name="phone">
                    <input type="hidden" name="email">
                    <input name="message_body" type="text" placeholder="Scrie mesajul tău...">
                    <button type="submit" class="bpa-msg-send" aria-label="Trimite">
                        <i data-lucide="send" style="width:18px;height:18px"></i>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <div id="messages-modal" class="bpa-msg-modal bpa-com-hidden">
        <div class="bpa-msg-modal__panel">
            <div class="bpa-msg-modal__head">
                <h3>Conversație nouă</h3>
                <button type="button" id="messages-close-modal" class="bpa-com-btn bpa-com-btn--outline ml-auto" style="padding:0.35rem 0.6rem">
                    <i data-lucide="x" style="width:16px;height:16px"></i>
                </button>
            </div>
            <form id="messages-new-form" class="bpa-msg-modal__body">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">Client</span>
                        <input class="bpa-com-input" type="text" name="name" required maxlength="255">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">Canal</span>
                        <select class="bpa-com-select" name="channel">
                            <option value="manual">Manual</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="olx">OLX</option>
                            <option value="pieseauto">PieseAuto.ro</option>
                            <option value="dezro">dez.ro</option>
                            <option value="facebook">Facebook</option>
                            <option value="website">Website</option>
                            <option value="email">Email</option>
                        </select>
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">Telefon</span>
                        <input class="bpa-com-input" type="tel" name="phone" maxlength="50">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">Email</span>
                        <input class="bpa-com-input" type="email" name="email" maxlength="255">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">Subiect</span>
                        <input class="bpa-com-input" type="text" name="subject" maxlength="160">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="text-xs font-bold block mb-1">ID conversație externă</span>
                        <input class="bpa-com-input" type="text" name="external_conversation_id" maxlength="190">
                    </label>
                    <label class="col-span-12">
                        <span class="text-xs font-bold block mb-1">Mesaj</span>
                        <textarea class="bpa-com-textarea" name="message_body" rows="4" required></textarea>
                    </label>
                </div>
                <div class="bpa-msg-modal__foot">
                    <button type="button" id="messages-cancel-modal" class="bpa-com-btn bpa-com-btn--outline">Anulează</button>
                    <button type="submit" class="bpa-com-btn bpa-com-btn--primary">Trimite</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/messages_endpoint.php';
  const conversationsEl = document.getElementById('messages-conversations');
  const paginationEl = document.getElementById('messages-pagination');
  const threadEl = document.getElementById('messages-thread');
  const searchEl = document.getElementById('messages-search');
  const form = document.getElementById('messages-form');
  const newForm = document.getElementById('messages-new-form');
  const modal = document.getElementById('messages-modal');
  const toast = document.getElementById('messages-toast');
  let conversations = [];
  let threadMessages = [];
  let activeConversationId = null;
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;
  let replyTemplates = [];

  async function loadReplyTemplates() {
    try {
      const res = await fetch('/admin/api/comunicare_endpoint.php?action=list');
      const json = await res.json();
      if (json.success) replyTemplates = json.data?.items || [];
      renderTemplateBar();
    } catch (e) {}
  }

  function renderTemplateBar() {
    const bar = document.getElementById('messages-template-bar');
    if (!bar) return;
    const quick = replyTemplates.filter(t => Number(t.is_quick) === 1).slice(0, 8);
    const rest = replyTemplates.filter(t => Number(t.is_quick) !== 1).slice(0, 4);
    const show = [...quick, ...rest];
    if (!show.length) {
      bar.innerHTML = '<span class="text-xs opacity-50">Adaugă template-uri în Comunicare → Template-uri</span>';
      return;
    }
    bar.innerHTML = show.map(t => `<button type="button" class="bpa-msg-tpl-btn msg-tpl-pick" data-id="${escapeHtml(t.randomn_id)}">${escapeHtml(t.title)}</button>`).join('');
  }

  async function applyTemplateToInput(randomnId) {
    const latest = threadMessages[threadMessages.length - 1] || {};
    const res = await fetch('/admin/api/comunicare_endpoint.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'apply', randomn_id: randomnId,
        variables: {
          client_name: latest.name || form.elements.name.value || '',
          phone: latest.phone || '',
          email: latest.email || '',
          shop_url: 'https://besoiupieseauto.ro',
        },
      }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    form.elements.message_body.value = json.data?.rendered_text || '';
  }

  document.getElementById('messages-template-bar')?.addEventListener('click', (event) => {
    const btn = event.target.closest('.msg-tpl-pick');
    if (!btn) return;
    applyTemplateToInput(btn.dataset.id).catch(e => showToast(e.message, true));
  });

  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type_product: actionType, ...payload }) });
    const rawText = await response.text();
    let result;
    try { result = JSON.parse(rawText); } catch (error) { throw new Error('Endpoint-ul nu a returnat JSON valid.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare necunoscută');
    return result.data;
  }

  function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('bpa-com-hidden');
    toast.classList.toggle('is-error', Boolean(isError));
    setTimeout(() => toast.classList.add('bpa-com-hidden'), 3200);
  }
  function formToObject(formElement) { const payload = {}; new FormData(formElement).forEach((value, key) => { if (String(value).trim() !== '') payload[key] = value; }); return payload; }
  function conversationId(message) { return Number(message.conversation_id || message.randomn_id); }
  function channelLabel(channel) { return ({ whatsapp:'WhatsApp', olx:'OLX', pieseauto:'PieseAuto.ro', dezro:'dez.ro', facebook:'Facebook', website:'Website', email:'Email', manual:'Manual' })[channel] || channel || 'Manual'; }
  function channelClass(channel) {
    const map = { whatsapp:'whatsapp', olx:'olx', facebook:'facebook', website:'website', email:'email' };
    return 'bpa-msg-channel--' + (map[channel] || 'manual');
  }
  function initials(name) {
    const p = String(name || 'C').trim().split(/\s+/);
    return (p[0]?.[0] || 'C').toUpperCase() + (p[1]?.[0] || '').toUpperCase();
  }

  function renderConversations() {
    if (!conversationsEl) return;
    if (!conversations.length) {
      conversationsEl.innerHTML = '<div class="bpa-msg-empty" style="padding:2rem 1rem"><p>Nu există mesaje.</p></div>';
      if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadConversations(p));
      return;
    }
    conversationsEl.innerHTML = conversations.map((latest) => {
      const id = conversationId(latest);
      const active = Number(activeConversationId) === id ? ' is-active' : '';
      const ch = latest.channel || 'manual';
      return `<button type="button" data-conversation-id="${escapeHtml(id)}" class="bpa-msg-conv${active}">
        <span class="bpa-msg-avatar">${escapeHtml(initials(latest.name))}</span>
        <span class="bpa-msg-conv__meta">
          <span class="bpa-msg-conv__top">
            <span class="bpa-msg-conv__name">${escapeHtml(latest.name || 'Client')}</span>
            <span class="bpa-msg-channel ${channelClass(ch)}">${escapeHtml(channelLabel(ch))}</span>
          </span>
          <span class="bpa-msg-conv__preview">${escapeHtml(latest.message_body || '')}</span>
        </span>
        <span class="text-xs opacity-40 flex-shrink-0">${escapeHtml(String(latest.created_at || '').slice(11, 16))}</span>
      </button>`;
    }).join('');
    if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadConversations(p));
  }

  function renderThread() {
    const items = threadMessages.slice().sort((a, b) => Number(a.id || 0) - Number(b.id || 0));
    if (!items.length) {
      threadEl.innerHTML = `<div class="bpa-msg-empty"><i data-lucide="messages-square"></i><p>Selectează o conversație.</p></div>`;
      if (window.lucide) window.lucide.createIcons();
      return;
    }
    const latest = items[items.length - 1];
    document.getElementById('messages-active-name').textContent = `${latest.name || 'Client'}`;
    document.getElementById('messages-active-status').textContent = `${channelLabel(latest.channel)} · ${latest.phone || latest.email || '—'}`;
    document.getElementById('messages-active-avatar').textContent = initials(latest.name);
    form.elements.conversation_id.value = activeConversationId;
    form.elements.name.value = latest.name || '';
    form.elements.phone.value = latest.phone || '';
    form.elements.email.value = latest.email || '';
    threadEl.innerHTML = items.map((message) => {
      const out = message.direction === 'outbound';
      return `<div class="bpa-msg-row${out ? ' bpa-msg-row--out' : ''}">
        <div class="bpa-msg-bubble${out ? ' bpa-msg-bubble--out' : ' bpa-msg-bubble--in'}">
          ${escapeHtml(message.message_body || '')}
          <div class="bpa-msg-bubble__time">${escapeHtml(String(message.created_at || '').slice(0, 16))} · ${escapeHtml(message.delivery_status || '')}</div>
        </div>
      </div>`;
    }).join('');
    threadEl.scrollTop = threadEl.scrollHeight;
    if (window.lucide) window.lucide.createIcons();
  }

  async function loadConversations(page) {
    if (page) currentPage = page;
    const data = await apiCall('conversations', { page: currentPage, per_page: 10, q: (searchEl?.value || '').trim() });
    const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
    conversations = parsed.items;
    listMeta = parsed;
    currentPage = parsed.page;
    renderConversations();
  }

  async function loadThread(conversationId) {
    activeConversationId = Number(conversationId);
    threadMessages = await apiCall('conversation', { conversation_id: activeConversationId });
    renderConversations();
    renderThread();
    const first = threadMessages[threadMessages.length - 1];
    if (first?.randomn_id) {
      try { await apiCall('markread', { randomn_id: Number(first.randomn_id) }); } catch (error) {}
    }
  }

  function openModal() { newForm.reset(); modal.classList.remove('bpa-com-hidden'); }
  function closeModal() { modal.classList.add('bpa-com-hidden'); }

  conversationsEl?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-conversation-id]');
    if (!button) return;
    loadThread(Number(button.dataset.conversationId)).catch((error) => showToast(error.message, true));
  });

  let searchTimer;
  searchEl?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadConversations().catch((e) => showToast(e.message, true)); }, 300);
  });

  document.getElementById('messages-new-conversation')?.addEventListener('click', openModal);
  document.getElementById('messages-close-modal')?.addEventListener('click', closeModal);
  document.getElementById('messages-cancel-modal')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const payload = formToObject(form);
      if (!payload.conversation_id) throw new Error('Selectează o conversație.');
      const latest = threadMessages[threadMessages.length - 1] || conversations.find((c) => conversationId(c) === Number(payload.conversation_id));
      await apiCall('add', { ...payload, channel: latest?.channel || 'manual', external_conversation_id: latest?.external_conversation_id || '', assigned_bot: latest?.assigned_bot || '', direction: 'outbound', message_status: 'queued', delivery_status: 'queued', bot_status: 'pending', is_read: 1 });
      form.elements.message_body.value = '';
      await loadThread(activeConversationId);
      await loadConversations(currentPage);
    } catch (error) { showToast(error.message, true); }
  });

  newForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const created = await apiCall('add', { ...formToObject(newForm), direction: 'outbound', message_status: 'queued', delivery_status: 'queued', bot_status: 'pending', is_read: 1 });
      closeModal();
      activeConversationId = created.conversation_id;
      await loadConversations(1);
      await loadThread(activeConversationId);
      showToast('Mesaj pus în coadă pentru robot.', false);
    } catch (error) { showToast(error.message, true); }
  });

  loadConversations().catch((error) => showToast(error.message, true));
  loadReplyTemplates();
  if (window.lucide) window.lucide.createIcons();
})();
</script>
