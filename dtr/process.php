<?php
require_once 'config/db.php';
requireLogin();

$userId = $_SESSION['user_id'];
$today  = date('Y-m-d');
$now    = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';

// AM Time In
if ($action === 'time_in') {
    $stmt = $pdo->prepare("SELECT id FROM time_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO time_records (user_id, date, time_in, status) VALUES (?, ?, ?, 'present')");
        $stmt->execute([$userId, $today, $now]);
    }
}

// Lunch Break (AM Time Out)
if ($action === 'lunch_break') {
    $stmt = $pdo->prepare("SELECT id FROM time_records WHERE user_id = ? AND date = ? AND time_in IS NOT NULL AND am_time_out IS NULL");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE time_records SET am_time_out = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$now, $userId, $today]);
    }
}

// PM Time In (Return from Lunch)
if ($action === 'pm_time_in') {
    $stmt = $pdo->prepare("SELECT id FROM time_records WHERE user_id = ? AND date = ? AND am_time_out IS NOT NULL AND pm_time_in IS NULL");
    $stmt->execute([$userId, $today]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE time_records SET pm_time_in = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$now, $userId, $today]);
    }
}

// PM Time Out (End of Day)
if ($action === 'time_out') {
    $stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND date = ? AND pm_time_in IS NOT NULL AND time_out IS NULL");
    $stmt->execute([$userId, $today]);
    $record = $stmt->fetch();

    if ($record) {
        // AM hours
        $amIn  = new DateTime($record['time_in']);
        $amOut = new DateTime($record['am_time_out']);
        $amHours = $amIn->diff($amOut)->h + ($amIn->diff($amOut)->i / 60);

        // PM hours
        $pmIn  = new DateTime($record['pm_time_in']);
        $pmOut = new DateTime($now);
        $pmHours = $pmIn->diff($pmOut)->h + ($pmIn->diff($pmOut)->i / 60);

        // Total: capped at 8 hours
        $total     = min(8.00, round($amHours + $pmHours, 2));
        $underTime = round(max(0, 8.00 - $total), 2);
        $status    = $total >= 6 ? 'present' : 'half-day';

        $stmt = $pdo->prepare("UPDATE time_records SET time_out = ?, hours_worked = ?, under_time = ?, status = ? WHERE id = ?");
        $stmt->execute([$now, $total, $underTime, $status, $record['id']]);
    }
}

// Undo AM Time Out → back to State 2
if ($action === 'undo_am_timeout') {
    $stmt = $pdo->prepare("UPDATE time_records SET am_time_out = NULL WHERE user_id = ? AND date = ? AND pm_time_in IS NULL");
    $stmt->execute([$userId, $today]);
}

// Undo PM Time In → back to State 3
if ($action === 'undo_pm_timein') {
    $stmt = $pdo->prepare("UPDATE time_records SET pm_time_in = NULL WHERE user_id = ? AND date = ? AND time_out IS NULL");
    $stmt->execute([$userId, $today]);
}

// Resume (clear PM Time Out only — keeps AM/PM in session)
if ($action === 'resume') {
    $stmt = $pdo->prepare("UPDATE time_records SET time_out = NULL, hours_worked = NULL, under_time = NULL, status = 'present' WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
}

// Undo (delete entire today's record)
if ($action === 'reset_time_in') {
    $stmt = $pdo->prepare("DELETE FROM time_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
}

// Save Journal only
if ($action === 'save_journal') {
    $remarks = trim($_POST['remarks'] ?? '');
    $stmt = $pdo->prepare("UPDATE time_records SET remarks = ? WHERE user_id = ? AND date = ?");
    $stmt->execute([$remarks ?: null, $userId, $today]);
}

header('Location: dashboard.php');
exit;
