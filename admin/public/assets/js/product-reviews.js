/**
 * Stele evaluare recenzii produs (UI).
 */
(function () {
  'use strict';

  function initReviewStars(root) {
    var scope = root || document;
    scope.querySelectorAll('.besoiu-review-rating-input').forEach(function (wrap) {
      if (wrap.dataset.ready === '1') return;
      wrap.dataset.ready = '1';
      var form = wrap.closest('form');
      var select = form ? form.querySelector('select[name="rating"], #besoiu-review-rating') : null;
      var buttons = wrap.querySelectorAll('.besoiu-review-star-btn');

      function paint(val) {
        buttons.forEach(function (btn, idx) {
          var icon = btn.querySelector('i');
          if (!icon) return;
          icon.className = idx < val ? 'fa-solid fa-star' : 'fa-regular fa-star';
        });
      }

      buttons.forEach(function (btn, idx) {
        btn.addEventListener('click', function () {
          var val = idx + 1;
          paint(val);
          if (select) select.value = String(val);
        });
      });
    });
  }

  function boot() {
    initReviewStars(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  document.addEventListener('shown.bs.tab', function () {
    initReviewStars(document);
  });

  window.besoiuInitProductReviewStars = initReviewStars;
})();
