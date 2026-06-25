/**
 * Coș minimal — badge header pe pagini fără carduri (legal, contact, about)
 */
(function () {
    'use strict';

    var CART_KEY = 'besoiu_cart';

    function readCart() {
        try {
            var items = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            return Array.isArray(items) ? items : [];
        } catch (e) {
            return [];
        }
    }

    function cartQuantity(items) {
        return items.reduce(function (sum, item) {
            return sum + (Number(item.quantity) || 0);
        }, 0);
    }

    function updateCartBadge() {
        var quantity = cartQuantity(readCart());
        document.querySelectorAll('.cart-count, [data-cart-count]').forEach(function (badge) {
            badge.textContent = String(quantity);
            badge.style.display = quantity > 0 ? 'inline-flex' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', updateCartBadge);
    window.addEventListener('besoiu:cart-updated', updateCartBadge);
    window.addEventListener('storage', function (e) {
        if (e.key === CART_KEY) {
            updateCartBadge();
        }
    });
})();
