<?php
declare(strict_types=1);

// Dallas plumbers directory page
$dbPath = __DIR__ . '/../../../submit/submissions.sqlite';

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Pull Dallas plumbers (case-insensitive)
  $stmt = $pdo->prepare("
    SELECT domain, name, url, phone, status, updated_at
    FROM submissions
    WHERE lower(category) = 'plumber'
      AND lower(city) = 'dallas'
      AND upper(state) = 'TX'
    ORDER BY
      CASE status
        WHEN 'published' THEN 0
        WHEN 'new' THEN 1
        WHEN 'error' THEN 2
        ELSE 3
      END,
      name COLLATE NOCASE ASC
  ");
  $stmt->execute();
  $rows = $stmt->fetchAll();

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Server error: " . $e->getMessage();
  exit;
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$total = count($rows);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dallas Plumbers | Yeow.ai</title>
  <meta name="description" content="AI-friendly plumber profiles for Dallas, TX. Yeow.ai supplements business websites with structured information for AI assistants."/>
  <meta name="robots" content="index,follow"/>
  <link rel="canonical" href="https://yeow.ai/dallas/plumbers"/>

  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; }
    main { max-width: 980px; margin: 0 auto; padding: 28px 18px; }
    h1 { margin: 0 0 8px; font-size: 30px; }
    p { color: #444; line-height: 1.5; margin: 8px 0; }
    .muted { color:#666; font-size: 14px; }
    .top { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; justify-content:space-between; }
    .search { width: 320px; max-width: 100%; padding: 12px; border:1px solid #ddd; border-radius: 12px; font-size: 16px; }
    .box { border: 1px solid #eee; border-radius: 16px; background: #fafafa; padding: 14px; margin-top: 14px; }
    table { width:100%; border-collapse: collapse; }
    th, td { text-align:left; padding: 10px 8px; border-bottom: 1px solid #eee; vertical-align: top; font-size: 14px; }
    th { color:#555; font-weight:700; }
    a { color: #0b5fff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .pill { display:inline-block; font-size:12px; padding:3px 10px; border-radius:999px; background:#f2f2f2; margin-right:6px; }
    .pill-live { background:#e9f7ef; color:#166534; }
    .pill-new { background:#eef2ff; color:#1e40af; }
    .pill-err { background:#fee2e2; color:#991b1b; }
    .cta { margin-top: 16px; }
    .btn { display:inline-block; padding: 12px 16px; border-radius: 12px; background:#0b5fff; color:#fff; font-weight:700; }
    .btn:hover { opacity: .92; text-decoration:none; }
  </style>
</head>

<body>
<main>
  <div class="top">
    <div>
      <h1>Dallas Plumbers</h1>
      <p class="muted">
        <?= $total ?> AI-friendly profiles. Yeow.ai supplements business websites with structured info for AI assistants.
      </p>
    </div>

    <div>
      <label class="muted" for="q">Search</label><br/>
      <input id="q" class="search" placeholder="Search by name or domain..." autocomplete="off"/>
    </div>
  </div>

  <div class="box">
    <p class="muted" style="margin:0;">
      Own a plumbing business in Dallas? Create or update your profile:
      <a class="btn" href="/submit/">Get your AI profile</a>
    </p>
  </div>

  <div class="box">
    <table id="tbl">
      <thead>
        <tr>
          <th>Business</th>
          <th>Website</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Profile</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $domain = (string)$r['domain'];
        $name   = (string)($r['name'] ?? $domain);
        $url    = (string)($r['url'] ?? '');
        $phone  = (string)($r['phone'] ?? '');
        $status = (string)($r['status'] ?? 'new');

        $pillClass = 'pill-new';
        $pillText = 'Generating';
        if ($status === 'published') { $pillClass = 'pill-live'; $pillText = 'Live'; }
        elseif ($status === 'error') { $pillClass = 'pill-err'; $pillText = 'Error'; }

        $profileUrl = '/site/' . rawurlencode($domain);
      ?>
        <tr>
          <td>
            <strong><?= h($name) ?></strong><br/>
            <span class="muted"><?= h($domain) ?></span>
          </td>
          <td>
            <?php if ($url): ?>
              <a href="<?= h($url) ?>" target="_blank" rel="nofollow noopener">Visit site</a>
            <?php else: ?>
              <span class="muted">Not provided</span>
            <?php endif; ?>
          </td>
          <td><?= $phone ? h($phone) : '<span class="muted">Not provided</span>' ?></td>
          <td><span class="pill <?= h($pillClass) ?>"><?= h($pillText) ?></span></td>
          <td><a href="<?= h($profileUrl) ?>">View profile</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <script>
    const q = document.getElementById('q');
    const rows = Array.from(document.querySelectorAll('#tbl tbody tr'));

    q.addEventListener('input', () => {
      const term = q.value.trim().toLowerCase();
      for (const tr of rows) {
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.includes(term) ? '' : 'none';
      }
    });
  </script>
</main>
</body>
</html>
