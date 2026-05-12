<?php
// debug-route.php — simple diagnostics for rewrite & session checks
require_once __DIR__ . '/db.php';

$sessionId = $_GET['session'] ?? '';
header('Content-Type: text/plain; charset=utf-8');
echo "DEBUG ROUTE\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n";
echo "session: " . ($sessionId ?: '(none)') . "\n";
if ($sessionId) {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
        echo "Session not found\n";
    } else {
        echo "Session found: " . json_encode($rows[0]) . "\n";
    }
}
echo "END DEBUG\n";

?>
