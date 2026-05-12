<?php
// session-status.php — returns JSON about whether both sides finished for a session
require_once __DIR__ . '/db.php';

$sessionId = $_GET['session'] ?? '';
header('Content-Type: application/json; charset=utf-8');

if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_session']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $aPresent = !empty($row['a_movies']);
    $bPresent = !empty($row['b_movies']);
    $aCount = $aPresent ? count(json_decode($row['a_movies'], true)) : 0;
    $bCount = $bPresent ? count(json_decode($row['b_movies'], true)) : 0;

    echo json_encode([
        'a_movies' => $aPresent,
        'b_movies' => $bPresent,
        'a_count'  => $aCount,
        'b_count'  => $bCount,
        'bothDone' => ($aPresent && $bPresent),
    ]);
} catch (PDOException $e) {
    error_log('session-status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
exit;