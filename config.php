<?php
// config.php

// Disable displaying deprecation warnings and minor notices in production to prevent layout corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Override DOCUMENT_ROOT on Vercel to ensure base path calculations resolve to empty string
if (getenv('VERCEL') === '1' || isset($_SERVER['VERCEL'])) {
    $_SERVER['DOCUMENT_ROOT'] = __DIR__;
}

$envPath = __DIR__ . '/env.ini';
$env = file_exists($envPath) ? parse_ini_file($envPath) : [];

// Read TMDB API Key (Support Base64 or Raw)
$tmdbKey = '';
if (isset($env['TMDB_KEY_B64'])) {
    $tmdbKey = base64_decode($env['TMDB_KEY_B64']);
} elseif (isset($_ENV['TMDB_KEY_B64'])) {
    $tmdbKey = base64_decode($_ENV['TMDB_KEY_B64']);
} elseif (isset($_SERVER['TMDB_KEY_B64'])) {
    $tmdbKey = base64_decode($_SERVER['TMDB_KEY_B64']);
} elseif (getenv('TMDB_KEY_B64')) {
    $tmdbKey = base64_decode(getenv('TMDB_KEY_B64'));
} elseif (isset($_ENV['TMDB_API_KEY'])) {
    $tmdbKey = $_ENV['TMDB_API_KEY'];
} elseif (isset($_SERVER['TMDB_API_KEY'])) {
    $tmdbKey = $_SERVER['TMDB_API_KEY'];
} elseif (getenv('TMDB_API_KEY')) {
    $tmdbKey = getenv('TMDB_API_KEY');
}
define('TMDB_API_KEY', $tmdbKey);

// Read Database Host
define('DB_HOST', trim($env['DB_HOST'] ?? $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?? ''));

// Read Database Port
define('DB_PORT', trim($env['DB_PORT'] ?? $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?? '6543'));

// Read Database Name
define('DB_NAME', trim($env['DB_NAME'] ?? $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?? ''));

// Read Database User
define('DB_USER', trim($env['DB_USER'] ?? $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?? $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?? ''));

// Read Database Password (Support Base64 or Raw)
$dbPass = '';
if (isset($env['DB_PASS_B64'])) {
    $dbPass = base64_decode($env['DB_PASS_B64']);
} elseif (isset($_ENV['DB_PASS_B64'])) {
    $dbPass = base64_decode($_ENV['DB_PASS_B64']);
} elseif (isset($_SERVER['DB_PASS_B64'])) {
    $dbPass = base64_decode($_SERVER['DB_PASS_B64']);
} elseif (getenv('DB_PASS_B64')) {
    $dbPass = base64_decode(getenv('DB_PASS_B64'));
} elseif (isset($_ENV['DB_PASSWORD'])) {
    $dbPass = $_ENV['DB_PASSWORD'];
} elseif (isset($_SERVER['DB_PASSWORD'])) {
    $dbPass = $_SERVER['DB_PASSWORD'];
} elseif (getenv('DB_PASSWORD')) {
    $dbPass = getenv('DB_PASSWORD');
} elseif (isset($_ENV['DB_PASS'])) {
    $dbPass = $_ENV['DB_PASS'];
} elseif (isset($_SERVER['DB_PASS'])) {
    $dbPass = $_SERVER['DB_PASS'];
} elseif (getenv('DB_PASS')) {
    $dbPass = getenv('DB_PASS');
}
define('DB_PASS', trim($dbPass));

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
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }
            
            // Manual decompression fallback
            if ($result !== false && substr($result, 0, 2) === "\x1f\x8b") {
                $decompressed = @gzdecode($result);
                if ($decompressed !== false) {
                    return $decompressed;
                }
            }
            return $result;
        }
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

// Helper to generate and retrieve CSRF tokens (supports session and stateless fallback)
function get_csrf_token($sessionId = '') {
    $secret = defined('DB_PASS') ? DB_PASS : 'moviemate_secret';
    if (!empty($sessionId)) {
        return hash_hmac('sha256', $sessionId . '_csrf', $secret);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash_hmac('sha256', $ip . '|' . $ua, $secret);
}


