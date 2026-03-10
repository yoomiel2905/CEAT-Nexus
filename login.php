<?php
session_start();

// Already logged in — go to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

// ── DB CONFIG ───────────────────────────────────────────────────────────────
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "PortalDB",
    "Uid"      => "",
    "PWD"      => ""
];

$error     = "";
$formEmail = "";

// ── HANDLE FORM SUBMISSION ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"] ?? "";
    $formEmail = $email;

    if (empty($email) || empty($password)) {
        $error = "Please enter both your email and password.";
    } else {
        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            $error = "Database connection failed. Please try again later.";
        } else {
            $sql    = "SELECT STUDENT_ID, STUDENT_NO, FIRST_NAME, LAST_NAME, PROGRAM, PASSWORD, IS_ADMIN
                      FROM STUDENTS WHERE EMAIL = ?";
            $params = [$email];
            $result = sqlsrv_query($conn, $sql, $params);

            if ($result === false) {
                $error = "A database error occurred. Please try again.";
            } else {
                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

                if (!$row || !password_verify($password, $row["PASSWORD"])) {
                    $error = "Invalid email or password. Please try again.";
                } else {
                    // ── Set session ─────────────────────────────────────────
                    session_regenerate_id(true);
                    $_SESSION['student_id'] = $row['STUDENT_ID'];
                    $_SESSION['student_no'] = $row['STUDENT_NO'];
                    $_SESSION['first_name'] = $row['FIRST_NAME'];
                    $_SESSION['last_name']  = $row['LAST_NAME'];
                    $_SESSION['program']    = $row['PROGRAM'];
                    $_SESSION['email']      = $email;
                    $_SESSION['is_admin']   = (bool)$row['IS_ADMIN'];

                    sqlsrv_close($conn);
                    header("Location: dashboard.php");
                    exit;
                }
            }

            sqlsrv_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CEAT NEXUS — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dlsu-dark:   #0c2310;
      --dlsu-mid:    #1a3d1a;
      --dlsu-green:  #3a8c3a;
      --dlsu-light:  #5cb85c;
      --dlsu-pale:   #a8d8a8;
      --card-bg:     rgba(12,35,12,0.90);
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
      background: linear-gradient(160deg, rgba(8,24,8,0.80) 0%, rgba(10,31,10,0.72) 50%, rgba(5,18,5,0.86) 100%);
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
      animation: fadeUp 0.45s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .login-card {
      width: 460px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 22px;
      backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px);
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,0.55), 0 0 0 1px rgba(92,184,92,0.07);
    }
    .card-accent {
      height: 3px;
      background: linear-gradient(90deg, var(--dlsu-light), #3db83d, transparent);
    }
    .card-inner { padding: 32px 36px 36px; }
    .logo-wrap {
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 22px;
    }
    .logo-wrap img {
      width: 100%; max-width: 360px; height: auto;
      filter: drop-shadow(0 4px 18px rgba(92,184,92,0.22));
    }
    .logo-fallback { display: none; align-items: center; gap: 10px; }
    .logo-mark {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
      border-radius: 11px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 16px rgba(92,184,92,0.4);
    }
    .logo-mark svg { width: 20px; height: 20px; fill: none; stroke: white; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
    .logo-text { font-family: 'Syne', sans-serif; font-size: 1.15rem; font-weight: 800; color: white; }
    .logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.58rem; font-weight: 400; color: var(--muted); letter-spacing: 0.12em; text-transform: uppercase; margin-top: 2px; }
    .card-eyebrow {
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--dlsu-pale);
      display: flex; align-items: center; justify-content: center; gap: 7px;
      margin-bottom: 5px;
    }
    .card-eyebrow::before, .card-eyebrow::after { content: '·'; color: var(--dlsu-light); font-size: 1.2em; }
    .card-title {
      font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800;
      color: white; text-align: center; letter-spacing: -0.02em; margin-bottom: 4px;
    }
    .card-sub {
      font-size: 0.76rem; color: var(--muted); font-weight: 300;
      text-align: center; line-height: 1.5; margin-bottom: 26px;
    }
    .divider { height: 1px; background: rgba(255,255,255,0.07); margin-bottom: 24px; }
    .alert-error {
      background: rgba(220,53,69,0.14); border: 1px solid rgba(220,53,69,0.35);
      color: #ff8a95; border-radius: 10px; padding: 11px 14px;
      font-size: 0.8rem; margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px;
    }
    .alert-info {
      background: rgba(92,184,92,0.12); border: 1px solid rgba(92,184,92,0.3);
      color: #8de88d; border-radius: 10px; padding: 10px 14px;
      font-size: 0.78rem; margin-bottom: 18px; text-align: center;
    }
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    label {
      font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.1em; color: rgba(255,255,255,0.55);
    }
    .input-wrap { position: relative; }
    .input-icon {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      font-size: 0.85rem; pointer-events: none;
    }
    .pw-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      font-size: 0.85rem; cursor: pointer; user-select: none;
      color: rgba(255,255,255,0.35); transition: color 0.15s;
    }
    .pw-toggle:hover { color: rgba(255,255,255,0.7); }
    input[type="email"],
    input[type="password"],
    input[type="text"] {
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
    .form-meta {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 22px;
    }
    .remember-label {
      display: flex; align-items: center; gap: 7px;
      font-size: 0.75rem; color: rgba(255,255,255,0.45); cursor: pointer; user-select: none;
    }
    .remember-label input[type="checkbox"] {
      width: 15px; height: 15px; padding: 0;
      accent-color: var(--dlsu-light); cursor: pointer;
    }
    .forgot-link {
      font-size: 0.75rem; color: var(--dlsu-light);
      font-weight: 600; text-decoration: none; transition: color 0.15s;
    }
    .forgot-link:hover { color: #8de88d; }
    .btn-submit {
      display: flex; align-items: center; justify-content: center; gap: 9px;
      width: 100%; padding: 13px 20px;
      background: var(--dlsu-light); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      box-shadow: 0 4px 18px rgba(92,184,92,0.35);
      transition: all 0.18s; letter-spacing: 0.02em;
    }
    .btn-submit:hover { background: #6ecf6e; transform: translateY(-2px); box-shadow: 0 8px 26px rgba(92,184,92,0.5); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit.loading { pointer-events: none; opacity: 0.75; }
    .spinner {
      display: none; width: 14px; height: 14px;
      border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
      border-radius: 50%; animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .or-divider {
      display: flex; align-items: center; gap: 12px; margin: 20px 0;
    }
    .or-divider::before, .or-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08); }
    .or-divider span { font-size: 0.65rem; color: rgba(255,255,255,0.25); font-weight: 600; letter-spacing: 0.08em; }
    .btn-register {
      display: flex; align-items: center; justify-content: center; gap: 9px;
      width: 100%; padding: 12px 20px;
      background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.7);
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 600;
      border: 1px solid rgba(255,255,255,0.13); border-radius: 10px;
      cursor: pointer; text-decoration: none;
      transition: all 0.18s; letter-spacing: 0.02em;
    }
    .btn-register:hover { background: rgba(255,255,255,0.11); border-color: rgba(92,184,92,0.35); color: white; }
    .card-bottom-note {
      margin-top: 22px; font-size: 0.62rem;
      color: rgba(255,255,255,0.18); text-align: center; line-height: 1.5;
    }
    .card-bottom-note span { color: rgba(92,184,92,0.55); font-weight: 600; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="login-card">
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

      <div class="card-eyebrow">Student Portal</div>
      <div class="card-title">Welcome Back!</div>
      <p class="card-sub">Sign in to your CEAT NEXUS account to continue.</p>

      <div class="divider"></div>

      <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
      <div class="alert-info">✅ &nbsp;Account created! You can now log in.</div>
      <?php elseif (isset($_GET['logout']) && $_GET['logout'] === '1'): ?>
      <div class="alert-info">👋 &nbsp;You've been signed out successfully.</div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
      <div class="alert-error">⚠ &nbsp;<?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php" id="loginForm">

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input type="email" id="email" name="email"
                  placeholder="yourid@dlsud.edu.ph"
                  value="<?= htmlspecialchars($formEmail) ?>"
                  autocomplete="email" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password"
                  placeholder="Your password"
                  autocomplete="current-password" required>
            <span class="pw-toggle" id="pwToggle" title="Show/hide password">👁</span>
          </div>
        </div>

        <div class="form-meta">
          <label class="remember-label">
            <input type="checkbox" name="remember" id="remember">
            Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <div class="spinner" id="spinner"></div>
          <span id="btnText">→ &nbsp;Sign In</span>
        </button>

      </form>

      <div class="or-divider"><span>OR</span></div>

      <a href="register.php" class="btn-register">✦ &nbsp;Create an Account</a>

      <div class="card-bottom-note">
        CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span><br>
        College of Engineering, Architecture &amp; Technology
      </div>

    </div>
  </div>
</div>

<script>
  // Password show/hide
  const pwField  = document.getElementById('password');
  const pwToggle = document.getElementById('pwToggle');
  pwToggle.addEventListener('click', function () {
    if (pwField.type === 'password') { pwField.type = 'text'; this.textContent = '🙈'; }
    else { pwField.type = 'password'; this.textContent = '👁'; }
  });

  // Loading spinner on submit
  document.getElementById('loginForm').addEventListener('submit', function () {
    const btn     = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const btnText = document.getElementById('btnText');
    btn.classList.add('loading');
    spinner.style.display = 'block';
    btnText.textContent   = 'Signing in…';
  });
</script>
</body>
</html>
