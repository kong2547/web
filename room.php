<?php
// room.php
session_start();
require 'db.php';

include 'log_action.php';

// ✅ ตรวจสอบการล็อกอิน
if (!isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

$username = $_SESSION['username'];

// ✅ เก็บ Log การเข้าใช้งาน
$action = basename($_SERVER['PHP_SELF']);
$stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, ?)");
$stmt->execute([$username, $action]);

// ✅ Logout
if (isset($_GET['logout'])) {
    $stmt = $conn->prepare("INSERT INTO logs (username, action) VALUES (?, 'logout')");
    $stmt->execute([$username]);
    session_destroy();
    header('location: login.php');
    exit();
}

if (!isset($_GET['id'])) die("ไม่พบห้อง");
$room_id = (int)$_GET['id'];

// ตรวจสอบ login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

/* ----------------- ดึงข้อมูลห้อง ----------------- */
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) die("ห้องไม่ถูกต้อง");

/* ----------------- Toggle switch ----------------- */
if (isset($_GET['toggle'])) {
    $sid = (int)$_GET['toggle'];
    $q = $conn->prepare("SELECT status FROM switches WHERE id=? AND room_id=?");
    $q->execute([$sid, $room_id]);
    $sw = $q->fetch();
    if ($sw) {
        $new = ($sw['status'] === 'on') ? 'off' : 'on';
        $u = $conn->prepare("UPDATE switches SET status=? WHERE id=?");
        $u->execute([$new, $sid]);
        $log = $conn->prepare("INSERT INTO switch_logs (switch_id, room_id, status) VALUES (?, ?, ?)");
        $log->execute([$sid, $room_id, $new]);
        
        // ตรวจสอบว่าเป็น AJAX request หรือไม่
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new]);
            exit();
        } else {
            header("Location: room.php?id=$room_id");
            exit();
        }
    }
    
    // ถ้าไม่ใช่ AJAX ให้ redirect ตามเดิม
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        header("Location: room.php?id=$room_id");
        exit();
    }
}

/* ----------------- เพิ่มสวิตช์ ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_switch'])) {
    $name = trim($_POST['switch_name']);
    $esp  = trim($_POST['esp_name']);
    $gpio = (int)$_POST['gpio_pin'];

    $ins = $conn->prepare("INSERT INTO switches (room_id, switch_name, esp_name, gpio_pin, status) VALUES (?, ?, ?, ?, 'off')");
    $ins->execute([$room_id, $name, $esp, $gpio]);

    header("Location: room.php?id=$room_id");
    exit();
}

/* ----------------- ลบสวิตช์ ----------------- */
if (isset($_GET['delete'])) {
    $sid = (int)$_GET['delete'];
    $d = $conn->prepare("DELETE FROM switches WHERE id=? AND room_id=?");
    $d->execute([$sid, $room_id]);
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'ลบสวิตช์สำเร็จ']);
        exit();
    } else {
        header("Location: room.php?id=$room_id");
        exit();
    }
}

/* ----------------- ดึงสวิตช์ และ logs ----------------- */
$stmt = $conn->prepare("SELECT * FROM switches WHERE room_id = ? ORDER BY id ASC");
$stmt->execute([$room_id]);
$switches = $stmt->fetchAll();

$range = $_GET['range'] ?? '1d';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if ($range === '7d') {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 7 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
} elseif ($range === '30d') {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 30 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
} elseif ($range === 'custom' && $start && $end) {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC");
    $stmt->execute([$room_id, $start.' 00:00:00', $end.' 23:59:59']);
} else {
    $stmt = $conn->prepare("SELECT switch_id, status, created_at FROM switch_logs WHERE room_id=? AND created_at >= NOW() - INTERVAL 1 DAY ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
}
$logs = $stmt->fetchAll();

/* ----------------- คำนวณเวลาที่เปิด ----------------- */
$onTimes = [];
foreach ($switches as $sw) {
    $sid = $sw['id'];
    $onTimes[$sid] = 0;
    $lastOn = null;
    foreach ($logs as $log) {
        if ($log['switch_id'] != $sid) continue;
        $time = strtotime($log['created_at']);
        if ($log['status'] === 'on') {
            $lastOn = $time;
        } elseif ($log['status'] === 'off' && $lastOn) {
            $onTimes[$sid] += ($time - $lastOn);
            $lastOn = null;
        }
    }
    if ($lastOn) {
        $onTimes[$sid] += (time() - $lastOn);
    }
}

function formatDuration($sec) {
    $h = floor($sec/3600);
    $m = floor(($sec%3600)/60);
    return ($h>0 ? $h." ชม. " : "").$m." นาที";
}

/* ----------------- SCHEDULE ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $device_id = $_POST['device_id'];
    $esp_name  = $_POST['esp_name'];
    $mode = 'on';
    $weekdays = isset($_POST['weekdays']) ? implode(',', array_map('trim', $_POST['weekdays'])) : null;
    $start_date = $_POST['start_date'] ?: null;
    $end_date   = $_POST['end_date'] ?: null;
    $start_time = $_POST['start_time'] ?: null;
    $end_time   = $_POST['end_time'] ?: null;
    $enabled    = isset($_POST['enabled']) ? 1 : 0;

    $ins = $conn->prepare("INSERT INTO schedule (device_id, esp_name, mode, weekdays, start_date, end_date, start_time, end_time, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$device_id, $esp_name, $mode, $weekdays, $start_date, $end_date, $start_time, $end_time, $enabled]);
    header("Location: room.php?id=$room_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $sid      = (int)$_POST['schedule_id'];
    $device_id = $_POST['device_id'];
    $esp_name  = $_POST['esp_name'];
    $mode = 'on';
    $weekdays  = isset($_POST['weekdays']) ? implode(',', array_map('trim', $_POST['weekdays'])) : null;
    $start_date= $_POST['start_date'] ?: null;
    $end_date  = $_POST['end_date'] ?: null;
    $start_time= $_POST['start_time'] ?: null;
    $end_time  = $_POST['end_time'] ?: null;
    $enabled   = isset($_POST['enabled']) ? 1 : 0;

    $upd = $conn->prepare("UPDATE schedule SET device_id=?, esp_name=?, mode=?, weekdays=?, start_date=?, end_date=?, start_time=?, end_time=?, enabled=? WHERE id=?");
    $upd->execute([$device_id, $esp_name, $mode, $weekdays, $start_date, $end_date, $start_time, $end_time, $enabled, $sid]);
    header("Location: room.php?id=$room_id");
    exit();
}

if (isset($_GET['del_schedule'])) {
    $sid = (int)$_GET['del_schedule'];
    $d = $conn->prepare("DELETE FROM schedule WHERE id=?");
    $d->execute([$sid]);
    header("Location: room.php?id=$room_id");
    exit();
}

if (isset($_GET['toggle_schedule'])) {
    $sid = (int)$_GET['toggle_schedule'];
    $q = $conn->prepare("SELECT enabled FROM schedule WHERE id=?");
    $q->execute([$sid]);
    $r = $q->fetch();
    if ($r) {
        $new = $r['enabled'] ? 0 : 1;
        $u = $conn->prepare("UPDATE schedule SET enabled=? WHERE id=?");
        $u->execute([$new, $sid]);
    }
    header("Location: room.php?id=$room_id");
    exit();
}

if (isset($_GET['edit_schedule'])) {
    $edit_id = (int)$_GET['edit_schedule'];
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit_schedule = $stmt->fetch();
}

/* ----------------- ดึง schedule ----------------- */
$ids = array_column($switches, 'id');
$schedules = [];
if (count($ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_map(function($id) { return "switch_$id"; }, $ids);
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE device_id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
}

// ✅ ส่วนที่แก้ไข: สร้าง Map สำหรับค้นหาชื่อสวิตช์ เพื่อใช้ในตาราง Schedule
$switch_name_map = [];
foreach ($switches as $sw) {
    $switch_name_map['switch_' . $sw['id']] = $sw['switch_name'];
}

/* ----------------- Upload รูปห้อง ----------------- */
$uploadDir = "uploads/";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (!empty($_FILES['room_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($ext, $allowed)) die("❌ Only JPG/PNG/GIF allowed");
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = "room_" . $room_id . "." . $ext;
        $filePath = $uploadDir . $fileName;
        foreach ($allowed as $e) {
            $oldFile = $uploadDir . "room_" . $room_id . "." . $e;
            if (file_exists($oldFile)) unlink($oldFile);
        }
        if (move_uploaded_file($_FILES['room_image']['tmp_name'], $filePath)) {
            $u = $conn->prepare("UPDATE rooms SET image=? WHERE id=?");
            $u->execute([$fileName, $room_id]);
        }
        header("Location: room.php?id=$room_id"); exit();
    }
}

if (isset($_GET['delete_image'])) {
    foreach (['jpg','jpeg','png','gif'] as $e) {
        $file = $uploadDir . "room_" . $room_id . "." . $e;
        if (file_exists($file)) unlink($file);
    }
    $u = $conn->prepare("UPDATE rooms SET image=NULL WHERE id=?");
    $u->execute([$room_id]);
    header("Location: room.php?id=$room_id"); exit();
}

// ----------------- สร้างตารางช่วงเวลาทำงานแต่ละ SW -----------------
$operationPeriods = [];

foreach ($switches as $sw) {
    $sid = $sw['id'];
    $lastOn = null;

    foreach ($logs as $log) {
        if ($log['switch_id'] != $sid) continue;

        $time = strtotime($log['created_at']);
        if ($log['status'] === 'on') {
            $lastOn = $time;
        } elseif ($log['status'] === 'off' && $lastOn) {
            $operationPeriods[$sid][] = [
                'on' => date('H:i:s', $lastOn),
                'off' => date('H:i:s', $time),
                'duration' => gmdate('H:i:s', $time - $lastOn)
            ];
            $lastOn = null;
        }
    }

    // ถ้ายังเปิดอยู่ ณ ปัจจุบัน
    if ($lastOn) {
        $now = time();
        $operationPeriods[$sid][] = [
            'on' => date('H:i:s', $lastOn),
            'off' => 'ยังทำงานอยู่',
            'duration' => gmdate('H:i:s', $now - $lastOn)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการห้อง <?= htmlspecialchars($room['room_name']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --light-bg: #f8f9fa;
    --card-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
}

body {
    background-color: var(--light-bg);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
}

.header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #1a2530 100%);
    color: #fff;
    padding: 1rem 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.container-main {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 15px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    margin-bottom: 1.5rem;
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
}

.card-header {
    background-color: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.25rem;
    font-weight: 600;
}

.card-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.switch-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e9ecef;
    transition: all 0.2s;
}

.switch-card:hover {
    border-color: var(--secondary-color);
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
}

.switch-info {
    flex-grow: 1;
}

.switch-name {
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.25rem;
}

.switch-details {
    font-size: 0.85rem;
    color: #6c757d;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    margin-left: 10px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success-color);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: all 0.2s;
}

.btn-primary {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
}

.btn-primary:hover {
    background-color: #2980b9;
    border-color: #2980b9;
    transform: translateY(-2px);
}

.btn-success {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.btn-warning {
    background-color: var(--warning-color);
    border-color: var(--warning-color);
}

.btn-danger {
    background-color: var(--danger-color);
    border-color: var(--danger-color);
}

.form-control, .form-select {
    border-radius: 8px;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    transition: border-color 0.2s;
}

.form-control:focus, .form-select:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

.badge {
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
}

.table {
    border-radius: 8px;
    overflow: hidden;
}

.table th {
    background-color: var(--primary-color);
    color: white;
    font-weight: 500;
    border: none;
}

.table td {
    vertical-align: middle;
    border-color: #e9ecef;
}

.esp-status-badge {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
}

.esp-status-badge.online {
    background-color: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.esp-status-badge.offline {
    background-color: rgba(231, 76, 60, 0.1);
    border: 1px solid rgba(231, 76, 60, 0.2);
    color: var(--danger-color);
}

.section-title {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.section-title i {
    margin-right: 0.5rem;
    font-size: 1.25rem;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* สไตล์สำหรับข้อความแจ้งเตือน */
.alert-position {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Loading state สำหรับ toggle */
.toggle-switch input:disabled + .slider {
    opacity: 0.6;
    cursor: not-allowed;
}

.toggle-switch input:disabled + .slider:before {
    background-color: #f8f9fa;
}

/* Latency display styles */
.latency-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.latency-good {
    background-color: #d4edda;
    color: #155724;
}

.latency-warning {
    background-color: #fff3cd;
    color: #856404;
}

.latency-poor {
    background-color: #f8d7da;
    color: #721c24;
}

/* เอฟเฟกต์แสงสำหรับหลอดไฟเมื่อเปิด */
.bulb-on {
    color: #ffc107 !important;
    filter: drop-shadow(0 0 10px rgba(255,193,7,0.7));
    animation: bulbGlow 2s infinite alternate;
}

.bulb-off {
    color: #6c757d !important;
    opacity: 0.6;
}

@keyframes bulbGlow {
    from {
        filter: drop-shadow(0 0 5px rgba(255,193,7,0.5));
    }
    to {
        filter: drop-shadow(0 0 15px rgba(255,193,7,0.8));
    }
}

/* เพิ่มการเปลี่ยนแปลงเมื่อ hover */
.fa-lightbulb {
    transition: all 0.3s ease;
}

.switch-card:hover .fa-lightbulb.bulb-off {
    opacity: 1;
    color: #adb5bd !important;
}

/* สไตล์สำหรับการแสดงผลหลอดไฟ */
.bulb-container {
    position: relative;
    display: inline-block;
    margin-right: 15px;
}

.bulb-status {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.bulb-status.on {
    background-color: var(--success-color);
    box-shadow: 0 0 5px var(--success-color);
}

.bulb-status.off {
    background-color: var(--secondary-color);
}

@media (max-width: 768px) {
    .container-main {
        margin: 10px auto;
    }
    
    .switch-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .switch-actions {
        margin-top: 0.75rem;
        width: 100%;
        display: flex;
        justify-content: space-between;
    }
    
    .toggle-switch {
        margin-left: 0;
    }
}

/* 🔇 ซ่อนกล่องผลการวัด Latency ทั้งหมด */
#latencyDisplay {
    display: none !important;
}
</style>
</head>
<body>
<header class="header">
    <div class="container-main">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">
                    <i class="fas fa-door-open me-2"></i>ห้อง: <?= htmlspecialchars($room['room_name']) ?>
                </h4>
                <p class="mb-0 opacity-75">ชั้น <?= htmlspecialchars($room['floor']) ?></p>
            </div>
            <a href="building.php" class="btn btn-warning text-dark">
                <i class="fas fa-arrow-left me-1"></i>กลับหน้าหลัก
            </a>
        </div>
        
        <div class="mt-3">
            <h6 class="mb-2">สถานะอุปกรณ์ ESP:</h6>
            <div id="espStatus" class="d-flex flex-wrap gap-2">
                <span class="badge bg-secondary">กำลังโหลดสถานะ ESP...</span>
            </div>
        </div>

        <!-- เพิ่มปุ่มทดสอบ latency -->
        <div class="mt-3">
            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#latencyTestModal">
                <i class="fas fa-stopwatch me-1"></i>ทดสอบ Latency
            </button>
        </div>
    </div>
</header>

<div class="container-main">
    <!-- Latency Display Area -->
    <div id="latencyDisplay"></div>

    <div class="row g-4">
        <!-- คอลัมน์ซ้าย -->
        <div class="col-lg-5">
            <!-- การ์ดจัดการสวิตช์ -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-plug me-2"></i>สวิตช์ในห้อง</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($switches)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-plug fa-3x text-muted mb-3"></i>
                            <p class="text-muted">ยังไม่มีสวิตช์ในห้องนี้</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($switches as $sw): ?>
                            <div class="switch-card">
                                <div class="switch-info">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bulb-container">
                                            <?php if ($sw['status'] === 'on'): ?>
                                                <i class="fas fa-lightbulb fa-2x bulb-on"></i>
                                                <div class="bulb-status on"></div>
                                            <?php else: ?>
                                                <i class="fas fa-lightbulb fa-2x bulb-off"></i>
                                                <div class="bulb-status off"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="switch-name"><?= htmlspecialchars($sw['switch_name']) ?></div>
                                            <div class="switch-details">
                                                ESP: <?= htmlspecialchars($sw['esp_name']) ?> | GPIO: <?= htmlspecialchars($sw['gpio_pin']) ?>
                                                <span class="badge bg-<?= $sw['status'] === 'on' ? 'success' : 'secondary' ?> ms-2">
                                                    <?= $sw['status'] === 'on' ? 'เปิด' : 'ปิด' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="switch-actions d-flex align-items-center">
                                    <button class="btn btn-sm btn-outline-danger me-2" 
                                            onclick="deleteSwitch(<?= $sw['id'] ?>, <?= $room_id ?>, this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?= $sw['status']==='on'?'checked':'' ?>
                                               onchange="toggleSwitch(<?= $sw['id'] ?>, <?= $room_id ?>, this)">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- การ์ดเพิ่มสวิตช์ใหม่ -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-plus-circle me-2"></i>เพิ่มสวิตช์ใหม่</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label for="switch_name" class="form-label">ชื่อสวิตช์</label>
                            <input type="text" class="form-control" id="switch_name" name="switch_name" placeholder="sw1" required>
                        </div>
                        <div class="col-12">
                            <label for="esp_name" class="form-label">ชื่อ ESP</label>
                            <input type="text" class="form-control" id="esp_name" name="esp_name" placeholder="เช่น ESP32_001" required>
                        </div>
                        <div class="col-12">
                            <label for="gpio_pin" class="form-label">GPIO Pin</label>
                            <input type="number" class="form-control" id="gpio_pin" name="gpio_pin" placeholder="เช่น 12, 13, 14" required>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary w-100" name="add_switch">
                                <i class="fas fa-save me-1"></i>บันทึกสวิตช์
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- การ์ดตั้งค่า Schedule -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i>
                        <?= isset($edit_schedule) ? "แก้ไข Schedule #".$edit_schedule['id'] : "สร้าง Schedule" ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <?php if (isset($edit_schedule)): ?>
                            <input type="hidden" name="schedule_id" value="<?= $edit_schedule['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <label class="form-label">สวิตช์</label>
                            <select name="device_id" class="form-select" required>
                                <?php foreach ($switches as $sw): ?>
                                    <option value="switch_<?= $sw['id'] ?>" <?= (isset($edit_schedule) && $edit_schedule['device_id']=="switch_".$sw['id'])?"selected":"" ?>>
                                        <?= htmlspecialchars($sw['switch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ESP Name</label>
                            <select name="esp_name" class="form-select" required>
                                <?php foreach ($switches as $sw): ?>
                                    <option value="<?= htmlspecialchars($sw['esp_name']) ?>" <?= (isset($edit_schedule) && $edit_schedule['esp_name']==$sw['esp_name'])?"selected":"" ?>>
                                        <?= htmlspecialchars($sw['esp_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">วันทำงาน (เลือกได้หลายวัน)</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                    $days = ['Mon'=>'จ','Tue'=>'อ','Wed'=>'พ','Thu'=>'พฤ','Fri'=>'ศ','Sat'=>'ส','Sun'=>'อา'];
                                    $selected_days = isset($edit_schedule['weekdays']) ? explode(',',$edit_schedule['weekdays']) : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; 
                                    foreach($days as $k=>$v) {
                                        $checked = in_array($k,$selected_days)?"checked":""; 
                                        echo "<div class='form-check form-check-inline'>
                                                <input class='form-check-input' type='checkbox' name='weekdays[]' value='$k' id='day_$k' $checked>
                                                <label class='form-check-label' for='day_$k'>$v</label>
                                              </div>";
                                    }
                                ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">วันที่เริ่ม</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $edit_schedule['start_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $edit_schedule['end_date'] ?? '' ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">เวลาเริ่ม</label>
                            <input type="time" name="start_time" class="form-control" required value="<?= $edit_schedule['start_time'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เวลาสิ้นสุด</label>
                            <input type="time" name="end_time" class="form-control" required value="<?= $edit_schedule['end_time'] ?? '' ?>">
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enabled" id="scheduleEnabled" <?= (isset($edit_schedule) && $edit_schedule['enabled'])?"checked":"" ?>>
                                <label class="form-check-label" for="scheduleEnabled">เปิดใช้งาน Schedule นี้</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-grid gap-2 d-md-flex">
                                <button class="btn btn-<?= isset($edit_schedule) ? "warning" : "success" ?> flex-grow-1"
                                        name="<?= isset($edit_schedule) ? "update_schedule" : "add_schedule" ?>">
                                    <i class="fas fa-<?= isset($edit_schedule) ? "edit" : "plus" ?> me-1"></i>
                                    <?= isset($edit_schedule) ? "บันทึกการแก้ไข" : "เพิ่ม Schedule" ?>
                                </button>
                                <?php if (isset($edit_schedule)): ?>
                                    <a href="room.php?id=<?= $room_id ?>" class="btn btn-secondary">ยกเลิก</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- คอลัมน์ขวา -->
        <div class="col-lg-7">
            <!-- การ์ดกราฟประวัติการใช้งาน -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>ประวัติการเปิด/ปิดสวิตช์</h5>
                    <div class="d-flex align-items-center">
                        <button id="modeBtn" class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-exchange-alt me-1"></i>โหมด: เวลาที่เปิดรวม
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="rangeDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-calendar me-1"></i>
                                <?= 
                                    $range === '1d' ? '1 วัน' : 
                                    ($range === '7d' ? '7 วัน' : 
                                    ($range === '30d' ? '30 วัน' : 'กำหนดเอง')) 
                                ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="room.php?id=<?= $room_id ?>&range=1d">ย้อนหลัง 1 วัน</a></li>
                                <li><a class="dropdown-item" href="room.php?id=<?= $room_id ?>&range=7d">ย้อนหลัง 7 วัน</a></li>
                                <li><a class="dropdown-item" href="room.php?id=<?= $room_id ?>&range=30d">ย้อนหลัง 30 วัน</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="get" class="px-3 py-2">
                                        <input type="hidden" name="id" value="<?= $room_id ?>">
                                        <input type="hidden" name="range" value="custom">
                                        <div class="mb-2">
                                            <label class="form-label small">จาก</label>
                                            <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">ถึง</label>
                                            <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm w-100">ตกลง</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="switchChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- การ์ดสรุประยะเวลาเปิด -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>สรุประยะเวลาเปิด (<?= htmlspecialchars($range) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>สวิตช์</th>
                                    <th class="text-end">เวลาเปิดรวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($switches as $sw): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bulb-container me-2">
                                                    <?php if ($sw['status'] === 'on'): ?>
                                                        <i class="fas fa-lightbulb text-warning bulb-on"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-lightbulb text-muted bulb-off"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <?= htmlspecialchars($sw['switch_name']) ?>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold"><?= formatDuration($onTimes[$sw['id']] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- การ์ดช่วงเวลาทำงานจริง -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>ช่วงเวลาที่สวิตช์ทำงานจริง</h5>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#operationPeriodTable">
                        <i class="fas fa-expand-alt me-1"></i>แสดง/ซ่อน
                    </button>
                </div>
                <div id="operationPeriodTable" class="collapse show">
                    <div class="card-body">
                        <?php if (empty($operationPeriods)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p>ยังไม่มีข้อมูลการเปิด/ปิดในช่วงเวลาที่เลือก</p>
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="operationAccordion">
                                <?php foreach ($switches as $index => $sw): ?>
                                    <div class="accordion-item border-0">
                                        <h2 class="accordion-header" id="heading<?= $index ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>">
                                                <div class="bulb-container me-2">
                                                    <?php if ($sw['status'] === 'on'): ?>
                                                        <i class="fas fa-lightbulb text-warning bulb-on"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-lightbulb text-muted bulb-off"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <?= htmlspecialchars($sw['switch_name']) ?>
                                                <span class="badge bg-primary ms-2"><?= count($operationPeriods[$sw['id']] ?? []) ?> รายการ</span>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#operationAccordion">
                                            <div class="accordion-body p-0">
                                                <?php if (!empty($operationPeriods[$sw['id']])): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th width="35%">เวลาเปิด</th>
                                                                    <th width="35%">เวลาปิด</th>
                                                                    <th width="30%" class="text-end">ระยะเวลา</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($operationPeriods[$sw['id']] as $p): ?>
                                                                    <tr>
                                                                        <td><?= $p['on'] ?></td>
                                                                        <td><?= $p['off'] ?></td>
                                                                        <td class="text-end"><span class="badge bg-success"><?= $p['duration'] ?></span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-3 text-muted">
                                                        <p class="mb-0">ไม่มีข้อมูลการทำงานของสวิตช์นี้</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- การ์ดรายการ Schedule -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>รายการ Schedule</h5>
                </div>
                <div class="card-body">
                    <?php if (count($schedules)===0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>ยังไม่มี Schedule</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>สวิตช์</th>
                                        <th>ESP</th>
                                        <th>วันทำงาน</th>
                                        <th>เวลา</th>
                                        <th class="text-center">สถานะ</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($schedules as $sch): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bulb-container me-2">
                                                        <?php 
                                                            $switch_status = 'off';
                                                            foreach($switches as $sw) {
                                                                if ('switch_' . $sw['id'] === $sch['device_id']) {
                                                                    $switch_status = $sw['status'];
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                        <?php if ($switch_status === 'on'): ?>
                                                            <i class="fas fa-lightbulb text-warning bulb-on"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-lightbulb text-muted bulb-off"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?= htmlspecialchars($switch_name_map[$sch['device_id']] ?? $sch['device_id']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($sch['esp_name']) ?></td>
                                            <td><?= htmlspecialchars($sch['weekdays'] ?: 'ทุกวัน') ?></td>
                                            <td><?= $sch['start_time'] ?> - <?= $sch['end_time'] ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $sch['enabled'] ? 'success' : 'secondary' ?>">
                                                    <?= $sch['enabled'] ? 'ใช้งาน' : 'ปิด' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a class="btn btn-outline-secondary" href="room.php?id=<?= $room_id ?>&toggle_schedule=<?= $sch['id'] ?>" title="Toggle">
                                                        <i class="fas fa-power-off"></i>
                                                    </a>
                                                    <a class="btn btn-primary" href="room.php?id=<?= $room_id ?>&edit_schedule=<?= $sch['id'] ?>" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a class="btn btn-danger" href="room.php?id=<?= $room_id ?>&del_schedule=<?= $sch['id'] ?>" onclick="return confirm('ยืนยันการลบ schedule?')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- การ์ดรูปห้อง -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-image me-2"></i>รูปห้อง</h5>
                </div>
                <div class="card-body">
                    <?php
                    $roomImg = null;
                    foreach (['jpg','jpeg','png','gif'] as $ext) {
                        $file = "uploads/room_" . $room_id . "." . $ext;
                        if (file_exists($file)) { $roomImg = $file; break; }
                    }
                    ?>
                    <?php if ($roomImg): ?>
                        <div class="text-center mb-3">
                            <img src="<?= $roomImg ?>?v=<?= time() ?>" class="img-fluid rounded shadow-sm" style="max-height:350px;object-fit:cover;width:100%;">
                        </div>
                        <div class="d-grid">
                            <a href="room.php?id=<?= $room_id ?>&delete_image=1" class="btn btn-danger" onclick="return confirm('ยืนยันการลบรูป?')">
                                <i class="fas fa-trash me-1"></i>ลบรูป
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-image fa-3x mb-3"></i>
                            <p>ยังไม่มีรูปห้อง</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="mt-3">
                        <div class="mb-3">
                            <label for="room_image_upload" class="form-label">อัปโหลดรูปห้อง</label>
                            <input type="file" name="room_image" id="room_image_upload" accept="image/*" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="upload_image" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i>อัปโหลด / เปลี่ยนรูป
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Latency Test Modal -->
<div class="modal fade" id="latencyTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🧪 ทดสอบ Latency</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">เลือกสวิตช์สำหรับทดสอบ:</label>
                    <select id="testSwitchSelect" class="form-select">
                        <?php foreach ($switches as $sw): ?>
                            <option value="<?= $sw['id'] ?>"><?= htmlspecialchars($sw['switch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">จำนวนครั้งที่ทดสอบ:</label>
                    <input type="number" id="testIterations" class="form-control" value="5" min="1" max="20">
                </div>
                <div id="testProgress" class="d-none">
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                    <p class="text-center mb-0" id="progressText">กำลังทดสอบ...</p>
                </div>
                <div id="testResults" class="d-none">
                    <h6>ผลลัพธ์:</h6>
                    <div id="resultsContent"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-primary" onclick="startLatencyTest()">เริ่มทดสอบ</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ตัวแปรเก็บเวลาเริ่มต้นสำหรับ latency
let latencyStartTime = 0;

const rawLogs = <?= json_encode($logs) ?>;   // log จาก PHP
const switches = <?= json_encode($switches) ?>;
const colors = ["#e74c3c","#3498db","#2ecc71","#f39c12","#9b59b6","#1abc9c"];
// ดึงวันที่ไม่ซ้ำ และเรียงจากเก่าไปใหม่
const allDates = [...new Set(rawLogs.map(l => l.created_at.substr(0,10)))];
allDates.sort(); // เรียงวันที่
const labels = allDates;

// ✅ ฟังก์ชันคำนวณเวลาเปิดรวม (ชั่วโมง/วัน)
function calculateOnTimeForDay(switch_id, date) {
    const logs = rawLogs.filter(l => l.switch_id == switch_id && l.created_at.startsWith(date));
    let totalSec = 0, lastOn = null;

    logs.forEach(l => {
        // ใช้ Date object เพื่อแปลงเวลาให้ถูกต้อง
        const t = new Date(l.created_at.replace(' ', 'T')).getTime() / 1000;
        if (l.status === 'on') {
            lastOn = t;
        } else if (l.status === 'off' && lastOn) {
            totalSec += (t - lastOn);
            lastOn = null;
        }
    });

    if (lastOn) {
        // หากสวิตช์ยังเปิดอยู่ นับเวลาจนถึงปัจจุบันหรือสิ้นวัน
        const now = Date.now() / 1000;
        const endOfDay = new Date(date + "T23:59:59").getTime() / 1000;
        const endTime = Math.min(now, endOfDay);
        if (endTime > lastOn) {
            totalSec += (endTime - lastOn);
        }
    }
    return totalSec / 3600; // ชั่วโมง
}

// ✅ ฟังก์ชันคำนวณจำนวนครั้งเปิด/วัน
function calculateOnCountForDay(switch_id, date) {
    const logs = rawLogs.filter(l => l.switch_id == switch_id && l.created_at.startsWith(date));
    return logs.filter(l => l.status === 'on').length;
}

// ✅ ฟังก์ชันสร้าง datasets ตามโหมด
function buildDatasets(mode) {
    return switches.map((sw,i) => {
        const values = labels.map(date => 
            mode === 'hours' ? calculateOnTimeForDay(sw.id, date) 
                             : calculateOnCountForDay(sw.id, date)
        );
        return {
            label: sw.switch_name,
            data: values,
            backgroundColor: colors[i % colors.length],
            borderColor: colors[i % colors.length],
            borderWidth: 1
        };
    });
}

// ✅ กำหนดโหมดเริ่มต้น = hours
let currentMode = 'hours';

// ✅ สร้าง Chart
const ctx = document.getElementById('switchChart').getContext('2d');
let chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: buildDatasets(currentMode)
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'เวลาที่เปิดสวิตช์รวมต่อวัน (ชั่วโมง)',
                font: { size: 16 }
            },
            legend: {
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (currentMode === 'hours') {
                            let h = Math.floor(context.parsed.y);
                            let m = Math.round((context.parsed.y - h) * 60);
                            return `${context.dataset.label}: ${h} ชม. ${m} นาที`;
                        } else {
                            return `${context.dataset.label}: ${context.parsed.y} ครั้ง`;
                        }
                    }
                }
            }
        },
        scales: {
            x: {
                stacked: false,
            },
            y: {
                stacked: false,
                beginAtZero: true,
                title: { display: true, text: 'ชั่วโมง' }
            }
        }
    }
});

// ✅ ปุ่มสลับโหมด
document.getElementById('modeBtn').addEventListener('click', () => {
    currentMode = (currentMode === 'hours') ? 'count' : 'hours';
    chart.data.datasets = buildDatasets(currentMode);

    // เปลี่ยน Title และ Y-axis label ตามโหมด
    if (currentMode === 'hours') {
        chart.options.plugins.title.text = 'เวลาที่เปิดสวิตช์รวมต่อวัน (ชั่วโมง)';
        chart.options.scales.y.title.text = 'ชั่วโมง';
        document.getElementById('modeBtn').innerText = "โหมด: เวลาที่เปิดรวม";
    } else {
        chart.options.plugins.title.text = 'จำนวนครั้งเปิดสวิตช์ต่อวัน';
        chart.options.scales.y.title.text = 'จำนวนครั้ง';
        document.getElementById('modeBtn').innerText = "โหมด: จำนวนครั้งเปิด";
    }
    
    chart.update();
});

// ✅ ดึงสถานะ ESP อัตโนมัติ
function refreshESPStatus() {
    fetch("api.php?cmd=esp_status&room_id=<?= $room_id ?>")
        .then(r => r.json())
        .then(data => {
            let html = "";
            data.forEach(e => {
                let statusClass = e.online ? "online" : "offline";
                let icon = e.online ? "fa-check-circle" : "fa-times-circle";
                let text  = e.online ? "เชื่อมต่อแล้ว" : "ตัดการเชื่อมต่อ";
                
                html += `
                    <div class="esp-status-badge ${statusClass}">
                        <i class="fas ${icon} me-2"></i>
                        <div class="d-flex flex-column">
                            <strong>${e.esp_name}</strong>
                            <small>${text} • ${e.last_seen || 'ไม่มีข้อมูล'}</small>
                            ${e.ip_address ? `<small>IP: ${e.ip_address}</small>` : ''}
                        </div>
                    </div>`;
            });
            document.getElementById("espStatus").innerHTML = html;
        })
        .catch(() => {
            document.getElementById("espStatus").innerHTML = `
                <div class="esp-status-badge offline">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div class="d-flex flex-column">
                        <strong>ข้อผิดพลาด</strong>
                        <small>ไม่สามารถโหลดสถานะ ESP ได้</small>
                    </div>
                </div>`;
        });
}

// ฟังก์ชันอัพเดทไอคอนหลอดไฟ
function updateBulbIcon(switchId, status) {
    const switchCard = document.querySelector(`.switch-card input[onchange*="toggleSwitch(${switchId}"]`).closest('.switch-card');
    const bulbIcon = switchCard.querySelector('.fa-lightbulb');
    const bulbStatus = switchCard.querySelector('.bulb-status');
    const statusBadge = switchCard.querySelector('.badge');
    
    if (status === 'on') {
        bulbIcon.classList.remove('bulb-off', 'text-muted');
        bulbIcon.classList.add('bulb-on', 'text-warning');
        if (bulbStatus) {
            bulbStatus.classList.remove('off');
            bulbStatus.classList.add('on');
        }
        if (statusBadge) {
            statusBadge.classList.remove('bg-secondary');
            statusBadge.classList.add('bg-success');
            statusBadge.textContent = 'เปิด';
        }
    } else {
        bulbIcon.classList.remove('bulb-on', 'text-warning');
        bulbIcon.classList.add('bulb-off', 'text-muted');
        if (bulbStatus) {
            bulbStatus.classList.remove('on');
            bulbStatus.classList.add('off');
        }
        if (statusBadge) {
            statusBadge.classList.remove('bg-success');
            statusBadge.classList.add('bg-secondary');
            statusBadge.textContent = 'ปิด';
        }
    }
}

// ฟังก์ชัน toggle สวิตช์แบบ AJAX พร้อมวัด latency
function toggleSwitch(switchId, roomId, checkbox) {
    // เริ่มจับเวลา
    latencyStartTime = performance.now();
    
    // ตรวจสอบสถานะปัจจุบันและสถานะใหม่
    const currentStatus = checkbox.checked;
    const newStatus = !currentStatus;
    
    // อัพเดทไอคอนหลอดไฟทันที (สำหรับการตอบสนองที่รวดเร็ว)
    updateBulbIcon(switchId, newStatus ? 'on' : 'off');
    
    // ป้องกันการคลิกซ้ำขณะโหลด
    checkbox.disabled = true;
    
    // ส่ง request ไปยัง server
    fetch(`room.php?id=${roomId}&toggle=${switchId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // อัพเดทสถานะ checkbox ตาม response
            checkbox.checked = data.new_status === 'on';
            
            // อัพเดทไอคอนหลอดไฟอีกครั้งเพื่อความแน่นอน
            updateBulbIcon(switchId, data.new_status);
            
            // คำนวณ latency (เว็บ → เซิร์ฟเวอร์)
            const webToServerLatency = performance.now() - latencyStartTime;
            
            // เรียกฟังก์ชันวัด latency แบบเต็ม (รอการยืนยันจาก ESP32)
            measureFullLatency(switchId, roomId, data.new_status, webToServerLatency, currentStatus);
            
            // แสดงข้อความสำเร็จชั่วคราว
            showTempMessage('อัพเดทสถานะสวิตช์สำเร็จ', 'success');
            
            // รีเฟรชข้อมูล ESP status
            refreshESPStatus();
            
        } else {
            showTempMessage('เกิดข้อผิดพลาดในการอัพเดทสถานะ', 'error');
            // รีเซตไอคอนกลับสู่สถานะเดิม
            updateBulbIcon(switchId, currentStatus ? 'on' : 'off');
            checkbox.checked = !checkbox.checked;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showTempMessage('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        // รีเซตไอคอนกลับสู่สถานะเดิม
        updateBulbIcon(switchId, currentStatus ? 'on' : 'off');
        checkbox.checked = !checkbox.checked;
    })
    .finally(() => {
        checkbox.disabled = false;
    });
}

// ฟังก์ชันวัด latency แบบเต็ม (เว็บ → ESP32) - แยกเปิด/ปิด
function measureFullLatency(switchId, roomId, newStatus, webToServerLatency, previousStatus) {
    const fullLatencyStartTime = performance.now();
    let latencyChecked = false;
    
    // กำหนดประเภทการดำเนินการ
    const actionType = newStatus === 'on' ? 'เปิด' : 'ปิด';
    
    // ฟังก์ชันตรวจสอบสถานะ ESP32 จนกว่าจะเปลี่ยน
    function checkESPStatus() {
        if (latencyChecked) return;
        
        fetch(`api.php?cmd=get_switch_status&switch_id=${switchId}&room_id=${roomId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status === newStatus) {
                    // ESP32 อัพเดทสถานะแล้ว
                    latencyChecked = true;
                    const fullLatency = performance.now() - fullLatencyStartTime;
                    
                    // แสดงผล latency พร้อมแยกประเภท
                    showLatencyResult(webToServerLatency, fullLatency, switchId, newStatus, actionType, previousStatus);
                    
                    // บันทึก latency ลงฐานข้อมูล
                    logLatencyToDB(webToServerLatency, fullLatency, switchId, roomId, newStatus, actionType);
                    
                } else {
                    // ยังไม่อัพเดท, ตรวจสอบอีกครั้งใน 100ms
                    setTimeout(checkESPStatus, 100);
                }
            })
            .catch(error => {
                console.error('Error checking ESP status:', error);
                if (!latencyChecked) {
                    setTimeout(checkESPStatus, 100);
                }
            });
    }
    
    // เริ่มตรวจสอบสถานะ ( timeout หลังจาก 10 วินาที)
    checkESPStatus();
    setTimeout(() => {
        if (!latencyChecked) {
            latencyChecked = true;
            showTempMessage(`⚠️ ไม่สามารถวัด latency การ${actionType}ได้ (Timeout)`, 'warning');
        }
    }, 10000);
}

// ฟังก์ชันแสดงผล latency - แยกเปิด/ปิด
function showLatencyResult(webToServerLatency, fullLatency, switchId, status, actionType, previousStatus) {
    const latencyDisplay = document.getElementById('latencyDisplay') || createLatencyDisplay();
    
    const latencyClass = fullLatency < 500 ? 'latency-good' : 
                        fullLatency < 1000 ? 'latency-warning' : 'latency-poor';
    
    // เลือกไอคอนตามประเภทการดำเนินการ
    const actionIcon = actionType === 'เปิด' ? '🔛' : '🔜';
    const statusIcon = status === 'on' ? '💡' : '⚫';
    
    const resultHTML = `
        <div class="alert alert-info alert-dismissible fade show">
            <h6>${actionIcon} ผลการวัด Latency การ${actionType}</h6>
            <div class="row">
                <div class="col-6">
                    <small>เว็บ → เซิร์ฟเวอร์: <strong>${webToServerLatency.toFixed(0)} ms</strong></small>
                </div>
                <div class="col-6">
                    <small>ทั้งหมด (เว็บ → ESP32): 
                        <span class="badge latency-badge ${latencyClass}">${fullLatency.toFixed(0)} ms</span>
                    </small>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-6">
                    <small>สถานะก่อนหน้า: ${previousStatus ? 'เปิด' : 'ปิด'} ${previousStatus ? '🔛' : '🔜'}</small>
                </div>
                <div class="col-6">
                    <small>สถานะปัจจุบัน: ${status === 'on' ? 'เปิด' : 'ปิด'} ${statusIcon}</small>
                </div>
            </div>
            <div class="mt-1">
                <small>สวิตช์: ${switchId} | ประเภท: ${actionType} | วัดเมื่อ: ${new Date().toLocaleTimeString()}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    latencyDisplay.innerHTML = resultHTML + latencyDisplay.innerHTML;
}

// สร้างพื้นที่แสดงผล latency
function createLatencyDisplay() {
    const div = document.createElement('div');
    div.id = 'latencyDisplay';
    div.className = 'mt-3';
    document.querySelector('.container-main').insertBefore(div, document.querySelector('.container-main').firstChild);
    return div;
}

// ฟังก์ชันบันทึก latency ลงฐานข้อมูล - แยกเปิด/ปิด
function logLatencyToDB(webToServerLatency, fullLatency, switchId, roomId, status, actionType) {
    fetch('api.php?cmd=log_latency', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            switch_id: switchId,
            room_id: roomId,
            web_to_server_ms: Math.round(webToServerLatency),
            full_latency_ms: Math.round(fullLatency),
            status: status,
            action_type: actionType, // ✅ เพิ่มประเภทการดำเนินการ
            timestamp: new Date().toISOString()
        })
    }).catch(error => console.error('Error logging latency:', error));
}

// ฟังก์ชันแสดงข้อความชั่วคราว
function showTempMessage(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // ลบข้อความหลังจาก 3 วินาที
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}

// ฟังก์ชันลบสวิตช์แบบ AJAX
function deleteSwitch(switchId, roomId, button) {
    if (!confirm('ยืนยันการลบสวิตช์?')) return;
    
    // ป้องกันการคลิกซ้ำ
    button.disabled = true;
    
    fetch(`room.php?id=${roomId}&delete=${switchId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTempMessage('ลบสวิตช์สำเร็จ', 'success');
            setTimeout(() => {
                location.reload(); // รีโหลดหน้าเมื่อลบสำเร็จ
            }, 1000);
        } else {
            showTempMessage('เกิดข้อผิดพลาดในการลบ', 'error');
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showTempMessage('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        button.disabled = false;
    });
}

// ฟังก์ชันทดสอบ latency หลายครั้ง - แยกเปิด/ปิด
async function startLatencyTest() {
    const switchId = document.getElementById('testSwitchSelect').value;
    const iterations = parseInt(document.getElementById('testIterations').value);
    const progress = document.getElementById('testProgress');
    const results = document.getElementById('testResults');
    const progressBar = progress.querySelector('.progress-bar');
    const progressText = document.getElementById('progressText');
    const resultsContent = document.getElementById('resultsContent');
    
    // รีเซต UI
    progress.classList.remove('d-none');
    results.classList.add('d-none');
    progressBar.style.width = '0%';
    
    const onLatencies = [];   // สำหรับการเปิด
    const offLatencies = [];  // สำหรับการปิด
    
    for (let i = 0; i < iterations; i++) {
        progressText.textContent = `กำลังทดสอบ... (${i + 1}/${iterations})`;
        progressBar.style.width = `${((i + 1) / iterations) * 100}%`;
        
        try {
            // ทดสอบการเปิด
            const onLatency = await performSingleLatencyTest(switchId, <?= $room_id ?>, 'on');
            onLatencies.push(onLatency);
            
            // รอ 1 วินาทีก่อนทดสอบการปิด
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // ทดสอบการปิด
            const offLatency = await performSingleLatencyTest(switchId, <?= $room_id ?>, 'off');
            offLatencies.push(offLatency);
            
            // รอระหว่างการทดสอบ
            if (i < iterations - 1) {
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        } catch (error) {
            console.error('Test failed:', error);
            onLatencies.push(-1);
            offLatencies.push(-1);
        }
    }
    
    // แสดงผลลัพธ์แยกเปิด/ปิด
    showTestResults(onLatencies, offLatencies, resultsContent);
    progress.classList.add('d-none');
    results.classList.remove('d-none');
}

// ฟังก์ชันทดสอบ latency ครั้งเดียว - แยกเปิด/ปิด
function performSingleLatencyTest(switchId, roomId, targetStatus) {
    return new Promise((resolve, reject) => {
        const startTime = performance.now();
        let completed = false;
        
        // ส่งคำสั่งตามสถานะเป้าหมาย
        fetch(`room.php?id=${roomId}&toggle=${switchId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                reject(new Error('Toggle failed'));
                return;
            }
            
            const checkStartTime = performance.now();
            
            // ตรวจสอบจนกว่า ESP32 จะอัพเดท
            function checkStatus() {
                if (completed) return;
                
                fetch(`api.php?cmd=get_switch_status&switch_id=${switchId}&room_id=${roomId}`)
                    .then(r => r.json())
                    .then(statusData => {
                        if (statusData.success && statusData.status === targetStatus) {
                            completed = true;
                            const latency = performance.now() - startTime;
                            resolve(latency);
                        } else {
                            // ตรวจสอบอีกครั้งใน 100ms (ไม่เกิน 10 วินาที)
                            if (performance.now() - checkStartTime < 10000) {
                                setTimeout(checkStatus, 100);
                            } else {
                                completed = true;
                                reject(new Error(`Timeout waiting for ESP32 to ${targetStatus}`));
                            }
                        }
                    })
                    .catch(() => {
                        if (performance.now() - checkStartTime < 10000) {
                            setTimeout(checkStatus, 100);
                        } else {
                            completed = true;
                            reject(new Error('Timeout'));
                        }
                    });
            }
            
            checkStatus();
        })
        .catch(reject);
    });
}

// แสดงผลลัพธ์การทดสอบ - แยกเปิด/ปิด
function showTestResults(onLatencies, offLatencies, container) {
    const validOnLatencies = onLatencies.filter(l => typeof l === 'number' && l > 0);
    const validOffLatencies = offLatencies.filter(l => typeof l === 'number' && l > 0);
    
    if (validOnLatencies.length === 0 && validOffLatencies.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">ไม่สามารถวัด latency ได้</div>';
        return;
    }
    
    // คำนวณผลลัพธ์สำหรับการเปิด
    let onResults = '';
    if (validOnLatencies.length > 0) {
        const onAvg = validOnLatencies.reduce((a, b) => a + b, 0) / validOnLatencies.length;
        const onMin = Math.min(...validOnLatencies);
        const onMax = Math.max(...validOnLatencies);
        
        onResults = `
            <div class="mb-4">
                <h6>🔛 ผลการวัด Latency การเปิด</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tr><td>จำนวนการทดสอบ:</td><td>${validOnLatencies.length}/${onLatencies.length} ครั้ง</td></tr>
                        <tr><td>ค่าเฉลี่ย:</td><td><strong>${onAvg.toFixed(0)} ms</strong></td></tr>
                        <tr><td>ต่ำสุด:</td><td>${onMin.toFixed(0)} ms</td></tr>
                        <tr><td>สูงสุด:</td><td>${onMax.toFixed(0)} ms</td></tr>
                    </table>
                </div>
            </div>
        `;
    }
    
    // คำนวณผลลัพธ์สำหรับการปิด
    let offResults = '';
    if (validOffLatencies.length > 0) {
        const offAvg = validOffLatencies.reduce((a, b) => a + b, 0) / validOffLatencies.length;
        const offMin = Math.min(...validOffLatencies);
        const offMax = Math.max(...validOffLatencies);
        
        offResults = `
            <div class="mb-4">
                <h6>🔜 ผลการวัด Latency การปิด</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tr><td>จำนวนการทดสอบ:</td><td>${validOffLatencies.length}/${offLatencies.length} ครั้ง</td></tr>
                        <tr><td>ค่าเฉลี่ย:</td><td><strong>${offAvg.toFixed(0)} ms</strong></td></tr>
                        <tr><td>ต่ำสุด:</td><td>${offMin.toFixed(0)} ms</td></tr>
                        <tr><td>สูงสุด:</td><td>${offMax.toFixed(0)} ms</td></tr>
                    </table>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = onResults + offResults;
    
    // เพิ่มรายละเอียดแต่ละครั้ง
    if (validOnLatencies.length > 0 || validOffLatencies.length > 0) {
        let details = '<div class="mt-2"><small class="text-muted">รายละเอียดแต่ละครั้ง:</small><br>';
        
        if (validOnLatencies.length > 0) {
            details += `<small>🔛 เปิด: ${validOnLatencies.map(l => l.toFixed(0)).join(', ')} ms</small><br>`;
        }
        
        if (validOffLatencies.length > 0) {
            details += `<small>🔜 ปิด: ${validOffLatencies.map(l => l.toFixed(0)).join(', ')} ms</small>`;
        }
        
        details += '</div>';
        container.innerHTML += details;
    }
}

// เรียกใช้ทันทีและตั้งเวลาทุก 5 วินาที
refreshESPStatus();
setInterval(refreshESPStatus, 5000);
</script>
</body>
</html>