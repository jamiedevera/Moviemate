<?php
// index.php — Step 0: create session + pretty links

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Fetch 2026 best movies backdrops for the slideshow
$backdrops = [];
$tmdbUrl = 'https://api.themoviedb.org/3/discover/movie?api_key=' . TMDB_API_KEY . '&primary_release_year=2026&sort_by=popularity.desc';
$response = http_get_contents($tmdbUrl);
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

// Demo/Mock session links for onboarding preview (does not write to DB)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
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
$mockSessionId = 'demo-session';
$inviteLink   = "{$scheme}://{$host}{$base}/m/{$mockSessionId}";  // Demo shareable link
$chooseLinkA  = "{$scheme}://{$host}{$base}/start-session.php";   // Triggers actual session creation on click
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Your Movie Date Link</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/assets/global.css?v=<?php echo time(); ?>">
    <script>
        if (localStorage.getItem('moviemate_visited')) {
            document.documentElement.classList.add('returning-user');
        }
    </script>
</head>
<body class="home-page">

<!-- Top Navigation Bar -->
<div class="top-nav">
    <div class="nav-brand">🎬 Moviemate</div>
    <div class="nav-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="user-greeting">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <a href="<?php echo htmlspecialchars($base); ?>/logout" class="nav-btn outline-btn">Log Out</a>
        <?php else: ?>
            <button class="nav-btn outline-btn" onclick="openAuthModal('login')">Log In</button>
            <button class="nav-btn" onclick="openAuthModal('signup')">Sign Up</button>
        <?php endif; ?>
    </div>
</div>

<!-- Slideshow Background -->
<div id="slideshow" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-2; background-color:#000;">
    <?php foreach ($backdrops as $idx => $bg): ?>
        <div class="slide" style="position:absolute; top:0; left:0; width:100%; height:100%; background-image:url('<?php echo htmlspecialchars($bg); ?>'); background-size:cover; background-position:center; opacity:<?php echo $idx === 0 ? '0.3' : '0'; ?>; transition:opacity 2s ease-in-out;"></div>
    <?php endforeach; ?>
</div>
<div id="slideshow-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; background: radial-gradient(circle, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.85) 75%, rgba(0,0,0,0.98) 100%); pointer-events: none;"></div>

<!-- Auth Modal (Hidden by default) -->
<div id="authModal" class="modal-overlay" style="display:none;">
    <div class="glass-card modal-content" style="max-width: 400px; padding: 40px 32px;">
        <button class="close-btn" onclick="closeAuthModal()">✕</button>
        <h2 id="authTitle" style="margin-bottom: 30px; font-weight: 800;">Log In</h2>
        
        <form id="authForm" onsubmit="submitAuth(event)">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" id="authAction" name="action" value="login">
            
            <div class="underline-input-group" id="usernameGroup" style="display:none;">
                <label>Username</label>
                <input type="text" name="username" class="underline-input" placeholder="Your username">
            </div>
            
            <div class="underline-input-group">
                <label>Email</label>
                <input type="email" name="email" class="underline-input" required placeholder="name@example.com">
            </div>
            
            <div class="underline-input-group">
                <label>Password</label>
                <input type="password" name="password" class="underline-input" required placeholder="••••••••">
            </div>
            
            <div id="authError" style="color:var(--primary-red); margin-bottom: 20px; display:none; font-size: 0.9rem;"></div>
            
            <button type="submit" class="cinematic-btn" id="authSubmitBtn" style="margin-top: 10px;">Log In <span class="btn-icon">➔</span></button>
            
            <p style="margin-top: 20px; font-size: 0.9rem;">
                <span id="authToggleText">Don't have an account?</span> 
                <a href="#" onclick="toggleAuthMode(); return false;" style="color:var(--primary-red); font-weight: 700; text-decoration: none;">
                    <span id="authToggleLink">Sign Up</span>
                </a>
            </p>
        </form>
    </div>
</div>

<div class="home-page-container centered-layout">
    <div class="landing-wrapper">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="category-badge">🍿 Cinematic Matchmaking</div>
            <h1 class="landing-title">YOUR TRUSTED<br><span>MOVIE MATCHMAKER</span></h1>
            <p class="landing-subtitle">Finding the perfect movie shouldn't be a chore. Invite your partner, select your favorite picks, and match instantly. No more endless scrolling.</p>
        </div>
        
        <!-- Central Glassmorphic Console Card -->
        <div class="console-section">
            <div class="glass-card" style="position: relative; overflow: hidden; max-width: 480px; width: 100%; padding: 40px 32px;">
                <!-- Returning User View (visible if .returning-user is set) -->
                <div class="returning-only">
                    <div class="step-card active" style="margin-bottom:0; text-align:center;">
                        <span class="step-icon">🍿</span>
                        <h2>Welcome Back!</h2>
                        <p>Ready to start another movie matchmaking session with your partner?</p>
                        <a class="cinematic-btn" href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()">Start Real Session <span class="btn-icon">➔</span></a>
                        <button class="cinematic-btn secondary-btn" style="margin-top: 12px;" onclick="showOnboardingTutorial()">How it works (Tutorial)</button>
                    </div>
                </div>

                <!-- Onboarding Only View (hidden if .returning-user is set) -->
                <div class="onboarding-only">
                    <!-- Step Tracker Progress Bar -->
                    <div class="steps-header">
                        <div class="steps-line">
                            <div class="steps-progress" id="stepsProgress"></div>
                        </div>
                        <div class="step-tab active" id="tab-1">
                            <div class="step-num">01</div>
                            <div class="step-label">Setup</div>
                        </div>
                        <div class="step-tab" id="tab-2">
                            <div class="step-num">02</div>
                            <div class="step-label">Share</div>
                        </div>
                        <div class="step-tab" id="tab-3">
                            <div class="step-num">03</div>
                            <div class="step-label">Choose</div>
                        </div>
                    </div>

                    <!-- STEP 1: Get Started -->
                    <div class="step-card active" id="step-1">
                        <span class="step-icon">🍿</span>
                        <h2>Ready for Movie Night?</h2>
                        <p>Create a movie matchmaking session. You and your partner will choose 5 movies each, and we'll instantly find your perfect match!</p>
                        <button class="cinematic-btn" onclick="goToStep(2)">Create Demo Session <span class="btn-icon">➔</span></button>
                    </div>

                    <!-- STEP 2: Share Link -->
                    <div class="step-card" id="step-2">
                        <span class="step-icon">🔗</span>
                        <h2>Invite Your Moviemate</h2>
                        <p>In a real session, we will generate a unique shareable link for your partner. Try out the copy button below in this demo:</p>
                        
                        <div class="share-container">
                            <input id="shareLink" class="input-box" type="text" readonly
                                   value="<?php echo htmlspecialchars($inviteLink); ?>" style="border:none; background:transparent; padding:0; margin:0; box-shadow:none;">
                            <button class="copy-icon-btn" onclick="copyLink()">Copy Link</button>
                        </div>
                        
                        <button class="cinematic-btn" onclick="goToStep(3)">Link Sent! Next Step <span class="btn-icon">➔</span></button>
                        <button class="cinematic-btn secondary-btn" onclick="goToStep(1)">Back</button>
                    </div>

                    <!-- STEP 3: Choose Movies -->
                    <div class="step-card" id="step-3">
                        <span class="step-icon">🎬</span>
                        <h2>Start Your Selection</h2>
                        <p>Ready to try it for real? Click the button below to start a live matchmaking session and pick your 5 movies!</p>
                        <a class="cinematic-btn" style="margin-top:0;" href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()">Start Real Session <span class="btn-icon">➔</span></a>
                        <button class="cinematic-btn secondary-btn" onclick="goToStep(2)">Back</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Genre Pills Grid -->
        <div class="genre-pills-section">
            <div class="genre-pills-list">
                <div class="genre-pill">
                    <span class="genre-icon">🍿</span>
                    <span>Action</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">💖</span>
                    <span>Romance</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">👻</span>
                    <span>Horror</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">🎭</span>
                    <span>Drama</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">👽</span>
                    <span>Sci-Fi</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">😂</span>
                    <span>Comedy</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">🕵️</span>
                    <span>Mystery</span>
                </div>
                <div class="genre-pill">
                    <span class="genre-icon">🦄</span>
                    <span>Fantasy</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Section matching Pinterest UI -->
    <footer class="landing-footer">
        <div class="footer-top">
            <div class="footer-left">
                <div class="footer-brand">🎬 Moviemate</div>
                <p>A cinematic matchmaking experience designed to make your movie selection instant, fun, and secure.</p>
            </div>
            <div class="footer-mid">
                <div class="footer-col">
                    <h4>About</h4>
                    <a href="#">Our Vision</a>
                    <a href="#">Security</a>
                    <a href="#">FAQ</a>
                </div>
                <div class="footer-col">
                    <h4>Platform</h4>
                    <a href="#">TMDb Api</a>
                    <a href="#">Privacy</a>
                    <a href="#">GitHub</a>
                </div>
            </div>
            <div class="footer-right">
                <a href="mailto:hello@moviemate.app" class="footer-contact-link">hello@moviemate.app <span>➔</span></a>
                <a href="#" class="footer-contact-link">Telegram <span>➘</span> Instagram <span>➔</span></a>
                <a href="tel:+18006686283" class="footer-contact-link">+1 (800) MOV-MATE <span>➔</span></a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>Location: Manila, PH</span>
            <span>&copy; <?php echo date('Y'); ?> Moviemate. All rights reserved.</span>
        </div>
    </footer>
</div>

<script>
function markVisited() {
    localStorage.setItem('moviemate_visited', 'true');
}

function showOnboardingTutorial() {
    document.documentElement.classList.remove('returning-user');
    goToStep(1);
}

function goToStep(step) {
    // 1. Switch active cards
    document.querySelectorAll('.step-card').forEach(card => {
        card.classList.remove('active');
    });
    document.getElementById(`step-${step}`).classList.add('active');

    // 2. Update tabs styling
    for (let i = 1; i <= 3; i++) {
        const tab = document.getElementById(`tab-${i}`);
        if (i < step) {
            tab.classList.remove('active');
            tab.classList.add('completed');
        } else if (i === step) {
            tab.classList.remove('completed');
            tab.classList.add('active');
        } else {
            tab.classList.remove('active', 'completed');
        }
    }

    // 3. Update Progress Bar
    const progressWidth = ((step - 1) / 2) * 100;
    document.getElementById('stepsProgress').style.width = `${progressWidth}%`;
}

function copyLink() {
    const input = document.getElementById('shareLink');
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.querySelector('.copy-icon-btn');
        const originalText = btn.innerText;
        btn.innerText = 'Copied! ✓';
        btn.style.background = '#22c55e';
        btn.style.borderColor = '#22c55e';
        btn.style.boxShadow = '0 0 10px rgba(34, 197, 94, 0.5)';
        setTimeout(() => {
            btn.innerText = originalText;
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.boxShadow = '';
        }, 2000);
    }).catch(err => {
        document.execCommand('copy');
        alert('Link copied! Send it to your moviemate 💛');
    });
}

// Auth Modal Logic
function openAuthModal(mode) {
    document.getElementById('authModal').style.display = 'flex';
    setAuthMode(mode);
}

function closeAuthModal() {
    document.getElementById('authModal').style.display = 'none';
}

function toggleAuthMode() {
    const currentMode = document.getElementById('authAction').value;
    setAuthMode(currentMode === 'login' ? 'signup' : 'login');
}

function setAuthMode(mode) {
    const isLogin = mode === 'login';
    document.getElementById('authAction').value = mode;
    document.getElementById('authTitle').innerText = isLogin ? 'Log In' : 'Create Account';
    document.getElementById('authSubmitBtn').innerText = isLogin ? 'Log In' : 'Sign Up';
    document.getElementById('authToggleText').innerText = isLogin ? "Don't have an account?" : "Already have an account?";
    document.getElementById('authToggleLink').innerText = isLogin ? "Sign Up" : "Log In";
    document.getElementById('usernameGroup').style.display = isLogin ? 'none' : 'block';
    if(!isLogin) {
        document.querySelector('input[name="username"]').setAttribute('required', 'required');
    } else {
        document.querySelector('input[name="username"]').removeAttribute('required');
    }
    document.getElementById('authError').style.display = 'none';
}

async function submitAuth(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const btn = document.getElementById('authSubmitBtn');
    
    btn.disabled = true;
    btn.innerText = 'Please wait...';
    
    try {
        const res = await fetch('/api/auth', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            document.getElementById('authError').innerText = data.error || 'An error occurred';
            document.getElementById('authError').style.display = 'block';
            btn.disabled = false;
            btn.innerText = document.getElementById('authAction').value === 'login' ? 'Log In' : 'Sign Up';
        }
    } catch (err) {
        document.getElementById('authError').innerText = 'Network error. Try again.';
        document.getElementById('authError').style.display = 'block';
        btn.disabled = false;
        btn.innerText = document.getElementById('authAction').value === 'login' ? 'Log In' : 'Sign Up';
    }
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
