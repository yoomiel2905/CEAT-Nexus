<?php
// ── PASTE THIS BLOCK AT THE VERY TOP OF dashboard.php (before anything else) ──
session_start();

// If not logged in, send to login page
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

// ── Convenience variables (use these anywhere in dashboard.php) ─────────────
$studentFirstName = $_SESSION['first_name'];    // e.g. "Juan"
$studentLastName  = $_SESSION['last_name'];     // e.g. "Santos"
$studentProgram   = $_SESSION['program'];       // e.g. "BSCpE"
$studentEmail     = $_SESSION['email'];
$studentNo        = $_SESSION['student_no'];    // e.g. "202330803"

// ── For the nav avatar initials ─────────────────────────────────────────────
$initials = strtoupper(substr($studentFirstName, 0, 1) . substr($studentLastName, 0, 1));

// THEN continue with your existing $serverName / sqlsrv_connect code below...
