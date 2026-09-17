/* Mi Server - JS del panel (vanilla, sin frameworks) */
(function () {
  'use strict';

  /* ---------- toast ---------- */
  function toast(msg, type) {
    var zone = document.getElementById('toast-zone');
    if (!zone) {
      zone = document.createElement('div');
      zone.id = 'toast-zone';
      document.body.appendChild(zone);
    }
    var el = document.createElement('div');
    el.className = 'toast ' + (type === 'ok' ? 'ok' : type === 'err' ? 'err' : type === 'warn' ? 'warn' : '');
    el.innerHTML = msg;
    zone.appendChild(el);
    setTimeout(function () { el.remove(); }, 5000);
  }

  function applyResponse(d) {
    if (!d) return;
    if (d.ok !== undefined) {
      toast(d.msg || (d.ok ? 'Correcto.' : 'Error.'), d.ok ? 'ok' : 'err');
    } else if (d.msg) {
      toast(d.msg, 'warn');
    }
    if (d.redirect) {
      setTimeout(function () { window.location.href = d.redirect; }, 350);
    }
  }

  function post(url, data) {
    var isForm = typeof FormData !== 'undefined' && data instanceof FormData;
    var body = isForm ? data : (typeof data === 'string' ? data : new URLSearchParams(data || {}));
    return fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: body,
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, msg: 'Respuesta no válida' }; }); })
      .then(applyResponse)
      .catch(function (e) { toast('Error de red: ' + e, 'err'); });
  }

  /* ---------- formularios data-ajax ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || form.getAttribute('data-ajax') !== '1') return;
    e.preventDefault();
    post(form.action, new FormData(form))
      .then(function (d) {
        if (d && !d.ok && form.getAttribute('name')) { /* noop */ }
      });
  });

  /* ---------- botones data-post ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-post]');
    if (!btn) return;
    e.preventDefault();
    var url = btn.getAttribute('data-post');
    var confirmMsg = btn.getAttribute('data-confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    var data = { _csrf: btn.getAttribute('data-csrf') || '' };
    var promptMsg = btn.getAttribute('data-prompt');
    if (promptMsg) {
      var val = window.prompt(promptMsg);
      if (val === null || val.trim() === '') return;
      var promptName = btn.getAttribute('data-prompt-name') || 'name';
      data[promptName] = val.trim();
    }
    for (var i = 0; i < btn.attributes.length; i++) {
      var at = btn.attributes[i];
      if (at.name.indexOf('data-extra-') === 0) data[at.name.slice(11)] = at.value;
    }
    post(url, data);
  });

  /* ---------- menú móvil ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle]');
    if (!t) return;
    var sel = t.getAttribute('data-toggle');
    var el = document.querySelector(sel);
    if (el) el.classList.toggle('open');
  });

  /* autofocus suave para inputs en páginas auth */
  window.addEventListener('load', function () {
    var f = document.querySelector('.auth-form input:not(.hidden)');
    if (f && !f.value) f.focus();
  });
})();