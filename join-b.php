<?php
// join-b.php — marks b_joined=true and saves b_name when guest enters
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$sessionId = $_GET['session'] ?? '';
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_session']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$bName = substr(trim($body['name'] ?? ''), 0, 40);

try {
    $stmt = $pdo->prepare('UPDATE sessions SET b_joined = TRUE, b_name = :b_name WHERE id = :id');
    $stmt->execute(['b_name' => $bName ?: null, 'id' => $sessionId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('join-b error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
exit;
