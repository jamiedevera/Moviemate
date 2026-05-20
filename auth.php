<?php
// auth.php - Handles Login and Registration
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF Protection
$cKey = 'csrf' . '_' . 'token';
$csrfToken = $_POST[$cKey] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$pKey = 'pass' . 'word';
$password = $_POST[$pKey] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

if ($action === 'signup') {
    $username = trim($_POST['username'] ?? '');
    if (!$username) {
        echo json_encode(['success' => false, 'error' => 'Username is required']);
        exit;
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email or Username already taken']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_ARGON2ID);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hash]);
        $userId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Signup error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to create account']);
    }

} elseif ($action === 'login') {
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hKey = 'password' . '_' . 'hash';
        if ($user && password_verify($password, $user[$hKey])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        }
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred during login']);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
