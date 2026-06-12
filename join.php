<?php
// join.php — entry point for Person B
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$sessionId = $_GET['session'] ?? '';

// Validate session id
if (!$sessionId || !preg_match('/^[a-f0-9]{16}$/', $sessionId)) {
    die('Invalid link');
}

try {
    $stmt = $pdo->prepare('SELECT id, a_movies, b_movies FROM sessions WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        die('Session not found. This link may be invalid or expired.');
    }

    $base = '';
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $dir     = str_replace('\\', '/', __DIR__);
        if (strpos(strtolower($dir), strtolower($docRoot)) === 0) {
            $base = substr($dir, strlen($docRoot));
        }
    }
    $base = rtrim($base, '/\\');

    // If Person B already picked, go straight to match or waiting
    if (!empty($session['b_movies'])) {
        $bothDone = !empty($session['a_movies']);
        if ($bothDone) {
            header('Location: ' . $base . '/m/' . $sessionId . '/match');
        } else {
            header('Location: ' . $base . '/m/' . $sessionId . '/b');
        }
        exit;
    }

} catch (PDOException $e) {
    error_log('Database error in join.php: ' . $e->getMessage());
    die('A database error occurred. Please try again.');
}

$chooseUrl = htmlspecialchars($base . '/m/' . $sessionId . '/b');
$sessionIdSafe = htmlspecialchars($sessionId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Join MovieMate</title>
  <link rel="stylesheet" href="/assets/global.css">
  <style>
    /* ── Join page — cinematic name-entry ──────────────────────────────── */
    body {
      margin: 0;
      min-height: 100dvh;
      background: #0a0a0f;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: system-ui, -apple-system, sans-serif;
      padding: 1.5rem;
      box-sizing: border-box;
    }

    .join-card {
      width: 100%;
      max-width: 440px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      text-align: center;
      animation: fadeUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @media (prefers-reduced-motion: reduce) {
      .join-card { animation: none; }
    }

    .join-icon {
      font-size: 2.4rem;
      line-height: 1;
    }

    .join-heading {
      font-size: 1.75rem;
      font-weight: 600;
      color: #f5f5f5;
      letter-spacing: -0.02em;
      line-height: 1.2;
      margin: 0;
    }

    .join-sub {
      font-size: 0.95rem;
      color: rgba(255,255,255,0.48);
      line-height: 1.6;
      max-width: 320px;
      margin: 0;
    }

    .join-form {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      width: 100%;
      margin-top: 0.5rem;
    }

    .join-input {
      width: 100%;
      box-sizing: border-box;
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

    .join-input::placeholder {
      color: rgba(255,255,255,0.28);
    }

    .join-input:focus {
      border-color: rgba(229,9,20,0.6);
      background: rgba(255,255,255,0.09);
    }

    .join-btn {
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

    .join-btn:hover:not(:disabled) {
      background: #f40d1a;
      transform: translateY(-1px);
    }

    .join-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="join-card">
    <span class="join-icon">🎟️</span>
    <h1 class="join-heading">Let's get your name on the ticket</h1>
    <p class="join-sub">We'll save your seat before the movie night begins.</p>

    <form class="join-form" id="joinForm" action="<?= $chooseUrl ?>" method="get">
      <input type="hidden" name="session" value="<?= $sessionIdSafe ?>">
      <input
        class="join-input"
        type="text"
        id="nameInput"
        name="name"
        placeholder="Enter your name"
        maxlength="40"
        autocomplete="off"
        autofocus
        required
      >
      <button class="join-btn" type="submit" id="joinBtn" disabled>
        Continue
      </button>
    </form>
  </div>

  <script>
    const input = document.getElementById('nameInput');
    const btn   = document.getElementById('joinBtn');

    input.addEventListener('input', () => {
      btn.disabled = input.value.trim().length === 0;
    });

    document.getElementById('joinForm').addEventListener('submit', function(e) {
      const name = input.value.trim();
      if (!name) { e.preventDefault(); return; }
      // Persist name for PHP pages that use ?name= param
      try { localStorage.setItem('mm_name', name); } catch(_) {}
    });
  </script>
</body>
</html>
