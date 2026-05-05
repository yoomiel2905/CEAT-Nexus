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

// ── DB CONNECTION ─────────────────────────────────────────────────────────────
$serverName = ".\SQLEXPRESS";
$connectionOptions = [
    "Database" => "PortalDB",
    "Uid"      => "",
    "PWD"      => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die(print_r(sqlsrv_errors(), true));
// ─────────────────────────────────────────────────────────────────────────────

$uploadMsg   = '';
$uploadError = '';
$deleteMsg   = '';

// ── ALLOWED TYPES ──────────────────────────────────────────────────────────────
$allowedMime = [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$allowedExt  = ['pdf','ppt','pptx','doc','docx','xls','xlsx'];

// ── HANDLE UPLOAD (admin only) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload' && $isAdmin) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $origName  = basename($_FILES['file']['name']);
        $tmpPath   = $_FILES['file']['tmp_name'];
        $mimeType  = mime_content_type($tmpPath);
        $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $category  = htmlspecialchars(trim($_POST['category'] ?? ''));
        $desc      = htmlspecialchars(trim($_POST['description'] ?? ''));

        if (!in_array($mimeType, $allowedMime) || !in_array($ext, $allowedExt)) {
            $uploadError = 'Invalid file type. Only PDF, PowerPoint, Word, and Excel files are allowed.';
        } else {
            // Save to uploads/ directory
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $safeName  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            $destPath  = $uploadDir . $safeName;

            if (move_uploaded_file($tmpPath, $destPath)) {
                $relativePath = 'uploads/' . $safeName;
                $studentId    = $_SESSION['student_id'];
                $sql = "INSERT INTO FILES (STUDENT_ID, FILE_NAME, FILE_PATH, CATEGORY, DESCRIPTION, DATE_UPLOADED, STATUS)
                        VALUES (?, ?, ?, ?, ?, GETDATE(), 'Active')";
                $params = [$studentId, $origName, $relativePath, $category, $desc];
                $stmt   = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    $uploadMsg = "\"$origName\" uploaded successfully!";
                } else {
                    $uploadError = 'Database error: ' . print_r(sqlsrv_errors(), true);
                    unlink($destPath);
                }
            } else {
                $uploadError = 'Failed to move uploaded file.';
            }
        }
    } else {
        $uploadError = 'No file selected or upload error.';
    }
}

// ── HANDLE DELETE (admin only) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $isAdmin) {
    $fileId = (int)($_POST['file_id'] ?? 0);
    if ($fileId > 0) {
        // Fetch path first
        $selSql  = "SELECT FILE_PATH FROM FILES WHERE FILE_ID = ?";
        $selStmt = sqlsrv_query($conn, $selSql, [$fileId]);
        $selRow  = $selStmt ? sqlsrv_fetch_array($selStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($selRow) {
            $delSql  = "DELETE FROM FILES WHERE FILE_ID = ?";
            $delStmt = sqlsrv_query($conn, $delSql, [$fileId]);
            if ($delStmt) {
                $physicalPath = __DIR__ . '/' . $selRow['FILE_PATH'];
                if (file_exists($physicalPath)) unlink($physicalPath);
                $deleteMsg = 'File deleted successfully.';
            }
        }
    }
}

// ── FILTERS ────────────────────────────────────────────────────────────────────
$filterType = $_GET['type']     ?? '';
$filterFrom = $_GET['date_from'] ?? '';
$filterTo   = $_GET['date_to']   ?? '';
$search     = $_GET['search']    ?? '';

// Build query
$where   = [];
$params  = [];

if ($filterType) {
    // Map type to extensions
    $typeMap = [
        'pdf'   => ["'%.pdf'"],
        'ppt'   => ["'%.ppt'", "'%.pptx'"],
        'word'  => ["'%.doc'", "'%.docx'"],
        'excel' => ["'%.xls'", "'%.xlsx'"],
    ];
    if (isset($typeMap[$filterType])) {
        $orClauses = array_map(fn($p) => "FILE_NAME LIKE $p", $typeMap[$filterType]);
        $where[]   = '(' . implode(' OR ', $orClauses) . ')';
    }
}
if ($filterFrom) {
    $where[]  = "CAST(DATE_UPLOADED AS DATE) >= ?";
    $params[] = $filterFrom;
}
if ($filterTo) {
    $where[]  = "CAST(DATE_UPLOADED AS DATE) <= ?";
    $params[] = $filterTo;
}
if ($search) {
    $where[]  = "(FILE_NAME LIKE ? OR DESCRIPTION LIKE ? OR CATEGORY LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$sql   = "SELECT F.FILE_ID, F.FILE_NAME, F.FILE_PATH, F.CATEGORY, F.DESCRIPTION,
                 F.DATE_UPLOADED, F.STATUS, S.FIRST_NAME + ' ' + S.LAST_NAME AS UPLOADED_BY
          FROM FILES F
          LEFT JOIN STUDENTS S ON F.STUDENT_ID = S.STUDENT_ID
          $whereClause
          ORDER BY F.DATE_UPLOADED DESC";

$stmt  = count($params) ? sqlsrv_query($conn, $sql, $params) : sqlsrv_query($conn, $sql);
$files = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $files[] = $row;
    }
}

// Helper: file icon by extension
function fileIcon($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'         => ['icon' => '📄', 'label' => 'PDF',   'color' => '#e74c3c', 'bg' => 'rgba(231,76,60,0.15)'],
        'ppt','pptx'  => ['icon' => '📊', 'label' => 'PPT',   'color' => '#e67e22', 'bg' => 'rgba(230,126,34,0.15)'],
        'doc','docx'  => ['icon' => '📝', 'label' => 'WORD',  'color' => '#3498db', 'bg' => 'rgba(52,152,219,0.15)'],
        'xls','xlsx'  => ['icon' => '📈', 'label' => 'EXCEL', 'color' => '#27ae60', 'bg' => 'rgba(39,174,96,0.15)'],
        default       => ['icon' => '📁', 'label' => 'FILE',  'color' => '#95a5a6', 'bg' => 'rgba(149,165,166,0.15)'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Locator — CEAT NEXUS</title>
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

        /* ── TOP NAV ──────────────────────────────────────────────── */
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
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; color: #ffffff;
        }

        .nav-logo-mark {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--dlsu-light), var(--dlsu-mid));
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 14px rgba(92,184,92,0.4);
        }

        .nav-logo-mark svg { width: 17px; height: 17px; fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

        .nav-logo-text { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; letter-spacing: -0.01em; }
        .nav-logo-text small { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.58rem; font-weight: 400; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 1px; }

        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link { padding: 6px 13px; font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.7); border-radius: 7px; cursor: pointer; transition: all 0.16s; text-decoration: none; letter-spacing: 0.01em; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: #ffffff; }

        .nav-right { display: flex; align-items: center; gap: 8px; }
        .nav-chip { display: flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 16px; background: rgba(92,184,92,0.15); border: 1px solid rgba(92,184,92,0.3); font-size: 0.68rem; font-weight: 700; color: #8de88d; letter-spacing: 0.04em; }
        .chip-dot { width: 6px; height: 6px; border-radius: 50%; background: #5cb85c; box-shadow: 0 0 6px #5cb85c; }

        /* Avatar */
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
        .avatar-dropdown::before { content: ''; position: absolute; top: -6px; right: 10px; width: 12px; height: 6px; clip-path: polygon(50% 0%, 0% 100%, 100% 100%); background: rgba(255,255,255,0.12); }

        /* ── BODY LAYOUT ─────────────────────────────────────────── */
        .body-wrap { display: flex; flex: 1; min-height: 0; }

        /* SIDEBAR */
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

        /* ── MAIN CONTENT ──────────────────────────────────────────── */
        .main { flex: 1; display: flex; flex-direction: column; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(92,184,92,0.3) transparent; }

        /* PAGE HEADER */
        .page-header {
            padding: 36px 44px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: linear-gradient(135deg, rgba(20,60,20,0.55) 0%, rgba(10,35,10,0.35) 100%);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            animation: fadeUp 0.4s ease both;
        }

        .page-header-left {}

        .page-eyebrow {
            font-size: 0.63rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase;
            color: var(--dlsu-pale); margin-bottom: 8px;
            display: flex; align-items: center; gap: 8px;
        }
        .page-eyebrow::before { content: ''; width: 14px; height: 2px; background: var(--dlsu-light); border-radius: 2px; }

        .page-title { font-family: 'Syne', sans-serif; font-size: 2.2rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
        .page-sub { font-size: 0.83rem; color: var(--muted); margin-top: 8px; font-weight: 400; }

        /* UPLOAD BUTTON (admin) */
        .btn-upload {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px;
            background: var(--dlsu-light);
            color: white; font-family: inherit; font-size: 0.82rem; font-weight: 700;
            border: none; border-radius: 9px; cursor: pointer;
            box-shadow: 0 4px 18px rgba(92,184,92,0.4); transition: all 0.18s;
            white-space: nowrap;
        }
        .btn-upload:hover { background: #6ecf6e; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(92,184,92,0.5); }

        /* ── FILTERS BAR ──────────────────────────────────────────── */
        .filters-bar {
            padding: 18px 44px;
            background: rgba(10,28,10,0.7);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            animation: fadeUp 0.4s 0.08s ease both;
        }

        .filter-label {
            font-size: 0.63rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: rgba(255,255,255,0.35);
            white-space: nowrap;
        }

        .filter-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .filter-select, .filter-input {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.13);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.78rem;
            font-weight: 500;
            padding: 7px 12px;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.16s, background 0.16s;
            height: 36px;
        }

        .filter-select option { background: #0f2610; color: #fff; }
        .filter-select:focus, .filter-input:focus { border-color: rgba(92,184,92,0.6); background: rgba(92,184,92,0.08); }
        .filter-input::placeholder { color: rgba(255,255,255,0.3); }

        .filter-sep { width: 1px; height: 28px; background: rgba(255,255,255,0.1); flex-shrink: 0; }

        .search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 300px; }
        .search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.4; }
        .search-input { width: 100%; padding-left: 32px !important; }

        .btn-filter {
            padding: 7px 18px; background: var(--dlsu-light); color: white;
            font-family: inherit; font-size: 0.78rem; font-weight: 700;
            border: none; border-radius: 8px; cursor: pointer;
            transition: background 0.16s; height: 36px; white-space: nowrap;
        }
        .btn-filter:hover { background: #6ecf6e; }

        .btn-reset {
            padding: 7px 14px;
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.6);
            font-family: inherit; font-size: 0.78rem; font-weight: 600;
            border: 1px solid rgba(255,255,255,0.13); border-radius: 8px;
            cursor: pointer; transition: all 0.16s; height: 36px; white-space: nowrap;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-reset:hover { background: rgba(255,255,255,0.12); color: white; }

        /* COUNT BADGE */
        .results-info {
            padding: 12px 44px 0;
            font-size: 0.72rem; color: rgba(255,255,255,0.38);
            font-weight: 500;
        }
        .results-info strong { color: var(--dlsu-pale); }

        /* ── FILES GRID ───────────────────────────────────────────── */
        .files-section { padding: 18px 44px 40px; flex: 1; }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            animation: fadeUp 0.4s 0.12s ease both;
        }

        .file-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .file-card:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(92,184,92,0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.3);
        }

        .file-card-top { display: flex; align-items: flex-start; gap: 14px; }

        .file-type-badge {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }

        .file-type-label {
            font-size: 0.42rem; font-weight: 800; letter-spacing: 0.1em;
            text-transform: uppercase; margin-top: 1px;
        }

        .file-info { flex: 1; min-width: 0; }

        .file-name {
            font-size: 0.85rem; font-weight: 700; color: white;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            line-height: 1.3; margin-bottom: 4px;
        }

        .file-meta {
            font-size: 0.68rem; color: rgba(255,255,255,0.4);
            display: flex; flex-direction: column; gap: 2px;
        }

        .file-category {
            display: inline-flex; align-items: center;
            padding: 3px 9px; border-radius: 20px;
            background: rgba(92,184,92,0.12); border: 1px solid rgba(92,184,92,0.2);
            font-size: 0.63rem; font-weight: 700; color: #8de88d;
            align-self: flex-start; letter-spacing: 0.04em;
        }

        .file-desc {
            font-size: 0.74rem; color: rgba(255,255,255,0.48);
            line-height: 1.55;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }

        .file-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.07);
            gap: 8px;
        }

        .file-date { font-size: 0.65rem; color: rgba(255,255,255,0.3); font-weight: 500; }

        .btn-download {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px;
            background: rgba(92,184,92,0.15); border: 1px solid rgba(92,184,92,0.3);
            color: #8de88d; font-family: inherit; font-size: 0.72rem; font-weight: 700;
            border-radius: 7px; cursor: pointer; transition: all 0.16s;
            text-decoration: none; white-space: nowrap;
        }
        .btn-download:hover { background: rgba(92,184,92,0.28); color: #a8f0a8; }

        .btn-delete-card {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 10px;
            background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.25);
            color: #ff8a80; font-family: inherit; font-size: 0.72rem; font-weight: 700;
            border-radius: 7px; cursor: pointer; transition: all 0.16s;
        }
        .btn-delete-card:hover { background: rgba(231,76,60,0.2); color: #ffaaaa; }

        /* ── EMPTY STATE ─────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 80px 40px;
            color: rgba(255,255,255,0.3);
            animation: fadeUp 0.4s ease both;
        }
        .empty-icon { font-size: 3.5rem; margin-bottom: 18px; opacity: 0.5; }
        .empty-title { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 800; color: rgba(255,255,255,0.45); margin-bottom: 8px; }
        .empty-sub { font-size: 0.82rem; }

        /* ── MODAL ───────────────────────────────────────────────── */
        .modal-backdrop-custom {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.65); backdrop-filter: blur(6px);
            z-index: 999; align-items: center; justify-content: center;
        }
        .modal-backdrop-custom.open { display: flex; }

        .modal-box {
            background: rgba(10,28,10,0.98);
            border: 1px solid rgba(255,255,255,0.13);
            border-radius: 20px;
            padding: 36px;
            width: 100%; max-width: 480px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            animation: modalIn 0.22s ease both;
        }
        @keyframes modalIn { from { opacity:0; transform: scale(0.95) translateY(10px); } to { opacity:1; transform: none; } }

        .modal-title { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; margin-bottom: 6px; }
        .modal-sub { font-size: 0.78rem; color: var(--muted); margin-bottom: 24px; }

        .form-label-custom { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 6px; display: block; }

        .form-control-custom {
            width: 100%; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.13);
            color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.82rem;
            padding: 10px 14px; border-radius: 9px; outline: none; transition: border-color 0.16s;
            margin-bottom: 16px;
        }
        .form-control-custom:focus { border-color: rgba(92,184,92,0.6); background: rgba(92,184,92,0.06); }
        .form-control-custom::placeholder { color: rgba(255,255,255,0.25); }
        .form-control-custom option { background: #0f2610; }

        /* File drop zone */
        .drop-zone {
            border: 2px dashed rgba(255,255,255,0.18); border-radius: 12px;
            padding: 28px 20px; text-align: center; cursor: pointer;
            transition: all 0.2s; margin-bottom: 16px; position: relative;
        }
        .drop-zone:hover, .drop-zone.dragover { border-color: rgba(92,184,92,0.6); background: rgba(92,184,92,0.06); }
        .drop-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .drop-icon { font-size: 2rem; margin-bottom: 8px; }
        .drop-text { font-size: 0.78rem; color: rgba(255,255,255,0.45); }
        .drop-text strong { color: var(--dlsu-pale); }
        .drop-allowed { font-size: 0.65rem; color: rgba(255,255,255,0.25); margin-top: 4px; }
        .drop-selected { font-size: 0.75rem; color: #8de88d; font-weight: 700; margin-top: 8px; display: none; }

        .modal-footer-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }

        .btn-cancel {
            padding: 10px 20px; background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.6); font-family: inherit; font-size: 0.82rem; font-weight: 600;
            border: 1px solid rgba(255,255,255,0.13); border-radius: 9px; cursor: pointer;
            transition: all 0.16s;
        }
        .btn-cancel:hover { background: rgba(255,255,255,0.12); color: white; }

        .btn-submit-modal {
            padding: 10px 24px; background: var(--dlsu-light); color: white;
            font-family: inherit; font-size: 0.82rem; font-weight: 700;
            border: none; border-radius: 9px; cursor: pointer;
            box-shadow: 0 4px 14px rgba(92,184,92,0.35); transition: all 0.18s;
        }
        .btn-submit-modal:hover { background: #6ecf6e; transform: translateY(-1px); }

        /* TOAST */
        .toast-wrap {
            position: fixed; bottom: 28px; right: 28px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
        }
        .toast-msg {
            padding: 12px 20px; border-radius: 10px; font-size: 0.82rem; font-weight: 600;
            box-shadow: 0 8px 28px rgba(0,0,0,0.4); animation: toastIn 0.3s ease;
        }
        .toast-success { background: rgba(39,174,96,0.92); color: white; border: 1px solid rgba(92,184,92,0.4); }
        .toast-error   { background: rgba(192,57,43,0.92);  color: white; border: 1px solid rgba(231,76,60,0.4); }
        @keyframes toastIn { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: none; } }

        /* Delete confirm modal */
        .confirm-modal { max-width: 400px; }
        .confirm-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .confirm-desc { font-size: 0.82rem; color: var(--muted); margin-bottom: 24px; line-height: 1.6; }
        .btn-danger-confirm {
            padding: 10px 24px; background: rgba(231,76,60,0.85); color: white;
            font-family: inherit; font-size: 0.82rem; font-weight: 700;
            border: none; border-radius: 9px; cursor: pointer; transition: all 0.18s;
        }
        .btn-danger-confirm:hover { background: #e74c3c; }

        /* FOOTER */
        .footer { background: rgba(8,22,8,0.95); border-top: 1px solid rgba(255,255,255,0.06); padding: 14px 52px; display: flex; align-items: center; justify-content: space-between; font-size: 0.65rem; color: rgba(255,255,255,0.28); flex-shrink: 0; }
        .footer span { color: rgba(92,184,92,0.7); font-weight: 600; }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
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
            <?php if($isAdmin): ?>
            <a href="admin.php" style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;background:rgba(231,76,60,0.18);border:1px solid rgba(231,76,60,0.4);font-size:0.62rem;font-weight:800;color:#ff8a80;letter-spacing:0.1em;text-transform:uppercase;text-decoration:none;">⚙ Admin</a>
            <?php endif; ?>
            <div class="nav-chip"><div class="chip-dot"></div>AY 2025–2026</div>
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

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">Module Locator</div>
            <div class="nav-section">Main</div>
            <a href="dashboard.php" class="side-item" style="color:inherit;"><span class="side-icon">🏠</span>Dashboard</a>
            <a href="forum.php" class="side-item" style="color:inherit;"><span class="side-icon">💬</span>Forums</a>
            <a href="file_locator.php" class="side-item active" style="color:inherit;"><span class="side-icon">📂</span>File Locator</a>
            <a href="calendar.php" class="side-item" style="color:inherit;"><span class="side-icon">📅</span>Calendar</a>
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

        <!-- MAIN -->
        <div class="main">

            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-header-left">
                    <div class="page-eyebrow">Resource Library</div>
                    <div class="page-title">📂 File Locator</div>
                    <div class="page-sub">Lecture notes, past exams, lab manuals, references &amp; more — all in one place.</div>
                </div>
                <?php if($isAdmin): ?>
                <button class="btn-upload" onclick="openUploadModal()">
                    ＋ Upload File
                </button>
                <?php endif; ?>
            </div>

            <!-- FILTER BAR -->
            <form method="GET" action="file_locator.php">
                <div class="filters-bar">
                    <span class="filter-label">Filter</span>

                    <div class="filter-group">
                        <select name="type" class="filter-select">
                            <option value="" <?= !$filterType ? 'selected':'' ?>>All Types</option>
                            <option value="pdf"   <?= $filterType==='pdf'   ? 'selected':'' ?>>📄 PDF</option>
                            <option value="ppt"   <?= $filterType==='ppt'   ? 'selected':'' ?>>📊 PowerPoint</option>
                            <option value="word"  <?= $filterType==='word'  ? 'selected':'' ?>>📝 Word</option>
                            <option value="excel" <?= $filterType==='excel' ? 'selected':'' ?>>📈 Excel</option>
                        </select>

                        <input type="date" name="date_from" class="filter-input"
                               value="<?= htmlspecialchars($filterFrom) ?>"
                               title="Date from" style="width:150px;">
                        <span style="font-size:0.7rem;color:rgba(255,255,255,0.3);">→</span>
                        <input type="date" name="date_to" class="filter-input"
                               value="<?= htmlspecialchars($filterTo) ?>"
                               title="Date to" style="width:150px;">
                    </div>

                    <div class="filter-sep"></div>

                    <div class="search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" class="filter-select filter-input search-input"
                               placeholder="Search files, categories…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <button type="submit" class="btn-filter">Apply</button>
                    <a href="file_locator.php" class="btn-reset">✕ Reset</a>
                </div>
            </form>

            <!-- RESULTS COUNT -->
            <div class="results-info">
                Showing <strong><?= count($files) ?></strong> file<?= count($files) !== 1 ? 's' : '' ?>
                <?php if($filterType || $filterFrom || $filterTo || $search): ?>
                    &nbsp;·&nbsp; Filtered view active
                <?php endif; ?>
            </div>

            <!-- FILE GRID -->
            <div class="files-section">
                <?php if(empty($files)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div class="empty-title">No files found</div>
                    <div class="empty-sub">
                        <?php if($filterType || $filterFrom || $filterTo || $search): ?>
                            Try adjusting your filters or search terms.
                        <?php elseif($isAdmin): ?>
                            No files yet. Click "Upload File" to add the first one!
                        <?php else: ?>
                            No files have been uploaded yet. Check back later.
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="files-grid">
                    <?php foreach($files as $f):
                        $fi   = fileIcon($f['FILE_NAME']);
                        $date = ($f['DATE_UPLOADED'] instanceof DateTime)
                                ? $f['DATE_UPLOADED']->format('M j, Y')
                                : date('M j, Y', strtotime($f['DATE_UPLOADED']));
                    ?>
                    <div class="file-card">
                        <div class="file-card-top">
                            <div class="file-type-badge" style="background:<?= $fi['bg'] ?>;">
                                <span><?= $fi['icon'] ?></span>
                                <span class="file-type-label" style="color:<?= $fi['color'] ?>"><?= $fi['label'] ?></span>
                            </div>
                            <div class="file-info">
                                <div class="file-name" title="<?= htmlspecialchars($f['FILE_NAME']) ?>">
                                    <?= htmlspecialchars($f['FILE_NAME']) ?>
                                </div>
                                <div class="file-meta">
                                    <?php if($f['UPLOADED_BY']): ?>
                                    <span>👤 <?= htmlspecialchars($f['UPLOADED_BY']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if($f['CATEGORY']): ?>
                        <div class="file-category"><?= htmlspecialchars($f['CATEGORY']) ?></div>
                        <?php endif; ?>

                        <?php if($f['DESCRIPTION']): ?>
                        <div class="file-desc"><?= htmlspecialchars($f['DESCRIPTION']) ?></div>
                        <?php endif; ?>

                        <div class="file-card-footer">
                            <span class="file-date">📅 <?= $date ?></span>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <a href="<?= htmlspecialchars($f['FILE_PATH']) ?>"
                                   download class="btn-download">⬇ Download</a>
                                <?php if($isAdmin): ?>
                                <button class="btn-delete-card"
                                        onclick="openDeleteModal(<?= (int)$f['FILE_ID'] ?>, '<?= htmlspecialchars(addslashes($f['FILE_NAME'])) ?>')">
                                    🗑
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <footer class="footer">
                <div>CEAT NEXUS &nbsp;·&nbsp; <span>De La Salle University — Dasmariñas</span> &nbsp;·&nbsp; File Locator</div>
                <div>AY 2025–2026 &nbsp;·&nbsp; All rights reserved</div>
            </footer>

        </div><!-- /main -->
    </div><!-- /body-wrap -->
</div><!-- /page -->

<!-- ── UPLOAD MODAL (admin) ─────────────────────────────────────────────── -->
<?php if($isAdmin): ?>
<div class="modal-backdrop-custom" id="uploadModal">
    <div class="modal-box">
        <div class="modal-title">📤 Upload File</div>
        <div class="modal-sub">Only PDF, PowerPoint, Word, and Excel files are accepted.</div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="action" value="upload">

            <label class="form-label-custom">File *</label>
            <div class="drop-zone" id="dropZone">
                <input type="file" name="file" id="fileInput" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx" required>
                <div class="drop-icon">📁</div>
                <div class="drop-text"><strong>Click to browse</strong> or drag &amp; drop</div>
                <div class="drop-allowed">PDF · PPT · PPTX · DOC · DOCX · XLS · XLSX</div>
                <div class="drop-selected" id="dropSelected"></div>
            </div>

            <label class="form-label-custom">Category</label>
            <input type="text" name="category" class="form-control-custom"
                   placeholder="e.g. Lecture Notes, Past Exam, Lab Manual…">

            <label class="form-label-custom">Description</label>
            <textarea name="description" class="form-control-custom"
                      rows="3" placeholder="Brief description of the file…"
                      style="resize:vertical;height:76px;"></textarea>

            <div class="modal-footer-btns">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn-submit-modal">Upload →</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE CONFIRM MODAL ───────────────────────────────────────────── -->
<div class="modal-backdrop-custom" id="deleteModal">
    <div class="modal-box confirm-modal">
        <div class="confirm-icon">🗑️</div>
        <div class="modal-title">Delete File?</div>
        <div class="confirm-desc" id="deleteDesc">
            This action cannot be undone. The file will be permanently removed from the server and database.
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="file_id" id="deleteFileId" value="">
            <div class="modal-footer-btns">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn-danger-confirm">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
    /* ── Avatar dropdown ── */
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

    /* ── Upload modal ── */
    function openUploadModal()  { document.getElementById('uploadModal').classList.add('open'); }
    function closeUploadModal() { document.getElementById('uploadModal').classList.remove('open'); }

    document.getElementById('uploadModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeUploadModal();
    });

    /* File drop zone */
    var fileInput = document.getElementById('fileInput');
    var dropZone  = document.getElementById('dropZone');
    var dropSel   = document.getElementById('dropSelected');

    fileInput?.addEventListener('change', function() {
        if (this.files.length) {
            dropSel.style.display = 'block';
            dropSel.textContent   = '✓ ' + this.files[0].name;
        }
    });

    dropZone?.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('dragover'); });
    dropZone?.addEventListener('dragleave', function()  { this.classList.remove('dragover'); });
    dropZone?.addEventListener('drop',      function(e) {
        e.preventDefault(); this.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            dropSel.style.display = 'block';
            dropSel.textContent   = '✓ ' + e.dataTransfer.files[0].name;
        }
    });

    /* ── Delete modal ── */
    function openDeleteModal(id, name) {
        document.getElementById('deleteFileId').value = id;
        document.getElementById('deleteDesc').innerHTML =
            'This will permanently delete <strong>' + name + '</strong>. This action cannot be undone.';
        document.getElementById('deleteModal').classList.add('open');
    }
    function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }
    document.getElementById('deleteModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    /* ── Toast notifications ── */
    function showToast(msg, type) {
        var wrap  = document.getElementById('toastWrap');
        var toast = document.createElement('div');
        toast.className = 'toast-msg toast-' + type;
        toast.textContent = msg;
        wrap.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 4000);
    }

    <?php if($uploadMsg):   ?> showToast('<?= addslashes($uploadMsg)  ?>', 'success'); <?php endif; ?>
    <?php if($uploadError): ?> showToast('<?= addslashes($uploadError) ?>', 'error');   <?php endif; ?>
    <?php if($deleteMsg):   ?> showToast('<?= addslashes($deleteMsg)  ?>', 'success'); <?php endif; ?>
</script>
</body>
</html>
<?php sqlsrv_close($conn); ?>