<?php
declare(strict_types=1);

require_once __DIR__ . '/profile_lib.php'; // uses write_placeholder_profile helpers if you have it
// If you don't have profile_lib.php, you can paste shard2/normalize_domain/write_placeholder_profile here.

$TOKEN = '69505860032473051613071920';

// Protect endpoint
$token = $_GET['token'] ?? '';
if (!is_string($token) || $token !== $TOKEN) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

$dbPath = __DIR__ . '/submissions.sqlite';

function normalize_domain_from_url(string $url): string {
  $url = trim($url);
  if ($url === '') return '';
  if (!preg_match('/^https?:\/\//i', $url)) $url = 'https://' . $url;
  $parts = parse_url($url);
  if (!is_array($parts) || empty($parts['host'])) return '';
  $host = strtolower((string)$parts['host']);
  $host = preg_replace('/^www\./i', '', $host);
  if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) return '';
  return $host;
}

function normalize_url(string $url): string {
  $url = trim($url);
  if ($url === '') return '';
  if (!preg_match('/^https?:\/\//i', $url)) $url = 'https://' . $url;
  return $url;
}

function clean(string $v): string {
  $v = trim($v);
  $v = str_replace(["\r\n", "\r"], "\n", $v);
  if (strlen($v) > 5000) $v = substr($v, 0, 5000);
  return $v;
}

function ensure_schema(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS submissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      domain TEXT NOT NULL UNIQUE,
      url TEXT NOT NULL,
      name TEXT NOT NULL,
      category TEXT NOT NULL,
      city TEXT NOT NULL,
      state TEXT NOT NULL,
      phone TEXT,
      service_area TEXT,
      email TEXT,
      notes TEXT,
      status TEXT NOT NULL DEFAULT 'new',
      last_error TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      source TEXT NOT NULL DEFAULT 'submit'
    );
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_submissions_status ON submissions(status);");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_submissions_updated_at ON submissions(updated_at);");

  // Add source column if missing (safe)
  $cols = $pdo->query("PRAGMA table_info(submissions)")->fetchAll();
  $hasSource = false;
  foreach ($cols as $c) {
    if (($c['name'] ?? '') === 'source') { $hasSource = true; break; }
  }
  if (!$hasSource) {
    $pdo->exec("ALTER TABLE submissions ADD COLUMN source TEXT NOT NULL DEFAULT 'submit';");
  }
}

$results = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
      throw new RuntimeException("No file uploaded.");
    }

    $tmp = $_FILES['csv']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) throw new RuntimeException("Unable to read uploaded file.");

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("PRAGMA journal_mode=WAL;");
    $pdo->exec("PRAGMA synchronous=NORMAL;");
    ensure_schema($pdo);

    // Read header
    $header = fgetcsv($fh);
    if (!$header) throw new RuntimeException("CSV is empty.");
    $map = [];
    foreach ($header as $i => $col) {
      $map[strtolower(trim($col))] = $i;
    }

    // Expected header keys
    $kName = 'company name';
    $kUrl  = 'website url';
    $kPhone= 'phone number';
    $kCounty='county';

    if (!isset($map[$kUrl])) {
      throw new RuntimeException("CSV missing required column: Website URL");
    }

    $now = gmdate('c');

    $stmt = $pdo->prepare("
      INSERT INTO submissions
        (domain, url, name, category, city, state, phone, service_area, email, notes, status, last_error, created_at, updated_at, source)
      VALUES
        (:domain, :url, :name, :category, :city, :state, :phone, :service_area, NULL, :notes, 'new', NULL, :created_at, :updated_at, 'seed')
      ON CONFLICT(domain) DO UPDATE SET
        url=excluded.url,
        name=excluded.name,
        category=excluded.category,
        city=excluded.city,
        state=excluded.state,
        phone=excluded.phone,
        service_area=excluded.service_area,
        notes=excluded.notes,
        status='new',
        last_error=NULL,
        updated_at=excluded.updated_at,
        source='seed'
    ");

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    $pdo->beginTransaction();

    while (($row = fgetcsv($fh)) !== false) {
      $urlRaw = clean($row[$map[$kUrl]] ?? '');
      if ($urlRaw === '') { $skipped++; continue; }

      $url = normalize_url($urlRaw);
      $domain = normalize_domain_from_url($url);
      if ($domain === '') { $skipped++; continue; }

      $name = isset($map[$kName]) ? clean($row[$map[$kName]] ?? '') : '';
      if ($name === '') $name = $domain;

      $phone = isset($map[$kPhone]) ? clean($row[$map[$kPhone]] ?? '') : '';
      $county= isset($map[$kCounty]) ? clean($row[$map[$kCounty]] ?? '') : '';

      // Dallas plumbers defaults
      $category = "Plumber";
      $city = "Dallas";
      $state = "TX";
      $service_area = $county !== '' ? ($county . " County, TX") : "Dallas, TX";
      $notes = "Seed import (Dallas plumbers).";

      // Determine insert vs update by checking existence
      $existsStmt = $pdo->prepare("SELECT 1 FROM submissions WHERE domain=:d LIMIT 1");
      $existsStmt->execute([":d" => $domain]);
      $exists = (bool)$existsStmt->fetchColumn();

      $stmt->execute([
        ":domain" => $domain,
        ":url" => $url,
        ":name" => $name,
        ":category" => $category,
        ":city" => $city,
        ":state" => $state,
        ":phone" => ($phone !== '' ? $phone : null),
        ":service_area" => $service_area,
        ":notes" => $notes,
        ":created_at" => $now,
        ":updated_at" => $now,
      ]);

      // Create placeholder immediately so link works
      // Uses your existing placeholder writer from submit.php (or profile_lib.php)
      // If you don't have it in profile_lib.php, comment this out and we'll paste it in.
      if (function_exists('write_placeholder_profile')) {
        write_placeholder_profile($domain, [
          "url" => $url,
          "name" => $name,
          "cat" => $category,
          "city" => $city,
          "state" => $state,
        ]);
      }

      if ($exists) $updated++; else $inserted++;
    }

    $pdo->commit();
    fclose($fh);

    $results = [
      "inserted" => $inserted,
      "updated" => $updated,
      "skipped" => $skipped,
    ];

  } catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
  }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Import CSV | Yeow.ai</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:0; }
    main { max-width: 860px; margin: 0 auto; padding: 30px 18px; }
    .box { border: 1px solid #eee; border-radius: 14px; padding: 18px; background: #fafafa; }
    input[type=file] { font-size: 16px; }
    .btn { display:inline-block; margin-top: 14px; padding: 10px 14px; border-radius: 12px; background:#0b5fff; color:#fff; border:0; font-weight:700; cursor:pointer; }
    .muted { color:#666; font-size: 13px; }
    code { background:#f2f2f2; padding:2px 6px; border-radius:6px; }
  </style>
</head>
<body>
<main>
  <h1>CSV Import</h1>
  <p class="muted">Upload a CSV with headers: <code>Company Name, Website URL, Phone Number, County</code>. This will upsert into SQLite and queue profiles for generation.</p>

  <div class="box">
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="csv" accept=".csv" required>
      <br/>
      <button class="btn" type="submit">Import CSV</button>
    </form>
  </div>

  <?php if ($error): ?>
    <p style="color:#b00020; margin-top:16px;"><strong>Error:</strong> <?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if ($results): ?>
    <div class="box" style="margin-top:16px;">
      <h2>Import Results</h2>
      <ul>
        <li>Inserted: <?= (int)$results['inserted'] ?></li>
        <li>Updated: <?= (int)$results['updated'] ?></li>
        <li>Skipped (bad/missing URL): <?= (int)$results['skipped'] ?></li>
      </ul>
      <p class="muted">Profiles are queued as <code>status=new</code>. Your cron worker will publish full profiles automatically.</p>
    </div>
  <?php endif; ?>

</main>
</body>
</html>
