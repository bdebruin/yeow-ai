<?php
// cron_worker.php
// Updated version with improved page discovery, prioritization, text cleaning, and service extraction

declare(strict_types=1);

require_once __DIR__ . '/profile_lib.php';

$dbPath = __DIR__ . '/submissions.sqlite';

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("PRAGMA journal_mode=WAL;");
$pdo->exec("PRAGMA synchronous=NORMAL;");

// Process up to N per run (keep cron fast)
$LIMIT = 10;

// Fetch 'new' submissions
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE status='new' ORDER BY updated_at ASC LIMIT :lim");
$stmt->bindValue(':lim', $LIMIT, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$processed = 0;

foreach ($rows as $r) {
    $domain = $r['domain'];
    $url    = $r['url'];
    $name   = $r['name'] ?: $domain;

    try {
        // =============================================
        // IMPROVED: Smart page discovery & prioritization
        // =============================================

        $base = $url;
        $homeHtml = http_get($base, 10);
        if (!$homeHtml) {
            throw new RuntimeException("Failed to fetch homepage");
        }

        $bestName = find_best_name($homeHtml, $name);

        // Collect all outbound links
        $candidates = [];
        if (preg_match_all('~<a[^>]+href=["\']([^"\']+)["\']~i', $homeHtml, $m)) {
            foreach ($m[1] as $href) {
                $abs = abs_url($base, $href);
                if ($abs) {
                    $candidates[] = $abs;
                }
            }
        }
        $candidates = array_values(array_unique($candidates));

        // Scoring function: higher score = more likely useful page
        $score_url = function (string $u) use ($base): int {
            $lu = strtolower($u);
            $score = 0;

            // Homepage/root bonus
            if (preg_match('#/$#', $u) || str_ends_with($lu, $base)) {
                $score += 5;
            }

            // High-value pages (contact/quote)
            $high = ['contact', 'contact-us', 'get-in-touch', 'call-us', 'schedule', 'book', 'quote', 'estimate', 'appointment'];
            foreach ($high as $kw) {
                if (str_contains($lu, $kw)) $score += 12;
            }

            // Medium-value (about/team)
            $medium = ['about', 'about-us', 'our-story', 'team', 'company', 'who-we-are', 'history'];
            foreach ($medium as $kw) {
                if (str_contains($lu, $kw)) $score += 6;
            }

            // Services / offerings
            $service_kw = ['service', 'services', 'what-we-do', 'plumbing', 'repair', 'install', 'maintenance', 'emergency'];
            foreach ($service_kw as $kw) {
                if (str_contains($lu, $kw)) $score += 8;
            }

            // Pricing / locations
            if (str_contains($lu, 'price') || str_contains($lu, 'cost') || str_contains($lu, 'area') || str_contains($lu, 'location')) {
                $score += 4;
            }

            // Penalties
            if (str_contains($lu, 'blog') || str_contains($lu, 'news') || str_contains($lu, 'gallery') || str_contains($lu, 'photo')) {
                $score -= 5;
            }
            if (str_contains($lu, 'login') || str_contains($lu, 'account') || str_contains($lu, 'cart') || str_contains($lu, 'shop')) {
                $score -= 10;
            }

            return $score;
        };

        // Score and sort
        $scored = [];
        foreach ($candidates as $u) {
            $scored[$u] = $score_url($u);
        }
        arsort($scored);

        // Take top pages + always include homepage
        $top_urls = array_keys($scored);
        $top_urls = array_slice($top_urls, 0, 6); // max 6 extras

        $pages_to_crawl = [$base];
        foreach ($top_urls as $u) {
            if ($u !== $base && !in_array($u, $pages_to_crawl)) {
                $pages_to_crawl[] = $u;
            }
        }
        $pages_to_crawl = array_slice($pages_to_crawl, 0, 8); // safety cap

        // =============================================
        // Crawl selected pages
        // =============================================

        $texts = [];
        foreach ($pages_to_crawl as $page_url) {
            $html = http_get($page_url, 12);
            if ($html) {
                $clean = extract_text($html);
                $texts[] = $clean;
            }
        }

        $allText = implode("\n\n", $texts);

        // =============================================
        // IMPROVED: Better cleaning + service extraction
        // =============================================

        $cleanedText = clean_text($allText);

        $phone   = $r['phone']   ?: find_phone($cleanedText);
        $email   = $r['email']   ?: find_email($cleanedText);
        $services = extract_services($cleanedText);

        // =============================================
        // Build JSON-LD (expanded a bit)
        // =============================================

        $jsonld = [
            "@context" => "https://schema.org",
            "@type" => [$r['category'] ?: "Plumber", "LocalBusiness"],
            "name" => $bestName,
            "url" => $url,
            "description" => "AI-optimized business profile hosted by Yeow.ai",
            "areaServed" => [
                ["@type" => "City", "name" => $r['city'] ?: "Dallas"],
                ["@type" => "State", "name" => $r['state'] ?: "TX"]
            ],
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => $r['city'] ?: "Dallas",
                "addressRegion" => $r['state'] ?: "TX",
                "addressCountry" => "US",
            ],
        ];

        if ($phone) $jsonld["telephone"] = $phone;
        if ($email) $jsonld["email"] = $email;
        if (!empty($services)) {
            $jsonld["makesOffer"] = array_map(
                fn($s) => ["@type" => "Offer", "itemOffered" => ["@type" => "Service", "name" => $s]],
                $services
            );
        }

        // =============================================
        // Final profile array
        // =============================================

        $profile = [
            "domain"       => $domain,
            "url"          => $url,
            "name"         => $bestName,
            "category"     => $r['category'] ?: "Plumber",
            "city"         => $r['city'] ?: "Dallas",
            "state"        => $r['state'] ?: "TX",
            "phone"        => $phone,
            "email"        => $email,
            "services"     => $services,
            "sources"      => $pages_to_crawl,
            "generated_at" => gmdate('c'),
            "jsonld"       => $jsonld,
        ];

        // Write files (your existing function)
        write_full_profile_files($profile);

        // Mark as done
        $u = $pdo->prepare("UPDATE submissions SET status='published', last_error=NULL, updated_at=:t WHERE domain=:d");
        $u->execute([":t" => gmdate('c'), ":d" => $domain]);

        $processed++;

    } catch (Throwable $e) {
        $msg = substr($e->getMessage(), 0, 500);
        $u = $pdo->prepare("UPDATE submissions SET status='error', last_error=:err, updated_at=:t WHERE domain=:d");
        $u->execute([
            ":err" => $msg,
            ":t"   => gmdate('c'),
            ":d"   => $domain
        ]);
    }
}

// =============================================
// IMPROVED HELPER FUNCTIONS
// =============================================

function clean_text(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);

    // Remove junk patterns
    $junk = [
        '/(footer|sidebar|nav|menu|cookie|privacy|terms|disclaimer)/i',
        '/copyright ©|all rights reserved/i',
        '/call us today|serving.*since/i',
    ];
    foreach ($junk as $p) {
        $text = preg_replace($p, ' ', $text);
    }

    return trim($text);
}

function extract_services(string $text): array {
    $lines = explode("\n", $text);
    $services = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) < 10 || strlen($line) > 120) continue;

        // Bullet or dash patterns
        if (preg_match('/^[-•*➤✓—]\s+(.+)/', $line, $m)) {
            $services[] = trim($m[1]);
        }
        // Numbered
        elseif (preg_match('/^\d+\.?\s+(.+)/', $line, $m)) {
            $services[] = trim($m[1]);
        }
        // Likely service sentences
        elseif (preg_match('/(install|repair|replace|clean|service|fix|emergency|drain|pipe|leak|heater|water|sewer|plumb)/i', $line)) {
            if (str_word_count($line) <= 12) {
                $services[] = $line;
            }
        }
    }

    $services = array_unique($services);
    $services = array_filter($services, fn($s) => strlen(trim($s)) >= 8);
    return array_slice($services, 0, 8);
}

// =============================================
// Output for cron logs
// =============================================

header("Content-Type: text/plain; charset=utf-8");
echo "OK processed={$processed}\n";