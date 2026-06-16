<?php
// api/db-stats.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$matches = 0;

try {
    // Count sessions where BOTH users submitted picks = completed match
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM sessions 
         WHERE a_movies IS NOT NULL AND a_movies != '' 
         AND b_movies IS NOT NULL AND b_movies != ''"
    );
    $matches = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log('db-stats error: ' . $e->getMessage());
}

echo json_encode([
    'matches' => $matches,
    'ok'      => true,
]);
