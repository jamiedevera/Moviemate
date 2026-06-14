<?php
// api/router.php — Vercel serverless entry point
// All requires use __DIR__.'/../' to reach root-level PHP files

// On Vercel, fix DOCUMENT_ROOT so base path resolves correctly
if (getenv('VERCEL') === '1' || isset($_SERVER['VERCEL'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
}

$rootDir = dirname(__DIR__);

$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

$base = '';
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir     = str_replace('\\', '/', $rootDir);
    if (strpos(strtolower($dir), strtolower($docRoot)) === 0) {
        $base = substr($dir, strlen($docRoot));
    }
}
$base = rtrim($base, '/\\');

// Serve static assets
$targetFile = null;
if (file_exists($rootDir . '/public' . $uri) && !is_dir($rootDir . '/public' . $uri)) {
    $targetFile = $rootDir . '/public' . $uri;
} elseif (file_exists($rootDir . $uri) && !is_dir($rootDir . $uri)) {
    $targetFile = $rootDir . $uri;
}

if ($targetFile !== null && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|woff|woff2|ttf|svg)$/', $targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($targetFile);
    return true;
}

$path = $uri;
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}

// /m/<session>
if (preg_match('#^/m/([a-f0-9]{16})/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require $rootDir . '/join.php';
    return true;
}

// /m/<session>/a
if (preg_match('#^/m/([a-f0-9]{16})/a/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    $_GET['who'] = 'A';
    require $rootDir . '/choose.php';
    return true;
}

// /m/<session>/b
if (preg_match('#^/m/([a-f0-9]{16})/b/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    $_GET['who'] = 'B';
    require $rootDir . '/choose.php';
    return true;
}

// /m/<session>/match
if (preg_match('#^/m/([a-f0-9]{16})/match/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require $rootDir . '/match.php';
    return true;
}

// /m/<session>/save
if (preg_match('#^/m/([a-f0-9]{16})/save/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require $rootDir . '/save-choices.php';
    return true;
}

// /m/<session>/status
if (preg_match('#^/m/([a-f0-9]{16})/status/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require $rootDir . '/session-status.php';
    return true;
}

// /m/<session>/join-b
if (preg_match('#^/m/([a-f0-9]{16})/join-b/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require $rootDir . '/join-b.php';
    return true;
}

// /start-session
if ($path === '/start-session' || $path === '/start-session/' || $path === '/start-session.php') {
    require $rootDir . '/start-session.php';
    return true;
}

// /api/auth
if ($path === '/api/auth' || $path === '/api/auth/') {
    require $rootDir . '/auth.php';
    return true;
}

// /api/stats
if ($path === '/api/stats' || $path === '/api/stats/') {
    require __DIR__ . '/db-stats.php';
    return true;
}

// /logout
if ($path === '/logout' || $path === '/logout/') {
    require $rootDir . '/config.php';
    session_destroy();
    header("Location: " . ($base ?: "/"));
    return true;
}

// Fallback
if ($path !== '/' && file_exists($rootDir . $path) && !is_dir($rootDir . $path)) {
    if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        require $rootDir . $path;
        return true;
    }
    return false;
}

require $rootDir . '/index.php';
return true;
