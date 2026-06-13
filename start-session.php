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
    $wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($wantsJson) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
    } else {
        die('Database error occurred while starting session.');
    }
    exit;
}

$redirectUrl = "/m/{$sessionId}/a";

// JSON response for fetch()-based callers (Next.js session flow)
$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

if ($wantsJson) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'   => true,
        'sessionId' => $sessionId,
        'url'       => $redirectUrl,
    ]);
    exit;
}

// Legacy redirect for direct form-POST callers
header("Location: {$redirectUrl}");
exit;
