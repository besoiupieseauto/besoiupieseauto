(function () {
  'use strict';

  const tree = document.getElementById('besoiuTree');
  if (!tree) return;

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function setChildrenOpen(wrapper, open, animate) {
    if (!wrapper) return;
    if (open) {
      wrapper.classList.add('is-open');
      wrapper.style.setProperty('--cp-tree-open', '1');
    } else {
      wrapper.classList.remove('is-open');
      wrapper.style.setProperty('--cp-tree-open', '0');
    }
    const row = wrapper.previousElementSibling;
    if (row && row.classList.contains('cp-tree-row')) {
      row.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    const toggle = row ? row.querySelector('[data-tree-toggle]') : null;
    if (toggle) {
      toggle.classList.toggle('is-open', open);
    }
    if (animate && open && !prefersReduced) {
      wrapper.classList.add('is-animating');
      const nodes = wrapper.querySelectorAll(':scope > .cp-tree-children__inner > .cp-tree-list > .cp-tree-node');
      nodes.forEach(function (node, i) {
        node.style.setProperty('--cp-stagger', String(Math.min(i * 0.025, 0.4)));
      });
      window.setTimeout(function () {
        wrapper.classList.remove('is-animating');
      }, 450);
    }
  }

  function nodeLevel(node) {
    return parseInt(node.getAttribute('data-level') || '1', 10) || 1;
  }

  function walkNodes(cb) {
    tree.querySelectorAll('.cp-tree-node').forEach(cb);
  }

  function expandToLevel(maxLevel, animate) {
    walkNodes(function (node) {
      const wrapper = node.querySelector(':scope > .cp-tree-children');
      if (!wrapper) return;
      setChildrenOpen(wrapper, nodeLevel(node) < maxLevel, animate);
    });
  }

  tree.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-tree-toggle]');
    if (!btn || !tree.contains(btn)) return;
    e.preventDefault();
    e.stopPropagation();
    const node = btn.closest('.cp-tree-node');
    const wrapper = node ? node.querySelector(':scope > .cp-tree-children') : null;
    if (!wrapper) return;
    setChildrenOpen(wrapper, !wrapper.classList.contains('is-open'), true);
  });

  tree.addEventListener('keydown', function (e) {
    const row = e.target.closest('.cp-tree-row');
    if (!row) return;
    const node = row.closest('.cp-tree-node');
    const wrapper = node ? node.querySelector(':scope > .cp-tree-children') : null;
    if (e.key === 'Enter' || e.key === ' ') {
      if (wrapper) {
        e.preventDefault();
        setChildrenOpen(wrapper, !wrapper.classList.contains('is-open'), true);
      }
    }
    if (e.key === 'ArrowRight' && wrapper && !wrapper.classList.contains('is-open')) {
      e.preventDefault();
      setChildrenOpen(wrapper, true, true);
    }
    if (e.key === 'ArrowLeft' && wrapper && wrapper.classList.contains('is-open')) {
      e.preventDefault();
      setChildrenOpen(wrapper, false, true);
    }
  });

  tree.querySelectorAll('[data-tree-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const action = btn.getAttribute('data-tree-action');
      if (action === 'expand-all') {
        walkNodes(function (node) {
          const wrapper = node.querySelector(':scope > .cp-tree-children');
          if (wrapper) setChildrenOpen(wrapper, true, true);
        });
      } else if (action === 'collapse-all') {
        walkNodes(function (node) {
          const wrapper = node.querySelector(':scope > .cp-tree-children');
          if (wrapper) setChildrenOpen(wrapper, false, false);
        });
      } else if (action === 'expand-l1') {
        expandToLevel(1, true);
      } else if (action === 'expand-l2') {
        expandToLevel(2, true);
      }
    });
  });

  const density = document.getElementById('besoiuTreeDensity');
  if (density) {
    density.addEventListener('input', function () {
      tree.setAttribute('data-density', density.value);
    });
    tree.setAttribute('data-density', density.value);
  }

  const searchBox = document.getElementById('searchBox');
  const visibleCountEl = document.getElementById('besoiuTreeVisibleCount');

  function applyClientHighlight(query) {
    const q = (query !== undefined ? String(query) : (searchBox ? searchBox.value.trim() : '')).toLowerCase();
    let visible = 0;
    walkNodes(function (node) {
      const blob = (node.getAttribute('data-search') || '').toLowerCase();
      const match = q === '' || blob.indexOf(q) !== -1;
      node.classList.toggle('is-filtered-out', !match);
      node.classList.toggle('is-search-match', q !== '' && match);
      if (match) visible++;
      if (q !== '' && match) {
        let parent = node.parentElement;
        while (parent && parent !== tree) {
          if (parent.classList.contains('cp-tree-children')) {
            setChildrenOpen(parent, true, false);
          }
          parent = parent.parentElement;
        }
      }
    });
    if (visibleCountEl && q !== '') {
      visibleCountEl.textContent = String(visible);
    }
  }

  if (searchBox) {
    searchBox.addEventListener('cp-tree-search', function (e) {
      applyClientHighlight(e.detail && e.detail.query !== undefined ? e.detail.query : '');
    });
    if (searchBox.value.trim() !== '') {
      applyClientHighlight(searchBox.value.trim());
    }
  }

  window.BesoiuTreeFilter = applyClientHighlight;

  requestAnimationFrame(function () {
    tree.classList.add('is-ready');
    expandToLevel(2, false);
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({ nodes: tree.querySelectorAll('[data-lucide]') });
    }
  });
})();
