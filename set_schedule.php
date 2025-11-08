<?php
require '../db.php';
include '../log_action.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true);
$room = $input['room'] ?? '';
$mode = $input['mode'] ?? '';
$start = $input['start_time'] ?? '';
$end = $input['end_time'] ?? '';
$enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;

if (!$room || !$start || !$end) {
    echo json_encode(['ok' => false, 'error' => 'missing parameters']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO schedules (room_key, mode, start_time, end_time, enabled) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$room, $mode, $start, $end, $enabled]);

    $username = $_SESSION['username'] ?? 'app';
    $action = "set_schedule ($room → $start - $end)";
    log_action($conn, $username, $action);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
