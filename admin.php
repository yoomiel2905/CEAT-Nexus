<?php
// ── SESSION GUARD — ADMIN ONLY ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: dashboard.php");
    exit;
}

$adminName    = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$adminInitials = strtoupper(substr($_SESSION['first_name'],0,1) . substr($_SESSION['last_name'],0,1));

// ── DB CONFIG ─────────────────────────────────────────────────────────────────
$serverName = ".\\SQLEXPRESS";
$connectionOptions = ["Database" => "PortalDB", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die(print_r(sqlsrv_errors(), true));

// ── ACTIVE TAB ────────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';

// ══════════════════════════════════════════════════════════════════════════════
// HANDLE POST ACTIONS (edit / delete)
// ══════════════════════════════════════════════════════════════════════════════
$flash = "";
$flashType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table  = $_POST['table']  ?? '';

    // ── DELETE ──────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $pkCol = $_POST['pk_col'] ?? '';
        $pkVal = $_POST['pk_val'] ?? '';
        if ($table && $pkCol && $pkVal) {
            $sql = "DELETE FROM $table WHERE $pkCol = ?";
            $res = sqlsrv_query($conn, $sql, [$pkVal]);
            if ($res === false) {
                $flashType = "error";
                $flash = "Delete failed: " . sqlsrv_errors()[0]['message'];
            } else {
                $flash = "Record deleted successfully.";
            }
        }
    }

    // ── EDIT STUDENT ────────────────────────────────────────────────────────
    if ($action === 'edit_student') {
        $sid      = $_POST['STUDENT_ID']  ?? '';
        $sno      = $_POST['STUDENT_NO']  ?? '';
        $fname    = $_POST['FIRST_NAME']  ?? '';
        $lname    = $_POST['LAST_NAME']   ?? '';
        $email    = $_POST['EMAIL']       ?? '';
        $program  = $_POST['PROGRAM']     ?? '';
        $year     = $_POST['YEAR_LEVEL']  ?? 1;
        $status   = $_POST['STATUS']      ?? 'active';

        $sql = "UPDATE STUDENTS SET STUDENT_NO=?, FIRST_NAME=?, LAST_NAME=?, EMAIL=?, PROGRAM=?, YEAR_LEVEL=?, STATUS=? WHERE STUDENT_ID=?";
        $res = sqlsrv_query($conn, $sql, [$sno, $fname, $lname, $email, $program, (int)$year, $status, $sid]);
        if ($res === false) {
            $flashType = "error";
            $flash = "Update failed: " . sqlsrv_errors()[0]['message'];
        } else {
            $flash = "Student updated successfully.";
        }
        $tab = 'students';
    }

    // ── EDIT EVENT ──────────────────────────────────────────────────────────
    if ($action === 'edit_event') {
        $eid   = $_POST['EVENT_ID']    ?? '';
        $title = $_POST['EVENT_TITLE'] ?? '';
        $desc  = $_POST['DESCRIPTION'] ?? '';
        $date  = $_POST['EVENT_DATE']  ?? '';
        $loc   = $_POST['LOCATION']    ?? '';

        $sql = "UPDATE EVENTS SET EVENT_TITLE=?, DESCRIPTION=?, EVENT_DATE=?, LOCATION=? WHERE EVENT_ID=?";
        $res = sqlsrv_query($conn, $sql, [$title, $desc, $date, $loc, $eid]);
        if ($res === false) {
            $flashType = "error";
            $flash = "Update failed: " . sqlsrv_errors()[0]['message'];
        } else {
            $flash = "Event updated successfully.";
        }
        $tab = 'events';
    }

    // ── ADD EVENT ───────────────────────────────────────────────────────────
    if ($action === 'add_event') {
        $title = $_POST['EVENT_TITLE'] ?? '';
        $desc  = $_POST['DESCRIPTION'] ?? '';
        $date  = $_POST['EVENT_DATE']  ?? '';
        $loc   = $_POST['LOCATION']    ?? '';

        $sql = "INSERT INTO EVENTS (EVENT_TITLE, DESCRIPTION, EVENT_DATE, LOCATION) VALUES (?,?,?,?)";
        $res = sqlsrv_query($conn, $sql, [$title, $desc, $date, $loc]);
        if ($res === false) {
            $flashType = "error";
            $flash = "Add failed: " . sqlsrv_errors()[0]['message'];
        } else {
            $flash = "Event added successfully.";
        }
        $tab = 'events';
    }

    // ── ADD ANNOUNCEMENT ────────────────────────────────────────────────────
    if ($action === 'add_announcement') {
        $title   = $_POST['TITLE']   ?? '';
        $content = $_POST['CONTENT'] ?? '';
        $sql = "INSERT INTO ANNOUNCEMENTS (TITLE, CONTENT, DATE_POSTED) VALUES (?,?,GETDATE())";
        $res = sqlsrv_query($conn, $sql, [$title, $content]);
        if ($res === false) {
            $flashType = "error";
            $flash = "Add failed: " . sqlsrv_errors()[0]['message'];
        } else {
            $flash = "Announcement posted.";
        }
        $tab = 'announcements';
    }

    // Redirect to prevent re-POST on refresh
    header("Location: admin.php?tab=$tab" . ($flash ? "&flash=" . urlencode($flash) . "&ft=$flashType" : ""));
    exit;
}

// Pick up flash from redirect
if (isset($_GET['flash']))    $flash     = $_GET['flash'];
if (isset($_GET['ft']))       $flashType = $_GET['ft'];

// ══════════════════════════════════════════════════════════════════════════════
// FETCH DATA PER TAB
// ══════════════════════════════════════════════════════════════════════════════

// Overview stats
$stats = [];
foreach ([
    'students'      => "SELECT COUNT(*) AS C FROM STUDENTS",
    'forum_posts'   => "SELECT COUNT(*) AS C FROM FORUM_POSTS",
    'files'         => "SELECT COUNT(*) AS C FROM FILES",
    'events'        => "SELECT COUNT(*) AS C FROM EVENTS",
    'announcements' => "SELECT COUNT(*) AS C FROM ANNOUNCEMENTS",
    'faculty'       => "SELECT COUNT(*) AS C FROM FACULTY",
] as $key => $sql) {
    $r = sqlsrv_query($conn, $sql);
    $row = $r ? sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC) : ['C' => 0];
    $stats[$key] = $row['C'] ?? 0;
}

// Students list
$students = [];
if ($tab === 'students' || $tab === 'overview') {
    $r = sqlsrv_query($conn, "SELECT TOP 100 STUDENT_ID, STUDENT_NO, FIRST_NAME, LAST_NAME, EMAIL, PROGRAM, YEAR_LEVEL, STATUS, DATE_CREATED FROM STUDENTS ORDER BY DATE_CREATED DESC");
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_CREATED'] instanceof DateTime) $row['DATE_CREATED'] = $row['DATE_CREATED']->format('M d, Y');
        $students[] = $row;
    }
}

// Forum posts
$posts = [];
if ($tab === 'forum') {
    $r = sqlsrv_query($conn, "SELECT TOP 100 POST_ID, STUDENT_ID, TITLE, CONTENT, DATE_POSTED FROM FORUM_POSTS ORDER BY DATE_POSTED DESC");
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_POSTED'] instanceof DateTime) $row['DATE_POSTED'] = $row['DATE_POSTED']->format('M d, Y');
        $posts[] = $row;
    }
}

// Events
$events = [];
if ($tab === 'events') {
    $r = sqlsrv_query($conn, "SELECT TOP 100 EVENT_ID, EVENT_TITLE, DESCRIPTION, EVENT_DATE, LOCATION FROM EVENTS ORDER BY EVENT_DATE DESC");
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['EVENT_DATE'] instanceof DateTime) $row['EVENT_DATE'] = $row['EVENT_DATE']->format('Y-m-d');
        $events[] = $row;
    }
}

// Announcements
$announcements = [];
if ($tab === 'announcements') {
    $r = sqlsrv_query($conn, "SELECT TOP 100 ANNOUNCEMENT_ID, TITLE, CONTENT, DATE_POSTED FROM ANNOUNCEMENTS ORDER BY DATE_POSTED DESC");
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_POSTED'] instanceof DateTime) $row['DATE_POSTED'] = $row['DATE_POSTED']->format('M d, Y');
        $announcements[] = $row;
    }
}

// Files
$files = [];
if ($tab === 'files') {
    $r = sqlsrv_query($conn, "SELECT TOP 100 FILE_ID, FILE_NAME, DESCRIPTION, UPLOADED_BY, DATE_UPLOADED FROM FILES ORDER BY DATE_UPLOADED DESC");
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_UPLOADED'] instanceof DateTime) $row['DATE_UPLOADED'] = $row['DATE_UPLOADED']->format('M d, Y');
        $files[] = $row;
    }
}

sqlsrv_close($conn);

// ── Programs list for dropdown ─────────────────────────────────────────────
$programs = ['BSCpE','BSCE','BSArch','BSME','BSECE','BSIE','BSEE','BSMA'];
$programLabels = [
    'BSCpE'  => 'Computer Engineering',
    'BSCE'   => 'Civil Engineering',
    'BSArch' => 'Architecture',
    'BSME'   => 'Mechanical Engineering',
    'BSECE'  => 'Electronics Engineering',
    'BSIE'   => 'Industrial Engineering',
    'BSEE'   => 'Electrical Engineering',
    'BSMA'   => 'Multimedia Arts',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CEAT NEXUS — Admin Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dlsu-dark:   #1a3d1a;
      --dlsu-mid:    #2a5c2a;
      --dlsu-green:  #3a8c3a;
      --dlsu-light:  #5cb85c;
      --dlsu-pale:   #a8d8a8;
      --white:       #ffffff;
      --muted:       rgba(255,255,255,0.65);
      --sidebar-w:   230px;
      --danger:      #e74c3c;
      --warning:     #f39c12;
      --info:        #3498db;
      --card-bg:     rgba(15,40,15,0.88);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; }

    body {
      background: url('images/bg.png') no-repeat center center fixed;
      background-size: cover;
      display: flex; flex-direction: column;
      min-height: 100vh; color: #fff;
    }

    body::before {
      content: ''; position: fixed; inset: 0;
      background: linear-gradient(180deg, rgba(8,22,8,0.88) 0%, rgba(12,32,12,0.82) 60%, rgba(6,18,6,0.92) 100%);
      z-index: 0; pointer-events: none;
    }

    body::after {
      content: ''; position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(61,184,61,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(61,184,61,0.025) 1px, transparent 1px);
      background-size: 40px 40px;
      z-index: 0; pointer-events: none;
    }

    .page { position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* ── TOP NAV ── */
    .topnav {
      background: rgba(8,22,8,0.96);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 28px; height: 56px; flex-shrink: 0;
      position: sticky; top: 0; z-index: 200;
    }

    .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }

    .nav-logo-mark {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 14px rgba(92,184,92,0.4);
    }

    .nav-logo-mark svg { width: 17px; height: 17px; fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

    .nav-logo-text { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; letter-spacing: -0.01em; }
    .nav-logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.56rem; font-weight: 400; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 1px; }

    .admin-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 20px;
      background: rgba(231,76,60,0.18); border: 1px solid rgba(231,76,60,0.4);
      font-size: 0.62rem; font-weight: 800; color: #ff8a80;
      letter-spacing: 0.1em; text-transform: uppercase;
    }

    .admin-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #e74c3c; box-shadow: 0 0 6px #e74c3c; }

    .nav-right { display: flex; align-items: center; gap: 10px; }

    .nav-user { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--muted); }

    .nav-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      border: 2px solid rgba(231,76,60,0.5);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; font-weight: 800;
      box-shadow: 0 0 10px rgba(231,76,60,0.3);
      text-decoration: none; color: white;
    }

    .nav-btn-link {
      padding: 6px 14px; border-radius: 7px;
      background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.65); font-size: 0.75rem; font-weight: 600;
      text-decoration: none; transition: all 0.16s;
    }
    .nav-btn-link:hover { background: rgba(255,255,255,0.12); color: white; }

    /* ── BODY LAYOUT ── */
    .body-wrap { display: flex; flex: 1; min-height: 0; }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w); flex-shrink: 0;
      background: rgba(8,22,8,0.92);
      backdrop-filter: blur(14px);
      border-right: 1px solid rgba(255,255,255,0.07);
      display: flex; flex-direction: column;
      overflow-y: auto;
    }

    .sidebar-header {
      padding: 18px 16px 12px;
      border-bottom: 1px solid rgba(255,255,255,0.07);
      font-size: 0.58rem; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: rgba(255,255,255,0.3);
      display: flex; align-items: center; gap: 7px;
    }
    .sidebar-header::before { content: ''; width: 14px; height: 2px; background: #e74c3c; border-radius: 2px; }

    .nav-section {
      padding: 14px 12px 5px;
      font-size: 0.56rem; font-weight: 700; letter-spacing: 0.16em;
      text-transform: uppercase; color: rgba(255,255,255,0.22);
    }

    .side-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 14px; margin: 1px 6px; border-radius: 9px;
      cursor: pointer; font-size: 0.8rem; font-weight: 500;
      color: rgba(255,255,255,0.5); transition: all 0.16s;
      text-decoration: none; position: relative;
    }
    .side-item:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
    .side-item.active {
      background: linear-gradient(135deg, rgba(231,76,60,0.2), rgba(231,76,60,0.08));
      color: #ff8a80; font-weight: 700;
    }
    .side-item.active::before {
      content: ''; position: absolute; left: -6px; top: 50%; transform: translateY(-50%);
      width: 3px; height: 55%; background: #e74c3c; border-radius: 0 3px 3px 0;
    }
    .side-icon { font-size: 0.95rem; width: 18px; text-align: center; flex-shrink: 0; }

    .side-count {
      margin-left: auto; background: rgba(231,76,60,0.25);
      color: #ff8a80; font-size: 0.6rem; font-weight: 800;
      padding: 2px 7px; border-radius: 10px;
    }

    .sidebar-footer {
      margin-top: auto; padding: 14px;
      border-top: 1px solid rgba(255,255,255,0.07);
      display: flex; align-items: center; gap: 9px;
    }
    .sf-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; font-weight: 800;
      border: 2px solid rgba(231,76,60,0.4); flex-shrink: 0;
    }
    .sf-name { font-size: 0.74rem; font-weight: 700; color: rgba(255,255,255,0.8); }
    .sf-role { font-size: 0.6rem; color: #ff8a80; margin-top: 1px; font-weight: 600; }

    /* ── MAIN ── */
    .main {
      flex: 1; overflow-y: auto; padding: 32px 36px;
      scrollbar-width: thin; scrollbar-color: rgba(92,184,92,0.3) transparent;
    }

    /* ── PAGE HEADER ── */
    .page-header {
      display: flex; align-items: flex-end; justify-content: space-between;
      margin-bottom: 28px; padding-bottom: 20px;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .page-eyebrow {
      font-size: 0.6rem; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: #ff8a80; margin-bottom: 4px;
      display: flex; align-items: center; gap: 7px;
    }
    .page-eyebrow::before { content: ''; width: 14px; height: 2px; background: #e74c3c; border-radius: 2px; }
    .page-title { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }

    /* ── FLASH ── */
    .flash {
      padding: 12px 16px; border-radius: 10px;
      font-size: 0.82rem; margin-bottom: 22px;
      display: flex; align-items: center; gap: 10px;
    }
    .flash.success { background: rgba(92,184,92,0.14); border: 1px solid rgba(92,184,92,0.3); color: #8de88d; }
    .flash.error   { background: rgba(231,76,60,0.14); border: 1px solid rgba(231,76,60,0.3); color: #ff8a80; }

    /* ── STAT CARDS ── */
    .stat-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
      margin-bottom: 32px;
    }
    .stat-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 14px; padding: 20px 22px;
      display: flex; align-items: center; gap: 16px;
      transition: background 0.18s;
    }
    .stat-card:hover { background: rgba(255,255,255,0.08); }
    .stat-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0;
    }
    .si-green  { background: rgba(92,184,92,0.15); }
    .si-red    { background: rgba(231,76,60,0.15); }
    .si-blue   { background: rgba(52,152,219,0.15); }
    .si-gold   { background: rgba(243,156,18,0.15); }
    .si-purple { background: rgba(155,89,182,0.15); }
    .si-teal   { background: rgba(26,188,156,0.15); }

    .stat-val { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; line-height: 1; color: white; }
    .stat-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-top: 3px; }

    /* ── SECTION HEADER ── */
    .section-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 16px;
    }
    .section-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; }
    .section-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 8px; border: none;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.78rem; font-weight: 700; cursor: pointer;
      transition: all 0.16s; text-decoration: none; letter-spacing: 0.02em;
    }
    .btn-green  { background: var(--dlsu-light); color: white; box-shadow: 0 3px 12px rgba(92,184,92,0.3); }
    .btn-green:hover  { background: #6ecf6e; transform: translateY(-1px); }
    .btn-red    { background: rgba(231,76,60,0.18); color: #ff8a80; border: 1px solid rgba(231,76,60,0.3); }
    .btn-red:hover    { background: rgba(231,76,60,0.3); }
    .btn-ghost  { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.12); }
    .btn-ghost:hover  { background: rgba(255,255,255,0.12); color: white; }
    .btn-sm { padding: 5px 11px; font-size: 0.7rem; }

    /* ── TABLE ── */
    .table-wrap {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px; overflow: hidden;
    }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 12px 16px; text-align: left;
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.12em;
      text-transform: uppercase; color: rgba(255,255,255,0.35);
      background: rgba(255,255,255,0.03);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      white-space: nowrap;
    }
    tbody tr { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.14s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,0.04); }
    tbody td { padding: 11px 16px; font-size: 0.8rem; color: rgba(255,255,255,0.8); vertical-align: middle; }

    .td-actions { display: flex; align-items: center; gap: 6px; }

    .status-badge {
      display: inline-block; padding: 2px 9px; border-radius: 20px;
      font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
    }
    .status-active   { background: rgba(92,184,92,0.18); color: #8de88d; }
    .status-inactive { background: rgba(231,76,60,0.18); color: #ff8a80; }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.72); z-index: 1000;
      align-items: center; justify-content: center;
      backdrop-filter: blur(4px);
    }
    .modal-overlay.open { display: flex; }

    .modal {
      background: rgba(12,32,12,0.97);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 18px; padding: 0;
      width: 520px; max-width: 95vw; max-height: 90vh;
      overflow-y: auto; box-shadow: 0 32px 80px rgba(0,0,0,0.6);
      animation: modalIn 0.2s ease;
    }
    @keyframes modalIn { from { opacity:0; transform: scale(0.96) translateY(10px); } to { opacity:1; transform: none; } }

    .modal-header {
      padding: 20px 24px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      display: flex; align-items: center; justify-content: space-between;
    }
    .modal-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; }
    .modal-close {
      width: 28px; height: 28px; border-radius: 7px;
      background: rgba(255,255,255,0.08); border: none;
      color: rgba(255,255,255,0.6); font-size: 1rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s;
    }
    .modal-close:hover { background: rgba(255,255,255,0.16); color: white; }

    .modal-body { padding: 20px 24px 24px; }

    /* ── FORM FIELDS ── */
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.5); }

    .form-input, .form-select, .form-textarea {
      width: 100%; padding: 10px 12px;
      background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
      border-radius: 8px; color: white;
      font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.83rem; font-weight: 500;
      outline: none; transition: border-color 0.16s, background 0.16s;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
      border-color: rgba(92,184,92,0.5); background: rgba(92,184,92,0.05);
    }
    .form-input::placeholder, .form-textarea::placeholder { color: rgba(255,255,255,0.2); }
    .form-select { -webkit-appearance: none; appearance: none; cursor: pointer; }
    .form-select option { background: #1a3d1a; }
    .form-textarea { resize: vertical; min-height: 90px; }

    .modal-footer {
      display: flex; align-items: center; justify-content: flex-end; gap: 10px;
      padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.07); margin-top: 8px;
    }

    /* ── CONFIRM DELETE MODAL ── */
    .confirm-text { font-size: 0.88rem; color: var(--muted); line-height: 1.6; margin-bottom: 20px; }
    .confirm-text strong { color: white; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align: center; padding: 48px 24px; color: rgba(255,255,255,0.25); }
    .empty-state .es-icon { font-size: 2.5rem; margin-bottom: 10px; }
    .empty-state .es-text { font-size: 0.82rem; }

    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(92,184,92,0.25); border-radius: 4px; }
  </style>
</head>
<body>
<div class="page">

  <!-- ── TOP NAV ────────────────────────────────────────────────────────── -->
  <nav class="topnav">
    <a class="nav-logo" href="dashboard.php">
      <div class="nav-logo-mark">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="nav-logo-text">
        CEAT NEXUS
        <small>Admin Panel</small>
      </div>
    </a>

    <div class="admin-badge">⚙ Administrator</div>

    <div class="nav-right">
      <a href="dashboard.php" class="nav-btn-link">← Back to Dashboard</a>
      <span class="nav-user"><?php echo htmlspecialchars($adminName); ?></span>
      <a href="logout.php" class="nav-avatar" title="Sign out"><?php echo htmlspecialchars($adminInitials); ?></a>
    </div>
  </nav>

  <!-- ── BODY ───────────────────────────────────────────────────────────── -->
  <div class="body-wrap">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
      <div class="sidebar-header">Admin Control</div>

      <div class="nav-section">Monitor</div>
      <a href="admin.php?tab=overview" class="side-item <?= $tab==='overview' ? 'active' : '' ?>">
        <span class="side-icon">📊</span>Overview
      </a>
      <a href="admin.php?tab=students" class="side-item <?= $tab==='students' ? 'active' : '' ?>">
        <span class="side-icon">🎓</span>Students
        <span class="side-count"><?= $stats['students'] ?></span>
      </a>
      <a href="admin.php?tab=forum" class="side-item <?= $tab==='forum' ? 'active' : '' ?>">
        <span class="side-icon">💬</span>Forum Posts
        <span class="side-count"><?= $stats['forum_posts'] ?></span>
      </a>
      <a href="admin.php?tab=files" class="side-item <?= $tab==='files' ? 'active' : '' ?>">
        <span class="side-icon">📂</span>Files
        <span class="side-count"><?= $stats['files'] ?></span>
      </a>

      <div class="nav-section">Manage</div>
      <a href="admin.php?tab=events" class="side-item <?= $tab==='events' ? 'active' : '' ?>">
        <span class="side-icon">📅</span>Events
      </a>
      <a href="admin.php?tab=announcements" class="side-item <?= $tab==='announcements' ? 'active' : '' ?>">
        <span class="side-icon">📢</span>Announcements
      </a>

      <div class="sidebar-footer">
        <div class="sf-avatar"><?php echo htmlspecialchars($adminInitials); ?></div>
        <div>
          <div class="sf-name"><?php echo htmlspecialchars($adminName); ?></div>
          <div class="sf-role">Administrator</div>
        </div>
      </div>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <div class="main">

      <?php if ($flash): ?>
      <div class="flash <?= htmlspecialchars($flashType) ?>">
        <?= $flashType === 'success' ? '✅' : '⚠' ?> &nbsp;<?= htmlspecialchars($flash) ?>
      </div>
      <?php endif; ?>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php if ($tab === 'overview'): ?>
      <!-- ── OVERVIEW ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Admin Panel</div>
          <div class="page-title">Overview</div>
        </div>
      </div>

      <div class="stat-grid">
        <a href="admin.php?tab=students" class="stat-card" style="text-decoration:none;">
          <div class="stat-icon si-green">🎓</div>
          <div><div class="stat-val"><?= number_format($stats['students']) ?></div><div class="stat-label">Registered Students</div></div>
        </a>
        <a href="admin.php?tab=forum" class="stat-card" style="text-decoration:none;">
          <div class="stat-icon si-blue">💬</div>
          <div><div class="stat-val"><?= number_format($stats['forum_posts']) ?></div><div class="stat-label">Forum Posts</div></div>
        </a>
        <a href="admin.php?tab=files" class="stat-card" style="text-decoration:none;">
          <div class="stat-icon si-gold">📂</div>
          <div><div class="stat-val"><?= number_format($stats['files']) ?></div><div class="stat-label">Uploaded Files</div></div>
        </a>
        <a href="admin.php?tab=events" class="stat-card" style="text-decoration:none;">
          <div class="stat-icon si-purple">📅</div>
          <div><div class="stat-val"><?= number_format($stats['events']) ?></div><div class="stat-label">Events</div></div>
        </a>
        <a href="admin.php?tab=announcements" class="stat-card" style="text-decoration:none;">
          <div class="stat-icon si-red">📢</div>
          <div><div class="stat-val"><?= number_format($stats['announcements']) ?></div><div class="stat-label">Announcements</div></div>
        </a>
        <div class="stat-card">
          <div class="stat-icon si-teal">👨‍🏫</div>
          <div><div class="stat-val"><?= number_format($stats['faculty']) ?></div><div class="stat-label">Faculty Members</div></div>
        </div>
      </div>

      <!-- Recent Students preview -->
      <div class="section-header">
        <div>
          <div class="section-title">Recent Registrations</div>
          <div class="section-sub">Last 5 students who joined</div>
        </div>
        <a href="admin.php?tab=students" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Student No.</th><th>Program</th><th>Date Joined</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($students, 0, 5) as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['FIRST_NAME'] . ' ' . $s['LAST_NAME']) ?></td>
            <td style="font-family:monospace;font-size:0.78rem;color:var(--dlsu-pale)"><?= htmlspecialchars($s['STUDENT_NO']) ?></td>
            <td><?= htmlspecialchars($s['PROGRAM']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($s['DATE_CREATED']) ?></td>
            <td><span class="status-badge status-<?= strtolower($s['STATUS']) ?>"><?= htmlspecialchars($s['STATUS']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?><tr><td colspan="5"><div class="empty-state"><div class="es-icon">🎓</div><div class="es-text">No students yet.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php elseif ($tab === 'students'): ?>
      <!-- ── STUDENTS ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Manage</div>
          <div class="page-title">Students</div>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Student No.</th><th>Email</th><th>Program</th><th>Year</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($students as $i => $s): ?>
          <tr>
            <td style="color:var(--muted);font-size:0.72rem"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($s['FIRST_NAME'] . ' ' . $s['LAST_NAME']) ?></strong></td>
            <td style="font-family:monospace;font-size:0.78rem;color:var(--dlsu-pale)"><?= htmlspecialchars($s['STUDENT_NO']) ?></td>
            <td style="font-size:0.76rem;color:var(--muted)"><?= htmlspecialchars($s['EMAIL']) ?></td>
            <td><?= htmlspecialchars($s['PROGRAM']) ?></td>
            <td><?= htmlspecialchars($s['YEAR_LEVEL']) ?></td>
            <td><span class="status-badge status-<?= strtolower($s['STATUS']) ?>"><?= htmlspecialchars($s['STATUS']) ?></span></td>
            <td style="color:var(--muted);font-size:0.75rem"><?= htmlspecialchars($s['DATE_CREATED']) ?></td>
            <td>
              <div class="td-actions">
                <button class="btn btn-ghost btn-sm" onclick='openEditStudent(<?= json_encode($s) ?>)'>✏ Edit</button>
                <button class="btn btn-red btn-sm" onclick="openDelete('STUDENTS','STUDENT_ID','<?= $s['STUDENT_ID'] ?>','<?= htmlspecialchars(addslashes($s['FIRST_NAME'].' '.$s['LAST_NAME'])) ?>')">🗑</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?><tr><td colspan="9"><div class="empty-state"><div class="es-icon">🎓</div><div class="es-text">No students found.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php elseif ($tab === 'forum'): ?>
      <!-- ── FORUM POSTS ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Monitor</div>
          <div class="page-title">Forum Posts</div>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Title</th><th>Content Preview</th><th>Student ID</th><th>Date Posted</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($posts as $i => $p): ?>
          <tr>
            <td style="color:var(--muted);font-size:0.72rem"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($p['TITLE']) ?></strong></td>
            <td style="color:var(--muted);font-size:0.76rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(substr($p['CONTENT'],0,80)) ?>…</td>
            <td style="font-family:monospace;font-size:0.75rem;color:var(--dlsu-pale)"><?= htmlspecialchars($p['STUDENT_ID']) ?></td>
            <td style="color:var(--muted);font-size:0.75rem"><?= htmlspecialchars($p['DATE_POSTED']) ?></td>
            <td>
              <button class="btn btn-red btn-sm" onclick="openDelete('FORUM_POSTS','POST_ID','<?= $p['POST_ID'] ?>','this post')">🗑 Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($posts)): ?><tr><td colspan="6"><div class="empty-state"><div class="es-icon">💬</div><div class="es-text">No forum posts yet.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php elseif ($tab === 'files'): ?>
      <!-- ── FILES ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Monitor</div>
          <div class="page-title">Uploaded Files</div>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>File Name</th><th>Description</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($files as $i => $f): ?>
          <tr>
            <td style="color:var(--muted);font-size:0.72rem"><?= $i+1 ?></td>
            <td><strong>📄 <?= htmlspecialchars($f['FILE_NAME']) ?></strong></td>
            <td style="color:var(--muted);font-size:0.76rem"><?= htmlspecialchars(substr($f['DESCRIPTION']??'',0,60)) ?></td>
            <td style="font-family:monospace;font-size:0.75rem;color:var(--dlsu-pale)"><?= htmlspecialchars($f['UPLOADED_BY']) ?></td>
            <td style="color:var(--muted);font-size:0.75rem"><?= htmlspecialchars($f['DATE_UPLOADED']) ?></td>
            <td>
              <button class="btn btn-red btn-sm" onclick="openDelete('FILES','FILE_ID','<?= $f['FILE_ID'] ?>','<?= htmlspecialchars(addslashes($f['FILE_NAME'])) ?>')">🗑 Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($files)): ?><tr><td colspan="6"><div class="empty-state"><div class="es-icon">📂</div><div class="es-text">No files uploaded yet.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php elseif ($tab === 'events'): ?>
      <!-- ── EVENTS ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Manage</div>
          <div class="page-title">Events</div>
        </div>
        <button class="btn btn-green" onclick="document.getElementById('addEventModal').classList.add('open')">+ Add Event</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Date</th><th>Location</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($events as $i => $e): ?>
          <tr>
            <td style="color:var(--muted);font-size:0.72rem"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($e['EVENT_TITLE']) ?></strong></td>
            <td style="color:var(--muted);font-size:0.76rem"><?= htmlspecialchars(substr($e['DESCRIPTION']??'',0,60)) ?></td>
            <td style="color:var(--dlsu-pale);font-family:monospace;font-size:0.78rem"><?= htmlspecialchars($e['EVENT_DATE']) ?></td>
            <td style="color:var(--muted);font-size:0.76rem"><?= htmlspecialchars($e['LOCATION']??'') ?></td>
            <td>
              <div class="td-actions">
                <button class="btn btn-ghost btn-sm" onclick='openEditEvent(<?= json_encode($e) ?>)'>✏ Edit</button>
                <button class="btn btn-red btn-sm" onclick="openDelete('EVENTS','EVENT_ID','<?= $e['EVENT_ID'] ?>','<?= htmlspecialchars(addslashes($e['EVENT_TITLE'])) ?>')">🗑</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($events)): ?><tr><td colspan="6"><div class="empty-state"><div class="es-icon">📅</div><div class="es-text">No events yet.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ════════════════════════════════════════════════════════════════ -->
      <?php elseif ($tab === 'announcements'): ?>
      <!-- ── ANNOUNCEMENTS ── -->
      <div class="page-header">
        <div>
          <div class="page-eyebrow">Manage</div>
          <div class="page-title">Announcements</div>
        </div>
        <button class="btn btn-green" onclick="document.getElementById('addAnnModal').classList.add('open')">+ Post Announcement</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Title</th><th>Content Preview</th><th>Date Posted</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($announcements as $i => $a): ?>
          <tr>
            <td style="color:var(--muted);font-size:0.72rem"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($a['TITLE']) ?></strong></td>
            <td style="color:var(--muted);font-size:0.76rem"><?= htmlspecialchars(substr($a['CONTENT'],0,80)) ?>…</td>
            <td style="color:var(--muted);font-size:0.75rem"><?= htmlspecialchars($a['DATE_POSTED']) ?></td>
            <td>
              <button class="btn btn-red btn-sm" onclick="openDelete('ANNOUNCEMENTS','ANNOUNCEMENT_ID','<?= $a['ANNOUNCEMENT_ID'] ?>','<?= htmlspecialchars(addslashes($a['TITLE'])) ?>')">🗑 Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($announcements)): ?><tr><td colspan="5"><div class="empty-state"><div class="es-icon">📢</div><div class="es-text">No announcements yet.</div></div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div><!-- /main -->
  </div><!-- /body-wrap -->
</div><!-- /page -->

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODALS -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏ Edit Student</div>
      <button class="modal-close" onclick="closeModal('editStudentModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="edit_student">
        <input type="hidden" name="STUDENT_ID" id="es_id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">First Name</label>
            <input type="text" name="FIRST_NAME" id="es_fname" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" name="LAST_NAME" id="es_lname" class="form-input" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Student Number</label>
          <input type="text" name="STUDENT_NO" id="es_sno" class="form-input" maxlength="9" pattern="\d{9}" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="EMAIL" id="es_email" class="form-input" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Program</label>
            <select name="PROGRAM" id="es_program" class="form-select">
              <?php foreach ($programs as $p): ?>
              <option value="<?= $p ?>"><?= $programLabels[$p] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Year Level</label>
            <select name="YEAR_LEVEL" id="es_year" class="form-select">
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
              <option value="5">5th Year</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="STATUS" id="es_status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('editStudentModal')">Cancel</button>
          <button type="submit" class="btn btn-green">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Event Modal -->
<div class="modal-overlay" id="editEventModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏ Edit Event</div>
      <button class="modal-close" onclick="closeModal('editEventModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="edit_event">
        <input type="hidden" name="EVENT_ID" id="ee_id">
        <div class="form-group">
          <label class="form-label">Event Title</label>
          <input type="text" name="EVENT_TITLE" id="ee_title" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="DESCRIPTION" id="ee_desc" class="form-textarea"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="EVENT_DATE" id="ee_date" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Location</label>
            <input type="text" name="LOCATION" id="ee_loc" class="form-input" placeholder="e.g. CEAT Lobby">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('editEventModal')">Cancel</button>
          <button type="submit" class="btn btn-green">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Event Modal -->
<div class="modal-overlay" id="addEventModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">+ Add Event</div>
      <button class="modal-close" onclick="closeModal('addEventModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="add_event">
        <div class="form-group">
          <label class="form-label">Event Title</label>
          <input type="text" name="EVENT_TITLE" class="form-input" placeholder="e.g. CEAT Engineering Week" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="DESCRIPTION" class="form-textarea" placeholder="Brief description of the event…"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="EVENT_DATE" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Location</label>
            <input type="text" name="LOCATION" class="form-input" placeholder="e.g. CEAT Lobby">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('addEventModal')">Cancel</button>
          <button type="submit" class="btn btn-green">Add Event</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal-overlay" id="addAnnModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📢 Post Announcement</div>
      <button class="modal-close" onclick="closeModal('addAnnModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="add_announcement">
        <div class="form-group">
          <label class="form-label">Title</label>
          <input type="text" name="TITLE" class="form-input" placeholder="Announcement title" required>
        </div>
        <div class="form-group">
          <label class="form-label">Content</label>
          <textarea name="CONTENT" class="form-textarea" placeholder="Write the full announcement here…" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('addAnnModal')">Cancel</button>
          <button type="submit" class="btn btn-green">Post Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title" style="color:#ff8a80">🗑 Confirm Delete</div>
      <button class="modal-close" onclick="closeModal('deleteModal')">✕</button>
    </div>
    <div class="modal-body">
      <p class="confirm-text">Are you sure you want to delete <strong id="deleteTarget"></strong>? This action cannot be undone.</p>
      <form method="POST" action="admin.php" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="table"  id="d_table">
        <input type="hidden" name="pk_col" id="d_pkcol">
        <input type="hidden" name="pk_val" id="d_pkval">
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
          <button type="submit" class="btn btn-red">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // ── Modal helpers ──────────────────────────────────────────────────────────
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }

  // Close modal on overlay click
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // ── Delete ─────────────────────────────────────────────────────────────────
  function openDelete(table, pkCol, pkVal, label) {
    document.getElementById('d_table').value  = table;
    document.getElementById('d_pkcol').value  = pkCol;
    document.getElementById('d_pkval').value  = pkVal;
    document.getElementById('deleteTarget').textContent = '"' + label + '"';
    document.getElementById('deleteModal').classList.add('open');
  }

  // ── Edit Student ───────────────────────────────────────────────────────────
  function openEditStudent(s) {
    document.getElementById('es_id').value      = s.STUDENT_ID;
    document.getElementById('es_sno').value     = s.STUDENT_NO;
    document.getElementById('es_fname').value   = s.FIRST_NAME;
    document.getElementById('es_lname').value   = s.LAST_NAME;
    document.getElementById('es_email').value   = s.EMAIL;
    document.getElementById('es_program').value = s.PROGRAM;
    document.getElementById('es_year').value    = s.YEAR_LEVEL;
    document.getElementById('es_status').value  = s.STATUS;
    document.getElementById('editStudentModal').classList.add('open');
  }

  // ── Edit Event ─────────────────────────────────────────────────────────────
  function openEditEvent(e) {
    document.getElementById('ee_id').value    = e.EVENT_ID;
    document.getElementById('ee_title').value = e.EVENT_TITLE;
    document.getElementById('ee_desc').value  = e.DESCRIPTION || '';
    document.getElementById('ee_date').value  = e.EVENT_DATE;
    document.getElementById('ee_loc').value   = e.LOCATION || '';
    document.getElementById('editEventModal').classList.add('open');
  }
</script>
</body>
</html>
