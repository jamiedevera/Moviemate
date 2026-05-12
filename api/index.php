<?php
// index.php — Step 0: create session + pretty links

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Fetch 2026 best movies backdrops for the slideshow
$backdrops = [];
$tmdbUrl = 'https://api.themoviedb.org/3/discover/movie?api_key=' . TMDB_API_KEY . '&primary_release_year=2026&sort_by=popularity.desc';
$response = @file_get_contents($tmdbUrl);
if ($response !== false) {
    $json = json_decode($response, true);
    if (!empty($json['results'])) {
        foreach (array_slice($json['results'], 0, 10) as $movie) {
            if (!empty($movie['backdrop_path'])) {
                $backdrops[] = 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'];
            }
        }
    }
}
// Fallback if API fails
if (empty($backdrops)) {
    $backdrops = ['https://image.tmdb.org/t/p/original/mRGmNnh6pBAGGp6fMBMwI8iTBUO.jpg'];
}

// Generate random session id
$sessionId = bin2hex(random_bytes(8)); // 16 hex chars

// Save empty session row
$stmt = $pdo->prepare('INSERT INTO sessions (id) VALUES (:id)');
$stmt->execute(['id' => $sessionId]);

// Build pretty URLs
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];

$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '/' || $base === '\\') {
    $base = '';
}

$inviteLink   = "{$scheme}://{$host}{$base}/m/{$sessionId}";      // for B
$chooseLinkA  = "{$scheme}://{$host}{$base}/m/{$sessionId}/a";    // A picks here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Your Movie Date Link</title>
    <link rel="stylesheet" href="/assets/global.css?v=3">
</head>
<body>

<!-- Slideshow Background -->
<div id="slideshow" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; background-color:#000;">
    <?php foreach ($backdrops as $idx => $bg): ?>
        <div class="slide" style="position:absolute; top:0; left:0; width:100%; height:100%; background-image:url('<?php echo htmlspecialchars($bg); ?>'); background-size:cover; background-position:center; opacity:<?php echo $idx === 0 ? '0.3' : '0'; ?>; transition:opacity 2s ease-in-out;"></div>
    <?php endforeach; ?>
</div>

<div class="glass-card">
    <h1>Movie Date Link <span>💌</span></h1>

    <p>1. Copy this link and send it to your moviemate:</p>
    <input id="shareLink" class="input-box" type="text" readonly
           value="<?php echo htmlspecialchars($inviteLink); ?>">
    <button class="cinematic-btn" onclick="copyLink()">Copy Link</button>

    <p style="margin-top:16px;">2. After sending the link, start choosing <strong>your</strong> movies:</p>
    <a class="cinematic-btn" style="margin-top:0;" href="<?php echo htmlspecialchars($chooseLinkA); ?>">Pick my movies 🎬</a>

    <small>They’ll open the invite link and later choose as Person B.</small>
</div>

<script>
function copyLink() {
    const input = document.getElementById('shareLink');
    input.select();
    document.execCommand('copy');
    alert('Link copied! Send it to your moviemate 💛');
}

// Slideshow Logic
document.addEventListener("DOMContentLoaded", () => {
    const slides = document.querySelectorAll('.slide');
    if (slides.length <= 1) return;
    
    let currentIdx = 0;
    setInterval(() => {
        slides[currentIdx].style.opacity = '0';
        currentIdx = (currentIdx + 1) % slides.length;
        slides[currentIdx].style.opacity = '0.3';
    }, 5000); // Change image every 5 seconds
});
</script>

</body>
</html>
