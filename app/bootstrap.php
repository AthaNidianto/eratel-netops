<?php
declare(strict_types=1);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

/* ---------- util ---------- */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    $pdo->exec('CREATE TABLE IF NOT EXISTS devices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        host TEXT NOT NULL,
        proto TEXT NOT NULL DEFAULT "http",
        created_at INTEGER NOT NULL
    )');

    // Isi awal daftar CRS (opsional, hanya jika tabel kosong)
    if (getenv('SEED_CRS') === '1'
        && (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO devices (name, host, proto, created_at) VALUES (?, ?, ?, ?)');
        $t = time();
        foreach (require __DIR__ . '/seed.php' as $i => $d) {
            $st->execute([$d[0], $d[1], 'http', $t + $i]);
        }
    }
}

/* ---------- link WebFig ---------- */
function webfig_url(array $d): string
{
    return ($d['proto'] === 'https' ? 'https' : 'http') . '://' . $d['host'] . '/';
}

/* ---------- layout ---------- */
function page_start(string $title): void
{
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="top">
  <div class="wrap bar">
    <a class="brand" href="index.php">
      <span class="mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 16.5h.01M11 16.5h.01"/><path d="M8 10a5.7 5.7 0 0 1 8 0M5.5 7.5a9.2 9.2 0 0 1 13 0"/></svg></span>
      <span><b>Eratel NetOps</b><small>DEVICE MANAGEMENT</small></span>
    </a>
  </div>
</header>
<?php
}

function page_end(string $script = ''): void
{
    if ($script !== '') {
        echo '<script src="assets/' . e($script) . '"></script>';
    }
    echo "\n</body>\n</html>\n";
}