<?php
// /public_html/api/grade.php
// Yeow.ai Grader (PHP 8)
// - Accepts POST JSON: { "url": "example.com" }
// - Fetches website HTML with cURL (redirects, gzip, browser UA)
// - Handles HTTP 403 as "Blocked" (supported case)
// - Extracts a few simple "AI-readiness" signals and returns a score + recommendations
//
// Expected response shape (your frontend uses these):
// { site, fetched_url, score, tier, recommendations[], signals[] }

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $httpCode, array $payload): void {
  http_response_code($httpCode);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function normalize_url_to_fetch(string $raw): array {
  $raw = trim($raw);
  if ($raw === '') return ['ok' => false, 'error' => 'Missing URL.'];

  // If they pass just a domain or domain/path, add scheme.
  if (!preg_match('~^https?://~i', $raw)) {
    $raw = 'https://' . $raw;
  }

  $parts = parse_url($raw);
  if (!$parts || empty($parts['host'])) {
    return ['ok' => false, 'error' => 'Invalid URL.'];
  }

  $host = strtolower($parts['host']);
  if (str_starts_with($host, 'www.')) $host = substr($host, 4);

  // Basic allowlist for host characters
  if (!preg_match('/^[a-z0-9.-]+$/', $host) || !str_contains($host, '.')) {
    return ['ok' => false, 'error' => 'Invalid host.'];
  }

  $path = $parts['path'] ?? '/';
  $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
  $pathQuery = $path . $query;

  // Try these in order:
  $tries = [
    "https://$host$pathQuery",
    "https://www.$host$pathQuery",
    "http://$host$pathQuery",
    "http://www.$host$pathQuery",
  ];

  return [
    'ok' => true,
    'site' => $host,
    'tries' => array_values(array_unique($tries)),
  ];
}

function curl_fetch(string $url): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 8,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 18,
    CURLOPT_ENCODING       => '', // allow gzip/deflate
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
      'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
      'Accept-Language: en-US,en;q=0.9',
      'Cache-Control: no-cache',
      'Pragma: no-cache',
      'Upgrade-Insecure-Requests: 1',
    ],
    CURLOPT_REFERER        => 'https://www.google.com/',
  ]);

  $resp = curl_exec($ch);
  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => false, 'error' => 'cURL: ' . $err];
  }

  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  $headers = substr($resp, 0, $headerSize);
  $body    = substr($resp, $headerSize);

  return [
    'ok' => true,
    'status' => $status,
    'final_url' => $finalUrl,
    'headers' => $headers,
    'body' => $body,
  ];
}

function http_fetch_best(array $tries): array {
  $lastErr = 'Unknown error';
  foreach ($tries as $tryUrl) {
    $fx = curl_fetch($tryUrl);
    if (!$fx['ok']) {
      $lastErr = $fx['error'] ?? 'Fetch error';
      continue;
    }

    $status = $fx['status'] ?? 0;
    if ($status === 403) {
      return [
        'ok' => false,
        'blocked' => true,
        'status' => 403,
        'final_url' => $fx['final_url'] ?? $tryUrl,
      ];
    }

    if ($status >= 200 && $status < 300 && isset($fx['body']) && strlen(trim((string)$fx['body'])) > 0) {
      return [
        'ok' => true,
        'status' => $status,
        'final_url' => $fx['final_url'] ?? $tryUrl,
        'headers' => $fx['headers'] ?? '',
        'body' => (string)$fx['body'],
      ];
    }

    $lastErr = 'HTTP ' . $status . ' from ' . ($fx['final_url'] ?? $tryUrl);
  }

  return ['ok' => false, 'error' => $lastErr];
}

function strip_tags_safely(string $html): string {
  $text = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
  $text = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $text);
  $text = strip_tags($text);
  $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace('/\s+/', ' ', $text);
  return trim($text);
}

function find_meta(string $html, string $name): ?string {
  // matches: <meta name="description" content="...">
  $pattern = '~<meta[^>]+name=["\']' . preg_quote($name, '~') . '["\'][^>]*content=["\']([^"\']+)["\']~i';
  if (preg_match($pattern, $html, $m)) return trim($m[1]);
  // matches: <meta property="og:description" content="...">
  $pattern2 = '~<meta[^>]+property=["\']' . preg_quote($name, '~') . '["\'][^>]*content=["\']([^"\']+)["\']~i';
  if (preg_match($pattern2, $html, $m)) return trim($m[1]);
  return null;
}

function find_title(string $html): ?string {
  if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
    $t = trim(strip_tags_safely($m[1]));
    return $t !== '' ? $t : null;
  }
  return null;
}

function has_pattern(string $html, string $regex): bool {
  return (bool)preg_match($regex, $html);
}

function grade_html(string $site, string $fetchedUrl, string $html): array {
  $signals = [];
  $recs = [];

  $title = find_title($html);
  $desc  = find_meta($html, 'description');
  $ogTitle = find_meta($html, 'og:title');
  $ogDesc  = find_meta($html, 'og:description');

  $hasH1 = has_pattern($html, '~<h1\b~i');
  $hasPhone = has_pattern($html, '~(\(\d{3}\)\s*\d{3}[-.\s]?\d{4})|(\b\d{3}[-.\s]\d{3}[-.\s]\d{4}\b)~');
  $hasEmail = has_pattern($html, '~[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}~i');
  $hasAddressWords = has_pattern($html, '~\b(street|st\.|ave|avenue|road|rd\.|suite|ste\.|blvd|boulevard|tx|texas|ca|california|zip)\b~i');
  $hasSchema = has_pattern($html, '~application/ld\+json~i');
  $hasLocalBusiness = has_pattern($html, '~LocalBusiness|Plumber|Electrician|HVACBusiness|Restaurant|ProfessionalService~i');

  // Simple scoring: start at 50, add/subtract based on key items
  $score = 50;

  if ($title) { $score += 10; $signals[] = "Title tag found"; } else { $recs[] = "Add a clear <title> that includes your service and city."; }
  if ($desc)  { $score += 10; $signals[] = "Meta description found"; } else { $recs[] = "Add a meta description that clearly describes what you do and where you serve."; }

  if ($hasH1) { $score += 5; $signals[] = "H1 heading found"; } else { $recs[] = "Add a single, clear H1 describing your primary service (and location if relevant)."; }

  if ($hasPhone) { $score += 7; $signals[] = "Phone number detected"; } else { $recs[] = "Add a visible phone number on the homepage and contact page."; }
  if ($hasEmail) { $score += 4; $signals[] = "Email detected"; } else { $recs[] = "Add a contact email address (or a contact form plus email)."; }

  if ($hasAddressWords) { $score += 6; $signals[] = "Possible address/location text detected"; } else { $recs[] = "Add service area and location details (city, neighborhoods, county)."; }

  if ($hasSchema) {
    $score += 10;
    $signals[] = "Structured data (JSON-LD) detected";
    if ($hasLocalBusiness) $signals[] = "LocalBusiness-like terms detected";
  } else {
    $recs[] = "Add schema.org LocalBusiness JSON-LD (business name, address/service area, phone, hours, services).";
  }

  // Extra AI-facing recs
  $recs[] = "Publish an AI-friendly profile on Yeow.ai that lists services, service area, FAQs, and contact details (no website changes required).";
  $recs[] = "Add a simple /sitemap.xml and make sure it includes your key service pages.";

  // Clamp 0..100
  $score = max(0, min(100, $score));

  $tier = 'Needs work';
  if ($score >= 85) $tier = 'Great';
  else if ($score >= 70) $tier = 'Good';
  else if ($score >= 55) $tier = 'Fair';

  // Keep recommendations short & non-technical
  $recs = array_values(array_unique(array_filter($recs)));
  $signals = array_values(array_unique(array_filter($signals)));

  // Limit list sizes for UI
  $recs = array_slice($recs, 0, 8);
  $signals = array_slice($signals, 0, 8);

  return [
    'site' => $site,
    'fetched_url' => $fetchedUrl,
    'score' => $score,
    'tier' => $tier,
    'recommendations' => $recs,
    'signals' => $signals,
    'debug' => [
      'title' => $title,
      'meta_description' => $desc,
      'og_title' => $ogTitle,
      'og_description' => $ogDesc,
    ],
  ];
}

/* -------------------- Main -------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(405, ['error' => 'Method not allowed. Use POST.']);
}

$data = read_json_body();
$url = (string)($data['url'] ?? '');
$norm = normalize_url_to_fetch($url);

if (!$norm['ok']) {
  respond(400, ['error' => $norm['error'] ?? 'Invalid input.']);
}

$site = $norm['site'];
$tries = $norm['tries'];

$fx = http_fetch_best($tries);

if (!empty($fx['blocked'])) {
  // Supported: site blocks automated fetch
  respond(200, [
    'site' => $site,
    'fetched_url' => $fx['final_url'] ?? ($tries[0] ?? $url),
    'score' => 0,
    'tier' => 'Blocked (403)',
    'recommendations' => [
      'This website blocks automated checks (HTTP 403).',
      'You can still create your Yeow profile to make your business legible to AI.',
      'Optional: paste your homepage text later for a deeper assessment.'
    ],
    'signals' => [
      'Server blocked automated fetch (403)',
      'Fallback grading used'
    ],
    'blocked' => true
  ]);
}

if (!$fx['ok']) {
  respond(400, [
    'error' => 'Could not fetch the website.',
    'details' => $fx['error'] ?? 'Unknown error',
    'tried' => $tries
  ]);
}

$html = (string)$fx['body'];
$fetchedUrl = (string)($fx['final_url'] ?? $tries[0]);

// If HTML looks like it's empty or purely JS shell, still grade lightly
if (strlen($html) < 250) {
  respond(200, [
    'site' => $site,
    'fetched_url' => $fetchedUrl,
    'score' => 20,
    'tier' => 'Very limited',
    'recommendations' => [
      'We could only retrieve a small amount of HTML from this site.',
      'You can still create your Yeow profile to provide AI-friendly business details.',
      'Optional: paste your homepage text later for deeper scoring.'
    ],
    'signals' => [
      'Very small HTML response',
      'Fallback grading used'
    ]
  ]);
}

$out = grade_html($site, $fetchedUrl, $html);

// Remove debug in production if you prefer:
unset($out['debug']);

respond(200, $out);
