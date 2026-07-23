(function () {
  const panel = document.getElementById('home-scraper-products');
  if (!panel) return;

  const tabs = panel.querySelectorAll('.home-special-tab[data-scraper-tab]');
  const cards = panel.querySelectorAll('[data-scraper-product]');
  const countEl = document.getElementById('home-scraper-count');

  function setActive(slug) {
    tabs.forEach((tab) => {
      const active = tab.getAttribute('data-scraper-tab') === slug;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    let visible = 0;
    cards.forEach((card) => {
      const cat = card.getAttribute('data-category') || '';
      const show = slug === 'toate' || cat === slug;
      card.classList.toggle('is-hidden', !show);
      card.hidden = !show;
      if (show) visible++;
    });

    if (countEl) countEl.textContent = String(visible);
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => setActive(tab.getAttribute('data-scraper-tab') || 'toate'));
  });

  setActive('toate');
})();
