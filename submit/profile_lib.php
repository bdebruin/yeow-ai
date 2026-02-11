<?php
declare(strict_types=1);

function shard2(string $domain): string {
  $d = preg_replace('/[^a-z0-9]/i', '', strtolower($domain));
  return (strlen($d) >= 2) ? substr($d, 0, 2) : 'xx';
}

function ensure_dir(string $path): void {
  if (!is_dir($path)) mkdir($path, 0755, true);
}

function http_get(string $url, int $timeout=8): ?string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 4,
    CURLOPT_CONNECTTIMEOUT => $timeout,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_USERAGENT => 'YeowAIProfileBot/1.0 (+https://yeow.ai)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  $body = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false || $code < 200 || $code >= 400) return null;
  return $body;
}

function abs_url(string $base, string $href): ?string {
  $href = trim($href);
  if ($href === '') return null;
  if (preg_match('~^https?://~i', $href)) return $href;
  if (str_starts_with($href, '//')) return 'https:' . $href;
  if (str_starts_with($href, '#')) return null;

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

function extract_text(string $html): string {
  // remove scripts/styles
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $html);
  // strip tags
  $text = strip_tags($html);
  // collapse whitespace
  $text = preg_replace('/\s+/', ' ', $text);
  return trim($text);
}

function find_best_name(string $html, string $fallback): string {
  if (preg_match('~<meta\s+property=["\']og:site_name["\']\s+content=["\']([^"\']+)["\']~i', $html, $m)) {
    return trim($m[1]);
  }
  if (preg_match('~<title>(.*?)</title>~is', $html, $m)) {
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
    // remove common separators
    $t = preg_split('/\s[-|•]\s/', $t)[0] ?? $t;
    if (strlen($t) >= 3) return $t;
  }
  if (preg_match('~<h1[^>]*>(.*?)</h1>~is', $html, $m)) {
    $h1 = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
    if (strlen($h1) >= 3 && strlen($h1) <= 80) return $h1;
  }
  return $fallback;
}

function find_phone(string $text): ?string {
  // very loose US phone match
  if (preg_match('/(\+?1[\s\-\.]?)?\(?\d{3}\)?[\s\-\.]?\d{3}[\s\-\.]?\d{4}/', $text, $m)) {
    return trim($m[0]);
  }
  return null;
}

function find_email(string $text): ?string {
  if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
    return strtolower($m[0]);
  }
  return null;
}

function guess_services(string $text): array {
  // Lightweight plumber keywords (extend later)
  $map = [
    'water heater' => 'Water heater repair/installation',
    'drain' => 'Drain cleaning',
    'sewer' => 'Sewer line service',
    'leak' => 'Leak detection/repair',
    'toilet' => 'Toilet repair',
    'faucet' => 'Faucet repair',
    'garbage disposal' => 'Garbage disposal service',
    'hydro jet' => 'Hydro-jetting',
    'slab leak' => 'Slab leak repair',
  ];
  $services = [];
  $lower = strtolower($text);
  foreach ($map as $k => $label) {
    if (str_contains($lower, $k)) $services[] = $label;
  }
  // Always include generic plumber service if we have nothing
  if (!$services) $services = ['Plumbing services'];
  // unique
  return array_values(array_unique($services));
}

function write_full_profile_files(array $profile): void {
  $domain = $profile['domain'];
  $sh = shard2($domain);

  $siteRoot = realpath(__DIR__ . '/../site');
  if ($siteRoot === false) $siteRoot = __DIR__ . '/../site';

  $outDir = $siteRoot . '/' . $sh;
  ensure_dir($outDir);

  $htmlPath = $outDir . '/' . $domain . '.html';
  $jsonPath = $outDir . '/' . $domain . '.json';
  $jsonldPath = $outDir . '/' . $domain . '.jsonld';

  file_put_contents($jsonPath, json_encode($profile, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);

  $jsonld = $profile['jsonld'];
  file_put_contents($jsonldPath, json_encode($jsonld, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX);

  // HTML (simple, consistent; you can swap to your richer template later)
  $name = htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8');
  $url = htmlspecialchars($profile['url'], ENT_QUOTES, 'UTF-8');
  $city = htmlspecialchars($profile['city'], ENT_QUOTES, 'UTF-8');
  $state = htmlspecialchars($profile['state'], ENT_QUOTES, 'UTF-8');

  $phone = htmlspecialchars($profile['phone'] ?: 'Not provided', ENT_QUOTES, 'UTF-8');
  $email = htmlspecialchars($profile['email'] ?: 'Not provided', ENT_QUOTES, 'UTF-8');

  $svcHtml = '';
  foreach ($profile['services'] as $s) {
    $svcHtml .= '<li>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . "</li>\n";
  }

  $profileUrl = '/site/' . rawurlencode($domain);

  $html = "<!doctype html>
<html lang='en'>
<head>
  <meta charset='utf-8'/>
  <meta name='viewport' content='width=device-width, initial-scale=1'/>
  <title>{$name} (from {$domain}) | Yeow.ai</title>
  <meta name='description' content='AI-friendly supplemental profile for {$domain}. Yeow.ai completes business websites for AI assistants.'/>
  <link rel='canonical' href='https://yeow.ai{$profileUrl}'/>
  <link rel='stylesheet' href='/assets/site.css'/>
  <script type='application/ld+json'>" . json_encode($jsonld, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "</script>
</head>
<body>
<main>
  <div class='box'>
    <h1>{$name}</h1>
    <div>
      <span class='pill'>Category: Plumber</span>
      <span class='pill'>Location: {$city}, {$state}</span>
      <span class='pill'>Status: Live</span>
    </div>

    <p style='margin-top:12px;'>
      This profile supplements <strong>{$domain}</strong> with structured information for AI assistants.
      <a href='{$url}' target='_blank' rel='nofollow noopener'>Visit website</a>.
    </p>

    <h2>Quick Facts</h2>
    <p><strong>Phone:</strong> {$phone}</p>
    <p><strong>Email:</strong> {$email}</p>

    <h2>Services</h2>
    <ul>
      {$svcHtml}
    </ul>

    <p class='muted'>Generated automatically from public website content. Business owners can request edits at <code>/submit</code>.</p>
  </div>
</main>
</body>
</html>";

  file_put_contents($htmlPath, $html, LOCK_EX);
}
