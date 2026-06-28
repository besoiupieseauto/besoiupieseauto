(function () {
    'use strict';

    const CART_KEY = 'besoiu_cart';
    const COUPON_KEY = 'besoiu_coupon';
    const ORDERS_ENDPOINT = '/admin/api/comenzi_endpoint.php';
    const COUPON_ENDPOINT = '/api/coupon_endpoint.php';
    const CART_ENDPOINT = '/api/cart_endpoint.php';
    const PAYMENT_ENDPOINT = '/api/payment_endpoint.php';
    const DEFAULT_PRODUCT_IMAGE = 'assets/images/products/product-4.jpg';

    let shopOrderCsrfToken = '';
    let serverCartSyncTimer = null;
    let checkoutFlowStep = 1;

    function eventTargetElement(event) {
        const target = event?.target;
        if (target instanceof Element) {
            return target;
        }
        if (target && target.parentElement instanceof Element) {
            return target.parentElement;
        }
        return null;
    }

    function eventClosest(event, selector) {
        const el = eventTargetElement(event);
        return el ? el.closest(selector) : null;
    }

    function isCartPage() {
        return Boolean(document.getElementById('cart-items-list'));
    }

    function scheduleServerCartSync(items = readCart()) {
        if (!isCartPage()) {
            return;
        }

        clearTimeout(serverCartSyncTimer);
        serverCartSyncTimer = setTimeout(() => {
            syncServerCart(items).catch(() => {});
        }, 800);
    }

    async function fetchShopOrderCsrf(forceRefresh = false) {
        if (shopOrderCsrfToken && !forceRefresh) {
            return shopOrderCsrfToken;
        }

        const response = await fetch(`${ORDERS_ENDPOINT}?action=csrf_token`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.data?.token) {
            throw new Error('Nu am putut initia sesiunea de comanda. Reincarca pagina.');
        }

        shopOrderCsrfToken = String(result.data.token);
        return shopOrderCsrfToken;
    }

    const PAYMENT_METHODS = {
        ridicare_locala: [
            { value: 'card_fizic', label: 'Card fizic', hint: 'POS la ridicare din magazin', icon: 'fa-solid fa-credit-card' },
            { value: 'numerar', label: 'Numerar', hint: 'Plata cash la ridicare', icon: 'fa-solid fa-money-bill-wave' },
            { value: 'card_online', label: 'Card online', hint: 'Plata online in avans', icon: 'fa-solid fa-globe' },
        ],
        tarif_fix: [
            { value: 'ramburs', label: 'Ramburs', hint: 'Numerar la livrare', icon: 'fa-solid fa-hand-holding-dollar' },
            { value: 'card_online', label: 'Card online', hint: 'Plata securizata online', icon: 'fa-solid fa-globe' },
        ],
    };

    const PAYMENT_LABELS = {
        card_fizic: 'Card fizic',
        numerar: 'Numerar',
        card_online: 'Card online',
        ramburs: 'Ramburs',
    };

    const SHIPPING_LABELS = {
        ridicare_locala: 'Ridicare locala',
        tarif_fix: 'Curier rapid',
    };

    function storefrontClientMessage(message) {
        const raw = String(message || '').trim();
        if (raw === '') {
            return '';
        }
        if (typeof window.besoiuStorefrontPublicError === 'function') {
            return window.besoiuStorefrontPublicError(raw);
        }
        return raw;
    }

    function parsePrice(value) {
        let raw = String(value ?? '').trim();
        if (raw === '' || /la\s*cerere/i.test(raw)) {
            return 0;
        }

        raw = raw.replace(/[^\d.,]/g, '');
        if (raw === '') {
            return 0;
        }

        const hasComma = raw.includes(',');
        const hasDot = raw.includes('.');

        if (hasComma && hasDot) {
            const lastComma = raw.lastIndexOf(',');
            const lastDot = raw.lastIndexOf('.');
            raw = lastComma > lastDot
                ? raw.replace(/\./g, '').replace(',', '.')
                : raw.replace(/,/g, '');
        } else if (hasComma) {
            const parts = raw.split(',');
            raw = parts.length === 2 && parts[1].length <= 2
                ? parts[0].replace(/\./g, '') + '.' + parts[1]
                : raw.replace(/,/g, '');
        } else if (hasDot) {
            const parts = raw.split('.');
            if (!(parts.length === 2 && parts[1].length <= 2)) {
                raw = raw.replace(/\./g, '');
            }
        }

        const parsed = parseFloat(raw);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function priceFromElement(root) {
        if (!root) {
            return 0;
        }

        const datasetPrice = root.dataset?.price ?? root.getAttribute?.('data-price') ?? '';
        if (String(datasetPrice).trim() !== '') {
            const fromDataset = parsePrice(datasetPrice);
            if (fromDataset > 0) {
                return fromDataset;
            }
        }

        const newPriceNode = root.querySelector?.('._product-price-new');
        if (newPriceNode) {
            const fromNewPrice = parsePrice(newPriceNode.textContent);
            if (fromNewPrice > 0) {
                return fromNewPrice;
            }
        }

        return parsePrice(textFrom(root, '._product-price') || textFrom(root, '.new-price') || textFrom(root, '.price-box'));
    }

    function formatMoney(value) {
        return `${Number(value || 0).toFixed(2)} RON`;
    }

    function normalizeCartItem(item) {
        const quantity = Math.max(1, Number(item.quantity) || 1);
        const price = Number(item.price) || 0;

        return {
            ...item,
            quantity,
            price,
            total_amount: Number(item.total_amount) || (quantity * price),
            product_image: item.product_image || getDefaultProductImage(),
        };
    }

    function readCart() {
        try {
            const items = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            return Array.isArray(items) ? items.map(normalizeCartItem) : [];
        } catch (error) {
            return [];
        }
    }

    function writeCart(items) {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
        updateCartBadge(items);
        scheduleServerCartSync(items);
    }

    function readAppliedCoupon() {
        try {
            const coupon = JSON.parse(localStorage.getItem(COUPON_KEY) || 'null');
            return coupon && typeof coupon === 'object' ? coupon : null;
        } catch (error) {
            return null;
        }
    }

    function writeAppliedCoupon(coupon) {
        if (!coupon) {
            localStorage.removeItem(COUPON_KEY);
            return;
        }
        localStorage.setItem(COUPON_KEY, JSON.stringify(coupon));
    }

    async function syncServerCart(items = readCart()) {
        const response = await fetch(CART_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync',
                items: items.map((item) => ({
                    randomn_id: item.product_id || item.randomn_id || '',
                    quantity: Number(item.quantity) || 1,
                })),
            }),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            return null;
        }

        return result.data;
    }

    async function pullServerCart() {
        const response = await fetch(CART_ENDPOINT, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data?.items)) {
            return null;
        }

        if (result.data.items.length === 0) {
            return null;
        }

        const mapped = result.data.items.map((item) => normalizeCartItem({
            key: `${item.product_name}|${item.oem || ''}|${item.price}`,
            product_id: item.randomn_id || item.product_id,
            randomn_id: item.randomn_id || item.product_id,
            product_name: item.product_name,
            product_image: item.product_image,
            oem: item.oem || '',
            quantity: item.quantity,
            price: item.price,
            total_amount: item.total_amount,
            source: item.source || 'server',
        }));

        localStorage.setItem(CART_KEY, JSON.stringify(mapped));
        updateCartBadge(mapped);

        return mapped;
    }

    async function applyCouponCode(code) {
        const normalized = String(code || '').trim().toUpperCase();
        if (!normalized) {
            throw new Error('Introdu un cod promotional.');
        }

        const subtotal = getCartTotal(readCart());
        const response = await fetch(COUPON_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code: normalized, subtotal }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            writeAppliedCoupon(null);
            throw new Error(result.message || 'Cupon invalid.');
        }

        writeAppliedCoupon({
            code: result.data.code,
            discount: Number(result.data.discount) || 0,
            total_after: Number(result.data.total_after) || subtotal,
            subtotal,
        });

        return result.data;
    }

    async function revalidateAppliedCoupon() {
        const applied = readAppliedCoupon();
        if (!applied?.code) {
            return;
        }

        const subtotal = getCartTotal(readCart());
        if (subtotal <= 0) {
            writeAppliedCoupon(null);
            return;
        }

        try {
            await applyCouponCode(applied.code);
        } catch (error) {
            writeAppliedCoupon(null);
            const hint = document.getElementById('cart-coupon-hint');
            if (hint) {
                hint.textContent = error.message;
            }
        }
    }

    function setCheckoutFlowStep(step) {
        checkoutFlowStep = Math.max(1, Math.min(3, Number(step) || 1));
        updateCheckoutStepper(checkoutFlowStep);
        updateCheckoutHint(checkoutFlowStep);
        const successPanel = document.getElementById('checkout-success-panel');
        if (successPanel) {
            successPanel.classList.toggle('is-hidden', checkoutFlowStep !== 3);
        }
    }

    function getCartQuantity(items = readCart()) {
        return items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);
    }

    function updateCartBadge(items = readCart()) {
        const quantity = getCartQuantity(items);
        document.querySelectorAll('.cart-count, [data-cart-count]').forEach((badge) => {
            badge.textContent = String(quantity);
            badge.style.display = quantity > 0 ? 'inline-flex' : 'none';
        });
    }

    function getMiniCart() {
        let miniCart = document.getElementById('besoiu-mini-cart');
        if (!miniCart) {
            miniCart = document.createElement('div');
            miniCart.id = 'besoiu-mini-cart';
            miniCart.style.cssText = 'position:fixed;right:22px;top:78px;z-index:99999;width:min(380px,calc(100vw - 32px));display:none;background:#fff;color:#111;border:1px solid #e7e7e7;border-radius:16px;box-shadow:0 18px 45px rgba(0,0,0,.18);padding:16px;font-size:14px;';
            document.body.appendChild(miniCart);
        }

        return miniCart;
    }

    function renderMiniCart(message = 'Produs adaugat in cos', isError = false) {
        const miniCart = getMiniCart();
        const cart = readCart();
        const total = cart.reduce((sum, item) => sum + (Number(item.total_amount) || 0), 0);
        const itemsHtml = cart.length
            ? cart.slice(0, 4).map((item, index) => `
                <div style="display:flex;gap:10px;align-items:center;padding:10px 0;border-top:${index === 0 ? '0' : '1px solid #f0f0f0'};">
                    <img src="${escapeHtml(item.product_image || getDefaultProductImage())}" alt="${escapeHtml(item.product_name)}" style="width:58px;height:58px;object-fit:cover;border-radius:10px;border:1px solid #eee;background:#f7f7f7;">
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(item.product_name)}</div>
                        <div style="font-size:12px;color:#666;margin-top:3px;">${Number(item.quantity) || 1} buc. x ${formatMoney(item.price)}</div>
                    </div>
                    <button type="button" data-mini-cart-remove="${index}" title="Sterge produsul" style="width:28px;height:28px;border:0;border-radius:50%;background:#dc3545;color:#fff;font-weight:700;cursor:pointer;">x</button>
                </div>
            `).join('')
            : '<div style="padding:18px 0;color:#666;">Cosul este gol.</div>';

        miniCart.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="flex:1;">
                    <div style="font-weight:800;color:${isError ? '#b42318' : '#0f9f6e'};">${escapeHtml(message)}</div>
                    <div style="font-size:12px;color:#666;margin-top:2px;">${getCartQuantity(cart)} produse in cos</div>
                </div>
                <button type="button" data-mini-cart-close style="border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#777;">x</button>
            </div>
            <div style="margin-top:10px;max-height:310px;overflow:auto;">${itemsHtml}</div>
            <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #eee;margin-top:12px;padding-top:12px;font-weight:800;">
                <span>Total</span>
                <span>${formatMoney(total)}</span>
            </div>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <button type="button" data-mini-cart-close style="flex:1;border:1px solid #ddd;background:#fff;color:#111;border-radius:10px;padding:10px;cursor:pointer;">Continua</button>
                <a href="/cart" style="flex:1;text-align:center;background:#111;color:#fff;border-radius:10px;padding:10px;text-decoration:none;">Mergi la cos</a>
            </div>
        `;

        miniCart.querySelectorAll('[data-mini-cart-close]').forEach((button) => {
            button.addEventListener('click', () => {
                miniCart.style.display = 'none';
            });
        });

        miniCart.querySelectorAll('[data-mini-cart-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                removeCartItem(Number(button.dataset.miniCartRemove));
                renderMiniCart('Produs sters din cos');
                renderCartPage();
            });
        });

        miniCart.style.display = 'block';
        clearTimeout(miniCart._hideTimer);
        if (!isError) {
            miniCart._hideTimer = setTimeout(() => {
                miniCart.style.display = 'none';
            }, 6000);
        }
    }

    function showCartMessage(message, isError) {
        renderMiniCart(storefrontClientMessage(message), isError);
    }

    function textFrom(root, selector) {
        return (root.querySelector(selector)?.textContent || '').trim();
    }

    function getDefaultProductImage() {
        return new URL(DEFAULT_PRODUCT_IMAGE, window.location.href).href;
    }

    function normalizeImageUrl(value) {
        const cleanValue = String(value || '').trim();
        if (!cleanValue) {
            return '';
        }

        try {
            return new URL(cleanValue, window.location.href).href;
        } catch (error) {
            return cleanValue;
        }
    }

    function imageFromBackground(element) {
        if (!element) {
            return '';
        }

        const backgroundImage = window.getComputedStyle(element).backgroundImage || '';
        const match = backgroundImage.match(/url\(["']?(.+?)["']?\)/);
        return match ? normalizeImageUrl(match[1]) : '';
    }

    function imageFromRoot(root) {
        if (!root) {
            return getDefaultProductImage();
        }

        const datasetImage = root.dataset.image || root.dataset.productImage || root.dataset.img || '';
        const imageElement = root.querySelector('._product-card-image img, .product-image img, img:not(._home-user-icon):not(._home-cart-icon)');
        const backgroundElement = root.querySelector('._product-card-image, .product-image-container') || root;
        const image = normalizeImageUrl(datasetImage)
            || normalizeImageUrl(imageElement?.getAttribute('src'))
            || imageFromBackground(backgroundElement)
            || getDefaultProductImage();

        return image;
    }

    function imageFromProductPage(details) {
        const imageElement = document.querySelector('.product-single-carousel img, .product-single-image img, .product-gallery img, .product-slider-container img');
        return normalizeImageUrl(imageElement?.getAttribute('src')) || imageFromRoot(details);
    }

    function getCatalogProduct(button) {
        const source = button.closest('[data-name]') || button.closest('._product-card');
        if (!source) {
            return null;
        }

        const productName = source.dataset.name || textFrom(source, '._product-card-name');
        const oem = source.dataset.oem || textFrom(source, '._product-oem').replace(/^OEM:\s*/i, '');
        const price = priceFromElement(source);
        const quantity = 1;
        const specs = getProductSpecsFromSource(source);

        return {
            key: `${productName}|${oem}|${price}`,
            product_id: source.dataset.productId || '',
            product_name: productName,
            product_image: imageFromRoot(source),
            oem,
            vin: source.dataset.vin || '',
            quantity,
            price,
            total_amount: price * quantity,
            source: 'catalog',
            specs,
        };
    }

    function getProductPageProduct(button) {
        const details = button.closest('.product-single-details') || document;
        const quantity = Math.max(1, parseInt(details.querySelector('.horizontal-quantity')?.value || '1', 10) || 1);
        const productName = textFrom(details, '.product-title') || 'Produs site';
        const productCode = textFrom(details, '.single-info-list li strong') || '';
        const price = priceFromElement(details);
        const productId = details.dataset.productId || '';

        return {
            key: `${productName}|${productCode}|${price}`,
            product_id: productId,
            product_name: productName,
            product_image: imageFromProductPage(details),
            oem: productCode,
            vin: '',
            quantity,
            price,
            total_amount: price * quantity,
            source: 'product',
        };
    }

    function getProductFromButton(button) {
        if (button.matches('.add-cart')) {
            return getProductPageProduct(button);
        }

        return getCatalogProduct(button);
    }

    function addToLocalCart(product) {
        const cart = readCart();
        const existing = cart.find((item) => item.key === product.key);

        if (existing) {
            existing.quantity += product.quantity;
            existing.total_amount = existing.quantity * existing.price;
            existing.product_image = existing.product_image || product.product_image;
        } else {
            cart.push({
                ...product,
                created_at: new Date().toISOString(),
            });
        }

        writeCart(cart);
    }

    function removeCartItem(index) {
        const cart = readCart();
        cart.splice(index, 1);
        writeCart(cart);
        window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
    }

    function getCartTotal(cart) {
        return cart.reduce((sum, item) => sum + (Number(item.total_amount) || 0), 0);
    }

    function getSelectedShippingMethod() {
        return document.querySelector('input[name="shipping_method"]:checked')?.value || 'ridicare_locala';
    }

    function getSelectedPaymentMethod() {
        return document.querySelector('input[name="payment_method"]:checked')?.value || '';
    }

    function renderPaymentMethods(shippingMethod, preferredValue = '', gridId = 'payment-methods-grid') {
        const grid = document.getElementById(gridId);
        if (!grid) {
            return;
        }

        const methods = PAYMENT_METHODS[shippingMethod] || PAYMENT_METHODS.ridicare_locala;
        const selectedValue = methods.some((method) => method.value === preferredValue)
            ? preferredValue
            : methods[0].value;
        const isQuickBuy = gridId === 'quickbuy-payment-grid';
        const optionClass = isQuickBuy ? 'qb-payment-opt payment-opt' : 'payment-opt';
        const inputName = isQuickBuy ? 'qb_payment_method' : 'payment_method';

        grid.innerHTML = methods.map((method) => `
            <label class="${optionClass} ${method.value === selectedValue ? 'active' : ''}" data-payment="${escapeHtml(method.value)}">
                <input type="radio" name="${inputName}" value="${escapeHtml(method.value)}" ${method.value === selectedValue ? 'checked' : ''}>
                <span class="p-radio"></span>
                <span class="p-icon"><i class="${escapeHtml(method.icon)}"></i></span>
                <span class="p-copy">
                    <span class="p-label">${escapeHtml(method.label)}</span>
                    <span class="p-hint">${escapeHtml(method.hint)}</span>
                </span>
            </label>
        `).join('');

        if (gridId === 'payment-methods-grid') {
            updateCheckoutLocationNote(shippingMethod);
        } else if (isQuickBuy) {
            updateQuickBuyLocationNote(shippingMethod);
        }
    }

    function updateCheckoutLocationNote(shippingMethod) {
        const note = document.getElementById('checkout-location-note');
        if (!note) {
            return;
        }

        note.textContent = shippingMethod === 'tarif_fix'
            ? 'Comanda va fi livrata prin curier. Poti plati ramburs la livrare sau online in avans.'
            : 'Comanda se ridica din magazinul nostru din Timisoara. Poti plati cu card fizic, numerar sau online in avans.';
    }

    function bindCheckoutOptions() {
        document.addEventListener('click', (event) => {
            if (eventClosest(event, '#besoiu-quickbuy-modal')) {
                return;
            }

            const deliveryOption = eventClosest(event, '.delivery-opt');
            if (deliveryOption) {
                document.querySelectorAll('.delivery-opt').forEach((option) => option.classList.remove('active'));
                deliveryOption.classList.add('active');
                const shippingMethod = deliveryOption.querySelector('input[name="shipping_method"]')?.value || 'ridicare_locala';
                renderPaymentMethods(shippingMethod);
                if (checkoutFlowStep >= 2) {
                    setCheckoutFlowStep(2);
                }
                return;
            }

            const paymentOption = eventClosest(event, '.payment-opt');
            if (paymentOption) {
                document.querySelectorAll('.payment-opt').forEach((option) => option.classList.remove('active'));
                paymentOption.classList.add('active');
                if (checkoutFlowStep >= 2) {
                    setCheckoutFlowStep(2);
                }
            }
        });

        renderPaymentMethods(getSelectedShippingMethod());
    }

    function updateCheckoutStepper(currentStep = 1) {
        const stepper = document.getElementById('checkout-stepper');
        if (!stepper) {
            return;
        }

        const steps = stepper.querySelectorAll('.step');
        const lines = stepper.querySelectorAll('.step-line');

        steps.forEach((stepEl, index) => {
            const stepNumber = index + 1;
            stepEl.classList.remove('active', 'done');
            const circle = stepEl.querySelector('.step-circle');
            if (stepNumber < currentStep) {
                stepEl.classList.add('done');
                if (circle) {
                    circle.innerHTML = '<i class="fa-solid fa-check"></i>';
                }
            } else if (stepNumber === currentStep) {
                stepEl.classList.add('active');
                if (circle && !circle.querySelector('i')) {
                    circle.textContent = String(stepNumber);
                }
            } else if (circle) {
                circle.textContent = String(stepNumber);
            }
        });

        lines.forEach((lineEl, index) => {
            lineEl.classList.remove('active', 'done');
            if (index + 1 < currentStep) {
                lineEl.classList.add('done');
            } else if (index + 1 === currentStep) {
                lineEl.classList.add('active');
            }
        });
    }

    function updateCheckoutHint(step = 1) {
        const hint = document.getElementById('checkout-flow-hint');
        if (!hint) {
            return;
        }

        const messages = {
            1: 'Pasul 1: verifică produsele din coș, apoi continuă la finalizare.',
            2: 'Pasul 2: alege livrarea, plata și completează datele de livrare.',
            3: 'Pasul 3: comanda a fost trimisă. Mulțumim!',
        };
        hint.textContent = messages[step] || messages[1];
    }

    function isCheckoutFormReady() {
        const form = document.getElementById('cart-shipping-form');
        if (!form) {
            return false;
        }

        const required = ['client_name', 'phone', 'address', 'city', 'postal_code'];
        return required.every((name) => String(form.querySelector(`[name="${name}"]`)?.value || '').trim() !== '')
            && Boolean(getSelectedPaymentMethod());
    }

    function syncCheckoutProgress() {
        if (!isCartPage()) {
            return;
        }

        const cart = readCart();
        const continueWrap = document.getElementById('cart-continue-wrap');

        if (cart.length === 0) {
            setCheckoutFlowStep(1);
            if (continueWrap) {
                continueWrap.classList.add('is-hidden');
            }
            return;
        }

        if (continueWrap) {
            continueWrap.classList.remove('is-hidden');
        }

        if (checkoutFlowStep < 2) {
            setCheckoutFlowStep(1);
            return;
        }

        setCheckoutFlowStep(checkoutFlowStep >= 3 ? 3 : 2);
    }

    function bindCheckoutStepperControls() {
        const continueButton = document.getElementById('btn-continue-checkout');
        if (continueButton) {
            continueButton.addEventListener('click', () => {
                const panel = document.getElementById('checkout-panel');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                setCheckoutFlowStep(2);
            });
        }

        const form = document.getElementById('cart-shipping-form');
        if (form) {
            form.addEventListener('input', () => {
                syncCheckoutProgress();
                queueAbandonmentLead();
            });
            form.addEventListener('change', () => {
                syncCheckoutProgress();
                queueAbandonmentLead();
            });
        }
    }

    let abandonmentLeadTimer = null;

    function queueAbandonmentLead() {
        if (checkoutFlowStep < 2) {
            return;
        }
        clearTimeout(abandonmentLeadTimer);
        abandonmentLeadTimer = setTimeout(() => {
            try {
                const form = document.getElementById('cart-shipping-form');
                if (!form) return;
                const formData = new FormData(form);
                const clientName = String(formData.get('client_name') || '').trim();
                const phone = String(formData.get('phone') || '').trim();
                if (!clientName || !phone) return;

                const cart = readCart();
                if (!cart.length) return;

                const total = cart.reduce((sum, item) => {
                    const qty = Number(item.quantity) || 1;
                    const price = Number(item.price) || 0;
                    return sum + qty * price;
                }, 0);

                fetch('/api/cart_abandonment_endpoint.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        client_name: clientName,
                        phone,
                        email: String(formData.get('email') || '').trim(),
                        cart,
                        total_amount: total,
                        items_count: cart.reduce((n, item) => n + (Number(item.quantity) || 1), 0),
                        checkout_step: checkoutFlowStep,
                    }),
                }).catch(() => {});
            } catch (e) {
                // lead optional — nu blochează checkout
            }
        }, 2000);
    }

    function collectCheckoutData() {
        const form = document.getElementById('cart-shipping-form');
        const formData = form ? new FormData(form) : new FormData();
        const shippingMethod = getSelectedShippingMethod();
        const paymentMethod = getSelectedPaymentMethod();
        const countrySelect = form?.querySelector('[name="country"]');
        const countySelect = form?.querySelector('[name="county"]');
        const clientName = String(formData.get('client_name') || '').trim();
        const phone = String(formData.get('phone') || '').trim();
        const email = String(formData.get('email') || '').trim();
        const address = String(formData.get('address') || '').trim();
        const city = String(formData.get('city') || '').trim();
        const postalCode = String(formData.get('postal_code') || '').trim();

        if (!clientName || !phone) {
            throw new Error('Completeaza numele si telefonul inainte de trimiterea comenzii.');
        }

        if (!address) {
            throw new Error('Completeaza adresa de livrare inainte de trimiterea comenzii.');
        }

        if (!city || !postalCode) {
            throw new Error('Completeaza orasul/localitatea si codul postal inainte de trimiterea comenzii.');
        }

        if (!paymentMethod) {
            throw new Error('Selecteaza metoda de plata inainte de trimiterea comenzii.');
        }

        return {
            client_name: clientName,
            phone,
            email,
            shipping_method: shippingMethod,
            shipping_method_label: SHIPPING_LABELS[shippingMethod] || shippingMethod,
            payment_method: paymentMethod,
            payment_method_label: PAYMENT_LABELS[paymentMethod] || paymentMethod,
            country: String(formData.get('country') || '').trim(),
            country_label: countrySelect?.selectedOptions?.[0]?.textContent?.trim() || '',
            county: String(formData.get('county') || '').trim(),
            county_label: countySelect?.selectedOptions?.[0]?.textContent?.trim() || '',
            address,
            city,
            postal_code: postalCode,
        };
    }

    function buildOrderNotes(cart, checkoutData, prefix = 'Comanda trimisa din cos dupa confirmarea clientului.') {
        const productLines = cart.map((item, index) => {
            const parts = [
                `${index + 1}. ${item.product_name}`,
                `${Number(item.quantity) || 1} buc.`,
                formatMoney(item.price),
                item.oem ? `OEM: ${item.oem}` : '',
                item.vin ? `VIN: ${item.vin}` : '',
                item.source ? `Sursa: ${item.source}` : '',
            ];

            return parts.filter(Boolean).join(' | ');
        });

        return [
            prefix,
            `Client: ${checkoutData.client_name}`,
            `Telefon: ${checkoutData.phone}`,
            checkoutData.email ? `Email: ${checkoutData.email}` : '',
            `Livrare: ${checkoutData.shipping_method_label}`,
            `Plata: ${checkoutData.payment_method_label}`,
            `Tara: ${checkoutData.country_label || checkoutData.country}`,
            `Judet: ${checkoutData.county_label || checkoutData.county}`,
            `Adresa: ${checkoutData.address || ''}`,
            `Localitate: ${checkoutData.city}`,
            `Cod postal: ${checkoutData.postal_code}`,
            '',
            'Produse:',
            ...productLines,
        ].join('\n');
    }

    async function fetchCartQuote(cart) {
        const missing = cart.find((item) => !/^[a-f0-9]{16}$/i.test(String(item.product_id || item.randomn_id || '').trim()));
        if (missing) {
            throw new Error('Un produs din cos nu are ID valid de magazin. Reincarca pagina si adauga produsul din nou.');
        }

        const csrfToken = await fetchShopOrderCsrf();
        const response = await fetch(ORDERS_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-BPA-CSRF': csrfToken,
            },
            body: JSON.stringify({
                type_product: 'quote',
                csrf_token: csrfToken,
                items: cart.map((item) => ({
                    randomn_id: String(item.product_id || item.randomn_id || '').toLowerCase(),
                    quantity: Number(item.quantity) || 1,
                })),
            }),
        });

        const result = await response.json();
        if (response.status === 403) {
            shopOrderCsrfToken = '';
            throw new Error(result.message || 'Sesiune expirata. Reincarca pagina.');
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Nu am putut valida preturile din catalog.');
        }

        return result.data;
    }

    function applyQuoteToCart(cart, quote) {
        const byId = {};
        for (const line of quote?.lines || []) {
            byId[String(line.randomn_id || '').toLowerCase()] = line;
        }

        let changed = false;
        const next = cart.map((item) => {
            const id = String(item.product_id || item.randomn_id || '').toLowerCase();
            const line = byId[id];
            if (!line) {
                return item;
            }
            const newPrice = Number(line.unit_price) || 0;
            const qty = Number(item.quantity) || 1;
            if (Math.abs(newPrice - (Number(item.price) || 0)) > 0.009) {
                changed = true;
            }
            return {
                ...item,
                price: newPrice,
                total_amount: newPrice * qty,
                product_name: line.product_name || item.product_name,
                product_image: line.product_image || item.product_image,
            };
        });

        return {
            cart: next,
            changed,
            total_amount: Number(quote?.total_amount) || getCartTotal(next),
        };
    }

    async function sendCartToAdmin(cart, checkoutData, notesPrefix) {
        const missingIdItem = cart.find((item) => !(item.product_id || item.randomn_id));
        if (missingIdItem) {
            throw new Error('Un produs din cos nu poate fi comandat fara identificator valid.');
        }

        const csrfToken = await fetchShopOrderCsrf();

        const response = await fetch(ORDERS_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-BPA-CSRF': csrfToken,
            },
            body: JSON.stringify({
                type_product: 'add',
                csrf_token: csrfToken,
                items: cart.map((item) => ({
                    randomn_id: item.product_id || item.randomn_id || '',
                    quantity: Number(item.quantity) || 1,
                })),
                client_name: checkoutData.client_name,
                phone: checkoutData.phone,
                email: checkoutData.email || undefined,
                vin: cart.find((item) => item.vin)?.vin || undefined,
                channel: 'website',
                payment_status: checkoutData.payment_method,
                order_status: 'noua',
                delivery_method: checkoutData.shipping_method,
                delivery_status: 'neexpediata',
                notes: buildOrderNotes(cart, checkoutData, notesPrefix),
                coupon_code: readAppliedCoupon()?.code || undefined,
            }),
        });

        const result = await response.json();
        if (response.status === 403) {
            shopOrderCsrfToken = '';
            throw new Error(result.message || 'Sesiune expirata. Reincarca pagina si incearca din nou.');
        }
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Comanda nu a putut fi înregistrată. Încearcă din nou.');
        }

        return result.data;
    }

    async function initOnlinePayment(orderData, checkoutData) {
        const amount = Number(orderData.total_amount) || getCartTotal(readCart());
        const response = await fetch(`${PAYMENT_ENDPOINT}?action=init`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_randomn_id: Number(orderData.randomn_id) || 0,
                amount,
                payment_method: checkoutData.payment_method,
            }),
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.data?.payment_url) {
            throw new Error(result.message || 'Plata online nu a putut fi initiata.');
        }

        return result.data;
    }

    async function handleSubmitOrder(button) {
        let cart = readCart();
        if (cart.length === 0) {
            showCartMessage('Cosul este gol.', true);
            return;
        }

        let checkoutData;
        try {
            checkoutData = collectCheckoutData();
        } catch (error) {
            showCartMessage(error.message, true);
            return;
        }

        try {
            const quote = await fetchCartQuote(cart);
            const applied = applyQuoteToCart(cart, quote);
            if (applied.changed) {
                writeCart(applied.cart);
                renderCartPage();
                window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
                cart = applied.cart;
                showCartMessage('Preturile au fost sincronizate cu catalogul magazinului.', false);
            }
        } catch (error) {
            showCartMessage(error.message, true);
            return;
        }

        const confirmed = window.confirm('Confirmi trimiterea comenzii?');
        if (!confirmed) {
            return;
        }

        button.disabled = true;
        try {
            const orderData = await sendCartToAdmin(cart, checkoutData);

            if (checkoutData.payment_method === 'card_online') {
                const payment = await initOnlinePayment(orderData, checkoutData);
                writeCart([]);
                writeAppliedCoupon(null);
                fetch(CART_ENDPOINT, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear' }),
                }).catch(() => {});
                renderCartPage();
                window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
                window.location.href = payment.payment_url;
                return;
            }

            writeCart([]);
            writeAppliedCoupon(null);
            fetch(CART_ENDPOINT, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear' }),
            }).catch(() => {});
            renderCartPage();
            window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
            setCheckoutFlowStep(3);
            const successTitle = document.getElementById('checkout-success-title');
            if (successTitle) {
                successTitle.textContent = orderData.order_number
                    ? `Comanda ${orderData.order_number} a fost trimisă cu succes.`
                    : 'Comanda a fost trimisă cu succes.';
            }
            showCartMessage(`Comanda ${orderData.order_number || ''} a fost înregistrată cu succes.`, false);
        } catch (error) {
            showCartMessage(error.message, true);
        } finally {
            button.disabled = false;
        }
    }

    async function handleAddToCart(button) {
        const product = getProductFromButton(button);
        if (!product || !product.product_name) {
            showCartMessage('Nu am putut identifica produsul.', true);
            return;
        }

        const productId = String(product.product_id || product.randomn_id || '').trim();
        if (productId.startsWith('epiesa_') || (productId && !/^[a-f0-9]{16}$/i.test(productId))) {
            showCartMessage('Acest produs nu poate fi comandat online. Contacteaza-ne pe WhatsApp.', true);
            return;
        }

        button.disabled = true;
        addToLocalCart(product);
        showCartMessage('Produs adaugat in cos. Comanda ramane salvata local pana la confirmare.', false);
        window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
        window.dispatchEvent(new CustomEvent('besoiu:product-added', { detail: product }));
        button.disabled = false;
    }

    function filterCardSpecsPreview(specs, limit = 4) {
        const exclude = new Set(['compatibilitate', 'model', 'motorizare', 'descriere']);
        const priority = ['brand', 'categorie', 'subcategorie', 'marca', 'marcă'];
        const priorityItems = {};
        const otherItems = [];

        (Array.isArray(specs) ? specs : []).forEach((spec) => {
            const label = String(spec?.label || '').trim();
            const value = String(spec?.value || '').trim();
            if (!label || !value) {
                return;
            }

            const key = label.toLowerCase();
            if (exclude.has(key) || value.length > 42) {
                return;
            }

            if (priority.includes(key)) {
                priorityItems[key] = { label, value };
                return;
            }

            otherItems.push({ label, value });
        });

        const result = [];
        priority.forEach((priorityKey) => {
            if (priorityItems[priorityKey]) {
                result.push(priorityItems[priorityKey]);
            }
        });

        otherItems.forEach((spec) => {
            if (result.length >= limit) {
                return;
            }
            result.push(spec);
        });

        return result.slice(0, limit);
    }

    function parseNoteSpecs(note) {
        const text = String(note || '').trim();
        if (!text || noteIsHtml(text)) {
            return [];
        }

        const specs = [];
        const seen = {};
        const append = (label, value) => {
            const cleanLabel = String(label || '').trim();
            const cleanValue = String(value || '').trim();
            if (!cleanLabel || !cleanValue) {
                return;
            }
            const key = cleanLabel.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            specs.push({ label: cleanLabel, value: cleanValue });
        };

        String(text).split(/\s*\|\s*|\s*;\s*/).forEach((chunk) => {
            const part = String(chunk || '').trim();
            if (!part) {
                return;
            }

            if (part.indexOf('::') === -1) {
                const colonMatch = part.match(/^([^:]+):\s*(.+)$/);
                if (colonMatch) {
                    append(colonMatch[1], colonMatch[2]);
                    return;
                }
            }

            const parts = part.split(/\s*::\s*/).map((item) => item.trim()).filter(Boolean);
            if (parts.length === 2) {
                append(parts[0], parts[1]);
                return;
            }

            if (parts.length > 2) {
                const start = parts.length % 2 === 1 ? 1 : 0;
                for (let i = start; i < parts.length - 1; i += 2) {
                    append(parts[i], parts[i + 1]);
                }
                return;
            }

            if (parts.length === 1) {
                append('Detaliu', parts[0]);
            }
        });

        return specs;
    }

    function decodeHtmlEntities(text) {
        const value = String(text || '').trim();
        if (!value) {
            return '';
        }

        const textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        let decoded = textarea.value;
        if (/&lt;|&amp;lt;/i.test(decoded)) {
            textarea.innerHTML = decoded;
            decoded = textarea.value;
        }

        return decoded;
    }

    function noteIsHtml(text) {
        return /<\s*(p|ul|ol|li|div|dl|dt|dd|table|thead|tbody|tr|th|td|h[1-6]|br|strong|b|em|i|span)\b/i.test(decodeHtmlEntities(text));
    }

    function stripHtml(text) {
        return String(decodeHtmlEntities(text) || '')
            .replace(/<\s*(br|hr)\s*\/?>/gi, ' ')
            .replace(/<\/\s*(p|li|tr|h[1-6])\s*>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function renderProductNoteHtml(note) {
        const html = decodeHtmlEntities(note);
        if (!noteIsHtml(html)) {
            return '';
        }

        return `<div class="_product-card-desc _product-card-desc--html">${html}</div>`;
    }

    function getProductSpecsFromSource(source) {
        if (!source) {
            return [];
        }

        if (source.dataset.specs) {
            try {
                const parsed = JSON.parse(source.dataset.specs);
                if (Array.isArray(parsed) && parsed.length) {
                    return parsed.filter((item) => item && item.label && item.value);
                }
            } catch (error) {
                /* fallback below */
            }
        }

        const note = source.dataset.desc
            || textFrom(source, '._product-card-desc')
            || textFrom(source, '._product-spec-value');
        if (noteIsHtml(note)) {
            const plain = stripHtml(note);
            return plain ? [{ label: 'Descriere', value: plain }] : [];
        }
        const parsedNote = parseNoteSpecs(note);
        return parsedNote.length ? parsedNote : (note ? [{ label: 'Descriere', value: note }] : []);
    }

    function renderProductSpecsHtml(specs, columns, extraClass) {
        const items = Array.isArray(specs) ? specs.filter((item) => item && item.label && item.value) : [];
        if (!items.length) {
            return '';
        }

        const colCount = Math.max(1, Math.min(3, Number(columns) || 2));
        const className = ['_product-specs-grid', '_product-specs-grid--' + colCount + 'col', extraClass || '']
            .filter(Boolean)
            .join(' ');

        return `<div class="${className}">${items.map((spec) => `
            <div class="_product-spec-item">
                <span class="_product-spec-label">${escapeHtml(spec.label)}</span>
                <span class="_product-spec-value">${escapeHtml(spec.value)}</span>
            </div>
        `).join('')}</div>`;
    }

    function bindAddButtons() {
        document.addEventListener('click', (event) => {
            const button = eventClosest(event, '.btn_addtoccard, .add-cart');
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            handleAddToCart(button);
        }, true);
    }

    function productCardActionsHtml(productId = '') {
        const idAttr = productId ? ` data-product-id="${escapeHtml(productId)}"` : '';
        return `<div class="_product-card-actions">
            <button class="_product-card-btn product_detal" type="button"${idAttr} title="Detalii produs">
                <img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon" width="18" height="18">
                <span>Detalii</span>
            </button>
            <button class="btn_addtoccard _pca-icon" type="button" title="Adaugă în coș">
                <img src="img/icons/14_cos_cumparaturi.svg" alt="" class="_pca-btn-icon" width="20" height="20">
            </button>
            <button class="btn_quickbuy _pca-icon" type="button" title="Cumpără cu 1 click">
                <img src="img/icons/26_plata_card.svg" alt="" class="_pca-btn-icon" width="20" height="20">
            </button>
        </div>`;
    }

    function productCardActionsHomeHtml(productId = '') {
        const idAttr = productId ? ` data-product-id="${escapeHtml(productId)}"` : '';
        return `<div class="_product-card-actions _product-card-actions--home">
            <button class="_product-card-btn product_detal" type="button"${idAttr} title="Detalii produs">
                <img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon _pca-btn-icon--on-primary" width="14" height="14">
                <span>Detalii</span>
            </button>
            <button class="btn_addtoccard _pca-icon" type="button" title="Adaugă în coș" aria-label="Adaugă în coș">
                <img src="img/icons/14_cos_cumparaturi.svg" alt="" class="_pca-btn-icon" width="15" height="15">
            </button>
            <button class="btn_quickbuy _pca-icon" type="button" title="Cumpără cu 1 click" aria-label="Cumpără cu 1 click">
                <img src="img/icons/26_plata_card.svg" alt="" class="_pca-btn-icon" width="15" height="15">
            </button>
        </div>`;
    }

    function getQuickBuyShippingMethod() {
        return document.querySelector('#besoiu-quickbuy-modal input[name="qb_shipping_method"]:checked')?.value || 'ridicare_locala';
    }

    function getQuickBuyPaymentMethod() {
        return document.querySelector('#besoiu-quickbuy-modal input[name="qb_payment_method"]:checked')?.value || '';
    }

    function updateQuickBuyLocationNote(shippingMethod) {
        const note = document.getElementById('quickbuy-location-note');
        if (!note) {
            return;
        }

        note.textContent = shippingMethod === 'tarif_fix'
            ? 'Comanda va fi livrata prin curier. Poti plati ramburs la livrare sau online in avans.'
            : 'Comanda se ridica din magazinul nostru din Timisoara. Poti plati cu card fizic, numerar sau online in avans.';
    }

    function showQuickBuyMessage(message, isError = false) {
        const box = document.getElementById('qb-form-message');
        if (!box) {
            return;
        }

        box.textContent = storefrontClientMessage(message);
        box.className = 'qb-msg ' + (isError ? 'is-error' : 'is-success');
    }

    function clearQuickBuyMessage() {
        const box = document.getElementById('qb-form-message');
        if (!box) {
            return;
        }

        box.textContent = '';
        box.className = 'qb-msg';
    }

    function updateQuickBuyTotal() {
        const product = window.__qbCurrentProduct;
        const qtyInput = document.getElementById('qb-quantity');
        const totalEl = document.getElementById('qb-total-price');
        if (!product || !qtyInput || !totalEl) {
            return;
        }

        const qty = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
        qtyInput.value = String(qty);
        const total = (Number(product.price) || 0) * qty;
        totalEl.textContent = total > 0 ? formatMoney(total) : 'La cerere';
    }

    function ensureQuickBuyModal() {
        if (document.getElementById('besoiu-quickbuy-modal')) {
            return;
        }

        const modal = document.createElement('div');
        modal.id = 'besoiu-quickbuy-modal';
        modal.className = 'qb-modal';
        modal.innerHTML = `
            <div class="qb-dialog" role="dialog" aria-modal="true" aria-labelledby="qb-modal-title">
                <div class="qb-head">
                    <h3 id="qb-modal-title"><i class="fa-solid fa-bolt"></i> Cumpără cu 1 click</h3>
                    <button type="button" class="qb-close" data-qb-close aria-label="Închide">&times;</button>
                </div>
                <div class="qb-body">
                    <div class="qb-product">
                        <img id="qb-product-image" src="" alt="">
                        <div>
                            <h4 class="qb-product-name" id="qb-product-name"></h4>
                            <div class="qb-product-meta" id="qb-product-meta"></div>
                            <div class="qb-product-price" id="qb-product-price"></div>
                            <div class="qb-qty">
                                <span>Cantitate</span>
                                <input id="qb-quantity" type="number" min="1" value="1">
                                <span id="qb-total-price"></span>
                            </div>
                        </div>
                    </div>

                    <div class="qb-specs-wrap" id="qb-product-specs"></div>

                    <p class="qb-msg" id="qb-form-message"></p>

                    <div class="qb-section-title"><i class="fa-solid fa-box"></i> Metoda de livrare</div>
                    <p class="qb-note" id="quickbuy-location-note">Alege unde vrei sa primesti comanda.</p>
                    <div class="qb-delivery-grid">
                        <label class="delivery-opt qb-delivery-opt active">
                            <input type="radio" name="qb_shipping_method" value="ridicare_locala" checked>
                            <span class="d-radio"></span>
                            <span class="d-icon"><i class="fa-solid fa-store"></i></span>
                            <span><span class="d-label">Ridicare locala</span><span class="d-hint">Timisoara, magazin</span></span>
                        </label>
                        <label class="delivery-opt qb-delivery-opt">
                            <input type="radio" name="qb_shipping_method" value="tarif_fix">
                            <span class="d-radio"></span>
                            <span class="d-icon"><i class="fa-solid fa-truck-fast"></i></span>
                            <span><span class="d-label">Curier rapid</span><span class="d-hint">Livrare acasa</span></span>
                        </label>
                    </div>

                    <div class="qb-section-title"><i class="fa-solid fa-credit-card"></i> Metoda de plata</div>
                    <div class="qb-payment-grid" id="quickbuy-payment-grid"></div>

                    <form id="besoiu-quickbuy-form" onsubmit="return false;">
                        <div class="qb-section-title"><i class="fa-solid fa-address-card"></i> Date livrare</div>
                        <input type="text" class="qb-field" name="client_name" placeholder="Nume si prenume" autocomplete="name" required>
                        <div class="qb-row">
                            <input type="tel" class="qb-field" name="phone" placeholder="Telefon" inputmode="tel" autocomplete="tel" required>
                            <input type="email" class="qb-field" name="email" placeholder="Email" inputmode="email" autocomplete="email">
                        </div>
                        <input type="text" class="qb-field" name="address" placeholder="Adresa (strada, numar, bloc)" autocomplete="street-address" required>
                        <div class="qb-row">
                            <input type="text" class="qb-field" name="city" placeholder="Localitate" autocomplete="address-level2" required>
                            <input type="text" class="qb-field" name="postal_code" placeholder="Cod postal" inputmode="numeric" autocomplete="postal-code" required>
                        </div>
                        <div class="qb-row">
                            <select class="qb-field" name="country">
                                <option value="RO">Romania</option>
                                <option value="MD">Republica Moldova</option>
                                <option value="DE">Germania</option>
                                <option value="IT">Italia</option>
                            </select>
                            <select class="qb-field" name="county">
                                <option value="TM">Timisoara</option>
                                <option value="B">Bucuresti</option>
                                <option value="CJ">Cluj</option>
                                <option value="IS">Iasi</option>
                            </select>
                        </div>
                        <button type="button" class="qb-submit" data-qb-submit>
                            <i class="fa-solid fa-check"></i> Trimite comanda
                        </button>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        bindQuickBuyModalEvents(modal);
    }

    function bindQuickBuyModalEvents(modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal || eventClosest(event, '[data-qb-close]')) {
                closeQuickBuyModal();
                return;
            }

            const deliveryOption = eventClosest(event, '.qb-delivery-opt');
            if (deliveryOption) {
                modal.querySelectorAll('.qb-delivery-opt').forEach((option) => option.classList.remove('active'));
                deliveryOption.classList.add('active');
                renderPaymentMethods(getQuickBuyShippingMethod(), '', 'quickbuy-payment-grid');
                return;
            }

            const paymentOption = eventClosest(event, '#quickbuy-payment-grid .payment-opt');
            if (paymentOption) {
                modal.querySelectorAll('#quickbuy-payment-grid .payment-opt').forEach((option) => option.classList.remove('active'));
                paymentOption.classList.add('active');
            }

            const submitButton = eventClosest(event, '[data-qb-submit]');
            if (submitButton) {
                event.preventDefault();
                handleQuickBuySubmit(submitButton);
            }
        });

        modal.addEventListener('input', (event) => {
            if (event.target.matches('#qb-quantity')) {
                updateQuickBuyTotal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeQuickBuyModal();
            }
        });
    }

    function openQuickBuyModal(product) {
        ensureQuickBuyModal();
        window.__qbCurrentProduct = product;
        clearQuickBuyMessage();

        const modal = document.getElementById('besoiu-quickbuy-modal');
        const imageEl = document.getElementById('qb-product-image');
        const nameEl = document.getElementById('qb-product-name');
        const metaEl = document.getElementById('qb-product-meta');
        const priceEl = document.getElementById('qb-product-price');
        const qtyInput = document.getElementById('qb-quantity');
        const specsEl = document.getElementById('qb-product-specs');

        if (imageEl) {
            imageEl.src = product.product_image || getDefaultProductImage();
            imageEl.alt = product.product_name || 'Produs';
        }
        if (nameEl) nameEl.textContent = product.product_name || 'Produs';
        if (metaEl) {
            metaEl.textContent = [
                product.oem ? `OEM: ${product.oem}` : '',
                product.vin ? `VIN: ${product.vin}` : '',
            ].filter(Boolean).join(' · ');
        }
        if (priceEl) {
            priceEl.textContent = product.price > 0 ? formatMoney(product.price) : 'La cerere';
        }
        if (qtyInput) {
            qtyInput.value = String(Math.max(1, Number(product.quantity) || 1));
        }
        if (specsEl) {
            const specsHtml = renderProductSpecsHtml(product.specs || [], 3);
            specsEl.innerHTML = specsHtml
                ? `<div class="qb-section-title"><i class="fa-solid fa-list"></i> Caracteristici</div>${specsHtml}`
                : '';
        }

        modal.querySelectorAll('.qb-delivery-opt').forEach((option, index) => {
            option.classList.toggle('active', index === 0);
            const input = option.querySelector('input[name="qb_shipping_method"]');
            if (input) input.checked = index === 0;
        });

        renderPaymentMethods('ridicare_locala', '', 'quickbuy-payment-grid');
        updateQuickBuyTotal();
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeQuickBuyModal() {
        const modal = document.getElementById('besoiu-quickbuy-modal');
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        document.body.style.overflow = '';
        window.__qbCurrentProduct = null;
    }

    function collectQuickBuyCheckoutData() {
        const form = document.getElementById('besoiu-quickbuy-form');
        const formData = form ? new FormData(form) : new FormData();
        const shippingMethod = getQuickBuyShippingMethod();
        const paymentMethod = getQuickBuyPaymentMethod();
        const countrySelect = form?.querySelector('[name="country"]');
        const countySelect = form?.querySelector('[name="county"]');
        const clientName = String(formData.get('client_name') || '').trim();
        const phone = String(formData.get('phone') || '').trim();
        const email = String(formData.get('email') || '').trim();
        const address = String(formData.get('address') || '').trim();
        const city = String(formData.get('city') || '').trim();
        const postalCode = String(formData.get('postal_code') || '').trim();

        if (!clientName || !phone) {
            throw new Error('Completeaza numele si telefonul inainte de trimiterea comenzii.');
        }

        if (!address) {
            throw new Error('Completeaza adresa de livrare inainte de trimiterea comenzii.');
        }

        if (!city || !postalCode) {
            throw new Error('Completeaza orasul/localitatea si codul postal inainte de trimiterea comenzii.');
        }

        if (!paymentMethod) {
            throw new Error('Selecteaza metoda de plata inainte de trimiterea comenzii.');
        }

        return {
            client_name: clientName,
            phone,
            email,
            shipping_method: shippingMethod,
            shipping_method_label: SHIPPING_LABELS[shippingMethod] || shippingMethod,
            payment_method: paymentMethod,
            payment_method_label: PAYMENT_LABELS[paymentMethod] || paymentMethod,
            country: String(formData.get('country') || '').trim(),
            country_label: countrySelect?.selectedOptions?.[0]?.textContent?.trim() || '',
            county: String(formData.get('county') || '').trim(),
            county_label: countySelect?.selectedOptions?.[0]?.textContent?.trim() || '',
            address,
            city,
            postal_code: postalCode,
        };
    }

    async function handleQuickBuySubmit(button) {
        const product = window.__qbCurrentProduct;
        if (!product) {
            showQuickBuyMessage('Produsul nu mai este disponibil in fereastra rapida.', true);
            return;
        }

        let checkoutData;
        try {
            checkoutData = collectQuickBuyCheckoutData();
        } catch (error) {
            showQuickBuyMessage(error.message, true);
            return;
        }

        const qty = Math.max(1, parseInt(document.getElementById('qb-quantity')?.value || '1', 10) || 1);
        const cartItem = {
            ...product,
            quantity: qty,
            total_amount: (Number(product.price) || 0) * qty,
            source: product.source || 'quick-buy',
        };

        button.disabled = true;
        clearQuickBuyMessage();

        try {
            await sendCartToAdmin(
                [cartItem],
                checkoutData,
                'Comanda rapida 1-click de pe site, dupa confirmarea clientului.'
            );
            closeQuickBuyModal();
            showCartMessage('Comanda rapidă a fost înregistrată cu succes.', false);
        } catch (error) {
            showQuickBuyMessage(error.message, true);
        } finally {
            button.disabled = false;
        }
    }

    function bindQuickBuyButtons() {
        document.addEventListener('click', (event) => {
            const button = eventClosest(event, '.btn_quickbuy');
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const product = getCatalogProduct(button);
            if (!product || !product.product_name) {
                showCartMessage('Nu am putut identifica produsul.', true);
                return;
            }

            openQuickBuyModal(product);
        }, true);
    }

    function renderCartPage() {
        const container = document.getElementById('cart-items-list') || document.querySelector('.table-cart tbody');
        if (!container) {
            return;
        }

        const cart = readCart();
        const total = cart.reduce((sum, item) => sum + (Number(item.total_amount) || 0), 0);

        const badge = document.getElementById('cart-badge');
        if (badge) {
            badge.textContent = cart.length + (cart.length === 1 ? ' produs' : ' produse');
        }

        if (cart.length === 0) {
            container.innerHTML = `<div class="cart-empty">
                <div class="cart-empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <h3>Coșul tău este gol</h3>
                <p>Adaugă produse din catalog pentru a continua.</p>
                <a href="/catalog">Vezi catalogul <i class="fa-solid fa-arrow-right"></i></a>
            </div>`;
        } else {
            container.innerHTML = cart.map((item, index) => {
                const unitPrice = formatMoney(item.price);
                const qty = Number(item.quantity) || 1;
                return `<div class="ci" data-cart-index="${index}">
                    <div class="ci-img">
                        <img src="${escapeHtml(item.product_image || getDefaultProductImage())}" alt="${escapeHtml(item.product_name)}">
                    </div>
                    <div class="ci-info">
                        <div class="ci-name">${escapeHtml(item.product_name)}</div>
                        <div class="ci-meta">${item.oem ? 'OEM: ' + escapeHtml(item.oem) + ' · ' : ''}${unitPrice}/buc</div>
                        <span class="ci-stock ${item.in_stock === false ? 'red' : 'green'}"><i class="fa-solid fa-circle-check"></i> ${item.in_stock === false ? 'Stoc epuizat' : 'In stoc'}</span>
                    </div>
                    <div class="ci-qty">
                        <button type="button" class="ci-qty-btn minus" data-cart-qty-dec="${index}"><i class="fa-solid fa-minus"></i></button>
                        <input class="ci-qty-val" data-cart-qty="${index}" type="number" min="1" value="${qty}">
                        <button type="button" class="ci-qty-btn plus" data-cart-qty-inc="${index}"><i class="fa-solid fa-plus"></i></button>
                    </div>
                    <div class="ci-price">
                        ${formatMoney(item.total_amount)}
                        ${qty > 1 ? '<span class="ci-price-unit">' + qty + ' × ' + unitPrice + '</span>' : ''}
                    </div>
                    <button type="button" class="ci-remove" data-cart-remove="${index}" title="Șterge"><i class="fa-solid fa-xmark"></i></button>
                </div>`;
            }).join('');
        }

        updateTotals(total, readAppliedCoupon());
        revalidateAppliedCoupon().then(() => {
            updateTotals(getCartTotal(readCart()), readAppliedCoupon());
        }).finally(() => {
            syncCheckoutProgress();
        });
    }

    function updateTotals(subtotal, coupon = readAppliedCoupon()) {
        const discount = coupon ? Number(coupon.discount) || 0 : 0;
        const total = Math.max(0, subtotal - discount);
        const totalsTable = document.querySelector('.table-totals');
        if (!totalsTable) {
            return;
        }

        const subtotalCell = totalsTable.querySelector('[data-cart-subtotal]');
        const discountRow = totalsTable.querySelector('[data-cart-discount-row]');
        const discountCell = totalsTable.querySelector('[data-cart-discount]');
        const totalCell = totalsTable.querySelector('[data-cart-total]');

        if (subtotalCell) {
            subtotalCell.textContent = formatMoney(subtotal);
        }
        if (discountRow) {
            discountRow.classList.toggle('is-hidden', !(discount > 0));
        }
        if (discountCell) {
            discountCell.textContent = `- ${formatMoney(discount)}`;
        }
        if (totalCell) {
            totalCell.textContent = formatMoney(total);
        }

        if (!subtotalCell && !totalCell) {
            totalsTable.querySelectorAll('td').forEach((cell) => {
                if (cell.textContent.trim().match(/^\d|RON/)) {
                    cell.textContent = formatMoney(total);
                }
            });
        }
    }

    function bindCouponControls() {
        const input = document.getElementById('cart-coupon-code');
        const button = document.getElementById('cart-coupon-apply');
        const hint = document.getElementById('cart-coupon-hint');
        if (!input || !button) {
            return;
        }

        const applied = readAppliedCoupon();
        if (applied?.code) {
            input.value = applied.code;
            if (hint) {
                hint.textContent = `Cupon activ: ${applied.code} (-${formatMoney(applied.discount)})`;
            }
        }

        const applyHandler = async () => {
            button.disabled = true;
            try {
                const data = await applyCouponCode(input.value);
                if (hint) {
                    hint.textContent = `Cupon aplicat: ${data.code} (-${formatMoney(data.discount)})`;
                }
                renderCartPage();
                showCartMessage('Cupon aplicat cu succes.', false);
            } catch (error) {
                if (hint) {
                    hint.textContent = error.message;
                }
                showCartMessage(error.message, true);
            } finally {
                button.disabled = false;
            }
        };

        button.addEventListener('click', applyHandler);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyHandler();
            }
        });
    }

    function bindCartPage() {
        document.addEventListener('submit', (event) => {
            if (!event.target.matches('#cart-shipping-form')) {
                return;
            }

            event.preventDefault();
            showCartMessage('Totalul cosului este actualizat.', false);
        });

        document.addEventListener('click', (event) => {
            const submitButton = eventClosest(event, '[data-cart-submit-order]');
            if (!submitButton) {
                return;
            }

            event.preventDefault();
            handleSubmitOrder(submitButton);
        });

        document.addEventListener('click', (event) => {
            const removeButton = eventClosest(event, '[data-cart-remove]');
            if (!removeButton) {
                return;
            }

            removeCartItem(Number(removeButton.dataset.cartRemove));
            renderCartPage();
        });

        document.addEventListener('change', (event) => {
            const quantityInput = eventClosest(event, '[data-cart-qty]');
            if (!quantityInput) {
                return;
            }

            const cart = readCart();
            const item = cart[Number(quantityInput.dataset.cartQty)];
            if (!item) {
                return;
            }

            item.quantity = Math.max(1, parseInt(quantityInput.value || '1', 10) || 1);
            item.total_amount = item.quantity * item.price;
            writeCart(cart);
            renderCartPage();
            window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
        });

        document.addEventListener('click', (event) => {
            const incBtn = eventClosest(event, '[data-cart-qty-inc]');
            const decBtn = eventClosest(event, '[data-cart-qty-dec]');
            if (!incBtn && !decBtn) return;

            const index = Number((incBtn || decBtn).dataset.cartQtyInc ?? (incBtn || decBtn).dataset.cartQtyDec);
            const cart = readCart();
            const item = cart[index];
            if (!item) return;

            if (incBtn) {
                item.quantity = (Number(item.quantity) || 1) + 1;
            } else {
                item.quantity = Math.max(1, (Number(item.quantity) || 1) - 1);
            }
            item.total_amount = item.quantity * item.price;
            writeCart(cart);
            renderCartPage();
            window.dispatchEvent(new CustomEvent('besoiu:cart-updated'));
        });

        window.addEventListener('besoiu:cart-updated', renderCartPage);
        renderCartPage();
    }

    function bindMiniCartPreview() {
        const showPreview = () => renderMiniCart('Cosul tau');
        document.querySelectorAll('a.cart, a[href="/cart"], a[href="cart.php"]').forEach((link) => {
            if (link.dataset.miniCartBound === '1') {
                return;
            }
            link.dataset.miniCartBound = '1';
            link.addEventListener('mouseenter', showPreview);
            link.addEventListener('focus', showPreview);
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    document.addEventListener('DOMContentLoaded', async () => {
        bindAddButtons();
        bindQuickBuyButtons();
        bindCheckoutOptions();
        bindCheckoutStepperControls();
        bindCartPage();
        bindCouponControls();
        bindMiniCartPreview();
        updateCartBadge();

        if (isCartPage()) {
            setCheckoutFlowStep(1);
            const localCart = readCart();
            if (localCart.length === 0) {
                await pullServerCart();
            }
            renderCartPage();
        }
    });

    function renderProductBadgeHtml(badgeKey) {
        const key = String(badgeKey || '').trim();
        const labels = {
            hot: 'HOT',
            nou: 'NOU',
            promo: 'PROMO',
            top: 'TOP',
            stoc: 'STOC',
            recomandat: 'RECOMANDAT',
        };
        if (!key || !labels[key]) {
            return '';
        }
        return `<div class="_product-card-badge"><span class="_product-badge _product-badge--${escapeHtml(key)}">${escapeHtml(labels[key])}</span></div>`;
    }

    function renderRecommendedBadgeHtml() {
        return renderProductBadgeHtml('recomandat');
    }

    function renderHomeGridCardHtml(options) {
        const name = options?.name || 'Produs';
        const image = options?.image || 'assets/images/products/1.jpg';
        const priceLabel = options?.priceLabel || 'La cerere';
        const productId = options?.productId || '';
        const badgeKey = String(options?.badge || '').trim();
        const badgeHtml = badgeKey
            ? renderProductBadgeHtml(badgeKey)
            : (options?.recommendedBadge ? renderRecommendedBadgeHtml() : '');

        return `${badgeHtml}<div class="_product-card-image _product-card-image--clickable">
            <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" loading="lazy" decoding="async">
        </div>
        <div class="_product-card-head">
            <h3 class="_product-card-name">${escapeHtml(name)}</h3>
        </div>
        <div class="_product-price">${escapeHtml(priceLabel)}</div>
        ${productCardActionsHtml(productId)}`;
    }

    function vitrinaShortTitle(name, maxLength = 52) {
        const clean = String(name || '').trim().replace(/\s+/g, ' ');
        if (!clean) {
            return 'Produs';
        }
        if (clean.length <= maxLength) {
            return clean;
        }
        let short = clean.slice(0, maxLength);
        const lastSpace = short.lastIndexOf(' ');
        if (lastSpace > maxLength * 0.5) {
            short = short.slice(0, lastSpace);
        }
        return short.replace(/[ ,.;:-]+$/u, '') + '…';
    }

    function vitrinaReferencePriceFromOffer(offerPrice, discountPercent) {
        const offer = Number(offerPrice) || 0;
        const discount = Number(discountPercent) || 0;
        if (offer <= 0 || discount <= 0 || discount >= 100) {
            return offer;
        }
        const reference = offer / (1 - (discount / 100));
        return Math.max(Math.ceil(reference), Math.ceil(offer) + 1);
    }

    function vitrinaCardPricing(product) {
        const newPrice = Number(product?.price_numeric || product?.price || 0) || 0;
        const newLabel = product?.price_label || product?.priceLabel
            || (newPrice > 0 ? `${newPrice.toFixed(2)} RON` : 'La cerere');
        if (newPrice <= 0) {
            return { newPrice: 0, newLabel: 'La cerere', oldLabel: '', discountPercent: 0 };
        }

        const oldRaw = product?.price_old_label || product?.price_old || product?.old_price || '';
        const oldParsed = parseFloat(String(oldRaw).replace(/[^\d.,]/g, '').replace(',', '.'));
        if (Number.isFinite(oldParsed) && oldParsed > newPrice) {
            const discountPercent = Math.max(1, Math.min(99, Math.round((1 - (newPrice / oldParsed)) * 100)));
            return {
                newPrice,
                newLabel,
                oldPrice: oldParsed,
                oldLabel: product?.price_old_label || `${oldParsed.toFixed(2)} RON`,
                discountPercent,
            };
        }

        const discountPercent = Number(product?.discount_percent) || 40;
        const oldPrice = vitrinaReferencePriceFromOffer(newPrice, discountPercent);
        return {
            newPrice,
            newLabel,
            oldPrice,
            oldLabel: `${oldPrice.toFixed(2)} RON`,
            discountPercent,
        };
    }

    function renderVitrinaDiscountBadgeHtml(percent) {
        const value = Number(percent) || 0;
        if (value <= 0) {
            return '';
        }
        return `<div class="_product-card-badge"><span class="_product-badge _product-badge--discount">-${escapeHtml(String(value))}%</span></div>`;
    }

    function renderVitrinaCardPriceHtml(pricing) {
        const oldLabel = String(pricing?.oldLabel || '').trim();
        const newLabel = String(pricing?.newLabel || 'La cerere').trim();
        const newPrice = Number(pricing?.newPrice) || 0;
        const oldPrice = Number(pricing?.oldPrice) || 0;
        if (oldLabel && oldPrice > newPrice && newPrice > 0) {
            return `<div class="_product-price _product-price--vitrina"><span class="_product-price-old">${escapeHtml(oldLabel)}</span><span class="_product-price-new">${escapeHtml(newLabel)}</span></div>`;
        }
        return `<div class="_product-price _product-price--vitrina"><span class="_product-price-new">${escapeHtml(newLabel)}</span></div>`;
    }

    function renderHomeVitrinaCardHtml(options) {
        const fullName = options?.name || 'Produs';
        const shortName = options?.shortName || vitrinaShortTitle(fullName);
        const image = options?.image || 'assets/images/products/1.jpg';
        const pricing = options?.pricing || vitrinaCardPricing(options?.product || options || {});

        return `${renderVitrinaDiscountBadgeHtml(pricing.discountPercent)}<div class="_product-card-image _product-card-image--clickable">
            <img src="${escapeHtml(image)}" alt="${escapeHtml(shortName)}" loading="lazy" decoding="async">
        </div>
        <div class="_product-card-head">
            <h3 class="_product-card-name">${escapeHtml(shortName)}</h3>
        </div>
        ${renderVitrinaCardPriceHtml(pricing)}`;
    }

    function renderMagazinCardHtml(options) {
        const name = options?.name || 'Produs';
        const image = options?.image || 'assets/images/products/1.jpg';
        const priceLabel = options?.priceLabel || 'La cerere';
        const productId = options?.productId || '';
        const oem = options?.oem || 'N/A';
        const badgeKey = String(options?.badge || '').trim();
        const deliveryTime = String(options?.deliveryTime || '24');
        const cardDescription = String(options?.description || '').trim();
        const cardSpecs = Array.isArray(options?.specs) ? options.specs : [];
        const badgeHtml = badgeKey ? renderProductBadgeHtml(badgeKey) : '';
        const specsBlock = cardSpecs.length
            ? renderProductSpecsHtml(cardSpecs, 2, '_product-card-specs')
            : '';
        const noteBlock = !specsBlock && cardDescription
            ? `<p class="_product-card-desc">${escapeHtml(cardDescription)}</p>`
            : '';

        return `${badgeHtml}
        <div class="_product-card-head"><h3 class="_product-card-name">${escapeHtml(name)}</h3></div>
        <div class="_product-card-image _product-card-image--clickable">
            <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" loading="lazy" decoding="async">
        </div>
        ${specsBlock}
        ${noteBlock}
        <div class="_product-card-info">
            <div class="_product-oem">OEM: ${escapeHtml(oem)}</div>
            <div class="_product-time">${escapeHtml(deliveryTime)} H</div>
        </div>
        <div class="_product-price">${escapeHtml(priceLabel)}</div>
        ${productCardActionsHtml(productId)}`;
    }

    window.besoiuRenderProductBadgeHtml = renderProductBadgeHtml;
    window.besoiuProductCardActionsHtml = productCardActionsHtml;
    window.besoiuProductCardActionsHomeHtml = productCardActionsHomeHtml;
    window.besoiuRenderHomeGridCardHtml = renderHomeGridCardHtml;
    window.besoiuRenderHomeVitrinaCardHtml = renderHomeVitrinaCardHtml;
    window.besoiuRenderMagazinCardHtml = renderMagazinCardHtml;
    window.besoiuVitrinaShortTitle = vitrinaShortTitle;
    window.besoiuVitrinaCardPricing = vitrinaCardPricing;
    window.besoiuFilterCardSpecsPreview = filterCardSpecsPreview;
    window.besoiuParseNoteSpecs = parseNoteSpecs;
    window.besoiuRenderProductSpecsHtml = renderProductSpecsHtml;
    window.besoiuNoteIsHtml = noteIsHtml;
    window.besoiuRenderProductNoteHtml = renderProductNoteHtml;
    window.besoiuStripHtml = stripHtml;
})();
