<?php
/**
 * robot/widget.js.php
 *
 * Serveste JavaScript-ul pentru widgetul de chat AI floating.
 * Include-l pe orice pagina cu:
 *   <script src="/robot/widget.js.php" defer></script>
 *
 * Widgetul:
 *  - Buton floating bottom-right cu icon chat
 *  - Fereastra de chat cu header branded, mesaje, input
 *  - Conectat la /robot/chat_widget_api.php (Groq LLM + stoc)
 *  - Persista sesiunea in localStorage
 *  - Responsive (full-screen pe mobile)
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$companyName = 'Besoiu Piese Auto';
$waPhone = '40726498573';
$waPrefill = 'Bună! Am nevoie de consultanță pentru piese auto.';
if (is_file(__DIR__ . '/company.json')) {
    $cj = json_decode((string)file_get_contents(__DIR__ . '/company.json'), true);
    $companyName = (string)($cj['company']['name'] ?? $companyName);
    $contactRaw = (string)($cj['terms']['contact'] ?? '');
    if ($contactRaw !== '' && stripos($contactRaw, 'demo') === false && preg_match('/(\d[\d\s]{8,})/', $contactRaw, $m)) {
        $digits = preg_replace('/\D+/', '', $m[1]);
        if (str_starts_with($digits, '0')) {
            $digits = '4' . substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '40' . $digits;
        }
        if ($digits !== '') {
            $waPhone = $digits;
        }
    }
}
$companyNameJs = json_encode($companyName);
$waUrlJs = json_encode('https://wa.me/' . $waPhone . '?text=' . rawurlencode($waPrefill));
?>
(function () {
  'use strict';

  const COMPANY = <?= $companyNameJs ?>;
  const WA_URL  = <?= $waUrlJs ?>;
  const API     = '/robot/chat_widget_api.php';
  const STORAGE_KEY = 'bpa_widget_sid';

  /* ── Singleton guard ── */
  if (document.getElementById('bpa-chat-widget')) return;

  /* ── Sesiune ID ── */
  let sid = localStorage.getItem(STORAGE_KEY) || '';

  /* ── CSS injectat ── */
  const style = document.createElement('style');
  style.textContent = `
    #bpa-fab {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 99990;
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e63946, #9d0208);
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(230,57,70,.45);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .2s;
    }
    #bpa-fab:hover {
      transform: scale(1.1);
      box-shadow: 0 8px 28px rgba(230,57,70,.55);
    }
    #bpa-fab .bpa-badge {
      position: absolute;
      top: -3px;
      right: -3px;
      min-width: 18px;
      height: 18px;
      border-radius: 100px;
      background: #22c55e;
      border: 2px solid #fff;
      font-size: 10px;
      font-weight: 700;
      color: #fff;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 0 4px;
    }
    #bpa-fab .bpa-badge.visible { display: flex; }

    #bpa-chat-widget {
      position: fixed;
      bottom: 96px;
      right: 24px;
      z-index: 99991;
      width: 370px;
      max-width: calc(100vw - 32px);
      height: 540px;
      max-height: calc(100vh - 120px);
      border-radius: 18px;
      background: #fff;
      box-shadow: 0 16px 60px rgba(0,0,0,.18), 0 4px 16px rgba(0,0,0,.08);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      transform: scale(.85) translateY(20px);
      opacity: 0;
      pointer-events: none;
      transition: transform .28s cubic-bezier(.34,1.56,.64,1), opacity .22s ease;
    }
    #bpa-chat-widget.open {
      transform: scale(1) translateY(0);
      opacity: 1;
      pointer-events: all;
    }
    @media (max-width: 480px) {
      #bpa-chat-widget {
        bottom: 0; right: 0;
        width: 100vw;
        height: 100dvh;
        max-height: 100dvh;
        border-radius: 0;
      }
      #bpa-fab { bottom: 16px; right: 16px; }
    }

    /* Header */
    #bpa-header {
      background: linear-gradient(135deg, #e63946, #9d0208);
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    #bpa-header .bpa-avatar {
      width: 38px; height: 38px;
      border-radius: 50%;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    #bpa-header .bpa-info { flex: 1; min-width: 0; }
    #bpa-header .bpa-name {
      color: #fff; font-weight: 700; font-size: .9rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    #bpa-header .bpa-sub {
      color: rgba(255,255,255,.75); font-size: .7rem;
      display: flex; align-items: center; gap: 5px; margin-top: 2px;
    }
    #bpa-header .bpa-sub .bpa-online-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: #4ade80; flex-shrink: 0;
      animation: bpa-pulse 2s infinite;
    }
    #bpa-close-btn {
      background: rgba(255,255,255,.15);
      border: none; border-radius: 8px;
      width: 30px; height: 30px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; color: #fff; font-size: 18px; line-height: 1;
      transition: background .15s;
      flex-shrink: 0;
    }
    #bpa-close-btn:hover { background: rgba(255,255,255,.28); }

    /* Mesaje */
    #bpa-messages {
      flex: 1;
      overflow-y: auto;
      padding: 14px 14px 8px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      background: #f8fafc;
      scroll-behavior: smooth;
    }
    #bpa-messages::-webkit-scrollbar { width: 4px; }
    #bpa-messages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 100px; }

    .bpa-msg {
      display: flex;
      flex-direction: column;
      max-width: 82%;
    }
    .bpa-msg.bpa-bot { align-items: flex-start; }
    .bpa-msg.bpa-user { align-items: flex-end; align-self: flex-end; }

    .bpa-msg-bubble {
      padding: 10px 13px;
      border-radius: 14px;
      font-size: .83rem;
      line-height: 1.55;
      word-break: break-word;
    }
    .bpa-bot .bpa-msg-bubble {
      background: #fff;
      color: #1e293b;
      border-bottom-left-radius: 4px;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
    }
    .bpa-user .bpa-msg-bubble {
      background: linear-gradient(135deg, #e63946, #c41230);
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .bpa-msg-time {
      font-size: .62rem;
      color: #94a3b8;
      margin-top: 3px;
      padding: 0 3px;
    }

    /* Sources chips */
    .bpa-sources {
      display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px;
    }
    .bpa-source-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 100px;
      background: #fff; border: 1px solid #e2e8f0;
      font-size: .68rem; font-weight: 600; color: #475569;
      white-space: nowrap;
    }
    .bpa-source-chip .bpa-stock-ok  { color: #16a34a; }
    .bpa-source-chip .bpa-stock-nok { color: #dc2626; }

    /* Typing indicator */
    .bpa-typing {
      display: flex; gap: 4px; align-items: center;
      padding: 12px 14px;
      background: #fff;
      border-radius: 14px;
      border-bottom-left-radius: 4px;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
      width: fit-content;
    }
    .bpa-typing span {
      width: 7px; height: 7px; border-radius: 50%;
      background: #e63946; opacity: .4;
      animation: bpa-bounce .9s infinite;
    }
    .bpa-typing span:nth-child(2) { animation-delay: .15s; }
    .bpa-typing span:nth-child(3) { animation-delay: .3s; }

    /* Input area */
    #bpa-input-area {
      padding: 10px 12px;
      border-top: 1px solid #f1f5f9;
      background: #fff;
      display: flex;
      align-items: flex-end;
      gap: 8px;
      flex-shrink: 0;
    }
    #bpa-input {
      flex: 1;
      min-height: 38px;
      max-height: 110px;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      padding: 9px 13px;
      font-size: .83rem;
      line-height: 1.4;
      resize: none;
      outline: none;
      font-family: inherit;
      transition: border-color .15s;
    }
    #bpa-input:focus { border-color: #e63946; }
    #bpa-send-btn {
      width: 38px; height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e63946, #9d0208);
      border: none;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      transition: transform .15s, box-shadow .15s;
      box-shadow: 0 2px 8px rgba(230,57,70,.35);
    }
    #bpa-send-btn:hover { transform: scale(1.08); box-shadow: 0 4px 14px rgba(230,57,70,.45); }
    #bpa-send-btn:disabled { opacity: .5; pointer-events: none; }

    /* Footer branding */
    #bpa-footer {
      padding: 5px 14px 8px;
      text-align: center;
      font-size: .62rem;
      color: #cbd5e1;
      background: #fff;
      flex-shrink: 0;
    }
    #bpa-footer .bpa-wa-link {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: 4px;
      padding: 5px 12px;
      border-radius: 100px;
      background: #ecfdf5;
      color: #15803d;
      font-size: .72rem;
      font-weight: 700;
      text-decoration: none;
      transition: background .15s;
    }
    #bpa-footer .bpa-wa-link:hover { background: #d1fae5; }

    @keyframes bpa-bounce {
      0%, 60%, 100% { transform: translateY(0); opacity: .4; }
      30% { transform: translateY(-5px); opacity: 1; }
    }
    @keyframes bpa-pulse {
      0%, 100% { opacity: 1; } 50% { opacity: .4; }
    }
  `;
  document.head.appendChild(style);

  /* ── DOM: FAB button ── */
  const fab = document.createElement('button');
  fab.id = 'bpa-fab';
  fab.setAttribute('aria-label', 'Deschide chat');
  fab.innerHTML = `
    <svg id="bpa-fab-icon" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <svg id="bpa-fab-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
    <div class="bpa-badge" id="bpa-badge">1</div>
  `;
  document.body.appendChild(fab);

  /* ── DOM: Widget ── */
  const widget = document.createElement('div');
  widget.id = 'bpa-chat-widget';
  widget.setAttribute('role', 'dialog');
  widget.setAttribute('aria-label', 'Chat asistent');
  widget.innerHTML = `
    <div id="bpa-header">
      <div class="bpa-avatar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="10" rx="2"/>
          <circle cx="12" cy="5" r="2"/>
          <path d="M12 7v4M8 15h.01M16 15h.01"/>
        </svg>
      </div>
      <div class="bpa-info">
        <div class="bpa-name">${COMPANY}</div>
        <div class="bpa-sub">
          <span class="bpa-online-dot"></span>
          Asistent AI · WhatsApp · catalog &amp; comenzi
        </div>
      </div>
      <button id="bpa-close-btn" aria-label="Inchide">&times;</button>
    </div>

    <div id="bpa-messages"></div>

    <div id="bpa-input-area">
      <textarea id="bpa-input" rows="1" placeholder="Scrie un mesaj..." maxlength="800"></textarea>
      <button id="bpa-send-btn" aria-label="Trimite">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
    <div id="bpa-footer">
      Powered by AI · Groq · ${COMPANY}
      <br><a class="bpa-wa-link" href="${WA_URL}" target="_blank" rel="noopener noreferrer" aria-label="Continuă conversația pe WhatsApp">Continuă pe WhatsApp</a>
    </div>
  `;
  document.body.appendChild(widget);

  /* ── Referinte DOM ── */
  const messagesEl = document.getElementById('bpa-messages');
  const inputEl    = document.getElementById('bpa-input');
  const sendBtn    = document.getElementById('bpa-send-btn');
  const badge      = document.getElementById('bpa-badge');
  let isOpen = false;
  let isWaiting = false;

  /* ── Util ── */
  function now() {
    return new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
  }

  function addMessage(role, text, sources) {
    const wrap = document.createElement('div');
    wrap.className = `bpa-msg bpa-${role}`;

    const bubble = document.createElement('div');
    bubble.className = 'bpa-msg-bubble';
    bubble.textContent = text;

    const time = document.createElement('div');
    time.className = 'bpa-msg-time';
    time.textContent = now();

    wrap.appendChild(bubble);
    wrap.appendChild(time);

    if (sources && sources.length) {
      const sc = document.createElement('div');
      sc.className = 'bpa-sources';
      sources.forEach(s => {
        const chip = document.createElement('div');
        chip.className = 'bpa-source-chip';
        const stockOk = s.stock > 0;
        chip.innerHTML = `
          <span>${s.name}</span>
          ${s.price ? `<span style="color:#64748b;">· ${s.price}</span>` : ''}
          <span class="${stockOk ? 'bpa-stock-ok' : 'bpa-stock-nok'}">${stockOk ? '✓' : '✗'}</span>
        `;
        sc.appendChild(chip);
      });
      wrap.appendChild(sc);
    }

    messagesEl.appendChild(wrap);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function showTyping() {
    const el = document.createElement('div');
    el.className = 'bpa-msg bpa-bot';
    el.id = 'bpa-typing-indicator';
    el.innerHTML = `<div class="bpa-typing"><span></span><span></span><span></span></div>`;
    messagesEl.appendChild(el);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function hideTyping() {
    document.getElementById('bpa-typing-indicator')?.remove();
  }

  /* ── Deschide/inchide ── */
  function openWidget() {
    isOpen = true;
    widget.classList.add('open');
    document.getElementById('bpa-fab-icon').style.display = 'none';
    document.getElementById('bpa-fab-close').style.display = '';
    badge.classList.remove('visible');
    inputEl.focus();
    if (!messagesEl.children.length) {
      addMessage('bot',
        'Buna ziua! 👋 Sunt asistentul AI al ' + COMPANY + '.\n\nVa pot ajuta cu:\n• Cautare piese auto (OEM, VIN)\n• Verificare stoc si preturi\n• Informatii despre comenzi si livrare\n• Consultanta bazata pe istoricul comenzilor\n\nCe va pot ajuta astazi?'
      );
    }
  }

  function closeWidget() {
    isOpen = false;
    widget.classList.remove('open');
    document.getElementById('bpa-fab-icon').style.display = '';
    document.getElementById('bpa-fab-close').style.display = 'none';
  }

  fab.addEventListener('click', () => isOpen ? closeWidget() : openWidget());
  document.getElementById('bpa-close-btn').addEventListener('click', closeWidget);

  window.bpaOpenChat = openWidget;
  window.bpaCloseChat = closeWidget;

  /* ── Trimite mesaj ── */
  async function sendMessage() {
    const text = inputEl.value.trim();
    if (!text || isWaiting) return;

    isWaiting = true;
    sendBtn.disabled = true;
    inputEl.value = '';
    inputEl.style.height = '';

    addMessage('user', text);
    showTyping();

    try {
      const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, session_id: sid })
      });
      const data = await res.json();
      if (data.session_id) {
        sid = data.session_id;
        localStorage.setItem('bpa_widget_sid', sid);
      }
      hideTyping();
      addMessage('bot', data.reply || 'Eroare. Incercati din nou.', data.sources || []);
    } catch (e) {
      hideTyping();
      addMessage('bot', 'Conexiune intrerupta. Verificati internetul si incercati din nou.');
    }

    isWaiting = false;
    sendBtn.disabled = false;
    inputEl.focus();
  }

  sendBtn.addEventListener('click', sendMessage);
  inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  /* Auto-resize textarea */
  inputEl.addEventListener('input', () => {
    inputEl.style.height = '';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 110) + 'px';
  });

  /* Badge dupa 5s daca nu s-a deschis */
  setTimeout(() => {
    if (!isOpen) badge.classList.add('visible');
  }, 5000);

})();
