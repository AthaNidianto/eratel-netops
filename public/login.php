<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (too_many_attempts()) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $st = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        // verifikasi selalu dijalankan agar waktu respons tidak membocorkan username valid
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ3m5v0mQy3m2M5bGq1h1cQeW9m0xW';
        $ok = password_verify((string) ($_POST['password'] ?? ''), $hash);
        if ($u && $ok) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            $_SESSION['uname'] = $u['username'];
            clear_attempts();
            redirect('index.php');
        }
        record_failed_attempt();
        $error = 'Username atau password salah.';
    }
}

page_start('Masuk · Eratel NetOps', false);
?>
<main class="login">
  <form class="modal login-card" method="post" autocomplete="on">
    <div class="login-brand">
      <span class="mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 16.5h.01M11 16.5h.01"/><path d="M8 10a5.7 5.7 0 0 1 8 0M5.5 7.5a9.2 9.2 0 0 1 13 0"/></svg></span>
      <div><h1>Eratel NetOps</h1><p class="eyebrow">DEVICE MANAGEMENT</p></div>
    </div>
    <?php if ($error): ?><div class="note err"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="row"><label class="l" for="username">Username</label>
      <input id="username" name="username" class="in" required autofocus autocomplete="username"></div>
    <div class="row"><label class="l" for="password">Password</label>
      <input id="password" name="password" type="password" class="in" required autocomplete="current-password"></div>
    <button class="btn primary block" type="submit">Masuk</button>
  </form>
</main>
<?php page_end(); ?>
