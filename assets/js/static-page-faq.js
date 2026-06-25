(function () {
  'use strict';

  document.querySelectorAll('.sp-faq-q').forEach(function (question) {
    question.setAttribute('role', 'button');
    question.setAttribute('tabindex', '0');
    question.setAttribute('aria-expanded', question.closest('.sp-faq-item')?.classList.contains('open') ? 'true' : 'false');

    function toggleItem() {
      var item = question.closest('.sp-faq-item');
      if (!item) {
        return;
      }
      var isOpen = item.classList.contains('open');
      document.querySelectorAll('.sp-faq-item.open').forEach(function (openItem) {
        openItem.classList.remove('open');
        var q = openItem.querySelector('.sp-faq-q');
        if (q) {
          q.setAttribute('aria-expanded', 'false');
        }
      });
      if (!isOpen) {
        item.classList.add('open');
        question.setAttribute('aria-expanded', 'true');
      }
    }

    question.addEventListener('click', toggleItem);
    question.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleItem();
      }
    });
  });
})();
