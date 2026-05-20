<?php
// config.php
$envPath = __DIR__ . '/env.ini';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];

// Read TMDB API Key (Support Base64 or Raw)
$tmdbKey = '';
if (isset($env['TMDB_KEY_B64'])) {
    $tmdbKey = base64_decode($env['TMDB_KEY_B64']);
} elseif (getenv('TMDB_KEY_B64')) {
    $tmdbKey = base64_decode(getenv('TMDB_KEY_B64'));
} elseif (getenv('TMDB_API_KEY')) {
    $tmdbKey = getenv('TMDB_API_KEY');
}
define('TMDB_API_KEY', $tmdbKey);

// Read Database Host
define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?? '');

// Read Database Port
define('DB_PORT', $env['DB_PORT'] ?? getenv('DB_PORT') ?? '6543');

// Read Database Name
define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?? '');

// Read Database User
define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?? '');

// Read Database Password (Support Base64 or Raw)
$dbPass = '';
if (isset($env['DB_PASS_B64'])) {
    $dbPass = base64_decode($env['DB_PASS_B64']);
} elseif (getenv('DB_PASS_B64')) {
    $dbPass = base64_decode(getenv('DB_PASS_B64'));
} elseif (getenv('DB_PASSWORD')) {
    $dbPass = getenv('DB_PASSWORD');
} elseif (getenv('DB_PASS')) {
    $dbPass = getenv('DB_PASS');
}
define('DB_PASS', $dbPass);

// --- PRODUCTION SECURITY ENHANCEMENTS ---

// 1. Secure Headers (OWASP)
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
// CSP allows local resources and TMDb API/Images
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://image.tmdb.org https://via.placeholder.com https://placehold.co data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.themoviedb.org;");

// 2. Session Security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Basic IP Rate Limiting (60 requests per minute)
$rateLimitWindow = 60;
$maxRequests = 60;
$time = time();

if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = ['count' => 1, 'time' => $time];
} else {
    if ($time - $_SESSION['rate_limit']['time'] < $rateLimitWindow) {
        $_SESSION['rate_limit']['count']++;
        if ($_SESSION['rate_limit']['count'] > $maxRequests) {
            http_response_code(429);
            die("429 Too Many Requests. Please slow down.");
        }
    } else {
        $_SESSION['rate_limit'] = ['count' => 1, 'time' => $time];
    }
}

// Global robust HTTP client wrapper to prevent TMDb loading failures (SSL, allow_url_fopen, etc.)
function http_get_contents($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);
        
        // Manual decompression fallback
        if ($result !== false && substr($result, 0, 2) === "\x1f\x8b") {
            $decompressed = @gzdecode($result);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }
        return $result;
    }
    
    // Fallback using file_get_contents with context to disable SSL verify
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'header' => "Accept-Encoding: gzip\r\n"
        ]
    ]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result !== false && substr($result, 0, 2) === "\x1f\x8b") {
        $decompressed = @gzdecode($result);
        if ($decompressed !== false) {
            return $decompressed;
        }
    }
    return $result;
}


