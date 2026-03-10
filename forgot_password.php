<?php
// ── forgot_password.php ──────────────────────────────────────────────────────
// Opens in a new window/tab. Two-step flow:
//   Step 1 — Verify identity (Student No + Email)
//   Step 2 — Set new password
// No email sending required; uses security questions via DB lookup.
// ─────────────────────────────────────────────────────────────────────────────
session_start();

$serverName = ".\\SQLEXPRESS";
$connectionOptions = ["Database" => "PortalDB", "Uid" => "", "PWD" => ""];

$step    = $_SESSION['fp_step'] ?? 1;   // 1 = verify identity, 2 = set new password
$error   = "";
$success = false;

// ── STEP 1: Verify Student No + Email ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $studentNo = trim($_POST['student_no'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));

    if (empty($studentNo) || empty($email)) {
        $error = "Please fill in both fields.";
    } else {
        $conn = sqlsrv_connect($serverName, $connectionOptions);
        if ($conn === false) {
            $error = "Database connection failed. Please try again.";
        } else {
            $sql    = "SELECT STUDENT_ID FROM STUDENTS WHERE STUDENT_NO = ? AND EMAIL = ?";
            $result = sqlsrv_query($conn, $sql, [$studentNo, $email]);
            $row    = $result ? sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC) : null;
            sqlsrv_close($conn);

            if (!$row) {
                $error = "No account found with that Student ID and email combination.";
            } else {
                // Identity confirmed — move to step 2
                $_SESSION['fp_step']       = 2;
                $_SESSION['fp_student_id'] = $row['STUDENT_ID'];
                $step = 2;
            }
        }
    }
}

// ── STEP 2: Set New Password ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    if (empty($_SESSION['fp_student_id'])) {
        $error = "Session expired. Please start over.";
        $_SESSION['fp_step'] = 1;
        $step = 1;
    } else {
        $newPw      = $_POST['new_password']     ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';

        if (strlen($newPw) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif ($newPw !== $confirmPw) {
            $error = "Passwords do not match.";
        } else {
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            if ($conn === false) {
                $error = "Database connection failed. Please try again.";
            } else {
                $hashed = password_hash($newPw, PASSWORD_DEFAULT);
                $sql    = "UPDATE STUDENTS SET PASSWORD = ? WHERE STUDENT_ID = ?";
                $result = sqlsrv_query($conn, $sql, [$hashed, $_SESSION['fp_student_id']]);
                sqlsrv_close($conn);

                if ($result === false) {
                    $error = "Failed to update password. Please try again.";
                } else {
                    // Clear fp session vars
                    unset($_SESSION['fp_step'], $_SESSION['fp_student_id']);
                    $success = true;
                    $step    = 3; // success screen
                }
            }
        }
    }
}

// ── CANCEL / START OVER ──────────────────────────────────────────────────────
if (isset($_GET['reset'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_student_id']);
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CEAT NEXUS — Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dlsu-dark:   #0c2310;
      --dlsu-mid:    #1a3d1a;
      --dlsu-green:  #3a8c3a;
      --dlsu-light:  #5cb85c;
      --dlsu-pale:   #a8d8a8;
      --card-bg:     rgba(12,35,12,0.92);
      --card-border: rgba(255,255,255,0.10);
      --muted:       rgba(255,255,255,0.5);
      --input-bg:    rgba(255,255,255,0.06);
      --input-border:rgba(255,255,255,0.14);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: url('images/bg.png') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    body::before {
      content: ''; position: fixed; inset: 0;
      background: linear-gradient(160deg, rgba(8,24,8,0.82) 0%, rgba(10,31,10,0.74) 50%, rgba(5,18,5,0.88) 100%);
      z-index: 0; pointer-events: none;
    }
    body::after {
      content: ''; position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(61,184,61,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(61,184,61,0.03) 1px, transparent 1px);
      background-size: 40px 40px;
      z-index: 0; pointer-events: none;
    }
    .wrapper {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 32px 24px;
      animation: fadeUp 0.4s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fp-card {
      width: 440px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 22px;
      backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px);
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,0.55), 0 0 0 1px rgba(92,184,92,0.07);
    }
    .card-accent { height: 3px; background: linear-gradient(90deg, var(--dlsu-light), #3db83d, transparent); }
    .card-inner { padding: 32px 34px 36px; }

    /* Logo */
    .logo-wrap { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
    .logo-wrap img { width: 100%; max-width: 300px; height: auto; filter: drop-shadow(0 4px 18px rgba(92,184,92,0.22)); }
    .logo-fallback { display: none; align-items: center; gap: 9px; }
    .logo-mark { width: 38px; height: 38px; background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid)); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 14px rgba(92,184,92,0.4); }
    .logo-mark svg { width: 18px; height: 18px; fill: none; stroke: white; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
    .logo-text { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800; color: white; }
    .logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.56rem; font-weight: 400; color: var(--muted); letter-spacing: 0.12em; text-transform: uppercase; margin-top: 2px; }

    /* Step progress */
    .step-progress {
      display: flex; align-items: center; justify-content: center;
      gap: 0; margin-bottom: 24px;
    }
    .step-node {
      width: 30px; height: 30px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.72rem; font-weight: 700;
      background: rgba(255,255,255,0.07);
      border: 1.5px solid rgba(255,255,255,0.15);
      color: rgba(255,255,255,0.35);
      transition: all 0.3s;
    }
    .step-node.active {
      background: var(--dlsu-light);
      border-color: var(--dlsu-light);
      color: white;
      box-shadow: 0 0 12px rgba(92,184,92,0.45);
    }
    .step-node.done {
      background: rgba(92,184,92,0.25);
      border-color: rgba(92,184,92,0.5);
      color: #8de88d;
    }
    .step-line {
      width: 40px; height: 1.5px;
      background: rgba(255,255,255,0.1);
    }
    .step-line.done { background: rgba(92,184,92,0.4); }

    .card-eyebrow {
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--dlsu-pale);
      display: flex; align-items: center; justify-content: center; gap: 7px;
      margin-bottom: 5px;
    }
    .card-eyebrow::before, .card-eyebrow::after { content: '·'; color: var(--dlsu-light); font-size: 1.2em; }
    .card-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: white; text-align: center; letter-spacing: -0.02em; margin-bottom: 5px; }
    .card-sub { font-size: 0.76rem; color: var(--muted); font-weight: 300; text-align: center; line-height: 1.6; margin-bottom: 24px; }
    .divider { height: 1px; background: rgba(255,255,255,0.07); margin-bottom: 22px; }

    .alert-error {
      background: rgba(220,53,69,0.14); border: 1px solid rgba(220,53,69,0.35);
      color: #ff8a95; border-radius: 10px; padding: 11px 14px;
      font-size: 0.8rem; margin-bottom: 18px;
      display: flex; align-items: flex-start; gap: 8px;
    }

    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.55); }
    .input-wrap { position: relative; }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.85rem; pointer-events: none; }
    .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 0.85rem; cursor: pointer; user-select: none; color: rgba(255,255,255,0.35); transition: color 0.15s; }
    .pw-toggle:hover { color: rgba(255,255,255,0.7); }
    .hint { font-size: 0.68rem; color: rgba(255,255,255,0.28); margin-top: 3px; }

    input[type="text"], input[type="email"], input[type="password"] {
      width: 100%; padding: 11px 38px 11px 36px;
      background: var(--input-bg); border: 1px solid var(--input-border);
      border-radius: 9px; color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.85rem; font-weight: 500;
      outline: none; transition: border-color 0.18s, background 0.18s;
      -webkit-appearance: none; appearance: none;
    }
    input:focus { border-color: rgba(92,184,92,0.55); background: rgba(92,184,92,0.06); }
    input::placeholder { color: rgba(255,255,255,0.22); }

    /* Password strength bar */
    .strength-bar-wrap { height: 4px; background: rgba(255,255,255,0.08); border-radius: 4px; margin-top: 7px; overflow: hidden; }
    .strength-bar { height: 100%; border-radius: 4px; width: 0%; transition: width 0.3s, background 0.3s; }

    .btn-submit {
      display: flex; align-items: center; justify-content: center; gap: 9px;
      width: 100%; padding: 13px 20px;
      background: var(--dlsu-light); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      box-shadow: 0 4px 18px rgba(92,184,92,0.35);
      transition: all 0.18s; letter-spacing: 0.02em;
      margin-top: 6px;
    }
    .btn-submit:hover { background: #6ecf6e; transform: translateY(-2px); box-shadow: 0 8px 26px rgba(92,184,92,0.5); }
    .btn-back {
      display: flex; align-items: center; justify-content: center;
      width: 100%; padding: 11px 20px; margin-top: 10px;
      background: transparent; color: rgba(255,255,255,0.4);
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.82rem; font-weight: 600;
      border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
      cursor: pointer; text-decoration: none;
      transition: all 0.18s;
    }
    .btn-back:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); }

    /* Success screen */
    .success-wrap { text-align: center; padding: 12px 0 8px; }
    .success-icon {
      width: 68px; height: 68px; border-radius: 50%;
      background: rgba(92,184,92,0.15);
      border: 2px solid rgba(92,184,92,0.4);
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem; margin: 0 auto 18px;
      box-shadow: 0 0 24px rgba(92,184,92,0.25);
    }
    .success-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: white; margin-bottom: 8px; }
    .success-msg { font-size: 0.78rem; color: var(--muted); line-height: 1.6; margin-bottom: 24px; }
    .btn-goto-login {
      display: inline-flex; align-items: center; justify-content: center; gap: 9px;
      padding: 13px 28px;
      background: var(--dlsu-light); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      box-shadow: 0 4px 18px rgba(92,184,92,0.35);
      transition: all 0.18s; text-decoration: none;
    }
    .btn-goto-login:hover { background: #6ecf6e; transform: translateY(-2px); color: white; }

    .card-bottom-note { margin-top: 22px; font-size: 0.62rem; color: rgba(255,255,255,0.18); text-align: center; line-height: 1.5; }
    .card-bottom-note span { color: rgba(92,184,92,0.55); font-weight: 600; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="fp-card">
    <div class="card-accent"></div>
    <div class="card-inner">

      <!-- LOGO -->
      <div class="logo-wrap">
        <img src="images/logo.png" alt="CEAT NEXUS"
             onerror="this.style.display='none'; document.getElementById('logo-fb').style.display='flex';">
        <div class="logo-fallback" id="logo-fb">
          <div class="logo-mark">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          </div>
          <div class="logo-text">CEAT NEXUS<small>DLSU — Dasmariñas</small></div>
        </div>
      </div>

      <?php if ($step < 3): ?>
      <!-- Step Progress -->
      <div class="step-progress">
        <div class="step-node <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
          <?= $step > 1 ? '✓' : '1' ?>
        </div>
        <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
        <div class="step-node <?= $step >= 2 ? 'active' : '' ?>">2</div>
      </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
      <!-- ── STEP 1: VERIFY IDENTITY ── -->
      <div class="card-eyebrow">Password Reset</div>
      <div class="card-title">Verify Your Identity</div>
      <p class="card-sub">Enter your Student ID Number and registered email address to continue.</p>
      <div class="divider"></div>

      <?php if (!empty($error)): ?>
      <div class="alert-error">⚠ &nbsp;<?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="forgot_password.php">
        <input type="hidden" name="action" value="verify">

        <div class="form-group">
          <label for="student_no">Student ID Number</label>
          <div class="input-wrap">
            <span class="input-icon">🪪</span>
            <input type="text" id="student_no" name="student_no"
                   placeholder="e.g. 202330199"
                   value="<?= htmlspecialchars($_POST['student_no'] ?? '') ?>"
                   autocomplete="off" required>
          </div>
        </div>

        <div class="form-group">
          <label for="email">Registered Email</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email"
                   placeholder="yourid@dlsud.edu.ph"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   autocomplete="email" required>
          </div>
        </div>

        <button type="submit" class="btn-submit">→ &nbsp;Verify Identity</button>
      </form>

      <a href="login.php" class="btn-back">← &nbsp;Back to Login</a>

      <?php elseif ($step === 2): ?>
      <!-- ── STEP 2: SET NEW PASSWORD ── -->
      <div class="card-eyebrow">Password Reset</div>
      <div class="card-title">Set New Password</div>
      <p class="card-sub">Choose a strong new password for your CEAT NEXUS account.</p>
      <div class="divider"></div>

      <?php if (!empty($error)): ?>
      <div class="alert-error">⚠ &nbsp;<?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="forgot_password.php" id="resetForm">
        <input type="hidden" name="action" value="reset">

        <div class="form-group">
          <label for="new_password">New Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="new_password" name="new_password"
                   placeholder="Min. 8 characters"
                   autocomplete="new-password"
                   oninput="updateStrength(this.value)"
                   required>
            <span class="pw-toggle" onclick="togglePw('new_password', this)">👁</span>
          </div>
          <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
          <div class="hint" id="strengthHint"></div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="confirm_password" name="confirm_password"
                   placeholder="Repeat your new password"
                   autocomplete="new-password"
                   required>
            <span class="pw-toggle" onclick="togglePw('confirm_password', this)">👁</span>
          </div>
        </div>

        <button type="submit" class="btn-submit">✓ &nbsp;Update Password</button>
      </form>

      <a href="forgot_password.php?reset=1" class="btn-back">← &nbsp;Start Over</a>

      <?php elseif ($step === 3): ?>
      <!-- ── STEP 3: SUCCESS ── -->
      <div class="success-wrap">
        <div class="success-icon">🔑</div>
        <div class="success-title">Password Updated!</div>
        <p class="success-msg">
          Your password has been successfully changed.<br>
          You can now log in with your new password.
        </p>
        <a href="login.php?pwreset=1" class="btn-goto-login" onclick="window.close(); return false;">
          → &nbsp;Go to Login
        </a>
      </div>
      <?php endif; ?>

      <div class="card-bottom-note">
        CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span><br>
        College of Engineering, Architecture &amp; Technology
      </div>

    </div>
  </div>
</div>

<script>
  function togglePw(fieldId, btn) {
    const f = document.getElementById(fieldId);
    if (f.type === 'password') { f.type = 'text'; btn.textContent = '🙈'; }
    else { f.type = 'password'; btn.textContent = '👁'; }
  }

  function updateStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const hint = document.getElementById('strengthHint');
    if (!bar) return;
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
      { w: '0%',   bg: 'transparent', label: '' },
      { w: '30%',  bg: '#e74c3c',     label: '🔴 Weak' },
      { w: '55%',  bg: '#f39c12',     label: '🟡 Fair' },
      { w: '78%',  bg: '#3498db',     label: '🔵 Good' },
      { w: '100%', bg: '#5cb85c',     label: '🟢 Strong' },
    ];
    const lvl = val.length === 0 ? 0 : Math.max(1, score);
    bar.style.width      = levels[lvl].w;
    bar.style.background = levels[lvl].bg;
    hint.textContent     = levels[lvl].label;
  }
</script>
</body>
</html>