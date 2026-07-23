(function () {
  'use strict';

  var root = document.getElementById('bpa-api-live-arm');
  if (!root) return;

  var apiUrl = root.getAttribute('data-api') || '/admin/api/settings_endpoint.php';
  var csrf = root.getAttribute('data-csrf') || '';
  var armed = root.getAttribute('data-armed') === '1';
  var permanent = root.getAttribute('data-permanent') === '1';
  var minutesLeft = parseInt(root.getAttribute('data-minutes-left') || '0', 10);

  var btnArm = document.getElementById('bpa-api-live-arm-btn');
  var btnDisarm = document.getElementById('bpa-api-live-disarm-btn');
  var selMinutes = document.getElementById('bpa-api-live-minutes');
  var statusEl = document.getElementById('bpa-api-live-status');

  function formatArmStatus(state) {
    if (!state || !state.armed) {
      return 'API oprit complet';
    }
    if (state.permanent) {
      return 'API mereu activ';
    }
    var mins = parseInt(state.minutes_left, 10) || 0;
    if (mins >= 60) {
      return 'API activ ~' + Math.round(mins / 60) + ' h';
    }
    return 'API activ ~' + mins + ' min';
  }

  function armConfirmLabel(mins) {
    if (mins <= 0) {
      return 'Armezi API live MEREU (până apeși «Oprit complet»)?\n\n'
        + 'Se pot consuma tokeni (Cursor, RapidAPI, scrape.do) la orice acțiune din admin.';
    }
    if (mins >= 240) {
      return 'Armezi API live pentru 4 ore?\n\n'
        + 'Se pot consuma tokeni la import, sync furnizori, scraper, audit, LLM.';
    }
    return 'Armezi API live pentru ' + mins + ' minute?\n\n'
      + 'Se pot consuma tokeni la import, sync furnizori, scraper, audit, LLM.';
  }

  function renderState(state) {
    armed = !!(state && state.armed);
    permanent = !!(state && state.permanent);
    minutesLeft = armed && !permanent ? (parseInt(state.minutes_left, 10) || 0) : 0;
    root.classList.toggle('is-armed', armed);
    root.classList.toggle('is-disarmed', !armed);
    root.classList.toggle('is-permanent', permanent);
    root.setAttribute('data-permanent', permanent ? '1' : '0');
    if (btnArm) btnArm.hidden = armed;
    if (btnDisarm) btnDisarm.hidden = !armed;
    if (selMinutes) selMinutes.disabled = armed;
    if (statusEl) {
      statusEl.textContent = formatArmStatus(state || { armed: armed, permanent: permanent, minutes_left: minutesLeft });
    }
  }

  function post(action, body) {
    var headers = { 'Content-Type': 'application/json' };
    if (csrf) headers['X-Admin-CSRF'] = csrf;
    return fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(Object.assign({ action: action }, body || {})),
    }).then(function (r) { return r.json(); });
  }

  function toast(msg, err) {
    if (typeof window.bpaToast === 'function') {
      window.bpaToast(msg, !!err);
      return;
    }
    if (err) console.warn(msg);
  }

  if (btnArm) {
    btnArm.addEventListener('click', function () {
      var mins = selMinutes ? parseInt(selMinutes.value, 10) : 30;
      if (isNaN(mins)) mins = 30;
      if (!confirm(armConfirmLabel(mins))) {
        return;
      }
      btnArm.disabled = true;
      post('arm_api_live', { minutes: mins })
        .then(function (j) {
          if (!j.success) throw new Error(j.message || 'Eroare');
          var arm = (j.data && j.data.api_live_arm) || {};
          renderState(arm);
          toast(j.message || 'API armat.');
          document.dispatchEvent(new CustomEvent('bpa-api-live-changed', { detail: j.data }));
        })
        .catch(function (e) { toast(e.message || 'Eroare armare', true); })
        .finally(function () { btnArm.disabled = false; });
    });
  }

  if (btnDisarm) {
    btnDisarm.addEventListener('click', function () {
      if (!confirm('Oprești complet API live?\n\nNiciun apel plătit (RapidAPI, scrape.do, Cursor) până la următorul «Armez».')) {
        return;
      }
      btnDisarm.disabled = true;
      post('disarm_api_live', {})
        .then(function (j) {
          if (!j.success) throw new Error(j.message || 'Eroare');
          renderState((j.data && j.data.api_live_arm) || { armed: false });
          toast(j.message || 'API oprit complet.');
          document.dispatchEvent(new CustomEvent('bpa-api-live-changed', { detail: j.data }));
        })
        .catch(function (e) { toast(e.message || 'Eroare dezarmare', true); })
        .finally(function () { btnDisarm.disabled = false; });
    });
  }

  renderState({
    armed: armed,
    permanent: permanent,
    minutes_left: minutesLeft,
  });

  window.bpaApiLiveIsArmed = function () { return armed; };
})();
