<?php
// save-choices.php

require_once __DIR__ . '/db.php';

// prevent back-button cache for this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Validate inputs
$sessionId = $_POST['session'] ?? $_GET['session'] ?? '';
$who       = $_POST['who'] ?? $_GET['who'] ?? '';
$movies    = $_POST['movies'] ?? [];

// DEBUG: log request info to server error log to help identify 404/500 causes
error_log('save-choices invoked: method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' session=' . ($sessionId ?? '') . ' who=' . ($who ?? '') . ' movies_count=' . (is_array($movies) ? count($movies) : '0'));

// Check session ID format (16 hex characters)
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    displayError('Invalid session ID', 'The session ID is invalid. Please go back and try again.');
}

// CSRF Validation
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    displayError('Invalid Request', 'Security token mismatch (CSRF). Please refresh the page and try again.');
}

// Check who parameter
if (!in_array($who, ['A','B'], true)) {
    displayError('Invalid user type', 'Invalid user type detected. Please use the correct link.');
}

// Check movies array
if (!is_array($movies)) {
    displayError('Invalid data', 'The movie data is invalid. Please go back and try again.');
}

if (empty($movies)) {
    displayError('No movies selected', 'You must select at least 1 movie. Please go back and select your movies.');
}

// Check movie count — require exactly 5 picks
if (count($movies) !== 5) {
    displayError('Invalid selection count', 'You must select exactly 5 movies before saving. Please go back and pick 5 movies.');
}



// Verify session exists in database
try {
    $checkStmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $checkStmt->execute(['id' => $sessionId]);
    $session = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        displayError('Session not found', 'This session does not exist or may have expired.');
    }
    
    // Check if this person already submitted
    $alreadyDone = ($who === 'A' && !empty($session['a_movies'])) || 
                   ($who === 'B' && !empty($session['b_movies']));
    
    if ($alreadyDone) {
        displayError('Already submitted', 'You have already submitted your movie choices for this session.');
    }
} catch (PDOException $e) {
    error_log('Database error in save-choices.php (check): ' . $e->getMessage());
    displayError('Database error', 'A database error occurred. Please try again later.');
}

// Keep only integers and remove duplicates
$movies = array_map('intval', array_filter($movies, 'is_numeric'));
$movies = array_unique($movies);
$movies = array_values($movies); // re-index

// Validate all movie IDs are positive integers
foreach ($movies as $movieId) {
    if ($movieId <= 0) {
        displayError('Invalid movie IDs', 'One or more movie IDs are invalid. Please try again.');
    }
}

// Final check after deduplication
if (count($movies) !== 5) {
    displayError('Invalid selection count', 'You must select exactly 5 unique movies before saving. Please go back and pick 5 movies.');
}



$json = json_encode($movies);

// Update DB: set a_movies or b_movies
try {
    if ($who === 'A') {
        $stmt = $pdo->prepare('UPDATE sessions SET a_movies = :movies WHERE id = :id');
    } else {
        $stmt = $pdo->prepare('UPDATE sessions SET b_movies = :movies WHERE id = :id');
    }
    $success = $stmt->execute(['movies' => $json, 'id' => $sessionId]);
    
    if (!$success) {
        displayError('Save failed', 'Failed to save your choices. Please try again.');
    }
    
    // If AJAX, return JSON and stop
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
} catch (PDOException $e) {
    error_log('Database error saving choices: ' . $e->getMessage());
    displayError('Database error', 'Failed to save your choices. Please try again.');
}


// Check if both sides are done
try {
    $stmt = $pdo->prepare('SELECT a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        displayError('Session error', 'Session not found after saving.');
    }

$base = '';
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir = str_replace('\\', '/', __DIR__);
    $docRootLower = strtolower($docRoot);
    $dirLower = strtolower($dir);
    if (strpos($dirLower, $docRootLower) === 0) {
        $base = substr($dir, strlen($docRoot));
    }
}
$base = rtrim($base, '/\\');

if (!empty($row['a_movies']) && !empty($row['b_movies'])) {
    // Both done → pretty URL handled by .htaccess / router.php
    header('Location: ' . $base . '/m/' . urlencode($sessionId) . '/match');
    exit;
}

} catch (PDOException $e) {
    error_log('Database error checking completion: ' . $e->getMessage());
    displayError('Database error', 'An error occurred while checking completion. Please try again.');
}

// Only one side done → show simple waiting message
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Saved!</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/assets/global.css?v=3">
</head>
<body>
<div class="glass-card">
      <h2 style="font-size: 2rem; margin-bottom: 16px;">Your picks are saved <span style="color:var(--primary-red);">✅</span></h2>
      <p>You selected <strong><?php echo count($movies); ?></strong> movie<?php echo count($movies) !== 1 ? 's' : ''; ?>.</p>
      <p>We're waiting for the other person to finish choosing their movies.</p>
      <small class="small-text">You can safely close this tab now. When both of you are done, you'll be able to see your results.</small>
  </div>
</body>
</html>

<script>
(() => {
    const session = "<?php echo htmlspecialchars($sessionId); ?>";
    const statusUrl = "/m/" + session + "/status";
    const pollInterval = 3000; // ms
    let pollTimer = null;

    // Poll endpoint to detect when both players are done
    function checkStatus() {
        fetch(statusUrl, { method: 'GET', cache: 'no-store' })
            .then(res => {
                if (!res.ok) throw new Error('status ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data && data.bothDone) {
                    // Redirect to the match page (clean URL)
                    window.location.replace('/m/' + session + '/match');
                }
            })
            .catch(err => {
                // Keep polling silently — network hiccups will be retried
                console.error('session status check failed', err);
            });
    }

    try {
        // Start polling
        pollTimer = setInterval(checkStatus, pollInterval);
        // Kick off immediately
        checkStatus();

        // Stop polling when page is unloaded
        window.addEventListener('beforeunload', () => {
            if (pollTimer) clearInterval(pollTimer);
        });
    } catch (e) {
        console.error('Polling error', e);
    }
})();
</script>

<script>
// Poll the session-status endpoint to detect when both sides have saved
(function () {
    var session = <?php echo json_encode($sessionId); ?>;
    if (!session) return;
    var statusUrl = '/m/' + session + '/status';
    var pollInterval = 3000; // 3s
    var timer = null;

    function checkStatus() {
        fetch(statusUrl, { cache: 'no-store' })
            .then(function (res) { if (!res.ok) throw new Error('status ' + res.status); return res.json(); })
            .then(function (data) {
                if (data && data.bothDone) {
                    // Redirect to match page when both are done
                    window.location.replace('/m/' + session + '/match');
                }
            }).catch(function (err) {
                console.error('session status check failed', err);
            });
    }

    // Start polling
    timer = setInterval(checkStatus, pollInterval);
    checkStatus();
    // Stop polling on unload
    window.addEventListener('beforeunload', function () { if (timer) clearInterval(timer); });
})();
</script>

<script>
/* existing back-button / popstate safeguard — unchanged */
(() => {
    try {
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
            // Prompt user before navigating away to home
            var go = confirm('If you go back, your picks/session will be lost and the current session will be reset. Continue?');
            if (go) {
                window.location.replace('/');
            } else {
                try { history.pushState(null, null, window.location.href); } catch (e) {}
            }
        });
    } catch (e) {
        // ignore errors
    }
})();
</script>

<?php
function displayError($title, $message) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => $message]);
        exit;
    }
    http_response_code(400);

    $base = '';
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $dir = str_replace('\\', '/', __DIR__);
        $docRootLower = strtolower($docRoot);
        $dirLower = strtolower($dir);
        if (strpos($dirLower, $docRootLower) === 0) {
            $base = substr($dir, strlen($docRoot));
        }
    }
    $base = rtrim($base, '/\\');

    echo '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>
    <link rel="stylesheet" href="' . htmlspecialchars($base) . '/assets/global.css?v=3">
    </head>
    <body>
      <div class="glass-card" style="border-color: var(--primary-red);">
          <h2 style="color: var(--primary-red); font-size: 2rem; margin-bottom: 16px;">' . htmlspecialchars($title) . ' ❌</h2>
          <p>' . htmlspecialchars($message) . '</p>
          <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
              <a href="' . htmlspecialchars($base) . '/" onclick="return confirm(\'If you go home, your picks will be lost and the current session will be reset. Continue?\');" class="cinematic-btn">Go Home</a>
              <a href="' . htmlspecialchars($base) . '/" onclick="return confirm(\'If you go home, your picks will be lost and the current session will be reset. Continue?\');" class="cinematic-btn" style="background-color: #333;">Start Over</a>
          </div>
      </div>
    </body></html>';
    exit;
}
?>
