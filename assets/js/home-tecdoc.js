/* Homepage TecDoc + produse + debug */
(function () {
'use strict';

    const BESOIU_ADMIN = window.BESOIU_ADMIN_CTX === true;

    function showBlock(el) {
        if (el) el.classList.remove('is-hidden');
    }

    function hideBlock(el) {
        if (el) el.classList.add('is-hidden');
    }
    /* ════════════════════════════════════════════════════════════
       1. UTILITAR DEBUG
       ════════════════════════════════════════════════════════════ */
    window.DebugPanel = (function() {
        const panel  = document.getElementById('_debug-panel');
        const toggle = document.getElementById('_debug-toggle');
        const log    = document.getElementById('_debug-log');
        const close  = document.getElementById('_debug-close');
        const clear  = document.getElementById('_debug-clear');

        if (!panel || !toggle || !log) {
            return { req: function () {}, ok: function () {}, err: function () {}, sel: function () {} };
        }

        toggle.addEventListener('click', () => {
            panel.style.display = 'block';
            toggle.style.display = 'none';
        });
        close.addEventListener('click', () => {
            panel.style.display = 'none';
            toggle.style.display = 'block';
        });
        clear.addEventListener('click', () => { log.innerHTML = ''; });

        function add(type, title, payload) {
            const entry = document.createElement('div');
            entry.className = 'entry';
            const time = new Date().toLocaleTimeString();
            let body = '';
            if (payload !== undefined) {
                try {
                    body = '\n' + JSON.stringify(payload, null, 2);
                } catch(e) {
                    body = '\n' + String(payload);
                }
            }
            entry.innerHTML = `<span class="tag ${type}">${type.toUpperCase()}</span><strong>${title}</strong> <span class="debug-entry-time">${time}</span>${body ? '<div>' + body.replace(/</g,'&lt;') + '</div>' : ''}`;
            log.appendChild(entry);
            log.scrollTop = log.scrollHeight;
            if (panel.style.display !== 'block') {
                panel.style.display = 'block';
                toggle.style.display = 'none';
            }
        }

        return {
            req: (title, payload) => add('req', title, payload),
            ok:  (title, payload) => add('ok',  title, payload),
            err: (title, payload) => add('err', title, payload),
            sel: (title, payload) => add('sel', title, payload)
        };
    })();

    /* ════════════════════════════════════════════════════════════
       2. INTEGRARE TECDOC — flux magazin:
          Categorie → Marcă → Model → Motorizare → subcategorii TecDoc
          → scanare TecDoc → afișare DOAR produse existente în BD (preț din BD)
       ════════════════════════════════════════════════════════════ */
    const apiPath = '/tecdoc_proxy.php';

    function isHomeVitrinaPage() {
        const grid = document.getElementById('_product-grid');
        if (!grid) {
            return false;
        }
        if (grid.dataset.homeVitrina === '1') {
            return true;
        }
        const page = grid.dataset.locPage ? String(grid.dataset.locPage) : '';
        return page.indexOf('/') !== -1;
    }

    function buildHomeCatalogUrl(overrides) {
        const params = new URLSearchParams();
        const vinInput = document.getElementById('_filter-vin');
        const oemInput = document.getElementById('_filter-oem');
        const vin = (vinInput && vinInput.value) ? vinInput.value.trim() : '';
        const oem = (oemInput && oemInput.value) ? oemInput.value.trim() : '';
        if (vin) {
            params.set('q', vin);
        } else if (oem) {
            params.set('q', oem);
        }
        if (_selectedCategoryLabel || _selectedCategory) {
            params.set('category', _selectedCategoryLabel || _selectedCategory);
        }
        const ctx = typeof getSelectedSubcategoryContext === 'function' ? getSelectedSubcategoryContext() : {};
        if (ctx.bdSubcategory) {
            params.set('subcategory', ctx.bdSubcategory);
        }
        const carId = _ultimulCarId || (selMotor && selMotor.value) || '';
        if (carId && carId !== '0') {
            params.set('car_id', String(carId));
        }
        if (selMarca && selMarca.value !== '0') {
            const marcaOpt = selMarca.options[selMarca.selectedIndex];
            if (marcaOpt) {
                params.set('marca', marcaOpt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim());
            }
        }
        if (overrides && typeof overrides === 'object') {
            Object.keys(overrides).forEach(function (key) {
                const val = overrides[key];
                if (val !== undefined && val !== null && String(val).trim() !== '') {
                    params.set(key, String(val));
                }
            });
        }
        params.set('from', 'home');
        const qs = params.toString();
        return '/catalog' + (qs ? '?' + qs : '');
    }

    function redirectHomeFiltersToCatalog(overrides) {
        if (!isHomeVitrinaPage()) {
            return false;
        }
        window.location.href = buildHomeCatalogUrl(overrides);
        return true;
    }

    const selMarca   = document.getElementById('select_marca');
    const selModel   = document.getElementById('model_marca');
    const selMotor   = document.getElementById('motorizari');
    const btnSearch  = document.getElementById('btnSearch');
    const selCategorie = document.getElementById('select_categorie');
    const subcatSlot   = document.getElementById('subcat-slot');
    const vehicleBox   = document.getElementById('vehicle-box');
    const loaderPiese  = document.getElementById('_loader-piese');
    const subcategoryPanel = document.getElementById('_subcategory-panel');
    const subcategoryList  = document.getElementById('_subcategory-list');

    const catBadge     = document.getElementById('cat-badge');
    const catBadgeText = document.getElementById('cat-badge-text');
    const catBadgeIcon = document.getElementById('cat-badge-icon');
    const catBadgeX    = document.getElementById('cat-badge-x');

    const filterbarSelectors = {
        select_marca: selMarca,
        model_marca: selModel,
        motorizari: selMotor,
        btnSearch: btnSearch,
        select_categorie: selCategorie,
        subcat_slot: subcatSlot,
        vehicle_box: vehicleBox,
        cat_toggle: document.getElementById('cat-toggle'),
        cat_popup: document.getElementById('cat-popup'),
        cat_overlay: document.getElementById('cat-popup-overlay'),
        category_grid: document.getElementById('category-grid-dynamic'),
        product_grid: document.getElementById('_product-grid'),
        loader_piese: loaderPiese,
        filter_oem: document.getElementById('_filter-oem'),
        filter_vin: document.getElementById('_filter-vin'),
    };
    const missingFilterbarSelectors = Object.keys(filterbarSelectors).filter(
        (key) => !filterbarSelectors[key]
    );
    if (missingFilterbarSelectors.length > 0) {
        console.warn('[home-tecdoc] Selectori filterbar lipsă:', missingFilterbarSelectors.join(', '));
        window.DebugPanel.err('Selectori filterbar', { missing: missingFilterbarSelectors });
    }

    /* ── Încarcă din admin elementele active (mărci, modele, subcategorii) ── */
    let _adminModele = [];
    let _adminSubcategorii = [];
    let _adminSubcategoriiForCategory = [];

    function deferNonCritical(fn) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: 2500 });
        } else {
            window.setTimeout(fn, 200);
        }
    }

    async function loadAdminSubcategoriesForCategory(categoryLabel) {
        _adminSubcategoriiForCategory = [];
        const label = String(categoryLabel || '').trim();
        if (!label) return;
        try {
            const res = await fetch('/api_categorii.php?action=subcategorii&category=' + encodeURIComponent(label));
            const json = await res.json();
            if (json.success && Array.isArray(json.subcategorii)) {
                _adminSubcategoriiForCategory = json.subcategorii.map(s => normalizeForMatch(s.label));
            }
        } catch (e) { /* fallback */ }
    }

    deferNonCritical(function () {
        (async function loadAdminData() {
            try {
                const [marciRes, modeleRes, subcatRes] = await Promise.all([
                    fetch('/api_categorii.php?action=marci'),
                    fetch('/api_categorii.php?action=modele'),
                    fetch('/api_categorii.php?action=subcategorii')
                ]);
                const marciJson = await marciRes.json();
                const modeleJson = await modeleRes.json();
                const subcatJson = await subcatRes.json();

                if (marciJson.success && marciJson.marci && selMarca) {
                    selMarca.innerHTML = '<option value="0">Alege marca</option>';
                    marciJson.marci.forEach(m => {
                        if (!m.tecdoc_id) return;
                        const opt = document.createElement('option');
                        opt.value = m.tecdoc_id;
                        opt.textContent = m.label + (m.count ? ` (${m.count})` : '');
                        selMarca.appendChild(opt);
                    });
                }
                if (modeleJson.success && modeleJson.modele) {
                    _adminModele = modeleJson.modele.map(m => normalizeForMatch(m.label));
                }
                if (subcatJson.success && subcatJson.subcategorii) {
                    _adminSubcategorii = subcatJson.subcategorii.map(s => normalizeForMatch(s.label));
                }
            } catch (e) { /* fallback */ }
        })();
    });

    function isModelAllowed(modelName) {
        if (_adminModele.length === 0) return true;
        const norm = normalizeForMatch(modelName);
        return _adminModele.some(am => norm.includes(am) || am.includes(norm));
    }

    function isSubcategoryAllowed(subcatLabel) {
        const list = _adminSubcategoriiForCategory.length > 0 ? _adminSubcategoriiForCategory : _adminSubcategorii;
        if (list.length === 0) return true;
        const norm = normalizeForMatch(subcatLabel);
        return list.some(as => norm.includes(as) || as.includes(norm));
    }

    let _selectedCategory = null;
    let _selectedCategoryLabel = null;
    let _ultimulCarId = null;
    let _categoriiTecdoc = {};
    let _tecdocNodeIndex = {};

    function besoiuStorefrontQuotaNotice() {
        if (typeof window.besoiuStorefrontQuotaNotice === 'function') {
            return window.besoiuStorefrontQuotaNotice();
        }
        return BESOIU_ADMIN
            ? 'Catalogul TecDoc (RapidAPI) este limitat. Căutarea continuă în stocul local.'
            : 'Căutarea continuă în stocul local al magazinului.';
    }

    function tecdocQuotaNotice(data) {
        if (!data || typeof data !== 'object') return '';
        if (data.error === 'quota_exceeded' || data.error === 'rate_limit_exceeded') {
            return data.message || besoiuStorefrontQuotaNotice();
        }
        if (data.notice) return publicStorefrontNotice(String(data.notice));
        return '';
    }

    function isTechnicalStorefrontNotice(text) {
        const lower = String(text || '').toLowerCase();
        if (!lower) return false;
        const needles = [
            'admin', 'migrare', 'migrat', 'migration', 'rapidapi', 'tecdoc',
            'eroare intern', 'sistem intern', 'sistem extern', 'intern/extern',
            'internă / externă', 'operator_message', 'ruleaza migrarea',
            'rulează migrarea', 'trimisa in admin', 'trimisă în admin',
            'fatal error', 'sqlstate', '.env', 'psubcategory', 'pvitrina',
        ];
        return needles.some((needle) => lower.includes(needle));
    }

    function publicStorefrontNotice(text) {
        const raw = String(text || '').trim();
        if (!raw) return '';
        if (BESOIU_ADMIN) return raw;
        return isTechnicalStorefrontNotice(raw) ? '' : raw;
    }

    async function syncTecdocApiStatus() {
        if (isHomeVitrinaPage() || window.__besoiuTecdocStatusFetched) {
            return;
        }
        window.__besoiuTecdocStatusFetched = true;
        try {
            const res = await fetch(`${apiPath}?action=status`);
            const data = await res.json();
            if (!data || data.success !== true) return;
            window.DebugPanel.ok('TecDoc status', {
                api_unavailable: data.api_unavailable,
                rate_limit_only: data.rate_limit_only,
                cache: data.cache,
            });
            if (!data.api_unavailable && !data.rate_limit_only) return;
            const notice = tecdocQuotaNotice({
                error: data.api_unavailable ? 'quota_exceeded' : 'rate_limit_exceeded',
                message: data.notice || data.last_error?.message || besoiuStorefrontQuotaNotice(),
            });
            if (!BESOIU_ADMIN) {
                return;
            }
            const debugStatus = document.getElementById('_product-debug-status');
            if (debugStatus && notice) {
                showBlock(debugStatus);
                debugStatus.textContent = notice;
            }
        } catch (e) {
            window.DebugPanel.err('TecDoc status', e.message || String(e));
        }
    }

    deferNonCritical(syncTecdocApiStatus);

    const searchInsightsBar = document.getElementById('search-insights-bar');

    function escapeInsightText(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function renderSearchInsightChip(label, count, attrs) {
        const countHtml = count > 1
            ? `<span class="search-insight-chip__count">${count}</span>`
            : '';
        const attrPairs = Object.keys(attrs || {}).map(function (key) {
            return `${key}="${escapeInsightText(attrs[key])}"`;
        }).join(' ');
        return `<button type="button" class="search-insight-chip" ${attrPairs}>${escapeInsightText(label)}${countHtml}</button>`;
    }

    function applySearchInsightCategory(label) {
        if (!label) return;
        _selectedCategory = null;
        _selectedCategoryLabel = label;
        if (catBadgeText) catBadgeText.textContent = label;
        showBlock(catBadge);
        loadAdminSubcategoriesForCategory(label).then(function () {
            if (redirectHomeFiltersToCatalog({ category: label })) {
                return;
            }
            if (typeof window.__applyProductFilters === 'function') {
                window.__applyProductFilters();
            }
        });
    }

    function applySearchInsightVehicle(item) {
        if (!item) return;
        const carId = item.car_id ? String(item.car_id) : '';
        if (carId && redirectHomeFiltersToCatalog({ car_id: carId })) {
            return;
        }
        if (typeof window.__applyProductFilters === 'function') {
            if (carId && selMotor) {
                selMotor.value = carId;
                _ultimulCarId = carId;
            }
            window.__applyProductFilters();
        }
    }

    function applySearchInsightQuery(item) {
        if (!item) return;
        const value = String(item.query_value || '').trim();
        if (!value) return;
        const type = String(item.query_type || 'name');
        const heroVin = document.getElementById('_filter-vin');
        const oemField = document.getElementById('_filter-oem');
        if (type === 'oem' && oemField) {
            oemField.value = value;
        } else if (heroVin) {
            heroVin.value = value;
        }
        if (redirectHomeFiltersToCatalog({ q: value })) {
            return;
        }
        if (typeof window.__applyProductFilters === 'function') {
            window.__applyProductFilters();
        }
    }

    function bindSearchInsightBar(bar) {
        if (!bar || bar.dataset.bound === '1') return;
        bar.dataset.bound = '1';
        bar.addEventListener('click', function (event) {
            const chip = event.target.closest('.search-insight-chip');
            if (!chip) return;
            const kind = chip.dataset.kind || '';
            if (kind === 'category') {
                applySearchInsightCategory(chip.dataset.label || '');
                return;
            }
            if (kind === 'vehicle') {
                applySearchInsightVehicle({
                    car_id: chip.dataset.carId || '',
                    label: chip.dataset.label || '',
                });
                return;
            }
            if (kind === 'query') {
                applySearchInsightQuery({
                    query_type: chip.dataset.queryType || 'name',
                    query_value: chip.dataset.queryValue || chip.dataset.label || '',
                });
            }
        });
    }

    async function loadSearchInsights() {
        if (!searchInsightsBar) return;
        bindSearchInsightBar(searchInsightsBar);
        try {
            const res = await fetch('/api_categorii.php?action=insights&limit=8');
            const json = await res.json();
            if (!json.success || !json.insights || json.insights.available !== true) {
                return;
            }
            const insights = json.insights;
            const chips = [];
            (insights.categories || []).slice(0, 3).forEach(function (row) {
                if (!row || !row.label) return;
                chips.push(renderSearchInsightChip(row.label, Number(row.search_count || 0), {
                    'data-kind': 'category',
                    'data-label': row.label,
                    title: 'Categorie populară',
                }));
            });
            (insights.vehicles || []).slice(0, 3).forEach(function (row) {
                if (!row || !row.label) return;
                const shortLabel = row.label.length > 42 ? row.label.slice(0, 39) + '…' : row.label;
                chips.push(renderSearchInsightChip(shortLabel, Number(row.search_count || 0), {
                    'data-kind': 'vehicle',
                    'data-car-id': row.car_id ? String(row.car_id) : '',
                    'data-label': row.label,
                    title: row.label,
                }));
            });
            (insights.queries || []).slice(0, 2).forEach(function (row) {
                if (!row || !row.query_value) return;
                chips.push(renderSearchInsightChip(row.query_value, Number(row.search_count || 0), {
                    'data-kind': 'query',
                    'data-query-type': row.query_type || 'name',
                    'data-query-value': row.query_value,
                    title: 'Căutare frecventă',
                }));
            });
            if (chips.length === 0) return;
            searchInsightsBar.innerHTML = '<span class="search-insights-label">Popular:</span>' + chips.join('');
            searchInsightsBar.classList.remove('is-hidden');
            window.DebugPanel.ok('Search insights', { chips: chips.length });
        } catch (e) {
            window.DebugPanel.err('Search insights', e.message || String(e));
        }
    }

    deferNonCritical(loadSearchInsights);

    async function apiCall(label, url) {
        window.DebugPanel.req(`Cerere: ${label}`, { url });
        try {
            const res  = await fetch(url);
            const data = await res.json();
            const quotaMsg = tecdocQuotaNotice(data);
            if (quotaMsg) {
                window.DebugPanel.err(`Limită API: ${label}`, { message: quotaMsg });
            } else {
                window.DebugPanel.ok(`Răspuns: ${label}`, data);
            }
            return data;
        } catch (err) {
            window.DebugPanel.err(`Eroare: ${label}`, err.message || String(err));
            throw err;
        }
    }

    let _motorChangeTimer = null;
    function scheduleMotorChange(handler) {
        if (_motorChangeTimer) window.clearTimeout(_motorChangeTimer);
        _motorChangeTimer = window.setTimeout(handler, 280);
    }

    let _autoFilterTimer = null;
    function scheduleAutoApplyVehicleFilter() {
        if (_autoFilterTimer) window.clearTimeout(_autoFilterTimer);
        _autoFilterTimer = window.setTimeout(function () {
            if (typeof window.__autoApplyVehicleFilter === 'function') {
                window.__autoApplyVehicleFilter();
            }
        }, 320);
    }

    function scrollToProductTop() {
        const section = document.querySelector('._product-section');
        if (!section) return;
        const top = section.getBoundingClientRect().top + window.pageYOffset - 90;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
        }[char]));
    }

    const CAT_SYNONYMS = {
        'motor':      ['motor', 'engine', 'motoare', 'bloc motor', 'chiulasa', 'arbore', 'piston', 'turbo', 'supapa', 'distributie', 'racire motor', 'cooling'],
        'frane':      ['fran', 'brake', 'disc frana', 'placute', 'etrier', 'franare', 'tambur', 'saboti'],
        'filtre':     ['filtr', 'filter', 'filtru ulei', 'filtru aer', 'filtru combustibil', 'filtru habitaclu', 'filtru polen'],
        'ulei':       ['ulei', 'oil', 'lichid', 'antigel', 'lubrifiant', 'fluid', 'lichid frana', 'lichid racire', 'ungere'],
        'ulei-lichide': ['ulei', 'oil', 'lichid', 'antigel', 'lubrifiant', 'fluid', 'ungere', 'filtre'],
        'suspensie':  ['suspensie', 'suspension', 'amortizor', 'arc', 'brat', 'bieleta', 'pivot', 'rulment roata', 'bara stabilizatoare', 'articulatie'],
        'electric':   ['electric', 'alternator', 'demaror', 'starter', 'baterie', 'senzor', 'bujie', 'bobina', 'cablaj', 'releu', 'siguranta'],
        'caroserie':  ['caroserie', 'body', 'bara', 'aripa', 'capota', 'usa', 'oglinda', 'far', 'stop', 'grila', 'parbriz', 'geam', 'stergator'],
        'transmisie': ['transmisie', 'transmission', 'cutie viteze', 'ambreiaj', 'volanta', 'cardan', 'planetara', 'diferential', 'sincron']
    };

    function normalizeForMatch(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
    }

    function fuzzyMatchCategory(localCat, apiLabel) {
        const synonyms = CAT_SYNONYMS[localCat] || [localCat];
        const norm = normalizeForMatch(apiLabel);
        return synonyms.some(syn => {
            const normSyn = normalizeForMatch(syn);
            return norm.includes(normSyn) || normSyn.includes(norm);
        });
    }

    function cleanCategoryLabel(label) {
        return String(label || '').replace(/\s*\([^)]*\)\s*$/, '').trim();
    }

    function categoryLabel(category, fallback = 'Categorie') {
        return cleanCategoryLabel(category?.text || category?.assemblyGroupName || category?.name || fallback);
    }

    function categoryChildren(category) {
        const children = category?.children;
        if (!children || typeof children !== 'object') return [];
        return Object.entries(children).map(([id, child]) => ({
            id, label: categoryLabel(child, `Subcategorie #${id}`), raw: child,
        })).filter(item => item.label !== '');
    }

    function buildTecdocNodeIndex(categories) {
        const index = {};
        function walk(node, id) {
            if (!node || index[id]) return;
            index[id] = node;
            const kids = node.children;
            if (kids && typeof kids === 'object') {
                Object.entries(kids).forEach(([childId, child]) => walk(child, childId));
            }
        }
        Object.entries(categories || {}).forEach(([id, cat]) => walk(cat, id));
        return index;
    }

    function tecdocCategoryMatches(selectedSlug, selectedLabel, apiLabel) {
        if (!selectedSlug && !selectedLabel) return false;
        const normApi = normalizeForMatch(apiLabel);
        const normLabel = normalizeForMatch(selectedLabel);
        const normSlug = normalizeForMatch(selectedSlug);
        if (normLabel && (normApi === normLabel || normApi.includes(normLabel) || normLabel.includes(normApi))) {
            return true;
        }
        if (normSlug && (normApi.includes(normSlug) || normSlug.includes(normApi))) {
            return true;
        }
        return fuzzyMatchCategory(selectedSlug, apiLabel) || fuzzyMatchCategory(selectedLabel, apiLabel);
    }

    async function fetchBdSubcategoriesForCategory(categoryLabel) {
        const label = String(categoryLabel || '').trim();
        if (!label) return [];
        try {
            const res = await fetch('/api_categorii.php?action=subcategorii&category=' + encodeURIComponent(label));
            const json = await res.json();
            if (!json.success || !Array.isArray(json.subcategorii)) return [];
            return json.subcategorii.map(s => ({
                id: 'bd:' + (s.slug || s.label),
                label: s.label,
                bdLabel: s.label,
                count: Number(s.count || 0),
            }));
        } catch (e) {
            return [];
        }
    }

    function findTecdocNodeForLabel(label) {
        const norm = normalizeForMatch(label);
        if (!norm || !_tecdocNodeIndex || Object.keys(_tecdocNodeIndex).length === 0) return '';
        let bestId = '';
        let bestScore = 0;
        Object.entries(_tecdocNodeIndex).forEach(([id, node]) => {
            const nodeLabel = normalizeForMatch(categoryLabel(node));
            if (!nodeLabel) return;
            let score = 0;
            if (nodeLabel === norm) score = 100;
            else if (nodeLabel.includes(norm) || norm.includes(nodeLabel)) score = 60;
            else if (norm.length >= 4 && nodeLabel.includes(norm.slice(0, Math.min(norm.length, 8)))) score = 30;
            if (score > bestScore) {
                bestScore = score;
                bestId = id;
            }
        });
        return bestScore >= 60 ? bestId : '';
    }

    function getSelectedSubcategoryContext() {
        const opt = selCategorie?.options[selCategorie.selectedIndex];
        if (!opt || opt.value === '0') {
            return { bdSubcategory: '', tecdocNodeId: '' };
        }
        return {
            bdSubcategory: opt.dataset.bdSubcategory || cleanCategoryLabel(opt.textContent.replace(/\s*\(\d+\)\s*$/, '')),
            tecdocNodeId: opt.dataset.tecdocNodeId || (/^\d+$/.test(String(opt.value)) ? String(opt.value) : ''),
        };
    }

    function populateSubcategoryDropdown(items) {
        if (!selCategorie || !subcatSlot || !vehicleBox) return;
        selCategorie.innerHTML = '<option value="0">Alege subcategoria</option>';
        items.forEach(item => {
            const opt = document.createElement('option');
            const tecdocNodeId = findTecdocNodeForLabel(item.label || item.bdLabel || '');
            opt.value = tecdocNodeId || item.id || '0';
            opt.textContent = item.label + (item.count ? ` (${item.count})` : '');
            opt.dataset.bdSubcategory = item.bdLabel || item.label;
            if (tecdocNodeId) opt.dataset.tecdocNodeId = tecdocNodeId;
            selCategorie.appendChild(opt);
        });
        subcatSlot.style.display = '';
        vehicleBox.classList.add('has-subcat');
    }

    function updateSearchButtonState() {
        if (isHomeVitrinaPage()) {
            if (btnSearch) btnSearch.disabled = false;
            return;
        }
        const hasMotor = selMotor && selMotor.value !== '0';
        const ctx = getSelectedSubcategoryContext();
        const hasSubcat = !!(ctx && ctx.bdSubcategory);
        const hasCategory = !!(_selectedCategoryLabel || _selectedCategory);
        const vinInput = document.getElementById('_filter-vin');
        const vinQuery = (vinInput && vinInput.value) ? vinInput.value.trim() : '';
        const canSearch = (hasMotor && hasSubcat)
            || (hasMotor && hasCategory)
            || hasCategory
            || vinQuery.length >= 2;
        if (btnSearch) btnSearch.disabled = !canSearch;
    }

    /* ── POPUP CATEGORII: alegere + badge + localStorage ── */
    const CAT_STORAGE_KEY = 'besoiu_selected_cat';

    function setCatBadgeIcon(icon) {
        if (!catBadgeIcon) {
            return;
        }
        const url = String(icon || '').trim();
        if (url) {
            catBadgeIcon.src = url;
            catBadgeIcon.hidden = false;
        } else {
            catBadgeIcon.removeAttribute('src');
            catBadgeIcon.hidden = true;
        }
    }

    async function selectCategory(cat, label, icon) {
        const catLabel = label || cat;
        _selectedCategory = cat;
        _selectedCategoryLabel = catLabel;
        if (catBadgeText) catBadgeText.textContent = label;
        setCatBadgeIcon(icon);
        catBadge?.classList.add('visible');
        updateSearchButtonState();
        try { localStorage.setItem(CAT_STORAGE_KEY, JSON.stringify({ cat, label, icon })); } catch(e) {}
        window.DebugPanel.sel('Categorie selectată din popup', { cat, label });
        await loadAdminSubcategoriesForCategory(_selectedCategoryLabel);
        if (selMotor && selMotor.value !== '0') {
            selMotor.dispatchEvent(new Event('change'));
        }
    }

    function clearCategory() {
        _selectedCategory = null;
        _selectedCategoryLabel = null;
        _adminSubcategoriiForCategory = [];
        catBadge?.classList.remove('visible');
        setCatBadgeIcon('');
        selCategorie.innerHTML = '<option value="0">Alege subcategoria</option>';
        subcatSlot.style.display = 'none';
        vehicleBox.classList.remove('has-subcat');
        updateSearchButtonState();
        try { localStorage.removeItem(CAT_STORAGE_KEY); } catch(e) {}
    }

    function restoreCategoryFromStorage() {
        try {
            const saved = JSON.parse(localStorage.getItem(CAT_STORAGE_KEY) || 'null');
            if (saved && saved.cat) {
                _selectedCategory = saved.cat;
                _selectedCategoryLabel = saved.label || saved.cat;
                if (catBadgeText) catBadgeText.textContent = saved.label || saved.cat;
                setCatBadgeIcon(saved.icon || '');
                catBadge?.classList.add('visible');
                loadAdminSubcategoriesForCategory(_selectedCategoryLabel);
            }
        } catch(e) {}
    }

    restoreCategoryFromStorage();
    updateSearchButtonState();
    catBadgeX?.addEventListener('click', clearCategory);

    /* ── MARCĂ → MODEL cascade ── */
    if (!selMarca || !selModel || !selMotor || !btnSearch || !selCategorie) {
        console.warn('[home-tecdoc] Elemente filterbar lipsă — handler-ele vehicul nu se leagă.');
    }

    selMarca?.addEventListener('change', async (e) => {
        const manuId = e.target.value;
        window.DebugPanel.sel('Marcă selectată', { manuId });

        selModel.innerHTML = '<option value="0">MODEL ...</option>';
        selMotor.innerHTML = '<option value="0">MOTORIZARE ...</option>';
        selMotor.disabled  = true;
        updateSearchButtonState();

        if (manuId === '0') { selModel.disabled = true; return; }

        selModel.disabled = false;
        selModel.innerHTML = '<option value="0">Se încarcă...</option>';

        try {
            const data = await apiCall('get_models', `${apiPath}?action=get_models&manuId=${manuId}`);
            selModel.innerHTML = '<option value="0">Alege modelul...</option>';
            if (data && Array.isArray(data.models) && data.models.length > 0) {
                const filtered = data.models.filter(m => isModelAllowed(m.modelName || ''));
                filtered.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.modelId;
                    const yearFrom = m.modelYearFrom ? String(m.modelYearFrom).substring(0, 4) : '';
                    const yearTo   = m.modelYearTo   ? String(m.modelYearTo).substring(0, 4)   : 'Prezent';
                    opt.textContent = `${m.modelName} (${yearFrom} - ${yearTo})`;
                    selModel.appendChild(opt);
                });
                if (filtered.length === 0) selModel.innerHTML = '<option value="0">Nu s-au găsit modele active</option>';
            } else {
                selModel.innerHTML = '<option value="0">Nu s-au găsit modele</option>';
            }
        } catch (error) {
            selModel.innerHTML = '<option value="0">Eroare la server</option>';
        }
    });

    /* ── MODEL → MOTORIZARE cascade ── */
    selModel?.addEventListener('change', async (e) => {
        const modelId = e.target.value;
        window.DebugPanel.sel('Model selectat', { modelId });

        selMotor.innerHTML = '<option value="0">MOTORIZARE ...</option>';
        updateSearchButtonState();

        if (modelId === '0') { selMotor.disabled = true; return; }

        selMotor.disabled = false;
        selMotor.innerHTML = '<option value="0">Se încarcă motorizările...</option>';

        try {
            const data = await apiCall('get_vehicles', `${apiPath}?action=get_vehicles&modelId=${modelId}`);
            selMotor.innerHTML = '<option value="0">MOTORIZARE / PUTERE ...</option>';
            if (data && Array.isArray(data.vehicles) && data.vehicles.length > 0) {
                data.vehicles.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.carId;
                    opt.textContent = `${v.typeName || v.typeEngineName || ''} (${v.powerPs} CP / ${v.powerKw} KW) - ${v.fuelType || ''}`;
                    selMotor.appendChild(opt);
                });
            } else if (data && data.vehicleTypeDetails) {
                const v = data.vehicleTypeDetails;
                const opt = document.createElement('option');
                opt.value = v.carId || modelId;
                opt.textContent = `${v.typeEngineName || ''} (${v.powerPs} CP / ${v.powerKw} KW) - ${v.fuelType || ''}`;
                selMotor.appendChild(opt);
            } else {
                selMotor.innerHTML = '<option value="0">Nu s-au găsit motorizări</option>';
            }
        } catch (error) {
            selMotor.innerHTML = '<option value="0">Eroare la server</option>';
        }
    });

    /* ── MOTORIZARE selectată → încarcă automat subcategoriile TecDoc (debounce) ── */
    selMotor?.addEventListener('change', () => {
        const carId = selMotor.value;
        updateSearchButtonState();

        if (subcatSlot) subcatSlot.style.display = 'none';
        vehicleBox?.classList.remove('has-subcat');
        if (selCategorie) selCategorie.innerHTML = '<option value="0">Alege subcategoria</option>';

        if (carId === '0') return;

        scheduleMotorChange(async () => {
        const activeCarId = selMotor.value;
        if (activeCarId === '0' || activeCarId !== carId) return;

        _ultimulCarId = activeCarId;
        _categoriiTecdoc = {};
        selCategorie.innerHTML = '<option value="0">Se încarcă...</option>';

        try {
            const data = await apiCall('get_parts', `${apiPath}?action=get_parts&carId=${activeCarId}`);

            if (data && data.categories && typeof data.categories === 'object') {
                _categoriiTecdoc = data.categories;
                _tecdocNodeIndex = buildTecdocNodeIndex(data.categories);

                if (!_selectedCategory && !_selectedCategoryLabel) {
                    selCategorie.innerHTML = '<option value="0">Selectează categoria din meniu</option>';
                    subcatSlot.style.display = '';
                    vehicleBox.classList.add('has-subcat');
                    return;
                }

                await loadAdminSubcategoriesForCategory(_selectedCategoryLabel || _selectedCategory);
                const bdItems = await fetchBdSubcategoriesForCategory(_selectedCategoryLabel || _selectedCategory);

                if (bdItems.length === 0) {
                    selCategorie.innerHTML = '<option value="0">Nu există subcategorii în stoc</option>';
                    subcatSlot.style.display = '';
                    vehicleBox.classList.add('has-subcat');
                    window.DebugPanel.ok(`0 subcategorii BD pentru "${_selectedCategoryLabel}"`, { carId });
                    return;
                }

                populateSubcategoryDropdown(bdItems);
                window.DebugPanel.ok(`${bdItems.length} subcategorii din stoc pentru "${_selectedCategoryLabel}"`, bdItems.map(i => i.label));
            }
        } catch (error) {
            selCategorie.innerHTML = '<option value="0">Eroare la server</option>';
        }
        scheduleAutoApplyVehicleFilter();
        });
    });

    function goToCatalogFromHomeFilters(overrides) {
        if (!isHomeVitrinaPage()) {
            return false;
        }
        const vinInput = document.getElementById('_filter-vin');
        const vinQuery = (vinInput && vinInput.value) ? vinInput.value.trim() : '';
        const hasCategory = !!(_selectedCategoryLabel || _selectedCategory);
        const carId = _ultimulCarId || (selMotor && selMotor.value) || '';
        const hasVehicle = carId && carId !== '0';
        if (!vinQuery && !hasCategory && !hasVehicle) {
            window.alert('Selectează categorie, vehicul sau introdu cod OEM / VIN, apoi apasă CAUTĂ PIESĂ.');
            return true;
        }
        redirectHomeFiltersToCatalog(overrides);
        return true;
    }

    /* ── CAUTĂ PIESĂ → redirect catalog cu toate filtrele selectate ── */
    btnSearch?.addEventListener('click', () => {
        if (goToCatalogFromHomeFilters()) {
            return;
        }
        const ctx = getSelectedSubcategoryContext();
        const carId = _ultimulCarId || selMotor.value;

        if (!ctx.bdSubcategory) {
            if (_selectedCategoryLabel && carId && carId !== '0') {
                showProductDebug('Selectează o subcategorie din stoc înainte de căutare.');
            }
            return;
        }

        if (typeof window.__showApiProductsForSubcategory === 'function') {
            window.__showApiProductsForSubcategory(ctx.tecdocNodeId, ctx.bdSubcategory);
        }
        scrollToProductTop();
    });

    /* ── Subcategorie selectată — rafinare automată listă ── */
    selCategorie?.addEventListener('change', (e) => {
        updateSearchButtonState();
        if (e.target.value === '0') {
            scheduleAutoApplyVehicleFilter();
            return;
        }
        scheduleAutoApplyVehicleFilter();
    });

    (function bindHomeHeaderSearch() {
        const headerInput = document.getElementById('_home-product-name');
        const headerBtn = document.getElementById('_home-search-btn');
        if (!headerInput || !headerBtn) return;
        function runHeaderSearch() {
            if (!isHomeVitrinaPage()) return;
            const query = headerInput.value.trim();
            if (!query) {
                window.alert('Introdu cod OEM, VIN sau denumire piesă.');
                return;
            }
            const vinField = document.getElementById('_filter-vin');
            if (vinField) vinField.value = query;
            redirectHomeFiltersToCatalog({ q: query });
        }
        headerBtn.addEventListener('click', runHeaderSearch);
        headerInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runHeaderSearch();
            }
        });
    })();

    function atașeazăEvenimenteCarduri() {
        document.querySelectorAll('._product-card').forEach(card => {
            if (card.dataset._bound === '1') return;
            card.dataset._bound = '1';

            const img = card.querySelector('._product-card-image');
            const btnDetalii = card.querySelector('.product_detal');

            const goToProduct = (e) => {
                e.preventDefault();
                const productId = card.dataset.productId || btnDetalii?.dataset.productId || '';
                if (!productId) {
                    alert('Produsul nu este disponibil în magazin.');
                    return;
                }
                window.location.href = `/produs?id=${encodeURIComponent(productId)}`;
            };

            if (img) { img.classList.add('_product-card-image--clickable'); img.addEventListener('click', goToProduct); }
            btnDetalii?.addEventListener('click', goToProduct);
        });
    }

    /* ════════════════════════════════════════════════════════════
       3. PRODUSE — doar prin TecDoc + BD (fără catalog inițial)
       ════════════════════════════════════════════════════════════ */
    function bindCategoryPickDelegation(container, closePopup) {
        if (!container || container.dataset.pickBound === '1') return;
        container.dataset.pickBound = '1';
        container.addEventListener('click', (event) => {
            const item = event.target.closest('.cat-popup-item, .category-card');
            if (!item || !item.dataset.cat) return;
            const cat = item.dataset.cat || '';
            const label = item.dataset.catLabel || cat;
            const icon = item.dataset.catIcon || '';
            selectCategory(cat, label, icon).then(() => {
                if (isHomeVitrinaPage()) {
                    return;
                }
                if (closePopup) {
                    const popup = document.getElementById('cat-popup');
                    const overlay = document.getElementById('cat-popup-overlay');
                    popup?.classList.remove('open');
                    overlay?.classList.remove('open');
                }
                scrollToProductTop();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const productGrid = document.getElementById('_product-grid');
        if (!productGrid) return;
        if (productGrid.dataset.productGridBound === '1') return;
        productGrid.dataset.productGridBound = '1';

        const resultCount = document.getElementById('_product-results-count');
        const emptyState  = document.getElementById('_product-empty-state');
        const debugStatus = document.getElementById('_product-debug-status');
        const loaderPiese = document.getElementById('_loader-piese');

        const inputName     = document.getElementById('_filter-name');
        const inputOem      = document.getElementById('_filter-oem');
        const inputVin      = document.getElementById('_filter-vin');
        const inputCategory = document.getElementById('_filter-category');

        const btnApply = document.getElementById('_product-apply-filters');
        const btnReset = document.getElementById('_product-reset-filters');

        const DEFAULT_EMPTY_HTML = emptyState ? emptyState.innerHTML.trim() : '';
        const BROWSE_PROMPT = emptyState?.dataset?.browsePrompt
            || 'Momentan nu avem produse recomandate în această secțiune. Folosește filtrele de sus sau vezi catalogul.';

        let lastSubcategoryChildren = [];
        let lastSubcategoryTitle = '';
        let tecdocSelectionActive = false;

        function storefrontPublicNoticeText(text) {
            const plain = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            if (!plain) {
                return '';
            }
            if (BESOIU_ADMIN) {
                return plain;
            }
            if (typeof window.besoiuStorefrontPublicNotice === 'function') {
                return window.besoiuStorefrontPublicNotice(plain);
            }
            return plain;
        }

        function setEmptyStateMessage(message, useHtml) {
            if (!emptyState) return;
            if (message && !useHtml) {
                const clean = storefrontPublicNoticeText(message);
                if (!clean) {
                    message = BROWSE_PROMPT;
                } else {
                    message = clean;
                }
            }
            showBlock(emptyState);
            if (message) {
                if (useHtml) {
                    emptyState.innerHTML = message;
                } else {
                    emptyState.textContent = message;
                }
                return;
            }
            if (DEFAULT_EMPTY_HTML) {
                emptyState.innerHTML = DEFAULT_EMPTY_HTML;
            } else {
                emptyState.textContent = BROWSE_PROMPT;
            }
        }

        function showBrowsePrompt(message) {
            if (isHomeVitrinaPage()) {
                if (!message) {
                    loadVitrinaProducts();
                    if (emptyState) {
                        hideBlock(emptyState);
                    }
                } else {
                    setEmptyStateMessage(message, false);
                }
                return;
            }
            productGrid.innerHTML = '';
            if (resultCount) resultCount.textContent = '0';
            setEmptyStateMessage(message || '', false);
        }

        function showProductDebug(message, type = 'info') {
            if (!BESOIU_ADMIN || !debugStatus) return;
            showBlock(debugStatus);
            debugStatus.textContent = message;
            if (window.DebugPanel && typeof window.DebugPanel.ok === 'function') {
                window.DebugPanel.ok('Filtru produse', { message, type });
            }
        }

        function getCurrentCards() { return Array.from(productGrid.querySelectorAll('._product-card:not([data-subcategory-card])')); }

        let activeHomeBrand = '';

        function updateHomeBrandFilterPanel(brands) {
            let panel = document.getElementById('_brand-filter-panel');
            if (!panel) {
                panel = document.createElement('div');
                panel.id = '_brand-filter-panel';
                panel.className = '_brand-filter-panel';
                const section = document.querySelector('._product-section');
                const grid = document.getElementById('_product-grid');
                if (section && grid) {
                    section.insertBefore(panel, grid);
                }
            }
            const list = Array.isArray(brands) ? brands.filter(Boolean) : [];
            if (list.length === 0) {
                panel.classList.add('is-hidden');
                panel.innerHTML = '';
                activeHomeBrand = '';
                return;
            }
            panel.classList.remove('is-hidden');
            panel.innerHTML = '<div class="_brand-filter-panel__label">Brand în stoc:</div><div class="_brand-filter-panel__chips">'
                + ['<button type="button" class="_brand-filter-chip active" data-brand="">Toate</button>']
                    .concat(list.map(brand => `<button type="button" class="_brand-filter-chip" data-brand="${escapeHtml(brand)}">${escapeHtml(brand)}</button>`))
                    .join('')
                + '</div>';
            if (panel.dataset.bound !== '1') {
                panel.dataset.bound = '1';
                panel.addEventListener('click', (event) => {
                    const chip = event.target.closest('._brand-filter-chip');
                    if (!chip) return;
                    activeHomeBrand = chip.dataset.brand || '';
                    panel.querySelectorAll('._brand-filter-chip').forEach(el => {
                        el.classList.toggle('active', (el.dataset.brand || '') === activeHomeBrand);
                    });
                    getCurrentCards().forEach(card => {
                        if (!activeHomeBrand) {
                            card.style.display = '';
                            return;
                        }
                        const cardBrand = normalizeForMatch(card.dataset.brand || '');
                        const wanted = normalizeForMatch(activeHomeBrand);
                        const visible = cardBrand === wanted || cardBrand.includes(wanted) || wanted.includes(cardBrand);
                        card.style.display = visible ? '' : 'none';
                    });
                    if (resultCount) {
                        const visibleCount = getCurrentCards().filter(card => card.style.display !== 'none').length;
                        resultCount.textContent = String(visibleCount);
                    }
                });
            }
        }

        function renderProductCards(cards) {
            productGrid.innerHTML = '';
            cards.forEach(card => {
                card.style.display = '';
                card.dataset._bound = '';
                productGrid.appendChild(card);
            });
            atașeazăEvenimenteCarduri();
        }

        function articleImage(article) { return article.s3image || article.img || article.image || 'assets/images/products/1.jpg'; }
        function articleName(article) { return article.articleName || article.name || article.genericArticleDescription || 'Piesă auto'; }
        function articleNumber(article) { return article.articleNumber || article.articleNo || article.code || 'N/A'; }
        function articleBrand(article) { return article.brandName || article.supplierName || article.brand || ''; }

        function renderStockProductCards(payload, title, options) {
            if (isHomeVitrinaPage() && !(options && options.forceReplace)) {
                const vitrinaCards = productGrid.querySelectorAll('._product-card[data-home-vitrina="1"]');
                if (vitrinaCards.length > 0) {
                    loadVitrinaProducts();
                    return;
                }
            }
            const products = Array.isArray(payload?.products) ? payload.products : (Array.isArray(payload) ? payload : []);
            const scanned = Number(payload?.scanned || 0);
            if (emptyState) products.length ? hideBlock(emptyState) : showBlock(emptyState);
            productGrid.innerHTML = gridToolbar(`Produse disponibile: ${title}`, 'subcategories');
            updateHomeBrandFilterPanel(Array.isArray(payload?.stock_brands) ? payload.stock_brands : []);
            if (payload?.vehicle?.label) {
                const vehicleBanner = document.createElement('div');
                vehicleBanner.className = 'fz-vin-vehicle-banner';
                vehicleBanner.className = '_product-vehicle-banner';
                vehicleBanner.innerHTML = `<strong>Vehicul identificat:</strong> ${escapeHtml(payload.vehicle.label)}${payload.vin ? ` · VIN ${escapeHtml(payload.vin)}` : ''}`;
                productGrid.appendChild(vehicleBanner);
            }
            if (payload?.notice && String(payload.notice).trim() !== '') {
                const noticeText = publicStorefrontNotice(payload.notice);
                if (noticeText !== '') {
                    const notice = document.createElement('div');
                    notice.className = '_product-notice';
                    notice.textContent = noticeText;
                    productGrid.appendChild(notice);
                }
            }
            products.forEach(product => {
                const name = product.name || 'Piesă auto';
                const shortName = product.short_name || (window.besoiuVitrinaShortTitle ? window.besoiuVitrinaShortTitle(name) : name);
                const oem = product.code || product.tecdoc_article || 'N/A';
                const brand = product.brand || product.tecdoc_brand || '';
                const image = product.image || 'assets/images/products/1.jpg';
                const priceLabel = product.price_label || (Number(product.price_numeric || 0) > 0 ? Number(product.price_numeric).toFixed(2) + ' RON' : 'La cerere');
                const price = Number(product.price_numeric || product.price || 0);
                const productId = product.randomn_id || product.id || '';
                const badgeKey = String(product.badge || product.pBadge || '').trim();
                const isHomeVitrinaCard = !!(options && options.homeVitrina);
                let displayBadge = badgeKey;
                if (options && options.homeVitrina && !displayBadge) {
                    displayBadge = 'recomandat';
                }
                const rawNote = String(product.note_plain || '').trim();
                const fallbackNote = rawNote !== '' ? rawNote : (product.note || product.tecdoc_specs || '');
                const description = isHomeVitrinaCard
                    ? shortName
                    : (fallbackNote || (brand ? `${brand} - ${name}` : name));
                const cardDescription = window.besoiuStripHtml
                    ? window.besoiuStripHtml(description)
                    : String(description || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                const apiSpecs = isHomeVitrinaCard
                    ? []
                    : (window.besoiuParseNoteSpecs ? window.besoiuParseNoteSpecs(cardDescription) : []);
                const cardSpecs = isHomeVitrinaCard
                    ? []
                    : (window.besoiuFilterCardSpecsPreview
                        ? window.besoiuFilterCardSpecsPreview(apiSpecs.length ? apiSpecs : [{ label: 'Brand', value: brand || '—' }, { label: 'Cod', value: oem }])
                        : apiSpecs.slice(0, 4));
                const card = document.createElement('article');
                const useHomeGrid = !!(options && options.homeGridLayout);
                card.className = useHomeGrid ? '_product-card home-grid-card home-vitrina-card' : '_product-card magazin-card';
                if (!useHomeGrid) {
                    card.dataset.cardType = 'magazin';
                }
                card.dataset.productId = productId;
                card.dataset.name = name;
                card.dataset.oem = oem;
                card.dataset.vin = '';
                card.dataset.category = title;
                card.dataset.brand = brand;
                card.dataset.price = String(price);
                card.dataset.image = image;
                if (displayBadge) {
                    card.dataset.badge = displayBadge;
                }
                if (options && options.homeVitrina) {
                    card.dataset.homeVitrina = '1';
                    card.dataset.recommended = '1';
                }
                if (apiSpecs.length) {
                    card.dataset.specs = JSON.stringify(apiSpecs);
                    card.dataset.desc = apiSpecs.map((spec) => spec.label + ' ' + spec.value).join(' ');
                } else {
                    card.dataset.desc = cardDescription;
                }
                if (useHomeGrid && isHomeVitrinaCard && window.besoiuRenderHomeVitrinaCardHtml) {
                    card.innerHTML = window.besoiuRenderHomeVitrinaCardHtml({
                        name,
                        shortName,
                        image,
                        priceLabel,
                        price_numeric: price,
                        price_old_label: product.price_old_label || '',
                        discount_percent: product.discount_percent || 0,
                        product,
                    });
                } else if (useHomeGrid && window.besoiuRenderHomeGridCardHtml) {
                    card.innerHTML = window.besoiuRenderHomeGridCardHtml({
                        name,
                        image,
                        priceLabel,
                        productId,
                        badge: badgeKey,
                        recommendedBadge: !!(options && options.homeVitrina && !badgeKey),
                    });
                } else if (window.besoiuRenderMagazinCardHtml) {
                    card.innerHTML = window.besoiuRenderMagazinCardHtml({
                        name,
                        image,
                        priceLabel,
                        productId,
                        oem,
                        badge: displayBadge,
                        description: cardDescription,
                        specs: cardSpecs,
                        deliveryTime: '24',
                    });
                } else {
                    const badgeHtml = window.besoiuRenderProductBadgeHtml
                        ? window.besoiuRenderProductBadgeHtml(displayBadge)
                        : '';
                    const specsBlock = window.besoiuRenderProductSpecsHtml
                        ? window.besoiuRenderProductSpecsHtml(cardSpecs, 2, '_product-card-specs')
                        : '';
                    const noteBlock = !specsBlock && cardDescription
                        ? `<p class="_product-card-desc">${escapeHtml(cardDescription)}</p>`
                        : '';
                    card.innerHTML = `${badgeHtml}
                    <div class="_product-card-head"><h3 class="_product-card-name">${escapeHtml(name)}</h3></div>
                    <div class="_product-card-image"><img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" loading="lazy" decoding="async"></div>
                    ${specsBlock}
                    ${noteBlock}
                    <div class="_product-card-info">
                        <div class="_product-oem">OEM: ${escapeHtml(oem)}</div>
                        <div class="_product-time">24 H</div>
                    </div>
                    <div class="_product-price">${escapeHtml(priceLabel)}</div>
                    ${window.besoiuProductCardActionsHtml ? window.besoiuProductCardActionsHtml(productId) : ''}`;
                }
                productGrid.appendChild(card);
            });
            atașeazăEvenimenteCarduri();
            if (resultCount) resultCount.textContent = products.length;
            if (emptyState) {
                if (products.length) {
                    hideBlock(emptyState);
                } else {
                    setEmptyStateMessage('Nu am găsit piese cu stoc confirmat în magazin pentru această selecție (produsele fără coloană stoc validă în Excel furnizor sunt respinse la import).', false);
                }
            }
            showProductDebug(products.length
                ? `${products.length} produse din stocul magazinului pentru "${title}".`
                : `Nu există piese în stoc pentru "${title}".`);
        }

        function renderApiArticleCards(articles, title) {
            renderStockProductCards({ products: articles, scanned: articles.length }, title);
        }

        function normalizeText(value) { return String(value || '').toLowerCase().trim().replace(/\s+/g, ' '); }
        function normalizeVin(value) { return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); }
        function normalizeProductCode(value) {
            return String(value || '').toUpperCase().trim()
                .replace(/[\s\-\/_.]/g, '')
                .replace(/[^A-Z0-9]/g, '');
        }
        window.besoiuNormalizeProductCode = normalizeProductCode;
        function isVinQuery(value) {
            const vin = normalizeVin(value);
            return vin.length === 17 && !/[IOQ]/.test(vin) && /^[A-HJ-NPR-Z0-9]{17}$/.test(vin);
        }
        function parsePrice(value) {
            const cleaned = String(value || '').replace(/[^\d.,]/g, '').replace(',', '.');
            const parsed = parseFloat(cleaned);
            return isNaN(parsed) ? 0 : parsed;
        }
        function getCardData(card) {
            return {
                name: normalizeText(card.dataset.name || card.querySelector('._product-card-name')?.textContent),
                oem: normalizeText(card.dataset.oem || ''),
                vin: normalizeText(card.dataset.vin || ''),
                category: normalizeText(card.dataset.category || ''),
                price: parsePrice(card.dataset.price || ''),
                desc: normalizeText(card.dataset.desc || card.querySelector('._product-card-desc')?.textContent || Array.from(card.querySelectorAll('._product-spec-value')).map(el => el.textContent).join(' '))
            };
        }
        function getFilters() {
            return {
                name: normalizeText(inputName?.value),
                oem: normalizeText(inputOem?.value),
                vin: normalizeVin(inputVin?.value),
                category: normalizeText(inputCategory?.value)
            };
        }

        async function searchProductsViaTecDoc(filters, globalSearch = false) {
            const hasVin = !!(filters.vin && isVinQuery(filters.vin));
            const hasOem = !!(filters.oem && String(filters.oem).trim().length >= 2);
            const useSearchOem = hasOem && !hasVin && globalSearch;

            const params = new URLSearchParams({ action: useSearchOem ? 'search_oem' : 'search_stock' });

            if (useSearchOem) {
                params.set('code', normalizeProductCode(filters.oem));
            } else {
                if (filters.name) params.set('name', filters.name);
                if (filters.oem) params.set('oem', normalizeProductCode(filters.oem));
                if (filters.vin) params.set('vin', filters.vin);
            }

            if (!globalSearch) {
                if (filters.category) params.set('category', filters.category);
                else if (_selectedCategoryLabel) params.set('category', _selectedCategoryLabel);
                const ctx = getSelectedSubcategoryContext();
                if (ctx.bdSubcategory) params.set('subcategory', ctx.bdSubcategory);
                const carId = filters.car_id || _ultimulCarId || selMotor?.value || '';
                if (carId && carId !== '0') params.set('car_id', carId);
                if (!ctx.bdSubcategory) {
                    if (ctx.tecdocNodeId) params.set('node_id', ctx.tecdocNodeId);
                    const marcaOpt = selMarca?.options[selMarca.selectedIndex];
                    if (marcaOpt && marcaOpt.value !== '0') {
                        params.set('marca', marcaOpt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim());
                    }
                }
            } else if (filters.category) {
                params.set('category', filters.category);
            }
            const response = await fetch(`${apiPath}?${params.toString()}`);
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Căutarea TecDoc a eșuat.');
            }
            if (data.car_id) _ultimulCarId = String(data.car_id);
            return data;
        }
        function matches(cardData, f) {
            const mName = !f.name || cardData.name.includes(f.name) || cardData.desc.includes(f.name);
            const mOem  = !f.oem  || cardData.oem.includes(f.oem);
            const mVin  = !f.vin  || cardData.vin.includes(f.vin) || cardData.desc.includes(f.vin) || cardData.name.includes(f.vin);
            const mCat  = !f.category || cardData.category.includes(f.category);
            return mName && mOem && mVin && mCat;
        }

        function gridToolbar(title, backAction) {
            return `<div class="_product-grid-toolbar">
                <button type="button" class="_product-btn _product-btn--back" data-grid-back="${backAction}"><i class="fa-solid fa-arrow-left"></i> Înapoi</button>
                <div class="_product-grid-toolbar__title">${escapeHtml(title)}</div>
            </div>`;
        }

        function renderSubcategoryCards(children, title) {
            lastSubcategoryChildren = children;
            lastSubcategoryTitle = title;
            tecdocSelectionActive = true;
            if (emptyState) hideBlock(emptyState);
            productGrid.innerHTML = gridToolbar(`Subcategorii: ${title}`, 'products');
            children.forEach(child => {
                const card = document.createElement('article');
                card.className = '_product-card _subcategory-card';
                card.dataset.subcategoryCard = '1';
                card.dataset.subcategoryId = child.id;
                card.dataset.subcategoryFilter = child.label;
                card.innerHTML = `
                    <div class="_product-card-head"><h3 class="_product-card-name">${escapeHtml(child.label)}</h3></div>
                    <div class="_product-card-image _product-card-image--placeholder"><span>▦</span></div>
                    <p class="_product-card-desc">Alege această subcategorie pentru a încărca produsele.</p>
                    <div class="_product-card-info"><div class="_product-oem">Subcategorie</div><div class="_product-time">BD</div></div>
                    <div class="_product-price">Vezi produse</div>
                    <div><button class="_product-card-btn _product-card-btn--full" type="button" data-subcategory-id="${escapeHtml(child.id)}" data-subcategory-filter="${escapeHtml(child.label)}">Alege</button></div>`;
                productGrid.appendChild(card);
            });
            if (resultCount) resultCount.textContent = children.length;
            if (emptyState) children.length ? hideBlock(emptyState) : showBlock(emptyState);
            showProductDebug(`S-au încărcat ${children.length} subcategorii pentru ${title}.`);
        }

        function getVitrinaLimit() {
            const grid = document.getElementById('_product-grid');
            const raw = grid && grid.dataset.vitrinaLimit ? parseInt(String(grid.dataset.vitrinaLimit), 10) : 10;
            return Number.isFinite(raw) ? Math.max(1, Math.min(10, raw)) : 10;
        }

        function lockHomeVitrinaUi(cardCount) {
            const panel = document.querySelector('.home-products-panel');
            if (panel && cardCount > 0) {
                panel.classList.add('home-products-panel--has-vitrina');
                panel.classList.remove('home-products-panel--empty-vitrina');
            }
            if (loaderPiese) hideBlock(loaderPiese);
            if (emptyState && cardCount > 0) hideBlock(emptyState);
            if (resultCount) resultCount.textContent = String(cardCount);
        }

        let _vitrinaLoading = false;
        let _vitrinaLoadFailed = false;

        async function loadVitrinaProducts(forceReload) {
            if (!isHomeVitrinaPage()) {
                showBrowsePrompt();
                return;
            }
            if (_vitrinaLoading) {
                return;
            }
            if (_vitrinaLoadFailed && !forceReload) {
                return;
            }
            const ssrCards = productGrid.querySelectorAll('._product-card[data-home-vitrina="1"]');
            if (ssrCards.length > 0) {
                lockHomeVitrinaUi(ssrCards.length);
                atașeazăEvenimenteCarduri();
                showProductDebug('Vitrină homepage (SSR): ' + ssrCards.length + ' produse.');
                return;
            }
            _vitrinaLoading = true;
            if (loaderPiese) showBlock(loaderPiese);
            if (emptyState) hideBlock(emptyState);
            const vitrinaLimit = getVitrinaLimit();
            try {
                const response = await fetch(apiPath + '?action=vitrina&limit=' + vitrinaLimit);
                const data = await response.json();
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'Nu am putut încărca vitrina.');
                }
                _vitrinaLoadFailed = false;
                renderStockProductCards(data, 'Produse recomandate', { homeGridLayout: true, homeVitrina: true });
                if (!data.count) {
                    setEmptyStateMessage(
                        'Niciun ulei sau consumabil pe vitrină. Adaugă produse din categoria Ulei & Lichide în Admin → Vitrina homepage.',
                        false
                    );
                }
            } catch (error) {
                _vitrinaLoadFailed = true;
                setEmptyStateMessage(error.message || 'Vitrina indisponibilă.', false);
            } finally {
                _vitrinaLoading = false;
                if (loaderPiese) hideBlock(loaderPiese);
            }
        }

        async function applyFilters() {
            const f = getFilters();
            const vinQuery = normalizeVin(inputVin?.value || f.vin || '');
            const hasVinSearch = isVinQuery(vinQuery);
            const oemQuery = normalizeProductCode(inputOem?.value || inputVin?.value || '');
            const hasOemSearch = !hasVinSearch && oemQuery.length >= 2;
            const hasRemoteQuery = hasVinSearch || hasOemSearch || !!(f.name && f.name.length >= 2);

            if (isHomeVitrinaPage()) {
                loadVitrinaProducts();
                return;
            }

            if (hasRemoteQuery && redirectHomeFiltersToCatalog({ q: hasVinSearch ? vinQuery : (oemQuery || f.name) })) {
                return;
            }

            if (hasRemoteQuery) {
                if (loaderPiese) showBlock(loaderPiese);
                if (emptyState) hideBlock(emptyState);
                try {
                    const globalSearch = hasVinSearch || hasOemSearch;
                    const searchFilters = hasVinSearch
                        ? { vin: vinQuery, oem: f.oem || undefined, name: f.name || undefined }
                        : {
                            oem: oemQuery,
                            name: oemQuery,
                            category: f.category || undefined,
                        };
                    const data = await searchProductsViaTecDoc(searchFilters, globalSearch);
                    if (hasVinSearch && data.car_id) {
                        tecdocSelectionActive = true;
                        _ultimulCarId = String(data.car_id);
                    }
                    const title = hasVinSearch
                        ? (data.vehicle?.label || 'VIN ' + vinQuery)
                        : (oemQuery || f.name || 'Căutare');
                    renderStockProductCards(data, title);
                    if (!data.count && data.notice) {
                        setEmptyStateMessage(data.notice, false);
                    }
                    scrollToProductTop();
                } catch (error) {
                    showProductDebug(error.message || 'Eroare la căutarea TecDoc.', 'err');
                    showBrowsePrompt(error.message || 'Nu am putut căuta produsele.');
                } finally {
                    if (loaderPiese) hideBlock(loaderPiese);
                }
                return;
            }

            if (!tecdocSelectionActive) {
                showBrowsePrompt();
                return;
            }

            const cards = getCurrentCards();
            if (cards.length === 0) {
                if (resultCount) resultCount.textContent = '0';
                return;
            }
            let visible = cards.filter(c => matches(getCardData(c), f));
            showProductDebug(`Filtru local aplicat. Produse găsite: ${visible.length}.`);
            productGrid.innerHTML = gridToolbar(`Produse pentru: ${f.name || lastSubcategoryTitle}`, 'subcategories');
            renderProductCards(visible);
            if (resultCount) resultCount.textContent = visible.length;
            if (emptyState) visible.length ? hideBlock(emptyState) : showBlock(emptyState);
        }

        function resetFilters() {
            if (inputName) inputName.value = '';
            if (inputOem) inputOem.value = '';
            if (inputVin) inputVin.value = '';
            if (inputCategory) inputCategory.value = '';
            if (subcategoryPanel) hideBlock(subcategoryPanel);
            if (subcategoryList) subcategoryList.innerHTML = '';
            lastSubcategoryChildren = [];
            lastSubcategoryTitle = '';
            tecdocSelectionActive = false;
            activeHomeBrand = '';
            updateHomeBrandFilterPanel([]);
            if (debugStatus) hideBlock(debugStatus);
            if (isHomeVitrinaPage()) {
                loadVitrinaProducts();
                return;
            }
            showBrowsePrompt();
        }

        function transferHeroSearchToFilter() {
            const query = (inputVin?.value || '').trim();
            if (!query) {
                showBrowsePrompt('Introdu un cod OEM, număr de piesă sau VIN pentru căutare.');
                scrollToProductTop();
                return;
            }
            if (isHomeVitrinaPage()) {
                redirectHomeFiltersToCatalog({ q: query });
                return;
            }
            if (!isVinQuery(query)) {
                const oemNorm = normalizeProductCode(query);
                if (inputOem) inputOem.value = oemNorm;
                if (inputName) inputName.value = oemNorm || query;
            } else {
                if (inputVin) inputVin.value = query;
                if (inputOem) inputOem.value = '';
                if (inputName) inputName.value = '';
            }
            applyFilters();
            scrollToProductTop();
        }

        btnApply?.addEventListener('click', () => {
            const query = (inputVin?.value || '').trim();
            if (query && isHomeVitrinaPage()) {
                redirectHomeFiltersToCatalog({ q: query });
                return;
            }
            if (query && !isVinQuery(query)) {
                const oemNorm = normalizeProductCode(query);
                if (inputOem) inputOem.value = oemNorm;
                if (inputName) inputName.value = oemNorm || query;
            } else if (query && isVinQuery(query)) {
                if (inputOem) inputOem.value = '';
                if (inputName) inputName.value = '';
            }
            applyFilters();
            scrollToProductTop();
        });
        btnReset?.addEventListener('click', resetFilters);
        inputVin?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); transferHeroSearchToFilter(); } });

        window.__applyProductFilters = applyFilters;
        window.__showSubcategoryChoices = renderSubcategoryCards;
        window.__showProductsForSubcategory = function (term) {
            if (inputName) inputName.value = term;
            if (inputCategory) inputCategory.value = '';
            tecdocSelectionActive = true;
            applyFilters();
        };
        async function loadProductsForSubcategory(nodeId, title, options) {
            const bdSubcategory = cleanCategoryLabel(title || '');
            const carId = _ultimulCarId || selMotor?.value || '';
            const category = _selectedCategoryLabel || _selectedCategory || '';
            if (!category || !bdSubcategory) {
                showProductDebug('Selectează categoria și subcategoria din stoc.');
                return;
            }
            const homeGrid = !!(options && options.homeGrid) || isHomeVitrinaPage();
            if (loaderPiese) showBlock(loaderPiese);
            if (emptyState) hideBlock(emptyState);
            productGrid.innerHTML = gridToolbar(`Se încarcă produse pentru: ${bdSubcategory}`, 'subcategories');
            try {
                const params = new URLSearchParams({ action: 'get_articles' });
                if (carId && carId !== '0') params.set('carId', carId);
                params.set('category', category);
                params.set('subcategory', bdSubcategory);
                const oemVal = normalizeProductCode(document.getElementById('_filter-oem')?.value || '');
                if (oemVal) params.set('oem', oemVal);
                const data = await apiCall('get_articles', `${apiPath}?${params.toString()}`);
                if (data && data.success === false) {
                    throw new Error(data.message || 'Eroare la încărcarea produselor');
                }
                if (data && !data.products?.length && tecdocQuotaNotice(data)) {
                    data.notice = data.notice || tecdocQuotaNotice(data);
                }
                renderStockProductCards(data, bdSubcategory, {
                    homeGridLayout: homeGrid,
                    homeVitrina: false,
                    forceReplace: homeGrid,
                });
                if (!options || options.scroll !== false) {
                    scrollToProductTop();
                }
            } catch (error) {
                productGrid.innerHTML = gridToolbar(`Produse: ${bdSubcategory}`, 'subcategories');
                setEmptyStateMessage('Nu am putut încărca produsele disponibile.', false);
                showProductDebug(error.message || 'Eroare la încărcarea produselor.');
            } finally {
                if (loaderPiese) hideBlock(loaderPiese);
            }
        }

        window.__autoApplyVehicleFilter = async function () {
            const carId = _ultimulCarId || selMotor?.value || '';
            if (!carId || carId === '0') {
                if (isHomeVitrinaPage()) {
                    loadVitrinaProducts();
                }
                return;
            }

            const ctx = getSelectedSubcategoryContext();
            const category = _selectedCategoryLabel || _selectedCategory || '';

            if (category && ctx.bdSubcategory) {
                await loadProductsForSubcategory(ctx.tecdocNodeId, ctx.bdSubcategory, {
                    homeGrid: isHomeVitrinaPage(),
                    scroll: true,
                });
                return;
            }

            if (category) {
                if (loaderPiese) showBlock(loaderPiese);
                if (emptyState) hideBlock(emptyState);
                try {
                    const data = await searchProductsViaTecDoc({ category, car_id: carId });
                    renderStockProductCards(data, category, {
                        homeGridLayout: isHomeVitrinaPage(),
                        homeVitrina: false,
                        forceReplace: isHomeVitrinaPage(),
                    });
                    scrollToProductTop();
                } catch (error) {
                    showProductDebug(error.message || 'Eroare la filtrarea produselor.', 'err');
                } finally {
                    if (loaderPiese) hideBlock(loaderPiese);
                }
                return;
            }

            const marcaOpt = selMarca?.options[selMarca.selectedIndex];
            if (!marcaOpt || marcaOpt.value === '0') {
                return;
            }

            if (loaderPiese) showBlock(loaderPiese);
            if (emptyState) hideBlock(emptyState);
            try {
                const data = await searchProductsViaTecDoc({
                    category: '',
                    car_id: carId,
                    marca: marcaOpt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim(),
                });
                renderStockProductCards(data, marcaOpt.textContent.trim(), {
                    homeGridLayout: isHomeVitrinaPage(),
                    homeVitrina: false,
                    forceReplace: isHomeVitrinaPage(),
                });
                scrollToProductTop();
            } catch (error) {
                showProductDebug(error.message || 'Eroare la filtrarea produselor.', 'err');
            } finally {
                if (loaderPiese) hideBlock(loaderPiese);
            }
        };

        window.__showApiProductsForSubcategory = async function (nodeId, title) {
            await loadProductsForSubcategory(nodeId, title, { homeGrid: isHomeVitrinaPage() });
        };

        productGrid.addEventListener('click', (event) => {
            const subcategoryButton = event.target.closest('[data-subcategory-filter]');
            if (subcategoryButton) {
                window.__showApiProductsForSubcategory(subcategoryButton.dataset.subcategoryId || '', subcategoryButton.dataset.subcategoryFilter || '');
                return;
            }
            const backButton = event.target.closest('[data-grid-back]');
            if (!backButton) return;
            if (backButton.dataset.gridBack === 'subcategories' && lastSubcategoryChildren.length) {
                renderSubcategoryCards(lastSubcategoryChildren, lastSubcategoryTitle);
                scrollToProductTop();
                return;
            }
            resetFilters();
            scrollToProductTop();
        });

        if (isHomeVitrinaPage()) {
            const initialVitrina = productGrid.querySelectorAll('._product-card[data-home-vitrina="1"]').length;
            if (initialVitrina > 0) {
                lockHomeVitrinaUi(initialVitrina);
            }
        }
        loadVitrinaProducts();
    });

    document.addEventListener('DOMContentLoaded', function initHomeCategoryUi() {
        if (typeof atașeazăEvenimenteCarduri === 'function') atașeazăEvenimenteCarduri();

        /* ── Click pe vehicle-item deschide select-ul din interior ── */
        document.querySelectorAll('.vehicle-item').forEach(item => {
            if (item.dataset.vehiclePickBound === '1') return;
            item.dataset.vehiclePickBound = '1';
            item.classList.add('_product-card-image--clickable');
            item.addEventListener('click', (e) => {
                if (e.target.tagName === 'SELECT' || e.target.tagName === 'OPTION') return;
                const sel = item.querySelector('select');
                if (sel && !sel.disabled) {
                    sel.focus();
                    if (typeof sel.showPicker === 'function') {
                        try { sel.showPicker(); } catch (err) { /* ignore */ }
                    }
                }
            });
        });

        /* ── Popup + grid categorii — delegare click (fără re-bind la fiecare fetch) ── */
        const catToggle = document.getElementById('cat-toggle');
        const catPopup = document.getElementById('cat-popup');
        const catOverlay = document.getElementById('cat-popup-overlay');
        const categoryGrid = document.getElementById('category-grid-dynamic');

        bindCategoryPickDelegation(catPopup, true);
        bindCategoryPickDelegation(categoryGrid, false);

        async function loadDynamicCategories() {
            try {
                const res = await fetch('/api_categorii.php?action=popup');
                const json = await res.json();
                if (!json.success || !json.categories) return;

                const cats = json.categories;

                if (catPopup) {
                    catPopup.innerHTML = cats.map(c => `
                        <div class="cat-popup-item" data-cat="${c.slug}" data-cat-label="${c.label}" data-cat-icon="${c.icon}">
                            <img src="${c.icon}" alt="">${c.label}<span class="cat-count">${c.count || ''}</span>
                        </div>
                    `).join('');
                }

                if (categoryGrid) {
                    categoryGrid.innerHTML = cats.map(c => `
                        <div class="category-card" data-cat="${c.slug}" data-cat-label="${c.label}" data-cat-icon="${c.icon}">
                            <div class="icon"><img src="${c.icon}" alt="${c.label}" class="category-card-icon"></div>
                            <b>${c.label}</b>
                            <span>${c.count ? c.count + ' produse' : ''}</span>
                        </div>
                    `).join('');
                }
            } catch (err) {
                console.warn('Categorii API indisponibil, se păstrează conținutul static.', err);
            }
        }

        deferNonCritical(loadDynamicCategories);

        const heroApplyBtn = document.getElementById('_product-apply-filters');
        const heroVinInput = document.getElementById('_filter-vin');
        if (heroApplyBtn && heroApplyBtn.dataset.heroBound !== '1') {
            heroApplyBtn.dataset.heroBound = '1';
            heroApplyBtn.addEventListener('click', function () {
                const query = (heroVinInput && heroVinInput.value) ? heroVinInput.value.trim() : '';
                if (!query) {
                    window.alert('Introdu cod OEM, VIN sau denumire piesă.');
                    return;
                }
                if (isHomeVitrinaPage()) {
                    redirectHomeFiltersToCatalog({ q: query });
                    return;
                }
                if (typeof window.__applyProductFilters === 'function') {
                    window.__applyProductFilters();
                }
            });
        }

        if (catToggle && catPopup && catOverlay) {
            if (catToggle.dataset.popupBound !== '1') {
                catToggle.dataset.popupBound = '1';
                catToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const willOpen = !catPopup.classList.contains('open');
                    if (willOpen && !catPopup.querySelector('.cat-popup-item, .category-card')) {
                        catPopup.innerHTML = '<div class="cat-popup-item cat-popup-loading" style="cursor:default;opacity:.75">Se încarcă categoriile…</div>';
                        loadDynamicCategories();
                    }
                    catPopup.classList.toggle('open');
                    catOverlay.classList.toggle('open');
                });
            }
            if (catOverlay.dataset.popupBound !== '1') {
                catOverlay.dataset.popupBound = '1';
                catOverlay.addEventListener('click', () => {
                    catPopup.classList.remove('open');
                    catOverlay.classList.remove('open');
                });
            }
        }
    });

})();
