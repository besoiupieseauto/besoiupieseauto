<?php
declare(strict_types=1);

$page = site_live_page_slug();
if ($page === '') {
    $page = trim((string) ($_GET['site_cms_page'] ?? ''));
}
require_once __DIR__ . '/site-builder.php';
$builderConfig = site_builder_export_js_config($page);
$apiUrl = '/api/admin-website.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="/assets/cms/cms-edit.css?v=7">
<link rel="stylesheet" href="/assets/cms/cms-builder.css?v=8">
<div id="bpaCmsToolbar" class="bpa-cms-toolbar" role="toolbar" aria-label="Editor conținut">
  <span class="bpa-cms-toolbar-badge"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Constructor Elementor</span>
  <span class="bpa-cms-toolbar-hint">Tab <strong>Imagini</strong> — toate pozele · Upload full · Salvează tot</span>
  <span id="bpaCmsStatus" class="bpa-cms-toolbar-status" aria-live="polite"></span>
  <button type="button" class="bpa-cms-btn bpa-cms-btn-ghost" id="bpaCmsPreview">
    <i class="fa-solid fa-eye" aria-hidden="true"></i> Previzualizare
  </button>
  <button type="button" class="bpa-cms-btn bpa-cms-btn-primary" id="bpaCmsSave">
    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvează tot
  </button>
</div>
<script>window.__bpaBuilderConfig = <?= json_encode($builderConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;</script>
<script>
(function () {
  'use strict';
  const PAGE = <?= json_encode($page, JSON_UNESCAPED_UNICODE) ?>;
  const API = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE) ?>;
  const statusEl = document.getElementById('bpaCmsStatus');
  const saveBtn = document.getElementById('bpaCmsSave');
  const previewBtn = document.getElementById('bpaCmsPreview');
  let dirty = false;

  document.body.classList.add('bpa-cms-editing');

  window.__bpaCmsMarkDirty = function () {
    dirty = true;
    setStatus('Modificări nesalvate…');
  };

  function setStatus(msg, isErr) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'bpa-cms-toolbar-status' + (isErr ? ' is-error' : msg ? ' is-ok' : '');
  }

  function collectFields() {
    const fields = {};
    document.querySelectorAll('[data-cms]').forEach((el) => {
      const key = el.getAttribute('data-cms') || '';
      const isHtml = el.getAttribute('data-cms-html') === '1';
      fields[key] = isHtml ? el.innerHTML.trim() : (el.textContent || '').trim();
    });
    if (typeof window.__bpaGetCmsImageFields === 'function') {
      Object.assign(fields, window.__bpaGetCmsImageFields());
    }
    return fields;
  }

  document.querySelectorAll('[data-cms]').forEach((el) => {
    el.addEventListener('input', () => window.__bpaCmsMarkDirty());
    el.addEventListener('focus', () => {
      el.classList.add('is-focused');
      if (typeof window.__bpaSelectCmsField === 'function') {
        window.__bpaSelectCmsField(el.getAttribute('data-cms') || '', el);
      }
    });
    el.addEventListener('blur', () => el.classList.remove('is-focused'));
    el.addEventListener('click', (e) => {
      if (typeof window.__bpaSelectCmsField === 'function') {
        window.__bpaSelectCmsField(el.getAttribute('data-cms') || '', el);
      }
      e.stopPropagation();
    });
  });

  async function save() {
    saveBtn.disabled = true;
    setStatus('Se salvează…');
      const blocks = typeof window.__bpaBuilderGetBlocks === 'function' ? window.__bpaBuilderGetBlocks() : [];
      const elementStyles = typeof window.__bpaGetCmsStyles === 'function' ? window.__bpaGetCmsStyles() : {};
      try {
        const res = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ page: PAGE, fields: collectFields(), blocks, elementStyles })
        });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Eroare la salvare');
      dirty = false;
      window.__bpaBuilderDirty = false;
      setStatus(json.message || 'Salvat.');
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'bpaCmsSaved', page: PAGE }, '*');
      }
    } catch (err) {
      setStatus(err.message || 'Eroare', true);
    } finally {
      saveBtn.disabled = false;
    }
  }

  saveBtn?.addEventListener('click', save);
  previewBtn?.addEventListener('click', () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('site_cms_edit');
    url.searchParams.delete('site_cms_page');
    window.open(url.toString(), '_blank', 'noopener');
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      save();
    }
  });

  window.addEventListener('beforeunload', (e) => {
    if (dirty || window.__bpaBuilderDirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
})();
</script>
<script src="/assets/cms/cms-builder.js?v=8"></script>
