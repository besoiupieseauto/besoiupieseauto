(function () {
  'use strict';

  var btn = document.getElementById('import-mod-ping');
  var out = document.getElementById('import-mod-out');
  if (btn && out) {
    btn.addEventListener('click', function () {
      out.textContent = 'JS din modules/Import/assets — OK · ' + new Date().toLocaleTimeString('ro-RO');
    });
  }

  var syncBtn = document.getElementById('import-sync-feeds');
  var syncOut = document.getElementById('import-sync-out');
  var api = window.IMPORT_BRIDGE_API || '';

  if (syncBtn && api) {
    syncBtn.addEventListener('click', async function () {
      syncBtn.disabled = true;
      if (syncOut) syncOut.textContent = 'Sincronizare…';
      try {
        var form = new FormData();
        form.append('action', 'sync_feeds');
        var res = await fetch(api + (api.indexOf('?') >= 0 ? '&' : '?') + 'action=sync_feeds', {
          method: 'POST',
          body: form,
        });
        var data = await res.json();
        if (syncOut) {
          syncOut.textContent = data.result?.summary || (data.success ? 'OK' : (data.error || 'Eroare'));
        }
      } catch (e) {
        if (syncOut) syncOut.textContent = e.message || 'Eroare';
      } finally {
        syncBtn.disabled = false;
      }
    });
  }
})();
