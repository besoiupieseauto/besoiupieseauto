(function () {
    'use strict';

    var API = 'api/cont_endpoint.php';

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function $all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function showStatus(el, message, type) {
        if (!el) return;
        el.style.display = 'block';
        el.textContent = message;
        el.className = 'ac-status ac-status--' + (type || 'info');
    }

    function hideStatus(el) {
        if (!el) el = $('#ac-global-status');
        if (el) {
            el.style.display = 'none';
            el.textContent = '';
        }
    }

    function apiPost(action, data) {
        var body = Object.assign({ action: action }, data || {});
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Eroare la server.');
                }
                return json;
            });
        });
    }

    function formData(form) {
        var data = {};
        new FormData(form).forEach(function (value, key) {
            data[key] = value;
        });
        return data;
    }

    function setLoading(form, loading) {
        var btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        btn.disabled = loading;
        btn.dataset.originalText = btn.dataset.originalText || btn.textContent;
        btn.textContent = loading ? 'Se procesează...' : btn.dataset.originalText;
    }

    function initAuthTabs() {
        var tabs = $all('.ac-auth-tab');
        var panels = $all('.ac-auth-panel');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-tab');
                tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
                panels.forEach(function (p) {
                    p.classList.toggle('active', p.getAttribute('data-panel') === target);
                });
            });
        });

        var initial = new URLSearchParams(window.location.search).get('view');
        if (initial === 'register') {
            var regTab = $('.ac-auth-tab[data-tab="register"]');
            if (regTab) regTab.click();
        }
    }

    function initLoginForm() {
        var form = $('#ac-login-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideStatus();
            setLoading(form, true);
            apiPost('login', formData(form))
                .then(function () {
                    window.location.href = 'cont.php';
                })
                .catch(function (err) {
                    showStatus($('#ac-login-status'), err.message, 'error');
                })
                .finally(function () {
                    setLoading(form, false);
                });
        });
    }

    function initRegisterForm() {
        var form = $('#ac-register-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideStatus();
            setLoading(form, true);
            apiPost('register', formData(form))
                .then(function () {
                    window.location.href = 'cont.php';
                })
                .catch(function (err) {
                    showStatus($('#ac-register-status'), err.message, 'error');
                })
                .finally(function () {
                    setLoading(form, false);
                });
        });
    }

    function initLogout() {
        $all('[data-ac-logout]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                apiPost('logout').then(function () {
                    window.location.href = 'cont.php';
                });
            });
        });
    }

    function initDashboardNav() {
        var links = $all('.ac-nav-link');
        var panels = $all('.ac-panel');
        if (!links.length) return;

        function activate(view) {
            if (!view || !document.querySelector('.ac-panel[data-view="' + view + '"]')) {
                return;
            }
            links.forEach(function (link) {
                link.classList.toggle('active', link.getAttribute('data-view') === view);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-view') === view);
            });
            if (history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('view', view);
                history.replaceState({}, '', url.pathname + '?' + url.searchParams.toString());
            }
        }

        links.forEach(function (link) {
            var view = link.getAttribute('data-view');
            if (!view) {
                return;
            }
            link.addEventListener('click', function (e) {
                e.preventDefault();
                activate(view);
            });
        });

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-view]');
            if (!trigger || trigger.classList.contains('ac-nav-link') || trigger.classList.contains('ac-panel')) {
                return;
            }
            var view = trigger.getAttribute('data-view');
            if (!view || !document.querySelector('.ac-panel[data-view="' + view + '"]')) {
                return;
            }
            e.preventDefault();
            activate(view);
        });

        var initial = new URLSearchParams(window.location.search).get('view') || 'dashboard';
        if (!document.querySelector('.ac-panel[data-view="' + initial + '"]')) {
            initial = 'dashboard';
        }
        activate(initial);
    }

    function initProfileForm() {
        var form = $('#ac-profile-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideStatus();
            setLoading(form, true);
            apiPost('update_profile', formData(form))
                .then(function (res) {
                    showStatus($('#ac-global-status'), res.message, 'success');
                })
                .catch(function (err) {
                    showStatus($('#ac-global-status'), err.message, 'error');
                })
                .finally(function () {
                    setLoading(form, false);
                });
        });
    }

    function initPasswordForm() {
        var form = $('#ac-password-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideStatus();
            setLoading(form, true);
            apiPost('change_password', formData(form))
                .then(function (res) {
                    form.reset();
                    showStatus($('#ac-global-status'), res.message, 'success');
                })
                .catch(function (err) {
                    showStatus($('#ac-global-status'), err.message, 'error');
                })
                .finally(function () {
                    setLoading(form, false);
                });
        });
    }

    function formatMoney(value) {
        var num = parseFloat(value) || 0;
        return num.toFixed(2) + ' RON';
    }

    function formatDate(value) {
        if (!value) return '—';
        var d = new Date(value.replace(' ', 'T'));
        if (isNaN(d.getTime())) return value;
        return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function statusClass(status) {
        var map = {
            noua: 'is-new',
            in_lucru: 'is-progress',
            confirmata: 'is-progress',
            platita: 'is-done',
            expediata: 'is-shipped',
            finalizata: 'is-done',
            livrata: 'is-done',
            retur: 'is-cancel',
            anulata: 'is-cancel'
        };
        return map[status] || 'is-new';
    }

    function paymentClass(state) {
        var map = {
            paid: 'is-paid',
            pending: 'is-pending',
            failed: 'is-failed'
        };
        return map[state] || 'is-pending';
    }

    function itemStatusLabel(order) {
        if (order.order_status === 'anulata') {
            return 'Anulat';
        }
        if (order.order_status === 'expediata' || order.delivery_status === 'expediata') {
            return 'Expediat';
        }
        if (order.order_status === 'finalizata' || order.order_status === 'platita') {
            return 'Procesat';
        }
        if (order.order_status === 'in_lucru') {
            return 'În pregătire';
        }
        return 'Comandat';
    }

    function renderOrderItem(order, item) {
        var image = item.product_image || 'assets/images/products/product-4.jpg';
        var priceLabel = Number(item.price) > 0 ? formatMoney(item.price) + '/buc' : '';
        var metaParts = ['Cantitate: ' + (item.quantity || 1)];
        if (item.oem) metaParts.push('OEM: ' + item.oem);
        if (priceLabel) metaParts.push(priceLabel);

        return '<div class="ac-order-item">' +
            '<img class="ac-order-item-img" src="' + escapeHtml(image) + '" alt="">' +
            '<div class="ac-order-item-body">' +
                '<p class="ac-order-item-name">' + escapeHtml(item.product_name || 'Produs') + '</p>' +
                '<div class="ac-order-item-meta">' + escapeHtml(metaParts.join(' · ')) + '</div>' +
            '</div>' +
            '<div class="ac-order-item-actions">' +
                '<span class="ac-order-item-status">' + escapeHtml(itemStatusLabel(order)) + '</span>' +
                '<a class="ac-order-btn ac-order-btn--view" href="' + escapeHtml(item.product_url || '/catalog') + '">' +
                    '<i class="fa-solid fa-eye"></i> Vezi produs' +
                '</a>' +
            '</div>' +
        '</div>';
    }

    function renderOrders(orders) {
        var wrap = $('#ac-orders-list');
        var empty = $('#ac-orders-empty');
        if (!wrap) return;

        if (!orders || !orders.length) {
            wrap.innerHTML = '';
            if (empty) empty.style.display = 'block';
            return;
        }

        if (empty) empty.style.display = 'none';
        wrap.innerHTML = orders.map(function (order) {
            var orderLabel = order.order_status_label || order.order_status || 'Comandă nouă';
            var paymentLabel = order.payment_state_label || 'De achitat';
            var paymentMethod = order.payment_method_label || order.payment_status || '—';
            var deliveryMethod = order.delivery_method_label || order.delivery_method || '—';
            var deliveryStatus = order.delivery_status_label || order.delivery_status || '—';
            var items = Array.isArray(order.items) && order.items.length
                ? order.items
                : [{
                    product_name: order.product_name || 'Produs',
                    quantity: order.quantity || 1,
                    price: 0,
                    product_image: order.product_image || '',
                    product_url: '/catalog'
                }];

            var itemsHtml = items.map(function (item) {
                return renderOrderItem(order, item);
            }).join('');

            var cancelBtn = order.can_cancel
                ? '<button type="button" class="ac-order-btn ac-order-btn--cancel" data-cancel-order="' + order.id + '">' +
                    '<i class="fa-solid fa-ban"></i> Anulează comanda' +
                  '</button>'
                : '';

            return '<article class="ac-order-card" data-order-id="' + order.id + '">' +
                '<div class="ac-order-head">' +
                    '<div class="ac-order-top">' +
                        '<div>' +
                            '<div class="ac-order-id">' + escapeHtml(order.order_number || ('#' + order.id)) + '</div>' +
                            '<span class="ac-order-date">' + formatDate(order.created_at) + '</span>' +
                        '</div>' +
                        '<span class="ac-order-badge ' + statusClass(order.order_status) + '">' + escapeHtml(orderLabel) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ac-order-info">' +
                    '<div class="ac-order-info-block">' +
                        '<span class="ac-order-info-label">Plată</span>' +
                        '<span class="ac-order-info-value">' +
                            escapeHtml(paymentMethod) +
                            ' <span class="ac-order-badge ' + paymentClass(order.payment_state) + '">' + escapeHtml(paymentLabel) + '</span>' +
                        '</span>' +
                    '</div>' +
                    '<div class="ac-order-info-block">' +
                        '<span class="ac-order-info-label">Livrare</span>' +
                        '<span class="ac-order-info-value">' +
                            escapeHtml(deliveryMethod) +
                            ' <span class="ac-order-badge is-progress">' + escapeHtml(deliveryStatus) + '</span>' +
                        '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ac-order-items">' +
                    '<h3 class="ac-order-items-title">Produse (' + items.length + ')</h3>' +
                    itemsHtml +
                '</div>' +
                '<div class="ac-order-foot">' +
                    '<div class="ac-order-total">Total comandă: <strong>' + formatMoney(order.total_amount) + '</strong></div>' +
                    cancelBtn +
                '</div>' +
            '</article>';
        }).join('');
    }

    function bindOrderActions() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-cancel-order]');
            if (!button) return;

            event.preventDefault();
            var orderId = Number(button.getAttribute('data-cancel-order'));
            if (!orderId) return;

            if (!window.confirm('Sigur vrei să anulezi această comandă?')) {
                return;
            }

            button.disabled = true;
            apiPost('cancel_order', { order_id: orderId })
                .then(function (res) {
                    showStatus($('#ac-global-status'), res.message, 'success');
                    loadOrders();
                })
                .catch(function (err) {
                    showStatus($('#ac-global-status'), err.message, 'error');
                    button.disabled = false;
                });
        });
    }

    var CART_KEY = 'besoiu_cart';
    var DEFAULT_CART_IMAGE = 'assets/images/products/product-4.jpg';

    function readLocalCart() {
        try {
            var items = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            if (!Array.isArray(items)) return [];
            return items.map(function (item) {
                var quantity = Math.max(1, Number(item.quantity) || 1);
                var price = Number(item.price) || 0;
                return {
                    product_id: item.product_id || '',
                    product_name: item.product_name || 'Produs',
                    product_image: item.product_image || DEFAULT_CART_IMAGE,
                    oem: item.oem || '',
                    quantity: quantity,
                    price: price,
                    total_amount: Number(item.total_amount) || (quantity * price)
                };
            });
        } catch (e) {
            return [];
        }
    }

    function cartProductUrl(item) {
        if (item.product_id) {
            return '/produs?id=' + encodeURIComponent(item.product_id);
        }
        if (item.oem) {
            return '/catalog?q=' + encodeURIComponent(item.oem);
        }
        return '/catalog?q=' + encodeURIComponent(item.product_name || '');
    }

    function orderProgressStep(status) {
        if (status === 'anulata' || status === 'retur') return -1;
        if (['finalizata', 'livrata'].indexOf(status) !== -1) return 3;
        if (status === 'expediata') return 2;
        if (['in_lucru', 'confirmata', 'platita'].indexOf(status) !== -1) return 1;
        return 0;
    }

    function renderOrderSteps(order) {
        var steps = [
            { label: 'Plasată' },
            { label: 'În procesare' },
            { label: 'Expediată' },
            { label: 'Livrată' }
        ];
        var current = orderProgressStep(order.order_status || 'noua');
        if (current < 0) {
            return '<div class="ac-order-badge is-cancel" style="display:inline-block;margin-top:8px;">' +
                escapeHtml(order.order_status_label || 'Anulată') + '</div>';
        }
        return '<div class="ac-order-steps">' + steps.map(function (step, index) {
            var cls = 'ac-order-step';
            if (index < current) cls += ' is-done';
            if (index === current) cls += ' is-active';
            return '<div class="' + cls + '">' +
                '<div class="ac-order-step-dot">' + (index < current ? '<i class="fa-solid fa-check"></i>' : (index + 1)) + '</div>' +
                '<div class="ac-order-step-label">' + step.label + '</div>' +
            '</div>';
        }).join('') + '</div>';
    }

    function renderLastOrder(order) {
        var wrap = $('#ac-dash-last-order');
        if (!wrap) return;

        if (!order) {
            wrap.innerHTML = '<div class="ac-dash-empty">' +
                '<i class="fa-solid fa-box-open"></i>' +
                'Nu ai comenzi încă. <a href="/catalog" class="ac-link">Plasează prima comandă</a>.' +
            '</div>';
            return;
        }

        var items = Array.isArray(order.items) && order.items.length ? order.items : [{
            product_name: order.product_name || 'Produs',
            product_image: order.product_image || DEFAULT_CART_IMAGE
        }];
        var first = items[0];
        var image = first.product_image || DEFAULT_CART_IMAGE;
        var title = first.product_name || order.product_name || 'Produs';
        if (items.length > 1) {
            title += ' (+' + (items.length - 1) + ' produse)';
        }

        wrap.innerHTML =
            '<div class="ac-last-order-head">' +
                '<div>' +
                    '<div class="ac-last-order-id">' + escapeHtml(order.order_number || ('#' + order.id)) + '</div>' +
                    '<div class="ac-last-order-date">' + formatDate(order.created_at) + '</div>' +
                '</div>' +
                '<span class="ac-order-badge ' + statusClass(order.order_status) + '">' +
                    escapeHtml(order.order_status_label || order.order_status) +
                '</span>' +
            '</div>' +
            '<div class="ac-last-order-product">' +
                '<img src="' + escapeHtml(image) + '" alt="">' +
                '<div>' +
                    '<h4>' + escapeHtml(title) + '</h4>' +
                    '<div class="ac-last-order-price">' + formatMoney(order.total_amount) + '</div>' +
                '</div>' +
            '</div>' +
            renderOrderSteps(order);
    }

    function renderActivityCards(orders) {
        var wrap = $('#ac-dash-activity');
        if (!wrap) return;

        var cart = readLocalCart();
        var favCount = 0;
        try {
            var favItems = JSON.parse(localStorage.getItem('besoiu_favorites') || '[]');
            favCount = Array.isArray(favItems) ? favItems.filter(Boolean).length : 0;
        } catch (e) {
            favCount = 0;
        }
        var cartQty = cart.reduce(function (sum, item) {
            return sum + (Number(item.quantity) || 0);
        }, 0);
        var cartTotal = cart.reduce(function (sum, item) {
            return sum + (Number(item.total_amount) || 0);
        }, 0);
        var orderCount = (orders || []).length;
        var firstCart = cart[0];

        var cartPreview = firstCart
            ? '<div class="ac-activity-preview">' +
                '<img src="' + escapeHtml(firstCart.product_image) + '" alt="">' +
                '<span>' + escapeHtml(firstCart.product_name) + '</span>' +
              '</div>'
            : '';

        wrap.innerHTML =
            '<article class="ac-activity-card">' +
                '<div class="ac-activity-top">' +
                    '<div class="ac-activity-icon"><i class="fa-solid fa-cart-shopping"></i></div>' +
                    '<div class="ac-activity-count" id="ac-activity-cart-count">' + cartQty + '</div>' +
                '</div>' +
                '<h4>Produse în coș</h4>' +
                '<p>' + (cartQty ? cartQty + ' produse · ' + formatMoney(cartTotal) : 'Coșul este gol momentan') + '</p>' +
                cartPreview +
                '<a href="/cart" class="ac-activity-link">Vezi coșul <i class="fa-solid fa-arrow-right"></i></a>' +
            '</article>' +
            '<article class="ac-activity-card">' +
                '<div class="ac-activity-top">' +
                    '<div class="ac-activity-icon"><i class="fa-solid fa-box"></i></div>' +
                    '<div class="ac-activity-count">' + orderCount + '</div>' +
                '</div>' +
                '<h4>Comenzi recente</h4>' +
                '<p>' + (orderCount ? 'Ultimele comenzi plasate din contul tău' : 'Nu ai comenzi încă') + '</p>' +
                '<button type="button" class="ac-activity-link" data-view="orders">Vezi istoricul <i class="fa-solid fa-arrow-right"></i></button>' +
            '</article>' +
            '<article class="ac-activity-card">' +
                '<div class="ac-activity-top">' +
                    '<div class="ac-activity-icon"><i class="fa-solid fa-heart"></i></div>' +
                    '<div class="ac-activity-count">' + favCount + '</div>' +
                '</div>' +
                '<h4>Favorite</h4>' +
                '<p>' + (favCount ? favCount + ' produse salvate' : 'Salvează piese preferate din catalog') + '</p>' +
                '<button type="button" class="ac-activity-link" data-view="favorites">' +
                    (favCount ? 'Vezi lista' : 'Deschide favorite') +
                    ' <i class="fa-solid fa-arrow-right"></i></button>' +
            '</article>';

        document.querySelectorAll('.cart-count, [data-cart-count]').forEach(function (badge) {
            badge.textContent = String(cartQty);
            badge.style.display = cartQty > 0 ? 'inline-flex' : 'none';
        });
    }

    function renderDashboardCart() {
        renderActivityCards(window.__acLastOrders || []);
    }

    function initDashboardCart() {
        renderDashboardCart();
        window.addEventListener('besoiu:cart-updated', renderDashboardCart);
        window.addEventListener('storage', function (event) {
            if (event.key === CART_KEY) {
                renderDashboardCart();
            }
        });
    }

    function computeDashboardStats(orders) {
        var list = orders || [];
        var active = 0;
        var delivered = 0;
        var spent = 0;

        list.forEach(function (order) {
            var status = order.order_status || '';
            if (['noua', 'in_lucru', 'confirmata', 'platita', 'expediata'].indexOf(status) !== -1) {
                active += 1;
            }
            if (['finalizata', 'livrata', 'expediata'].indexOf(status) !== -1) {
                delivered += 1;
            }
            if (status !== 'anulata') {
                spent += parseFloat(order.total_amount) || 0;
            }
        });

        return {
            total: list.length,
            active: active,
            delivered: delivered,
            spent: spent
        };
    }

    var __acChartInstances = [];

    function destroyAcCharts() {
        __acChartInstances.forEach(function (chart) {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        __acChartInstances = [];
    }

    function getLastMonths(count) {
        var months = [];
        var now = new Date();
        var i;
        for (i = count - 1; i >= 0; i--) {
            var d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            months.push({
                key: d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'),
                label: d.toLocaleDateString('ro-RO', { month: 'short' })
            });
        }
        return months;
    }

    function buildMonthlyStats(orders) {
        var months = getLastMonths(6);
        var orderCounts = months.map(function () { return 0; });
        var spent = months.map(function () { return 0; });
        var delivered = months.map(function () { return 0; });
        var active = months.map(function () { return 0; });

        (orders || []).forEach(function (order) {
            var d = new Date(String(order.created_at || '').replace(' ', 'T'));
            if (isNaN(d.getTime())) return;
            var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            var idx = -1;
            months.forEach(function (month, monthIndex) {
                if (month.key === key) idx = monthIndex;
            });
            if (idx < 0) return;

            orderCounts[idx] += 1;
            var status = order.order_status || '';
            if (['noua', 'in_lucru', 'confirmata', 'platita', 'expediata'].indexOf(status) !== -1) {
                active[idx] += 1;
            }
            if (['finalizata', 'livrata', 'expediata'].indexOf(status) !== -1) {
                delivered[idx] += 1;
            }
            if (status !== 'anulata') {
                spent[idx] += parseFloat(order.total_amount) || 0;
            }
        });

        return {
            months: months,
            orderCounts: orderCounts,
            spent: spent,
            delivered: delivered,
            active: active
        };
    }

    function miniChartOptions(color) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: '#0f172a',
                    padding: 8,
                    titleFont: { size: 11 },
                    bodyFont: { size: 11 }
                }
            },
            scales: {
                x: {
                    display: false,
                    grid: { display: false }
                },
                y: {
                    display: false,
                    beginAtZero: true,
                    grid: { display: false }
                }
            },
            elements: {
                bar: { borderRadius: 4 },
                line: { tension: 0.35, borderWidth: 2 },
                point: { radius: 0, hitRadius: 8, hoverRadius: 3 }
            },
            datasets: {
                bar: { maxBarThickness: 14 }
            },
            interaction: { intersect: false, mode: 'index' }
        };
    }

    function mainChartOptions(isMoney) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10,
                    callbacks: isMoney ? {
                        label: function (ctx) {
                            return (parseFloat(ctx.raw) || 0).toFixed(2) + ' RON';
                        }
                    } : undefined
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10, weight: '600' }, color: '#64748b', maxRotation: 0 }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef2f7' },
                    ticks: {
                        font: { size: 10 },
                        color: '#64748b',
                        maxTicksLimit: 5,
                        callback: isMoney
                            ? function (value) { return value >= 1000 ? (value / 1000) + 'k' : value; }
                            : undefined
                    }
                }
            },
            elements: {
                bar: { borderRadius: 6, maxBarThickness: 28 },
                line: { tension: 0.35, borderWidth: 2 },
                point: { radius: 2, hoverRadius: 4, borderWidth: 2, backgroundColor: '#fff' }
            }
        };
    }

    function createAcChart(canvasId, config) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;
        var chart = new Chart(canvas, config);
        __acChartInstances.push(chart);
        return chart;
    }

    function renderDashboardCharts(orders) {
        if (typeof Chart === 'undefined') return;

        destroyAcCharts();
        var monthly = buildMonthlyStats(orders || []);
        var labels = monthly.months.map(function (m) { return m.label; });

        createAcChart('ac-chart-kpi-orders', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: monthly.orderCounts,
                    backgroundColor: 'rgba(5,150,105,.85)',
                    borderColor: '#059669',
                    borderWidth: 1
                }]
            },
            options: miniChartOptions('#059669')
        });

        createAcChart('ac-chart-kpi-active', {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: monthly.active,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.12)',
                    fill: true
                }]
            },
            options: miniChartOptions('#2563eb')
        });

        createAcChart('ac-chart-kpi-delivered', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: monthly.delivered,
                    backgroundColor: 'rgba(13,148,136,.85)',
                    borderColor: '#0d9488',
                    borderWidth: 1
                }]
            },
            options: miniChartOptions('#0d9488')
        });

        createAcChart('ac-chart-kpi-spent', {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: monthly.spent,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,.12)',
                    fill: true
                }]
            },
            options: miniChartOptions('#7c3aed')
        });

        createAcChart('ac-chart-monthly-orders', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Comenzi',
                    data: monthly.orderCounts,
                    backgroundColor: 'rgba(5,150,105,.78)',
                    borderColor: '#047857',
                    borderWidth: 1
                }]
            },
            options: mainChartOptions(false)
        });

        createAcChart('ac-chart-monthly-spent', {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cheltuieli',
                    data: monthly.spent,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5,150,105,.15)',
                    fill: true
                }]
            },
            options: mainChartOptions(true)
        });
    }

    function renderDashboard(orders) {
        window.__acLastOrders = orders || [];
        var stats = computeDashboardStats(orders);
        var totalEl = $('#ac-stat-orders');
        var activeEl = $('#ac-stat-active');
        var deliveredEl = $('#ac-stat-delivered');
        var spentEl = $('#ac-stat-spent');

        if (totalEl) totalEl.textContent = String(stats.total);
        if (activeEl) activeEl.textContent = String(stats.active);
        if (deliveredEl) deliveredEl.textContent = String(stats.delivered);
        if (spentEl) {
            spentEl.textContent = stats.spent >= 1000
                ? stats.spent.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON'
                : formatMoney(stats.spent);
        }

        renderLastOrder(orders && orders.length ? orders[0] : null);
        renderActivityCards(orders);
        renderProfileProgress();
        renderDashboardCharts(orders);
    }

    function renderProfileProgress() {
        var panel = $('.ac-panel[data-view="dashboard"]');
        if (!panel) return;

        var fields = [
            panel.getAttribute('data-profile-name'),
            panel.getAttribute('data-profile-email'),
            panel.getAttribute('data-profile-phone'),
            panel.getAttribute('data-profile-city'),
            panel.getAttribute('data-profile-address')
        ];
        var filled = fields.filter(function (value) {
            return String(value || '').trim() !== '';
        }).length;
        var pct = Math.round((filled / fields.length) * 100);
        var fill = $('#ac-dash-progress-fill');
        var pctEl = $('#ac-dash-progress-pct');
        var hint = $('#ac-dash-progress-hint');

        if (fill) fill.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%';
        if (hint) {
            if (pct >= 100) {
                hint.textContent = 'Profil complet. Checkout-ul va fi precompletat automat.';
            } else if (!String(panel.getAttribute('data-profile-phone') || '').trim()) {
                hint.textContent = 'Adaugă telefonul pentru confirmări rapide ale comenzii.';
            } else {
                hint.textContent = 'Completează adresa de livrare pentru checkout mai rapid.';
            }
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function loadOrders() {
        if (!$('#ac-orders-list') && !$('#ac-dash-last-order')) return;
        apiPost('orders')
            .then(function (res) {
                var orders = res.orders || [];
                renderOrders(orders);
                renderDashboard(orders);
            })
            .catch(function () {
                renderOrders([]);
                renderDashboard([]);
            });
    }

    function initFavorites() {
        var listEl = $('#ac-favorites-list');
        var emptyEl = $('#ac-favorites-empty');
        if (!listEl) return;

        var FAV_KEY = 'besoiu_favorites';

        function readFavorites() {
            try {
                var items = JSON.parse(localStorage.getItem(FAV_KEY) || '[]');
                return Array.isArray(items) ? items.map(String).filter(Boolean) : [];
            } catch (e) {
                return [];
            }
        }

        function renderFavorites() {
            var favorites = readFavorites();
            if (!favorites.length) {
                listEl.innerHTML = '';
                if (emptyEl) emptyEl.style.display = '';
                return;
            }

            if (emptyEl) emptyEl.style.display = 'none';
            listEl.innerHTML = favorites.map(function (productId) {
                var href = '/produs?id=' + encodeURIComponent(productId);
                return '<a class="ac-favorite-item" href="' + escapeHtml(href) + '">' +
                    '<span class="ac-favorite-id"><i class="fa-solid fa-heart"></i> Produs #' + escapeHtml(productId) + '</span>' +
                    '<span class="ac-favorite-link">Vezi produsul →</span>' +
                    '</a>';
            }).join('');
        }

        renderFavorites();
        window.addEventListener('besoiu:favorites-updated', renderFavorites);
        window.addEventListener('storage', function (e) {
            if (e.key === FAV_KEY) {
                renderFavorites();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAuthTabs();
        initLoginForm();
        initRegisterForm();
        initLogout();
        initDashboardNav();
        initProfileForm();
        initPasswordForm();
        initFavorites();
        bindOrderActions();
        initDashboardCart();
        loadOrders();
    });
})();
