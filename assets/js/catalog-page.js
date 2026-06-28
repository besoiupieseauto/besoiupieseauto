/* Catalog filters + grid */
(function () {
'use strict';
    const BESOIU_ADMIN = window.BESOIU_ADMIN_CTX === true;
    const productGrid = document.getElementById('_product-grid') || document.getElementById('product-grid');
    if (!productGrid) return;

    var catalogRemoteBusy = false;
    var catalogSearchDebounce = null;
    var stockSearchTimer = null;

    const catalogNotices = document.getElementById('catalog-notices');
    if (productGrid.dataset.catalogTruncated === '1' && catalogNotices) {
        var total = parseInt(productGrid.dataset.catalogTotal || '0', 10);
        var loaded = parseInt(productGrid.dataset.catalogLoaded || '0', 10);
        if (total > loaded) {
            catalogNotices.innerHTML = '<div class="catalog-notice">Afisam <strong>' + loaded + '</strong> din <strong>' + total + '</strong> produse. Foloseste filtrele sau cautarea pentru rezultate mai precise.</div>';
        }
    }

    let allProducts = Array.from(productGrid.querySelectorAll('._product-card'));

    function productCardSlot(card) {
        return card.closest('.product-col') || card;
    }

    function refreshProductCards() {
        allProducts = Array.from(productGrid.querySelectorAll('._product-card'));
    }
    const sortSelect = document.getElementById('orderby');
    const countSelect = document.getElementById('count');
    const countBottomSelect = document.getElementById('count-bottom');
    const vinSearch = document.getElementById('vin-search');
    const categorySearch = document.getElementById('category-search');
    const priceMinInput = document.getElementById('priceMin');
    const priceMaxInput = document.getElementById('priceMax');
    const priceSliderFill = document.getElementById('priceSliderFill');
    const filterPriceRange = document.getElementById('filter-price-range');
    const categoryList = document.getElementById('category-list');
    const subcategoryList = document.getElementById('subcategory-list');
    const marcaList = document.getElementById('marca-list');
    const brandList = document.getElementById('brand-list');
    const emptyState = document.getElementById('empty-state');
    const resetFiltersBtn = document.getElementById('resetFilters');
    const applyFiltersBtn = document.getElementById('applyFilters');
    const paginationInfo = document.getElementById('pagination-info');
    const catalogNoticesEl = document.getElementById('catalog-notices');

    const openMobileFilter = document.getElementById('openMobileFilter');
    const closeMobileFilter = document.getElementById('closeMobileFilter');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const applyMobileFilters = document.getElementById('applyMobileFilters');
    const resetMobileFilters = document.getElementById('resetMobileFilters');

    const mVinSearch = document.getElementById('m-vin-search');
    const mCategorySearch = document.getElementById('m-category-search');
    const mPriceMinInput = document.getElementById('m-priceMin');
    const mPriceMaxInput = document.getElementById('m-priceMax');
    const mPriceSliderFill = document.getElementById('m-priceSliderFill');
    const mFilterPriceRange = document.getElementById('m-filter-price-range');
    const mCategoryList = document.getElementById('m-category-list');
    const mSubcategoryList = document.getElementById('m-subcategory-list');
    const mMarcaList = document.getElementById('m-marca-list');
    const mBrandList = document.getElementById('m-brand-list');

    let categoryFilters = [];
    let subcategoryFilters = [];
    let marcaFilters = [];
    let brandFilters = [];
    let activeCategory = '';
    let activeSubcategory = '';
    let activeMarca = '';
    let activeBrand = '';
    let currentCount = parseInt(countSelect.value, 10);

    function buildMagazinCardPayload(product) {
        var name = product.name || 'Piesă auto';
        var oem = product.code || product.tecdoc_article || product.oem || 'N/A';
        var brand = product.brand || product.tecdoc_brand || '';
        var image = product.image || 'assets/images/products/1.jpg';
        var priceLabel = product.price_label || (Number(product.price_numeric || 0) > 0 ? Number(product.price_numeric).toFixed(2) + ' RON' : 'La cerere');
        var productId = product.randomn_id || product.id || '';
        var rawDesc = product.note_plain || product.note || product.description || '';
        var cardDescription = window.besoiuStripHtml
            ? window.besoiuStripHtml(rawDesc)
            : String(rawDesc || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (!cardDescription) {
            cardDescription = brand ? brand + ' - ' + name : name;
        }
        var apiSpecs = window.besoiuParseNoteSpecs ? window.besoiuParseNoteSpecs(cardDescription) : [];
        var cardSpecs = window.besoiuFilterCardSpecsPreview
            ? window.besoiuFilterCardSpecsPreview(apiSpecs.length ? apiSpecs : [
                { label: 'Brand', value: brand || '—' },
                { label: 'Cod', value: oem }
            ], 4)
            : apiSpecs.slice(0, 4);

        return {
            name: name,
            oem: oem,
            brand: brand,
            image: image,
            priceLabel: priceLabel,
            productId: productId,
            price: String(Number(product.price_numeric || product.price || 0)),
            description: cardDescription,
            specs: cardSpecs,
            badge: String(product.badge || product.pBadge || '').trim(),
            deliveryTime: String(product.delivery_time || product.deliveryTime || '24'),
            category: product.category || product.subcategory || '',
        };
    }

    function createMagazinCard(product) {
        var payload = buildMagazinCardPayload(product);
        var card = document.createElement('article');
        card.className = '_product-card magazin-card';
        card.dataset.cardType = 'magazin';
        card.dataset.productId = payload.productId;
        card.dataset.name = payload.name;
        card.dataset.oem = payload.oem;
        card.dataset.category = payload.category;
        card.dataset.brand = payload.brand;
        card.dataset.price = payload.price;
        card.dataset.image = payload.image;
        card.dataset.desc = payload.description;
        if (payload.badge) {
            card.dataset.badge = payload.badge;
        }
        if (payload.specs.length) {
            card.dataset.specs = JSON.stringify(payload.specs);
        }
        card.innerHTML = window.besoiuRenderMagazinCardHtml
            ? window.besoiuRenderMagazinCardHtml(payload)
            : '';
        return card;
    }

    function clearCatalogNotices() {
        if (!catalogNoticesEl) return;
        catalogNoticesEl.innerHTML = '';
    }

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

    function addCatalogNotice(html, type) {
        if (!catalogNoticesEl || !html) return;
        const publicText = storefrontPublicNoticeText(html);
        if (!BESOIU_ADMIN && publicText === '') {
            return;
        }
        const div = document.createElement('div');
        div.className = 'catalog-notice catalog-notice--' + (type || 'info');
        div.innerHTML = BESOIU_ADMIN ? html : escapeHtml(publicText);
        catalogNoticesEl.appendChild(div);
    }

    function buildFilterSummaryFromUrl(params) {
        const parts = [];
        if (params.get('from') === 'home') {
            parts.push('Căutare din <strong>filtrele de pe homepage</strong> (redirecționare către catalog)');
        }
        const q = params.get('q');
        if (q) parts.push('Cod/VIN: <strong>' + escapeHtml(q) + '</strong>');
        const cat = params.get('category');
        if (cat) parts.push('Categorie: <strong>' + escapeHtml(cat) + '</strong>');
        const sub = params.get('subcategory');
        if (sub) parts.push('Subcategorie: <strong>' + escapeHtml(sub) + '</strong>');
        const marca = params.get('marca');
        if (marca) parts.push('Marcă: <strong>' + escapeHtml(marca) + '</strong>');
        if (params.get('car_id')) {
            parts.push('Motorizare selectată (căutare compatibilitate vehicul)');
        }
        return parts.join(' · ');
    }

    function appendTecdocNoticeFromResponse(data) {
        if (!data || !data.notice) return;
        addCatalogNotice(escapeHtml(String(data.notice)), 'warn');
    }

    function besoiuStorefrontQuotaNotice() {
        if (typeof window.besoiuStorefrontQuotaNotice === 'function') {
            return window.besoiuStorefrontQuotaNotice();
        }
        return BESOIU_ADMIN
            ? 'Catalogul TecDoc (RapidAPI) este limitat. Căutarea continuă în stocul local.'
            : 'Căutarea continuă în stocul local al magazinului.';
    }

    async function appendTecdocStatusNotice() {
        if (window.__besoiuCatalogTecdocStatusFetched) {
            return;
        }
        window.__besoiuCatalogTecdocStatusFetched = true;
        try {
            const response = await fetch('/tecdoc_proxy.php?action=status');
            const data = await response.json();
            if (!data || data.success !== true) return;
            if (!data.api_unavailable && !data.rate_limit_only) return;
            const msg = data.notice
                ? String(data.notice)
                : (BESOIU_ADMIN
                    ? ((data.last_error && data.last_error.message)
                        ? String(data.last_error.message)
                        : besoiuStorefrontQuotaNotice())
                    : besoiuStorefrontQuotaNotice());
            if (msg) {
                addCatalogNotice(escapeHtml(msg), 'warn');
            }
        } catch (_) { /* ignore */ }
    }

    async function initCatalogNotices() {
        clearCatalogNotices();
        appendCatalogPreloadNotice();
        await appendTecdocStatusNotice();
    }

    function appendCatalogPreloadNotice() {
        if (!productGrid || productGrid.dataset.catalogTruncated !== '1') {
            return;
        }
        var total = parseInt(productGrid.dataset.catalogTotal || '0', 10);
        var loaded = parseInt(productGrid.dataset.catalogLoaded || '0', 10);
        if (!total || !loaded || total <= loaded) {
            return;
        }
        addCatalogNotice(
            'Catalogul conține <strong>' + total.toLocaleString('ro-RO') + '</strong> produse. '
            + 'Pe această pagină sunt preîncărcate <strong>' + loaded.toLocaleString('ro-RO') + '</strong> (cele mai recente). '
            + 'Folosește <strong>căutarea după cod OEM / VIN</strong> sau filtrele din stânga pentru a găsi orice piesă din stoc.',
            'info'
        );
    }

    let minCatalogPrice = 0;
    let maxCatalogPrice = 6000;

    function getDeliveryTime(card) {
        const el = card.querySelector('._product-time');
        if (!el) return 24;
        const match = el.textContent.match(/(\d+)/);
        return match ? parseInt(match[1], 10) : 24;
    }

    function readSliderValue(input, fallback) {
        if (!input) return fallback;
        var parsed = parseInt(input.value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatPriceRange(minValue, maxValue) {
        return Math.min(minValue, maxValue) + ' RON - ' + Math.max(minValue, maxValue) + ' RON';
    }

    function updateSliderFill(minInput, maxInput, fillEl) {
        if (!minInput || !maxInput || !fillEl) return;
        var minBound = parseInt(minInput.min, 10) || 0;
        var maxBound = parseInt(minInput.max, 10) || 0;
        var minValue = readSliderValue(minInput, minBound);
        var maxValue = readSliderValue(maxInput, maxBound);
        var range = maxBound - minBound || 1;
        var left = ((minValue - minBound) / range) * 100;
        var width = ((maxValue - minValue) / range) * 100;
        fillEl.style.left = left + '%';
        fillEl.style.width = Math.max(width, 0) + '%';
    }

    function setSliderBounds(minInput, maxInput, minPrice, maxPrice, minValue, maxValue) {
        if (!minInput || !maxInput) return;
        minInput.min = String(minPrice);
        minInput.max = String(maxPrice);
        maxInput.min = String(minPrice);
        maxInput.max = String(maxPrice);
        minInput.value = String(minValue);
        maxInput.value = String(maxValue);
    }

    function updatePriceRangeLabel(minInput, maxInput, labelEl) {
        if (!labelEl || !minInput || !maxInput) return;
        labelEl.textContent = formatPriceRange(
            readSliderValue(minInput, minCatalogPrice),
            readSliderValue(maxInput, maxCatalogPrice)
        );
    }

    function handlePriceSliderChange(changedInput, minInput, maxInput, fillEl, labelEl, syncPair) {
        var minVal = readSliderValue(minInput, minCatalogPrice);
        var maxVal = readSliderValue(maxInput, maxCatalogPrice);

        if (minVal > maxVal) {
            if (changedInput === minInput) {
                maxInput.value = String(minVal);
                maxVal = minVal;
            } else {
                minInput.value = String(maxVal);
                minVal = maxVal;
            }
        }

        updateSliderFill(minInput, maxInput, fillEl);
        updatePriceRangeLabel(minInput, maxInput, labelEl);

        if (syncPair) {
            setSliderBounds(
                syncPair.minInput,
                syncPair.maxInput,
                minCatalogPrice,
                maxCatalogPrice,
                minVal,
                maxVal
            );
            updateSliderFill(syncPair.minInput, syncPair.maxInput, syncPair.fillEl);
            updatePriceRangeLabel(syncPair.minInput, syncPair.maxInput, syncPair.labelEl);
        }

        renderProducts();
    }

    function setupPriceSlider(minInput, maxInput, fillEl, labelEl, syncPair) {
        if (!minInput || !maxInput) return;

        minInput.addEventListener('input', function() {
            handlePriceSliderChange(minInput, minInput, maxInput, fillEl, labelEl, syncPair);
        });

        maxInput.addEventListener('input', function() {
            handlePriceSliderChange(maxInput, minInput, maxInput, fillEl, labelEl, syncPair);
        });
    }

    function resetPriceSliders() {
        setSliderBounds(priceMinInput, priceMaxInput, minCatalogPrice, maxCatalogPrice, minCatalogPrice, maxCatalogPrice);
        setSliderBounds(mPriceMinInput, mPriceMaxInput, minCatalogPrice, maxCatalogPrice, minCatalogPrice, maxCatalogPrice);
        updateSliderFill(priceMinInput, priceMaxInput, priceSliderFill);
        updateSliderFill(mPriceMinInput, mPriceMaxInput, mPriceSliderFill);
        updatePriceRangeLabel(priceMinInput, priceMaxInput, filterPriceRange);
        updatePriceRangeLabel(mPriceMinInput, mPriceMaxInput, mFilterPriceRange);
    }

    function openFilterPopup() {
        if (window.innerWidth > 991) return;
        mobileSidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.classList.add('mobile-filter-open');
        syncDesktopToMobile();
    }

    function closeFilterPopup() {
        mobileSidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.classList.remove('mobile-filter-open');
    }

    function syncDesktopToMobile() {
        mVinSearch.value = vinSearch.value;
        mCategorySearch.value = categorySearch.value;
        setSliderBounds(
            mPriceMinInput,
            mPriceMaxInput,
            minCatalogPrice,
            maxCatalogPrice,
            readSliderValue(priceMinInput, minCatalogPrice),
            readSliderValue(priceMaxInput, maxCatalogPrice)
        );
        updateSliderFill(mPriceMinInput, mPriceMaxInput, mPriceSliderFill);
        updatePriceRangeLabel(mPriceMinInput, mPriceMaxInput, mFilterPriceRange);
    }

    function syncMobileToDesktop() {
        vinSearch.value = mVinSearch.value;
        categorySearch.value = mCategorySearch.value;
        setSliderBounds(
            priceMinInput,
            priceMaxInput,
            minCatalogPrice,
            maxCatalogPrice,
            readSliderValue(mPriceMinInput, minCatalogPrice),
            readSliderValue(mPriceMaxInput, maxCatalogPrice)
        );
        updateSliderFill(priceMinInput, priceMaxInput, priceSliderFill);
        updatePriceRangeLabel(priceMinInput, priceMaxInput, filterPriceRange);
    }

    function normalize(text) {
        return (text || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    function normalizeVin(text) {
        return String(text || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function isVinQuery(text) {
        var vin = normalizeVin(text);
        return vin.length === 17 && !/[IOQ]/.test(vin) && /^[A-HJ-NPR-Z0-9]{17}$/.test(vin);
    }

    async function renderRemoteStockSearch(query, mode) {
        var rawQuery = String(query || '').trim();
        if (!rawQuery || catalogRemoteBusy) return;

        catalogRemoteBusy = true;
        await initCatalogNotices();

        var loadingLabel = mode === 'vin'
            ? 'Se decodează VIN și se caută piese compatibile...'
            : 'Se caută cod OEM / piesă în stoc...';
        productGrid.innerHTML = '<div class="catalog-vin-loading" style="grid-column:1/-1;padding:24px;text-align:center;color:#0f766e;">' + escapeHtml(loadingLabel) + '</div>';
        emptyState.style.display = 'none';
        if (paginationInfo) paginationInfo.textContent = 'Căutare...';

        try {
            var params = new URLSearchParams({ action: mode === 'oem' ? 'search_oem' : 'search_stock' });
            if (mode === 'vin') {
                params.set('vin', normalizeVin(rawQuery));
            } else if (mode === 'oem') {
                params.set('code', rawQuery);
            } else {
                params.set('oem', rawQuery);
                params.set('name', rawQuery);
            }
            var response = await fetch('/tecdoc_proxy.php?' + params.toString());
            var data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Căutarea a eșuat.');
            }

            productGrid.innerHTML = '';
            appendTecdocNoticeFromResponse(data);
            applyStockBrandsFromResponse(data, false);

            if (data.vehicle && data.vehicle.label) {
                var vehicleBanner = document.createElement('div');
                vehicleBanner.className = '_product-vehicle-banner';
                vehicleBanner.style.gridColumn = '1 / -1';
                vehicleBanner.innerHTML = '<strong>Vehicul identificat:</strong> ' + escapeHtml(data.vehicle.label)
                    + (data.vin ? ' · VIN ' + escapeHtml(data.vin) : '');
                productGrid.appendChild(vehicleBanner);
            }

            var products = Array.isArray(data.products) ? data.products : [];
            products.forEach(function(product) {
                productGrid.appendChild(createMagazinCard(product));
            });

            if (products.length === 0) {
                emptyState.style.display = 'block';
                emptyState.textContent = data.notice || (mode === 'vin'
                    ? 'Nu am găsit piese în stoc pentru acest VIN.'
                    : 'Nu am găsit piese în stoc pentru acest cod OEM.');
            } else {
                emptyState.style.display = 'none';
            }

            if (paginationInfo) {
                paginationInfo.textContent = products.length + ' produse (căutare ' + (mode === 'vin' ? 'VIN' : 'OEM') + ')';
            }
            refreshProductCards();
            if (typeof window.besoiuBindCartButtons === 'function') {
                window.besoiuBindCartButtons(productGrid);
            }
        } catch (error) {
            productGrid.innerHTML = '';
            emptyState.style.display = 'block';
            emptyState.textContent = error.message || 'Nu am putut finaliza căutarea.';
            if (paginationInfo) paginationInfo.textContent = '0 produse';
        } finally {
            catalogRemoteBusy = false;
        }
    }

    async function renderVinRemoteSearch(vin) {
        return renderRemoteStockSearch(vin, 'vin');
    }

    async function renderOemRemoteSearch(query) {
        return renderRemoteStockSearch(query, 'oem');
    }

    function syncPriceRangeFromProducts() {
        const prices = allProducts
            .map(function(p) { return parseFloat(p.dataset.price || '0'); })
            .filter(function(p) { return Number.isFinite(p) && p > 0; });

        if (!prices.length) return;

        minCatalogPrice = Math.floor(Math.min.apply(null, prices) / 10) * 10;
        maxCatalogPrice = Math.ceil(Math.max.apply(null, prices) / 10) * 10;
        if (maxCatalogPrice <= minCatalogPrice) {
            maxCatalogPrice = minCatalogPrice + 10;
        }
        resetPriceSliders();
    }

    var CAT_ICONS = {
        'motor':'05_motor','frane':'01_frane','filtre':'02_filtre','ulei':'03_ulei_lichide',
        'suspensie':'04_suspensie','electric':'06_electric','caroserie':'07_caroserie',
        'transmisie':'08_transmisie','bmw':'17_marca_auto','piston':'05_motor',
        'second hand':'22_cutie_produse','piese auto':'22_cutie_produse'
    };
    function getCatIconClass(label, slug, iconPath) {
        if (window.SiteIcons) {
            return SiteIcons.resolve(iconPath || label, slug || label);
        }
        return 'fa-solid fa-box-open';
    }
    function renderCatIcon(label, slug, iconPath) {
        return '<span class="site-icon site-icon--sm"><i class="' + getCatIconClass(label, slug, iconPath) + '"></i></span>';
    }

    function buildSimpleHtml(items, className, dataKey) {
        if (!items || !items.length) {
            return '<li><span style="color:var(--muted);font-size:13px;">Nu există opțiuni.</span></li>';
        }
        return items.map(function(item) {
            return '<li><a href="#" class="' + className + '" data-' + dataKey + '="' + escapeHtml(item.label) + '">' + escapeHtml(item.label) + ' <span class="products-count">(' + item.count + ')</span></a></li>';
        }).join('');
    }

    function subcategoryListHeader(activeValue) {
        var toateActive = activeValue === '' ? ' active' : '';
        return '<li><a href="#" class="subcategory-filter' + toateActive + '" data-subcategory=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-folder-tree"></i></span>Toate</a></li>';
    }

    function refreshBrandLists(items, activeValue, hint) {
        var html = '<li><a href="#" class="brand-filter' + (activeValue === '' ? ' active' : '') + '" data-brand=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-industry"></i></span>Toate</a></li>';
        if (hint) {
            html += '<li><span style="color:var(--muted);font-size:13px;">' + escapeHtml(hint) + '</span></li>';
        } else if (items && items.length) {
            html += items.map(function(item) {
                var label = typeof item === 'string' ? item : (item.label || '');
                return '<li><a href="#" class="brand-filter' + (activeValue === label ? ' active' : '') + '" data-brand="' + escapeHtml(label) + '">' + escapeHtml(label) + '</a></li>';
            }).join('');
        }
        if (brandList) brandList.innerHTML = html;
        if (mBrandList) mBrandList.innerHTML = html;
        setActiveFacet('.brand-filter', activeValue || '', 'brand');
    }

    function applyStockBrandsFromResponse(data, keepActive) {
        var brands = Array.isArray(data && data.stock_brands) ? data.stock_brands : [];
        if (!keepActive) {
            activeBrand = '';
        } else if (activeBrand) {
            var wanted = normalize(activeBrand);
            var exists = brands.some(function(item) {
                return normalize(String(item || '')) === wanted;
            });
            if (!exists) {
                activeBrand = '';
            }
        }
        refreshBrandLists(brands, activeBrand, brands.length ? '' : 'Nu există branduri în stoc pentru această selecție.');
    }

    function refreshSubcategoryLists(items, activeValue, hint) {
        var html = subcategoryListHeader(activeValue || '');
        if (hint) {
            html += '<li><span style="color:var(--muted);font-size:13px;">' + escapeHtml(hint) + '</span></li>';
        } else if (items && items.length) {
            html += buildSimpleHtml(items, 'subcategory-filter', 'subcategory');
        } else if (activeCategory) {
            html += '<li><span style="color:var(--muted);font-size:13px;">Nu există subcategorii în stoc.</span></li>';
        }
        subcategoryList.innerHTML = html;
        mSubcategoryList.innerHTML = html;
        setActiveFacet('.subcategory-filter', activeValue || '', 'subcategory');
    }

    function loadSubcategoriesForCategory(categoryLabel, keepActive) {
        if (!categoryLabel) {
            activeSubcategory = '';
            refreshSubcategoryLists([], '', 'Selectează o categorie pentru subcategorii.');
            return Promise.resolve();
        }

        return fetch('/api_categorii.php?action=subcategorii&category=' + encodeURIComponent(categoryLabel))
            .then(function(res) { return res.json(); })
            .then(function(json) {
                var items = (json.success && Array.isArray(json.subcategorii)) ? json.subcategorii : [];
                if (!keepActive) {
                    activeSubcategory = '';
                } else if (activeSubcategory) {
                    var wanted = normalize(activeSubcategory);
                    var exists = items.some(function(item) {
                        return normalize(item.label) === wanted;
                    });
                    if (!exists) {
                        activeSubcategory = '';
                    }
                }
                refreshSubcategoryLists(items, activeSubcategory, '');
            })
            .catch(function() {
                activeSubcategory = keepActive ? activeSubcategory : '';
                refreshSubcategoryLists([], activeSubcategory, 'Nu am putut încărca subcategoriile.');
            });
    }

    function productSearchText(product) {
        return normalize([
            product.dataset.name,
            product.dataset.desc,
            product.dataset.oem,
            product.dataset.marca,
            product.dataset.category,
            product.dataset.subcategory
        ].join(' '));
    }

    function matchesMarcaFilter(product, marcaFilter) {
        if (!marcaFilter) return true;
        var wanted = normalize(marcaFilter);
        var marcaField = normalize(product.dataset.marca || '');
        if (marcaField && (marcaField === wanted || marcaField.includes(wanted))) {
            return true;
        }
        return productSearchText(product).includes(wanted);
    }

    function matchesBrandFilter(product, brandFilter) {
        if (!brandFilter) return true;
        var wanted = normalize(brandFilter);
        var brandField = normalize(product.dataset.brand || '');
        return brandField === wanted || brandField.includes(wanted) || wanted.includes(brandField);
    }

    function matchesSubcategoryFilter(product, subcategoryFilter) {
        if (!subcategoryFilter) return true;
        var wanted = normalize(subcategoryFilter);
        var subcategoryField = normalize(product.dataset.subcategory || '');
        return subcategoryField === wanted || subcategoryField.includes(wanted);
    }

    function updateEmptyStateMessage(filteredCount) {
        if (!emptyState) return;
        var parts = [];
        if (activeCategory) parts.push('categoria „' + activeCategory + '”');
        if (activeSubcategory) parts.push('subcategoria „' + activeSubcategory + '”');
        if (activeMarca) parts.push('marca „' + activeMarca + '”');
        if (activeBrand) parts.push('brandul „' + activeBrand + '”');
        if (vinSearch.value.trim()) parts.push('căutarea „' + vinSearch.value.trim() + '”');

        if (filteredCount === 0 && parts.length) {
            emptyState.innerHTML = '<i class="fa-solid fa-box-open"></i>Nu au fost găsite produse pentru ' + parts.join(', ') + '. Încearcă alte filtre sau resetează selecția.';
        } else {
            emptyState.innerHTML = '<i class="fa-solid fa-box-open"></i>Nu au fost găsite produse pentru filtrele selectate.';
        }
    }

    var facetListsBound = false;
    function bindFacetListsOnce() {
        if (facetListsBound) return;
        facetListsBound = true;

        function handleFacetClick(e) {
            var categoryLink = e.target.closest('.category-filter');
            if (categoryLink) {
                e.preventDefault();
                activeCategory = categoryLink.dataset.category || '';
                activeSubcategory = '';
                setActiveFacet('.category-filter', activeCategory, 'category');
                setActiveFacet('.subcategory-filter', '', 'subcategory');
                loadSubcategoriesForCategory(activeCategory).then(renderProducts);
                return;
            }

            var subcategoryLink = e.target.closest('.subcategory-filter');
            if (subcategoryLink) {
                e.preventDefault();
                activeSubcategory = subcategoryLink.dataset.subcategory || '';
                setActiveFacet('.subcategory-filter', activeSubcategory, 'subcategory');
                renderProducts();
                return;
            }

            var marcaLink = e.target.closest('.marca-filter');
            if (marcaLink) {
                e.preventDefault();
                activeMarca = marcaLink.dataset.marca || '';
                setActiveFacet('.marca-filter', activeMarca, 'marca');
                renderProducts();
                return;
            }

            var brandLink = e.target.closest('.brand-filter');
            if (brandLink) {
                e.preventDefault();
                activeBrand = brandLink.dataset.brand || '';
                setActiveFacet('.brand-filter', activeBrand, 'brand');
                renderProducts();
            }
        }

        categoryList.addEventListener('click', handleFacetClick);
        subcategoryList.addEventListener('click', handleFacetClick);
        marcaList.addEventListener('click', handleFacetClick);
        if (brandList) brandList.addEventListener('click', handleFacetClick);
        mCategoryList.addEventListener('click', handleFacetClick);
        mSubcategoryList.addEventListener('click', handleFacetClick);
        mMarcaList.addEventListener('click', handleFacetClick);
        if (mBrandList) mBrandList.addEventListener('click', handleFacetClick);
    }

    function rebuildFacetLists() {
        return fetch('/api_categorii.php?action=facets')
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (!json.success) return;

                function buildCategoryHtml(items) {
                    if (!items || !items.length) {
                        return '<li><span style="color:var(--muted);font-size:13px;">Nu există categorii în stoc.</span></li>';
                    }
                    return items.map(function(cat) {
                        return '<li data-cat="' + escapeHtml(cat.label) + '"><a href="#" class="category-filter" data-category="' + escapeHtml(cat.label) + '">' + renderCatIcon(cat.label, cat.slug, cat.icon) + escapeHtml(cat.label) + ' <span class="products-count">(' + cat.count + ')</span></a></li>';
                    }).join('');
                }

                var categoryHtml = buildCategoryHtml(json.categories || []);
                categoryList.innerHTML = '<li><a href="#" class="category-filter active" data-category=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-box-open"></i></span>Toate</a></li>' + categoryHtml;
                mCategoryList.innerHTML = categoryList.innerHTML;

                refreshSubcategoryLists([], '', 'Selectează o categorie pentru subcategorii.');

                var marcaHtml = buildSimpleHtml(json.marci || [], 'marca-filter', 'marca');
                marcaList.innerHTML = '<li><a href="#" class="marca-filter active" data-marca=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-car"></i></span>Toate</a></li>' + marcaHtml;
                mMarcaList.innerHTML = marcaList.innerHTML;

                refreshBrandLists(json.brands || [], activeBrand, '');

                categoryFilters = Array.from(document.querySelectorAll('.category-filter'));
                subcategoryFilters = Array.from(document.querySelectorAll('.subcategory-filter'));
                marcaFilters = Array.from(document.querySelectorAll('.marca-filter'));
                brandFilters = Array.from(document.querySelectorAll('.brand-filter'));
                bindFacetListsOnce();
            })
            .catch(function() {
                rebuildCategoryListFallback();
                return null;
            });
    }

    function rebuildCategoryListFallback() {
        var categories = new Map();
        allProducts.forEach(function(product) {
            var rawLabel = (product.dataset.category || '').trim();
            var label = rawLabel && rawLabel.length <= 60 ? rawLabel : 'Piese auto';
            var key = normalize(label);
            var current = categories.get(key) || { label: label, count: 0 };
            current.count += 1;
            categories.set(key, current);
        });

        var sortedCategories = Array.from(categories.values())
            .sort(function(a, b) { return a.label.localeCompare(b.label, 'ro'); });

        var html = sortedCategories.length
            ? sortedCategories.map(function(cat) {
                return '<li data-cat="' + escapeHtml(cat.label) + '"><a href="#" class="category-filter" data-category="' + escapeHtml(cat.label) + '">' + renderCatIcon(cat.label, cat.label, '') + escapeHtml(cat.label) + ' <span class="products-count">(' + cat.count + ')</span></a></li>';
            }).join('')
            : '<li><span style="color:var(--muted);font-size:13px;">Nu există categorii.</span></li>';

        categoryList.innerHTML = '<li><a href="#" class="category-filter active" data-category=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-box-open"></i></span>Toate</a></li>' + html;
        mCategoryList.innerHTML = categoryList.innerHTML;
        categoryFilters = Array.from(document.querySelectorAll('.category-filter'));
        refreshSubcategoryLists([], '', 'Selectează o categorie pentru subcategorii.');
        bindFacetListsOnce();
    }

    function rebuildCategoryList() {
        return rebuildFacetLists();
    }

    function setActiveFacet(selector, value, attr) {
        document.querySelectorAll(selector).forEach(function(item) {
            item.classList.remove('active');
            if ((item.dataset[attr] || '') === value) {
                item.classList.add('active');
            }
        });
    }

    function scheduleCatalogFilterRender() {
        clearTimeout(catalogSearchDebounce);
        catalogSearchDebounce = setTimeout(renderProducts, 400);
    }

    function renderProducts() {
        var rawQuery = (vinSearch.value || '').trim();
        if (isVinQuery(rawQuery)) {
            renderVinRemoteSearch(rawQuery);
            return;
        }
        if (rawQuery.length >= 2) {
            clearTimeout(stockSearchTimer);
            stockSearchTimer = setTimeout(function() {
                renderOemRemoteSearch(rawQuery);
            }, 350);
            return;
        }

        var query = normalize(vinSearch.value);
        var rawMinPrice = readSliderValue(priceMinInput, minCatalogPrice);
        var rawMaxPrice = readSliderValue(priceMaxInput, maxCatalogPrice);
        var minPrice = rawMinPrice;
        var maxPrice = rawMaxPrice;
        var sortValue = sortSelect.value;

        var filtered = allProducts.filter(function(product) {
            var name = normalize(product.dataset.name);
            var category = normalize(product.dataset.category);
            var subcategory = normalize(product.dataset.subcategory || '');
            var marca = normalize(product.dataset.marca || '');
            var price = parseFloat(product.dataset.price || '0');
            var oem = normalize(product.dataset.oem || '');

            var matchesSearch = !query || name.includes(query) || oem.includes(query) || productSearchText(product).includes(query);
            var matchesCategory = !activeCategory || category === normalize(activeCategory);
            var matchesSubcategory = matchesSubcategoryFilter(product, activeSubcategory);
            var matchesMarca = matchesMarcaFilter(product, activeMarca);
            var matchesBrand = matchesBrandFilter(product, activeBrand);
            var matchesPrice = price >= Math.min(minPrice, maxPrice) && price <= Math.max(minPrice, maxPrice);

            return matchesSearch && matchesCategory && matchesSubcategory && matchesMarca && matchesBrand && matchesPrice;
        });

        filtered.sort(function(a, b) {
            var aName = (a.dataset.name || '').toLowerCase();
            var bName = (b.dataset.name || '').toLowerCase();
            var aPrice = parseFloat(a.dataset.price || '0') || 0;
            var bPrice = parseFloat(b.dataset.price || '0') || 0;
            var aTime = getDeliveryTime(a);
            var bTime = getDeliveryTime(b);

            switch (sortValue) {
                case 'price-asc': return aPrice - bPrice;
                case 'price-desc': return bPrice - aPrice;
                case 'name-asc': return aName.localeCompare(bName, 'ro');
                case 'name-desc': return bName.localeCompare(aName, 'ro');
                case 'time-asc': return aTime - bTime;
                default: return 0;
            }
        });

        allProducts.forEach(function(product) {
            var slot = productCardSlot(product);
            slot.classList.add('hidden-by-filter');
            slot.style.display = 'none';
        });

        filtered.forEach(function(product, index) {
            var slot = productCardSlot(product);
            if (index < currentCount) {
                slot.classList.remove('hidden-by-filter');
                slot.style.display = '';
            }
            productGrid.appendChild(slot);
        });

        allProducts
            .filter(function(p) { return !filtered.includes(p); })
            .forEach(function(p) { productGrid.appendChild(productCardSlot(p)); });

        emptyState.style.display = filtered.length === 0 ? 'block' : 'none';
        updateEmptyStateMessage(filtered.length);

        if (paginationInfo) {
            var shown = Math.min(currentCount, filtered.length);
            paginationInfo.textContent = shown + ' din ' + filtered.length + ' produse';
        }
    }

    function syncCount(value) {
        currentCount = parseInt(value, 10);
        countSelect.value = value;
        countBottomSelect.value = value;
        renderProducts();
    }

    function resetAll() {
        vinSearch.value = '';
        categorySearch.value = '';
        mVinSearch.value = '';
        mCategorySearch.value = '';
        activeCategory = '';
        activeSubcategory = '';
        activeMarca = '';
        activeBrand = '';
        resetPriceSliders();
        sortSelect.value = 'default';
        syncCount(12);
        setActiveFacet('.category-filter', '', 'category');
        setActiveFacet('.subcategory-filter', '', 'subcategory');
        setActiveFacet('.marca-filter', '', 'marca');
        setActiveFacet('.brand-filter', '', 'brand');
        refreshSubcategoryLists([], '', 'Selectează o categorie pentru subcategorii.');
        rebuildFacetLists().then(function () {
            renderProducts();
            closeFilterPopup();
        });
        closeFilterPopup();
    }

    // Hero search bar integration
    var heroSearch = document.getElementById('hero-search');
    var heroSearchBtn = document.getElementById('hero-search-btn');
    if (heroSearch) {
        heroSearch.addEventListener('input', function() {
            vinSearch.value = heroSearch.value;
            scheduleCatalogFilterRender();
        });
        heroSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                vinSearch.value = heroSearch.value;
                renderProducts();
            }
        });
    }
    if (heroSearchBtn) {
        heroSearchBtn.addEventListener('click', function() {
            vinSearch.value = heroSearch.value;
            renderProducts();
        });
    }

    // Events
    sortSelect.addEventListener('change', renderProducts);
    countSelect.addEventListener('change', function() { syncCount(this.value); });
    countBottomSelect.addEventListener('change', function() { syncCount(this.value); });
    vinSearch.addEventListener('input', function() {
        if (heroSearch) heroSearch.value = vinSearch.value;
        scheduleCatalogFilterRender();
    });
    setupPriceSlider(priceMinInput, priceMaxInput, priceSliderFill, filterPriceRange, {
        minInput: mPriceMinInput,
        maxInput: mPriceMaxInput,
        fillEl: mPriceSliderFill,
        labelEl: mFilterPriceRange
    });
    setupPriceSlider(mPriceMinInput, mPriceMaxInput, mPriceSliderFill, mFilterPriceRange, {
        minInput: priceMinInput,
        maxInput: priceMaxInput,
        fillEl: priceSliderFill,
        labelEl: filterPriceRange
    });

    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', renderProducts);
    if (resetFiltersBtn) resetFiltersBtn.addEventListener('click', resetAll);

    categorySearch.addEventListener('input', function() {
        var q = normalize(this.value);
        Array.from(categoryList.querySelectorAll('li')).forEach(function(item) {
            item.style.display = normalize(item.textContent).includes(q) ? '' : 'none';
        });
    });

    // Mobile sidebar
    if (openMobileFilter) openMobileFilter.addEventListener('click', function(e) { e.preventDefault(); openFilterPopup(); });
    if (closeMobileFilter) closeMobileFilter.addEventListener('click', closeFilterPopup);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeFilterPopup);

    if (applyMobileFilters) {
        applyMobileFilters.addEventListener('click', function() {
            syncMobileToDesktop();
            renderProducts();
            closeFilterPopup();
        });
    }

    if (resetMobileFilters) {
        resetMobileFilters.addEventListener('click', resetAll);
    }

    if (mCategorySearch) {
        mCategorySearch.addEventListener('input', function() {
            var q = normalize(this.value);
            Array.from(mCategoryList.querySelectorAll('li')).forEach(function(item) {
                item.style.display = normalize(item.textContent).includes(q) ? '' : 'none';
            });
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 991) closeFilterPopup();
    });

    // Căutare din header (mobil + desktop)
    var headerSearchInput = document.getElementById('_home-product-name');
    var headerSearchBtn = document.getElementById('_home-search-btn');
    function applyHeaderSearch() {
        if (!headerSearchInput || !vinSearch) return;
        var term = headerSearchInput.value.trim();
        vinSearch.value = term;
        if (mVinSearch) mVinSearch.value = term;
        if (heroSearch) heroSearch.value = term;
        renderProducts();
    }
    if (headerSearchInput) {
        headerSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyHeaderSearch();
            }
        });
    }
    if (headerSearchBtn) {
        headerSearchBtn.addEventListener('click', applyHeaderSearch);
    }

    // Widget accordion
    document.querySelectorAll('.widget-title[data-target]').forEach(function(title) {
        title.addEventListener('click', function() {
            var body = document.getElementById(this.dataset.target);
            if (!body) return;
            this.classList.toggle('collapsed');
            body.classList.toggle('hidden');
        });
    });

    // Init
    syncPriceRangeFromProducts();
    resetPriceSliders();

    var initialParams = new URLSearchParams(window.location.search);
    var initialQuery = initialParams.get('q');
    var initialCategory = initialParams.get('category') || '';
    var initialSubcategory = initialParams.get('subcategory') || '';
    var initialCarId = initialParams.get('car_id') || '';
    if (initialQuery && vinSearch) {
        vinSearch.value = initialQuery;
        var heroSearchEl = document.getElementById('hero-search');
        if (heroSearchEl) heroSearchEl.value = initialQuery;
    }

    function applyInitialMarcaFromUrl() {
        var initialMarca = initialParams.get('marca') || '';
        if (!initialMarca) return;
        activeMarca = initialMarca;
        setActiveFacet('.marca-filter', activeMarca, 'marca');
    }

    async function renderVehicleStockFromParams(carId, category, subcategory) {
        if (!carId || !subcategory) {
            return false;
        }
        await initCatalogNotices();
        productGrid.innerHTML = '<div class="catalog-vin-loading" style="grid-column:1/-1;padding:24px;text-align:center;color:#0f766e;">Se încarcă produse pentru vehicul...</div>';
        emptyState.style.display = 'none';
        try {
            var params = new URLSearchParams({ action: 'get_articles', carId: carId, category: category, subcategory: subcategory });
            var response = await fetch('/tecdoc_proxy.php?' + params.toString());
            var data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Căutarea a eșuat.');
            }
            productGrid.innerHTML = '';
            if (data.notice) {
                addCatalogNotice(escapeHtml(String(data.notice)), 'warn');
            }
            applyStockBrandsFromResponse(data, false);
            var products = Array.isArray(data.products) ? data.products : [];
            products.forEach(function(product) {
                var payload = buildMagazinCardPayload(product);
                payload.category = subcategory;
                productGrid.appendChild(createMagazinCard(Object.assign({}, product, payload)));
            });
            if (products.length === 0) {
                emptyState.style.display = 'block';
                emptyState.textContent = data.notice || 'Nu am găsit piese pentru selecția vehiculului.';
            } else {
                emptyState.style.display = 'none';
            }
            if (paginationInfo) {
                paginationInfo.textContent = products.length + ' produse (vehicul)';
            }
            refreshProductCards();
            if (typeof window.besoiuBindCartButtons === 'function') {
                window.besoiuBindCartButtons(productGrid);
            }
            return true;
        } catch (err) {
            productGrid.innerHTML = '';
            emptyState.style.display = 'block';
            emptyState.textContent = err.message || 'Eroare la încărcarea produselor.';
            return false;
        }
    }

    function applyInitialCategoryFilters() {
        if (!initialCategory) {
            renderProducts();
            return;
        }

        activeCategory = initialCategory;
        activeSubcategory = initialSubcategory;
        setActiveFacet('.category-filter', activeCategory, 'category');
        loadSubcategoriesForCategory(activeCategory, true).then(function() {
            if (activeSubcategory) {
                setActiveFacet('.subcategory-filter', activeSubcategory, 'subcategory');
            }
            renderProducts();
        });
    }

    productGrid.addEventListener('click', function(e) {
        var btnDetalii = e.target.closest('.product_detal');
        if (btnDetalii) {
            e.preventDefault();
            var cardDet = btnDetalii.closest('._product-card');
            var productId = (cardDet && cardDet.dataset.productId) || btnDetalii.dataset.productId || '';
            window.location.href = productId ? '/produs?id=' + encodeURIComponent(productId) : '/produs';
            return;
        }

        var imgClick = e.target.closest('._product-card-image');
        if (imgClick) {
            var cardImg = imgClick.closest('._product-card');
            if (!cardImg) return;
            e.preventDefault();
            var pid = cardImg.dataset.productId || '';
            window.location.href = pid ? '/produs?id=' + encodeURIComponent(pid) : '/produs';
        }
    });

    rebuildCategoryList().then(function () {
        applyInitialMarcaFromUrl();
        if (initialCarId && initialSubcategory && initialCategory) {
            activeCategory = initialCategory;
            activeSubcategory = initialSubcategory;
            setActiveFacet('.category-filter', activeCategory, 'category');
            return loadSubcategoriesForCategory(activeCategory, true).then(function () {
                setActiveFacet('.subcategory-filter', activeSubcategory, 'subcategory');
                return renderVehicleStockFromParams(initialCarId, initialCategory, initialSubcategory);
            });
        }
        if (initialQuery) {
            var mode = isVinQuery(initialQuery) ? 'vin' : 'oem';
            return renderRemoteStockSearch(initialQuery, mode);
        }
        return initCatalogNotices().then(function () {
            applyInitialCategoryFilters();
        });
    });
})();
