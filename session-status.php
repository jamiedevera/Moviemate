<?php
// session-status.php
require_once __DIR__ . '/db.php';

$sessionId = $_GET['session'] ?? '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_session']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT a_movies, b_movies, b_joined, b_name FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $aPresent  = !empty($row['a_movies']);
    $bPresent  = !empty($row['b_movies']);
    $bJoined   = !empty($row['b_joined']);
    $bName     = $row['b_name'] ?? '';

    echo json_encode([
        'aJoined'  => true, // A always present if session exists
        'bJoined'  => $bJoined,
        'bName'    => $bName,
        'aDone'    => $aPresent,
        'bDone'    => $bPresent,
        'bothDone' => ($aPresent && $bPresent),
        // legacy keys
        'a_movies' => $aPresent,
        'b_movies' => $bPresent,
    ]);

} catch (PDOException $e) {
    error_log('session-status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
exit;
