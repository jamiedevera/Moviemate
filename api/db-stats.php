<?php
// api/db-stats.php — Returns live platform stats as JSON
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$matches = 0;
$movies  = 0;

try {
    // Completed = both users submitted their picks
    $stmt    = $pdo->query("SELECT COUNT(*) FROM sessions WHERE a_movies IS NOT NULL AND b_movies IS NOT NULL AND a_movies != '' AND b_movies != ''");
    $matches = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    // DB unavailable – return defaults silently
}

echo json_encode([
    'matches' => $matches,
    'movies'  => $movies,
    'ok'      => true,
]);
