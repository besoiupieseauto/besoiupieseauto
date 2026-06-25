<?php
/** Panou publicare PieseAuto — BESOIU PIESE AUTO */
$scannedCount = count($scannedProducts);
$defaultTarget = $defaultTarget ?? 'besoiu';
$paInit = $paSnapshot ?? [];
$initServiceOnline = (bool) ($paInit['service_online'] ?? false);
$initBrowserOpen = (bool) ($paInit['browser_open'] ?? false);
$initPlatformConnected = (bool) ($paInit['platform_connected'] ?? false);
$initReady = (bool) ($paInit['ready'] ?? false);
$initConfigured = (bool) ($paInit['configured'] ?? false);

function pa_tile_state(bool $ok, bool $warn = false): string {
    if ($ok) {
        return 'ok';
    }
    return $warn ? 'warn' : 'bad';
}
?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .pa-atelier {
            --pa-primary: #2563eb;
            --pa-primary-dark: #1d4ed8;
            --pa-primary-soft: rgba(37,99,235,.12);
            --pa-border: #e5e7eb;
            --pa-bg: #f8fafc;
            --pa-ink: #0f172a;
            --pa-muted: #64748b;
            --pa-danger: #dc2626;
            --pa-success: #16a34a;
            max-width: 100%;
            overflow-x: clip;
            margin-top: 16px;
            font-size: 14px;
            color: var(--pa-ink);
        }
        .pa-atelier * { box-sizing: border-box; }
        .pa-atelier .pa-flex { display: flex; align-items: center; gap: 8px; }
        .pa-atelier .pa-flex-between { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .pa-atelier .pa-flex-start { display: flex; align-items: flex-start; gap: 12px; }
        .pa-atelier .pa-flex-wrap { display: flex; flex-wrap: wrap; gap: 8px; }
        .pa-atelier .pa-flex-1 { flex: 1; min-width: 0; }
        .pa-atelier .pa-hidden { display: none !important; }
        .pa-atelier .pa-mt-1 { margin-top: 4px; }
        .pa-atelier .pa-mt-2 { margin-top: 8px; }
        .pa-atelier .pa-mt-3 { margin-top: 12px; }
        .pa-atelier .pa-mb-2 { margin-bottom: 8px; }
        .pa-atelier .pa-text-muted { color: var(--pa-muted); font-size: .78rem; }
        .pa-atelier .pa-text-small { font-size: .78rem; }
        .pa-atelier .pa-text-danger { color: var(--pa-danger); }
        .pa-atelier .pa-text-success { color: var(--pa-success); font-weight: 700; }
        .pa-atelier .pa-fw-bold { font-weight: 700; }
        .pa-atelier .pa-text-break { word-break: break-word; }
        .pa-atelier .pa-text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pa-atelier .pa-badge {
            display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px;
            font-size: .72rem; font-weight: 700; background: #e2e8f0; color: #475569;
        }
        .pa-atelier .pa-badge--ok { background: #dcfce7; color: #166534; }
        .pa-atelier .pa-badge--warn { background: #fef3c7; color: #92400e; }
        .pa-atelier .pa-badge--danger { background: #fee2e2; color: #991b1b; }
        .pa-atelier .pa-ms-auto { margin-left: auto; }
        .pa-atelier .pa-input,
        .pa-atelier .pa-textarea,
        .pa-atelier .pa-select {
            width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;
            color: var(--pa-ink); font-size: .82rem; padding: 0 10px; outline: none;
        }
        .pa-atelier .pa-input { height: 34px; }
        .pa-atelier .pa-input--sm { height: 32px; font-size: .78rem; }
        .pa-atelier .pa-textarea { padding: 8px 10px; min-height: 72px; resize: vertical; }
        .pa-atelier .pa-select { height: 34px; }
        .pa-atelier .pa-input:focus,
        .pa-atelier .pa-textarea:focus,
        .pa-atelier .pa-select:focus { border-color: var(--pa-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .pa-atelier .pa-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 34px; padding: 0 12px; border-radius: 8px; border: 1px solid transparent;
            font-size: .78rem; font-weight: 700; cursor: pointer; transition: .15s ease;
        }
        .pa-atelier .pa-btn--primary { background: var(--pa-primary); border-color: var(--pa-primary); color: #fff; }
        .pa-atelier .pa-btn--primary:hover { background: var(--pa-primary-dark); border-color: var(--pa-primary-dark); }
        .pa-atelier .pa-btn--outline { background: #fff; border-color: var(--pa-primary); color: var(--pa-primary); }
        .pa-atelier .pa-btn--outline:hover { background: var(--pa-primary-soft); }
        .pa-atelier .pa-btn--danger { background: #fff; border-color: #fca5a5; color: var(--pa-danger); }
        .pa-atelier .pa-btn--danger:hover { background: #fef2f2; }
        .pa-atelier .pa-btn--ghost { background: transparent; border-color: #cbd5e1; color: #475569; }
        .pa-atelier .pa-btn--link { background: transparent; border: none; color: var(--pa-danger); height: auto; padding: 0; }
        .pa-atelier .pa-btn--block { width: 100%; }
        .pa-atelier .pa-btn--flex { flex: 1; }
        .pa-atelier .pa-input-group { display: flex; gap: 0; }
        .pa-atelier .pa-input-group .pa-input { border-radius: 8px 0 0 8px; }
        .pa-atelier .pa-input-group .pa-btn { border-radius: 0 8px 8px 0; }
        .pa-atelier .pa-form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .pa-atelier .pa-form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .pa-atelier .pa-grid-main { display: grid; grid-template-columns: minmax(280px, 1fr) minmax(0, 2fr); gap: 16px; align-items: start; }
        .pa-dash { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
        .pa-dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .85rem 1rem;
            align-items: end;
        }
        .pa-dash-actions {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .pa-status-bar {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eef2f7;
        }
        .pa-status-tile {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: .75rem .85rem;
            background: #fafbfc;
            min-width: 0;
        }
        .pa-status-tile.is-ok { border-color: #86efac; background: #f0fdf4; }
        .pa-status-tile.is-warn { border-color: #fde68a; background: #fffbeb; }
        .pa-status-tile.is-bad { border-color: #fca5a5; background: #fef2f2; }
        .pa-status-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .pa-status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; background: #94a3b8; }
        .pa-status-dot--ok { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
        .pa-status-dot--warn { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); }
        .pa-status-dot--bad { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.2); }
        .pa-status-name { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--pa-muted); }
        .pa-status-value { font-size: .92rem; font-weight: 800; color: var(--pa-ink); }
        .pa-status-hint { font-size: .7rem; color: var(--pa-muted); margin-top: 2px; word-break: break-all; }
        .pa-ready-banner {
            display: none;
            align-items: center;
            gap: 8px;
            margin-bottom: 1rem;
            padding: .65rem .85rem;
            border-radius: 10px;
            background: #ecfdf5;
            border: 1px solid #86efac;
            color: #166534;
            font-size: .8rem;
            font-weight: 700;
        }
        .pa-ready-banner.is-visible { display: flex; }
        .pa-dash-label { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--pa-muted); margin-bottom: .35rem; }
        .pa-dash-value { font-size: .95rem; font-weight: 700; color: var(--pa-ink); word-break: break-word; }
        .pa-flow { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eef2f7; }
        .pa-flow-step { font-size: .75rem; font-weight: 700; padding: .35rem .75rem; border-radius: 999px; background: #f1f5f9; color: var(--pa-muted); white-space: nowrap; }
        .pa-flow-step.is-done { background: var(--pa-primary-soft); color: var(--pa-primary); }
        .pa-flow-arrow { color: #cbd5e1; font-size: .8rem; }
        .pa-station { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; margin-bottom: 1rem; max-width: 100%; }
        .pa-station-head { padding: .85rem 1rem; background: #fafbfc; border-bottom: 1px solid #eef2f7; border-left: 4px solid var(--pa-primary); }
        .pa-station-title { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--pa-muted); margin: 0; }
        .pa-station-sub { font-size: .78rem; color: #94a3b8; margin: .15rem 0 0; }
        .pa-station-body { padding: 1rem; }
        .pa-station--wide .pa-station-head { border-left-width: 4px; }
        .pa-btn-teal, .pa-btn-outline-teal { /* alias */ }
        .pa-atelier .pa-btn-teal { background: var(--pa-primary); border-color: var(--pa-primary); color: #fff; font-weight: 700; }
        .pa-atelier .pa-btn-teal:hover { background: var(--pa-primary-dark); border-color: var(--pa-primary-dark); color: #fff; }
        .pa-atelier .pa-btn-outline-teal { border: 1px solid var(--pa-primary); color: var(--pa-primary); background: #fff; font-weight: 700; }
        .pa-atelier .pa-btn-outline-teal:hover { background: var(--pa-primary-soft); color: var(--pa-primary); }
        .pa-pill:hover { border-color: var(--pa-primary); color: var(--pa-primary); }
        .pa-pill.is-active { background: var(--pa-primary-soft); border-color: var(--pa-primary); color: var(--pa-primary); }
        .pa-pill--new { border-style: dashed; color: var(--pa-muted); }
        #consoleBox { background: #0b1220; border-radius: 12px; padding: 14px; min-height: 140px; max-height: min(200px, 28vh); overflow-y: auto; border: 1px solid #1e293b; }
        .pa-account-pills { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
        .pa-pill { border: 1px solid #dbe3ea; background: #fff; border-radius: 999px; padding: .35rem .85rem; font-size: .78rem; font-weight: 700; color: var(--pa-ink); cursor: pointer; transition: .15s ease; max-width: 100%; overflow: hidden; text-overflow: ellipsis; }
        .console-line { font-family: 'Consolas', 'Fira Code', monospace; font-size: .78rem; margin-bottom: 5px; border-left: 2px solid var(--pa-primary); padding-left: 8px; color: #86efac; word-break: break-word; }
        .pulse-online { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; animation: pa-pulse 2s infinite; flex-shrink: 0; }
        @keyframes pa-pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.7); } 70% { box-shadow: 0 0 0 7px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        .pa-inventory { max-height: min(320px, 38vh); overflow-y: auto; padding-right: 4px; }
        .scan-item { transition: .15s ease; cursor: pointer; border-left: 3px solid transparent !important; }
        .scan-item:hover { background: var(--pa-primary-soft) !important; border-color: #dbe3ea !important; }
        .scan-selected { background: var(--pa-primary-soft) !important; border-color: var(--pa-primary) !important; border-left-color: var(--pa-primary) !important; }
        .pa-preview-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; background: #fafbfc; margin-bottom: 1rem; }
        .pa-preview-img { width: 72px; height: 72px; object-fit: contain; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; flex-shrink: 0; }
        .pa-preview-img-empty { width: 72px; height: 72px; border-radius: 10px; border: 1px dashed #cbd5e1; background: #fff; display: flex; align-items: center; justify-content: center; font-size: .7rem; color: #94a3b8; flex-shrink: 0; }
        .pa-form-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--pa-muted); margin-bottom: .3rem; }
        .pa-history-box { max-height: min(120px, 18vh); overflow-y: auto; border: 1px solid #eef2f7; border-radius: 10px; padding: .5rem; background: #fafbfc; }
        .pa-split { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 1rem; align-items: start; }
        .pa-split > div { min-width: 0; }
        .pa-form-row-price { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        @media (max-width: 1399px) {
            .pa-atelier .pa-grid-main { grid-template-columns: 1fr; }
            .pa-split { grid-template-columns: 1fr; }
            .pa-inventory { max-height: min(260px, 32vh); }
        }
        @media (max-width: 991px) {
            .pa-status-bar { grid-template-columns: 1fr; }
            .pa-dash-grid { grid-template-columns: 1fr 1fr; }
            .pa-dash-actions { grid-column: 1 / -1; justify-content: stretch; }
            .pa-dash-actions .pa-btn { flex: 1 1 auto; }
            .pa-flow .pa-ms-auto { margin-left: 0 !important; width: 100%; text-align: center; }
        }
        @media (max-width: 575px) {
            .pa-dash-grid { grid-template-columns: 1fr; }
            .pa-station-body { padding: .75rem; }
            .pa-form-row-price { grid-template-columns: 1fr; }
            .pa-atelier .pa-form-row-2 { grid-template-columns: 1fr; }
            .pa-atelier .pa-form-row-3 { grid-template-columns: 1fr; }
        }
    </style>

    <div class="pa-atelier">

        <!-- Stația 0: Panou de bord -->
        <div class="pa-dash">
            <div class="pa-ready-banner<?= $initReady ? ' is-visible' : '' ?>" id="paReadyBanner">
                <span class="pa-status-dot pa-status-dot--ok"></span>
                Totul e conectat — poți publica anunțuri pe PieseAuto.ro.
            </div>

            <div class="pa-status-bar" id="paStatusBar" role="status" aria-live="polite">
                <div class="pa-status-tile is-<?= pa_tile_state($initServiceOnline) ?>" id="tileService">
                    <div class="pa-status-head">
                        <span class="pa-status-dot pa-status-dot--<?= pa_tile_state($initServiceOnline) ?>" id="dotService"></span>
                        <span class="pa-status-name">① Port serviciu</span>
                    </div>
                    <div class="pa-status-value" id="valService"><?= $initServiceOnline ? 'DESCHIS' : 'ÎNCHIS' ?></div>
                    <div class="pa-status-hint" id="hintService"><?= htmlspecialchars($robotServiceLabel ?? '127.0.0.1:5011', ENT_QUOTES) ?></div>
                </div>
                <div class="pa-status-tile is-<?= pa_tile_state($initBrowserOpen, $initServiceOnline) ?>" id="tileBrowser">
                    <div class="pa-status-head">
                        <span class="pa-status-dot pa-status-dot--<?= pa_tile_state($initBrowserOpen, $initServiceOnline) ?>" id="dotBrowser"></span>
                        <span class="pa-status-name">② Browser Chrome</span>
                    </div>
                    <div class="pa-status-value" id="valBrowser"><?= $initBrowserOpen ? 'DESCHIS' : 'ÎNCHIS' ?></div>
                    <div class="pa-status-hint" id="hintBrowser"><?= $initBrowserOpen ? 'Fereastră Chrome activă' : 'Apasă «Pornează browser» sus' ?></div>
                </div>
                <div class="pa-status-tile is-<?= pa_tile_state($initPlatformConnected, $initBrowserOpen) ?>" id="tilePlatform">
                    <div class="pa-status-head">
                        <span class="pa-status-dot pa-status-dot--<?= pa_tile_state($initPlatformConnected, $initBrowserOpen) ?>" id="dotPlatform"></span>
                        <span class="pa-status-name">③ PieseAuto.ro</span>
                    </div>
                    <div class="pa-status-value" id="valPlatform"><?= $initPlatformConnected ? 'CONECTAT' : 'NELOGAT' ?></div>
                    <div class="pa-status-hint" id="hintPlatform"><?= $initPlatformConnected ? 'Sesiune activă pe PieseAuto' : ($initBrowserOpen ? 'Aștept login pe PieseAuto' : 'Deschide browserul mai întâi') ?></div>
                </div>
            </div>

            <div class="pa-dash-grid">
                <div class="pa-hidden">
                    <span id="globalStatus">—</span>
                    <span id="server-status-dot"></span>
                </div>
                <div>
                    <div class="pa-dash-label">Cont activ</div>
                    <div class="pa-dash-value" id="dashActiveAccount">—</div>
                </div>
                <div>
                    <div class="pa-dash-label">Target</div>
                    <div class="pa-dash-value" id="dashTarget"><?= htmlspecialchars($defaultTarget, ENT_QUOTES) ?></div>
                </div>
                <div>
                    <div class="pa-dash-label">Magazie</div>
                    <div class="pa-dash-value"><span id="scanate_count"><?= (int)$scannedCount ?></span> piese</div>
                </div>
                <div class="pa-dash-actions">
                    <button class="pa-btn pa-btn-teal" type="button" id="btnStartRobot" onclick="startRobot()">Pornează browser</button>
                    <button class="pa-btn pa-btn--outline" type="button" id="btnResetSession" onclick="resetSessionAndStartRobot()" title="Șterge profilul Chrome robot și login nou">Sesiune nouă</button>
                    <button class="pa-btn pa-btn--danger" type="button" id="btnStopRobot" onclick="stopTotalRobot()">Stop</button>
                </div>
            </div>
            <div class="pa-flow">
                <span class="pa-flow-step" id="flowStep1">1. Poarta cont</span>
                <span class="pa-flow-arrow">→</span>
                <span class="pa-flow-step" id="flowStep2">2. Robot browser</span>
                <span class="pa-flow-arrow">→</span>
                <span class="pa-flow-step" id="flowStep3">3. Anunț publicat</span>
                <span class="pa-ms-auto pa-badge<?= $initReady ? ' pa-badge--ok' : ($initPlatformConnected ? ' pa-badge--ok' : ($initBrowserOpen ? ' pa-badge--warn' : ($initServiceOnline ? ' pa-badge--warn' : ''))) ?>" id="bot-status-label"><?= $initReady ? 'GATA' : ($initPlatformConnected ? 'CONECTAT' : ($initBrowserOpen ? 'BROWSER' : ($initServiceOnline ? 'SERVICIU OK' : 'INACTIV'))) ?></span>
            </div>
        </div>

        <div class="pa-grid-main">
            <!-- Stânga: Stația 1 + 2 -->
            <div>
                <div class="pa-station">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 1 · Poarta cont</p>
                        <p class="pa-station-sub">Login PieseAuto și conturi salvate</p>
                    </div>
                    <div class="pa-station-body">
                        <form id="addpieseauto" data-endpoint="/admin/api/pieseauto_accounts_endpoint.php" data-method="POST">
                            <div class="pa-mb-2">
                                <label class="pa-form-label">Firmă</label>
                                <input type="text" name="company_name" class="pa-input pa-input--sm" id="accCompanyName" placeholder="Ex: BESOIU PIESE AUTO SRL" autocomplete="organization">
                            </div>
                            <div class="pa-form-row-2 pa-mb-2">
                                <div>
                                    <label class="pa-form-label">Email site</label>
                                    <input type="text" name="email" class="pa-input pa-input--sm" id="accEmail" autocomplete="username">
                                </div>
                                <div>
                                    <label class="pa-form-label">Parolă</label>
                                    <input type="password" name="pas" class="pa-input pa-input--sm" id="accPass" autocomplete="current-password">
                                </div>
                                <input type="hidden" name="type_product" value="add">
                                <input type="hidden" name="ridusers" id="ridusers" value="">
                            </div>
                            <div class="pa-mb-2">
                                <label class="pa-form-label">Utilizator target</label>
                                <input type="text" id="target-user" class="pa-input pa-input--sm" value="<?= htmlspecialchars($defaultTarget, ENT_QUOTES) ?>" placeholder="Ex: besoiu">
                            </div>
                            <button class="pa-btn pa-btn-teal pa-btn--block pa-mb-2" type="submit" id="btnSaveAccount">AUTENTIFICARE &amp; SALVARE</button>
                        </form>

                        <label class="pa-form-label">Conturi salvate</label>
                        <div class="pa-account-pills" id="accountPills">
                            <?php foreach ($accounts as $id => $info): ?>
                                <button type="button" class="pa-pill"
                                        data-client-id="<?= htmlspecialchars($id, ENT_QUOTES) ?>"
                                        data-id="<?= htmlspecialchars((string)$info['id'], ENT_QUOTES) ?>"
                                        data-name="<?= htmlspecialchars($info['name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($info['email'], ENT_QUOTES) ?>"
                                        data-pass="<?= htmlspecialchars($info['pass'], ENT_QUOTES) ?>"
                                        data-target="<?= htmlspecialchars($info['target'] ?? $defaultTarget, ENT_QUOTES) ?>"
                                        onclick="selectAccountPill(this)">
                                    <?= htmlspecialchars($info['label'], ENT_QUOTES) ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="pa-pill pa-pill--new" onclick="resetAccountFormForNew()">+ Cont nou</button>
                        </div>

                        <div class="pa-flex-wrap pa-mt-2 pa-hidden" id="accountManageBtns">
                            <button type="button" class="pa-btn pa-btn-outline-teal pa-btn--flex" onclick="salveazaContSelectat()">
                                <i class="bi bi-pencil-square"></i> Salvează
                            </button>
                            <button type="button" class="pa-btn pa-btn--danger pa-btn--flex" onclick="stergeContPieseauto()">
                                <i class="bi bi-trash"></i> Șterge
                            </button>
                        </div>

                        <!-- select ascuns pentru compatibilitate JS vechi -->
                        <select class="pa-hidden" id="clientSelect" onchange="fillFieldsFromSelect()">
                            <option value="" selected>—</option>
                            <?php foreach ($accounts as $id => $info): ?>
                                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"
                                        data-id="<?= htmlspecialchars((string)$info['id'], ENT_QUOTES) ?>"
                                        data-name="<?= htmlspecialchars($info['name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($info['email'], ENT_QUOTES) ?>"
                                        data-pass="<?= htmlspecialchars($info['pass'], ENT_QUOTES) ?>"
                                        data-target="<?= htmlspecialchars($info['target'] ?? $defaultTarget, ENT_QUOTES) ?>"><?= htmlspecialchars($info['label'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pa-station">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 2 · Bandă robot</p>
                        <p class="pa-station-sub">Consolă și lansare browser</p>
                    </div>
                    <div class="pa-station-body">
                        <div id="consoleBox">
                            <div id="consoleText">
                                <div class="console-line">Consolă robot — mesaje live la lansare.</div>
                            </div>
                        </div>
                        <p class="pa-text-muted pa-mt-3 pa-mb-0" style="font-size:.78rem;">Lansare browser: butonul <strong>«Pornează browser»</strong> din panoul de sus (același acțiune).</p>
                    </div>
                </div>
            </div>

            <!-- Dreapta: Stația 3 Magazie → Anunț -->
            <div>
                <div class="pa-station pa-station--wide">
                    <div class="pa-station-head">
                        <p class="pa-station-title">Stația 3 · Magazie → Anunț</p>
                        <p class="pa-station-sub">Inventar scanat stânga · fișă publicare dreapta</p>
                    </div>
                    <div class="pa-station-body">
                        <div class="pa-split">
                            <!-- Inventar -->
                            <div>
                                <label class="pa-form-label">Inventar scanat</label>
                                <div class="pa-input-group pa-mb-2">
                                    <input type="text" id="scan_search" class="pa-input" placeholder="Caută în magazie...">
                                    <button class="pa-btn pa-btn-teal" type="button" onclick="incarcaProduseScanate(true)" title="Reîmprospătează">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div id="scanate_list" class="pa-inventory" style="border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:8px;">
                                    <div class="pa-text-muted pa-text-small" style="padding:8px;">Magazia e goală (0 piese). Apasă reîmprospătare după ce adaugi produse în catalog.</div>
                                </div>
                                <div class="pa-flex-wrap pa-mt-2">
                                    <button class="pa-btn pa-btn-outline-teal pa-btn--flex" type="button" onclick="pornesteAutoProduse()">
                                        <i class="bi bi-play-fill"></i> Auto coadă
                                    </button>
                                    <button class="pa-btn pa-btn--ghost pa-btn--flex" type="button" onclick="opresteAutoProduse()">Stop auto</button>
                                </div>
                                <div class="pa-history-box pa-mt-2">
                                    <div class="pa-flex-between pa-mb-2">
                                        <span class="pa-form-label" style="margin:0;">Procesate</span>
                                        <button class="pa-btn pa-btn--link" type="button" onclick="stergeIstoricAuto()">Șterge</button>
                                    </div>
                                    <div id="auto_history">
                                        <div class="pa-text-muted pa-text-small">Niciun produs procesat.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fișă anunț -->
                            <div>
                                <label class="pa-form-label">Fișă anunț — preview</label>
                                <div class="pa-preview-card">
                                    <div class="pa-flex-start">
                                        <div id="preview_card_img_wrap">
                                            <div class="pa-preview-img-empty" id="preview_card_img_empty">Fără img</div>
                                            <img id="preview_card_img" class="pa-preview-img pa-hidden" src="" alt="">
                                        </div>
                                        <div class="pa-flex-1">
                                            <div class="pa-fw-bold pa-text-break" id="preview_card_title">Selectează o piesă din magazie</div>
                                            <div class="pa-text-muted pa-mt-1" id="preview_card_meta">—</div>
                                            <div class="pa-text-success pa-fw-bold pa-mt-2" id="preview_card_price">— LEI</div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="scanat_id_selectat">
                                <div class="pa-mb-2">
                                    <label class="pa-form-label">Titlu anunț</label>
                                    <input type="text" id="piesa_titlu" class="pa-input pa-input--sm" placeholder="Titlu anunț">
                                </div>
                                <div class="pa-mb-2">
                                    <label class="pa-form-label">Descriere</label>
                                    <textarea id="piesa_descriere" class="pa-textarea" rows="3" placeholder="Descriere detaliată..."></textarea>
                                </div>
                                <div class="pa-form-row-price pa-mb-2">
                                    <div>
                                        <label class="pa-form-label">Preț (LEI)</label>
                                        <input type="number" id="piesa_pret" class="pa-input pa-input--sm" value="100">
                                    </div>
                                    <div>
                                        <label class="pa-form-label">Stare</label>
                                        <select id="piesa_stare" class="pa-select">
                                            <option value="Second" selected>Second Hand</option>
                                            <option value="Nou">Nou</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="pa-form-label">Categorie</label>
                                        <input type="text" id="piesa_cat_nume" class="pa-input pa-input--sm" value="Alte piese de caroserie" placeholder="Subcategorie PieseAuto.ro">
                                    </div>
                                </div>
                                <div class="pa-mb-2">
                                    <label class="pa-form-label">Imagini</label>
                                    <div id="imagini_multiple"></div>
                                </div>
                                <div id="preview_scanat" class="pa-hidden">
                                    <img id="preview_scanat_img" src="" alt="">
                                </div>
                                <div class="pa-flex-wrap" style="justify-content:flex-end;margin-top:12px;">
                                    <button class="pa-btn pa-btn--link" type="button" onclick="stopTotalRobot()">Stop total</button>
                                    <button class="pa-btn pa-btn-teal" type="button" onclick="trimitePiesaNoua()">
                                        <i class="bi bi-send-fill"></i> Publică în browser
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const API_URL = <?= json_encode($robotPaCfg['proxy'] ?? '/admin/api/robot_pieseauto_proxy.php', JSON_UNESCAPED_SLASHES) ?>;
    const ROBOT_LAUNCHER = '/admin/api/pieseauto_robot_launcher_endpoint.php';
    const SCANNED_API = '/admin/api/pieseauto_scanned_endpoint.php';
    const STATUS_API = '/admin/api/pieseauto_status_endpoint.php';
    const RESET_SESSION_API = '/admin/api/pieseauto_reset_session_endpoint.php';
    const ROBOT_PA_CFG = <?= json_encode($robotPaCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.produseScanateAll = <?= json_encode($scannedProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.produseScanate = [];
    const ngrokHeaders = {
        "Content-Type": "application/json",
        "ngrok-skip-browser-warning": "69420",
        "X-Robot-Channel": ROBOT_PA_CFG.channel_header || ""
    };
    let statusTimer = null;
    let pollInFlight = false;
    const STATUS_POLL_MS = 15000;
    const STALE_AFTER_MS = 45000;
    const PA_INIT = <?= json_encode([
        'service_online' => $initServiceOnline,
        'browser_open' => $initBrowserOpen,
        'platform_connected' => $initPlatformConnected,
        'service_label' => (string) ($paInit['service_label'] ?? $robotServiceLabel ?? '127.0.0.1:5011'),
        'platform_page' => (string) ($paInit['platform_page'] ?? ''),
        'robot_message' => (string) ($paInit['robot_message'] ?? ''),
        'configured' => $initConfigured,
    ], JSON_UNESCAPED_UNICODE) ?>;
    const STABLE_STATUS = {
        service_online: !!PA_INIT.service_online,
        browser_open: !!PA_INIT.browser_open,
        platform_connected: !!PA_INIT.platform_connected,
        service_label: PA_INIT.service_label || '127.0.0.1:5011',
        platform_page: PA_INIT.platform_page || '',
        robot_message: PA_INIT.robot_message || '',
        configured: !!PA_INIT.configured,
        failStreak: 0,
        renderKey: '',
        lastOkAt: 0,
    };

    function scopeContId(id) {
        const ch = String(ROBOT_PA_CFG.channel_header || '').trim();
        const raw = String(id || '').replace(/[^a-zA-Z0-9]/g, '') || 'default';
        if (!ch) return raw;
        const prefix = ch + '_';
        return raw.toLowerCase().startsWith(prefix) ? raw : prefix + raw;
    }

    function targetContId() {
        return scopeContId(document.getElementById('target-user')?.value || '');
    }

    function robotUrl(path) { return API_URL + '?path=' + encodeURIComponent(path); }

    async function robotJson(res, label) {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            const preview = text.replace(/\s+/g, ' ').trim().slice(0, 120);
            throw new Error((label || 'Robot') + ' a returnat HTML, nu JSON: ' + preview);
        }
    }

    async function robotFetch(path, options = {}, timeoutMs = 5500) {
        const headers = Object.assign({}, ngrokHeaders, options.headers || {});
        const opts = Object.assign({}, options, { headers });
        const directBase = ROBOT_PA_CFG.direct_pieseauto || '';
        const localBase = ROBOT_PA_CFG.local_robot || '';

        async function doFetch(url, ms) {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), ms);
            try {
                return await fetch(url, Object.assign({}, opts, { signal: ctrl.signal }));
            } finally {
                clearTimeout(timer);
            }
        }

        if (directBase) {
            try {
                const res = await doFetch(directBase + path, timeoutMs);
                if (res.ok) return res;
            } catch (e) { /* fallback */ }
        }

        if (location.protocol === 'http:' && localBase) {
            try {
                const res = await doFetch(localBase + path, timeoutMs);
                if (res.ok) return res;
            } catch (e) { /* fallback */ }
        }

        return doFetch(robotUrl(path), timeoutMs);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function updateDashboard() {
        const company = document.getElementById('accCompanyName')?.value.trim() || '—';
        const target = document.getElementById('target-user')?.value.trim() || '—';
        const dashAcc = document.getElementById('dashActiveAccount');
        const dashTgt = document.getElementById('dashTarget');
        if (dashAcc) dashAcc.textContent = company !== '' ? company : '—';
        if (dashTgt) dashTgt.textContent = target;
        const hasAccount = !!document.getElementById('ridusers')?.value || !!document.getElementById('accEmail')?.value;
        const s1 = document.getElementById('flowStep1');
        if (s1) s1.classList.toggle('is-done', hasAccount);
    }

    function updateLivePreview() {
        const titlu = document.getElementById('piesa_titlu')?.value.trim() || 'Selectează o piesă din magazie';
        const pret = document.getElementById('piesa_pret')?.value || '—';
        const stare = document.getElementById('piesa_stare');
        const stareTxt = stare ? stare.options[stare.selectedIndex].text : '';
        const cat = document.getElementById('piesa_cat_nume')?.value.trim() || '—';
        const pt = document.getElementById('preview_card_title');
        const pm = document.getElementById('preview_card_meta');
        const pp = document.getElementById('preview_card_price');
        if (pt) pt.textContent = titlu;
        if (pm) pm.textContent = stareTxt + ' · ' + cat;
        if (pp) pp.textContent = pret + ' LEI';
    }

    function setPreviewImage(url) {
        const img = document.getElementById('preview_card_img');
        const empty = document.getElementById('preview_card_img_empty');
        const legacy = document.getElementById('preview_scanat_img');
        if (url) {
            if (img) { img.src = url; img.classList.remove('pa-hidden'); }
            if (empty) empty.classList.add('pa-hidden');
            if (legacy) legacy.src = url;
        } else {
            if (img) { img.src = ''; img.classList.add('pa-hidden'); }
            if (empty) empty.classList.remove('pa-hidden');
            if (legacy) legacy.src = '';
        }
    }

    function seteazaImaginiMultiple(images) {
        const container = document.getElementById('imagini_multiple');
        container.innerHTML = '';
        if (!images || images.length === 0) {
            container.innerHTML = '<div class="pa-text-muted pa-text-small">Nu există imagini</div>';
            return;
        }
        images.forEach((img, index) => {
            const url = typeof img === 'string' ? img : (img.url || '');
            const div = document.createElement('div');
            div.className = 'pa-input-group pa-mb-2';
            div.innerHTML = `<span class="pa-badge" style="border-radius:8px 0 0 8px;">${index+1}</span><input type="text" class="pa-input img-input" value="${escapeHtml(url)}"><a href="${escapeHtml(url)}" target="_blank" class="pa-btn pa-btn--ghost">↗</a>`;
            container.appendChild(div);
        });
    }

    function filtreazaProduseScanate(items, q) {
        const needle = String(q || '').trim().toLowerCase();
        if (!needle) return items;
        return items.filter(item => [item.title, item.car_brand, item.category_name, item.description].join(' ').toLowerCase().includes(needle));
    }

    function afiseazaProduseScanate(items, autoSelectFirst = false) {
        const box = document.getElementById('scanate_list');
        const countEl = document.getElementById('scanate_count');
        window.produseScanate = Array.isArray(items) ? items : [];
        if (countEl) countEl.textContent = String(window.produseScanate.length);
        if (!window.produseScanate.length) {
            box.innerHTML = '<div class="pa-text-muted pa-text-small" style="padding:8px;">Magazia e goală (0 piese). <a href="/admin/product">Mergi la Produse</a> sau apasă reîmprospătare.</div>';
            return;
        }
        box.innerHTML = '';
        window.produseScanate.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'scan-item';
            row.style.cssText = 'border:1px solid #e2e8f0;border-radius:8px;padding:8px;margin-bottom:8px;background:#f8fafc;';
            const title = item.title || 'Fără titlu';
            const price = item.price || 0;
            const category = item.category_name || item.category_full || '';
            const brand = item.car_brand || '';
            const image = item.image_url || '';
            row.innerHTML = `<div class="pa-flex" style="gap:8px;">
                <div style="width:44px;height:44px;flex:0 0 44px;">${image ? `<img src="${escapeHtml(image)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">` : `<div style="width:44px;height:44px;border-radius:8px;border:1px dashed #cbd5e1;background:#fff;"></div>`}</div>
                <div class="pa-flex-1" style="overflow:hidden;"><div class="pa-fw-bold pa-text-small pa-text-truncate">${escapeHtml(title)}</div><div class="pa-text-muted pa-text-small pa-text-truncate">${escapeHtml(brand)} · ${escapeHtml(category)}</div></div>
                <div class="pa-text-small pa-fw-bold pa-text-success">${escapeHtml(String(price))}</div></div>`;
            row.onclick = function() {
                document.querySelectorAll('#scanate_list .scan-item').forEach(el => el.classList.remove('scan-selected'));
                row.classList.add('scan-selected');
                selecteazaProdusScannat(item);
            };
            box.appendChild(row);
            if (autoSelectFirst && index === 0) { row.classList.add('scan-selected'); selecteazaProdusScannat(item); }
        });
    }

    async function incarcaProduseScanate(refreshFromServer = false) {
        const q = document.getElementById('scan_search').value.trim();
        const box = document.getElementById('scanate_list');
        if (!refreshFromServer && window.produseScanateAll?.length) {
            afiseazaProduseScanate(filtreazaProduseScanate(window.produseScanateAll, q), q === '' && !refreshFromServer);
            return;
        }
        box.innerHTML = '<div class="pa-text-muted pa-text-small" style="padding:8px;">Se încarcă magazia...</div>';
        try {
            const res = await fetch(SCANNED_API + '?q=' + encodeURIComponent(q) + '&limit=200');
            const data = await res.json();
            if (!data || data.status !== 'ok' || !Array.isArray(data.items)) {
                box.innerHTML = '<div class="pa-text-muted pa-text-small" style="padding:8px;">Nu s-au găsit produse.</div>';
                return;
            }
            window.produseScanateAll = data.items;
            afiseazaProduseScanate(data.items, q === '');
        } catch (e) {
            box.innerHTML = '<div class="pa-text-danger pa-text-small" style="padding:8px;">Eroare la încărcare.</div>';
        }
    }

    function selecteazaProdusScannat(item) {
        document.getElementById('scanat_id_selectat').value = item.id || '';
        document.getElementById('piesa_titlu').value = item.title || '';
        document.getElementById('piesa_descriere').value = item.description || '';
        document.getElementById('piesa_pret').value = item.price || 100;
        document.getElementById('piesa_cat_nume').value = item.pieseauto_category || item.sub_category || item.category_name || '';
        if (item.images?.length) seteazaImaginiMultiple(item.images);
        else if (item.image_url) seteazaImaginiMultiple([item.image_url]);
        else seteazaImaginiMultiple([]);
        setPreviewImage(item.image_url || '');
        updateLivePreview();
    }

    async function checkGlobal() {
        const s = document.getElementById('globalStatus');
        const dot = document.getElementById('server-status-dot');
        try {
            const res = await robotFetch('/verificare_sesiune', { headers: ngrokHeaders });
            if (res.ok) {
                if (s) s.textContent = 'ONLINE';
                if (dot) dot.style.background = '#22c55e';
                return true;
            }
            throw new Error('offline');
        } catch (e) {
            if (s) s.textContent = 'OFFLINE';
            if (dot) dot.style.background = '#ef4444';
            return false;
        }
    }

    function setStatusTile(tileId, dotId, valId, hintId, state, value, hint) {
        const tile = document.getElementById(tileId);
        const dot = document.getElementById(dotId);
        const val = document.getElementById(valId);
        const hintEl = document.getElementById(hintId);
        const tileClass = 'pa-status-tile is-' + state;
        const dotClass = 'pa-status-dot pa-status-dot--' + state;
        if (tile && tile.className !== tileClass) tile.className = tileClass;
        if (dot && dot.className !== dotClass) dot.className = dotClass;
        if (val && val.textContent !== value) val.textContent = value;
        if (hintEl && hint && hintEl.textContent !== hint) hintEl.textContent = hint;
    }

    function normalizeStatusView(data) {
        const robotMsg = String((data && data.robot_message) || '');
        const loggedHint = robotStatusLooksLoggedIn(robotMsg);
        const platformOk = !!(data && (data.platform_connected || loggedHint));
        const browserOk = !!(data && (data.browser_open || (platformOk && loggedHint)));
        return {
            status: 'ok',
            service_online: !!(data && data.service_online),
            browser_open: browserOk,
            platform_connected: platformOk,
            service_label: (data && data.service_label) || STABLE_STATUS.service_label || '127.0.0.1:5011',
            platform_page: (data && data.platform_page) || (platformOk ? 'pieseauto.ro/contul-meu' : ''),
            robot_message: robotMsg,
            configured: !!(data && data.configured),
        };
    }

    function mergeLiveTextIntoView(view, liveText) {
        const st = String(liveText || '').trim();
        if (!st || st === 'Inactiv') return view;
        view.robot_message = st;
        if (robotStatusLooksLoggedIn(st)) {
            view.platform_connected = true;
            view.browser_open = true;
            view.platform_page = view.platform_page || 'pieseauto.ro/contul-meu';
        } else if (robotStatusLooksBrowserActive(st)) {
            view.browser_open = true;
        } else if (/oprit|eroare|eșuat|esuat|logout|parola gre/i.test(st)) {
            view.platform_connected = false;
        }
        return view;
    }

    function stabilizeStatusView(view) {
        const out = Object.assign({}, view);
        const downgrade = /oprit|eroare|eșuat|esuat|logout|parola gre/i.test(out.robot_message || '');

        if (STABLE_STATUS.platform_connected && !out.platform_connected && !downgrade) {
            out.platform_connected = true;
            out.browser_open = true;
            if (!out.platform_page) out.platform_page = STABLE_STATUS.platform_page || 'pieseauto.ro/contul-meu';
        }
        if (STABLE_STATUS.browser_open && !out.browser_open && !downgrade && out.service_online) {
            out.browser_open = true;
        }
        if (STABLE_STATUS.service_online && !out.service_online && STABLE_STATUS.failStreak < 5) {
            out.service_online = true;
            out.service_label = STABLE_STATUS.service_label || out.service_label;
        }
        return out;
    }

    function rememberStableStatus(view) {
        STABLE_STATUS.service_online = !!view.service_online;
        STABLE_STATUS.browser_open = !!view.browser_open;
        STABLE_STATUS.platform_connected = !!view.platform_connected;
        STABLE_STATUS.service_label = view.service_label || STABLE_STATUS.service_label;
        STABLE_STATUS.platform_page = view.platform_page || STABLE_STATUS.platform_page;
        STABLE_STATUS.robot_message = view.robot_message || STABLE_STATUS.robot_message;
        STABLE_STATUS.configured = !!view.configured;
    }

    function appendConsoleStatusLine(st) {
        const div = document.getElementById('consoleText');
        if (!div || !st || div.innerText.includes(st)) return;
        const line = document.createElement('div');
        line.className = 'console-line';
        if (st.includes('❌') || /eșuat|esuat|gre/i.test(st)) {
            line.style.color = '#f87171';
        } else if (st.includes('🏁') || st.includes('✅')) {
            line.style.color = '#4ade80';
        }
        line.innerText = `[${new Date().toLocaleTimeString()}] ${st}`;
        div.appendChild(line);
        div.parentElement.scrollTop = div.parentElement.scrollHeight;
    }

    function updateFlowBadgesFromMessage(st) {
        const botLabel = document.getElementById('bot-status-label');
        const s2 = document.getElementById('flowStep2');
        const s3 = document.getElementById('flowStep3');
        const isError = st.includes('❌') || /eșuat|esuat|parola gre/i.test(st);
        const isSuccess = robotStatusLooksLoggedIn(st);
        if (isError) {
            if (botLabel) { botLabel.textContent = 'EROARE LOGIN'; botLabel.className = 'pa-ms-auto pa-badge pa-badge--danger'; }
            if (s2) s2.classList.remove('is-done');
        } else if (isSuccess) {
            if (botLabel) { botLabel.textContent = 'CONECTAT'; botLabel.className = 'pa-ms-auto pa-badge pa-badge--ok'; }
            if (s2) s2.classList.add('is-done');
            if (s3) s3.classList.add('is-done');
        } else if (robotStatusLooksBrowserActive(st) && !isError) {
            if (botLabel) { botLabel.textContent = 'BROWSER'; botLabel.className = 'pa-ms-auto pa-badge pa-badge--warn'; }
            if (s2) s2.classList.add('is-done');
        } else if (st !== 'Inactiv' && !isError && botLabel) {
            botLabel.textContent = 'ACTIV';
            botLabel.className = 'pa-ms-auto pa-badge pa-badge--warn';
        }
    }

    function shortenUrl(url) {
        return String(url || '').replace(/^https?:\/\/(www\.)?/i, '').split('?')[0] || 'pieseauto.ro';
    }

    function robotStatusLooksLoggedIn(text) {
        return /logat|conectat|succes|recuperat|deja logat|🏁|✅/i.test(String(text || ''));
    }

    function robotStatusLooksBrowserActive(text) {
        const st = String(text || '');
        return robotStatusLooksLoggedIn(st)
            || /browser deja activ|robotul este deja activ|chrome activ|sesiune existent|pornire chrome|deschid pieseauto|pregătire chrome|pregatire chrome|navigăm|navigam|lansat|singură fereastră|singura fereastra|browserul se deschide/i.test(st);
    }

    function viewFromStarePayload(stare, liveMsg) {
        let view = normalizeStatusView({
            status: 'ok',
            service_online: stare.service_online !== false,
            browser_open: !!(stare.browser_open || stare.browser_active),
            platform_connected: !!stare.platform_connected,
            service_label: stare.service_port ? ('127.0.0.1:' + stare.service_port) : STABLE_STATUS.service_label,
            platform_page: stare.page_url || STABLE_STATUS.platform_page,
            robot_message: liveMsg || stare.mesaj || STABLE_STATUS.robot_message,
            configured: STABLE_STATUS.configured,
        });
        if (liveMsg) {
            view = mergeLiveTextIntoView(view, liveMsg);
        } else if (stare.mesaj) {
            view = mergeLiveTextIntoView(view, stare.mesaj);
        }
        return view;
    }

    let launchWatchTimer = null;

    async function watchRobotLaunch(contId) {
        if (launchWatchTimer) {
            clearInterval(launchWatchTimer);
            launchWatchTimer = null;
        }
        const started = Date.now();
        const maxMs = 120000;

        async function tick() {
            if (Date.now() - started > maxMs) {
                if (launchWatchTimer) clearInterval(launchWatchTimer);
                launchWatchTimer = null;
                return;
            }
            let liveMsg = '';
            let finished = false;
            try {
                const [stRes, stareRes] = await Promise.all([
                    robotFetch('/get_status?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders }, 8000),
                    robotFetch('/stare_completa?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders }, 8000),
                ]);
                if (stRes.ok) {
                    const st = await stRes.json();
                    liveMsg = String(st.status || '').trim();
                }
                if (stareRes.ok) {
                    const stare = await stareRes.json();
                    if (!liveMsg || liveMsg === 'Inactiv') {
                        liveMsg = String(stare.mesaj || '').trim();
                    }
                    const view = stabilizeStatusView(viewFromStarePayload(stare, liveMsg));
                    if (liveMsg && liveMsg !== 'Inactiv') {
                        appendConsoleStatusLine(liveMsg);
                        updateFlowBadgesFromMessage(liveMsg);
                    }
                    renderStatusView(view);
                    STABLE_STATUS.failStreak = 0;
                    STABLE_STATUS.lastOkAt = Date.now();
                    if (view.platform_connected) {
                        finished = true;
                        logAuto('Conectat la PieseAuto.ro — gata de publicare.', '#86efac');
                    } else if (/eroare|eșuat|esuat|parola gre|❌/i.test(liveMsg)) {
                        finished = true;
                    }
                } else if (liveMsg && liveMsg !== 'Inactiv') {
                    await mergeLiveRobotStatus(liveMsg);
                    updateFlowBadgesFromMessage(liveMsg);
                }
            } catch (e) { /* reîncearcă */ }

            if (finished && launchWatchTimer) {
                clearInterval(launchWatchTimer);
                launchWatchTimer = null;
            }
        }

        setStatusTile(
            'tileBrowser', 'dotBrowser', 'valBrowser', 'hintBrowser',
            'warn', 'PORNIRE', 'Se deschide Chrome robot...'
        );
        await tick();
        launchWatchTimer = setInterval(tick, 2500);
    }

    async function mergeLiveRobotStatus(robotStatusText) {
        const view = mergeLiveTextIntoView(normalizeStatusView({
            status: 'ok',
            service_online: STABLE_STATUS.service_online,
            browser_open: STABLE_STATUS.browser_open,
            platform_connected: STABLE_STATUS.platform_connected,
            service_label: STABLE_STATUS.service_label,
            platform_page: STABLE_STATUS.platform_page,
            robot_message: STABLE_STATUS.robot_message,
            configured: STABLE_STATUS.configured,
        }), robotStatusText);
        renderStatusView(stabilizeStatusView(view));
    }

    function renderStatusView(view) {
        const renderKey = [
            view.service_online ? 1 : 0,
            view.browser_open ? 1 : 0,
            view.platform_connected ? 1 : 0,
            view.service_label,
            view.platform_page,
        ].join('|');
        if (renderKey === STABLE_STATUS.renderKey) {
            rememberStableStatus(view);
            return true;
        }
        STABLE_STATUS.renderKey = renderKey;
        rememberStableStatus(view);
        return applyStatusData(view);
    }

    function applyStatusData(data) {
        if (!data || data.status !== 'ok') return false;

        const robotMsg = String(data.robot_message || '');
        const loggedHint = /logat|conectat|succes|recuperat|deja logat|🏁|✅/i.test(robotMsg);
        const platformOk = !!(data.platform_connected || loggedHint);
        const browserOk = !!(data.browser_open || (platformOk && loggedHint));

        setStatusTile(
            'tileService', 'dotService', 'valService', 'hintService',
            data.service_online ? 'ok' : 'bad',
            data.service_online ? 'DESCHIS' : 'ÎNCHIS',
            data.service_online
                ? (data.service_label || '127.0.0.1:5011')
                : (data.access_hint || data.robot_message || 'Pornește robot\\start_pieseauto_visible.bat')
        );
        setStatusTile(
            'tileBrowser', 'dotBrowser', 'valBrowser', 'hintBrowser',
            browserOk ? 'ok' : (data.service_online ? 'warn' : 'bad'),
            browserOk ? 'DESCHIS' : 'ÎNCHIS',
            browserOk ? 'Fereastră Chrome robot' : (data.service_online ? 'Apasă «Pornează browser» sus' : '—')
        );
        setStatusTile(
            'tilePlatform', 'dotPlatform', 'valPlatform', 'hintPlatform',
            platformOk ? 'ok' : (browserOk ? 'warn' : 'bad'),
            platformOk ? 'CONECTAT' : 'NELOGAT',
            platformOk
                ? shortenUrl(data.platform_page || 'pieseauto.ro/contul-meu')
                : (browserOk ? 'Se face login...' : '—')
        );

        const botLabel = document.getElementById('bot-status-label');
        const readyBanner = document.getElementById('paReadyBanner');
        const s1 = document.getElementById('flowStep1');
        const s2 = document.getElementById('flowStep2');
        const s3 = document.getElementById('flowStep3');

        if (data.configured && s1) s1.classList.add('is-done');
        if (browserOk && s2) s2.classList.add('is-done');
        else if (s2) s2.classList.remove('is-done');
        if (platformOk && s3) s3.classList.add('is-done');
        else if (s3) s3.classList.remove('is-done');

        const ready = !!(data.service_online && browserOk && platformOk);
        if (ready) {
            if (botLabel) { botLabel.textContent = 'GATA'; botLabel.className = 'pa-ms-auto pa-badge pa-badge--ok'; }
            readyBanner?.classList.add('is-visible');
        } else {
            readyBanner?.classList.remove('is-visible');
            if (botLabel) {
                if (platformOk) {
                    botLabel.textContent = 'CONECTAT';
                    botLabel.className = 'pa-ms-auto pa-badge pa-badge--ok';
                } else if (browserOk) {
                    botLabel.textContent = 'BROWSER';
                    botLabel.className = 'pa-ms-auto pa-badge pa-badge--warn';
                } else if (data.service_online) {
                    botLabel.textContent = 'SERVICIU OK';
                    botLabel.className = 'pa-ms-auto pa-badge pa-badge--warn';
                } else {
                    botLabel.textContent = 'INACTIV';
                    botLabel.className = 'pa-ms-auto pa-badge';
                }
            }
        }

        const gs = document.getElementById('globalStatus');
        if (gs) gs.textContent = data.service_online ? 'ONLINE' : 'OFFLINE';
        return true;
    }

    async function fetchStatusSnapshot(live) {
        const target = document.getElementById('target-user')?.value.trim() || 'besoiu';
        const url = STATUS_API + '?target=' + encodeURIComponent(target) + (live ? '&live=1' : '');
        try {
            const res = await fetch(url);
            if (res.ok) {
                const data = await res.json();
                if (data && data.status === 'ok') return data;
            }
        } catch (e) { /* fallback launcher */ }
        if (!live) return null;
        try {
            const res = await fetch(ROBOT_LAUNCHER + '?action=ping&target=' + encodeURIComponent(target));
            if (res.ok) {
                const data = await res.json();
                if (data && data.snapshot && data.snapshot.status === 'ok') return data.snapshot;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    async function fetchLiveRobotBundle(contId) {
        if (!contId) return { liveMsg: '', stare: null };
        try {
            const [stareRes, statusRes] = await Promise.all([
                robotFetch('/stare_completa?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders }, 5500),
                robotFetch('/get_status?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders }, 4000),
            ]);
            let liveMsg = '';
            if (statusRes.ok) {
                const st = await statusRes.json();
                liveMsg = String(st.status || '').trim();
            }
            if (stareRes.ok) {
                const stare = await stareRes.json();
                if (!liveMsg || liveMsg === 'Inactiv') {
                    liveMsg = String(stare.mesaj || '').trim();
                }
                return { liveMsg, stare };
            }
            if (liveMsg && liveMsg !== 'Inactiv') {
                return { liveMsg, stare: { service_online: true, mesaj: liveMsg } };
            }
        } catch (e) { /* ignore */ }
        return { liveMsg: '', stare: null };
    }

    async function pollConnectionStatusBackground() {
        if (pollInFlight) return null;
        pollInFlight = true;
        try {
        const contId = targetContId();
        const live = await fetchLiveRobotBundle(contId);

        let view = {
            status: 'ok',
            service_online: STABLE_STATUS.service_online,
            browser_open: STABLE_STATUS.browser_open,
            platform_connected: STABLE_STATUS.platform_connected,
            service_label: STABLE_STATUS.service_label,
            platform_page: STABLE_STATUS.platform_page,
            robot_message: STABLE_STATUS.robot_message,
            configured: STABLE_STATUS.configured,
        };

        if (live.stare && typeof live.stare === 'object') {
            view = normalizeStatusView({
                status: 'ok',
                service_online: live.stare.service_online !== false,
                browser_open: !!(live.stare.browser_open || live.stare.browser_active),
                platform_connected: !!live.stare.platform_connected,
                service_label: live.stare.service_port ? ('127.0.0.1:' + live.stare.service_port) : view.service_label,
                platform_page: live.stare.page_url || view.platform_page,
                robot_message: live.liveMsg || live.stare.mesaj || view.robot_message,
                configured: view.configured,
            });
            STABLE_STATUS.failStreak = 0;
            STABLE_STATUS.lastOkAt = Date.now();
        } else {
            STABLE_STATUS.failStreak += 1;
            if (STABLE_STATUS.lastOkAt && (Date.now() - STABLE_STATUS.lastOkAt) < STALE_AFTER_MS) {
                view = stabilizeStatusView(view);
                renderStatusView(view);
                return view;
            }
        }

        if (live.liveMsg) {
            view = mergeLiveTextIntoView(view, live.liveMsg);
            appendConsoleStatusLine(live.liveMsg);
            updateFlowBadgesFromMessage(live.liveMsg);
        }

        view = stabilizeStatusView(view);

        if (!view.service_online && STABLE_STATUS.failStreak >= 12 && !STABLE_STATUS.platform_connected) {
            setStatusTile('tileService', 'dotService', 'valService', 'hintService', 'bad', 'ÎNCHIS', 'Pornește robot\\start_pieseauto_visible.bat');
            setStatusTile('tileBrowser', 'dotBrowser', 'valBrowser', 'hintBrowser', 'bad', 'ÎNCHIS', '—');
            setStatusTile('tilePlatform', 'dotPlatform', 'valPlatform', 'hintPlatform', 'bad', 'NELOGAT', '—');
            const botLabel = document.getElementById('bot-status-label');
            if (botLabel) { botLabel.textContent = 'INACTIV'; botLabel.className = 'pa-ms-auto pa-badge'; }
            rememberStableStatus(view);
            return null;
        }

        renderStatusView(view);
        return view;
        } finally {
            pollInFlight = false;
        }
    }

    async function refreshConnectionStatus() {
        return pollConnectionStatusBackground();
    }

    function ensureBackgroundStatusPolling() {
        if (statusTimer) return;
        statusTimer = setInterval(pollConnectionStatusBackground, STATUS_POLL_MS);
    }

    async function ensurePieseautoRobot() {
        const target = document.getElementById('target-user')?.value.trim() || 'besoiu';
        let snap = await pollConnectionStatusBackground();
        if (snap && snap.service_online) {
            return true;
        }

        logAuto('Verific / pornesc serviciul robot Python...', '#fbbf24');
        try {
            const launchRes = await fetch(ROBOT_LAUNCHER, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start', robot: 'pieseauto', target }),
            });
            const launchData = await launchRes.json().catch(() => ({}));

            if (launchData.online) {
                logAuto((launchData.message || 'Robot online.') + ' (' + (launchData.robot_base || snap?.service_label || '') + ')', '#86efac');
                await pollConnectionStatusBackground();
                return true;
            }

            if (launchData.already_running && !launchData.online) {
                logAuto('Proces detectat, dar portul nu răspunde cu «online». Repornește robot\\start_pieseauto_visible.bat.', '#f87171');
                return false;
            }

            if (launchData.message) {
                logAuto(String(launchData.message), launchData.success ? '#fbbf24' : '#f87171');
            }
        } catch (e) {
            logAuto('Nu am putut apela launcher-ul robot.', '#f87171');
        }

        for (let i = 0; i < 8; i++) {
            await new Promise(r => setTimeout(r, 2000));
            snap = await pollConnectionStatusBackground();
            if (snap && snap.service_online) {
                logAuto('Robot online pe ' + (snap.service_label || 'port'), '#86efac');
                return true;
            }
        }

        logAuto('Robot indisponibil. Deschide robot\\start_pieseauto_visible.bat și lasă fereastra deschisă.', '#f87171');
        return false;
    }

    function startStatusPolling(id) {
        void id;
        ensureBackgroundStatusPolling();
        pollConnectionStatusBackground();
    }

    async function reconectareAutomataRobot() {
        const actualId = targetContId();
        if (!actualId) return;
        try {
            const res = await robotFetch('/get_status?cont_id=' + encodeURIComponent(actualId), { headers: ngrokHeaders });
            const data = await res.json();
            if (data.status !== 'Inactiv') {
                await mergeLiveRobotStatus(data.status);
                ensureBackgroundStatusPolling();
            }
        } catch (e) {}
    }

    const AUTO_HISTORY_KEY = 'pa_auto_history_' + (ROBOT_PA_CFG.channel_header || 'default') + '_v1';
    let autoQueue = [], autoRunning = false, autoIndex = 0;

    function logAuto(mesaj, color = '#fbbf24') {
        const div = document.getElementById('consoleText');
        if (!div) return;
        const line = document.createElement('div');
        line.className = 'console-line';
        line.style.color = color;
        line.innerText = `[${new Date().toLocaleTimeString()}] ${mesaj}`;
        div.appendChild(line);
        div.parentElement.scrollTop = div.parentElement.scrollHeight;
    }

    function sleepAuto(ms) { return new Promise(r => setTimeout(r, ms)); }
    function getProdusKey(item) { return String(item.id || item.title || item.image_url || '').trim(); }
    function citesteIstoricAuto() { try { return JSON.parse(localStorage.getItem(AUTO_HISTORY_KEY) || '{}'); } catch (e) { return {}; } }
    function salveazaIstoricAuto(h) { localStorage.setItem(AUTO_HISTORY_KEY, JSON.stringify(h)); }

    function seteazaStatusProdus(item, status, mesaj = '') {
        const key = getProdusKey(item);
        if (!key) return;
        const history = citesteIstoricAuto();
        history[key] = { id: item.id||'', title: item.title||'', price: item.price||'', image: item.image_url||'', status, mesaj, updated_at: new Date().toLocaleString() };
        salveazaIstoricAuto(history);
        randareIstoricAuto();
        if (status === 'Adăugat') {
            const s3 = document.getElementById('flowStep3');
            if (s3) s3.classList.add('is-done');
        }
    }

    function produsEsteAdaugat(item) {
        const h = citesteIstoricAuto();
        const k = getProdusKey(item);
        return !!(h[k] && h[k].status === 'Adăugat');
    }

    function randareIstoricAuto() {
        const box = document.getElementById('auto_history');
        if (!box) return;
        const items = Object.values(citesteIstoricAuto()).reverse();
        if (!items.length) { box.innerHTML = '<div class="pa-text-muted pa-text-small">Niciun produs procesat.</div>'; return; }
        box.innerHTML = items.map(item => {
            let badgeClass = 'pa-badge';
            if (item.status === 'Adăugat') badgeClass = 'pa-badge pa-badge--ok';
            if (item.status === 'Eroare') badgeClass = 'pa-badge pa-badge--danger';
            if (item.status === 'Se adaugă') badgeClass = 'pa-badge pa-badge--warn';
            return `<div class="pa-flex-between" style="padding:4px 0;border-bottom:1px solid #eef2f7;"><div class="pa-flex-1"><div class="pa-text-small pa-fw-bold pa-text-truncate">${escapeHtml(item.title||'—')}</div></div><span class="${badgeClass}">${escapeHtml(item.status)}</span></div>`;
        }).join('');
    }

    function stergeIstoricAuto() {
        if (!confirm('Ștergi istoricul?')) return;
        localStorage.removeItem(AUTO_HISTORY_KEY);
        randareIstoricAuto();
    }

    async function asteaptaRobotLiber(contId) {
        while (autoRunning) {
            try {
                const res = await robotFetch('/este_ocupat?cont_id=' + encodeURIComponent(contId), { headers: ngrokHeaders });
                const data = await res.json();
                if (!data.busy) return true;
                logAuto('Robot ocupat. Aștept...');
            } catch (e) { logAuto('Reîncerc verificare robot...', '#ef4444'); }
            await sleepAuto(5000);
        }
        return false;
    }

    function payloadDinProdusScanat(item, contId) {
        const images = item.images?.length ? item.images.map(i => typeof i === 'string' ? i : (i.url||'')).filter(Boolean) : (item.image_url ? [item.image_url] : []);
        const categorie = item.pieseauto_category || item.sub_category || item.category_name || 'Alte piese de caroserie';
        return { cont_id: contId, titlu: item.title||'', descriere: item.description||item.title||'', pret: item.price||100, stare_produs: 'Second', categorie_nume: categorie, imagine_url: images[0]||'', imagini_multiple: images };
    }

    async function pornesteAutoProduse() {
        if (autoRunning) return alert('Auto deja rulează.');
        if (!window.produseScanate?.length) return alert('Magazia e goală.');
        const actualId = targetContId();
        if (!actualId) return alert('Completează target.');
        autoQueue = [...window.produseScanate]; autoRunning = true; autoIndex = 0;
        logAuto('AUTO: ' + autoQueue.length + ' piese în coadă.', '#86efac');
        while (autoRunning && autoIndex < autoQueue.length) {
            const item = autoQueue[autoIndex];
            const payload = payloadDinProdusScanat(item, actualId);
            if (produsEsteAdaugat(item)) { seteazaStatusProdus(item,'Sărit','Deja adăugat'); autoIndex++; continue; }
            if (!payload.titlu || payload.titlu.length < 15) { seteazaStatusProdus(item,'Sărit','Titlu scurt'); autoIndex++; continue; }
            seteazaStatusProdus(item,'Se adaugă');
            logAuto(`AUTO ${autoIndex+1}/${autoQueue.length}: ${payload.titlu}`);
            try {
                const res = await robotFetch('/adauga_piesa_noua', { method:'POST', headers: ngrokHeaders, body: JSON.stringify(payload) });
                const data = await res.json();
                if (data.status !== 'succes') { seteazaStatusProdus(item,'Eroare', data.mesaj||''); autoIndex++; await sleepAuto(5000); continue; }
                await sleepAuto(3000);
                if (!(await asteaptaRobotLiber(actualId))) { seteazaStatusProdus(item,'Oprit','Manual'); break; }
                seteazaStatusProdus(item,'Adăugat');
            } catch (e) { seteazaStatusProdus(item,'Eroare','Conexiune'); }
            autoIndex++; await sleepAuto(5000);
        }
        autoRunning = false;
        logAuto('AUTO terminat.', '#86efac');
        alert('Auto terminat sau oprit.');
    }

    async function stopTotalRobot() {
        autoRunning = false;
        const actualId = targetContId();
        logAuto('STOP TOTAL...', '#ef4444');
        try {
            const res = await robotFetch('/stop_total?cont_id=' + encodeURIComponent(actualId), { method:'POST', headers: ngrokHeaders, body: JSON.stringify({ cont_id: actualId }) });
            const data = await res.json();
            logAuto(data.mesaj || 'Oprit.', '#ef4444');
            STABLE_STATUS.browser_open = false;
            STABLE_STATUS.platform_connected = false;
            STABLE_STATUS.renderKey = '';
            const botLabel = document.getElementById('bot-status-label');
            if (botLabel) { botLabel.textContent = 'OPRIT'; botLabel.className = 'pa-ms-auto pa-badge pa-badge--danger'; }
            document.getElementById('flowStep2')?.classList.remove('is-done');
            document.getElementById('flowStep3')?.classList.remove('is-done');
            setTimeout(pollConnectionStatusBackground, 1500);
        } catch (e) { alert('Nu am putut opri robotul.'); }
    }

    async function resetSessionAndStartRobot() {
        const email = document.getElementById('accEmail').value;
        const pass = document.getElementById('accPass').value;
        const userTarget = document.getElementById('target-user')?.value.trim() || '';
        if (!email || !pass) {
            return alert('Completează email și parolă în Stația 1 (sau selectează un cont salvat).');
        }
        if (!userTarget) {
            return alert('Completează utilizator target.');
        }
        if (!confirm('Ștergi complet sesiunea Chrome robot (cookies, login) și pornești de la zero?')) {
            return;
        }

        autoRunning = false;
        STABLE_STATUS.browser_open = false;
        STABLE_STATUS.platform_connected = false;
        STABLE_STATUS.renderKey = '';

        const actualId = scopeContId(userTarget);
        const btns = document.querySelectorAll('#btnStartRobot, #btnResetSession, #btnStopRobot');
        btns.forEach(b => { b.disabled = true; });

        logAuto('Curăț sesiune robot (profil Chrome șters)...', '#fbbf24');
        try {
            if (!(await ensurePieseautoRobot())) {
                return alert('Serviciul robot nu răspunde. Pornește robot\\start_pieseauto_visible.bat.');
            }
            const res = await fetch(RESET_SESSION_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ target: userTarget }),
            });
            const raw = await res.text();
            let data = {};
            try { data = raw ? JSON.parse(raw) : {}; } catch (e) {
                throw new Error('Răspuns invalid de la server (nu e JSON). Reîncarcă pagina.');
            }
            if (!res.ok || !data.success) {
                throw new Error(data.mesaj || 'Reset eșuat');
            }
            logAuto(data.mesaj || 'Sesiune curățată.', '#86efac');
            document.getElementById('flowStep2')?.classList.remove('is-done');
            document.getElementById('flowStep3')?.classList.remove('is-done');
            const botLabel = document.getElementById('bot-status-label');
            if (botLabel) { botLabel.textContent = 'RESET'; botLabel.className = 'pa-ms-auto pa-badge'; }
            await refreshConnectionStatus();
            await new Promise(r => setTimeout(r, 2000));
            logAuto('Pornesc browser cu sesiune nouă...', '#fbbf24');
            btns.forEach(b => { b.disabled = false; });
            await startRobot(true);
        } catch (e) {
            logAuto('Eroare reset sesiune: ' + (e.message || e), '#f87171');
            alert('Nu am putut reseta sesiunea: ' + (e.message || 'verifică robotul.'));
        } finally {
            btns.forEach(b => { b.disabled = false; });
        }
    }

    function opresteAutoProduse() { autoRunning = false; logAuto('AUTO oprit.', '#ef4444'); }

    async function startRobot(forceFresh = false) {
        const email = document.getElementById('accEmail')?.value?.trim() || '';
        const pass = document.getElementById('accPass')?.value || '';
        const userTarget = document.getElementById('target-user')?.value?.trim() || '';
        logAuto(forceFresh ? 'Comandă: sesiune nouă + login...' : 'Comandă: lansare browser robot...', '#fbbf24');
        if (!email || !pass) {
            logAuto('Lipsesc email/parolă — selectează un cont salvat sau completează Stația 1.', '#f87171');
            return alert('Completează datele de logare!');
        }
        if (!userTarget) {
            logAuto('Lipsește utilizator target.', '#f87171');
            return alert('Completează utilizator target!');
        }
        const launchBtns = document.querySelectorAll('#btnStartRobot');
        launchBtns.forEach(b => { b.disabled = true; });
        try {
            if (!(await ensurePieseautoRobot())) {
                logAuto('Serviciul PieseAuto nu răspunde. Deschide robot\\start_pieseauto_visible.bat și lasă fereastra deschisă.', '#f87171');
                return alert('Serviciul PieseAuto nu răspunde.\n\n1. Dublu-click: robot\\start_pieseauto_visible.bat\n2. Lasă fereastra deschisă\n3. Reîncarcă pagina și apasă «Pornează browser»');
            }
            const actualId = scopeContId(userTarget);
            const st = await robotFetch('/este_ocupat?cont_id=' + encodeURIComponent(actualId), { headers: ngrokHeaders }, 8000);
            const stData = await robotJson(st, '/este_ocupat');
            if (!forceFresh && stData.platform_connected) {
                startStatusPolling(actualId);
                logAuto('Deja logat pe PieseAuto.ro — nu refac login.', '#86efac');
                await watchRobotLaunch(actualId);
                return;
            }
            if (forceFresh && stData.browser_active) {
                logAuto('Închid browserul pentru sesiune nouă...', '#fbbf24');
                await robotFetch('/stop_total?cont_id=' + encodeURIComponent(actualId), {
                    method: 'POST',
                    headers: ngrokHeaders,
                    body: JSON.stringify({ cont_id: actualId }),
                });
                await new Promise(r => setTimeout(r, 2500));
            }
            logAuto('Trimit comanda /comanda către robot...', '#fbbf24');
            const cmdRes = await robotFetch('/comanda', { method: 'POST', headers: ngrokHeaders, body: JSON.stringify({ cont_id: actualId, user: email, pass: pass, force_fresh: !!forceFresh }) });
            const cmdData = await robotJson(cmdRes, '/comanda');
            if (!cmdRes.ok) {
                throw new Error(cmdData.mesaj || ('HTTP ' + cmdRes.status));
            }
            startStatusPolling(actualId);
            if (cmdData.status === 'activ' && /deja logat/i.test(String(cmdData.mesaj || ''))) {
                logAuto(cmdData.mesaj || 'Deja logat pe PieseAuto.ro.', '#86efac');
                await watchRobotLaunch(actualId);
            } else {
                logAuto(cmdData.mesaj || 'Browser robot lansat.', '#86efac');
                await watchRobotLaunch(actualId);
            }
        } catch (e) {
            logAuto('Eroare la lansare: ' + (e.message || e), '#f87171');
            alert('Eroare Robot Python: ' + (e.message || 'verifică consola.'));
        } finally {
            launchBtns.forEach(b => { b.disabled = false; });
        }
    }

    async function trimitePiesaNoua() {
        const actualId = targetContId();
        if (!actualId) return alert('Completează target.');
        const imagini = Array.from(document.querySelectorAll('#imagini_multiple .img-input')).map(i => i.value.trim()).filter(Boolean);
        const payload = {
            cont_id: actualId,
            titlu: document.getElementById('piesa_titlu').value.trim(),
            descriere: document.getElementById('piesa_descriere').value.trim(),
            pret: document.getElementById('piesa_pret').value || 0,
            stare_produs: document.getElementById('piesa_stare').value,
            categorie_nume: document.getElementById('piesa_cat_nume').value.trim(),
            imagine_url: imagini[0] || '',
            imagini_multiple: imagini
        };
        if (payload.titlu.length < 15) return alert('Titlul: minim 15 caractere.');
        if (payload.pret === '' || Number(payload.pret) < 0) return alert('Preț invalid.');
        try {
            const res = await robotFetch('/adauga_piesa_noua', { method:'POST', headers: ngrokHeaders, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.status === 'succes') logAuto('Trimit ' + imagini.length + ' imagini...', '#fbbf24');
            alert(data.mesaj || 'Trimis către robot!');
        } catch (e) { alert('Eroare conexiune Python.'); }
    }

    function updateAccountMode() {
        const rid = document.getElementById('ridusers').value;
        const isEdit = !!rid;
        const btn = document.getElementById('btnSaveAccount');
        const manage = document.getElementById('accountManageBtns');
        if (btn) btn.textContent = isEdit ? 'SALVEAZĂ MODIFICĂRILE' : 'AUTENTIFICARE & SALVARE';
        if (manage) manage.classList.toggle('pa-hidden', !isEdit);
        if (manage && isEdit) manage.style.display = 'flex';
        updateDashboard();
    }

    function selectAccountPill(el) {
        document.querySelectorAll('#accountPills .pa-pill:not(.pa-pill--new)').forEach(p => p.classList.remove('is-active'));
        el.classList.add('is-active');
        document.getElementById('accCompanyName').value = el.getAttribute('data-name') || el.textContent.trim();
        document.getElementById('accEmail').value = el.getAttribute('data-email') || '';
        document.getElementById('accPass').value = el.getAttribute('data-pass') || '';
        document.getElementById('ridusers').value = el.getAttribute('data-id') || '';
        const tgt = el.getAttribute('data-target') || '';
        if (tgt) document.getElementById('target-user').value = tgt;
        const sel = document.getElementById('clientSelect');
        if (sel) sel.value = el.getAttribute('data-client-id') || '';
        updateAccountMode();
        reconectareAutomataRobot();
    }

    function fillFieldsFromSelect() {
        const sel = document.getElementById('clientSelect');
        const opt = sel.options[sel.selectedIndex];
        if (!sel.value) { document.getElementById('ridusers').value = ''; updateAccountMode(); return; }
        document.getElementById('accCompanyName').value = opt.getAttribute('data-name') || '';
        document.getElementById('accEmail').value = opt.getAttribute('data-email') || '';
        document.getElementById('accPass').value = opt.getAttribute('data-pass') || '';
        document.getElementById('ridusers').value = opt.getAttribute('data-id') || '';
        const tgt = opt.getAttribute('data-target') || '';
        if (tgt) document.getElementById('target-user').value = tgt;
        document.querySelectorAll('#accountPills .pa-pill').forEach(p => {
            p.classList.toggle('is-active', p.getAttribute('data-client-id') === sel.value);
        });
        updateAccountMode();
        reconectareAutomataRobot();
    }

    function resetAccountFormForNew() {
        document.getElementById('clientSelect').value = '';
        document.getElementById('ridusers').value = '';
        document.getElementById('accCompanyName').value = '';
        document.getElementById('accEmail').value = '';
        document.getElementById('accPass').value = '';
        document.querySelectorAll('#accountPills .pa-pill').forEach(p => p.classList.remove('is-active'));
        updateAccountMode();
        document.getElementById('accCompanyName').focus();
    }

    function salveazaContSelectat() {
        if (!document.getElementById('ridusers').value) return alert('Selectează un cont.');
        document.getElementById('addpieseauto').requestSubmit();
    }

    async function stergeContPieseauto() {
        const rid = document.getElementById('ridusers').value;
        const active = document.querySelector('#accountPills .pa-pill.is-active');
        const label = active?.textContent?.trim() || 'acest cont';
        if (!rid) return alert('Selectează un cont.');
        if (!confirm('Ștergi contul „' + label + '”?')) return;
        try {
            const res = await fetch('/admin/api/pieseauto_accounts_endpoint.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', randomn_id: rid }) });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            alert(json.message || 'Cont șters.');
            window.location.reload();
        } catch (e) { alert('Eroare conexiune.'); }
    }

    async function salveazaContPieseauto(e) {
        e.preventDefault();
        const companyName = document.getElementById('accCompanyName').value.trim();
        const email = document.getElementById('accEmail').value.trim();
        const pass = document.getElementById('accPass').value;
        if (!companyName) return alert('Introdu firma.');
        if (!email || !pass) return alert('Completează email și parolă.');
        const form = document.getElementById('addpieseauto');
        const data = {};
        form.querySelectorAll('input, textarea, select').forEach(i => { if (i.name) data[i.name] = i.value; });
        data.company_name = companyName; data.email = email; data.pas = pass;
        data.target_user = document.getElementById('target-user')?.value.trim() || 'besoiu';
        try {
            const res = await fetch(form.dataset.endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) });
            const json = await res.json();
            if (!json.success) return alert(json.message || 'Eroare.');
            alert(document.getElementById('ridusers').value ? 'Cont actualizat!' : 'Cont salvat!');
            window.location.reload();
        } catch (e) { alert('Eroare conexiune.'); }
    }

    window.addEventListener('load', () => {
        document.getElementById('addpieseauto')?.addEventListener('submit', salveazaContPieseauto);
        ['accCompanyName','target-user'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', function () {
                updateDashboard();
            });
        });
        ['piesa_titlu','piesa_pret','piesa_stare','piesa_cat_nume'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', updateLivePreview);
            document.getElementById(id)?.addEventListener('change', updateLivePreview);
        });
        STABLE_STATUS.lastOkAt = PA_INIT.service_online ? Date.now() : 0;
        if (document.visibilityState === 'visible') {
            setTimeout(pollConnectionStatusBackground, 400);
        }
        ensureBackgroundStatusPolling();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                pollConnectionStatusBackground();
                ensureBackgroundStatusPolling();
            } else if (statusTimer) {
                clearInterval(statusTimer);
                statusTimer = null;
            }
        });
        setTimeout(reconectareAutomataRobot, 1200);
        randareIstoricAuto();
        afiseazaProduseScanate(window.produseScanateAll || [], false);
        updateAccountMode();
        updateLivePreview();
        document.getElementById('scan_search')?.addEventListener('input', () => incarcaProduseScanate(false));
    });
    </script>
