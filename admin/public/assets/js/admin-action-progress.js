/**
 * Besoiu Admin — popup progres + rezultat pentru acțiuni test/execuție
 */
(function () {
  'use strict';

  var MODAL_ID = 'bpa-action-modal';
  var busy = false;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function notify(msg, isError) {
    if (window.aiAgentToast) {
      window.aiAgentToast(msg, isError);
      return;
    }
    var st = document.getElementById('settings-toast');
    if (st) {
      st.textContent = msg;
      st.classList.remove('hidden', 'is-error', 'is-err', 'is-ok');
      st.classList.add(isError ? 'is-err' : 'is-ok');
      clearTimeout(st._t);
      st._t = setTimeout(function () { st.classList.add('hidden'); }, 4500);
      return;
    }
    if (isError) console.error(msg); else console.log(msg);
  }

  function ensureModal() {
    var el = document.getElementById(MODAL_ID);
    if (el) return el;

    el = document.createElement('div');
    el.id = MODAL_ID;
    el.className = 'bpa-action-modal';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'bpa-action-title');
    el.hidden = true;
    el.innerHTML = ''
      + '<div class="bpa-action-modal__backdrop" data-bpa-close></div>'
      + '<div class="bpa-action-modal__card">'
      + '<div class="bpa-action-modal__icon bpa-action-modal__icon--run" id="bpa-action-icon" aria-hidden="true">'
      + '<span class="bpa-action-modal__spinner"></span></div>'
      + '<h3 class="bpa-action-modal__title" id="bpa-action-title">Se procesează…</h3>'
      + '<p class="bpa-action-modal__subtitle" id="bpa-action-subtitle"></p>'
      + '<div class="bpa-load bpa-load--compact" id="bpa-action-load">'
      + '<div class="bpa-load__head"><span class="bpa-load__label" id="bpa-action-load-label">Pornire…</span>'
      + '<span class="bpa-load__pct" id="bpa-action-pct">0%</span></div>'
      + '<div class="bpa-load__track"><div class="bpa-load__bar bpa-load__bar--pulse" id="bpa-action-bar" style="width:8%"></div></div>'
      + '<ul class="bpa-load__steps" id="bpa-action-steps"></ul>'
      + '</div>'
      + '<p class="bpa-action-modal__result" id="bpa-action-result" hidden></p>'
      + '<div class="bpa-action-modal__actions">'
      + '<button type="button" class="bpa-action-modal__btn bpa-action-modal__btn--ghost" id="bpa-action-close" hidden>Închide</button>'
      + '</div></div>';
    document.body.appendChild(el);

    el.querySelector('[data-bpa-close]')?.addEventListener('click', close);
    document.getElementById('bpa-action-close')?.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !el.hidden && !busy) close();
    });

    return el;
  }

  function setPct(pct, indeterminate) {
    var bar = document.getElementById('bpa-action-bar');
    var pctEl = document.getElementById('bpa-action-pct');
    if (!bar || !pctEl) return;
    var p = Math.max(0, Math.min(100, pct));
    bar.classList.toggle('bpa-load__bar--pulse', !!indeterminate);
    if (!indeterminate) {
      bar.style.width = p + '%';
      pctEl.textContent = Math.round(p) + '%';
    } else {
      pctEl.textContent = '…';
    }
  }

  function renderSteps(steps, activeIdx, doneIdx, errorIdx) {
    var ul = document.getElementById('bpa-action-steps');
    if (!ul) return;
    if (!steps || !steps.length) {
      ul.innerHTML = '';
      ul.hidden = true;
      return;
    }
    ul.hidden = false;
    ul.innerHTML = steps.map(function (label, i) {
      var cls = '';
      var ico = '○';
      if (errorIdx === i) { cls = ' is-error'; ico = '✕'; }
      else if (doneIdx >= i && activeIdx > i) { cls = ' is-done'; ico = '✓'; }
      else if (activeIdx === i) { cls = ' is-active'; ico = '●'; }
      return '<li class="' + cls + '"><span class="bpa-load__step-ico">' + ico + '</span>' + esc(label) + '</li>';
    }).join('');
  }

  function openShell(title, subtitle, steps) {
    var modal = ensureModal();
    modal.hidden = false;
    modal.className = 'bpa-action-modal is-running';
    document.body.classList.add('bpa-action-modal-open');

    document.getElementById('bpa-action-title').textContent = title || 'Se procesează…';
    var sub = document.getElementById('bpa-action-subtitle');
    if (sub) {
      sub.textContent = subtitle || '';
      sub.hidden = !subtitle;
    }

    var icon = document.getElementById('bpa-action-icon');
    if (icon) icon.className = 'bpa-action-modal__icon bpa-action-modal__icon--run';

    var load = document.getElementById('bpa-action-load');
    if (load) {
      load.hidden = false;
      load.classList.remove('is-complete', 'is-error');
    }

    var res = document.getElementById('bpa-action-result');
    if (res) { res.hidden = true; res.textContent = ''; }

    var closeBtn = document.getElementById('bpa-action-close');
    if (closeBtn) closeBtn.hidden = true;

    renderSteps(steps, 0, -1, -1);
    setPct(8, true);
    document.getElementById('bpa-action-load-label').textContent = 'Inițializare…';
  }

  function finishSuccess(message, steps) {
    var modal = ensureModal();
    modal.classList.remove('is-running');
    modal.classList.add('is-success');

    var icon = document.getElementById('bpa-action-icon');
    if (icon) icon.className = 'bpa-action-modal__icon bpa-action-modal__icon--ok';
    icon.innerHTML = '✓';

    var load = document.getElementById('bpa-action-load');
    if (load) {
      load.classList.add('is-complete');
      setPct(100, false);
    }
    document.getElementById('bpa-action-load-label').textContent = 'Finalizat cu succes';
    if (steps && steps.length) renderSteps(steps, steps.length, steps.length - 1, -1);

    var res = document.getElementById('bpa-action-result');
    if (res) {
      res.hidden = false;
      res.className = 'bpa-action-modal__result is-ok';
      res.textContent = message || 'Operațiune reușită.';
    }

    document.getElementById('bpa-action-close').hidden = false;
    notify(message || 'Operațiune reușită.', false);
  }

  function finishError(message, steps, stepIdx) {
    var modal = ensureModal();
    modal.classList.remove('is-running');
    modal.classList.add('is-error');

    var icon = document.getElementById('bpa-action-icon');
    if (icon) icon.className = 'bpa-action-modal__icon bpa-action-modal__icon--fail';
    icon.innerHTML = '✕';

    var load = document.getElementById('bpa-action-load');
    if (load) {
      load.classList.add('is-error');
      setPct(100, false);
    }
    document.getElementById('bpa-action-load-label').textContent = 'Eșec';
    if (steps && steps.length) renderSteps(steps, stepIdx, stepIdx - 1, stepIdx);

    var res = document.getElementById('bpa-action-result');
    if (res) {
      res.hidden = false;
      res.className = 'bpa-action-modal__result is-fail';
      res.textContent = message || 'A apărut o eroare.';
    }

    document.getElementById('bpa-action-close').hidden = false;
    notify(message || 'A apărut o eroare.', true);
  }

  function close() {
    var modal = document.getElementById(MODAL_ID);
    if (!modal || modal.hidden) return;
    if (modal.classList.contains('is-running')) return;
    busy = false;
    modal.hidden = true;
    modal.classList.remove('is-running', 'is-success', 'is-error');
    document.body.classList.remove('bpa-action-modal-open');
    var icon = document.getElementById('bpa-action-icon');
    if (icon) {
      icon.className = 'bpa-action-modal__icon bpa-action-modal__icon--run';
      icon.innerHTML = '<span class="bpa-action-modal__spinner"></span>';
    }
  }

  /**
   * @param {object} opts
   * @param {string} opts.title
   * @param {string} [opts.subtitle]
   * @param {string[]} [opts.steps]
   * @param {function(function(number,string?,boolean?)):Promise} opts.task — update(stepIndex, label?, indeterminate?)
   * @param {function(*):string} [opts.successMessage]
   * @param {function(Error):string} [opts.errorMessage]
   * @param {function(*):void} [opts.onSuccess]
   * @param {number} [opts.autoCloseMs]
   */
  function run(opts) {
    opts = opts || {};
    if (busy) {
      notify('O altă acțiune rulează deja — așteaptă finalizarea.', true);
      return Promise.reject(new Error('busy'));
    }

    var steps = opts.steps || [];
    busy = true;
    openShell(opts.title, opts.subtitle, steps);

    var activeStep = 0;
    var timer = null;
    var fakePct = 12;

    function tickFake() {
      if (fakePct < 88) {
        fakePct += 4 + Math.random() * 6;
        setPct(fakePct, false);
      }
    }
    timer = setInterval(tickFake, 420);

    function update(stepIndex, label, indeterminate) {
      if (typeof stepIndex === 'number') activeStep = stepIndex;
      if (label) {
        var lbl = document.getElementById('bpa-action-load-label');
        if (lbl) lbl.textContent = label;
      }
      if (steps.length) renderSteps(steps, activeStep, activeStep - 1, -1);
      if (indeterminate !== false && steps.length) {
        var stepPct = steps.length ? ((activeStep + 0.5) / steps.length) * 100 : fakePct;
        setPct(Math.max(fakePct, stepPct), false);
      }
    }

    return Promise.resolve()
      .then(function () { return opts.task(update); })
      .then(function (result) {
        clearInterval(timer);
        var msg = typeof opts.successMessage === 'function'
          ? opts.successMessage(result)
          : (opts.successMessage || (result && result.message) || 'Operațiune finalizată.');
        finishSuccess(msg, steps);
        if (typeof opts.onSuccess === 'function') opts.onSuccess(result);
        var autoMs = opts.autoCloseMs != null ? opts.autoCloseMs : 2800;
        if (autoMs > 0) {
          setTimeout(function () {
            if (!busy) return;
            busy = false;
            close();
          }, autoMs);
        } else {
          busy = false;
        }
        return result;
      })
      .catch(function (err) {
        clearInterval(timer);
        var msg = typeof opts.errorMessage === 'function'
          ? opts.errorMessage(err)
          : (err && err.message ? err.message : 'Eroare necunoscută.');
        finishError(msg, steps, activeStep);
        busy = false;
        throw err;
      });
  }

  function runFetchJson(opts) {
    var fetchOpts = opts.fetch || {};
    return run({
      title: opts.title,
      subtitle: opts.subtitle,
      steps: opts.steps,
      successMessage: opts.successMessage,
      errorMessage: opts.errorMessage,
      onSuccess: opts.onSuccess,
      autoCloseMs: opts.autoCloseMs,
      task: function (update) {
        var stepCount = (opts.steps || []).length || 3;
        update(0, 'Trimit cererea…', false);
        return fetch(fetchOpts.url, fetchOpts.init || {})
          .then(function (r) {
            update(Math.min(1, stepCount - 1), 'Procesare răspuns…', false);
            return r.json().then(function (j) { return { http: r, json: j }; });
          })
          .then(function (pack) {
            update(stepCount - 1, 'Finalizare…', false);
            var j = pack.json;
            if (fetchOpts.requireSuccess !== false && j && j.success === false) {
              throw new Error(j.message || 'Cererea a eșuat.');
            }
            if (typeof fetchOpts.validate === 'function') fetchOpts.validate(j);
            return j;
          });
      },
    });
  }

  var METRO_META = {
    test_metro_ollama: {
      title: 'Test Ollama',
      subtitle: 'Verificare model local qwen — fără tokeni cloud',
      steps: ['Conectare Ollama', 'Încărcare model', 'Generare răspuns test'],
    },
    test_metro_cloud: {
      title: 'Test Metro LLM',
      subtitle: 'Verificare lanț Ollama → Groq → Gemini → OpenRouter',
      steps: ['Validare provideri', 'Apel router Metro', 'Confirmare răspuns'],
    },
    test_metro_cycle: {
      title: 'Ciclu local Ollama',
      subtitle: 'Rulează joburi supervizor pe LLM local',
      steps: ['Pregătire ciclu', 'Execuție joburi', 'Actualizare rapoarte'],
    },
  };

  function runMetroTest(action, settingsApi, onSuccess) {
    var meta = METRO_META[action] || { title: 'Test Metro', steps: ['Pornire', 'Execuție', 'Finalizare'] };
    return runFetchJson({
      title: meta.title,
      subtitle: meta.subtitle,
      steps: meta.steps,
      fetch: {
        url: settingsApi || '/admin/api/settings_endpoint.php',
        init: {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: action }),
        },
      },
      successMessage: function (j) { return j.message || meta.title + ' — OK'; },
      onSuccess: onSuccess,
    });
  }

  var JOB_META = {
    supervisor_run_cycle: {
      title: 'Ciclu supervizor complet',
      subtitle: 'Toate fazele: catalog, pipeline, tokeni, furnizori…',
      steps: ['Pornire ciclu', 'Joburi fază 2–4', 'Raport final'],
    },
    supervisor_run_catalog: {
      title: 'Scan catalog produse',
      steps: ['Sample BD', 'Audit imagini/OEM', 'Calcul scor'],
    },
    supervisor_run_pipeline: {
      title: 'Test pipeline imagini',
      steps: ['Pregătire 50 teste', 'Verificare HTTP', 'Raport health'],
    },
    supervisor_run_tokens: {
      title: 'Analiză buget tokeni',
      steps: ['Citire chei API', 'Calcul consum', 'Alerte buget'],
    },
    supervisor_run_suppliers: {
      title: 'Scan furnizori',
      steps: ['Citire fișiere sync', 'Detectare fișiere noi', 'Validare'],
    },
    supervisor_run_composer_repair: {
      title: 'Composer Repair',
      steps: ['Colectare alerte', 'Analiză LLM', 'Aplicare fix-uri'],
    },
    supervisor_run_conversations: {
      title: 'Analiză conversații',
      steps: ['Citire mesaje', 'Detectare probleme', 'Semnale pozitive'],
    },
    composer_repair_run: {
      title: 'Composer — analiză & reparare',
      steps: ['Scan alerte', 'Plan reparații', 'Execuție sigură'],
    },
  };

  function runSupervisorJob(postFn, action, body, onSuccess) {
    var meta = JOB_META[action] || { title: action, steps: ['Pornire', 'Execuție', 'Finalizare'] };
    return run({
      title: meta.title,
      subtitle: meta.subtitle,
      steps: meta.steps,
      successMessage: function (j) { return (j && j.message) || meta.title + ' finalizat.'; },
      onSuccess: onSuccess,
      task: function (update) {
        update(0, 'Trimit comanda…');
        return postFn(Object.assign({ action: action }, body || {})).then(function (j) {
          update(1, 'Procesare pe server…');
          update(2, 'Actualizare date…');
          return j;
        });
      },
    });
  }

  window.BesoiuActionProgress = {
    run: run,
    runFetchJson: runFetchJson,
    runMetroTest: runMetroTest,
    runSupervisorJob: runSupervisorJob,
    close: close,
    notify: notify,
  };
})();
