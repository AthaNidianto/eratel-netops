<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Metode tidak didukung.'], 405);
}
// Header kustom ini tidak bisa dikirim lintas situs tanpa izin CORS, jadi cukup untuk menolak request dari situs lain.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'netops') {
    json_out(['error' => 'Request tidak valid.'], 403);
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

    if ($name === '' || mb_strlen($name) > 80) {
        json_out(['error' => 'Nama perangkat wajib diisi (maksimal 80 karakter).'], 422);
    }
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', (string) $host)) {
        json_out(['error' => 'IP Address harus berformat 157.20.207.3:3011 atau nama host.'], 422);
    }

    if ($id > 0) {
        $st = $pdo->prepare('SELECT id FROM devices WHERE id = ?');
        $st->execute([$id]);
        if (!$st->fetch()) {
            json_out(['error' => 'Perangkat tidak ditemukan.'], 404);
        }
        $pdo->prepare('UPDATE devices SET name = ?, host = ?, proto = ? WHERE id = ?')
            ->execute([$name, $host, $proto, $id]);
    } else {
        $pdo->prepare('INSERT INTO devices (name, host, proto, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$name, $host, $proto, time()]);
    }
    json_out(['ok' => true]);
}

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM devices WHERE id = ?')->execute([(int) ($in['id'] ?? 0)]);
    json_out(['ok' => true]);
}

json_out(['error' => 'Aksi tidak dikenal.'], 400);