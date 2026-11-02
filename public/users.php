<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$pdo = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $err = 'Sesi form kedaluwarsa. Muat ulang halaman.';
    } else {
        $do = $_POST['do'] ?? '';
        $uid = (int) ($_POST['id'] ?? 0);
        $pass = (string) ($_POST['password'] ?? '');

        if ($do === 'add') {
            $name = trim((string) ($_POST['username'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $name)) {
                $err = 'Username 3-32 karakter: huruf, angka, titik, minus atau underscore.';
            } elseif (strlen($pass) < 8) {
                $err = 'Password minimal 8 karakter.';
            } else {
                try {
                    $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)')
                        ->execute([$name, password_hash($pass, PASSWORD_DEFAULT), time()]);
                    $msg = "Pengguna {$name} ditambahkan.";
                } catch (PDOException $ex) {
                    $err = 'Username sudah dipakai.';
                }
            }
        } elseif ($do === 'password') {
            if (strlen($pass) < 8) {
                $err = 'Password minimal 8 karakter.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                $msg = 'Password diperbarui.';
            }
        } elseif ($do === 'delete') {
            if ($uid === (int) $_SESSION['uid']) {
                $err = 'Akun yang sedang dipakai tidak bisa dihapus.';
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                $msg = 'Pengguna dihapus.';
            }
        }
    }
}

$users = $pdo->query('SELECT id, username, created_at FROM users ORDER BY username')->fetchAll();

page_start('Pengguna · Eratel NetOps');
?>
<main class="wrap main narrow">
  <h2 class="pagetitle">Pengguna panel</h2>
  <p class="foot" style="margin-top:0">Semua pengguna punya akses penuh ke daftar perangkat. Tambahkan akun untuk tiap teknisi.</p>
  <?php if ($msg): ?><div class="note ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="note err"><?= e($err) ?></div><?php endif; ?>

  <div class="panel">
    <?php foreach ($users as $u): ?>
      <form class="urow" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <div class="uname"><b><?= e($u['username']) ?></b>
          <?php if ((int) $u['id'] === (int) $_SESSION['uid']): ?><span class="chip tls">ANDA</span><?php endif; ?></div>
        <label class="sr" for="pw<?= (int) $u['id'] ?>">Password baru untuk <?= e($u['username']) ?></label>
        <input id="pw<?= (int) $u['id'] ?>" class="in" type="password" name="password" placeholder="Password baru" autocomplete="new-password">
        <button class="btn" name="do" value="password" type="submit">Ganti</button>
        <?php if ((int) $u['id'] !== (int) $_SESSION['uid']): ?>
          <button class="btn danger" name="do" value="delete" type="submit" onclick="return confirm('Hapus pengguna <?= e($u['username']) ?>?')">Hapus</button>
        <?php endif; ?>
      </form>
    <?php endforeach; ?>
  </div>

  <h3 class="sub">Tambah pengguna</h3>
  <form class="panel addrow" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="do" value="add">
    <label class="sr" for="nu">Username</label>
    <input id="nu" class="in" name="username" placeholder="Username" required autocomplete="off">
    <label class="sr" for="np">Password</label>
    <input id="np" class="in" type="password" name="password" placeholder="Password (min. 8 karakter)" required autocomplete="new-password">
    <button class="btn primary" type="submit">Tambah</button>
  </form>
</main>
<?php page_end(); ?>
