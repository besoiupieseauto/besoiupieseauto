(function () {
  'use strict';

  const root = document.getElementById('export-hub-root');
  if (!root) return;

  const ENDPOINT = root.dataset.api || '/admin/api/export_action_endpoint.php';
  const tabs = Array.from(root.querySelectorAll('[data-export-section]'));
  const panels = Array.from(root.querySelectorAll('[data-export-panel]'));
  let activeSection = tabs.find((t) => t.classList.contains('is-active'))?.dataset.exportSection || 'catalog';
  let previewTimer = null;

  function activePanel() {
    return panels.find((p) => p.dataset.exportPanel === activeSection) || panels[0];
  }

  function parseFilterKeys(panel) {
    try {
      return JSON.parse(panel.dataset.filters || '[]');
    } catch (e) {
      return [];
    }
  }

  function applyFilterVisibility(panel) {
    const keys = parseFilterKeys(panel);
    panel.querySelectorAll('[data-filter]').forEach((field) => {
      const allowed = (field.dataset.filter || '').split(/\s+/).filter(Boolean);
      const visible = allowed.some((key) => keys.includes(key));
      field.hidden = !visible;
      if (!visible) {
        field.querySelectorAll('input, select').forEach((el) => {
          if (el.type === 'checkbox') el.checked = false;
          else el.value = '';
        });
      }
    });
  }

  function collectFilters(form) {
    const filters = {};
    if (!form) return filters;
    const data = new FormData(form);
    data.forEach((value, key) => {
      if (key === 'format') return;
      if (key === 'not_found_only') {
        filters.not_found_only = true;
        return;
      }
      const trimmed = String(value || '').trim();
      if (trimmed !== '') filters[key] = trimmed;
    });
    return filters;
  }

  function showToast(panel, message, isError) {
    const toast = panel?.querySelector('[data-export-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'exp-toast is-visible ' + (isError ? 'is-error' : 'is-ok');
  }

  function hideToast(panel) {
    const toast = panel?.querySelector('[data-export-toast]');
    if (toast) toast.className = 'exp-toast';
  }

  async function apiPost(payload) {
    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
  }

  async function readJsonResponse(response, fallbackMessage) {
    const rawBody = await response.text();
    if (!rawBody.trim()) {
      throw new Error(fallbackMessage + ' (răspuns gol, HTTP ' + response.status + ').');
    }
    try {
      return JSON.parse(rawBody);
    } catch (e) {
      const trimmed = rawBody.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      if (trimmed && trimmed.length < 400) {
        throw new Error(trimmed);
      }
      throw new Error(fallbackMessage + ' (HTTP ' + response.status + ').');
    }
  }

  async function refreshPreview(panel) {
    const form = panel.querySelector('[data-export-form]');
    const countEl = panel.querySelector('[data-preview-count]');
    if (!countEl) return;

    countEl.textContent = 'Se calculează…';
    countEl.classList.add('is-loading');
    hideToast(panel);

    try {
      const response = await apiPost({
        action: 'preview',
        section: panel.dataset.exportPanel,
        filters: collectFilters(form),
      });

      const json = await readJsonResponse(response, 'Previzualizare eșuată');
      if (!response.ok || !json.success) {
        throw new Error(json.message || 'Previzualizare eșuată.');
      }

      countEl.textContent = json.label || String(json.count ?? '0');
    } catch (error) {
      countEl.textContent = '—';
      showToast(panel, error.message || 'Previzualizare eșuată.', true);
    } finally {
      countEl.classList.remove('is-loading');
    }
  }

  function schedulePreview(panel) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => refreshPreview(panel), 350);
  }

  async function runExport(panel) {
    const form = panel.querySelector('[data-export-form]');
    const exportBtn = panel.querySelector('[data-action="export"]');
    const btnLabel = panel.querySelector('[data-export-btn-label]');
    const format = form?.querySelector('[data-export-format]')?.value || 'csv';
    const prevLabel = btnLabel ? btnLabel.textContent : '';

    if (exportBtn) exportBtn.disabled = true;
    if (btnLabel) btnLabel.textContent = 'Se generează…';
    hideToast(panel);

    try {
      const response = await apiPost({
        action: 'export',
        section: panel.dataset.exportPanel,
        format,
        filters: collectFilters(form),
      });

      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      const rawBody = await response.text();

      if (!response.ok) {
        let message = 'Export eșuat (HTTP ' + response.status + ').';
        try {
          const json = JSON.parse(rawBody);
          message = json.message || message;
        } catch (e) {
          const trimmed = rawBody.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
          if (trimmed && trimmed.length < 400) message = trimmed;
        }
        throw new Error(message);
      }

      if (contentType.includes('application/json')) {
        const json = JSON.parse(rawBody);
        throw new Error(json.message || 'Export eșuat.');
      }

      const blob = new Blob([rawBody], { type: contentType || 'application/octet-stream' });
      const disposition = response.headers.get('content-disposition') || '';
      const match = disposition.match(/filename="([^"]+)"/i);
      const filename = match ? match[1] : `export_${panel.dataset.exportPanel}_${Date.now()}`;

      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);

      showToast(panel, 'Fișier descărcat: ' + filename, false);
    } catch (error) {
      showToast(panel, error.message || 'Export eșuat.', true);
    } finally {
      if (exportBtn) exportBtn.disabled = false;
      if (btnLabel) btnLabel.textContent = prevLabel || 'Descarcă export';
    }
  }

  function switchSection(sectionId) {
    activeSection = sectionId;
    tabs.forEach((tab) => {
      const active = tab.dataset.exportSection === sectionId;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => {
      const active = panel.dataset.exportPanel === sectionId;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
      if (active) {
        applyFilterVisibility(panel);
        refreshPreview(panel);
      }
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => switchSection(tab.dataset.exportSection || 'catalog'));
  });

  panels.forEach((panel) => {
    applyFilterVisibility(panel);

    const form = panel.querySelector('[data-export-form]');
    form?.addEventListener('input', () => schedulePreview(panel));
    form?.addEventListener('change', () => schedulePreview(panel));

    panel.querySelector('[data-action="refresh-preview"]')?.addEventListener('click', () => refreshPreview(panel));
    panel.querySelector('[data-action="export"]')?.addEventListener('click', () => runExport(panel));
  });

  const initial = activePanel();
  if (initial) refreshPreview(initial);
})();
