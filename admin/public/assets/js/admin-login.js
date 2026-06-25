/**
 * Autentificare admin — Besoiu login 2026
 */
(function () {
  'use strict';

  var SELECTOR_GROUPS = {
    form: ['#admin-login-form', '#addusers', 'form[data-endpoint*="addusersadd"]'],
    user: ['#admin-login-user', '#login', 'input[name="login"]'],
    password: ['#admin-login-password', '#password', 'input[name="password"]'],
    toggle: ['#admin-login-toggle-pass', '#btnTogglePass'],
    status: ['#admin-login-status', '#loginStatus'],
    submit: ['#admin-login-submit', 'button[type="submit"]'],
    loader: ['#admin-page-loader', '.page-loader', '#page-loader']
  };

  function pick(selectors) {
    for (var i = 0; i < selectors.length; i++) {
      var el = document.querySelector(selectors[i]);
      if (el) {
        return el;
      }
    }
    return null;
  }

  function setStatus(el, type, message) {
    if (!el) {
      return;
    }
    el.textContent = message;
    el.classList.add('is-visible');
    el.classList.remove(
      'besoiu-login__status--idle',
      'besoiu-login__status--loading',
      'besoiu-login__status--error',
      'besoiu-login__status--success'
    );
    el.classList.add('besoiu-login__status--' + type);
  }

  function hidePageLoader() {
    SELECTOR_GROUPS.loader.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (loader) {
        loader.style.pointerEvents = 'none';
        loader.setAttribute('aria-hidden', 'true');
        loader.classList.add('opacity-0');
        window.setTimeout(function () {
          loader.classList.add('hidden');
        }, 300);
      });
    });
  }

  function refreshLucideIcon(node) {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({
        icons: window.lucide.icons,
        attrs: { 'stroke-width': 1.75 },
        nameAttr: 'data-lucide',
        root: node || document
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    hidePageLoader();
    refreshLucideIcon();

    var form = pick(SELECTOR_GROUPS.form);
    var login = pick(SELECTOR_GROUPS.user);
    var password = pick(SELECTOR_GROUPS.password);
    var toggle = pick(SELECTOR_GROUPS.toggle);
    var status = pick(SELECTOR_GROUPS.status);
    var submitBtn = pick(SELECTOR_GROUPS.submit);

    if (!form) {
      console.warn('admin-login: form negăsit');
      return;
    }

    function bindTogglePassword(btn) {
      if (!btn || !password || btn.dataset.boundTogglePass === '1') {
        return;
      }
      btn.dataset.boundTogglePass = '1';
      btn.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Ascunde parola' : 'Arată parola');
        btn.innerHTML = show
          ? '<i data-lucide="eye-off"></i>'
          : '<i data-lucide="eye"></i>';
        refreshLucideIcon(btn);
      });
    }

    bindTogglePassword(toggle);
    document.querySelectorAll('[data-action="toggle-password"]').forEach(bindTogglePassword);

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      e.stopPropagation();

      var endpoint = form.dataset.endpoint || '/admin/addusersadd';
      var method = (form.dataset.method || 'POST').toUpperCase();
      var nextParam = '';
      try {
        nextParam = new URLSearchParams(window.location.search).get('next') || '';
      } catch (e) { /* ignore */ }
      if (!nextParam && form.dataset.next) {
        nextParam = form.dataset.next;
      }

      var data = {
        type_product: 'login',
        login: (login && login.value ? login.value : '').trim(),
        password: password && password.value ? password.value : ''
      };
      if (nextParam && nextParam.indexOf('/admin/') === 0) {
        data.next = nextParam;
      }

      if (!data.login || !data.password) {
        setStatus(status, 'error', 'Completează utilizatorul și parola.');
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
      }
      setStatus(status, 'loading', 'Se verifică datele…');

      try {
        var res = await fetch(endpoint, {
          method: method,
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          body: JSON.stringify(data)
        });

        var raw = await res.text();
        var json;

        try {
          json = JSON.parse(raw);
        } catch (parseErr) {
          throw new Error('Răspuns invalid de la server.');
        }

        if (!res.ok || json.success !== true) {
          throw new Error(json.message || 'Date de autentificare incorecte.');
        }

        setStatus(status, 'success', 'Autentificare reușită — redirecționare…');
        window.location.href = json.redirect || '/admin/dashboard';
      } catch (err) {
        setStatus(status, 'error', err.message || 'Autentificare eșuată.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
        }
      }
    });
  });
})();
