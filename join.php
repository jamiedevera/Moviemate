<?php
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$sessionId = $_GET['session'] ?? '';
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) { die('Invalid link.'); }

try {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies, a_name FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) die('Session not found. This link may be expired.');
    if (!empty($session['b_movies'])) {
        header('Location: ' . (!empty($session['a_movies']) ? "/m/{$sessionId}/match" : "/m/{$sessionId}/b"));
        exit;
    }
} catch (PDOException $e) {
    error_log('join.php error: ' . $e->getMessage());
    die('A database error occurred.');
}

$hostName      = htmlspecialchars($session['a_name'] ?? 'Your MovieMate');
$sessionIdSafe = htmlspecialchars($sessionId);
$chooseUrl     = "/m/{$sessionId}/b";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Movie Ticket — MovieMate</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      min-height: 100dvh;
      background: #0a0a0f;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: system-ui, -apple-system, sans-serif;
      padding: 2rem 1rem;
      color: #f5f5f5;
      overflow: hidden;
    }

    /* Grain — matches Next.js shell */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      opacity: 0.035;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
      background-size: 200px 200px;
      z-index: 0;
    }

    .shell {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 480px;
    }

    /* ── Screens ── */
    .screen {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      text-align: center;
      transition: opacity 0.32s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1);
    }

    .screen.hidden {
      opacity: 0;
      pointer-events: none;
      transform: translateY(-12px);
      position: absolute;
      width: 100%;
    }

    .screen.visible {
      opacity: 1;
      transform: translateY(0);
      animation: stepIn 0.32s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes stepIn {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Typography — matches session page ── */
    .step-icon { font-size: 2.4rem; line-height: 1; margin-bottom: 0.25rem; }

    .heading {
      font-size: 1.75rem;
      font-weight: 600;
      color: #f5f5f5;
      letter-spacing: -0.02em;
      line-height: 1.2;
    }

    .sub {
      font-size: 0.95rem;
      color: rgba(255,255,255,0.5);
      max-width: 340px;
      line-height: 1.6;
    }

    /* ── Ticket card ── */
    .ticket {
      background: linear-gradient(135deg, #1a1a24, #13131c);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 2rem;
      width: 100%;
      position: relative;
      overflow: hidden;
    }

    .ticket::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, #e50914, #ff6b6b, #e50914);
    }

    .ticket-tear {
      width: 100%;
      height: 1px;
      background: repeating-linear-gradient(90deg,rgba(255,255,255,0.12) 0px,rgba(255,255,255,0.12) 6px,transparent 6px,transparent 12px);
      margin: 1.25rem 0;
      position: relative;
    }
    .ticket-tear::before, .ticket-tear::after {
      content: '';
      position: absolute;
      top: 50%; transform: translateY(-50%);
      width: 14px; height: 14px;
      border-radius: 50%;
      background: #0a0a0f;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .ticket-tear::before { left: -20px; }
    .ticket-tear::after  { right: -20px; }

    .ticket-eyebrow {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #e50914;
      margin-bottom: 0.5rem;
    }
    .ticket-stub {
      font-size: 0.7rem;
      color: rgba(255,255,255,0.25);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    /* ── Input ── */
    input[type="text"] {
      width: 100%;
      padding: 0.875rem 1.125rem;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px;
      color: #f5f5f5;
      font-size: 1rem;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s, background 0.2s;
    }
    input[type="text"]::placeholder { color: rgba(255,255,255,0.25); }
    input[type="text"]:focus { border-color: rgba(229,9,20,0.6); background: rgba(255,255,255,0.09); }

    /* ── Button ── */
    .btn {
      width: 100%;
      padding: 0.875rem 1.5rem;
      background: #e50914;
      color: #fff;
      font-size: 0.95rem;
      font-weight: 600;
      font-family: inherit;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s, opacity 0.2s;
    }
    .btn:hover:not(:disabled) { background: #f40d1a; transform: translateY(-1px); }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ── Seats — identical to session page ── */
    .room {
      display: flex;
      align-items: center;
      gap: 1.25rem;
      margin: 0.75rem 0;
      width: 100%;
    }

    .seat {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.4rem;
      padding: 1.25rem 1rem;
      border-radius: 12px;
      border: 1px solid rgba(229,9,20,0.3);
      background: rgba(229,9,20,0.08);
    }

    .seat-guest {
      animation: seatPop 0.4s cubic-bezier(0.22,1,0.36,1) 0.4s both;
    }

    @keyframes seatPop {
      from { transform: scale(0.92); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }

    .seat-avatar { font-size: 2rem; line-height: 1; }
    .seat-name   { font-size: 0.9rem; font-weight: 500; color: rgba(255,255,255,0.85); }
    .seat-tag    { font-size: 0.7rem; color: rgba(255,255,255,0.35); }

    .room-divider { font-size: 1.1rem; flex-shrink: 0; }

    /* ── CTA fade in ── */
    .arrival-cta {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      animation: fadeInUp 0.4s ease 0.85s both;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Flash ── */
    .flash {
      position: fixed; inset: 0;
      background: #0a0a0f;
      opacity: 0; pointer-events: none;
      z-index: 200;
      transition: opacity 0.25s ease;
    }
    .flash.active { opacity: 1; }
  </style>
</head>
<body>
<div class="flash" id="flash"></div>

<div class="shell">

  <!-- Screen 1: Ticket -->
  <div class="screen visible" id="screenTicket">
    <div class="ticket">
      <span class="step-icon">🎟️</span>
      <p class="ticket-eyebrow">Movie Ticket</p>
      <h1 class="heading"><?= $hostName ?> invited you to a MovieMate screening.</h1>
      <div class="ticket-tear"></div>
      <p class="ticket-stub">One admit — present this ticket at the door</p>
    </div>

    <input type="text" id="nameInput" placeholder="What's your name?" maxlength="40" autocomplete="off" autofocus>
    <button class="btn" id="joinBtn" disabled>Join <?= $hostName ?>'s Screening</button>
  </div>

  <!-- Screen 2: Both seated -->
  <div class="screen hidden" id="screenArrival">
    <span class="step-icon">🎬</span>
    <h1 class="heading">Both MovieMates have arrived.</h1>
    <p class="sub">The theater is ready. Time to pick your movies.</p>

    <div class="room">
      <div class="seat">
        <div class="seat-avatar">🍿</div>
        <div class="seat-name"><?= $hostName ?></div>
        <div class="seat-tag">Seated</div>
      </div>
      <div class="room-divider">❤️</div>
      <div class="seat seat-guest">
        <div class="seat-avatar">🍿</div>
        <div class="seat-name" id="guestNameDisplay">You</div>
        <div class="seat-tag">Seated</div>
      </div>
    </div>

    <div class="arrival-cta">
      <button class="btn" id="startBtn">Start Choosing Movies</button>
    </div>
  </div>

</div><!-- /.shell -->

<script>
  const input    = document.getElementById('nameInput');
  const joinBtn  = document.getElementById('joinBtn');
  const flash    = document.getElementById('flash');
  const screen1  = document.getElementById('screenTicket');
  const screen2  = document.getElementById('screenArrival');
  const guestDisplay = document.getElementById('guestNameDisplay');
  const startBtn = document.getElementById('startBtn');
  const chooseUrl = '<?= $chooseUrl ?>';

  input.addEventListener('input', () => {
    joinBtn.disabled = input.value.trim().length === 0;
  });

  joinBtn.addEventListener('click', async () => {
    const name = input.value.trim();
    if (!name) return;
    joinBtn.disabled = true;
    guestDisplay.textContent = name;
    try { localStorage.setItem('mm_name', name); } catch(_) {}

    // Notify DB so host polling picks up the join
    try {
      await fetch('/m/<?= $sessionIdSafe ?>/join-b', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name }),
      });
    } catch(_) {}

    // Cinematic flash
    flash.classList.add('active');
    await sleep(250);
    screen1.classList.remove('visible'); screen1.classList.add('hidden');
    screen2.classList.remove('hidden');  screen2.classList.add('visible');
    flash.classList.remove('active');
  });

  startBtn.addEventListener('click', () => {
    const name = input.value.trim() || localStorage.getItem('mm_name') || '';
    window.location.href = chooseUrl + '?name=' + encodeURIComponent(name);
  });

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
</script>
</body>
</html>
