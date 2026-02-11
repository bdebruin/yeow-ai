<?php
declare(strict_types=1);

// Simple admin view. Protect with a token.
$token = $_GET['token'] ?? '';
if ($token !== '69505860032473051613071920') {
  http_response_code(403);
  echo "Forbidden";
  exit;
}

$dbPath = __DIR__ . '/submissions.sqlite';

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$status = $_GET['status'] ?? 'new';
$allowed = ['new','generated','published','error','all'];
if (!in_array($status, $allowed, true)) $status = 'new';

if ($status === 'all') {
  $stmt = $pdo->query("SELECT * FROM submissions ORDER BY updated_at DESC LIMIT 500");
} else {
  $stmt = $pdo->prepare("SELECT * FROM submissions WHERE status=:s ORDER BY updated_at DESC LIMIT 500");
  $stmt->execute([':s' => $status]);
}

$rows = $stmt->fetchAll();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Yeow Submissions Admin</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #eee; padding: 8px; font-size: 13px; vertical-align: top; }
    th { background: #fafafa; text-align: left; }
    .muted { color: #666; }
    a { color: #0b5fff; }
  </style>
</head>
<body>
  <h1>Submissions (<?= htmlspecialchars($status) ?>)</h1>
  <p class="muted">
    Filter:
    <a href="?token=<?= urlencode($token) ?>&status=new">new</a> |
    <a href="?token=<?= urlencode($token) ?>&status=generated">generated</a> |
    <a href="?token=<?= urlencode($token) ?>&status=published">published</a> |
    <a href="?token=<?= urlencode($token) ?>&status=error">error</a> |
    <a href="?token=<?= urlencode($token) ?>&status=all">all</a>
  </p>

  <table>
    <thead>
      <tr>
        <th>Updated</th>
        <th>Domain</th>
        <th>Business</th>
        <th>Category</th>
        <th>City</th>
        <th>State</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Status</th>
        <th>Website</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['updated_at']) ?></td>
        <td>
          <strong><?= htmlspecialchars($r['domain']) ?></strong><br/>
          <a href="/site/<?= htmlspecialchars($r['domain']) ?>" target="_blank">profile</a>
        </td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['category']) ?></td>
        <td><?= htmlspecialchars($r['city']) ?></td>
        <td><?= htmlspecialchars($r['state']) ?></td>
        <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank">site</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
