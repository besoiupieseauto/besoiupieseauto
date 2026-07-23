/**
 * Pagină dedicată /admin/planner — plan eficiență financiară (rânduri dinamice).
 * Robustețe: try/catch global, feedback vizibil (toast + banner), slidere aliniate cu inputurile,
 * presete built-in read-only + scenarii salvate separat.
 */
(function () {
  'use strict';

  const ENDPOINT = '/admin/api/dashboard_endpoint.php';
  const PLANNER_API = '/admin/api/planner_endpoint.php';
  const STRATEGIC_PLAN_URL = '/admin/public/assets/data/planner-strategic-plan.json';
  const PLANNER_STORAGE_KEY = 'bws_marketing_planner_v4';
  const LEGACY_STORAGE_KEY = 'bws_marketing_planner_v3';
  const SAVED_STORAGE_KEY = 'bws_marketing_planner_saved_v2';

  const SCENARIO_TYPE_LABELS = {
    custom: 'Personalizat',
    pessimistic: 'Pesimist',
    realistic: 'Realist',
    optimistic: 'Optimist',
  };

  const PRESET_KEYS = ['pessimistic', 'realistic', 'optimistic'];

  const PRESET_BUILTIN = {
    pessimistic: { users: 500, conversion: 2 },
    realistic: { users: 1000, conversion: 5 },
    optimistic: { users: 10000, conversion: 8 },
  };

  const USERS_SLIDER_BASE_MAX = 100000;
  const CONV_SLIDER_BASE_MAX = 20;
  const PLAUSIBILITY_USER_LIMIT = 500000;

  /** Ținte orientative din planul strategic — scenariu REALIST (implicit) */
  const STRATEGIC_MILESTONES = [
    { label: 'Luna 3', visits: 10000, conversion: 2 },
    { label: 'Luna 6', visits: 18000, conversion: 2 },
    { label: 'Luna 12', visits: 35000, conversion: 2 },
    { label: 'An 3 (run-rate)', visits: 65000, conversion: 2 },
  ];

  /** Milestone-uri per tip scenariu strategic (plan §5–6) */
  const SCENARIO_MILESTONE_PROFILES = {
    strategic_faza1_2026: [
      { label: 'Luna 3 (țintă scenariu)', visits: 10000, conversion: 2, isScenarioTarget: true },
    ],
    strategic_an1_conservator: [
      { label: 'Luna 3', visits: 8000, conversion: 2 },
      { label: 'Luna 6', visits: 12000, conversion: 2 },
      { label: 'Luna 12 (țintă scenariu)', visits: 15000, conversion: 2, isScenarioTarget: true },
    ],
    strategic_an1_realist: [
      { label: 'Luna 3', visits: 10000, conversion: 2 },
      { label: 'Luna 6', visits: 18000, conversion: 2 },
      { label: 'Luna 12 (țintă scenariu)', visits: 35000, conversion: 2, isScenarioTarget: true },
      { label: 'An 3 (orientativ)', visits: 65000, conversion: 2 },
    ],
    strategic_an1_optimist: [
      { label: 'Luna 3', visits: 10000, conversion: 2 },
      { label: 'Luna 6', visits: 22000, conversion: 2 },
      { label: 'Luna 12 (țintă scenariu)', visits: 55000, conversion: 2, isScenarioTarget: true },
    ],
    strategic_plan_36luni: [
      { label: 'Luna 12', visits: 35000, conversion: 2 },
      { label: 'An 2', visits: 50000, conversion: 2 },
      { label: 'An 3 (țintă scenariu)', visits: 65000, conversion: 2, isScenarioTarget: true },
    ],
  };

  function getMilestonesForPreset(preset, calc) {
    const slot = String(preset?.id || activeScenarioId || '');
    const profile = SCENARIO_MILESTONE_PROFILES[slot];
    if (profile && profile.length) {
      return profile.map((m) => Object.assign({}, m));
    }
    if (calc.targetUsers > 0) {
      return [
        { label: 'Ținta declarată în scenariu', visits: calc.targetUsers, conversion: calc.conversionRate || 2, isScenarioTarget: true },
      ];
    }
    return STRATEGIC_MILESTONES.map((m) => Object.assign({}, m));
  }

  const DEFAULT_ROWS = {
    expenses: [
      { id: 'exp_salary', label: 'Salarii + contribuții', value: 12000 },
      { id: 'exp_rent', label: 'Chirie spațiu', value: 2500 },
      { id: 'exp_amort', label: 'Amortizare active', value: 800 },
      { id: 'exp_util', label: 'Utilități', value: 600 },
      { id: 'exp_ins', label: 'Asigurări', value: 400 },
      { id: 'exp_acc', label: 'Contabilitate / juridic', value: 500 },
      { id: 'exp_it', label: 'IT, hosting, software', value: 350 },
      { id: 'exp_mkt', label: 'Marketing operațional', value: 700 },
      { id: 'exp_trans', label: 'Combustibil / transport', value: 450 },
    ],
    income: [
      { id: 'inc_shop', label: 'Magazin online (site)', value: 3500 },
      { id: 'inc_mkt', label: 'Marketplace (PieseAuto.ro)', value: 1200 },
      { id: 'inc_b2b', label: 'Caiet comenzi / B2B', value: 800 },
    ],
    scenario: [
      { id: 'sc_users', label: 'Utilizatori țintă', value: 1000, calcKey: 'target_users', step: '1', min: 0 },
      { id: 'sc_conv', label: 'Rată conversie (%)', value: 5, calcKey: 'conversion_rate', step: '0.1', min: 0, max: 100 },
      { id: 'sc_price', label: 'Preț mediu produs (RON)', value: 141.23, calcKey: 'avg_price', step: '0.01', min: 0 },
    ],
    investments: [
      { id: 'inv_dev', label: 'Elaborare platformă / ERP', value: 15000 },
      { id: 'inv_promo', label: 'Promovare (campanii)', value: 5000 },
    ],
  };

  const CALC_KEY_LABELS = {
    target_users: 'utilizatori',
    conversion_rate: 'conversie',
    avg_price: 'preț mediu',
  };

  let activeScenarioId = null;
  let plannerState = null;
  let plannerPricing = { avg: 0, min: 0, max: 0, priced_count: 0 };
  let plannerBound = false;
  let recalcTimer = null;
  let plausibilityWarned = false;

  // Sincronizare server (audit #43) — localStorage rămâne cache offline / fallback.
  let serverAvailable = false;
  let serverSyncDone = false;
  let serverPresetsCache = [];
  let serverSaveTimer = null;

  /* ---------------------------------------------------------------------- */
  /* Feedback vizibil (toast + banner de eroare)                            */
  /* ---------------------------------------------------------------------- */

  function ensureToastHost() {
    let host = document.getElementById('bws-planner-toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'bws-planner-toast-host';
      host.className = 'bws-planner-toast-host';
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    return host;
  }

  function showToast(message, tone) {
    try {
      const host = ensureToastHost();
      const toast = document.createElement('div');
      toast.className = 'bws-planner-toast' + (tone ? ' is-' + tone : '');
      toast.textContent = message;
      host.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('is-visible'));
      setTimeout(() => {
        toast.classList.remove('is-visible');
        setTimeout(() => toast.remove(), 250);
      }, 2600);
    } catch (e) {
      /* fallback silențios */
    }
  }

  function showPlannerError(message) {
    const banner = document.getElementById('bws-planner-error');
    const msg = document.getElementById('bws-planner-error-msg');
    if (msg && message) msg.textContent = message;
    if (banner) banner.hidden = false;
  }

  function hidePlannerError() {
    const banner = document.getElementById('bws-planner-error');
    if (banner) banner.hidden = true;
  }

  /* ---------------------------------------------------------------------- */
  /* Utilitare stare                                                        */
  /* ---------------------------------------------------------------------- */

  function getRowUnit(sectionKey, row) {
    if (sectionKey === 'expenses' || sectionKey === 'income') return 'RON/lună';
    if (sectionKey === 'investments') return 'RON total';
    if (row.calcKey === 'target_users') return 'utilizatori';
    if (row.calcKey === 'conversion_rate') return '%';
    if (row.calcKey === 'avg_price') return 'RON';
    return 'valoare';
  }

  function uid(prefix) {
    return prefix + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  }

  function cloneRows(rows) {
    return rows.map((row) => ({ ...row }));
  }

  function formatDateIso(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function defaultMeta(overrides) {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 3, 0);
    return Object.assign({
      name: 'Scenariu',
      periodStart: formatDateIso(start),
      periodEnd: formatDateIso(end),
      assumptions: '',
      type: 'custom',
    }, overrides || {});
  }

  function periodMonthsFromMeta(meta) {
    if (!meta || !meta.periodStart || !meta.periodEnd) return 3;
    const start = new Date(meta.periodStart);
    const end = new Date(meta.periodEnd);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return 3;
    const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1;
    return Math.max(1, Math.min(36, months));
  }

  function formatPeriodLabel(meta) {
    if (!meta || (!meta.periodStart && !meta.periodEnd)) return '—';
    const start = meta.periodStart || '—';
    const end = meta.periodEnd || '—';
    const months = periodMonthsFromMeta(meta);
    return start + ' → ' + end + ' (' + months + ' luni)';
  }

  function ensureMeta(state) {
    state.meta = Object.assign(defaultMeta(), state.meta || {});
    return state;
  }

  function migrateState(parsed) {
    if (!parsed || typeof parsed !== 'object') return defaultState();
    if (parsed.version === 4 && isValidState(parsed)) return ensureMeta(deepCloneState(parsed));
    if (parsed.version === 3 && isValidState(parsed)) {
      const next = deepCloneState(parsed);
      next.version = 4;
      next.meta = defaultMeta();
      return next;
    }
    if (parsed.salary !== undefined) return ensureMeta(migrateLegacyV2(parsed));
    return defaultState();
  }

  function emptyScenarioState(metaOverrides) {
    return {
      version: 4,
      meta: defaultMeta(metaOverrides),
      expenses: [],
      income: [],
      scenario: [],
      investments: [],
    };
  }

  function defaultState() {
    return emptyScenarioState();
  }

  function deepCloneState(state) {
    return {
      version: 4,
      meta: Object.assign(defaultMeta(), state.meta || {}),
      expenses: cloneRows(state.expenses || []),
      income: cloneRows(state.income || []),
      scenario: cloneRows(state.scenario || []),
      investments: cloneRows(state.investments || []),
    };
  }

  function isValidState(parsed) {
    return !!parsed
      && (parsed.version === 3 || parsed.version === 4)
      && Array.isArray(parsed.expenses)
      && Array.isArray(parsed.income)
      && Array.isArray(parsed.scenario)
      && Array.isArray(parsed.investments);
  }

  /* ---------------------------------------------------------------------- */
  /* Scenarii salvate (separate de built-in)                                */
  /* ---------------------------------------------------------------------- */

  function readLocalSavedPresets() {
    try {
      const raw = localStorage.getItem(SAVED_STORAGE_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.filter((p) => p && p.state) : [];
    } catch (e) {
      return [];
    }
  }

  function writeLocalSavedPresets(list) {
    try {
      localStorage.setItem(SAVED_STORAGE_KEY, JSON.stringify(list));
    } catch (e) {
      showToast('Nu am putut salva scenariul (spațiu localStorage plin).', 'error');
    }
  }

  function readSavedPresets() {
    return serverAvailable ? serverPresetsCache : readLocalSavedPresets();
  }

  function hasStrategicScenarios() {
    return readSavedPresets().some((p) => String(p.id || '').startsWith('strategic_'));
  }

  function updateStrategicImportBanner() {
    const banner = document.getElementById('bws-planner-strategic-import');
    if (!banner) return;
    banner.hidden = hasStrategicScenarios();
  }

  async function fetchStrategicTemplates() {
    const res = await fetch(STRATEGIC_PLAN_URL, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Nu am putut încărca planul strategic.');
    const json = await res.json();
    if (!json || !Array.isArray(json.templates)) {
      throw new Error('Structură plan strategic invalidă.');
    }
    return json;
  }

  function buildStateFromStrategicTemplate(template) {
    const meta = Object.assign(defaultMeta(), template.meta || {});
    const cloneSection = (rows) => (
      Array.isArray(rows)
        ? rows.map((row) => Object.assign({}, row))
        : []
    );
    return {
      version: 4,
      meta: meta,
      expenses: cloneSection(template.expenses),
      income: cloneSection(template.income),
      scenario: cloneSection(template.scenario),
      investments: cloneSection(template.investments),
    };
  }

  function importStrategicPlansLocal(templates, skipExisting) {
    let imported = 0;
    let skipped = 0;
    let list = readLocalSavedPresets();

    templates.forEach((template) => {
      const slot = String(template.slot || '').trim();
      if (!slot) return;
      if (skipExisting && list.some((p) => p.id === slot)) {
        skipped++;
        return;
      }
      const state = buildStateFromStrategicTemplate(template);
      const preset = {
        id: slot,
        name: template.name || state.meta.name || 'Scenariu strategic',
        savedAt: new Date().toISOString(),
        state: state,
        meta: state.meta,
      };
      list = list.filter((p) => p.id !== slot);
      list.push(preset);
      imported++;
    });

    writeLocalSavedPresets(list);
    serverPresetsCache = list;
    return { imported: imported, skipped: skipped };
  }

  async function importStrategicPlans() {
    const btn = document.getElementById('bws-planner-import-strategic');
    if (btn) btn.disabled = true;

    try {
      const catalog = await fetchStrategicTemplates();
      const templates = catalog.templates || [];

      if (serverAvailable) {
        const json = await plannerApi('import_strategic_plans', { skip_existing: true });
        serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
        const imported = Number(json.data?.imported || 0);
        const skipped = Number(json.data?.skipped || 0);
        renderSavedPresets();
        updateStrategicImportBanner();
        if (imported > 0) {
          showToast('Importate ' + imported + ' scenarii din planul strategic.', 'success');
        } else if (skipped > 0) {
          showToast('Scenariile strategice există deja — deschide-le din listă.', 'neutral');
        } else {
          showToast('Niciun scenariu nou de importat.', 'neutral');
        }
        return;
      }

      const result = importStrategicPlansLocal(templates, true);
      renderSavedPresets();
      updateStrategicImportBanner();
      if (result.imported > 0) {
        showToast('Importate ' + result.imported + ' scenarii (salvare locală).', 'success');
      } else {
        showToast('Scenariile strategice există deja.', 'neutral');
      }
    } catch (e) {
      showToast(e.message || 'Import plan strategic eșuat.', 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  function showListView() {
    activeScenarioId = null;
    plannerState = null;
    const list = document.getElementById('bws-planner-view-list');
    const detail = document.getElementById('bws-planner-view-detail');
    const headList = document.getElementById('bws-planner-head-actions-list');
    const headDetail = document.getElementById('bws-planner-head-actions-detail');
    if (list) list.hidden = false;
    if (detail) detail.hidden = true;
    if (headList) headList.hidden = false;
    if (headDetail) headDetail.hidden = true;
    const title = document.getElementById('bws-planner-page-title');
    const sub = document.getElementById('bws-planner-page-subtitle');
    if (title) title.textContent = 'Scenarii financiare';
    if (sub) sub.textContent = 'Creezi un scenariu, apoi intri în el și adaugi manual cheltuieli, venituri și ce ai nevoie.';
    if (window.location.hash) {
      history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    renderSavedPresets();
    updateStrategicImportBanner();
  }

  function showDetailView(scenarioId, preset) {
    activeScenarioId = scenarioId;
    plannerState = ensureMeta(deepCloneState(preset.state || emptyScenarioState(preset.meta)));
    if (preset.meta) plannerState.meta = Object.assign(defaultMeta(), preset.meta, plannerState.meta);
    if (preset.name) plannerState.meta.name = preset.name;

    const list = document.getElementById('bws-planner-view-list');
    const detail = document.getElementById('bws-planner-view-detail');
    const headList = document.getElementById('bws-planner-head-actions-list');
    const headDetail = document.getElementById('bws-planner-head-actions-detail');
    if (list) list.hidden = true;
    if (detail) detail.hidden = false;
    if (headList) headList.hidden = true;
    if (headDetail) headDetail.hidden = false;

    const title = document.getElementById('bws-planner-page-title');
    const sub = document.getElementById('bws-planner-page-subtitle');
    if (title) title.textContent = plannerState.meta.name || preset.name || 'Scenariu';
    if (sub) sub.textContent = formatPeriodLabel(plannerState.meta);

    renderAllSections(plannerState);
    renderActiveMeta(plannerState);
    updateResults(plannerState);
    renderFeasibilityPanel(plannerState, calcPlanner(plannerState), preset);
    document.getElementById('bws-planner-feasibility')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.location.hash = 'scenario=' + encodeURIComponent(scenarioId);
  }

  function openScenario(id) {
    const preset = readSavedPresets().find((p) => p.id === id);
    if (!preset) {
      showToast('Scenariul nu a fost găsit.', 'error');
      return;
    }
    showDetailView(id, preset);
  }

  function saveActiveScenario() {
    if (!activeScenarioId) return;
    const state = collectStateFromDom();
    const name = state.meta.name || 'Scenariu';
    if (serverAvailable) {
      clearTimeout(serverSaveTimer);
      serverSaveTimer = setTimeout(() => {
        plannerApi('update_preset', { id: activeScenarioId, name: name, state: state })
          .then((json) => {
            serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
          })
          .catch(() => { /* retry la următoarea modificare */ });
      }, 600);
      return;
    }
    const list = readLocalSavedPresets().map((p) => (
      p.id === activeScenarioId ? { ...p, name: name, state: state, meta: state.meta, savedAt: new Date().toISOString() } : p
    ));
    writeLocalSavedPresets(list);
    serverPresetsCache = list;
  }

  function collectMetaFromDom() {
    return {
      name: (document.getElementById('bws-planner-meta-name')?.value || '').trim() || 'Scenariu',
      periodStart: document.getElementById('bws-planner-meta-start')?.value || '',
      periodEnd: document.getElementById('bws-planner-meta-end')?.value || '',
      assumptions: (document.getElementById('bws-planner-meta-assumptions')?.value || '').trim(),
      type: 'custom',
    };
  }

  function renderActiveMeta(state) {
    const meta = ensureMeta(state).meta;
    const nameEl = document.getElementById('bws-planner-meta-name');
    const startEl = document.getElementById('bws-planner-meta-start');
    const endEl = document.getElementById('bws-planner-meta-end');
    const assumptionsEl = document.getElementById('bws-planner-meta-assumptions');
    if (nameEl) nameEl.value = meta.name || '';
    if (startEl) startEl.value = meta.periodStart || '';
    if (endEl) endEl.value = meta.periodEnd || '';
    if (assumptionsEl) assumptionsEl.value = meta.assumptions || '';
  }

  function persistMetaFromDom() {
    if (!activeScenarioId) return;
    const state = collectStateFromDom();
    plannerState = state;
    const title = document.getElementById('bws-planner-page-title');
    if (title) title.textContent = state.meta.name || 'Scenariu';
    saveActiveScenario();
  }

  function openScenarioModal() {
    const modal = document.getElementById('bws-planner-modal');
    if (!modal) return;
    document.getElementById('bws-planner-modal-title').textContent = 'Scenariu nou';
    document.getElementById('bws-planner-modal-id').value = '';
    const meta = defaultMeta({ name: 'Scenariu ' + (readSavedPresets().length + 1) });
    document.getElementById('bws-planner-modal-name').value = meta.name || '';
    document.getElementById('bws-planner-modal-start').value = meta.periodStart || '';
    document.getElementById('bws-planner-modal-end').value = meta.periodEnd || '';
    document.getElementById('bws-planner-modal-assumptions').value = '';
    modal.hidden = false;
  }

  function closeScenarioModal() {
    const modal = document.getElementById('bws-planner-modal');
    if (modal) modal.hidden = true;
  }

  function buildStateForSave(baseState) {
    const state = deepCloneState(baseState || collectStateFromDom());
    state.meta = Object.assign(defaultMeta(), collectMetaFromDom(), state.meta || {});
    return state;
  }

  function saveScenarioFromModal() {
    const meta = {
      name: (document.getElementById('bws-planner-modal-name')?.value || '').trim() || 'Scenariu',
      periodStart: document.getElementById('bws-planner-modal-start')?.value || '',
      periodEnd: document.getElementById('bws-planner-modal-end')?.value || '',
      assumptions: (document.getElementById('bws-planner-modal-assumptions')?.value || '').trim(),
      type: 'custom',
    };

    const state = emptyScenarioState(meta);
    const preset = {
      id: uid('preset'),
      name: meta.name,
      savedAt: new Date().toISOString(),
      state: state,
      meta: meta,
    };

    if (serverAvailable) {
      plannerApi('save_preset', { id: preset.id, name: preset.name, state: preset.state })
        .then((json) => {
          serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
          const saved = (json.data && json.data.id) ? json.data : preset;
          closeScenarioModal();
          showToast('Scenariu creat.', 'success');
          openScenario(saved.id || preset.id);
        })
        .catch((err) => showToast(err.message || 'Eroare la creare scenariu.', 'error'));
      return;
    }

    const list = readLocalSavedPresets();
    list.push(preset);
    writeLocalSavedPresets(list);
    serverPresetsCache = list;
    closeScenarioModal();
    showToast('Scenariu creat.', 'success');
    openScenario(preset.id);
  }

  function persistPreset(preset, name, isUpdate) {
    if (serverAvailable) {
      const action = isUpdate ? 'update_preset' : 'save_preset';
      plannerApi(action, { id: preset.id, name: name, state: preset.state })
        .then((json) => {
          serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
          renderSavedPresets();
          showToast(isUpdate ? 'Scenariu actualizat pe server.' : 'Scenariu salvat pe server.', 'success');
        })
        .catch((err) => showToast(err.message || 'Eroare la salvare scenariu.', 'error'));
      return;
    }
    let list = readLocalSavedPresets();
    if (isUpdate) {
      list = list.map((p) => (p.id === preset.id ? preset : p));
    } else {
      list.push(preset);
    }
    writeLocalSavedPresets(list);
    renderSavedPresets();
    showToast(isUpdate ? 'Scenariu actualizat local.' : 'Scenariu salvat local.', 'success');
  }

  function updateSavedPreset(id, name, state) {
    const preset = { id: id, name: name, savedAt: new Date().toISOString(), state: state, meta: state.meta };
    persistPreset(preset, name, true);
  }

  function saveCurrentPreset() {
    openScenarioModal('create');
    const meta = collectMetaFromDom();
    document.getElementById('bws-planner-modal-name').value = meta.name || ('Scenariu ' + (readSavedPresets().length + 1));
    document.getElementById('bws-planner-modal-type').value = meta.type || 'custom';
    document.getElementById('bws-planner-modal-start').value = meta.periodStart || '';
    document.getElementById('bws-planner-modal-end').value = meta.periodEnd || '';
    document.getElementById('bws-planner-modal-assumptions').value = meta.assumptions || '';
    document.getElementById('bws-planner-modal-id').value = '';
    document.getElementById('bws-planner-modal-title').textContent = 'Salvează planul curent';
  }

  function saveBuiltinAsScenario(key) {
    const base = applyBuiltinScenario(collectStateFromDom(), key);
    base.meta = defaultMeta({
      name: (SCENARIO_TYPE_LABELS[key] || 'Scenariu') + ' ' + (readSavedPresets().length + 1),
      type: key,
    });
    const preset = {
      id: uid('preset'),
      name: base.meta.name,
      savedAt: new Date().toISOString(),
      state: base,
      meta: base.meta,
    };
    persistPreset(preset, base.meta.name, false);
  }

  function duplicateSavedPreset(id) {
    const preset = readSavedPresets().find((p) => p.id === id);
    if (!preset) return;
    const copy = deepCloneState(preset.state);
    copy.meta = Object.assign(defaultMeta(), copy.meta || {}, {
      name: (preset.name || 'Scenariu') + ' (copie)',
    });
    if (serverAvailable) {
      plannerApi('duplicate_preset', { id: id, name: copy.meta.name, state: copy })
        .then((json) => {
          serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
          renderSavedPresets();
          showToast('Scenariu duplicat.', 'success');
        })
        .catch((err) => showToast(err.message || 'Nu am putut duplica scenariul.', 'error'));
      return;
    }
    const newPreset = {
      id: uid('preset'),
      name: copy.meta.name,
      savedAt: new Date().toISOString(),
      state: copy,
      meta: copy.meta,
    };
    const list = readLocalSavedPresets();
    list.push(newPreset);
    writeLocalSavedPresets(list);
    renderSavedPresets();
    showToast('Scenariu duplicat.', 'success');
  }

  function scenarioVerdict(calc) {
    if (calc.result > 0) return { label: 'Profitabil', tone: 'profit' };
    if (calc.result < 0) return { label: 'Deficit', tone: 'loss' };
    return { label: 'Echilibru', tone: 'neutral' };
  }

  function getSelectedScenarioIds() {
    return Array.from(document.querySelectorAll('[data-scenario-select]:checked'))
      .map((el) => el.getAttribute('data-scenario-select'))
      .filter(Boolean);
  }

  function renderScenarioCompare() {
    const ids = getSelectedScenarioIds();
    const charts = document.getElementById('bws-planner-compare-charts');
    const tableWrap = document.getElementById('bws-planner-compare-table-wrap');
    const tbody = document.getElementById('bws-planner-compare-body');
    if (!charts || !tbody) return;

    if (ids.length < 2) {
      charts.innerHTML = '<p class="bws-planner-compare-empty">Selectează cel puțin 2 scenarii pentru comparație.</p>';
      if (tableWrap) tableWrap.hidden = true;
      return;
    }

    const presets = readSavedPresets().filter((p) => ids.includes(p.id));
    const rows = presets.map((preset) => {
      const calc = calcPlanner(ensureMeta(deepCloneState(preset.state)));
      const verdict = scenarioVerdict(calc);
      return { preset, calc, verdict };
    });

    const maxVal = Math.max.apply(null, rows.map((r) => Math.max(r.calc.expenseQuarterly, r.calc.totalQuarterly, 1)));
    let chartHtml = '<div class="bws-planner-compare-bars">';
    rows.forEach((row) => {
      const expPct = (row.calc.expenseQuarterly / maxVal) * 100;
      const incPct = (row.calc.totalQuarterly / maxVal) * 100;
      chartHtml += '<div class="bws-planner-compare-bar-group">'
        + '<div class="bws-planner-compare-bar-group__title">' + escapeHtml(row.preset.name || 'Scenariu') + '</div>'
        + '<div class="bws-planner-compare-bar-row"><span>Cheltuieli</span><div class="bws-planner-compare-bar"><i style="width:' + expPct + '%" class="is-expense"></i></div><strong>' + escapeHtml(fmtMoney(row.calc.expenseQuarterly)) + '</strong></div>'
        + '<div class="bws-planner-compare-bar-row"><span>Venit</span><div class="bws-planner-compare-bar"><i style="width:' + incPct + '%" class="is-income"></i></div><strong>' + escapeHtml(fmtMoney(row.calc.totalQuarterly)) + '</strong></div>'
        + '<div class="bws-planner-compare-bar-row"><span>Rezultat</span><strong class="is-' + row.verdict.tone + '">' + escapeHtml(fmtMoney(row.calc.result)) + '</strong></div>'
        + '</div>';
    });
    chartHtml += '</div>';
    charts.innerHTML = chartHtml;

    let tableHtml = '';
    rows.forEach((row) => {
      tableHtml += '<tr>'
        + '<td><strong>' + escapeHtml(row.preset.name || 'Scenariu') + '</strong><br><small>' + escapeHtml(formatPeriodLabel(row.preset.meta || row.preset.state?.meta)) + '</small></td>'
        + '<td>' + escapeHtml(fmtMoney(row.calc.expenseQuarterly)) + '</td>'
        + '<td>' + escapeHtml(fmtMoney(row.calc.totalQuarterly)) + '</td>'
        + '<td class="is-' + row.verdict.tone + '">' + escapeHtml(fmtMoney(row.calc.result)) + '</td>'
        + '<td>' + escapeHtml(row.calc.surplus <= 0 ? '—' : fmtQuartersRecover(row.calc.quartersToRecover)) + '</td>'
        + '<td><span class="bws-planner-pill is-' + (row.verdict.tone === 'profit' ? 'yes' : (row.verdict.tone === 'loss' ? 'no' : 'neutral')) + '">' + escapeHtml(row.verdict.label) + '</span></td>'
        + '</tr>';
    });
    tbody.innerHTML = tableHtml;
    if (tableWrap) tableWrap.hidden = false;
  }

  function isStrategicPreset(preset) {
    if (!preset) return false;
    const id = String(preset.id || '');
    const type = String(preset.meta?.type || preset.state?.meta?.type || '');
    return id.startsWith('strategic_') || type === 'strategic';
  }

  function formatScenarioTargetLine(calc) {
    if (!calc.targetUsers && !calc.avgPrice) return '—';
    const parts = [];
    if (calc.targetUsers > 0) parts.push(fmtNum(calc.targetUsers) + ' viz/lună');
    if (calc.conversionRate > 0) parts.push(calc.conversionRate.toLocaleString('ro-RO') + '% conv.');
    if (calc.avgPrice > 0) parts.push(fmtMoney(calc.avgPrice) + ' mediu');
    if (calc.scenarioMonthly > 0) parts.push('→ ' + fmtMoney(calc.scenarioMonthly) + '/lună');
    return parts.join(' · ');
  }

  function evaluateProjectFeasibility(calc, state) {
    const reasons = [];
    let tone = 'neutral';
    let label = 'De verificat';
    const hasLines = (state.expenses?.length || 0) + (state.income?.length || 0) + (state.scenario?.length || 0) > 0;

    if (!hasLines) {
      return {
        tone: 'neutral',
        label: 'Date incomplete',
        lead: 'Adaugă cheltuieli, venituri și ipoteze pentru a evalua fezabilitatea.',
        reasons: ['Scenariul nu are încă date suficiente pentru o evaluare.'],
      };
    }

    if (calc.result > 0) {
      const margin = calc.result / Math.max(calc.expenseQuarterly, 1);
      if (margin >= 0.15) {
        tone = 'profit';
        label = 'Proiect realizabil';
        reasons.push('Venitul total depășește cheltuielile cu ' + fmtMoney(calc.result) + ' pe trimestru.');
      } else {
        tone = 'warn';
        label = 'Realizabil — marjă subțire';
        reasons.push('Există profit (' + fmtMoney(calc.result) + '/trim.), dar marja e mică (~' + Math.round(margin * 100) + '% din cheltuieli).');
      }
    } else if (calc.result < 0) {
      tone = 'loss';
      label = 'Nerealizabil la datele curente';
      reasons.push('Deficit de ' + fmtMoney(calc.deficit) + ' pe trimestru — veniturile nu acoperă costurile.');
    } else {
      tone = 'neutral';
      label = 'La limită (echilibru zero)';
      reasons.push('Venitul acoperă exact cheltuielile, fără surplus pentru investiții sau riscuri.');
    }

    if (calc.targetUsers > 0 && calc.avgPrice > 0) {
      reasons.push(
        'Țintă marketing: ' + fmtNum(calc.targetUsers) + ' vizite × '
        + calc.conversionRate.toLocaleString('ro-RO') + '% × '
        + fmtMoney(calc.avgPrice) + ' = '
        + fmtMoney(calc.scenarioMonthly) + ' / lună ('
        + fmtNum(calc.orders) + ' comenzi estimate).'
      );
    }

    if (calc.incomeMonthly > 0) {
      reasons.push('Venit stabil (fără marketing): ' + fmtMoney(calc.incomeMonthly) + ' / lună.');
    }

    if (calc.investmentsTotal > 0) {
      if (calc.surplus > 0 && calc.remainingInvestments <= 0) {
        reasons.push('Investițiile de ' + fmtMoney(calc.investmentsTotal) + ' se recuperează din surplusul trimestrial.');
      } else if (calc.surplus > 0) {
        reasons.push('Din surplusul trimestrial rămân ' + fmtMoney(calc.remainingInvestments) + ' până acoperi toate investițiile.');
      } else {
        reasons.push('Investițiile (' + fmtMoney(calc.investmentsTotal) + ') nu se acoperă — lipsește profit trimestrial.');
        if (tone === 'profit') {
          tone = 'warn';
          label = 'Venit OK — investiții grele';
        }
      }
    }

    if (!calc.scenarioCovers && calc.stableCovers) {
      reasons.push('Doar venitul stabil acoperă cheltuielile; proiecția marketing nu ajunge la țintă.');
    }

    const lead = tone === 'profit'
      ? 'La cifrele din scenariu, proiectul poate fi susținut financiar în perioada aleasă.'
      : (tone === 'loss'
        ? 'La cifrele actuale proiectul nu se susține — ajustează venituri, trafic sau costuri.'
        : 'Verifică ipotezele și compară cu milestone-urile din planul strategic.');

    return { tone: tone, label: label, lead: lead, reasons: reasons };
  }

  function renderFeasibilityPanel(state, calc, preset) {
    const hero = document.getElementById('bws-planner-feasibility-hero');
    const badge = document.getElementById('bws-planner-feasibility-badge');
    const verdict = document.getElementById('bws-planner-feasibility-verdict');
    const lead = document.getElementById('bws-planner-feasibility-lead');
    if (!hero || !verdict) return;

    const evalResult = evaluateProjectFeasibility(calc, state);
    const strategic = isStrategicPreset(preset || { id: activeScenarioId, meta: state.meta });

    hero.setAttribute('data-tone', evalResult.tone);
    if (badge) {
      badge.textContent = strategic ? 'Plan strategic — evaluare fezabilitate' : 'Evaluare fezabilitate proiect';
      badge.classList.toggle('is-strategic', strategic);
    }
    verdict.textContent = evalResult.label;
    if (lead) lead.textContent = evalResult.lead;

    setText('bws-planner-eval-visits', calc.targetUsers > 0 ? fmtNum(calc.targetUsers) : '—');
    setText('bws-planner-eval-conv', calc.conversionRate > 0 ? calc.conversionRate.toLocaleString('ro-RO') + '%' : '—');
    setText('bws-planner-eval-price', calc.avgPrice > 0 ? fmtMoney(calc.avgPrice) : '—');
    setText('bws-planner-eval-mkt-month', calc.scenarioMonthly > 0 ? fmtMoney(calc.scenarioMonthly) : '—');
    setText('bws-planner-eval-total-month', fmtMoney(calc.incomeMonthly + calc.scenarioMonthly));
    setText('bws-planner-eval-expense-month', fmtMoney(calc.expenseMonthly));
    setText('bws-planner-eval-result-q', fmtMoney(calc.result));
    setText(
      'bws-planner-eval-surplus',
      calc.result >= 0 ? ('+' + fmtMoney(calc.surplus)) : ('-' + fmtMoney(calc.deficit))
    );

    const formulaEl = document.getElementById('bws-planner-eval-formula-text');
    if (formulaEl) {
      if (calc.targetUsers > 0 && calc.avgPrice > 0) {
        formulaEl.textContent = fmtNum(calc.targetUsers) + ' × '
          + calc.conversionRate.toLocaleString('ro-RO') + '% × '
          + fmtMoney(calc.avgPrice) + ' = '
          + fmtMoney(calc.scenarioMonthly) + ' / lună marketing'
          + ' + ' + fmtMoney(calc.incomeMonthly) + ' stabil'
          + ' − ' + fmtMoney(calc.expenseMonthly) + ' cheltuieli';
      } else {
        formulaEl.textContent = 'Completează vizite, conversie și preț mediu în secțiunea Ipoteze & proiecții.';
      }
    }

    const assumptionsWrap = document.getElementById('bws-planner-feasibility-assumptions-wrap');
    const assumptionsText = document.getElementById('bws-planner-feasibility-assumptions');
    const assumptions = (state.meta?.assumptions || '').trim();
    if (assumptionsWrap && assumptionsText) {
      if (assumptions) {
        assumptionsWrap.hidden = false;
        assumptionsText.textContent = assumptions;
      } else {
        assumptionsWrap.hidden = true;
        assumptionsText.textContent = '';
      }
    }

    const reasonsWrap = document.getElementById('bws-planner-feasibility-reasons-wrap');
    const reasonsList = document.getElementById('bws-planner-feasibility-reasons');
    if (reasonsWrap && reasonsList) {
      reasonsWrap.hidden = !evalResult.reasons.length;
      reasonsList.innerHTML = evalResult.reasons
        .map((reason) => '<li>' + escapeHtml(reason) + '</li>')
        .join('');
    }

    const milestonesWrap = document.getElementById('bws-planner-feasibility-milestones');
    if (milestonesWrap) {
      milestonesWrap.hidden = !strategic && calc.avgPrice <= 0;
    }
  }

  function feasibilityPillHtml(evalResult) {
    const toneMap = { profit: 'yes', loss: 'no', warn: 'warn', neutral: 'neutral' };
    const pillTone = toneMap[evalResult.tone] || 'neutral';
    return '<span class="bws-planner-pill is-' + pillTone + '">' + escapeHtml(evalResult.label) + '</span>';
  }

  function renderSavedPresets() {
    const list = document.getElementById('bws-planner-saved-list');
    const empty = document.getElementById('bws-planner-saved-empty');
    if (!list) return;

    const presets = readSavedPresets();
    list.querySelectorAll('tr[data-preset-row]').forEach((n) => n.remove());

    if (!presets.length) {
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;

    presets.forEach((preset) => {
      const state = ensureMeta(deepCloneState(preset.state || emptyScenarioState()));
      const calc = calcPlanner(state);
      const evalResult = evaluateProjectFeasibility(calc, state);
      const meta = Object.assign(defaultMeta(), preset.meta || {}, state.meta || {});
      const strategic = isStrategicPreset(preset);
      const tr = document.createElement('tr');
      tr.className = 'bws-planner-scenario-row' + (strategic ? ' is-strategic' : '');
      tr.setAttribute('data-preset-row', '1');
      tr.setAttribute('data-preset-id', preset.id);
      tr.innerHTML = ''
        + '<td><input type="checkbox" data-scenario-select="' + escapeHtml(preset.id) + '" aria-label="Selectează ' + escapeHtml(preset.name || 'scenariu') + '"></td>'
        + '<td>'
        + (strategic ? '<span class="bws-planner-strategic-tag">Plan strategic</span> ' : '')
        + '<strong>' + escapeHtml(preset.name || meta.name || 'Scenariu') + '</strong>'
        + '<div class="bws-planner-scenario-result-line is-' + (calc.result >= 0 ? 'profit' : 'loss') + '">'
        + 'Rezultat: ' + escapeHtml(fmtMoney(calc.result)) + ' / trim.'
        + '</div>'
        + '</td>'
        + '<td class="bws-planner-scenario-targets">' + escapeHtml(formatScenarioTargetLine(calc)) + '</td>'
        + '<td>' + escapeHtml(formatPeriodLabel(meta)) + '</td>'
        + '<td class="bws-planner-scenario-feasibility">' + feasibilityPillHtml(evalResult) + '</td>'
        + '<td class="bws-planner-scenario-actions">'
        + '<button type="button" class="bws-planner-preset-btn bws-planner-preset-btn--primary" data-saved-open="' + escapeHtml(preset.id) + '">Evaluează</button>'
        + '<button type="button" class="bws-planner-preset-btn bws-planner-preset-btn--ghost" data-saved-dup="' + escapeHtml(preset.id) + '">Copiază</button>'
        + '<button type="button" class="bws-planner-preset-btn bws-planner-preset-btn--ghost" data-saved-del="' + escapeHtml(preset.id) + '">Șterge</button>'
        + '</td>';
      list.appendChild(tr);
    });
  }

  function deleteSavedPreset(id) {
    const wasActive = activeScenarioId === id;
    const finish = () => {
      if (wasActive) showListView();
      else renderSavedPresets();
      showToast('Scenariu șters.', 'neutral');
    };

    if (serverAvailable) {
      plannerApi('delete_preset', { id: id })
        .then((json) => {
          serverPresetsCache = Array.isArray(json.presets) ? json.presets : serverPresetsCache;
          finish();
        })
        .catch((err) => {
          showToast(err.message || 'Nu am putut șterge scenariul pe server.', 'error');
        });
      return;
    }

    const list = readLocalSavedPresets().filter((p) => p.id !== id);
    writeLocalSavedPresets(list);
    serverPresetsCache = list;
    finish();
  }

  function openScenarioFromHash() {
    const match = window.location.hash.match(/scenario=([^&]+)/);
    if (!match) return;
    const id = decodeURIComponent(match[1]);
    if (readSavedPresets().some((p) => p.id === id)) {
      openScenario(id);
    } else {
      showListView();
    }
  }

  /* ---------------------------------------------------------------------- */
  /* Presete built-in (read-only)                                           */
  /* ---------------------------------------------------------------------- */

  function applyBuiltinScenario(state, key) {
    const builtin = PRESET_BUILTIN[key];
    if (!builtin) return state;
    const next = deepCloneState(state);
    const usersRow = next.scenario.find((r) => r.calcKey === 'target_users');
    const convRow = next.scenario.find((r) => r.calcKey === 'conversion_rate');
    if (usersRow) usersRow.value = builtin.users;
    if (convRow) convRow.value = builtin.conversion;
    if (plannerPricing.avg > 0) {
      const priceRow = next.scenario.find((r) => r.calcKey === 'avg_price');
      if (priceRow) priceRow.value = plannerPricing.avg;
    }
    return next;
  }

  function loadBuiltinPreset(key) {
    const base = plannerState || readPlannerState();
    plannerState = applyBuiltinScenario(base, key);
    plannerState.meta = Object.assign(defaultMeta(), plannerState.meta || {}, {
      type: key,
      name: (SCENARIO_TYPE_LABELS[key] || 'Scenariu') + ' (activ)',
    });
    savePlannerState(plannerState);
    renderAllSections(plannerState);
    renderActiveMeta(plannerState);
    updateResults(plannerState);
    const b = PRESET_BUILTIN[key];
    if (b) {
      showToast('Scenariu aplicat: ' + fmtNum(b.users) + ' util. · '
        + b.conversion.toLocaleString('ro-RO') + '%.', 'success');
    }
  }

  function updatePresetMeta() {
    PRESET_KEYS.forEach((key) => {
      const node = document.getElementById('bws-preset-meta-' + key);
      const builtin = PRESET_BUILTIN[key];
      if (!node || !builtin) return;
      node.textContent = fmtNum(builtin.users) + ' util. · '
        + builtin.conversion.toLocaleString('ro-RO') + '%';
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Slidere (adaptive — urmăresc inputul, fără clamp distructiv)           */
  /* ---------------------------------------------------------------------- */

  function refreshSliderRanges(calc) {
    const usersSlider = document.getElementById('bws-slider-users');
    const convSlider = document.getElementById('bws-slider-conv');
    const usersMaxLabel = document.getElementById('bws-slider-users-max');
    const convMaxLabel = document.getElementById('bws-slider-conv-max');

    if (usersSlider) {
      let max = USERS_SLIDER_BASE_MAX;
      if (calc.target_users > max) max = Math.ceil(calc.target_users / 1000) * 1000;
      usersSlider.max = String(max);
      if (usersMaxLabel) usersMaxLabel.textContent = fmtNum(max);
    }
    if (convSlider) {
      let max = CONV_SLIDER_BASE_MAX;
      if (calc.conversion_rate > max) max = Math.min(100, Math.ceil(calc.conversion_rate));
      convSlider.max = String(max);
      if (convMaxLabel) convMaxLabel.textContent = max.toLocaleString('ro-RO') + '%';
    }
  }

  function syncSlidersFromState(state) {
    const calc = getScenarioCalc(state.scenario || []);
    refreshSliderRanges(calc);

    const usersSlider = document.getElementById('bws-slider-users');
    const convSlider = document.getElementById('bws-slider-conv');
    const usersVal = document.getElementById('bws-slider-users-val');
    const convVal = document.getElementById('bws-slider-conv-val');

    if (usersSlider) {
      usersSlider.value = String(Math.max(0, Math.min(Number(usersSlider.max), calc.target_users)));
    }
    if (convSlider) {
      convSlider.value = String(Math.max(0, Math.min(Number(convSlider.max), calc.conversion_rate)));
    }
    if (usersVal) usersVal.textContent = fmtNum(calc.target_users);
    if (convVal) convVal.textContent = calc.conversion_rate.toLocaleString('ro-RO') + '%';
  }

  function setScenarioCalcValue(calcKey, value) {
    const line = document.querySelector('.bws-planner-line[data-calc-key="' + calcKey + '"]');
    if (!line) return;
    const input = line.querySelector('.bws-planner-value-input');
    if (input) input.value = String(value);
  }

  /** Apelat în timpul tragerii sliderului — nu resincronizează poziția (evită „săritul"). */
  function handleSliderInput(slider) {
    if (!slider) return;
    if (slider.id === 'bws-slider-users') {
      setScenarioCalcValue('target_users', slider.value);
      const usersVal = document.getElementById('bws-slider-users-val');
      if (usersVal) usersVal.textContent = fmtNum(parsePlannerNumber(slider.value));
    } else if (slider.id === 'bws-slider-conv') {
      setScenarioCalcValue('conversion_rate', slider.value);
      const convVal = document.getElementById('bws-slider-conv-val');
      if (convVal) convVal.textContent = parsePlannerNumber(slider.value).toLocaleString('ro-RO') + '%';
    }
    const state = collectStateFromDom();
    savePlannerState(state);
    updateResults(state, { skipSliderSync: true });
  }

  function resetPlannerDefaults() {
    if (!window.confirm('Resetezi tot planul la valorile implicite? Scenariile salvate rămân intacte.')) {
      return;
    }
    try {
      localStorage.removeItem(PLANNER_STORAGE_KEY);
    } catch (e) {
      /* ignore */
    }
    plannerState = defaultState();
    if (plannerPricing.avg > 0) {
      const priceRow = plannerState.scenario.find((r) => r.calcKey === 'avg_price');
      if (priceRow) priceRow.value = plannerPricing.avg;
    }
    savePlannerState(plannerState);
    renderAllSections(plannerState);
    updateResults(plannerState);
    showToast('Planul a fost resetat la valorile implicite.', 'neutral');
  }

  /* ---------------------------------------------------------------------- */
  /* Tabel strategie                                                        */
  /* ---------------------------------------------------------------------- */

  function updateStrategyTable(calc, preset) {
    const tbody = document.getElementById('bws-planner-strategy-body');
    const note = document.getElementById('bws-planner-strategy-note');
    if (!tbody) return;

    const scenarioMonthly = calc.scenarioMonthly || 0;
    const avgPrice = calc.avgPrice || 0;
    const milestones = getMilestonesForPreset(preset, calc);

    if (avgPrice <= 0) {
      tbody.innerHTML = '<tr><td colspan="6">Completează prețul mediu în scenariul marketing pentru comparație.</td></tr>';
      return;
    }

    let html = '';
    milestones.forEach((milestone) => {
      const conv = milestone.conversion ?? calc.conversionRate ?? 2;
      const targetRevenue = Math.round(milestone.visits * (conv / 100) * avgPrice);
      const diff = scenarioMonthly - targetRevenue;
      const isTargetRow = !!milestone.isScenarioTarget;
      const onTarget = Math.abs(diff) < 1;
      const diffClass = onTarget ? 'is-on-target' : (diff >= 0 ? 'is-ahead' : 'is-behind');
      let diffText;
      if (onTarget && isTargetRow) {
        diffText = 'La țintă ✓';
      } else if (onTarget) {
        diffText = '0,00 RON';
      } else {
        diffText = (diff >= 0 ? '+' : '') + fmtMoney(diff);
      }

      html += '<tr class="' + (isTargetRow ? 'is-scenario-target-row' : '') + '">'
        + '<td><strong>' + escapeHtml(milestone.label) + '</strong>'
        + (isTargetRow ? ' <span class="bws-planner-milestone-tag">ținta ta</span>' : '')
        + '</td>'
        + '<td>' + fmtNum(milestone.visits) + '</td>'
        + '<td>' + conv.toLocaleString('ro-RO') + '%</td>'
        + '<td>' + fmtMoney(targetRevenue) + '</td>'
        + '<td>' + fmtMoney(scenarioMonthly) + '</td>'
        + '<td class="' + diffClass + '">' + escapeHtml(diffText) + '</td>'
        + '</tr>';
    });
    tbody.innerHTML = html;

    if (note) {
      note.textContent = 'Compară venitul marketing al scenariului ('
        + fmtMoney(scenarioMonthly) + '/lună) cu etapele de creștere din plan. '
        + 'Minusul (roșu) = sub ținta acelui milestone — nu înseamnă pierdere sau deficit. '
        + 'Rândul „ținta ta” trebuie să fie verde când cifrele scenariului coincid cu planul ales.';
    }
  }

  function bindSectionNav() {
    const nav = document.getElementById('bws-planner-section-nav');
    if (!nav || nav.dataset.bound === '1') return;
    nav.dataset.bound = '1';

    const links = nav.querySelectorAll('.bws-planner-section-nav__link');
    links.forEach((link) => {
      link.addEventListener('click', (event) => {
        const href = link.getAttribute('href');
        if (!href || !href.startsWith('#')) return;
        const target = document.querySelector(href);
        if (!target) return;
        event.preventDefault();
        if (target.tagName === 'DETAILS') {
          target.open = true;
        }
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        links.forEach((l) => l.classList.remove('is-active'));
        link.classList.add('is-active');
      });
    });

    const sections = document.querySelectorAll('[id^="bws-planner-stage-"]');
    if (!sections.length || typeof IntersectionObserver === 'undefined') return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const id = entry.target.id.replace('bws-planner-stage-', '');
        links.forEach((l) => {
          l.classList.toggle('is-active', l.getAttribute('data-section') === id);
        });
      });
    }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });

    sections.forEach((section) => observer.observe(section));
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildSectionTable(title, rows, unit) {
    let html = '<h2>' + escapeHtml(title) + '</h2><table><thead><tr><th>Denumire</th><th>Valoare</th></tr></thead><tbody>';
    rows.forEach((row) => {
      html += '<tr><td>' + escapeHtml(row.label) + '</td><td>'
        + escapeHtml(fmtNum(row.value)) + ' ' + escapeHtml(unit) + '</td></tr>';
    });
    html += '</tbody></table>';
    return html;
  }

  function exportPlannerPdf() {
    const state = collectStateFromDom();
    const calc = calcPlanner(state);
    const now = new Date().toLocaleString('ro-RO');
    const verdict = calc.result > 0
      ? 'PROFIT ' + fmtMoney(calc.result) + ' / trimestru'
      : (calc.result < 0 ? 'DEFICIT ' + fmtMoney(calc.deficit) + ' / trimestru' : 'Echilibru la zero');

    const html = '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8"><title>Plan eficiență — Besoiu Piese Auto</title>'
      + '<style>'
      + 'body{font-family:Segoe UI,Arial,sans-serif;color:#111;padding:24px;font-size:12px;line-height:1.45}'
      + 'h1{margin:0 0 4px;font-size:20px} .meta{color:#666;margin-bottom:18px}'
      + '.verdict{padding:12px 14px;border-radius:8px;margin:16px 0;font-weight:700;font-size:14px}'
      + '.verdict.profit{background:#ecfdf5;border:1px solid #86efac;color:#166534}'
      + '.verdict.loss{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b}'
      + 'h2{margin:18px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#475569}'
      + 'table{width:100%;border-collapse:collapse;margin-bottom:8px}'
      + 'th,td{border:1px solid #e2e8f0;padding:7px 8px;text-align:left}'
      + 'th{background:#f8fafc;font-size:10px;text-transform:uppercase}'
      + '.kpi{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0}'
      + '.kpi div{border:1px solid #e2e8f0;border-radius:8px;padding:10px}'
      + '.kpi strong{display:block;font-size:15px;margin-top:4px}'
      + '.foot{margin-top:20px;font-size:10px;color:#94a3b8}'
      + '@media print{body{padding:0}}</style></head><body>'
      + '<h1>Plan eficiență financiară</h1>'
      + '<div class="meta">Besoiu Piese Auto · generat ' + escapeHtml(now) + '</div>'
      + '<div class="verdict ' + (calc.result > 0 ? 'profit' : (calc.result < 0 ? 'loss' : '')) + '">' + escapeHtml(verdict) + '</div>'
      + buildSectionTable('1. Cheltuieli lunare', state.expenses, 'RON/lună')
      + '<p><strong>Total cheltuieli trimestriale:</strong> ' + escapeHtml(fmtMoney(calc.expenseQuarterly)) + '</p>'
      + buildSectionTable('2. Venit stabil lunar', state.income, 'RON/lună')
      + '<p><strong>Total venit stabil trimestrial:</strong> ' + escapeHtml(fmtMoney(calc.incomeQuarterly)) + '</p>'
      + buildSectionTable('3. Scenariu marketing', state.scenario, '—')
      + '<p><strong>Formula:</strong> ' + escapeHtml(
        fmtNum(calc.targetUsers) + ' × ' + calc.conversionRate + '% = '
        + fmtNum(calc.orders) + ' comenzi × ' + fmtMoney(calc.avgPrice)
      ) + '</p>'
      + '<p><strong>Venit scenariu trimestrial:</strong> ' + escapeHtml(fmtMoney(calc.scenarioQuarterly)) + '</p>'
      + buildSectionTable('4. Investiții', state.investments, 'RON total')
      + '<div class="kpi">'
      + '<div>Venit total trimestrial<strong>' + escapeHtml(fmtMoney(calc.totalQuarterly)) + '</strong></div>'
      + '<div>Rezultat operațional<strong>' + escapeHtml(fmtMoney(calc.result)) + '</strong></div>'
      + '<div>Deficit<strong>' + escapeHtml(fmtMoney(calc.deficit)) + '</strong></div>'
      + '<div>Surplus<strong>' + escapeHtml(fmtMoney(calc.surplus)) + '</strong></div>'
      + '</div>'
      + '<p><strong>Recuperare investiții:</strong> '
      + escapeHtml(calc.surplus <= 0 ? 'Nu acoperi cheltuielile' : fmtQuartersRecover(calc.quartersToRecover))
      + '</p>'
      + '<p>' + escapeHtml(document.getElementById('bws-planner-summary')?.textContent || '') + '</p>'
      + '<div class="foot">Document informativ — nu înlocuiește planificarea contabilă.</div>'
      + '</body></html>';

    // Preferă Blob URL (nu e deprecat / blocat de CSP ca document.write).
    let win = null;
    try {
      const blob = new Blob([html], { type: 'text/html' });
      const url = URL.createObjectURL(blob);
      win = window.open(url, '_blank', 'noopener,noreferrer,width=900,height=700');
      if (win) {
        setTimeout(() => {
          try { win.focus(); win.print(); } catch (e) { /* pop-up print blocat */ }
        }, 500);
        setTimeout(() => URL.revokeObjectURL(url), 60000);
        return;
      }
    } catch (e) {
      /* fallback la document.write */
    }

    try {
      win = window.open('', '_blank', 'noopener,noreferrer,width=900,height=700');
      if (!win) throw new Error('popup blocat');
      win.document.open();
      win.document.write(html);
      win.document.close();
      win.focus();
      setTimeout(() => { try { win.print(); } catch (e) { /* ignore */ } }, 350);
    } catch (e) {
      showToast('Permite pop-up-urile pentru a exporta PDF-ul.', 'error');
    }
  }

  /* ---------------------------------------------------------------------- */
  /* Persistență localStorage + migrare                                     */
  /* ---------------------------------------------------------------------- */

  function migrateLegacyV2(parsed) {
    const state = defaultState();
    const map = {
      salary: 'exp_salary', rent: 'exp_rent', amortization: 'exp_amort', utilities: 'exp_util',
      insurance: 'exp_ins', accounting: 'exp_acc', it: 'exp_it', marketing_ops: 'exp_mkt',
      transport: 'exp_trans', expense_other1: null, expense_other2: null,
      income_shop: 'inc_shop', income_marketplace: 'inc_mkt', income_b2b: 'inc_b2b', income_other: null,
      invest_dev: 'inv_dev', invest_promo: 'inv_promo', invest_other: null,
    };
    Object.entries(map).forEach(([legacyKey, rowId]) => {
      if (parsed[legacyKey] === undefined) return;
      const val = Number(parsed[legacyKey]) || 0;
      if (rowId) {
        const section = rowId.startsWith('exp_') ? 'expenses'
          : rowId.startsWith('inc_') ? 'income' : 'investments';
        const row = state[section].find((r) => r.id === rowId);
        if (row) row.value = val;
      } else if (val > 0) {
        const section = legacyKey.startsWith('expense_') ? 'expenses'
          : legacyKey.startsWith('income_') ? 'income' : 'investments';
        state[section].push({ id: uid('row'), label: 'Linie importată', value: val });
      }
    });
    const usersRow = state.scenario.find((r) => r.calcKey === 'target_users');
    const convRow = state.scenario.find((r) => r.calcKey === 'conversion_rate');
    const priceRow = state.scenario.find((r) => r.calcKey === 'avg_price');
    if (parsed.target_users !== undefined && usersRow) usersRow.value = Number(parsed.target_users) || 1000;
    if (parsed.conversion_rate !== undefined && convRow) convRow.value = Number(parsed.conversion_rate) || 5;
    if (parsed.avg_price !== undefined && priceRow) priceRow.value = Number(parsed.avg_price) || 141.23;
    return state;
  }

  function readPlannerState() {
    try {
      let raw = localStorage.getItem(PLANNER_STORAGE_KEY);
      if (!raw) {
        raw = localStorage.getItem(LEGACY_STORAGE_KEY);
      }
      if (raw) {
        const parsed = JSON.parse(raw);
        const migrated = migrateState(parsed);
        savePlannerState(migrated);
        return migrated;
      }
    } catch (e) {
      showToast('Nu am putut citi datele salvate — folosesc valorile implicite.', 'error');
    }
    return defaultState();
  }

  function writeLocalState(state) {
    plannerState = state;
    try {
      localStorage.setItem(PLANNER_STORAGE_KEY, JSON.stringify(state));
    } catch (e) {
      /* ignoră (quota) */
    }
  }

  function savePlannerState(state) {
    plannerState = state;
    if (activeScenarioId) {
      saveActiveScenario();
    }
  }

  /* ---------------------------------------------------------------------- */
  /* Sincronizare server                                                    */
  /* ---------------------------------------------------------------------- */

  async function plannerApi(action, body) {
    const opts = {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
    };
    if (action === 'load' && !body) {
      opts.method = 'GET';
    } else {
      opts.method = 'POST';
      opts.body = JSON.stringify(Object.assign({ action: action }, body || {}));
    }
    const res = await fetch(PLANNER_API, opts);
    const json = await res.json();
    if (!res.ok || !json || json.success !== true) {
      throw new Error((json && json.message) || 'Eroare server planner.');
    }
    return json;
  }

  function queueServerSaveCurrent(state) {
    if (!serverAvailable || !serverSyncDone) return;
    const snapshot = deepCloneState(state);
    clearTimeout(serverSaveTimer);
    serverSaveTimer = setTimeout(() => {
      plannerApi('save_current', { state: snapshot }).catch(() => {
        /* offline — rămâne salvat local */
      });
    }, 800);
  }

  async function syncFromServer() {
    let data;
    try {
      const json = await plannerApi('load');
      data = json.data || {};
      serverAvailable = true;
    } catch (e) {
      serverAvailable = false;
      serverSyncDone = false;
      renderSavedPresets();
      updateStrategicImportBanner();
      return;
    }

    serverPresetsCache = Array.isArray(data.presets) ? data.presets : [];
    serverSyncDone = true;
    renderSavedPresets();
    updateStrategicImportBanner();
    openScenarioFromHash();
  }

  /** Acceptă 10000, 10 000, 10.000 (RO) și 141,23 */
  function parsePlannerNumber(raw) {
    if (raw === null || raw === undefined) return 0;
    if (typeof raw === 'number') return Number.isFinite(raw) ? raw : 0;

    let s = String(raw).trim().replace(/\s+/g, '').replace(/ron/gi, '');
    if (!s) return 0;

    const hasComma = s.includes(',');
    const hasDot = s.includes('.');

    if (hasComma && hasDot) {
      if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
        s = s.replace(/\./g, '').replace(',', '.');
      } else {
        s = s.replace(/,/g, '');
      }
    } else if (hasComma) {
      s = s.replace(',', '.');
    } else if (hasDot) {
      const parts = s.split('.');
      const looksGrouped = parts.length > 1 && parts.slice(1).every((part) => part.length === 3);
      if (looksGrouped) {
        s = parts.join('');
      }
    }

    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  /** True dacă textul este nenul dar nu reprezintă un număr valid. */
  function looksInvalidNumber(raw) {
    const s = String(raw == null ? '' : raw).trim();
    if (!s) return false;
    const cleaned = s.replace(/\s+/g, '').replace(/ron/gi, '');
    return !/^[0-9.,]+$/.test(cleaned);
  }

  function fmtNum(n) {
    return Number(n || 0).toLocaleString('ro-RO');
  }

  function fmtMoney(n) {
    return Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
  }

  function fmtQuartersRecover(q) {
    if (q === null || q <= 0) return 'Nu acoperi cheltuielile';
    if (q < 0.05) return 'Sub 1 lună';
    if (q < 1) {
      return q.toLocaleString('ro-RO', { maximumFractionDigits: 1 })
        + ' trimestre (~' + Math.max(1, Math.ceil(q * 3)) + ' luni)';
    }
    return q.toLocaleString('ro-RO', { maximumFractionDigits: 1 }) + ' trimestre';
  }

  function setText(id, value) {
    const node = document.getElementById(id);
    if (node) node.textContent = value;
  }

  function sumRowValues(rows) {
    return rows.reduce((acc, row) => acc + Math.max(0, parsePlannerNumber(row.value)), 0);
  }

  function getScenarioCalc(rows) {
    const out = { target_users: 0, conversion_rate: 0, avg_price: 0 };
    rows.forEach((row) => {
      if (row.calcKey === 'target_users') out.target_users = Math.max(0, Math.round(parsePlannerNumber(row.value)));
      if (row.calcKey === 'conversion_rate') out.conversion_rate = Math.min(100, Math.max(0, parsePlannerNumber(row.value)));
      if (row.calcKey === 'avg_price') out.avg_price = Math.max(0, parsePlannerNumber(row.value));
    });
    return out;
  }

  function calcPlanner(state) {
    const expenseMonthly = sumRowValues(state.expenses);
    const periodMonths = periodMonthsFromMeta(state.meta);
    const expenseQuarterly = expenseMonthly * 3;
    const expensePeriod = expenseMonthly * periodMonths;
    const incomeMonthly = sumRowValues(state.income);
    const incomeQuarterly = incomeMonthly * 3;
    const incomePeriod = incomeMonthly * periodMonths;
    const scenarioCalc = getScenarioCalc(state.scenario);
    const avgPrice = scenarioCalc.avg_price || Number(plannerPricing.avg) || 0;
    const orders = Math.round(scenarioCalc.target_users * (scenarioCalc.conversion_rate / 100));
    const scenarioMonthly = orders * avgPrice;
    const scenarioQuarterly = scenarioMonthly * 3;
    const scenarioPeriod = scenarioMonthly * periodMonths;
    const totalQuarterly = incomeQuarterly + scenarioQuarterly;
    const totalPeriod = incomePeriod + scenarioPeriod;
    const result = totalQuarterly - expenseQuarterly;
    const resultPeriod = totalPeriod - expensePeriod;
    const investmentsTotal = sumRowValues(state.investments);
    const surplus = result > 0 ? result : 0;
    const deficit = result < 0 ? Math.abs(result) : 0;

    return {
      expenseMonthly,
      expenseQuarterly,
      expensePeriod,
      incomeMonthly,
      incomeQuarterly,
      incomePeriod,
      periodMonths,
      avgPrice,
      orders,
      targetUsers: scenarioCalc.target_users,
      conversionRate: scenarioCalc.conversion_rate,
      scenarioMonthly,
      scenarioQuarterly,
      scenarioPeriod,
      totalQuarterly,
      totalPeriod,
      result,
      resultPeriod,
      stableCovers: incomeQuarterly >= expenseQuarterly,
      scenarioCovers: result >= 0,
      scenarioOnlyCovers: scenarioQuarterly >= expenseQuarterly,
      deficit,
      surplus,
      investmentsTotal,
      quartersToRecover: surplus > 0 ? investmentsTotal / surplus : null,
      remainingInvestments: Math.max(0, investmentsTotal - surplus),
    };
  }

  function setPlannerPill(id, value, positive) {
    const node = document.getElementById(id);
    if (!node) return;
    node.textContent = value;
    node.classList.remove('is-yes', 'is-no', 'is-neutral');
    node.classList.add(typeof positive === 'boolean' ? (positive ? 'is-yes' : 'is-no') : 'is-neutral');
  }

  function setMetricTone(elId, tone) {
    const node = document.getElementById(elId);
    if (!node) return;
    node.classList.remove('is-profit', 'is-loss', 'is-neutral');
    if (tone) node.classList.add(tone);
  }

  function updateVerdictBanner(calc, state) {
    const banner = document.getElementById('bws-planner-verdict-banner');
    const icon = document.getElementById('bws-planner-verdict-icon');
    const title = document.getElementById('bws-planner-verdict-title');
    const sub = document.getElementById('bws-planner-verdict-sub');
    if (!banner || !title || !sub) return;

    const isEmpty = state && !(state.expenses?.length || state.income?.length || state.scenario?.length || state.investments?.length);
    if (isEmpty) {
      banner.setAttribute('data-tone', 'neutral');
      if (icon) icon.textContent = '—';
      title.textContent = 'Scenariu gol';
      sub.textContent = 'Adaugă cheltuieli și venituri pentru a vedea rezultatul.';
      return;
    }

    let tone = 'neutral';
    let iconText = '◎';
    let titleText = 'Echilibru la zero';
    let subText = 'Venitul total acoperă exact cheltuielile.';

    if (calc.result > 0) {
      tone = 'profit';
      iconText = '▲';
      titleText = 'PROFIT ' + fmtMoney(calc.result) + ' / trimestru';
      subText = 'Venit total ' + fmtMoney(calc.totalQuarterly) + ' depășește cheltuielile de '
        + fmtMoney(calc.expenseQuarterly) + '.';
    } else if (calc.result < 0) {
      tone = 'loss';
      iconText = '▼';
      titleText = 'DEFICIT ' + fmtMoney(calc.deficit) + ' / trimestru';
      subText = 'Mai ai nevoie de ' + fmtMoney(calc.deficit) + ' ca să ajungi la zero '
        + '(cheltuieli ' + fmtMoney(calc.expenseQuarterly) + ' vs venit ' + fmtMoney(calc.totalQuarterly) + ').';
    }

    if (calc.targetUsers > PLAUSIBILITY_USER_LIMIT) {
      subText += ' ⚠ Scenariu foarte optimist (' + fmtNum(calc.targetUsers) + ' utilizatori) — verifică ipotezele.';
    }

    banner.setAttribute('data-tone', tone);
    if (icon) icon.textContent = iconText;
    title.textContent = titleText;
    sub.textContent = subText;
  }

  function updateCompareBar(calc) {
    const maxVal = Math.max(calc.expenseQuarterly, calc.totalQuarterly, 1);
    const expensePct = (calc.expenseQuarterly / maxVal) * 100;
    const incomePct = (calc.totalQuarterly / maxVal) * 100;

    setText('bws-planner-compare-expense', fmtMoney(calc.expenseQuarterly));
    setText('bws-planner-compare-income', fmtMoney(calc.totalQuarterly));

    const barExpense = document.getElementById('bws-planner-bar-expense');
    const barIncome = document.getElementById('bws-planner-bar-income');
    if (barExpense) barExpense.style.width = expensePct + '%';
    if (barIncome) barIncome.style.width = incomePct + '%';

    const delta = document.getElementById('bws-planner-compare-delta');
    if (delta) {
      delta.textContent = calc.result >= 0
        ? 'Surplus: ' + fmtMoney(calc.surplus)
        : 'Lipsesc: ' + fmtMoney(calc.deficit);
      delta.classList.toggle('is-profit', calc.result > 0);
      delta.classList.toggle('is-loss', calc.result < 0);
    }
  }

  function updateFormula(calc) {
    const node = document.getElementById('bws-planner-formula-text');
    if (!node) return;

    if (calc.avgPrice <= 0) {
      node.textContent = 'Completează utilizatori, conversie și preț mediu.';
      return;
    }

    node.textContent = fmtNum(calc.targetUsers) + ' utilizatori × '
      + calc.conversionRate.toLocaleString('ro-RO') + '% = '
      + fmtNum(calc.orders) + ' comenzi × '
      + fmtMoney(calc.avgPrice) + ' = '
      + fmtMoney(calc.scenarioMonthly) + ' / lună';
  }

  function collectStateFromDom() {
    const state = {
      version: 4,
      meta: collectMetaFromDom(),
      expenses: [],
      income: [],
      scenario: [],
      investments: [],
    };

    ['expenses', 'income', 'scenario', 'investments'].forEach((sectionKey) => {
      const container = document.getElementById('bws-planner-lines-' + sectionKey);
      if (!container) return;
      container.querySelectorAll('.bws-planner-line[data-row-id]').forEach((line) => {
        const labelInput = line.querySelector('.bws-planner__label-input');
        const valueInput = line.querySelector('.bws-planner-value-input');
        const rawValue = valueInput ? valueInput.value : '';
        const parsedValue = parsePlannerNumber(rawValue);

        if (valueInput) {
          valueInput.classList.toggle('is-invalid', looksInvalidNumber(rawValue));
        }

        const row = {
          id: line.getAttribute('data-row-id') || uid('row'),
          label: (labelInput && labelInput.value.trim()) || 'Linie nouă',
          value: Math.max(0, parsedValue),
        };
        const calcKey = line.getAttribute('data-calc-key');
        if (calcKey) row.calcKey = calcKey;
        if (valueInput) {
          const step = valueInput.getAttribute('data-step');
          const min = valueInput.getAttribute('data-min');
          const max = valueInput.getAttribute('data-max');
          if (step) row.step = step;
          if (min !== null && min !== '') row.min = Number(min);
          if (max !== null && max !== '') row.max = Number(max);
        }
        state[sectionKey].push(row);
      });
    });

    return state;
  }

  function buildRowElement(sectionKey, row, isOnlyRow) {
    const line = document.createElement('div');
    line.className = 'bws-planner-line';
    line.setAttribute('data-row-id', row.id);
    if (row.calcKey) line.setAttribute('data-calc-key', row.calcKey);

    const labelWrap = document.createElement('div');
    labelWrap.className = 'bws-planner-line__label-wrap';

    const labelInput = document.createElement('input');
    labelInput.type = 'text';
    labelInput.className = 'bws-planner__label-input';
    labelInput.value = row.label || 'Linie nouă';
    labelInput.placeholder = 'Denumire linie';
    labelInput.setAttribute('aria-label', 'Denumire linie');
    labelWrap.appendChild(labelInput);

    if (sectionKey === 'scenario' && row.calcKey && CALC_KEY_LABELS[row.calcKey]) {
      const badge = document.createElement('span');
      badge.className = 'bws-planner-calc-badge';
      badge.title = 'Această linie intră în formula de venit';
      badge.textContent = '⚡ ' + CALC_KEY_LABELS[row.calcKey];
      labelWrap.appendChild(badge);
    } else if (sectionKey === 'scenario') {
      const info = document.createElement('span');
      info.className = 'bws-planner-calc-badge bws-planner-calc-badge--info';
      info.title = 'Linie informativă — nu intră în calculul de venit';
      info.textContent = 'informativ';
      labelWrap.appendChild(info);
    }

    const valueWrap = document.createElement('div');
    valueWrap.className = 'bws-planner-line__controls';

    const valueInput = document.createElement('input');
    valueInput.type = 'text';
    valueInput.inputMode = 'decimal';
    valueInput.className = 'bws-planner__input bws-planner-value-input';
    valueInput.value = String(row.value ?? 0);
    valueInput.setAttribute('aria-label', 'Valoare' + (row.label ? ' — ' + row.label : ''));
    if (row.step) valueInput.setAttribute('data-step', String(row.step));
    if (row.min !== undefined && row.min !== null) valueInput.setAttribute('data-min', String(row.min));
    if (row.max !== undefined && row.max !== null) valueInput.setAttribute('data-max', String(row.max));

    const unit = document.createElement('span');
    unit.className = 'bws-planner-line__unit';
    unit.textContent = getRowUnit(sectionKey, row);

    const delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'bws-planner-row-del';
    delBtn.textContent = '×';
    delBtn.title = 'Șterge linia';
    delBtn.setAttribute('aria-label', 'Șterge linia ' + (row.label || ''));

    valueWrap.appendChild(valueInput);
    valueWrap.appendChild(unit);
    valueWrap.appendChild(delBtn);
    line.appendChild(labelWrap);
    line.appendChild(valueWrap);

    if (sectionKey === 'scenario' && row.calcKey === 'avg_price') {
      const syncBtn = document.createElement('button');
      syncBtn.type = 'button';
      syncBtn.className = 'bws-planner__sync-btn bws-planner__sync-btn--inline';
      syncBtn.textContent = 'Preț mediu din catalog';
      syncBtn.addEventListener('click', () => {
        if (plannerPricing.avg > 0) {
          valueInput.value = String(plannerPricing.avg);
          persistAndRecalc();
          showToast('Preț mediu sincronizat: ' + fmtMoney(plannerPricing.avg) + '.', 'success');
        } else {
          showToast('Prețul mediu din catalog nu este disponibil momentan.', 'error');
        }
      });
      line.appendChild(syncBtn);
      line.classList.add('bws-planner-line--with-extra');
    }

    return line;
  }

  function renderSection(sectionKey, rows) {
    const container = document.getElementById('bws-planner-lines-' + sectionKey);
    if (!container) return;
    container.innerHTML = '';
    container.classList.toggle('bws-planner-lines--empty', !rows.length);
    rows.forEach((row) => {
      container.appendChild(buildRowElement(sectionKey, row, false));
    });
  }

  function renderAllSections(state) {
    renderSection('expenses', state.expenses);
    renderSection('income', state.income);
    renderSection('scenario', state.scenario);
    renderSection('investments', state.investments);
  }

  function persistAndRecalc() {
    const state = collectStateFromDom();
    plannerState = state;
    if (activeScenarioId) {
      saveActiveScenario();
    }
    updateResults(state);
  }

  function scheduleRecalc() {
    clearTimeout(recalcTimer);
    recalcTimer = setTimeout(persistAndRecalc, 120);
  }

  function updateResults(state, opts) {
    opts = opts || {};
    state = state || plannerState || readPlannerState();
    const calc = calcPlanner(state);
    const pricedCount = Number(plannerPricing.priced_count || 0);

    setText('bws-plan-expense-m', fmtMoney(calc.expenseMonthly));
    setText('bws-plan-expense-q', fmtMoney(calc.expenseQuarterly));
    setText('bws-plan-income-m', fmtMoney(calc.incomeMonthly));
    setText('bws-plan-income-q', fmtMoney(calc.incomeQuarterly));
    setText('bws-planner-orders', fmtNum(calc.orders));
    setText('bws-plan-scenario-month', calc.avgPrice > 0 ? fmtMoney(calc.scenarioMonthly) : '—');
    setText('bws-plan-scenario-q', calc.avgPrice > 0 ? fmtMoney(calc.scenarioQuarterly) : '—');
    setText('bws-plan-total-q', fmtMoney(calc.totalQuarterly));
    setText('bws-plan-result', fmtMoney(calc.result));
    setText('bws-plan-deficit', fmtMoney(calc.deficit));
    setText('bws-plan-surplus', fmtMoney(calc.surplus));
    setText('bws-plan-investments-total', fmtMoney(calc.investmentsTotal));

    setPlannerPill('bws-plan-stable-covers', calc.stableCovers ? 'DA' : 'NU', calc.stableCovers);
    setPlannerPill('bws-plan-scenario-covers', calc.scenarioCovers ? 'DA' : 'NU', calc.scenarioCovers);
    setPlannerPill('bws-plan-scenario-only-covers', calc.scenarioOnlyCovers ? 'DA' : 'NU', calc.scenarioOnlyCovers);

    setMetricTone('bws-plan-result-metric', calc.result > 0 ? 'is-profit' : (calc.result < 0 ? 'is-loss' : 'is-neutral'));
    setMetricTone('bws-plan-deficit-metric', calc.deficit > 0 ? 'is-loss' : 'is-neutral');
    setMetricTone('bws-plan-surplus-metric', calc.surplus > 0 ? 'is-profit' : 'is-neutral');

    const resultHint = document.getElementById('bws-plan-result-hint');
    if (resultHint) {
      resultHint.textContent = calc.result >= 0
        ? 'Ești pe profit — surplus disponibil pentru investiții'
        : 'Ești pe pierdere — trebuie crescut venitul sau redus costurile';
    }

    updateVerdictBanner(calc, state);
    updateCompareBar(calc);
    updateFormula(calc);
    if (!opts.skipSliderSync) {
      syncSlidersFromState(state);
    } else {
      refreshSliderRanges(getScenarioCalc(state.scenario || []));
    }

    setText(
      'bws-plan-quarters-recover',
      calc.surplus <= 0 ? 'Nu acoperi cheltuielile' : fmtQuartersRecover(calc.quartersToRecover)
    );
    setText(
      'bws-plan-remaining-inv',
      calc.remainingInvestments <= 0 && calc.surplus > 0 && calc.investmentsTotal > 0
        ? 'Recuperat în primul trimestru'
        : fmtMoney(calc.remainingInvestments)
    );

    setText('bws-planner-avg-price', plannerPricing.avg > 0 ? fmtMoney(plannerPricing.avg) : '—');
    setText(
      'bws-planner-catalog-meta',
      pricedCount > 0
        ? pricedCount.toLocaleString('ro-RO') + ' produse · min ' + fmtMoney(plannerPricing.min) + ' · max ' + fmtMoney(plannerPricing.max)
        : 'Nu există produse active cu preț valid'
    );

    let summary = '';
    const hasLines = (state.expenses?.length || 0) + (state.income?.length || 0) + (state.scenario?.length || 0) > 0;
    if (!hasLines) {
      summary = 'Scenariu gol — adaugă cheltuieli, venituri sau ipoteze pentru a vedea rezultatul.';
    } else if (calc.scenarioCovers) {
      summary = 'Cu venit stabil + scenariul de ' + fmtNum(calc.targetUsers) + ' utilizatori ('
        + calc.conversionRate.toLocaleString('ro-RO') + '%, ' + fmtMoney(calc.avgPrice) + ' mediu), '
        + 'ești la zero sau profit trimestrial: ' + fmtMoney(calc.result) + '. ';
      if (calc.surplus > 0 && calc.investmentsTotal > 0) {
        summary += 'Investițiile de ' + fmtMoney(calc.investmentsTotal) + ' se recuperează în '
          + fmtQuartersRecover(calc.quartersToRecover).toLowerCase() + '.';
      }
    } else if (calc.stableCovers) {
      summary = 'Venitul stabil acoperă cheltuielile, dar combinat cu scenariul marketing rămâne deficit: '
        + fmtMoney(calc.deficit) + '.';
    } else if (calc.scenarioOnlyCovers) {
      summary = 'Fără venit stabil nu acoperi cheltuielile; doar cu scenariul previzibil ai '
        + fmtMoney(calc.scenarioQuarterly) + ' / trimestru vs cheltuieli ' + fmtMoney(calc.expenseQuarterly) + '.';
    } else {
      summary = 'Nici venitul stabil, nici scenariul de ' + fmtNum(calc.targetUsers)
        + ' utilizatori nu acoperă cheltuielile trimestriale de ' + fmtMoney(calc.expenseQuarterly)
        + '. Deficit: ' + fmtMoney(calc.deficit) + '.';
    }
    setText('bws-planner-summary', summary);
    if (activeScenarioId) {
      const preset = readSavedPresets().find((p) => p.id === activeScenarioId);
      updateStrategyTable(calc, preset);
      renderFeasibilityPanel(state, calc, preset);
    } else {
      updateStrategyTable(calc, null);
    }
  }

  function addRow(sectionKey) {
    const state = collectStateFromDom();
    const labels = {
      expenses: 'Cheltuială nouă',
      income: 'Venit nou',
      scenario: 'Parametru nou',
      investments: 'Investiție nouă',
    };
    const newRow = { id: uid('row'), label: labels[sectionKey] || 'Linie nouă', value: 0 };
    state[sectionKey].push(newRow);
    savePlannerState(state);
    renderSection(sectionKey, state[sectionKey]);
    updateResults(state);

    if (sectionKey === 'scenario') {
      showToast('Linia din scenariu este informativă — nu intră automat în formula de venit.', 'neutral');
    }
  }

  function addCalcRow(calcKey) {
    const state = collectStateFromDom();
    if (state.scenario.some((r) => r.calcKey === calcKey)) {
      showToast('Există deja o linie pentru acest parametru.', 'neutral');
      return;
    }
    const defs = {
      target_users: { label: 'Utilizatori țintă', value: 0, step: '1', min: 0 },
      conversion_rate: { label: 'Rată conversie (%)', value: 0, step: '0.1', min: 0, max: 100 },
      avg_price: { label: 'Preț mediu produs (RON)', value: 0, step: '0.01', min: 0 },
    };
    const def = defs[calcKey];
    if (!def) return;
    state.scenario.push(Object.assign({ id: uid('row'), calcKey: calcKey }, def));
    savePlannerState(state);
    renderSection('scenario', state.scenario);
    updateResults(state);
  }

  function deleteRow(lineEl) {
    const sectionKey = lineEl.closest('[data-planner-stage]')?.getAttribute('data-planner-stage');
    const rowId = lineEl.getAttribute('data-row-id');
    if (!sectionKey || !rowId) return;

    const state = collectStateFromDom();
    const rows = state[sectionKey] || [];
    state[sectionKey] = rows.filter((r) => r.id !== rowId);
    savePlannerState(state);
    renderSection(sectionKey, state[sectionKey]);
    updateResults(state);
  }

  function bindMarketingPlanner() {
    if (plannerBound) return;
    const panel = document.getElementById('bws-marketing-planner');
    if (!panel) return;
    plannerBound = true;

    showListView();

    ['bws-planner-meta-name', 'bws-planner-meta-start', 'bws-planner-meta-end', 'bws-planner-meta-assumptions']
      .forEach((id) => {
        document.getElementById(id)?.addEventListener('change', persistMetaFromDom);
        document.getElementById(id)?.addEventListener('input', persistMetaFromDom);
      });

    document.getElementById('bws-planner-new-scenario')?.addEventListener('click', () => openScenarioModal());
    document.getElementById('bws-planner-import-strategic')?.addEventListener('click', importStrategicPlans);
    document.getElementById('bws-planner-back-list')?.addEventListener('click', showListView);
    document.getElementById('bws-planner-delete-scenario')?.addEventListener('click', () => {
      if (!activeScenarioId) return;
      if (window.confirm('Ștergi acest scenariu? Acțiunea nu poate fi anulată.')) {
        deleteSavedPreset(activeScenarioId);
      }
    });
    document.getElementById('bws-planner-modal-save')?.addEventListener('click', saveScenarioFromModal);
    document.getElementById('bws-planner-compare-selected')?.addEventListener('click', () => {
      renderScenarioCompare();
      document.getElementById('bws-planner-stage-compare')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    document.getElementById('bws-planner-select-all')?.addEventListener('change', (event) => {
      const checked = event.target.checked;
      document.querySelectorAll('[data-scenario-select]').forEach((el) => { el.checked = checked; });
    });
    document.querySelectorAll('[data-modal-close]').forEach((el) => {
      el.addEventListener('click', closeScenarioModal);
    });

    function handlePlannerFieldChange(event) {
      const t = event.target;
      if (!t || !activeScenarioId) return;
      if (t.classList.contains('bws-planner-slider__input')) {
        handleSliderInput(t);
        return;
      }
      if (t.classList.contains('bws-planner-value-input')) {
        scheduleRecalc();
        return;
      }
      if (t.classList.contains('bws-planner__label-input')) {
        savePlannerState(collectStateFromDom());
      }
    }

    panel.addEventListener('input', handlePlannerFieldChange);

    document.getElementById('bws-planner-export-pdf')?.addEventListener('click', exportPlannerPdf);

    document.getElementById('bws-planner-error-reset')?.addEventListener('click', async () => {
      try {
        localStorage.removeItem(PLANNER_STORAGE_KEY);
        localStorage.removeItem(LEGACY_STORAGE_KEY);
        localStorage.removeItem(SAVED_STORAGE_KEY);
      } catch (e) { /* ignore */ }
      window.location.reload();
    });

    panel.addEventListener('click', (event) => {
      const scenarioRow = event.target.closest('tr[data-preset-id]');
      if (scenarioRow && !event.target.closest('button, input, a, label')) {
        openScenario(scenarioRow.getAttribute('data-preset-id'));
        return;
      }

      const savedOpen = event.target.closest('[data-saved-open]');
      if (savedOpen) {
        event.preventDefault();
        openScenario(savedOpen.getAttribute('data-saved-open'));
        return;
      }
      const savedDup = event.target.closest('[data-saved-dup]');
      if (savedDup) {
        event.preventDefault();
        duplicateSavedPreset(savedDup.getAttribute('data-saved-dup'));
        return;
      }
      const savedDel = event.target.closest('[data-saved-del]');
      if (savedDel) {
        event.preventDefault();
        if (window.confirm('Ștergi acest scenariu?')) {
          deleteSavedPreset(savedDel.getAttribute('data-saved-del'));
        }
        return;
      }
      const addCalcBtn = event.target.closest('[data-planner-add-calc]');
      if (addCalcBtn) {
        event.preventDefault();
        addCalcRow(addCalcBtn.getAttribute('data-planner-add-calc'));
        return;
      }
      const addBtn = event.target.closest('[data-planner-add]');
      if (addBtn) {
        event.preventDefault();
        addRow(addBtn.getAttribute('data-planner-add'));
        return;
      }
      const delBtn = event.target.closest('.bws-planner-row-del');
      if (delBtn) {
        event.preventDefault();
        const line = delBtn.closest('.bws-planner-line');
        if (line) deleteRow(line);
      }
    });
  }

  function applyCatalogPricing(pricing) {
    plannerPricing = {
      avg: Number(pricing.avg || 0),
      min: Number(pricing.min || 0),
      max: Number(pricing.max || 0),
      priced_count: Number(pricing.priced_count || 0),
    };

    const meta = document.getElementById('bpa-planner-catalog-meta');
    if (meta) {
      meta.textContent = plannerPricing.avg > 0
        ? 'Catalog sincronizat · medie ' + fmtMoney(plannerPricing.avg)
        : 'Nu există produse active cu preț valid în catalog.';
    }
    if (activeScenarioId && plannerState) {
      updateResults(plannerState);
    }
  }

  async function loadCatalogPricing() {
    try {
      const response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ refresh: 0 }),
      });
      const result = await response.json();
      if (response.ok && result.success && result.data?.products?.pricing) {
        applyCatalogPricing(result.data.products.pricing);
        return;
      }
      throw new Error('răspuns invalid');
    } catch (e) {
      const meta = document.getElementById('bpa-planner-catalog-meta');
      if (meta) {
        meta.textContent = 'Prețul mediu din catalog nu a putut fi încărcat — folosesc valoarea introdusă manual.';
      }
      updateResults(plannerState || readPlannerState());
    }
  }

  function boot() {
    if (!document.getElementById('bws-marketing-planner')) return;
    try {
      bindMarketingPlanner();
      hidePlannerError();
    } catch (e) {
      if (window.console && console.error) console.error('[planner] boot error:', e);
      showPlannerError('Detaliu tehnic: ' + (e && e.message ? e.message : 'eroare necunoscută'));
    }
    syncFromServer().catch((e) => {
      if (window.console && console.error) console.error('[planner] server sync error:', e);
    });
    loadCatalogPricing().catch((e) => {
      if (window.console && console.error) console.error('[planner] pricing error:', e);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
