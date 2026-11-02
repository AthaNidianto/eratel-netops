// ==UserScript==
// @name         Eratel WebFig Auto Login
// @namespace    eratel-netops
// @version      1.0
// @description  Mengisi login WebFig MikroTik otomatis saat dibuka dari Eratel NetOps
// @match        http://*/webfig/*
// @match        https://*/webfig/*
// @run-at       document-start
// @grant        none
// ==/UserScript==

(function () {
  'use strict';

  // Link dari Eratel NetOps: http://IP:PORT/webfig/#nl=<base64 {u,p}>
  var m = location.hash.match(/^#nl=([^&]+)/);
  if (!m) return;

  var cred;
  try {
    cred = JSON.parse(decodeURIComponent(escape(atob(decodeURIComponent(m[1])))));
  } catch (e) { return; }

  // Hapus kredensial dari address bar dan history agar tidak ikut tersimpan.
  try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}

  var tries = 0;
  var timer = setInterval(attempt, 200);

  function visible(el) { return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); }

  function setVal(el, v) {
    var desc = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value');
    if (desc && desc.set) desc.set.call(el, v); else el.value = v;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function attempt() {
    if (++tries > 100) { clearInterval(timer); return; } // menyerah setelah ~20 detik
    var pw = [].slice.call(document.querySelectorAll('input[type=password]')).filter(visible)[0];
    if (!pw) return;
    clearInterval(timer);

    var userInput = [].slice.call(document.querySelectorAll('input')).filter(function (i) {
      var t = (i.getAttribute('type') || 'text').toLowerCase();
      return (t === 'text' || t === 'email') && visible(i);
    })[0];

    if (userInput && cred.u != null) setVal(userInput, cred.u);
    setVal(pw, cred.p || '');

    setTimeout(function () {
      var btn = [].slice.call(document.querySelectorAll('button, input[type=submit], input[type=button], a'))
        .filter(visible)
        .filter(function (b) { return /log\s*in|masuk|sign\s*in/i.test((b.textContent || b.value || '').trim()); })[0];
      if (btn) { btn.click(); return; }
      ['keydown', 'keypress', 'keyup'].forEach(function (type) {
        pw.dispatchEvent(new KeyboardEvent(type, { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
      });
    }, 250);
  }
})();
