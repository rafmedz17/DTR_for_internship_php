<?php
require_once 'config/db.php';
requireLogin();

$userId = $_SESSION['user_id'];
$today = date('Y-m-d');
$now = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'time_in') {
    // Check if already timed in today
    $stmt = $pdo->prepare("SELECT id FROM time_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);

    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO time_records (user_id, date, time_in, status) VALUES (?, ?, ?, 'present')");
        $stmt->execute([$userId, $today, $now]);
    }
}

if ($action === 'time_out') {
    $remarks = trim($_POST['remarks'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL");
    $stmt->execute([$userId, $today]);
    $record = $stmt->fetch();

    if ($record) {
        // Calculate hours worked
        $timeIn = new DateTime($record['time_in']);
        $timeOut = new DateTime($now);
        $diff = $timeIn->diff($timeOut);
        $hoursWorked = $diff->h + ($diff->i / 60);

        // Determine status
        $status = $hoursWorked >= 6 ? 'present' : 'half-day';

        $stmt = $pdo->prepare("UPDATE time_records SET time_out = ?, hours_worked = ?, status = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$now, round($hoursWorked, 2), $status, $remarks ?: null, $record['id']]);
    }
}

if ($action === 'resume') {
    // Clear time_out so the user goes back to "In Progress" state
    $stmt = $pdo->prepare("UPDATE time_records SET time_out = NULL, hours_worked = NULL, status = 'present', remarks = NULL WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
}

if ($action === 'reset_time_in') {
    $stmt = $pdo->prepare("DELETE FROM time_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
}

if ($action === 'edit_record') {
    $timeIn = $_POST['time_in'] ?? '';
    $timeOut = $_POST['time_out'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if ($timeIn && $timeOut && $timeOut > $timeIn) {
        $dtIn = new DateTime($timeIn);
        $dtOut = new DateTime($timeOut);
        $diff = $dtIn->diff($dtOut);
        $hoursWorked = $diff->h + ($diff->i / 60);
        $status = $hoursWorked >= 6 ? 'present' : 'half-day';

        $stmt = $pdo->prepare("UPDATE time_records SET time_in = ?, time_out = ?, hours_worked = ?, status = ?, remarks = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$timeIn, $timeOut, round($hoursWorked, 2), $status, $remarks ?: null, $userId, $today]);
    }
}

header('Location: dashboard.php');
exit;
