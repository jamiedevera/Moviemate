<?php
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$sessionId = $_GET['session'] ?? '';

if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    die('Invalid link.');
}

try {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies, a_name FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) die('Session not found. This link may be expired.');

    if (!empty($session['b_movies'])) {
        $bothDone = !empty($session['a_movies']);
        header('Location: ' . ($bothDone ? "/m/{$sessionId}/match" : "/m/{$sessionId}/b"));
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
      padding: 1.5rem;
      color: #f5f5f5;
      overflow: hidden;
    }

    /* ── Screens ── */
    .screen {
      width: 100%;
      max-width: 440px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      text-align: center;
      position: absolute;
      transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.22,1,0.36,1);
    }

    .screen.hidden {
      opacity: 0;
      pointer-events: none;
      transform: translateY(16px);
    }

    .screen.visible {
      opacity: 1;
      pointer-events: all;
      transform: translateY(0);
      animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
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

    /* Tear line */
    .ticket-tear {
      width: 100%;
      height: 1px;
      background: repeating-linear-gradient(
        90deg,
        rgba(255,255,255,0.12) 0px,
        rgba(255,255,255,0.12) 6px,
        transparent 6px,
        transparent 12px
      );
      margin: 1.25rem 0;
      position: relative;
    }

    .ticket-tear::before,
    .ticket-tear::after {
      content: '';
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: #0a0a0f;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .ticket-tear::before { left: -20px; }
    .ticket-tear::after  { right: -20px; }

    .ticket-icon { font-size: 2.2rem; line-height: 1; margin-bottom: 0.75rem; display: block; }
    .ticket-eyebrow {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: #e50914;
      margin-bottom: 0.5rem;
    }
    .ticket-title {
      font-size: 1.4rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      line-height: 1.3;
    }
    .ticket-host {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.45);
      margin-top: 0.4rem;
    }
    .ticket-stub {
      font-size: 0.7rem;
      color: rgba(255,255,255,0.25);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    /* ── Inputs ── */
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
    input[type="text"]:focus {
      border-color: rgba(229,9,20,0.6);
      background: rgba(255,255,255,0.09);
    }

    /* ── Buttons ── */
    .btn {
      width: 100%;
      padding: 0.9rem 1.5rem;
      background: #e50914;
      color: #fff;
      font-size: 0.95rem;
      font-weight: 700;
      font-family: inherit;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s, opacity 0.2s;
    }
    .btn:hover:not(:disabled) { background: #f40d1a; transform: translateY(-1px); }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ── Seats row ── */
    .seats-row {
      display: flex;
      align-items: center;
      gap: 1.25rem;
      width: 100%;
      margin: 0.5rem 0;
    }

    .seat {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.4rem;
      padding: 1.25rem 1rem;
      border-radius: 12px;
      border: 1px solid transparent;
    }

    .seat-filled {
      background: rgba(229,9,20,0.08);
      border-color: rgba(229,9,20,0.3);
    }

    .seat-animate {
      animation: seatPop 0.4s cubic-bezier(0.22,1,0.36,1) 0.4s both;
    }

    @keyframes seatPop {
      from { transform: scale(0.92); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }

    .seat-avatar { font-size: 2rem; line-height: 1; }
    .seat-name   { font-size: 0.9rem; font-weight: 500; color: rgba(255,255,255,0.85); }
    .seat-tag    { font-size: 0.7rem; color: rgba(255,255,255,0.35); }

    .seat-heart {
      font-size: 1.1rem;
      flex-shrink: 0;
      color: rgba(255,255,255,0.25);
    }

    /* ── Theater arrival screen ── */
    .arrival-rows {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      width: 100%;
    }

    .arrival-row {
      display: flex;
      align-items: center;
      gap: 0.875rem;
      padding: 1rem 1.25rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 12px;
      opacity: 0;
      transform: translateX(-12px);
      transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.22,1,0.36,1);
    }

    .arrival-row.show {
      opacity: 1;
      transform: translateX(0);
    }

    .arrival-avatar { font-size: 1.5rem; }
    .arrival-text { text-align: left; }
    .arrival-name { font-size: 0.95rem; font-weight: 600; color: #f5f5f5; }
    .arrival-sub  { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 0.1rem; }

    .arrival-cta {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      width: 100%;
      opacity: 0;
      transform: translateY(8px);
      transition: opacity 0.4s ease 0.6s, transform 0.4s ease 0.6s;
    }
    .arrival-cta.show { opacity: 1; transform: translateY(0); }
    .arrival-cta p {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.4);
      margin-bottom: 0.25rem;
    }

    /* ── Cinematic flash overlay ── */
    .flash {
      position: fixed;
      inset: 0;
      background: #0a0a0f;
      opacity: 0;
      pointer-events: none;
      z-index: 200;
      transition: opacity 0.25s ease;
    }
    .flash.active { opacity: 1; }
  </style>
</head>
<body>

<div class="flash" id="flash"></div>

<!-- Screen 1: Ticket + name entry -->
<div class="screen visible" id="screenTicket">
  <div class="ticket">
    <span class="ticket-icon">🎟️</span>
    <p class="ticket-eyebrow">Movie Ticket</p>
    <h1 class="ticket-title"><?= $hostName ?> invited you to a MovieMate screening.</h1>
    <div class="ticket-tear"></div>
    <p class="ticket-stub">One admit — present this ticket at the door</p>
  </div>

  <input
    type="text"
    id="nameInput"
    placeholder="What's your name?"
    maxlength="40"
    autocomplete="off"
    autofocus
  >

  <button class="btn" id="joinBtn" disabled>
    Join <?= $hostName ?>'s Screening
  </button>
</div>

<!-- Screen 2: Both seated -->
<div class="screen hidden" id="screenArrival">
  <span style="font-size:2.2rem">🎬</span>
  <h1 style="font-size:1.4rem;font-weight:700;letter-spacing:-0.02em;margin-bottom:0.25rem">Both MovieMates have arrived.</h1>
  <p style="font-size:0.9rem;color:rgba(255,255,255,0.45);margin-bottom:0.5rem">The theater is ready. Time to pick your movies.</p>

  <div class="seats-row">
    <div class="seat seat-filled">
      <div class="seat-avatar">🍿</div>
      <div class="seat-name"><?= $hostName ?></div>
      <div class="seat-tag">Seated</div>
    </div>
    <div class="seat-heart">❤️</div>
    <div class="seat seat-filled seat-animate" id="guestSeat">
      <div class="seat-avatar">🍿</div>
      <div class="seat-name" id="guestNameDisplay">You</div>
      <div class="seat-tag">Seated</div>
    </div>
  </div>

  <div class="arrival-cta" id="arrivalCta">
    <button class="btn" id="startBtn">Start Choosing Movies</button>
  </div>
</div>

<script>
  const input      = document.getElementById('nameInput');
  const joinBtn    = document.getElementById('joinBtn');
  const flash      = document.getElementById('flash');
  const screen1    = document.getElementById('screenTicket');
  const screen2    = document.getElementById('screenArrival');
  const rowHost    = document.getElementById('rowHost');
  const rowGuest   = document.getElementById('rowGuest');
  const arrivalCta = document.getElementById('arrivalCta');
  const guestDisplay = document.getElementById('guestNameDisplay');
  const startBtn   = document.getElementById('startBtn');
  const chooseUrl  = '<?= $chooseUrl ?>';

  input.addEventListener('input', () => {
    joinBtn.disabled = input.value.trim().length === 0;
  });

  joinBtn.addEventListener('click', async () => {
    const name = input.value.trim();
    if (!name) return;
    joinBtn.disabled = true;

    try { localStorage.setItem('mm_name', name); } catch(_) {}
    guestDisplay.textContent = name;

    // Mark b_joined in DB so host polling detects the join
    try {
      await fetch('/m/<?= $sessionIdSafe ?>/join-b', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name }),
      });
    } catch(_) {}

    // Cinematic flash transition
    flash.classList.add('active');
    await sleep(250);

    // Switch screens
    screen1.classList.remove('visible');
    screen1.classList.add('hidden');
    screen2.classList.remove('hidden');
    screen2.classList.add('visible');

    flash.classList.remove('active');

    // Staggered arrivals
    await sleep(300);
    rowHost.classList.add('show');
    await sleep(500);
    rowGuest.classList.add('show');
    await sleep(600);
    arrivalCta.classList.add('show');
  });

  startBtn.addEventListener('click', () => {
    const name = input.value.trim() || localStorage.getItem('mm_name') || '';
    const params = new URLSearchParams({ name });
    window.location.href = chooseUrl + '?' + params.toString();
  });

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
</script>
</body>
</html>
