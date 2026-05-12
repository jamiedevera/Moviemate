<?php
// router.php - Emulates .htaccess for PHP built-in server

// Serve static files as-is
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|woff|woff2|ttf|svg)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

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

// Fallback: If it's a real file, serve it. If it's a directory or missing, serve index.php
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require 'index.php';
return true;
