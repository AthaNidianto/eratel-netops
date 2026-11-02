<?php
declare(strict_types=1);

/* ---------- session & header keamanan ---------- */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('netops');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

/* ---------- util ---------- */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- penyimpanan ---------- */
function data_dir(): string
{
    $dir = getenv('DATA_DIR') ?: dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . data_dir() . '/netops.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS devices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        host TEXT NOT NULL,
        proto TEXT NOT NULL DEFAULT "http",
        username TEXT NOT NULL DEFAULT "",
        password_enc TEXT NOT NULL DEFAULT "",
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT NOT NULL,
        at INTEGER NOT NULL
    )');

    // Akun admin pertama
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $user = getenv('ADMIN_USER') ?: 'admin';
        $pass = getenv('ADMIN_PASSWORD') ?: '';
        if ($pass === '') {
            $pass = bin2hex(random_bytes(8));
            error_log("[netops] ADMIN_PASSWORD kosong. Akun awal: {$user} / {$pass}");
        }
        $st = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)');
        $st->execute([$user, password_hash($pass, PASSWORD_DEFAULT), time()]);
    }

    // Isi awal daftar CRS (opsional, hanya jika tabel kosong)
    if (getenv('SEED_CRS') === '1'
        && (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO devices (name, host, proto, username, password_enc, created_at) VALUES (?, ?, ?, ?, "", ?)');
        $t = time();
        foreach (require __DIR__ . '/seed.php' as $i => $d) {
            $st->execute([$d[0], $d[1], 'http', $d[2], $t + $i]);
        }
    }
}

/* ---------- enkripsi password perangkat (libsodium) ---------- */
function app_key(): string
{
    $env = getenv('APP_KEY');
    if ($env) {
        $key = base64_decode($env, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY harus base64 dari 32 byte.');
        }
        return $key;
    }
    $file = data_dir() . '/app.key';
    if (!is_file($file)) {
        file_put_contents($file, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        chmod($file, 0600);
    }
    return base64_decode(trim((string) file_get_contents($file)));
}

function enc(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, app_key()));
}

function dec(string $stored): string
{
    if ($stored === '') {
        return '';
    }
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return '';
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, app_key());
    return $plain === false ? '' : $plain;
}

/* ---------- autentikasi & CSRF ---------- */
function logged_in(): bool
{
    return isset($_SESSION['uid']);
}

function require_login(): void
{
    if (!logged_in()) {
        redirect('login.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $sent): bool
{
    return $sent !== null && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function too_many_attempts(): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([client_ip(), time() - 600]);
    return (int) $st->fetchColumn() >= 5;
}

function record_failed_attempt(): void
{
    db()->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([client_ip(), time()]);
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 86400]);
}

function clear_attempts(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
}

/* ---------- link WebFig ---------- */
function connect_url(array $d): string
{
    $base = ($d['proto'] === 'https' ? 'https' : 'http') . '://' . $d['host'] . '/webfig/';
    $pass = dec($d['password_enc']);
    if ($d['username'] === '' && $pass === '') {
        return $base;
    }
    $payload = base64_encode(json_encode(['u' => $d['username'], 'p' => $pass], JSON_UNESCAPED_UNICODE));
    return $base . '#nl=' . rawurlencode($payload);
}

/* ---------- layout ---------- */
function page_start(string $title, bool $withNav = true): void
{
    $user = $_SESSION['uname'] ?? '';
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php if ($withNav): ?>
<header class="top">
  <div class="wrap bar">
    <a class="brand" href="index.php">
      <span class="mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 16.5h.01M11 16.5h.01"/><path d="M8 10a5.7 5.7 0 0 1 8 0M5.5 7.5a9.2 9.2 0 0 1 13 0"/></svg></span>
      <span><b>Eratel NetOps</b><small>DEVICE MANAGEMENT</small></span>
    </a>
    <nav class="nav">
      <a href="users.php">Pengguna</a>
      <span class="who"><?= e($user) ?></span>
      <a href="logout.php">Keluar</a>
    </nav>
  </div>
</header>
<?php endif;
}

function page_end(string $script = ''): void
{
    if ($script !== '') {
        echo '<script src="assets/' . e($script) . '"></script>';
    }
    echo "\n</body>\n</html>\n";
}
