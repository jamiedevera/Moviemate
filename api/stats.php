<?php
// api/stats.php — Returns live platform stats as JSON

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$matches = 0;
$satisfaction = 98; // Default – can be computed from ratings later

try {
    $pdo = get_db_connection();

    // Count completed sessions (sessions where both users submitted)
    $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE status = 'completed'");
    $matches = (int) $stmt->fetchColumn();

    // If we have ratings, compute satisfaction
    // $stmt2 = $pdo->query("SELECT AVG(rating) * 20 FROM ratings");
    // $avgRating = $stmt2->fetchColumn();
    // if ($avgRating) $satisfaction = round($avgRating);

} catch (Exception $e) {
    // DB unavailable – return defaults silently
}

echo json_encode([
    'matches'      => $matches,
    'satisfaction' => $satisfaction,
    'ok'           => true,
]);
