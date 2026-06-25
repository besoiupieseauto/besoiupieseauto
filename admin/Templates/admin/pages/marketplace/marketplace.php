<?php
require __DIR__ . '/../_partials/fz-surface-styles.php';

use Evasystem\Services\PieseAuto\PieseAutoRobotConfig;
use Evasystem\Services\PieseAuto\PieseAutoStatusService;

$paStatus = (new PieseAutoStatusService())->snapshot('besoiu', false);
$mpRobotCfg = PieseAutoRobotConfig::adminJsConfig();
$mpScopedCont = PieseAutoRobotConfig::scopedContId('besoiu');
$accountsCount = (int) ($paStatus['accounts_count'] ?? 0);
$serviceOnline = (bool) ($paStatus['service_online'] ?? false);
$browserOpen = (bool) ($paStatus['browser_open'] ?? false);
$platformConnected = (bool) ($paStatus['platform_connected'] ?? false);
$serviceLabel = (string) ($paStatus['service_label'] ?? '127.0.0.1:5011');
$platformPage = (string) ($paStatus['platform_page'] ?? '');
$configured = (bool) ($paStatus['configured'] ?? false);
$ready = (bool) ($paStatus['ready'] ?? false);

$browserHint = $browserOpen ? 'Fereastră Chrome robot' : ($serviceOnline ? 'Apasă «Pornează browser» în panou' : '—');
$platformHint = $platformConnected
    ? (preg_replace('#^https?://(www\.)?#i', '', $platformPage) ?: 'pieseauto.ro/contul-meu')
    : ($browserOpen ? 'Se face login...' : 'Login necesar');

function mp_status_class(bool $ok, bool $warn = false): string {
    if ($ok) {
        return 'ok';
    }
    return $warn ? 'warn' : 'bad';
}
?>

<style>
  .fz-card-status {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin: 12px 0 0;
    padding-top: 12px;
    border-top: 1px solid #eef2f7;
  }
  .fz-card-status-item {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 10px;
    background: #fafbfc;
    min-width: 0;
  }
  .fz-card-status-item.is-ok { border-color: #86efac; background: #f0fdf4; }
  .fz-card-status-item.is-warn { border-color: #fde68a; background: #fffbeb; }
  .fz-card-status-item.is-bad { border-color: #fca5a5; background: #fef2f2; }
  .fz-card-status-label { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
  .fz-card-status-value { font-size: .78rem; font-weight: 800; color: #0f172a; margin-top: 2px; }
  .fz-card-status-hint { font-size: .65rem; color: #64748b; margin-top: 2px; word-break: break-all; }
  @media (max-width: 768px) { .fz-card-status { grid-template-columns: 1fr; } }
  .fz-card-ready-banner {
    display: none;
    margin: 0 0 12px;
    padding: 10px 14px;
    border-radius: 10px;
    background: #dcfce7;
    border: 1px solid #86efac;
    color: #166534;
    font-size: .82rem;
    font-weight: 700;
  }
  .fz-card-ready-banner.is-visible { display: block; }
  #mpPaCard.is-ready { border-color: #86efac; box-shadow: 0 0 0 1px #bbf7d0; }
</style>

<div class="furnizori-page">
  <div class="fz-header">
    <div>
      <h2 class="fz-title">Marketplace</h2>
      <p class="fz-subtitle">Platforme externe Besoiu — publicare anunțuri și sincronizare stoc.</p>
    </div>
  </div>

  <p class="fz-summary-caption">Rezumat marketplace</p>
  <div class="fz-summary-metrics" role="status">
    <div class="fz-metric">
      <span class="fz-metric-label">Platforme active</span>
      <span class="fz-metric-value" id="mpPlatformsActive"><?= $ready ? 1 : 0 ?></span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Produse magazie</span>
      <span class="fz-metric-value">0</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Anunțuri publicate</span>
      <span class="fz-metric-value">0</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Conturi salvate</span>
      <span class="fz-metric-value" id="mpAccountsCount"><?= $accountsCount ?></span>
    </div>
  </div>

  <div class="fz-grid">
    <a href="/admin/marketplace-pieseauto" class="fz-card is-link<?= $ready ? ' is-ready' : '' ?>" id="mpPaCard">
      <div class="fz-card-ready-banner<?= $ready ? ' is-visible' : '' ?>" id="mpPaReadyBanner">
        Totul e conectat — poți publica anunțuri pe PieseAuto.ro.
      </div>
      <div class="fz-card-head">
        <div class="fz-card-avatar">PA</div>
        <div class="fz-card-head-content">
          <h3 class="fz-card-name">PieseAuto.ro</h3>
          <p class="fz-card-desc">Robot publicare — cont, magazie produse Besoiu, anunț în browser.</p>
          <div class="fz-card-badges" id="mpPaBadges">
            <?php if ($ready): ?>
              <span class="fz-badge" style="background:#dcfce7;color:#166534;">Gata de publicare</span>
            <?php elseif ($configured): ?>
              <span class="fz-badge fz-badge--muted">Cont salvat</span>
            <?php else: ?>
              <span class="fz-badge fz-badge--muted">Neconfigurat</span>
            <?php endif; ?>
            <span class="fz-badge fz-badge--muted">Robot Python</span>
          </div>
        </div>
      </div>

      <div class="fz-card-status" id="mpPaStatus" role="status" aria-live="polite">
        <div class="fz-card-status-item is-<?= mp_status_class($serviceOnline) ?>" id="mpTilePort">
          <div class="fz-card-status-label">Port serviciu</div>
          <div class="fz-card-status-value" id="mpValPort"><?= $serviceOnline ? 'DESCHIS' : 'ÎNCHIS' ?></div>
          <div class="fz-card-status-hint" id="mpHintPort"><?= htmlspecialchars($serviceLabel, ENT_QUOTES) ?></div>
        </div>
        <div class="fz-card-status-item is-<?= mp_status_class($browserOpen, $serviceOnline) ?>" id="mpTileBrowser">
          <div class="fz-card-status-label">Browser Chrome</div>
          <div class="fz-card-status-value" id="mpValBrowser"><?= $browserOpen ? 'DESCHIS' : 'ÎNCHIS' ?></div>
          <div class="fz-card-status-hint" id="mpHintBrowser"><?= htmlspecialchars($browserHint, ENT_QUOTES) ?></div>
        </div>
        <div class="fz-card-status-item is-<?= mp_status_class($platformConnected, $browserOpen) ?>" id="mpTilePlatform">
          <div class="fz-card-status-label">PieseAuto.ro</div>
          <div class="fz-card-status-value" id="mpValPlatform"><?= $platformConnected ? 'CONECTAT' : 'NELOGAT' ?></div>
          <div class="fz-card-status-hint" id="mpHintPlatform"><?= htmlspecialchars($platformHint, ENT_QUOTES) ?></div>
        </div>
      </div>

      <div class="fz-metrics">
        <div class="fz-metric">
          <span class="fz-metric-label">Magazie</span>
          <span class="fz-metric-value">0 piese</span>
        </div>
        <div class="fz-metric">
          <span class="fz-metric-label">Publicate</span>
          <span class="fz-metric-value">0</span>
        </div>
      </div>
      <div class="fz-card-foot" id="mpPaFoot"><?= $ready ? 'Totul conectat — deschide panoul →' : 'Configurează platforma →' ?></div>
    </a>

    <a href="/admin/marketplace-baselinker" class="fz-card is-link" id="mpBlCard">
      <div class="fz-card-head">
        <div class="fz-card-avatar" style="background:#ecfdf5;color:#0f766e;">BL</div>
        <div class="fz-card-head-content">
          <h3 class="fz-card-name">BaseLinker</h3>
          <p class="fz-card-desc">API catalog produse — token, test conexiune, mapare câmpuri și sincronizare stoc.</p>
          <div class="fz-card-badges">
            <span class="fz-badge fz-badge--muted">API REST</span>
            <span class="fz-badge fz-badge--muted">Catalog produse</span>
          </div>
        </div>
      </div>
      <div class="fz-metrics">
        <div class="fz-metric">
          <span class="fz-metric-label">Comenzi</span>
          <span class="fz-metric-value">Sync automat</span>
        </div>
        <div class="fz-metric">
          <span class="fz-metric-label">Produse</span>
          <span class="fz-metric-value">Batch manual</span>
        </div>
      </div>
      <div class="fz-card-foot">Configurează BaseLinker →</div>
    </a>
  </div>
</div>

<script>
(function () {
  const MP_CFG = <?= json_encode(array_merge($mpRobotCfg, ['scoped_cont' => $mpScopedCont]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const MP_INIT = <?= json_encode([
      'service_online' => $serviceOnline,
      'browser_open' => $browserOpen,
      'platform_connected' => $platformConnected,
      'service_label' => $serviceLabel,
      'platform_page' => $platformPage !== '' ? $platformPage : ($platformConnected ? 'pieseauto.ro/contul-meu' : ''),
      'robot_message' => (string) ($paStatus['robot_message'] ?? ''),
      'configured' => $configured,
      'accounts_count' => $accountsCount,
      'ready' => $ready,
  ], JSON_UNESCAPED_UNICODE) ?>;
  const STATUS_POLL_MS = 15000;
  const STALE_AFTER_MS = 45000;
  let pollInFlight = false;
  let pollTimer = null;
  const ngrokHeaders = {
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': '69420',
    'X-Robot-Channel': MP_CFG.channel_header || 'besoiu',
  };
  const STABLE = {
    service_online: !!MP_INIT.service_online,
    browser_open: !!MP_INIT.browser_open,
    platform_connected: !!MP_INIT.platform_connected,
    service_label: MP_INIT.service_label || '127.0.0.1:5011',
    platform_page: MP_INIT.platform_page || '',
    robot_message: MP_INIT.robot_message || '',
    configured: !!MP_INIT.configured,
    accounts_count: Number(MP_INIT.accounts_count || 0),
    failStreak: 0,
    renderKey: '',
    lastOkAt: 0,
  };

  function robotStatusLooksLoggedIn(text) {
    return /logat|conectat|succes|recuperat|deja logat|🏁|✅/i.test(String(text || ''));
  }

  function shortenUrl(url) {
    return String(url || '').replace(/^https?:\/\/(www\.)?/i, '').split('?')[0] || 'pieseauto.ro/contul-meu';
  }

  function mergeLiveTextIntoView(view, liveText) {
    const st = String(liveText || '').trim();
    if (!st || st === 'Inactiv') return view;
    view.robot_message = st;
    if (robotStatusLooksLoggedIn(st)) {
      view.platform_connected = true;
      view.browser_open = true;
      view.platform_page = view.platform_page || 'pieseauto.ro/contul-meu';
      view.ready = !!(view.service_online && view.browser_open && view.platform_connected);
    } else if (/browser deja activ|robotul este deja activ|chrome activ|sesiune existent|pornire chrome|deschid pieseauto|pregătire chrome|pregatire chrome|navigăm|navigam|lansat|singură fereastră/i.test(st)) {
      view.browser_open = true;
      view.service_online = true;
    }
    return view;
  }

  function robotProxyUrl(path) {
    return (MP_CFG.proxy || '/admin/api/robot_pieseauto_proxy.php') + '?path=' + encodeURIComponent(path);
  }

  async function robotFetchMp(path, timeoutMs) {
    const ms = timeoutMs || 5500;
    const headers = ngrokHeaders;
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), ms);
    const opts = { headers, signal: ctrl.signal };

    try {
      if (MP_CFG.direct_pieseauto) {
        try {
          const res = await fetch(MP_CFG.direct_pieseauto + path, opts);
          if (res.ok) return res;
        } catch (e) { /* tunel */ }
      }
      if (location.protocol === 'http:' && MP_CFG.local_robot) {
        try {
          const res = await fetch(MP_CFG.local_robot + path, opts);
          if (res.ok) return res;
        } catch (e) { /* local */ }
      }
      const res = await fetch(robotProxyUrl(path), opts);
      return res.ok ? res : null;
    } catch (e) {
      return null;
    } finally {
      clearTimeout(timer);
    }
  }

  async function fetchLiveRobotData() {
    const contId = MP_CFG.scoped_cont || 'besoiu_besoiu';
    try {
      const res = await robotFetchMp('/stare_completa?cont_id=' + encodeURIComponent(contId), 5500);
      if (!res) return { liveMsg: '', stare: null };
      const stare = await res.json();
      const liveMsg = String(stare.mesaj || '');
      return { liveMsg, stare };
    } catch (e) {
      return { liveMsg: '', stare: null };
    }
  }

  function normalizeMpStatus(data) {
    const robotMsg = String((data && data.robot_message) || '');
    const loggedHint = robotStatusLooksLoggedIn(robotMsg);
    const platformOk = !!(data && (data.platform_connected || loggedHint));
    const browserOk = !!(data && (data.browser_open || (platformOk && loggedHint)));
    return {
      status: 'ok',
      service_online: !!(data && data.service_online),
      browser_open: browserOk,
      platform_connected: platformOk,
      service_label: (data && data.service_label) || STABLE.service_label || '127.0.0.1:5011',
      platform_page: (data && data.platform_page) || (platformOk ? 'pieseauto.ro/contul-meu' : ''),
      robot_message: robotMsg,
      configured: !!(data && data.configured),
      accounts_count: Number((data && data.accounts_count) || 0),
      ready: !!(data && data.service_online && browserOk && platformOk),
    };
  }

  function stabilizeView(view) {
    const out = Object.assign({}, view);
    const downgrade = /oprit|eroare|eșuat|esuat|logout|parola gre/i.test(out.robot_message || '');
    if (STABLE.platform_connected && !out.platform_connected && !downgrade) {
      out.platform_connected = true;
      out.browser_open = true;
      out.ready = !!(out.service_online && out.browser_open && out.platform_connected);
      if (!out.platform_page) out.platform_page = STABLE.platform_page || 'pieseauto.ro/contul-meu';
    }
    if (STABLE.browser_open && !out.browser_open && !downgrade && out.service_online) {
      out.browser_open = true;
    }
    if (STABLE.service_online && !out.service_online && STABLE.failStreak < 5) {
      out.service_online = true;
      out.service_label = STABLE.service_label || out.service_label;
    }
    return out;
  }

  function rememberStable(view) {
    STABLE.service_online = !!view.service_online;
    STABLE.browser_open = !!view.browser_open;
    STABLE.platform_connected = !!view.platform_connected;
    STABLE.service_label = view.service_label || STABLE.service_label;
    STABLE.platform_page = view.platform_page || STABLE.platform_page;
    STABLE.robot_message = view.robot_message || STABLE.robot_message;
    STABLE.configured = !!view.configured;
    STABLE.accounts_count = Number(view.accounts_count || 0);
  }

  function setMpTile(tileId, valId, hintId, state, value, hint) {
    const tile = document.getElementById(tileId);
    const val = document.getElementById(valId);
    const hintEl = document.getElementById(hintId);
    const tileClass = 'fz-card-status-item is-' + state;
    if (tile && tile.className !== tileClass) tile.className = tileClass;
    if (val && val.textContent !== value) val.textContent = value;
    if (hintEl && hint && hintEl.textContent !== hint) hintEl.textContent = hint;
  }

  function renderMpStatus(view) {
    const renderKey = [
      view.service_online ? 1 : 0,
      view.browser_open ? 1 : 0,
      view.platform_connected ? 1 : 0,
      view.service_label,
      view.platform_page,
    ].join('|');
    if (renderKey === STABLE.renderKey) {
      rememberStable(view);
      return;
    }
    STABLE.renderKey = renderKey;
    rememberStable(view);

    setMpTile('mpTilePort', 'mpValPort', 'mpHintPort',
      view.service_online ? 'ok' : 'bad',
      view.service_online ? 'DESCHIS' : 'ÎNCHIS',
      view.service_label || '127.0.0.1:5011'
    );
    setMpTile('mpTileBrowser', 'mpValBrowser', 'mpHintBrowser',
      view.browser_open ? 'ok' : (view.service_online ? 'warn' : 'bad'),
      view.browser_open ? 'DESCHIS' : 'ÎNCHIS',
      view.browser_open ? 'Fereastră Chrome robot' : (view.service_online ? 'Apasă «Pornează browser» în panou' : '—')
    );
    setMpTile('mpTilePlatform', 'mpValPlatform', 'mpHintPlatform',
      view.platform_connected ? 'ok' : (view.browser_open ? 'warn' : 'bad'),
      view.platform_connected ? 'CONECTAT' : 'NELOGAT',
      view.platform_connected ? shortenUrl(view.platform_page) : (view.browser_open ? 'Se face login...' : 'Login necesar')
    );

    const badges = document.getElementById('mpPaBadges');
    const foot = document.getElementById('mpPaFoot');
    const platforms = document.getElementById('mpPlatformsActive');
    const accounts = document.getElementById('mpAccountsCount');
    const card = document.getElementById('mpPaCard');
    const banner = document.getElementById('mpPaReadyBanner');

    if (accounts) accounts.textContent = String(view.accounts_count || 0);
    if (platforms) platforms.textContent = view.ready ? '1' : '0';
    if (foot) {
      foot.textContent = view.ready
        ? 'Totul conectat — deschide panoul →'
        : 'Configurează platforma →';
    }
    if (card) card.classList.toggle('is-ready', !!view.ready);
    if (banner) banner.classList.toggle('is-visible', !!view.ready);

    if (badges) {
      let mainBadge = '<span class="fz-badge fz-badge--muted">Neconfigurat</span>';
      if (view.ready) {
        mainBadge = '<span class="fz-badge" style="background:#dcfce7;color:#166534;">Gata de publicare</span>';
      } else if (view.configured) {
        mainBadge = '<span class="fz-badge fz-badge--muted">Cont salvat</span>';
      } else if (view.service_online && !view.browser_open) {
        mainBadge = '<span class="fz-badge" style="background:#fef3c7;color:#92400e;">Serviciu pornit</span>';
      } else if (view.platform_connected) {
        mainBadge = '<span class="fz-badge" style="background:#dcfce7;color:#166534;">Conectat</span>';
      }
      badges.innerHTML = mainBadge + '<span class="fz-badge fz-badge--muted">Robot Python</span>';
    }
  }

  function viewFromStare(stare, liveMsg, baseView) {
    const view = normalizeMpStatus({
      status: 'ok',
      service_online: stare.service_online !== false,
      browser_open: !!(stare.browser_open || stare.browser_active),
      platform_connected: !!stare.platform_connected,
      service_label: stare.service_port ? ('127.0.0.1:' + stare.service_port) : baseView.service_label,
      platform_page: stare.page_url || baseView.platform_page,
      robot_message: liveMsg || stare.mesaj || baseView.robot_message,
      configured: baseView.configured,
      accounts_count: baseView.accounts_count,
    });
    return mergeLiveTextIntoView(view, liveMsg || stare.mesaj || '');
  }

  async function refreshMarketplacePaStatus() {
    if (pollInFlight) return;
    pollInFlight = true;
    try {
      const baseView = normalizeMpStatus({
        status: 'ok',
        service_online: STABLE.service_online,
        browser_open: STABLE.browser_open,
        platform_connected: STABLE.platform_connected,
        service_label: STABLE.service_label,
        platform_page: STABLE.platform_page,
        robot_message: STABLE.robot_message,
        configured: STABLE.configured,
        accounts_count: STABLE.accounts_count,
      });

      const live = await fetchLiveRobotData();
      let view = baseView;

      if (live.stare && typeof live.stare === 'object') {
        view = viewFromStare(live.stare, live.liveMsg, baseView);
        STABLE.failStreak = 0;
        STABLE.lastOkAt = Date.now();
      } else {
        STABLE.failStreak += 1;
        if (STABLE.lastOkAt && (Date.now() - STABLE.lastOkAt) < STALE_AFTER_MS) {
          view = stabilizeView(baseView);
          renderMpStatus(view);
          return;
        }
      }

      view = stabilizeView(view);
      renderMpStatus(view);
    } finally {
      pollInFlight = false;
    }
  }

  function scheduleMarketplacePoll() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshMarketplacePaStatus, STATUS_POLL_MS);
  }

  renderMpStatus(stabilizeView(normalizeMpStatus(MP_INIT)));
  STABLE.lastOkAt = MP_INIT.service_online ? Date.now() : 0;
  if (document.visibilityState === 'visible') {
    setTimeout(refreshMarketplacePaStatus, 400);
  }
  scheduleMarketplacePoll();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      refreshMarketplacePaStatus();
      scheduleMarketplacePoll();
    } else if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  });
})();
</script>
