<?php
// router.php - Emulates .htaccess for PHP built-in server

// Custom static file handler to serve assets from the project folder under any document root
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Override DOCUMENT_ROOT on Vercel to ensure base path calculations resolve to empty string
if (getenv('VERCEL') === '1' || isset($_SERVER['VERCEL'])) {
    $_SERVER['DOCUMENT_ROOT'] = __DIR__;
}

$base = '';
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir = str_replace('\\', '/', __DIR__);
    $docRootLower = strtolower($docRoot);
    $dirLower = strtolower($dir);
    if (strpos($dirLower, $docRootLower) === 0) {
        $base = substr($dir, strlen($docRoot));
    }
}
$base = rtrim($base, '/\\');

$targetFile = null;
if (file_exists(__DIR__ . '/public' . $uri) && !is_dir(__DIR__ . '/public' . $uri)) {
    $targetFile = __DIR__ . '/public' . $uri;
} elseif (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    $targetFile = __DIR__ . $uri;
} elseif ($base !== '' && strpos($uri, $base) === 0) {
    $strippedUri = substr($uri, strlen($base));
    if (file_exists(__DIR__ . '/public' . $strippedUri) && !is_dir(__DIR__ . '/public' . $strippedUri)) {
        $targetFile = __DIR__ . '/public' . $strippedUri;
    } elseif (file_exists(__DIR__ . $strippedUri) && !is_dir(__DIR__ . $strippedUri)) {
        $targetFile = __DIR__ . $strippedUri;
    }
}

if ($targetFile !== null && preg_match('/\.(?:png|jpg|jpeg|gif|css|js|woff|woff2|ttf|svg)$/', $targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($targetFile);
    return true;
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Strip subdirectory prefix if running from a parent directory
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}

// /m/<session>
if (preg_match('#^/m/([a-f0-9]{16})/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require 'join.php';
    return true;
}

// /m/<session>/a
if (preg_match('#^/m/([a-f0-9]{16})/a/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    $_GET['who'] = 'A';
    require 'choose.php';
    return true;
}

// /m/<session>/b
if (preg_match('#^/m/([a-f0-9]{16})/b/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    $_GET['who'] = 'B';
    require 'choose.php';
    return true;
}

// /m/<session>/match
if (preg_match('#^/m/([a-f0-9]{16})/match/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require 'match.php';
    return true;
}

// /m/<session>/save
if (preg_match('#^/m/([a-f0-9]{16})/save/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require 'save-choices.php';
    return true;
}

// /m/<session>/status
if (preg_match('#^/m/([a-f0-9]{16})/status/?$#', $path, $matches)) {
    $_GET['session'] = $matches[1];
    require 'session-status.php';
    return true;
}

// /api/auth
if ($path === '/api/auth' || $path === '/api/auth/') {
    require 'auth.php';
    return true;
}

// /logout
if ($path === '/logout' || $path === '/logout/') {
    require 'config.php';
    session_destroy();
    header("Location: " . ($base ?: "/"));
    return true;
}

// Fallback: If it's a real file, serve/execute it. If it's a directory or missing, serve index.php
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        require __DIR__ . $path;
        return true;
    }
    return false;
}

require 'index.php';
return true;
