<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

// Password perangkat sengaja tidak dibaca di sini; hanya dikirim saat tombol Connect ditekan.
$devices = db()->query('SELECT id, name, host, proto, username, (password_enc <> "") AS has_pass FROM devices ORDER BY id')->fetchAll();

page_start('Eratel NetOps');
?>
<main class="wrap main">
  <div class="top-actions">
    <div class="search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <label class="sr" for="q">Cari perangkat</label>
      <input id="q" type="search" placeholder="Cari IP Address atau Nama Perangkat…  ( tekan / )" autocomplete="off">
    </div>
    <span id="count" class="count"><?= count($devices) ?> perangkat</span>
    <button id="add" class="btn primary" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Unit Baru
    </button>
  </div>

  <div id="status" role="status" aria-live="polite"></div>

  <section id="grid" class="grid" aria-label="Daftar perangkat">
    <?php foreach ($devices as $d): ?>
      <article class="card"
               data-id="<?= (int) $d['id'] ?>"
               data-name="<?= e($d['name']) ?>"
               data-host="<?= e($d['host']) ?>"
               data-proto="<?= e($d['proto']) ?>"
               data-user="<?= e($d['username']) ?>"
               data-haspass="<?= $d['has_pass'] ? '1' : '0' ?>"
               data-search="<?= e(strtolower($d['name'] . ' ' . $d['host'] . ' ' . $d['username'])) ?>">
        <div class="head">
          <h2><?= e($d['name']) ?></h2>
          <span class="chip<?= $d['proto'] === 'https' ? ' tls' : '' ?>"><?= $d['proto'] === 'https' ? 'HTTPS' : 'HTTP' ?></span>
        </div>
        <div class="fields">
          <dl class="field"><dt>IP Address</dt><dd><?= e($d['host']) ?></dd></dl>
          <dl class="field"><dt>Username</dt><dd><?= e($d['username'] ?: '-') ?></dd></dl>
        </div>
        <div class="acts">
          <button class="btn go js-connect" type="button">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/></svg>
            Connect WebFig
          </button>
          <button class="btn icon js-edit" type="button" title="Edit" aria-label="Edit <?= e($d['name']) ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          </button>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <div id="empty" class="empty" <?= $devices ? 'hidden' : '' ?>>
    <strong id="empty-title"><?= $devices ? 'Tidak ada hasil' : 'Belum ada perangkat' ?></strong>
    <span id="empty-text">Tambahkan CRS pertama dengan nama, IP:port, username dan password WebFig-nya.</span>
  </div>

  <p class="foot">
    Connect WebFig membuka WebFig di tab baru dan mengisi login otomatis lewat userscript Tampermonkey.
    <a href="eratel-webfig-autologin.user.js">Pasang userscript</a>. Tanpa userscript, WebFig tetap terbuka dan login dilakukan manual.
  </p>
</main>

<div id="modal" class="scrim" hidden>
  <form id="form" class="modal" autocomplete="off">
    <h3 id="mtitle">Unit Baru</h3>
    <div class="row"><label class="l" for="f-name">Nama perangkat</label><input id="f-name" class="in" required maxlength="80" placeholder="CRS KLALING"></div>
    <div class="row2">
      <div class="row"><label class="l" for="f-host">IP Address : Port</label><input id="f-host" class="in mono" required placeholder="157.20.207.3:3011"></div>
      <div class="row"><label class="l" for="f-proto">Protokol</label>
        <select id="f-proto" class="in"><option value="http">HTTP</option><option value="https">HTTPS</option></select></div>
    </div>
    <div class="row"><label class="l" for="f-user">Username</label><input id="f-user" class="in mono" maxlength="64" placeholder="teknisi"></div>
    <div class="row"><label class="l" for="f-pass">Password</label>
      <div class="pw"><input id="f-pass" class="in mono" type="password" autocomplete="new-password"><button id="f-show" type="button">Tampilkan</button></div>
    </div>
    <p class="hint" id="f-hint"></p>
    <label class="check" id="f-nopass-row" hidden><input id="f-nopass" type="checkbox"> Hapus password yang tersimpan</label>
    <div id="f-err" class="note err" hidden></div>
    <div class="mfoot">
      <button id="f-del" class="btn danger" type="button" hidden>Hapus</button>
      <div class="r"><button id="f-cancel" class="btn" type="button">Batal</button><button id="f-save" class="btn primary" type="submit">Simpan</button></div>
    </div>
  </form>
</div>
<?php page_end('app.js'); ?>
