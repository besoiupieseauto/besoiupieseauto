<?php
/**
 * Templates/admin/pages/bots/bots.php
 *
 * Pagina UNICA de gestionare a botilor + tool-urile portate din /robot/.
 * Tabs:
 *   1. Configurare boti  — CRUD boti din DB (existent, neatins)
 *   2. WhatsApp Manager  — iframe /robot/chat.php
 *   3. Scanner pieseauto — iframe /robot/parser_view.php
 *   4. Selector TecDoc   — iframe /robot/tecdoc.php
 *   5. VIN Allegro       — iframe /robot/vin.php
 *   6. Facebook Scanner  — iframe /robot/fb_view_protected.php
 *   7. Lead-uri          — citeste robot/data/leads.json (nativ)
 *   8. Webhook & Log     — citeste robot/data/webhook.log + status (nativ)
 *
 * Iframe-urile au lazy-load (data-src) — se incarca doar cand tab-ul devine activ.
 */

require_once __DIR__ . '/../../../../../robot/bootstrap.php';

/* ---------- Status chei .env ---------- */
$envKeys = [
    'ULTRAMSG_INSTANCE'      => 'WhatsApp UltraMsg instance',
    'ULTRAMSG_TOKEN'         => 'WhatsApp UltraMsg token',
    'WEBHOOK_KEY'            => 'Cheia validare webhook',
    'OPENAI_KEY'             => 'OpenAI (general)',
    'OPENAI_KEY_VIN'         => 'OpenAI (vin.php)',
    'GROQ_KEY'               => 'Groq AI',
    'APIFY_TOKEN'            => 'Apify (Facebook)',
    'RAPIDAPI_TECDOC_KEY'    => 'RapidAPI tecdoc + allegro2',
    'RAPIDAPI_AUTOPARTS_KEY' => 'RapidAPI auto-parts-catalog',
];
$envStatus = [];
foreach ($envKeys as $k => $label) {
    $v = env($k, '');
    $envStatus[$k] = ['label' => $label, 'ok' => $v !== '', 'len' => strlen((string)$v)];
}

/* ---------- Webhook URL pentru UltraMsg ---------- */
$webhookKey = (string) env('WEBHOOK_KEY', '');
$instance   = (string) env('ULTRAMSG_INSTANCE', '');
$webhookUrl = \Evasystem\Core\AdminUrl::publicSiteUrl('/robot/webhook.php?key=' . urlencode($webhookKey));

/* ---------- Leads din robot/data/leads.json ---------- */
$leadsFile = __DIR__ . '/../../../../../robot/data/leads.json';
$leads = [];
if (is_file($leadsFile)) {
    $raw = @file_get_contents($leadsFile);
    $j   = json_decode((string)$raw, true);
    if (is_array($j)) $leads = array_reverse($j);
}

/* ---------- Webhook log ---------- */
$logFile = __DIR__ . '/../../../../../robot/data/webhook.log';
$logLines = [];
$logSize  = 0;
$logMtime = '';
if (is_file($logFile)) {
    $logSize  = filesize($logFile);
    $logMtime = date('Y-m-d H:i:s', filemtime($logFile));
    $all = @file($logFile, FILE_IGNORE_NEW_LINES);
    if (is_array($all)) $logLines = array_slice($all, -300);
}

/* ---------- KPI dedup + sesiuni ---------- */
$dedupFile  = __DIR__ . '/../../../../../robot/data/dedup.json';
$dedupCount = 0;
if (is_file($dedupFile)) {
    $j = json_decode((string)file_get_contents($dedupFile), true);
    if (is_array($j)) $dedupCount = count($j);
}
$sessionsDir   = __DIR__ . '/../../../../../robot/data/sessions';
$sessionsCount = is_dir($sessionsDir) ? count(glob($sessionsDir . '/*.json') ?: []) : 0;

function bots_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<div>
  <div id="bots-toast" class="hidden fixed right-5 top-5 z-[100000] rounded-md border bg-white px-4 py-3 text-sm shadow"></div>

  <div class="mt-8 flex flex-wrap items-start justify-between gap-3">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(99,102,241,.35);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4M8 15h.01M16 15h.01"/></svg>
      </div>
      <div>
        <h2 style="font-size:1.2rem;font-weight:800;letter-spacing:-.02em;margin:0;">Boti & Roboti</h2>
        <p style="font-size:.8rem;opacity:.6;margin:2px 0 0;">Configurare boti + tool-uri din <code style="font-size:.75rem;">aibotpiese.online</code>. Selecteaza un tab.</p>
      </div>
    </div>
    <a href="/robot/" target="_blank" style="display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 16px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;font-size:.8rem;font-weight:600;color:#475569;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#6366f1';this.style.color='#6366f1';" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#475569';">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      /robot/ direct
    </a>
  </div>

  <!-- ============ TAB NAV ============ -->
  <div class="mt-5 flex flex-wrap gap-1 border-b" id="bots-tabs-nav" role="tablist">
    <button type="button" data-tab="config"   class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="database" class="size-4"></i> Configurare boti
    </button>
    <button type="button" data-tab="whatsapp" class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="message-circle" class="size-4"></i> WhatsApp
    </button>
    <button type="button" data-tab="parser"   class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="search" class="size-4"></i> Pieseauto.ro
    </button>
    <button type="button" data-tab="facebook" class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="facebook" class="size-4"></i> Facebook
    </button>
    <button type="button" data-tab="leads"    class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="user-plus" class="size-4"></i> Lead-uri <span class="ml-1 rounded-full bg-primary/10 px-2 text-xs text-primary"><?= count($leads) ?></span>
    </button>
    <button type="button" data-tab="webhook"  class="bots-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">
      <i data-lucide="webhook" class="size-4"></i> Webhook & Log
    </button>
  </div>

  <!-- ============ TAB: CONFIGURARE BOTI (CRUD existent) ============ -->
  <div data-tab-pane="config" class="bots-pane mt-5">
    <div class="bots-filter-bar">
      <div style="position:relative;flex:1;min-width:200px;max-width:320px;">
        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);opacity:.4;pointer-events:none;" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input id="bots-search" style="padding-left:32px;width:100%;" type="text" placeholder="Cauta bot, canal, token...">
      </div>
      <select id="bots-channel-filter">
        <option value="">Toate canalele</option>
        <option value="whatsapp">📱 WhatsApp</option>
        <option value="olx">🏪 OLX</option>
        <option value="pieseauto">🚗 PieseAuto.ro</option>
        <option value="dezro">📄 Dez.ro</option>
        <option value="facebook">👤 Facebook</option>
        <option value="email">✉️ Email</option>
        <option value="website">🌐 Website</option>
        <option value="manual">⚙️ Manual</option>
      </select>
      <select id="bots-plan-filter">
        <option value="">Toate planurile</option>
        <option value="free">Free</option>
        <option value="paid">⭐ Paid</option>
      </select>
      <select id="bots-status-filter">
        <option value="">Toate statusurile</option>
        <option value="active">✅ Active</option>
        <option value="expired">⚠️ Expired</option>
        <option value="disabled">❌ Disabled</option>
      </select>
      <button id="bots-open-create" type="button" style="margin-left:auto;height:38px;display:inline-flex;align-items:center;gap:7px;padding:0 18px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;font-size:.83rem;font-weight:700;cursor:pointer;box-shadow:0 2px 8px rgba(99,102,241,.35);transition:all .15s ease;" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(99,102,241,.45)';" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(99,102,241,.35)';">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Adauga bot
      </button>
    </div>

    <div id="bots-grid" class="mt-6 grid gap-5" style="grid-template-columns:repeat(auto-fill,minmax(285px,1fr));"></div>
    <div id="bots-pagination" class="mt-4"></div>

    <div id="bots-modal" class="hidden fixed inset-0 bg-black/40" style="z-index:99999;overflow-y:auto;padding:16px;">
      <div class="mx-auto w-full max-w-4xl rounded-lg bg-white shadow-xl" style="background:#fff;max-height:calc(100vh - 32px);overflow-y:auto;">
        <div class="mb-5 flex items-center border-b p-6 pb-4">
          <h3 id="bots-modal-title" class="text-base font-medium">Bot nou</h3>
          <button type="button" id="bots-close-modal" class="ml-auto rounded border px-3 py-2">Inchide</button>
        </div>
        <form id="bots-form" data-action="add" style="padding:24px;">
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
            <label class="col-span-12 md:col-span-3"><span class="mb-1 block text-sm">Request folosite</span><input class="box h-10 w-full rounded-md border px-3" type="number" min="0" name="requests_used" value="0"></label>
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

  <!-- ============ TAB: WHATSAPP (UI NATIV) ============ -->
  <div data-tab-pane="whatsapp" class="bots-pane mt-5 hidden">

    <!-- Header sectiune -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#25d366,#128c5e);display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.555 4.122 1.524 5.855L0 24l6.29-1.501A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.846 0-3.574-.5-5.065-1.373l-.363-.214-3.736.892.942-3.636-.236-.374A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
        </div>
        <div>
          <h3 style="font-size:.95rem;font-weight:700;margin:0;">Manager Conversatii WhatsApp</h3>
          <p style="font-size:.75rem;opacity:.55;margin:0;">Via UltraMsg · backend: <code style="font-size:.7rem;">/robot/chat.php</code></p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span id="wa-status-dot" style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>
        <span id="wa-status-txt" style="font-size:.75rem;color:#64748b;">Incarcare...</span>
        <button onclick="waLoadGroups()" style="height:34px;padding:0 12px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
          Reincarca
        </button>
        <button onclick="waNewContact()" style="height:34px;padding:0 14px;border-radius:8px;background:linear-gradient(135deg,#25d366,#128c5e);color:#fff;border:none;font-size:.78rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
          Contact nou
        </button>
      </div>
    </div>

    <div class="box rounded-xl border bg-white shadow-sm overflow-hidden">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:2px solid #f1f5f9;">
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Telefon</th>
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Ultimul mesaj</th>
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Ora</th>
            <th style="padding:12px 16px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Actiune</th>
          </tr>
        </thead>
        <tbody id="wa-table-body">
          <tr><td colspan="4" style="padding:48px;text-align:center;color:#94a3b8;font-size:.85rem;">Se incarca conversatiile...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- CHAT DRAWER -->
    <div id="wa-drawer" style="display:none;position:fixed;top:0;right:0;width:420px;height:100vh;background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,.12);z-index:99998;display:none;flex-direction:column;">
      <div style="padding:16px 20px;background:linear-gradient(135deg,#25d366,#128c5e);display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div style="flex:1;min-width:0;">
          <div id="wa-drawer-phone" style="color:#fff;font-weight:700;font-size:.95rem;"></div>
          <div style="color:rgba(255,255,255,.75);font-size:.72rem;">WhatsApp</div>
        </div>
        <button onclick="waCloseDrawer()" style="background:rgba(255,255,255,.2);border:none;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:18px;">&times;</button>
      </div>
      <div id="wa-chat-history" style="flex:1;overflow-y:auto;padding:16px;background:#f0f2f5;display:flex;flex-direction:column;gap:8px;"></div>
      <div style="padding:12px 16px;background:#fff;border-top:1px solid #f1f5f9;display:flex;gap:8px;">
        <input id="wa-msg-input" type="text" placeholder="Scrie mesaj..." style="flex:1;height:40px;border:1.5px solid #e2e8f0;border-radius:20px;padding:0 14px;font-size:.84rem;outline:none;" onkeydown="if(event.key==='Enter') waSend();">
        <button onclick="waSend()" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#25d366,#128c5e);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
    <div id="wa-drawer-overlay" onclick="waCloseDrawer()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:99997;"></div>
  </div>

  <!-- ============ TAB: PIESEAUTO.RO (UI NATIV) ============ -->
  <div data-tab-pane="parser" class="bots-pane mt-5 hidden">

    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#e63946,#9d0208);display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
        <div>
          <h3 style="font-size:.95rem;font-weight:700;margin:0;">Robot AI Hibrid — Pieseauto.ro</h3>
          <p style="font-size:.75rem;opacity:.55;margin:0;">Cereri noi + analiza piata + match stoc · backend: <code style="font-size:.7rem;">/robot/parser.php</code></p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span id="ps-dot" style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>
        <span id="ps-status" style="font-size:.75rem;color:#64748b;">—</span>
        <div style="display:flex;height:34px;border-radius:8px;overflow:hidden;border:1.5px solid #e2e8f0;">
          <input id="ps-user" type="text" placeholder="Utilizator target..." style="width:200px;padding:0 12px;font-size:.8rem;border:none;outline:none;">
        </div>
        <button onclick="psSetMode('run')" style="height:34px;padding:0 16px;border-radius:8px;background:#16a34a;color:#fff;border:none;font-size:.78rem;font-weight:700;cursor:pointer;">RUN</button>
        <button onclick="psSetMode('stop')" style="height:34px;padding:0 16px;border-radius:8px;background:#dc2626;color:#fff;border:none;font-size:.78rem;font-weight:700;cursor:pointer;">STOP</button>
      </div>
    </div>

    <div class="box rounded-xl border bg-white shadow-sm overflow-x-auto">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:2px solid #f1f5f9;">
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Ora / ID</th>
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Vehicul / Piesa</th>
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Stoc Target</th>
            <th style="padding:12px 16px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Analiza Piata</th>
            <th style="padding:12px 16px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:700;">Link</th>
          </tr>
        </thead>
        <tbody id="ps-table-body">
          <tr><td colspan="5" style="padding:48px;text-align:center;color:#94a3b8;font-size:.85rem;">Apasa RUN pentru a porni scanarea...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ============ TAB: FACEBOOK (UI NATIV) ============ -->
  <div data-tab-pane="facebook" class="bots-pane mt-5 hidden">

    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1877f2,#0856bb);display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </div>
        <div>
          <h3 style="font-size:.95rem;font-weight:700;margin:0;">Facebook Sniper — Grupuri Piese</h3>
          <p style="font-size:.75rem;opacity:.55;margin:0;">Via Apify + comentarii AI Groq · backend: <code style="font-size:.7rem;">/robot/fb_parser.php</code></p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span id="fb-dot-native" style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>
        <span id="fb-status-native" style="font-size:.75rem;color:#64748b;">—</span>
        <button onclick="fbSetMode('run')" style="height:34px;padding:0 16px;border-radius:8px;background:#16a34a;color:#fff;border:none;font-size:.78rem;font-weight:700;cursor:pointer;">Porneste</button>
        <button onclick="fbSetMode('stop')" style="height:34px;padding:0 16px;border-radius:8px;background:#dc2626;color:#fff;border:none;font-size:.78rem;font-weight:700;cursor:pointer;">Opreste</button>
        <button onclick="fbLoad()" style="height:34px;padding:0 12px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
          Reincarca
        </button>
      </div>
    </div>

    <div id="fb-posts-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;">
      <div style="grid-column:1/-1;padding:48px;text-align:center;color:#94a3b8;font-size:.85rem;background:#fff;border-radius:12px;border:1px solid #f1f5f9;">
        Apasa "Porneste" pentru a activa scanarea Facebook...
      </div>
    </div>
  </div>

  <!-- ============ TAB: LEADS ============ -->
  <div data-tab-pane="leads" class="bots-pane mt-5 hidden">
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <p class="text-sm opacity-70 mr-auto">Storage: <code>robot/data/leads.json</code> (<?= count($leads) ?> intrari).</p>
      <input id="bots-leads-search" type="text" placeholder="Cauta nume / telefon / mesaj..." class="box h-10 w-72 rounded-md border px-3">
      <button onclick="location.reload()" class="box inline-flex h-10 items-center gap-2 rounded-lg border px-4 py-2 text-sm">
        <i data-lucide="refresh-cw" class="size-4"></i> Reincarca
      </button>
    </div>
    <div class="box rounded-lg border bg-white shadow-sm overflow-x-auto">
      <table class="w-full caption-bottom text-sm">
        <thead class="border-b">
          <tr class="text-left text-xs uppercase opacity-70">
            <th class="h-12 px-4 font-medium">Data</th>
            <th class="h-12 px-4 font-medium">Nume</th>
            <th class="h-12 px-4 font-medium">Telefon</th>
            <th class="h-12 px-4 font-medium">Mesaj</th>
            <th class="h-12 px-4 font-medium">Pagina</th>
            <th class="h-12 px-4 font-medium">IP</th>
            <th class="h-12 px-4 font-medium text-right">Actiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($leads)): ?>
            <tr><td colspan="7" class="p-8 text-center opacity-60">Niciun lead capturat inca.</td></tr>
          <?php else: foreach ($leads as $lead): ?>
            <tr class="border-b bots-leads-row"
                data-search="<?= bots_h(strtolower(($lead['name'] ?? '') . ' ' . ($lead['phone'] ?? '') . ' ' . ($lead['msg'] ?? ''))) ?>">
              <td class="p-4 text-xs opacity-70 whitespace-nowrap"><?= bots_h($lead['date'] ?? '') ?></td>
              <td class="p-4 font-medium"><?= bots_h($lead['name'] ?? '') ?></td>
              <td class="p-4">
                <a href="https://wa.me/<?= bots_h(preg_replace('/[^0-9]/', '', $lead['phone'] ?? '')) ?>" target="_blank" class="text-success hover:underline inline-flex items-center gap-1">
                  <i data-lucide="message-circle" class="size-3"></i> <?= bots_h($lead['phone'] ?? '') ?>
                </a>
              </td>
              <td class="p-4 max-w-md"><?= bots_h($lead['msg'] ?? '') ?></td>
              <td class="p-4 text-xs opacity-70"><?= bots_h($lead['page'] ?? '') ?></td>
              <td class="p-4 text-xs opacity-50"><?= bots_h($lead['ip'] ?? '') ?></td>
              <td class="p-4 text-right">
                <button class="box bots-leads-copy inline-flex h-8 items-center justify-center rounded border px-2 text-xs"
                        data-text="<?= bots_h(($lead['name'] ?? '') . ' | ' . ($lead['phone'] ?? '') . ' | ' . ($lead['msg'] ?? '')) ?>">
                  <i data-lucide="copy" class="size-3"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ============ TAB: WEBHOOK & LOG ============ -->
  <div data-tab-pane="webhook" class="bots-pane mt-5 hidden">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div class="box rounded-lg border bg-white p-4">
        <div class="text-xs opacity-60">Instance</div>
        <div class="mt-1 text-base font-medium"><?= bots_h($instance ?: '—') ?></div>
      </div>
      <div class="box rounded-lg border bg-white p-4">
        <div class="text-xs opacity-60">Sesiuni active</div>
        <div class="mt-1 text-base font-medium"><?= $sessionsCount ?> conversatii</div>
      </div>
      <div class="box rounded-lg border bg-white p-4">
        <div class="text-xs opacity-60">Dedup cache</div>
        <div class="mt-1 text-base font-medium"><?= $dedupCount ?> evenimente recente</div>
      </div>
    </div>

    <div class="mt-4 box rounded-lg border bg-warning/5 p-4">
      <div class="flex items-start gap-3">
        <i data-lucide="link" class="size-5 text-warning"></i>
        <div class="text-sm flex-1">
          <strong>URL webhook UltraMsg</strong> (copiaza in <a href="https://app.ultramsg.com" target="_blank" class="text-primary underline">app.ultramsg.com</a> &rarr; instance &rarr; Webhook):
          <div class="mt-2 flex gap-2">
            <code id="bots-webhook-url" class="flex-1 break-all rounded bg-white p-2 text-xs"><?= bots_h($webhookUrl) ?></code>
            <button id="bots-webhook-copy" class="box inline-flex h-10 w-10 items-center justify-center rounded-lg border" title="Copy">
              <i data-lucide="copy" class="size-4"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <h3 class="mt-6 text-base font-medium">Status chei <code>robot/.env</code></h3>
    <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($envStatus as $k => $s): ?>
        <div class="box flex items-center justify-between rounded-md border bg-white p-3">
          <div>
            <div class="font-mono text-xs"><?= bots_h($k) ?></div>
            <div class="text-xs opacity-60"><?= bots_h($s['label']) ?></div>
          </div>
          <span class="rounded-full border px-2 py-1 text-xs <?= $s['ok'] ? 'text-success border-success/30' : 'text-danger border-danger/30' ?>">
            <?= $s['ok'] ? 'OK (' . $s['len'] . ')' : 'MISSING' ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-6 box rounded-lg border bg-white shadow-sm">
      <div class="flex items-center justify-between border-b p-4">
        <div>
          <h3 class="text-base font-medium">webhook.log</h3>
          <p class="text-xs opacity-60">
            <?= count($logLines) ?> linii afisate (max 300) | total fisier <?= number_format($logSize / 1024, 1) ?> KB
          </p>
        </div>
        <div class="text-xs opacity-60"><?= $logMtime ? 'Modified: ' . bots_h($logMtime) : 'Log inexistent' ?></div>
      </div>
      <pre class="m-0 max-h-[500px] overflow-auto bg-slate-900 p-4 font-mono text-xs leading-5 text-slate-200"><?php
        if (empty($logLines)) {
            echo '<span style="color:#94a3b8">Niciun log inca. Trimite un mesaj WhatsApp catre instance ' . bots_h($instance) . ' ca sa testezi.</span>';
        } else {
            foreach ($logLines as $l) {
                $line = bots_h($l);
                if (str_contains($l, 'OUT_CHAT'))      $line = '<span style="color:#86efac">' . $line . '</span>';
                elseif (str_contains($l, 'OUT_IMG'))   $line = '<span style="color:#7dd3fc">' . $line . '</span>';
                elseif (str_contains($l, 'IN_RAW'))    $line = '<span style="color:#fcd34d">' . $line . '</span>';
                elseif (str_contains($l, 'DEDUP'))     $line = '<span style="color:#94a3b8">' . $line . '</span>';
                elseif (stripos($l, 'error') !== false || stripos($l, 'fail') !== false) {
                    $line = '<span style="color:#fca5a5">' . $line . '</span>';
                }
                echo $line . "\n";
            }
        }
      ?></pre>
    </div>

    <p class="mt-3 text-xs opacity-60">
      <i data-lucide="info" class="inline size-3"></i> Pentru log-uri JSON brute, deschide
      <a href="<?= bots_h($webhookUrl) ?>&debug=1" target="_blank" class="text-primary underline">webhook.php?debug=1</a>.
    </p>
  </div>
</div>

<style>
  .bots-tab { background: transparent; border-color: transparent; opacity: 0.65; transition: opacity .2s; }
  .bots-tab:hover { opacity: 1; }
  .bots-tab.active { background: #fff; border-color: rgb(229, 231, 235); border-bottom-color: #fff; opacity: 1; font-weight: 500; }

  /* ====== BOT CARDS REDESIGN ====== */
  .bot-card {
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease;
    background: #fff;
    border: 1px solid rgba(0,0,0,.07);
  }
  .bot-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.12), 0 4px 8px rgba(0,0,0,.06);
  }
  .bot-card-header {
    position: relative;
    height: 110px;
    display: flex;
    align-items: flex-end;
    padding: 14px 16px 12px;
    overflow: hidden;
  }
  .bot-card-header::before {
    content:'';
    position: absolute;
    inset: 0;
    opacity: .13;
  }
  .bot-card-header .bot-avatar {
    position: relative;
    z-index: 2;
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.25);
    backdrop-filter: blur(4px);
    border: 1.5px solid rgba(255,255,255,.4);
    flex-shrink: 0;
  }
  .bot-card-header .bot-avatar svg { filter: drop-shadow(0 1px 3px rgba(0,0,0,.25)); }
  .bot-card-header .bot-header-info { position: relative; z-index: 2; flex: 1; min-width: 0; padding-left: 12px; }
  .bot-card-header .bot-name {
    font-size: .95rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 1px 3px rgba(0,0,0,.15);
  }
  .bot-card-header .bot-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 100px;
    background: rgba(255,255,255,.28);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,.35);
  }
  .bot-status-pill {
    position: absolute;
    top: 12px; right: 12px;
    z-index: 3;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 100px;
    backdrop-filter: blur(6px);
  }
  .bot-status-pill.active   { background: rgba(16,185,129,.2); color: #065f46; border: 1px solid rgba(16,185,129,.4); }
  .bot-status-pill.expired  { background: rgba(245,158,11,.2);  color: #92400e; border: 1px solid rgba(245,158,11,.35);}
  .bot-status-pill.disabled { background: rgba(239,68,68,.18);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }

  .bot-card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 10px; }
  .bot-info-row { display: flex; align-items: center; gap: 8px; font-size: .8rem; }
  .bot-info-row .bot-info-label { opacity: .5; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; min-width: 48px; }
  .bot-info-row .bot-info-val { font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .bot-info-row .bot-channel-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 100px;
    font-size: .72rem; font-weight: 700;
  }

  .bot-req-bar { height: 4px; border-radius: 100px; background: #f1f5f9; overflow: hidden; margin-top: 2px; }
  .bot-req-bar-fill { height: 100%; border-radius: 100px; transition: width .5s ease; }

  .bot-card-footer { border-top: 1px solid #f1f5f9; padding: 10px 16px; display: flex; gap: 6px; }
  .bot-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    height: 34px; padding: 0 14px; border-radius: 8px;
    font-size: .78rem; font-weight: 600;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all .15s ease;
    text-decoration: none;
  }
  .bot-btn:hover { filter: brightness(.93); }
  .bot-btn-preview { background: #f0f4ff; color: #3b5bdb; border-color: #c5d0fa; }
  .bot-btn-edit    { background: #f8f9fa; color: #343a40; border-color: #dee2e6; }
  .bot-btn-test    { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }

  /* ====== CHANNEL COLOR THEMES ====== */
  /* whatsapp */  .bot-ch-whatsapp  { background: linear-gradient(135deg,#25d366,#128c5e); color:#fff; }
  /* facebook */  .bot-ch-facebook  { background: linear-gradient(135deg,#1877f2,#0856bb); color:#fff; }
  /* olx */       .bot-ch-olx       { background: linear-gradient(135deg,#ff6d00,#e55000); color:#fff; }
  /* pieseauto */ .bot-ch-pieseauto { background: linear-gradient(135deg,#e63946,#9d0208); color:#fff; }
  /* dezro */     .bot-ch-dezro     { background: linear-gradient(135deg,#7c3aed,#4c1d95); color:#fff; }
  /* email */     .bot-ch-email     { background: linear-gradient(135deg,#f59e0b,#b45309); color:#fff; }
  /* website */   .bot-ch-website   { background: linear-gradient(135deg,#06b6d4,#0369a1); color:#fff; }
  /* manual */    .bot-ch-manual    { background: linear-gradient(135deg,#64748b,#334155); color:#fff; }

  /* channel chip colors */
  .chip-whatsapp  { background:#dcfce7; color:#15803d; }
  .chip-facebook  { background:#dbeafe; color:#1d4ed8; }
  .chip-olx       { background:#ffedd5; color:#c2410c; }
  .chip-pieseauto { background:#fee2e2; color:#b91c1c; }
  .chip-dezro     { background:#ede9fe; color:#6d28d9; }
  .chip-email     { background:#fef3c7; color:#b45309; }
  .chip-website   { background:#e0f2fe; color:#0369a1; }
  .chip-manual    { background:#f1f5f9; color:#475569; }

  /* fill bar colors */
  .fill-whatsapp  { background: linear-gradient(90deg,#25d366,#128c5e); }
  .fill-facebook  { background: linear-gradient(90deg,#1877f2,#0856bb); }
  .fill-olx       { background: linear-gradient(90deg,#ff6d00,#e55000); }
  .fill-pieseauto { background: linear-gradient(90deg,#e63946,#9d0208); }
  .fill-dezro     { background: linear-gradient(90deg,#7c3aed,#4c1d95); }
  .fill-email     { background: linear-gradient(90deg,#f59e0b,#b45309); }
  .fill-website   { background: linear-gradient(90deg,#06b6d4,#0369a1); }
  .fill-manual    { background: linear-gradient(90deg,#64748b,#334155); }

  /* decorative circles in header */
  .bot-deco-circle {
    position: absolute;
    border-radius: 50%;
    opacity: .18;
    pointer-events: none;
  }

  /* ====== TOGGLE SWITCH ====== */
  .bot-toggle {
    width: 38px; height: 20px;
    border-radius: 100px;
    background: #e2e8f0;
    position: relative;
    transition: background .2s ease;
    flex-shrink: 0;
  }
  .bot-toggle-on { background: #16a34a; }
  .bot-toggle-thumb {
    position: absolute;
    top: 2px; left: 2px;
    width: 16px; height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
    transition: left .2s cubic-bezier(.34,1.56,.64,1);
  }
  .bot-toggle-on .bot-toggle-thumb { left: 20px; }
  .bot-toggle-wrap:hover .bot-toggle { filter: brightness(.93); }

  /* ====== GRID ====== */
  #bots-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }

  /* ====== FILTER BAR ====== */
  .bots-filter-bar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
  .bots-filter-bar input,
  .bots-filter-bar select {
    height: 38px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    padding: 0 12px;
    font-size: .83rem;
    background: #fff;
    transition: border-color .15s;
  }
  .bots-filter-bar input:focus,
  .bots-filter-bar select:focus { outline:none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
</style>

<script>
(function () {
  'use strict';

  // ============================================================
  // TAB SWITCHER + LAZY IFRAMES
  // ============================================================
  const tabs  = document.querySelectorAll('.bots-tab');
  const panes = document.querySelectorAll('.bots-pane');

  function activateTab(name) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    panes.forEach(p => {
      const visible = p.dataset.tabPane === name;
      p.classList.toggle('hidden', !visible);
      if (visible) {
        const iframe = p.querySelector('iframe[data-src]');
        if (iframe && !iframe.src) iframe.src = iframe.dataset.src;
      }
    });
    try { history.replaceState(null, '', '?tab=' + encodeURIComponent(name)); } catch (_) {}
  }

  tabs.forEach(t => t.addEventListener('click', () => activateTab(t.dataset.tab)));

  const initial = (new URL(location.href)).searchParams.get('tab') || 'config';
  activateTab(document.querySelector(`.bots-tab[data-tab="${initial}"]`) ? initial : 'config');

  // ============================================================
  // LEADS: search + copy
  // ============================================================
  document.getElementById('bots-leads-search')?.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('.bots-leads-row').forEach(row => {
      row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
  });
  document.querySelectorAll('.bots-leads-copy').forEach(btn => {
    btn.addEventListener('click', () => {
      navigator.clipboard.writeText(btn.dataset.text).then(() => {
        btn.innerHTML = '<i data-lucide="check" class="size-3"></i>';
        if (window.lucide) window.lucide.createIcons();
        setTimeout(() => { btn.innerHTML = '<i data-lucide="copy" class="size-3"></i>'; if (window.lucide) window.lucide.createIcons(); }, 1500);
      });
    });
  });

  // ============================================================
  // WEBHOOK URL: copy
  // ============================================================
  document.getElementById('bots-webhook-copy')?.addEventListener('click', function () {
    const url = document.getElementById('bots-webhook-url')?.textContent || '';
    navigator.clipboard.writeText(url).then(() => {
      this.innerHTML = '<i data-lucide="check" class="size-4"></i>';
      if (window.lucide) window.lucide.createIcons();
      setTimeout(() => { this.innerHTML = '<i data-lucide="copy" class="size-4"></i>'; if (window.lucide) window.lucide.createIcons(); }, 1500);
    });
  });

  // ============================================================
  // CRUD BOTI (cod existent — operează in tab "config")
  // ============================================================
  const ENDPOINT = '/admin/api/bots_endpoint.php';
  const grid = document.getElementById('bots-grid');
  const form = document.getElementById('bots-form');
  const modal = document.getElementById('bots-modal');
  const toast = document.getElementById('bots-toast');
  const filters = {
    search: document.getElementById('bots-search'),
    channel: document.getElementById('bots-channel-filter'),
    plan: document.getElementById('bots-plan-filter'),
    status: document.getElementById('bots-status-filter')
  };
  let bots = [];
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;
  const paginationEl = document.getElementById('bots-pagination');

  async function apiCall(action, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: action, ...payload })
    });
    const raw = await response.text();
    let result;
    try { result = JSON.parse(raw); }
    catch (error) { throw new Error('Endpoint-ul nu a returnat JSON valid.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare necunoscuta');
    return result.data;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));
  }

  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('hidden');
    toast.classList.toggle('text-danger', Boolean(isError));
    setTimeout(() => toast.classList.add('hidden'), 3000);
  }

  function formToObject(formElement) {
    const payload = {};
    new FormData(formElement).forEach((value, key) => {
      if (String(value).trim() !== '') payload[key] = value;
    });
    return payload;
  }

  function filteredBots() { return bots; }

  function listPayload() {
    return {
      page: currentPage,
      per_page: 10,
      q: (filters.search?.value || '').trim(),
      channel: filters.channel?.value || undefined,
      token_status: filters.status?.value || undefined,
      token_plan: filters.plan?.value || undefined,
    };
  }

  function maskToken(token) {
    if (!token) return '-';
    const value = String(token);
    return value.length <= 8 ? '********' : `${value.slice(0, 4)}...${value.slice(-4)}`;
  }

  /* ========== CHANNEL / TYPE METADATA ========== */
  const CHANNEL_META = {
    whatsapp:  {
      label: 'WhatsApp', chipCls: 'chip-whatsapp', headerCls: 'bot-ch-whatsapp', fillCls: 'fill-whatsapp',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.22)"/>
        <path d="M24 8C15.163 8 8 15.163 8 24c0 2.99.832 5.787 2.278 8.175L8 40l8.093-2.226A15.937 15.937 0 0024 40c8.837 0 16-7.163 16-16S32.837 8 24 8z" fill="#fff" opacity=".9"/>
        <path d="M32.5 27.883c-.45-.225-2.664-1.316-3.078-1.466-.413-.15-.714-.225-.999.225-.3.45-1.149 1.466-1.409 1.766-.26.3-.524.337-.974.112-2.664-1.332-4.41-2.38-6.16-5.396-.465-.8.465-.742 1.33-2.47.15-.3.075-.563-.038-.788-.112-.225-1.0-2.413-1.373-3.31-.36-.868-.727-.749-1--.764-.26-.015-.562-.019-.863-.019-.3 0-.787.112-1.2.562-.412.45-1.574 1.539-1.574 3.752 0 2.213 1.611 4.351 1.836 4.651.225.3 3.15 4.801 7.64 6.738 2.841 1.226 3.953 1.33 5.375 1.12.862-.13 2.664-1.089 3.04-2.14.375-1.052.375-1.952.263-2.14-.112-.187-.413-.3-.863-.524z" fill="#25D366"/>
      </svg>`
    },
    facebook:  {
      label: 'Facebook', chipCls: 'chip-facebook', headerCls: 'bot-ch-facebook', fillCls: 'fill-facebook',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <path d="M32 8h-4a8 8 0 00-8 8v4h-4v6h4v16h6V26h4l1-6h-5v-3a2 2 0 012-2h3V8z" fill="#fff" opacity=".9"/>
      </svg>`
    },
    olx:       {
      label: 'OLX', chipCls: 'chip-olx', headerCls: 'bot-ch-olx', fillCls: 'fill-olx',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <rect x="10" y="14" width="10" height="10" rx="5" stroke="#fff" stroke-width="3" opacity=".9"/>
        <rect x="28" y="14" width="10" height="10" rx="5" stroke="#fff" stroke-width="3" opacity=".9"/>
        <rect x="10" y="28" width="10" height="6" rx="2" fill="#fff" opacity=".9"/>
        <path d="M28 31l5-5 5 5M33 26v8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".9"/>
      </svg>`
    },
    pieseauto: {
      label: 'PieseAuto', chipCls: 'chip-pieseauto', headerCls: 'bot-ch-pieseauto', fillCls: 'fill-pieseauto',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <path d="M10 30h28M13 30l3-8h16l3 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>
        <circle cx="16" cy="32" r="3" fill="#fff" opacity=".9"/>
        <circle cx="32" cy="32" r="3" fill="#fff" opacity=".9"/>
        <path d="M18 22h12" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".6"/>
      </svg>`
    },
    dezro:     {
      label: 'Dez.ro', chipCls: 'chip-dezro', headerCls: 'bot-ch-dezro', fillCls: 'fill-dezro',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <rect x="10" y="12" width="18" height="22" rx="3" stroke="#fff" stroke-width="2.5" opacity=".9"/>
        <path d="M15 18h8M15 23h6M15 28h4" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".75"/>
        <circle cx="34" cy="30" r="6" fill="rgba(255,255,255,.2)" stroke="#fff" stroke-width="2.5"/>
        <path d="M32 30l2 2 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`
    },
    email:     {
      label: 'Email', chipCls: 'chip-email', headerCls: 'bot-ch-email', fillCls: 'fill-email',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <rect x="8" y="14" width="32" height="22" rx="4" stroke="#fff" stroke-width="2.5" opacity=".9"/>
        <path d="M8 18l16 10 16-10" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".85"/>
      </svg>`
    },
    website:   {
      label: 'Website', chipCls: 'chip-website', headerCls: 'bot-ch-website', fillCls: 'fill-website',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <circle cx="24" cy="24" r="13" stroke="#fff" stroke-width="2.5" opacity=".9"/>
        <path d="M24 11c-3 4-4 8-4 13s1 9 4 13M24 11c3 4 4 8 4 13s-1 9-4 13M11 24h26" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".8"/>
      </svg>`
    },
    manual:    {
      label: 'Manual', chipCls: 'chip-manual', headerCls: 'bot-ch-manual', fillCls: 'fill-manual',
      svg: `<svg viewBox="0 0 48 48" width="30" height="30" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="24" cy="24" r="20" fill="rgba(255,255,255,.18)"/>
        <rect x="14" y="10" width="14" height="18" rx="3" stroke="#fff" stroke-width="2.5" opacity=".9"/>
        <path d="M14 36h14M18 14h6M18 19h6M18 24h4" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".75"/>
        <path d="M28 28l6 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".6"/>
      </svg>`
    }
  };

  const TYPE_LABELS = {
    message_sender: 'Message Sender',
    scraper: 'Scraper',
    sync: 'Sync',
    ai_reply: 'AI Reply',
    notification: 'Notification'
  };

  const TYPE_ICONS = {
    message_sender: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    scraper:        '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>',
    sync:           '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>',
    ai_reply:       '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M22 2 12 12"/><path d="m22 2-5 5"/><path d="m22 2-5.5.5"/></svg>',
    notification:   '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>'
  };

  function statusClass(status) {
    if (status === 'active') return 'text-success';
    if (status === 'expired') return 'text-warning';
    return 'text-danger';
  }

  function reqPct(used, limit) {
    if (!limit || limit <= 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
  }

  function render() {
    const rows = filteredBots();
    if (!grid) return;
    if (!rows.length) {
      grid.innerHTML = `<div style="grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center;opacity:.55;gap:12px;">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4M8 15h.01M16 15h.01"/></svg>
        <p style="font-size:.9rem;font-weight:600;">Nu exista boti care sa corespunda filtrelor</p>
        <p style="font-size:.8rem;">Adauga un bot nou cu butonul de sus</p>
      </div>`;
      if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => load(p));
      return;
    }

    grid.innerHTML = rows.map((bot) => {
      const ch      = (bot.channel || 'manual').toLowerCase();
      const meta    = CHANNEL_META[ch] || CHANNEL_META['manual'];
      const status  = bot.token_status || 'active';
      const used    = parseInt(bot.requests_used || 0, 10);
      const limit   = parseInt(bot.requests_limit || 0, 10);
      const pct     = reqPct(used, limit);
      const typeKey = bot.bot_type || 'message_sender';
      const typeLabel = TYPE_LABELS[typeKey] || typeKey;
      const typeIcon  = TYPE_ICONS[typeKey]  || '';
      const lastMsg = bot.last_test_message ? escapeHtml(bot.last_test_message).substring(0, 55) + (bot.last_test_message.length > 55 ? '…' : '') : null;

      return `
      <article class="bot-card" data-id="${escapeHtml(bot.randomn_id)}">

        <!-- HEADER cu gradient per canal -->
        <div class="bot-card-header ${meta.headerCls}">
          <!-- cerc decorativ stanga-sus -->
          <div class="bot-deco-circle" style="width:90px;height:90px;background:#fff;top:-20px;left:-20px;"></div>
          <!-- cerc decorativ dreapta-jos -->
          <div class="bot-deco-circle" style="width:60px;height:60px;background:#fff;bottom:-15px;right:10px;"></div>

          <!-- status pill -->
          <span class="bot-status-pill ${status}">${escapeHtml(status)}</span>

          <!-- avatar SVG -->
          <div class="bot-avatar">${meta.svg}</div>

          <!-- name + type -->
          <div class="bot-header-info">
            <div class="bot-name" style="color:#fff;">${escapeHtml(bot.name || 'Bot fara nume')}</div>
            <div class="bot-type-badge" style="color:rgba(255,255,255,.9);">
              ${typeIcon}
              ${escapeHtml(typeLabel)}
            </div>
          </div>
        </div>

        <!-- BODY -->
        <div class="bot-card-body">

          <div class="bot-info-row" style="justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:6px;">
              <span class="bot-info-label">Canal</span>
              <span class="bot-channel-chip ${meta.chipCls}">${escapeHtml(meta.label)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              <span class="bot-info-label">Plan</span>
              <span class="bot-info-val" style="font-size:.78rem;background:${bot.token_plan==='paid'?'#fef9c3':'#f8fafc'};color:${bot.token_plan==='paid'?'#a16207':'#64748b'};padding:2px 8px;border-radius:100px;border:1px solid ${bot.token_plan==='paid'?'#fde68a':'#e2e8f0'}">
                ${bot.token_plan==='paid'?'⭐ Paid':'Free'}
              </span>
            </div>
          </div>

          <div class="bot-info-row">
            <span class="bot-info-label">Token</span>
            <span class="bot-info-val" style="font-family:monospace;font-size:.75rem;flex:1;">${escapeHtml(maskToken(bot.token_value))}</span>
          </div>

          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
              <span class="bot-info-label">Requests</span>
              <span style="font-size:.72rem;font-weight:600;color:${pct>=90?'#dc2626':pct>=60?'#d97706':'#64748b'}">
                ${used.toLocaleString()} / ${limit ? limit.toLocaleString() : '∞'}
                ${limit ? `<span style="opacity:.55;">(${pct}%)</span>` : ''}
              </span>
            </div>
            <div class="bot-req-bar">
              <div class="bot-req-bar-fill ${meta.fillCls}" style="width:${limit?pct:8}%;"></div>
            </div>
          </div>

          ${bot.starts_at || bot.ends_at ? `
          <div class="bot-info-row" style="gap:12px;font-size:.74rem;opacity:.6;">
            ${bot.starts_at ? `<span>▶ ${escapeHtml(bot.starts_at.substring(0,10))}</span>` : ''}
            ${bot.ends_at   ? `<span>⏹ ${escapeHtml(bot.ends_at.substring(0,10))}</span>` : ''}
          </div>` : ''}

          ${lastMsg ? `<div style="font-size:.73rem;line-height:1.4;color:#64748b;background:#f8fafc;border-radius:8px;padding:7px 10px;border:1px solid #f1f5f9;">${lastMsg}</div>` : ''}

        </div>

        <!-- FOOTER BUTOANE -->
        <div class="bot-card-footer" style="align-items:center;">
          <!-- Toggle activ/dezactiv -->
          <label class="bot-toggle-wrap" title="${status === 'active' ? 'Bot activ — click pentru dezactivare' : 'Bot inactiv — click pentru activare'}" style="display:flex;align-items:center;gap:7px;cursor:pointer;margin-right:4px;flex-shrink:0;">
            <div class="bot-toggle ${status === 'active' ? 'bot-toggle-on' : ''}" data-action="toggle" data-id="${escapeHtml(bot.randomn_id)}" data-status="${escapeHtml(status)}">
              <div class="bot-toggle-thumb"></div>
            </div>
            <span style="font-size:.72rem;font-weight:600;color:${status === 'active' ? '#16a34a' : '#94a3b8'};">${status === 'active' ? 'Activ' : 'Inactiv'}</span>
          </label>

          <a href="/admin/profilebots?id=${encodeURIComponent(bot.randomn_id)}" class="bot-btn bot-btn-preview">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview
          </a>
          <button type="button" data-action="edit" data-bot='${escapeHtml(JSON.stringify(bot))}' class="bot-btn bot-btn-edit">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button type="button" data-action="testbot" data-id="${escapeHtml(bot.randomn_id)}" class="bot-btn bot-btn-test" style="margin-left:auto;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Test
          </button>
        </div>
      </article>`;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
    if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => load(p));
  }

  function openModal(bot) {
    form.reset();
    form.dataset.action = bot ? 'edit' : 'add';
    document.getElementById('bots-modal-title').textContent = bot ? 'Editeaza bot' : 'Bot nou';
    if (bot) {
      Object.entries(bot).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (field) field.value = value ?? '';
      });
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  async function load(page) {
    if (page) currentPage = page;
    try {
      const data = await apiCall('list', listPayload());
      const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
      bots = parsed.items;
      listMeta = parsed;
      currentPage = parsed.page;
      render();
    } catch (e) { showToast(e.message, true); }
  }

  document.getElementById('bots-open-create')?.addEventListener('click', () => openModal(null));
  document.getElementById('bots-close-modal')?.addEventListener('click', closeModal);
  document.getElementById('bots-cancel')?.addEventListener('click', closeModal);
  let botsFilterTimer;
  const reloadBots = () => { currentPage = 1; load().catch((e) => showToast(e.message, true)); };
  Object.values(filters).forEach((filter) => filter?.addEventListener('input', () => { clearTimeout(botsFilterTimer); botsFilterTimer = setTimeout(reloadBots, 300); }));
  Object.values(filters).forEach((filter) => filter?.addEventListener('change', reloadBots));

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall(form.dataset.action || 'add', formToObject(form));
      closeModal();
      showToast('Bot salvat.', false);
      await load();
    } catch (error) { showToast(error.message, true); }
  });

  grid?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    try {
      if (button.dataset.action === 'edit') {
        openModal(JSON.parse(button.dataset.bot || '{}'));
        return;
      }
      if (button.dataset.action === 'testbot') {
        const result = await apiCall('testbot', { randomn_id: Number(button.dataset.id) });
        showToast(`Test: ${result.last_test_status} - ${result.last_test_message}`, result.last_test_status !== 'success');
        await load();
        return;
      }
      if (button.dataset.action === 'toggle') {
        const currentStatus = button.dataset.status;
        const nextStatus = currentStatus === 'active' ? 'disabled' : 'active';
        /* optimistic UI */
        const togEl = button;
        togEl.classList.toggle('bot-toggle-on', nextStatus === 'active');
        togEl.dataset.status = nextStatus;
        const lbl = togEl.closest('.bot-toggle-wrap')?.querySelector('span');
        if (lbl) {
          lbl.textContent = nextStatus === 'active' ? 'Activ' : 'Inactiv';
          lbl.style.color = nextStatus === 'active' ? '#16a34a' : '#94a3b8';
        }
        await apiCall('setstatus', { randomn_id: Number(button.dataset.id), token_status: nextStatus });
        showToast(nextStatus === 'active' ? 'Bot activat.' : 'Bot dezactivat.', false);
        await load();
        return;
      }
    } catch (error) { showToast(error.message, true); }
  });

  load();

  if (window.lucide) window.lucide.createIcons();
})();
</script>

<!-- ================================================================
     WHATSAPP TAB — JS NATIV
     ================================================================ -->
<script>
(function () {
  'use strict';

  let waCurrent = '';
  let waTimer = null;

  function waEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function waStatus(online) {
    const dot = document.getElementById('wa-status-dot');
    const txt = document.getElementById('wa-status-txt');
    if (dot) dot.style.background = online ? '#22c55e' : '#ef4444';
    if (txt) txt.textContent = online ? 'Online' : 'Offline / Fara date';
  }

  window.waLoadGroups = function () {
    fetch('/robot/chat.php?action=fetch_groups')
      .then(r => r.json())
      .then(data => {
        waStatus(data.length > 0);
        const tbody = document.getElementById('wa-table-body');
        if (!tbody) return;
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="4" style="padding:48px;text-align:center;color:#94a3b8;font-size:.85rem;">Nicio conversatie inca. Asteapta un mesaj WhatsApp.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(g => `
          <tr style="border-bottom:1px solid #f8fafc;transition:background .15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <td style="padding:14px 16px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#25d366,#128c5e);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <span style="font-weight:700;font-size:.85rem;">+${waEsc(g.phone)}</span>
              </div>
            </td>
            <td style="padding:14px 16px;font-size:.83rem;color:#475569;max-width:300px;">
              <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${waEsc(g.last_msg)}</span>
            </td>
            <td style="padding:14px 16px;font-size:.75rem;color:#94a3b8;">${waEsc(g.time || '')}</td>
            <td style="padding:14px 16px;text-align:right;">
              <button onclick="waOpenDrawer('${waEsc(g.phone)}')"
                style="height:32px;padding:0 14px;border-radius:8px;background:#f0fdf4;color:#16a34a;border:1.5px solid #bbf7d0;font-size:.76rem;font-weight:700;cursor:pointer;">
                Deschide chat
              </button>
            </td>
          </tr>
        `).join('');
      })
      .catch(() => waStatus(false));
  };

  window.waNewContact = function () {
    const p = prompt('Număr telefon (cu prefix tara, fara +):');
    if (p) waOpenDrawer(p.replace(/[^0-9]/g, ''));
  };

  window.waOpenDrawer = function (phone) {
    waCurrent = phone;
    const drawer = document.getElementById('wa-drawer');
    const overlay = document.getElementById('wa-drawer-overlay');
    const ph = document.getElementById('wa-drawer-phone');
    if (ph) ph.textContent = '+' + phone;
    if (drawer)  { drawer.style.display = 'flex'; }
    if (overlay) { overlay.style.display = 'block'; }
    waLoadChat();
    clearInterval(waTimer);
    waTimer = setInterval(waLoadChat, 4000);
  };

  window.waCloseDrawer = function () {
    const drawer = document.getElementById('wa-drawer');
    const overlay = document.getElementById('wa-drawer-overlay');
    if (drawer)  drawer.style.display = 'none';
    if (overlay) overlay.style.display = 'none';
    waCurrent = '';
    clearInterval(waTimer);
  };

  function waLoadChat() {
    if (!waCurrent) return;
    fetch('/robot/chat.php?action=fetch_chat&phone=' + encodeURIComponent(waCurrent))
      .then(r => r.json())
      .then(data => {
        const el = document.getElementById('wa-chat-history');
        if (!el) return;
        el.innerHTML = data.map(m => {
          const isAdmin = m.s === 'admin';
          const isBot   = m.s === 'bot';
          const bg    = isAdmin ? '#dcf8c6' : '#fff';
          const align = isAdmin ? 'flex-end' : 'flex-start';
          const label = isAdmin ? 'TU' : isBot ? 'BOT' : 'CLIENT';
          const labelColor = isAdmin ? '#16a34a' : isBot ? '#2563eb' : '#6b7280';
          return `
            <div style="display:flex;flex-direction:column;align-items:${align};max-width:80%;">
              <div style="background:${bg};padding:10px 14px;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);font-size:.82rem;line-height:1.5;">
                <div style="font-size:.65rem;font-weight:700;color:${labelColor};margin-bottom:3px;">${label}</div>
                ${waEsc(m.t)}
                <div style="font-size:.65rem;color:#94a3b8;text-align:right;margin-top:4px;">${waEsc(m.time || '')}</div>
              </div>
            </div>`;
        }).join('');
        el.scrollTop = el.scrollHeight;
      });
  }

  window.waSend = function () {
    const input = document.getElementById('wa-msg-input');
    if (!input || !input.value.trim() || !waCurrent) return;
    const fd = new FormData();
    fd.append('phone', waCurrent);
    fd.append('msg', input.value.trim());
    fetch('/robot/chat.php?action=send', { method: 'POST', body: fd })
      .then(() => { input.value = ''; waLoadChat(); });
  };

  /* auto-refresh groups la fiecare 8s cand tab-ul e activ */
  let waGroupTimer = null;
  document.addEventListener('DOMContentLoaded', () => {
    const tabBtn = document.querySelector('.bots-tab[data-tab="whatsapp"]');
    if (tabBtn) {
      tabBtn.addEventListener('click', () => {
        waLoadGroups();
        clearInterval(waGroupTimer);
        waGroupTimer = setInterval(waLoadGroups, 8000);
      });
    }
  });
})();
</script>

<!-- ================================================================
     PIESEAUTO TAB — JS NATIV
     ================================================================ -->
<script>
(function () {
  'use strict';

  let psSeen = new Set();
  let psLocked = false;
  let psTimer = null;

  function psEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  window.psSetMode = async function (mode) {
    try {
      await fetch(`/robot/toggle.php?set=${mode}`);
      const dot = document.getElementById('ps-dot');
      const lbl = document.getElementById('ps-status');
      if (mode === 'run') {
        if (dot) dot.style.background = '#22c55e';
        if (lbl) lbl.textContent = 'Activ — Scanare...';
        psSeen.clear();
        clearInterval(psTimer);
        psFetch();
        psTimer = setInterval(psFetch, 14000);
      } else {
        if (dot) dot.style.background = '#ef4444';
        if (lbl) lbl.textContent = 'Oprit';
        clearInterval(psTimer);
      }
    } catch (e) {}
  };

  async function psFetch() {
    if (psLocked) return;
    psLocked = true;
    const user = (document.getElementById('ps-user')?.value || '').trim();
    const dot  = document.getElementById('ps-dot');
    const lbl  = document.getElementById('ps-status');
    try {
      const res  = await fetch(`/robot/parser.php?user=${encodeURIComponent(user)}`);
      const data = await res.json();
      if (data.status === 'stopped') {
        if (dot) dot.style.background = '#ef4444';
        if (lbl) lbl.textContent = 'Oprit';
        psLocked = false;
        return;
      }
      if (dot) dot.style.background = '#22c55e';
      if (lbl) lbl.textContent = 'Activ — Scanare...';
      const tbody = document.getElementById('ps-table-body');
      if (!tbody) { psLocked = false; return; }
      [...data].reverse().forEach(item => {
        if (psSeen.has(item.id)) return;
        psSeen.add(item.id);
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f8fafc';
        if (item.analysis?.match) tr.style.background = '#f0fdf4';
        const matchHtml = item.analysis?.match
          ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:100px;background:#dcfce7;color:#16a34a;font-size:.72rem;font-weight:700;">
               <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
               Disponibil la ${psEsc(user.toUpperCase())}
             </span>`
          : `<span style="color:#94a3b8;font-size:.78rem;">Absent stoc target</span>`;
        const price = item.analysis?.pret_recomandat ?? '-';
        const min   = item.analysis?.minim_piata ?? '-';
        const cnt   = item.analysis?.total_concurenti ?? 0;
        tr.innerHTML = `
          <td style="padding:12px 16px;">
            <div style="font-size:.75rem;color:#94a3b8;">${psEsc(item.ora)}</div>
            <code style="font-size:.68rem;color:#64748b;">#${psEsc(item.id)}</code>
          </td>
          <td style="padding:12px 16px;">
            <div style="font-weight:700;font-size:.85rem;">${psEsc(item.piesa)}</div>
            <div style="font-size:.75rem;color:#64748b;">${psEsc(item.masina)}</div>
          </td>
          <td style="padding:12px 16px;">${matchHtml}</td>
          <td style="padding:12px 16px;">
            <div style="background:#eef2ff;border:1px solid #e0e7ff;border-radius:10px;padding:8px 12px;display:inline-block;">
              <div style="font-weight:800;font-size:.95rem;color:#4338ca;">${psEsc(price)} RON</div>
              <div style="font-size:.7rem;color:#6366f1;margin-top:1px;">Min: ${psEsc(min)} RON · ${psEsc(cnt)} vanzatori</div>
            </div>
          </td>
          <td style="padding:12px 16px;text-align:right;">
            <a href="${psEsc(item.url)}" target="_blank"
               style="display:inline-flex;align-items:center;gap:4px;height:30px;padding:0 12px;border-radius:8px;background:#0f172a;color:#fff;font-size:.72rem;font-weight:700;text-decoration:none;">
              Oferta
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
          </td>`;
        tbody.insertBefore(tr, tbody.firstChild);
        if (tbody.children.length === 1 && tbody.querySelector('td[colspan]')) tbody.innerHTML = '';
      });
    } catch (e) {}
    psLocked = false;
  }
})();
</script>

<!-- ================================================================
     FACEBOOK TAB — JS NATIV
     ================================================================ -->
<script>
(function () {
  'use strict';

  let fbTimer = null;

  function fbStartPolling() {
    fbLoad();
    clearInterval(fbTimer);
    fbTimer = setInterval(fbLoad, 60000);
  }

  function fbEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  window.fbSetMode = async function (mode) {
    try {
      await fetch(`/robot/toggle2.php?target=fb&set=${mode}`);
      if (mode === 'run') {
        fbLoad();
        clearInterval(fbTimer);
        fbTimer = setInterval(fbLoad, 60000);
      } else {
        clearInterval(fbTimer);
        const dot = document.getElementById('fb-dot-native');
        const lbl = document.getElementById('fb-status-native');
        if (dot) dot.style.background = '#ef4444';
        if (lbl) lbl.textContent = 'Oprit';
      }
    } catch(e) {}
  };

  window.fbLoad = async function () {
    try {
      const res  = await fetch('/robot/fb_parser.php');
      const data = await res.json();
      const dot  = document.getElementById('fb-dot-native');
      const lbl  = document.getElementById('fb-status-native');
      const grid = document.getElementById('fb-posts-grid');
      if (!grid) return;

      if (data.status === 'stopped') {
        if (dot) dot.style.background = '#ef4444';
        if (lbl) lbl.textContent = 'Sistem oprit';
        return;
      }

      if (dot) { dot.style.background = '#22c55e'; dot.style.animation = 'fb-blink 1.5s infinite'; }
      if (lbl) lbl.textContent = 'Live din grup';

      if (!data.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;padding:48px;text-align:center;color:#94a3b8;background:#fff;border-radius:12px;border:1px solid #f1f5f9;">Asteptare date de la Apify...</div>';
        return;
      }

      grid.innerHTML = data.map(post => {
        const oemList = Array.isArray(post.oem_codes) ? post.oem_codes : [];
        const oemBadge = post.oem_found
          ? '<span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:6px;background:#dcfce7;color:#166534;">OEM în catalog</span>'
          : (oemList.length ? '<span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:6px;background:#fef3c7;color:#92400e;">OEM de verificat</span>' : '<span style="font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:6px;background:#f1f5f9;color:#64748b;">Fără cod OEM</span>');
        const oemChips = oemList.map(c => `<code style="font-size:.68rem;background:#eff6ff;color:#1d4ed8;padding:2px 6px;border-radius:4px;margin-right:4px;">${fbEsc(c)}</code>`).join('');
        const reply = post.mesaj_sugerat || '';
        const replyId = 'fb-reply-' + String(post.id || Math.random().toString(36).slice(2)).replace(/[^a-zA-Z0-9_-]/g, '');
        return `
        <div style="background:#fff;border-radius:14px;border:1px solid #f1f5f9;padding:16px 18px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:box-shadow .2s;"
             onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,.05)'">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:6px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1877f2,#0856bb);display:flex;align-items:center;justify-content:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <span style="font-weight:700;font-size:.84rem;color:#1877f2;">${fbEsc(post.autor)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              ${oemBadge}
              <span style="font-size:.7rem;color:#94a3b8;">${fbEsc(post.ora)}</span>
            </div>
          </div>
          <p style="font-size:.82rem;line-height:1.55;color:#374151;margin:0 0 8px;">${fbEsc(post.piesa)}</p>
          ${oemChips ? `<div style="margin-bottom:10px;">${oemChips}</div>` : ''}
          ${reply ? `<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:12px;">
            <div style="font-size:.68rem;font-weight:700;color:#64748b;margin-bottom:4px;">Răspuns sugerat (OM)</div>
            <p id="${replyId}" style="font-size:.78rem;line-height:1.5;color:#334155;margin:0;">${fbEsc(reply)}</p>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('${replyId}').textContent)"
                    style="margin-top:8px;height:28px;padding:0 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;font-size:.72rem;font-weight:600;cursor:pointer;">Copiază răspuns</button>
          </div>` : ''}
          <a href="${fbEsc(post.url)}" target="_blank"
             style="display:inline-flex;align-items:center;gap:5px;height:32px;padding:0 14px;border-radius:8px;background:#1877f2;color:#fff;font-size:.75rem;font-weight:700;text-decoration:none;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Vezi postarea
          </a>
        </div>`;
      }).join('');
    } catch(e) {}
  };

  /* auto-load: click pe tab SAU ?tab=facebook (selector #fb-posts-grid) */
  document.addEventListener('DOMContentLoaded', () => {
    const tabBtn = document.querySelector('.bots-tab[data-tab="facebook"]');
    if (tabBtn) {
      tabBtn.addEventListener('click', fbStartPolling);
    }
    const initialTab = (new URL(location.href)).searchParams.get('tab');
    if (initialTab === 'facebook') {
      fbStartPolling();
    }
  });
})();
</script>
<style>
  @keyframes fb-blink { 0%,100%{opacity:1} 50%{opacity:.3} }
</style>
