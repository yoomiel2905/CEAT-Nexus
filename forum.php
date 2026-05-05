<?php
// ── SESSION GUARD ─────────────────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: login.php"); exit; }
$studentFirstName = $_SESSION['first_name'];
$studentLastName  = $_SESSION['last_name'];
$studentProgram   = $_SESSION['program'];
$studentNo        = $_SESSION['student_no'];
$studentId        = $_SESSION['student_id'];
$isAdmin          = $_SESSION['is_admin'] ?? false;
$initials = strtoupper(substr($studentFirstName,0,1).substr($studentLastName,0,1));

// ── DB ────────────────────────────────────────────────────────────────────────
$serverName = ".\SQLEXPRESS";
$connectionOptions = ["Database" => "PortalDB", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die(print_r(sqlsrv_errors(), true));

$flash = ""; $flashType = "success";

// ── HANDLE POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create new post
    if ($action === 'create_post') {
        $title   = trim($_POST['title']   ?? '');
        $content = trim($_POST['body'] ?? '');
        $category= trim($_POST['category']?? 'General');
        if ($title && $content) {
            $sql = "INSERT INTO FORUM_POSTS (STUDENT_ID, TITLE, BODY, CATEGORY, DATE_POSTED, STATUS) VALUES (?,?,?,?,GETDATE(),'Active')";
            $r   = sqlsrv_query($conn, $sql, [$studentId, $title, $content, $category]);
            if ($r === false) { $flash = "Post failed: ".sqlsrv_errors()[0]['message']; $flashType="error"; }
            else { $flash = "Post created!"; }
        } else { $flash = "Title and content are required."; $flashType="error"; }
        header("Location: forum.php?flash=".urlencode($flash)."&ft=$flashType");
        exit;
    }

    // Post a reply
    if ($action === 'reply') {
        $postId  = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['reply_body'] ?? '');
        if ($postId && $content) {
            $sql = "INSERT INTO FORUM_REPLIES (POST_ID, STUDENT_ID, BODY, DATE_REPLIED) VALUES (?,?,?,GETDATE())";
            $r   = sqlsrv_query($conn, $sql, [$postId, $studentId, $content]);
            if ($r === false) { $flash = "Reply failed: ".sqlsrv_errors()[0]['message']; $flashType="error"; }
            else { $flash = "Reply posted!"; }
        }
        header("Location: forum.php?view=$postId&flash=".urlencode($flash)."&ft=$flashType");
        exit;
    }

    // Delete post (admin or own)
    if ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        // fetch owner
        $check = sqlsrv_query($conn, "SELECT STUDENT_ID FROM FORUM_POSTS WHERE POST_ID=?", [$postId]);
        $owner = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        if ($owner && ($isAdmin || $owner['STUDENT_ID'] == $studentId)) {
            sqlsrv_query($conn, "DELETE FROM FORUM_REPLIES WHERE POST_ID=?", [$postId]);
            sqlsrv_query($conn, "DELETE FROM FORUM_POSTS WHERE POST_ID=?", [$postId]);
            $flash = "Post deleted.";
        } else { $flash = "Not authorized."; $flashType="error"; }
        header("Location: forum.php?flash=".urlencode($flash)."&ft=$flashType");
        exit;
    }

    // Delete reply (admin or own)
    if ($action === 'delete_reply') {
        $replyId = (int)($_POST['reply_id'] ?? 0);
        $postId  = (int)($_POST['post_id']  ?? 0);
        $check = sqlsrv_query($conn, "SELECT STUDENT_ID FROM FORUM_REPLIES WHERE REPLY_ID=?", [$replyId]);
        $owner = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        if ($owner && ($isAdmin || $owner['STUDENT_ID'] == $studentId)) {
            sqlsrv_query($conn, "DELETE FROM FORUM_REPLIES WHERE REPLY_ID=?", [$replyId]);
            $flash = "Reply deleted.";
        } else { $flash = "Not authorized."; $flashType="error"; }
        header("Location: forum.php?view=$postId&flash=".urlencode($flash)."&ft=$flashType");
        exit;
    }
}

if (isset($_GET['flash']))  $flash    = $_GET['flash'];
if (isset($_GET['ft']))     $flashType= $_GET['ft'];

// ── VIEW SINGLE POST ──────────────────────────────────────────────────────────
$viewPostId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewPost   = null;
$replies    = [];

if ($viewPostId) {
    $r = sqlsrv_query($conn,
        "SELECT fp.POST_ID, fp.TITLE, fp.BODY, fp.CATEGORY, fp.DATE_POSTED, fp.STATUS,
                s.FIRST_NAME, s.LAST_NAME, s.PROGRAM, s.STUDENT_ID as AUTHOR_ID
         FROM FORUM_POSTS fp
         JOIN STUDENTS s ON fp.STUDENT_ID = s.STUDENT_ID
         WHERE fp.POST_ID = ?", [$viewPostId]);
    if ($r) {
        $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
        if ($row) {
            if ($row['DATE_POSTED'] instanceof DateTime) $row['DATE_POSTED'] = $row['DATE_POSTED']->format('M d, Y g:i A');
            $viewPost = $row;
        }
    }

    $rr = sqlsrv_query($conn,
        "SELECT fr.REPLY_ID, fr.BODY, fr.DATE_REPLIED,
                s.FIRST_NAME, s.LAST_NAME, s.PROGRAM, s.STUDENT_ID as AUTHOR_ID
         FROM FORUM_REPLIES fr
         JOIN STUDENTS s ON fr.STUDENT_ID = s.STUDENT_ID
         WHERE fr.POST_ID = ?
         ORDER BY fr.DATE_REPLIED ASC", [$viewPostId]);
    while ($rr && $row = sqlsrv_fetch_array($rr, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_REPLIED'] instanceof DateTime) $row['DATE_REPLIED'] = $row['DATE_REPLIED']->format('M d, Y g:i A');
        $replies[] = $row;
    }
}

// ── FETCH ALL POSTS (list view) ───────────────────────────────────────────────
$filterCat = $_GET['cat'] ?? 'All';
$search    = trim($_GET['q'] ?? '');
$posts     = [];

if (!$viewPostId) {
    $where = "WHERE 1=1";
    $params = [];
    if ($filterCat !== 'All') { $where .= " AND fp.CATEGORY = ?"; $params[] = $filterCat; }
    if ($search)              { $where .= " AND (fp.TITLE LIKE ? OR fp.BODY LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql = "SELECT fp.POST_ID, fp.TITLE, fp.BODY, fp.CATEGORY, fp.DATE_POSTED, fp.STATUS,
                   s.FIRST_NAME, s.LAST_NAME, s.PROGRAM, fp.STUDENT_ID as AUTHOR_ID,
                   (SELECT COUNT(*) FROM FORUM_REPLIES fr WHERE fr.POST_ID=fp.POST_ID) AS REPLY_COUNT
            FROM FORUM_POSTS fp
            JOIN STUDENTS s ON fp.STUDENT_ID = s.STUDENT_ID
            $where
            ORDER BY fp.DATE_POSTED DESC";
    $r = sqlsrv_query($conn, $sql, empty($params) ? [] : $params);
    while ($r && $row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
        if ($row['DATE_POSTED'] instanceof DateTime) $row['DATE_POSTED'] = $row['DATE_POSTED']->format('M d, Y');
        $posts[] = $row;
    }
}

// Category counts
$catCounts = ['All' => 0];
$cr = sqlsrv_query($conn, "SELECT CATEGORY, COUNT(*) AS C FROM FORUM_POSTS GROUP BY CATEGORY");
while ($cr && $row = sqlsrv_fetch_array($cr, SQLSRV_FETCH_ASSOC)) {
    $catCounts[$row['CATEGORY']] = $row['C'];
    $catCounts['All'] += $row['C'];
}

sqlsrv_close($conn);

$categories = ['All','General','Academics','Projects','Campus Life','Announcements','Tech Help'];
$catColors  = [
    'General'       => '#5cb85c',
    'Academics'     => '#3498db',
    'Projects'      => '#f39c12',
    'Campus Life'   => '#9b59b6',
    'Announcements' => '#e74c3c',
    'Tech Help'     => '#1abc9c',
];
function catColor($cat) {
    global $catColors;
    return $catColors[$cat] ?? '#5cb85c';
}
function timeAgo($dateStr) {
    // simple display, already formatted
    return $dateStr;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CEAT NEXUS — Forums</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --dlsu-dark:   #1a3d1a;
      --dlsu-mid:    #2a5c2a;
      --dlsu-green:  #3a8c3a;
      --dlsu-light:  #5cb85c;
      --dlsu-pale:   #a8d8a8;
      --white:       #ffffff;
      --muted:       rgba(255,255,255,0.55);
      --sidebar-w:   220px;
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
      background: linear-gradient(180deg, rgba(8,22,8,0.85) 0%, rgba(12,30,12,0.78) 60%, rgba(6,18,6,0.90) 100%);
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
      background: rgba(12,30,12,0.95); backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,0.07);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 28px; height: 56px; flex-shrink: 0;
      position: sticky; top: 0; z-index: 200;
    }
    .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
    .nav-logo-mark {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
      border-radius: 8px; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 14px rgba(92,184,92,0.4);
    }
    .nav-logo-mark svg { width: 17px; height: 17px; fill:none; stroke:#fff; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
    .nav-logo-text { font-family:'Syne',sans-serif; font-size:1rem; font-weight:800; }
    .nav-logo-text small { display:block; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.56rem; font-weight:400; color:var(--muted); letter-spacing:0.1em; text-transform:uppercase; margin-top:1px; }
    .nav-right { display:flex; align-items:center; gap:10px; }
    .nav-chip {
      display:flex; align-items:center; gap:6px; padding:4px 11px; border-radius:16px;
      background:rgba(92,184,92,0.14); border:1px solid rgba(92,184,92,0.28);
      font-size:0.66rem; font-weight:700; color:#8de88d; letter-spacing:0.04em;
    }
    .chip-dot { width:6px; height:6px; border-radius:50%; background:#5cb85c; box-shadow:0 0 6px #5cb85c; }
    .avatar-wrap { position: relative; }
    .nav-avatar {
      width:32px; height:32px; border-radius:50%;
      background:linear-gradient(135deg,var(--dlsu-light),var(--dlsu-mid));
      border:2px solid rgba(92,184,92,0.45);
      display:flex; align-items:center; justify-content:center;
      font-size:0.7rem; font-weight:800; cursor:pointer;
      box-shadow:0 0 10px rgba(92,184,92,0.25); user-select:none;
    }
    .nav-avatar:hover { box-shadow:0 0 18px rgba(92,184,92,0.5); }
    .avatar-dropdown {
      display:none; position:absolute; top:calc(100% + 10px); right:0;
      width:200px; background:rgba(10,28,10,0.97);
      border:1px solid rgba(255,255,255,0.12); border-radius:14px;
      box-shadow:0 16px 48px rgba(0,0,0,0.5); z-index:500;
      animation:dropIn 0.18s ease; overflow:hidden;
    }
    .avatar-dropdown.open { display:block; }
    @keyframes dropIn { from{opacity:0;transform:translateY(-6px) scale(0.97)} to{opacity:1;transform:none} }
    .dd-header { padding:14px 16px 10px; border-bottom:1px solid rgba(255,255,255,0.07); }
    .dd-name { font-size:0.82rem; font-weight:700; color:white; }
    .dd-role { font-size:0.65rem; color:rgba(255,255,255,0.4); margin-top:2px; }
    .dd-item { display:flex; align-items:center; gap:10px; padding:10px 16px; font-size:0.8rem; font-weight:600; color:rgba(255,255,255,0.65); text-decoration:none; transition:background 0.14s; }
    .dd-item:hover { background:rgba(255,255,255,0.07); color:white; }
    .dd-item.danger { color:rgba(231,76,60,0.8); }
    .dd-item.danger:hover { background:rgba(231,76,60,0.1); color:#ff8a80; }
    .dd-divider { height:1px; background:rgba(255,255,255,0.07); }

    /* ── LAYOUT ── */
    .body-wrap { display:flex; flex:1; min-height:0; }

    /* ── SIDEBAR ── */
    .sidebar {
      width:var(--sidebar-w); flex-shrink:0;
      background:rgba(10,28,10,0.90); backdrop-filter:blur(14px);
      border-right:1px solid rgba(255,255,255,0.07);
      display:flex; flex-direction:column; overflow-y:auto;
    }
    .sidebar-header { padding:18px 16px 12px; border-bottom:1px solid rgba(255,255,255,0.07); font-size:0.58rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:rgba(255,255,255,0.28); display:flex; align-items:center; gap:7px; }
    .sidebar-header::before { content:''; width:14px; height:2px; background:var(--dlsu-light); border-radius:2px; }
    .nav-section { padding:14px 12px 5px; font-size:0.56rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:rgba(255,255,255,0.22); }
    .side-item { display:flex; align-items:center; gap:10px; padding:9px 14px; margin:1px 6px; border-radius:9px; cursor:pointer; font-size:0.8rem; font-weight:500; color:rgba(255,255,255,0.5); transition:all 0.16s; text-decoration:none; position:relative; }
    .side-item:hover { background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.9); }
    .side-item.active { background:linear-gradient(135deg,rgba(92,184,92,0.22),rgba(92,184,92,0.10)); color:#8de88d; font-weight:700; }
    .side-item.active::before { content:''; position:absolute; left:-6px; top:50%; transform:translateY(-50%); width:3px; height:55%; background:var(--dlsu-light); border-radius:0 3px 3px 0; }
    .side-icon { font-size:0.95rem; width:18px; text-align:center; flex-shrink:0; }
    .side-badge { margin-left:auto; background:var(--dlsu-light); color:white; font-size:0.58rem; font-weight:800; padding:1px 6px; border-radius:8px; }
    .side-soon { margin-left:auto; background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.28); font-size:0.53rem; font-weight:700; padding:2px 6px; border-radius:5px; letter-spacing:0.05em; text-transform:uppercase; }
    .sidebar-footer { margin-top:auto; padding:14px; border-top:1px solid rgba(255,255,255,0.07); display:flex; align-items:center; gap:9px; }
    .sf-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--dlsu-light),var(--dlsu-mid)); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; border:2px solid rgba(92,184,92,0.35); flex-shrink:0; }
    .sf-name { font-size:0.74rem; font-weight:700; color:rgba(255,255,255,0.8); }
    .sf-role { font-size:0.6rem; color:rgba(255,255,255,0.35); margin-top:1px; }

    /* ── MAIN ── */
    .main { flex:1; display:flex; flex-direction:column; overflow-y:auto; }

    /* ── FORUM HEADER ── */
    .forum-header {
      padding:28px 36px 20px;
      border-bottom:1px solid rgba(255,255,255,0.07);
      background:linear-gradient(135deg,rgba(20,55,20,0.5) 0%,rgba(10,30,10,0.3) 100%);
      backdrop-filter:blur(6px);
      display:flex; align-items:center; justify-content:space-between;
      flex-shrink:0;
    }
    .fh-left {}
    .fh-eyebrow { font-size:0.6rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:var(--dlsu-pale); display:flex; align-items:center; gap:7px; margin-bottom:4px; }
    .fh-eyebrow::before { content:''; width:14px; height:2px; background:var(--dlsu-light); border-radius:2px; }
    .fh-title { font-family:'Syne',sans-serif; font-size:1.5rem; font-weight:800; letter-spacing:-0.02em; }
    .fh-sub { font-size:0.76rem; color:var(--muted); margin-top:4px; }

    .btn-new {
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 20px; background:var(--dlsu-light); color:white;
      font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem; font-weight:700;
      border:none; border-radius:10px; cursor:pointer;
      box-shadow:0 4px 16px rgba(92,184,92,0.35); transition:all 0.18s;
    }
    .btn-new:hover { background:#6ecf6e; transform:translateY(-1px); box-shadow:0 6px 22px rgba(92,184,92,0.5); }

    /* ── FILTER BAR ── */
    .filter-bar {
      padding:14px 36px;
      border-bottom:1px solid rgba(255,255,255,0.06);
      display:flex; align-items:center; gap:10px; flex-wrap:wrap;
      background:rgba(10,28,10,0.5); flex-shrink:0;
    }
    .search-wrap { position:relative; flex:1; min-width:200px; max-width:340px; }
    .search-icon { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:0.8rem; pointer-events:none; }
    .search-input {
      width:100%; padding:8px 12px 8px 32px;
      background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
      border-radius:8px; color:white; font-family:'Plus Jakarta Sans',sans-serif;
      font-size:0.8rem; outline:none; transition:border-color 0.16s;
    }
    .search-input:focus { border-color:rgba(92,184,92,0.45); }
    .search-input::placeholder { color:rgba(255,255,255,0.2); }
    .cat-filters { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .cat-pill {
      padding:5px 13px; border-radius:20px;
      font-size:0.7rem; font-weight:700;
      background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
      color:rgba(255,255,255,0.5); cursor:pointer; text-decoration:none; transition:all 0.16s;
      white-space:nowrap;
    }
    .cat-pill:hover { background:rgba(255,255,255,0.1); color:white; }
    .cat-pill.active { background:rgba(92,184,92,0.2); border-color:rgba(92,184,92,0.45); color:#8de88d; }

    /* ── FLASH ── */
    .flash-bar {
      padding:11px 36px; font-size:0.8rem; display:flex; align-items:center; gap:8px;
    }
    .flash-bar.success { background:rgba(92,184,92,0.12); border-bottom:1px solid rgba(92,184,92,0.2); color:#8de88d; }
    .flash-bar.error   { background:rgba(231,76,60,0.12); border-bottom:1px solid rgba(231,76,60,0.2); color:#ff8a80; }

    /* ── POST LIST ── */
    .posts-wrap { padding:20px 36px; flex:1; }
    .posts-grid { display:flex; flex-direction:column; gap:10px; }

    .post-card {
      background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
      border-radius:14px; padding:18px 20px;
      display:flex; align-items:flex-start; gap:16px;
      text-decoration:none; color:inherit; transition:all 0.18s; cursor:pointer;
    }
    .post-card:hover { background:rgba(255,255,255,0.07); border-color:rgba(92,184,92,0.25); transform:translateY(-1px); }
    .post-card:hover .post-title { color:#8de88d; }

    .post-avatar {
      width:38px; height:38px; border-radius:50%; flex-shrink:0;
      background:linear-gradient(135deg,var(--dlsu-light),var(--dlsu-mid));
      display:flex; align-items:center; justify-content:center;
      font-size:0.72rem; font-weight:800;
      border:2px solid rgba(92,184,92,0.3);
    }
    .post-body { flex:1; min-width:0; }
    .post-meta-top { display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap; }
    .post-cat {
      display:inline-block; padding:2px 9px; border-radius:20px;
      font-size:0.6rem; font-weight:800; letter-spacing:0.06em; text-transform:uppercase;
    }
    .post-author { font-size:0.72rem; color:var(--muted); }
    .post-date   { font-size:0.7rem; color:rgba(255,255,255,0.3); margin-left:auto; white-space:nowrap; }
    .post-title  { font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:800; margin-bottom:5px; transition:color 0.16s; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .post-preview{ font-size:0.78rem; color:var(--muted); line-height:1.5; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .post-footer { display:flex; align-items:center; gap:14px; margin-top:10px; }
    .post-stat   { display:flex; align-items:center; gap:5px; font-size:0.7rem; color:rgba(255,255,255,0.35); }

    .delete-btn {
      margin-left:auto; padding:4px 10px; border-radius:6px;
      background:rgba(231,76,60,0.12); border:1px solid rgba(231,76,60,0.25);
      color:#ff8a80; font-size:0.65rem; font-weight:700; cursor:pointer;
      transition:all 0.15s;
    }
    .delete-btn:hover { background:rgba(231,76,60,0.25); }

    /* ── EMPTY STATE ── */
    .empty-state { text-align:center; padding:60px 24px; color:rgba(255,255,255,0.2); }
    .empty-icon  { font-size:3rem; margin-bottom:12px; }
    .empty-text  { font-size:0.85rem; line-height:1.6; }
    .empty-cta   { margin-top:16px; }

    /* ── SINGLE POST VIEW ── */
    .post-view { padding:28px 36px; flex:1; }
    .back-link { display:inline-flex; align-items:center; gap:7px; font-size:0.78rem; font-weight:600; color:var(--muted); text-decoration:none; margin-bottom:20px; transition:color 0.15s; }
    .back-link:hover { color:white; }

    .post-full {
      background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
      border-radius:16px; overflow:hidden; margin-bottom:24px;
    }
    .pf-header { padding:22px 24px 18px; border-bottom:1px solid rgba(255,255,255,0.07); }
    .pf-meta { display:flex; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap; }
    .pf-title { font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:800; letter-spacing:-0.01em; margin-bottom:4px; }
    .pf-author-row { display:flex; align-items:center; gap:10px; }
    .pf-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--dlsu-light),var(--dlsu-mid)); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; border:2px solid rgba(92,184,92,0.35); flex-shrink:0; }
    .pf-name  { font-size:0.8rem; font-weight:700; }
    .pf-sub   { font-size:0.68rem; color:var(--muted); margin-top:1px; }
    .pf-body  { padding:22px 24px; font-size:0.88rem; line-height:1.75; color:rgba(255,255,255,0.82); white-space:pre-wrap; }

    /* ── REPLIES ── */
    .replies-section { margin-bottom:24px; }
    .replies-header { font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:800; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
    .replies-count { background:rgba(92,184,92,0.18); color:#8de88d; font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:10px; }

    .reply-card {
      background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.07);
      border-radius:12px; padding:15px 18px; margin-bottom:10px;
      display:flex; gap:12px;
    }
    .reply-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,rgba(92,184,92,0.6),rgba(42,92,42,0.8)); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:800; flex-shrink:0; }
    .reply-body { flex:1; }
    .reply-author { font-size:0.76rem; font-weight:700; margin-bottom:2px; }
    .reply-meta   { font-size:0.65rem; color:var(--muted); margin-bottom:8px; }
    .reply-text   { font-size:0.82rem; line-height:1.65; color:rgba(255,255,255,0.78); white-space:pre-wrap; }
    .reply-delete { float:right; }

    /* ── REPLY FORM ── */
    .reply-form-wrap {
      background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.09);
      border-radius:14px; padding:18px 20px;
    }
    .reply-form-title { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); margin-bottom:12px; }
    .reply-textarea {
      width:100%; min-height:90px; padding:11px 14px;
      background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
      border-radius:9px; color:white; font-family:'Plus Jakarta Sans',sans-serif;
      font-size:0.83rem; resize:vertical; outline:none; transition:border-color 0.16s;
      margin-bottom:12px;
    }
    .reply-textarea:focus { border-color:rgba(92,184,92,0.5); }
    .reply-textarea::placeholder { color:rgba(255,255,255,0.2); }
    .reply-submit {
      padding:9px 22px; background:var(--dlsu-light); color:white;
      font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem; font-weight:700;
      border:none; border-radius:8px; cursor:pointer; transition:all 0.16s;
    }
    .reply-submit:hover { background:#6ecf6e; transform:translateY(-1px); }

    /* ── MODAL ── */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.72); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:rgba(10,28,10,0.97); border:1px solid rgba(255,255,255,0.12); border-radius:18px; width:560px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 32px 80px rgba(0,0,0,0.6); animation:modalIn 0.2s ease; }
    @keyframes modalIn { from{opacity:0;transform:scale(0.96) translateY(10px)} to{opacity:1;transform:none} }
    .modal-header { padding:20px 24px 16px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:space-between; }
    .modal-title  { font-family:'Syne',sans-serif; font-size:1rem; font-weight:800; }
    .modal-close  { width:28px; height:28px; border-radius:7px; background:rgba(255,255,255,0.08); border:none; color:rgba(255,255,255,0.6); font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .modal-close:hover { background:rgba(255,255,255,0.16); color:white; }
    .modal-body   { padding:20px 24px 24px; }
    .form-group   { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
    .form-label   { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:rgba(255,255,255,0.5); }
    .form-input, .form-select, .form-textarea {
      width:100%; padding:10px 12px;
      background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
      border-radius:8px; color:white; font-family:'Plus Jakarta Sans',sans-serif;
      font-size:0.83rem; outline:none; transition:border-color 0.16s;
    }
    .form-input:focus,.form-select:focus,.form-textarea:focus { border-color:rgba(92,184,92,0.5); }
    .form-input::placeholder,.form-textarea::placeholder { color:rgba(255,255,255,0.2); }
    .form-select { -webkit-appearance:none; appearance:none; cursor:pointer; }
    .form-select option { background:#1a3d1a; }
    .form-textarea { resize:vertical; min-height:130px; }
    .modal-footer { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.07); margin-top:8px; }
    .btn-ghost  { padding:8px 18px; border-radius:8px; background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.7); font-family:'Plus Jakarta Sans',sans-serif; font-size:0.8rem; font-weight:700; border:1px solid rgba(255,255,255,0.12); cursor:pointer; transition:all 0.16s; }
    .btn-ghost:hover { background:rgba(255,255,255,0.12); color:white; }
    .btn-primary { padding:8px 22px; border-radius:8px; background:var(--dlsu-light); color:white; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.8rem; font-weight:700; border:none; cursor:pointer; box-shadow:0 3px 12px rgba(92,184,92,0.3); transition:all 0.16s; }
    .btn-primary:hover { background:#6ecf6e; transform:translateY(-1px); }

    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-track { background:transparent; }
    ::-webkit-scrollbar-thumb { background:rgba(92,184,92,0.25); border-radius:4px; }
  </style>
</head>
<body>
<div class="page">

  <!-- TOP NAV -->
  <nav class="topnav">
    <a class="nav-logo" href="dashboard.php">
      <div class="nav-logo-mark">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="nav-logo-text">CEAT NEXUS<small>De La Salle University — Dasmariñas</small></div>
    </a>
    <div class="nav-right">
      <?php if($isAdmin): ?>
      <a href="admin.php" style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;background:rgba(231,76,60,0.18);border:1px solid rgba(231,76,60,0.4);font-size:0.62rem;font-weight:800;color:#ff8a80;letter-spacing:0.1em;text-transform:uppercase;text-decoration:none;">⚙ Admin</a>
      <?php endif; ?>
      <div class="nav-chip"><div class="chip-dot"></div>AY 2025–2026</div>
      <div class="avatar-wrap">
        <div class="nav-avatar" onclick="toggleDropdown()"><?php echo htmlspecialchars($initials); ?></div>
        <div class="avatar-dropdown" id="avatarDropdown">
          <div class="dd-header">
            <div class="dd-name"><?php echo htmlspecialchars($studentFirstName.' '.$studentLastName); ?></div>
            <div class="dd-role"><?php echo htmlspecialchars($studentProgram); ?></div>
          </div>
          <?php if($isAdmin): ?><a href="admin.php" class="dd-item">⚙&nbsp; Admin Panel</a><div class="dd-divider"></div><?php endif; ?>
          <a href="settings.php" class="dd-item">⚙&nbsp; Settings</a>
          <div class="dd-divider"></div>
          <a href="logout.php" class="dd-item danger">↩&nbsp; Log Out</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- BODY -->
  <div class="body-wrap">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sidebar-header">Module Locator</div>
      <div class="nav-section">Main</div>
      <a href="dashboard.php" class="side-item" style="color:inherit;"><span class="side-icon">🏠</span>Dashboard</a>
      <a href="forum.php"     class="side-item active"><span class="side-icon">💬</span>Forums</a>
      <a href="#"             class="side-item"><span class="side-icon">📂</span>File Locator</a>
      <a href="calendar.php"             class="side-item"><span class="side-icon">📅</span>Calendar</a>
      <div class="nav-section">Campus</div>
      <div class="side-item"><span class="side-icon">🗺️</span>CEAT Map<span class="side-soon">Soon</span></div>
      <div class="side-item"><span class="side-icon">🏛️</span>Room Gallery</div>
      <div class="side-item"><span class="side-icon">📢</span>Announcements<span class="side-soon">Soon</span></div>
      <div class="side-item"><span class="side-icon">🎓</span>Faculty<span class="side-soon">Soon</span></div>
      <div class="side-item"><span class="side-icon">🔬</span>Lab Schedules<span class="side-soon">Soon</span></div>
      <div class="side-item"><span class="side-icon">🤝</span>Organizations<span class="side-soon">Soon</span></div>
      <div class="sidebar-footer">
        <div class="sf-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div>
          <div class="sf-name"><?php echo htmlspecialchars($studentFirstName.' '.$studentLastName); ?></div>
          <div class="sf-role"><?php echo htmlspecialchars($studentProgram); ?><?php if($isAdmin): ?> · <span style="color:#ff8a80;font-size:0.58rem">ADMIN</span><?php endif; ?></div>
        </div>
      </div>
    </aside>

    <!-- MAIN -->
    <div class="main">

      <?php if ($flash): ?>
      <div class="flash-bar <?= htmlspecialchars($flashType) ?>"><?= $flashType==='success'?'✅':'⚠' ?> &nbsp;<?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>

      <?php if ($viewPost): ?>
      <!-- ════════════════════════════════════════════════ SINGLE POST VIEW -->
      <div class="post-view">
        <a href="forum.php" class="back-link">← Back to Forums</a>

        <!-- Post -->
        <div class="post-full">
          <div class="pf-header">
            <div class="pf-meta">
              <span class="post-cat" style="background:<?= catColor($viewPost['CATEGORY']) ?>22;color:<?= catColor($viewPost['CATEGORY']) ?>;border:1px solid <?= catColor($viewPost['CATEGORY']) ?>44;"><?= htmlspecialchars($viewPost['CATEGORY']) ?></span>
              <span style="font-size:0.7rem;color:var(--muted)"><?= htmlspecialchars($viewPost['DATE_POSTED']) ?></span>
            </div>
            <div class="pf-title"><?= htmlspecialchars($viewPost['TITLE']) ?></div>
            <div class="pf-author-row" style="margin-top:12px;">
              <div class="pf-avatar"><?= strtoupper(substr($viewPost['FIRST_NAME'],0,1).substr($viewPost['LAST_NAME'],0,1)) ?></div>
              <div>
                <div class="pf-name"><?= htmlspecialchars($viewPost['FIRST_NAME'].' '.$viewPost['LAST_NAME']) ?></div>
                <div class="pf-sub"><?= htmlspecialchars($viewPost['PROGRAM']) ?></div>
              </div>
              <?php if($isAdmin || $viewPost['AUTHOR_ID'] == $studentId): ?>
              <form method="POST" style="margin-left:auto;">
                <input type="hidden" name="action"  value="delete_post">
                <input type="hidden" name="post_id" value="<?= $viewPost['POST_ID'] ?>">
                <button type="submit" class="delete-btn" onclick="return confirm('Delete this post and all replies?')">🗑 Delete Post</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="pf-body"><?= htmlspecialchars($viewPost['BODY']) ?></div>
        </div>

        <!-- Replies -->
        <div class="replies-section">
          <div class="replies-header">
            Replies <span class="replies-count"><?= count($replies) ?></span>
          </div>
          <?php if (empty($replies)): ?>
          <div style="text-align:center;padding:28px;color:rgba(255,255,255,0.22);font-size:0.82rem;">No replies yet. Be the first to respond!</div>
          <?php endif; ?>
          <?php foreach ($replies as $rep): ?>
          <div class="reply-card">
            <div class="reply-avatar"><?= strtoupper(substr($rep['FIRST_NAME'],0,1).substr($rep['LAST_NAME'],0,1)) ?></div>
            <div class="reply-body">
              <div class="reply-author">
                <?= htmlspecialchars($rep['FIRST_NAME'].' '.$rep['LAST_NAME']) ?>
                <?php if($isAdmin || $rep['AUTHOR_ID'] == $studentId): ?>
                <form method="POST" style="display:inline;float:right;">
                  <input type="hidden" name="action"   value="delete_reply">
                  <input type="hidden" name="reply_id" value="<?= $rep['REPLY_ID'] ?>">
                  <input type="hidden" name="post_id"  value="<?= $viewPostId ?>">
                  <button type="submit" class="delete-btn" onclick="return confirm('Delete this reply?')" style="font-size:0.6rem;padding:2px 8px;">🗑</button>
                </form>
                <?php endif; ?>
              </div>
              <div class="reply-meta"><?= htmlspecialchars($rep['PROGRAM']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($rep['DATE_REPLIED']) ?></div>
              <div class="reply-text"><?= htmlspecialchars($rep['BODY']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Reply form -->
        <div class="reply-form-wrap">
          <div class="reply-form-title">Post a Reply</div>
          <form method="POST">
            <input type="hidden" name="action"  value="reply">
            <input type="hidden" name="post_id" value="<?= $viewPost['POST_ID'] ?>">
            <textarea name="reply_body" class="reply-textarea" placeholder="Write your reply here…" required></textarea>
            <button type="submit" class="reply-submit">✦ Post Reply</button>
          </form>
        </div>
      </div>

      <?php else: ?>
      <!-- ════════════════════════════════════════════════ POST LIST VIEW -->
      <div class="forum-header">
        <div class="fh-left">
          <div class="fh-eyebrow">Community</div>
          <div class="fh-title">CEAT Forums</div>
          <div class="fh-sub">Discuss, ask questions, and connect with fellow CEAT students.</div>
        </div>
        <button class="btn-new" onclick="document.getElementById('newPostModal').classList.add('open')">✦ &nbsp;New Post</button>
      </div>

      <form method="GET" action="forum.php">
        <div class="filter-bar">
          <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Search posts…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="cat-filters">
            <?php foreach ($categories as $cat): ?>
            <a href="forum.php?cat=<?= urlencode($cat) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="cat-pill <?= $filterCat===$cat ? 'active' : '' ?>">
              <?= htmlspecialchars($cat) ?><?= isset($catCounts[$cat]) && $cat!=='All' ? ' <span style="opacity:0.55">('.$catCounts[$cat].')</span>' : '' ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </form>

      <div class="posts-wrap">
        <?php if (empty($posts)): ?>
        <div class="empty-state">
          <div class="empty-icon">💬</div>
          <div class="empty-text"><?= $search ? "No posts matched \"".htmlspecialchars($search)."\"" : "No posts yet in this category." ?><br>Be the first to start a discussion!</div>
          <div class="empty-cta"><button class="btn-new" onclick="document.getElementById('newPostModal').classList.add('open')">✦ &nbsp;Create First Post</button></div>
        </div>
        <?php else: ?>
        <div class="posts-grid">
          <?php foreach ($posts as $p):
            $pInitials = strtoupper(substr($p['FIRST_NAME'],0,1).substr($p['LAST_NAME'],0,1));
            $color = catColor($p['CATEGORY']);
          ?>
          <div class="post-card" onclick="window.location='forum.php?view=<?= $p['POST_ID'] ?>'">
            <div class="post-avatar"><?= $pInitials ?></div>
            <div class="post-body">
              <div class="post-meta-top">
                <span class="post-cat" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44;"><?= htmlspecialchars($p['CATEGORY']) ?></span>
                <span class="post-author"><?= htmlspecialchars($p['FIRST_NAME'].' '.$p['LAST_NAME']) ?> &middot; <?= htmlspecialchars($p['PROGRAM']) ?></span>
                <span class="post-date"><?= htmlspecialchars($p['DATE_POSTED']) ?></span>
              </div>
              <div class="post-title"><?= htmlspecialchars($p['TITLE']) ?></div>
              <div class="post-preview"><?= htmlspecialchars(substr($p['BODY'],0,120)) ?>…</div>
              <div class="post-footer">
                <span class="post-stat">💬 <?= $p['REPLY_COUNT'] ?> <?= $p['REPLY_COUNT']==1?'reply':'replies' ?></span>
                <?php if($isAdmin || $p['AUTHOR_ID'] == $studentId): ?>
                <form method="POST" onclick="event.stopPropagation();" style="margin-left:auto;">
                  <input type="hidden" name="action"  value="delete_post">
                  <input type="hidden" name="post_id" value="<?= $p['POST_ID'] ?>">
                  <button type="submit" class="delete-btn" onclick="return confirm('Delete this post and all its replies?')">🗑 Delete</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- /main -->
  </div><!-- /body-wrap -->
</div><!-- /page -->

<!-- NEW POST MODAL -->
<div class="modal-overlay" id="newPostModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✦ New Post</div>
      <button class="modal-close" onclick="document.getElementById('newPostModal').classList.remove('open')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="forum.php">
        <input type="hidden" name="action" value="create_post">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-select">
            <?php foreach (array_slice($categories,1) as $cat): ?>
            <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-input" placeholder="What's your post about?" required>
        </div>
        <div class="form-group">
          <label class="form-label">Content</label>
          <textarea name="body" class="form-textarea" placeholder="Write your post here…" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="document.getElementById('newPostModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-primary">✦ &nbsp;Publish Post</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function toggleDropdown() {
    document.getElementById('avatarDropdown').classList.toggle('open');
  }
  document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.avatar-wrap');
    if (wrap && !wrap.contains(e.target)) {
      var dd = document.getElementById('avatarDropdown');
      if (dd) dd.classList.remove('open');
    }
  });
  // Close modal on overlay click
  document.getElementById('newPostModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
</script>
</body>
</html>
