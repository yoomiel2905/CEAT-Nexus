<?php
// ── DB CONFIG (same as dashboard.php) ──────────────────────────────────────
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "PortalDB",
    "Uid"      => "",
    "PWD"      => ""
];

$errors   = [];
$success  = false;
$formData = ["firstname" => "", "lastname" => "", "email" => "", "id_number" => "", "program" => ""];

// ── HANDLE FORM SUBMISSION ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize inputs
    $firstname = trim($_POST["firstname"] ?? "");
    $lastname  = trim($_POST["lastname"]  ?? "");
    $email     = trim($_POST["email"]     ?? "");
    $id_number = trim($_POST["id_number"] ?? "");
    $program   = trim($_POST["program"]   ?? "");
    $password  = $_POST["password"]  ?? "";
    $confirm   = $_POST["confirm"]   ?? "";

    // Keep form data for repopulation on error
    $formData = compact("firstname", "lastname", "email", "id_number", "program");

    // ── Validation ────────────────────────────────────────────────────────
    if (empty($firstname)) $errors[] = "First name is required.";
    if (empty($lastname))  $errors[] = "Last name is required.";

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($id_number)) {
        $errors[] = "Student ID number is required.";
    } elseif (!preg_match('/^\d{7}$/', $id_number)) {
        $errors[] = "Student ID must be exactly 7 digits (e.g. 2412345).";
    }

    if (empty($program)) $errors[] = "Please select your program.";

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    // ── DB Operations (only if no validation errors) ───────────────────────
    if (empty($errors)) {
        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            $errors[] = "Database connection failed. Please try again later.";
        } else {
            // Check for duplicate email or ID
            $checkSql    = "SELECT COUNT(*) AS CNT FROM STUDENTS WHERE EMAIL = ? OR ID_NUMBER = ?";
            $checkParams = [$email, $id_number];
            $checkResult = sqlsrv_query($conn, $checkSql, $checkParams);

            if ($checkResult === false) {
                $errors[] = "Database error during duplicate check.";
            } else {
                $checkRow = sqlsrv_fetch_array($checkResult, SQLSRV_FETCH_ASSOC);
                if ($checkRow["CNT"] > 0) {
                    $errors[] = "An account with this email or Student ID already exists.";
                } else {
                    // Hash password and insert
                    $hashedPw = password_hash($password, PASSWORD_BCRYPT);

                    $insertSql = "INSERT INTO STUDENTS (FIRST_NAME, LAST_NAME, EMAIL, ID_NUMBER, PROGRAM, PASSWORD_HASH, CREATED_AT)
                                  VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                    $insertParams = [$firstname, $lastname, $email, $id_number, $program, $hashedPw];
                    $insertResult = sqlsrv_query($conn, $insertSql, $insertParams);

                    if ($insertResult === false) {
                        $errors[] = "Registration failed: " . print_r(sqlsrv_errors(), true);
                    } else {
                        $success = true;
                    }
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
  <title>CEAT NEXUS — Register</title>
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
      display: flex;
      align-items: center;
      justify-content: center;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background: linear-gradient(160deg, rgba(8,24,8,0.80) 0%, rgba(10,31,10,0.72) 50%, rgba(5,18,5,0.86) 100%);
      z-index: 0; pointer-events: none;
    }

    body::after {
      content: '';
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(61,184,61,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(61,184,61,0.03) 1px, transparent 1px);
      background-size: 40px 40px;
      z-index: 0; pointer-events: none;
    }

    .wrapper {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh;
      padding: 32px 24px;
      animation: fadeUp 0.45s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .reg-card {
      width: 680px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 22px;
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      padding: 0 0 36px;
      box-shadow: 0 32px 80px rgba(0,0,0,0.55), 0 0 0 1px rgba(92,184,92,0.07);
      overflow: hidden;
    }

    .card-accent {
      height: 3px;
      background: linear-gradient(90deg, var(--dlsu-light), #3db83d, transparent);
    }

    .card-inner { padding: 32px 36px 0; }

    .logo-wrap {
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 20px;
    }

    .logo-wrap img {
      width: 580px; height: auto;
      filter: drop-shadow(0 4px 18px rgba(92,184,92,0.22));
    }

    .logo-fallback { display: none; align-items: center; gap: 10px; }

    .logo-mark {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
      border-radius: 11px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 16px rgba(92,184,92,0.4);
    }

    .logo-mark svg { width: 20px; height: 20px; fill: none; stroke: white; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
    .logo-text { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800; color: white; }
    .logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.58rem; font-weight: 400; color: var(--muted); letter-spacing: 0.12em; text-transform: uppercase; margin-top: 2px; }

    .card-eyebrow {
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--dlsu-pale);
      display: flex; align-items: center; justify-content: center; gap: 7px;
      margin-bottom: 5px;
    }
    .card-eyebrow::before, .card-eyebrow::after { content: '·'; color: var(--dlsu-light); font-size: 1.2em; }

    .card-title {
      font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800;
      color: white; text-align: center; letter-spacing: -0.02em; margin-bottom: 4px;
    }

    .card-sub {
      font-size: 0.76rem; color: var(--muted); font-weight: 300;
      text-align: center; line-height: 1.5; margin-bottom: 24px;
    }

    .divider { height: 1px; background: rgba(255,255,255,0.07); margin-bottom: 24px; }

    .alert {
      border-radius: 10px; padding: 12px 15px; margin-bottom: 20px;
      font-size: 0.8rem; line-height: 1.55;
    }

    .alert-error {
      background: rgba(220,53,69,0.14);
      border: 1px solid rgba(220,53,69,0.35);
      color: #ff8a95;
    }

    .alert-error ul { margin: 6px 0 0 16px; }
    .alert-error li { margin-bottom: 2px; }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px 16px;
    }

    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }

    label {
      font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.1em; color: rgba(255,255,255,0.55);
    }

    .input-wrap { position: relative; }

    .input-icon {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      font-size: 0.85rem; pointer-events: none;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select {
      width: 100%;
      padding: 10px 12px 10px 36px;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: 9px;
      color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.84rem; font-weight: 500;
      outline: none;
      transition: border-color 0.18s, background 0.18s;
      -webkit-appearance: none; appearance: none;
    }

    select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,0.4)' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 12px center;
      padding-right: 32px;
    }

    select option { background: #1a3d1a; color: white; }

    input:focus, select:focus {
      border-color: rgba(92,184,92,0.55);
      background: rgba(92,184,92,0.06);
    }

    input::placeholder { color: rgba(255,255,255,0.22); }

    .pw-strength-bar {
      height: 3px; border-radius: 3px; margin-top: 6px;
      background: rgba(255,255,255,0.08); overflow: hidden;
    }
    .pw-strength-fill { height: 100%; width: 0; border-radius: 3px; transition: width 0.3s, background 0.3s; }
    .pw-hint { font-size: 0.65rem; color: rgba(255,255,255,0.3); margin-top: 4px; }

    .btn-submit {
      display: flex; align-items: center; justify-content: center; gap: 9px;
      width: 100%; padding: 13px 20px; margin-top: 22px;
      background: var(--dlsu-light); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      box-shadow: 0 4px 18px rgba(92,184,92,0.35);
      transition: all 0.18s; letter-spacing: 0.02em;
    }

    .btn-submit:hover {
      background: #6ecf6e; transform: translateY(-2px);
      box-shadow: 0 8px 26px rgba(92,184,92,0.5);
    }

    .card-footer-note {
      margin-top: 18px; font-size: 0.72rem;
      color: rgba(255,255,255,0.42); text-align: center; line-height: 1.6;
    }

    .card-footer-note a { color: var(--dlsu-light); font-weight: 700; text-decoration: none; transition: color 0.15s; }
    .card-footer-note a:hover { color: #8de88d; }

    .card-bottom-note {
      margin-top: 20px; font-size: 0.62rem;
      color: rgba(255,255,255,0.18); text-align: center; line-height: 1.5;
    }
    .card-bottom-note span { color: rgba(92,184,92,0.55); font-weight: 600; }

    /* Success state */
    .success-body { text-align: center; padding: 16px 0 8px; }
    .success-icon { font-size: 3rem; margin-bottom: 14px; filter: drop-shadow(0 0 20px rgba(92,184,92,0.5)); }
    .success-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: white; margin-bottom: 8px; }
    .success-sub { font-size: 0.82rem; color: var(--muted); line-height: 1.6; margin-bottom: 20px; }

    .btn-login {
      display: inline-flex; align-items: center; justify-content: center; gap: 9px;
      padding: 12px 32px;
      background: var(--dlsu-light); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.88rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer; text-decoration: none;
      box-shadow: 0 4px 18px rgba(92,184,92,0.35);
      transition: all 0.18s;
    }
    .btn-login:hover { background: #6ecf6e; transform: translateY(-2px); color: white; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="reg-card">
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

      <div class="card-eyebrow">Create Account</div>
      <div class="card-title">Join CEAT NEXUS</div>
      <p class="card-sub">Register with your DLSU-D student credentials to get access.</p>

      <div class="divider"></div>

      <?php if ($success): ?>
      <!-- SUCCESS -->
      <div class="success-body">
        <div class="success-icon">✅</div>
        <div class="success-title">You're registered!</div>
        <p class="success-sub">
          Your CEAT NEXUS account has been created successfully.<br>
          You can now log in and explore your student hub.
        </p>
        <a href="login.php" class="btn-login">→ &nbsp;Go to Login</a>
      </div>

      <?php else: ?>
      <!-- FORM -->

      <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <strong>⚠ Please fix the following:</strong>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" action="register.php" autocomplete="off">

        <div class="form-grid">

          <div class="form-group">
            <label for="firstname">First Name</label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input type="text" id="firstname" name="firstname" placeholder="e.g. Juan"
                     value="<?= htmlspecialchars($formData['firstname']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="lastname">Last Name</label>
            <div class="input-wrap">
              <span class="input-icon">👤</span>
              <input type="text" id="lastname" name="lastname" placeholder="e.g. dela Cruz"
                     value="<?= htmlspecialchars($formData['lastname']) ?>" required>
            </div>
          </div>

          <div class="form-group full">
            <label for="email">DLSU-D Email Address</label>
            <div class="input-wrap">
              <span class="input-icon">✉️</span>
              <input type="email" id="email" name="email" placeholder="yourid@dlsud.edu.ph"
                     value="<?= htmlspecialchars($formData['email']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="id_number">Student ID Number</label>
            <div class="input-wrap">
              <span class="input-icon">🪪</span>
              <input type="text" id="id_number" name="id_number" placeholder="7-digit ID e.g. 2412345"
                     maxlength="7" pattern="\d{7}"
                     value="<?= htmlspecialchars($formData['id_number']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="program">Program</label>
            <div class="input-wrap">
              <span class="input-icon">🎓</span>
              <select id="program" name="program" required>
                <option value="" disabled <?= empty($formData['program']) ? 'selected' : '' ?>>Select your program</option>
                <optgroup label="Engineering">
                  <option value="BSCE"   <?= $formData['program']==='BSCE'   ? 'selected':'' ?>>BS Civil Engineering</option>
                  <option value="BSEE"   <?= $formData['program']==='BSEE'   ? 'selected':'' ?>>BS Electrical Engineering</option>
                  <option value="BSME"   <?= $formData['program']==='BSME'   ? 'selected':'' ?>>BS Mechanical Engineering</option>
                  <option value="BSECE"  <?= $formData['program']==='BSECE'  ? 'selected':'' ?>>BS Electronics Engineering</option>
                  <option value="BSIE"   <?= $formData['program']==='BSIE'   ? 'selected':'' ?>>BS Industrial Engineering</option>
                  <option value="BSCHE"  <?= $formData['program']==='BSCHE'  ? 'selected':'' ?>>BS Chemical Engineering</option>
                </optgroup>
                <optgroup label="Architecture">
                  <option value="BSArch" <?= $formData['program']==='BSArch' ? 'selected':'' ?>>BS Architecture</option>
                </optgroup>
                <optgroup label="Technology">
                  <option value="BSIT"   <?= $formData['program']==='BSIT'   ? 'selected':'' ?>>BS Information Technology</option>
                  <option value="BSCpE"  <?= $formData['program']==='BSCpE'  ? 'selected':'' ?>>BS Computer Engineering</option>
                </optgroup>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
            </div>
            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
            <div class="pw-hint" id="pw-hint">Enter a password</div>
          </div>

          <div class="form-group">
            <label for="confirm">Confirm Password</label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" required>
            </div>
          </div>

        </div><!-- /form-grid -->

        <button type="submit" class="btn-submit">✦ &nbsp;Create My Account</button>

      </form>

      <p class="card-footer-note">
        Already have an account? <a href="login.php">Login here →</a>
      </p>

      <?php endif; ?>

      <div class="card-bottom-note">
        CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span><br>
        College of Engineering, Architecture &amp; Technology
      </div>

    </div><!-- /card-inner -->
  </div><!-- /reg-card -->
</div><!-- /wrapper -->

<script>
  const pwInput  = document.getElementById('password');
  const pwFill   = document.getElementById('pw-fill');
  const pwHint   = document.getElementById('pw-hint');

  pwInput.addEventListener('input', function () {
    const val = this.value;
    let score = 0, hint = '';

    if (val.length === 0) { hint = 'Enter a password'; }
    else if (val.length < 6) { score = 1; hint = 'Too short'; }
    else {
      if (val.length >= 8)            score++;
      if (/[A-Z]/.test(val))          score++;
      if (/[0-9]/.test(val))          score++;
      if (/[^A-Za-z0-9]/.test(val))   score++;

      hint = ['', 'Weak', 'Fair', 'Good', 'Strong ✓'][score] || 'Weak';
    }

    const widths = ['0%','25%','50%','75%','100%'];
    const colors = ['transparent','#e74c3c','#f39c12','#3498db','#5cb85c'];

    pwFill.style.width      = widths[score] || '0%';
    pwFill.style.background = colors[score] || 'transparent';
    pwHint.textContent      = hint;
    pwHint.style.color      = colors[score] || 'rgba(255,255,255,0.3)';
  });

  const confirmInput = document.getElementById('confirm');
  confirmInput.addEventListener('input', function () {
    if (!this.value) { this.style.borderColor = ''; }
    else if (this.value === pwInput.value) { this.style.borderColor = 'rgba(92,184,92,0.6)'; }
    else { this.style.borderColor = 'rgba(220,53,69,0.6)'; }
  });

  document.getElementById('id_number').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g,'').slice(0,7);
  });
</script>
</body>
</html>