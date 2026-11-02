<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (!logged_in()) {
    json_out(['error' => 'Sesi habis. Muat ulang halaman dan masuk lagi.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Metode tidak didukung.'], 405);
}
if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_out(['error' => 'Token sesi tidak valid. Muat ulang halaman.'], 403);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(['error' => 'Data tidak valid.'], 400);
}

$pdo = db();
$action = $in['action'] ?? '';

if ($action === 'save') {
    $id    = isset($in['id']) ? (int) $in['id'] : 0;
    $name  = trim((string) ($in['name'] ?? ''));
    $host  = preg_replace('#^https?://#i', '', trim((string) ($in['host'] ?? '')));
    $host  = preg_replace('#/.*$#', '', (string) $host);
    $proto = ($in['proto'] ?? 'http') === 'https' ? 'https' : 'http';
    $user  = trim((string) ($in['user'] ?? ''));
    $pass  = (string) ($in['pass'] ?? '');
    $clear = !empty($in['nopass']);

    if ($name === '' || mb_strlen($name) > 80) {
        json_out(['error' => 'Nama perangkat wajib diisi (maksimal 80 karakter).'], 422);
    }
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', (string) $host)) {
        json_out(['error' => 'IP Address harus berformat 157.20.207.3:3011 atau nama host.'], 422);
    }
    if (mb_strlen($user) > 64 || mb_strlen($pass) > 128) {
        json_out(['error' => 'Username atau password terlalu panjang.'], 422);
    }

    if ($id > 0) {
        $st = $pdo->prepare('SELECT id FROM devices WHERE id = ?');
        $st->execute([$id]);
        if (!$st->fetch()) {
            json_out(['error' => 'Perangkat tidak ditemukan.'], 404);
        }
        $pdo->prepare('UPDATE devices SET name = ?, host = ?, proto = ?, username = ? WHERE id = ?')
            ->execute([$name, $host, $proto, $user, $id]);
        if ($pass !== '') {
            $pdo->prepare('UPDATE devices SET password_enc = ? WHERE id = ?')->execute([enc($pass), $id]);
        } elseif ($clear) {
            $pdo->prepare('UPDATE devices SET password_enc = "" WHERE id = ?')->execute([$id]);
        }
    } else {
        $pdo->prepare('INSERT INTO devices (name, host, proto, username, password_enc, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $host, $proto, $user, enc($pass), time()]);
    }
    json_out(['ok' => true]);
}

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM devices WHERE id = ?')->execute([(int) ($in['id'] ?? 0)]);
    json_out(['ok' => true]);
}

if ($action === 'connect') {
    $st = $pdo->prepare('SELECT * FROM devices WHERE id = ?');
    $st->execute([(int) ($in['id'] ?? 0)]);
    $d = $st->fetch();
    if (!$d) {
        json_out(['error' => 'Perangkat tidak ditemukan.'], 404);
    }
    json_out(['url' => connect_url($d)]);
}

json_out(['error' => 'Aksi tidak dikenal.'], 400);
