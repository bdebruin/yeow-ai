<?php
// /browse/index.php (Yeow style + SQLite search)

declare(strict_types=1);

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 120); // keep it sane

// --- CONFIG: update this path to match YOUR actual sqlite file ---
$dbPath = __DIR__ . '/../submit/submissions.sqlite';   // common location
if (!is_file($dbPath)) {
  // fallback to /submit/data.sqlite (if browse is /browse and submit is /submit)
  $dbPath = dirname(__DIR__) . '/submit/submissions.sqlite';
}

// Featured can stay as fallback / marketing
$featured = [
  'theprosperplumber.com',
  'goodguys.app',
];

// Result containers
$results = [];
$recent = [];
$dbError = null;

function normalizeDomainQuery(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('~^https?://~i', '', $s);
  $s = preg_replace('~^www\.~i', '', $s);
  $s = preg_replace('~/.*$~', '', $s); // strip path
  $s = preg_replace('/[^a-z0-9.\-]/', '', $s);
  return $s ?? '';
}

$qDomain = normalizeDomainQuery($q);

try {
  if (is_file($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Confirm table exists (avoid fatal)
    $hasTable = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='submissions'")->fetchColumn();

    if ($hasTable) {
      if ($qDomain !== '') {
        // Search results
        $stmt = $pdo->prepare("
          SELECT domain, status, created_at, updated_at
          FROM submissions
          WHERE domain LIKE :q
          ORDER BY updated_at DESC
          LIMIT 200
        ");
        $stmt->execute([':q' => '%' . $qDomain . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } else {
        // Recent profiles (no query)
        $stmt = $pdo->query("
          SELECT domain, status, created_at, updated_at
          FROM submissions
          ORDER BY updated_at DESC
          LIMIT 60
        ");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }
    } else {
      $dbError = "SQLite table 'submissions' not found.";
    }
  } else {
    $dbError = "SQLite database not found at expected path.";
  }
} catch (Throwable $e) {
  $dbError = $e->getMessage();
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function prettyStatus(?string $s): string {
  $s = strtolower(trim((string)$s));
  if ($s === '') return 'unknown';
  return $s;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Browse AI Profiles | Yeow.ai</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="https://yeow.ai/browse">

  <style>
    :root{
      --bg:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --line:#e2e8f0;
      --soft:#f8fafc;
      --blue:#2563eb;
      --blue2:#1d4ed8;
      --radius:18px;
      --radiusPill:999px;
      --shadow:0 14px 30px rgba(15,23,42,.08);
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    a{ color:inherit; text-decoration:none; }
    a:hover{ text-decoration:underline; }

    .top{
      height:56px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:0 16px;
      border-bottom:1px solid var(--line);
      background:#fff;
    }
    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:900;
      letter-spacing:-.4px;
    }
    .brand img{
      width:32px;
      height:32px;
      border-radius:8px;
      border:1px solid var(--line);
    }
    .nav a{
      font-size:13px;
      padding:8px 10px;
      border-radius:10px;
      color:#334155;
    }
    .nav a:hover{
      background:var(--soft);
      text-decoration:none;
    }

    .wrap{
      max-width:1000px;
      margin:0 auto;
      padding:40px 18px 80px;
    }
    h1{
      font-size:32px;
      letter-spacing:-.8px;
      margin:0 0 6px;
    }
    .sub{
      color:var(--muted);
      font-size:16px;
      margin-bottom:18px;
      line-height:1.5;
    }

    .search{
      display:flex;
      gap:10px;
      align-items:center;
      padding:12px 14px;
      border:1px solid var(--line);
      border-radius:var(--radiusPill);
      box-shadow:var(--shadow);
      max-width:720px;
      background:#fff;
    }
    .search input{
      flex:1;
      border:none;
      outline:none;
      font-size:16px;
    }
    .search button{
      border:none;
      background:linear-gradient(135deg,var(--blue),var(--blue2));
      color:#fff;
      font-weight:900;
      border-radius:999px;
      padding:10px 16px;
      cursor:pointer;
    }
    .search button:hover{ filter:brightness(1.05); }

    .section{ margin-top:28px; }
    .section h2{
      font-size:18px;
      margin:0 0 12px;
      letter-spacing:-.3px;
    }

    .grid{
      display:grid;
      grid-template-columns: repeat(auto-fill,minmax(240px,1fr));
      gap:14px;
    }
    .card{
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:14px;
      background:#fff;
      transition: box-shadow .15s ease, transform .15s ease;
    }
    .card:hover{
      box-shadow:var(--shadow);
      transform:translateY(-1px);
      text-decoration:none;
    }
    .domain{
      font-weight:950;
      letter-spacing:-.2px;
    }
    .meta{
      font-size:13px;
      color:var(--muted);
      margin-top:4px;
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .pill{
      display:inline-block;
      padding:6px 10px;
      font-size:12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:var(--soft);
      color:#475569;
      font-weight:800;
    }

    .empty{
      margin-top:18px;
      padding:22px;
      border:1px dashed var(--line);
      border-radius:var(--radius);
      color:var(--muted);
      text-align:center;
    }

    .warn{
      margin-top:16px;
      padding:12px 14px;
      border-radius:14px;
      border:1px solid rgba(234,179,8,.35);
      background: rgba(234,179,8,.10);
      color:#7c5f00;
      font-size:13px;
      line-height:1.4;
    }

    footer{
      margin-top:60px;
      text-align:center;
      font-size:12px;
      color:var(--muted);
    }
  </style>
</head>

<body>
  <div class="top">
    <div class="brand">
      <img src="/assets/yeow_logo.jpg" alt="Yeow">
      Yeow
    </div>
    <div class="nav">
      <a href="/">Home</a>
      <a href="/submit">Create profile</a>
    </div>
  </div>

  <div class="wrap">
    <h1>Browse profiles</h1>
    <div class="sub">Search by website. Profiles are public.</div>

    <form class="search" action="/browse" method="get">
      <input
        type="text"
        name="q"
        value="<?php echo h($q); ?>"
        placeholder="Search a domain (e.g., goodguys.app)"
        autocomplete="off"
      />
      <button type="submit">Search</button>
    </form>

    <?php if ($dbError): ?>
      <div class="warn">
        Browse search is not connected to the database yet.<br>
        <strong>Reason:</strong> <?php echo h($dbError); ?><br>
        Showing featured profiles instead.
      </div>
    <?php endif; ?>

    <?php if ($qDomain !== ''): ?>
      <div class="section">
        <h2>Search results for “<?php echo h($qDomain); ?>”</h2>

        <?php if (!empty($results)): ?>
          <div class="grid">
            <?php foreach ($results as $r): ?>
              <?php $domain = (string)($r['domain'] ?? ''); ?>
              <a class="card" href="/site/<?php echo rawurlencode($domain); ?>">
                <div class="domain"><?php echo h($domain); ?></div>
                <div class="meta">
                  <span class="pill"><?php echo h(prettyStatus((string)($r['status'] ?? ''))); ?></span>
                  <?php if (!empty($r['updated_at'])): ?>
                    <span>Updated <?php echo h((string)$r['updated_at']); ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty">
            No results found. Try another domain.<br><br>
            <a class="pill" href="/submit">Create a profile</a>
          </div>
        <?php endif; ?>
      </div>

    <?php else: ?>

      <div class="section">
        <h2>Featured</h2>
        <div class="grid">
          <?php foreach ($featured as $domain): ?>
            <a class="card" href="/site/<?php echo rawurlencode($domain); ?>">
              <div class="domain"><?php echo h($domain); ?></div>
              <div class="meta"><span class="pill">View profile</span></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section">
        <h2>Recent</h2>

        <?php if (!empty($recent)): ?>
          <div class="grid">
            <?php foreach ($recent as $r): ?>
              <?php $domain = (string)($r['domain'] ?? ''); ?>
              <a class="card" href="/site/<?php echo rawurlencode($domain); ?>">
                <div class="domain"><?php echo h($domain); ?></div>
                <div class="meta">
                  <span class="pill"><?php echo h(prettyStatus((string)($r['status'] ?? ''))); ?></span>
                  <?php if (!empty($r['created_at'])): ?>
                    <span>Created <?php echo h((string)$r['created_at']); ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty">
            No profiles yet. Create the first one.<br><br>
            <a class="pill" href="/submit">Create profile</a>
          </div>
        <?php endif; ?>

      </div>

    <?php endif; ?>

    <footer>
      Yeow profiles live at <code>/site/yourdomain.com</code>.
    </footer>
  </div>
</body>
</html>
