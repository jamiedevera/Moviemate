<?php
// create-link.php – Person A picks, generate shareable session link

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tmdb.php';

// Person A picks
$selectedMovies = $_POST['movies'] ?? [];

if (!is_array($selectedMovies) || count($selectedMovies) === 0) {
    die("Please go back and select at least one movie.");
}

// allow up to 5 now (instead of 4)
if (count($selectedMovies) > 5) {
    die("Please go back and select up to 5 movies only.");
}

// Sanitize IDs
$selectedMovies = array_map('intval', $selectedMovies);

// 1️⃣ Create a new session and save Person A's movies in the DB
// Generate a secure random session ID (16 hex chars). Use a fallback if random_bytes not available.
try {
    if (function_exists('random_bytes')) {
        $sessionId = bin2hex(random_bytes(8));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $sessionId = bin2hex(openssl_random_pseudo_bytes(8));
    } else {
        // Last resort: lightweight fallback using uniqid, not cryptographically secure but still unique
        $sessionId = substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 16);
    }
} catch (Exception $e) {
    error_log('Session ID generation failed: ' . $e->getMessage());
    // fallback
    $sessionId = substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 16);
}

// Save A's movie list as JSON in sessions.a_movies
$aMoviesJson = json_encode($selectedMovies);

try {
    $stmt = $pdo->prepare('INSERT INTO sessions (id, a_movies) VALUES (:id, :a_movies)');
    $stmt->execute([
        'id'       => $sessionId,
        'a_movies' => $aMoviesJson,
    ]);
} catch (PDOException $e) {
    error_log('Failed to create session: ' . $e->getMessage());
    // Friendly error page
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body style="background:#111827;color:#f9fafb;font-family:Arial;display:flex;align-items:center;justify-content:center;min-height:100vh;"><div style="background:#1f2937;padding:24px;border-radius:12px;max-width:560px;width:100%;text-align:center;"><h2>Unable to create session</h2><p>We could not create a new session due to a server error. Please try again in a moment.</p><p style="margin-top:10px; font-size:0.9rem;">If this keeps happening, contact support.</p></div></body></html>';
    exit;
}

// Log a successful creation for diagnostics
error_log('Session created: ' . $sessionId . ' (movies=' . count($selectedMovies) . ')');

// 2️⃣ Build pretty link for your moviemate (they will be Person B)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // folder where this file lives

// Pretty URL instead of join.php?session=...
$shareLink = $scheme . '://' . $host . $basePath . '/m/' . $sessionId . '/b';
$resultLink = $scheme . '://' . $host . $basePath . '/m/' . $sessionId . '/match';

// Fallback link (in case the host doesn't support mod_rewrite / pretty URLs)
$fallbackShareLink = $scheme . '://' . $host . $basePath . '/join.php?session=' . $sessionId . '&who=B';

// 3️⃣ Fetch movie details for preview (reusing tmdb_get_movie)
$movieDetails = [];

foreach ($selectedMovies as $id) {
    try {
        $movie = tmdb_get_movie($id);
        if ($movie) {
            $movieDetails[] = [
                'id'     => $movie['id'],
                'title'  => $movie['title'],
                'poster' => $movie['poster'] ?? '', // safe fallback
            ];
        }
    } catch (Exception $e) {
        error_log('TMDB lookup failed for id ' . (int)$id . ': ' . $e->getMessage());
        // fall back: keep working without TMDB detail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Invite Link</title>

    <!-- Load external CSS -->
    <link rel="stylesheet" href="/assets/global.css">
    <link rel="stylesheet" href="/assets/create-link.css">
</head>
<body>
<div class="glass-card">
    <h1>Share this link <span>💌</span></h1>
    <p>Send this link to the other person so they can pick their movies too:</p>

    <!-- show PRETTY URL and a fallback link (join.php) if pretty URLs aren't available -->
    <input class="input-box" type="text" readonly value="<?php echo htmlspecialchars($shareLink); ?>" id="shareLink" style="margin-bottom: 8px;">
    <small class="small-text" style="display:block; margin-top:8px; opacity:0.86; margin-bottom: 8px;">If this link doesn't work on your host, use the fallback link:</small>
    <input class="input-box" type="text" readonly value="<?php echo htmlspecialchars($fallbackShareLink); ?>" id="fallbackShareLink" style="margin-bottom: 16px;">
    <button class="cinematic-btn" onclick="copyLink()">Copy to clipboard</button>

    <?php if (!empty($movieDetails)): ?>
        <div class="chosen">
            <p class="small">You chose these movies:</p>
            <div class="grid">
                <?php foreach ($movieDetails as $movie): ?>
                    <div class="movie">
                        <img src="<?php echo htmlspecialchars($movie['poster']); ?>"
                             alt="<?php echo htmlspecialchars($movie['title']); ?>">
                        <div class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <p class="small">
        When they open the link, they’ll be Person B.  
        They’ll pick their 5 movies, and after that you’ll both see your compatibility and recommendations.
    </p>
</div>

<script>
function copyLink() {
    const input = document.getElementById('shareLink');
    input.select();
    document.execCommand('copy');
    alert('Link copied to clipboard!');
}
</script>

</body>
</html>
