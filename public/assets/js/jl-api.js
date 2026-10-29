(function () {
  'use strict';

  const API = (function () {
    const path = window.location.pathname || '/public/index.php';
    const publicPath = path.replace(/\/public(?:\/.*)?$/, '/public');
    return publicPath.replace(/\/$/, '') + '/api/rpc.php';
  })();

  function normalizeError(error) {
    if (error && typeof error === 'object' && error.message) return error;
    return new Error(typeof error === 'string' ? error : 'Kesalahan server aplikasi.');
  }

  function request(method, args) {
    return fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Jago-Laba-API': '1',
        'X-CSRF-Token': window.JL_SESSION?.csrf || '',
      },
      body: JSON.stringify({ method: String(method), args: args || [] }),
    }).then(async (response) => {
      let body;
      try {
        body = await response.json();
      } catch (error) {
        throw new Error('Respons backend bukan JSON valid (HTTP ' + response.status + ').');
      }
      if (!response.ok || !body || body.ok !== true) {
        const message = body?.error?.message || ('Operasi ' + String(method) + ' gagal.');
        throw new Error(message);
      }
      return body.result;
    });
  }

  function runner(state) {
    const current = state || { success: null, failure: null, userObject: null };
    return new Proxy({}, {
      get: function (_target, property) {
        if (property === 'withSuccessHandler') {
          return function (handler) { return runner({ ...current, success: typeof handler === 'function' ? handler : null }); };
        }
        if (property === 'withFailureHandler') {
          return function (handler) { return runner({ ...current, failure: typeof handler === 'function' ? handler : null }); };
        }
        if (property === 'withUserObject') {
          return function (object) { return runner({ ...current, userObject: object }); };
        }
        if (property === 'then') return undefined;
        return function (...args) {
          const promise = request(property, args);
          promise.then(function (result) {
            if (current.success) current.success(result, current.userObject);
          }).catch(function (error) {
            console.error('JAGO LABA API ERROR [' + String(property) + ']:', error);
            if (current.failure) current.failure(normalizeError(error), current.userObject);
          });
          return promise;
        };
      },
    });
  }

  window.jlApi = {
    api: API,
    run: runner(),
    call: request,
    ready: true,
  };
})();
