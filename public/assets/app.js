(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var editing = null, delTimer = null;

  function api(body) {
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'netops' },
      body: JSON.stringify(body),
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().catch(function () { return { error: 'Respons server tidak valid.' }; })
        .then(function (j) { if (!r.ok || j.error) throw new Error(j.error || 'Gagal.'); return j; });
    });
  }

  function flash(msg, isErr) {
    var s = $('#status');
    s.textContent = '';
    if (!msg) return;
    var d = document.createElement('div');
    d.className = 'note' + (isErr ? ' err' : '');
    d.textContent = msg;
    s.appendChild(d);
    clearTimeout(flash.t);
    flash.t = setTimeout(function () { s.textContent = ''; }, 6000);
  }

  /* ---------- cari ---------- */
  var cards = [].slice.call(document.querySelectorAll('.card'));
  function filter() {
    var q = $('#q').value.trim().toLowerCase(), shown = 0;
    cards.forEach(function (c) {
      var hit = !q || c.dataset.search.indexOf(q) > -1;
      c.hidden = !hit;
      if (hit) shown++;
    });
    $('#count').textContent = (q ? shown + ' dari ' : '') + cards.length + ' perangkat';
    var empty = $('#empty');
    empty.hidden = shown > 0;
    if (!cards.length) {
      $('#empty-title').textContent = 'Belum ada perangkat';
      $('#empty-text').textContent = 'Tambahkan CRS pertama dengan nama dan IP:port WebFig-nya.';
    } else {
      $('#empty-title').textContent = 'Tidak ada hasil';
      $('#empty-text').textContent = 'Tidak ada perangkat yang cocok dengan "' + $('#q').value.trim() + '".';
    }
  }
  $('#q').addEventListener('input', filter);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !$('#modal').hidden) closeModal();
    if (e.key === '/' && $('#modal').hidden && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
      e.preventDefault(); $('#q').focus();
    }
  });

  /* ---------- modal ---------- */
  function openModal(card) {
    editing = card || null;
    var d = card ? card.dataset : null;
    $('#mtitle').textContent = d ? 'Edit ' + d.name : 'Unit Baru';
    $('#f-name').value = d ? d.name : '';
    $('#f-host').value = d ? d.host : '';
    $('#f-proto').value = d && d.proto === 'https' ? 'https' : 'http';
    $('#f-err').hidden = true;
    var del = $('#f-del');
    del.hidden = !d; del.textContent = 'Hapus'; del.classList.remove('armed');
    $('#modal').hidden = false;
    setTimeout(function () { $('#f-name').focus(); }, 0);
  }
  function closeModal() { $('#modal').hidden = true; editing = null; clearTimeout(delTimer); }
  function formError(m) { var x = $('#f-err'); x.textContent = m; x.hidden = !m; }

  $('#add').addEventListener('click', function () { openModal(null); });
  $('#f-cancel').addEventListener('click', closeModal);
  $('#modal').addEventListener('mousedown', function (e) { if (e.target === this) closeModal(); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.js-edit');
    if (b) openModal(b.closest('.card'));
  });
  $('#form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('#f-save'); btn.disabled = true; formError('');
    api({
      action: 'save',
      id: editing ? Number(editing.dataset.id) : 0,
      name: $('#f-name').value, host: $('#f-host').value, proto: $('#f-proto').value
    }).then(function () { location.reload(); })
      .catch(function (err) { formError(err.message); btn.disabled = false; });
  });

  $('#f-del').addEventListener('click', function () {
    var b = this;
    if (!b.classList.contains('armed')) {
      b.classList.add('armed'); b.textContent = 'Klik lagi untuk hapus';
      delTimer = setTimeout(function () { b.classList.remove('armed'); b.textContent = 'Hapus'; }, 4000);
      return;
    }
    api({ action: 'delete', id: Number(editing.dataset.id) })
      .then(function () { location.reload(); })
      .catch(function (err) { formError(err.message); });
  });
})();