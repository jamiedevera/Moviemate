<?php
// index.php — Step 0: create session + pretty links

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$backdrops = [
    'https://image.tmdb.org/t/p/original/mRGmNnh6pBAGGp6fMBMwI8iTBUO.jpg',
    'https://image.tmdb.org/t/p/original/36551C7464Vm1msoZ5K2nahwG49.jpg',
    'https://image.tmdb.org/t/p/original/xl1w94548gZXIvXtZah65EVz76q.jpg',
    'https://image.tmdb.org/t/p/original/5gKK2j31V6il6okrQ47GgS6J24r.jpg',
    'https://image.tmdb.org/t/p/original/zfb52MI415Xe2328lhIEvNc2Vky.jpg'
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = '';
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot      = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $dir          = str_replace('\\', '/', __DIR__);
    $docRootLower = strtolower($docRoot);
    $dirLower     = strtolower($dir);
    if (strpos($dirLower, $docRootLower) === 0) {
        $base = substr($dir, strlen($docRoot));
    }
}
$base          = rtrim($base, '/\\');
$mockSessionId = 'demo-session';
$inviteLink    = "{$scheme}://{$host}{$base}/m/{$mockSessionId}";
$chooseLinkA   = "{$scheme}://{$host}{$base}/start-session.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moviemate — Cinematic Matchmaking</title>
    <link rel="stylesheet" href="/assets/global.css">
    <link rel="stylesheet" href="/assets/index.css">
    <script>
        if (localStorage.getItem('moviemate_visited')) {
            document.documentElement.classList.add('returning-user');
        }
    </script>
</head>
<body class="home-page">

<!-- ── TOP NAV ── -->
<nav class="top-nav">
    <div class="nav-brand">
        <span class="nav-brand-dot"></span>
        Moviemate
    </div>
    <div class="nav-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="user-greeting">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <a href="<?php echo htmlspecialchars($base); ?>/logout" class="nav-btn outline-btn">Log Out</a>
        <?php else: ?>
            <button class="nav-btn outline-btn" onclick="openAuthModal('login')">Log In</button>
            <button class="nav-btn" onclick="openAuthModal('signup')">Sign Up</button>
        <?php endif; ?>
    </div>
</nav>

<!-- ── AUTH MODAL ── -->
<div id="authModal" class="modal-overlay" style="display:none;">
    <div class="glass-card modal-content" style="max-width:400px; padding:40px 32px;">
        <button class="close-btn" onclick="closeAuthModal()">✕</button>
        <h2 id="authTitle" style="margin-bottom:28px;">Log In</h2>
        <form id="authForm" onsubmit="submitAuth(event)">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
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
            <div id="authError" style="color:var(--red); margin-bottom:18px; display:none; font-size:0.9rem;"></div>
            <button type="submit" class="cinematic-btn" id="authSubmitBtn" style="margin-top:8px;">
                Log In <span class="btn-icon">➔</span>
            </button>
            <p style="margin-top:18px; font-size:0.88rem;">
                <span id="authToggleText">Don't have an account?</span>
                <a href="#" onclick="toggleAuthMode(); return false;" style="color:var(--red); font-weight:700; text-decoration:none;">
                    <span id="authToggleLink">Sign Up</span>
                </a>
            </p>
        </form>
    </div>
</div>

<!-- ── HERO ── -->
<section class="ci-hero">
    <div class="ci-hero__slides">
        <?php foreach ($backdrops as $idx => $bg): ?>
            <div class="ci-hero__slide <?php echo $idx === 0 ? 'is-active' : ''; ?>"
                 style="background-image:url('<?php echo htmlspecialchars($bg); ?>')"></div>
        <?php endforeach; ?>
        <div class="ci-hero__overlay"></div>
    </div>
    <div class="ci-hero__content">
        <p class="ci-hero__eyebrow">Creative&nbsp;&nbsp;&nbsp;Space</p>
        <h1 class="ci-hero__title">Cinema World</h1>
    </div>
    <div class="ci-hero__fade"></div>
</section>

<!-- ── MARQUEE ── -->
<div class="ci-marquee" aria-hidden="true">
    <div class="ci-marquee__track">
        <?php
        $items = ['WATCH WITH US', 'BE WITH US', 'CREATE WITH US', 'MATCH WITH US', 'ENJOY WITH US'];
        for ($r = 0; $r < 4; $r++):
            foreach ($items as $item):
        ?>
            <span class="ci-marquee__item">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M7 0L8.5 5.5H14L9.5 8.5L11 14L7 11L3 14L4.5 8.5L0 5.5H5.5L7 0Z" fill="currentColor"/>
                </svg>
                <?php echo htmlspecialchars($item); ?>
            </span>
        <?php endforeach; endfor; ?>
    </div>
</div>

<!-- ── INTRO / CTA ── -->
<section class="ci-intro">
    <div class="ci-intro__badge">WE BELONG TO THIS</div>
    <h2 class="ci-intro__heading">
        WE CREATE <em>films</em><br>
        THAT WILL BE WITH YOU<br>
        <strong>FOREVER</strong>
    </h2>
    <p class="ci-intro__body">
        Finding the perfect movie shouldn't be a chore. Moviemate pairs you and your partner through a quick, fun selection process — so you spend less time arguing and more time watching.
    </p>

    <div class="ci-console glass-card">
        <div class="returning-only">
            <div style="text-align:center;">
                <span class="step-icon">🍿</span>
                <h2>Welcome Back!</h2>
                <p>Ready to start another movie matchmaking session?</p>
                <a class="cinematic-btn" href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()">Start Real Session <span class="btn-icon">➔</span></a>
                <button class="cinematic-btn secondary-btn" style="margin-top:12px;" onclick="showOnboardingTutorial()">How it works</button>
            </div>
        </div>
        <div class="onboarding-only">
            <div class="steps-header">
                <div class="steps-line"><div class="steps-progress" id="stepsProgress"></div></div>
                <div class="step-tab active" id="tab-1"><div class="step-num">01</div><div class="step-label">Setup</div></div>
                <div class="step-tab" id="tab-2"><div class="step-num">02</div><div class="step-label">Share</div></div>
                <div class="step-tab" id="tab-3"><div class="step-num">03</div><div class="step-label">Choose</div></div>
            </div>
            <div class="step-card active" id="step-1">
                <span class="step-icon">🍿</span>
                <h2>Ready for Movie Night?</h2>
                <p>Create a session, invite your partner, each pick 5 films — we'll find your perfect match instantly.</p>
                <button class="cinematic-btn" onclick="goToStep(2)">Create Demo Session <span class="btn-icon">➔</span></button>
            </div>
            <div class="step-card" id="step-2">
                <span class="step-icon">🔗</span>
                <h2>Invite Your Moviemate</h2>
                <p>Share this link with your partner so they can join and make their picks.</p>
                <div class="share-container">
                    <input id="shareLink" class="input-box" type="text" readonly value="<?php echo htmlspecialchars($inviteLink); ?>" style="border:none;background:transparent;padding:0;margin:0;box-shadow:none;">
                    <button class="copy-icon-btn" onclick="copyLink()">Copy</button>
                </div>
                <button class="cinematic-btn" onclick="goToStep(3)">Link Sent! Next <span class="btn-icon">➔</span></button>
                <button class="cinematic-btn secondary-btn" onclick="goToStep(1)">Back</button>
            </div>
            <div class="step-card" id="step-3">
                <span class="step-icon">🎬</span>
                <h2>Start Your Selection</h2>
                <p>Both of you are ready. Click below to begin your live matchmaking session!</p>
                <a class="cinematic-btn" href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()">Start Real Session <span class="btn-icon">➔</span></a>
                <button class="cinematic-btn secondary-btn" onclick="goToStep(2)">Back</button>
            </div>
        </div>
    </div>
</section>

<!-- ── BENTO GRID ── -->
<section class="ci-works">
    <h3 class="ci-works__heading">Our Works</h3>
    <div class="ci-works__grid">
        <div class="ci-work-card ci-work-card--tall" style="background-image:url('https://image.tmdb.org/t/p/w500/mRGmNnh6pBAGGp6fMBMwI8iTBUO.jpg')">
            <div class="ci-work-card__overlay"></div>
            <span class="ci-work-card__label">Creative</span>
        </div>
        <div class="ci-work-card" style="background-image:url('https://image.tmdb.org/t/p/w500/36551C7464Vm1msoZ5K2nahwG49.jpg')">
            <div class="ci-work-card__overlay"></div>
            <span class="ci-work-card__label">Talented</span>
            <a href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()" class="ci-work-card__cta" aria-label="Start session">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17L17 7M17 7H7M17 7V17"/></svg>
            </a>
        </div>
        <div class="ci-work-card" style="background-image:url('https://image.tmdb.org/t/p/w500/xl1w94548gZXIvXtZah65EVz76q.jpg')">
            <div class="ci-work-card__overlay"></div>
            <span class="ci-work-card__label">Modern</span>
            <a href="<?php echo htmlspecialchars($chooseLinkA); ?>" onclick="markVisited()" class="ci-work-card__cta ci-work-card__cta--refresh" aria-label="Try another">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer class="landing-footer">
    <div class="footer-top">
        <div class="footer-left">
            <div class="footer-brand"><span class="nav-brand-dot"></span> Moviemate</div>
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
                <a href="#">TMDb API</a>
                <a href="#">Privacy</a>
                <a href="#">GitHub</a>
            </div>
        </div>
        <div class="footer-right">
            <a href="mailto:hello@moviemate.app" class="footer-contact-link">hello@moviemate.app <span>➔</span></a>
            <a href="#" class="footer-contact-link">Instagram <span>➔</span></a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>Location: Manila, PH</span>
        <span>&copy; <?php echo date('Y'); ?> Moviemate. All rights reserved.</span>
    </div>
</footer>

<script>
function markVisited() { localStorage.setItem('moviemate_visited','true'); }
function showOnboardingTutorial() { document.documentElement.classList.remove('returning-user'); goToStep(1); }
function goToStep(step) {
    document.querySelectorAll('.step-card').forEach(c => c.classList.remove('active'));
    document.getElementById(`step-${step}`).classList.add('active');
    for (let i = 1; i <= 3; i++) {
        const tab = document.getElementById(`tab-${i}`);
        tab.classList.toggle('completed', i < step);
        tab.classList.toggle('active', i === step);
        if (i > step) tab.classList.remove('active','completed');
    }
    document.getElementById('stepsProgress').style.width = `${((step-1)/2)*100}%`;
}
function copyLink() {
    const input = document.getElementById('shareLink');
    input.select(); input.setSelectionRange(0,99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.querySelector('.copy-icon-btn');
        const orig = btn.innerText;
        btn.innerText = 'Copied ✓'; btn.style.background = '#22c55e';
        setTimeout(() => { btn.innerText = orig; btn.style.background = ''; }, 2000);
    }).catch(() => document.execCommand('copy'));
}
function openAuthModal(mode) { document.getElementById('authModal').style.display='flex'; setAuthMode(mode); }
function closeAuthModal()    { document.getElementById('authModal').style.display='none'; }
function toggleAuthMode()    { const m=document.getElementById('authAction').value; setAuthMode(m==='login'?'signup':'login'); }
function setAuthMode(mode) {
    const isLogin = mode==='login';
    document.getElementById('authAction').value=mode;
    document.getElementById('authTitle').innerText=isLogin?'Log In':'Create Account';
    document.getElementById('authSubmitBtn').innerText=isLogin?'Log In':'Sign Up';
    document.getElementById('authToggleText').innerText=isLogin?"Don't have an account?":"Already have an account?";
    document.getElementById('authToggleLink').innerText=isLogin?'Sign Up':'Log In';
    document.getElementById('usernameGroup').style.display=isLogin?'none':'block';
    const un=document.querySelector('input[name="username"]');
    isLogin?un.removeAttribute('required'):un.setAttribute('required','required');
    document.getElementById('authError').style.display='none';
}
async function submitAuth(e) {
    e.preventDefault();
    const btn=document.getElementById('authSubmitBtn');
    btn.disabled=true; btn.innerText='Please wait…';
    try {
        const res=await fetch('/api/auth',{method:'POST',body:new FormData(e.target)});
        const data=await res.json();
        if(data.success){window.location.reload();}
        else{
            document.getElementById('authError').innerText=data.error||'An error occurred';
            document.getElementById('authError').style.display='block';
            btn.disabled=false;
            btn.innerText=document.getElementById('authAction').value==='login'?'Log In':'Sign Up';
        }
    } catch {
        document.getElementById('authError').innerText='Network error. Try again.';
        document.getElementById('authError').style.display='block';
        btn.disabled=false;
        btn.innerText=document.getElementById('authAction').value==='login'?'Log In':'Sign Up';
    }
}
document.addEventListener('DOMContentLoaded',()=>{
    const slides=document.querySelectorAll('.ci-hero__slide');
    if(slides.length<=1)return;
    let cur=0;
    setInterval(()=>{
        slides[cur].classList.remove('is-active');
        cur=(cur+1)%slides.length;
        slides[cur].classList.add('is-active');
    },5000);
});
</script>
</body>
</html>
