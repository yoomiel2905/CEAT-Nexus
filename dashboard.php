<?php
// ── SESSION GUARD ─────────────────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: login.php"); exit; }
$studentFirstName = $_SESSION['first_name'];
$studentLastName  = $_SESSION['last_name'];
$studentProgram   = $_SESSION['program'];
$studentNo        = $_SESSION['student_no'];
$isAdmin          = $_SESSION['is_admin'] ?? false;
$initials = strtoupper(substr($studentFirstName,0,1).substr($studentLastName,0,1));
// ─────────────────────────────────────────────────────────────────────────────
?>
<?php
$serverName=".\SQLEXPRESS";
$connectionOptions=[
    "Database"=>"PortalDB",
    "Uid"=>"",
    "PWD"=>""
];
$conn=sqlsrv_connect($serverName, $connectionOptions);
if($conn==false)
    die(print_r(sqlsrv_errors(),true));

// Fetch stats
$sql_students="SELECT COUNT(*) AS TOTAL FROM STUDENTS";
$result_students=sqlsrv_query($conn,$sql_students);
$row_students=sqlsrv_fetch_array($result_students);
$totalstudents=$row_students['TOTAL'];

$sql_posts="SELECT COUNT(*) AS TOTAL FROM FORUM_POSTS";
$result_posts=sqlsrv_query($conn,$sql_posts);
$row_posts=sqlsrv_fetch_array($result_posts);
$totalposts=$row_posts['TOTAL'];

$sql_files="SELECT COUNT(*) AS TOTAL FROM FILES";
$result_files=sqlsrv_query($conn,$sql_files);
$row_files=sqlsrv_fetch_array($result_files);
$totalfiles=$row_files['TOTAL'];

$sql_event="SELECT TOP 1 EVENT_DATE FROM EVENTS ORDER BY EVENT_DATE ASC";
$result_event=sqlsrv_query($conn,$sql_event);
$row_event=sqlsrv_fetch_array($result_event);
// sqlsrv returns DATETIME columns as PHP DateTime objects, not strings
// So we use ->format() directly instead of date()/strtotime()
$nextevent=$row_event['EVENT_DATE']->format('M j');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEAT NEXUS — DLSU-D</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --sidebar-w:   220px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: url('images/bg.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #ffffff;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(180deg, rgba(15,40,15,0.72) 0%, rgba(20,55,20,0.65) 60%, rgba(10,30,10,0.80) 100%);
            z-index: 0;
            pointer-events: none;
        }

        .page {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* TOP NAV */
        .topnav {
            background: rgba(15,38,15,0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            height: 56px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 200;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #ffffff;
        }

        .nav-logo-mark {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 14px rgba(92,184,92,0.4);
        }

        .nav-logo-mark svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: #fff;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .nav-logo-text {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .nav-logo-text small {
            display: block;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.58rem;
            font-weight: 400;
            color: var(--muted);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-link {
            padding: 6px 13px;
            font-size: 0.78rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.16s;
            text-decoration: none;
            letter-spacing: 0.01em;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #ffffff;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 16px;
            background: rgba(92,184,92,0.15);
            border: 1px solid rgba(92,184,92,0.3);
            font-size: 0.68rem;
            font-weight: 700;
            color: #8de88d;
            letter-spacing: 0.04em;
        }

        .chip-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #5cb85c;
            box-shadow: 0 0 6px #5cb85c;
        }

        .nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.16s;
            position: relative;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.13);
        }

        .nav-notif {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            background: #ff6b35;
            border-radius: 50%;
            border: 1.5px solid #0f2610;
        }


        /* AVATAR DROPDOWN */
        .avatar-wrap { position: relative; }
        .nav-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
            border: 2px solid rgba(92,184,92,0.45);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 800; cursor: pointer;
            box-shadow: 0 0 10px rgba(92,184,92,0.25);
            user-select: none; transition: box-shadow 0.18s;
        }
        .nav-avatar:hover, .nav-avatar.open { box-shadow: 0 0 18px rgba(92,184,92,0.5); }

        .avatar-dropdown {
            display: none; position: absolute; top: calc(100% + 10px); right: 0;
            width: 200px;
            background: rgba(10,28,10,0.97);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 14px; overflow: hidden;
            box-shadow: 0 16px 48px rgba(0,0,0,0.5);
            z-index: 500;
            animation: dropIn 0.18s ease;
        }
        .avatar-dropdown.open { display: block; }
        @keyframes dropIn {
            from { opacity:0; transform: translateY(-6px) scale(0.97); }
            to   { opacity:1; transform: none; }
        }
        .dd-header {
            padding: 14px 16px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .dd-name { font-size: 0.82rem; font-weight: 700; color: white; }
        .dd-role { font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
        .dd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; font-size: 0.8rem; font-weight: 600;
            color: rgba(255,255,255,0.65); text-decoration: none;
            transition: background 0.14s, color 0.14s; cursor: pointer;
        }
        .dd-item:hover { background: rgba(255,255,255,0.07); color: white; }
        .dd-item.danger { color: rgba(231,76,60,0.8); }
        .dd-item.danger:hover { background: rgba(231,76,60,0.1); color: #ff8a80; }
        .dd-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 2px 0; }
        /* arrow pointer */
        .avatar-dropdown::before {
            content: ''; position: absolute; top: -6px; right: 10px;
            width: 12px; height: 6px;
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            background: rgba(255,255,255,0.12);
        }


        /* BODY LAYOUT */
        .body-wrap {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* LEFT SIDEBAR */
        .sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            background: rgba(12,35,12,0.88);
            backdrop-filter: blur(14px);
            border-right: 1px solid rgba(255,255,255,0.07);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(92,184,92,0.3) transparent;
        }

        .sidebar-header {
            padding: 18px 16px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .sidebar-header::before {
            content: '';
            width: 14px;
            height: 2px;
            background: var(--dlsu-light);
            border-radius: 2px;
        }

        .nav-section {
            padding: 14px 12px 6px;
            font-size: 0.57rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
        }

        .side-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            margin: 1px 6px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255,255,255,0.55);
            transition: all 0.16s;
            position: relative;
        }

        .side-item:hover {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.9);
        }

        .side-item.active {
            background: linear-gradient(135deg, rgba(92,184,92,0.22), rgba(92,184,92,0.10));
            color: #8de88d;
            font-weight: 700;
        }

        .side-item.active::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 55%;
            background: var(--dlsu-light);
            border-radius: 0 3px 3px 0;
        }

        .side-icon {
            font-size: 0.95rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        .side-badge {
            margin-left: auto;
            background: var(--dlsu-light);
            color: white;
            font-size: 0.58rem;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 8px;
        }

        .side-soon {
            margin-left: auto;
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.28);
            font-size: 0.53rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 14px;
            border-top: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .sf-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            border: 2px solid rgba(92,184,92,0.35);
            flex-shrink: 0;
        }

        .sf-name {
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(255,255,255,0.8);
        }

        .sf-role {
            font-size: 0.62rem;
            color: rgba(255,255,255,0.35);
            margin-top: 1px;
        }

        /* MAIN CONTENT */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(92,184,92,0.3) transparent;
        }

        /* HERO */
        .hero {
            flex-shrink: 0;
            padding: 52px 52px 44px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: linear-gradient(135deg, rgba(20,60,20,0.55) 0%, rgba(10,35,10,0.35) 100%);
            backdrop-filter: blur(6px);
        }

        .hero-left {
            animation: fadeUp 0.5s ease both;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--dlsu-pale);
            margin-bottom: 14px;
        }

        .hero-eyebrow::before {
            content: '·';
            color: var(--dlsu-light);
            font-size: 1.2em;
        }

        .hero-eyebrow::after {
            content: '·';
            color: var(--dlsu-light);
            font-size: 1.2em;
        }

        .hero-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.4rem, 5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.0;
            letter-spacing: -0.03em;
            color: #ffffff;
        }

        .hero-title .line2 {
            color: var(--dlsu-pale);
            font-style: italic;
            font-size: 0.78em;
            font-weight: 700;
        }

        .hero-body {
            margin-top: 16px;
            font-size: 0.88rem;
            color: var(--muted);
            font-weight: 300;
            line-height: 1.65;
            max-width: 380px;
        }

        .hero-cta {
            margin-top: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-primary-custom {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 24px;
            background: var(--dlsu-light);
            color: white;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(92,184,92,0.4);
            transition: all 0.18s;
            letter-spacing: 0.02em;
        }

        .btn-primary-custom:hover {
            background: #6ecf6e;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(92,184,92,0.5);
        }

        .btn-ghost-custom {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.75);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-ghost-custom:hover {
            background: rgba(255,255,255,0.12);
            color: white;
        }

        /* HERO RIGHT */
        .hero-right {
            animation: fadeUp 0.5s 0.1s ease both;
            opacity: 0;
            animation-fill-mode: both;
        }

        .info-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 28px;
            backdrop-filter: blur(10px);
        }

        .info-card-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--dlsu-pale);
            margin-bottom: 6px;
        }

        .info-card-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .info-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }

        .info-stat {
            background: rgba(92,184,92,0.1);
            border: 1px solid rgba(92,184,92,0.2);
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }

        .info-stat-val {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #8de88d;
            line-height: 1;
        }

        .info-stat-label {
            font-size: 0.62rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-top: 3px;
        }

        .info-note {
            font-size: 0.76rem;
            color: var(--muted);
            line-height: 1.55;
            border-left: 2px solid rgba(92,184,92,0.4);
            padding-left: 12px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* TILES SECTION */
        .tiles-section {
            flex-shrink: 0;
            background: rgba(12,35,12,0.88);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .tiles-header {
            padding: 22px 52px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .tiles-label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tiles-label::before {
            content: '';
            width: 18px;
            height: 2px;
            background: var(--dlsu-light);
            border-radius: 2px;
        }

        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: 14px;
        }

        .tile {
            padding: 26px 24px 22px;
            border-right: 1px solid rgba(255,255,255,0.06);
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .tile:last-child {
            border-right: none;
        }

        .tile:hover {
            background: rgba(255,255,255,0.05);
        }

        .tile.soon {
            cursor: default;
            opacity: 0.55;
        }

        .tile.soon:hover {
            background: transparent;
        }

        .tile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--dlsu-light), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .tile:hover::before {
            opacity: 1;
        }

        .tile.soon::before {
            display: none;
        }

        .tile-icon {
            font-size: 2rem;
            margin-bottom: 12px;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tile-title {
            font-family: 'Syne', sans-serif;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            line-height: 1.2;
            margin-bottom: 6px;
            color: #ffffff;
        }

        .tile-desc {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.45);
            line-height: 1.5;
            flex: 1;
        }

        .tile-cta {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--dlsu-light);
            transition: gap 0.16s;
        }

        .tile:hover .tile-cta {
            gap: 8px;
        }

        .tile.soon .tile-cta {
            color: rgba(255,255,255,0.25);
        }

        .ti-green  { background: rgba(92,184,92,0.15); }
        .ti-gold   { background: rgba(255,215,0,0.12); }
        .ti-blue   { background: rgba(59,130,246,0.12); }
        .ti-orange { background: rgba(255,107,53,0.12); }
        .ti-purple { background: rgba(167,139,250,0.12); }

        .tiles-grid-2 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .tiles-grid-2 .tile {
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        .tiles-grid-2 .tile:last-child {
            border-right: none;
        }

        /* FOOTER */
        .footer {
            background: rgba(8,22,8,0.95);
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 14px 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.28);
            flex-shrink: 0;
        }

        .footer span {
            color: rgba(92,184,92,0.7);
            font-weight: 600;
        }

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(92,184,92,0.25); border-radius: 4px; }
    </style>
</head>
<body>
<div class="page">

    <!-- TOP NAV -->
    <nav class="topnav">
        <a class="nav-logo" href="#">
            <div class="nav-logo-mark">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="nav-logo-text">
                CEAT NEXUS
                <small>De La Salle University — Dasmariñas</small>
            </div>
        </a>

        <div class="nav-links">
            <a class="nav-link active" href="#">Dashboard</a>
            <a class="nav-link" href="#">About CEAT</a>
            <a class="nav-link" href="#">Programs</a>
            <a class="nav-link" href="#">Research</a>
            <a class="nav-link" href="#">Campus Map</a>
        </div>

        <div class="nav-right">
            <?php if($isAdmin): ?>
            <a href="admin.php" style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;background:rgba(231,76,60,0.18);border:1px solid rgba(231,76,60,0.4);font-size:0.62rem;font-weight:800;color:#ff8a80;letter-spacing:0.1em;text-transform:uppercase;text-decoration:none;">⚙ Admin</a>
            <?php endif; ?>
            <div class="nav-chip"><div class="chip-dot"></div>AY 2025–2026</div>
            <div class="nav-btn">🔍</div>
            <div class="nav-btn">🔔<div class="nav-notif"></div></div>
            <div class="avatar-wrap">
                <div class="nav-avatar" id="avatarBtn" onclick="toggleDropdown()"><?php echo htmlspecialchars($initials); ?></div>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="dd-header">
                        <div class="dd-name"><?php echo htmlspecialchars($studentFirstName.' '.$studentLastName); ?></div>
                        <div class="dd-role"><?php echo htmlspecialchars($studentProgram); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($studentNo); ?></div>
                    </div>
                    <?php if($isAdmin): ?>
                    <a href="admin.php" class="dd-item">⚙&nbsp; Admin Panel</a>
                    <div class="dd-divider"></div>
                    <?php endif; ?>
                    <a href="settings.php" class="dd-item">⚙&nbsp; Settings</a>
                    <div class="dd-divider"></div>
                    <a href="logout.php" class="dd-item danger">↩&nbsp; Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- BODY WRAP -->
    <div class="body-wrap">

        <!-- LEFT SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">Module Locator</div>

            <div class="nav-section">Main</div>
            <div class="side-item active"><span class="side-icon">🏠</span>Dashboard</div>
            <a href="forum.php" class="side-item" style="text-decoration:none;color:inherit;"><span class="side-icon">💬</span>Forums</a>
            <div class="side-item"><span class="side-icon">📂</span>File Locator</div>
            <div class="side-item"><span class="side-icon">📅</span>Calendar</div>

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
                    <div class="sf-role"><?php echo htmlspecialchars($studentProgram); ?><?php if($isAdmin): ?> &nbsp;·&nbsp; <span style="color:#ff8a80;font-size:0.58rem">ADMIN</span><?php endif; ?></div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main">

            <!-- HERO BANNER -->
            <section class="hero">
                <div class="hero-left">
                    <div class="hero-eyebrow">A.Y. 2025 – 2026</div>
                    <h1 class="hero-title">
                        Here for<br>
                        <span class="line2">the future.</span>
                    </h1>
                    <p class="hero-body">
                        Welcome to <strong>CEAT NEXUS</strong> — your one-stop student hub for the College of Engineering, Architecture and Technology at DLSU-Dasmariñas. Access forums, files, events, campus maps, and more.
                    </p>
                    <div class="hero-cta">
                        <button class="btn-primary-custom">Explore Programs →</button>
                        <button class="btn-ghost-custom">📢 Announcements</button>
                    </div>
                </div>

                <div class="hero-right">
                    <div class="info-card">
                        <div class="info-card-label">CEAT at a Glance</div>
                        <div class="info-card-title">College of Engineering,<br>Architecture &amp; Technology</div>
                        <div class="info-stats">
                            <?php
                            echo '<div class="info-stat">';
                            echo '<div class="info-stat-val">'.number_format($totalstudents).'</div>';
                            echo '<div class="info-stat-label">Students</div>';
                            echo '</div>';

                            echo '<div class="info-stat">';
                            echo '<div class="info-stat-val">'.number_format($totalposts).'</div>';
                            echo '<div class="info-stat-label">Forum Posts</div>';
                            echo '</div>';

                            echo '<div class="info-stat">';
                            echo '<div class="info-stat-val">'.number_format($totalfiles).'</div>';
                            echo '<div class="info-stat-label">Files Shared</div>';
                            echo '</div>';

                            echo '<div class="info-stat">';
                            echo '<div class="info-stat-val">'.$nextevent.'</div>';
                            echo '<div class="info-stat-label">Next Event</div>';
                            echo '</div>';
                            ?>
                        </div>
                        <div class="info-note">
                            CEAT Engineering Week kicks off <strong>March 5, 2026</strong>. Mark your calendars and check the Calendar module for the full schedule!
                        </div>
                    </div>
                </div>
            </section>

            <!-- MODULE TILES — Row 1 -->
            <section class="tiles-section">
                <div class="tiles-header">
                    <div class="tiles-label">Quick Access</div>
                </div>

                <div class="tiles-grid">
                    <?php
                    // Row 1 tiles data
                    $tiles = [
                        ['icon'=>'💬', 'tint'=>'ti-green',  'title'=>'Forums',       'desc'=>'Discuss, ask, and connect with fellow CEAT students.',       'soon'=>false],
                        ['icon'=>'📂', 'tint'=>'ti-gold',   'title'=>'File Locator', 'desc'=>'Lecture notes, past exams, lab manuals &amp; more.',          'soon'=>false],
                        ['icon'=>'📅', 'tint'=>'ti-blue',   'title'=>'Calendar',     'desc'=>'Events, deadlines, thesis defenses &amp; activities.',        'soon'=>false],
                        ['icon'=>'🏛️', 'tint'=>'ti-purple', 'title'=>'Room Gallery', 'desc'=>'Browse CEAT rooms, labs and studio spaces.',                  'soon'=>false],
                        ['icon'=>'🗺️', 'tint'=>'ti-orange', 'title'=>'CEAT Map',     'desc'=>'Interactive floor plan for the CEAT building.',               'soon'=>true]
                    ];

                    for($i=0; $i<count($tiles); $i++){
                        $t = $tiles[$i];
                        $soonclass = $t['soon'] ? ' soon' : '';
                        $ctatext   = $t['soon'] ? 'COMING SOON' : 'VIEW MORE ›';

                        echo '<div class="tile'.$soonclass.'">';
                        echo '<div class="tile-icon '.$t['tint'].'">'.$t['icon'].'</div>';
                        echo '<div class="tile-title">'.$t['title'].'</div>';
                        echo '<div class="tile-desc">'.$t['desc'].'</div>';
                        echo '<div class="tile-cta">'.$ctatext.'</div>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- Row 2 tiles -->
                <div class="tiles-grid-2">
                    <?php
                    $tiles2 = [
                        ['icon'=>'📢', 'tint'=>'ti-green',  'title'=>'Announcements',   'desc'=>'Official notices from the Dean\'s office and departments.'],
                        ['icon'=>'🎓', 'tint'=>'ti-gold',   'title'=>'Faculty Directory','desc'=>'Profiles and consultation schedules of CEAT faculty.'],
                        ['icon'=>'🔬', 'tint'=>'ti-blue',   'title'=>'Lab Schedules',    'desc'=>'Real-time availability of all CEAT laboratories.'],
                        ['icon'=>'🤝', 'tint'=>'ti-purple', 'title'=>'Organizations',    'desc'=>'Explore CEAT student orgs and join events.']
                    ];

                    for($i=0; $i<count($tiles2); $i++){
                        $t2 = $tiles2[$i];

                        echo '<div class="tile soon">';
                        echo '<div class="tile-icon '.$t2['tint'].'">'.$t2['icon'].'</div>';
                        echo '<div class="tile-title">'.$t2['title'].'</div>';
                        echo '<div class="tile-desc">'.$t2['desc'].'</div>';
                        echo '<div class="tile-cta">COMING SOON</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </section>

            <footer class="footer">
                <div>CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span> &nbsp;·&nbsp; College of Engineering, Architecture &amp; Technology</div>
                <div>AY 2025–2026 &nbsp;·&nbsp; All rights reserved</div>
            </footer>

        </div><!-- /main -->
    </div><!-- /body-wrap -->
</div><!-- /page -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar item click handler
    var sideitems = document.querySelectorAll('.side-item');
    for(var i = 0; i < sideitems.length; i++){
        sideitems[i].addEventListener('click', function(){
            // Remove active from all
            for(var j = 0; j < sideitems.length; j++){
                sideitems[j].classList.remove('active');
            }
            this.classList.add('active');
        });
    }

    // Nav link click handler
    var navlinks = document.querySelectorAll('.nav-link');
    for(var i = 0; i < navlinks.length; i++){
        navlinks[i].addEventListener('click', function(){
            for(var j = 0; j < navlinks.length; j++){
                navlinks[j].classList.remove('active');
            }
            this.classList.add('active');
        });
    }

    // Avatar dropdown toggle
    function toggleDropdown() {
        var btn = document.getElementById('avatarBtn');
        var dd  = document.getElementById('avatarDropdown');
        btn.classList.toggle('open');
        dd.classList.toggle('open');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        var wrap = document.querySelector('.avatar-wrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('avatarBtn').classList.remove('open');
            document.getElementById('avatarDropdown').classList.remove('open');
        }
    });
</script>
</body>
</html>
<?php
sqlsrv_close($conn);
?>