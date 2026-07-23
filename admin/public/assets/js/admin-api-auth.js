/**
 * Auth helper pentru fetch /admin/api/* — credentials + redirect la login pe 401.
 */
(function (global) {
  'use strict';

  var LOGIN = '/admin/login';
  var redirecting = false;

  function requestUrl(input) {
    if (typeof input === 'string') {
      return input;
    }
    if (input && typeof input.url === 'string') {
      return input.url;
    }
    return '';
  }

  function isAdminApi(input) {
    var url = requestUrl(input);
    return url.indexOf('/admin/api/') !== -1 || url.indexOf('/admin/public/api/') !== -1;
  }

  function redirectLogin() {
    if (redirecting) {
      return;
    }
    redirecting = true;
    var next = encodeURIComponent(global.location.pathname + global.location.search);
    global.location.href = LOGIN + '?next=' + next;
  }

  function attachCsrf(init) {
    var meta = global.document && global.document.querySelector('meta[name="csrf-token"]');
    if (!meta || !meta.content) {
      return init;
    }
    init.headers = init.headers || {};
    if (init.headers instanceof global.Headers) {
      if (!init.headers.has('X-Admin-CSRF')) {
        init.headers.set('X-Admin-CSRF', meta.content);
      }
      return init;
    }
    if (!init.headers['X-Admin-CSRF']) {
      init.headers['X-Admin-CSRF'] = meta.content;
    }
    return init;
  }

  global.BesoiuAdminAuth = {
    isAdminApi: isAdminApi,
    redirectLogin: redirectLogin,
    handleUnauthorized: function (response) {
      if (response && response.status === 401) {
        redirectLogin();
        return true;
      }
      return false;
    },
  };

  if (typeof global.fetch !== 'function') {
    return;
  }

  var nativeFetch = global.fetch.bind(global);
  global.fetch = function (input, init) {
    init = init || {};
    if (isAdminApi(input)) {
      init.credentials = init.credentials || 'same-origin';
    }
    init = attachCsrf(init);
    return nativeFetch(input, init).then(function (response) {
      if (isAdminApi(input) && response.status === 401) {
        redirectLogin();
      }
      return response;
    });
  };
})(window);
