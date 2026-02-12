<?php

declare(strict_types=1);
// /submit/submit.php

// Updated to use submissions.sqlite and richer schema

ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);








require_once __DIR__ . '/../profile_lib.php'; // assuming write_placeholder_profile is here



header('Content-Type: text/html; charset=utf-8');



function fail(string $msg, int $code = 400): void {

    http_response_code($code);

    echo "<!doctype html><html><body style='font-family:system-ui;padding:24px;'>";

    echo "<h2>Error</h2><p>" . htmlspecialchars($msg) . "</p>";

    echo "<p><a href='/submit'>Back</a></p>";

    echo "</body></html>";

    exit;

}



function normalize_domain(string $raw): string {

    $raw = trim($raw);

    if ($raw === '') return '';



    if (!preg_match('~^https?://~i', $raw)) {

        $raw = 'https://' . $raw;

    }



    $parts = parse_url($raw);

    if (!$parts || empty($parts['host'])) return '';



    $host = strtolower($parts['host']);

    if (str_starts_with($host, 'www.')) {

        $host = substr($host, 4);

    }



    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) return '';



    return $host;

}



try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        fail('Invalid request method.', 405);

    }



    $raw = $_POST['url'] ?? '';

    $domain = normalize_domain((string)$raw);



    if ($domain === '') {

        fail('Please enter a valid website (example: goodguys.app).');

    }



    // Use the main database

    $dbPath = __DIR__ . '/../submissions.sqlite';

    $pdo = new PDO("sqlite:$dbPath");

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



    $now = gmdate('c');



    // Insert / update

    $stmt = $pdo->prepare("

        INSERT INTO submissions (

            domain, url, name, category, city, state,

            status, created_at, updated_at, source, notes

        ) VALUES (

            :domain, :url, :name, :category, :city, :state,

            'new', :created_at, :updated_at, 'submit', :notes

        )

        ON CONFLICT(domain) DO UPDATE SET

            url = excluded.url,

            name = excluded.name,

            updated_at = excluded.updated_at,

            status = 'new',

            notes = excluded.notes || ' | ' || submissions.notes

    ");



    $stmt->execute([

        ':domain'     => $domain,

        ':url'        => 'https://' . $domain,

        ':name'       => $domain,               // will be improved later by cron

        ':category'   => 'Unknown',             // cron will try to improve

        ':city'       => 'Unknown',

        ':state'      => 'TX',                  // default – can be overridden later

        ':created_at' => $now,

        ':updated_at' => $now,

        ':notes'      => 'Submitted via /submit form'

    ]);



    // Create placeholder profile page immediately

    if (function_exists('write_placeholder_profile')) {

        write_placeholder_profile($domain, [

            'url'  => 'https://' . $domain,

            'name' => $domain,

            'cat'  => 'Unknown',

            'city' => 'Unknown',

            'state'=> 'TX'

        ]);

    }



    // Redirect to the public profile page

    header('Location: /site/' . rawurlencode($domain), true, 302);

    exit;



} catch (Throwable $e) {

    fail('Server error: ' . $e->getMessage(), 500);


}
