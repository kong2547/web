<?php
// room.php
session_start();
require 'db.php'; // ตรวจสอบให้แน่ใจว่าไฟล์ db.php เชื่อมต่อฐานข้อมูลได้ถูกต้อง

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
    }
    header("Location: room.php?id=$room_id");
    exit();
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
    header("Location: room.php?id=$room_id");
    exit();
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
    $mode = $_POST['mode'] === 'off' ? 'off' : 'on';
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
    $mode      = $_POST['mode'] === 'off' ? 'off' : 'on';
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
    // ใช้ Anonymous Function แทน Arrow Function เพื่อรองรับ PHP รุ่นเก่า (>= 5.3)
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการห้อง <?= htmlspecialchars($room['room_name']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ปรับพื้นหลังเป็นสีขาว และใช้ font ที่อ่านง่าย */
body {
    background-color: #f8f9fa; /* สีขาวเทาอ่อน */
    font-family: "Segoe UI", Tahoma, sans-serif;
}
/* Header สีน้ำเงินเข้ม */
.header {
    background: #2c3e50; /* Dark Blue */
    color: #fff;
    padding: 14px 0; /* ปรับ padding บน/ล่าง */
}
.header a {
    color: #f1c40f; /* Yellow */
}
/* container หลักให้อยู่ตรงกลาง */
.container-main {
    max-width: 1200px;
    margin: 20px auto;
}
/* การ์ดสวิตช์ให้ดูสะอาดตา */
.switch-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #dee2e6; /* เพิ่มเส้นขอบบางๆ */
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); /* Shadow เล็กน้อย */
}
/* ปรับฟอร์มย่อยให้มีระยะห่าง */
.form-sm input:not([type="checkbox"]), .form-sm select, .form-sm button {
    margin-bottom: 10px;
}
.form-sm input:not([type="checkbox"]), .form-sm select {
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    width: 100%;
}
/* ตาราง Schedule */
.table-sched td, .table-sched th {
    vertical-align: middle;
    font-size: 0.85rem; /* ตัวอักษรเล็กลงในตาราง */
}
/* Toggle Switch สไตล์เดิม แต่ใช้คลาส Bootstrap */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    margin-left: 10px; /* เพิ่มระยะห่างด้านซ้าย */
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
    background-color: #27ae60;
}
input:checked + .slider:before {
    transform: translateX(26px);
}
</style>
</head>
<body>
<header class="header">
    <div class="container-main">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                ห้อง: <?= htmlspecialchars($room['room_name']) ?> (ชั้น <?= htmlspecialchars($room['floor']) ?>)
            </h4>
            <a href="building.php" class="btn btn-warning text-dark">← กลับหน้าหลัก</a>
        </div>
        <hr class="text-light my-2">
        <div id="espStatus" class="d-flex flex-wrap gap-2 mt-2">
            <span class="badge bg-secondary">กำลังโหลดสถานะ ESP...</span>
        </div>
    </div>
</header>

<div class="container-main">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title text-primary">🔌 สวิตช์ที่มี</h5>
                    <?php if (empty($switches)): ?>
                        <p class="text-muted">ยังไม่มีสวิตช์ในห้องนี้</p>
                    <?php else: ?>
                        <?php foreach ($switches as $sw): ?>
                            <div class="switch-card shadow-sm">
                                <div>
                                    <strong class="text-dark"><?= htmlspecialchars($sw['switch_name']) ?></strong><br>
                                    <small class="text-muted">ESP: <?= htmlspecialchars($sw['esp_name']) ?> | GPIO: <?= htmlspecialchars($sw['gpio_pin']) ?></small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a class="btn btn-sm btn-outline-danger me-2" href="room.php?id=<?= $room_id ?>&delete=<?= $sw['id'] ?>" onclick="return confirm('ลบสวิตช์ใช่หรือไม่?')">
                                        ลบ
                                    </a>
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?= $sw['status']==='on'?'checked':'' ?>
                                               onchange="window.location.href='room.php?id=<?= $room_id ?>&toggle=<?= $sw['id'] ?>'">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title text-success">➕ เพิ่มสวิตช์ใหม่</h6>
                    <form method="post" class="form-sm">
                        <input name="switch_name" class="form-control" placeholder="ชื่อสวิตช์" required>
                        <input name="esp_name" class="form-control" placeholder="ESP Name (เช่น ESP32_001)" required>
                        <input name="gpio_pin" type="number" class="form-control" placeholder="GPIO pin" required>
                        <button class="btn btn-primary w-100" name="add_switch">บันทึกสวิตช์</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title text-info">⏰ <?= isset($edit_schedule) ? "แก้ไข Schedule #".$edit_schedule['id'] : "สร้าง Schedule" ?></h6>
                    <form method="post" class="form-sm">
                        <?php if (isset($edit_schedule)): ?>
                            <input type="hidden" name="schedule_id" value="<?= $edit_schedule['id'] ?>">
                        <?php endif; ?>
                        
                        <label class="form-label mb-1">สวิตช์</label>
                        <select name="device_id" class="form-select" required>
                            <?php foreach ($switches as $sw): ?>
                                <option value="switch_<?= $sw['id'] ?>" <?= (isset($edit_schedule) && $edit_schedule['device_id']=="switch_".$sw['id'])?"selected":"" ?>>
                                    <?= htmlspecialchars($sw['switch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mb-1">ESP Name</label>
                        <select name="esp_name" class="form-select" required>
                            <?php foreach ($switches as $sw): ?>
                                <option value="<?= htmlspecialchars($sw['esp_name']) ?>" <?= (isset($edit_schedule) && $edit_schedule['esp_name']==$sw['esp_name'])?"selected":"" ?>>
                                    <?= htmlspecialchars($sw['esp_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label mb-1">โหมด</label>
                        <select name="mode" class="form-select">
                            <option value="on"  <?= (isset($edit_schedule) && $edit_schedule['mode']=='on')?"selected":"" ?>>เปิด</option>
                            <option value="off" <?= (isset($edit_schedule) && $edit_schedule['mode']=='off')?"selected":"" ?>>ปิด</option>
                        </select>

                        <label class="form-label mt-2">วัน (เลือกได้หลายวัน)</label><br>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php
                                $days = ['Mon'=>'จ','Tue'=>'อ','Wed'=>'พ','Thu'=>'พฤ','Fri'=>'ศ','Sat'=>'ส','Sun'=>'อา'];
                                $selected_days = isset($edit_schedule['weekdays']) ? explode(',',$edit_schedule['weekdays']) : [];
                                foreach($days as $k=>$v) {
                                    $checked = in_array($k,$selected_days)?"checked":""; 
                                    echo "<label><input type='checkbox' name='weekdays[]' value='$k' $checked> $v</label>";
                                }
                            ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label mb-1">วันที่เริ่ม</label>
                                <input type="date" name="start_date" class="form-control" value="<?= $edit_schedule['start_date'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1">วันที่จบ</label>
                                <input type="date" name="end_date" class="form-control" value="<?= $edit_schedule['end_date'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label class="form-label mb-1">เวลาเริ่ม</label>
                                <input type="time" name="start_time" class="form-control" required value="<?= $edit_schedule['start_time'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1">เวลาจบ</label>
                                <input type="time" name="end_time" class="form-control" required value="<?= $edit_schedule['end_time'] ?? '' ?>">
                            </div>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="enabled" id="scheduleEnabled" <?= (isset($edit_schedule) && $edit_schedule['enabled'])?"checked":"" ?>>
                            <label class="form-check-label" for="scheduleEnabled">เปิดใช้งาน</label>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-<?= isset($edit_schedule) ? "warning" : "success" ?> flex-grow-1"
                                    name="<?= isset($edit_schedule) ? "update_schedule" : "add_schedule" ?>">
                                <?= isset($edit_schedule) ? "บันทึกการแก้ไข" : "เพิ่ม Schedule" ?>
                            </button>
                            <?php if (isset($edit_schedule)): ?>
                                <a href="room.php?id=<?= $room_id ?>" class="btn btn-secondary">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title text-primary">📊 กราฟประวัติการเปิด/ปิด</h6>
                    
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="id" value="<?= $room_id ?>">
                        <div class="col-auto">
                            <label class="form-label">ช่วงเวลา</label>
                            <select name="range" class="form-select" onchange="this.form.submit()">
                                <option value="1d"  <?= $range==='1d'?'selected':'' ?>>ย้อนหลัง 1 วัน</option>
                                <option value="7d"  <?= $range==='7d'?'selected':'' ?>>ย้อนหลัง 7 วัน</option>
                                <option value="30d" <?= $range==='30d'?'selected':'' ?>>ย้อนหลัง 30 วัน</option>
                                <option value="custom" <?= $range==='custom'?'selected':'' ?>>กำหนดเอง</option>
                            </select>
                        </div>
                        <?php if ($range==='custom'): ?>
                            <div class="col-auto">
                                <label class="form-label">จาก</label>
                                <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">ถึง</label>
                                <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">ตกลง</button>
                            </div>
                        <?php endif; ?>
                    </form>

                    <div class="text-end mb-3">
                        <button id="modeBtn" class="btn btn-sm btn-outline-primary">
                            โหมด: เวลาที่เปิดรวม
                        </button>
                    </div>

                    <canvas id="switchChart" style="width:100%;height:300px"></canvas>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title text-primary">⏱️ สรุประยะเวลาเปิด (<?= htmlspecialchars($range) ?>)</h6>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>สวิตช์</th><th>เวลาเปิดรวม</th></tr></thead>
                        <tbody>
                            <?php foreach ($switches as $sw): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sw['switch_name']) ?></td>
                                    <td><?= formatDuration($onTimes[$sw['id']] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title text-primary">📋 รายการ Schedule</h6>
                    <?php if (count($schedules)===0): ?>
                        <p class="text-muted">ยังไม่มี Schedule</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover table-sched">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Device</th><th>ESP</th><th>Mode</th><th>Weekdays</th>
                                        <th>Date Range</th><th>Time</th><th>Enabled</th><th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($schedules as $sch): ?>
                                        <tr>
                                            <td><?= $sch['id'] ?></td>
                                            <td><?= htmlspecialchars($switch_name_map[$sch['device_id']] ?? $sch['device_id']) ?></td>
                                            <td><?= htmlspecialchars($sch['esp_name']) ?></td>
                                            <td><span class="badge bg-<?= $sch['mode']=='on'?'success':'danger' ?>"><?= strtoupper($sch['mode']) ?></span></td>
                                            <td><?= htmlspecialchars($sch['weekdays']?:'ทุกวัน') ?></td>
                                            <td><?= $sch['start_date'] ?> - <?= $sch['end_date'] ?></td>
                                            <td><?= $sch['start_time'] ?> - <?= $sch['end_time'] ?></td>
                                            <td><span class="badge bg-<?= $sch['enabled'] ? 'success' : 'secondary' ?>"><?= $sch['enabled'] ? 'ใช้งาน' : 'ปิด' ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a class="btn btn-outline-secondary" href="room.php?id=<?= $room_id ?>&toggle_schedule=<?= $sch['id'] ?>">Toggle</a>
                                                    <a class="btn btn-primary" href="room.php?id=<?= $room_id ?>&edit_schedule=<?= $sch['id'] ?>">Edit</a>
                                                    <a class="btn btn-danger" href="room.php?id=<?= $room_id ?>&del_schedule=<?= $sch['id'] ?>" onclick="return confirm('ลบ schedule?')">Delete</a>
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

            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-primary">🖼️ รูปห้อง</h6>
                    <?php
                    $roomImg = null;
                    foreach (['jpg','jpeg','png','gif'] as $ext) {
                        $file = "uploads/room_" . $room_id . "." . $ext;
                        if (file_exists($file)) { $roomImg = $file; break; }
                    }
                    ?>
                    <?php if ($roomImg): ?>
                        <img src="<?= $roomImg ?>?v=<?= time() ?>" class="img-fluid rounded mb-3" style="max-height:350px;object-fit:cover;width:100%;">
                        <a href="room.php?id=<?= $room_id ?>&delete_image=1" class="btn btn-danger btn-sm">ลบรูป</a>
                    <?php else: ?>
                        <p class="text-muted">ยังไม่มีรูปห้อง</p>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="mt-3">
                        <label for="room_image_upload" class="form-label">อัปโหลดรูปห้อง</label>
                        <input type="file" name="room_image" id="room_image_upload" accept="image/*" class="form-control mb-2" required>
                        <button type="submit" name="upload_image" class="btn btn-primary w-100">อัปโหลด / เปลี่ยนรูป</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
const rawLogs = <?= json_encode($logs) ?>;   // log จาก PHP
const switches = <?= json_encode($switches) ?>;
const colors = ["#e74c3c","#3498db","#2ecc71","#f39c12","#9b59b6","#1abc9c"];
// ดึงวันที่ไม่ซ้ำ และเรียงจากเก่าไปใหม่
const allDates = [...new Set(rawLogs.map(l => l.created_at.substr(0,10)))];
allDates.sort(); // เรียงวันที่
const labels = allDates;

// ✅ ฟังก์ชันคำนวณเวลาเปิดรวม (ชั่วโมง/วัน) - ใช้ฟังก์ชันเดิม
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

// ✅ ฟังก์ชันคำนวณจำนวนครั้งเปิด/วัน - ใช้ฟังก์ชันเดิม
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
                stacked: false, // ❌ แก้เป็น FALSE เพื่อให้เป็น Grouped Bar Chart (แท่งติดกัน)
            },
            y: {
                stacked: false, // ❌ แก้เป็น FALSE เพื่อให้เป็น Grouped Bar Chart (แท่งติดกัน)
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
                let color = e.online ? "success" : "danger";
                let text  = e.online ? "เชื่อมต่อแล้ว" : "ตัดการเชื่อมต่อ";
                html += `
                    <span class="badge bg-${color} me-1 p-2">
                        ${e.esp_name} : <strong>${text}</strong>
                        ${e.ip_address ? `<br><small>IP: ${e.ip_address} (${e.ip_mode || ''})</small>` : ''}
                        ${e.gateway ? `<br><small>GW: ${e.gateway}</small>` : ''}
                        ${e.subnet ? `<br><small>Subnet: ${e.subnet}</small>` : ''}

                        <br><small>(${e.last_seen || 'no data'})</small>
                    </span>`;
            });
            document.getElementById("espStatus").innerHTML = html;
        })
        .catch(() => {
            document.getElementById("espStatus").innerHTML = `<span class="badge bg-warning">❌ Error ในการโหลดสถานะ ESP</span>`;
        });
}
// เรียกใช้ทันทีและตั้งเวลาทุก 5 วินาที
refreshESPStatus();
setInterval(refreshESPStatus, 5000);

</script>

</body>
</html>