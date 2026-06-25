<div>
  <div id="bot-profile-toast" class="hidden fixed right-5 top-5 z-[100000] rounded-md border bg-white px-4 py-3 text-sm shadow"></div>

  <div class="mt-10 flex flex-wrap items-center gap-3">
    <a href="/admin/bots" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm">Inapoi la bots</a>
    <div class="ml-auto flex flex-wrap gap-2">
      <button id="bot-profile-edit" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm text-primary">Edit</button>
      <button id="bot-profile-test" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm text-success">Test Bot</button>
      <button id="bot-profile-toggle" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm">Activeaza/Dezactiveaza</button>
      <button id="bot-profile-delete" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm text-danger">Sterge</button>
    </div>
  </div>

  <section class="mt-6 grid grid-cols-12 gap-5">
    <article class="box col-span-12 rounded-lg border bg-white p-6 xl:col-span-4">
      <div class="flex items-start gap-4">
        <div class="flex size-14 items-center justify-center rounded-lg bg-primary/10 text-primary"><i data-lucide="bot" class="size-7"></i></div>
        <div class="min-w-0">
          <h2 id="bot-profile-name" class="truncate text-xl font-semibold">Bot</h2>
          <p id="bot-profile-type" class="mt-1 text-sm opacity-70">-</p>
        </div>
      </div>
      <div class="mt-6 grid grid-cols-2 gap-3 text-sm">
        <div><div class="text-xs opacity-60">Canal</div><div id="bot-profile-channel" class="font-medium">-</div></div>
        <div><div class="text-xs opacity-60">Plan</div><div id="bot-profile-plan" class="font-medium">-</div></div>
        <div><div class="text-xs opacity-60">Token status</div><div id="bot-profile-token-status" class="font-medium">-</div></div>
        <div><div class="text-xs opacity-60">Status</div><div id="bot-profile-status" class="font-medium">-</div></div>
        <div class="col-span-2"><div class="text-xs opacity-60">Token</div><div id="bot-profile-token" class="break-all font-medium">-</div></div>
      </div>
    </article>

    <article class="box col-span-12 rounded-lg border bg-white p-6 xl:col-span-8">
      <h3 class="text-base font-medium">Descriere</h3>
      <p id="bot-profile-notes" class="mt-3 whitespace-pre-wrap text-sm leading-6 opacity-80">-</p>
      <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-lg border p-4"><div class="text-xs opacity-60">Perioada start</div><div id="bot-profile-starts" class="mt-1 font-medium">-</div></div>
        <div class="rounded-lg border p-4"><div class="text-xs opacity-60">Perioada sfarsit</div><div id="bot-profile-ends" class="mt-1 font-medium">-</div></div>
        <div class="rounded-lg border p-4"><div class="text-xs opacity-60">Requests</div><div id="bot-profile-requests" class="mt-1 font-medium">-</div></div>
        <div class="rounded-lg border p-4"><div class="text-xs opacity-60">Ultim test</div><div id="bot-profile-last-test" class="mt-1 font-medium">-</div></div>
        <div class="rounded-lg border p-4 md:col-span-2"><div class="text-xs opacity-60">Webhook URL</div><div id="bot-profile-webhook" class="mt-1 break-all font-medium">-</div></div>
        <div class="rounded-lg border p-4 md:col-span-2"><div class="text-xs opacity-60">Test URL</div><div id="bot-profile-test-url" class="mt-1 break-all font-medium">-</div></div>
      </div>
    </article>
  </section>

  <section class="mt-5">
    <article class="box rounded-lg border bg-white p-6">
      <div class="flex flex-wrap items-center gap-3">
        <div>
          <h3 class="text-base font-medium">Workspace bot</h3>
          <p id="bot-workbench-desc" class="mt-1 text-sm opacity-70">Incarcare workspace...</p>
        </div>
        <span id="bot-workbench-channel-badge" class="ml-auto rounded-full border px-3 py-1 text-xs">-</span>
        <a id="bot-workbench-open" href="#" target="_blank" rel="noopener" class="box inline-flex h-9 items-center rounded-lg border px-3 text-sm text-primary">
          Deschide separat
        </a>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        <a id="bot-workbench-link-main" href="#" target="_blank" rel="noopener" class="box inline-flex h-10 items-center justify-center rounded-lg border px-3 text-sm">Tool principal</a>
        <a id="bot-workbench-link-webhook" href="#" target="_blank" rel="noopener" class="box inline-flex h-10 items-center justify-center rounded-lg border px-3 text-sm">Webhook URL</a>
        <a id="bot-workbench-link-test" href="#" target="_blank" rel="noopener" class="box inline-flex h-10 items-center justify-center rounded-lg border px-3 text-sm">Test URL</a>
      </div>

      <div id="bot-workbench-iframe-wrap" class="mt-4 box rounded-lg border bg-background overflow-hidden" style="height: calc(100vh - 320px); min-height: 520px;">
        <iframe id="bot-workbench-frame" src="" style="width:100%;height:100%;border:0;display:block;" title="Workspace bot"></iframe>
      </div>

      <div id="bot-workbench-empty" class="hidden mt-4 rounded-lg border border-dashed p-6 text-sm opacity-80">
        Pentru acest canal nu exista inca un tool intern dedicat in proiect.
        Configureaza URL-urile botului (Webhook / Test URL) si foloseste butoanele de mai sus.
      </div>
    </article>
  </section>

  <div id="bots-modal" class="hidden fixed inset-0 bg-black/40" style="z-index:99999;overflow-y:auto;padding:16px;">
    <div class="mx-auto w-full max-w-4xl rounded-lg bg-white shadow-xl" style="background:#fff;max-height:calc(100vh - 32px);overflow-y:auto;">
      <div class="mb-5 flex items-center border-b p-6 pb-4">
        <h3 class="text-base font-medium">Editeaza bot</h3>
        <button type="button" id="bots-close-modal" class="ml-auto rounded border px-3 py-2">Inchide</button>
      </div>
      <form id="bots-form" data-action="edit" style="padding:24px;">
        <input type="hidden" name="randomn_id">
        <div class="grid grid-cols-12 gap-4">
          <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Nume bot</span><input class="box h-10 w-full rounded-md border px-3" name="name" required maxlength="255"></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Tip</span><select class="box h-10 w-full rounded-md border px-3" name="bot_type"><option value="message_sender">Message sender</option><option value="scraper">Scraper</option><option value="sync">Sync</option><option value="ai_reply">AI reply</option><option value="notification">Notification</option></select></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Canal</span><select class="box h-10 w-full rounded-md border px-3" name="channel"><option value="whatsapp">WhatsApp</option><option value="olx">OLX</option><option value="pieseauto">PieseAuto.ro</option><option value="dezro">dez.ro</option><option value="facebook">Facebook</option><option value="email">Email</option><option value="website">Website</option><option value="manual">Manual</option></select></label>
          <label class="col-span-12"><span class="mb-1 block text-sm">Token</span><textarea class="box min-h-20 w-full rounded-md border px-3 py-2" name="token_value"></textarea></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Status token</span><select class="box h-10 w-full rounded-md border px-3" name="token_status"><option value="active">Active</option><option value="expired">Expired</option><option value="disabled">Disabled</option></select></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Plan</span><select class="box h-10 w-full rounded-md border px-3" name="token_plan"><option value="free">Free</option><option value="paid">Paid</option></select></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Incepe la</span><input class="box h-10 w-full rounded-md border px-3" type="datetime-local" name="starts_at"></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Sfarsit la</span><input class="box h-10 w-full rounded-md border px-3" type="datetime-local" name="ends_at"></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Limita request</span><input class="box h-10 w-full rounded-md border px-3" type="number" min="0" name="requests_limit"></label>
          <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Request folosite</span><input class="box h-10 w-full rounded-md border px-3" type="number" min="0" name="requests_used"></label>
          <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Webhook URL</span><input class="box h-10 w-full rounded-md border px-3" type="url" name="webhook_url"></label>
          <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Test URL</span><input class="box h-10 w-full rounded-md border px-3" type="url" name="test_url"></label>
          <label class="col-span-12"><span class="mb-1 block text-sm">Descriere / note</span><textarea class="box min-h-20 w-full rounded-md border px-3 py-2" name="notes"></textarea></label>
        </div>
        <div class="mt-5 flex justify-end gap-2 border-t bg-white pt-4" style="position:sticky;bottom:0;z-index:2;">
          <button type="button" id="bots-cancel" class="box rounded-lg border px-4 py-2">Anuleaza</button>
          <button type="submit" class="box rounded-lg border bg-primary px-4 py-2 text-white">Salveaza</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const ENDPOINT = '/admin/api/bots_endpoint.php';
  const params = new URLSearchParams(window.location.search);
  const randomId = Number(params.get('id') || 0);
  const form = document.getElementById('bots-form');
  const modal = document.getElementById('bots-modal');
  const toast = document.getElementById('bot-profile-toast');
  let bot = null;

  async function apiCall(action, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: action, ...payload })
    });
    const raw = await response.text();
    let result;
    try {
      result = JSON.parse(raw);
    } catch (error) {
      throw new Error('Endpoint-ul nu a returnat JSON valid.');
    }
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare necunoscuta');
    return result.data;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[character]));
  }

  function text(id, value) {
    const element = document.getElementById(id);
    if (element) element.innerHTML = escapeHtml(value || '-');
  }

  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('hidden');
    toast.classList.toggle('text-danger', Boolean(isError));
    setTimeout(() => toast.classList.add('hidden'), 3000);
  }

  function maskToken(token) {
    if (!token) return '-';
    const value = String(token);
    return value.length <= 12 ? '************' : `${value.slice(0, 6)}...${value.slice(-6)}`;
  }

  function formToObject(formElement) {
    const payload = {};
    new FormData(formElement).forEach((value, key) => {
      if (String(value).trim() !== '') payload[key] = value;
    });
    return payload;
  }

  function fillForm() {
    form.reset();
    Object.entries(bot || {}).forEach(([key, value]) => {
      const field = form.elements.namedItem(key);
      if (field) field.value = value ?? '';
    });
  }

  function render() {
    text('bot-profile-name', bot.name || 'Bot');
    text('bot-profile-type', bot.bot_type || '-');
    text('bot-profile-channel', bot.channel || '-');
    text('bot-profile-plan', bot.token_plan || '-');
    text('bot-profile-token-status', bot.token_status || '-');
    text('bot-profile-status', bot.status || '-');
    text('bot-profile-token', maskToken(bot.token_value));
    text('bot-profile-notes', bot.notes || 'Fara descriere.');
    text('bot-profile-starts', bot.starts_at || '-');
    text('bot-profile-ends', bot.ends_at || '-');
    text('bot-profile-requests', `${bot.requests_used || 0} / ${bot.requests_limit || 'nelimitat'}`);
    text('bot-profile-last-test', `${bot.last_test_status || '-'} ${bot.last_test_message ? '- ' + bot.last_test_message : ''}`);
    text('bot-profile-webhook', bot.webhook_url || '-');
    text('bot-profile-test-url', bot.test_url || '-');
    document.getElementById('bot-profile-toggle').textContent = bot.token_status === 'active' ? 'Dezactiveaza' : 'Activeaza';
    renderWorkbench();
    if (window.lucide) window.lucide.createIcons();
  }

  function isInternalTool(url) {
    return typeof url === 'string' && (url.startsWith('/robot/') || url.startsWith('/admin/'));
  }

  function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) return url;
    return `https://${url}`;
  }

  function workspaceForBot(currentBot) {
    const channel = String(currentBot.channel || '').toLowerCase();
    const botType = String(currentBot.bot_type || '').toLowerCase();

    if (channel === 'whatsapp') {
      return {
        label: 'WhatsApp Manager',
        description: 'Conversații WhatsApp + răspunsuri automate din /robot/chat.php',
        url: '/robot/chat.php'
      };
    }
    if (channel === 'pieseauto') {
      return {
        label: 'Pieseauto Scanner',
        description: 'Cereri noi + analiza concurență + verificare stoc din /robot/parser_view.php',
        url: '/robot/parser_view.php'
      };
    }
    if (channel === 'facebook') {
      return {
        label: 'Facebook Scanner',
        description: 'Scraping grupuri FB + generare răspuns AI din /robot/fb_view_protected.php',
        url: '/robot/fb_view_protected.php'
      };
    }
    if (channel === 'website' || channel === 'email') {
      return {
        label: 'Webhook & Log',
        description: 'Monitorizare webhook și log intern din /admin/bots?tab=webhook',
        url: '/admin/bots?tab=webhook'
      };
    }
    if (channel === 'manual' || botType === 'scraper') {
      return {
        label: 'Tab de operare',
        description: 'Rulează manual tool-urile din /admin/bots (tabs dedicate).',
        url: '/admin/bots'
      };
    }

    return {
      label: 'Fără workspace dedicat',
      description: 'Canalul nu are încă mapare către un tool intern.',
      url: ''
    };
  }

  function setActionLink(id, url) {
    const element = document.getElementById(id);
    if (!element) return;
    const normalized = normalizeExternalUrl(url);
    if (!normalized) {
      element.classList.add('opacity-50', 'pointer-events-none');
      element.setAttribute('href', '#');
      return;
    }
    element.classList.remove('opacity-50', 'pointer-events-none');
    element.setAttribute('href', normalized);
  }

  function renderWorkbench() {
    const workbench = workspaceForBot(bot || {});
    const badge = document.getElementById('bot-workbench-channel-badge');
    const desc = document.getElementById('bot-workbench-desc');
    const open = document.getElementById('bot-workbench-open');
    const frameWrap = document.getElementById('bot-workbench-iframe-wrap');
    const frame = document.getElementById('bot-workbench-frame');
    const empty = document.getElementById('bot-workbench-empty');

    if (desc) desc.textContent = workbench.description || '-';
    if (badge) badge.textContent = `${bot.channel || '-'} / ${bot.bot_type || '-'}`;

    const mainUrl = normalizeExternalUrl(workbench.url);
    const webhookUrl = normalizeExternalUrl(bot.webhook_url);
    const testUrl = normalizeExternalUrl(bot.test_url);

    if (open) {
      if (mainUrl) {
        open.classList.remove('opacity-50', 'pointer-events-none');
        open.setAttribute('href', mainUrl);
      } else {
        open.classList.add('opacity-50', 'pointer-events-none');
        open.setAttribute('href', '#');
      }
    }

    setActionLink('bot-workbench-link-main', mainUrl);
    setActionLink('bot-workbench-link-webhook', webhookUrl);
    setActionLink('bot-workbench-link-test', testUrl);

    if (isInternalTool(mainUrl)) {
      frameWrap?.classList.remove('hidden');
      empty?.classList.add('hidden');
      if (frame) frame.src = mainUrl;
    } else {
      frameWrap?.classList.add('hidden');
      empty?.classList.remove('hidden');
      if (frame) frame.src = '';
    }
  }

  async function load() {
    if (!randomId) throw new Error('Lipseste id-ul botului.');
    bot = await apiCall('get', { randomn_id: randomId });
    render();
  }

  document.getElementById('bot-profile-edit')?.addEventListener('click', () => {
    fillForm();
    modal.classList.remove('hidden');
  });
  document.getElementById('bots-close-modal')?.addEventListener('click', () => modal.classList.add('hidden'));
  document.getElementById('bots-cancel')?.addEventListener('click', () => modal.classList.add('hidden'));

  document.getElementById('bot-profile-test')?.addEventListener('click', async () => {
    try {
      const result = await apiCall('testbot', { randomn_id: randomId });
      showToast(`Test: ${result.last_test_status} - ${result.last_test_message}`, result.last_test_status !== 'success');
      await load();
    } catch (error) {
      showToast(error.message, true);
    }
  });

  document.getElementById('bot-profile-toggle')?.addEventListener('click', async () => {
    try {
      const nextStatus = bot.token_status === 'active' ? 'disabled' : 'active';
      await apiCall('setstatus', { randomn_id: randomId, token_status: nextStatus });
      showToast('Status actualizat.', false);
      await load();
    } catch (error) {
      showToast(error.message, true);
    }
  });

  document.getElementById('bot-profile-delete')?.addEventListener('click', async () => {
    if (!confirm('Confirmi stergerea botului?')) return;
    try {
      await apiCall('delete', { randomn_id: randomId });
      window.location.href = '/admin/bots';
    } catch (error) {
      showToast(error.message, true);
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall('edit', formToObject(form));
      modal.classList.add('hidden');
      showToast('Bot salvat.', false);
      await load();
    } catch (error) {
      showToast(error.message, true);
    }
  });

  load().catch((error) => showToast(error.message, true));
})();
</script>
