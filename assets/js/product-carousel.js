/**
 * Product page — Owl Carousel + cantitate (necesită jQuery + Owl)
 */
(function ($) {
  'use strict';

  $(document).ready(function () {
    var $mainCarousel = $('.product-single-carousel');

    if ($mainCarousel.length) {
      $mainCarousel.owlCarousel({
        items: 1,
        loop: true,
        nav: true,
        dots: false,
        margin: 0,
        autoplay: false,
        smartSpeed: 600,
        mouseDrag: true,
        touchDrag: true,
        pullDrag: true,
        navText: [
          '<span aria-label="Anterior">‹</span>',
          '<span aria-label="Următor">›</span>'
        ]
      });

      $('.prod-thumbnail .owl-dot').each(function (index) {
        $(this).on('click', function () {
          $mainCarousel.trigger('to.owl.carousel', [index, 500]);
          $('.prod-thumbnail .owl-dot').removeClass('active');
          $(this).addClass('active');
        });
      });

      $('.prod-thumbnail .owl-dot').first().addClass('active');

      $mainCarousel.on('changed.owl.carousel', function (event) {
        var index = event.item.index - (event.relatedTarget._clones.length / 2);
        var count = event.item.count;
        if (index < 0) {
          index = count - 1;
        }
        if (index >= count) {
          index = 0;
        }
        $('.prod-thumbnail .owl-dot').removeClass('active');
        $('.prod-thumbnail .owl-dot').eq(index).addClass('active');
      });
    }

    function qtyValue($input) {
      var val = parseInt($input.val(), 10);
      return Number.isFinite(val) && val > 0 ? val : 1;
    }

    $('.qty-minus').on('click', function () {
      var $input = $(this).siblings('.horizontal-quantity');
      var val = qtyValue($input);
      if (val > 1) {
        $input.val(val - 1);
      }
    });

    $('.qty-plus').on('click', function () {
      var $input = $(this).siblings('.horizontal-quantity');
      var val = qtyValue($input);
      if (val < 99) {
        $input.val(val + 1);
      }
    });

    var tabRoot = document.getElementById('productTabs');
    if (tabRoot) {
      tabRoot.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var targetSel = btn.getAttribute('data-bs-target');
          if (!targetSel) return;

          tabRoot.querySelectorAll('.nav-link').forEach(function (link) {
            link.classList.remove('active');
            link.setAttribute('aria-selected', 'false');
          });
          btn.classList.add('active');
          btn.setAttribute('aria-selected', 'true');

          var content = document.getElementById('productTabsContent');
          if (!content) return;
          content.querySelectorAll('.tab-pane').forEach(function (pane) {
            pane.classList.remove('active', 'show');
          });
          var pane = content.querySelector(targetSel);
          if (pane) {
            pane.classList.add('active', 'show');
          }
        });
      });
    }

    var FAV_KEY = 'besoiu_favorites';

    function readFavorites() {
      try {
        var items = JSON.parse(localStorage.getItem(FAV_KEY) || '[]');
        return Array.isArray(items) ? items.map(String) : [];
      } catch (e) {
        return [];
      }
    }

    function writeFavorites(ids) {
      localStorage.setItem(FAV_KEY, JSON.stringify(ids));
      window.dispatchEvent(new CustomEvent('besoiu:favorites-updated'));
    }

    function showProductToast(message) {
      var toast = document.getElementById('prod-action-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'prod-action-toast';
        toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;background:#111;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.2);opacity:0;transition:opacity .25s;pointer-events:none';
        document.body.appendChild(toast);
      }
      toast.textContent = message;
      toast.style.opacity = '1';
      clearTimeout(toast._hideTimer);
      toast._hideTimer = setTimeout(function () {
        toast.style.opacity = '0';
      }, 2600);
    }

    function syncFavoriteButton(btn) {
      if (!btn) return;
      var productId = String(btn.dataset.productId || '').trim();
      if (!productId) return;
      var isFavorite = readFavorites().indexOf(productId) !== -1;
      btn.classList.toggle('is-favorite', isFavorite);
      btn.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-regular', !isFavorite);
        icon.classList.toggle('fa-solid', isFavorite);
      }
    }

    var favBtn = document.getElementById('prod-fav-btn');
    syncFavoriteButton(favBtn);
    if (favBtn) {
      favBtn.addEventListener('click', function () {
        var productId = String(favBtn.dataset.productId || '').trim();
        if (!productId) return;
        var favorites = readFavorites();
        var index = favorites.indexOf(productId);
        if (index === -1) {
          favorites.push(productId);
          showProductToast('Produs adăugat la favorite.');
        } else {
          favorites.splice(index, 1);
          showProductToast('Produs eliminat din favorite.');
        }
        writeFavorites(favorites);
        syncFavoriteButton(favBtn);
      });
    }

    var shareBtn = document.getElementById('prod-share-btn');
    if (shareBtn) {
      shareBtn.addEventListener('click', function () {
        var shareUrl = shareBtn.dataset.shareUrl || window.location.href;
        var shareTitle = shareBtn.dataset.shareTitle || document.title;
        var shareText = 'Vezi acest produs pe Besoiu Piese Auto';

        if (navigator.share) {
          navigator.share({ title: shareTitle, text: shareText, url: shareUrl }).catch(function () {});
          return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(shareUrl).then(function () {
            showProductToast('Link copiat în clipboard.');
          }).catch(function () {
            showProductToast(shareUrl);
          });
          return;
        }

        showProductToast(shareUrl);
      });
    }
  });
})(window.jQuery);
