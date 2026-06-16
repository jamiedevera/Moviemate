<?php
// save-choices.php
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$sessionId = $_POST['session'] ?? $_GET['session'] ?? '';
$who       = $_POST['who']     ?? $_GET['who']     ?? '';
$movies    = $_POST['movies']  ?? [];

error_log('save-choices: method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' session=' . $sessionId . ' who=' . $who . ' movies_count=' . (is_array($movies) ? count($movies) : 0));

if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    displayError('Invalid session ID', 'The session ID is invalid. Please go back and try again.');
}

// ── CSRF — stateless only (sessions don't persist on Vercel serverless) ──────
// Both choose.php and save-choices.php derive the same token from sessionId + secret.
// No PHP session required.
$secret         = defined('DB_PASS') ? DB_PASS : 'moviemate_secret';
$expectedToken  = hash_hmac('sha256', $sessionId . '_csrf', $secret);
$submittedToken = $_POST['csrf_token'] ?? '';

if (!hash_equals($expectedToken, $submittedToken)) {
    // On Vercel, PHP sessions are unreliable — skip session token and rely solely on stateless token.
    // If stateless also fails, show error.
    displayError('Invalid Request', 'Security token mismatch. Please refresh the page and try again.');
}

if (!in_array($who, ['A', 'B'], true)) {
    displayError('Invalid user type', 'Invalid user type detected.');
}

if (!is_array($movies) || empty($movies)) {
    displayError('No movies selected', 'You must select at least 1 movie.');
}

if (count($movies) !== 5) {
    displayError('Invalid selection count', 'You must select exactly 5 movies. You selected ' . count($movies) . '.');
}

// Verify session exists
try {
    $checkStmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $checkStmt->execute(['id' => $sessionId]);
    $session = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) displayError('Session not found', 'This session does not exist or may have expired.');

    $alreadyDone = ($who === 'A' && !empty($session['a_movies'])) ||
                   ($who === 'B' && !empty($session['b_movies']));
    if ($alreadyDone) displayError('Already submitted', 'You have already submitted your movie choices.');
} catch (PDOException $e) {
    error_log('DB error (check): ' . $e->getMessage());
    displayError('Database error', 'A database error occurred. Please try again.');
}

// Sanitise movies
$movies = array_values(array_unique(array_map('intval', array_filter($movies, 'is_numeric'))));
foreach ($movies as $mid) {
    if ($mid <= 0) displayError('Invalid movie IDs', 'One or more movie IDs are invalid.');
}
if (count($movies) !== 5) {
    displayError('Invalid selection count', 'You must select exactly 5 unique movies.');
}

$json = json_encode($movies);

// Save to DB
try {
    $col  = ($who === 'A') ? 'a_movies' : 'b_movies';
    $stmt = $pdo->prepare("UPDATE sessions SET {$col} = :movies WHERE id = :id");
    if (!$stmt->execute(['movies' => $json, 'id' => $sessionId])) {
        displayError('Save failed', 'Failed to save your choices. Please try again.');
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
} catch (PDOException $e) {
    error_log('DB error (save): ' . $e->getMessage());
    displayError('Database error', 'Failed to save your choices. Please try again.');
}

// Compute base
$base = '';
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir     = str_replace('\\', '/', __DIR__);
    if (stripos($dir, $docRoot) === 0) $base = substr($dir, strlen($docRoot));
}
$base = rtrim($base, '/\\');

// Check if both done → redirect to match
try {
    $stmt = $pdo->prepare('SELECT a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) displayError('Session error', 'Session not found after saving.');

    if (!empty($row['a_movies']) && !empty($row['b_movies'])) {
        header('Location: ' . $base . '/m/' . urlencode($sessionId) . '/match');
        exit;
    }
} catch (PDOException $e) {
    error_log('DB error (check completion): ' . $e->getMessage());
    displayError('Database error', 'An error occurred while checking completion.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Saved! — MovieMate</title>
    <link rel="stylesheet" href="/assets/global.css">
</head>
<body>
<div class="glass-card">
    <h2 style="font-size:2rem;margin-bottom:16px;">Your picks are saved <span style="color:var(--primary-red);">✅</span></h2>
    <p>You selected <strong><?php echo count($movies); ?></strong> movies.</p>
    <p id="waitingText">Waiting for your MovieMate to finish choosing...</p>
    <small class="small-text">You can safely close this tab. When both of you are done, you'll be redirected automatically.</small>
</div>

<script>
(function () {
    var session    = <?php echo json_encode($sessionId); ?>;
    var statusUrl  = '/m/' + session + '/status';
    var timer      = setInterval(checkStatus, 3000);
    checkStatus();
    window.addEventListener('beforeunload', function () { clearInterval(timer); });

    function checkStatus() {
        fetch(statusUrl, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.bothDone) window.location.replace('/m/' + session + '/match'); })
            .catch(function () {});
    }

    setTimeout(function () {
        var el = document.getElementById('waitingText');
        if (el) el.innerHTML = "Still waiting? Make sure your partner has opened their link!<br><br><a href='/' style='color:#ef4444;font-weight:bold;text-decoration:underline;'>Start a New Session</a>";
    }, 15000);
})();
</script>
</body>
</html>
<?php
function displayError($title, $message) {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => $message]);
        exit;
    }
    http_response_code(400);
    $base = '';
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $dir     = str_replace('\\', '/', __DIR__);
        if (stripos($dir, $docRoot) === 0) $base = substr($dir, strlen($docRoot));
    }
    $base = rtrim($base, '/\\');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>
    <link rel="stylesheet" href="/assets/global.css"></head><body>
    <div class="glass-card" style="border-color:var(--primary-red);">
        <h2 style="color:var(--primary-red);font-size:2rem;margin-bottom:16px;">' . htmlspecialchars($title) . ' ❌</h2>
        <p>' . htmlspecialchars($message) . '</p>
        <div style="margin-top:24px;display:flex;flex-direction:column;gap:12px;">
            <a href="' . htmlspecialchars($base) . '/" class="cinematic-btn">Go Home</a>
        </div>
    </div></body></html>';
    exit;
}
?>
