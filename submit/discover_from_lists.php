<?php
declare(strict_types=1);

/**
 * discover_from_lists.php
 * Cron-friendly discovery:
 * - Reads /submit/discovery_sources.txt (list pages)
 * - Fetches each page
 * - Extracts outbound website links
 * - Filters out directories/social/junk
 * - Upserts new businesses into submissions.sqlite as source='discovered', status='new'
 * - Optionally creates placeholder profile pages immediately
 */

// ---------- CONFIG ----------
$DB_PATH = __DIR__ . '/submissions.sqlite';
$SOURCES_FILE = __DIR__ . '/discovery_sources.txt';

// Defaults for discovered profiles (tune as you expand)
$DEFAULT_CATEGORY = 'Plumber';
$DEFAULT_CITY = 'Dallas';
$DEFAULT_STATE = 'TX';

// Safety limits
$MAX_SOURCE_PAGES_PER_RUN = 25;  // don’t fetch too many per cron run
$MAX_LINKS_PER_SOURCE = 250;     // cap extraction per page
$HTTP_TIMEOUT = 12;

// Filter out common non-business domains
$BLOCK_HOSTS = [
  'facebook.com','m.facebook.com','fb.com','instagram.com','linkedin.com','youtube.com','youtu.be',
  'yelp.com','angi.com','homeadvisor.com','thumbtack.com','bbb.org','nextdoor.com',
  'google.com','goo.gl','maps.google.com','g.page',
  'mapquest.com','yellowpages.com','opencorporates.com','wikipedia.org',
  'tripadvisor.com','foursquare.com','x.com','twitter.com','tiktok.com'
];

// Filter out common non-business URL patterns
$BLOCK_SUBSTR = [
  '/search', '/login', '/signin', '/signup', '/register', '/account',
  'utm_', 'mailto:', 'tel:', '#'
];

// If you want: restrict discovered domains to likely business sites only
$REQUIRE_TLD = true;

// ---------- OPTIONAL: shared helpers ----------
if (file_exists(__DIR__ . '/profile_lib.php')) {
  require_once __DIR__ . '/profile_lib.php';
}

// ---------- Helpers ----------
function log_line(string $s): void {
  // Writes to STDOUT (cron logs)
  echo $s . "\n";
}

function http_get(string $url, int $timeout): ?string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 4,
    CURLOPT_CONNECTTIMEOUT => $timeout,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_USERAGENT => 'YeowDiscoveryBot/1.0 (+https://yeow.ai)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false || $code < 200 || $code >= 400) return null;
  return $body;
}

function normalize_url(string $url): string {
  $url = trim($url);
  if ($url === '') return '';
  if (!preg_match('/^https?:\/\//i', $url)) $url = 'https://' . $url;
  return $url;
}

function normalize_domain_from_url(string $url): string {
  $url = normalize_url($url);
  if ($url === '') return '';
  $p = parse_url($url);
  if (!is_array($p) || empty($p['host'])) return '';
  $host = strtolower((string)$p['host']);
  $host = preg_replace('/^www\./i', '', $host);

  // Basic domain-ish
  if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) return '';
  return $host;
}

function abs_url(string $base, string $href): ?string {
  $href = trim($href);
  if ($href === '' || str_starts_with($href, '#')) return null;
  if (preg_match('~^https?://~i', $href)) return $href;
  if (str_starts_with($href, '//')) return 'https:' . $href;
  if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) return null;

  $p = parse_url($base);
  if (!$p || empty($p['scheme']) || empty($p['host'])) return null;

  $scheme = $p['scheme'];
  $host = $p['host'];
  $port = isset($p['port']) ? (':' . $p['port']) : '';

  if (str_starts_with($href, '/')) {
    return "{$scheme}://{$host}{$port}{$href}";
  }

  $path = $p['path'] ?? '/';
  $dir = preg_replace('~/[^/]*$~', '/', $path);
  return "{$scheme}://{$host}{$port}{$dir}{$href}";
}

function looks_blocked(string $url, array $blockHosts, array $blockSubstr): bool {
  $lu = strtolower($url);
  foreach ($blockSubstr as $s) {
    if ($s !== '' && str_contains($lu, strtolower($s))) return true;
  }
  $host = '';
  $p = parse_url($url);
  if (is_array($p) && !empty($p['host'])) {
    $host = strtolower((string)$p['host']);
    $host = preg_replace('/^www\./i', '', $host);
  }
  if ($host !== '') {
    foreach ($blockHosts as $bh) {
      $bh = strtolower($bh);
      if ($host === $bh || str_ends_with($host, '.' . $bh)) return true;
    }
  }
  return false;
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

function read_sources(string $path, int $max): array {
  if (!file_exists($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return [];
  $out = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    $out[] = $line;
    if (count($out) >= $max) break;
  }
  return $out;
}

function extract_links(string $html, string $baseUrl, int $maxLinks): array {
  $links = [];
  if (preg_match_all('~<a[^>]+href=["\']([^"\']+)["\']~i', $html, $m)) {
    foreach ($m[1] as $href) {
      $abs = abs_url($baseUrl, $href);
      if ($abs) $links[] = $abs;
      if (count($links) >= $maxLinks) break;
    }
  }
  return array_values(array_unique($links));
}

// ---------- Main ----------
header('Content-Type: text/plain; charset=utf-8');

$sources = read_sources($SOURCES_FILE, $MAX_SOURCE_PAGES_PER_RUN);
if (!$sources) {
  log_line("No sources found. Add URLs to: $SOURCES_FILE");
  exit;
}

$pdo = new PDO('sqlite:' . $DB_PATH, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("PRAGMA journal_mode=WAL;");
$pdo->exec("PRAGMA synchronous=NORMAL;");
ensure_schema($pdo);

$now = gmdate('c');

$upsert = $pdo->prepare("
  INSERT INTO submissions
    (domain, url, name, category, city, state, phone, service_area, email, notes, status, last_error, created_at, updated_at, source)
  VALUES
    (:domain, :url, :name, :category, :city, :state, NULL, NULL, NULL, :notes, 'new', NULL, :created_at, :updated_at, 'discovered')
  ON CONFLICT(domain) DO UPDATE SET
    url=excluded.url,
    name=excluded.name,
    category=excluded.category,
    city=excluded.city,
    state=excluded.state,
    notes=excluded.notes,
    status='new',
    last_error=NULL,
    updated_at=excluded.updated_at,
    source='discovered'
");

$existsStmt = $pdo->prepare("SELECT 1 FROM submissions WHERE domain=:d LIMIT 1");

$totalSources = 0;
$totalLinks = 0;
$totalCandidateDomains = 0;
$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($sources as $srcUrlRaw) {
  $srcUrl = normalize_url($srcUrlRaw);
  $totalSources++;

  log_line("Source: $srcUrl");

  $html = http_get($srcUrl, $HTTP_TIMEOUT);
  if (!$html) {
    log_line("  - fetch failed");
    continue;
  }

  $links = extract_links($html, $srcUrl, $MAX_LINKS_PER_SOURCE);
  $totalLinks += count($links);

  $pdo->beginTransaction();

  foreach ($links as $link) {
    if (looks_blocked($link, $BLOCK_HOSTS, $BLOCK_SUBSTR)) { $skipped++; continue; }

    $domain = normalize_domain_from_url($link);
    if ($domain === '') { $skipped++; continue; }

    if ($REQUIRE_TLD && !preg_match('/\.[a-z]{2,}$/i', $domain)) { $skipped++; continue; }

    $totalCandidateDomains++;

    // Build canonical URL (domain root)
    $canonicalUrl = 'https://' . $domain;

    // Detect insert vs update
    $existsStmt->execute([":d" => $domain]);
    $exists = (bool)$existsStmt->fetchColumn();

    // Upsert row with conservative defaults
    $name = $domain; // unknown until crawl; cron_worker will improve it later
    $notes = "Discovered from: $srcUrl";

    $upsert->execute([
      ":domain" => $domain,
      ":url" => $canonicalUrl,
      ":name" => $name,
      ":category" => $DEFAULT_CATEGORY,
      ":city" => $DEFAULT_CITY,
      ":state" => $DEFAULT_STATE,
      ":notes" => $notes,
      ":created_at" => $now,
      ":updated_at" => $now,
    ]);

    // Create placeholder immediately if available
    if (function_exists('write_placeholder_profile')) {
      write_placeholder_profile($domain, [
        "url" => $canonicalUrl,
        "name" => $name,
        "cat" => $DEFAULT_CATEGORY,
        "city" => $DEFAULT_CITY,
        "state" => $DEFAULT_STATE,
      ]);
    }

    if ($exists) $updated++; else $inserted++;
  }

  $pdo->commit();
}

log_line("----");
log_line("Sources processed: $totalSources");
log_line("Links scanned: $totalLinks");
log_line("Candidate domains: $totalCandidateDomains");
log_line("Inserted: $inserted");
log_line("Updated: $updated");
log_line("Skipped: $skipped");
log_line("Done.");
