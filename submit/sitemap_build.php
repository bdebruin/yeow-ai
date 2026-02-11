<?php
declare(strict_types=1);

/**
 * sitemap_build.php
 * Builds sitemaps from SQLite into /public_html/sitemaps/
 *
 * Outputs:
 * - /sitemaps/site.xml
 * - /sitemaps/dallas-plumbers.xml
 * - /sitemaps/sitemap-index.xml
 */

$DB_PATH = __DIR__ . '/submissions.sqlite';
$OUT_DIR = realpath(__DIR__ . '/../sitemaps') ?: (__DIR__ . '/../sitemaps');

$BASE = 'https://yeow.ai';
$NOW = gmdate('c');

// Safety: how many URLs per sitemap file (keep it sane)
$MAX_URLS = 45000;

// Ensure output directory
if (!is_dir($OUT_DIR)) {
  mkdir($OUT_DIR, 0755, true);
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function write_file(string $path, string $content): void {
  file_put_contents($path, $content, LOCK_EX);
}

function urlset(array $items): string {
  $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
  foreach ($items as $it) {
    $loc = $it['loc'];
    $lastmod = $it['lastmod'] ?? null;
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . h($loc) . "</loc>\n";
    if ($lastmod) {
      $xml .= "    <lastmod>" . h($lastmod) . "</lastmod>\n";
    }
    $xml .= "  </url>\n";
  }
  $xml .= "</urlset>\n";
  return $xml;
}

function sitemap_index(array $sitemaps): string {
  $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
  foreach ($sitemaps as $sm) {
    $xml .= "  <sitemap>\n";
    $xml .= "    <loc>" . h($sm['loc']) . "</loc>\n";
    if (!empty($sm['lastmod'])) {
      $xml .= "    <lastmod>" . h($sm['lastmod']) . "</lastmod>\n";
    }
    $xml .= "  </sitemap>\n";
  }
  $xml .= "</sitemapindex>\n";
  return $xml;
}

try {
  $pdo = new PDO('sqlite:' . $DB_PATH, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Build SITE sitemap (all domains that have a profile)
  // We include published + generating placeholders (new) since /site/<domain> resolves.
  $stmtAll = $pdo->prepare("
    SELECT domain, updated_at
    FROM submissions
    WHERE domain IS NOT NULL AND domain <> ''
    ORDER BY updated_at DESC
  ");
  $stmtAll->execute();
  $rowsAll = $stmtAll->fetchAll();

  $itemsAll = [];
  foreach ($rowsAll as $r) {
    $domain = (string)$r['domain'];
    $lastmod = (string)($r['updated_at'] ?? '');
    $itemsAll[] = [
      'loc' => $BASE . '/site/' . rawurlencode($domain),
      'lastmod' => $lastmod ?: null,
    ];
    if (count($itemsAll) >= $MAX_URLS) break; // keep single file for now
  }

  $siteXml = urlset($itemsAll);
  write_file($OUT_DIR . '/site.xml', $siteXml);

  // Build Dallas Plumbers sitemap
  $stmtDp = $pdo->prepare("
    SELECT domain, updated_at
    FROM submissions
    WHERE lower(category)='plumber' AND lower(city)='dallas' AND upper(state)='TX'
      AND domain IS NOT NULL AND domain <> ''
    ORDER BY updated_at DESC
  ");
  $stmtDp->execute();
  $rowsDp = $stmtDp->fetchAll();

  $itemsDp = [];
  // Include the directory page itself
  $itemsDp[] = [
    'loc' => $BASE . '/dallas/plumbers',
    'lastmod' => $NOW,
  ];

  foreach ($rowsDp as $r) {
    $domain = (string)$r['domain'];
    $lastmod = (string)($r['updated_at'] ?? '');
    $itemsDp[] = [
      'loc' => $BASE . '/site/' . rawurlencode($domain),
      'lastmod' => $lastmod ?: null,
    ];
    if (count($itemsDp) >= $MAX_URLS) break;
  }

  $dpXml = urlset($itemsDp);
  write_file($OUT_DIR . '/dallas-plumbers.xml', $dpXml);

  // Sitemap index
  $index = sitemap_index([
    ['loc' => $BASE . '/sitemaps/site.xml', 'lastmod' => $NOW],
    ['loc' => $BASE . '/sitemaps/dallas-plumbers.xml', 'lastmod' => $NOW],
  ]);
  write_file($OUT_DIR . '/sitemap-index.xml', $index);

  header('Content-Type: text/plain; charset=utf-8');
  echo "OK\n";
  echo "Wrote:\n";
  echo " - {$OUT_DIR}/site.xml (" . count($itemsAll) . " urls)\n";
  echo " - {$OUT_DIR}/dallas-plumbers.xml (" . count($itemsDp) . " urls)\n";
  echo " - {$OUT_DIR}/sitemap-index.xml (2 sitemaps)\n";

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "ERROR: " . $e->getMessage() . "\n";
}
