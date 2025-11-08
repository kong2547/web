<?php
require '../db.php';
header('Content-Type: application/json; charset=utf-8');

$room = $_GET['room'] ?? '';

if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'missing room']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT light_status FROM rooms WHERE room_key = ?");
    $stmt->execute([$room]);
    $status = $stmt->fetchColumn();

    if ($status !== false) {
        echo json_encode(['ok' => true, 'room' => $room, 'status' => $status]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'room not found']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
