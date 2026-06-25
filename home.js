/**
 * Header search + pulse — legat de index.php / header.php
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var searchBtn = document.getElementById('_home-search-btn');
  var searchInput = document.getElementById('_home-product-name');
  var addToCartBtn = document.getElementById('_home-add-to-cart-btn');

  function pulseButton(el) {
    if (!el) {
      return;
    }
    el.classList.add('_home-is-active');
    window.setTimeout(function () {
      el.classList.remove('_home-is-active');
    }, 180);
  }

  if (searchBtn && searchInput) {
    searchBtn.addEventListener('click', function () {
      pulseButton(searchBtn);
      var query = searchInput.value.trim();
      if (query === '') {
        return;
      }

      var isHome = /index\.php$/i.test(window.location.pathname)
        || window.location.pathname.endsWith('/')
        || window.location.pathname === '';

      if (isHome) {
        window.location.href = '/catalog?q=' + encodeURIComponent(query);
        return;
      }

      window.location.href = '/catalog?search=' + encodeURIComponent(query);
    });

    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchBtn.click();
      }
    });
  }

  if (addToCartBtn) {
    addToCartBtn.addEventListener('click', function () {
      pulseButton(addToCartBtn);
    });
  }

  var chatTrigger = document.getElementById('help-ai-chat-trigger');
  if (chatTrigger) {
    chatTrigger.addEventListener('click', function () {
      if (typeof window.bpaOpenChat === 'function') {
        window.bpaOpenChat();
        return;
      }
      var fab = document.getElementById('bpa-fab');
      if (fab) {
        fab.click();
      }
    });
  }
});
