<?php
// start-session.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Generate random session id
try {
    if (function_exists('random_bytes')) {
        $sessionId = bin2hex(random_bytes(8));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $sessionId = bin2hex(openssl_random_pseudo_bytes(8));
    } else {
        $sessionId = substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 16);
    }
} catch (Exception $e) {
    $sessionId = substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 16);
}

// Insert into DB
try {
    $stmt = $pdo->prepare('INSERT INTO sessions (id) VALUES (:id)');
    $stmt->execute(['id' => $sessionId]);
} catch (PDOException $e) {
    error_log('Failed to create session: ' . $e->getMessage());
    die('Database error occurred while starting session.');
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

// Redirect to Person A's selection page
header("Location: {$base}/m/{$sessionId}/a");
exit;
