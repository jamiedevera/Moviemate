<?php
// match.php – TMDB-BASED VERSION (session-based, with smarter compatibility + recommendations)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tmdb.php';

// prevent browser caching / back-button showing stale UI
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

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

// ------------ Read session & load from DB ------------
$sessionId = $_GET['session'] ?? '';

// Validate session ID format
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    die('Invalid session ID. The link might be incorrect.');
}

try {
    $stmt = $pdo->prepare('SELECT a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database error in match.php: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}

if (!$row) {
    die('Session not found. The link may be invalid or expired.');
}

$aMoviesJson = $row['a_movies'] ?? null;
$bMoviesJson = $row['b_movies'] ?? null;

if (empty($aMoviesJson) || empty($bMoviesJson)) {
    echo '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Waiting...</title></head>
    <body style="background:#111827;color:#f9fafb;font-family:Arial;display:flex;align-items:center;justify-content:center;min-height:100vh;">
      <div style="background:#1f2937;padding:24px;border-radius:16px;max-width:480px;text-align:center;">
          <h2>Not Ready Yet ⏳</h2>
          <p>Both people need to finish choosing their movies before viewing the results.</p>
          <p><a href="' . htmlspecialchars($base) . '/" onclick="return confirm(\'This will reset the current session and your saved picks — are you sure you want to start a new session?\');" style="color:#f59e0b;">Start a new session</a></p>
      </div>
    </body></html>
    <script>
    (function(){
        const session = "' . htmlspecialchars($sessionId) . '";
        const statusUrl = "' . htmlspecialchars($base) . '/m/" + session + "/status";
        const pollInterval = 3000;
        let timer;
        function poll() {
            fetch(statusUrl, { method: "GET", cache: "no-store" })
            .then(r => r.ok ? r.json() : Promise.reject("status " + r.status))
            .then(data => {
                if (data && data.bothDone) {
                    window.location.replace("' . htmlspecialchars($base) . '/m/" + session + "/match");
                }
            }).catch(e => { console.error("status check failed", e); });
        }
        timer = setInterval(poll, pollInterval);
        poll();
        window.addEventListener("beforeunload", () => clearInterval(timer));
    })();
    </script>';
    exit;
}

$aMovies = json_decode($aMoviesJson, true);
$bMovies = json_decode($bMoviesJson, true);

// Validate JSON decode
if (!is_array($aMovies) || !is_array($bMovies)) {
    die('Invalid movie data. Please try creating a new session.');
}

if (empty($aMovies) || empty($bMovies)) {
    die("Missing movie choices. Please go back and try again.");
}

// Convert to integers and validate
$aMovies = array_map('intval', array_filter($aMovies, 'is_numeric'));
$bMovies = array_map('intval', array_filter($bMovies, 'is_numeric'));

if (empty($aMovies) || empty($bMovies)) {
    die("Invalid movie IDs. Please try again.");
}

// ------------ Load all movie details ------------
$personA = [];
$personB = [];

$allIds = array_unique(array_merge($aMovies, $bMovies));
$allMovies = tmdb_get_movies_parallel($allIds);

foreach ($aMovies as $id) {
    if (isset($allMovies[$id])) $personA[] = $allMovies[$id];
}

foreach ($bMovies as $id) {
    if (isset($allMovies[$id])) $personB[] = $allMovies[$id];
}

if (empty($personA) || empty($personB)) {
    die('Could not load movie details from TMDb. Please try again later.');
}

// Build an index by movie ID
$movieIndex = [];
foreach ($personA as $m) {
    $movieIndex[$m['id']] = $m;
}
foreach ($personB as $m) {
    if (!isset($movieIndex[$m['id']])) {
        $movieIndex[$m['id']] = $m;
    }
}

// ------------ Taste profile helpers (genres + actors + directors) ------------
function build_profile(array $movies): array {
    $genres    = []; // 'Comedy' => weight
    $actors    = []; // actorId  => weight
    $directors = []; // directorId => weight

    foreach ($movies as $movie) {
        $weight = 1.0;

        // Genres
        $gNames = array_column($movie['genres'] ?? [], 'name');
        foreach ($gNames as $g) {
            $genres[$g] = ($genres[$g] ?? 0) + $weight;
        }

        // Actors
        foreach ($movie['actors'] ?? [] as $actor) {
            $id = $actor['id'];
            $actors[$id] = ($actors[$id] ?? 0) + $weight;
        }

        // Directors
        foreach ($movie['directors'] ?? [] as $dir) {
            $id = $dir['id'];
            $directors[$id] = ($directors[$id] ?? 0) + $weight;
        }
    }

    return [
        'genres'    => $genres,
        'actors'    => $actors,
        'directors' => $directors,
    ];
}

/**
 * Simple overlap score based on shared keys.
 * Returns a value in [0,1].
 */
function overlap_score(array $a, array $b, int $maxNormalize): float {
    $keysA = array_keys($a);
    $keysB = array_keys($b);
    $shared = array_intersect($keysA, $keysB);
    $count  = count($shared);
    if ($maxNormalize <= 0) return 0.0;
    return min(1.0, $count / $maxNormalize);
}

/**
 * Score a candidate recommendation movie given both users' profiles.
 */
function score_candidate(array $movie, array $profileA, array $profileB, float $baseCount): float {
    $gNames      = array_column($movie['genres'] ?? [], 'name');
    $actorIds    = array_column($movie['actors'] ?? [], 'id');
    $directorIds = array_column($movie['directors'] ?? [], 'id');
    $rating      = $movie['vote_average'] ?? 0;

    $gScoreA = 0; $gScoreB = 0;
    foreach ($gNames as $g) {
        $gScoreA += $profileA['genres'][$g] ?? 0;
        $gScoreB += $profileB['genres'][$g] ?? 0;
    }

    $aScoreA = 0; $aScoreB = 0;
    foreach ($actorIds as $id) {
        $aScoreA += $profileA['actors'][$id] ?? 0;
        $aScoreB += $profileB['actors'][$id] ?? 0;
    }

    $dScoreA = 0; $dScoreB = 0;
    foreach ($directorIds as $id) {
        $dScoreA += $profileA['directors'][$id] ?? 0;
        $dScoreB += $profileB['directors'][$id] ?? 0;
    }

    // Combine A/B preference
    $genreMatch    = $gScoreA + $gScoreB;
    $actorMatch    = $aScoreA + $aScoreB;
    $directorMatch = $dScoreA + $dScoreB;

    // Normalize rating 0–10 → 0–1
    $ratingBoost = $rating / 10.0;

    // Final score (you can tune these weights)
    return
        $baseCount * 2.0 +      // appears in multiple rec lists
        $genreMatch * 0.7 +
        $actorMatch * 1.0 +
        $directorMatch * 1.2 +
        $ratingBoost * 1.0;
}

// Build taste profiles for both users
$profileA = build_profile($personA);
$profileB = build_profile($personB);

// ------------ Compatibility (main match) ------------

// Which movies did you both pick?
$shared      = array_intersect($aMovies, $bMovies);
$sharedCount = count($shared);

// For ratio: how many movies did the "pickier" person choose?
$maxPicks    = max(count($aMovies), count($bMovies));
$sharedMoviesRatio = $maxPicks > 0 ? $sharedCount / $maxPicks : 0.0;   // 0–1

// Pick a "featured" movie for the big card (same logic as before)
$recommendedMovie = null;
if ($sharedCount > 0) {
    $sharedId = array_values($shared)[0]; // first shared
    $recommendedMovie = $movieIndex[$sharedId] ?? tmdb_get_movie($sharedId);
} else {
    $genreMatches = [];

    foreach ($personA as $a) {
        foreach ($personB as $b) {
            $aGenres = array_column($a['genres'] ?? [], 'name');
            $bGenres = array_column($b['genres'] ?? [], 'name');

            $overlap = array_intersect($aGenres, $bGenres);
            $score   = count($overlap);

            if ($score > 0) {
                $genreMatches[] = [
                    'score'  => $score,
                    'movieA' => $a,
                    'movieB' => $b,
                ];
            }
        }
    }

    if (!empty($genreMatches)) {
        usort($genreMatches, fn($x, $y) => $y['score'] - $x['score']);
        $recommendedMovie = $genreMatches[0]['movieA'];
    } else {
        $recommendedMovie = $personA[0];
    }
}

/**
 * Helper: compute overlap of keys, normalized by a cap
 * (we’ll reuse your existing overlap_score but with dynamic caps)
 */
function profile_overlap(array $profileA, array $profileB, int $cap): float {
    $keysA = array_keys($profileA);
    $keysB = array_keys($profileB);
    $shared = array_intersect($keysA, $keysB);
    $count  = count($shared);
    if ($cap <= 0) return 0.0;
    return min(1.0, $count / $cap);
}

// Dynamically cap how many “shared things” can fully saturate the score
$genreCap    = max(1, min(8,  max(count($profileA['genres']),    count($profileB['genres']))));
$actorCap    = max(1, min(10, max(count($profileA['actors']),    count($profileB['actors']))));
$directorCap = max(1, min(4,  max(count($profileA['directors']), count($profileB['directors']))));

// Overlaps (0–1)
$genreOverlap    = profile_overlap($profileA['genres'],    $profileB['genres'],    $genreCap);
$actorOverlap    = profile_overlap($profileA['actors'],    $profileB['actors'],    $actorCap);
$directorOverlap = profile_overlap($profileA['directors'], $profileB['directors'], $directorCap);

// Final compatibility:
//  - 20% base
//  - up to 45% from same movies (but proportional to how many you share)
//  - up to 20% from genres
//  - up to 10% from actors
//  - up to 5% from directors
$compatPercent =
      20
    + 45 * $sharedMoviesRatio
    + 20 * $genreOverlap
    + 10 * $actorOverlap
    +  5 * $directorOverlap;

$compatPercent = (int) round(max(0, min(100, $compatPercent)));



// ------------ Top "fresh" recommendations ------------
$allPickedIds = array_unique(array_merge($aMovies, $bMovies));
$seedA = array_slice($aMovies, 0, 4);
$seedB = array_slice($bMovies, 0, 4);
$seedIds = array_unique(array_merge($seedA, $seedB));

$recommendationPool = []; // id => ['movie' => ..., 'count' => float]

$allRecs = tmdb_get_recommendations_parallel($seedIds, 8);
foreach ($allRecs as $seedId => $recs) {
    foreach ($recs as $rec) {
        $id = $rec['id'];

        // Skip movies they already picked
        if (in_array($id, $allPickedIds, true)) {
            continue;
        }

        if (!isset($recommendationPool[$id])) {
            $recommendationPool[$id] = [
                'movie' => $rec,
                'count' => 1.0,
            ];
        } else {
            // If this movie comes from multiple seeds, stack its base count
            $recommendationPool[$id]['count'] += 1.0;
        }
    }
}

$topNewRecommendations = [];

if (!empty($recommendationPool)) {
    $scored = [];

    foreach ($recommendationPool as $entry) {
        $movie = $entry['movie'];
        $base  = $entry['count'];

        $score = score_candidate($movie, $profileA, $profileB, $base);
        $scored[] = [
            'movie' => $movie,
            'score' => $score,
        ];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    // Get more than 4 so we can swap when user clicks "Seen it / Skip"
    $topNewRecommendations = array_map(
        fn($x) => $x['movie'],
        array_slice($scored, 0, 12) // first 12 candidates
    );
}

// Split into first 4 visible + the rest used as "queue" for skipping
$initialRecs = array_slice($topNewRecommendations, 0, 4);
$extraRecs   = array_slice($topNewRecommendations, 4);

// If we are LOW on recommendations, fetch popular movies as fallback
if (count($initialRecs) < 4) {
    $fallback = tmdb_get_popular(1);
    foreach ($fallback as $f) {
        if (!in_array($f['id'], $allPickedIds)) {
            if (count($initialRecs) < 4) {
                $initialRecs[] = $f;
            } else {
                $extraRecs[] = $f;
            }
        }
    }
}

// Prepare a slim version for JS
$extraRecsForJs = array_map(function($m) {
    return [
        'title'    => $m['title'] ?? 'Untitled',
        'poster'   => $m['poster'] ?? '',
        'overview' => $m['overview'] ?? '',
    ];
}, $extraRecs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Movie Match</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/assets/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/assets/match.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="glass-card" style="max-width: 1400px; width: 100%;">

    <h1>Your Compatibility ❤️</h1>
    <div class="percent"><?php echo $compatPercent; ?>%</div>

    <div class="section-title">You Both Picked</div>
    <?php if ($sharedCount > 0): ?>
        <p style="text-align:center;">You both chose the same movie! Perfect match 😳💘</p>
    <?php else: ?>
        <p style="text-align:center;">No identical picks, but we found the closest match.</p>
    <?php endif; ?>

    <div class="match-box">
        <img src="<?php echo htmlspecialchars($recommendedMovie['poster']); ?>" alt="Poster">
        <div>
            <h2><?php echo htmlspecialchars($recommendedMovie['title']); ?></h2>
            <p><?php echo htmlspecialchars($recommendedMovie['overview']); ?></p>
        </div>
    </div>

    <?php if (!empty($initialRecs)): ?>
        <div class="section-title">New Movie Recommendations (For Both of You) 🍿</div>
        <p class="subtext">
            Based on your top picks, here are movies we think you’ll both enjoy — all of these are <strong>outside</strong> your current choices.<br>
            If you’ve already watched one, click <strong>“Seen it / Skip”</strong> to swap it for a new suggestion.
        </p>
        <div class="grid" id="recommendationGrid">
            <?php foreach ($initialRecs as $idx => $m): ?>
                <div class="movie-card" id="rec-card-<?php echo $idx; ?>">
                    <div class="poster-wrapper">
                        <img src="<?php echo htmlspecialchars($m['poster']); ?>" alt="Poster" class="rec-poster">
                    </div>
                    <div class="movie-title" style="margin-top:10px; font-weight:700;"><?php echo htmlspecialchars($m['title']); ?></div>
                    <div class="movie-overview" style="margin-top:6px; font-size:0.8rem; color:#9ca3af; max-height:4.2em; overflow:hidden;">
                        <?php echo htmlspecialchars($m['overview'] ?? ''); ?>
                    </div>

                    <div class="pill-actions" style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px; overflow: visible;">
                        <button type="button" class="action-pill btn-skip" onclick="handleAction(this, 'skip')">
                            <span class="pill-icon">✕</span>
                            <span>Skip</span>
                        </button>
                        <button type="button" class="action-pill btn-seen" onclick="handleAction(this, 'seen')">
                            <span class="pill-icon">✓</span>
                            <span>Seen It</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Empty State Container (Hidden by default) -->
        <div id="emptyState" style="display: none; text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1); margin: 20px 0;">
            <div style="font-size: 3rem; margin-bottom: 20px;">🎬</div>
            <h3 style="color: #fff; margin-bottom: 10px;">No recommendations available right now</h3>
            <p style="color: #9ca3af; font-size: 0.9rem; margin-bottom: 25px;">Try updating your preferences or generating again.</p>
            <a href="<?php echo htmlspecialchars($base); ?>/" class="action-pill" style="display: inline-flex; width: auto; padding: 10px 25px; background: var(--primary-red); text-decoration: none;">Refresh Recommendations</a>
        </div>
<?php else: ?>
    <!-- Initial Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <h3 style="color: #fff;">No recommendations available right now</h3>
        <p style="color: #9ca3af;">Try updating your preferences or generating again.</p>
        <a href="<?php echo htmlspecialchars($base); ?>/" class="cinematic-btn" style="display: inline-block; margin-top: 20px;">Generate Again</a>
    </div>
<?php endif; ?>


    <div class="section-title">Person A picked</div>
    <div class="grid">
        <?php foreach ($personA as $m): ?>
            <div class="movie-card">
                <div class="poster-wrapper">
                    <img src="<?php echo htmlspecialchars($m['poster']); ?>" alt="Poster">
                </div>
                <div><?php echo htmlspecialchars($m['title']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">Person B picked</div>
    <div class="grid">
        <?php foreach ($personB as $m): ?>
            <div class="movie-card">
                <div class="poster-wrapper">
                    <img src="<?php echo htmlspecialchars($m['poster']); ?>" alt="Poster">
                </div>
                <div><?php echo htmlspecialchars($m['title']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- NEW: Your Seen History Section -->
    <div id="watchedSection" style="display: none; margin-top: 50px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
        <div class="section-title">Your Seen History</div>
        
        <!-- Guest CTA -->
        <div class="guest-cta" style="background: rgba(229, 9, 20, 0.1); border: 1px solid var(--primary-red); padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div style="font-size: 0.9rem; color: #fff;">
                <strong>Want to save these permanently?</strong><br>
                Create an account to sync your history across all your devices.
            </div>
            <button class="action-pill" style="width: auto; padding: 8px 16px; font-size: 0.8rem; background: var(--primary-red);">Sign Up</button>
        </div>

        <div class="grid" id="watchedGrid">
            <!-- Dynamically populated via JS -->
        </div>
    </div>


    <a href="<?php echo htmlspecialchars($base); ?>/" onclick="return confirm('This will reset the current session and your saved picks — are you sure you want to try again?');" class="cinematic-btn" style="margin-top: 60px; text-align: center;">Find More Movies</a>

</div>

<script>
// Extra recommendations queue (beyond the first 4)
const extraRecommendations = <?php echo json_encode($extraRecsForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let extraIndex = 0;

// Handle both Dynamic Pill and Dual Action buttons
function handleAction(btn, type) {
    const card = btn.closest('.movie-card');
    if (!card) return;
    const movieData = {
        title: card.querySelector('.movie-title').textContent,
        poster: card.querySelector('.rec-poster').src
    };
    const btns = card.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);
    card.style.transition = 'all 0.3s ease-out';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-10px)';
    setTimeout(() => {
        if (type === 'seen') { addToWatchedList(movieData); showSeenItFeedback(); }
        if (extraIndex < extraRecommendations.length) {
            const next = extraRecommendations[extraIndex++];
            card.querySelector('.rec-poster').src = next.poster;
            card.querySelector('.movie-title').textContent = next.title;
            card.querySelector('.movie-overview').textContent = next.overview;
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
                btns.forEach(b => b.disabled = false);
            }, 50);
        } else { 
            card.remove(); 
            // Check if grid is empty
            const remaining = grid.querySelectorAll('.movie-card');
            if (remaining.length === 0) {
                document.getElementById('emptyState').style.display = 'block';
            }
        }

    }, 300);
}


// Interaction Handler
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-skip, .btn-seen');
    if (btn) handleAction(btn, btn.classList.contains('btn-skip') ? 'skip' : 'seen');
});


const watchedIds = new Set();

function addToWatchedList(movie) {
    const section = document.getElementById('watchedSection');
    const grid = document.getElementById('watchedGrid');
    if (!section || !grid) return;

    // Prevent Duplicates
    if (watchedIds.has(movie.title)) return;
    watchedIds.add(movie.title);

    section.style.display = 'block';
    const newCard = document.createElement('div');
    newCard.className = 'movie-card';
    newCard.innerHTML = `
        <div class="poster-wrapper"><img src="${movie.poster}"></div>
        <div style="font-size:0.9rem; font-weight:700; color:white; margin-top:10px;">${movie.title}</div>
        <div style="font-size:0.75rem; color:#9ca3af; margin-top:2px;">Seen Today</div>
    `;
    grid.appendChild(newCard);
}





// Optional: Show feedback when "Seen It" is clicked
function showSeenItFeedback() {
    const feedback = document.createElement('div');
    feedback.style.cssText = "position:fixed; top:20px; right:20px; background:linear-gradient(135deg, #e50914 0%, #ff4b4b 100%); color:white; padding:12px 16px; border-radius:8px; z-index:1000; font-weight:600; box-shadow:0 4px 12px rgba(229, 9, 20, 0.4); animation: slideIn 0.3s ease-out;";
    feedback.textContent = '✓ Marked as seen!';
    document.body.appendChild(feedback);
    setTimeout(() => {
        feedback.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => { feedback.remove(); }, 300);
    }, 2000);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
`;
document.head.appendChild(style);
</script>



</body>
</html>

<script>
// Prevent back button from returning users to the picker page - redirect to home on popstate
(function () {
    try {
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
            var go = confirm('If you go back, you will be leaving the results page and the session will be reset. Continue?');
            if (go) {
                window.location.replace('/');
            } else {
                try { history.pushState(null, null, window.location.href); } catch (e) {}
            }
        });
    } catch (e) {
        // ignore
    }
})();
</script>
