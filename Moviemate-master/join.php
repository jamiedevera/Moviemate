<?php
// join.php — entry point for Person B

require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$sessionId = $_GET['session'] ?? '';

// validate session id
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    die('Invalid link');
}

try {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        die('Session not found');
    }

if (!empty($session['b_movies'])) {
    $bothDone = !empty($session['b_movies']) && !empty($session['a_movies']);
    if ($bothDone) {
        header('Location: /m/' . $sessionId . '/match');
    } else {
        // or just send them back to the join page
        header('Location: /m/' . $sessionId);
    }
    exit;
}

} catch (PDOException $e) {
    error_log('Database error in join.php: ' . $e->getMessage());
    die('A database error occurred.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join Movie Date</title>
    <link rel="stylesheet" href="/assets/global.css?v=3">
</head>
<body>
<div class="glass-card">
    <h1>Hey moviemate <span>🍿</span></h1>
    <p>Someone sent you a movie date link. Click below to choose your movies as <strong>Person B</strong>.</p>
    <a class="cinematic-btn" href="/m/<?php echo htmlspecialchars($sessionId); ?>/b">
        Choose my movies 🎬
    </a>
</div>
</body>
</html>
