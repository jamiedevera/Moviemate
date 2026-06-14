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

    // Already finished
    if (!empty($session['b_movies'])) {
        $bothDone = !empty($session['a_movies']);
        header('Location: ' . ($bothDone ? "/m/{$sessionId}/match" : "/m/{$sessionId}/b"));
        exit;
    }
} catch (PDOException $e) {
    error_log('join.php error: ' . $e->getMessage());
    die('A database error occurred.');
}

$hostName     = htmlspecialchars($session['a_name'] ?? 'Your MovieMate');
$sessionIdSafe = htmlspecialchars($sessionId);
$chooseUrl    = "/m/{$sessionId}/b";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>You've been invited — MovieMate</title>
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
    }

    .card {
      width: 100%;
      max-width: 440px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      text-align: center;
      animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Ticket visual */
    .ticket {
      background: linear-gradient(135deg, #1a1a24, #13131c);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 1.75rem 2rem;
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

    .ticket-icon { font-size: 2.2rem; line-height: 1; margin-bottom: 0.75rem; display: block; }

    .ticket-title {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 0.5rem;
    }

    .ticket-host {
      font-size: 0.9rem;
      color: rgba(255,255,255,0.5);
      line-height: 1.6;
    }

    .ticket-host strong {
      color: #fff;
      font-weight: 600;
    }

    /* Divider */
    .divider {
      width: 100%;
      height: 1px;
      background: rgba(255,255,255,0.08);
      margin: 0.25rem 0;
    }

    /* Name input */
    .input-label {
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.35);
      align-self: flex-start;
    }

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

    /* Submit button */
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
      letter-spacing: 0.01em;
    }

    .btn:hover:not(:disabled) { background: #f40d1a; transform: translateY(-1px); }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* Transition overlay */
    .overlay {
      position: fixed;
      inset: 0;
      background: #0a0a0f;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
      z-index: 100;
    }

    .overlay.active { opacity: 1; pointer-events: all; }
    .overlay-icon { font-size: 2.5rem; animation: pulse 1s ease-in-out infinite; }
    .overlay-text { font-size: 1.1rem; font-weight: 500; color: rgba(255,255,255,0.7); }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50%       { transform: scale(1.1); }
    }
  </style>
</head>
<body>

  <!-- Cinematic transition overlay -->
  <div class="overlay" id="overlay">
    <span class="overlay-icon">🎬</span>
    <p class="overlay-text">Taking your seat…</p>
  </div>

  <div class="card" id="card">
    <!-- Ticket -->
    <div class="ticket">
      <span class="ticket-icon">🎟️</span>
      <h1 class="ticket-title">You've been invited</h1>
      <p class="ticket-host">
        <strong><?= $hostName ?></strong> has reserved a private screening<br>
        for you on MovieMate.
      </p>
    </div>

    <div class="divider"></div>

    <!-- Name entry -->
    <label class="input-label" for="nameInput">What's your name?</label>
    <input
      type="text"
      id="nameInput"
      placeholder="Enter your name"
      maxlength="40"
      autocomplete="off"
      autofocus
    >

    <button class="btn" id="joinBtn" disabled>
      Join <?= $hostName ?>'s Screening
    </button>
  </div>

  <script>
    const input   = document.getElementById('nameInput');
    const btn     = document.getElementById('joinBtn');
    const overlay = document.getElementById('overlay');

    input.addEventListener('input', () => {
      btn.disabled = input.value.trim().length === 0;
    });

    btn.addEventListener('click', async () => {
      const name = input.value.trim();
      if (!name) return;

      btn.disabled = true;
      try { localStorage.setItem('mm_name', name); } catch(_) {}

      // Save guest name via status endpoint if available, otherwise just proceed
      // Show cinematic transition
      overlay.classList.add('active');

      // Short dramatic pause before redirecting
      await new Promise(r => setTimeout(r, 1200));

      const params = new URLSearchParams({ name });
      window.location.href = '<?= $chooseUrl ?>?' + params.toString();
    });
  </script>
</body>
</html>
