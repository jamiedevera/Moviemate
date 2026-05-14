<?php
// choose.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tmdb_genres.php';

// prevent back-button cache for this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// compute base path (works for subfolders)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '/' || $base === '\\') { $base = ''; }

$sessionId = $_GET['session'] ?? '';
$who       = $_GET['who'] ?? ''; // 'A' or 'B'

// Validate session ID format
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    die('Invalid session ID. Please start over from the homepage.');
}

// Validate who parameter
if (!in_array($who, ['A', 'B'], true)) {
    die('Invalid user type. Please use the correct link.');
}

// Verify session exists in database
try {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        die('Session not found. The link may be invalid or expired.');
    }

    // Check if this person already completed their selection
    $alreadyDone = ($who === 'A' && !empty($session['a_movies'])) || 
                   ($who === 'B' && !empty($session['b_movies']));
    
    if ($alreadyDone) {
        // If both sides completed, go to results; otherwise show the waiting page
        $bothDone = !empty($session['a_movies']) && !empty($session['b_movies']);
        if ($bothDone) {
            header('Location: /m/' . $sessionId . '/match');
        } else {
            header('Location: /m/' . $sessionId . '/save');
        }
        exit;
    }
} catch (PDOException $e) {
    error_log('Database error in choose.php: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}

// ---- Load popular movies (page 1) on the server ----
$popularEndpoint = 'https://api.themoviedb.org/3/movie/popular?api_key='
    . TMDB_API_KEY . '&language=en-US&page=1';

$popularMovies = [];
$response = @file_get_contents($popularEndpoint);
if ($response !== false) {
    $json = json_decode($response, true);
    if (!empty($json['results']) && is_array($json['results'])) {
        $popularMovies = $json['results'];
    }
}

// Check if TMDb API is working
if (empty($popularMovies)) {
    die('Unable to load movies from TMDb. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Movies</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base . '/assets/global.css?v=' . time()); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base . '/assets/choose.css?v=' . time()); ?>">
</head>
<body>

<div class="choice-container">
    <div class="movie-explorer">
        <h1 id="pageTitle" class="page-title">Select Your Movies <span>🎬</span></h1>


        <div class="search-wrapper" id="searchWrapper">
            <div class="search-bar">
                <input type="text" id="movieSearch" class="input-box" style="margin-bottom: 0;" placeholder="Search any movie...">

                <div class="search-filters" id="searchFilters">
                    <select id="filterGenre">
                        <option value="">Genre</option>
                        <?php foreach ($TMDB_GENRES as $gid => $gname): ?>
                            <option value="<?php echo (int)$gid; ?>">
                                <?php echo htmlspecialchars($gname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input
                        type="number"
                        id="filterYear"
                        placeholder="Year"
                        min="1900"
                        max="<?php echo date('Y') + 1; ?>"
                    >

                    <button type="button" class="cinematic-btn" style="padding: 10px 20px; border-radius: 8px; width: auto;" id="applyFilterBtn">Go</button>
                </div>
            </div>

            <div class="results" id="resultsBox"></div>
        </div>

        <h2 class="section-heading">Most Popular Movies</h2>
        <div class="grid" id="popularGrid"></div>
        <div id="paginationControls" class="pagination"></div>
    </div>

    <aside class="selection-sidebar" id="selectionSidebar" style="display: none;">
        <div class="selection-tile" id="selectionTile">
            <h3>Your Top 5 Picks</h3>
            
            <div class="progress-bar">
                <div class="pill" id="pill-0"></div>
                <div class="pill" id="pill-1"></div>
                <div class="pill" id="pill-2"></div>
                <div class="pill" id="pill-3"></div>
                <div class="pill" id="pill-4"></div>
            </div>


            <div class="selected-list" id="selectedList">
                <!-- Selected movies will be injected here -->
                <div class="empty-state">No movies picked yet</div>
            </div>

            <form action="<?php echo htmlspecialchars($base . '/m/' . $sessionId . '/save'); ?>" method="post" id="movieForm" onsubmit="return handleFormSubmit(event)">
                <input type="hidden" name="session" value="<?php echo htmlspecialchars($sessionId); ?>">
                <input type="hidden" name="who" value="<?php echo htmlspecialchars($who); ?>">
                <!-- Hidden inputs for movies will be added by JS -->
                <div id="hiddenMovies"></div>
                
                <button type="submit" id="saveBtn" class="cinematic-btn submit-btn" disabled>Submit Picks</button>
            </form>
        </div>
    </aside>
</div>

<!-- Waiting State Overlay -->
<div id="waitingOverlay" class="waiting-overlay" style="display: none;">
    <div class="waiting-content">
        <div class="pulse-indicator"></div>
        <h2>Saving your picks...</h2>
        <p id="waitingMessage">Waiting for your moviemate to finish...</p>
    </div>
</div>

<div id="feedback" aria-live="polite" style="position:fixed;top:16px;right:16px;z-index:2000;display:none;padding:10px 14px;border-radius:8px;background:#111827;color:#f9fafb;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>




    <script>
        const TMDB_KEY       = "<?php echo TMDB_API_KEY; ?>";
        const GENRE_MAP      = <?php echo json_encode($TMDB_GENRES); ?>;
        // Validation handled by /assets/choose.js validateForm();
    </script>
    <script src="<?php echo htmlspecialchars($base . '/assets/choose.js?v=' . time()); ?>"></script>
</body>
</html>
