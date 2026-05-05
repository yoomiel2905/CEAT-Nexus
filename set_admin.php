<?php
// ── set_admin.php ─────────────────────────────────────────────────────────────
// ONE-TIME SETUP SCRIPT — Run this ONCE in your browser, then DELETE it.
// It adds an IS_ADMIN column to STUDENTS and marks a specific student as admin.
// ─────────────────────────────────────────────────────────────────────────────
/* USE THIS ONLY IF SETTING ADMIN, REMOVE /* AND THIS MESSAGE.

$serverName = ".\\SQLEXPRESS";
$connectionOptions = ["Database" => "PortalDB", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die("Connection failed: " . print_r(sqlsrv_errors(), true));

$steps = [];

// Step 1: Add IS_ADMIN column if it doesn't exist
$check = sqlsrv_query($conn, "SELECT COUNT(*) AS C FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='STUDENTS' AND COLUMN_NAME='IS_ADMIN'");
$row   = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);

if ($row['C'] == 0) {
    $r = sqlsrv_query($conn, "ALTER TABLE STUDENTS ADD IS_ADMIN BIT NOT NULL DEFAULT 0");
    $steps[] = $r !== false ? "✅ Added IS_ADMIN column to STUDENTS table." : "❌ Failed to add column: " . sqlsrv_errors()[0]['message'];
} else {
    $steps[] = "ℹ️ IS_ADMIN column already exists.";
}

// Step 2: Grant admin to the student with this email
// ─── CHANGE THIS EMAIL to your own account ───────────────────────────────────
$adminEmail = "aikinejaynhaloot@gmail.com";
// ─────────────────────────────────────────────────────────────────────────────

$r = sqlsrv_query($conn, "UPDATE STUDENTS SET IS_ADMIN = 1 WHERE EMAIL = ?", [$adminEmail]);
if ($r === false) {
    $steps[] = "❌ Failed to grant admin: " . sqlsrv_errors()[0]['message'];
} else {
    $affected = sqlsrv_rows_affected($r);
    if ($affected > 0) {
        $steps[] = "✅ Admin granted to: <strong>" . htmlspecialchars($adminEmail) . "</strong>";
    } else {
        $steps[] = "⚠️ No student found with email: <strong>" . htmlspecialchars($adminEmail) . "</strong> — check spelling.";
    }
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CEAT NEXUS — Set Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0c1f0c; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 32px 36px; width: 500px; }
    h2 { font-size: 1.1rem; margin-bottom: 20px; color: #8de88d; }
    .step { padding: 10px 14px; background: rgba(255,255,255,0.04); border-radius: 8px; margin-bottom: 10px; font-size: 0.85rem; line-height: 1.5; border-left: 3px solid rgba(92,184,92,0.4); }
    .warn { background: rgba(243,156,18,0.1); border-color: rgba(243,156,18,0.5); color: #f9c74f; }
    .note { margin-top: 20px; font-size: 0.75rem; color: rgba(255,255,255,0.35); line-height: 1.6; border-top: 1px solid rgba(255,255,255,0.07); padding-top: 16px; }
    .note strong { color: #ff8a80; }
    a { color: #5cb85c; }
  </style>
</head>
<body>
  <div class="card">
    <h2>⚙ CEAT NEXUS — Admin Setup</h2>
    <?php foreach ($steps as $step): ?>
      <div class="step <?= strpos($step,'⚠') !== false ? 'warn' : '' ?>"><?= $step ?></div>
    <?php endforeach; ?>
    <div class="note">
      <strong>⚠ Important:</strong> Delete this file from your server immediately after use.<br><br>
      Now update <code>login.php</code> to load <code>IS_ADMIN</code> from the database and set <code>$_SESSION['is_admin']</code> — see instructions below or use the updated login.php provided.<br><br>
      <a href="login.php">→ Go to Login</a>
    </div>
  </div>
</body>
</html>


