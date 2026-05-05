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

$serverName = ".\\SQLEXPRESS";
$connectionOptions = ["Database" => "PortalDB", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die(print_r(sqlsrv_errors(), true));

// ── FLASH MESSAGE ─────────────────────────────────────────────────────────────
$flash     = "";
$flashType = "success";

// ── HANDLE POST ACTIONS ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    // ── ADD EVENT ────────────────────────────────────────────────────────────
    if ($action === 'add_event') {
        $title    = trim($_POST['EVENT_TITLE']   ?? '');
        $desc     = trim($_POST['DESCRIPTION']   ?? '');
        $date     = trim($_POST['EVENT_DATE']    ?? '');
        $loc      = trim($_POST['LOCATION']      ?? '');
        $type     = trim($_POST['EVENT_TYPE']    ?? 'General');
        $timeStart = trim($_POST['TIME_START']   ?? '');
        $timeEnd   = trim($_POST['TIME_END']     ?? '');

        if ($title && $date) {
            $sql = "INSERT INTO EVENTS (STUDENT_ID, EVENT_TITLE, DESCRIPTION, EVENT_DATE, LOCATION, EVENT_TYPE, TIME_START, TIME_END)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$params = [
    $_SESSION['student_id'],
    $title, $desc, $date, $loc, $type,
    $timeStart ?: null,
    $timeEnd   ?: null
];
            $res = sqlsrv_query($conn, $sql, $params);
            if ($res === false) {
                $flashType = "error";
                $flash = "Failed to add event: " . (sqlsrv_errors()[0]['message'] ?? 'Unknown error');
            } else {
                $flash = "Event \"$title\" added successfully!";
            }
        } else {
            $flashType = "error";
            $flash = "Event title and date are required.";
        }
    }

    // ── DELETE EVENT ─────────────────────────────────────────────────────────
    if ($action === 'delete_event') {
        $eid = (int)($_POST['EVENT_ID'] ?? 0);
        if ($eid > 0) {
            $res = sqlsrv_query($conn, "DELETE FROM EVENTS WHERE EVENT_ID = ?", [$eid]);
            if ($res === false) {
                $flashType = "error";
                $flash = "Delete failed: " . (sqlsrv_errors()[0]['message'] ?? 'Unknown error');
            } else {
                $flash = "Event deleted successfully.";
            }
        }
    }

    // Redirect to avoid re-POST on refresh
    $ym = $_POST['ym'] ?? date('Y-m');
    $loc_redirect = "calendar.php?ym=$ym" . ($flash ? "&flash=" . urlencode($flash) . "&ftype=$flashType" : "");
    header("Location: $loc_redirect");
    exit;
}

// ── RESTORE FLASH FROM REDIRECT ───────────────────────────────────────────────
if (isset($_GET['flash'])) {
    $flash     = htmlspecialchars($_GET['flash']);
    $flashType = $_GET['ftype'] ?? 'success';
}

// ── CALENDAR MONTH LOGIC ──────────────────────────────────────────────────────
$ymParam  = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ymParam)) $ymParam = date('Y-m');
[$curYear, $curMonth] = explode('-', $ymParam);
$curYear  = (int)$curYear;
$curMonth = (int)$curMonth;

// Clamp
if ($curMonth < 1)  { $curMonth = 12; $curYear--; }
if ($curMonth > 12) { $curMonth = 1;  $curYear++; }

$firstDay    = mktime(0,0,0,$curMonth,1,$curYear);
$daysInMonth = (int)date('t', $firstDay);
$startDow    = (int)date('N', $firstDay); // 1=Mon … 7=Sun → convert to Sun-based
$startDow    = $startDow % 7; // Sun=0, Mon=1 … Sat=6

$prevYm = date('Y-m', mktime(0,0,0,$curMonth-1,1,$curYear));
$nextYm = date('Y-m', mktime(0,0,0,$curMonth+1,1,$curYear));
$monthName = date('F', $firstDay);

// ── FETCH EVENTS FOR THIS MONTH ───────────────────────────────────────────────
$monthStart = sprintf('%04d-%02d-01', $curYear, $curMonth);
$monthEnd   = sprintf('%04d-%02d-%02d', $curYear, $curMonth, $daysInMonth);

$sql = "SELECT EVENT_ID, EVENT_TITLE, DESCRIPTION, EVENT_DATE, LOCATION, 
               ISNULL(EVENT_TYPE,'General') AS EVENT_TYPE,
               TIME_START, TIME_END
        FROM EVENTS
        WHERE EVENT_DATE >= ? AND EVENT_DATE <= ?
        ORDER BY EVENT_DATE ASC, EVENT_ID ASC";
$res = sqlsrv_query($conn, $sql, [$monthStart, $monthEnd]);

$eventsByDay = [];
$allEvents   = [];
if ($res) {
    while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
        $day = (int)$row['EVENT_DATE']->format('j');
        $eventsByDay[$day][] = $row;
        $allEvents[] = $row;
    }
}

// ── EVENT TYPE COLOUR MAP ─────────────────────────────────────────────────────
$typeColors = [
    'General'    => ['bg'=>'rgba(92,184,92,0.22)',   'dot'=>'#5cb85c', 'tag'=>'rgba(92,184,92,0.3)'],
    'Academic'   => ['bg'=>'rgba(59,130,246,0.22)',  'dot'=>'#3b82f6', 'tag'=>'rgba(59,130,246,0.3)'],
    'Org'        => ['bg'=>'rgba(167,139,250,0.22)', 'dot'=>'#a78bfa', 'tag'=>'rgba(167,139,250,0.3)'],
    'Deadline'   => ['bg'=>'rgba(239,68,68,0.22)',   'dot'=>'#ef4444', 'tag'=>'rgba(239,68,68,0.3)'],
    'Holiday'    => ['bg'=>'rgba(251,191,36,0.22)',  'dot'=>'#fbbf24', 'tag'=>'rgba(251,191,36,0.3)'],
    'Workshop'   => ['bg'=>'rgba(20,184,166,0.22)',  'dot'=>'#14b8a6', 'tag'=>'rgba(20,184,166,0.3)'],
];
function typeColor($type, $key) {
    global $typeColors;
    return $typeColors[$type][$key] ?? $typeColors['General'][$key];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar — CEAT NEXUS</title>
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

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; }

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
        .page { position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── TOP NAV ── */
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
        .nav-logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.58rem; font-weight: 400; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 1px; }
        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link { padding: 6px 13px; font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.7); border-radius: 7px; cursor: pointer; transition: all 0.16s; text-decoration: none; letter-spacing: 0.01em; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-right { display: flex; align-items: center; gap: 8px; }
        .nav-chip { display: flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 16px; background: rgba(92,184,92,0.15); border: 1px solid rgba(92,184,92,0.3); font-size: 0.68rem; font-weight: 700; color: #8de88d; letter-spacing: 0.04em; }
        .chip-dot { width: 6px; height: 6px; border-radius: 50%; background: #5cb85c; box-shadow: 0 0 6px #5cb85c; }
        .nav-btn { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; cursor: pointer; transition: background 0.16s; position: relative; }
        .nav-btn:hover { background: rgba(255,255,255,0.13); }

        /* AVATAR DROPDOWN */
        .avatar-wrap { position: relative; }
        .nav-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid)); border: 2px solid rgba(92,184,92,0.45); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; cursor: pointer; box-shadow: 0 0 10px rgba(92,184,92,0.25); user-select: none; transition: box-shadow 0.18s; }
        .nav-avatar:hover, .nav-avatar.open { box-shadow: 0 0 18px rgba(92,184,92,0.5); }
        .avatar-dropdown { display: none; position: absolute; top: calc(100% + 10px); right: 0; width: 200px; background: rgba(10,28,10,0.97); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,0.5); z-index: 500; animation: dropIn 0.18s ease; }
        .avatar-dropdown.open { display: block; }
        @keyframes dropIn { from { opacity:0; transform: translateY(-6px) scale(0.97); } to { opacity:1; transform: none; } }
        .dd-header { padding: 14px 16px 10px; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .dd-name { font-size: 0.82rem; font-weight: 700; color: white; }
        .dd-role { font-size: 0.65rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
        .dd-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.65); text-decoration: none; transition: background 0.14s, color 0.14s; cursor: pointer; }
        .dd-item:hover { background: rgba(255,255,255,0.07); color: white; }
        .dd-item.danger { color: rgba(231,76,60,0.8); }
        .dd-item.danger:hover { background: rgba(231,76,60,0.1); color: #ff8a80; }
        .dd-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 2px 0; }

        /* ── LAYOUT ── */
        .body-wrap { display: flex; flex: 1; min-height: 0; }

        /* ── SIDEBAR ── */
        .sidebar { width: var(--sidebar-w); flex-shrink: 0; background: rgba(12,35,12,0.88); backdrop-filter: blur(14px); border-right: 1px solid rgba(255,255,255,0.07); display: flex; flex-direction: column; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(92,184,92,0.3) transparent; }
        .sidebar-header { padding: 18px 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.6rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(255,255,255,0.35); display: flex; align-items: center; gap: 7px; }
        .sidebar-header::before { content: ''; width: 14px; height: 2px; background: var(--dlsu-light); border-radius: 2px; }
        .nav-section { padding: 14px 12px 6px; font-size: 0.57rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: rgba(255,255,255,0.25); }
        .side-item { display: flex; align-items: center; gap: 10px; padding: 9px 14px; margin: 1px 6px; border-radius: 9px; cursor: pointer; font-size: 0.8rem; font-weight: 500; color: rgba(255,255,255,0.55); transition: all 0.16s; position: relative; text-decoration: none; }
        .side-item:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
        .side-item.active { background: linear-gradient(135deg, rgba(92,184,92,0.22), rgba(92,184,92,0.10)); color: #8de88d; font-weight: 700; }
        .side-item.active::before { content: ''; position: absolute; left: -6px; top: 50%; transform: translateY(-50%); width: 3px; height: 55%; background: var(--dlsu-light); border-radius: 0 3px 3px 0; }
        .side-icon { font-size: 0.95rem; width: 18px; text-align: center; flex-shrink: 0; }
        .side-soon { margin-left: auto; background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.28); font-size: 0.53rem; font-weight: 700; padding: 2px 6px; border-radius: 5px; letter-spacing: 0.05em; text-transform: uppercase; }
        .sidebar-footer { margin-top: auto; padding: 14px; border-top: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; gap: 9px; }
        .sf-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid)); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; border: 2px solid rgba(92,184,92,0.35); flex-shrink: 0; }
        .sf-name { font-size: 0.75rem; font-weight: 700; color: rgba(255,255,255,0.8); }
        .sf-role { font-size: 0.62rem; color: rgba(255,255,255,0.35); margin-top: 1px; }

        /* ── MAIN ── */
        .main { flex: 1; display: flex; flex-direction: column; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(92,184,92,0.3) transparent; }

        /* ── CALENDAR HEADER ── */
        .cal-header {
            padding: 28px 40px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, rgba(20,60,20,0.55) 0%, rgba(10,35,10,0.35) 100%);
            backdrop-filter: blur(6px);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .cal-title-group { display: flex; flex-direction: column; gap: 4px; }
        .cal-eyebrow { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--dlsu-pale); }
        .cal-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: #fff; line-height: 1; }
        .cal-nav { display: flex; align-items: center; gap: 10px; }
        .cal-nav-btn {
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.13);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; cursor: pointer;
            transition: all 0.16s; text-decoration: none; color: #fff;
        }
        .cal-nav-btn:hover { background: rgba(92,184,92,0.2); border-color: rgba(92,184,92,0.4); color: #8de88d; }
        .cal-nav-month { font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700; min-width: 130px; text-align: center; }
        .cal-header-actions { display: flex; align-items: center; gap: 10px; }
        .btn-add-event {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 20px;
            background: var(--dlsu-light);
            color: white; border: none; border-radius: 9px;
            font-family: inherit; font-size: 0.8rem; font-weight: 700;
            cursor: pointer; transition: all 0.18s;
            box-shadow: 0 4px 16px rgba(92,184,92,0.35);
            letter-spacing: 0.02em;
        }
        .btn-add-event:hover { background: #6ecf6e; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(92,184,92,0.5); }

        /* ── LEGEND ── */
        .cal-legend {
            display: flex; align-items: center; gap: 14px;
            padding: 10px 40px;
            background: rgba(12,35,12,0.6);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .legend-label { font-size: 0.58rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-right: 4px; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.68rem; font-weight: 600; color: rgba(255,255,255,0.6); }
        .legend-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* ── FLASH ── */
        .flash-bar {
            margin: 14px 40px 0;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeUp 0.3s ease;
        }
        .flash-success { background: rgba(92,184,92,0.18); border: 1px solid rgba(92,184,92,0.35); color: #8de88d; }
        .flash-error   { background: rgba(239,68,68,0.18);  border: 1px solid rgba(239,68,68,0.35);  color: #fca5a5; }

        /* ── CALENDAR GRID ── */
        .cal-body { flex: 1; padding: 20px 40px 30px; }

        .cal-dow-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            margin-bottom: 1px;
        }
        .cal-dow {
            text-align: center;
            padding: 8px 4px;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
        }
        .cal-dow.weekend { color: rgba(92,184,92,0.5); }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        .cal-cell {
            min-height: 110px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            padding: 8px 9px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            transition: background 0.16s;
            position: relative;
        }
        .cal-cell:hover { background: rgba(255,255,255,0.055); }
        .cal-cell.empty { background: transparent; border-color: transparent; }
        .cal-cell.today {
            background: rgba(92,184,92,0.1);
            border-color: rgba(92,184,92,0.35);
        }
        .cal-cell.today .cell-day { color: #5cb85c; }
        .cal-cell.weekend-col { background: rgba(255,255,255,0.015); }
        .cell-day {
            font-family: 'Syne', sans-serif;
            font-size: 0.88rem;
            font-weight: 800;
            color: rgba(255,255,255,0.55);
            line-height: 1;
            margin-bottom: 2px;
        }
        .cell-day-today {
            width: 24px; height: 24px;
            background: var(--dlsu-light);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
            color: white;
            box-shadow: 0 0 10px rgba(92,184,92,0.5);
        }
        .event-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 3px 7px;
            border-radius: 5px;
            font-size: 0.64rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.14s;
            border: 1px solid transparent;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .event-pill:hover { filter: brightness(1.2); transform: scale(1.02); }
        .event-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .event-pill-more {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.4);
            font-weight: 700;
            padding: 1px 5px;
            cursor: pointer;
        }
        .event-pill-more:hover { color: rgba(255,255,255,0.7); }

        /* ── EVENT DETAIL PANEL (right side) ── */
        .cal-wrap { display: flex; gap: 20px; align-items: flex-start; }
        .cal-grid-col { flex: 1; min-width: 0; }
        .event-panel {
            width: 280px;
            flex-shrink: 0;
            background: rgba(12,35,12,0.88);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(14px);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(92,184,92,0.3) transparent;
        }
        .panel-title { font-family: 'Syne', sans-serif; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.5); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .panel-title::before { content: ''; width: 18px; height: 2px; background: var(--dlsu-light); border-radius: 2px; }
        .panel-event-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.16s;
        }
        .panel-event-card:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.14); }
        .pec-date { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.35); margin-bottom: 4px; }
        .pec-title { font-size: 0.82rem; font-weight: 700; color: #fff; line-height: 1.3; margin-bottom: 4px; }
        .pec-loc { font-size: 0.68rem; color: rgba(255,255,255,0.45); display: flex; align-items: center; gap: 4px; }
        .pec-type { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.58rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 6px; }
        .pec-actions { display: flex; justify-content: flex-end; margin-top: 8px; }
        .btn-del { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer; transition: all 0.16s; font-family: inherit; }
        .btn-del:hover { background: rgba(239,68,68,0.28); border-color: rgba(239,68,68,0.55); }
        .panel-empty { font-size: 0.78rem; color: rgba(255,255,255,0.3); text-align: center; padding: 24px 0; }
        .panel-empty-icon { font-size: 2rem; margin-bottom: 8px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(6px);
            z-index: 900;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: rgba(12,35,12,0.98);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            padding: 32px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            animation: modalIn 0.22s ease;
        }
        @keyframes modalIn { from { opacity:0; transform: translateY(20px) scale(0.97); } to { opacity:1; transform: none; } }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .modal-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800; }
        .modal-close { width: 30px; height: 30px; border-radius: 8px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: background 0.16s; color: #fff; }
        .modal-close:hover { background: rgba(255,255,255,0.14); }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 10px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 9px;
            color: #fff; font-family: inherit; font-size: 0.82rem;
            transition: border-color 0.16s, background 0.16s;
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: rgba(92,184,92,0.5);
            background: rgba(255,255,255,0.08);
        }
        .form-select option { background: #0f260f; color: #fff; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }
        .btn-cancel { padding: 10px 20px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.13); color: rgba(255,255,255,0.7); border-radius: 9px; font-family: inherit; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.16s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .btn-submit { padding: 10px 26px; background: var(--dlsu-light); border: none; color: #fff; border-radius: 9px; font-family: inherit; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.18s; box-shadow: 0 4px 14px rgba(92,184,92,0.35); }
        .btn-submit:hover { background: #6ecf6e; transform: translateY(-1px); }

        /* DELETE CONFIRM MODAL */
        .del-modal-text { font-size: 0.88rem; color: rgba(255,255,255,0.7); line-height: 1.6; margin-bottom: 20px; }
        .del-modal-text strong { color: #fff; }
        .btn-del-confirm { padding: 10px 24px; background: rgba(239,68,68,0.85); border: none; color: #fff; border-radius: 9px; font-family: inherit; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.18s; }
        .btn-del-confirm:hover { background: #ef4444; transform: translateY(-1px); }

        /* FOOTER */
        .footer { background: rgba(8,22,8,0.95); border-top: 1px solid rgba(255,255,255,0.06); padding: 14px 52px; display: flex; align-items: center; justify-content: space-between; font-size: 0.65rem; color: rgba(255,255,255,0.28); flex-shrink: 0; }
        .footer span { color: rgba(92,184,92,0.7); font-weight: 600; }

        @keyframes fadeUp { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: none; } }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(92,184,92,0.25); border-radius: 4px; }
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
            <div class="nav-logo-text">
                CEAT NEXUS
                <small>De La Salle University — Dasmariñas</small>
            </div>
        </a>

        <div class="nav-links">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="#">About CEAT</a>
            <a class="nav-link" href="#">Programs</a>
            <a class="nav-link" href="#">Research</a>
            <a class="nav-link" href="#">Campus Map</a>
        </div>

        <div class="nav-right">
            <?php if ($isAdmin): ?>
            <a href="admin.php" style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;background:rgba(231,76,60,0.18);border:1px solid rgba(231,76,60,0.4);font-size:0.62rem;font-weight:800;color:#ff8a80;letter-spacing:0.1em;text-transform:uppercase;text-decoration:none;">⚙ Admin</a>
            <?php endif; ?>
            <div class="nav-chip"><div class="chip-dot"></div>AY 2025–2026</div>
            <div class="nav-btn">🔍</div>
            <div class="nav-btn">🔔</div>
            <div class="avatar-wrap">
                <div class="nav-avatar" id="avatarBtn" onclick="toggleDropdown()"><?php echo htmlspecialchars($initials); ?></div>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="dd-header">
                        <div class="dd-name"><?php echo htmlspecialchars($studentFirstName.' '.$studentLastName); ?></div>
                        <div class="dd-role"><?php echo htmlspecialchars($studentProgram); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($studentNo); ?></div>
                    </div>
                    <?php if ($isAdmin): ?>
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
            <a href="dashboard.php" class="side-item" style="color:inherit;"><span class="side-icon">🏠</span>Dashboard</a>
            <a href="forum.php" class="side-item" style="color:inherit;"><span class="side-icon">💬</span>Forums</a>
            <a href="file_locator.php" class="side-item" style="text-decoration:none;color:inherit;"><span class="side-icon">📂</span>File Locator</a>
            <div class="side-item active"><span class="side-icon">📅</span>Calendar</div>

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
                    <div class="sf-role"><?php echo htmlspecialchars($studentProgram); ?><?php if ($isAdmin): ?> &nbsp;·&nbsp; <span style="color:#ff8a80;font-size:0.58rem">ADMIN</span><?php endif; ?></div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main">

            <!-- CALENDAR HEADER -->
            <div class="cal-header">
                <div class="cal-title-group">
                    <div class="cal-eyebrow">📅 Academic Calendar</div>
                    <div class="cal-title"><?php echo $monthName . ' ' . $curYear; ?></div>
                </div>

                <div class="cal-nav">
                    <a href="calendar.php?ym=<?php echo $prevYm; ?>" class="cal-nav-btn">‹</a>
                    <div class="cal-nav-month"><?php echo $monthName . ' ' . $curYear; ?></div>
                    <a href="calendar.php?ym=<?php echo $nextYm; ?>" class="cal-nav-btn">›</a>
                    <a href="calendar.php?ym=<?php echo date('Y-m'); ?>" class="cal-nav-btn" title="Go to today" style="font-size:0.75rem;font-weight:700;">Today</a>
                </div>

                <div class="cal-header-actions">
                    <?php if ($isAdmin): ?>
                    <button class="btn-add-event" onclick="openAddModal()">＋ Add Event</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LEGEND -->
            <div class="cal-legend">
                <span class="legend-label">Types</span>
                <?php foreach ($typeColors as $typeName => $tc): ?>
                <div class="legend-item">
                    <div class="legend-dot" style="background:<?php echo $tc['dot']; ?>"></div>
                    <?php echo $typeName; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($flash): ?>
            <div class="flash-bar flash-<?php echo $flashType; ?>">
                <?php echo $flashType === 'success' ? '✓' : '✕'; ?>
                <?php echo $flash; ?>
            </div>
            <?php endif; ?>

            <!-- CALENDAR BODY -->
            <div class="cal-body">
                <div class="cal-wrap">

                    <!-- GRID -->
                    <div class="cal-grid-col">
                        <!-- Day-of-week headers -->
                        <div class="cal-dow-row">
                            <div class="cal-dow weekend">Sun</div>
                            <div class="cal-dow">Mon</div>
                            <div class="cal-dow">Tue</div>
                            <div class="cal-dow">Wed</div>
                            <div class="cal-dow">Thu</div>
                            <div class="cal-dow">Fri</div>
                            <div class="cal-dow weekend">Sat</div>
                        </div>

                        <div class="cal-grid" id="calGrid">
                        <?php
                        $today = (int)date('j');
                        $todayMonth = (int)date('m');
                        $todayYear  = (int)date('Y');

                        // Empty cells before first day
                        for ($e = 0; $e < $startDow; $e++) {
                            echo '<div class="cal-cell empty"></div>';
                        }

                        // Day cells
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $dow = ($startDow + $d - 1) % 7;
                            $isToday   = ($d === $today && $curMonth === $todayMonth && $curYear === $todayYear);
                            $isWeekend = ($dow === 0 || $dow === 6);

                            $cls = 'cal-cell';
                            if ($isToday)   $cls .= ' today';
                            if ($isWeekend) $cls .= ' weekend-col';

                            echo '<div class="' . $cls . '" data-day="' . $d . '">';

                            // Day number
                            if ($isToday) {
                                echo '<div class="cell-day"><div class="cell-day-today">' . $d . '</div></div>';
                            } else {
                                echo '<div class="cell-day">' . $d . '</div>';
                            }

                            // Events
                            $dayEvents = $eventsByDay[$d] ?? [];
                            $showMax   = 3;
                            foreach (array_slice($dayEvents, 0, $showMax) as $ev) {
                                $t   = htmlspecialchars($ev['EVENT_TYPE'] ?? 'General');
                                $bg  = typeColor($ev['EVENT_TYPE'] ?? 'General', 'bg');
                                $dot = typeColor($ev['EVENT_TYPE'] ?? 'General', 'dot');
                                $ttl = htmlspecialchars($ev['EVENT_TITLE']);
                                $eid = (int)$ev['EVENT_ID'];
                                echo '<div class="event-pill" style="background:' . $bg . ';border-color:' . typeColor($ev['EVENT_TYPE'] ?? 'General', 'tag') . '" '
                                   . 'onclick="showEventDetail(' . $eid . ')" title="' . $ttl . '">'
                                   . '<div class="event-dot" style="background:' . $dot . '"></div>'
                                   . '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $ttl . '</span>'
                                   . '</div>';
                            }
                            if (count($dayEvents) > $showMax) {
                                echo '<div class="event-pill-more" onclick="filterPanelDay(' . $d . ')">+' . (count($dayEvents) - $showMax) . ' more</div>';
                            }

                            echo '</div>';
                        }

                        // Fill trailing cells
                        $totalCells = $startDow + $daysInMonth;
                        $rem = $totalCells % 7;
                        if ($rem > 0) {
                            for ($e = 0; $e < (7 - $rem); $e++) {
                                echo '<div class="cal-cell empty"></div>';
                            }
                        }
                        ?>
                        </div><!-- /cal-grid -->
                    </div><!-- /cal-grid-col -->

                    <!-- EVENT PANEL -->
                    <div class="event-panel">
                        <div class="panel-title" id="panelTitle">Upcoming Events</div>

                        <?php if (empty($allEvents)): ?>
                        <div class="panel-empty">
                            <div class="panel-empty-icon">📭</div>
                            No events this month<?php echo $isAdmin ? '.<br>Use <strong>+ Add Event</strong> to create one.' : '.'; ?>
                        </div>
                        <?php else: ?>
                        <div id="eventPanelList">
                        <?php foreach ($allEvents as $ev):
                            $eid  = (int)$ev['EVENT_ID'];
                            $ttl  = htmlspecialchars($ev['EVENT_TITLE']);
                            $loc  = htmlspecialchars($ev['LOCATION'] ?? '');
                            $desc = htmlspecialchars($ev['DESCRIPTION'] ?? '');
                            $type = htmlspecialchars($ev['EVENT_TYPE'] ?? 'General');
                            $dateObj = $ev['EVENT_DATE'];
                            $dateStr = $dateObj->format('D, M j Y');
                            $dayNum  = (int)$dateObj->format('j');

                            $dotColor = typeColor($ev['EVENT_TYPE'] ?? 'General', 'dot');
                            $tagBg    = typeColor($ev['EVENT_TYPE'] ?? 'General', 'tag');

                            $timeInfo = '';
                            if (!empty($ev['TIME_START'])) {
                                $ts = is_object($ev['TIME_START']) ? $ev['TIME_START']->format('g:i A') : $ev['TIME_START'];
                                $timeInfo = $ts;
                                if (!empty($ev['TIME_END'])) {
                                    $te = is_object($ev['TIME_END']) ? $ev['TIME_END']->format('g:i A') : $ev['TIME_END'];
                                    $timeInfo .= ' – ' . $te;
                                }
                            }
                        ?>
                        <div class="panel-event-card" id="pec-<?php echo $eid; ?>" data-day="<?php echo $dayNum; ?>" onclick="highlightDay(<?php echo $dayNum; ?>)">
                            <div class="pec-date"><?php echo $dateStr; ?><?php echo $timeInfo ? ' · ' . htmlspecialchars($timeInfo) : ''; ?></div>
                            <span class="pec-type" style="background:<?php echo $tagBg; ?>;color:<?php echo $dotColor; ?>"><?php echo $type; ?></span>
                            <div class="pec-title"><?php echo $ttl; ?></div>
                            <?php if ($loc): ?>
                            <div class="pec-loc">📍 <?php echo $loc; ?></div>
                            <?php endif; ?>
                            <?php if ($desc): ?>
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-top:5px;line-height:1.5;"><?php echo $desc; ?></div>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                            <div class="pec-actions">
                                <button class="btn-del" onclick="event.stopPropagation();openDeleteModal(<?php echo $eid; ?>,'<?php echo addslashes($ttl); ?>')">🗑 Delete</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div><!-- /event-panel -->

                </div><!-- /cal-wrap -->
            </div><!-- /cal-body -->

            <footer class="footer">
                <div>CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span> &nbsp;·&nbsp; College of Engineering, Architecture &amp; Technology</div>
                <div>AY 2025–2026 &nbsp;·&nbsp; All rights reserved</div>
            </footer>

        </div><!-- /main -->
    </div><!-- /body-wrap -->
</div><!-- /page -->


<!-- ══════════════════════════════════════════════════════════════════════
     ADD EVENT MODAL (admin only)
════════════════════════════════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">📅 Add New Event</div>
            <button class="modal-close" onclick="closeAddModal()">✕</button>
        </div>
        <form method="POST" action="calendar.php?ym=<?php echo htmlspecialchars($ymParam); ?>">
            <input type="hidden" name="action" value="add_event">
            <input type="hidden" name="ym"     value="<?php echo htmlspecialchars($ymParam); ?>">

            <div class="form-group">
                <label class="form-label">Event Title *</label>
                <input type="text" name="EVENT_TITLE" class="form-input" placeholder="e.g. CEAT Engineering Week" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="EVENT_DATE" class="form-input" required id="modalDateInput">
                </div>
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <select name="EVENT_TYPE" class="form-select">
                        <option value="General">General</option>
                        <option value="Academic">Academic</option>
                        <option value="Deadline">Deadline</option>
                        <option value="Holiday">Holiday</option>
                        <option value="Org">Organization</option>
                        <option value="Workshop">Workshop</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="TIME_START" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time</label>
                    <input type="time" name="TIME_END" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="LOCATION" class="form-input" placeholder="e.g. CEAT Auditorium, Room 301…">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="DESCRIPTION" class="form-textarea" placeholder="Brief description of the event…"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-submit">Save Event</button>
            </div>
        </form>
    </div>
</div>


<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#fca5a5;">🗑 Delete Event</div>
            <button class="modal-close" onclick="closeDeleteModal()">✕</button>
        </div>
        <div class="del-modal-text">
            Are you sure you want to delete <strong id="delEventName"></strong>? This action cannot be undone.
        </div>
        <form method="POST" action="calendar.php?ym=<?php echo htmlspecialchars($ymParam); ?>" id="deleteForm">
            <input type="hidden" name="action"   value="delete_event">
            <input type="hidden" name="ym"       value="<?php echo htmlspecialchars($ymParam); ?>">
            <input type="hidden" name="EVENT_ID" id="delEventId">
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn-del-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Avatar dropdown ─────────────────────────────────────────────────────────
function toggleDropdown() {
    document.getElementById('avatarBtn').classList.toggle('open');
    document.getElementById('avatarDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.avatar-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('avatarBtn').classList.remove('open');
        document.getElementById('avatarDropdown').classList.remove('open');
    }
});

// ── Add event modal ──────────────────────────────────────────────────────────
function openAddModal(prefillDate) {
    document.getElementById('addModal').classList.add('open');
    if (prefillDate) {
        document.getElementById('modalDateInput').value = prefillDate;
    }
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('open');
}

// ── Delete modal ─────────────────────────────────────────────────────────────
function openDeleteModal(eid, ename) {
    document.getElementById('delEventId').value  = eid;
    document.getElementById('delEventName').textContent = ename;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ── Highlight calendar day + scroll to panel card ────────────────────────────
function highlightDay(day) {
    document.querySelectorAll('.cal-cell').forEach(function(c) {
        c.style.boxShadow = '';
        c.style.borderColor = '';
    });
    var cell = document.querySelector('.cal-cell[data-day="' + day + '"]');
    if (cell) {
        cell.style.boxShadow    = '0 0 0 2px rgba(92,184,92,0.6)';
        cell.style.borderColor  = 'rgba(92,184,92,0.6)';
        cell.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function showEventDetail(eid) {
    var card = document.getElementById('pec-' + eid);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        card.style.border = '1px solid rgba(92,184,92,0.5)';
        setTimeout(function() { card.style.border = ''; }, 2000);
        var day = parseInt(card.getAttribute('data-day'));
        if (day) highlightDay(day);
    }
}

// Click on day cell (empty area) to open add modal with date prefilled
<?php if ($isAdmin): ?>
document.querySelectorAll('.cal-cell:not(.empty)').forEach(function(cell) {
    cell.addEventListener('dblclick', function() {
        var day = this.getAttribute('data-day');
        var month = '<?php echo sprintf('%02d', $curMonth); ?>';
        var year  = '<?php echo $curYear; ?>';
        var d = parseInt(day);
        var dateStr = year + '-' + month + '-' + (d < 10 ? '0' + d : d);
        openAddModal(dateStr);
    });
});
<?php endif; ?>

// Filter panel by day
function filterPanelDay(day) {
    highlightDay(day);
    var cards = document.querySelectorAll('.panel-event-card');
    cards.forEach(function(c) {
        var d = parseInt(c.getAttribute('data-day'));
        c.style.display = (d === day) ? '' : 'none';
    });
    document.getElementById('panelTitle').textContent = 'Day ' + day + ' Events';
}

// Auto-dismiss flash after 4s
var flashBar = document.querySelector('.flash-bar');
if (flashBar) {
    setTimeout(function() {
        flashBar.style.transition = 'opacity 0.5s';
        flashBar.style.opacity = '0';
        setTimeout(function() { flashBar.remove(); }, 500);
    }, 4000);
}
</script>
</body>
</html>
<?php sqlsrv_close($conn); ?>