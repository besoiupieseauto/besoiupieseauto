(function () {
  'use strict';

  const cfg = window.__bpaBuilderConfig;
  if (!cfg || !cfg.page) return;

  const styleDefaults = Object.assign({}, cfg.styleDefaults || {});
  const styleFields = cfg.styleFields || [];
  let activeTab = 'content';
  let dragId = null;

  const state = {
    blocks: (cfg.blocks || []).map(normalizeBlock),
    selectedId: null,
    selectedCmsKey: null,
    selectedCmsEl: null,
    cmsStyles: Object.assign({}, cfg.cmsStyles || {}),
    pendingZone: cfg.zones[0] || 'main',
    pendingParent: '',
    pendingColumn: 0,
    lastInsertType: 'text',
    cmsImages: (cfg.cmsImages || []).map((item) => Object.assign({}, item)),
    selectedImageKey: null,
  };

  function getCmsImageRecord(cmsKey) {
    return state.cmsImages.find((item) => item.cmsKey === cmsKey) || null;
  }

  function setCmsImageUrl(cmsKey, url) {
    const rec = getCmsImageRecord(cmsKey);
    if (rec) rec.url = url;
    const sel = 'img[data-cms-image="' + cmsKey.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
    const img = document.querySelector(sel);
    const wrap = document.querySelector('.bpa-cms-image-wrap[data-cms-image="' + cmsKey.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]')
      || img?.closest('.bpa-cms-image-wrap');
    if (!url) {
      if (img && wrap) {
        wrap.classList.add('bpa-cms-image-wrap--empty');
        wrap.innerHTML = '<span class="bpa-cms-image-placeholder"><i class="fa-solid fa-image"></i> Adaugă imagine</span>';
        wrap.setAttribute('data-cms-image', cmsKey);
        bindCmsImageElements(wrap);
      } else if (img) {
        img.removeAttribute('src');
      }
    } else if (img) {
      img.src = url;
    } else if (wrap) {
      const rec = getCmsImageRecord(cmsKey);
      const variant = (rec && rec.variant) || wrap.getAttribute('data-cms-variant') || 'default';
      let imgClass = 'bpa-cms-image';
      if (variant === 'icon') imgClass += ' ui-icon-28 hdr-icon footer-social-icon';
      if (variant === 'logo') imgClass += ' logo-img';
      if (variant === 'full') imgClass += ' why-car-image';
      wrap.classList.remove('bpa-cms-image-wrap--empty');
      wrap.innerHTML = '<img src="' + esc(url) + '" data-cms-image="' + esc(cmsKey) + '" data-cms-variant="' + esc(variant) + '" class="' + imgClass + '" alt=""><span class="bpa-cms-image-badge">Schimbă imaginea</span>';
      bindCmsImageElements(wrap);
    }
    markDirty();
    if (state.selectedImageKey === cmsKey) renderImagesPanel();
  }

  async function uploadCmsImageFile(cmsKey, file) {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch(cfg.mediaApi || '/api/admin-cms-media.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Upload eșuat');
    setCmsImageUrl(cmsKey, json.url);
  }

  function renderImagesPanel() {
    const panel = document.getElementById('bpaImagesPanel');
    if (!panel) return;
    if (!state.cmsImages.length) {
      panel.innerHTML = '<div class="bpa-builder-empty-form">Nu există imagini editabile pe această pagină.</div>';
      return;
    }
    let html = '<p class="bpa-form-hint bpa-form-hint--tight">Imagini manuale — upload sau URL. <strong>Carousel hero</strong> nu ia produse din magazin; slide-urile sunt doar din „Carousel hero (fără BD)”.</p>';
    let lastGroup = '';
    state.cmsImages.forEach((item) => {
      if (item.group !== lastGroup) {
        lastGroup = item.group;
        html += '<div class="bpa-images-group-title">' + esc(item.group) + '</div>';
      }
      const active = state.selectedImageKey === item.cmsKey ? ' is-active' : '';
      const url = item.url || '';
      html += '<div class="bpa-image-row' + active + '" data-cms-image-row="' + esc(item.cmsKey) + '">';
      html += '<div class="bpa-image-row__preview">';
      if (url) html += '<img src="' + esc(url) + '" alt="">';
      else html += '<span class="bpa-image-row__empty"><i class="fa-solid fa-image"></i></span>';
      html += '</div><div class="bpa-image-row__body">';
      html += '<strong>' + esc(item.label) + '</strong>';
      html += '<input type="text" class="bpa-image-url-input" data-image-url="' + esc(item.cmsKey) + '" value="' + esc(url) + '" placeholder="URL imagine sau upload">';
      html += '<div class="bpa-image-row__actions">';
      html += '<button type="button" class="bpa-upload-btn bpa-image-upload-btn" data-image-upload="' + esc(item.cmsKey) + '"><i class="fa-solid fa-upload"></i> Upload</button>';
      if (url) html += '<button type="button" class="bpa-image-clear-btn" data-image-clear="' + esc(item.cmsKey) + '">Șterge</button>';
      html += '<button type="button" class="bpa-image-locate-btn" data-image-locate="' + esc(item.cmsKey) + '">Vezi pe pagină</button>';
      html += '</div></div></div>';
    });
    panel.innerHTML = html;

    panel.querySelectorAll('.bpa-image-url-input').forEach((input) => {
      input.addEventListener('change', () => setCmsImageUrl(input.getAttribute('data-image-url'), input.value.trim()));
    });
    panel.querySelectorAll('.bpa-image-upload-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const key = btn.getAttribute('data-image-upload');
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.onchange = async () => {
          try {
            btn.disabled = true;
            await uploadCmsImageFile(key, fileInput.files && fileInput.files[0]);
          } catch (err) {
            alert(err.message || 'Eroare upload');
          } finally {
            btn.disabled = false;
          }
        };
        fileInput.click();
      });
    });
    panel.querySelectorAll('[data-image-clear]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (confirm('Ștergi această imagine?')) setCmsImageUrl(btn.getAttribute('data-image-clear'), '');
      });
    });
    panel.querySelectorAll('[data-image-locate]').forEach((btn) => {
      btn.addEventListener('click', () => selectCmsImage(btn.getAttribute('data-image-locate'), null, true));
    });
    panel.querySelectorAll('[data-cms-image-row]').forEach((row) => {
      row.addEventListener('click', (e) => {
        if (e.target.closest('button,input')) return;
        selectCmsImage(row.getAttribute('data-cms-image-row'), null, false);
      });
    });
  }

  function selectCmsImage(cmsKey, el, scroll) {
    if (!cmsKey) return;
    state.selectedImageKey = cmsKey;
    state.selectedId = null;
    state.selectedCmsKey = null;
    state.selectedCmsEl = null;
    document.querySelectorAll('.bpa-block').forEach((b) => b.classList.remove('is-selected'));
    document.querySelectorAll('[data-cms]').forEach((n) => n.classList.remove('is-cms-selected'));
    document.querySelectorAll('.bpa-cms-image-wrap, img.bpa-cms-image').forEach((n) => n.classList.remove('is-cms-image-selected'));
    const target = el || document.querySelector('[data-cms-image="' + cmsKey.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
    if (target) {
      (target.closest('.bpa-cms-image-wrap') || target).classList.add('is-cms-image-selected');
      if (scroll !== false) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    switchSidebarTab('images');
    renderImagesPanel();
    renderForms();
  }

  function bindCmsImageElements(root) {
    (root || document).querySelectorAll('img[data-cms-image], .bpa-cms-image-wrap[data-cms-image]').forEach((el) => {
      if (el.__bpaImgBound) return;
      el.__bpaImgBound = true;
      el.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const key = el.getAttribute('data-cms-image') || el.querySelector('img[data-cms-image]')?.getAttribute('data-cms-image');
        if (key) selectCmsImage(key, el);
      });
    });
  }

  window.__bpaSelectCmsImage = selectCmsImage;

  function cmsShortKey(fullKey) {
    const prefix = cfg.page + '.';
    return fullKey.startsWith(prefix) ? fullKey.slice(prefix.length) : fullKey;
  }

  function getCmsStyle(shortKey) {
    if (!state.cmsStyles[shortKey]) {
      state.cmsStyles[shortKey] = Object.assign({}, styleDefaults);
    }
    return state.cmsStyles[shortKey];
  }

  function applyCmsStyleToElement(el, shortKey) {
    if (!el) return;
    const style = getCmsStyle(shortKey);
    el.classList.add('bpa-cms-styled');
    ['bpa-pad-', 'bpa-mt-', 'bpa-mb-', 'bpa-ta-', 'bpa-mw-', 'bpa-br-'].forEach((pfx) => {
      el.classList.forEach((c) => { if (c.startsWith(pfx)) el.classList.remove(c); });
    });
    const map = { padding: 'bpa-pad', marginTop: 'bpa-mt', marginBottom: 'bpa-mb', textAlign: 'bpa-ta', maxWidth: 'bpa-mw', borderRadius: 'bpa-br' };
    Object.keys(map).forEach((key) => {
      const val = style[key];
      if (val && val !== 'none') el.classList.add(map[key] + '-' + val);
    });
    const css = [];
    if (style.bgColor) css.push('background-color:' + style.bgColor);
    if (style.textColor) css.push('color:' + style.textColor);
    if (style.bgImage) css.push("background-image:url('" + style.bgImage.replace(/'/g, '') + "')", 'background-size:cover', 'background-position:center');
    el.style.cssText = css.join(';');
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  }

  function attrJson(obj) {
    return JSON.stringify(obj || {})
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function newId() {
    return 'blk_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 6);
  }

  function normalizeBlock(b) {
    const type = b.type || 'text';
    const defs = (cfg.types[type] && cfg.types[type].defaults) || {};
    const styleIn = b.style && typeof b.style === 'object' ? b.style : {};
    const style = Object.assign({}, styleDefaults, styleIn);
    return {
      id: b.id || newId(),
      type,
      zone: b.zone || state.pendingZone,
      parentId: b.parentId || '',
      column: parseInt(b.column, 10) || 0,
      props: Object.assign({}, defs, b.props || {}),
      style,
    };
  }

  function getBlock(id) {
    return state.blocks.find((b) => b.id === id) || null;
  }

  function blockLabel(block) {
    const t = cfg.types[block.type];
    const name = t ? t.label : block.type;
    if (block.type === 'section') return name + ': ' + (block.props.label || 'Secțiune');
    const preview = block.props.text || block.props.title || block.props.label || block.props.html || '';
    const short = String(preview).replace(/<[^>]+>/g, '').slice(0, 24);
    return name + (short ? ' — ' + short : '');
  }

  function styleClasses(style) {
    const classes = ['bpa-el'];
    const map = { padding: 'bpa-pad', marginTop: 'bpa-mt', marginBottom: 'bpa-mb', textAlign: 'bpa-ta', maxWidth: 'bpa-mw', borderRadius: 'bpa-br' };
    Object.keys(map).forEach((key) => {
      const val = style[key];
      if (val && val !== 'none') classes.push(map[key] + '-' + val);
    });
    return classes.join(' ');
  }

  function styleInline(style) {
    const css = [];
    if (style.bgColor) css.push('background-color:' + style.bgColor);
    if (style.textColor) css.push('color:' + style.textColor);
    if (style.bgImage) css.push("background-image:url('" + style.bgImage.replace(/'/g, '') + "')", 'background-size:cover', 'background-position:center');
    return css.join(';');
  }

  function childrenOf(parentId, column, zone) {
    return state.blocks.filter((b) => {
      if ((b.parentId || '') !== (parentId || '')) return false;
      if (column !== undefined && column !== null && (b.column || 0) !== column) return false;
      if (zone && !parentId && (b.zone || '') !== zone) return false;
      return true;
    });
  }

  function getSiblings(block) {
    const parentId = block.parentId || '';
    if (!parentId) {
      return childrenOf('', undefined, block.zone);
    }
    const parent = getBlock(parentId);
    if (parent && parent.type === 'row') {
      return childrenOf(parentId, block.column || 0);
    }
    return childrenOf(parentId);
  }

  function blockSubtreeEndIndex(blockId) {
    const ids = collectDescendantIds(blockId);
    let max = state.blocks.findIndex((b) => b.id === blockId);
    ids.forEach((id) => {
      const i = state.blocks.findIndex((b) => b.id === id);
      if (i > max) max = i;
    });
    return max;
  }

  function createBlocksForType(type, zone, parentId, column) {
    const typeDef = cfg.types[type];
    if (!typeDef) return [];
    const main = normalizeBlock({
      id: newId(),
      type,
      zone,
      parentId: parentId || '',
      column: column || 0,
      props: Object.assign({}, typeDef.defaults || {}),
      style: Object.assign({}, styleDefaults),
    });
    const list = [main];
    if (type === 'section' && !parentId) {
      list.push(
        normalizeBlock({
          id: newId(),
          type: 'heading',
          zone,
          parentId: main.id,
          column: 0,
          props: { level: 'h2', text: 'Titlu secțiune nouă' },
          style: Object.assign({}, styleDefaults),
        }),
        normalizeBlock({
          id: newId(),
          type: 'text',
          zone,
          parentId: main.id,
          column: 0,
          props: { html: '<p>Scrie conținutul aici. Click pe text pentru a edita.</p>' },
          style: Object.assign({}, styleDefaults),
        })
      );
    }
    return list;
  }

  function rowClassNames(p) {
    const cols = ['2', '3', '4'].includes(p.cols) ? p.cols : '2';
    const gap = p.gap || 'md';
    const layout = p.layout === 'flex' ? 'flex' : 'grid';
    const align = ['stretch', 'start', 'center', 'end'].includes(p.align) ? p.align : 'stretch';
    const justify = ['start', 'center', 'end', 'between'].includes(p.justify) ? p.justify : 'start';
    return 'bpa-row bpa-row--' + cols + ' bpa-row--gap-' + gap
      + ' bpa-row--layout-' + layout + ' bpa-row--align-' + align + ' bpa-row--justify-' + justify;
  }

  function dropTargetLabel(parentId, column) {
    if (!parentId) return 'zona principală';
    const parent = getBlock(parentId);
    if (!parent) return 'container';
    let label = blockLabel(parent);
    if (parent.type === 'row') {
      label += ' — col ' + (column + 1);
    }
    return label;
  }

  function updatePaletteHint() {
    const el = document.getElementById('bpaPaletteHint');
    if (!el) return;
    if (state.pendingParent) {
      el.textContent = 'Inserezi în: ' + dropTargetLabel(state.pendingParent, state.pendingColumn)
        + '. Alege din paletă sau click pe + din coloană.';
      el.style.color = '#0f766e';
      return;
    }
    if (state.selectedId) {
      const sel = getBlock(state.selectedId);
      if (sel && (sel.type === 'section' || sel.type === 'row')) {
        el.textContent = 'Container selectat — elementele noi se adaugă în interior.';
        el.style.color = '#0f766e';
        return;
      }
    }
    el.textContent = 'Fără selecție → la poziția aleasă. Cu bloc selectat → dedesubt sau în container.';
    el.style.color = '';
  }

  function highlightDropTarget(parentId, column) {
    document.querySelectorAll('.bpa-drop-slot').forEach((slot) => {
      const sp = slot.getAttribute('data-drop-parent') || '';
      const sc = parseInt(slot.getAttribute('data-drop-column'), 10) || 0;
      slot.classList.toggle('is-target', sp === (parentId || '') && sc === (column || 0));
    });
  }

  function setDropTarget(parentId, column, zone) {
    state.pendingParent = parentId || '';
    state.pendingColumn = column || 0;
    if (zone) state.pendingZone = zone;
    highlightDropTarget(state.pendingParent, state.pendingColumn);
    updatePaletteHint();
    switchSidebarTab('content');
  }

  function clearDropTarget() {
    state.pendingParent = '';
    state.pendingColumn = 0;
    highlightDropTarget('', 0);
    updatePaletteHint();
  }

  let dropMenuEl = null;

  function closeDropSlotMenu() {
    if (dropMenuEl) {
      dropMenuEl.remove();
      dropMenuEl = null;
    }
  }

  function openDropSlotMenu(slot, parentId, column, zone) {
    closeDropSlotMenu();
    setDropTarget(parentId, column, zone);
    const types = [
      ['section', 'Secțiune'],
      ['row', 'Rând coloane'],
      ['heading', 'Titlu'],
      ['text', 'Paragraf'],
      ['image', 'Imagine'],
      ['button', 'Buton'],
      ['iconbox', 'Cutie icon'],
      ['html', 'HTML'],
      ['spacer', 'Spațiu'],
    ];
    const menu = document.createElement('div');
    menu.className = 'bpa-drop-menu';
    menu.innerHTML = '<p class="bpa-drop-menu__title">Adaugă în ' + esc(dropTargetLabel(parentId, column)) + '</p>'
      + '<div class="bpa-drop-menu__grid">'
      + types.map(([t, label]) => '<button type="button" data-drop-type="' + esc(t) + '">' + esc(label) + '</button>').join('')
      + '</div>';
    const rect = slot.getBoundingClientRect();
    menu.style.left = Math.min(rect.left, window.innerWidth - 290) + 'px';
    menu.style.top = (rect.bottom + 6) + 'px';
    document.body.appendChild(menu);
    dropMenuEl = menu;
    menu.querySelectorAll('[data-drop-type]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        insertFromPalette(btn.getAttribute('data-drop-type'));
        closeDropSlotMenu();
      });
    });
    setTimeout(() => {
      document.addEventListener('click', (ev) => {
        if (dropMenuEl && !dropMenuEl.contains(ev.target)) closeDropSlotMenu();
      }, { once: true });
    }, 0);
  }

  function insertFromPalette(type) {
    if (!cfg.types[type]) return;
    state.lastInsertType = type;
    const zone = document.getElementById('bpaBuilderZoneSelect')?.value || state.pendingZone;

    if (state.pendingParent) {
      addBlock(type, { zone, parentId: state.pendingParent, column: state.pendingColumn });
      return;
    }

    if (state.selectedId) {
      const sel = getBlock(state.selectedId);
      if (sel?.type === 'section') {
        addBlock(type, { zone: sel.zone, parentId: sel.id, column: 0 });
        return;
      }
      if (sel?.type === 'row') {
        addBlock(type, { zone: sel.zone, parentId: sel.id, column: state.pendingColumn || 0 });
        return;
      }
      insertBlockRelative(state.selectedId, 'after', type);
      return;
    }

    addBlock(type, { zone, parentId: '', column: 0 });
  }

  function insertIndexForNewBlock(zone, parentId, column) {
    if (parentId) {
      const parent = getBlock(parentId);
      const siblings = parent && parent.type === 'row'
        ? childrenOf(parentId, column)
        : childrenOf(parentId);
      if (siblings.length) {
        return blockSubtreeEndIndex(siblings[siblings.length - 1].id) + 1;
      }
      const parentIdx = state.blocks.findIndex((b) => b.id === parentId);
      return parentIdx >= 0 ? parentIdx + 1 : state.blocks.length;
    }
    const roots = childrenOf('', undefined, zone);
    if (roots.length) {
      return blockSubtreeEndIndex(roots[roots.length - 1].id) + 1;
    }
    return state.blocks.length;
  }

  function addBlocksAtIndex(newBlocks, index) {
    state.blocks.splice(index, 0, ...newBlocks);
    renderAllZones();
    selectBlock(newBlocks[0].id);
    markDirty();
    updateBlockList();
  }

  function videoEmbed(url) {
    url = (url || '').trim();
    let m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{6,})/);
    if (m) return 'https://www.youtube.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1];
    if (url.includes('embed') || url.includes('player.vimeo')) return url;
    return '';
  }

  function parseItems(json) {
    try {
      const arr = JSON.parse(json || '[]');
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function renderInner(block) {
    const p = block.props;
    const s = block.style || styleDefaults;
    const cls = styleClasses(s);
    const inl = styleInline(s);
    const st = inl ? ' style="' + esc(inl) + '"' : '';
    const ie = (prop, html) => ' contenteditable="true" data-inline-prop="' + prop + '"' + (html ? ' data-inline-html="1"' : '');

    switch (block.type) {
      case 'section': {
        const ov = s.bgOverlay && s.bgImage ? '<div class="bpa-section__overlay" style="background:' + esc(s.bgOverlay) + '"></div>' : '';
        let kids = childrenOf(block.id).map((c) => renderBlockEl(c)).join('');
        kids += '<div class="bpa-drop-slot bpa-drop-slot--section" data-drop-parent="' + esc(block.id) + '" data-drop-column="0" data-drop-zone="' + esc(block.zone) + '">+ Click sau trage bloc aici</div>';
        return '<section class="bpa-section ' + cls + '"' + st + '><div class="bpa-section__inner">' + ov + kids + '</div></section>';
      }
      case 'row': {
        const cols = ['2', '3', '4'].includes(p.cols) ? p.cols : '2';
        const gap = p.gap || 'md';
        let html = '<div class="' + rowClassNames(p) + ' ' + cls + '"' + st + '>';
        for (let c = 0; c < parseInt(cols, 10); c++) {
          html += '<div class="bpa-row__col" data-column="' + c + '">';
          html += childrenOf(block.id, c).map((ch) => renderBlockEl(ch)).join('');
          html += '<div class="bpa-drop-slot" data-drop-parent="' + esc(block.id) + '" data-drop-column="' + c + '" data-drop-zone="' + esc(block.zone) + '">+ Adaugă în col ' + (c + 1) + '</div></div>';
        }
        return html + '</div>';
      }
      case 'heading': {
        const lvl = ['h1', 'h2', 'h3', 'h4'].includes(p.level) ? p.level : 'h2';
        return '<div class="bpa-block-heading ' + cls + '"' + st + '><div class="bpa-mw-wrap"><' + lvl + ' class="bpa-block-heading__text"' + ie('text') + '>' + esc(p.text) + '</' + lvl + '></div></div>';
      }
      case 'text':
        return '<div class="bpa-block-text ' + cls + '"' + st + '><div class="bpa-mw-wrap"><div class="bpa-block-text__body"' + ie('html', true) + '>' + (p.html || '') + '</div></div></div>';
      case 'message':
        return '<div class="bpa-block-message ' + cls + '"' + st + '><div class="bpa-mw-wrap"><div class="bpa-block-alert bpa-block-alert--' + esc(p.variant || 'info') + '"><strong' + ie('title') + '>' + esc(p.title) + '</strong><p' + ie('text') + '>' + esc(p.text).replace(/\n/g, '<br>') + '</p></div></div></div>';
      case 'image': {
        const h = p.height || 'auto';
        const w = p.width === 'full' ? ' bpa-block-image--w-full' : '';
        if (!p.url) return '<figure class="bpa-block-image bpa-block-image--h-' + esc(h) + w + ' ' + cls + '"' + st + '><div class="bpa-mw-wrap"><div class="bpa-block-image--placeholder"><i class="fa-solid fa-image"></i> Încarcă imagine</div></div></figure>';
        const img = '<img src="' + esc(p.url) + '" alt="' + esc(p.alt) + '" loading="lazy">';
        const inner = p.link ? '<a href="' + esc(p.link) + '">' + img + '</a>' : img;
        const cap = p.caption ? '<figcaption>' + esc(p.caption) + '</figcaption>' : '';
        return '<figure class="bpa-block-image bpa-block-image--h-' + esc(h) + w + ' ' + cls + '"' + st + '><div class="bpa-mw-wrap">' + inner + cap + '</div></figure>';
      }
      case 'button': {
        const btnCls = { ghost: 'bpa-btn-ghost', glow: 'bpa-btn-glow', dark: 'bpa-btn-dark' }[p.style] || 'bpa-btn-accent';
        const sz = p.size || 'md';
        return '<div class="bpa-block-button bpa-ta-' + esc(s.textAlign || 'left') + ' ' + cls + '"' + st + '><div class="bpa-mw-wrap"><a class="bpa-btn ' + btnCls + ' bpa-btn--' + sz + '" href="' + esc(p.url || '#') + '"><span' + ie('label') + '>' + esc(p.label) + '</span></a></div></div>';
      }
      case 'iconbox':
        return '<div class="bpa-iconbox ' + cls + '"' + st + '><div class="bpa-mw-wrap"><div class="bpa-iconbox__inner">' +
          (p.image ? '<img class="bpa-iconbox__img" src="' + esc(p.image) + '" alt="">' : '<div class="bpa-iconbox__icon"><i class="' + esc(p.icon || 'fa-solid fa-star') + '"></i></div>') +
          '<strong' + ie('title') + '>' + esc(p.title) + '</strong><p' + ie('text') + '>' + esc(p.text).replace(/\n/g, '<br>') + '</p></div></div></div>';
      case 'cards': {
        const items = parseItems(p.items);
        let g = '<div class="bpa-cards ' + cls + '"' + st + '><div class="bpa-mw-wrap">';
        g += '<h2 class="bpa-cards__title"' + ie('title') + '>' + esc(p.title) + '</h2><div class="bpa-cards__grid bpa-cards__grid--' + esc(p.cols || '3') + '">';
        items.forEach((it) => {
          g += '<div class="bpa-cards__item">' + (it.icon ? '<div class="bpa-cards__icon"><i class="' + esc(it.icon) + '"></i></div>' : '') + '<strong>' + esc(it.title) + '</strong><p>' + (it.text || '') + '</p></div>';
        });
        return g + '</div></div></div>';
      }
      case 'steps': {
        const items = parseItems(p.items);
        let g = '<div class="bpa-steps ' + cls + '"' + st + '><div class="bpa-mw-wrap"><h2' + ie('title') + '>' + esc(p.title) + '</h2><div class="bpa-steps__list">';
        items.forEach((it, i) => {
          g += '<div class="bpa-step"><div class="bpa-step__num">' + (i + 1) + '</div><div><strong>' + esc(it.title) + '</strong><p>' + (it.text || '') + '</p></div></div>';
        });
        return g + '</div></div></div>';
      }
      case 'faq': {
        const items = parseItems(p.items);
        let g = '<div class="bpa-faq ' + cls + '"' + st + '><div class="bpa-mw-wrap"><h2' + ie('title') + '>' + esc(p.title) + '</h2><div class="bpa-faq__list">';
        items.forEach((it, i) => {
          g += '<details class="bpa-faq__item"' + (i === 0 ? ' open' : '') + '><summary>' + esc(it.q) + '</summary><div class="bpa-faq__a">' + (it.a || '') + '</div></details>';
        });
        return g + '</div></div></div>';
      }
      case 'video': {
        const emb = videoEmbed(p.url);
        return '<div class="bpa-video ' + cls + '"' + st + '><div class="bpa-mw-wrap">' +
          (emb ? '<div class="bpa-video__wrap"><iframe src="' + esc(emb) + '" allowfullscreen loading="lazy"></iframe></div>' : '<div class="bpa-video__placeholder">URL YouTube/Vimeo</div>') +
          (p.caption ? '<p class="bpa-video__caption">' + esc(p.caption) + '</p>' : '') + '</div></div>';
      }
      case 'html':
        return '<div class="bpa-block-html ' + cls + '"' + st + '><div class="bpa-mw-wrap">' + (p.html || '') + '</div></div>';
      case 'columns':
        return '<div class="bpa-block-columns ' + cls + '"' + st + '><div class="bpa-mw-wrap"><div class="bpa-block-columns__grid"><div class="bpa-block-columns__col"' + ie('left', true) + '>' + (p.left || '') + '</div><div class="bpa-block-columns__col"' + ie('right', true) + '>' + (p.right || '') + '</div></div></div></div>';
      case 'spacer':
        return '<div class="bpa-block-spacer bpa-block-spacer--' + esc(p.size || 'md') + '"></div>';
      case 'divider':
        return '<hr class="bpa-block-divider bpa-block-divider--' + esc(p.style || 'solid') + ' ' + cls + '"' + st + '>';
      default:
        return '';
    }
  }

  function controlsHtml() {
    return `<div class="bpa-block-controls" role="toolbar" aria-label="Acțiuni bloc">
      <button type="button" class="bpa-block-ctrl bpa-block-ctrl--add" data-block-act="insert-above" title="Adaugă deasupra">+↑</button>
      <button type="button" class="bpa-block-ctrl bpa-block-ctrl--add" data-block-act="insert-below" title="Adaugă dedesubt">+↓</button>
      <button type="button" class="bpa-block-ctrl" data-block-act="up" title="Mută sus">▲</button>
      <button type="button" class="bpa-block-ctrl" data-block-act="down" title="Mută jos">▼</button>
      <button type="button" class="bpa-block-ctrl bpa-block-ctrl--danger" data-block-act="delete" title="Șterge">✕</button>
    </div>`;
  }

  function insertBarHtml(refId, position, zone) {
    return `<div class="bpa-insert-bar" data-insert-ref="${esc(refId)}" data-insert-pos="${esc(position)}" data-insert-zone="${esc(zone)}">
      <button type="button" class="bpa-insert-btn" title="Adaugă element aici">+</button>
    </div>`;
  }

  function renderBlockEl(block) {
    const t = cfg.types[block.type] || {};
    const isContainer = !!t.container;
    return `<div class="bpa-block${isContainer ? ' bpa-block--container' : ''}" draggable="true"
      data-block-id="${esc(block.id)}" data-block-type="${esc(block.type)}" data-block-label="${esc(blockLabel(block))}"
      data-block-parent="${esc(block.parentId || '')}" data-block-column="${block.column || 0}" data-block-zone="${esc(block.zone)}"
      data-block-props="${attrJson(block.props)}" data-block-style="${attrJson(block.style)}">
      <div class="bpa-block-drag" title="Trage pentru a muta">⋮⋮</div>
      ${renderInner(block)}${controlsHtml()}</div>`;
  }

  function renderZone(zone) {
    const zoneEl = document.querySelector(`[data-builder-zone="${zone}"]`);
    if (!zoneEl) return;
    Array.from(zoneEl.children).forEach((child) => {
      if (child.classList && (child.classList.contains('bpa-block') || child.classList.contains('bpa-insert-bar'))) {
        child.remove();
      }
    });
    const roots = childrenOf('', undefined, zone);
    zoneEl.classList.toggle('bpa-builder-zone--empty', roots.length === 0);
    let dropRoot = zoneEl.querySelector('.bpa-drop-slot--root') || zoneEl.querySelector('.bpa-drop-slot');
    if (roots.length && !dropRoot) {
      dropRoot = document.createElement('div');
      dropRoot.className = 'bpa-drop-slot bpa-drop-slot--root';
      dropRoot.setAttribute('data-drop-parent', '');
      dropRoot.setAttribute('data-drop-column', '0');
      dropRoot.setAttribute('data-drop-zone', zone);
      dropRoot.textContent = '+ Adaugă la final';
      zoneEl.appendChild(dropRoot);
    }
    if (!roots.length && dropRoot) {
      dropRoot.remove();
      dropRoot = null;
    }
    roots.forEach((b, i) => {
      if (i === 0) {
        const barTmp = document.createElement('div');
        barTmp.innerHTML = insertBarHtml(b.id, 'before', zone);
        const bar = barTmp.firstElementChild;
        if (bar && dropRoot) zoneEl.insertBefore(bar, dropRoot);
        else if (bar) zoneEl.appendChild(bar);
      }
      const tmp = document.createElement('div');
      tmp.innerHTML = renderBlockEl(b);
      const node = tmp.firstElementChild;
      if (!node) return;
      if (dropRoot) zoneEl.insertBefore(node, dropRoot);
      else zoneEl.appendChild(node);
      const afterTmp = document.createElement('div');
      afterTmp.innerHTML = insertBarHtml(b.id, 'after', zone);
      const afterBar = afterTmp.firstElementChild;
      if (afterBar && dropRoot) zoneEl.insertBefore(afterBar, dropRoot);
      else if (afterBar) zoneEl.appendChild(afterBar);
    });
    bindInlineEditors(zoneEl);
    bindDrag(zoneEl);
    bindInsertBars(zoneEl);
    updateZoneMarkers();
  }

  function updateZoneMarkers() {
    document.querySelectorAll('.bpa-builder-zone').forEach((zone) => {
      const isEmpty = zone.classList.contains('bpa-builder-zone--empty');
      let marker = zone.querySelector('.bpa-zone-marker');
      if (isEmpty) {
        if (marker) marker.remove();
        return;
      }
      if (!marker) {
        const label = zone.getAttribute('data-zone-label') || zone.getAttribute('data-builder-zone') || 'Zonă';
        marker = document.createElement('div');
        marker.className = 'bpa-zone-marker';
        marker.innerHTML = '<span><i class="fa-solid fa-layer-group"></i> ' + esc(label) + '</span>';
        zone.insertBefore(marker, zone.firstChild);
      }
    });
  }

  function renderAllZones() {
    (cfg.zones || []).forEach(renderZone);
  }

  function selectBlock(id) {
    state.selectedId = id;
    state.selectedCmsKey = null;
    state.selectedCmsEl = null;
    state.selectedImageKey = null;
    document.querySelectorAll('[data-cms]').forEach((el) => el.classList.remove('is-cms-selected'));
    document.querySelectorAll('.bpa-block').forEach((el) => el.classList.toggle('is-selected', el.dataset.blockId === id));
    document.querySelectorAll('.bpa-builder-block-list li').forEach((li) => li.classList.toggle('is-active', li.dataset.blockId === id));
    document.querySelectorAll('.bpa-insert-bar').forEach((bar) => {
      const ref = bar.getAttribute('data-insert-ref');
      bar.classList.toggle('is-near-selected', ref === id);
    });
    const hint = document.getElementById('bpaPaletteHint');
    updatePaletteHint();
    renderForms();
    const el = document.querySelector(`[data-block-id="${id}"]`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function selectCmsField(fullKey, el) {
    if (!fullKey) return;
    state.selectedId = null;
    state.selectedImageKey = null;
    state.selectedCmsKey = fullKey;
    state.selectedCmsEl = el || document.querySelector('[data-cms="' + fullKey.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
    document.querySelectorAll('.bpa-block').forEach((b) => b.classList.remove('is-selected'));
    document.querySelectorAll('[data-cms]').forEach((node) => {
      node.classList.toggle('is-cms-selected', node === state.selectedCmsEl);
    });
    renderForms();
    switchSidebarTab('content');
  }

  function switchSidebarTab(tab) {
    activeTab = tab;
    const sidebar = document.getElementById('bpaBuilderSidebar');
    if (!sidebar) return;
    sidebar.querySelectorAll('.bpa-builder-tab').forEach((t) => {
      t.classList.toggle('is-active', t.getAttribute('data-tab') === tab);
    });
    sidebar.querySelectorAll('.bpa-tab-panel').forEach((p) => {
      p.classList.toggle('is-active', p.getAttribute('data-panel') === tab);
    });
    if (tab === 'images') renderImagesPanel();
  }

  function emptyFormHtml(msg) {
    return '<div class="bpa-builder-empty-form">' + msg + '</div>';
  }

  function renderField(f, block, prefix) {
    const val = prefix === 'style' ? (block.style[f.key] ?? '') : (block.props[f.key] ?? '');
    let html = `<label>${esc(f.label)}</label>`;
    if (f.type === 'select') {
      html += `<select data-${prefix}="${esc(f.key)}">`;
      Object.entries(f.options || {}).forEach(([k, lbl]) => {
        html += `<option value="${esc(k)}"${val === k ? ' selected' : ''}>${esc(lbl)}</option>`;
      });
      html += '</select>';
    } else if (f.type === 'color') {
      html += `<div class="bpa-color-row"><input type="color" data-${prefix}="${esc(f.key)}" value="${esc(val || '#ffffff')}"><input type="text" class="bpa-color-text" data-${prefix}-text="${esc(f.key)}" value="${esc(val)}" placeholder="transparent"></div>`;
    } else if (f.type === 'image') {
      html += `<div class="bpa-image-field"><input type="text" data-${prefix}="${esc(f.key)}" value="${esc(val)}" placeholder="URL imagine">`;
      html += `<button type="button" class="bpa-upload-btn" data-upload-for="${prefix}:${esc(f.key)}"><i class="fa-solid fa-upload"></i> Upload</button>`;
      if (val) html += `<img class="bpa-image-preview" src="${esc(val)}" alt="">`;
      html += '</div>';
    } else if (f.type === 'json') {
      html += `<textarea data-${prefix}="${esc(f.key)}" rows="6" class="bpa-json-field">${esc(val)}</textarea>`;
    } else if (f.type === 'textarea' || f.type === 'html') {
      html += `<textarea data-${prefix}="${esc(f.key)}" rows="${f.type === 'html' ? 6 : 3}">${esc(val)}</textarea>`;
    } else {
      html += `<input type="text" data-${prefix}="${esc(f.key)}" value="${esc(val)}" placeholder="${esc(f.placeholder || '')}">`;
    }
    return html;
  }

  function bindFormInputs(formEl, block) {
    formEl.querySelectorAll('[data-prop]').forEach((input) => {
      input.addEventListener('input', () => {
        block.props[input.getAttribute('data-prop')] = input.value;
        refreshBlock(block.id);
        markDirty();
        updateBlockList();
      });
    });
    formEl.querySelectorAll('[data-style]').forEach((input) => {
      input.addEventListener('input', () => {
        block.style[input.getAttribute('data-style')] = input.value;
        refreshBlock(block.id);
        markDirty();
      });
    });
    formEl.querySelectorAll('[data-style-text]').forEach((input) => {
      input.addEventListener('input', () => {
        const key = input.getAttribute('data-style-text');
        block.style[key] = input.value;
        const color = formEl.querySelector(`[data-style="${key}"]`);
        if (color && /^#[0-9a-f]{6}$/i.test(input.value)) color.value = input.value;
        refreshBlock(block.id);
        markDirty();
      });
    });
    formEl.querySelectorAll('.bpa-upload-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-upload-for') || '';
        const [prefix, key] = target.split(':');
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.onchange = async () => {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          const fd = new FormData();
          fd.append('file', file);
          btn.disabled = true;
          try {
            const res = await fetch(cfg.mediaApi || '/api/admin-cms-media.php', { method: 'POST', credentials: 'same-origin', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Upload eșuat');
            if (prefix === 'style') block.style[key] = json.url;
            else block.props[key] = json.url;
            refreshBlock(block.id);
            renderForms();
            markDirty();
          } catch (err) {
            alert(err.message || 'Eroare upload');
          } finally {
            btn.disabled = false;
          }
        };
        fileInput.click();
      });
    });
  }

  function bindCmsStyleInputs(formEl, shortKey) {
    const style = getCmsStyle(shortKey);
    formEl.querySelectorAll('[data-style]').forEach((input) => {
      input.addEventListener('input', () => {
        style[input.getAttribute('data-style')] = input.value;
        applyCmsStyleToElement(state.selectedCmsEl, shortKey);
        markDirty();
      });
    });
    formEl.querySelectorAll('[data-style-text]').forEach((input) => {
      input.addEventListener('input', () => {
        const key = input.getAttribute('data-style-text');
        style[key] = input.value;
        const color = formEl.querySelector('[data-style="' + key + '"]');
        if (color && /^#[0-9a-f]{6}$/i.test(input.value)) color.value = input.value;
        applyCmsStyleToElement(state.selectedCmsEl, shortKey);
        markDirty();
      });
    });
    formEl.querySelectorAll('.bpa-upload-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-upload-for') || '';
        const [, key] = target.split(':');
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.onchange = async () => {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          const fd = new FormData();
          fd.append('file', file);
          btn.disabled = true;
          try {
            const res = await fetch(cfg.mediaApi || '/api/admin-cms-media.php', { method: 'POST', credentials: 'same-origin', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Upload eșuat');
            style[key] = json.url;
            applyCmsStyleToElement(state.selectedCmsEl, shortKey);
            renderForms();
            markDirty();
          } catch (err) {
            alert(err.message || 'Eroare upload');
          } finally {
            btn.disabled = false;
          }
        };
        fileInput.click();
      });
    });
  }

  function renderForms() {
    const contentEl = document.getElementById('bpaBuilderContentForm');
    const designEl = document.getElementById('bpaBuilderDesignForm');
    const block = getBlock(state.selectedId);

    if (state.selectedCmsKey && state.selectedCmsEl) {
      const shortKey = cmsShortKey(state.selectedCmsKey);
      const isHtml = state.selectedCmsEl.getAttribute('data-cms-html') === '1';
      const val = isHtml ? state.selectedCmsEl.innerHTML.trim() : (state.selectedCmsEl.textContent || '').trim();
      if (contentEl) {
        let html = '<div class="bpa-builder-form"><strong class="bpa-form-type">Text pagină</strong>';
        html += '<p class="bpa-form-hint">Câmp: <code>' + esc(shortKey) + '</code></p>';
        html += '<label>Conținut</label>';
        html += '<textarea id="bpaCmsTextEdit" rows="' + (isHtml ? 6 : 3) + '">' + esc(val) + '</textarea>';
        html += '<p class="bpa-form-hint">Poți edita și direct pe pagină (click pe text).</p></div>';
        contentEl.innerHTML = html;
        const ta = document.getElementById('bpaCmsTextEdit');
        ta?.addEventListener('input', () => {
          if (isHtml) state.selectedCmsEl.innerHTML = ta.value;
          else state.selectedCmsEl.textContent = ta.value;
          markDirty();
        });
      }
      if (designEl) {
        const fakeBlock = { style: getCmsStyle(shortKey), props: {} };
        let html = '<div class="bpa-builder-form"><strong class="bpa-form-type">Design element</strong>';
        styleFields.forEach((f) => { html += renderField(f, fakeBlock, 'style'); });
        html += '</div>';
        designEl.innerHTML = html;
        bindCmsStyleInputs(designEl, shortKey);
      }
      return;
    }

    if (!block) {
      if (contentEl) {
        contentEl.innerHTML = emptyFormHtml(
          'Alege <strong>poziția pe pagină</strong> (sus), apoi un element din paletă. '
          + 'După ce ai blocuri: click pe bloc → <strong>+↑ +↓</strong> sau + Deasupra/Dedesubt.'
        );
      }
      if (designEl) {
        designEl.innerHTML = emptyFormHtml('Selectează un bloc sau un text din pagină pentru opțiuni Design (culori, fundal, spațiere).');
      }
      return;
    }

    const typeDef = cfg.types[block.type] || {};
    if (contentEl) {
      let html = '<div class="bpa-builder-form"><strong class="bpa-form-type">' + esc(typeDef.label || block.type) + '</strong>';
      html += '<div class="bpa-relative-insert">';
      html += '<label>Poziție bloc</label>';
      html += '<div class="bpa-relative-insert-btns">';
      html += '<button type="button" class="bpa-insert-rel" data-rel="before">+ Deasupra</button>';
      html += '<button type="button" class="bpa-insert-rel" data-rel="after">+ Dedesubt</button>';
      html += '<button type="button" class="bpa-insert-rel bpa-insert-rel--danger" data-rel="delete">Șterge</button>';
      html += '</div>';
      html += '<p class="bpa-form-hint">Paleta din dreapta adaugă <strong>dedesubt</strong> blocului selectat. Tu alegi câte blocuri vrei — nu există limită fixă.</p>';
      html += '</div>';
      (typeDef.fields || []).forEach((f) => { html += renderField(f, block, 'prop'); });
      html += '</div>';
      contentEl.innerHTML = html;
      bindFormInputs(contentEl, block);
      contentEl.querySelectorAll('.bpa-insert-rel').forEach((btn) => {
        btn.addEventListener('click', () => {
          const rel = btn.getAttribute('data-rel');
          if (rel === 'delete') {
            if (confirm('Ștergi acest bloc?')) deleteBlock(block.id);
            return;
          }
          openInsertPicker(block.id, rel === 'before' ? 'before' : 'after');
        });
      });
    }
    if (designEl) {
      let html = '<div class="bpa-builder-form"><strong class="bpa-form-type">Design bloc</strong>';
      styleFields.forEach((f) => { html += renderField(f, block, 'style'); });
      html += '</div>';
      designEl.innerHTML = html;
      bindFormInputs(designEl, block);
    }
  }

  function refreshBlock(id) {
    const block = getBlock(id);
    if (!block) return;
    const typeDef = cfg.types[block.type];
    if (typeDef && typeDef.container) {
      renderAllZones();
      if (state.selectedId === id) {
        document.querySelector(`[data-block-id="${id}"]`)?.classList.add('is-selected');
      }
      return;
    }
    const el = document.querySelector(`[data-block-id="${id}"]`);
    if (!el) return;
    const parent = el.parentElement;
    const tmp = document.createElement('div');
    tmp.innerHTML = renderBlockEl(block);
    const newEl = tmp.firstElementChild;
    if (!parent || !newEl) return;
    parent.replaceChild(newEl, el);
    if (state.selectedId === id) newEl.classList.add('is-selected');
    bindInlineEditors(parent);
    bindDrag(parent);
  }

  function insertBlockRelative(refId, position, type) {
    const ref = getBlock(refId);
    if (!ref || !cfg.types[type]) return;
    state.lastInsertType = type;
    const newBlocks = createBlocksForType(type, ref.zone, ref.parentId || '', ref.column || 0);
    const idx = position === 'before'
      ? state.blocks.findIndex((b) => b.id === refId)
      : blockSubtreeEndIndex(refId) + 1;
    addBlocksAtIndex(newBlocks, idx);
  }

  function openInsertPicker(refId, position) {
    const type = state.lastInsertType || 'text';
    const label = (cfg.types[type] && cfg.types[type].label) || type;
    const choice = window.prompt(
      'Tip element de adăugat:\n'
      + 'text, heading, image, button, section, row, iconbox, cards, faq, message, video, html, spacer, divider\n\n'
      + 'Ultimul folosit: ' + label,
      type
    );
    if (choice === null) return;
    const t = String(choice).trim().toLowerCase();
    if (!cfg.types[t]) {
      alert('Tip necunoscut. Exemple: text, heading, section, image');
      return;
    }
    insertBlockRelative(refId, position, t);
  }

  function addBlock(type, opts) {
    opts = opts || {};
    const typeDef = cfg.types[type];
    if (!typeDef) return;
    state.lastInsertType = type;
    const zone = opts.zone || state.pendingZone;
    const parentId = opts.parentId !== undefined ? opts.parentId : (state.pendingParent || '');
    const column = opts.column !== undefined ? opts.column : state.pendingColumn;
    const newBlocks = createBlocksForType(type, zone, parentId, column);
    const idx = insertIndexForNewBlock(zone, parentId, column);
    addBlocksAtIndex(newBlocks, idx);
  }

  function bindInsertBars(root) {
    (root || document).querySelectorAll('.bpa-insert-btn').forEach((btn) => {
      if (btn.__bpaIns) return;
      btn.__bpaIns = true;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const bar = btn.closest('.bpa-insert-bar');
        if (!bar) return;
        const refId = bar.getAttribute('data-insert-ref');
        const pos = bar.getAttribute('data-insert-pos') === 'before' ? 'before' : 'after';
        openInsertPicker(refId, pos);
      });
    });
  }

  function duplicateBlock(id) {
    const src = getBlock(id);
    if (!src) return;
    const copy = normalizeBlock(JSON.parse(JSON.stringify(src)));
    copy.id = newId();
    const idx = state.blocks.findIndex((b) => b.id === id);
    state.blocks.splice(idx + 1, 0, copy);
    renderAllZones();
    selectBlock(copy.id);
    markDirty();
    updateBlockList();
  }

  function collectDescendantIds(id) {
    const ids = [id];
    state.blocks.forEach((b) => {
      if (b.parentId === id) ids.push(...collectDescendantIds(b.id));
    });
    return ids;
  }

  function deleteBlock(id) {
    const block = getBlock(id);
    if (!block) return;
    const toRemove = collectDescendantIds(id);
    state.blocks = state.blocks.filter((b) => !toRemove.includes(b.id));
    if (state.selectedId === id) state.selectedId = null;
    renderAllZones();
    renderForms();
    updateBlockList();
    markDirty();
  }

  function moveBlock(id, dir) {
    const block = getBlock(id);
    if (!block) return;
    const siblings = childrenOf(block.parentId || '', block.column, block.parentId ? undefined : block.zone);
    const idx = siblings.findIndex((b) => b.id === id);
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= siblings.length) return;
    const swap = siblings[newIdx];
    const allIdx = state.blocks.indexOf(block);
    const swapIdx = state.blocks.indexOf(swap);
    state.blocks[allIdx] = swap;
    state.blocks[swapIdx] = block;
    renderAllZones();
    selectBlock(id);
    markDirty();
    updateBlockList();
  }

  function moveBlockTo(id, parentId, column, zone) {
    const block = getBlock(id);
    if (!block) return;
    if (block.id === parentId) return;
    block.parentId = parentId || '';
    block.column = column || 0;
    if (!parentId) block.zone = zone || block.zone;
    renderAllZones();
    selectBlock(id);
    markDirty();
    updateBlockList();
  }

  function updateBlockList() {
    const list = document.getElementById('bpaBuilderBlockList');
    if (!list) return;
    if (!state.blocks.length) {
      list.innerHTML = '<li class="bpa-list-empty">Niciun bloc — adaugă o Secțiune</li>';
      return;
    }

    function depthOf(block) {
      let d = 0;
      let cur = block;
      while (cur && cur.parentId) {
        d++;
        cur = getBlock(cur.parentId);
      }
      return d;
    }

    list.innerHTML = state.blocks.map((b) => {
      const depth = depthOf(b);
      const indent = depth > 0 ? '<span class="bpa-tree-indent">' + '└'.repeat(Math.min(depth, 4)) + '</span> ' : '';
      const colHint = b.parentId && getBlock(b.parentId)?.type === 'row' ? ' [col ' + ((b.column || 0) + 1) + ']' : '';
      return `<li data-block-id="${esc(b.id)}" class="${b.id === state.selectedId ? 'is-active' : ''}${depth > 0 ? ' is-child' : ''}">`
        + indent
        + `<i class="fa-solid ${esc((cfg.types[b.type] || {}).icon || 'fa-cube')}"></i>`
        + `<span>${esc(blockLabel(b) + colHint)}</span></li>`;
    }).join('');
    list.querySelectorAll('li[data-block-id]').forEach((li) => {
      li.addEventListener('click', () => selectBlock(li.dataset.blockId));
    });
  }

  function bindInlineEditors(root) {
    (root || document).querySelectorAll('[data-inline-prop]').forEach((el) => {
      if (el.__bpaBound) return;
      el.__bpaBound = true;
      el.addEventListener('input', () => {
        const blockEl = el.closest('.bpa-block');
        const block = getBlock(blockEl?.dataset?.blockId);
        if (!block) return;
        const key = el.getAttribute('data-inline-prop');
        const isHtml = el.getAttribute('data-inline-html') === '1';
        block.props[key] = isHtml ? el.innerHTML.trim() : (el.textContent || '').trim();
        blockEl.dataset.blockProps = JSON.stringify(block.props);
        markDirty();
        updateBlockList();
      });
      el.addEventListener('click', (e) => e.stopPropagation());
    });
  }

  function bindDrag(root) {
    (root || document).querySelectorAll('.bpa-block[draggable]').forEach((el) => {
      if (el.__bpaDrag) return;
      el.__bpaDrag = true;
      el.addEventListener('dragstart', (e) => {
        dragId = el.dataset.blockId;
        el.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      el.addEventListener('dragend', () => {
        el.classList.remove('is-dragging');
        dragId = null;
        document.querySelectorAll('.bpa-drop-slot.is-over').forEach((s) => s.classList.remove('is-over'));
      });
    });
    (root || document).querySelectorAll('.bpa-drop-slot').forEach((slot) => {
      if (slot.__bpaDrop) return;
      slot.__bpaDrop = true;
      slot.addEventListener('dragover', (e) => { e.preventDefault(); slot.classList.add('is-over'); });
      slot.addEventListener('dragleave', () => slot.classList.remove('is-over'));
      slot.addEventListener('drop', (e) => {
        e.preventDefault();
        slot.classList.remove('is-over');
        slot.__bpaJustDropped = true;
        setTimeout(() => { slot.__bpaJustDropped = false; }, 250);
        if (!dragId) return;
        const parentId = slot.getAttribute('data-drop-parent') || '';
        const column = parseInt(slot.getAttribute('data-drop-column'), 10) || 0;
        const zone = slot.getAttribute('data-drop-zone') || getBlock(dragId)?.zone || state.pendingZone;
        moveBlockTo(dragId, parentId, column, zone);
      });
      slot.addEventListener('click', (e) => {
        e.stopPropagation();
        if (slot.__bpaJustDropped) return;
        if (e.target.closest('.bpa-block')) return;
        const parentId = slot.getAttribute('data-drop-parent') || '';
        const column = parseInt(slot.getAttribute('data-drop-column'), 10) || 0;
        const zone = slot.getAttribute('data-drop-zone') || state.pendingZone;
        openDropSlotMenu(slot, parentId, column, zone);
      });
    });
  }

  function buildSidebar() {
    const sidebar = document.createElement('aside');
    sidebar.className = 'bpa-builder-sidebar';
    sidebar.id = 'bpaBuilderSidebar';
    sidebar.innerHTML = `
      <div class="bpa-builder-sidebar__head">
        <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Constructor pagină</h3>
        <p>Elementor-style — secțiuni, coloane, design</p>
      </div>
      <div class="bpa-builder-tabs">
        <button type="button" class="bpa-builder-tab is-active" data-tab="content">Conținut</button>
        <button type="button" class="bpa-builder-tab" data-tab="design">Design</button>
        <button type="button" class="bpa-builder-tab" data-tab="images">Imagini</button>
        <button type="button" class="bpa-builder-tab" data-tab="structure">Structură</button>
      </div>
      <div class="bpa-builder-sidebar__body">
        <div class="bpa-tab-panel is-active" data-panel="content">
          <label class="bpa-field-label">Poziție pe pagină</label>
          <p class="bpa-form-hint bpa-form-hint--tight">Unde apare primul bloc (zonele goale nu se văd pe pagină).</p>
          <select id="bpaBuilderZoneSelect" class="bpa-select">
            ${(cfg.zones || []).map((z) => `<option value="${esc(z)}">${esc((cfg.zoneLabels || {})[z] || z)}</option>`).join('')}
          </select>
          <label class="bpa-field-label">Adaugă element</label>
          <p class="bpa-form-hint bpa-form-hint--tight" id="bpaPaletteHint">Fără selecție → la poziția aleasă. Cu bloc selectat → dedesubt.</p>
          <div class="bpa-builder-palette" id="bpaBuilderPalette"></div>
          <div id="bpaBuilderContentForm"></div>
        </div>
        <div class="bpa-tab-panel" data-panel="design"><div id="bpaBuilderDesignForm"></div></div>
        <div class="bpa-tab-panel" data-panel="images"><div id="bpaImagesPanel"></div></div>
        <div class="bpa-tab-panel" data-panel="structure">
          <label class="bpa-field-label">Toate blocurile</label>
          <ul class="bpa-builder-block-list" id="bpaBuilderBlockList"></ul>
        </div>
      </div>`;
    document.body.appendChild(sidebar);

    sidebar.querySelectorAll('.bpa-builder-tab').forEach((tab) => {
      tab.addEventListener('click', () => switchSidebarTab(tab.getAttribute('data-tab')));
    });

    const palette = sidebar.querySelector('#bpaBuilderPalette');
    const order = ['section', 'row', 'heading', 'text', 'image', 'button', 'iconbox', 'cards', 'steps', 'faq', 'message', 'video', 'columns', 'html', 'spacer', 'divider'];
    order.forEach((type) => {
      const def = cfg.types[type];
      if (!def) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bpa-builder-palette-btn';
      btn.innerHTML = `<i class="fa-solid ${esc(def.icon || 'fa-cube')}"></i>${esc(def.label)}`;
      btn.title = def.desc || '';
      btn.addEventListener('click', () => {
        insertFromPalette(type);
      });
      palette.appendChild(btn);
    });

    document.getElementById('bpaBuilderZoneSelect')?.addEventListener('change', (e) => {
      state.pendingZone = e.target.value;
    });

    updateBlockList();
    renderForms();
    renderImagesPanel();
  }

  function injectZoneMarkers() {
    document.querySelectorAll('.bpa-builder-zone:not(.bpa-builder-zone--empty)').forEach((zone) => {
      if (zone.querySelector('.bpa-zone-marker')) return;
      const label = zone.getAttribute('data-zone-label') || zone.getAttribute('data-builder-zone') || 'Zonă';
      const bar = document.createElement('div');
      bar.className = 'bpa-zone-marker';
      bar.innerHTML = '<span><i class="fa-solid fa-layer-group"></i> ' + esc(label) + '</span>';
      zone.insertBefore(bar, zone.firstChild);
    });
  }

  function markDirty() {
    window.__bpaBuilderDirty = true;
    if (typeof window.__bpaCmsMarkDirty === 'function') window.__bpaCmsMarkDirty();
  }

  document.addEventListener('click', (e) => {
    const actBtn = e.target.closest('[data-block-act]');
    if (actBtn) {
      e.preventDefault();
      e.stopPropagation();
      const blockEl = actBtn.closest('.bpa-block');
      const id = blockEl?.dataset?.blockId;
      if (!id) return;
      const act = actBtn.getAttribute('data-block-act');
      if (act === 'delete' && confirm('Ștergi acest bloc?')) deleteBlock(id);
      else if (act === 'insert-above') insertBlockRelative(id, 'before', state.lastInsertType || 'text');
      else if (act === 'insert-below') insertBlockRelative(id, 'after', state.lastInsertType || 'text');
      else if (act === 'duplicate') duplicateBlock(id);
      else if (act === 'up') moveBlock(id, -1);
      else if (act === 'down') moveBlock(id, 1);
      return;
    }
    const insBtn = e.target.closest('.bpa-insert-btn');
    if (insBtn) return;
    const blockEl = e.target.closest('.bpa-block');
    if (blockEl && !e.target.closest('.bpa-block-controls') && !e.target.closest('[contenteditable]')) {
      selectBlock(blockEl.dataset.blockId);
      const parentId = blockEl.dataset.blockId;
      const type = blockEl.dataset.blockType;
      if (type === 'section') {
        setDropTarget(parentId, 0, blockEl.dataset.blockZone || state.pendingZone);
      } else if (type === 'row') {
        state.pendingColumn = 0;
        setDropTarget(parentId, 0, blockEl.dataset.blockZone || state.pendingZone);
      }
    }
  });

  window.__bpaBuilderGetBlocks = () => state.blocks.map((b) => ({
    id: b.id,
    type: b.type,
    zone: b.zone,
    parentId: b.parentId || '',
    column: b.column || 0,
    props: b.props,
    style: b.style,
  }));

  window.__bpaGetCmsImageFields = () => {
    const out = {};
    state.cmsImages.forEach((item) => {
      out[item.cmsKey] = item.url || '';
    });
    return out;
  };

  window.__bpaGetCmsStyles = () => state.cmsStyles;
  window.__bpaSelectCmsField = selectCmsField;

  // Aplică stiluri salvate pe elementele CMS la încărcare
  document.querySelectorAll('[data-cms]').forEach((el) => {
    const key = el.getAttribute('data-cms');
    if (key) applyCmsStyleToElement(el, cmsShortKey(key));
  });

  buildSidebar();
  updatePaletteHint();
  renderAllZones();
  injectZoneMarkers();
  updateZoneMarkers();
  bindCmsImageElements(document);
})();
