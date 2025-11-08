<?php
session_start();
require '../db.php';
include '../log_action.php';
header('Content-Type: application/json; charset=utf-8');

$room = $_GET['room'] ?? '';
$status = $_GET['status'] ?? '';

if (!$room || !$status) {
    echo json_encode(['ok' => false, 'error' => 'missing parameters']);
    exit;
}

try {
    // ✅ หาห้องจาก room_name
    $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ?");
    $stmt->execute([$room]);
    $roomData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roomData) {
        echo json_encode(['ok' => false, 'error' => 'Room not found']);
        exit;
    }

    $room_id = $roomData['id'];

    // ✅ อัปเดตหรือเพิ่มสถานะในตาราง switches
    $stmt = $conn->prepare("SELECT id FROM switches WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $switch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($switch) {
        // ถ้ามีอยู่แล้ว → อัปเดตสถานะ
        $stmt = $conn->prepare("UPDATE switches SET status = ? WHERE room_id = ?");
        $stmt->execute([$status, $room_id]);
    } else {
        // ถ้ายังไม่มี → เพิ่มใหม่
        $stmt = $conn->prepare("INSERT INTO switches (room_id, status) VALUES (?, ?)");
        $stmt->execute([$room_id, $status]);
    }

    // ✅ บันทึก Log
    $username = $_SESSION['username'] ?? 'app';
    $action = "update_switch ($room → $status)";
    log_action($conn, $username, $action);

    echo json_encode(['ok' => true, 'message' => 'Switch status updated']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
