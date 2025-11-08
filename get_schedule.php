<?php
require '../db.php';
header('Content-Type: application/json; charset=utf-8');

$room = $_GET['room'] ?? '';

if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'missing room']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, room_key, mode, start_time, end_time, enabled FROM schedules WHERE room_key = ? ORDER BY id DESC");
    $stmt->execute([$room]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'schedules' => $schedules]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
